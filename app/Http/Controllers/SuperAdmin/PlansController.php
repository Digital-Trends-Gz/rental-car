<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Core\LandingPageSettings;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Support\PlanTranslations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlansController extends Controller
{
    private const LIMIT_FIELDS = [
        'max_employees',
        'max_branches',
        'max_cars',
        'max_reservations_per_month',
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
            'plans' => PlanTranslations::localizeCollection(Plan::withCount('tenants')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(), app()->getLocale()),
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
            'sort_order' => 'required|integer|min:0',
            'features' => 'nullable|array',
            'custom_pricing' => 'nullable|boolean',
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
            'max_reservations_per_month' => 'nullable|integer|min:1',
            'max_contracts' => 'nullable|integer|min:1',
            'openai_requests_per_day' => 'nullable|integer|min:1',
            'is_active' => 'required|boolean',
            'is_most_value' => 'nullable|boolean',
        ], $this->featureFlagValidationRules()));

        $plan = Plan::create($this->normalizePlanPayload($validated));
        $this->syncMostValuePlan($plan);

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
            'supportedLocales' => $this->supportedLocaleMeta(),
            'planTranslations' => $this->planTranslationPayload($plan),
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
            'sort_order' => 'required|integer|min:0',
            'features' => 'nullable|array',
            'custom_pricing' => 'nullable|boolean',
            'translations' => 'nullable|array',
            'translations.*.name' => 'nullable|string|max:255',
            'translations.*.description' => 'nullable|string',
            'translations.*.sort_order' => 'nullable|integer|min:0',
            'translations.*.features' => 'nullable|array',
            'translations.*.features.*' => 'nullable|string|max:500',
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
            'max_reservations_per_month' => 'nullable|integer|min:1',
            'max_contracts' => 'nullable|integer|min:1',
            'openai_requests_per_day' => 'nullable|integer|min:1',
            'is_active' => 'required|boolean',
            'is_most_value' => 'nullable|boolean',
        ], $this->featureFlagValidationRules()));

        $translations = $validated['translations'] ?? [];
        unset($validated['translations']);

        $plan->update($this->normalizePlanPayload($validated));
        $this->syncMostValuePlan($plan->fresh());
        $this->syncPlanTranslations($plan->fresh(), $translations);

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
        $validated['custom_pricing'] = (bool) ($validated['custom_pricing'] ?? false);
        $validated['is_most_value'] = (bool) ($validated['is_most_value'] ?? false);

        if ($validated['custom_pricing']) {
            $validated['monthly_price'] = 0;
            $validated['yearly_price'] = 0;
            $validated['one_time_price'] = null;
            $validated['monthly_price_id'] = null;
            $validated['yearly_price_id'] = null;
            $validated['one_time_price_id'] = null;
        }

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

    private function syncMostValuePlan(?Plan $plan): void
    {
        if (!$plan?->is_most_value) {
            return;
        }

        Plan::query()
            ->whereKeyNot($plan->getKey())
            ->update(['is_most_value' => false]);
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

    private function planTranslationPayload(Plan $plan): array
    {
        $settings = $this->landingSettings();
        $payload = [];
        $features = array_values(array_map('strval', $plan->features ?? []));

        foreach ($this->supportedLocaleKeys() as $locale) {
            $root = "translations.{$locale}.".PlanTranslations::ROOT_KEY.'.'.$plan->getKey();
            $translatedFeatures = data_get($settings, "{$root}.features", []);
            $translatedFeatures = is_array($translatedFeatures) ? $translatedFeatures : [];

            $payload[$locale] = [
                'name' => (string) data_get($settings, "{$root}.name", $plan->name),
                'description' => (string) data_get($settings, "{$root}.description", $plan->description ?? ''),
                'sort_order' => (int) data_get($settings, "{$root}.sort_order", $plan->sort_order ?? 0),
                'features' => array_map(
                    static fn (string $feature, int $index): string => (string) data_get($translatedFeatures, (string) $index, $feature),
                    $features,
                    array_keys($features)
                ),
            ];
        }

        return $payload;
    }

    private function syncPlanTranslations(Plan $plan, mixed $translations): void
    {
        $translations = is_array($translations) ? $translations : [];
        $settings = $this->landingSettings();

        foreach ($this->supportedLocaleKeys() as $locale) {
            $input = data_get($translations, $locale, []);
            if (!is_array($input)) {
                continue;
            }

            $root = 'translations.'.$locale.'.'.PlanTranslations::ROOT_KEY.'.'.$plan->getKey();
            $row = [];

            $name = trim((string) data_get($input, 'name', ''));
            if ($name !== '') {
                $row['name'] = $name;
            }

            $description = trim((string) data_get($input, 'description', ''));
            if ($description !== '') {
                $row['description'] = $description;
            }

            $sortOrder = data_get($input, 'sort_order');
            if ($sortOrder !== null && $sortOrder !== '' && is_numeric($sortOrder)) {
                $row['sort_order'] = (string) max(0, (int) $sortOrder);
            }

            $features = data_get($input, 'features', []);
            if (is_array($features)) {
                foreach ($features as $index => $feature) {
                    $feature = trim((string) $feature);
                    if ($feature !== '') {
                        $row['features'][(int) $index] = $feature;
                    }
                }
            }

            if (empty($row)) {
                data_forget($settings, $root);
            } else {
                data_set($settings, $root, $row);
            }
        }

        SiteSetting::query()->updateOrCreate(
            ['key' => LandingPageSettings::KEY],
            ['value' => LandingPageSettings::normalize($settings)]
        );
    }

    private function landingSettings(): array
    {
        $stored = SiteSetting::query()
            ->where('key', LandingPageSettings::KEY)
            ->value('value');

        return LandingPageSettings::normalize(is_array($stored) ? $stored : null);
    }

    private function supportedLocaleKeys(): array
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));
        if (empty($supported)) {
            $supported = LandingPageSettings::supportedLocaleKeys();
        }

        $supported = array_values(array_unique(array_map('strval', $supported)));

        return empty($supported) ? ['en'] : $supported;
    }

    private function supportedLocaleMeta(): array
    {
        $meta = (array) config('laravellocalization.supportedLocales', []);

        return array_map(function (string $code) use ($meta): array {
            $locale = (array) ($meta[$code] ?? []);

            return [
                'code' => $code,
                'name' => (string) ($locale['name'] ?? strtoupper($code)),
                'native' => (string) ($locale['native'] ?? $locale['name'] ?? strtoupper($code)),
                'direction' => (string) ($locale['script'] ?? '') === 'Arab' ? 'rtl' : 'ltr',
            ];
        }, $this->supportedLocaleKeys());
    }
}
