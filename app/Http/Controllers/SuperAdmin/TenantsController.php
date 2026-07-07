<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Notifications\TenantAdminInvitationNotification;
use App\Support\BrandLogoImageResizer;
use App\Support\CompanyOwners;
use App\Support\TenantAdminAccessSync;
use App\Rules\DigitsOnly;
use App\Rules\LettersOnly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class TenantsController
{
    public function __construct(
        private readonly FilePondService $filePondService,
        private readonly BrandLogoImageResizer $brandLogoImageResizer,
        private readonly TenantAdminAccessSync $tenantAdminAccessSync,
    ) {}

    /**
     * Display a listing of tenants.
     */
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->with('subscriptionPlan:id,name,max_employees,max_branches,max_cars,max_contracts,openai_requests_per_day')
            ->withCount(['users', 'branches', 'cars', 'reservations', 'contracts'])
            ->latest()
            ->paginate(20);

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Create', [
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name']),
            'logoFiles' => [],
        ]);
    }

    /**
     * Store a newly created tenant in storage and create an admin user for dashboard login.
     */
    public function store(Request $request)
    {
        $request->merge([
            'slug' => Str::slug((string) $request->input('slug')),
            'domain' => $this->normalizeDomain($request->input('domain')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug',
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:tenants,domain'],
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'company_owners' => ['required', 'array', 'min:1'],
            'company_owners.*.name' => ['required', 'string', 'max:255', new LettersOnly()],
            'company_owners.*.commercial_registration_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'company_owners.*.tax_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'company_owners.*.civil_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where(static fn ($query) => $query->where('is_active', true))],
            'logo_temp_folders' => ['array'],
            'logo_temp_folders.*' => ['string'],
            'admin_name' => ['required', 'string', 'max:255', new LettersOnly()],
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $tenantData = [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'domain' => $validated['domain'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'plan_id' => (int) $validated['plan_id'],
            'settings' => [
                'company_owners' => CompanyOwners::normalize($validated['company_owners'] ?? []),
            ],
            'trial_ends_at' => now()->addMonth(),
            'is_active' => true,
        ];

        $tenant = Tenant::create($tenantData);

        $adminUser = User::withoutGlobalScope('tenant')->create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => UserRole::ADMIN,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $this->tenantAdminAccessSync->syncUser($adminUser, $tenant);
        $adminUser->notify(new TenantAdminInvitationNotification($tenant));

        $siteSetting = TenantSiteSetting::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['site_name' => $tenant->name]
        );

        $this->filePondService->handleFileUpdates(
            $siteSetting,
            is_array($request->input('logo_temp_folders', [])) ? $request->input('logo_temp_folders', []) : [],
            [],
            'logo'
        );

        $logoFile = $siteSetting->files()
            ->where('collection', 'logo')
            ->latest('id')
            ->first();

        if ($logoFile) {
            $this->brandLogoImageResizer->resize(
                $logoFile,
                BrandLogoImageResizer::TARGET_WIDTH,
                BrandLogoImageResizer::TARGET_HEIGHT
            );
        }

        return redirect()
            ->route('superadmin.tenants.show', $tenant)
            ->with('success', 'Tenant created and the admin activation email has been sent.');
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): Response
    {
        $tenant->load('subscriptionPlan:id,name');
        $tenant->loadCount(['users', 'cars', 'reservations', 'payments']);
        $tenant->load([
            'users' => fn($query) => $query->latest()->take(5),
            'reservations' => fn($query) => $query->latest()->take(5),
        ]);

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        $tenant->loadMissing('siteSetting.files');

        $adminUser = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN)
            ->first();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'plan_id' => $tenant->plan_id,
                'is_active' => $tenant->is_active,
                'logo_url' => TenantSiteSetting::forTenant($tenant)['logo_url'],
                'company_owners' => data_get($tenant->settings, 'company_owners', []),
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'plans' => $plans,
            'admin_user' => $adminUser ? [
                'id' => $adminUser->id,
                'name' => $adminUser->name,
                'email' => $adminUser->email,
            ] : null,
            'logoFiles' => $tenant->siteSetting
                ? $tenant->siteSetting->files()
                    ->where('collection', 'logo')
                    ->get()
                    ->map(fn ($file) => [
                        'id' => $file->id,
                        'url' => TenantSiteSetting::publicUrlFromPath($file->path),
                    ])
                    ->values()
                    ->all()
                : [],
        ]);
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $request->merge([
            'slug' => Str::slug((string) $request->input('slug')),
            'domain' => $this->normalizeDomain($request->input('domain')),
        ]);

        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $tenant->id,
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:tenants,domain,' . $tenant->id],
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'company_owners' => ['required', 'array', 'min:1'],
            'company_owners.*.name' => ['required', 'string', 'max:255', new LettersOnly()],
            'company_owners.*.commercial_registration_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'company_owners.*.tax_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'company_owners.*.civil_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where(static fn ($query) => $query->where('is_active', true))],
            'is_active' => 'required|boolean',
            'document_extraction_daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'logo_temp_folders' => ['array'],
            'logo_temp_folders.*' => ['string'],
            'logo_removed_files' => ['array'],
            'logo_removed_files.*' => ['integer'],
        ];

        if ($request->filled('admin_password')) {
            $rules['admin_password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $validated = $request->validate($rules);

        $tenant->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'domain' => $validated['domain'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'plan_id' => (int) $validated['plan_id'],
            'is_active' => $validated['is_active'],
            'settings' => array_merge(
                is_array($tenant->settings) ? $tenant->settings : [],
                [
                    'company_owners' => CompanyOwners::normalize($validated['company_owners'] ?? []),
                ]
            ),
        ]);

        if (!empty($validated['admin_password'])) {
            $adminUser = User::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('role', UserRole::ADMIN)
                ->first();

            if ($adminUser) {
                $adminUser->update(['password' => Hash::make($validated['admin_password'])]);
            }
        }

        $adminUser = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN)
            ->first();

        if ($adminUser) {
            $this->tenantAdminAccessSync->syncUser($adminUser, $tenant);
        }

        $siteSetting = TenantSiteSetting::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['site_name' => $tenant->name]
        );

        $dailyLimit = null;
        if (array_key_exists('document_extraction_daily_limit', $validated)) {
            $dailyLimitValue = $validated['document_extraction_daily_limit'];
            $dailyLimit = is_numeric($dailyLimitValue) && (int) $dailyLimitValue >= 1
                ? min((int) $dailyLimitValue, 100000)
                : null;
        }

        $siteSetting->update([
            'document_extraction_daily_limit' => $dailyLimit,
        ]);

        $tempFolders = $request->input('logo_temp_folders', []);
        $removedIds = $request->input('logo_removed_files', []);

        if (!empty($tempFolders)) {
            $existingIds = $siteSetting->files()->where('collection', 'logo')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge(is_array($removedIds) ? $removedIds : [], $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $siteSetting,
            is_array($tempFolders) ? $tempFolders : [],
            is_array($removedIds) ? $removedIds : [],
            'logo'
        );

        if (!empty($tempFolders)) {
            $logoFile = $siteSetting->files()
                ->where('collection', 'logo')
                ->latest('id')
                ->first();

            if ($logoFile) {
                $this->brandLogoImageResizer->resize(
                    $logoFile,
                    BrandLogoImageResizer::TARGET_WIDTH,
                    BrandLogoImageResizer::TARGET_HEIGHT
                );
            }
        }

        return redirect()
            ->route('superadmin.tenants.show', $tenant)
            ->with('success', 'Tenant updated successfully.');
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', 'Tenant deleted successfully.');
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $normalized = strtolower(trim($domain));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = explode('/', $normalized)[0] ?? $normalized;
        $normalized = preg_replace('/:\d+$/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized, '.');

        if (str_starts_with($normalized, 'www.')) {
            $normalized = substr($normalized, 4);
        }

        return $normalized !== '' ? $normalized : null;
    }
}
