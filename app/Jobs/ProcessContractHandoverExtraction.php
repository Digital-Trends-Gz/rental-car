<?php

namespace App\Jobs;

use App\Core\TenantContext;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractDriver;
use App\Models\Tenant;
use App\Services\Contracts\ContractDriverDocumentExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ProcessContractHandoverExtraction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 50;

    public function __construct(
        public readonly int $contractId
    ) {
    }

    public function handle(ContractDriverDocumentExtractor $extractor): void
    {
        $contract = Contract::withoutTenantScope()
            ->find($this->contractId);

        if (!$contract) {
            return;
        }

        $tenant = Tenant::query()->find((int) $contract->tenant_id);
        if (!$tenant) {
            return;
        }

        TenantContext::set($tenant);

        try {
            $contract = Contract::query()
                ->with([
                    'reservation.user:id,name,email,is_active',
                    'reservation.car:id,branch_id,year,make,model,license_plate,status,mileage',
                    'reservation.car.branch:id,name',
                    'branch:id,name',
                ])
                ->find($this->contractId);

            if (!$contract) {
                return;
            }

            $state = $this->normalizeState($contract->handover_state);
            $step = data_get($state, 'steps.report_upload', []);
            $documents = $this->extractQueuedDocuments($step);

            if ($documents === []) {
                $this->markFailed($contract, $state, 'No uploaded document files were found for extraction.');
                return;
            }

            try {
                $mergedFields = [];
                $rawOutput = [];
                $textPreview = [];
                $processedDocuments = [];

                foreach ($documents as $document) {
                    $documentType = (string) ($document['document_type'] ?? 'other');
                    $filePaths = $this->extractDocumentFilePaths($document);

                    if ($filePaths === []) {
                        continue;
                    }

                    $result = $extractor->extractFromFilePaths($filePaths, $documentType);
                    $fields = is_array($result['fields'] ?? null) ? $result['fields'] : [];

                    $mergedFields = array_replace($mergedFields, $fields);
                    $rawOutput[] = $result['raw_output'] ?? null;
                    $textPreview[] = (string) ($result['raw_text'] ?? '');
                    $processedDocuments[] = array_merge($document, [
                        'extraction_status' => 'extracted',
                        'extracted_fields' => $fields,
                        'raw_output' => $result['raw_output'] ?? null,
                        'raw_text' => $result['raw_text'] ?? '',
                        'confidence' => $result['confidence'] ?? null,
                        'provider' => $result['provider'] ?? null,
                        'engine' => $result['engine'] ?? null,
                    ]);
                }

                if ($processedDocuments === []) {
                    $this->markFailed($contract, $state, 'No readable uploaded document files were found for extraction.');
                    return;
                }

                $extraction = [
                    'fields' => $mergedFields,
                    'raw_output' => $rawOutput,
                    'raw_text' => trim(implode("\n\n----------------\n\n", array_filter($textPreview, static fn (string $text): bool => trim($text) !== ''))),
                ];

                $appliedFields = $this->applyExtractedFields($contract, $extraction['fields'] ?? []);

                if (!empty($appliedFields)) {
                    $contract->saveQuietly();
                }

                $this->syncPrimaryDriverFromExtraction($contract, $extraction['fields'] ?? [], $extraction);

                $contract->forceFill([
                    'ai_extraction_status' => 'extracted',
                    'ai_extracted_data' => $extraction['fields'] ?? [],
                ])->saveQuietly();

                $state['steps']['report_upload']['payload'] = array_merge(
                    is_array(data_get($step, 'payload')) ? data_get($step, 'payload') : [],
                    [
                        'extraction_status' => 'extracted',
                        'extracted_fields' => $extraction['fields'] ?? [],
                        'applied_fields' => $appliedFields,
                        'raw_output' => $extraction['raw_output'] ?? null,
                        'text_preview' => $extraction['raw_text'] ?? null,
                        'documents' => $processedDocuments,
                        'stored_documents' => $processedDocuments,
                    ]
                );

                $contract->forceFill([
                    'handover_state' => $state,
                ])->saveQuietly();
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if ($this->isDailyLimitError($message)) {
                    $this->markFailed($contract, $state, $message, true);
                    return;
                }

                if ($this->isRateLimitError($message)) {
                    $this->markRetrying($contract, $state);
                    $this->release($this->retryDelayForAttempt($this->attempts()));
                    return;
                }

                $this->markFailed($contract, $state, $message);
            }
        } finally {
            TenantContext::clear();
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalizeState(mixed $state): array
    {
        $state = is_array($state) ? $state : [];
        $state['steps'] = is_array(data_get($state, 'steps')) ? data_get($state, 'steps') : [];
        $state['steps']['report_upload'] = is_array(data_get($state, 'steps.report_upload'))
            ? data_get($state, 'steps.report_upload')
            : [];
        $state['steps']['report_upload']['payload'] = is_array(data_get($state, 'steps.report_upload.payload'))
            ? data_get($state, 'steps.report_upload.payload')
            : [];

        return $state;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<int, array<string, mixed>>
     */
    private function extractQueuedDocuments(array $step): array
    {
        $documents = data_get($step, 'payload.stored_documents');
        if (!is_array($documents) || $documents === []) {
            $documents = data_get($step, 'payload.documents');
        }

        return is_array($documents) ? array_values(array_filter($documents, static fn ($document): bool => is_array($document))) : [];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, string>
     */
    private function extractDocumentFilePaths(array $document): array
    {
        $filePaths = [];

        foreach (Arr::wrap($document['file_paths'] ?? null) as $path) {
            $path = trim((string) $path);
            if ($path !== '') {
                $filePaths[] = $path;
            }
        }

        $singlePath = trim((string) ($document['file_path'] ?? ''));
        if ($singlePath !== '') {
            $filePaths[] = $singlePath;
        }

        return array_values(array_unique($filePaths));
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, string>
     */
    private function extractTempFolders(array $documents): array
    {
        $folders = [];

        foreach ($documents as $document) {
            foreach (Arr::wrap($document['temp_folders'] ?? []) as $folder) {
                $folder = trim((string) $folder);
                if ($folder !== '') {
                    $folders[] = $folder;
                }
            }
        }

        return array_values(array_unique($folders));
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applyExtractedFields(Contract $contract, array $fields): array
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

            if (in_array($contractField, ['contract_date', 'start_date', 'end_date'], true)) {
                try {
                    $value = Carbon::parse((string) $value)->toDateString();
                } catch (\Throwable) {
                    continue;
                }
            }

            $applied[$contractField] = $value;
        }

        if (!empty($applied)) {
            if (isset($applied['status']) && !is_string($applied['status'])) {
                unset($applied['status']);
            }

            $contract->forceFill($applied);
        }

        return $applied;
    }

    private function syncPrimaryDriverFromExtraction(Contract $contract, array $fields, array $extraction): void
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
            'confidence' => null,
            'ai_reviewed' => false,
            'notes' => $this->nullableString($contract->notes ?? null),
        ]);

        $driver->saveQuietly();
    }

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

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function markFailed(Contract $contract, array $state, string $message, bool $retrying = false): void
    {
        $contract->forceFill([
            'ai_extraction_status' => 'failed',
        ])->saveQuietly();

        $payload = [
            'extraction_status' => 'failed',
            'extraction_error' => $message,
        ];

        if ($retrying) {
            $payload['extraction_retrying'] = true;
        }

        $state['steps']['report_upload']['payload'] = array_merge(
            is_array(data_get($state, 'steps.report_upload.payload')) ? data_get($state, 'steps.report_upload.payload') : [],
            $payload
        );

        $contract->forceFill([
            'handover_state' => $state,
        ])->saveQuietly();
    }

    private function markRetrying(Contract $contract, array $state): void
    {
        $contract->forceFill([
            'ai_extraction_status' => 'pending',
        ])->saveQuietly();

        $state['steps']['report_upload']['payload'] = array_merge(
            is_array(data_get($state, 'steps.report_upload.payload')) ? data_get($state, 'steps.report_upload.payload') : [],
            [
                'extraction_status' => 'retrying',
                'extraction_error' => null,
                'extraction_retrying' => true,
            ]
        );

        $contract->forceFill([
            'handover_state' => $state,
        ])->saveQuietly();
    }

    private function isRateLimitError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, '429')
            || str_contains($message, 'quota');
    }

    private function isDailyLimitError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'daily ai document extraction limit reached')
            || str_contains($message, 'document extraction limit reached');
    }

    private function retryDelayForAttempt(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 60,
            $attempt === 2 => 120,
            $attempt === 3 => 180,
            $attempt === 4 => 240,
            default => 300,
        };
    }
}
