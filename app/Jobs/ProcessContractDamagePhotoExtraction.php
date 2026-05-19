<?php

namespace App\Jobs;

use App\Core\TenantContext;
use App\Models\CarDamageReport;
use App\Models\ContractHandoverPhoto;
use App\Models\Tenant;
use App\Services\Contracts\ContractDamagePhotoExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessContractDamagePhotoExtraction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 30;

    public function __construct(
        public readonly int $damageReportId
    ) {
    }

    public function handle(ContractDamagePhotoExtractor $extractor): void
    {
        $damageReport = CarDamageReport::withoutTenantScope()->with(['contract.reservation.user', 'contract.reservation.car', 'car', 'reservation'])->find($this->damageReportId);

        if (!$damageReport) {
            return;
        }

        $tenant = Tenant::query()->find((int) $damageReport->tenant_id);
        if (!$tenant) {
            return;
        }

        TenantContext::set($tenant);

        try {
            $damageReport = CarDamageReport::query()
                ->with(['files', 'contract.reservation.user', 'contract.reservation.car', 'car', 'reservation'])
                ->find($this->damageReportId);

            if (!$damageReport) {
                return;
            }

            $state = $this->normalizeState($damageReport->contract?->handover_state);
            $step = data_get($state, 'steps.damage_photo_upload', []);
            $handoverPhotos = ContractHandoverPhoto::withoutTenantScope()
                ->where('damage_report_id', $damageReport->id)
                ->orderBy('id')
                ->get();
            $photos = $this->extractQueuedPhotos($handoverPhotos);
            $persistedPhotos = $this->queuedPhotoPayload($handoverPhotos);

            if ($photos === []) {
                return;
            }

            try {
                $result = $extractor->extractFromPhotoGroups($photos, (string) ($damageReport->report_type ?: 'before_delivery'));
                $items = is_array($result['items'] ?? null) ? $result['items'] : [];
                $vehicleReadings = $this->normalizeVehicleReadings($result['vehicle_readings'] ?? []);

                $this->syncItems($damageReport, $items, (string) ($damageReport->report_type ?: 'before_delivery'));
                $this->syncContractReadings($damageReport, $vehicleReadings);

                if (($result['summary'] ?? null) !== null) {
                    $damageReport->forceFill([
                        'summary' => $result['summary'],
                    ])->saveQuietly();
                }

                $this->updateHandoverState($damageReport, $state, [
                    'extraction_status' => 'extracted',
                    'extraction_error' => null,
                    'extraction_retrying' => false,
                    'summary' => $result['summary'] ?? null,
                    'extracted_fields' => $items,
                    'applied_fields' => $items,
                    'raw_output' => $result['raw_output'] ?? null,
                    'text_preview' => $result['raw_text'] ?? null,
                    'photos' => $persistedPhotos,
                    'stored_photos' => $persistedPhotos,
                    'vehicle_readings' => $vehicleReadings,
                ]);
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if ($this->isDailyLimitError($message)) {
                    $this->markFailed($damageReport, $state, $message, $persistedPhotos, true);
                    return;
                }

                if ($this->isTimeoutError($message)) {
                    $this->markRetrying($damageReport, $state, $persistedPhotos);
                    $this->release($this->retryDelayForAttempt($this->attempts()));
                    return;
                }

                if ($this->isRateLimitError($message)) {
                    $this->markRetrying($damageReport, $state, $persistedPhotos);
                    $this->release($this->retryDelayForAttempt($this->attempts()));
                    return;
                }

                $this->markFailed($damageReport, $state, $message, $persistedPhotos);
            }
        } finally {
            TenantContext::clear();
        }
    }

    private function normalizeState(mixed $state): array
    {
        $state = is_array($state) ? $state : [];
        $state['steps'] = is_array(data_get($state, 'steps')) ? data_get($state, 'steps') : [];
        $state['steps']['damage_photo_upload'] = is_array(data_get($state, 'steps.damage_photo_upload'))
            ? data_get($state, 'steps.damage_photo_upload')
            : [];
        $state['steps']['damage_photo_upload']['payload'] = is_array(data_get($state, 'steps.damage_photo_upload.payload'))
            ? data_get($state, 'steps.damage_photo_upload.payload')
            : [];

        return $state;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<int, array{view_side: string, photo_type: string, file_paths: array<int, string>}>
     */
    /**
     * @param  iterable<int, ContractHandoverPhoto>  $handoverPhotos
     * @return array<int, array{view_side: string, photo_type: string, file_paths: array<int, string>}>
     */
    private function extractQueuedPhotos(iterable $handoverPhotos): array
    {
        $prepared = [];

        foreach ($handoverPhotos as $photo) {
            if ($photo->photo_type !== 'damage') {
                continue;
            }

            $filePaths = array_values(array_filter([
                trim((string) $photo->file_path),
            ], static fn (string $path): bool => $path !== ''));

            if ($filePaths === []) {
                continue;
            }

            $prepared[] = [
                'view_side' => (string) ($photo->view_side ?? 'front'),
                'photo_type' => (string) $photo->photo_type,
                'file_paths' => $filePaths,
            ];
        }

        return $prepared;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(CarDamageReport $damageReport, array $items, string $reportType): void
    {
        $damageReport->items()->delete();

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $damageReport->items()->create([
                'tenant_id' => $damageReport->tenant_id,
                'zone_code' => (string) ($item['zone_code'] ?? ''),
                'view_side' => (string) ($item['view_side'] ?? 'front'),
                'damage_type' => (string) ($item['damage_type'] ?? 'other'),
                'severity' => (string) ($item['severity'] ?? 'minor'),
                'damage_timing' => (string) ($item['damage_timing'] ?? ($reportType === 'after_return' ? 'after_return' : 'before_pickup')),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'marker_x' => $item['marker_x'] ?? null,
                'marker_y' => $item['marker_y'] ?? null,
                'estimated_cost' => $item['estimated_cost'] ?? null,
                'notes' => $item['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    private function syncContractReadings(CarDamageReport $damageReport, array $vehicleReadings): void
    {
        $contract = $damageReport->contract;
        if (!$contract) {
            return;
        }

        $updates = [];

        if (array_key_exists('vehicle_odometer', $vehicleReadings) && $vehicleReadings['vehicle_odometer'] !== null) {
            $updates['vehicle_odometer'] = (int) $vehicleReadings['vehicle_odometer'];
        }

        if (array_key_exists('vehicle_fuel_level', $vehicleReadings) && $vehicleReadings['vehicle_fuel_level'] !== null) {
            $updates['vehicle_fuel_level'] = $vehicleReadings['vehicle_fuel_level'];
        }

        if ($updates !== []) {
            $contract->forceFill($updates)->saveQuietly();
        }
    }

    /**
     * @param  iterable<int, ContractHandoverPhoto>  $handoverPhotos
     * @return array<int, array<string, mixed>>
     */
    private function queuedPhotoPayload(iterable $handoverPhotos): array
    {
        $photos = [];

        foreach ($handoverPhotos as $photo) {
            $photos[] = [
                'id' => $photo->id,
                'source' => 'handover_photo',
                'phase' => $photo->phase,
                'photo_type' => $photo->photo_type,
                'view_side' => $photo->view_side,
                'title' => $photo->title,
                'notes' => $photo->notes,
                'file_path' => $photo->file_path,
                'file_paths' => [$photo->file_path],
                'file_name' => $photo->file_name,
                'mime_type' => $photo->mime_type,
                'damage_report_id' => $photo->damage_report_id,
                'storage_target' => 'handover_archive',
                'extraction_status' => $photo->extraction_status,
            ];
        }

        return $photos;
    }

    private function updateHandoverState(CarDamageReport $damageReport, array $state, array $payload): void
    {
        $incomingVehicleReadings = data_get($payload, 'vehicle_readings');
        $hasIncomingVehicleReadings = is_array($incomingVehicleReadings) && array_filter(
            $incomingVehicleReadings,
            static fn ($value): bool => $value !== null && $value !== ''
        ) !== [];

        $state['steps']['damage_photo_upload']['payload'] = array_merge(
            is_array(data_get($state, 'steps.damage_photo_upload.payload')) ? data_get($state, 'steps.damage_photo_upload.payload') : [],
            $payload
        );

        if ($hasIncomingVehicleReadings) {
            $state['steps']['vehicle_readings']['payload'] = array_merge(
                is_array(data_get($state, 'steps.vehicle_readings.payload')) ? data_get($state, 'steps.vehicle_readings.payload') : [],
                [
                    'vehicle_odometer' => data_get($payload, 'vehicle_readings.vehicle_odometer'),
                    'vehicle_fuel_level' => data_get($payload, 'vehicle_readings.vehicle_fuel_level'),
                ]
            );
        }

        $damageReport->contract?->forceFill([
            'handover_state' => $state,
        ])->saveQuietly();
    }

    private function markFailed(CarDamageReport $damageReport, array $state, string $message, array $photos, bool $retrying = false): void
    {
        $this->updateHandoverState($damageReport, $state, [
            'extraction_status' => 'failed',
            'extraction_error' => $message,
            'extraction_retrying' => $retrying,
            'photos' => $photos,
            'stored_photos' => $photos,
        ]);
    }

    private function markRetrying(CarDamageReport $damageReport, array $state, array $photos): void
    {
        $this->updateHandoverState($damageReport, $state, [
            'extraction_status' => 'retrying',
            'extraction_error' => null,
            'extraction_retrying' => true,
            'photos' => $photos,
            'stored_photos' => $photos,
        ]);
    }

    private function isDailyLimitError(string $message): bool
    {
        return str_contains(strtolower($message), 'daily ai document extraction limit');
    }

    private function isRateLimitError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'rate limit') || str_contains($message, 'too many requests');
    }

    private function isTimeoutError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'curl error 28')
            || str_contains($message, 'operation timed out')
            || str_contains($message, 'timeout');
    }

    private function normalizeVehicleReadings(mixed $vehicleReadings): array
    {
        $vehicleReadings = is_array($vehicleReadings) ? $vehicleReadings : [];

        return [
            'vehicle_odometer' => isset($vehicleReadings['vehicle_odometer']) && is_numeric($vehicleReadings['vehicle_odometer'])
                ? (int) $vehicleReadings['vehicle_odometer']
                : null,
            'vehicle_fuel_level' => $this->nullableString($vehicleReadings['vehicle_fuel_level'] ?? null),
            'odometer_confidence' => isset($vehicleReadings['odometer_confidence']) && is_numeric($vehicleReadings['odometer_confidence'])
                ? (float) $vehicleReadings['odometer_confidence']
                : null,
            'fuel_level_confidence' => isset($vehicleReadings['fuel_level_confidence']) && is_numeric($vehicleReadings['fuel_level_confidence'])
                ? (float) $vehicleReadings['fuel_level_confidence']
                : null,
        ];
    }

    private function retryDelayForAttempt(int $attempt): int
    {
        return min(300, max(30, $attempt * 30));
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
