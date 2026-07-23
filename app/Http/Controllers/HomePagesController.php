<?php

namespace App\Http\Controllers;

use App\Enums\CarStatus;
use App\Core\LocalizationSettings;
use App\Core\TenantContext;
use App\Core\LandingPageSettings;
use App\Models\Car;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\SaasVisit;
use App\Models\SiteSetting;
use App\Models\TenantSiteSetting;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Rules\LettersOnly;
use App\Support\PlanTranslations;
use App\Support\PlanPricing;
use App\Support\CurrencyCatalog;
use App\Support\TenantSeoResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomePagesController extends Controller
{
    private const MAIN_SITE_SEO_KEY = 'main_site_seo';
    private const WEB_PAGES_CONTENT_KEY = 'main_web_pages_content';
    private const WEB_PAGE_TITLES = [
        'privacy_policy' => [
            'en' => 'Privacy Policy',
            'ar' => 'سياسة الخصوصية',
            'ur' => 'Privacy Policy',
        ],
        'terms_of_use' => [
            'en' => 'Terms of Use',
            'ar' => 'سياسة الاستخدام',
            'ur' => 'Terms of Use',
        ],
        'security_policy' => [
            'en' => 'Security Policy',
            'ar' => 'سياسة الأمان',
            'ur' => 'Security Policy',
        ],
    ];
    private const STATIC_PAGE_TITLES = [
        'privacy_policy' => [
            'en' => 'Privacy Policy',
            'ar' => 'سياسة الخصوصية',
            'ur' => 'Privacy Policy',
        ],
        'terms_conditions' => [
            'en' => 'Terms of Use',
            'ar' => 'سياسة الاستخدام',
            'ur' => 'Terms of Use',
        ],
        'security_policy' => [
            'en' => 'Security Policy',
            'ar' => 'سياسة الأمان',
            'ur' => 'Security Policy',
        ],
    ];
    /**
     * @return array<int, string>
     */
    private function publicFleetStatuses(): array
    {
        return [
            CarStatus::AVAILABLE->value,
            CarStatus::RESERVED->value,
            CarStatus::RENTED->value,
            CarStatus::CLEANING->value,
        ];
    }

    public function index()
    {
        if (!TenantContext::get()) {
            $this->recordSaasLandingVisit(request());
            $carSearch = trim((string) request()->string('car_search')->toString());

            $landingSettings = $this->localizedLandingSettings();
            $availableLocales = array_values(array_map('strval', array_filter(
                (array) data_get($landingSettings, 'enabled_locales', []),
                static fn ($value) => trim((string) $value) !== ''
            )));
            if (empty($availableLocales)) {
                $availableLocales = LandingPageSettings::supportedLocaleKeys();
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
                        'is_most_value',
                        'monthly_price',
                        'yearly_price',
                        'one_time_price',
                    ]), app()->getLocale())
            );

            $tenantLogos = Tenant::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(12)
                ->with('siteSetting.files')
                ->get(['id', 'name', 'slug'])
                ->map(static function (Tenant $tenant) {
                    $siteSetting = TenantSiteSetting::forTenant($tenant);

                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'logo_url' => $siteSetting['logo_url']
                            ?? data_get($tenant->settings, 'branding.logo_url')
                            ?? data_get($tenant->settings, 'logo_url')
                            ?? data_get($tenant->settings, 'logo'),
                    ];
                })
                ->values();

            $featuredCars = Car::withoutTenantScope()
                ->whereIn('status', $this->publicFleetStatuses())
                ->where('tenant_id', '>', 0)
                ->with([
                    'tenant.siteSetting',
                    'branch:id,tenant_id,country,city,address',
                    'files',
                ])
                ->select([
                    'id',
                    'tenant_id',
                    'branch_id',
                    'make',
                    'model',
                    'year',
                    'price_per_day',
                    'description',
                    'description_translations',
                    'fuel_type',
                    'status',
                ])
                ->when($carSearch !== '', function ($query) use ($carSearch) {
                    $query->where(function ($builder) use ($carSearch) {
                        $builder->where('make', 'like', "%{$carSearch}%")
                            ->orWhere('model', 'like', "%{$carSearch}%")
                            ->orWhere('description', 'like', "%{$carSearch}%")
                            ->orWhereHas('tenant', function ($tenantQuery) use ($carSearch) {
                                $tenantQuery->where('name', 'like', "%{$carSearch}%")
                                    ->orWhere('slug', 'like', "%{$carSearch}%");
                            });
                    });
                })
                ->inRandomOrder()
                ->limit(6)
                ->get()
                ->map(fn (Car $car) => $this->carCardData($car))
                ->values();

            $contactSubmitUrl = route('contact.guestContact');
            $seo = TenantSeoResolver::forPage(null, 'home');

            return inertia('SuperAdmin/landing/Landing', compact('landingSettings', 'plans', 'tenantLogos', 'featuredCars', 'carSearch', 'contactSubmitUrl', 'availableLocales', 'seo'));
        }

        $homeCars = Car::whereIn('status', $this->publicFleetStatuses())
            ->with([
                'tenant.siteSetting',
                'branch:id,tenant_id,country,city,address',
                'files',
            ])
            ->select('id', 'tenant_id', 'branch_id', 'make', 'model', 'year', 'price_per_day', 'description', 'description_translations', 'fuel_type', 'status')
            ->orderByRaw("CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 WHEN status = ? THEN 2 ELSE 3 END", [
                CarStatus::AVAILABLE->value,
                CarStatus::RESERVED->value,
                CarStatus::RENTED->value,
            ])
            ->orderByDesc('year')
            ->limit(6)
            ->get()
            ->map(fn (Car $car) => $this->carCardData($car))
            ->values();
        $seo = TenantSeoResolver::forPage(TenantContext::get(), 'home');

        return inertia('Welcome', compact('homeCars', 'seo'));
    }

    private function recordSaasLandingVisit(Request $request): void
    {
        if (!$request->isMethod('get') || $request->expectsJson()) {
            return;
        }

        $referrer = trim((string) $request->headers->get('referer'));

        SaasVisit::query()->create([
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'landing_path' => '/'.$request->path('/'),
            'referrer_url' => $referrer !== '' ? $referrer : null,
            'referrer_host' => $this->parseUrlComponent($referrer, PHP_URL_HOST),
            'referrer_path' => $this->parseUrlComponent($referrer, PHP_URL_PATH),
            'utm_source' => $this->nullableQueryParam($request, 'utm_source'),
            'utm_medium' => $this->nullableQueryParam($request, 'utm_medium'),
            'utm_campaign' => $this->nullableQueryParam($request, 'utm_campaign'),
            'utm_content' => $this->nullableQueryParam($request, 'utm_content'),
            'utm_term' => $this->nullableQueryParam($request, 'utm_term'),
            'ip_address' => $request->ip(),
            'user_agent' => $this->nullableString($request->userAgent()),
            'visited_at' => now(),
        ]);
    }

    private function parseUrlComponent(?string $url, int $component): ?string
    {
        $url = trim((string) ($url ?? ''));

        if ($url === '') {
            return null;
        }

        $value = parse_url($url, $component);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nullableQueryParam(Request $request, string $key): ?string
    {
        return $this->nullableString($request->query($key));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    public function fleet(Request $request)
    {
        $tenantId = TenantContext::id();

        $query = Car::withoutTenantScope()->whereIn('status', $this->publicFleetStatuses())
            ->where('tenant_id', '>', 0)
            ->when($tenantId, fn ($query) => $this->applyTenantFleetScope($query, (int) $tenantId))
            ->with([
                'tenant.siteSetting',
                'branch:id,tenant_id,name,country,city,address',
                'files',
            ])
            ->select('id', 'tenant_id', 'branch_id', 'make', 'model', 'year', 'price_per_day', 'description', 'description_translations', 'fuel_type', 'status');
        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('make', 'like', "%{$searchTerm}%")
                    ->orWhere('model', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        if (!$tenantId && $request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        // Make filter
        if ($request->filled('make')) {
            $query->where('make', $request->make);
        }

        // Fuel type filter
        if ($request->filled('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        // Year filter
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price_per_day', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_day', '<=', $request->max_price);
        }

        $cars = $query
            ->orderByRaw("CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 WHEN status = ? THEN 2 ELSE 3 END", [
                CarStatus::AVAILABLE->value,
                CarStatus::RESERVED->value,
                CarStatus::RENTED->value,
            ])
            ->paginate(10)
            ->through(fn (Car $car) => $this->carCardData($car))
            ->withQueryString();

        // Get filter options
        $makes = Car::withoutTenantScope()
            ->whereIn('status', $this->publicFleetStatuses())
            ->where('tenant_id', '>', 0)
            ->when($tenantId, fn ($query) => $this->applyTenantFleetScope($query, (int) $tenantId))
            ->distinct()
            ->pluck('make')
            ->toArray();

        $fuelTypes = Car::withoutTenantScope()
            ->whereIn('status', $this->publicFleetStatuses())
            ->where('tenant_id', '>', 0)
            ->when($tenantId, fn ($query) => $this->applyTenantFleetScope($query, (int) $tenantId))
            ->distinct()
            ->pluck('fuel_type')
            ->toArray();

        $years = Car::withoutTenantScope()
            ->whereIn('status', $this->publicFleetStatuses())
            ->where('tenant_id', '>', 0)
            ->when($tenantId, fn ($query) => $this->applyTenantFleetScope($query, (int) $tenantId))
            ->distinct()
            ->pluck('year')
            ->toArray();

        $publicTenantIds = Car::withoutTenantScope()
            ->whereIn('status', $this->publicFleetStatuses())
            ->where('tenant_id', '>', 0)
            ->when($tenantId, fn ($query) => $this->applyTenantFleetScope($query, (int) $tenantId))
            ->distinct()
            ->pluck('tenant_id');

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->whereIn('id', $publicTenantIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(static fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ])
            ->values();

        $publicBranchIds = Car::withoutTenantScope()
            ->whereIn('status', $this->publicFleetStatuses())
            ->where('tenant_id', '>', 0)
            ->when($tenantId, fn ($query) => $this->applyTenantFleetScope($query, (int) $tenantId))
            ->whereNotNull('branch_id')
            ->distinct()
            ->pluck('branch_id');

        $branches = Branch::withoutTenantScope()
            ->whereIn('id', $publicBranchIds)
            ->with(['tenant:id,name,slug'])
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'country', 'city', 'address'])
            ->map(static fn (Branch $branch) => [
                'id' => $branch->id,
                'tenant_id' => $branch->tenant_id,
                'name' => $branch->name,
                'country' => $branch->country,
                'city' => $branch->city,
                'address' => $branch->address,
            ])
            ->values();

        $filters = $request->only(['search', 'tenant_id', 'branch_id', 'make', 'fuel_type', 'min_price', 'max_price', 'year']);
        $seo = TenantSeoResolver::forPage(TenantContext::get(), 'fleet');
        $landingSettings = null;
        $availableLocales = null;

        if (!$tenantId) {
            $landingSettings = $this->localizedLandingSettings();
            $availableLocales = array_values(array_map('strval', array_filter(
                (array) data_get($landingSettings, 'enabled_locales', []),
                static fn ($value) => trim((string) $value) !== ''
            )));

            if (empty($availableLocales)) {
                $availableLocales = LandingPageSettings::supportedLocaleKeys();
            }
        }

        $props = [
            'cars' => $cars,
            'makes' => $makes,
            'fuelTypes' => $fuelTypes,
            'years' => $years,
            'filters' => $filters,
            'tenants' => $tenants,
            'branches' => $branches,
            'seo' => $seo,
            'landingSettings' => $landingSettings,
        ];

        if ($availableLocales !== null) {
            $props['availableLocales'] = $availableLocales;
            $props['available_locales'] = $availableLocales;
        }

        return inertia('Fleet', $props);
    }

    public function applications()
    {
        abort_if(TenantContext::get(), 404);

        [$landingSettings, $availableLocales] = $this->landingShellSettings();
        abort_if(data_get($landingSettings, 'applications_page.enabled') === false, 404);

        return inertia('Applications', [
            'landingSettings' => $landingSettings,
            'applicationsPage' => data_get($landingSettings, 'applications_page', []),
            'availableLocales' => $availableLocales,
            'available_locales' => $availableLocales,
            'seo' => TenantSeoResolver::forPage(null, 'applications'),
        ]);
    }

    public function plans()
    {
        abort_if(TenantContext::get(), 404);

        [$landingSettings, $availableLocales] = $this->landingShellSettings();
        abort_if(data_get($landingSettings, 'plans_comparison_page.enabled') === false, 404);

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
                    'is_most_value',
                    'monthly_price',
                    'yearly_price',
                    'one_time_price',
                    'max_employees',
                    'max_branches',
                    'max_cars',
                    'max_contracts',
                ]), app()->getLocale())
        )->values();

        return inertia('PlansComparison', [
            'landingSettings' => $landingSettings,
            'plansPage' => data_get($landingSettings, 'plans_comparison_page', []),
            'plans' => $plans,
            'availableLocales' => $availableLocales,
            'available_locales' => $availableLocales,
            'seo' => TenantSeoResolver::forPage(null, 'plans'),
        ]);
    }

    private function applyTenantFleetScope($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function carCardData(Car $car): array
    {
        return [
            'id' => $car->id,
            'tenant_id' => $car->tenant_id,
            'tenant_slug' => $car->tenant?->slug,
            'branch_id' => $car->branch_id,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'price_per_day' => (string) $car->price_per_day,
            'currency' => CurrencyCatalog::forTenant($car->tenant),
            'description' => $car->localizedDescription(),
            'fuel_type' => $car->fuel_type?->value ?? (string) $car->fuel_type,
            'status' => $car->status?->value ?? (string) $car->status,
            'image_url' => $car->image_url,
            'tenant_name' => $car->tenant?->name,
            'tenant_logo_url' => $this->resolveTenantLogo($car),
            'tenant_primary_color' => $this->resolveTenantPrimaryColor($car),
            'tenant_secondary_color' => $this->resolveTenantSecondaryColor($car),
            'location_text' => $this->resolveCarLocation($car),
        ];
    }

    private function resolveTenantPrimaryColor(Car $car): string
    {
        return $this->resolveTenantColor($car, 'primary_color', '#3b82f6');
    }

    private function resolveTenantSecondaryColor(Car $car): string
    {
        return $this->resolveTenantColor($car, 'secondary_color', '#6d28d9');
    }

    private function resolveTenantColor(Car $car, string $field, string $fallback): string
    {
        $tenant = $car->tenant;
        if (!$tenant) {
            return $fallback;
        }

        $siteSetting = $tenant->siteSetting;
        $color = trim((string) ($siteSetting?->{$field} ?? ''));

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $brandingColor = trim((string) data_get($settings, "branding.{$field}", ''));

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $brandingColor)
            ? strtolower($brandingColor)
            : $fallback;
    }

    private function resolveTenantLogo(Car $car): ?string
    {
        $tenant = $car->tenant;
        if (!$tenant) {
            return null;
        }

        $siteLogo = trim((string) data_get(TenantSiteSetting::forTenant($tenant), 'logo_url', ''));
        if ($siteLogo !== '') {
            return $siteLogo;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $brandingLogo = trim((string) (
            data_get($settings, 'branding.logo_url')
            ?? data_get($settings, 'logo_url')
            ?? data_get($settings, 'logo')
            ?? ''
        ));

        return $brandingLogo !== '' ? $brandingLogo : null;
    }

    private function resolveCarLocation(Car $car): ?string
    {
        $branchCountry = trim((string) ($car->branch?->country ?? ''));
        $branchCity = trim((string) ($car->branch?->city ?? ''));
        $branchLocation = implode(' - ', array_values(array_filter([$branchCountry, $branchCity])));

        if ($branchLocation !== '') {
            return $branchLocation;
        }

        $marketLocation = $car->tenant?->siteSetting?->market_location;
        $country = trim((string) (
            data_get($marketLocation, 'country_name')
            ?: data_get($marketLocation, 'country_code')
            ?: ''
        ));
        $city = trim((string) data_get($marketLocation, 'city', ''));
        $location = implode(' - ', array_values(array_filter([$country, $city])));

        return $location !== '' ? $location : null;
    }

    public function about()
    {
        return inertia('About', [
            'seo' => TenantSeoResolver::forPage(TenantContext::get(), 'about'),
        ]);
    }

    public function sitemap(): Response
    {
        $tenant = TenantContext::get();
        if ($tenant) {
            $settings = TenantSiteSetting::forTenant($tenant);
            $pages = (array) data_get($settings, 'seo.technical.sitemap.pages', []);
        } else {
            $stored = SiteSetting::query()->where('key', self::MAIN_SITE_SEO_KEY)->value('value');
            $pages = (array) data_get(is_array($stored) ? $stored : [], 'technical.sitemap.pages', []);
        }
        $baseUrl = rtrim((string) url('/'), '/');

        if (empty($pages)) {
            $pages = [
                ['path' => '/', 'priority' => 1.0, 'changeFreq' => 'weekly', 'lastmod' => null],
            ];
        }

        $urls = collect($pages)
            ->filter(fn ($page) => is_array($page) && trim((string) data_get($page, 'path')) !== '')
            ->map(function (array $page) use ($baseUrl) {
                $path = (string) data_get($page, 'path', '/');
                if (!str_starts_with($path, '/')) {
                    $path = '/'.$path;
                }

                $priority = data_get($page, 'priority');
                $priority = is_numeric($priority) ? max(0.1, min(1.0, round((float) $priority, 1))) : 0.5;

                $changeFreq = (string) data_get($page, 'changeFreq', 'weekly');
                $lastmod = $this->nullableString(data_get($page, 'lastmod')) ?: now()->toDateString();

                return [
                    'loc' => $baseUrl.$path,
                    'lastmod' => $lastmod,
                    'changefreq' => $changeFreq,
                    'priority' => number_format($priority, 1, '.', ''),
                ];
            })
            ->values();

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $tenant = TenantContext::get();
        if ($tenant) {
            $settings = TenantSiteSetting::forTenant($tenant);
            $robots = (array) data_get($settings, 'seo.technical.robots', []);
        } else {
            $stored = SiteSetting::query()->where('key', self::MAIN_SITE_SEO_KEY)->value('value');
            $robots = (array) data_get(is_array($stored) ? $stored : [], 'technical.robots', []);
        }
        $baseUrl = rtrim((string) url('/'), '/');

        $allowAll = (bool) data_get($robots, 'allowAll', true);
        $disallowPaths = collect((array) data_get($robots, 'disallowPaths', []))
            ->map(fn ($path) => $this->nullableString($path))
            ->filter()
            ->values()
            ->all();

        $crawlDelay = (int) data_get($robots, 'crawlDelay', 1);
        $requestRate = (int) data_get($robots, 'requestRate', 30);
        $sitemapUrl = (string) (data_get($robots, 'sitemapUrl') ?: '/sitemap.xml');
        if (!str_starts_with($sitemapUrl, '/')) {
            $sitemapUrl = '/'.$sitemapUrl;
        }

        $content = "User-agent: *\n";
        if ($allowAll) {
            $content .= "Allow: /\n";
        } else {
            foreach ($disallowPaths as $path) {
                $linePath = str_starts_with($path, '/') ? $path : '/'.$path;
                $content .= "Disallow: {$linePath}\n";
            }
        }

        if ($crawlDelay > 0) {
            $content .= "Crawl-delay: {$crawlDelay}\n";
        }

        if ($requestRate > 0) {
            $content .= "Request-rate: {$requestRate}/1m\n";
        }

        $content .= "\nSitemap: {$baseUrl}{$sitemapUrl}\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function contact()
    {
        return inertia('Contact', [
            'seo' => TenantSeoResolver::forPage(TenantContext::get(), 'contact'),
        ]);
    }

    public function privacyPolicy()
    {
        return $this->staticPage('privacy_policy');
    }

    public function termsOfUse()
    {
        return $this->staticPage('terms_of_use');
    }

    public function termsConditionsRedirect()
    {
        $params = [];
        $subdomain = request()->route('subdomain');

        if ($subdomain) {
            $params['subdomain'] = $subdomain;
        }

        $route = request()->routeIs('tenant.*') ? 'tenant.terms-of-use' : 'terms-of-use';

        return redirect()->route($route, $params, 301);
    }

    public function securityPolicy()
    {
        return $this->staticPage('security_policy');
    }

    private function staticPage(string $section)
    {
        abort_unless(array_key_exists($section, self::WEB_PAGE_TITLES), 404);

        $localization = LocalizationSettings::load();
        $locale = app()->getLocale();
        $tenantStaticPage = $this->tenantStaticPageSettings($section);
        $content = $this->localizedStaticPageContent(
            is_array($tenantStaticPage)
                ? data_get($tenantStaticPage, 'content')
                : data_get($this->staticPageSettings(), $section),
            $locale,
            $localization
        );
        $title = is_array($tenantStaticPage)
            ? ($this->localizedStaticPageContent(data_get($tenantStaticPage, 'title'), $locale, $localization)
                ?: $this->localizedStaticPageTitle($section, $locale))
            : $this->localizedStaticPageTitle($section, $locale);
        [$landingSettings, $availableLocales] = TenantContext::get()
            ? [null, null]
            : $this->landingShellSettings();

        $props = [
            'page' => [
                'section' => $section,
                'title' => $title,
                'content_html' => $content,
                'locale' => $locale,
                'direction' => $this->staticPageDirection($locale, $localization),
            ],
            'seo' => TenantSeoResolver::forPage(TenantContext::get(), str_replace('_', '-', $section)),
        ];

        if ($landingSettings !== null) {
            $props['landingSettings'] = $landingSettings;
        }

        if ($availableLocales !== null) {
            $props['availableLocales'] = $availableLocales;
            $props['available_locales'] = $availableLocales;
        }

        return inertia('StaticPage', $props);
    }

    private function tenantStaticPageSettings(string $section): ?array
    {
        $tenant = TenantContext::get();

        if (! $tenant) {
            return null;
        }

        $settings = TenantSiteSetting::forTenant($tenant);
        $page = data_get($settings, "static_pages.{$section}");

        return is_array($page) ? $page : null;
    }

    private function staticPageSettings(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::WEB_PAGES_CONTENT_KEY)
            ->value('value');

        return is_array($stored) ? $stored : [];
    }

    private function localizedStaticPageContent(mixed $content, string $locale, array $localization): string
    {
        if (!is_array($content)) {
            return trim((string) ($content ?? ''));
        }

        $defaultLocale = LocalizationSettings::defaultLocale($localization);
        $value = trim((string) ($content[$locale] ?? ''));

        if ($value !== '') {
            return $value;
        }

        $defaultValue = trim((string) ($content[$defaultLocale] ?? ''));

        if ($defaultValue !== '') {
            return $defaultValue;
        }

        foreach ($content as $fallbackValue) {
            $fallbackValue = trim((string) $fallbackValue);
            if ($fallbackValue !== '') {
                return $fallbackValue;
            }
        }

        return '';
    }

    private function localizedStaticPageTitle(string $section, string $locale): string
    {
        $translationKey = "static_pages.{$section}.title";
        $translatedTitle = trans($translationKey, [], $locale);

        if (is_string($translatedTitle) && $translatedTitle !== $translationKey && trim($translatedTitle) !== '') {
            return $translatedTitle;
        }

        $localizedTitles = [
            'privacy_policy' => [
                'ar' => 'سياسة الخصوصية',
            ],
            'terms_of_use' => [
                'ar' => 'سياسة الاستخدام',
            ],
            'security_policy' => [
                'ar' => 'سياسة الأمان',
            ],
        ];
        $titles = array_replace(
            self::WEB_PAGE_TITLES[$section] ?? [],
            $localizedTitles[$section] ?? []
        );
        $baseLocale = strtolower(explode('-', $locale)[0] ?? $locale);

        return (string) ($titles[$locale] ?? $titles[$baseLocale] ?? $titles['en'] ?? $section);
    }

    private function staticPageDirection(string $locale, array $localization): string
    {
        foreach (($localization['locales'] ?? []) as $row) {
            if (($row['code'] ?? null) === $locale && in_array(($row['direction'] ?? null), ['ltr', 'rtl'], true)) {
                return (string) $row['direction'];
            }
        }

        return str_starts_with($locale, 'ar') || str_starts_with($locale, 'ur') ? 'rtl' : 'ltr';
    }

    private function landingShellSettings(): array
    {
        $landingSettings = $this->localizedLandingSettings();
        $availableLocales = array_values(array_map('strval', array_filter(
            (array) data_get($landingSettings, 'enabled_locales', []),
            static fn ($value) => trim((string) $value) !== ''
        )));

        if (empty($availableLocales)) {
            $availableLocales = LandingPageSettings::supportedLocaleKeys();
        }

        return [$landingSettings, $availableLocales];
    }

    private function localizedLandingSettings(): array
    {
        $landingSetting = SiteSetting::query()
            ->with('files')
            ->where('key', LandingPageSettings::KEY)
            ->first();

        return LandingPageSettings::localize(
            $this->hydrateLandingImageUrls(
                LandingPageSettings::normalize(is_array($landingSetting?->value) ? $landingSetting->value : null),
                $landingSetting,
            ),
            app()->getLocale()
        );
    }

    private function hydrateLandingImageUrls(array $settings, ?SiteSetting $landingSetting): array
    {
        if (!$landingSetting) {
            return $settings;
        }

        $heroUrl = $this->latestLandingFileUrl($landingSetting, 'hero');
        if ($this->shouldUseUploadedImageUrl(data_get($settings, 'hero.image_url'), $heroUrl)) {
            data_set($settings, 'hero.image_url', $heroUrl);
        }

        $localizedImages = (array) data_get($settings, 'hero.localized_images', []);
        foreach (LandingPageSettings::supportedLocaleKeys() as $locale) {
            $localeUrl = $this->latestLandingFileUrl($landingSetting, 'hero_'.$locale);
            if ($this->shouldUseUploadedImageUrl($localizedImages[$locale] ?? null, $localeUrl)) {
                $localizedImages[$locale] = $localeUrl;
            }
        }
        data_set($settings, 'hero.localized_images', $localizedImages);

        $cards = (array) data_get($settings, 'features_section.cards', []);
        foreach ($cards as $index => $card) {
            if (!is_array($card)) {
                continue;
            }

            $url = $this->latestLandingFileUrl($landingSetting, 'feature_card_'.max(0, (int) $index).'_image');
            if ($this->shouldUseUploadedImageUrl($card['image_url'] ?? null, $url)) {
                $cards[$index]['image_url'] = $url;
            }
        }
        data_set($settings, 'features_section.cards', $cards);

        $steps = (array) data_get($settings, 'getting_started.items', []);
        foreach ($steps as $index => $step) {
            if (!is_array($step)) {
                continue;
            }

            $url = $this->latestLandingFileUrl($landingSetting, 'getting_started_step_'.max(0, (int) $index).'_image');
            if ($this->shouldUseUploadedImageUrl($step['image_url'] ?? null, $url)) {
                $steps[$index]['image_url'] = $url;
            }
        }
        data_set($settings, 'getting_started.items', $steps);

        $apps = (array) data_get($settings, 'mobile_apps_section.apps', []);
        foreach ($apps as $index => $app) {
            if (!is_array($app)) {
                continue;
            }

            $imageUrl = $this->latestLandingFileUrl($landingSetting, 'mobile_app_'.max(0, (int) $index).'_image');
            if ($this->shouldUseUploadedImageUrl($app['image_url'] ?? null, $imageUrl)) {
                $apps[$index]['image_url'] = $imageUrl;
            }

            $localizedImages = is_array($app['localized_images'] ?? null) ? $app['localized_images'] : [];
            foreach (LandingPageSettings::supportedLocaleKeys() as $locale) {
                $localizedImageUrl = $this->latestLandingFileUrl(
                    $landingSetting,
                    'mobile_app_'.max(0, (int) $index).'_image_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $locale)
                );
                if ($this->shouldUseUploadedImageUrl($localizedImages[$locale] ?? null, $localizedImageUrl)) {
                    $localizedImages[$locale] = $localizedImageUrl;
                }
            }
            $apps[$index]['localized_images'] = $localizedImages;

            $iconUrl = $this->latestLandingFileUrl($landingSetting, 'mobile_app_'.max(0, (int) $index).'_icon');
            if ($this->shouldUseUploadedImageUrl($app['icon_url'] ?? null, $iconUrl)) {
                $apps[$index]['icon_url'] = $iconUrl;
            }
        }
        data_set($settings, 'mobile_apps_section.apps', $apps);

        return LandingPageSettings::normalize($settings);
    }

    private function latestLandingFileUrl(SiteSetting $landingSetting, string $collection): ?string
    {
        $file = $landingSetting->files()
            ->where('collection', $collection)
            ->latest('id')
            ->first();

        return $file ? SiteSetting::publicUrlFromPath($file->path) : null;
    }

    private function shouldUseUploadedImageUrl(mixed $currentUrl, ?string $uploadedUrl): bool
    {
        if (!$uploadedUrl) {
            return false;
        }

        $current = trim((string) ($currentUrl ?? ''));
        if ($current === '') {
            return true;
        }

        return str_contains($current, '/storage/files/sitesetting/')
            || str_starts_with(ltrim($current, '/'), 'storage/files/sitesetting/');
    }

    public function guestContact(Request $request)
    {
        $tenantSlug = TenantContext::get()?->slug;
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            return redirect()->back()->with('error', 'Contact form is only available on tenant websites.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', new LettersOnly()],
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $ticket = Ticket::create([
            'tenant_id' => $tenantId,
            'channel' => 'guest',
            'guest_name' => $request->name,
            'guest_email' => $request->email,
            'subject' => $request->subject,
        ]);

        $ticket->messages()->create([
            'tenant_id' => $ticket->tenant_id,
            'message' => $request->message,
        ]);

        return redirect()->route('tenant.contact', ['subdomain' => $tenantSlug])->with('success', 'Message sent successfully!');
    }

    public function landingContact(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', new LettersOnly()],
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $ticket = Ticket::withoutTenantScope()->create([
            'tenant_id' => null,
            'channel' => 'landing',
            'guest_name' => $request->name,
            'guest_email' => $request->email,
            'subject' => $request->subject,
            'status' => TicketStatus::NEW,
        ]);

        $ticket->messages()->create([
            'tenant_id' => null,
            'message' => $request->message,
            'is_admin' => false,
        ]);

        return back()->with('success', 'Message sent successfully!');
    }
}
