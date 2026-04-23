<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDocument;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class CarDocumentsController extends Controller
{
    public function __construct(
        private readonly FilePondService $filePondService,
        private readonly BranchAccess $branchAccess,
    ) {
    }

    public function index(Car $car): Response
    {
        $car = $this->resolveCar(request(), $car);
        $this->authorizeCar($car);
        $car->load([
            'documents.files',
            'branch:id,name',
        ]);

        return Inertia::render('Admin/Cars/Documents/Index', [
            'car' => $this->carPayload($car),
            'documents' => $car->documents->map(fn (CarDocument $document) => $this->documentPayload($document))->values(),
            'documentTypes' => $this->documentTypeOptions(),
        ]);
    }

    public function create(Car $car): Response
    {
        $car = $this->resolveCar(request(), $car);
        $this->authorizeCar($car);

        return Inertia::render('Admin/Cars/Documents/Edit', [
            'car' => $this->carPayload($car->load('branch:id,name')),
            'document' => null,
            'frontImageFiles' => [],
            'backImageFiles' => [],
            'documentTypes' => $this->documentTypeOptions(),
        ]);
    }

    public function store(Request $request, Car $car): RedirectResponse
    {
        $car = $this->resolveCar($request, $car);
        $subdomain = $request->route('subdomain');
        $this->authorizeCar($car);
        $validated = $this->validateDocument($request);

        $document = CarDocument::create([
            'tenant_id' => (int) $car->tenant_id,
            'car_id' => (int) $car->id,
            'type' => $validated['type'],
            'document_number' => $this->nullableString($validated['document_number'] ?? null),
            'issuer' => $this->nullableString($validated['issuer'] ?? null),
            'issue_date' => $validated['issue_date'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'expiry_date' => $validated['type'] === 'purchase_contract'
                ? ($validated['purchase_date'] ?? null)
                : ($validated['expiry_date'] ?? null),
            'cost' => $validated['cost'] ?? null,
            'notes' => $this->nullableString($validated['notes'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $this->syncImages($document, $request);

        return redirect()
            ->route('admin.cars.documents.index', ['subdomain' => $subdomain, 'car' => $car->id])
            ->with('success', 'Car document created successfully.');
    }

    public function edit(Car $car, CarDocument $document): Response
    {
        $car = $this->resolveCar(request(), $car);
        $this->authorizeDocument($car, $document);
        $document->loadMissing('files');

        return Inertia::render('Admin/Cars/Documents/Edit', [
            'car' => $this->carPayload($car->load('branch:id,name')),
            'document' => $this->documentPayload($document),
            'frontImageFiles' => $document->files
                ->filter(fn ($file) => in_array($file->collection, ['front_image', 'attachment'], true))
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => Storage::url($file->path),
                ])
                ->values()
                ->all(),
            'backImageFiles' => $document->files
                ->where('collection', 'back_image')
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => Storage::url($file->path),
                ])
                ->values()
                ->all(),
            'documentTypes' => $this->documentTypeOptions(),
        ]);
    }

    public function update(Request $request, Car $car, CarDocument $document): RedirectResponse
    {
        $car = $this->resolveCar($request, $car);
        $subdomain = $request->route('subdomain');
        $this->authorizeDocument($car, $document);
        $validated = $this->validateDocument($request);

        $document->update([
            'type' => $validated['type'],
            'document_number' => $this->nullableString($validated['document_number'] ?? null),
            'issuer' => $this->nullableString($validated['issuer'] ?? null),
            'issue_date' => $validated['issue_date'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'expiry_date' => $validated['type'] === 'purchase_contract'
                ? ($validated['purchase_date'] ?? null)
                : ($validated['expiry_date'] ?? null),
            'cost' => $validated['cost'] ?? null,
            'notes' => $this->nullableString($validated['notes'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'ten_day_reminder_sent_at' => null,
        ]);

        $this->syncImages($document, $request);

        return redirect()
            ->route('admin.cars.documents.index', ['subdomain' => $subdomain, 'car' => $car->id])
            ->with('success', 'Car document updated successfully.');
    }

    public function destroy(Car $car, CarDocument $document): RedirectResponse
    {
        $car = $this->resolveCar(request(), $car);
        $subdomain = request()->route('subdomain');
        $this->authorizeDocument($car, $document);
        $document->delete();

        return redirect()
            ->route('admin.cars.documents.index', ['subdomain' => $subdomain, 'car' => $car->id])
            ->with('success', 'Car document deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', Rule::in(CarDocument::TYPES)],
            'document_number' => ['nullable', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'purchase_date' => ['nullable', 'date', 'required_if:type,purchase_contract'],
            'expiry_date' => ['nullable', 'date', 'required_unless:type,purchase_contract'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'front_image_temp_folders' => ['array'],
            'front_image_temp_folders.*' => ['string'],
            'front_image_removed_files' => ['array'],
            'front_image_removed_files.*' => ['integer'],
            'back_image_temp_folders' => ['array'],
            'back_image_temp_folders.*' => ['string'],
            'back_image_removed_files' => ['array'],
            'back_image_removed_files.*' => ['integer'],
        ]);
    }

    private function syncImages(CarDocument $document, Request $request): void
    {
        $this->syncImageCollection(
            $document,
            $request->input('front_image_temp_folders', []),
            $request->input('front_image_removed_files', []),
            'front_image',
            ['front_image', 'attachment'],
        );

        $this->syncImageCollection(
            $document,
            $request->input('back_image_temp_folders', []),
            $request->input('back_image_removed_files', []),
            'back_image',
            ['back_image'],
        );
    }

    private function syncImageCollection(
        CarDocument $document,
        mixed $tempFolders,
        mixed $removedIds,
        string $targetCollection,
        array $replaceCollections,
    ): void {
        $tempFolders = is_array($tempFolders) ? $tempFolders : [];
        $removedIds = is_array($removedIds) ? $removedIds : [];

        if (!empty($tempFolders)) {
            $existingIds = $document->files()
                ->whereIn('collection', $replaceCollections)
                ->pluck('id')
                ->all();

            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $document,
            $tempFolders,
            $removedIds,
            $targetCollection
        );
    }

    private function authorizeCar(Car $car): void
    {
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $car->branch_id), 403);
    }

    private function resolveCar(Request $request, ?Car $car = null): Car
    {
        if ($car && $car->exists) {
            return $car;
        }

        $carId = (int) $request->route('car');

        return Car::query()->findOrFail($carId);
    }

    private function authorizeDocument(Car $car, CarDocument $document): void
    {
        $this->authorizeCar($car);
        abort_unless((int) $document->car_id === (int) $car->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function carPayload(Car $car): array
    {
        return [
            'id' => $car->id,
            'year' => $car->year,
            'make' => $car->make,
            'model' => $car->model,
            'license_plate' => $car->license_plate,
            'branch_name' => $car->branch?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(CarDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'document_number' => $document->document_number,
            'issuer' => $document->issuer,
            'issue_date' => optional($document->issue_date)->toDateString(),
            'purchase_date' => optional($document->purchase_date)->toDateString(),
            'expiry_date' => optional($document->expiry_date)->toDateString(),
            'cost' => $document->cost,
            'notes' => $document->notes,
            'is_active' => (bool) $document->is_active,
            'status_key' => $document->status_key,
            'days_remaining' => $document->days_remaining,
            'front_image_url' => $document->front_image_url,
            'back_image_url' => $document->back_image_url,
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function documentTypeOptions(): array
    {
        return [
            ['value' => 'license', 'label' => CarDocument::labelForType('license')],
            ['value' => 'insurance', 'label' => CarDocument::labelForType('insurance')],
            ['value' => 'purchase_contract', 'label' => CarDocument::labelForType('purchase_contract')],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
