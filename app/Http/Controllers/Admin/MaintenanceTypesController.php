<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceType;
use App\Models\MaintenanceWorkshop;
use App\Support\BranchLocationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class MaintenanceTypesController extends Controller
{
    public function __construct(private readonly FilePondService $filePondService)
    {
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $maintenanceTypes = MaintenanceType::query()
            ->withCount('workshops')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $maintenanceTypes->getCollection()->transform(function (MaintenanceType $type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'is_active' => (bool) $type->is_active,
                'sort_order' => (int) $type->sort_order,
                'workshops_count' => (int) $type->workshops_count,
                'edit_url' => route('admin.maintenance-types.edit', $type),
                'destroy_url' => route('admin.maintenance-types.destroy', $type),
            ];
        });

        return Inertia::render('Admin/MaintenanceTypes/Index', [
            'maintenanceTypes' => $maintenanceTypes,
            'filters' => [
                'search' => $search,
            ],
            'indexUrl' => route('admin.maintenance-types.index'),
            'createUrl' => route('admin.maintenance-types.create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/MaintenanceTypes/Edit', [
            'maintenanceType' => null,
            'indexUrl' => route('admin.maintenance-types.index'),
            'submitUrl' => route('admin.maintenance-types.store'),
            'method' => 'post',
            'workshops' => [],
            'countries' => BranchLocationOptions::countrySelectOptions(app()->getLocale()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('maintenance_types', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'workshops' => ['array'],
            'workshops.*.name' => ['required', 'string', 'max:255'],
            'workshops.*.phone' => ['required', 'string', 'max:50'],
            'workshops.*.rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'workshops.*.country' => ['nullable', 'string', 'max:10'],
            'workshops.*.city' => ['nullable', 'string', 'max:100'],
            'workshops.*.street_name' => ['nullable', 'string', 'max:255'],
            'workshops.*.street_number' => ['nullable', 'string', 'max:50'],
            'workshops.*.building_number' => ['nullable', 'string', 'max:50'],
            'workshops.*.office_number' => ['nullable', 'string', 'max:50'],
            'workshops.*.post_code' => ['nullable', 'string', 'max:50'],
            'workshops.*.google_map_url' => ['nullable', 'url', 'max:1000'],
            'workshops.*.front_image_temp_folders' => ['array'],
            'workshops.*.front_image_temp_folders.*' => ['string'],
        ]);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $maintenanceType = MaintenanceType::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $this->syncWorkshops($maintenanceType, $validated['workshops'] ?? []);

        return redirect()
            ->route('admin.maintenance-types.index')
            ->with('success', 'Maintenance type created successfully.');
    }

    public function edit(MaintenanceType $maintenanceType): Response
    {
        $maintenanceType->load('workshops.files');

        return Inertia::render('Admin/MaintenanceTypes/Edit', [
            'maintenanceType' => [
                'id' => $maintenanceType->id,
                'name' => $maintenanceType->name,
                'description' => $maintenanceType->description,
                'is_active' => (bool) $maintenanceType->is_active,
                'sort_order' => (int) $maintenanceType->sort_order,
            ],
            'workshops' => $maintenanceType->workshops->map(function (MaintenanceWorkshop $workshop) {
                $existingFile = $workshop->files->firstWhere('collection', 'front_image');

                return [
                    'id' => $workshop->id,
                    'name' => $workshop->name,
                    'phone' => $workshop->phone,
                    'rate' => $workshop->rate !== null ? (string) $workshop->rate : '',
                    'country' => $workshop->country,
                    'city' => $workshop->city,
                    'street_name' => $workshop->street_name,
                    'street_number' => $workshop->street_number,
                    'building_number' => $workshop->building_number,
                    'office_number' => $workshop->office_number,
                    'post_code' => $workshop->post_code,
                    'google_map_url' => $workshop->google_map_url,
                    'frontImageFiles' => $existingFile ? [[
                        'id' => $existingFile->id,
                        'url' => $workshop->front_image_url,
                    ]] : [],
                ];
            })->values(),
            'indexUrl' => route('admin.maintenance-types.index'),
            'submitUrl' => route('admin.maintenance-types.update', $maintenanceType),
            'method' => 'put',
            'countries' => BranchLocationOptions::countrySelectOptions(app()->getLocale()),
        ]);
    }

    public function update(Request $request, MaintenanceType $maintenanceType): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('maintenance_types', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($maintenanceType->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'workshops' => ['array'],
            'workshops.*.id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_workshops', 'id')->where(fn ($q) => $q->where('maintenance_type_id', $maintenanceType->id)->where('tenant_id', $tenantId)),
            ],
            'workshops.*.name' => ['required', 'string', 'max:255'],
            'workshops.*.phone' => ['required', 'string', 'max:50'],
            'workshops.*.rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'workshops.*.country' => ['nullable', 'string', 'max:10'],
            'workshops.*.city' => ['nullable', 'string', 'max:100'],
            'workshops.*.street_name' => ['nullable', 'string', 'max:255'],
            'workshops.*.street_number' => ['nullable', 'string', 'max:50'],
            'workshops.*.building_number' => ['nullable', 'string', 'max:50'],
            'workshops.*.office_number' => ['nullable', 'string', 'max:50'],
            'workshops.*.post_code' => ['nullable', 'string', 'max:50'],
            'workshops.*.google_map_url' => ['nullable', 'url', 'max:1000'],
            'workshops.*.front_image_temp_folders' => ['array'],
            'workshops.*.front_image_temp_folders.*' => ['string'],
            'workshops.*.front_image_removed_file_ids' => ['array'],
            'workshops.*.front_image_removed_file_ids.*' => ['integer'],
        ]);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $maintenanceType->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $this->syncWorkshops($maintenanceType, $validated['workshops'] ?? []);

        return redirect()
            ->route('admin.maintenance-types.index')
            ->with('success', 'Maintenance type updated successfully.');
    }

    public function destroy(MaintenanceType $maintenanceType): RedirectResponse
    {
        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $maintenanceType->delete();

        return back()->with('success', 'Maintenance type deleted successfully.');
    }

    private function tenantId(Request $request): int
    {
        return (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);
    }

    /**
     * @param array<int, array<string, mixed>> $workshops
     */
    private function syncWorkshops(MaintenanceType $maintenanceType, array $workshops): void
    {
        $existingIds = $maintenanceType->workshops()->pluck('id')->all();
        $submittedIds = collect($workshops)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $deleteIds = array_values(array_diff($existingIds, $submittedIds));
        if (!empty($deleteIds)) {
            MaintenanceWorkshop::query()
                ->where('maintenance_type_id', $maintenanceType->id)
                ->whereIn('id', $deleteIds)
                ->delete();
        }

        foreach ($workshops as $payload) {
            $workshop = null;

            if (!empty($payload['id'])) {
                $workshop = MaintenanceWorkshop::query()
                    ->where('maintenance_type_id', $maintenanceType->id)
                    ->find((int) $payload['id']);
            }

            if (!$workshop) {
                $workshop = new MaintenanceWorkshop([
                    'tenant_id' => $maintenanceType->tenant_id,
                    'maintenance_type_id' => $maintenanceType->id,
                ]);
            }

            $workshop->fill([
                'tenant_id' => $maintenanceType->tenant_id,
                'maintenance_type_id' => $maintenanceType->id,
                'name' => $payload['name'] ?? '',
                'phone' => $payload['phone'] ?? '',
                'rate' => $payload['rate'] ?? 0,
                'country' => $this->nullableString($payload['country'] ?? null),
                'city' => $this->nullableString($payload['city'] ?? null),
                'street_name' => $this->nullableString($payload['street_name'] ?? null),
                'street_number' => $this->nullableString($payload['street_number'] ?? null),
                'building_number' => $this->nullableString($payload['building_number'] ?? null),
                'office_number' => $this->nullableString($payload['office_number'] ?? null),
                'post_code' => $this->nullableString($payload['post_code'] ?? null),
                'google_map_url' => $this->nullableString($payload['google_map_url'] ?? null),
            ]);
            $workshop->save();

            $this->filePondService->handleFileUpdates(
                $workshop,
                $payload['front_image_temp_folders'] ?? [],
                $payload['front_image_removed_file_ids'] ?? [],
                'front_image'
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
