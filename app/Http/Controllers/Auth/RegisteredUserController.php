<?php

namespace App\Http\Controllers\Auth;

use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Permission;
use App\Models\PaymentProvider;
use App\Models\Role;
use App\Models\SubscriptionPaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CompanyOwners;
use App\Support\Payments\MyFatoorahSubscriptionProvider;
use App\Support\PlanTranslations;
use App\Support\PlanPricing;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Throwable;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Intl\Countries;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use App\Rules\DigitsOnly;
use App\Rules\LettersOnly;

class RegisteredUserController extends Controller
{
    private const REGISTRATION_SESSION_KEY = 'saas.registration';
    private const PLAN_SELECTION_SESSION_KEY = 'saas.registration.plan';
    private const CHECKOUT_SESSION_KEY = 'saas.registration.checkout_session_id';
    private const SUBSCRIPTION_TXN_SESSION_KEY = 'saas.registration.subscription_transaction_id';
    private const MAX_REGISTRATION_PARTNER_SEATS = 10;

    /**
     * Show the registration page.
     */
    public function create(Request $request): Response
    {
        $registration = $request->session()->get(self::REGISTRATION_SESSION_KEY, []);
        $companyOwners = CompanyOwners::normalize(data_get($registration, 'company_owners', []));

        if ($companyOwners === [] && data_get($registration, 'company_identifiers')) {
            $companyOwners = CompanyOwners::normalize([[
                'name' => $registration['name'] ?? '',
                'commercial_registration_number' => data_get($registration, 'company_identifiers.commercial_registration_number'),
                'tax_number' => data_get($registration, 'company_identifiers.tax_number'),
                'civil_number' => data_get($registration, 'company_identifiers.civil_number'),
            ]]);
        }

        $firstOwner = $companyOwners[0] ?? [];

        return Inertia::render('auth/Register', [
            'prefill' => [
                'name' => $registration['name'] ?? null,
                'email' => $registration['email'] ?? null,
                'custom_domain' => $registration['custom_domain'] ?? null,
                'country_iso2' => $registration['country_iso2'] ?? null,
                'phone_country_code' => $registration['phone_country_code'] ?? null,
                'phone_national' => $registration['phone_national'] ?? null,
                'phone' => $registration['phone'] ?? null,
                'company_owners' => $companyOwners,
                'commercial_registration_number' => $firstOwner['commercial_registration_number'] ?? data_get($registration, 'company_identifiers.commercial_registration_number'),
                'tax_number' => $firstOwner['tax_number'] ?? data_get($registration, 'company_identifiers.tax_number'),
                'civil_number' => $firstOwner['civil_number'] ?? data_get($registration, 'company_identifiers.civil_number'),
                'partner_seats' => $registration['partner_seats'] ?? 0,
            ],
            'countries' => $this->registrationCountries(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => $request->input('company_name', $request->input('name')),
            'email' => $request->input('email_company', $request->input('email')),
            'custom_domain' => $this->normalizeDomain($request->input('custom_domain')),
            'country_iso2' => strtoupper(trim((string) $request->input('country_iso2', ''))),
            'phone_national' => trim((string) $request->input('phone_national', $request->input('phone', ''))),
        ]);

        $tenantId = TenantContext::id();
        $tenantSlug = TenantContext::get()?->slug;
        if ($tenantId) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', new LettersOnly()],
                'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
                'civil_number' => ['required', 'string', 'max:255', new DigitsOnly()],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ], $this->registrationValidationMessages());

            $user = User::create([
                'name' => $validated['name'],
                'civil_number' => trim((string) $validated['civil_number']),
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::CLIENT,
                'tenant_id' => $tenantId,
            ]);

            event(new Registered($user));

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->to($this->postAuthenticationUrl($user, $tenantSlug))
                ->with('success', 'Your account has been created. Please verify your email to continue.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new LettersOnly()],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                'unique:tenants,email',
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
                'unique:tenants,domain',
            ],
            'country_iso2' => [
                'nullable',
                'string',
                'size:2',
                Rule::in($this->registrationCountryIso2List()),
                'required_with:phone_national',
            ],
            'phone_national' => [
                'nullable',
                'string',
                'max:30',
                'required_with:country_iso2',
            ],
            'company_owners' => ['required', 'array', 'min:1'],
            'company_owners.*.name' => ['required', 'string', 'max:255', new LettersOnly()],
            'company_owners.*.commercial_registration_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'company_owners.*.tax_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'company_owners.*.civil_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'commercial_registration_number' => ['nullable', 'string', 'max:255', new DigitsOnly()],
            'tax_number' => ['nullable', 'string', 'max:255', new DigitsOnly()],
            'civil_number' => ['nullable', 'string', 'max:255', new DigitsOnly()],
            'partner_seats' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_REGISTRATION_PARTNER_SEATS],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], $this->registrationValidationMessages());

        $companyOwners = CompanyOwners::normalize($validated['company_owners'] ?? []);
        $primaryOwner = $companyOwners[0] ?? CompanyOwners::blankRow();

        [$phoneE164, $phoneCountryCode, $phoneNational] = $this->normalizeRegistrationPhone(
            $validated['country_iso2'] ?? null,
            $validated['phone_national'] ?? null
        );

        if (!empty($validated['phone_national']) && $phoneE164 === null) {
            return back()
                ->withErrors([
                    'phone_national' => 'Please enter a valid phone number for the selected country.',
                ])
                ->withInput();
        }

        $request->session()->put(self::REGISTRATION_SESSION_KEY, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'custom_domain' => $validated['custom_domain'] ?? null,
            'civil_number' => $primaryOwner['civil_number'],
            'country_iso2' => strtoupper(trim((string) ($validated['country_iso2'] ?? ''))) ?: null,
            'phone_country_code' => $phoneCountryCode,
            'phone_national' => $phoneNational,
            'phone' => $phoneE164,
            'partner_seats' => (int) ($validated['partner_seats'] ?? 0),
            'company_owners' => $companyOwners,
            'company_identifiers' => [
                'commercial_registration_number' => $primaryOwner['commercial_registration_number'],
                'tax_number' => $primaryOwner['tax_number'],
                'civil_number' => $primaryOwner['civil_number'],
            ],
            'password_hash' => Hash::make($validated['password']),
        ]);

        $request->session()->forget(self::PLAN_SELECTION_SESSION_KEY);

        return to_route($this->authRouteName('register.plans'));
    }

    private function registrationValidationMessages(): array
    {
        return [
            'password.confirmed' => $this->translationLine(
                'validation.confirmed',
                'The password field confirmation does not match.',
                ['attribute' => 'password'],
            ),
            'password.mixed' => $this->translationLine(
                'validation.password.mixed',
                'The password field must contain at least one uppercase and one lowercase letter.',
                ['attribute' => 'password'],
            ),
        ];
    }

    private function translationLine(string $key, string $fallback, array $replace = []): string
    {
        $line = trans($key, $replace);

        return $line === $key ? $fallback : $line;
    }

    private function registrationCompanyOwners(array $registration): array
    {
        $companyOwners = CompanyOwners::normalize($registration['company_owners'] ?? []);

        if ($companyOwners !== [] || !data_get($registration, 'company_identifiers')) {
            return $companyOwners;
        }

        return CompanyOwners::normalize([[
            'name' => $registration['name'] ?? '',
            'commercial_registration_number' => data_get($registration, 'company_identifiers.commercial_registration_number'),
            'tax_number' => data_get($registration, 'company_identifiers.tax_number'),
            'civil_number' => data_get($registration, 'company_identifiers.civil_number'),
        ]]);
    }

    public function plans(Request $request): Response|RedirectResponse
    {
        if ($this->isExistingTenantPlanFlowOnSubdomain($request)) {
            return to_route('register.plans');
        }

        if (TenantContext::id() && !$this->isExistingTenantPlanFlow($request)) {
            return $this->redirectToTenantRegister();
        }

        if (!$request->session()->has(self::REGISTRATION_SESSION_KEY)) {
            return to_route($this->authRouteName('register'))->with('error', 'Please complete registration details first.');
        }

        $plans = PlanPricing::decorateCollection(
            PlanTranslations::localizeCollection(Plan::query()
                ->with('discounts')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'id',
                    'name',
                    'description',
                    'sort_order',
                    'features',
                    'feature_flags',
                    'custom_pricing',
                    'monthly_price',
                    'monthly_price_id',
                    'yearly_price',
                    'yearly_price_id',
                    'one_time_price',
                    'one_time_price_id',
                ]), app()->getLocale())
        );

        return Inertia::render('auth/RegisterPlans', [
            'plans' => $plans,
            'selection' => $request->session()->get(self::PLAN_SELECTION_SESSION_KEY, [
                'plan_id' => null,
                'billing_cycle' => 'monthly',
            ]),
            'urls' => [
                'register' => route($this->authRouteName('register')),
                'plansStore' => route($this->authRouteName('register.plans.store')),
                'checkout' => route($this->authRouteName('register.checkout')),
            ],
        ]);
    }

    public function upgrade(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && ($user->role ?? null) === UserRole::ADMIN && $user->tenant_id, 403);

        $tenant = Tenant::query()->findOrFail((int) $user->tenant_id);

        $request->session()->put(self::REGISTRATION_SESSION_KEY, [
            'mode' => 'existing_tenant',
            'existing_user_id' => $user->id,
            'existing_tenant_id' => $tenant->id,
            'name' => $tenant->name ?: $user->name,
            'email' => $user->email,
            'custom_domain' => $tenant->domain,
            'phone' => $tenant->phone,
        ]);

        $request->session()->forget([
            self::PLAN_SELECTION_SESSION_KEY,
            self::CHECKOUT_SESSION_KEY,
            self::SUBSCRIPTION_TXN_SESSION_KEY,
        ]);

        return to_route($this->authRouteName('register.plans'));
    }

    public function storePlan(Request $request): RedirectResponse
    {
        if (TenantContext::id() && !$this->isExistingTenantPlanFlow($request)) {
            return $this->redirectToTenantRegister();
        }

        if (!$request->session()->has(self::REGISTRATION_SESSION_KEY)) {
            return to_route($this->authRouteName('register'))->with('error', 'Please complete registration details first.');
        }

        $validated = $request->validate([
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where(static fn ($query) => $query->where('is_active', true)),
            ],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly', 'one_time'])],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);

        if ($plan->custom_pricing) {
            return back()->withErrors([
                'plan_id' => 'This plan uses custom pricing. Please contact us to continue.',
            ]);
        }

        if ($validated['billing_cycle'] === 'one_time' && $plan->one_time_price === null) {
            return back()->withErrors([
                'billing_cycle' => 'The selected plan does not support one-time billing.',
            ]);
        }

        if (!$this->resolvePlanPriceId($plan, $validated['billing_cycle'])
            && $this->resolvePlanAmount($plan, $validated['billing_cycle']) <= 0) {
            return back()->withErrors([
                'billing_cycle' => 'The selected plan is not available for this billing cycle.',
            ]);
        }

        $request->session()->put(self::PLAN_SELECTION_SESSION_KEY, $validated);

        return to_route($this->authRouteName('register.checkout'));
    }

    public function checkout(Request $request, MyFatoorahSubscriptionProvider $myFatoorah): Response|RedirectResponse
    {
        if ($this->isExistingTenantPlanFlowOnSubdomain($request)) {
            return to_route('register.checkout');
        }

        if (TenantContext::id() && !$this->isExistingTenantPlanFlow($request)) {
            return $this->redirectToTenantRegister();
        }

        $registration = $request->session()->get(self::REGISTRATION_SESSION_KEY);
        if (!$registration) {
            return to_route($this->authRouteName('register'))->with('error', 'Please complete registration details first.');
        }

        $selection = $request->session()->get(self::PLAN_SELECTION_SESSION_KEY);
        if (!$selection || empty($selection['plan_id']) || empty($selection['billing_cycle'])) {
            return to_route($this->authRouteName('register.plans'))->with('error', 'Please choose a plan first.');
        }

        $plan = Plan::query()
            ->where('is_active', true)
            ->find($selection['plan_id']);

        if (!$plan) {
            $request->session()->forget(self::PLAN_SELECTION_SESSION_KEY);
            return to_route($this->authRouteName('register.plans'))->with('error', 'The selected plan is no longer available.');
        }

        $localizedPlan = PlanTranslations::localizePlan($plan, app()->getLocale());

        $paymentProviders = $this->availablePlatformSubscriptionProviders();
        $providerPaymentMethods = [];
        $defaultMyFatoorahPaymentMethodId = null;

        $myFatoorahProvider = PaymentProvider::query()
            ->where('code', 'myfatoorah')
            ->where('is_enabled', true)
            ->where('supports_platform_subscriptions', true)
            ->first();

        if ($myFatoorahProvider) {
            $config = is_array($myFatoorahProvider->config) ? $myFatoorahProvider->config : [];
            $defaultMyFatoorahPaymentMethodId = (int) ($config['payment_method_id'] ?? 0) ?: null;

            try {
                $providerPaymentMethods['myfatoorah'] = $myFatoorah->listPaymentMethods(
                    $myFatoorahProvider,
                    $this->resolvePlanAmount($plan, (string) $selection['billing_cycle']),
                    (string) config('app.currency_code', 'USD')
                );
            } catch (Throwable $e) {
                Log::warning('Unable to load MyFatoorah payment methods for checkout page', [
                    'message' => $e->getMessage(),
                ]);
                $providerPaymentMethods['myfatoorah'] = [];
            }
        }

        return Inertia::render('auth/RegisterCheckout', [
            'registration' => [
                'name' => $registration['name'],
                'email' => $registration['email'],
                'civil_number' => $registration['civil_number'] ?? data_get($registration, 'company_identifiers.civil_number'),
                'phone' => $registration['phone'] ?? null,
                'custom_domain' => $registration['custom_domain'] ?? null,
            ],
            'plan' => [
                'id' => $plan->id,
                'name' => $localizedPlan->name,
                'description' => $localizedPlan->description,
                'features' => $localizedPlan->features ?? [],
                'pricing_meta' => data_get(PlanPricing::decoratePlan($localizedPlan), 'pricing_meta', []),
            ],
            'billingCycle' => $selection['billing_cycle'],
            'amount' => $this->resolvePlanAmount($plan, $selection['billing_cycle']),
            'originalAmount' => $this->resolveBasePlanAmount($plan, $selection['billing_cycle']),
            'currencyCode' => config('app.currency_code', 'USD'),
            'urls' => [
                'register' => route($this->authRouteName('register')),
                'plans' => route($this->authRouteName('register.plans')),
                'checkoutStore' => route($this->authRouteName('register.checkout.store')),
            ],
            'paymentProviders' => $paymentProviders,
            'selectedPaymentProvider' => $this->defaultPlatformSubscriptionProviderCode(),
            'providerPaymentMethods' => $providerPaymentMethods,
            'selectedProviderPaymentMethodId' => $defaultMyFatoorahPaymentMethodId,
        ]);
    }

    public function completeCheckout(Request $request, MyFatoorahSubscriptionProvider $myFatoorah): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        if (TenantContext::id() && !$this->isExistingTenantPlanFlow($request)) {
            return $this->redirectToTenantRegister();
        }

        $registration = $request->session()->get(self::REGISTRATION_SESSION_KEY);
        $selection = $request->session()->get(self::PLAN_SELECTION_SESSION_KEY);
        if (!$registration || !$selection || empty($selection['plan_id']) || empty($selection['billing_cycle'])) {
            return to_route($this->authRouteName('register'))->with('error', 'Please restart registration.');
        }

        if (($registration['mode'] ?? null) !== 'existing_tenant') {
            $companyOwners = $this->registrationCompanyOwners($registration);
            $primaryOwner = $companyOwners[0] ?? CompanyOwners::blankRow();
            $registration['company_owners'] = $companyOwners;
            $registration['company_identifiers'] = [
                'commercial_registration_number' => $primaryOwner['commercial_registration_number'],
                'tax_number' => $primaryOwner['tax_number'],
                'civil_number' => $primaryOwner['civil_number'],
            ];
            $registration['civil_number'] = $primaryOwner['civil_number'];
            $request->session()->put(self::REGISTRATION_SESSION_KEY, $registration);
        }

        $request->validate([
            'accept_terms' => ['accepted'],
            'payment_provider_code' => ['nullable', 'string', 'max:50'],
            'payment_method_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $isExistingTenantFlow = ($registration['mode'] ?? null) === 'existing_tenant';

        $registrationValidator = Validator::make($registration, $isExistingTenantFlow ? [
            'existing_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'existing_tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ] : [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                'unique:tenants,email',
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
                'unique:tenants,domain',
            ],
            'civil_number' => ['required', 'string', 'max:255'],
            'company_owners' => ['required', 'array', 'min:1'],
            'company_owners.*.name' => ['required', 'string', 'max:255'],
            'company_owners.*.commercial_registration_number' => ['required', 'string', 'max:255'],
            'company_owners.*.tax_number' => ['required', 'string', 'max:255'],
            'company_owners.*.civil_number' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'partner_seats' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_REGISTRATION_PARTNER_SEATS],
            'password_hash' => ['required', 'string'],
        ]);

        if ($registrationValidator->fails()) {
            return back()->withErrors([
                'accept_terms' => $registrationValidator->errors()->first(),
            ]);
        }

        $plan = Plan::query()
            ->where('is_active', true)
            ->find($selection['plan_id']);

        if (!$plan) {
            $request->session()->forget(self::PLAN_SELECTION_SESSION_KEY);
            return to_route($this->authRouteName('register.plans'))->with('error', 'The selected plan is no longer available.');
        }

        $requestedProviderCode = strtolower(trim((string) $request->input(
            'payment_provider_code',
            $this->defaultPlatformSubscriptionProviderCode() ?? 'stripe'
        )));
        if ($requestedProviderCode === '') {
            $requestedProviderCode = strtolower((string) ($this->defaultPlatformSubscriptionProviderCode() ?? 'stripe'));
        }

        $paymentProvider = PaymentProvider::query()
            ->where('code', $requestedProviderCode)
            ->where('is_enabled', true)
            ->where('supports_platform_subscriptions', true)
            ->first();

        if (!$paymentProvider && $requestedProviderCode !== 'stripe') {
            return back()->withErrors([
                'payment_provider_code' => 'The selected payment provider is not available.',
            ]);
        }

        $priceId = $this->resolvePlanPriceId($plan, $selection['billing_cycle']);
        if ($requestedProviderCode === 'stripe' && !$priceId && $this->resolvePlanAmount($plan, (string) $selection['billing_cycle']) <= 0) {
            return back()->withErrors([
                'accept_terms' => 'Invalid Stripe pricing configuration for the selected plan.',
            ]);
        }

        $transaction = SubscriptionPaymentTransaction::create([
            'provider_code' => $paymentProvider?->code ?? 'stripe',
            'payment_provider_id' => $paymentProvider?->id,
            'plan_id' => $plan->id,
            'billing_cycle' => (string) $selection['billing_cycle'],
            'amount' => $this->resolvePlanAmount($plan, (string) $selection['billing_cycle']),
            'currency' => strtoupper((string) config('app.currency_code', 'USD')),
            'status' => 'pending',
            'payer_name' => (string) ($registration['name'] ?? ''),
            'payer_email' => (string) ($registration['email'] ?? ''),
            'payer_phone' => (string) ($registration['phone_national'] ?? $registration['phone'] ?? ''),
            'metadata' => [
                'registration_mode' => $registration['mode'] ?? 'new',
                'auth_route_prefix' => TenantContext::id() ? 'tenant' : 'central',
                'registration_snapshot' => $registration,
                'plan_selection_snapshot' => $selection,
            ],
        ]);

        $request->session()->put(self::SUBSCRIPTION_TXN_SESSION_KEY, $transaction->id);

        if (($paymentProvider?->code ?? $requestedProviderCode) === 'myfatoorah') {
            try {
                $callbackUrl = route($this->authRouteName('register.checkout.provider.return'), [
                    'provider' => 'myfatoorah',
                ]);
                $errorUrl = route($this->authRouteName('register.checkout.provider.cancel'), [
                    'provider' => 'myfatoorah',
                ]);

                $checkout = $myFatoorah->createCheckout([
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'payment_method_id' => $request->filled('payment_method_id')
                        ? (int) $request->input('payment_method_id')
                        : null,
                    'callback_url' => $callbackUrl,
                    'error_url' => $errorUrl,
                    'customer_name' => $transaction->payer_name,
                    'customer_email' => $transaction->payer_email,
                    'customer_mobile' => $transaction->payer_phone,
                    'customer_reference' => (string) $transaction->id,
                    'user_defined_field' => 'plan:'.$plan->id.'|cycle:'.$selection['billing_cycle'],
                    'items' => [[
                        'ItemName' => sprintf('SaaS Plan %s (%s)', $plan->name, $selection['billing_cycle']),
                        'Quantity' => 1,
                        'UnitPrice' => (float) $transaction->amount,
                    ]],
                ], $paymentProvider);

                $transaction->update([
                    'provider_checkout_id' => (string) ($checkout['invoice_id'] ?? ''),
                    'provider_response' => $checkout['raw'] ?? [],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_url_created' => true,
                        'payment_method_id' => $request->filled('payment_method_id')
                            ? (int) $request->input('payment_method_id')
                            : null,
                    ]),
                ]);

                return Inertia::location((string) $checkout['payment_url']);
            } catch (Throwable $e) {
                report($e);

                $transaction->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => $e->getMessage(),
                ]);

                return back()->withErrors([
                    'payment_provider_code' => config('app.debug')
                        ? 'MyFatoorah error: '.$e->getMessage()
                        : 'Could not create MyFatoorah checkout session. Please try again.',
                ]);
            }
        }

        $stripeSecret = $this->resolveStripeSecretFromProviderConfig($paymentProvider) ?: (string) config('cashier.secret');
        if ($stripeSecret === '') {
            return back()->withErrors([
                'accept_terms' => 'Stripe is not configured. Please contact support.',
            ]);
        }

        $mode = $selection['billing_cycle'] === 'one_time' ? 'payment' : 'subscription';
        $discountedAmount = $this->resolvePlanAmount($plan, (string) $selection['billing_cycle']);
        $baseAmount = $this->resolveBasePlanAmount($plan, (string) $selection['billing_cycle']);

        try {
            $stripe = new \Stripe\StripeClient($stripeSecret);
            $checkoutSession = $stripe->checkout->sessions->create([
                'mode' => $mode,
                'line_items' => [
                    $this->buildStripeLineItem(
                        $plan,
                        (string) $selection['billing_cycle'],
                        $priceId,
                        $discountedAmount,
                        $baseAmount
                    ),
                ],
                'customer_email' => $registration['email'],
                'success_url' => route($this->authRouteName('register.checkout.success')).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route($this->authRouteName('register.checkout.cancel')),
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                    'billing_cycle' => (string) $selection['billing_cycle'],
                    'registration_email' => (string) $registration['email'],
                ],
            ]);
            $transaction->update([
                'provider_checkout_id' => (string) $checkoutSession->id,
                'provider_response' => is_array($checkoutSession->toArray(false) ?? null)
                    ? $checkoutSession->toArray(false)
                    : ['id' => (string) $checkoutSession->id],
            ]);
        } catch (Throwable $e) {
            report($e);
            Log::error('Stripe checkout session creation failed', [
                'message' => $e->getMessage(),
                'plan_id' => $plan->id,
                'billing_cycle' => $selection['billing_cycle'],
                'price_id' => $priceId,
            ]);

            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);

            $message = config('app.debug')
                ? 'Stripe error: '.$e->getMessage()
                : 'Could not create Stripe checkout session. Please try again.';

            return back()->withErrors([
                'accept_terms' => $message,
            ]);
        }

        $request->session()->put(self::CHECKOUT_SESSION_KEY, $checkoutSession->id);

        return Inertia::location((string) $checkoutSession->url);
    }

    public function checkoutSuccess(Request $request): RedirectResponse
    {
        if (TenantContext::id() && !$this->isExistingTenantPlanFlow($request)) {
            return $this->redirectToTenantRegister();
        }

        $checkoutSessionId = $this->requestQueryValue($request, 'session_id');
        if ($checkoutSessionId === null || mb_strlen($checkoutSessionId) > 255) {
            return to_route($this->authRouteName('register.checkout'))->with('error', 'Invalid checkout session.');
        }

        $expectedCheckoutSessionId = $request->session()->get(self::CHECKOUT_SESSION_KEY);
        if (!$expectedCheckoutSessionId || $checkoutSessionId !== $expectedCheckoutSessionId) {
            return to_route($this->authRouteName('register.checkout'))->with('error', 'Invalid checkout session.');
        }

        $stripeProvider = null;
        $subscriptionTxnId = (int) $request->session()->get(self::SUBSCRIPTION_TXN_SESSION_KEY, 0);
        if ($subscriptionTxnId > 0) {
            $subscriptionTxn = SubscriptionPaymentTransaction::query()->find($subscriptionTxnId);
            if (($subscriptionTxn?->provider_code ?? '') === 'stripe' && $subscriptionTxn?->payment_provider_id) {
                $stripeProvider = PaymentProvider::query()->find($subscriptionTxn->payment_provider_id);
            }
        }

        if (!$stripeProvider) {
            $stripeProvider = PaymentProvider::query()
                ->where('code', 'stripe')
                ->where('is_enabled', true)
                ->where('supports_platform_subscriptions', true)
                ->first();
        }

        $stripeSecret = $this->resolveStripeSecretFromProviderConfig($stripeProvider) ?: (string) config('cashier.secret');
        if ($stripeSecret === '') {
            return to_route($this->authRouteName('register.checkout'))->with('error', 'Stripe is not configured. Please contact support.');
        }

        try {
            $stripe = new \Stripe\StripeClient($stripeSecret);
            $checkoutSession = $stripe->checkout->sessions->retrieve(
                $checkoutSessionId,
                [
                    'expand' => [
                        'payment_intent.payment_method',
                        'subscription.default_payment_method',
                        'subscription.latest_invoice.payment_intent.payment_method',
                    ],
                ]
            );
        } catch (Throwable) {
            return to_route($this->authRouteName('register.checkout'))->with('error', 'Could not verify Stripe payment session.');
        }

        if (($checkoutSession->status ?? null) !== 'complete') {
            return to_route($this->authRouteName('register.checkout'))->with('error', 'Payment is not completed yet.');
        }

        $paymentStatus = (string) ($checkoutSession->payment_status ?? '');
        if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            return to_route($this->authRouteName('register.checkout'))->with('error', 'Payment is not confirmed yet.');
        }

        $lineItems = [];
        try {
            $lineItemsResponse = $stripe->checkout->sessions->allLineItems(
                $checkoutSessionId,
                ['limit' => 100]
            );
            $lineItems = is_array($lineItemsResponse->data ?? null) ? $lineItemsResponse->data : [];
        } catch (Throwable $e) {
            Log::warning('Could not fetch Stripe checkout line items', [
                'message' => $e->getMessage(),
                'session_id' => $checkoutSessionId,
            ]);
        }

        $registration = $request->session()->get(self::REGISTRATION_SESSION_KEY);
        $selection = $request->session()->get(self::PLAN_SELECTION_SESSION_KEY);
        if (!$registration || !$selection || empty($selection['plan_id']) || empty($selection['billing_cycle'])) {
            return to_route($this->authRouteName('register'))->with('error', 'Registration session expired. Please register again.');
        }

        if (($registration['mode'] ?? null) !== 'existing_tenant') {
            $companyOwners = $this->registrationCompanyOwners($registration);
            $primaryOwner = $companyOwners[0] ?? CompanyOwners::blankRow();
            $registration['company_owners'] = $companyOwners;
            $registration['company_identifiers'] = [
                'commercial_registration_number' => $primaryOwner['commercial_registration_number'],
                'tax_number' => $primaryOwner['tax_number'],
                'civil_number' => $primaryOwner['civil_number'],
            ];
            $registration['civil_number'] = $primaryOwner['civil_number'];
            $request->session()->put(self::REGISTRATION_SESSION_KEY, $registration);
        }

        $isExistingTenantFlow = ($registration['mode'] ?? null) === 'existing_tenant';

        Validator::make($registration, $isExistingTenantFlow ? [
            'existing_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'existing_tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ] : [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                'unique:tenants,email',
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
                'unique:tenants,domain',
            ],
            'civil_number' => ['required', 'string', 'max:255'],
            'company_owners' => ['required', 'array', 'min:1'],
            'company_owners.*.name' => ['required', 'string', 'max:255'],
            'company_owners.*.commercial_registration_number' => ['required', 'string', 'max:255'],
            'company_owners.*.tax_number' => ['required', 'string', 'max:255'],
            'company_owners.*.civil_number' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password_hash' => ['required', 'string'],
        ])->validate();

        $plan = Plan::query()
            ->where('is_active', true)
            ->find($selection['plan_id']);

        if (!$plan) {
            return to_route($this->authRouteName('register.plans'))->with('error', 'The selected plan is no longer available.');
        }

        if ($isExistingTenantFlow) {
            $tenant = Tenant::query()->find((int) $registration['existing_tenant_id']);
            $user = User::withoutGlobalScope('tenant')->find((int) $registration['existing_user_id']);

            if (!$tenant || !$user || (int) $user->tenant_id !== (int) $tenant->id) {
                return to_route($this->authRouteName('tenant-login'))->with('error', 'Could not verify tenant account for this payment.');
            }

            $accessEndsAt = $this->resolveAccessEndsAt(
                (string) $selection['billing_cycle'],
                $tenant->trial_ends_at
            );

            DB::transaction(function () use ($tenant, $plan, $user, $accessEndsAt) {
                $tenant->update([
                    'plan_id' => $plan->id,
                    'trial_ends_at' => $accessEndsAt,
                    'is_active' => true,
                ]);

                $user->update([
                    'trial_ends_at' => $accessEndsAt,
                ]);
            });

            $this->ensureTenantAdminFullAccess($user, $tenant);

            $this->persistCheckoutSubscription(
                $user,
                $checkoutSession,
                (string) $selection['billing_cycle'],
                $this->resolvePlanPriceId($plan, (string) $selection['billing_cycle']),
                $accessEndsAt,
                $lineItems,
                $this->resolveCheckoutAmountPaid($checkoutSession, $lineItems),
                $this->resolveCheckoutCurrency($checkoutSession, $lineItems)
            );
        } else {
            $accessEndsAt = $this->resolveAccessEndsAt((string) $selection['billing_cycle']);
            $companyOwners = CompanyOwners::normalize($registration['company_owners'] ?? []);
            $primaryOwner = $companyOwners[0] ?? CompanyOwners::blankRow();

            [$tenant, $user] = DB::transaction(function () use ($registration, $plan, $accessEndsAt, $companyOwners, $primaryOwner) {
                $tenant = Tenant::create([
                    'name' => $registration['name'],
                    'slug' => $this->generateUniqueSlug($registration['name']),
                    'domain' => $registration['custom_domain'] ?? null,
                    'email' => $registration['email'],
                    'phone' => $registration['phone'] ?? null,
                    'country_iso2' => $registration['country_iso2'] ?? null,
                    'phone_country_code' => $registration['phone_country_code'] ?? null,
                    'phone_national' => $registration['phone_national'] ?? null,
                    'phone_e164' => $registration['phone'] ?? null,
                    'plan_id' => $plan->id,
                    'partner_seats' => (int) ($registration['partner_seats'] ?? 0),
                    'settings' => [
                        'company_owners' => $companyOwners,
                        'company_identifiers' => $registration['company_identifiers'] ?? [],
                    ],
                    'trial_ends_at' => $accessEndsAt,
                    'is_active' => true,
                ]);

                $user = User::withoutGlobalScope('tenant')->create([
                    'name' => $registration['name'],
                    'civil_number' => $primaryOwner['civil_number'] ?: trim((string) ($registration['civil_number'] ?? data_get($registration, 'company_identifiers.civil_number', ''))),
                    'email' => $registration['email'],
                    'password' => $registration['password_hash'],
                    'role' => UserRole::ADMIN,
                    'tenant_id' => $tenant->id,
                    'is_active' => true,
                    'trial_ends_at' => $accessEndsAt,
                ]);

                return [$tenant, $user];
            });

            $this->ensureTenantAdminFullAccess($user, $tenant);

            $this->persistCheckoutSubscription(
                $user,
                $checkoutSession,
                (string) $selection['billing_cycle'],
                $this->resolvePlanPriceId($plan, (string) $selection['billing_cycle']),
                $accessEndsAt,
                $lineItems,
                $this->resolveCheckoutAmountPaid($checkoutSession, $lineItems),
                $this->resolveCheckoutCurrency($checkoutSession, $lineItems)
            );

            event(new Registered($user));
        }

        $request->session()->forget([
            self::REGISTRATION_SESSION_KEY,
            self::PLAN_SELECTION_SESSION_KEY,
            self::CHECKOUT_SESSION_KEY,
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->to($this->postAuthenticationUrl($user, $tenant->slug))
            ->with('success', 'Payment completed successfully. Please verify your email to activate your account.');
    }

    public function checkoutCancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::CHECKOUT_SESSION_KEY);

        return to_route($this->authRouteName('register.checkout'))->with('error', 'Payment cancelled. Please try again.');
    }

    public function checkoutProviderReturn(Request $request, string $provider): RedirectResponse
    {
        $provider = strtolower($provider);
        if ($provider !== 'myfatoorah') {
            return to_route($this->authRouteName('register.checkout'))->with('error', 'Unsupported payment provider callback.');
        }

        $transactionId = (int) $request->session()->get(self::SUBSCRIPTION_TXN_SESSION_KEY, 0);
        $transaction = $transactionId > 0
            ? SubscriptionPaymentTransaction::query()->find($transactionId)
            : null;

        if (!$transaction) {
            return to_route($this->authRouteName('register.checkout'))
                ->with('error', 'Payment transaction session expired. Please retry checkout.');
        }

        $paymentProvider = $transaction->payment_provider_id
            ? PaymentProvider::query()->find($transaction->payment_provider_id)
            : PaymentProvider::query()
                ->where('code', $provider)
                ->where('is_enabled', true)
                ->where('supports_platform_subscriptions', true)
                ->first();

        if (!$paymentProvider) {
            return to_route($this->authRouteName('register.checkout'))
                ->with('error', 'Payment provider configuration is missing or disabled.');
        }

        try {
            $verification = app(MyFatoorahSubscriptionProvider::class)
                ->verifyPaymentStatus($request->query(), $paymentProvider);
        } catch (Throwable $e) {
            report($e);

            $transaction->update([
                'return_status' => 'verify_error',
                'failed_at' => now(),
                'failure_reason' => $e->getMessage(),
                'provider_reference' => (string) ($request->query('paymentId') ?? $request->query('Id') ?? ''),
                'provider_response' => [
                    'callback_query' => $request->query(),
                    'verify_error' => $e->getMessage(),
                ],
            ]);

            return to_route($this->authRouteName('register.checkout'))->with('error', config('app.debug')
                ? 'MyFatoorah verification error: '.$e->getMessage()
                : 'Could not verify MyFatoorah payment. Please contact support if you were charged.');
        }

        $mergedProviderResponse = array_filter([
            'callback_query' => $request->query(),
            'execute_payment' => is_array($transaction->provider_response) ? $transaction->provider_response : null,
            'verify_payment_status' => $verification['raw'] ?? null,
        ], static fn ($v) => $v !== null);

        $transaction->update([
            'provider_checkout_id' => $verification['invoice_id'] ?: $transaction->provider_checkout_id,
            'provider_transaction_id' => $verification['transaction_id'] ?: $verification['payment_id'] ?: $transaction->provider_transaction_id,
            'provider_reference' => $verification['payment_id'] ?: $transaction->provider_reference,
            'return_status' => (string) ($verification['invoice_status'] ?? 'callback_received'),
            'provider_response' => $mergedProviderResponse,
            'paid_at' => ($verification['is_paid'] ?? false) ? ($verification['paid_at'] ?? now()) : $transaction->paid_at,
            'failed_at' => ($verification['is_failed'] ?? false) ? now() : $transaction->failed_at,
            'failure_reason' => ($verification['is_failed'] ?? false)
                ? ((string) ($verification['failure_reason'] ?? 'Payment was not completed.'))
                : null,
            'status' => ($verification['is_paid'] ?? false)
                ? 'paid'
                : (($verification['is_failed'] ?? false) ? 'failed' : $transaction->status),
        ]);

        $transaction->refresh();

        if (!($verification['is_paid'] ?? false)) {
            return to_route($this->authRouteName('register.checkout'))
                ->with('error', (string) ($verification['failure_reason'] ?? 'Payment is not completed yet.'));
        }

        return $this->finalizeVerifiedProviderCheckout($request, $transaction, [
            'provider_code' => $provider,
            'provider_transaction_id' => (string) ($verification['transaction_id'] ?: $verification['payment_id'] ?: ''),
            'provider_checkout_id' => (string) ($verification['invoice_id'] ?? ''),
            'payment_method' => (string) ($verification['payment_method'] ?? 'myfatoorah'),
            'amount_paid' => isset($verification['amount_paid']) ? (float) $verification['amount_paid'] : null,
            'currency' => $verification['currency'] ?? null,
            'paid_at' => $verification['paid_at'] ?? null,
            'raw' => is_array($verification['raw'] ?? null) ? $verification['raw'] : [],
        ]);
    }

    public function checkoutProviderCancel(Request $request, string $provider): RedirectResponse
    {
        $transactionId = (int) $request->session()->get(self::SUBSCRIPTION_TXN_SESSION_KEY, 0);
        $transaction = $transactionId > 0
            ? SubscriptionPaymentTransaction::query()->find($transactionId)
            : null;

        if ($transaction && $transaction->status === 'pending') {
            $transaction->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'return_status' => 'cancelled',
                'provider_response' => [
                    'provider' => $provider,
                    'query' => $request->query(),
                ],
            ]);
        }

        $request->session()->forget(self::SUBSCRIPTION_TXN_SESSION_KEY);

        return to_route($this->authRouteName('register.checkout'))->with('error', ucfirst($provider).' payment was cancelled.');
    }

    public function subscriptionProviderWebhook(
        Request $request,
        string $provider,
        MyFatoorahSubscriptionProvider $myFatoorah
    ): \Illuminate\Http\JsonResponse {
        $provider = strtolower($provider);

        if ($provider !== 'myfatoorah') {
            return response()->json(['ok' => false, 'message' => 'Unsupported provider.'], 404);
        }

        $paymentProvider = PaymentProvider::query()
            ->where('code', 'myfatoorah')
            ->where('is_enabled', true)
            ->where('supports_platform_subscriptions', true)
            ->first();

        if (!$paymentProvider) {
            return response()->json(['ok' => false, 'message' => 'Provider not configured.'], 422);
        }

        $config = is_array($paymentProvider->config) ? $paymentProvider->config : [];
        $configuredSecret = trim((string) ($config['webhook_secret'] ?? ''));
        if ($configuredSecret !== '') {
            $providedSecret = trim((string) (
                $request->header('X-Webhook-Secret')
                ?? $request->header('Webhook-Secret')
                ?? $request->header('X-MyFatoorah-Webhook-Secret')
                ?? ''
            ));

            if (!hash_equals($configuredSecret, $providedSecret)) {
                return response()->json(['ok' => false, 'message' => 'Invalid webhook secret.'], 401);
            }
        }

        $payload = $request->all();

        try {
            $verification = $myFatoorah->verifyPaymentStatus($payload, $paymentProvider);
        } catch (Throwable $e) {
            report($e);
            Log::warning('MyFatoorah subscription webhook verification failed', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json([
                'ok' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Verification failed.',
            ], 422);
        }

        $transaction = $this->findSubscriptionTransactionForProviderWebhook('myfatoorah', $payload, $verification);
        if (!$transaction) {
            Log::warning('MyFatoorah subscription webhook transaction not found', [
                'payload' => $payload,
                'verification' => $verification,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Transaction not found.',
            ], 404);
        }

        if ($transaction->status === 'paid') {
            return response()->json([
                'ok' => true,
                'message' => 'Already processed.',
                'transaction_id' => $transaction->id,
            ]);
        }

        $transaction->update([
            'provider_checkout_id' => (string) ($verification['invoice_id'] ?: $transaction->provider_checkout_id),
            'provider_transaction_id' => (string) ($verification['transaction_id'] ?: $verification['payment_id'] ?: $transaction->provider_transaction_id),
            'provider_reference' => (string) ($verification['payment_id'] ?: $transaction->provider_reference),
            'return_status' => (string) ($verification['invoice_status'] ?? $transaction->return_status ?? 'webhook_received'),
            'provider_response' => [
                'webhook_payload' => $payload,
                'verify_payment_status' => $verification['raw'] ?? [],
            ],
            'status' => ($verification['is_paid'] ?? false)
                ? 'paid'
                : (($verification['is_failed'] ?? false) ? 'failed' : $transaction->status),
            'paid_at' => ($verification['is_paid'] ?? false)
                ? (($verification['paid_at'] ?? null) ?: now())
                : $transaction->paid_at,
            'failed_at' => ($verification['is_failed'] ?? false) ? now() : $transaction->failed_at,
            'failure_reason' => ($verification['is_failed'] ?? false)
                ? ((string) ($verification['failure_reason'] ?? 'Payment failed.'))
                : null,
        ]);

        // Webhook currently confirms the transaction state.
        // Final activation/login still completes on return callback (session-based flow).
        return response()->json([
            'ok' => true,
            'transaction_id' => $transaction->id,
            'status' => $transaction->fresh()->status,
        ]);
    }

    public function postPaymentLogin(Request $request, int $user): RedirectResponse
    {
        $authUser = User::withoutGlobalScope('tenant')->find($user);
        $tenant = $authUser?->tenant_id
            ? Tenant::query()->find((int) $authUser->tenant_id)
            : null;

        if (!$tenant || !$authUser) {
            abort(403, 'Invalid tenant context for post-payment login.');
        }

        Auth::login($authUser, true);
        $request->session()->regenerate();

        $destination = $authUser->role === UserRole::ADMIN ? 'admin.cars.index' : 'client.home';

        return to_route($destination, ['subdomain' => $tenant->slug]);
    }

    private function resolvePlanAmount(Plan $plan, string $billingCycle): float
    {
        return PlanPricing::resolveAmount($plan, $billingCycle);
    }

    private function resolveBasePlanAmount(Plan $plan, string $billingCycle): float
    {
        return match ($billingCycle) {
            'yearly' => (float) $plan->yearly_price,
            'one_time' => (float) ($plan->one_time_price ?? 0),
            default => (float) $plan->monthly_price,
        };
    }

    private function findSubscriptionTransactionForProviderWebhook(
        string $providerCode,
        array $payload,
        array $verification
    ): ?SubscriptionPaymentTransaction {
        $query = SubscriptionPaymentTransaction::query()
            ->where('provider_code', $providerCode);

        $candidateIds = array_values(array_filter(array_unique([
            (string) ($verification['payment_id'] ?? ''),
            (string) ($verification['transaction_id'] ?? ''),
            (string) ($verification['invoice_id'] ?? ''),
            (string) data_get($payload, 'paymentId', ''),
            (string) data_get($payload, 'Id', ''),
            (string) data_get($payload, 'InvoiceId', ''),
        ]), static fn ($value) => trim((string) $value) !== ''));

        if ($candidateIds !== []) {
            $transaction = (clone $query)
                ->where(function ($q) use ($candidateIds) {
                    $q->whereIn('provider_reference', $candidateIds)
                        ->orWhereIn('provider_transaction_id', $candidateIds)
                        ->orWhereIn('provider_checkout_id', $candidateIds);
                })
                ->latest('id')
                ->first();

            if ($transaction) {
                return $transaction;
            }
        }

        $customerReference = trim((string) (
            data_get($verification, 'raw.Data.CustomerReference')
            ?? data_get($verification, 'raw.Data.CustomerRefNo')
            ?? data_get($payload, 'CustomerReference')
            ?? ''
        ));

        if (ctype_digit($customerReference)) {
            return (clone $query)->find((int) $customerReference);
        }

        return null;
    }

    private function resolvePlanPriceId(Plan $plan, string $billingCycle): ?string
    {
        $priceId = match ($billingCycle) {
            'yearly' => $plan->yearly_price_id,
            'one_time' => $plan->one_time_price_id,
            default => $plan->monthly_price_id,
        };

        $priceId = is_string($priceId) ? trim($priceId) : '';

        if ($priceId === '' || !str_starts_with($priceId, 'price_')) {
            return null;
        }

        return $priceId;
    }

    private function buildStripeLineItem(
        Plan $plan,
        string $billingCycle,
        ?string $priceId,
        float $discountedAmount,
        float $baseAmount
    ): array {
        if ($priceId && abs($discountedAmount - $baseAmount) < 0.00001) {
            return [
                'price' => $priceId,
                'quantity' => 1,
            ];
        }

        $currency = strtolower((string) config('app.currency_code', 'usd'));
        $lineItem = [
            'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => sprintf('SaaS Plan %s (%s)', $plan->name, $billingCycle),
                ],
                'unit_amount' => max(0, (int) round($discountedAmount * 100)),
            ],
            'quantity' => 1,
        ];

        if ($billingCycle === 'monthly' || $billingCycle === 'yearly') {
            $lineItem['price_data']['recurring'] = [
                'interval' => $billingCycle === 'yearly' ? 'year' : 'month',
            ];
        }

        return $lineItem;
    }

    private function persistCheckoutSubscription(
        User $user,
        object $checkoutSession,
        string $billingCycle,
        ?string $priceId,
        \Carbon\Carbon $accessEndsAt,
        array $lineItems = [],
        ?float $amountPaid = null,
        ?string $currency = null
    ): void {
        $stripeId = $this->resolveStripeObjectId(data_get($checkoutSession, 'subscription'));
        if ($stripeId === '') {
            $stripeId = $this->resolveStripeObjectId(data_get($checkoutSession, 'payment_intent'));
        }
        if ($stripeId === '') {
            $stripeId = $this->resolveStripeObjectId(data_get($checkoutSession, 'id'));
        }
        if ($stripeId === '') {
            return;
        }

        $mode = trim((string) ($checkoutSession->mode ?? ''));
        $type = $billingCycle === 'one_time' || $mode === 'payment' ? 'one_time' : 'default';

        $stripeStatus = trim((string) ($checkoutSession->payment_status ?? ''));
        if ($stripeStatus === '') {
            $stripeStatus = $mode === 'subscription' ? 'active' : 'paid';
        }
        $paymentMethod = $this->resolveCheckoutPaymentMethod($checkoutSession);
        $paidAt = $this->resolveCheckoutPaidAt($checkoutSession);

        $now = now();
        $subscription = DB::table('subscriptions')
            ->where('stripe_id', $stripeId)
            ->first(['id']);

        if ($subscription) {
            DB::table('subscriptions')
                ->where('id', $subscription->id)
                ->update([
                    'user_id' => $user->id,
                    'type' => $type,
                    'stripe_status' => $stripeStatus,
                    'payment_method' => $paymentMethod,
                    'stripe_price' => $priceId,
                    'quantity' => 1,
                    'paid_at' => $paidAt->toDateTimeString(),
                    'amount_paid' => $amountPaid,
                    'currency' => $currency,
                    'trial_ends_at' => $accessEndsAt->toDateTimeString(),
                    'ends_at' => $accessEndsAt->toDateTimeString(),
                    'updated_at' => $now,
                ]);
            $subscriptionId = (int) $subscription->id;
        } else {
            $subscriptionId = (int) DB::table('subscriptions')->insertGetId([
                'user_id' => $user->id,
                'type' => $type,
                'stripe_id' => $stripeId,
                'stripe_status' => $stripeStatus,
                'payment_method' => $paymentMethod,
                'stripe_price' => $priceId,
                'quantity' => 1,
                'paid_at' => $paidAt->toDateTimeString(),
                'amount_paid' => $amountPaid,
                'currency' => $currency,
                'trial_ends_at' => $accessEndsAt->toDateTimeString(),
                'ends_at' => $accessEndsAt->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($lineItems === []) {
            $lineItems = [(object) [
                'id' => $stripeId.':line:0',
                'quantity' => 1,
                'price' => (object) [
                    'id' => $priceId ?: 'unknown_price',
                    'product' => 'unknown_product',
                ],
            ]];
        }

        foreach ($lineItems as $index => $item) {
            $itemStripeId = trim((string) data_get($item, 'id', ''));
            if ($itemStripeId === '') {
                $itemStripeId = $stripeId.':line:'.$index;
            }

            $itemPrice = data_get($item, 'price');
            $itemPriceId = trim((string) (
                (is_object($itemPrice) ? data_get($itemPrice, 'id') : null)
                ?? (is_string($itemPrice) ? $itemPrice : null)
                ?? $priceId
                ?? 'unknown_price'
            ));

            $itemProductId = trim((string) (
                (is_object($itemPrice) ? data_get($itemPrice, 'product') : null)
                ?? 'unknown_product'
            ));

            $itemQuantity = (int) (data_get($item, 'quantity') ?? 1);
            if ($itemQuantity <= 0) {
                $itemQuantity = 1;
            }

            DB::table('subscription_items')->updateOrInsert(
                ['stripe_id' => $itemStripeId],
                [
                    'subscription_id' => $subscriptionId,
                    'stripe_product' => $itemProductId !== '' ? $itemProductId : 'unknown_product',
                    'stripe_price' => $itemPriceId !== '' ? $itemPriceId : 'unknown_price',
                    'quantity' => $itemQuantity,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function finalizeVerifiedProviderCheckout(
        Request $request,
        SubscriptionPaymentTransaction $transaction,
        array $paymentDetails
    ): RedirectResponse {
        if (TenantContext::id() && !$this->isExistingTenantPlanFlow($request)) {
            return $this->redirectToTenantRegister();
        }

        $registration = $request->session()->get(self::REGISTRATION_SESSION_KEY);
        $selection = $request->session()->get(self::PLAN_SELECTION_SESSION_KEY);
        if (!$registration || !$selection || empty($selection['plan_id']) || empty($selection['billing_cycle'])) {
            return to_route($this->authRouteName('register'))->with('error', 'Registration session expired. Please register again.');
        }

        $isExistingTenantFlow = ($registration['mode'] ?? null) === 'existing_tenant';

        Validator::make($registration, $isExistingTenantFlow ? [
            'existing_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'existing_tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ] : [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                'unique:tenants,email',
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
                'unique:tenants,domain',
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password_hash' => ['required', 'string'],
        ])->validate();

        $plan = Plan::query()
            ->where('is_active', true)
            ->find($selection['plan_id']);

        if (!$plan) {
            return to_route($this->authRouteName('register.plans'))->with('error', 'The selected plan is no longer available.');
        }

        $paidAt = $paymentDetails['paid_at'] ?? null;
        if (!$paidAt instanceof \Carbon\Carbon) {
            try {
                $paidAt = $paidAt ? \Carbon\Carbon::parse((string) $paidAt) : null;
            } catch (Throwable) {
                $paidAt = null;
            }
        }
        $paidAt ??= now();

        $amountPaid = isset($paymentDetails['amount_paid']) && is_numeric($paymentDetails['amount_paid'])
            ? (float) $paymentDetails['amount_paid']
            : (float) $transaction->amount;
        $currency = strtoupper(trim((string) ($paymentDetails['currency'] ?? $transaction->currency ?? config('app.currency_code', 'USD'))));
        if ($currency === '') {
            $currency = strtoupper((string) config('app.currency_code', 'USD'));
        }

        if ($isExistingTenantFlow) {
            $tenant = Tenant::query()->find((int) $registration['existing_tenant_id']);
            $user = User::withoutGlobalScope('tenant')->find((int) $registration['existing_user_id']);

            if (!$tenant || !$user || (int) $user->tenant_id !== (int) $tenant->id) {
                return to_route($this->authRouteName('tenant-login'))->with('error', 'Could not verify tenant account for this payment.');
            }

            $accessEndsAt = $this->resolveAccessEndsAt(
                (string) $selection['billing_cycle'],
                $tenant->trial_ends_at
            );

            DB::transaction(function () use ($tenant, $plan, $user, $accessEndsAt, $transaction, $selection, $paidAt, $amountPaid, $currency, $paymentDetails) {
                $tenant->update([
                    'plan_id' => $plan->id,
                    'trial_ends_at' => $accessEndsAt,
                    'is_active' => true,
                ]);

                $user->update([
                    'trial_ends_at' => $accessEndsAt,
                ]);

                $transaction->update([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => (string) $selection['billing_cycle'],
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'amount' => $amountPaid,
                    'currency' => $currency,
                    'provider_transaction_id' => $paymentDetails['provider_transaction_id'] ?? $transaction->provider_transaction_id,
                    'provider_checkout_id' => $paymentDetails['provider_checkout_id'] ?? $transaction->provider_checkout_id,
                    'failure_reason' => null,
                ]);
            });

            $this->ensureTenantAdminFullAccess($user, $tenant);
            $transaction->refresh();
            $this->persistProviderSubscriptionRecord(
                $user,
                $transaction,
                (string) ($paymentDetails['provider_code'] ?? $transaction->provider_code),
                (string) $selection['billing_cycle'],
                $accessEndsAt,
                (string) ($paymentDetails['payment_method'] ?? $paymentDetails['provider_code'] ?? $transaction->provider_code)
            );
        } else {
            $accessEndsAt = $this->resolveAccessEndsAt((string) $selection['billing_cycle']);

            [$tenant, $user] = DB::transaction(function () use ($registration, $plan, $accessEndsAt, $transaction, $selection, $paidAt, $amountPaid, $currency, $paymentDetails) {
                $tenant = Tenant::create([
                    'name' => $registration['name'],
                    'slug' => $this->generateUniqueSlug($registration['name']),
                    'domain' => $registration['custom_domain'] ?? null,
                    'email' => $registration['email'],
                    'phone' => $registration['phone'] ?? null,
                    'country_iso2' => $registration['country_iso2'] ?? null,
                    'phone_country_code' => $registration['phone_country_code'] ?? null,
                    'phone_national' => $registration['phone_national'] ?? null,
                    'phone_e164' => $registration['phone'] ?? null,
                    'plan_id' => $plan->id,
                    'partner_seats' => (int) ($registration['partner_seats'] ?? 0),
                    'trial_ends_at' => $accessEndsAt,
                    'is_active' => true,
                ]);

                $user = User::withoutGlobalScope('tenant')->create([
                    'name' => $registration['name'],
                    'email' => $registration['email'],
                    'password' => $registration['password_hash'],
                    'role' => UserRole::ADMIN,
                    'tenant_id' => $tenant->id,
                    'is_active' => true,
                    'trial_ends_at' => $accessEndsAt,
                ]);

                $transaction->update([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => (string) $selection['billing_cycle'],
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'amount' => $amountPaid,
                    'currency' => $currency,
                    'provider_transaction_id' => $paymentDetails['provider_transaction_id'] ?? $transaction->provider_transaction_id,
                    'provider_checkout_id' => $paymentDetails['provider_checkout_id'] ?? $transaction->provider_checkout_id,
                    'failure_reason' => null,
                ]);

                return [$tenant, $user];
            });

            $this->ensureTenantAdminFullAccess($user, $tenant);
            $transaction->refresh();
            $this->persistProviderSubscriptionRecord(
                $user,
                $transaction,
                (string) ($paymentDetails['provider_code'] ?? $transaction->provider_code),
                (string) $selection['billing_cycle'],
                $accessEndsAt,
                (string) ($paymentDetails['payment_method'] ?? $paymentDetails['provider_code'] ?? $transaction->provider_code)
            );

            event(new Registered($user));
        }

        $request->session()->forget([
            self::REGISTRATION_SESSION_KEY,
            self::PLAN_SELECTION_SESSION_KEY,
            self::CHECKOUT_SESSION_KEY,
            self::SUBSCRIPTION_TXN_SESSION_KEY,
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->to($this->postAuthenticationUrl($user, $tenant->slug))
            ->with('success', 'Payment completed successfully. Please verify your email to activate your account.');
    }

    private function persistProviderSubscriptionRecord(
        User $user,
        SubscriptionPaymentTransaction $transaction,
        string $providerCode,
        string $billingCycle,
        \Carbon\Carbon $accessEndsAt,
        string $paymentMethod
    ): void {
        $providerCode = strtolower(trim($providerCode)) ?: 'provider';
        $paymentMethod = trim($paymentMethod) !== '' ? trim($paymentMethod) : $providerCode;

        $providerObjectId = trim((string) (
            $transaction->provider_transaction_id
            ?: $transaction->provider_checkout_id
            ?: $transaction->provider_reference
            ?: $transaction->id
        ));

        $subscriptionExternalId = $providerCode.':'.$providerObjectId;
        $type = $billingCycle === 'one_time' ? 'one_time' : 'default';
        $paidAt = $transaction->paid_at ?? now();
        $amountPaid = is_numeric($transaction->amount) ? (float) $transaction->amount : null;
        $currency = strtoupper(trim((string) $transaction->currency));
        $currency = $currency !== '' ? $currency : null;
        $now = now();

        $existing = DB::table('subscriptions')
            ->where('stripe_id', $subscriptionExternalId)
            ->first(['id']);

        $payload = [
            'user_id' => $user->id,
            'type' => $type,
            'stripe_status' => 'paid',
            'payment_method' => $paymentMethod,
            'stripe_price' => null,
            'quantity' => 1,
            'paid_at' => $paidAt->toDateTimeString(),
            'amount_paid' => $amountPaid,
            'currency' => $currency,
            'trial_ends_at' => $accessEndsAt->toDateTimeString(),
            'ends_at' => $accessEndsAt->toDateTimeString(),
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('subscriptions')->where('id', $existing->id)->update($payload);
            return;
        }

        DB::table('subscriptions')->insert($payload + [
            'stripe_id' => $subscriptionExternalId,
            'created_at' => $now,
        ]);
    }

    private function resolveStripeObjectId(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_object($value) || is_array($value)) {
            return trim((string) data_get($value, 'id', ''));
        }

        return '';
    }

    private function resolveCheckoutPaymentMethod(object $checkoutSession): string
    {
        $methodType = trim((string) (
            data_get($checkoutSession, 'payment_intent.payment_method.type')
            ?? data_get($checkoutSession, 'subscription.default_payment_method.type')
            ?? data_get($checkoutSession, 'subscription.latest_invoice.payment_intent.payment_method.type')
            ?? data_get($checkoutSession, 'payment_method_types.0')
            ?? ''
        ));

        $cardBrand = trim((string) (
            data_get($checkoutSession, 'payment_intent.payment_method.card.brand')
            ?? data_get($checkoutSession, 'subscription.default_payment_method.card.brand')
            ?? data_get($checkoutSession, 'subscription.latest_invoice.payment_intent.payment_method.card.brand')
            ?? ''
        ));

        if ($methodType === 'card') {
            return $cardBrand !== '' ? 'Card ('.strtoupper($cardBrand).')' : 'Credit Card';
        }

        if ($methodType === '') {
            return 'Unknown';
        }

        return Str::headline($methodType);
    }

    private function resolveCheckoutPaidAt(object $checkoutSession): \Carbon\Carbon
    {
        $createdTimestamp = (int) data_get($checkoutSession, 'created', 0);
        if ($createdTimestamp > 0) {
            return \Carbon\Carbon::createFromTimestamp($createdTimestamp);
        }

        return now();
    }

    private function resolveCheckoutAmountPaid(object $checkoutSession, array $lineItems = []): ?float
    {
        $amountMinor = data_get($checkoutSession, 'amount_total');
        if (!is_numeric($amountMinor)) {
            $lineItemsTotal = 0;
            $hasAnyLineAmount = false;

            foreach ($lineItems as $item) {
                $lineAmount = data_get($item, 'amount_total');
                if (is_numeric($lineAmount)) {
                    $lineItemsTotal += (int) $lineAmount;
                    $hasAnyLineAmount = true;
                }
            }

            if (!$hasAnyLineAmount) {
                return null;
            }

            $amountMinor = $lineItemsTotal;
        }

        $currency = $this->resolveCheckoutCurrency($checkoutSession, $lineItems);
        $exponent = $this->resolveCurrencyExponent($currency);

        return (float) ((int) $amountMinor / (10 ** $exponent));
    }

    private function resolveCheckoutCurrency(object $checkoutSession, array $lineItems = []): ?string
    {
        $currency = strtoupper(trim((string) (data_get($checkoutSession, 'currency') ?? '')));
        if ($currency !== '') {
            return $currency;
        }

        foreach ($lineItems as $item) {
            $lineCurrency = strtoupper(trim((string) (data_get($item, 'currency') ?? '')));
            if ($lineCurrency !== '') {
                return $lineCurrency;
            }
        }

        $defaultCurrency = strtoupper(trim((string) config('cashier.currency', 'USD')));

        return $defaultCurrency !== '' ? $defaultCurrency : null;
    }

    private function resolveCurrencyExponent(?string $currency): int
    {
        $currencyCode = strtoupper(trim((string) $currency));

        if ($currencyCode === '') {
            return 2;
        }

        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
            'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        if (in_array($currencyCode, $zeroDecimalCurrencies, true)) {
            return 0;
        }

        $threeDecimalCurrencies = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
        if (in_array($currencyCode, $threeDecimalCurrencies, true)) {
            return 3;
        }

        return 2;
    }

    private function resolveAccessEndsAt(string $billingCycle, mixed $existingEndsAt = null): \Carbon\Carbon
    {
        $baseDate = now();
        if ($existingEndsAt instanceof \DateTimeInterface && $existingEndsAt > $baseDate) {
            $baseDate = \Carbon\Carbon::instance($existingEndsAt);
        }

        return match ($billingCycle) {
            'yearly' => $baseDate->copy()->addYear(),
            'one_time' => $baseDate->copy()->addYear(),
            default => $baseDate->copy()->addMonth(),
        };
    }

    private function isExistingTenantPlanFlow(Request $request): bool
    {
        return TenantContext::id() !== null
            && ($request->session()->get(self::REGISTRATION_SESSION_KEY.'.mode') === 'existing_tenant');
    }

    private function isExistingTenantPlanFlowOnSubdomain(Request $request): bool
    {
        return $this->isExistingTenantPlanFlow($request);
    }

    private function authRouteName(string $routeName): string
    {
        return TenantContext::id() ? 'tenant.'.$routeName : $routeName;
    }

    private function availablePlatformSubscriptionProviders(): array
    {
        $providers = PaymentProvider::query()
            ->where('is_enabled', true)
            ->where('supports_platform_subscriptions', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentProvider $provider) => [
                'code' => $provider->code,
                'name' => $provider->name,
                'mode' => $provider->mode,
                'is_default' => (bool) $provider->is_default,
                'description' => $provider->description,
            ])
            ->values()
            ->all();

        if ($providers === []) {
            $providers[] = [
                'code' => 'stripe',
                'name' => 'Stripe',
                'mode' => 'test',
                'is_default' => true,
                'description' => 'Fallback default provider (legacy Stripe flow).',
            ];
        }

        return $providers;
    }

    private function defaultPlatformSubscriptionProviderCode(): ?string
    {
        $provider = PaymentProvider::query()
            ->where('is_enabled', true)
            ->where('supports_platform_subscriptions', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first(['code']);

        return $provider?->code;
    }

    private function resolveStripeSecretFromProviderConfig(?PaymentProvider $provider): ?string
    {
        if (!$provider || strtolower((string) $provider->code) !== 'stripe') {
            return null;
        }

        $config = is_array($provider->config) ? $provider->config : [];
        $secret = trim((string) ($config['secret_key'] ?? ''));

        return $secret !== '' ? $secret : null;
    }

    private function redirectToTenantRegister(): RedirectResponse
    {
        $params = [];
        if ($slug = TenantContext::get()?->slug) {
            $params['subdomain'] = $slug;
        }

        return to_route('tenant.register', $params);
    }

    private function generateUniqueSlug(string $companyName): string
    {
        $baseSlug = Str::slug($companyName);
        if ($baseSlug === '') {
            $baseSlug = 'tenant';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
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

    private function registrationCountries(): array
    {
        static $countries = null;

        if (is_array($countries)) {
            return $countries;
        }

        $namesEn = Countries::getNames('en');
        $namesAr = Countries::getNames('ar');
        $phoneUtil = PhoneNumberUtil::getInstance();
        $items = [];

        foreach ($namesEn as $iso2 => $nameEn) {
            $iso = strtoupper((string) $iso2);

            if (strlen($iso) !== 2) {
                continue;
            }

            $countryCode = (int) $phoneUtil->getCountryCodeForRegion($iso);
            if ($countryCode <= 0) {
                continue;
            }

            $items[] = [
                'iso2' => $iso,
                'name_en' => $nameEn,
                'name_ar' => $namesAr[$iso] ?? $nameEn,
                'dial_code' => '+'.$countryCode,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['name_en'], (string) $b['name_en']));
        $countries = $items;

        return $countries;
    }

    private function registrationCountryIso2List(): array
    {
        return array_map(
            static fn (array $country): string => (string) $country['iso2'],
            $this->registrationCountries()
        );
    }

    private function normalizeRegistrationPhone(?string $countryIso2, ?string $phoneNational): array
    {
        $countryIso2 = strtoupper(trim((string) ($countryIso2 ?? '')));
        $phoneNational = trim((string) ($phoneNational ?? ''));

        if ($phoneNational === '') {
            return [null, null, null];
        }

        if ($countryIso2 === '') {
            return [null, null, $phoneNational];
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $parsed = $phoneUtil->parse($phoneNational, $countryIso2);
        } catch (NumberParseException) {
            return [null, null, $phoneNational];
        }

        if (!$phoneUtil->isValidNumberForRegion($parsed, $countryIso2)) {
            if (!$phoneUtil->isValidNumber($parsed)) {
                return [null, null, $phoneNational];
            }
        }

        $e164 = $phoneUtil->format($parsed, PhoneNumberFormat::E164);
        $dialCode = '+'.$parsed->getCountryCode();
        $normalizedNational = (string) $parsed->getNationalNumber();

        return [$e164, $dialCode, $normalizedNational];
    }

    private function requestQueryValue(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));
        if ($value !== '') {
            return $value;
        }

        $requestUri = (string) $request->server('REQUEST_URI', '');
        if ($requestUri === '') {
            return null;
        }

        $query = parse_url($requestUri, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $fallback = trim((string) ($params[$key] ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    private function postAuthenticationUrl(User $user, ?string $tenantSlug = null): string
    {
        if ($user->requiresTenantEmailVerification() && !$user->hasVerifiedEmail()) {
            return $this->verificationNoticeUrl($tenantSlug);
        }

        if ($user->role === UserRole::SUPER_ADMIN) {
            return route('superadmin.dashboard');
        }

        if ($user->role === UserRole::ADMIN && $tenantSlug) {
            return route('admin.cars.index', ['subdomain' => $tenantSlug]);
        }

        if ($user->role === UserRole::CLIENT && $tenantSlug) {
            return route('client.home', ['subdomain' => $tenantSlug]);
        }

        return route('dashboard');
    }

    private function verificationNoticeUrl(?string $tenantSlug = null): string
    {
        if ($tenantSlug) {
            return route('tenant.verification.notice', ['subdomain' => $tenantSlug]);
        }

        return route('verification.notice');
    }

    private function ensureTenantAdminFullAccess(User $user, ?Tenant $tenant = null): void
    {
        if ($user->role !== UserRole::ADMIN || empty($user->tenant_id)) {
            return;
        }

        if (!$tenant) {
            $tenant = Tenant::query()->find((int) $user->tenant_id);
        }

        if (!$tenant || strcasecmp((string) $tenant->email, (string) $user->email) !== 0) {
            return;
        }

        $tenantId = (int) $user->tenant_id;

        $role = Role::withoutGlobalScope('tenant')->firstOrCreate(
            [
                'name' => 'tenant-owner',
                'tenant_id' => $tenantId,
            ],
            [
                'display_name' => 'Tenant Owner',
                'description' => 'Default full-access role for the tenant account owner.',
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
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
