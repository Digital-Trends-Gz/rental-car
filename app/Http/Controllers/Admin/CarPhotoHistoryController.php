<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarPhotoHistory;
use App\Support\BranchAccess;
use App\Support\FileUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class CarPhotoHistoryController extends Controller
{
    public function __construct(
        protected BranchAccess $branchAccess,
        protected FilePondService $filePondService
    ) {}

    public function index(Request $request, Car $car): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $car->load('branch:id,name');

        $histories = $car->photoHistories()
            ->with(['user:id,name', 'files'])
            ->latest()
            ->get()
            ->map(function ($history) use ($car) {
                return [
                    'id' => $history->id,
                    'reason' => $history->reason,
                    'notes' => $history->notes,
                    'user_name' => $history->user?->name,
                    'created_at' => $history->created_at?->format('Y-m-d H:i'),
                    'photos_count' => $history->files->count(),
                    'edit_url' => route('admin.cars.photo-histories.edit', [$car, $history]),
                ];
            });

        return Inertia::render('Admin/Cars/PhotoHistories/Index', [
            'car' => [
                'id' => $car->id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'license_plate' => $car->license_plate,
                'image_url' => $car->image_url,
                'branch_name' => $car->branch?->name,
            ],
            'histories' => $histories,
        ]);
    }

    public function create(Request $request, Car $car): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $car->load('branch:id,name');

        return Inertia::render('Admin/Cars/PhotoHistories/Edit', [
            'car' => [
                'id' => $car->id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'license_plate' => $car->license_plate,
                'image_url' => $car->image_url,
                'branch_name' => $car->branch?->name,
            ],
            'history' => null,
            'imageFiles' => [],
        ]);
    }

    public function store(Request $request, Car $car): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:before_delivery,after_return,new_damage,after_cleaning,after_maintenance'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos_temp_folders' => ['array'],
            'photos_temp_folders.*' => ['string'],
            'photos_removed_files' => ['array'],
            'photos_removed_files.*' => ['integer'],
        ]);

        $history = $car->photoHistories()->create([
            'tenant_id' => $car->tenant_id,
            'user_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'],
        ]);

        $this->filePondService->handleFileUpdates(
            $history,
            $validated['photos_temp_folders'] ?? [],
            $validated['photos_removed_files'] ?? [],
            'photos'
        );

        return redirect()->route('admin.cars.photo-histories.index', $car->id)
            ->with('success', 'Photo history record created successfully.');
    }

    public function edit(Request $request, Car $car, CarPhotoHistory $photoHistory): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        if ($photoHistory->car_id !== $car->id) {
            abort(404);
        }

        $car->load('branch:id,name');
        $photoHistory->load('files');

        return Inertia::render('Admin/Cars/PhotoHistories/Edit', [
            'car' => [
                'id' => $car->id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'license_plate' => $car->license_plate,
                'image_url' => $car->image_url,
                'branch_name' => $car->branch?->name,
            ],
            'history' => [
                'id' => $photoHistory->id,
                'reason' => $photoHistory->reason,
                'notes' => $photoHistory->notes,
            ],
            'imageFiles' => $photoHistory->files
                ->filter(fn ($file) => $file->collection === 'photos')
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => FileUrl::fromStoragePath($file->path),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(Request $request, Car $car, CarPhotoHistory $photoHistory): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        if ($photoHistory->car_id !== $car->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:before_delivery,after_return,new_damage,after_cleaning,after_maintenance'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos_temp_folders' => ['array'],
            'photos_temp_folders.*' => ['string'],
            'photos_removed_files' => ['array'],
            'photos_removed_files.*' => ['integer'],
        ]);

        $photoHistory->update([
            'reason' => $validated['reason'],
            'notes' => $validated['notes'],
        ]);

        $this->filePondService->handleFileUpdates(
            $photoHistory,
            $validated['photos_temp_folders'] ?? [],
            $validated['photos_removed_files'] ?? [],
            'photos'
        );

        return redirect()->route('admin.cars.photo-histories.index', $car->id)
            ->with('success', 'Photo history record updated successfully.');
    }

    public function destroy(Request $request, Car $car, CarPhotoHistory $photoHistory): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        if ($photoHistory->car_id !== $car->id) {
            abort(404);
        }

        $photoHistory->delete();

        return redirect()->route('admin.cars.photo-histories.index', $car->id)
            ->with('success', 'Photo history record deleted successfully.');
    }
}
