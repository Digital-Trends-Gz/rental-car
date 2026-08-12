<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plans\PlanPermissionAccess;
use App\Services\Plans\PlanUsageLimits;
use App\Rules\DigitsOnly;
use App\Rules\LettersOnly;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class EmployeesController extends Controller
{
    private const TENANT_OWNER_ROLE = 'tenant-owner';
    private const TENANT_PARTNER_ROLE = 'tenant-partner';

    public function __construct(
        private BranchAccess $branchAccess,
        private PlanUsageLimits $planUsageLimits,
        private PlanPermissionAccess $planPermissionAccess
    )
    {
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $employees = User::query()
            ->where('role', UserRole::ADMIN)
            ->when($canAccessAllBranches && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(!$canAccessAllBranches && !empty($user?->branch_id), fn ($q) => $q->where('branch_id', (int) $user->branch_id))
            ->when(!$canAccessAllBranches && empty($user?->branch_id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('civil_number', 'like', "%{$search}%");
                });
            })
            ->with(['branch', 'roles'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $employees->getCollection()->transform(function ($employee) {
            $employee->setRelation(
                'direct_permissions',
                $employee->permissions()->withoutGlobalScope('tenant')->get(['id', 'name', 'display_name'])
            );
            return $employee;
        });

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
            ],
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'employeeUsage' => $this->planUsageLimits->employeeUsage($this->currentTenant()),
        ]);
    }

    public function create(): Response
    {
        if ($message = $this->planUsageLimits->employeeLimitMessage($this->currentTenant())) {
            abort(403, $message);
        }

        $branches = $this->branchAccess->availableBranchesForUser(request()->user());
        $canManageRolesAndPermissions = $this->canManageRolesAndPermissions();
        $roles = $canManageRolesAndPermissions ? $this->assignableRoles() : collect();
        $permissions = $canManageRolesAndPermissions ? $this->planPermissionAccess->tenantPermissions() : collect();

        return Inertia::render('Admin/Employees/Edit', [
            'employee' => null,
            'branches' => $branches,
            'roles' => $roles,
            'permissions' => $permissions,
            'canManageRolesAndPermissions' => $canManageRolesAndPermissions,
        ]);
    }

    public function store(Request $request)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        if ($message = $this->planUsageLimits->employeeLimitMessage($this->currentTenant())) {
            return redirect()->back()->with('error', $message);
        }

        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($request->user());
        $canManageRolesAndPermissions = $this->canManageRolesAndPermissions();
        $allowedPermissionIds = $this->planPermissionAccess->tenantPermissionQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new LettersOnly()],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'civil_number' => ['required', 'string', 'max:255', new DigitsOnly(), Rule::unique('users')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'branch_id' => [
                $canAccessAllBranches ? 'nullable' : 'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
            'is_active' => ['required', 'boolean'],
            'role_ids' => [$canManageRolesAndPermissions ? 'array' : 'prohibited'],
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
            'permission_ids' => [$canManageRolesAndPermissions ? 'array' : 'prohibited'],
            'permission_ids.*' => [Rule::in($allowedPermissionIds)],
        ]);

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $request->user(),
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );
        $roleIds = $canManageRolesAndPermissions ? $this->normalizeAssignableRoleIds(
            $validated['role_ids'] ?? [],
            (string) $validated['email']
        ) : [];

        if ($canManageRolesAndPermissions && $this->roleIdsIncludePartner($roleIds) && !$this->canAssignPartnerRole()) {
            return redirect()->back()
                ->withErrors(['role_ids' => $this->partnerLimitMessage()])
                ->withInput();
        }

        $employee = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'civil_number' => trim((string) $validated['civil_number']),
            'password' => Hash::make($validated['password']),
            'role' => UserRole::ADMIN,
            'tenant_id' => $this->tenantId(),
            'branch_id' => $validated['branch_id'],
            'is_active' => $validated['is_active'],
            'email_verified_at' => now(),
        ]);

        $employee->syncRoles($roleIds);

        if ($canManageRolesAndPermissions && !empty($validated['permission_ids'])) {
            $employee->permissions()->sync(
                $this->planPermissionAccess->allowedIdsFromInput($validated['permission_ids'])
            );
        }

        return redirect()
            ->route('admin.employees.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Employee created successfully.');
    }

    public function edit(User $employee): Response
    {
        abort_unless($this->isAdminEmployee($employee), 403);
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $employee->branch_id ? (int) $employee->branch_id : null), 403);

        $branches = $this->branchAccess->availableBranchesForUser(request()->user());
        $canManageRolesAndPermissions = $this->canManageRolesAndPermissions();
        $roles = $canManageRolesAndPermissions ? $this->assignableRoles($employee) : collect();
        $permissions = $canManageRolesAndPermissions ? $this->planPermissionAccess->tenantPermissions() : collect();
        $allowedPermissionIds = $permissions->pluck('id')->map(fn ($id) => (int) $id)->all();

        return Inertia::render('Admin/Employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'civil_number' => $employee->civil_number,
                'branch_id' => $employee->branch_id,
                'is_active' => (bool) $employee->is_active,
                'role_ids' => $employee->roles->pluck('id')->toArray(),
                'permission_ids' => $employee->permissions()
                    ->withoutGlobalScope('tenant')
                    ->whereIn('permissions.id', $allowedPermissionIds)
                    ->pluck('permissions.id')
                    ->toArray(),
            ],
            'branches' => $branches,
            'roles' => $roles,
            'permissions' => $permissions,
            'canManageRolesAndPermissions' => $canManageRolesAndPermissions,
        ]);
    }

    public function update(Request $request, User $employee)
    {
        abort_unless($this->isAdminEmployee($employee), 403);
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $employee->branch_id ? (int) $employee->branch_id : null), 403);

        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($request->user());
        $canManageRolesAndPermissions = $this->canManageRolesAndPermissions();
        $allowedPermissionIds = $this->planPermissionAccess->tenantPermissionQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new LettersOnly()],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($employee->id)],
            'civil_number' => ['required', 'string', 'max:255', new DigitsOnly(), Rule::unique('users')->ignore($employee->id)],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'branch_id' => [
                $canAccessAllBranches ? 'nullable' : 'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
            'is_active' => ['required', 'boolean'],
            'role_ids' => [$canManageRolesAndPermissions ? 'array' : 'prohibited'],
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
            'permission_ids' => [$canManageRolesAndPermissions ? 'array' : 'prohibited'],
            'permission_ids.*' => [Rule::in($allowedPermissionIds)],
        ]);

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $request->user(),
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );
        $roleIds = $canManageRolesAndPermissions ? $this->normalizeAssignableRoleIds(
            $validated['role_ids'] ?? [],
            (string) $validated['email'],
            $employee
        ) : [];

        if ($canManageRolesAndPermissions && $this->roleIdsIncludePartner($roleIds) && !$this->canAssignPartnerRole($employee)) {
            return redirect()->back()
                ->withErrors(['role_ids' => $this->partnerLimitMessage()])
                ->withInput();
        }

        $employee->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'civil_number' => trim((string) $validated['civil_number']),
            'branch_id' => $validated['branch_id'],
            'is_active' => $validated['is_active'],
        ]);

        if (!empty($validated['password'])) {
            $employee->update(['password' => Hash::make($validated['password'])]);
        }

        if ($canManageRolesAndPermissions) {
            $employee->syncRoles($roleIds);
            $employee->permissions()->sync(
                $this->planPermissionAccess->allowedIdsFromInput($validated['permission_ids'] ?? [])
            );
        }

        return redirect()
            ->route('admin.employees.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(User $employee)
    {
        abort_unless($this->isAdminEmployee($employee), 403);
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $employee->branch_id ? (int) $employee->branch_id : null), 403);

        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        // Prevent self-deletion
        if ($employee->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete yourself.');
        }

        $employee->delete();

        return redirect()
            ->route('admin.employees.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Employee deleted successfully.');
    }

    private function tenantId(): int
    {
        return (int) (TenantContext::id() ?? auth()->user()?->tenant_id ?? 0);
    }

    private function currentTenant(): ?Tenant
    {
        $tenantId = $this->tenantId();

        if ($tenantId <= 0) {
            return null;
        }

        return Tenant::query()->with('subscriptionPlan')->find($tenantId);
    }

    private function canManageRolesAndPermissions(): bool
    {
        $tenant = TenantContext::get();

        if (!$tenant) {
            $tenantId = $this->tenantId();
            $tenant = $tenantId > 0 ? Tenant::query()->find($tenantId) : null;
        }

        if (!$tenant) {
            return false;
        }

        $tenant->loadMissing('subscriptionPlan');

        return $tenant->supportsFeature('roles_and_permissions');
    }

    /**
     * Keep the tenant-owner role only for the tenant's primary account email.
     *
     * @param  array<int, int|string>  $roleIds
     * @return array<int, int>
     */
    private function normalizeAssignableRoleIds(array $roleIds, string $email, ?User $employee = null): array
    {
        $normalizedRoleIds = collect($roleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $tenantOwnerRoleId = $this->tenantOwnerRoleId();

        if (!$tenantOwnerRoleId) {
            return $normalizedRoleIds->all();
        }

        if ($this->isPrimaryTenantEmail($email, $employee)) {
            if (!$normalizedRoleIds->contains($tenantOwnerRoleId)) {
                $normalizedRoleIds->push($tenantOwnerRoleId);
            }

            return $normalizedRoleIds->unique()->values()->all();
        }

        return $normalizedRoleIds
            ->reject(fn (int $roleId) => $roleId === $tenantOwnerRoleId)
            ->values()
            ->all();
    }

    private function tenantOwnerRoleId(): ?int
    {
        $tenantId = $this->tenantId();

        if ($tenantId <= 0) {
            return null;
        }

        $roleId = Role::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('name', self::TENANT_OWNER_ROLE)
            ->value('id');

        return $roleId ? (int) $roleId : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int, name?:string, display_name:string}>
     */
    private function assignableRoles(?User $employee = null)
    {
        $this->ensureTenantFullAccessRole(self::TENANT_PARTNER_ROLE, 'Tenant Partner', 'Full-access partner role for this tenant.');

        $roles = Role::orderBy('display_name')->get(['id', 'name', 'display_name']);

        if ($employee && $this->isPrimaryTenantEmail((string) $employee->email, $employee)) {
            return $roles->values();
        }

        return $roles
            ->reject(fn (Role $role) => $role->name === self::TENANT_OWNER_ROLE)
            ->values();
    }

    private function tenantPartnerRoleId(): ?int
    {
        $tenantId = $this->tenantId();

        if ($tenantId <= 0) {
            return null;
        }

        $roleId = Role::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('name', self::TENANT_PARTNER_ROLE)
            ->value('id');

        return $roleId ? (int) $roleId : null;
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function roleIdsIncludePartner(array $roleIds): bool
    {
        $partnerRoleId = $this->tenantPartnerRoleId();

        return $partnerRoleId !== null && in_array($partnerRoleId, $roleIds, true);
    }

    private function canAssignPartnerRole(?User $employee = null): bool
    {
        $tenant = Tenant::query()->find($this->tenantId());
        $allowedSeats = max(0, (int) ($tenant?->partner_seats ?? 0));

        if ($allowedSeats <= 0) {
            return false;
        }

        $currentPartners = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId())
            ->where('role', UserRole::ADMIN)
            ->whereHas('roles', fn ($query) => $query->where('name', self::TENANT_PARTNER_ROLE))
            ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
            ->count();

        return $currentPartners < $allowedSeats;
    }

    private function partnerLimitMessage(): string
    {
        return trans('site.dashboard.admin.employees.form.partner_seat_limit_reached');
    }

    private function ensureTenantFullAccessRole(string $name, string $displayName, string $description): ?Role
    {
        $tenantId = $this->tenantId();

        if ($tenantId <= 0) {
            return null;
        }

        $role = Role::withoutGlobalScope('tenant')->firstOrCreate(
            [
                'name' => $name,
                'tenant_id' => $tenantId,
            ],
            [
                'display_name' => $displayName,
                'description' => $description,
            ]
        );

        $permissionIds = Permission::withoutGlobalScope('tenant')
            ->where('name', 'like', 'tenant-%')
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($permissionIds);

        return $role;
    }

    private function isPrimaryTenantEmail(string $email, ?User $employee = null): bool
    {
        $tenantId = $this->tenantId();

        if ($tenantId <= 0) {
            return false;
        }

        $tenantEmail = Tenant::query()
            ->whereKey($tenantId)
            ->value('email');

        if (!$tenantEmail) {
            return false;
        }

        if (strcasecmp(trim($email), trim((string) $tenantEmail)) !== 0) {
            return false;
        }

        if ($employee && !empty($employee->exists) && strcasecmp(trim((string) $employee->email), trim((string) $tenantEmail)) !== 0) {
            return false;
        }

        return true;
    }

    private function isAdminEmployee(User $employee): bool
    {
        return $employee->role === UserRole::ADMIN
            || (string) $employee->role === UserRole::ADMIN->value;
    }
}
