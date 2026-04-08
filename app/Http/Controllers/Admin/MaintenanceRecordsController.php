<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\CarStatus;
use App\Enums\MaintenanceRecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarMaintenance;
use App\Models\MaintenanceType;
use App\Models\MaintenanceWorkshop;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceRecordsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $carId = $this->branchAccess->normalizeRequestedBranchId($request->input('car_id'));

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values();

        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $carsQuery = Car::query()
            ->select(['id', 'make', 'model', 'year', 'license_plate', 'branch_id'])
            ->orderBy('make')
            ->orderBy('model');
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);
        $cars = $carsQuery
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            ])
            ->values();

        $query = CarMaintenance::query()
            ->with([
                'car:id,make,model,year,license_plate',
                'branch:id,name',
                'maintenanceType:id,name',
                'creator:id,name',
            ]);

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        if ($carId) {
            $query->where('car_id', $carId);
        }

        $query
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('workshop_name', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('car', function ($carQuery) use ($search) {
                            $carQuery->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('license_plate', 'like', "%{$search}%");
                        })
                        ->orWhereHas('maintenanceType', fn ($typeQuery) => $typeQuery->where('name', 'like', "%{$search}%"));
                });
            });

        $records = $query
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $records->getCollection()->transform(function (CarMaintenance $record) {
            $status = $record->status;
            return [
                'id' => $record->id,
                'car' => $record->car
                    ? trim("{$record->car->year} {$record->car->make} {$record->car->model} ({$record->car->license_plate})")
                    : '-',
                'type' => $record->maintenanceType?->name ?? '-',
                'branch' => $record->branch?->name ?? '-',
                'status' => $status instanceof MaintenanceRecordStatus ? $status->value : (string) $status,
                'status_label' => $status instanceof MaintenanceRecordStatus ? $status->label() : ucfirst(str_replace('_', ' ', (string) $status)),
                'status_color' => $status instanceof MaintenanceRecordStatus ? $status->color() : '#6B7280',
                'scheduled_date' => optional($record->scheduled_date)?->toDateString(),
                'cost' => $record->cost !== null ? (float) $record->cost : null,
                'workshop_name' => $record->workshop_name,
                'edit_url' => route('admin.maintenance-records.edit', $record),
                'destroy_url' => route('admin.maintenance-records.destroy', $record),
            ];
        });

        $statuses = collect(MaintenanceRecordStatus::cases())->map(fn ($statusCase) => [
            'value' => $statusCase->value,
            'label' => $statusCase->label(),
            'color' => $statusCase->color(),
        ])->values();

        return Inertia::render('Admin/MaintenanceRecords/Index', [
            'records' => $records,
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'cars' => $cars,
            'canAccessAllBranches' => $canAccessAllBranches,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
                'car_id' => $carId,
            ],
            'indexUrl' => route('admin.maintenance-records.index'),
            'createUrl' => route('admin.maintenance-records.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/MaintenanceRecords/Edit', [
            'record' => null,
            ...$this->formOptions($request),
            'indexUrl' => route('admin.maintenance-records.index'),
            'submitUrl' => route('admin.maintenance-records.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRecord($request);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car = $this->resolveAccessibleCar($request, (int) $validated['car_id']);
        $workshop = $this->resolveAccessibleWorkshop(
            $request,
            $validated['maintenance_workshop_id'] ?? null,
            $validated['maintenance_type_id'] ?? null,
        );

        $maintenance = CarMaintenance::create([
            'car_id' => $car->id,
            'branch_id' => $car->branch_id,
            'maintenance_type_id' => $validated['maintenance_type_id'] ?? null,
            'maintenance_workshop_id' => $workshop?->id,
            'status' => $validated['status'],
            'scheduled_date' => $validated['scheduled_date'] ?? null,
            'started_at' => $validated['started_at'] ?? null,
            'completed_at' => $validated['completed_at'] ?? null,
            'cost' => $validated['cost'] ?? null,
            'odometer' => $validated['odometer'] ?? null,
            'workshop_name' => $workshop?->name,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);
        $this->syncCarStatusForCarId((int) $maintenance->car_id);

        return redirect()
            ->route('admin.maintenance-records.index')
            ->with('success', 'Maintenance record created successfully.');
    }

    public function edit(Request $request, CarMaintenance $maintenance): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $maintenance->branch_id ? (int) $maintenance->branch_id : null), 403);

        return Inertia::render('Admin/MaintenanceRecords/Edit', [
            'record' => [
                'id' => $maintenance->id,
                'car_id' => $maintenance->car_id,
                'maintenance_type_id' => $maintenance->maintenance_type_id,
                'maintenance_workshop_id' => $this->resolveSelectedWorkshopId($maintenance),
                'status' => $maintenance->status instanceof MaintenanceRecordStatus ? $maintenance->status->value : (string) $maintenance->status,
                'scheduled_date' => optional($maintenance->scheduled_date)?->toDateString(),
                'started_at' => optional($maintenance->started_at)?->format('Y-m-d\TH:i'),
                'completed_at' => optional($maintenance->completed_at)?->format('Y-m-d\TH:i'),
                'cost' => $maintenance->cost !== null ? (float) $maintenance->cost : null,
                'odometer' => $maintenance->odometer,
                'workshop_name' => $maintenance->workshop_name,
                'notes' => $maintenance->notes,
            ],
            ...$this->formOptions($request),
            'indexUrl' => route('admin.maintenance-records.index'),
            'submitUrl' => route('admin.maintenance-records.update', $maintenance),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, CarMaintenance $maintenance): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $maintenance->branch_id ? (int) $maintenance->branch_id : null), 403);

        $validated = $this->validateRecord($request);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $previousCarId = (int) $maintenance->car_id;
        $car = $this->resolveAccessibleCar($request, (int) $validated['car_id']);
        $workshop = $this->resolveAccessibleWorkshop(
            $request,
            $validated['maintenance_workshop_id'] ?? null,
            $validated['maintenance_type_id'] ?? null,
        );

        $maintenance->update([
            'car_id' => $car->id,
            'branch_id' => $car->branch_id,
            'maintenance_type_id' => $validated['maintenance_type_id'] ?? null,
            'maintenance_workshop_id' => $workshop?->id,
            'status' => $validated['status'],
            'scheduled_date' => $validated['scheduled_date'] ?? null,
            'started_at' => $validated['started_at'] ?? null,
            'completed_at' => $validated['completed_at'] ?? null,
            'cost' => $validated['cost'] ?? null,
            'odometer' => $validated['odometer'] ?? null,
            'workshop_name' => $workshop?->name,
            'notes' => $validated['notes'] ?? null,
        ]);
        $this->syncCarStatusForCarId((int) $maintenance->car_id);
        if ($previousCarId !== (int) $maintenance->car_id) {
            $this->syncCarStatusForCarId($previousCarId);
        }

        return redirect()
            ->route('admin.maintenance-records.index')
            ->with('success', 'Maintenance record updated successfully.');
    }

    public function destroy(Request $request, CarMaintenance $maintenance): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $maintenance->branch_id ? (int) $maintenance->branch_id : null), 403);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $carId = (int) $maintenance->car_id;
        $maintenance->delete();
        $this->syncCarStatusForCarId($carId);

        return back()->with('success', 'Maintenance record deleted successfully.');
    }

    private function validateRecord(Request $request): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);

        return $request->validate([
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')],
            'maintenance_type_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_types', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'maintenance_workshop_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_workshops', 'id')->where(function ($q) use ($tenantId, $request) {
                    $q->where('tenant_id', $tenantId);

                    if ($request->filled('maintenance_type_id')) {
                        $q->where('maintenance_type_id', (int) $request->input('maintenance_type_id'));
                    }
                }),
            ],
            'status' => ['required', 'string', Rule::enum(MaintenanceRecordStatus::class)],
            'scheduled_date' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function resolveAccessibleCar(Request $request, int $carId): Car
    {
        $query = Car::query()->whereKey($carId);
        $this->branchAccess->applyToQuery($query, $request->user(), null);
        $car = $query->first();

        abort_if(!$car, 422, 'Selected car is not accessible.');

        return $car;
    }

    private function syncCarStatusForCarId(int $carId): void
    {
        $car = Car::query()->find($carId);
        if (!$car) {
            return;
        }

        $hasInProgressMaintenance = CarMaintenance::query()
            ->where('car_id', $carId)
            ->where('status', MaintenanceRecordStatus::IN_PROGRESS->value)
            ->exists();

        if ($hasInProgressMaintenance) {
            if ($car->status !== CarStatus::MAINTENANCE) {
                $car->update(['status' => CarStatus::MAINTENANCE->value]);
            }
            return;
        }

        if ($car->status === CarStatus::MAINTENANCE) {
            $car->update(['status' => CarStatus::AVAILABLE->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $user = $request->user();

        $carsQuery = Car::query()
            ->select(['id', 'year', 'make', 'model', 'license_plate']);
        $this->branchAccess->applyToQuery($carsQuery, $user, null);
        $cars = $carsQuery
            ->orderBy('make')
            ->orderBy('model')
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            ])
            ->values();

        $maintenanceTypes = MaintenanceType::query()
            ->with(['workshops' => fn ($query) => $query
                ->select(['id', 'maintenance_type_id', 'name', 'phone', 'city', 'country'])
                ->orderBy('name')])
            ->select(['id', 'name', 'is_active', 'sort_order'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MaintenanceType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'workshops' => $type->workshops->map(fn (MaintenanceWorkshop $workshop) => [
                    'id' => $workshop->id,
                    'name' => $workshop->name,
                    'phone' => $workshop->phone,
                    'city' => $workshop->city,
                    'country' => $workshop->country,
                    'label' => trim($workshop->name.($workshop->city ? " - {$workshop->city}" : '').($workshop->phone ? " ({$workshop->phone})" : '')),
                ])->values(),
            ])
            ->values();

        $statuses = collect(MaintenanceRecordStatus::cases())->map(fn ($statusCase) => [
            'value' => $statusCase->value,
            'label' => $statusCase->label(),
            'color' => $statusCase->color(),
        ])->values();

        return [
            'cars' => $cars,
            'maintenanceTypes' => $maintenanceTypes,
            'statuses' => $statuses,
        ];
    }

    private function resolveAccessibleWorkshop(Request $request, mixed $workshopId, mixed $maintenanceTypeId): ?MaintenanceWorkshop
    {
        if (!$workshopId) {
            return null;
        }

        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);

        return MaintenanceWorkshop::query()
            ->where('tenant_id', $tenantId)
            ->where('maintenance_type_id', (int) $maintenanceTypeId)
            ->findOrFail((int) $workshopId);
    }

    private function resolveSelectedWorkshopId(CarMaintenance $maintenance): ?int
    {
        if ($maintenance->maintenance_workshop_id) {
            return (int) $maintenance->maintenance_workshop_id;
        }

        if (!$maintenance->maintenance_type_id || !$maintenance->workshop_name) {
            return null;
        }

        return MaintenanceWorkshop::query()
            ->where('maintenance_type_id', $maintenance->maintenance_type_id)
            ->where('name', $maintenance->workshop_name)
            ->value('id');
    }
}
