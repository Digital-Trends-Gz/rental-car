<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarPhotoHistory;
use App\Support\BranchAccess;
use App\Support\FileUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class CarPhotoHistoryController extends Controller
{
    private const REASONS = [
        'before_delivery' => [
            'en' => 'Before delivery',
            'ar' => 'قبل التسليم',
            'ur' => 'حوالگی سے پہلے',
        ],
        'after_return' => [
            'en' => 'After return',
            'ar' => 'بعد الاستلام',
            'ur' => 'واپسی کے بعد',
        ],
        'new_damage' => [
            'en' => 'New damage',
            'ar' => 'ضرر جديد',
            'ur' => 'نیا نقصان',
        ],
        'after_cleaning' => [
            'en' => 'After cleaning',
            'ar' => 'بعد التنظيف',
            'ur' => 'صفائی کے بعد',
        ],
        'after_maintenance' => [
            'en' => 'After maintenance',
            'ar' => 'بعد الصيانة',
            'ur' => 'مرمت کے بعد',
        ],
    ];

    public function __construct(
        private BranchAccess $branchAccess,
        private FilePondService $filePondService,
    ) {}

    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorizeCarAccess($request, $car);

        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $histories = $car->photoHistories()
            ->with(['user:id,name', 'files'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'car' => $this->carPayload($car),
            'photo_histories' => $histories->getCollection()
                ->map(fn (CarPhotoHistory $history) => $this->historyPayload($history, $request))
                ->values(),
            'pagination' => [
                'current_page' => $histories->currentPage(),
                'per_page' => $histories->perPage(),
                'total' => $histories->total(),
                'last_page' => $histories->lastPage(),
                'from' => $histories->firstItem(),
                'to' => $histories->lastItem(),
                'has_more_pages' => $histories->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request, Car $car): JsonResponse
    {
        $this->authorizeCarAccess($request, $car);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:' . implode(',', array_keys(self::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos_temp_folders' => ['nullable', 'array'],
            'photos_temp_folders.*' => ['string'],
        ]);

        $files = $this->uploadedPhotoFiles($request);
        $tempFolders = array_values(array_filter($validated['photos_temp_folders'] ?? []));

        if ($files === [] && $tempFolders === []) {
            throw ValidationException::withMessages([
                'photos' => 'At least one photo or temp folder is required.',
            ]);
        }

        $this->validateUploadedPhotos($files);

        $history = DB::transaction(function () use ($request, $car, $validated, $files, $tempFolders) {
            $history = $car->photoHistories()->create([
                'tenant_id' => $car->tenant_id,
                'user_id' => $request->user()?->id,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($tempFolders !== []) {
                $this->filePondService->handleFileUpdates($history, $tempFolders, [], 'photos');
            }

            if ($files !== []) {
                $this->storeUploadedPhotos($history, $files);
            }

            return $history->load(['user:id,name', 'files']);
        });

        return response()->json([
            'message' => 'Photo history created successfully.',
            'photo_history' => $this->historyPayload($history, $request),
        ], 201);
    }

    public function destroy(Request $request, Car $car, CarPhotoHistory $photoHistory): JsonResponse
    {
        $this->authorizeCarAccess($request, $car);

        if ((int) $photoHistory->car_id !== (int) $car->id) {
            abort(404);
        }

        $photoHistory->delete();

        return response()->json([
            'message' => 'Photo history deleted successfully.',
            'deleted_id' => $photoHistory->id,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $locale = $this->locale($request);

        return response()->json([
            'statuses' => collect(self::REASONS)
                ->map(fn (array $labels, string $value) => [
                    'value' => $value,
                    'label' => $labels[$locale] ?? $labels['en'],
                ])
                ->values(),
        ]);
    }

    private function authorizeCarAccess(Request $request, Car $car): void
    {
        abort_unless((int) $car->tenant_id === (int) ($request->user()?->tenant_id ?? 0), 404);
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);
    }

    private function carPayload(Car $car): array
    {
        return [
            'id' => $car->id,
            'name' => trim("{$car->year} {$car->make} {$car->model}"),
            'license_plate' => $car->license_plate,
            'branch_id' => $car->branch_id,
        ];
    }

    private function historyPayload(CarPhotoHistory $history, Request $request): array
    {
        $locale = $this->locale($request);

        return [
            'id' => $history->id,
            'car_id' => $history->car_id,
            'reason' => $history->reason,
            'reason_label' => self::REASONS[$history->reason][$locale] ?? self::REASONS[$history->reason]['en'] ?? $history->reason,
            'notes' => $history->notes,
            'user' => $history->user ? [
                'id' => $history->user->id,
                'name' => $history->user->name,
            ] : null,
            'photos_count' => $history->files->where('collection', 'photos')->count(),
            'photos' => $history->files
                ->where('collection', 'photos')
                ->values()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => FileUrl::fromStoragePath($file->path),
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                ])
                ->all(),
            'created_at' => $history->created_at?->toISOString(),
        ];
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
                ['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240']]
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
    private function storeUploadedPhotos(CarPhotoHistory $history, array $files): void
    {
        $disk = config('vilt-filepond.storage_disk', 'public');

        foreach (array_values($files) as $index => $file) {
            $path = $file->store("car-photo-histories/{$history->id}", $disk);

            $history->files()->create([
                'original_name' => $file->getClientOriginalName(),
                'filename' => basename($path),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'collection' => 'photos',
                'order' => $index,
            ]);
        }
    }

    private function locale(Request $request): string
    {
        $locale = strtolower(substr((string) $request->header('Accept-Language', app()->getLocale()), 0, 2));

        return in_array($locale, ['ar', 'ur'], true) ? $locale : 'en';
    }
}
