<?php

namespace App\Http\Controllers;

use App\Enums\CarStatus;
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
use Illuminate\Http\Request;

class HomePagesController extends Controller
{
    /**
     * @return array<int, string>
     */
    private function publicFleetStatuses(): array
    {
        return [
            CarStatus::AVAILABLE->value,
            CarStatus::RESERVED->value,
            CarStatus::RENTED->value,
        ];
    }

    public function index()
    {
        if (!TenantContext::get()) {
            $this->recordSaasLandingVisit(request());
            $carSearch = trim((string) request()->string('car_search')->toString());

            $stored = SiteSetting::query()
                ->where('key', LandingPageSettings::KEY)
                ->value('value');

            $landingSettings = LandingPageSettings::normalize(is_array($stored) ? $stored : null);

            $plans = Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get([
                    'id',
                    'name',
                    'description',
                    'features',
                    'monthly_price',
                    'yearly_price',
                    'one_time_price',
                ]);

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
                ->with([
                    'tenant.siteSetting',
                    'branch:id,tenant_id,address',
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

            return inertia('SuperAdmin/landing/Landing', compact('landingSettings', 'plans', 'tenantLogos', 'featuredCars', 'carSearch', 'contactSubmitUrl'));
        }

        $homeCars = Car::whereIn('status', $this->publicFleetStatuses())
            ->with([
                'tenant.siteSetting',
                'branch:id,tenant_id,address',
                'files',
            ])
            ->select('id', 'tenant_id', 'branch_id', 'make', 'model', 'year', 'price_per_day', 'description', 'fuel_type', 'status')
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

        return inertia('Welcome', compact('homeCars'));
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
        $query = Car::withoutTenantScope()->whereIn('status', $this->publicFleetStatuses())
            ->with([
                'tenant.siteSetting',
                'branch:id,tenant_id,name,address',
                'files',
            ])
            ->select('id', 'tenant_id', 'branch_id', 'make', 'model', 'year', 'price_per_day', 'description', 'fuel_type', 'status');

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('make', 'like', "%{$searchTerm}%")
                    ->orWhere('model', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->filled('tenant_id')) {
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
        $makes = Car::whereIn('status', $this->publicFleetStatuses())
            ->distinct()
            ->pluck('make')
            ->toArray();

        $fuelTypes = Car::whereIn('status', $this->publicFleetStatuses())
            ->distinct()
            ->pluck('fuel_type')
            ->toArray();

        $years = Car::whereIn('status', $this->publicFleetStatuses())
            ->distinct()
            ->pluck('year')
            ->toArray();

        $publicTenantIds = Car::withoutTenantScope()
            ->whereIn('status', $this->publicFleetStatuses())
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
            ->whereNotNull('branch_id')
            ->distinct()
            ->pluck('branch_id');

        $branches = Branch::withoutTenantScope()
            ->whereIn('id', $publicBranchIds)
            ->with(['tenant:id,name,slug'])
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'address'])
            ->map(static fn (Branch $branch) => [
                'id' => $branch->id,
                'tenant_id' => $branch->tenant_id,
                'name' => $branch->name,
                'address' => $branch->address,
            ])
            ->values();

        $filters = $request->only(['search', 'tenant_id', 'branch_id', 'make', 'fuel_type', 'min_price', 'max_price', 'year']);

        return inertia('Fleet', compact('cars', 'makes', 'fuelTypes', 'years', 'filters', 'tenants', 'branches'));
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
            'description' => $car->description,
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

        $siteLogo = trim((string) data_get($tenant->siteSetting, 'logo_url', ''));
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

    private function resolveCarLocation(Car $car): string
    {
        $branchLocation = trim((string) ($car->branch?->address ?? ''));
        if ($branchLocation !== '') {
            return $branchLocation;
        }

        $tenantAddress = data_get($car->tenant?->siteSetting?->contact, 'address');
        $locale = (string) app()->getLocale();

        $localizedAddress = trim((string) (
            data_get($tenantAddress, $locale)
            ?: data_get($tenantAddress, 'en')
            ?: data_get($tenantAddress, 'ar')
            ?: ''
        ));

        return $localizedAddress !== '' ? $localizedAddress : 'Location not set';
    }

    public function about()
    {
        return inertia('About');
    }

    public function contact()
    {
        return inertia('Contact');
    }

    public function guestContact(Request $request)
    {
        $tenantSlug = TenantContext::get()?->slug;
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            return redirect()->back()->with('error', 'Contact form is only available on tenant websites.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
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
            'name' => 'required|string|max:255',
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
