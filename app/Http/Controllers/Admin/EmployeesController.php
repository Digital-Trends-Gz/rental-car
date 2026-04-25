<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
    public function __construct(
        private BranchAccess $branchAccess,
        private PlanUsageLimits $planUsageLimits
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
        ]);
    }

    public function create(): Response
    {
        $branches = $this->branchAccess->availableBranchesForUser(request()->user());
        $roles = Role::orderBy('display_name')->get(['id', 'display_name']);
        $permissions = Permission::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('name', 'like', 'tenant-%')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'description']);

        return Inertia::render('Admin/Employees/Edit', [
            'employee' => null,
            'branches' => $branches,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        if ($message = $this->planUsageLimits->employeeLimitMessage()) {
            return redirect()->back()->with('error', $message);
        }

        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($request->user());
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
            'role_ids' => ['array'],
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
            'permission_ids' => ['array'],
            'permission_ids.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query
                    ->whereNull('tenant_id')
                    ->where('name', 'like', 'tenant-%')),
            ],
        ]);

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $request->user(),
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

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

        if (!empty($validated['role_ids'])) {
            $employee->syncRoles($validated['role_ids']);
        }

        if (!empty($validated['permission_ids'])) {
            $employee->permissions()->sync(
                collect($validated['permission_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all()
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
        $roles = Role::orderBy('display_name')->get(['id', 'display_name']);
        $permissions = Permission::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('name', 'like', 'tenant-%')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'description']);

        return Inertia::render('Admin/Employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'civil_number' => $employee->civil_number,
                'branch_id' => $employee->branch_id,
                'is_active' => (bool) $employee->is_active,
                'role_ids' => $employee->roles->pluck('id')->toArray(),
                'permission_ids' => $employee->permissions()->withoutGlobalScope('tenant')->pluck('permissions.id')->toArray(),
            ],
            'branches' => $branches,
            'roles' => $roles,
            'permissions' => $permissions,
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
            'role_ids' => ['array'],
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
            'permission_ids' => ['array'],
            'permission_ids.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query
                    ->whereNull('tenant_id')
                    ->where('name', 'like', 'tenant-%')),
            ],
        ]);

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $request->user(),
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

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

        $employee->syncRoles($validated['role_ids'] ?? []);
        $employee->permissions()->sync(
            collect($validated['permission_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
        );

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

    private function isAdminEmployee(User $employee): bool
    {
        return $employee->role === UserRole::ADMIN
            || (string) $employee->role === UserRole::ADMIN->value;
    }
}
