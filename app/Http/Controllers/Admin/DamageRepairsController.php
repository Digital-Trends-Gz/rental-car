<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\DamageRepairStatus;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\DamageRepair;
use App\Models\MaintenanceWorkshop;
use App\Services\Cars\DamageRepairStatusSync;
use App\Support\BranchAccess;
use App\Support\CarDamageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DamageRepairsController extends Controller
{
    public function __construct(
        private BranchAccess $branchAccess,
        private DamageRepairStatusSync $damageRepairStatusSync,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $carId = $this->branchAccess->normalizeRequestedBranchId($request->input('car_id'));
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

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

        $zoneLabels = CarDamageCatalog::zoneLabelMap();
        $damageTypeLabels = collect(CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        $query = DamageRepair::query()
            ->with([
                'car:id,make,model,year,license_plate',
                'branch:id,name',
                'damageCase:id,zone_code,damage_type,severity,status',
                'workshop:id,name',
            ]);

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        if ($carId) {
            $query->where('car_id', $carId);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('repair_number', 'like', "%{$search}%")
                    ->orWhere('workshop_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('car', function ($carQuery) use ($search) {
                        $carQuery->where('make', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%");
                    });
            });
        }

        $repairs = $query
            ->latest('opened_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $repairs->getCollection()->transform(function (DamageRepair $repair) use ($zoneLabels, $damageTypeLabels) {
            $statusEnum = $repair->status instanceof DamageRepairStatus
                ? $repair->status
                : DamageRepairStatus::from((string) $repair->status);

            return [
                'id' => $repair->id,
                'repair_number' => $repair->repair_number,
                'car' => $repair->car
                    ? trim("{$repair->car->year} {$repair->car->make} {$repair->car->model} ({$repair->car->license_plate})")
                    : '-',
                'branch' => $repair->branch?->name ?? '-',
                'damage_zone' => $zoneLabels[$repair->damageCase?->zone_code ?? ''] ?? ($repair->damageCase?->zone_code ?? '-'),
                'damage_type' => $damageTypeLabels[$repair->damageCase?->damage_type ?? ''] ?? ($repair->damageCase?->damage_type ?? '-'),
                'workshop_name' => $repair->workshop_name ?: ($repair->workshop?->name ?? '-'),
                'status' => $statusEnum->value,
                'status_label' => $statusEnum->label(),
                'status_color' => $statusEnum->color(),
                'opened_at' => optional($repair->opened_at)?->format('Y-m-d H:i'),
                'completed_at' => optional($repair->completed_at)?->format('Y-m-d H:i'),
                'estimated_cost' => $repair->estimated_cost !== null ? (float) $repair->estimated_cost : null,
                'actual_cost' => $repair->actual_cost !== null ? (float) $repair->actual_cost : null,
                'edit_url' => route('admin.damage-repairs.edit', $repair),
                'destroy_url' => route('admin.damage-repairs.destroy', $repair),
            ];
        });

        return Inertia::render('Admin/DamageRepairs/Index', [
            'repairs' => $repairs,
            'statuses' => $this->statusOptions(),
            'branches' => $branchOptions,
            'cars' => $cars,
            'canAccessAllBranches' => $canAccessAllBranches,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
                'car_id' => $carId,
            ],
            'indexUrl' => route('admin.damage-repairs.index'),
            'createUrl' => route('admin.damage-repairs.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        $prefilledDamageCaseId = (int) $request->integer('damage_case_id');

        return Inertia::render('Admin/DamageRepairs/Edit', [
            'repair' => [
                'damage_case_id' => $prefilledDamageCaseId > 0 ? $prefilledDamageCaseId : null,
                'maintenance_workshop_id' => null,
                'status' => DamageRepairStatus::OPEN->value,
                'opened_at' => now()->format('Y-m-d\TH:i'),
                'started_at' => null,
                'completed_at' => null,
                'estimated_cost' => null,
                'actual_cost' => null,
                'notes' => null,
                'completion_notes' => null,
            ],
            ...$this->formOptions($request, $prefilledDamageCaseId > 0 ? $prefilledDamageCaseId : null),
            'indexUrl' => route('admin.damage-repairs.index'),
            'submitUrl' => route('admin.damage-repairs.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRepair($request);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $damageCase = $this->resolveAccessibleDamageCase($request, (int) $validated['car_damage_case_id']);
        $workshop = $this->resolveAccessibleWorkshop($request, $validated['maintenance_workshop_id'] ?? null);
        $this->ensureNoOtherActiveRepair((int) $damageCase->id, null, (string) $validated['status']);

        DB::transaction(function () use ($request, $validated, $damageCase, $workshop) {
            DamageRepair::create([
                'car_damage_case_id' => $damageCase->id,
                'car_id' => $damageCase->car_id,
                'branch_id' => $damageCase->branch_id,
                'maintenance_workshop_id' => $workshop?->id,
                'repair_number' => $this->generateRepairNumber(),
                'status' => $validated['status'],
                'opened_at' => $validated['opened_at'] ?? now(),
                'started_at' => $validated['started_at'] ?? null,
                'completed_at' => $this->normalizeCompletedAt($validated),
                'estimated_cost' => $validated['estimated_cost'] ?? null,
                'actual_cost' => $validated['actual_cost'] ?? null,
                'workshop_name' => $workshop?->name,
                'notes' => $validated['notes'] ?? null,
                'completion_notes' => $validated['completion_notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $this->damageRepairStatusSync->syncForCase($damageCase);
        });

        return redirect()
            ->route('admin.damage-repairs.index')
            ->with('success', 'Damage repair created successfully.');
    }

    public function edit(Request $request, DamageRepair $damageRepair): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $damageRepair->branch_id ? (int) $damageRepair->branch_id : null), 403);

        return Inertia::render('Admin/DamageRepairs/Edit', [
            'repair' => [
                'id' => $damageRepair->id,
                'damage_case_id' => $damageRepair->car_damage_case_id,
                'maintenance_workshop_id' => $this->resolveSelectedWorkshopId($damageRepair),
                'status' => $damageRepair->status instanceof DamageRepairStatus ? $damageRepair->status->value : (string) $damageRepair->status,
                'opened_at' => optional($damageRepair->opened_at)?->format('Y-m-d\TH:i'),
                'started_at' => optional($damageRepair->started_at)?->format('Y-m-d\TH:i'),
                'completed_at' => optional($damageRepair->completed_at)?->format('Y-m-d\TH:i'),
                'estimated_cost' => $damageRepair->estimated_cost !== null ? (float) $damageRepair->estimated_cost : null,
                'actual_cost' => $damageRepair->actual_cost !== null ? (float) $damageRepair->actual_cost : null,
                'notes' => $damageRepair->notes,
                'completion_notes' => $damageRepair->completion_notes,
            ],
            ...$this->formOptions($request, (int) $damageRepair->car_damage_case_id),
            'indexUrl' => route('admin.damage-repairs.index'),
            'submitUrl' => route('admin.damage-repairs.update', $damageRepair),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, DamageRepair $damageRepair): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $damageRepair->branch_id ? (int) $damageRepair->branch_id : null), 403);

        $validated = $this->validateRepair($request, $damageRepair);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $previousDamageCaseId = (int) $damageRepair->car_damage_case_id;
        $damageCase = $this->resolveAccessibleDamageCase($request, (int) $validated['car_damage_case_id']);
        $workshop = $this->resolveAccessibleWorkshop($request, $validated['maintenance_workshop_id'] ?? null);
        $this->ensureNoOtherActiveRepair((int) $damageCase->id, (int) $damageRepair->id, (string) $validated['status']);

        DB::transaction(function () use ($validated, $damageRepair, $damageCase, $workshop, $previousDamageCaseId) {
            $damageRepair->update([
                'car_damage_case_id' => $damageCase->id,
                'car_id' => $damageCase->car_id,
                'branch_id' => $damageCase->branch_id,
                'maintenance_workshop_id' => $workshop?->id,
                'status' => $validated['status'],
                'opened_at' => $validated['opened_at'] ?? now(),
                'started_at' => $validated['started_at'] ?? null,
                'completed_at' => $this->normalizeCompletedAt($validated),
                'estimated_cost' => $validated['estimated_cost'] ?? null,
                'actual_cost' => $validated['actual_cost'] ?? null,
                'workshop_name' => $workshop?->name,
                'notes' => $validated['notes'] ?? null,
                'completion_notes' => $validated['completion_notes'] ?? null,
            ]);

            if ($previousDamageCaseId !== (int) $damageCase->id) {
                $this->damageRepairStatusSync->syncForCase($previousDamageCaseId);
            }

            $this->damageRepairStatusSync->syncForCase($damageCase);
        });

        return redirect()
            ->route('admin.damage-repairs.index')
            ->with('success', 'Damage repair updated successfully.');
    }

    public function destroy(Request $request, DamageRepair $damageRepair): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $damageRepair->branch_id ? (int) $damageRepair->branch_id : null), 403);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $damageCaseId = (int) $damageRepair->car_damage_case_id;

        DB::transaction(function () use ($damageRepair, $damageCaseId) {
            $damageRepair->delete();
            $this->damageRepairStatusSync->syncForCase($damageCaseId);
        });

        return back()->with('success', 'Damage repair deleted successfully.');
    }

    private function validateRepair(Request $request, ?DamageRepair $repair = null): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);

        return $request->validate([
            'car_damage_case_id' => [
                'required',
                'integer',
                Rule::exists('car_damage_cases', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'maintenance_workshop_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_workshops', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'status' => ['required', 'string', Rule::enum(DamageRepairStatus::class)],
            'opened_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function formOptions(Request $request, ?int $selectedDamageCaseId = null): array
    {
        $user = $request->user();
        $zoneLabels = CarDamageCatalog::zoneLabelMap();
        $damageTypeLabels = collect(CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        $damageCasesQuery = CarDamageCase::query()
            ->with('car:id,year,make,model,license_plate')
            ->where(function ($query) {
                $query->where('status', 'open')
                    ->orWhere('status', 'in_repair');
            })
            ->orderByDesc('last_detected_at')
            ->orderByDesc('id');
        $this->branchAccess->applyToQuery($damageCasesQuery, $user, null, 'branch_id');

        $damageCases = $damageCasesQuery->get();

        if ($selectedDamageCaseId && !$damageCases->contains('id', $selectedDamageCaseId)) {
            $selectedCase = CarDamageCase::query()
                ->with('car:id,year,make,model,license_plate')
                ->find($selectedDamageCaseId);

            if ($selectedCase && $this->branchAccess->canAccessBranchId($user, $selectedCase->branch_id ? (int) $selectedCase->branch_id : null)) {
                $damageCases->push($selectedCase);
            }
        }

        $damageCaseOptions = $damageCases
            ->unique('id')
            ->map(function (CarDamageCase $case) use ($zoneLabels, $damageTypeLabels, $severityLabels) {
                $carLabel = $case->car
                    ? trim("{$case->car->year} {$case->car->make} {$case->car->model} ({$case->car->license_plate})")
                    : 'Unknown car';

                $damageLabel = sprintf(
                    '%s - %s - %s',
                    $zoneLabels[$case->zone_code] ?? $case->zone_code,
                    $damageTypeLabels[$case->damage_type] ?? $case->damage_type,
                    $severityLabels[$case->severity] ?? $case->severity,
                );

                return [
                    'id' => $case->id,
                    'label' => "{$carLabel} - {$damageLabel}",
                    'status' => $case->status,
                ];
            })
            ->values();

        $workshops = MaintenanceWorkshop::query()
            ->select(['id', 'name', 'phone', 'city', 'country'])
            ->where('tenant_id', (int) (TenantContext::id() ?? $user?->tenant_id ?? 0))
            ->orderBy('name')
            ->get()
            ->map(fn (MaintenanceWorkshop $workshop) => [
                'id' => $workshop->id,
                'label' => trim($workshop->name.($workshop->city ? " - {$workshop->city}" : '').($workshop->phone ? " ({$workshop->phone})" : '')),
            ])
            ->values();

        return [
            'damageCases' => $damageCaseOptions,
            'workshops' => $workshops,
            'statuses' => $this->statusOptions(),
        ];
    }

    /**
     * @return array<int, array{value:string,label:string,color:string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (DamageRepairStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
        ], DamageRepairStatus::cases());
    }

    private function resolveAccessibleDamageCase(Request $request, int $damageCaseId): CarDamageCase
    {
        $query = CarDamageCase::query()->whereKey($damageCaseId);
        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');
        $damageCase = $query->first();

        abort_if(!$damageCase, 422, 'Selected damage is not accessible.');

        return $damageCase;
    }

    private function resolveAccessibleWorkshop(Request $request, mixed $workshopId): ?MaintenanceWorkshop
    {
        if (!$workshopId) {
            return null;
        }

        return MaintenanceWorkshop::query()
            ->where('tenant_id', (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0))
            ->findOrFail((int) $workshopId);
    }

    private function resolveSelectedWorkshopId(DamageRepair $damageRepair): ?int
    {
        if ($damageRepair->maintenance_workshop_id) {
            return (int) $damageRepair->maintenance_workshop_id;
        }

        if (!$damageRepair->workshop_name) {
            return null;
        }

        return MaintenanceWorkshop::query()
            ->where('tenant_id', (int) $damageRepair->tenant_id)
            ->where('name', $damageRepair->workshop_name)
            ->value('id');
    }

    private function normalizeCompletedAt(array $validated): ?string
    {
        if (($validated['status'] ?? null) === DamageRepairStatus::COMPLETED->value) {
            return $validated['completed_at'] ?? now()->format('Y-m-d H:i:s');
        }

        return null;
    }

    private function ensureNoOtherActiveRepair(int $damageCaseId, ?int $ignoreRepairId, string $status): void
    {
        if (!in_array($status, DamageRepairStatus::activeValues(), true)) {
            return;
        }

        $query = DamageRepair::query()
            ->where('car_damage_case_id', $damageCaseId)
            ->whereIn('status', DamageRepairStatus::activeValues());

        if ($ignoreRepairId) {
            $query->whereKeyNot($ignoreRepairId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'car_damage_case_id' => 'This damage already has an active repair record.',
            ]);
        }
    }

    private function generateRepairNumber(): string
    {
        $tenantId = (int) (TenantContext::id() ?? auth()->user()?->tenant_id ?? 0);
        $datePrefix = now()->format('Ym');

        $lastNumber = DamageRepair::query()
            ->where('tenant_id', $tenantId)
            ->where('repair_number', 'like', "DRP-{$datePrefix}-%")
            ->orderByDesc('repair_number')
            ->value('repair_number');

        $nextSequence = 1;

        if (is_string($lastNumber) && preg_match('/^DRP-\d{6}-(\d+)$/', $lastNumber, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('DRP-%s-%04d', $datePrefix, $nextSequence);
    }
}
