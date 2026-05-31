<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContractStatus;
use App\Enums\CarStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Core\ReservationSettings;
use App\Core\AiAutomationSettings;
use App\Jobs\ProcessContractHandoverExtraction;
use App\Jobs\ProcessContractDamagePhotoExtraction;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\CarDamageItem;
use App\Models\ContractReturnReport;
use App\Models\Contract;
use App\Models\ContractArchiveFile;
use App\Models\ContractHandoverPhoto;
use App\Models\ContractDriver;
use App\Models\ContractDriverDocument;
use App\Models\CarDamageReport;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\CarDamageCatalog;
use App\Services\Contracts\ContractDamagePhotoExtractor;
use App\Services\Contracts\ContractDriverDocumentExtractor;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use MohamedGaldi\ViltFilepond\Models\TempFile;

class ContractsController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly ContractDriverDocumentExtractor $contractDriverDocumentExtractor,
        private readonly ContractDamagePhotoExtractor $contractDamagePhotoExtractor,
        private readonly FilePondService $filePondService
    ) {
    }

    public function damageOptions(Request $request): JsonResponse
    {
        $this->authorizeAdminApiUser($request);
        $locale = $this->resolveApiLocale($request);

        return response()->json([
            'locale' => $locale,
            'data' => $this->damageOptionsPayload($locale),
        ]);
    }

    public function damageOptionGroup(Request $request, string $group): JsonResponse
    {
        $this->authorizeAdminApiUser($request);
        $locale = $this->resolveApiLocale($request);
        $options = $this->damageOptionsPayload($locale);
        $normalizedGroup = str_replace('-', '_', trim(strtolower($group)));

        abort_unless(array_key_exists($normalizedGroup, $options), 404, 'Damage option group not found.');

        return response()->json([
            'locale' => $locale,
            'group' => $normalizedGroup,
            'data' => $options[$normalizedGroup],
        ]);
    }

    public function documents(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);

        $contract->loadMissing([
            'reservation.car:id,branch_id,year,make,model,license_plate,status',
            'branch:id,name',
            'drivers.documents',
            'archiveFiles.driver',
            'handoverPhotos',
        ]);

        abort_unless($this->canAccessContract($contract, $user), 403);

        return response()->json([
            'contract' => $this->contractPayload($contract),
            'drivers' => $contract->drivers
                ->sortBy('sort_order')
                ->sortBy(fn (ContractDriver $driver) => $driver->role === 'primary' ? 0 : 1)
                ->values()
                ->map(fn (ContractDriver $driver) => $this->driverPayload($driver))
                ->all(),
            'archive_files' => $contract->archiveFiles
                ->sortBy('id')
                ->values()
                ->map(fn (ContractArchiveFile $file) => $this->archiveFilePayload($file))
                ->all(),
            'documents' => $this->flattenDocuments($contract),
        ]);
    }

    public function damageReportStatus(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $phase = $this->normalizeHandoverPhase($request->input('phase', $request->query('phase', 'return')));
        $requestedReportType = $request->input('report_type', $request->query('report_type'));
        $requestedReportType = is_string($requestedReportType)
            ? str_replace('-', '_', strtolower(trim($requestedReportType)))
            : null;
        $reportType = $requestedReportType ?: ($phase === 'return' ? 'after_return' : 'before_delivery');

        abort_unless(in_array($reportType, ['before_delivery', 'after_return'], true), 422, 'The selected damage report type is invalid.');

        $contract->loadMissing([
            'reservation.user:id,name,email,is_active',
            'reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
            'reservation.car.branch:id,name',
            'branch:id,name',
            'returnStatusReport',
            'damageReports.files',
            'damageReports.items',
        ]);

        abort_unless($this->canAccessContract($contract, $user), 403);

        if ($reportType === 'after_return') {
            $state = $this->normalizeReturnHandoverState($contract->handover_state);
            $stepPayload = is_array(data_get($state, 'steps.return_inspection.payload'))
                ? data_get($state, 'steps.return_inspection.payload')
                : [];
        } else {
            $state = $this->normalizeHandoverState($contract->handover_state);
            $stepPayload = is_array(data_get($state, 'steps.damage_photo_upload.payload'))
                ? data_get($state, 'steps.damage_photo_upload.payload')
                : [];
            $deliveryConfirmationPayload = is_array(data_get($state, 'steps.delivery_confirmation.payload'))
                ? data_get($state, 'steps.delivery_confirmation.payload')
                : [];

            if (array_key_exists('has_damage', $deliveryConfirmationPayload)) {
                $stepPayload['has_damage'] = $deliveryConfirmationPayload['has_damage'];
            }
        }

        $damageReport = $contract->damageReports
            ->filter(static fn (CarDamageReport $report): bool => $report->report_type === $reportType)
            ->sortByDesc('id')
            ->first();

        $hasDamage = data_get($stepPayload, 'has_damage');
        if ($reportType === 'after_return' && $hasDamage === null && $contract->returnStatusReport) {
            $hasDamage = $contract->returnStatusReport->has_damage;
        }
        $hasDamage = $hasDamage !== null
            ? filter_var($hasDamage, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $hasDamage = $hasDamage ?? true;

        if (!$hasDamage) {
            return response()->json([
                'phase' => $phase,
                'damage_report_status' => 'skipped',
                'damage_report' => null,
                'damage_report_id' => null,
                'damage_report_number' => null,
                'damage_report_type' => $reportType,
            ]);
        }

        $extractionStatus = strtolower((string) ($stepPayload['extraction_status'] ?? ''));
        $hasExtractedItems = $damageReport?->relationLoaded('items')
            ? $damageReport->items->isNotEmpty()
            : ($damageReport?->items()->exists() ?? false);

        $status = match (true) {
            $extractionStatus === 'extracted' || $hasExtractedItems => 'done',
            $extractionStatus === 'retrying' => 'retrying',
            $extractionStatus === 'failed' => 'failed',
            default => 'pending',
        };

        return response()->json([
            'phase' => $phase,
            'damage_report_status' => $status,
            'damage_report' => $status === 'done' && $damageReport
                ? $this->damageReportPayload($damageReport->fresh(['files', 'items']))
                : null,
            'damage_report_id' => $damageReport?->id,
            'damage_report_number' => $damageReport?->report_number,
            'damage_report_type' => $damageReport?->report_type,
        ]);
    }

    public function handover(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $locale = $this->resolveApiLocale($request);
        $phase = $this->normalizeHandoverPhase($request->input('phase', $request->query('phase', 'delivery')));

        $reservation->loadMissing([
            'user:id,name,email,is_active',
            'car:id,branch_id,year,make,model,license_plate,status,mileage',
            'car.branch:id,name',
            'contract.branch:id,name',
            'contract.handoverPhotos',
            'contract.reservation.user:id,name,email,is_active',
            'contract.reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
            'contract.reservation.car.branch:id,name',
        ]);

        if ($phase === 'return') {
            $contract = $reservation->contract;
            abort_unless($contract, 404, 'Contract not found for this reservation.');
        } else {
            $contract = $reservation->contract ?: $this->createDraftContractForReservation($reservation);
        }

        $reservation->setRelation('contract', $contract);
        abort_unless($this->canAccessContract($contract, $user), 403);
        if ($phase === 'delivery') {
            $this->retryQueuedExtractionIfNeeded($contract);
        }
        $contract->refresh();

        return response()->json([
            'reservation' => $this->reservationPayload($reservation),
            'contract' => $this->contractPayload($contract->loadMissing([
                'reservation.user:id,name,email,is_active',
                'reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
                'reservation.car.branch:id,name',
                'branch:id,name',
            ]), $locale),
            'phase' => $phase,
            'phase_label' => $this->handoverPhaseLabel($phase),
            'handover' => $phase === 'return'
                ? $this->returnHandoverPayload($contract, $locale)
                : $this->handoverPayload($contract, $locale),
        ]);
    }

    private function retryQueuedExtractionIfNeeded(Contract $contract): void
    {
        $state = $this->normalizeHandoverState($contract->handover_state);
        $payload = data_get($state, 'steps.report_upload.payload', []);
        $payload = is_array($payload) ? $payload : [];

        $status = (string) ($payload['extraction_status'] ?? '');
        $error = strtolower((string) ($payload['extraction_error'] ?? ''));
        $hasDocuments = !empty(data_get($payload, 'documents', []));

        if (!$hasDocuments) {
            return;
        }

        if ($status === 'retrying') {
            return;
        }

        if ($status !== 'failed' || !str_contains($error, 'rate limit')) {
            return;
        }

        $payload['extraction_status'] = 'retrying';
        $payload['extraction_error'] = null;
        $payload['extraction_retrying'] = true;

        $state['steps']['report_upload']['payload'] = $payload;

        $contract->forceFill([
            'ai_extraction_status' => 'pending',
            'handover_state' => $state,
        ])->saveQuietly();

        ProcessContractHandoverExtraction::dispatch($contract->id);
    }

    public function updateHandover(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $locale = $this->resolveApiLocale($request);
        $phase = $this->normalizeHandoverPhase($request->input('phase', $request->query('phase', 'delivery')));

        $contract->loadMissing([
            'reservation.user:id,name,email,is_active',
            'reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
            'reservation.car.branch:id,name',
            'branch:id,name',
            'handoverPhotos',
        ]);

        abort_unless($this->canAccessContract($contract, $user), 403);

        $page = (int) $request->integer('page');
        if ($phase === 'return') {
            abort_unless($page >= 1 && $page <= 6, 422, 'The page field is required.');
        } else {
            abort_unless($page >= 1 && $page <= 7, 422, 'The page field is required.');
        }

        $payload = $request->input('payload');
        if (is_string($payload)) {
            $decodedPayload = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPayload)) {
                $payload = $decodedPayload;
            }
        }

        if (!is_array($payload)) {
            if ($phase === 'return') {
                $payload = match ($page) {
                    1 => [
                        'reviewed' => $request->input('reviewed'),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    2 => [
                        'photos' => $request->input('photos', []),
                        'temp_folders' => $request->input('temp_folders', []),
                        'files' => $request->file('files', []),
                        'vehicle_odometer' => $request->input('vehicle_odometer'),
                        'vehicle_fuel_level' => $request->input('vehicle_fuel_level'),
                        'return_odometer' => $request->input('return_odometer'),
                        'return_fuel_level' => $request->input('return_fuel_level'),
                        'reviewed' => $request->boolean('reviewed', false),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    3 => [
                        'photos' => $request->input('photos', []),
                        'temp_folders' => $request->input('temp_folders', []),
                        'files' => $request->file('files', []),
                        'view_side' => $request->input('view_side'),
                        'photo_type' => $request->input('photo_type'),
                        'return_odometer' => $request->input('return_odometer'),
                        'return_fuel_level' => $request->input('return_fuel_level'),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    4 => [
                        'damage_report_id' => $request->input('damage_report_id'),
                        'summary' => $request->input('summary'),
                        'has_damage' => $request->boolean('has_damage', true),
                        'discount' => $request->input('discount'),
                        'items' => $request->has('items') ? $request->input('items') : null,
                        'deleted_item_ids' => $request->has('deleted_item_ids') ? $request->input('deleted_item_ids') : null,
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    5 => [
                        'payment_status' => $request->input('payment_status'),
                        'discount' => $request->input('discount'),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    6 => [
                        'return_confirmed' => $request->boolean('return_confirmed', false),
                        'payment_status' => $request->input('payment_status'),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    default => [],
                };
            } else {
                $payload = match ($page) {
                    1 => [
                        'reviewed' => $request->input('reviewed'),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    2 => [
                        'document_type' => $request->input('document_type'),
                        'documents' => $request->input('documents', []),
                        'temp_folders' => $request->input('temp_folders', []),
                        'files' => $request->file('files', []),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    3 => [
                        'report_type' => $request->input('report_type'),
                        'photos' => $request->input('photos', []),
                        'temp_folders' => $request->input('temp_folders', []),
                        'files' => $request->file('files', []),
                        'photo_type' => $request->input('photo_type'),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    4 => [
                        'vehicle_odometer' => $request->input('vehicle_odometer'),
                        'vehicle_fuel_level' => $request->input('vehicle_fuel_level'),
                        'reviewed' => $request->boolean('reviewed', false),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    5 => [
                        'accepted_terms' => $request->boolean('accepted_terms', false),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    6 => [
                        'damage_report_id' => $request->input('damage_report_id'),
                        'summary' => $request->input('summary'),
                        'has_damage' => $request->has('has_damage') ? $request->boolean('has_damage') : null,
                        'items' => $request->input('items', []),
                        'deleted_item_ids' => $request->input('deleted_item_ids', []),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    7 => [
                        'delivery_confirmed' => $request->boolean('delivery_confirmed', false),
                        'note' => $request->input('note'),
                        'notes' => $request->input('notes'),
                    ],
                    default => [],
                };
            }
        }

        if ($phase === 'delivery' && $page === 2) {
            $payload = $this->normalizeHandoverPageTwoPayload($request, $payload);
        }

        if ($phase === 'delivery' && $page === 3) {
            $payload = $this->normalizeHandoverPageThreePayload($request, $payload);
        }

        if ($phase === 'delivery' && $page === 4) {
            $payload = $this->normalizeHandoverPageFourPayload($request, $payload);
        }

        if ($phase === 'delivery' && $page === 5) {
            $payload = $this->normalizeHandoverPageFivePayload($request, $payload);
        }

        if ($phase === 'delivery' && $page === 6) {
            $payload = $this->normalizeHandoverPageSixPayload($request, $payload);
        }

        if ($phase === 'delivery' && $page === 7) {
            $payload = $this->normalizeHandoverPageSevenPayload($request, $payload);
        }

        if ($phase === 'return') {
            return $this->updateReturnHandover($request, $contract, $locale, $page, $payload);
        }

        $step = $this->handoverStepForPage($page);
        $stepPayload = $this->validateHandoverStepPayload($page, $payload);
        $extraction = null;
        $appliedFields = [];

        if ($page === 1) {
            $contractNote = array_key_exists('notes', $stepPayload)
                ? $stepPayload['notes']
                : (array_key_exists('note', $stepPayload) ? $stepPayload['note'] : $contract->notes);
            $contractNote = $this->nullableString($contractNote);

            if ($contractNote !== $contract->notes) {
                $contract->forceFill([
                    'notes' => $contractNote,
                ])->saveQuietly();
            }

            $stepPayload = array_merge($stepPayload, [
                'note' => $contractNote,
                'notes' => $contractNote,
            ]);
        }

        if ($page === 2) {
            $documents = $this->prepareHandoverDocumentsForExtraction($request, $stepPayload);
            $primaryDriver = $this->ensurePrimaryDriverExists($contract);
            $persistedDocuments = $this->persistHandoverDocumentFiles($contract, $documents, $primaryDriver);

            $contract->forceFill([
                'ai_extraction_status' => 'pending',
                'ai_extracted_data' => null,
            ])->saveQuietly();

            $stepPayload = array_merge($stepPayload, [
                'documents' => $persistedDocuments,
                'stored_documents' => $persistedDocuments,
                'document_types' => array_values(array_unique(array_map(
                    static fn (array $document): string => (string) ($document['document_type'] ?? 'other'),
                    $persistedDocuments
                ))),
                'extraction_status' => 'pending',
                'extraction_error' => null,
                'extraction_retrying' => false,
                'extracted_fields' => null,
                'applied_fields' => $appliedFields,
                'raw_output' => null,
                'text_preview' => null,
                'extracted_documents' => [],
            ]);

            ProcessContractHandoverExtraction::dispatch($contract->id);
        } elseif ($page === 3) {
            $photos = $this->prepareHandoverInspectionPhotosForExtraction($request, $stepPayload);
            $damageReport = $this->resolveOrCreateDraftDamageReportForHandover($contract, $request);
            $vehicleReadingPhotos = $this->prepareHandoverVehicleReadingPhotosForExtraction($photos);
            $vehicleReadings = $this->vehicleReadingsPayload($contract);

            if ($vehicleReadingPhotos !== []) {
                try {
                    $vehicleExtraction = $this->contractDamagePhotoExtractor->extractFromPhotoGroups(
                        $vehicleReadingPhotos,
                        (string) ($damageReport->report_type ?: 'before_delivery')
                    );

                    $vehicleReadings = $this->normalizeVehicleReadingsPayload($vehicleExtraction['vehicle_readings'] ?? []);
                    $contract->forceFill([
                        'vehicle_odometer' => $vehicleReadings['vehicle_odometer'],
                        'vehicle_fuel_level' => $this->nullableString($vehicleReadings['vehicle_fuel_level'] ?? null),
                    ])->saveQuietly();
                } catch (\Throwable $e) {
                    $vehicleReadings = $this->vehicleReadingsPayload($contract);
                }
            }

            $persistedPhotos = $this->persistHandoverHandoverPhotos(
                $contract,
                $damageReport,
                $photos,
                $damageReport->report_type === 'after_return' ? 'return' : 'delivery'
            );

            $stepPayload = array_merge($stepPayload, [
                'damage_report_id' => $damageReport->id,
                'damage_report_number' => $damageReport->report_number,
                'damage_report_status' => $damageReport->status,
                'damage_report_type' => $damageReport->report_type,
                'photos' => $persistedPhotos,
                'stored_photos' => $persistedPhotos,
                'handover_photos' => $persistedPhotos,
                'extraction_status' => 'pending',
                'extraction_error' => null,
                'extraction_retrying' => false,
                'extracted_fields' => null,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'vehicle_readings' => [
                    'vehicle_odometer' => $vehicleReadings['vehicle_odometer'],
                    'vehicle_fuel_level' => $vehicleReadings['vehicle_fuel_level'],
                ],
            ]);

            if (array_filter($persistedPhotos, static fn (array $photo): bool => (string) ($photo['photo_type'] ?? 'damage') === 'damage') !== []) {
                ProcessContractDamagePhotoExtraction::dispatch($damageReport->id);
            }

            $extraction = [
                'status' => 'processing',
                'message' => $vehicleReadingPhotos !== []
                    ? 'Vehicle readings extracted. Damage photo extraction has been queued.'
                    : 'Damage photo extraction has been queued.',
                'fields' => null,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'damage_report' => $this->damageReportPayload($damageReport->fresh(['files', 'items'])),
                'vehicle_readings' => $vehicleReadings,
                'handover_photos' => $persistedPhotos,
            ];
        } elseif ($page === 4) {
            $vehicleOdometer = array_key_exists('vehicle_odometer', $stepPayload)
                ? $stepPayload['vehicle_odometer']
                : $contract->vehicle_odometer;
            $vehicleFuelLevel = array_key_exists('vehicle_fuel_level', $stepPayload)
                ? $stepPayload['vehicle_fuel_level']
                : $contract->vehicle_fuel_level;
            $contractNote = array_key_exists('notes', $stepPayload)
                ? $stepPayload['notes']
                : (array_key_exists('note', $stepPayload) ? $stepPayload['note'] : $contract->notes);

            $vehicleOdometer = $vehicleOdometer !== null && $vehicleOdometer !== ''
                ? (int) $vehicleOdometer
                : null;
            $vehicleFuelLevel = $this->nullableString($vehicleFuelLevel ?? null);
            $contractNote = $this->nullableString($contractNote);

            DB::transaction(function () use ($contract, $vehicleOdometer, $vehicleFuelLevel, $contractNote): void {
                $contract->forceFill([
                    'vehicle_odometer' => $vehicleOdometer,
                    'vehicle_fuel_level' => $vehicleFuelLevel,
                    'notes' => $contractNote,
                ])->saveQuietly();

                $car = $contract->reservation?->car;
                if ($car && $vehicleOdometer !== null) {
                    $currentMileage = (int) ($car->mileage ?? 0);
                    $car->forceFill([
                        'mileage' => max($currentMileage, $vehicleOdometer),
                    ])->saveQuietly();
                }
            });

            $stepPayload = array_merge($stepPayload, [
                'vehicle_odometer' => $contract->vehicle_odometer,
                'vehicle_fuel_level' => $contract->vehicle_fuel_level,
                'notes' => $contract->notes,
                'reviewed' => (bool) ($stepPayload['reviewed'] ?? false),
                'vehicle_readings' => $this->vehicleReadingsPayload($contract),
                'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
            ]);
        } elseif ($page === 5) {
            $acceptedTerms = (bool) ($stepPayload['accepted_terms'] ?? false);

            $stepPayload = array_merge($stepPayload, [
                'accepted_terms' => $acceptedTerms,
                'accepted_at' => $acceptedTerms ? now()->toIso8601String() : null,
                'mobile_signature_text' => $this->mobileSignatureTextForContract($contract),
            ]);
        } elseif ($page === 6) {
            $hasDamage = array_key_exists('has_damage', $stepPayload) && $stepPayload['has_damage'] !== null
                ? filter_var($stepPayload['has_damage'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;
            $hasDamage = $hasDamage ?? true;

            $damageReport = null;
            if ($hasDamage) {
                $damageReport = $this->resolveOrCreateDraftDamageReportForHandover($contract, $request, 'delivery', 'before_delivery');
            } else {
                $this->deleteDraftDamageReportForHandover($contract, 'before_delivery');
            }

            $summary = array_key_exists('summary', $stepPayload)
                ? $this->nullableString($stepPayload['summary'] ?? null)
                : ($damageReport?->summary);

            $normalizedItems = null;
            if ($hasDamage && array_key_exists('items', $stepPayload) && is_array($stepPayload['items'])) {
                $normalizedItems = [];

                foreach (array_values($stepPayload['items']) as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $normalizedItems[] = [
                        'id' => isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null,
                        'zone_code' => (string) ($item['zone_code'] ?? ''),
                        'view_side' => (string) ($item['view_side'] ?? 'front'),
                        'damage_type' => (string) ($item['damage_type'] ?? 'scratch'),
                        'severity' => (string) ($item['severity'] ?? 'minor'),
                        'damage_timing' => (string) ($item['damage_timing'] ?? 'before_pickup'),
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'marker_x' => $item['marker_x'] ?? null,
                        'marker_y' => $item['marker_y'] ?? null,
                        'estimated_cost' => $item['estimated_cost'] ?? null,
                        'notes' => $item['notes'] ?? null,
                        'sort_order' => $index,
                    ];
                }
            }

            $deletedItemIds = array_values(array_unique(array_filter(array_map(
                static fn ($value): int => is_numeric($value) ? (int) $value : 0,
                (array) ($stepPayload['deleted_item_ids'] ?? [])
            ))));

            DB::transaction(function () use ($contract, $damageReport, $summary, $normalizedItems, $deletedItemIds, $request): void {
                if ($damageReport) {
                    $damageReport->forceFill([
                        'status' => 'draft',
                        'summary' => $summary,
                    ])->saveQuietly();

                    if (is_array($normalizedItems)) {
                        $this->syncItems($damageReport, $normalizedItems, $deletedItemIds);

                        if ($contract->reservation?->car && $contract->reservation) {
                            $this->syncDamageCases(
                                $damageReport,
                                $normalizedItems,
                                $contract->reservation->car,
                                $contract,
                                $contract->reservation,
                                $request->user()?->id
                            );
                        }
                    }
                }
            });

            $damageReport = $hasDamage && $damageReport
                ? $damageReport->fresh(['files', 'items'])
                : null;

            $stepPayload = array_merge($stepPayload, [
                'has_damage' => $hasDamage,
                'damage_report_id' => $damageReport?->id,
                'damage_report_number' => $damageReport?->report_number,
                'damage_report_status' => $hasDamage ? ($damageReport?->status ?? 'pending') : 'skipped',
                'damage_report_type' => $hasDamage ? $damageReport?->report_type : null,
                'damage_report' => $damageReport ? $this->damageReportPayload($damageReport) : null,
                'contract_inputs' => $this->contractInputsPayload($contract),
                'vehicle_readings' => $this->vehicleReadingsPayload($contract),
                'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
            ]);
        } elseif ($page === 7) {
            $deliveryConfirmed = (bool) ($stepPayload['delivery_confirmed'] ?? false);

            if ($deliveryConfirmed) {
                DB::transaction(function () use ($contract): void {
                    $contract->forceFill([
                        'status' => ContractStatus::ACTIVE->value,
                    ])->saveQuietly();

                    if ($contract->reservation) {
                        $contract->reservation->forceFill([
                            'status' => ReservationStatus::ACTIVE->value,
                        ])->saveQuietly();
                    }

                    if ($contract->reservation?->car) {
                        $contract->reservation->car->forceFill([
                            'status' => CarStatus::RENTED->value,
                        ])->saveQuietly();
                    }
                });
            }

            $contract->refresh()->loadMissing([
                'tenant:id,slug',
                'reservation.user:id,name,email,is_active',
                'reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
                'reservation.car.branch:id,name',
            ]);

            $stepPayload = array_merge($stepPayload, [
                'delivery_confirmed' => $deliveryConfirmed,
                'delivered_at' => $deliveryConfirmed ? now()->toIso8601String() : null,
                'contract_file' => $this->contractFilePayload($contract, $locale),
                'contract_status' => $contract->status instanceof ContractStatus
                    ? $contract->status->value
                    : (string) $contract->status,
                'reservation_status' => $contract->reservation?->status instanceof ReservationStatus
                    ? $contract->reservation->status->value
                    : (string) ($contract->reservation?->status ?? ''),
                'car_status' => $contract->reservation?->car?->status instanceof CarStatus
                    ? $contract->reservation->car->status->value
                    : (string) ($contract->reservation?->car?->status ?? ''),
                'reservation' => $this->reservationPayload($contract->reservation),
                'contract_inputs' => $this->contractInputsPayload($contract),
                'vehicle_readings' => $this->vehicleReadingsPayload($contract),
                'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
            ]);
        }

        $state = $this->normalizeHandoverState($contract->handover_state);
        $state['current_page'] = max($state['current_page'], min($page + 1, 7));
        $state['completed_pages'] = array_values(array_unique(array_merge($state['completed_pages'], [$page])));
        $state['steps'][$step['key']] = [
            'page' => $page,
            'key' => $step['key'],
            'label' => $step['label'],
            'completed' => true,
            'completed_at' => now()->toIso8601String(),
            'payload' => $stepPayload,
        ];

        $contract->forceFill([
            'handover_state' => $state,
        ])->saveQuietly();
        if ($page === 2) {
            $extraction = [
                'status' => 'pending',
                'message' => 'Document extraction has been queued.',
                'fields' => null,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'documents' => $persistedDocuments ?? [],
            ];
        }

        $contract->refresh()->loadMissing([
            'reservation.user:id,name,email,is_active',
            'reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
            'reservation.car.branch:id,name',
            'branch:id,name',
        ]);

        return response()->json([
            'message' => 'Handover step saved successfully.',
            'reservation' => $this->reservationPayload($contract->reservation),
            'contract' => $this->contractPayload($contract, $locale),
            'handover' => $this->handoverPayload($contract, $locale),
            'extraction' => $extraction ? [
                'status' => $extraction['status'] ?? 'pending',
                'message' => $extraction['message'] ?? null,
                'fields' => $extraction['fields'] ?? null,
                'applied_fields' => $appliedFields,
                'raw_output' => $extraction['raw_output'] ?? null,
                'text_preview' => $extraction['text_preview'] ?? null,
                'damage_report' => $extraction['damage_report'] ?? null,
                'vehicle_readings' => $extraction['vehicle_readings'] ?? null,
            ] : null,
        ]);
    }

    private function updateReturnHandover(Request $request, Contract $contract, string $locale, int $page, array $payload): JsonResponse
    {
        if ($page === 2) {
            $payload = $this->normalizeReturnHandoverPageTwoPayload($request, $payload);
        } elseif ($page === 3) {
            $payload = $this->normalizeReturnHandoverPageThreePayload($request, $payload);
        } elseif ($page !== 1 && $page !== 4 && $page !== 5 && $page !== 6) {
            throw ValidationException::withMessages([
                'page' => ['This return handover page is not implemented yet. Use page 1, 2, 3, 4, 5 or 6.'],
            ]);
        }

        $step = $this->returnHandoverStepForPage($page);
        $stepPayload = $this->validateReturnHandoverStepPayload($page, $payload);
        $extraction = null;

        if ($page === 2) {
            $photos = $this->prepareHandoverInspectionPhotosForExtraction($request, $stepPayload);
            $vehicleReadingPhotos = $this->prepareHandoverVehicleReadingPhotosForExtraction($photos);
            $damageReport = $this->resolveOrCreateDraftDamageReportForHandover($contract, $request, 'return', 'after_return');

            $returnReadings = [
                'return_odometer' => $contract->return_odometer,
                'return_fuel_level' => $this->nullableString($contract->return_fuel_level ?? null),
                'odometer_confidence' => null,
                'fuel_level_confidence' => null,
            ];

            if ($vehicleReadingPhotos !== []) {
                try {
                    $vehicleExtraction = $this->contractDamagePhotoExtractor->extractFromPhotoGroups(
                        $vehicleReadingPhotos,
                        'after_return'
                    );

                    $returnReadings = $this->normalizeReturnVehicleReadingsPayload($vehicleExtraction['vehicle_readings'] ?? []);
                } catch (\Throwable $e) {
                    $returnReadings = $returnReadings;
                }
            }

            $persistedPhotos = $this->persistHandoverHandoverPhotos(
                $contract,
                $damageReport,
                $photos,
                'return'
            );

            $stepPayload = array_merge($stepPayload, [
                'damage_report_id' => $damageReport->id,
                'damage_report_number' => $damageReport->report_number,
                'damage_report_status' => $damageReport->status,
                'damage_report_type' => $damageReport->report_type,
                'photos' => $persistedPhotos,
                'stored_photos' => $persistedPhotos,
                'handover_photos' => $persistedPhotos,
                'extraction_status' => 'pending',
                'extraction_error' => null,
                'extraction_retrying' => false,
                'extracted_fields' => null,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'vehicle_readings' => $returnReadings,
                'return_odometer' => $returnReadings['return_odometer'],
                'return_fuel_level' => $returnReadings['return_fuel_level'],
            ]);

            if (array_filter($persistedPhotos, static fn (array $photo): bool => (string) ($photo['photo_type'] ?? 'damage') === 'damage') !== []) {
                ProcessContractDamagePhotoExtraction::dispatch($damageReport->id);
            }

            $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'damage_report_id' => $damageReport->id,
                'return_location' => $contract->reservation?->return_location ?? null,
                'return_odometer' => $returnReadings['return_odometer'],
                'notes' => $stepPayload['notes'] ?? $stepPayload['note'] ?? null,
            ]);

            $extraction = [
                'status' => 'processing',
                'message' => 'Damage photo extraction has been queued.',
                'fields' => null,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'damage_report' => $this->damageReportPayload($damageReport->fresh(['files', 'items'])),
                'handover_photos' => $persistedPhotos,
                'vehicle_readings' => $returnReadings,
            ];
        } elseif ($page === 3) {
            $photos = $this->prepareHandoverInspectionPhotosForExtraction($request, $stepPayload, false);
            $vehicleReadingPhotos = $this->prepareHandoverVehicleReadingPhotosForExtraction($photos);
            $damageReport = $this->resolveOrCreateDraftDamageReportForHandover($contract, $request, 'return', 'after_return');

            $returnReadings = [
                'return_odometer' => isset($stepPayload['return_odometer']) && is_numeric($stepPayload['return_odometer'])
                    ? (int) $stepPayload['return_odometer']
                    : $contract->return_odometer,
                'return_fuel_level' => $this->nullableString($stepPayload['return_fuel_level'] ?? $contract->return_fuel_level),
                'odometer_confidence' => null,
                'fuel_level_confidence' => null,
            ];

            if ($vehicleReadingPhotos !== []) {
                try {
                    $vehicleExtraction = $this->contractDamagePhotoExtractor->extractFromPhotoGroups(
                        $vehicleReadingPhotos,
                        'after_return'
                    );

                    $returnReadings = $this->normalizeReturnVehicleReadingsPayload($vehicleExtraction['vehicle_readings'] ?? []);
                } catch (\Throwable $e) {
                    $returnReadings = $returnReadings;
                }
            }

            $persistedPhotos = [];
            if ($photos !== []) {
                $persistedPhotos = $this->persistHandoverHandoverPhotos(
                    $contract,
                    $damageReport,
                    $photos,
                    'return'
                );
            }

            DB::transaction(function () use ($contract, $returnReadings): void {
                $contract->forceFill([
                    'return_odometer' => $returnReadings['return_odometer'],
                    'return_fuel_level' => $this->nullableString($returnReadings['return_fuel_level'] ?? null),
                    'actual_return_time' => now(),
                ])->saveQuietly();

                $car = $contract->reservation?->car;
                if ($car && $returnReadings['return_odometer'] !== null) {
                    $currentMileage = (int) ($car->mileage ?? 0);
                    $car->forceFill([
                        'mileage' => max($currentMileage, (int) $returnReadings['return_odometer']),
                    ])->saveQuietly();
                }
            });

            $returnReport = $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'damage_report_id' => $damageReport->id,
                'actual_return_time' => now(),
                'return_location' => $contract->reservation?->return_location ?? null,
                'return_odometer' => $returnReadings['return_odometer'],
                'vehicle_condition_after' => $contract->vehicle_condition_after ?? null,
                'notes' => $stepPayload['notes'] ?? $stepPayload['note'] ?? null,
            ]);

            if ($persistedPhotos !== []) {
                $stepPayload = array_merge($stepPayload, [
                    'photos' => $persistedPhotos,
                    'stored_photos' => $persistedPhotos,
                    'handover_photos' => $persistedPhotos,
                ]);
            }

            $stepPayload = array_merge($stepPayload, [
                'vehicle_readings' => $returnReadings,
                'return_odometer' => $returnReadings['return_odometer'],
                'return_fuel_level' => $returnReadings['return_fuel_level'],
                'extraction_status' => 'extracted',
                'extraction_error' => null,
                'extraction_retrying' => false,
                'extracted_fields' => $returnReadings,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
            ]);

            $extraction = [
                'status' => 'extracted',
                'message' => 'Vehicle readings saved successfully.',
                'fields' => [
                    'return_odometer' => $returnReadings['return_odometer'],
                    'return_fuel_level' => $returnReadings['return_fuel_level'],
                ],
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'damage_report' => $damageReport->fresh(['files', 'items']),
                'vehicle_readings' => $returnReadings,
                'handover_photos' => $persistedPhotos,
                'return_status_report' => [
                    'id' => $returnReport->id,
                    'report_number' => $returnReport->report_number,
                    'status' => $returnReport->status,
                    'actual_return_time' => optional($returnReport->actual_return_time)->toIso8601String(),
                    'return_odometer' => $returnReport->return_odometer,
                    'return_fuel_level' => $returnReport->return_fuel_level,
                    'vehicle_condition_after' => $returnReport->vehicle_condition_after,
                    'damage_report_id' => $returnReport->damage_report_id,
                    'notes' => $returnReport->notes,
                ],
            ];
        } elseif ($page === 4) {
            $vehicleConditionAfter = $this->nullableString($stepPayload['vehicle_condition_after'] ?? $contract->vehicle_condition_after ?? null);
            $hasDamage = array_key_exists('has_damage', $stepPayload)
                ? filter_var($stepPayload['has_damage'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;
            $hasDamage = $hasDamage ?? true;

            $damageReport = null;
            if ($hasDamage) {
                $damageReport = $this->resolveOrCreateDraftDamageReportForHandover($contract, $request, 'return', 'after_return');
            } else {
                $this->deleteDraftDamageReportForHandover($contract, 'after_return');
            }

            $summary = array_key_exists('summary', $stepPayload)
                ? $this->nullableString($stepPayload['summary'] ?? null)
                : ($damageReport?->summary);

            $normalizedItems = null;
            if ($hasDamage && array_key_exists('items', $stepPayload) && is_array($stepPayload['items'])) {
                $normalizedItems = [];

                foreach (array_values($stepPayload['items']) as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $normalizedItems[] = [
                        'id' => isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null,
                        'zone_code' => (string) ($item['zone_code'] ?? ''),
                        'view_side' => (string) ($item['view_side'] ?? 'front'),
                        'damage_type' => (string) ($item['damage_type'] ?? 'scratch'),
                        'severity' => (string) ($item['severity'] ?? 'minor'),
                        'damage_timing' => 'after_return',
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'marker_x' => $item['marker_x'] ?? null,
                        'marker_y' => $item['marker_y'] ?? null,
                        'estimated_cost' => $item['estimated_cost'] ?? null,
                        'notes' => $item['notes'] ?? null,
                        'sort_order' => $index,
                    ];
                }
            }

            $deletedItemIds = array_values(array_unique(array_filter(array_map(
                static fn ($value): int => is_numeric($value) ? (int) $value : 0,
                (array) ($stepPayload['deleted_item_ids'] ?? [])
            ))));

            DB::transaction(function () use ($contract, $damageReport, $summary, $normalizedItems, $deletedItemIds, $request): void {
                if ($damageReport) {
                    $damageReport->forceFill([
                        'status' => 'draft',
                        'summary' => $summary,
                    ])->saveQuietly();

                    if (is_array($normalizedItems)) {
                        $this->syncItems($damageReport, $normalizedItems, $deletedItemIds);
                        $this->syncDamageCases(
                            $damageReport,
                            $normalizedItems,
                            $contract->reservation?->car,
                            $contract,
                            $contract->reservation,
                            $request->user()?->id
                        );
                    }
                }
            });

            $damageReport = $damageReport?->fresh(['files', 'items']);
            $discount = array_key_exists('discount', $stepPayload)
                ? $this->normalizeMoney($stepPayload['discount'])
                : null;

            $draftReturnReport = $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'damage_report_id' => $damageReport?->id,
                'actual_return_time' => $contract->actual_return_time ?? now(),
                'return_location' => $contract->reservation?->return_location ?? null,
                'return_odometer' => $contract->return_odometer,
                'return_fuel_level' => $contract->return_fuel_level,
                'vehicle_condition_after' => $vehicleConditionAfter,
                'has_damage' => $hasDamage,
                'discount' => $discount,
                'notes' => $summary,
            ]);

            $summaryPayload = $this->returnStatusReportSummaryPayload(
                $contract->fresh(['reservation', 'damageReports.items', 'returnStatusReport']),
                $draftReturnReport,
                $damageReport,
                $vehicleConditionAfter,
                $hasDamage
            );

            $returnReport = $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'damage_report_id' => $damageReport?->id,
                'actual_return_time' => $contract->actual_return_time ?? now(),
                'return_location' => $contract->reservation?->return_location ?? null,
                'return_odometer' => $contract->return_odometer,
                'return_fuel_level' => $contract->return_fuel_level,
                'vehicle_condition_after' => $vehicleConditionAfter,
                'has_damage' => $hasDamage,
                'notes' => $summary,
                'payment_status' => $summaryPayload['payment_status'] ?? 'not_paid',
                'discount' => $summaryPayload['discount'] ?? $discount,
                'extra_kilometers' => $summaryPayload['extra_kilometers'] ?? null,
                'kilometer_rate' => $summaryPayload['kilometer_rate'] ?? null,
                'cleaning_fee' => $summaryPayload['cleaning_fee'] ?? null,
                'fuel_fee' => $summaryPayload['fuel_fee'] ?? null,
                'fuel_credit' => $summaryPayload['fuel_credit'] ?? null,
                'late_hours' => $summaryPayload['late_hours'] ?? null,
                'late_hour_rate' => $summaryPayload['late_hour_rate'] ?? null,
                'damage_fee' => $summaryPayload['damage_fee'] ?? null,
                'maintenance_fee' => $summaryPayload['maintenance_fee'] ?? null,
                'other_fee' => $summaryPayload['other_fee'] ?? null,
                'total_extra_charges' => $summaryPayload['total_extra_charges'] ?? null,
            ]);

            $summaryPayload = $this->returnStatusReportSummaryPayload($contract->fresh(['reservation', 'damageReports.items']), $returnReport, $damageReport);

            $stepPayload = array_merge($stepPayload, [
                'has_damage' => $hasDamage,
                'damage_report_id' => $damageReport?->id,
                'damage_report_number' => $damageReport?->report_number,
                'damage_report_status' => $hasDamage ? $damageReport?->status : 'skipped',
                'damage_report_type' => $hasDamage ? $damageReport?->report_type : null,
                'summary' => $summary,
                'items' => $hasDamage && $damageReport
                    ? ($normalizedItems ?? $damageReport->items->map(fn ($item) => [
                        'id' => $item->id,
                        'zone_code' => $item->zone_code,
                        'view_side' => $item->view_side,
                        'damage_type' => $item->damage_type,
                        'severity' => $item->severity,
                        'damage_timing' => $item->damage_timing,
                        'quantity' => (int) $item->quantity,
                        'marker_x' => $item->marker_x !== null ? (float) $item->marker_x : null,
                        'marker_y' => $item->marker_y !== null ? (float) $item->marker_y : null,
                        'estimated_cost' => $item->estimated_cost !== null ? (float) $item->estimated_cost : null,
                        'notes' => $item->notes,
                    ])->values()->all())
                    : [],
                'damage_report' => $hasDamage && $damageReport ? $this->damageReportPayload($damageReport->fresh(['files', 'items'])) : null,
                'final_summary' => $summaryPayload,
            ]);

            $extraction = [
                'status' => $hasDamage ? 'extracted' : 'skipped',
                'message' => $hasDamage
                    ? 'Return damage report saved successfully.'
                    : 'No damage report created for this return.',
                'fields' => [
                    'damage_report_id' => $damageReport?->id,
                ],
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'damage_report_status' => $hasDamage ? ($damageReport?->status ?? 'pending') : 'skipped',
                'damage_report' => $hasDamage && $damageReport ? $damageReport->fresh(['files', 'items']) : null,
                'vehicle_readings' => $this->returnVehicleReadingsPayload($contract),
                'final_summary' => $summaryPayload,
            ];
        } elseif ($page === 5) {
            $paymentStatus = null;
            if (array_key_exists('payment_status', $stepPayload)) {
                $paymentStatus = $this->nullableString($stepPayload['payment_status'] ?? null);
                if ($paymentStatus !== null && !in_array($paymentStatus, ['paid', 'not_paid'], true)) {
                    throw ValidationException::withMessages([
                        'payment_status' => ['The selected payment status is invalid.'],
                    ]);
                }
            }

            $report = $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'payment_status' => $paymentStatus,
                'discount' => $stepPayload['discount'] ?? null,
                'notes' => $stepPayload['notes'] ?? $stepPayload['note'] ?? null,
            ]);

            $summaryPayload = $this->returnStatusReportSummaryPayload($contract->fresh(), $report);
            $report = $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'payment_status' => $paymentStatus,
                'discount' => $summaryPayload['discount'] ?? null,
                'extra_kilometers' => $summaryPayload['extra_kilometers'] ?? null,
                'kilometer_rate' => $summaryPayload['kilometer_rate'] ?? null,
                'cleaning_fee' => $summaryPayload['cleaning_fee'] ?? null,
                'fuel_fee' => $summaryPayload['fuel_fee'] ?? null,
                'fuel_credit' => $summaryPayload['fuel_credit'] ?? null,
                'late_hours' => $summaryPayload['late_hours'] ?? null,
                'late_hour_rate' => $summaryPayload['late_hour_rate'] ?? null,
                'damage_fee' => $summaryPayload['damage_fee'] ?? null,
                'maintenance_fee' => $summaryPayload['maintenance_fee'] ?? null,
                'other_fee' => $summaryPayload['other_fee'] ?? null,
                'total_extra_charges' => $summaryPayload['total_extra_charges'] ?? null,
                'notes' => $stepPayload['notes'] ?? $stepPayload['note'] ?? null,
            ]);
            $summaryPayload = $this->returnStatusReportSummaryPayload($contract->fresh(), $report);

            $stepPayload = array_merge($stepPayload, [
                'payment_status' => $report->payment_status ?? 'not_paid',
                'discount' => $summaryPayload['discount'] ?? 0,
                'final_summary' => $summaryPayload,
                'return_status_report' => $summaryPayload['return_status_report'],
            ]);

            $extraction = [
                'status' => 'extracted',
                'message' => 'Return summary prepared successfully.',
                'fields' => $summaryPayload,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'damage_report_status' => $summaryPayload['damage_report_status'] ?? null,
                'damage_report' => $summaryPayload['damage_report'] ?? null,
                'vehicle_readings' => $summaryPayload['vehicle_readings'] ?? $this->returnVehicleReadingsPayload($contract),
                'return_status_report' => $summaryPayload['return_status_report'],
                'final_summary' => $summaryPayload,
            ];
        } elseif ($page === 6) {
            $returnConfirmed = (bool) ($stepPayload['return_confirmed'] ?? false);
            $paymentStatus = $this->nullableString($stepPayload['payment_status'] ?? null)
                ?? ($contract->returnStatusReport?->payment_status ?? 'not_paid');

            $summaryPayload = $this->returnStatusReportSummaryPayload($contract->fresh(['reservation', 'damageReports.items', 'returnStatusReport']), $contract->returnStatusReport);

            $report = $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'damage_report_id' => data_get($summaryPayload, 'damage_report.id') ?? $contract->returnStatusReport?->damage_report_id,
                'status' => 'finalized',
                'payment_status' => $paymentStatus,
                'actual_return_time' => $contract->actual_return_time ?? now(),
                'return_location' => $contract->reservation?->return_location ?? $contract->returnStatusReport?->return_location,
                'return_odometer' => $contract->return_odometer,
                'return_fuel_level' => $contract->return_fuel_level,
                'vehicle_condition_after' => $contract->vehicle_condition_after ?? $contract->returnStatusReport?->vehicle_condition_after,
                'has_damage' => $summaryPayload['has_damage'] ?? $contract->returnStatusReport?->has_damage,
                'extra_kilometers' => $summaryPayload['extra_kilometers'] ?? null,
                'kilometer_rate' => $summaryPayload['kilometer_rate'] ?? null,
                'cleaning_fee' => $summaryPayload['cleaning_fee'] ?? null,
                'fuel_fee' => $summaryPayload['fuel_fee'] ?? null,
                'fuel_credit' => $summaryPayload['fuel_credit'] ?? null,
                'late_hours' => $summaryPayload['late_hours'] ?? null,
                'late_hour_rate' => $summaryPayload['late_hour_rate'] ?? null,
                'damage_fee' => $summaryPayload['damage_fee'] ?? null,
                'maintenance_fee' => $summaryPayload['maintenance_fee'] ?? null,
                'other_fee' => $summaryPayload['other_fee'] ?? null,
                'discount' => $summaryPayload['discount'] ?? null,
                'total_extra_charges' => $summaryPayload['total_extra_charges'] ?? null,
                'notes' => $stepPayload['notes'] ?? $stepPayload['note'] ?? $contract->returnStatusReport?->notes,
            ]);

            if ($returnConfirmed) {
                DB::transaction(function () use ($contract, $report, $paymentStatus): void {
                    $this->syncReturnStatusReportPayment($contract, $report, $paymentStatus);

                    $contract->forceFill([
                        'actual_return_time' => $report->actual_return_time ?? $contract->actual_return_time ?? now(),
                        'return_odometer' => $report->return_odometer ?? $contract->return_odometer,
                        'return_fuel_level' => $report->return_fuel_level ?? $contract->return_fuel_level,
                        'vehicle_condition_after' => $report->vehicle_condition_after ?? $contract->vehicle_condition_after,
                        'status' => ContractStatus::COMPLETED->value,
                    ])->saveQuietly();

                    if ($contract->reservation) {
                        $contract->reservation->forceFill([
                            'status' => ReservationStatus::COMPLETED->value,
                        ])->saveQuietly();
                    }

                    if ($contract->reservation?->car) {
                        $updates = [
                            'status' => CarStatus::AVAILABLE->value,
                        ];

                        if ($report->return_odometer !== null) {
                            $updates['mileage'] = max((int) ($contract->reservation->car->mileage ?? 0), (int) $report->return_odometer);
                        }

                        $contract->reservation->car->forceFill($updates)->saveQuietly();
                    }
                });

                $contract->refresh()->loadMissing(['reservation.car', 'returnStatusReport']);
                $report = $contract->returnStatusReport?->fresh();
                $summaryPayload = $this->returnStatusReportSummaryPayload($contract->fresh(['reservation', 'damageReports.items', 'returnStatusReport']), $report);
            }

            $stepPayload = array_merge($stepPayload, [
                'return_confirmed' => $returnConfirmed,
                'returned_at' => $returnConfirmed ? now()->toIso8601String() : null,
                'payment_status' => $paymentStatus,
                'final_summary' => $summaryPayload,
                'return_status_report' => $summaryPayload['return_status_report'],
                'return_status_report_file' => $this->returnStatusReportFilePayload($contract, $report),
                'contract_status' => $this->contractStatusValue($contract->status),
                'reservation_status' => $contract->reservation?->status instanceof ReservationStatus
                    ? $contract->reservation->status->value
                    : (string) ($contract->reservation?->status ?? ''),
                'car_status' => $contract->reservation?->car?->status instanceof CarStatus
                    ? $contract->reservation->car->status->value
                    : (string) ($contract->reservation?->car?->status ?? ''),
            ]);

            $extraction = [
                'status' => $returnConfirmed ? 'finalized' : 'pending',
                'message' => $returnConfirmed
                    ? 'Vehicle return confirmed and return report finalized.'
                    : 'Vehicle return confirmation is required.',
                'fields' => $summaryPayload,
                'applied_fields' => [],
                'raw_output' => null,
                'text_preview' => null,
                'damage_report_status' => $summaryPayload['damage_report_status'] ?? null,
                'damage_report' => $summaryPayload['damage_report'] ?? null,
                'vehicle_readings' => $summaryPayload['vehicle_readings'] ?? $this->returnVehicleReadingsPayload($contract),
                'return_status_report' => $summaryPayload['return_status_report'],
                'return_status_report_file' => $this->returnStatusReportFilePayload($contract, $report),
                'final_summary' => $summaryPayload,
            ];
        } else {
            $contractNote = array_key_exists('notes', $stepPayload)
                ? $stepPayload['notes']
                : (array_key_exists('note', $stepPayload) ? $stepPayload['note'] : $contract->notes);
            $contractNote = $this->nullableString($contractNote);

            if ($contractNote !== $contract->notes) {
                $contract->forceFill([
                    'notes' => $contractNote,
                ])->saveQuietly();
            }

            $stepPayload = array_merge($stepPayload, [
                'reviewed' => (bool) ($stepPayload['reviewed'] ?? false),
                'note' => $contractNote,
                'notes' => $contractNote,
            ]);

            $this->syncReturnStatusReportDraft($contract, [
                'created_by' => $request->user()?->id,
                'return_location' => $contract->reservation?->return_location ?? null,
                'notes' => $contractNote,
            ]);
        }

        $state = $this->normalizeReturnHandoverState($contract->handover_state);
        $state['current_page'] = max($state['current_page'], min($page + 1, 6));
        $state['completed_pages'] = array_values(array_unique(array_merge($state['completed_pages'], [$page])));
        $state['steps'][$step['key']] = [
            'page' => $page,
            'key' => $step['key'],
            'label' => $step['label'],
            'completed' => true,
            'completed_at' => now()->toIso8601String(),
            'payload' => $stepPayload,
        ];

        $allState = is_array($contract->handover_state) ? $contract->handover_state : [];
        $allState['active_phase'] = 'return';
        $allState['phases']['return'] = $state;

        $contract->forceFill([
            'handover_state' => $allState,
        ])->saveQuietly();

        $contract->refresh()->loadMissing([
            'reservation.user:id,name,email,is_active',
            'reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
            'reservation.car.branch:id,name',
            'branch:id,name',
        ]);

        return response()->json([
            'message' => 'Return handover step saved successfully.',
            'reservation' => $this->reservationPayload($contract->reservation),
            'contract' => $this->contractPayload($contract, $locale),
            'phase' => 'return',
            'phase_label' => $this->handoverPhaseLabel('return'),
            'handover' => $this->returnHandoverPayload($contract, $locale),
            'extraction' => $extraction ? [
                'status' => $extraction['status'] ?? 'pending',
                'message' => $extraction['message'] ?? null,
                'fields' => $extraction['fields'] ?? null,
                'applied_fields' => [],
                'raw_output' => $extraction['raw_output'] ?? null,
                'text_preview' => $extraction['text_preview'] ?? null,
                'damage_report_status' => $extraction['damage_report_status'] ?? null,
                'damage_report' => $extraction['damage_report'] ?? null,
                'vehicle_readings' => $extraction['vehicle_readings'] ?? null,
                'return_status_report' => $extraction['return_status_report'] ?? null,
                'return_status_report_file' => $extraction['return_status_report_file'] ?? null,
                'final_summary' => $extraction['final_summary'] ?? null,
            ] : null,
        ]);
    }

    private function authorizeAdminApiUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }

    private function resolveApiLocale(Request $request): string
    {
        $supportedLocales = array_values(array_filter((array) config('app.available_locales', ['en']), static fn ($locale) => is_string($locale) && $locale !== ''));
        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $preferred = $request->getPreferredLanguage($supportedLocales);

        if (is_string($preferred) && $preferred !== '') {
            return $preferred;
        }

        return in_array($fallback, $supportedLocales, true) ? $fallback : ($supportedLocales[0] ?? 'en');
    }

    private function canAccessContract(Contract $contract, User $user): bool
    {
        $contract->loadMissing('reservation.car:id,branch_id');

        $branchId = $contract->branch_id
            ? (int) $contract->branch_id
            : ($contract->reservation?->car?->branch_id ? (int) $contract->reservation->car->branch_id : null);

        return $this->branchAccess->canAccessBranchId($user, $branchId);
    }

    private function damageOptionsPayload(string $locale): array
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);

        try {
            return [
                'zones' => array_map(
                    static fn (array $zone): array => [
                        'value' => $zone['code'],
                        'label' => $zone['label'],
                    ],
                    CarDamageCatalog::zoneDefinitions()
                ),
                'damage_types' => array_values(CarDamageCatalog::damageTypes()),
                'severity_levels' => array_values(CarDamageCatalog::severityLevels()),
                'damage_timings' => array_values(CarDamageCatalog::damageTimings()),
                'view_sides' => array_values(CarDamageCatalog::viewSides()),
            ];
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function contractPayload(Contract $contract, ?string $locale = null): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'status' => $this->contractStatusValue($contract->status),
            'status_label' => $this->contractStatusLabel($contract->status),
            'status_color' => $this->contractStatusColor($contract->status),
            'contract_date' => optional($contract->contract_date)->toDateString(),
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => optional($contract->end_date)->toDateString(),
            'vehicle_odometer' => $contract->vehicle_odometer,
            'vehicle_fuel_level' => $contract->vehicle_fuel_level,
            'branch_name' => $contract->branch?->name,
            'ai_extraction_status' => $contract->ai_extraction_status,
            'ai_extracted_data' => $contract->ai_extracted_data,
            'handover_photos' => $this->handoverPhotosPayload($contract),
            'inputs' => $this->contractInputsPayload($contract),
            'reservation' => $contract->reservation ? [
                'id' => $contract->reservation->id,
                'reservation_number' => $contract->reservation->reservation_number,
                'status' => $contract->reservation->status instanceof \App\Enums\ReservationStatus
                    ? $contract->reservation->status->value
                    : (string) $contract->reservation->status,
            ] : null,
            'car' => $contract->reservation?->car ? [
                'id' => $contract->reservation->car->id,
                'name' => trim(sprintf(
                    '%s %s %s',
                    (string) ($contract->reservation->car->year ?? ''),
                    (string) ($contract->reservation->car->make ?? ''),
                    (string) ($contract->reservation->car->model ?? '')
                )),
                'license_plate' => (string) ($contract->reservation->car->license_plate ?? ''),
                'status' => $contract->reservation->car->status instanceof \App\Enums\CarStatus
                    ? $contract->reservation->car->status->value
                    : (string) $contract->reservation->car->status,
            ] : null,
            'handover' => $this->handoverPayload($contract, $locale),
        ];
    }

    private function reservationPayload(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'status' => $reservation->status instanceof \App\Enums\ReservationStatus
                ? $reservation->status->value
                : (string) $reservation->status,
            'client_name' => $reservation->user?->name,
            'client_email' => $reservation->user?->email,
            'client_status' => $reservation->user ? ($reservation->user->is_active ? 'active' : 'suspended') : null,
            'client_status_label' => $reservation->user ? ($reservation->user->is_active ? 'Active' : 'Suspended') : null,
            'client' => $reservation->user ? [
                'id' => $reservation->user->id,
                'name' => $reservation->user->name,
                'email' => $reservation->user->email,
                'is_active' => (bool) $reservation->user->is_active,
                'status' => $reservation->user->is_active ? 'active' : 'suspended',
                'status_label' => $reservation->user->is_active ? 'Active' : 'Suspended',
            ] : null,
            'car' => $reservation->car ? [
                'id' => $reservation->car->id,
                'name' => trim(sprintf(
                    '%s %s %s',
                    (string) ($reservation->car->year ?? ''),
                    (string) ($reservation->car->make ?? ''),
                    (string) ($reservation->car->model ?? '')
                )),
                'license_plate' => (string) ($reservation->car->license_plate ?? ''),
                'branch_name' => (string) ($reservation->car->branch?->name ?? ''),
                'status' => $reservation->car->status instanceof \App\Enums\CarStatus
                    ? $reservation->car->status->value
                    : (string) $reservation->car->status,
            ] : null,
        ];
    }

    private function handoverPayload(Contract $contract, ?string $locale = null): array
    {
        $state = $this->normalizeHandoverState($contract->handover_state);

        return [
            'current_page' => $state['current_page'],
            'completed_pages' => $state['completed_pages'],
            'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
            'steps' => array_values(array_map(
                function (array $step) use ($contract, $locale): array {
                    $payload = $step['payload'];
                    if ($step['page'] === 1) {
                        $payload = array_merge([
                            'reservation' => $this->reservationPayload($contract->reservation),
                            'client' => $contract->reservation?->user ? [
                                'id' => $contract->reservation->user->id,
                                'name' => $contract->reservation->user->name,
                                'email' => $contract->reservation->user->email,
                                'is_active' => (bool) $contract->reservation->user->is_active,
                                'status' => $contract->reservation->user->is_active ? 'active' : 'suspended',
                                'status_label' => $contract->reservation->user->is_active ? 'Active' : 'Suspended',
                            ] : null,
                        ], $payload);
                    }

                    if ($step['page'] === 2) {
                        $payload = array_merge([
                            'contract_inputs' => $this->contractInputsPayload($contract),
                            'ai_extracted_data' => $contract->ai_extracted_data ?? [],
                        ], $payload);
                    }

                    if ($step['page'] === 3) {
                        $payload = array_merge([
                            'handover_photos' => $this->handoverPhotosPayload($contract, 'delivery'),
                            'vehicle_readings' => $this->vehicleReadingsPayload($contract),
                        ], $payload);
                    }

                    if ($step['page'] === 4) {
                        $payload = array_merge([
                            'contract_inputs' => $this->contractInputsPayload($contract),
                            'vehicle_odometer' => $contract->vehicle_odometer,
                            'vehicle_fuel_level' => $contract->vehicle_fuel_level,
                            'vehicle_readings' => $this->vehicleReadingsPayload($contract),
                            'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
                        ], $payload);
                    }

                    if ($step['page'] === 5) {
                        $payload = array_merge([
                            'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
                            'accepted_terms' => (bool) data_get($payload, 'accepted_terms', false),
                            'accepted_at' => data_get($payload, 'accepted_at'),
                        ], $payload);
                    }

                    if ($step['page'] === 6) {
                        $hasDamage = array_key_exists('has_damage', $payload) && $payload['has_damage'] !== null
                            ? filter_var($payload['has_damage'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                            : null;
                        $hasDamage = $hasDamage ?? true;
                        $damageReport = $contract->damageReports
                            ->filter(static fn (CarDamageReport $report): bool => $report->report_type === 'before_delivery')
                            ->sortByDesc('id')
                            ->first();

                        $payload = array_merge([
                            'reservation' => $this->reservationPayload($contract->reservation),
                            'contract_inputs' => $this->contractInputsPayload($contract),
                            'vehicle_readings' => $this->vehicleReadingsPayload($contract),
                            'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
                            'has_damage' => $hasDamage,
                            'damage_report_status' => $hasDamage
                                ? ($damageReport ? ($damageReport->items()->exists() ? 'done' : 'pending') : 'pending')
                                : 'skipped',
                            'damage_report' => $hasDamage && $damageReport
                                ? $this->damageReportPayload($damageReport->fresh(['files', 'items']))
                                : null,
                        ], $payload);
                    }

                    if ($step['page'] === 7) {
                        $payload = array_merge([
                            'reservation' => $this->reservationPayload($contract->reservation),
                            'contract_inputs' => $this->contractInputsPayload($contract),
                            'vehicle_readings' => $this->vehicleReadingsPayload($contract),
                            'mobile_signature_text' => $this->mobileSignatureTextForContract($contract, $locale),
                            'delivery_confirmed' => (bool) data_get($payload, 'delivery_confirmed', false),
                            'contract_file' => $this->contractFilePayload($contract, $locale),
                            'contract_status' => $this->contractStatusValue($contract->status),
                            'reservation_status' => $contract->reservation?->status instanceof ReservationStatus
                                ? $contract->reservation->status->value
                                : (string) ($contract->reservation?->status ?? ''),
                            'car_status' => $contract->reservation?->car?->status instanceof CarStatus
                                ? $contract->reservation->car->status->value
                                : (string) ($contract->reservation?->car?->status ?? ''),
                        ], $payload);
                    }

                    return [
                    'page' => $step['page'],
                    'key' => $step['key'],
                    'label' => $step['label'],
                    'completed' => $step['completed'],
                    'payload' => $payload,
                ];
                },
                $state['steps']
            )),
        ];
    }

    private function returnHandoverPayload(Contract $contract, ?string $locale = null): array
    {
        $state = $this->normalizeReturnHandoverState($contract->handover_state);

        return [
            'phase' => 'return',
            'phase_label' => $this->handoverPhaseLabel('return'),
            'current_page' => $state['current_page'],
            'completed_pages' => $state['completed_pages'],
            'steps' => array_values(array_map(
                function (array $step) use ($contract, $locale): array {
                    $payload = $step['payload'];

                    if ($step['page'] === 1) {
                        $payload = array_merge([
                            'reservation' => $this->reservationPayload($contract->reservation),
                            'client' => $contract->reservation?->user ? [
                                'id' => $contract->reservation->user->id,
                                'name' => $contract->reservation->user->name,
                                'email' => $contract->reservation->user->email,
                                'is_active' => (bool) $contract->reservation->user->is_active,
                                'status' => $contract->reservation->user->is_active ? 'active' : 'suspended',
                                'status_label' => $contract->reservation->user->is_active ? 'Active' : 'Suspended',
                            ] : null,
                        ], $payload);
                    }

                    if ($step['page'] === 2) {
                        $payload = array_merge([
                            'contract_inputs' => $this->contractInputsPayload($contract),
                            'handover_photos' => $this->handoverPhotosPayload($contract, 'return'),
                        ], $payload);
                    }

                    if ($step['page'] === 3) {
                        $payload = array_merge([
                            'contract_inputs' => $this->contractInputsPayload($contract),
                            'vehicle_readings' => $this->returnVehicleReadingsPayload($contract),
                            'return_odometer' => $contract->return_odometer,
                            'return_fuel_level' => $contract->return_fuel_level,
                        ], $payload);
                    }

                    if ($step['page'] === 4) {
                        $hasDamage = array_key_exists('has_damage', $payload)
                            ? filter_var($payload['has_damage'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                            : null;
                        $hasDamage = $hasDamage ?? true;
                        $damageReport = $contract->damageReports
                            ->filter(static fn (CarDamageReport $report): bool => $report->report_type === 'after_return')
                            ->sortByDesc('id')
                            ->first();

                        if (!$hasDamage) {
                            $payload = array_merge([
                                'has_damage' => false,
                                'damage_report_status' => 'skipped',
                                'damage_report' => null,
                            ], $payload);

                            return [
                                'page' => $step['page'],
                                'key' => $step['key'],
                                'label' => $step['label'],
                                'completed' => $step['completed'],
                                'payload' => $payload,
                            ];
                        }

                        $payload = array_merge([
                            'damage_report_status' => $damageReport
                                ? ($damageReport->items()->exists() ? 'done' : 'pending')
                                : 'pending',
                            'damage_report' => $damageReport
                                ? $this->damageReportPayload($damageReport->fresh(['files', 'items']))
                                : null,
                        ], $payload);
                    }

                    if ($step['page'] === 5) {
                        $summary = $this->returnStatusReportSummaryPayload($contract, $contract->returnStatusReport);

                        $payload = array_merge([
                            'payment_status' => $summary['payment_status'] ?? ($contract->returnStatusReport?->payment_status ?? 'not_paid'),
                            'final_summary' => $summary,
                            'return_status_report' => $summary['return_status_report'],
                        ], $payload);
                    }

                    if ($step['page'] === 6) {
                        $summary = $this->returnStatusReportSummaryPayload($contract, $contract->returnStatusReport);

                        $payload = array_merge([
                            'return_confirmed' => data_get($payload, 'return_confirmed', false),
                            'final_summary' => $summary,
                            'return_status_report' => $summary['return_status_report'],
                            'return_status_report_file' => $this->returnStatusReportFilePayload($contract, $contract->returnStatusReport),
                            'contract_status' => $this->contractStatusValue($contract->status),
                            'reservation_status' => $contract->reservation?->status instanceof ReservationStatus
                                ? $contract->reservation->status->value
                                : (string) ($contract->reservation?->status ?? ''),
                            'car_status' => $contract->reservation?->car?->status instanceof CarStatus
                                ? $contract->reservation->car->status->value
                                : (string) ($contract->reservation?->car?->status ?? ''),
                        ], $payload);
                    }

                    return [
                        'page' => $step['page'],
                        'key' => $step['key'],
                        'label' => $step['label'],
                        'completed' => $step['completed'],
                        'payload' => $payload,
                    ];
                },
                $state['steps']
            )),
        ];
    }

    private function normalizeHandoverState(mixed $state): array
    {
        $state = is_array($state) ? $state : [];

        $completedPages = array_values(array_unique(array_map(
            static fn ($page): int => max(1, (int) $page),
            data_get($state, 'completed_pages', [])
        )));

        $steps = data_get($state, 'steps', []);
        $steps = is_array($steps) ? $steps : [];
        $customerReview = $steps['customer_review'] ?? [];
        $documentsUpload = $steps['report_upload'] ?? [];
        $damagePhotoUpload = $steps['damage_photo_upload'] ?? [];
        $vehicleReadings = $steps['vehicle_readings'] ?? [];
        $damageReview = $steps['damage_review'] ?? [];

        return [
            'current_page' => max(1, (int) data_get($state, 'current_page', 1)),
            'completed_pages' => $completedPages,
            'steps' => [
                'customer_review' => [
                    'page' => 1,
                    'key' => 'customer_review',
                    'label' => 'Customer Review',
                    'completed' => in_array(1, $completedPages, true),
                    'payload' => is_array(data_get($customerReview, 'payload')) ? data_get($customerReview, 'payload') : [],
                ],
                'report_upload' => [
                    'page' => 2,
                    'key' => 'report_upload',
                    'label' => 'Report Upload',
                    'completed' => in_array(2, $completedPages, true),
                    'payload' => is_array(data_get($documentsUpload, 'payload')) ? data_get($documentsUpload, 'payload') : [],
                ],
                'damage_photo_upload' => [
                    'page' => 3,
                    'key' => 'damage_photo_upload',
                    'label' => 'Damage Photos',
                    'completed' => in_array(3, $completedPages, true),
                    'payload' => is_array(data_get($damagePhotoUpload, 'payload')) ? data_get($damagePhotoUpload, 'payload') : [],
                ],
                'vehicle_readings' => [
                    'page' => 4,
                    'key' => 'vehicle_readings',
                    'label' => 'Vehicle Readings',
                    'completed' => in_array(4, $completedPages, true),
                    'payload' => is_array(data_get($vehicleReadings, 'payload')) ? data_get($vehicleReadings, 'payload') : [],
                ],
                'terms_confirmation' => [
                    'page' => 5,
                    'key' => 'terms_confirmation',
                    'label' => 'Terms Confirmation',
                    'completed' => in_array(5, $completedPages, true),
                    'payload' => is_array(data_get($steps, 'terms_confirmation.payload'))
                        ? data_get($steps, 'terms_confirmation.payload')
                        : [],
                ],
                'damage_review' => [
                    'page' => 6,
                    'key' => 'damage_review',
                    'label' => 'Damage Review',
                    'completed' => in_array(6, $completedPages, true),
                    'payload' => is_array(data_get($damageReview, 'payload')) ? data_get($damageReview, 'payload') : [],
                ],
                'delivery_confirmation' => [
                    'page' => 7,
                    'key' => 'delivery_confirmation',
                    'label' => 'Delivery Confirmation',
                    'completed' => in_array(7, $completedPages, true),
                    'payload' => is_array(data_get($steps, 'delivery_confirmation.payload'))
                        ? data_get($steps, 'delivery_confirmation.payload')
                        : [],
                ],
            ],
        ];
    }

    private function normalizeReturnHandoverState(mixed $state): array
    {
        $state = is_array($state) ? $state : [];
        $returnState = data_get($state, 'phases.return', []);
        $returnState = is_array($returnState) ? $returnState : [];

        $completedPages = array_values(array_unique(array_map(
            static fn ($page): int => max(1, (int) $page),
            data_get($returnState, 'completed_pages', [])
        )));

        $steps = data_get($returnState, 'steps', []);
        $steps = is_array($steps) ? $steps : [];
        $customerReview = $steps['customer_review'] ?? [];
        $returnInspection = $steps['return_inspection'] ?? [];
        $vehicleReadings = $steps['vehicle_readings'] ?? [];
        $damageReview = $steps['damage_review'] ?? [];
        $finalSummary = $steps['final_summary'] ?? [];
        $returnConfirmation = $steps['return_confirmation'] ?? [];

        return [
            'current_page' => max(1, (int) data_get($returnState, 'current_page', 1)),
            'completed_pages' => $completedPages,
            'steps' => [
                'customer_review' => [
                    'page' => 1,
                    'key' => 'customer_review',
                    'label' => 'Customer Review',
                    'completed' => in_array(1, $completedPages, true),
                    'payload' => is_array(data_get($customerReview, 'payload')) ? data_get($customerReview, 'payload') : [],
                ],
                'return_inspection' => [
                    'page' => 2,
                    'key' => 'return_inspection',
                    'label' => 'Return Inspection',
                    'completed' => in_array(2, $completedPages, true),
                    'payload' => is_array(data_get($returnInspection, 'payload')) ? data_get($returnInspection, 'payload') : [],
                ],
                'vehicle_readings' => [
                    'page' => 3,
                    'key' => 'vehicle_readings',
                    'label' => 'Vehicle Readings',
                    'completed' => in_array(3, $completedPages, true),
                    'payload' => is_array(data_get($vehicleReadings, 'payload')) ? data_get($vehicleReadings, 'payload') : [],
                ],
                'damage_review' => [
                    'page' => 4,
                    'key' => 'damage_review',
                    'label' => 'Damage Review',
                    'completed' => in_array(4, $completedPages, true),
                    'payload' => is_array(data_get($damageReview, 'payload')) ? data_get($damageReview, 'payload') : [],
                ],
                'final_summary' => [
                    'page' => 5,
                    'key' => 'final_summary',
                    'label' => 'Final Summary',
                    'completed' => in_array(5, $completedPages, true),
                    'payload' => is_array(data_get($finalSummary, 'payload')) ? data_get($finalSummary, 'payload') : [],
                ],
                'return_confirmation' => [
                    'page' => 6,
                    'key' => 'return_confirmation',
                    'label' => 'Return Confirmation',
                    'completed' => in_array(6, $completedPages, true),
                    'payload' => is_array(data_get($returnConfirmation, 'payload')) ? data_get($returnConfirmation, 'payload') : [],
                ],
            ],
        ];
    }

    private function returnHandoverStepForPage(int $page): array
    {
        return match ($page) {
            1 => ['key' => 'customer_review', 'label' => 'Customer Review'],
            2 => ['key' => 'return_inspection', 'label' => 'Return Inspection'],
            3 => ['key' => 'vehicle_readings', 'label' => 'Vehicle Readings'],
            4 => ['key' => 'damage_review', 'label' => 'Damage Review'],
            5 => ['key' => 'final_summary', 'label' => 'Final Summary'],
            6 => ['key' => 'return_confirmation', 'label' => 'Return Confirmation'],
            default => throw ValidationException::withMessages([
                'page' => ['This return handover page is not implemented yet. Use page 1, 2, 3, 4, 5 or 6.'],
            ]),
        };
    }

    private function validateReturnHandoverStepPayload(int $page, array $payload): array
    {
        return match ($page) {
            1 => Validator::make($payload, [
                'reviewed' => ['required', 'accepted'],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            2 => Validator::make($payload, [
                'photos' => ['nullable', 'array', 'min:1'],
                'photos.*.view_side' => ['nullable', 'string', Rule::in(array_column(CarDamageCatalog::viewSides(), 'value'))],
                'photos.*.photo_type' => ['nullable', 'string', Rule::in(['damage', 'odometer', 'fuel'])],
                'photos.*.temp_folders' => ['nullable', 'array'],
                'photos.*.temp_folders.*' => ['string'],
                'photos.*.files' => ['nullable', 'array'],
                'photos.*.files.*' => ['file', 'max:10240'],
                'photos.*.notes' => ['nullable', 'string', 'max:5000'],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            3 => Validator::make($payload, [
                'photos' => ['nullable', 'array'],
                'photos.*.view_side' => ['nullable', 'string', Rule::in(array_column(CarDamageCatalog::viewSides(), 'value'))],
                'photos.*.photo_type' => ['nullable', 'string', Rule::in(['odometer', 'fuel'])],
                'photos.*.temp_folders' => ['nullable', 'array'],
                'photos.*.temp_folders.*' => ['string'],
                'photos.*.files' => ['nullable', 'array'],
                'photos.*.files.*' => ['file', 'max:10240'],
                'photos.*.notes' => ['nullable', 'string', 'max:5000'],
                'return_odometer' => ['nullable', 'numeric', 'min:0'],
                'return_fuel_level' => ['nullable', 'string', Rule::in(['empty', '1/4', '1/2', '3/4', 'full'])],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            4 => Validator::make($payload, [
                  'damage_report_id' => ['nullable', 'integer', 'min:1'],
                  'summary' => ['nullable', 'string', 'max:5000'],
                  'vehicle_condition_after' => ['nullable', Rule::in(['clean', 'not_clean'])],
                  'has_damage' => ['nullable', 'boolean'],
                  'discount' => ['nullable', 'numeric', 'min:0'],
                  'items' => ['nullable', 'array'],
                  'items.*.id' => ['nullable', 'integer', 'min:1'],
                  'items.*.zone_code' => ['required', 'string', Rule::in(CarDamageCatalog::zoneCodes())],
                  'items.*.view_side' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::viewSides(), 'value'))],
                  'items.*.damage_type' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::damageTypes(), 'value'))],
                  'items.*.severity' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::severityLevels(), 'value'))],
                  'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
                  'items.*.marker_x' => ['nullable', 'numeric', 'min:0'],
                  'items.*.marker_y' => ['nullable', 'numeric', 'min:0'],
                  'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
                  'items.*.notes' => ['nullable', 'string', 'max:2000'],
                  'deleted_item_ids' => ['nullable', 'array'],
                  'deleted_item_ids.*' => ['integer', 'min:1'],
                  'note' => ['nullable', 'string', 'max:5000'],
                  'notes' => ['nullable', 'string', 'max:5000'],
              ])->validate(),
            5 => Validator::make($payload, [
                  'payment_status' => ['nullable', Rule::in(['paid', 'not_paid'])],
                  'discount' => ['nullable', 'numeric', 'min:0'],
                  'note' => ['nullable', 'string', 'max:5000'],
                  'notes' => ['nullable', 'string', 'max:5000'],
              ])->validate(),
            6 => Validator::make($payload, [
                  'return_confirmed' => ['required', 'accepted'],
                  'payment_status' => ['nullable', Rule::in(['paid', 'not_paid'])],
                  'note' => ['nullable', 'string', 'max:5000'],
                  'notes' => ['nullable', 'string', 'max:5000'],
              ])->validate(),
            default => throw ValidationException::withMessages([
                'page' => ['This return handover page is not implemented yet. Use page 1, 2, 3, 4, 5 or 6.'],
            ]),
        };
    }

    private function normalizeReturnHandoverPageTwoPayload(Request $request, array $payload): array
    {
        $payload = $this->normalizeHandoverPageThreePayload($request, $payload);

        if (array_key_exists('reviewed', $payload) || $request->has('reviewed')) {
            $payload['reviewed'] = (bool) ($payload['reviewed'] ?? $request->boolean('reviewed', false));
        }

        if (array_key_exists('note', $payload) || $request->has('note')) {
            $payload['note'] = $payload['note'] ?? $request->input('note');
        }

        if (array_key_exists('notes', $payload) || $request->has('notes')) {
            $payload['notes'] = $payload['notes'] ?? $request->input('notes');
        }

        return $payload;
    }

    private function normalizeReturnHandoverPageThreePayload(Request $request, array $payload): array
    {
        $photos = is_array($payload['photos'] ?? null) ? array_values($payload['photos']) : [];
        $uploadedPhotos = data_get($request->allFiles(), 'photos', []);
        $topLevelFiles = array_values(array_filter(
            is_array($request->file('files', [])) ? $request->file('files', []) : [],
            static fn ($file): bool => $file instanceof UploadedFile
        ));

        foreach ($photos as $index => $photo) {
            $photoFiles = data_get($uploadedPhotos, "{$index}.files", []);
            $photoFiles = array_values(array_filter(
                is_array($photoFiles) ? $photoFiles : [],
                static fn ($file): bool => $file instanceof UploadedFile
            ));

            $storedFolders = $photoFiles === [] ? [] : $this->storeUploadedHandoverFiles($photoFiles);
            $tempFolders = array_values(array_unique(array_filter(
                array_merge(
                    is_array($photo['temp_folders'] ?? null) ? $photo['temp_folders'] : [],
                    $storedFolders
                ),
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            )));

            $photos[$index] = [
                'view_side' => $photo['view_side'] ?? 'front',
                'photo_type' => $photo['photo_type'] ?? 'odometer',
                'temp_folders' => $tempFolders,
                'files' => $photoFiles,
                'notes' => $photo['notes'] ?? null,
            ];
        }

        if ($photos === []) {
            $topLevelPhotoType = strtolower(trim((string) ($payload['photo_type'] ?? $request->input('photo_type', ''))));
            $topLevelPhotoType = in_array($topLevelPhotoType, ['odometer', 'fuel'], true) ? $topLevelPhotoType : null;
            $hasTopLevelPhotoData = $topLevelPhotoType !== null
                || filled($payload['view_side'] ?? null)
                || filled($request->input('view_side'))
                || !empty($topLevelFiles);

            if ($hasTopLevelPhotoData) {
                $photos = [[
                    'view_side' => $payload['view_side'] ?? $request->input('view_side', 'front'),
                    'photo_type' => $topLevelPhotoType ?? 'odometer',
                    'temp_folders' => is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : [],
                    'files' => $topLevelFiles,
                    'notes' => $payload['notes'] ?? $payload['note'] ?? null,
                ]];
            }
        }

        $payload['photos'] = $photos;

        if (array_key_exists('return_odometer', $payload) || $request->has('return_odometer')) {
            $payload['return_odometer'] = $payload['return_odometer'] ?? $request->input('return_odometer');
        }

        if (array_key_exists('return_fuel_level', $payload) || $request->has('return_fuel_level')) {
            $payload['return_fuel_level'] = $payload['return_fuel_level'] ?? $request->input('return_fuel_level');
        }

        if (array_key_exists('note', $payload) || $request->has('note')) {
            $payload['note'] = $payload['note'] ?? $request->input('note');
        }

        if (array_key_exists('notes', $payload) || $request->has('notes')) {
            $payload['notes'] = $payload['notes'] ?? $request->input('notes');
        }

        return $payload;
    }

    private function normalizeReturnVehicleReadingsPayload(mixed $vehicleReadings): array
    {
        $vehicleReadings = is_array($vehicleReadings) ? $vehicleReadings : [];

        return [
            'return_odometer' => isset($vehicleReadings['vehicle_odometer']) && is_numeric($vehicleReadings['vehicle_odometer'])
                ? (int) $vehicleReadings['vehicle_odometer']
                : null,
            'return_fuel_level' => $this->normalizeFuelLevelForStorage($vehicleReadings['vehicle_fuel_level'] ?? null),
            'odometer_confidence' => isset($vehicleReadings['odometer_confidence']) && is_numeric($vehicleReadings['odometer_confidence'])
                ? (float) $vehicleReadings['odometer_confidence']
                : null,
            'fuel_level_confidence' => isset($vehicleReadings['fuel_level_confidence']) && is_numeric($vehicleReadings['fuel_level_confidence'])
                ? (float) $vehicleReadings['fuel_level_confidence']
                : null,
        ];
    }

    private function returnVehicleReadingsPayload(Contract $contract): array
    {
        return [
            'return_odometer' => $contract->return_odometer,
            'return_fuel_level' => $contract->return_fuel_level,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncReturnStatusReportDraft(Contract $contract, array $attributes = []): ContractReturnReport
    {
        $existingReport = ContractReturnReport::query()
            ->where('tenant_id', $contract->tenant_id)
            ->where('contract_id', $contract->id)
            ->latest('id')
            ->first();

        $report = ContractReturnReport::query()->updateOrCreate(
            [
                'tenant_id' => $contract->tenant_id,
                'contract_id' => $contract->id,
            ],
            [
                'branch_id' => $attributes['branch_id'] ?? $contract->branch_id ?: $contract->reservation?->car?->branch_id,
                'reservation_id' => $contract->reservation_id,
                'car_id' => $attributes['car_id'] ?? $contract->reservation?->car?->id,
                'damage_report_id' => $attributes['damage_report_id'] ?? null,
                'created_by' => $attributes['created_by'] ?? null,
                'report_number' => $existingReport?->report_number ?: $this->generateReturnReportNumber((int) $contract->tenant_id),
                'status' => $attributes['status'] ?? 'draft',
                'payment_status' => $attributes['payment_status'] ?? 'not_paid',
                'has_damage' => array_key_exists('has_damage', $attributes)
                    ? $attributes['has_damage']
                    : ($existingReport?->has_damage ?? null),
                'actual_return_time' => $attributes['actual_return_time'] ?? $existingReport?->actual_return_time,
                'return_location' => $attributes['return_location'] ?? $existingReport?->return_location,
                'return_odometer' => array_key_exists('return_odometer', $attributes) ? $attributes['return_odometer'] : $existingReport?->return_odometer,
                'return_fuel_level' => array_key_exists('return_fuel_level', $attributes) ? $attributes['return_fuel_level'] : $existingReport?->return_fuel_level,
                'vehicle_condition_after' => $attributes['vehicle_condition_after'] ?? $existingReport?->vehicle_condition_after,
                'extra_kilometers' => $attributes['extra_kilometers'] ?? $existingReport?->extra_kilometers ?? 0,
                'kilometer_rate' => $attributes['kilometer_rate'] ?? $existingReport?->kilometer_rate ?? 0,
                'cleaning_fee' => $attributes['cleaning_fee'] ?? $existingReport?->cleaning_fee ?? 0,
                'fuel_fee' => $attributes['fuel_fee'] ?? $existingReport?->fuel_fee ?? 0,
                'fuel_credit' => $attributes['fuel_credit'] ?? $existingReport?->fuel_credit ?? 0,
                'late_hours' => $attributes['late_hours'] ?? $existingReport?->late_hours ?? 0,
                'late_hour_rate' => $attributes['late_hour_rate'] ?? $existingReport?->late_hour_rate ?? 0,
                'damage_fee' => $attributes['damage_fee'] ?? $existingReport?->damage_fee ?? 0,
                'maintenance_fee' => $attributes['maintenance_fee'] ?? $existingReport?->maintenance_fee ?? 0,
                'other_fee' => $attributes['other_fee'] ?? $existingReport?->other_fee ?? 0,
                'discount' => $attributes['discount'] ?? $existingReport?->discount ?? 0,
                'total_extra_charges' => $attributes['total_extra_charges'] ?? $existingReport?->total_extra_charges ?? 0,
                'notes' => $attributes['notes'] ?? $existingReport?->notes,
            ]
        );

        return $report->fresh();
    }

    private function syncReturnStatusReportPayment(Contract $contract, ContractReturnReport $report, string $paymentStatus): void
    {
        $totalExtraCharges = $this->normalizeMoney($report->total_extra_charges ?? 0);

        if ($totalExtraCharges <= 0) {
            if ($report->payment_id) {
                $payment = Payment::query()
                    ->where('tenant_id', $contract->tenant_id)
                    ->find($report->payment_id);

                if ($payment && $payment->status !== PaymentStatus::COMPLETED) {
                    $payment->forceFill([
                        'status' => PaymentStatus::CANCELLED,
                        'processed_at' => null,
                        'notes' => trim(sprintf(
                            'Return status report %s no longer has payable extra charges.',
                            $report->report_number
                        )),
                    ])->save();
                }

                $report->forceFill(['payment_id' => null])->save();
            }

            return;
        }

        $payment = $report->payment_id
            ? Payment::query()->where('tenant_id', $contract->tenant_id)->find($report->payment_id)
            : null;

        if (!$payment) {
            $payment = new Payment();
            $payment->tenant_id = $contract->tenant_id;
            $payment->reservation_id = $contract->reservation_id;
            $payment->user_id = $contract->reservation?->user_id;
        }

        $payment->forceFill([
            'amount' => $totalExtraCharges,
            'currency' => strtoupper((string) ($contract->currency ?: config('app.currency_code', 'USD'))),
            'payment_method' => PaymentMethod::CASH,
            'status' => $paymentStatus === 'paid'
                ? PaymentStatus::COMPLETED
                : PaymentStatus::PENDING,
            'processed_at' => $paymentStatus === 'paid' ? now() : null,
            'notes' => trim(sprintf(
                'Return status report %s for contract %s%s.',
                $report->report_number,
                $contract->contract_number,
                $paymentStatus === 'paid' ? '' : ' pending settlement'
            )),
        ])->save();

        $report->forceFill(['payment_id' => $payment->id])->save();
    }

    private function deleteDraftDamageReportForHandover(Contract $contract, string $reportType = 'after_return'): void
    {
        $draftReports = CarDamageReport::query()
            ->where('tenant_id', $contract->tenant_id)
            ->where('contract_id', $contract->id)
            ->where('report_type', $reportType)
            ->where('status', 'draft')
            ->get();

        foreach ($draftReports as $draftReport) {
            $draftReport->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(CarDamageReport $report, array $items, array $deletedItemIds = []): void
    {
        $deletedItemIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): int => is_numeric($value) ? (int) $value : 0,
            $deletedItemIds
        ))));

        if ($deletedItemIds !== []) {
            CarDamageItem::query()
                ->where('tenant_id', $report->tenant_id)
                ->where('car_damage_report_id', $report->id)
                ->whereIn('id', $deletedItemIds)
                ->delete();
        }

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemId = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null;
            if ($itemId !== null && in_array($itemId, $deletedItemIds, true)) {
                continue;
            }

            $attributes = [
                'tenant_id' => $report->tenant_id,
                'zone_code' => $item['zone_code'],
                'view_side' => $item['view_side'],
                'damage_type' => $item['damage_type'],
                'severity' => $item['severity'],
                'damage_timing' => $item['damage_timing'] ?? ($report->report_type === 'after_return' ? 'after_return' : 'before_pickup'),
                'quantity' => (int) $item['quantity'],
                'marker_x' => $item['marker_x'] ?? null,
                'marker_y' => $item['marker_y'] ?? null,
                'estimated_cost' => $item['estimated_cost'] ?? null,
                'notes' => $item['notes'] ?? null,
                'sort_order' => $index,
            ];

            if ($itemId !== null) {
                $existingItem = CarDamageItem::query()
                    ->where('tenant_id', $report->tenant_id)
                    ->where('car_damage_report_id', $report->id)
                    ->whereKey($itemId)
                    ->first();

                if ($existingItem) {
                    $existingItem->forceFill($attributes)->saveQuietly();
                    continue;
                }
            }

            $report->items()->create($attributes);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncDamageCases(
        CarDamageReport $report,
        array $items,
        Car $car,
        Contract $contract,
        Reservation $reservation,
        ?int $userId
    ): void {
        foreach (array_values($items) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $existingCase = CarDamageCase::query()
                ->where('tenant_id', $report->tenant_id)
                ->where('car_id', $car->id)
                ->where('zone_code', $item['zone_code'])
                ->where('damage_type', $item['damage_type'])
                ->whereIn('status', ['open', 'in_repair'])
                ->orderBy('id')
                ->first();

            $payload = [
                'branch_id' => $car->branch_id,
                'opened_in_contract_id' => $existingCase?->opened_in_contract_id ?: $contract->id,
                'opened_in_reservation_id' => $existingCase?->opened_in_reservation_id ?: $reservation->id,
                'last_report_id' => $report->id,
                'created_by' => $existingCase?->created_by ?: $userId,
                'zone_code' => $item['zone_code'],
                'view_side' => $item['view_side'],
                'damage_type' => $item['damage_type'],
                'severity' => $item['severity'],
                'damage_timing' => $item['damage_timing'] ?? ($report->report_type === 'after_return' ? 'after_return' : 'before_pickup'),
                'quantity' => (int) $item['quantity'],
                'marker_x' => $item['marker_x'] ?? null,
                'marker_y' => $item['marker_y'] ?? null,
                'estimated_cost' => $item['estimated_cost'] ?? null,
                'notes' => $item['notes'] ?? null,
                'status' => 'open',
                'first_detected_at' => $existingCase?->first_detected_at ?: ($report->inspected_at ?? now()),
                'last_detected_at' => $report->inspected_at ?? now(),
            ];

            if ($existingCase) {
                $existingCase->update($payload);
                continue;
            }

            CarDamageCase::create([
                'tenant_id' => $report->tenant_id,
                'car_id' => $car->id,
                ...$payload,
            ]);
        }
    }

    private function handoverStepForPage(int $page): array
    {
        return match ($page) {
            1 => ['key' => 'customer_review', 'label' => 'Customer Review'],
            2 => ['key' => 'report_upload', 'label' => 'Report Upload'],
            3 => ['key' => 'damage_photo_upload', 'label' => 'Damage Photos'],
            4 => ['key' => 'vehicle_readings', 'label' => 'Vehicle Readings'],
            5 => ['key' => 'terms_confirmation', 'label' => 'Terms Confirmation'],
            6 => ['key' => 'damage_review', 'label' => 'Damage Review'],
            7 => ['key' => 'delivery_confirmation', 'label' => 'Delivery Confirmation'],
            default => throw ValidationException::withMessages([
                'page' => ['This handover page is not implemented yet. Use page 1, 2, 3, 4, 5, 6 or 7.'],
            ]),
        };
    }

    private function validateHandoverStepPayload(int $page, array $payload): array
    {
        return match ($page) {
            1 => Validator::make($payload, [
                'reviewed' => ['required', 'accepted'],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            2 => Validator::make($payload, [
                'document_type' => ['nullable', 'string', Rule::in($this->archiveDocumentTypes())],
                'documents' => ['nullable', 'array', 'min:1'],
                'documents.*.document_type' => ['nullable', 'string', Rule::in($this->archiveDocumentTypes())],
                'documents.*.temp_folders' => ['nullable', 'array'],
                'documents.*.temp_folders.*' => ['string'],
                'documents.*.files' => ['nullable', 'array'],
                'documents.*.files.*' => ['file', 'max:10240'],
                'documents.*.notes' => ['nullable', 'string', 'max:5000'],
                'temp_folders' => ['nullable', 'array'],
                'temp_folders.*' => ['string'],
                'files' => ['nullable', 'array'],
                'files.*' => ['file', 'max:10240'],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            3 => Validator::make($payload, [
                'report_type' => ['nullable', 'string', Rule::in(['before_delivery', 'after_return'])],
                'photos' => ['nullable', 'array', 'min:1'],
                'photos.*.view_side' => ['nullable', 'string', Rule::in(array_column(CarDamageCatalog::viewSides(), 'value'))],
                'photos.*.photo_type' => ['nullable', 'string', Rule::in(['damage', 'odometer', 'fuel'])],
                'photos.*.temp_folders' => ['nullable', 'array'],
                'photos.*.temp_folders.*' => ['string'],
                'photos.*.files' => ['nullable', 'array'],
                'photos.*.files.*' => ['file', 'max:10240'],
                'photos.*.notes' => ['nullable', 'string', 'max:5000'],
                'temp_folders' => ['nullable', 'array'],
                'temp_folders.*' => ['string'],
                'files' => ['nullable', 'array'],
                'files.*' => ['file', 'max:10240'],
                'view_side' => ['nullable', 'string', Rule::in(array_column(CarDamageCatalog::viewSides(), 'value'))],
                'photo_type' => ['nullable', 'string', Rule::in(['damage', 'odometer', 'fuel'])],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            4 => Validator::make($payload, [
                'reviewed' => ['nullable', 'boolean'],
                'vehicle_odometer' => ['nullable', 'integer', 'min:0'],
                'vehicle_fuel_level' => ['nullable', 'string', Rule::in(['empty', 'quarter', '1/4', 'half', '1/2', 'three_quarters', '3/4', 'full'])],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            5 => Validator::make($payload, [
                'accepted_terms' => ['required', 'accepted'],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            6 => Validator::make($payload, [
                'damage_report_id' => ['nullable', 'integer', 'min:1'],
                'summary' => ['nullable', 'string', 'max:5000'],
                'has_damage' => ['nullable', 'boolean'],
                'items' => ['nullable', 'array'],
                'items.*.id' => ['nullable', 'integer', 'min:1'],
                'items.*.zone_code' => ['required', 'string', Rule::in(CarDamageCatalog::zoneCodes())],
                'items.*.view_side' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::viewSides(), 'value'))],
                'items.*.damage_type' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::damageTypes(), 'value'))],
                'items.*.severity' => ['required', 'string', Rule::in(array_column(CarDamageCatalog::severityLevels(), 'value'))],
                'items.*.damage_timing' => ['nullable', Rule::in(['before_pickup', 'after_return'])],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
                'items.*.marker_x' => ['nullable', 'numeric', 'min:0'],
                'items.*.marker_y' => ['nullable', 'numeric', 'min:0'],
                'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
                'items.*.notes' => ['nullable', 'string', 'max:2000'],
                'deleted_item_ids' => ['nullable', 'array'],
                'deleted_item_ids.*' => ['integer', 'min:1'],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            7 => Validator::make($payload, [
                'delivery_confirmed' => ['required', 'accepted'],
                'note' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            default => throw ValidationException::withMessages([
                'page' => ['This handover page is not implemented yet. Use page 1, 2, 3, 4, 5, 6 or 7.'],
            ]),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function normalizeHandoverPageTwoPayload(Request $request, array $payload): array
    {
        $documents = is_array($payload['documents'] ?? null) ? array_values($payload['documents']) : [];
        $uploadedDocuments = data_get($request->allFiles(), 'documents', []);
        $topLevelFiles = array_values(array_filter(
            is_array($request->file('files', [])) ? $request->file('files', []) : [],
            static fn ($file): bool => $file instanceof UploadedFile
        ));

        if ($documents === []) {
            $documents = [[
                'document_type' => $payload['document_type'] ?? 'other',
                'temp_folders' => is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : [],
                'files' => $topLevelFiles,
                'notes' => $payload['notes'] ?? $payload['note'] ?? null,
            ]];
        }

        foreach ($documents as $index => $document) {
            $documentFiles = data_get($uploadedDocuments, "{$index}.files", []);
            $documentFiles = array_values(array_filter(
                is_array($documentFiles) ? $documentFiles : [],
                static fn ($file): bool => $file instanceof UploadedFile
            ));

            $documents[$index] = [
                'document_type' => $document['document_type'] ?? 'other',
                'temp_folders' => array_values(array_filter(
                    is_array($document['temp_folders'] ?? null) ? $document['temp_folders'] : [],
                    static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
                )),
                'files' => $documentFiles,
                'notes' => $document['notes'] ?? null,
            ];
        }

        $payload['documents'] = $documents;
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $stepPayload
     * @return array<int, array{document_type: string, temp_folders: array<int, string>, notes: mixed}>
     */
    private function prepareHandoverDocumentsForExtraction(Request $request, array $stepPayload): array
    {
        $documents = is_array($stepPayload['documents'] ?? null)
            ? $stepPayload['documents']
            : [];

        if ($documents === []) {
            $documents = [[
                'document_type' => $stepPayload['document_type'] ?? 'other',
                'temp_folders' => $stepPayload['temp_folders'] ?? [],
                'files' => data_get($request->allFiles(), 'files', []),
                'notes' => $stepPayload['notes'] ?? null,
            ]];
        }

        $normalized = [];

        foreach ($documents as $index => $document) {
            $tempFolders = array_values(array_filter(
                is_array($document['temp_folders'] ?? null) ? $document['temp_folders'] : [],
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            ));

            $uploadedFiles = array_values(array_filter(
                is_array($document['files'] ?? null) ? $document['files'] : [],
                static fn ($file): bool => $file instanceof UploadedFile
            ));

            foreach ($this->storeUploadedHandoverFiles($uploadedFiles) as $folder) {
                $tempFolders[] = $folder;
            }

            $tempFolders = array_values(array_unique(array_filter(
                $tempFolders,
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            )));

            if ($tempFolders === []) {
                throw ValidationException::withMessages([
                    "documents.$index.temp_folders" => ['At least one uploaded file or temp folder is required for each document.'],
                ]);
            }

            $normalized[] = [
                'document_type' => (string) ($document['document_type'] ?? 'other'),
                'temp_folders' => $tempFolders,
                'notes' => $document['notes'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeHandoverPageThreePayload(Request $request, array $payload): array
    {
        $photos = is_array($payload['photos'] ?? null) ? $payload['photos'] : [];
        $uploadedPhotos = data_get($request->allFiles(), 'photos', []);
        $topLevelFiles = array_values(array_filter(
            is_array($request->file('files', [])) ? $request->file('files', []) : [],
            static fn ($file): bool => $file instanceof UploadedFile
        ));

        if ($photos === []) {
            $photos = [[
                'view_side' => $payload['view_side'] ?? 'front',
                'photo_type' => $payload['photo_type'] ?? 'damage',
                'temp_folders' => is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : [],
                'files' => $topLevelFiles,
                'notes' => $payload['notes'] ?? $payload['note'] ?? null,
            ]];
        }

        $normalizedPhotos = [];
        foreach ($photos as $index => $photo) {
            $photoFiles = data_get($uploadedPhotos, "{$index}.files", []);
            $photoFiles = array_values(array_filter(
                is_array($photoFiles) ? $photoFiles : [],
                static fn ($file): bool => $file instanceof UploadedFile
            ));

            $normalizedPhotos[$index] = [
                'view_side' => $photo['view_side'] ?? 'front',
                'photo_type' => $photo['photo_type'] ?? 'damage',
                'temp_folders' => array_values(array_filter(
                    is_array($photo['temp_folders'] ?? null) ? $photo['temp_folders'] : [],
                    static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
                )),
                'files' => $photoFiles,
                'notes' => $photo['notes'] ?? null,
            ];
        }

        $payload['photos'] = array_values($normalizedPhotos);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeHandoverPageFourPayload(Request $request, array $payload): array
    {
        if (array_key_exists('vehicle_odometer', $payload) || $request->has('vehicle_odometer')) {
            $payload['vehicle_odometer'] = $payload['vehicle_odometer'] ?? $request->input('vehicle_odometer');
        }

        if (array_key_exists('vehicle_fuel_level', $payload) || $request->has('vehicle_fuel_level')) {
            $payload['vehicle_fuel_level'] = $payload['vehicle_fuel_level'] ?? $request->input('vehicle_fuel_level');
        }

        if (array_key_exists('reviewed', $payload) || $request->has('reviewed')) {
            $payload['reviewed'] = (bool) ($payload['reviewed'] ?? $request->boolean('reviewed', false));
        }

        if (array_key_exists('note', $payload) || $request->has('note')) {
            $payload['note'] = $payload['note'] ?? $request->input('note');
        }

        if (array_key_exists('notes', $payload) || $request->has('notes')) {
            $payload['notes'] = $payload['notes'] ?? $request->input('notes');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeHandoverPageFivePayload(Request $request, array $payload): array
    {
        if (array_key_exists('accepted_terms', $payload) || $request->has('accepted_terms')) {
            $payload['accepted_terms'] = $request->boolean('accepted_terms', (bool) ($payload['accepted_terms'] ?? false));
        }

        if (array_key_exists('note', $payload) || $request->has('note')) {
            $payload['note'] = $payload['note'] ?? $request->input('note');
        }

        if (array_key_exists('notes', $payload) || $request->has('notes')) {
            $payload['notes'] = $payload['notes'] ?? $request->input('notes');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeHandoverPageSixPayload(Request $request, array $payload): array
    {
        if (array_key_exists('has_damage', $payload) || $request->has('has_damage')) {
            $payload['has_damage'] = $request->has('has_damage')
                ? $request->boolean('has_damage')
                : $payload['has_damage'];
        }

        if (array_key_exists('summary', $payload) || $request->has('summary')) {
            $payload['summary'] = $payload['summary'] ?? $request->input('summary');
        }

        if (array_key_exists('items', $payload) || $request->has('items')) {
            $payload['items'] = $payload['items'] ?? $request->input('items');
        }

        if (array_key_exists('deleted_item_ids', $payload) || $request->has('deleted_item_ids')) {
            $payload['deleted_item_ids'] = $payload['deleted_item_ids'] ?? $request->input('deleted_item_ids');
        }

        if (array_key_exists('note', $payload) || $request->has('note')) {
            $payload['note'] = $payload['note'] ?? $request->input('note');
        }

        if (array_key_exists('notes', $payload) || $request->has('notes')) {
            $payload['notes'] = $payload['notes'] ?? $request->input('notes');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeHandoverPageSevenPayload(Request $request, array $payload): array
    {
        if (array_key_exists('delivery_confirmed', $payload) || $request->has('delivery_confirmed')) {
            $payload['delivery_confirmed'] = $request->boolean('delivery_confirmed', (bool) ($payload['delivery_confirmed'] ?? false));
        }

        if (array_key_exists('note', $payload) || $request->has('note')) {
            $payload['note'] = $payload['note'] ?? $request->input('note');
        }

        if (array_key_exists('notes', $payload) || $request->has('notes')) {
            $payload['notes'] = $payload['notes'] ?? $request->input('notes');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $stepPayload
     * @return array<int, array{view_side: string, photo_type: string, temp_folders: array<int, string>, notes: mixed}>
     */
    private function prepareHandoverInspectionPhotosForExtraction(Request $request, array $stepPayload, bool $allowDefaultPhotoGroup = true): array
    {
        $photos = is_array($stepPayload['photos'] ?? null)
            ? $stepPayload['photos']
            : [];

        if ($photos === [] && $allowDefaultPhotoGroup) {
            $photos = [[
                'view_side' => $stepPayload['view_side'] ?? 'front',
                'temp_folders' => $stepPayload['temp_folders'] ?? [],
                'files' => data_get($request->allFiles(), 'files', []),
                'notes' => $stepPayload['notes'] ?? null,
            ]];
        }

        $normalized = [];

        foreach ($photos as $index => $photo) {
            $viewSide = $this->normalizeDamagePhotoViewSide($photo['view_side'] ?? null);
            $tempFolders = array_values(array_filter(
                is_array($photo['temp_folders'] ?? null) ? $photo['temp_folders'] : [],
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            ));

            $uploadedFiles = array_values(array_filter(
                is_array($photo['files'] ?? null) ? $photo['files'] : [],
                static fn ($file): bool => $file instanceof UploadedFile
            ));

            foreach ($this->storeUploadedHandoverFiles($uploadedFiles) as $folder) {
                $tempFolders[] = $folder;
            }

            $tempFolders = array_values(array_unique(array_filter(
                $tempFolders,
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            )));

            if ($tempFolders === []) {
                throw ValidationException::withMessages([
                    "photos.$index.temp_folders" => ['At least one uploaded file or temp folder is required for each photo group.'],
                ]);
            }

            $normalized[] = [
                'view_side' => $viewSide,
                'photo_type' => $this->normalizeInspectionPhotoType($photo['photo_type'] ?? null),
                'temp_folders' => $tempFolders,
                'notes' => $photo['notes'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{document_type: string, temp_folders: array<int, string>, notes: mixed}>  $documents
     * @return array{
     *   documents: array<int, array<string, mixed>>,
     *   merged_fields: array<string, mixed>,
     *   raw_output: array<int, mixed>,
     *   text_preview: array<int, string>,
     * }
     */
    private function extractHandoverDocuments(array $documents): array
    {
        $enrichedDocuments = [];
        $mergedFields = [];
        $rawOutput = [];
        $textPreview = [];

        foreach ($documents as $index => $document) {
            $documentType = (string) ($document['document_type'] ?? 'other');
            $tempFolders = array_values(array_filter(
                is_array($document['temp_folders'] ?? null) ? $document['temp_folders'] : [],
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            ));

            if ($tempFolders === []) {
                throw ValidationException::withMessages([
                    "documents.$index.temp_folders" => ['At least one uploaded file or temp folder is required for each document.'],
                ]);
            }

            $result = $this->contractDriverDocumentExtractor->extractFromTempFolders($tempFolders, $documentType);
            $fields = is_array($result['fields'] ?? null) ? $result['fields'] : [];

            $mergedFields = array_replace($mergedFields, $fields);
            $rawOutput[] = $result['raw_output'] ?? null;
            $textPreview[] = (string) ($result['raw_text'] ?? '');

            $enrichedDocuments[] = [
                'document_type' => $documentType,
                'temp_folders' => $tempFolders,
                'notes' => $document['notes'] ?? null,
                'extraction_status' => 'extracted',
                'extracted_fields' => $fields,
                'raw_output' => $result['raw_output'] ?? null,
                'raw_text' => $result['raw_text'] ?? '',
                'confidence' => $result['confidence'] ?? null,
                'provider' => $result['provider'] ?? null,
                'engine' => $result['engine'] ?? null,
            ];
        }

        return [
            'documents' => $enrichedDocuments,
            'merged_fields' => $mergedFields,
            'raw_output' => $rawOutput,
            'text_preview' => array_values(array_filter($textPreview, static fn (string $text): bool => trim($text) !== '')),
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    private function storeUploadedHandoverFiles(array $files): array
    {
        $folders = [];

        foreach ($files as $file) {
            $folders[] = $this->storeHandoverTempFile($file);
        }

        return $folders;
    }

    private function storeHandoverTempFile(UploadedFile $file): string
    {
        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $tempPath = trim((string) config('vilt-filepond.temp_path', 'temp-files'), '/');
        $folder = 'handover-'.Str::uuid()->toString();

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) $file->extension());
        }

        $filename = (string) Str::uuid();
        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $relativePath = $disk->putFileAs($tempPath.'/'.$folder, $file, $filename);
        if (!is_string($relativePath) || $relativePath === '') {
            throw ValidationException::withMessages([
                'documents' => ['Unable to store the uploaded document. Please try again.'],
            ]);
        }

        TempFile::query()->create([
            'original_name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'path' => $relativePath,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'folder' => $folder,
            'is_chunked' => false,
        ]);

        return $folder;
    }

    /**
     * Remove temporary OCR files after a successful extraction so the images are not persisted
     * in contract driver document slots or additional driver sections.
     *
     * @param  array<int, array{temp_folders?: array<int, string>}>  $documents
     */
    private function cleanupHandoverTempFolders(array $documents): void
    {
        $folders = [];

        foreach ($documents as $document) {
            foreach (is_array($document['temp_folders'] ?? null) ? $document['temp_folders'] : [] as $folder) {
                $folder = trim((string) $folder);
                if ($folder !== '') {
                    $folders[] = $folder;
                }
            }
        }

        $folders = array_values(array_unique($folders));
        if ($folders === []) {
            return;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $tempPath = trim((string) config('vilt-filepond.temp_path', 'temp-files'), '/');

        foreach ($folders as $folder) {
            $disk->deleteDirectory($tempPath.'/'.$folder);
        }

        TempFile::query()
            ->whereIn('folder', $folders)
            ->delete();
    }

    private function archiveDocumentTypes(): array
    {
        return [
            'passport',
            'id_card',
            'residency_card',
            'driver_license',
            'visa',
            'insurance',
            'other',
        ];
    }

    private function contractInputsPayload(Contract $contract): array
    {
        return [
            'contract_number' => $contract->contract_number,
            'status' => $this->contractStatusValue($contract->status),
            'contract_date' => optional($contract->contract_date)->toDateString(),
            'renter_name' => $contract->renter_name,
            'renter_id_number' => $contract->renter_id_number,
            'renter_phone' => $contract->renter_phone,
            'car_details' => $contract->car_details,
            'plate_number' => $contract->plate_number,
            'vehicle_odometer' => $contract->vehicle_odometer,
            'vehicle_fuel_level' => $contract->vehicle_fuel_level,
            'return_odometer' => $contract->return_odometer,
            'return_fuel_level' => $contract->return_fuel_level,
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => optional($contract->end_date)->toDateString(),
            'total_amount' => $contract->total_amount !== null ? (float) $contract->total_amount : null,
            'currency' => $contract->currency,
            'notes' => $contract->notes,
        ];
    }

    private function createDraftContractForReservation(Reservation $reservation): Contract
    {
        $reservation->loadMissing([
            'user:id,name,email,is_active',
            'car:id,branch_id,year,make,model,license_plate,mileage,price_per_day,price_per_week,price_per_month,allowed_km_per_day,allowed_km_per_week,allowed_km_per_month',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => $reservation->tenant_id,
            'branch_id' => $reservation->car?->branch_id,
            'reservation_id' => $reservation->id,
            'contract_number' => $this->generateContractNumber((int) $reservation->tenant_id),
            'status' => ContractStatus::DRAFT->value,
            'contract_date' => now()->toDateString(),
            'renter_name' => $reservation->user?->name,
            'renter_id_number' => null,
            'renter_phone' => null,
            'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
            'plate_number' => $reservation->car?->license_plate,
            'vehicle_odometer' => $reservation->car?->mileage,
            'vehicle_fuel_level' => null,
            'price_per_day' => $reservation->car?->price_per_day,
            'price_per_week' => $reservation->car?->price_per_week,
            'price_per_month' => $reservation->car?->price_per_month,
            'allowed_km_per_day' => $reservation->car?->allowed_km_per_day,
            'allowed_km_per_week' => $reservation->car?->allowed_km_per_week,
            'allowed_km_per_month' => $reservation->car?->allowed_km_per_month,
            'return_odometer' => null,
            'return_fuel_level' => null,
            'actual_return_time' => null,
            'start_date' => optional($reservation->start_date)->toDateString(),
            'end_date' => optional($reservation->end_date)->toDateString(),
            'total_amount' => $reservation->total_amount,
            'currency' => strtoupper((string) config('app.currency_code', 'USD')),
            'notes' => null,
            'ai_extracted_data' => null,
            'ai_extraction_status' => AiAutomationSettings::isContractsExtractionEnabled() ? 'not_requested' : 'disabled',
            'handover_state' => null,
        ]);

        return $contract->fresh();
    }

    private function applyExtractedContractFields(Contract $contract, array $fields): array
    {
        $fields = $this->normalizeContractExtractionFields($fields);

        $mapping = [
            'contract_number' => 'contract_number',
            'status' => 'status',
            'contract_date' => 'contract_date',
            'renter_name' => 'renter_name',
            'renter_id_number' => 'renter_id_number',
            'renter_phone' => 'renter_phone',
            'car_details' => 'car_details',
            'plate_number' => 'plate_number',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
            'total_amount' => 'total_amount',
            'currency' => 'currency',
            'notes' => 'notes',
        ];

        $applied = [];
        foreach ($mapping as $contractField => $fieldKey) {
            if (!array_key_exists($fieldKey, $fields)) {
                continue;
            }

            $value = $fields[$fieldKey];
            if ($value === null || $value === '') {
                continue;
            }

            $applied[$contractField] = $value;
        }

        if (!empty($applied)) {
            $contract->forceFill($applied);
        }

        return $applied;
    }

    /**
     * Persist extracted OCR values to the primary driver record used by the contract edit form.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $extraction
     */
    private function ensurePrimaryDriverExists(Contract $contract): ContractDriver
    {
        $driver = $contract->primaryDriver()->first();
        if ($driver) {
            return $driver;
        }

        $driver = new ContractDriver([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'role' => 'primary',
            'sort_order' => 0,
        ]);

        $driver->saveQuietly();

        return $driver;
    }

    private function syncPrimaryDriverFromExtraction(Contract $contract, array $fields, array $extraction): ContractDriver
    {
        $fields = $this->normalizeContractExtractionFields($fields);

        $driver = $this->ensurePrimaryDriverExists($contract);

        $driver->forceFill([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'role' => 'primary',
            'sort_order' => 0,
            'client_id' => $contract->reservation?->user?->id,
            'full_name' => $this->nullableString($fields['full_name'] ?? null)
                ?? $this->nullableString($contract->renter_name ?? null)
                ?? $contract->reservation?->user?->name,
            'full_name_ar' => $this->nullableString($fields['full_name_ar'] ?? null),
            'phone' => $this->nullableString($fields['phone'] ?? null) ?? $this->nullableString($contract->renter_phone ?? null),
            'nationality' => $this->nullableString($fields['nationality'] ?? null),
            'place_of_issue' => $this->nullableString($fields['place_of_issue'] ?? null),
            'date_of_birth' => $this->nullableString($fields['date_of_birth'] ?? null),
            'identity_number' => $this->nullableString($fields['renter_id_number'] ?? null) ?? $this->nullableString($fields['identity_number'] ?? null),
            'passport_number' => $this->nullableString($fields['passport_number'] ?? null),
            'passport_expiry_date' => $this->nullableString($fields['passport_expiry_date'] ?? null),
            'visa_number' => $this->nullableString($fields['visa_number'] ?? null),
            'visa_expiry_date' => $this->nullableString($fields['visa_expiry_date'] ?? null),
            'residency_number' => $this->nullableString($fields['residency_number'] ?? null),
            'license_number' => $this->nullableString($fields['license_number'] ?? null),
            'license_issue_date' => $this->nullableString($fields['license_issue_date'] ?? null),
            'identity_expiry_date' => $this->nullableString($fields['identity_expiry_date'] ?? null),
            'license_expiry_date' => $this->nullableString($fields['license_expiry_date'] ?? null),
            'extraction_status' => 'extracted',
            'extracted_data' => !empty($fields) ? $fields : null,
            'raw_output' => is_array($extraction['raw_output'] ?? null) ? $extraction['raw_output'] : null,
            'confidence' => isset($extraction['documents'][0]['confidence']) && is_numeric($extraction['documents'][0]['confidence'])
                ? (float) $extraction['documents'][0]['confidence']
                : null,
            'ai_reviewed' => false,
            'notes' => $this->nullableString($contract->notes ?? null),
        ]);

        $driver->saveQuietly();

        return $driver;
    }

    /**
     * Persist the uploaded handover files into the same storage structures used by the
     * contract edit screen: the first image goes to the primary driver's main document slot
     * and the rest go to the contract archive.
     *
     * @param  array<int, array<string, mixed>>  $documents
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    private function persistHandoverDocumentFiles(Contract $contract, array $documents, ContractDriver $primaryDriver): array
    {
        $folders = [];
        $persisted = [];

        foreach ($documents as $document) {
            $documentType = $this->nullableString($document['document_type'] ?? null) ?? 'other';
            $tempFolders = array_values(array_filter(
                is_array($document['temp_folders'] ?? null) ? $document['temp_folders'] : [],
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            ));

            foreach ($tempFolders as $folder) {
                $folders[] = [
                    'folder' => (string) $folder,
                    'document_type' => $documentType,
                    'notes' => $document['notes'] ?? null,
                ];
            }
        }

        if ($folders === []) {
            return [];
        }

        $primaryDocument = array_shift($folders);
        if ($primaryDocument) {
            $document = $this->moveTempFileToHandoverDriverDocument(
                $primaryDriver,
                (string) $primaryDocument['folder'],
                (string) $primaryDocument['document_type']
            );

            if ($document instanceof ContractDriverDocument) {
                $persisted[] = [
                    'source' => 'driver_document',
                    'storage_target' => 'primary_driver',
                    'document_id' => $document->id,
                    'document_type' => $document->document_type,
                    'file_path' => $document->file_path,
                    'file_name' => $document->file_name,
                    'mime_type' => $document->mime_type,
                    'url' => $this->storageUrl($document->file_path),
                    'extraction_status' => 'pending',
                ];
            }
        }

        foreach ($folders as $archiveIndex => $archiveInput) {
            $archiveFile = $this->moveTempFileToHandoverArchiveFile(
                $contract,
                (string) $archiveInput['folder'],
                (string) $archiveInput['document_type'],
                $archiveInput['notes'] ?? null
            );

            if ($archiveFile instanceof ContractArchiveFile) {
                $persisted[] = [
                    'source' => 'archive_file',
                    'storage_target' => 'additional_archive',
                    'archive_file_id' => $archiveFile->id,
                    'document_type' => $archiveFile->document_type,
                    'title' => $archiveFile->title,
                    'notes' => $archiveFile->notes,
                    'file_path' => $archiveFile->file_path,
                    'file_name' => $archiveFile->file_name,
                    'mime_type' => $archiveFile->mime_type,
                    'url' => $this->storageUrl($archiveFile->file_path),
                    'extraction_status' => 'pending',
                ];
            }
        }

        return $persisted;
    }

    /**
     * @param  array<int, array{view_side: string, photo_type: string, temp_folders: array<int, string>, notes: mixed}>  $photos
     * @return array<int, array<string, mixed>>
     */
    private function persistHandoverHandoverPhotos(
        Contract $contract,
        CarDamageReport $damageReport,
        array $photos,
        string $phase
    ): array {
        $persisted = [];

        foreach ($photos as $photoIndex => $photo) {
            $photoType = $this->normalizeInspectionPhotoType($photo['photo_type'] ?? null);

            $tempFolders = array_values(array_filter(
                is_array($photo['temp_folders'] ?? null) ? $photo['temp_folders'] : [],
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            ));

            foreach ($tempFolders as $order => $folder) {
                $viewSide = $this->normalizeDamagePhotoViewSide($photo['view_side'] ?? null);
                $handoverPhoto = $this->moveTempFileToHandoverPhotoArchive(
                    $contract,
                    $damageReport,
                    (string) $folder,
                    $phase,
                    $photoType,
                    $viewSide,
                    $photo['notes'] ?? null,
                    $order + $photoIndex
                );

                if (!$handoverPhoto) {
                    continue;
                }

                $persisted[] = [
                    'source' => 'handover_photo',
                    'photo_type' => $handoverPhoto->photo_type,
                    'phase' => $handoverPhoto->phase,
                    'storage_target' => 'handover_archive',
                    'handover_photo_id' => $handoverPhoto->id,
                    'damage_report_id' => $handoverPhoto->damage_report_id,
                    'view_side' => $handoverPhoto->view_side,
                    'title' => $handoverPhoto->title,
                    'notes' => $handoverPhoto->notes,
                    'file_path' => $handoverPhoto->file_path,
                    'file_paths' => [$handoverPhoto->file_path],
                    'file_name' => $handoverPhoto->file_name,
                    'mime_type' => $handoverPhoto->mime_type,
                    'url' => $this->storageUrl($handoverPhoto->file_path),
                    'extraction_status' => $handoverPhoto->extraction_status,
                ];
            }
        }

        return $persisted;
    }

    /**
     * @param  array<int, array{view_side: string, photo_type: string, temp_folders: array<int, string>, notes: mixed}>  $photos
     * @return array<int, array{view_side: string, photo_type: string, file_paths: array<int, string>}>
     */
    private function prepareHandoverVehicleReadingPhotosForExtraction(array $photos): array
    {
        $normalized = [];

        foreach ($photos as $photo) {
            $photoType = $this->normalizeInspectionPhotoType($photo['photo_type'] ?? null);
            if (!in_array($photoType, ['odometer', 'fuel'], true)) {
                continue;
            }

            $viewSide = $this->normalizeDamagePhotoViewSide($photo['view_side'] ?? null);
            $tempFolders = array_values(array_filter(
                is_array($photo['temp_folders'] ?? null) ? $photo['temp_folders'] : [],
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            ));

            $uploadedFiles = array_values(array_filter(
                is_array($photo['files'] ?? null) ? $photo['files'] : [],
                static fn ($file): bool => $file instanceof UploadedFile
            ));

            foreach ($this->storeUploadedHandoverFiles($uploadedFiles) as $folder) {
                $tempFolders[] = $folder;
            }

            $filePaths = [];
            foreach ($tempFolders as $folder) {
                $tempFile = TempFile::query()->where('folder', $folder)->first();
                if (!$tempFile) {
                    continue;
                }

                $filePath = trim((string) $tempFile->path);
                if ($filePath !== '') {
                    $filePaths[] = $filePath;
                }
            }

            $filePaths = array_values(array_unique(array_filter(
                $filePaths,
                static fn (string $path): bool => trim($path) !== ''
            )));

            if ($filePaths === []) {
                continue;
            }

            $normalized[] = [
                'view_side' => $viewSide,
                'photo_type' => $photoType,
                'file_paths' => $filePaths,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{view_side: string, photo_type: string, temp_folders: array<int, string>, notes: mixed}>  $photos
     * @param  array<int, string>  $photoTypes
     */
    private function cleanupHandoverPhotoTempFolders(array $photos, array $photoTypes): void
    {
        $folders = [];

        foreach ($photos as $photo) {
            $photoType = $this->normalizeInspectionPhotoType($photo['photo_type'] ?? null);
            if (!in_array($photoType, $photoTypes, true)) {
                continue;
            }

            $tempFolders = array_values(array_filter(
                is_array($photo['temp_folders'] ?? null) ? $photo['temp_folders'] : [],
                static fn ($folder): bool => is_string($folder) && trim($folder) !== ''
            ));

            foreach ($tempFolders as $folder) {
                $folders[] = $folder;
            }
        }

        foreach (array_values(array_unique($folders)) as $folder) {
            $tempFile = TempFile::query()->where('folder', $folder)->first();
            if (!$tempFile) {
                continue;
            }

            $disk = Storage::disk(config('vilt-filepond.storage_disk'));
            $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
            $tempFile->delete();
        }
    }

    private function moveTempFileToHandoverDriverDocument(
        ContractDriver $driver,
        string $folder,
        string $documentType
    ): ?ContractDriverDocument {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (!$tempFile) {
            return null;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $extension = pathinfo((string) $tempFile->filename, PATHINFO_EXTENSION);
        $filename = 'contract_driver_document_'.$driver->id.'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $newPath = config('vilt-filepond.files_path').'/contractdriverdocument/'.$driver->id.'/'.$documentType.'/'.$filename;

        $disk->move($tempFile->path, $newPath);

        $document = ContractDriverDocument::create([
            'tenant_id' => $driver->tenant_id,
            'contract_driver_id' => $driver->id,
            'document_type' => $documentType,
            'side' => 'single',
            'file_path' => 'storage/'.$newPath,
            'file_name' => $tempFile->original_name,
            'mime_type' => $tempFile->mime_type,
            'ocr_status' => 'pending',
        ]);

        $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
        $tempFile->delete();

        return $document;
    }

    private function moveTempFileToHandoverArchiveFile(
        Contract $contract,
        string $folder,
        ?string $documentType = null,
        mixed $notes = null,
        ?string $title = null
    ): ?ContractArchiveFile {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (!$tempFile) {
            return null;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $extension = pathinfo((string) $tempFile->filename, PATHINFO_EXTENSION);
        $filename = 'contract_archive_'.$contract->id.'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $newPath = config('vilt-filepond.files_path').'/contractarchive/'.$contract->id.'/'.$filename;

        $disk->move($tempFile->path, $newPath);

        $archiveFile = ContractArchiveFile::create([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'contract_driver_id' => null,
            'document_type' => $documentType ?: 'other',
            'title' => $title ?: $tempFile->original_name,
            'notes' => $this->nullableString($notes),
            'file_path' => 'storage/'.$newPath,
            'file_name' => $tempFile->original_name,
            'mime_type' => $tempFile->mime_type,
        ]);

        $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
        $tempFile->delete();

        return $archiveFile;
    }

    private function moveTempFileToHandoverPhotoArchive(
        Contract $contract,
        CarDamageReport $damageReport,
        string $folder,
        string $phase,
        string $photoType,
        string $viewSide,
        mixed $notes = null,
        int $order = 0
    ): ?ContractHandoverPhoto {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (!$tempFile) {
            return null;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $extension = pathinfo((string) $tempFile->filename, PATHINFO_EXTENSION);
        $filename = 'contract_handover_photo_'.$contract->id.'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $newPath = config('vilt-filepond.files_path').'/contracthandover/'.$contract->id.'/'.$this->normalizeHandoverPhase($phase).'/'.$this->normalizeInspectionPhotoType($photoType).'/'.$filename;
        $normalizedPhase = $this->normalizeHandoverPhase($phase);
        $normalizedPhotoType = $this->normalizeInspectionPhotoType($photoType);
        $normalizedViewSide = $this->normalizeDamagePhotoViewSide($viewSide);
        $normalizedNotes = $this->nullableString($notes);
        $existingPhoto = ContractHandoverPhoto::query()
            ->where('contract_id', $contract->id)
            ->where('phase', $normalizedPhase)
            ->where('photo_type', $normalizedPhotoType)
            ->where('view_side', $normalizedViewSide)
            ->first();

        $disk->move($tempFile->path, $newPath);

        if ($existingPhoto) {
            $existingPath = ltrim((string) preg_replace('/^storage\//', '', (string) $existingPhoto->file_path), '/');
            $handoverPhoto = $existingPhoto;
            $handoverPhoto->forceFill([
                'damage_report_id' => $damageReport->id,
                'phase' => $normalizedPhase,
                'photo_type' => $normalizedPhotoType,
                'view_side' => $normalizedViewSide,
                'title' => $this->inspectionPhotoTitle($photoType),
                'notes' => $normalizedNotes,
                'file_path' => 'storage/'.$newPath,
                'file_name' => $tempFile->original_name,
                'mime_type' => $tempFile->mime_type,
                'extraction_status' => 'pending',
                'extracted_data' => null,
                'extracted_value' => null,
            ])->save();

            if ($existingPath !== '' && $existingPath !== ltrim('storage/'.$newPath, '/')) {
                $disk->delete($existingPath);
            }
        } else {
            $handoverPhoto = ContractHandoverPhoto::create([
                'tenant_id' => $contract->tenant_id,
                'contract_id' => $contract->id,
                'damage_report_id' => $damageReport->id,
                'phase' => $normalizedPhase,
                'photo_type' => $normalizedPhotoType,
                'view_side' => $normalizedViewSide,
                'title' => $this->inspectionPhotoTitle($photoType),
                'notes' => $normalizedNotes,
                'file_path' => 'storage/'.$newPath,
                'file_name' => $tempFile->original_name,
                'mime_type' => $tempFile->mime_type,
                'extraction_status' => 'pending',
                'extracted_data' => null,
                'extracted_value' => null,
            ]);
        }

        $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
        $tempFile->delete();

        return $handoverPhoto;
    }

    private function moveTempFileToDamageReportPhoto(
        CarDamageReport $damageReport,
        string $folder,
        string $collection,
        int $order = 0
    ): ?\MohamedGaldi\ViltFilepond\Models\File {
        $folder = trim($folder);
        if ($folder === '') {
            return null;
        }

        return $this->filePondService->moveTempFileToModel($damageReport, $folder, $collection, $order);
    }

    private function resolveOrCreateDraftDamageReportForHandover(
        Contract $contract,
        Request $request,
        string $phase = 'delivery',
        string $reportType = 'before_delivery'
    ): CarDamageReport
    {
        $state = $phase === 'return'
            ? $this->normalizeReturnHandoverState($contract->handover_state)
            : $this->normalizeHandoverState($contract->handover_state);

        $existingReportId = (int) data_get(
            $state,
            $phase === 'return'
                ? 'steps.return_inspection.payload.damage_report_id'
                : 'steps.damage_photo_upload.payload.damage_report_id',
            0
        );

        if ($existingReportId > 0) {
            $existing = CarDamageReport::query()->with(['files', 'items'])->find($existingReportId);
            if ($existing) {
                return $existing;
            }
        }

        $contract->loadMissing([
            'reservation.car:id,branch_id,year,make,model,license_plate',
            'reservation:id,user_id,car_id',
        ]);

        $existingDraft = CarDamageReport::query()
            ->where('tenant_id', $contract->tenant_id)
            ->where('contract_id', $contract->id)
            ->where('report_type', $reportType)
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        if ($existingDraft) {
            return $existingDraft;
        }

        return CarDamageReport::query()->create([
            'tenant_id' => $contract->tenant_id,
            'car_id' => $contract->reservation?->car_id,
            'branch_id' => $contract->branch_id ?: $contract->reservation?->car?->branch_id,
            'contract_id' => $contract->id,
            'reservation_id' => $contract->reservation_id,
            'created_by' => $request->user()?->id,
            'report_number' => $this->generateReportNumber(),
            'report_type' => $reportType,
            'status' => 'draft',
            'inspected_at' => now(),
            'odometer' => $contract->reservation?->car?->mileage,
            'summary' => null,
        ]);
    }

    private function normalizeDamagePhotoViewSide(mixed $value): string
    {
        $viewSide = strtolower(trim((string) ($value ?? '')));
        $allowed = array_column(CarDamageCatalog::viewSides(), 'value');

        return in_array($viewSide, $allowed, true) ? $viewSide : 'front';
    }

    private function normalizeInspectionPhotoType(mixed $value): string
    {
        $photoType = strtolower(trim((string) ($value ?? 'damage')));
        $allowed = ['damage', 'odometer', 'fuel'];

        return in_array($photoType, $allowed, true) ? $photoType : 'damage';
    }

    private function inspectionPhotoTitle(string $photoType): string
    {
        return match ($this->normalizeInspectionPhotoType($photoType)) {
            'odometer' => 'Odometer Photo',
            'fuel' => 'Fuel Photo',
            default => 'Damage Photo',
        };
    }

    private function damagePhotoCollection(string $viewSide): string
    {
        return 'damage_photo_'.$this->normalizeDamagePhotoViewSide($viewSide);
    }

    private function normalizeHandoverPhase(mixed $value): string
    {
        $phase = strtolower(trim((string) ($value ?? 'delivery')));

        return in_array($phase, ['delivery', 'return'], true) ? $phase : 'delivery';
    }

    private function handoverPhaseLabel(string $phase): string
    {
        return match ($this->normalizeHandoverPhase($phase)) {
            'return' => 'Return',
            default => 'Delivery',
        };
    }

    private function vehicleReadingsPayload(Contract $contract): array
    {
        return [
            'vehicle_odometer' => $contract->vehicle_odometer,
            'vehicle_fuel_level' => $contract->vehicle_fuel_level,
        ];
    }

    private function mobileSignatureTextForContract(Contract $contract, ?string $locale = null): ?string
    {
        $translated = trim((string) Lang::get('contracts.pdf.contract_texts.mobile_signature_text', [], $locale));
        if ($translated !== '') {
            return $translated;
        }

        $tenant = $contract->tenant?->loadMissing('siteSetting') ?? $contract->tenant;
        $settings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];
        $text = trim((string) data_get($settings, 'contract_pdf.mobile_signature_text', ''));

        if ($text === '') {
            $text = 'Please review the contract details on mobile and confirm before signing.';
        }

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<string, mixed>  $vehicleReadings
     * @return array{
     *   vehicle_odometer: int|null,
     *   vehicle_fuel_level: string|null,
     *   odometer_confidence: float|null,
     *   fuel_level_confidence: float|null
     * }
     */
    private function normalizeVehicleReadingsPayload(mixed $vehicleReadings): array
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

    private function damageReportPayload(CarDamageReport $damageReport): array
    {
        $damageReport->loadMissing(['files', 'items']);
        $comparison = $this->damageReportComparisonPayload($damageReport);
        $items = $damageReport->report_type === 'after_return'
            ? $this->afterReturnDamageItems($damageReport)
            : $damageReport->items->values();

        return [
            'id' => $damageReport->id,
            'report_number' => $damageReport->report_number,
            'report_type' => $damageReport->report_type,
            'status' => $damageReport->status,
            'summary' => $damageReport->summary,
            'inspected_at' => optional($damageReport->inspected_at)?->format('Y-m-d H:i'),
            'items_count' => $items->count(),
            'items' => $items->map(fn ($item): array => $this->damageReportItemPayload($item))->values()->all(),
            'photos' => $damageReport->files
                ->sortBy(['collection', 'order', 'id'])
                ->values()
                ->map(function ($file): array {
                    $collection = (string) ($file->collection ?? '');
                    $viewSide = str_starts_with($collection, 'damage_photo_')
                        ? substr($collection, strlen('damage_photo_'))
                        : 'front';

                    return [
                        'id' => $file->id,
                        'view_side' => $viewSide,
                        'collection' => $collection,
                        'file_path' => $file->path,
                        'file_name' => $file->original_name,
                        'mime_type' => $file->mime_type,
                        'url' => $file->url ?? $this->storageUrl($file->path),
                    ];
                })
                ->all(),
            'comparison' => $comparison,
            'merged_items' => $comparison['merged_items'] ?? $items->map(fn ($item): array => $this->damageReportItemPayload($item))->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\CarDamageItem>|array<int, \App\Models\CarDamageItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function damageReportItemsPayload(iterable $items): array
    {
        $payload = [];

        foreach ($items as $item) {
            if ($item instanceof \App\Models\CarDamageItem) {
                $payload[] = $this->damageReportItemPayload($item);
            }
        }

        return array_values($payload);
    }

    private function damageReportItemPayload(\App\Models\CarDamageItem $item): array
    {
        return [
            'id' => $item->id,
            'zone_code' => $item->zone_code,
            'view_side' => $item->view_side,
            'damage_type' => $item->damage_type,
            'severity' => $item->severity,
            'damage_timing' => $item->damage_timing,
            'quantity' => (int) $item->quantity,
            'marker_x' => $item->marker_x !== null ? (float) $item->marker_x : null,
            'marker_y' => $item->marker_y !== null ? (float) $item->marker_y : null,
            'estimated_cost' => $item->estimated_cost !== null ? (float) $item->estimated_cost : null,
            'notes' => $item->notes,
            'signature' => $this->damageReportItemSignature($item),
        ];
    }

    private function damageReportItemSignature(\App\Models\CarDamageItem $item): string
    {
        return implode('|', [
            mb_strtolower(trim((string) ($item->zone_code ?? ''))),
            mb_strtolower(trim((string) ($item->view_side ?? ''))),
            mb_strtolower(trim((string) ($item->damage_type ?? ''))),
            mb_strtolower(trim((string) ($item->severity ?? ''))),
            (string) max(1, (int) ($item->quantity ?? 1)),
        ]);
    }

    private function damageReportComparisonPayload(CarDamageReport $damageReport): array
    {
        $oppositeReportType = $damageReport->report_type === 'after_return' ? 'before_delivery' : 'after_return';

        $againstReport = CarDamageReport::query()
            ->withoutTenantScope()
            ->where('contract_id', $damageReport->contract_id)
            ->where('report_type', $oppositeReportType)
            ->orderByDesc('id')
            ->with(['items'])
            ->first();

        $currentItems = $this->damageReportItemsPayload($damageReport->items->values());

        if (! $againstReport) {
            return [
                'against_report' => null,
                'shared_items' => [],
                'items_only_in_current' => $currentItems,
                'items_only_in_against' => [],
                'merged_items' => $currentItems,
            ];
        }

        $againstItems = $this->damageReportItemsPayload($againstReport->items->values());
        $currentBySignature = collect($currentItems)->keyBy('signature');
        $againstBySignature = collect($againstItems)->keyBy('signature');

        $sharedItems = [];
        $currentOnlyItems = [];
        foreach ($currentItems as $item) {
            if ($againstBySignature->has($item['signature'])) {
                $sharedItems[] = $this->damageReportComparisonItemPayload($item, 'current', true);
            } else {
                $currentOnlyItems[] = $this->damageReportComparisonItemPayload($item, 'current', false);
            }
        }

        $againstOnlyItems = [];
        foreach ($againstItems as $item) {
            if (! $currentBySignature->has($item['signature'])) {
                $againstOnlyItems[] = $this->damageReportComparisonItemPayload($item, 'against', false);
            }
        }

        $mergedItems = array_values(array_merge(
            array_map(fn (array $item): array => $this->damageReportComparisonItemPayload($item, 'against', $currentBySignature->has($item['signature'])), $againstItems),
            $currentOnlyItems
        ));

        return [
            'against_report' => [
                'id' => $againstReport->id,
                'report_number' => $againstReport->report_number,
                'report_type' => $againstReport->report_type,
                'status' => $againstReport->status,
                'items_count' => $againstReport->items->count(),
            ],
            'shared_items' => $sharedItems,
            'items_only_in_current' => $currentOnlyItems,
            'items_only_in_against' => $againstOnlyItems,
            'merged_items' => $mergedItems,
        ];
    }

    private function damageReportComparisonItemPayload(array $item, string $source, bool $shared): array
    {
        $item['source'] = $source;
        $item['shared'] = $shared;

        return $item;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalizeContractExtractionFields(array $fields): array
    {
        $normalized = $fields;

        if (!array_key_exists('renter_name', $normalized)) {
            $fullName = $this->nullableString($normalized['full_name'] ?? null);
            if ($fullName !== null) {
                $normalized['renter_name'] = $fullName;
            }
        }

        if (!array_key_exists('renter_id_number', $normalized)) {
            foreach (['identity_number', 'residency_number', 'passport_number', 'visa_number', 'license_number', 'document_number'] as $key) {
                $value = $this->nullableString($normalized[$key] ?? null);
                if ($value !== null) {
                    $normalized['renter_id_number'] = $value;
                    break;
                }
            }
        }

        return $normalized;
    }

    private function generateContractNumber(int $tenantId): string
    {
        $datePrefix = now()->format('Ymd');

        $latest = Contract::query()
            ->when($tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('contract_number', 'like', "CTR-{$datePrefix}-%")
            ->latest('id')
            ->value('contract_number');

        $nextSequence = 1;
        if (is_string($latest) && preg_match('/CTR-\d{8}-(\d{4})$/', $latest, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('CTR-%s-%04d', $datePrefix, $nextSequence);
    }

    private function generateReportNumber(): string
    {
        return 'DMG-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }

    private function generateReturnReportNumber(int $tenantId): string
    {
        $datePrefix = now()->format('Ymd');

        $latest = ContractReturnReport::query()
            ->when($tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('report_number', 'like', "RTR-{$datePrefix}-%")
            ->latest('id')
            ->value('report_number');

        $nextSequence = 1;
        if (is_string($latest) && preg_match('/RTR-\d{8}-(\d{4})$/', $latest, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('RTR-%s-%04d', $datePrefix, $nextSequence);
    }

    private function driverPayload(ContractDriver $driver): array
    {
        $driver->loadMissing('documents');

        return [
            'id' => $driver->id,
            'owner_key' => $this->driverOwnerKey($driver),
            'role' => $driver->role,
            'full_name' => $driver->full_name,
            'full_name_ar' => $driver->full_name_ar,
            'phone' => $driver->phone,
            'nationality' => $driver->nationality,
            'place_of_issue' => $driver->place_of_issue,
            'date_of_birth' => optional($driver->date_of_birth)->toDateString(),
            'identity_number' => $driver->identity_number,
            'passport_number' => $driver->passport_number,
            'passport_expiry_date' => optional($driver->passport_expiry_date)->toDateString(),
            'visa_number' => $driver->visa_number,
            'visa_expiry_date' => optional($driver->visa_expiry_date)->toDateString(),
            'residency_number' => $driver->residency_number,
            'license_number' => $driver->license_number,
            'license_issue_date' => optional($driver->license_issue_date)->toDateString(),
            'identity_expiry_date' => optional($driver->identity_expiry_date)->toDateString(),
            'license_expiry_date' => optional($driver->license_expiry_date)->toDateString(),
            'extraction_status' => $driver->extraction_status,
            'extracted_data' => $driver->extracted_data,
            'raw_output' => $driver->raw_output,
            'confidence' => $driver->confidence !== null ? (float) $driver->confidence : null,
            'ai_reviewed' => (bool) $driver->ai_reviewed,
            'notes' => $driver->notes,
            'customer_photo' => $driver->customer_photo_path ? [
                'file_path' => $driver->customer_photo_path,
                'url' => $this->storageUrl($driver->customer_photo_path),
                'file_name' => $driver->customer_photo_name,
                'name' => $driver->customer_photo_name,
                'mime_type' => $driver->customer_photo_mime_type,
            ] : null,
            'documents' => $driver->documents
                ->sortBy('id')
                ->values()
                ->map(fn (ContractDriverDocument $document) => $this->driverDocumentPayload($document, $driver))
                ->all(),
        ];
    }

    private function driverDocumentPayload(ContractDriverDocument $document, ContractDriver $driver): array
    {
        return [
            'id' => $document->id,
            'source' => 'driver_document',
            'owner_key' => $this->driverOwnerKey($driver),
            'driver_id' => $driver->id,
            'driver_role' => $driver->role,
            'document_type' => $document->document_type,
            'document_type_label' => $this->documentTypeLabel($document->document_type),
            'side' => $document->side,
            'file_path' => $document->file_path,
            'file_name' => $document->file_name,
            'mime_type' => $document->mime_type,
            'ocr_status' => $document->ocr_status,
            'ocr_provider' => $document->ocr_provider,
            'raw_ocr_json' => $document->raw_ocr_json,
            'normalized_json' => $document->normalized_json,
            'confidence' => $document->confidence !== null ? (float) $document->confidence : null,
            'reviewed_at' => optional($document->reviewed_at)->toDateTimeString(),
            'url' => $this->storageUrl($document->file_path),
        ];
    }

    private function archiveFilePayload(ContractArchiveFile $file): array
    {
        $driver = $file->relationLoaded('driver') ? $file->driver : $file->driver()->first();

        return [
            'id' => $file->id,
            'source' => 'archive_file',
            'owner_key' => $this->archiveOwnerKey($driver),
            'contract_driver_id' => $file->contract_driver_id,
            'document_type' => $file->document_type,
            'document_type_label' => $this->documentTypeLabel($file->document_type),
            'title' => $file->title,
            'notes' => $file->notes,
            'existing_files' => $file->file_path ? [[
                'id' => $file->id,
                'url' => $this->storageUrl($file->file_path),
            ]] : [],
        ];
    }

    private function handoverPhotosPayload(Contract $contract, ?string $phase = null): array
    {
        $photos = $contract->relationLoaded('handoverPhotos')
            ? $contract->handoverPhotos
            : $contract->handoverPhotos()->get();

        $photos = $photos->sortBy('id')->values();

        if ($phase !== null) {
            $phase = $this->normalizeHandoverPhase($phase);
            $photos = $photos->filter(static fn (ContractHandoverPhoto $photo): bool => (string) $photo->phase === $phase)->values();
            return $photos->map(fn (ContractHandoverPhoto $photo) => $this->handoverPhotoPayload($photo))->all();
        }

        return [
            'delivery' => $photos
                ->filter(static fn (ContractHandoverPhoto $photo): bool => (string) $photo->phase === 'delivery')
                ->values()
                ->map(fn (ContractHandoverPhoto $photo) => $this->handoverPhotoPayload($photo))
                ->all(),
            'return' => $photos
                ->filter(static fn (ContractHandoverPhoto $photo): bool => (string) $photo->phase === 'return')
                ->values()
                ->map(fn (ContractHandoverPhoto $photo) => $this->handoverPhotoPayload($photo))
                ->all(),
        ];
    }

    private function handoverPhotoPayload(ContractHandoverPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'source' => 'handover_photo',
            'phase' => $photo->phase,
            'phase_label' => $this->handoverPhaseLabel($photo->phase),
            'photo_type' => $photo->photo_type,
            'photo_type_label' => $this->documentTypeLabel($photo->photo_type),
            'view_side' => $photo->view_side,
            'title' => $photo->title,
            'notes' => $photo->notes,
            'damage_report_id' => $photo->damage_report_id,
            'file_path' => $photo->file_path,
            'file_name' => $photo->file_name,
            'mime_type' => $photo->mime_type,
            'url' => $this->storageUrl($photo->file_path),
            'extraction_status' => $photo->extraction_status,
            'extracted_data' => $photo->extracted_data,
            'extracted_value' => $photo->extracted_value,
        ];
    }

    private function flattenDocuments(Contract $contract): array
    {
        $documents = [];

        foreach ($contract->drivers->sortBy('sort_order')->sortBy(fn (ContractDriver $driver) => $driver->role === 'primary' ? 0 : 1) as $driver) {
            if ($driver->customer_photo_path) {
                $documents[] = [
                    'id' => $driver->id,
                    'source' => 'customer_photo',
                    'owner_key' => $this->driverOwnerKey($driver),
                    'driver_id' => $driver->id,
                    'driver_role' => $driver->role,
                    'document_type' => 'customer_photo',
                    'document_type_label' => 'Customer Photo',
                    'file_path' => $driver->customer_photo_path,
                    'file_name' => $driver->customer_photo_name,
                    'mime_type' => $driver->customer_photo_mime_type,
                    'url' => $this->storageUrl($driver->customer_photo_path),
                ];
            }

            foreach ($driver->documents->sortBy('id') as $document) {
                $documents[] = $this->driverDocumentPayload($document, $driver);
            }
        }

        foreach ($contract->archiveFiles->sortBy('id') as $file) {
            $documents[] = array_merge($this->archiveFilePayload($file), [
                'source' => 'archive_file',
            ]);
        }

        return $documents;
    }

    private function driverOwnerKey(ContractDriver $driver): string
    {
        return $driver->role === 'primary'
            ? 'primary'
            : 'additional_'.(int) $driver->sort_order;
    }

    private function archiveOwnerKey(?ContractDriver $driver): string
    {
        if (!$driver) {
            return '';
        }

        return $this->driverOwnerKey($driver);
    }

    private function documentTypeLabel(?string $type): string
    {
        $type = trim((string) $type);
        if ($type === '') {
            return '';
        }

        return match ($type) {
            'driver_license' => 'Driver License',
            'id_card' => 'ID Card',
            'residency_card' => 'Residency Card',
            'passport' => 'Passport',
            'visa' => 'Visa',
            'customer_photo' => 'Customer Photo',
            default => Str::title(str_replace(['_', '-'], ' ', $type)),
        };
    }

    private function contractStatusValue(mixed $status): string
    {
        return $status instanceof ContractStatus ? $status->value : (string) $status;
    }

    private function contractStatusLabel(mixed $status): string
    {
        $enum = $status instanceof ContractStatus ? $status : ContractStatus::tryFrom((string) $status);

        return $enum?->label() ?? Str::title(str_replace('_', ' ', (string) $status));
    }

    private function contractStatusColor(mixed $status): string
    {
        $enum = $status instanceof ContractStatus ? $status : ContractStatus::tryFrom((string) $status);

        return $enum?->color() ?? '#6B7280';
    }

    private function storageUrl(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return Storage::url($normalized);
    }

    private function returnStatusReportSummaryPayload(
        Contract $contract,
        ?ContractReturnReport $report = null,
        ?CarDamageReport $damageReport = null,
        ?string $vehicleConditionAfter = null,
        ?bool $hasDamage = null
    ): array {
        $contract->loadMissing(['reservation', 'damageReports.items', 'returnStatusReport']);
        $report ??= $contract->returnStatusReport;
        $settings = $this->reservationSettings((int) $contract->tenant_id);

        $returnOdometer = $report?->return_odometer !== null
            ? (int) $report->return_odometer
            : ($contract->return_odometer !== null ? (int) $contract->return_odometer : null);
        $checkoutFuelLevel = $this->normalizeFuelLevelForStorage($contract->vehicle_fuel_level);
        $returnFuelLevel = $this->normalizeFuelLevelForStorage($report?->return_fuel_level ?? $contract->return_fuel_level);
        $actualReturnTime = $report?->actual_return_time
            ? Carbon::parse($report->actual_return_time)->format('Y-m-d H:i')
            : ($contract->actual_return_time ? Carbon::parse($contract->actual_return_time)->format('Y-m-d H:i') : null);
        $useStoredCharges = $report?->status === 'finalized';

        if (!$damageReport && $report?->damage_report_id) {
            $damageReport = $contract->damageReports->firstWhere('id', (int) $report->damage_report_id);
        }

        $effectiveHasDamage = $hasDamage;
        if ($effectiveHasDamage === null && $report?->has_damage !== null) {
            $effectiveHasDamage = (bool) $report->has_damage;
        }
        $effectiveHasDamage ??= true;

        if (!$damageReport && $effectiveHasDamage !== false) {
            $damageReport = $contract->damageReports
                ->filter(static fn (CarDamageReport $candidate): bool => $candidate->report_type === 'after_return')
                ->sortByDesc('id')
                ->first();
        }

        if ($effectiveHasDamage === false) {
            $damageReport = null;
        }

        $calculatedExtraKilometers = $this->resolveExtraKilometers($contract, $settings, $actualReturnTime, $returnOdometer);
        $extraKilometers = $this->normalizeMoney($useStoredCharges ? ($report?->extra_kilometers ?? $calculatedExtraKilometers) : $calculatedExtraKilometers);
        $calculatedKilometerRate = ReservationSettings::resolveKilometerRate($settings, $extraKilometers);
        $kilometerRate = $this->normalizeMoney($useStoredCharges ? ($report?->kilometer_rate ?? $calculatedKilometerRate) : $calculatedKilometerRate);
        $calculatedCleaningFee = ReservationSettings::resolveCleaningFee($settings);
        $cleaningFee = $this->normalizeMoney($useStoredCharges ? ($report?->cleaning_fee ?? $calculatedCleaningFee) : $calculatedCleaningFee);
        $calculatedFuelFee = ReservationSettings::resolveFuelFeeByLoss($settings, $checkoutFuelLevel, $returnFuelLevel);
        $fuelFee = $this->normalizeMoney($useStoredCharges ? ($report?->fuel_fee ?? $calculatedFuelFee) : $calculatedFuelFee);
        $calculatedFuelCredit = ReservationSettings::resolveFuelCreditByGain($settings, $checkoutFuelLevel, $returnFuelLevel);
        $fuelCredit = $this->normalizeMoney($useStoredCharges ? ($report?->fuel_credit ?? $calculatedFuelCredit) : $calculatedFuelCredit);
        $calculatedLateHours = $this->resolveLateHours($contract, $settings, $actualReturnTime);
        $lateHours = $this->normalizeMoney($useStoredCharges ? ($report?->late_hours ?? $calculatedLateHours) : $calculatedLateHours);
        $calculatedLateHourRate = ReservationSettings::resolveLateHourlyFee($settings);
        $lateHourRate = $this->normalizeMoney($useStoredCharges ? ($report?->late_hour_rate ?? $calculatedLateHourRate) : $calculatedLateHourRate);
        $damageFee = $this->normalizeMoney($damageReport
            ? $this->afterReturnDamageItems($damageReport)->sum('estimated_cost')
            : ($report?->damage_fee ?? 0)
        );
        $maintenanceFee = $this->normalizeMoney($report?->maintenance_fee ?? 0);
        $otherFee = $this->normalizeMoney($report?->other_fee ?? 0);
        $lateFee = $this->calculateLateFee(
            $lateHours,
            $settings,
            $this->normalizeMoney($contract->price_per_day ?? $contract->reservation?->daily_rate ?? 0),
            $lateHourRate
        );
        $subtotalBeforeDiscount = $this->normalizeMoney(
            ($extraKilometers * $kilometerRate)
            + $cleaningFee
            + $fuelFee
            - $fuelCredit
            + $lateFee
            + $damageFee
            + $maintenanceFee
            + $otherFee
        );
        $discount = min($this->normalizeMoney($report?->discount ?? 0), max(0.0, $subtotalBeforeDiscount));
        $totalExtraCharges = $this->normalizeMoney($subtotalBeforeDiscount - $discount);
        $paymentStatus = $report?->payment_status ?? ($totalExtraCharges > 0 ? 'not_paid' : 'paid');
        $damageReportStatus = $effectiveHasDamage === false
            ? 'skipped'
            : ($damageReport ? ($damageReport->items()->exists() ? 'done' : 'pending') : 'pending');

        $fees = [
            ['key' => 'extra_kilometer_charges', 'label' => 'Extra Kilometer Charges', 'amount' => $this->normalizeMoney($extraKilometers * $kilometerRate)],
            ['key' => 'cleaning_fee', 'label' => 'Cleaning Fee', 'amount' => $cleaningFee],
            ['key' => 'fuel_fee', 'label' => 'Fuel Fee', 'amount' => $fuelFee],
            ['key' => 'fuel_credit', 'label' => 'Fuel Credit', 'amount' => $fuelCredit > 0 ? -$fuelCredit : 0.0],
            ['key' => 'late_return_fee', 'label' => 'Late Return Fee', 'amount' => $lateFee],
            ['key' => 'damage_fee', 'label' => 'Damage Fee', 'amount' => $damageFee],
            ['key' => 'maintenance_fee', 'label' => 'Maintenance Fee', 'amount' => $maintenanceFee],
            ['key' => 'other_fee', 'label' => 'Other Fee', 'amount' => $otherFee],
            ['key' => 'discount', 'label' => 'Discount', 'amount' => $discount > 0 ? -$discount : 0.0],
        ];

        return [
            'return_status_report' => $this->returnStatusReportPayload($report),
            'payment_status' => $paymentStatus,
            'currency' => strtoupper((string) ($contract->currency ?: config('app.currency_code', 'USD'))),
            'allowed_kilometers' => $this->resolveAllowedKilometers($contract, $settings, $actualReturnTime),
            'actual_kilometers_driven' => max(0, (float) ($returnOdometer ?? 0) - (float) ($contract->vehicle_odometer ?? 0)),
            'extra_kilometers' => $extraKilometers,
            'kilometer_rate' => $kilometerRate,
            'extra_kilometer_charges' => $this->normalizeMoney($extraKilometers * $kilometerRate),
            'cleaning_fee' => $cleaningFee,
            'fuel_fee' => $fuelFee,
            'fuel_credit' => $fuelCredit,
            'late_hours' => $lateHours,
            'late_hour_rate' => $lateHourRate,
            'late_fee' => $lateFee,
            'damage_fee' => $damageFee,
            'maintenance_fee' => $maintenanceFee,
            'other_fee' => $otherFee,
            'discount' => $discount,
            'total_extra_charges' => $totalExtraCharges,
            'fees' => $fees,
            'vehicle_readings' => [
                'checkout_odometer' => $contract->vehicle_odometer,
                'return_odometer' => $returnOdometer,
                'checkout_fuel_level' => $checkoutFuelLevel,
                'return_fuel_level' => $returnFuelLevel,
            ],
            'vehicle_condition_after' => $vehicleConditionAfter ?? $report?->vehicle_condition_after ?? $contract->vehicle_condition_after,
            'has_damage' => $effectiveHasDamage,
            'damage_report_status' => $damageReportStatus,
            'damage_report' => $damageReport ? $this->damageReportPayload($damageReport->fresh(['files', 'items'])) : null,
        ];
    }

    private function returnStatusReportPayload(?ContractReturnReport $report): ?array
    {
        if (!$report) {
            return null;
        }

        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'status' => $report->status,
            'payment_status' => $report->payment_status ?? 'not_paid',
            'actual_return_time' => optional($report->actual_return_time)?->toIso8601String(),
            'return_location' => $report->return_location,
            'return_odometer' => $report->return_odometer,
            'return_fuel_level' => $report->return_fuel_level,
            'vehicle_condition_after' => $report->vehicle_condition_after,
            'has_damage' => $report->has_damage,
            'damage_report_id' => $report->damage_report_id,
            'extra_kilometers' => $report->extra_kilometers,
            'kilometer_rate' => $report->kilometer_rate,
            'cleaning_fee' => $report->cleaning_fee,
            'fuel_fee' => $report->fuel_fee,
            'fuel_credit' => $report->fuel_credit,
            'late_hours' => $report->late_hours,
            'late_hour_rate' => $report->late_hour_rate,
            'damage_fee' => $report->damage_fee,
            'maintenance_fee' => $report->maintenance_fee,
            'other_fee' => $report->other_fee,
            'discount' => $report->discount,
            'total_extra_charges' => $report->total_extra_charges,
            'notes' => $report->notes,
        ];
    }

    private function returnStatusReportFilePayload(Contract $contract, ?ContractReturnReport $report): ?array
    {
        if (!$report) {
            return null;
        }

        $contract->loadMissing('tenant:id,slug');
        $locale = app()->getLocale();

        return [
            'type' => 'pdf',
            'filename' => sprintf('%s-%s-invoice.pdf', $report->report_number, $locale),
            'url' => route('api.contracts.return-report.pdf', [
                'contract' => $contract->id,
                'lang' => $locale,
            ]),
            'api_url' => route('api.contracts.return-report.pdf', [
                'contract' => $contract->id,
                'lang' => $locale,
            ]),
            'admin_url' => route('admin.contracts.return-report.pdf', [
                'subdomain' => $contract->tenant?->slug,
                'contractId' => $contract->id,
                'lang' => $locale,
            ]),
        ];
    }

    private function contractFilePayload(Contract $contract, ?string $locale = null): array
    {
        $contract->loadMissing('tenant:id,slug');
        $locale = $locale ?: app()->getLocale();
        $filename = sprintf('%s-%s-report.pdf', $contract->contract_number, $locale);

        return [
            'type' => 'pdf',
            'filename' => $filename,
            'url' => route('api.contracts.pdf', [
                'contract' => $contract->id,
                'lang' => $locale,
            ]),
            'api_url' => route('api.contracts.pdf', [
                'contract' => $contract->id,
                'lang' => $locale,
            ]),
            'admin_url' => route('admin.contracts.pdf', [
                'subdomain' => $contract->tenant?->slug,
                'contract' => $contract->id,
                'lang' => $locale,
            ]),
        ];
    }

    private function reservationSettings(int $tenantId): array
    {
        $settings = DB::table('tenant_site_settings')
            ->where('tenant_id', $tenantId)
            ->value('reservation_settings');

        $decoded = is_array($settings) ? $settings : (json_decode((string) $settings, true) ?: null);

        return ReservationSettings::normalize($decoded);
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function calculateLateFee(float $lateHours, array $settings, float $dailyRate, ?float $hourlyRateOverride = null): float
    {
        if ($lateHours <= 0) {
            return 0.0;
        }

        $lateReturn = is_array($settings['late_return'] ?? null) ? $settings['late_return'] : [];
        $mode = (string) ($lateReturn['mode'] ?? 'hourly');
        $hourlyFee = $hourlyRateOverride !== null && $hourlyRateOverride > 0
            ? $hourlyRateOverride
            : ReservationSettings::resolveLateHourlyFee($settings);
        $afterHours = isset($lateReturn['after_hours']) ? (int) $lateReturn['after_hours'] : 0;

        if ($mode === 'daily_after_threshold' && $afterHours > 0 && $lateHours > $afterHours) {
            $excessHours = $lateHours - $afterHours;
            $fullDays = (int) floor($excessHours / 24);
            $remainingHours = round($excessHours - ($fullDays * 24), 2);
            $dayRate = $dailyRate > 0 ? $dailyRate : $hourlyFee * 24;

            return round(($fullDays * $dayRate) + ($remainingHours * $hourlyFee), 2);
        }

        return round($lateHours * $hourlyFee, 2);
    }

    private function resolveExtraKilometers(Contract $contract, array $settings, ?string $actualReturnTime = null, ?int $returnOdometer = null): float
    {
        $vehicleOdometer = (int) ($contract->vehicle_odometer ?? 0);
        $returnOdometer = $returnOdometer ?? ($contract->return_odometer !== null ? (int) $contract->return_odometer : 0);
        $drivenKilometers = max(0, $returnOdometer - $vehicleOdometer);
        $allowedKilometers = $this->resolveAllowedKilometers($contract, $settings, $actualReturnTime);

        return max(0, $drivenKilometers - $allowedKilometers);
    }

    private function resolveAllowedKilometers(Contract $contract, array $settings, ?string $actualReturnTime = null): float
    {
        $days = $this->resolveContractDurationDays($contract) + $this->resolveLateChargeDays($contract, $settings, $actualReturnTime);
        if ($days <= 0) {
            return 0.0;
        }

        $daily = (float) ($contract->allowed_km_per_day ?? 0);
        if ($daily > 0) {
            return $this->normalizeMoney($days * $daily);
        }

        $weekly = (float) ($contract->allowed_km_per_week ?? 0);
        if ($weekly > 0) {
            return $this->normalizeMoney(max(1, (int) ceil($days / 7)) * $weekly);
        }

        $monthly = (float) ($contract->allowed_km_per_month ?? 0);
        if ($monthly > 0) {
            return $this->normalizeMoney(max(1, (int) ceil($days / 30)) * $monthly);
        }

        return 0.0;
    }

    private function resolveLateChargeDays(Contract $contract, array $settings, ?string $actualReturnTime = null): int
    {
        $lateHours = $this->resolveLateHours($contract, $settings, $actualReturnTime);
        if ($lateHours <= 0) {
            return 0;
        }

        $lateSettings = $settings['late_return'] ?? [];
        if (($lateSettings['mode'] ?? 'hourly') !== 'daily_after_threshold') {
            return 0;
        }

        $threshold = (float) ($lateSettings['after_hours'] ?? 0);
        if ($threshold <= 0 || $lateHours <= $threshold) {
            return 0;
        }

        return max(1, (int) ceil(($lateHours - $threshold) / 24));
    }

    private function resolveContractDurationDays(Contract $contract): int
    {
        if (!$contract->start_date || !$contract->end_date) {
            return 0;
        }

        return max(1, (int) Carbon::parse($contract->start_date)->diffInDays(Carbon::parse($contract->end_date)) + 1);
    }

    private function resolveLateHours(Contract $contract, array $settings, ?string $actualReturnTime = null): float
    {
        $actual = $actualReturnTime ? Carbon::parse($actualReturnTime) : ($contract->actual_return_time ? Carbon::parse($contract->actual_return_time) : null);
        $expected = $this->expectedReturnDateTime($contract, $settings);

        if (!$actual || !$expected || $actual->lessThanOrEqualTo($expected)) {
            return 0.0;
        }

        return round($expected->diffInMinutes($actual) / 60, 2);
    }

    private function expectedReturnDateTime(Contract $contract, array $settings): ?Carbon
    {
        if (!$contract->end_date) {
            return null;
        }

        $policy = $settings['return_time_policy']['mode'] ?? 'fixed_time';
        $time = match ($policy) {
            'same_pickup' => (string) ($contract->reservation?->pickup_time ?? '18:00'),
            'set_during_reservation' => (string) ($contract->reservation?->return_time ?? '18:00'),
            default => (string) ($settings['return_time_policy']['fixed_time'] ?? '18:00'),
        };

        return Carbon::parse(Carbon::parse($contract->end_date)->format('Y-m-d').' '.($time ?: '18:00'));
    }

    private function afterReturnDamageItems(CarDamageReport $report)
    {
        $report->loadMissing('items');
        $items = $report->items;
        $hasExplicitTiming = $items->contains(fn ($item) => !blank($item->damage_timing));

        if ($hasExplicitTiming) {
            return $items->filter(fn ($item) => $item->damage_timing === 'after_return')->values();
        }

        if ($report->report_type === 'after_return') {
            return $items->values();
        }

        return collect();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
    private function normalizeFuelLevelForStorage(mixed $value): ?string
    {
        $fuelLevel = strtolower(trim((string) ($value ?? '')));

        return match ($fuelLevel) {
            'empty', '0', '0/4', '0%', 'empty tank' => 'empty',
            'quarter', '1/4', '1-4', '1 4', 'one-quarter', 'one quarter', '25', '25%', 'quarter tank' => 'quarter',
            'half', '1/2', '1-2', '1 2', 'two-quarters', 'two quarters', '50', '50%', 'half tank' => 'half',
            'three_quarters', '3/4', '3-4', '3 4', 'three-quarters', 'three quarters', '75', '75%', '3/4 tank' => 'three_quarters',
            'full', '1', '100', '100%', 'full tank' => 'full',
            default => $this->nullableString($value),
        };
    }
}
