<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\Plans\PlanPermissionAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RolesController extends Controller
{
    public function __construct(
        private readonly PlanPermissionAccess $planPermissionAccess,
    ) {}

    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['permissions'])
            ->orderBy('display_name')
            ->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $permissions = $this->planPermissionAccess->tenantPermissions();

        return Inertia::render('Admin/Roles/Edit', [
            'role' => null,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $tenantId = $this->tenantId();
        $allowedPermissionIds = $this->planPermissionAccess->tenantPermissionQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $request->merge([
            'name' => Str::slug((string) $request->input('display_name')),
        ]);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [Rule::in($allowedPermissionIds)],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
            'tenant_id' => $tenantId,
        ]);

        $permissionIds = $this->planPermissionAccess->allowedIdsFromInput($validated['permission_ids'] ?? []);

        $role->permissions()->sync($permissionIds);

        return redirect()
            ->route('admin.roles.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): Response
    {
        $role->load([
            'permissions' => fn ($query) => $query->select('permissions.id'),
        ]);
        
        $permissions = $this->planPermissionAccess->tenantPermissions();
        $allowedPermissionIds = $permissions->pluck('id')->map(fn ($id) => (int) $id)->all();

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'permission_ids' => $role->permissions
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->intersect($allowedPermissionIds)
                    ->values()
                    ->toArray(),
            ],
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $tenantId = $this->tenantId();
        $allowedPermissionIds = $this->planPermissionAccess->tenantPermissionQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $request->merge([
            'name' => Str::slug((string) $request->input('display_name')),
        ]);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [Rule::in($allowedPermissionIds)],
        ]);

        $role->update([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
        ]);

        $permissionIds = $this->planPermissionAccess->allowedIdsFromInput($validated['permission_ids'] ?? []);

        $role->permissions()->sync($permissionIds);

        return redirect()
            ->route('admin.roles.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        // Check if role is in use
        if ($role->users()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete role that is assigned to employees.');
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Role deleted successfully.');
    }

    private function tenantId(): int
    {
        return (int) (TenantContext::id() ?? auth()->user()?->tenant_id ?? 0);
    }
}
