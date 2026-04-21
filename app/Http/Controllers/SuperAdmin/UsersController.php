<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class UsersController
{
    /**
     * List users that can log in to Super Admin (role = super_admin).
     */
    public function index(Request $request): Response
    {
        $users = User::withoutGlobalScope('tenant')
            ->where('role', UserRole::SUPER_ADMIN)
            ->with([
                'roles' => fn ($query) => $query
                    ->withoutGlobalScope('tenant')
                    ->whereNull('roles.tenant_id')
                    ->select('roles.id', 'roles.name', 'roles.display_name'),
            ])
            ->latest()
            ->paginate(15);

        $users->setCollection(
            $users->getCollection()->map(function (User $user) {
                $user->setRelation('permissions', $user->allPermissions(['id', 'name', 'display_name']));
                return $user;
            })
        );
        
        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
        ]);

    }

    /**
     * Show form to create a new Super Admin user.
     */
    public function create(): Response
    {
        $roles = Role::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'description']);
        
        return Inertia::render('SuperAdmin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a new Super Admin user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_ids' => 'array',
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->whereNull('tenant_id')),
            ],
        ]);

        $user = User::withoutGlobalScope('tenant')->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::SUPER_ADMIN,
            'tenant_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $roleIds = collect($validated['role_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $user->syncRoles($roleIds);

        return redirect()
            ->route('superadmin.users.index')
            ->with('success', 'Super Admin user created with assigned roles. They can log in at the Super Admin login.');
    }

    /**
     * Show form to edit user roles and permissions.
     */
    public function edit(User $user): Response
    {
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(404);
        }

        $user->load('roles');
        $roles = Role::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'description']);

        return Inertia::render('SuperAdmin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Update user information, password, and roles.
     */
    public function update(Request $request, User $user)
    {
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role_ids' => 'array',
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->whereNull('tenant_id')),
            ],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $roleIds = collect($validated['role_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $user->syncRoles($roleIds);

        return redirect()
            ->route('superadmin.users.index')
            ->with('success', 'User information, password, and roles updated successfully.');
    }

    /**
     * Delete a Super Admin user (not allowed for super_admin role).
     */
    public function destroy(User $user)
    {
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(404);
        }

        // Prevent deletion of all super admin users - need at least one
        $superAdminCount = User::withoutGlobalScope('tenant')
            ->where('role', UserRole::SUPER_ADMIN)
            ->count();

        if ($superAdminCount <= 1) {
            return redirect()
                ->route('superadmin.users.index')
                ->with('error', 'Cannot delete the last Super Admin user. Create another Super Admin user first.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()
            ->route('superadmin.users.index')
            ->with('success', "User '{$userName}' has been deleted successfully.");
    }
}
