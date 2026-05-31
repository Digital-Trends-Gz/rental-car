<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccidentReport;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccidentReportsController extends Controller
{
    public function index(Request $request, Contract $contract): JsonResponse
    {
        $reports = $contract->accidentReports()
            ->with(['photos', 'contract:id,contract_number', 'reservation:id,reservation_number', 'car:id,make,model,year,license_plate'])
            ->latest('id')
            ->paginate((int) min(max((int) $request->integer('per_page', 10), 1), 50));

        return response()->json([
            'data' => $reports->getCollection()
                ->map(fn (AccidentReport $report) => $this->reportPayload($report, $request))
                ->values(),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
                'has_more_pages' => $reports->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'accident_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['required', 'string', 'max:3000'],
            'police_report_number' => ['nullable', 'string', 'max:100'],
            'has_injuries' => ['nullable', 'boolean'],
            'third_party_involved' => ['nullable', 'boolean'],
            'third_party_details' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photo_types' => ['nullable', 'array'],
            'photo_types.*' => ['nullable', 'string', 'max:50'],
            'photo_notes' => ['nullable', 'array'],
            'photo_notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $files = $this->uploadedPhotoFiles($request);
        $this->validateUploadedPhotos($files);

        $contract->loadMissing(['reservation:id,reservation_number,car_id', 'reservation.car:id,make,model,year,license_plate', 'branch']);
        $reservation = $contract->reservation;

        $report = DB::transaction(function () use ($request, $contract, $reservation, $validated, $files): AccidentReport {
            $report = AccidentReport::create([
                'contract_id' => $contract->id,
                'reservation_id' => $contract->reservation_id,
                'car_id' => $reservation?->car_id,
                'branch_id' => $contract->branch_id,
                'reported_by' => $request->user()?->id,
                'accident_number' => $this->generateAccidentNumber(),
                'status' => 'reported',
                'accident_at' => $validated['accident_at'] ?? null,
                'location' => $validated['location'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'description' => $validated['description'],
                'police_report_number' => $validated['police_report_number'] ?? null,
                'has_injuries' => $request->boolean('has_injuries'),
                'third_party_involved' => $request->boolean('third_party_involved'),
                'third_party_details' => $this->normalizeThirdPartyDetails($validated['third_party_details'] ?? null),
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->storePhotos($report, $files, $request->input('photo_types', []), $request->input('photo_notes', []));

            return $report->load(['photos', 'contract:id,contract_number', 'reservation:id,reservation_number', 'car:id,make,model,year,license_plate']);
        });

        return response()->json([
            'message' => $this->localized($request, 'Accident report created successfully.', 'تم إنشاء بلاغ الحادث بنجاح.'),
            'accident_report' => $this->reportPayload($report, $request),
        ], 201);
    }

    public function show(Request $request, AccidentReport $accidentReport): JsonResponse
    {
        $accidentReport->load(['photos', 'contract:id,contract_number', 'reservation:id,reservation_number', 'car:id,make,model,year,license_plate']);

        return response()->json([
            'accident_report' => $this->reportPayload($accidentReport, $request),
        ]);
    }

    /**
     * @return array<int|string, UploadedFile>
     */
    private function uploadedPhotoFiles(Request $request): array
    {
        $photos = $request->file('photos', []);

        if ($photos instanceof UploadedFile) {
            return [0 => $photos];
        }

        if (! is_array($photos)) {
            return [];
        }

        $files = [];
        foreach ($photos as $index => $photo) {
            if ($photo instanceof UploadedFile) {
                $files[$index] = $photo;
                continue;
            }

            if (is_array($photo) && ($photo['file'] ?? null) instanceof UploadedFile) {
                $files[$index] = $photo['file'];
            }
        }

        return $files;
    }

    /**
     * @param array<int|string, UploadedFile> $files
     */
    private function validateUploadedPhotos(array $files): void
    {
        foreach ($files as $index => $file) {
            $validator = Validator::make(
                ['file' => $file],
                ['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "photos.$index" => $validator->errors()->first('file'),
                ]);
            }
        }
    }

    /**
     * @param array<int|string, UploadedFile> $files
     */
    private function storePhotos(AccidentReport $report, array $files, array $types, array $notes): void
    {
        foreach ($files as $index => $file) {
            $path = $file->store("accident-reports/{$report->id}", config('vilt-filepond.storage_disk'));

            $report->photos()->create([
                'photo_type' => $types[$index] ?? null,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'notes' => $notes[$index] ?? null,
            ]);
        }
    }

    private function normalizeThirdPartyDetails(mixed $details): ?array
    {
        if (is_array($details)) {
            return $details;
        }

        if (is_string($details) && trim($details) !== '') {
            $decoded = json_decode($details, true);

            return is_array($decoded) ? $decoded : ['details' => $details];
        }

        return null;
    }

    private function generateAccidentNumber(): string
    {
        do {
            $number = 'ACC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (AccidentReport::where('accident_number', $number)->exists());

        return $number;
    }

    private function reportPayload(AccidentReport $report, Request $request): array
    {
        return [
            'id' => $report->id,
            'accident_number' => $report->accident_number,
            'status' => $report->status,
            'status_label' => $this->statusLabel($report->status, $request),
            'contract' => [
                'id' => $report->contract_id,
                'contract_number' => $report->contract?->contract_number,
            ],
            'reservation' => [
                'id' => $report->reservation_id,
                'reservation_number' => $report->reservation?->reservation_number,
            ],
            'car' => [
                'id' => $report->car_id,
                'name' => trim(implode(' ', array_filter([
                    $report->car?->year,
                    $report->car?->make,
                    $report->car?->model,
                ]))),
                'license_plate' => $report->car?->license_plate,
            ],
            'accident_at' => $report->accident_at?->toISOString(),
            'location' => $report->location,
            'latitude' => $report->latitude,
            'longitude' => $report->longitude,
            'description' => $report->description,
            'police_report_number' => $report->police_report_number,
            'has_injuries' => $report->has_injuries,
            'third_party_involved' => $report->third_party_involved,
            'third_party_details' => $report->third_party_details,
            'notes' => $report->notes,
            'photos' => $report->photos
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'photo_type' => $photo->photo_type,
                    'file_name' => $photo->file_name,
                    'mime_type' => $photo->mime_type,
                    'size' => $photo->size,
                    'notes' => $photo->notes,
                    'url' => $this->fileUrl($photo->file_path),
                ])
                ->values(),
            'created_at' => $report->created_at?->toISOString(),
            'updated_at' => $report->updated_at?->toISOString(),
        ];
    }

    private function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim((string) preg_replace('/^storage\//', '', $path), '/');

        if (preg_match('/^https?:\/\//i', $normalized)) {
            return $normalized;
        }

        return Storage::disk(config('vilt-filepond.storage_disk'))->url($normalized);
    }

    private function statusLabel(string $status, Request $request): string
    {
        $labels = [
            'reported' => ['en' => 'Reported', 'ar' => 'تم الإبلاغ'],
            'under_review' => ['en' => 'Under review', 'ar' => 'قيد المراجعة'],
            'resolved' => ['en' => 'Resolved', 'ar' => 'تم الحل'],
            'rejected' => ['en' => 'Rejected', 'ar' => 'مرفوض'],
        ];

        $lang = $this->language($request);

        return $labels[$status][$lang] ?? Str::headline($status);
    }

    private function localized(Request $request, string $en, string $ar): string
    {
        return $this->language($request) === 'ar' ? $ar : $en;
    }

    private function language(Request $request): string
    {
        return str_starts_with(strtolower((string) $request->header('Accept-Language')), 'ar') ? 'ar' : 'en';
    }
}
