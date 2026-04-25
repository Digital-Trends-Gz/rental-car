<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlansController extends Controller
{
    private const LIMIT_FIELDS = [
        'max_employees',
        'max_branches',
        'max_cars',
        'max_contracts',
        'openai_requests_per_day',
    ];

    private const FEATURE_FLAGS = [
        'client_portal' => ['label' => 'Client Portal', 'helper' => 'Allow clients to access reservations and notifications.'],
        'booking_calendar' => ['label' => 'Booking Calendar', 'helper' => 'Show the availability calendar to clients and admins.'],
        'cash_payments' => ['label' => 'Cash Payments', 'helper' => 'Enable cash deposits and manual cash collection.'],
        'extension_request' => ['label' => 'Extension Requests', 'helper' => 'Let clients request rental extensions.'],
        'force_extend_contract' => ['label' => 'Force Extend Contract', 'helper' => 'Allow the office to extend contracts immediately.'],
        'car_documents' => ['label' => 'Car Documents', 'helper' => 'Manage purchase and registration documents for cars.'],
        'maintenance_module' => ['label' => 'Maintenance Module', 'helper' => 'Track maintenance records and schedules.'],
        'violations_module' => ['label' => 'Violations Module', 'helper' => 'Manage traffic violations and fines.'],
        'damage_reports' => ['label' => 'Damage Reports', 'helper' => 'Record vehicle damage reports and repairs.'],
        'reports_module' => ['label' => 'Reports Module', 'helper' => 'Show operational reports and analytics.'],
        'pdf_export' => ['label' => 'PDF Export', 'helper' => 'Export contracts and reservations to PDF.'],
        'ai_contract_extraction' => ['label' => 'AI Contract Extraction', 'helper' => 'Use OpenAI to extract contract data from uploads.'],
        'whatsapp_notifications' => ['label' => 'WhatsApp Notifications', 'helper' => 'Send notifications over WhatsApp.'],
        'sms_notifications' => ['label' => 'SMS Notifications', 'helper' => 'Send notifications over SMS.'],
        'email_notifications' => ['label' => 'Email Notifications', 'helper' => 'Send notifications by email.'],
        'custom_branding' => ['label' => 'Custom Branding', 'helper' => 'Allow tenant-specific branding and logos.'],
        'custom_domain' => ['label' => 'Custom Domain', 'helper' => 'Allow tenants to use their own domain.'],
        'stripe_connect' => ['label' => 'Stripe Connect', 'helper' => 'Connect tenant payments to Stripe.'],
        'coupon_system' => ['label' => 'Coupon System', 'helper' => 'Enable coupon creation and usage.'],
        'auto_discounts' => ['label' => 'Auto Discounts', 'helper' => 'Apply automatic discounts based on rules.'],
        'roles_and_permissions' => ['label' => 'Roles and Permissions', 'helper' => 'Manage staff roles and granular permissions.'],
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => Plan::withCount('tenants')->latest()->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Plans/Create', [
            'featureFlags' => $this->featureFlags(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'feature_flags' => 'nullable|array',
            'monthly_price' => 'required|numeric|min:0',
            'monthly_price_id' => 'nullable|string|max:255',
            'yearly_price' => 'required|numeric|min:0',
            'yearly_price_id' => 'nullable|string|max:255',
            'one_time_price' => 'nullable|numeric|min:0',
            'one_time_price_id' => 'nullable|string|max:255',
            'max_employees' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'max_cars' => 'nullable|integer|min:1',
            'max_contracts' => 'nullable|integer|min:1',
            'openai_requests_per_day' => 'nullable|integer|min:1',
            'is_active' => 'required|boolean',
        ], $this->featureFlagValidationRules()));

        Plan::create($this->normalizePlanPayload($validated));

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Plan $plan): Response
    {
        $plan->loadCount('tenants');

        return Inertia::render('SuperAdmin/Plans/Edit', [
            'plan' => $plan,
            'featureFlags' => $this->featureFlags(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'feature_flags' => 'nullable|array',
            'monthly_price' => 'required|numeric|min:0',
            'monthly_price_id' => 'nullable|string|max:255',
            'yearly_price' => 'required|numeric|min:0',
            'yearly_price_id' => 'nullable|string|max:255',
            'one_time_price' => 'nullable|numeric|min:0',
            'one_time_price_id' => 'nullable|string|max:255',
            'max_employees' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'max_cars' => 'nullable|integer|min:1',
            'max_contracts' => 'nullable|integer|min:1',
            'openai_requests_per_day' => 'nullable|integer|min:1',
            'is_active' => 'required|boolean',
        ], $this->featureFlagValidationRules()));

        $plan->update($this->normalizePlanPayload($validated));

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        $plan->delete();

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan deleted successfully.');
    }

    private function normalizePlanPayload(array $validated): array
    {
        foreach (self::LIMIT_FIELDS as $field) {
            $value = $validated[$field] ?? null;
            $validated[$field] = $value === '' ? null : $value;
        }

        $featureFlags = $validated['feature_flags'] ?? [];
        $validated['feature_flags'] = collect(array_keys(self::FEATURE_FLAGS))
            ->mapWithKeys(fn (string $key) => [$key => (bool) data_get($featureFlags, $key, false)])
            ->all();

        return $validated;
    }

    private function featureFlags(): array
    {
        return collect(self::FEATURE_FLAGS)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'helper' => $meta['helper'],
            ])
            ->values()
            ->all();
    }

    private function featureFlagValidationRules(): array
    {
        $rules = [];

        foreach (array_keys(self::FEATURE_FLAGS) as $key) {
            $rules["feature_flags.{$key}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
