<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContractStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractArchiveFile;
use App\Models\ContractDriver;
use App\Models\ContractDriverDocument;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractsController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess
    ) {
    }

    public function documents(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);

        $contract->loadMissing([
            'reservation.car:id,branch_id,year,make,model,license_plate,status',
            'branch:id,name',
            'drivers.documents',
            'archiveFiles.driver',
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

    private function authorizeAdminApiUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }

    private function canAccessContract(Contract $contract, User $user): bool
    {
        $contract->loadMissing('reservation.car:id,branch_id');

        $branchId = $contract->branch_id
            ? (int) $contract->branch_id
            : ($contract->reservation?->car?->branch_id ? (int) $contract->reservation->car->branch_id : null);

        return $this->branchAccess->canAccessBranchId($user, $branchId);
    }

    private function contractPayload(Contract $contract): array
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
            'branch_name' => $contract->branch?->name,
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
        ];
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
}
