<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Core\MrtaPdfSettings;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\AccidentReport;
use App\Models\SiteSetting;
use App\Models\TenantSiteSetting;
use App\Support\BrandLogoImageResizer;
use App\Support\BranchLocationOptions;
use App\Support\CountryOptions;
use App\Support\TenantPdfTemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class WebsiteSettingsController extends Controller
{
    private const WEB_PAGES_CONTENT_KEY = 'main_web_pages_content';
    private const STATIC_PAGES_CONTENT_KEY = 'main_static_pages_content';

    public function __construct(
        private readonly FilePondService $filePondService,
        private readonly BrandLogoImageResizer $brandLogoImageResizer,
    ) {}

    public function edit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        return Inertia::render('Admin/Settings/Website', $this->websitePageProps($tenant));
    }

    public function seoAudit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        return Inertia::render('Admin/Settings/SeoAudit', $this->websitePageProps($tenant));
    }

    public function seoEdit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        return Inertia::render('Admin/Settings/SeoSettings', $this->seoPageProps($tenant));
    }

    public function staticPagesEdit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $settings = TenantSiteSetting::forTenant($tenant);

        return Inertia::render('Admin/Settings/StaticPages', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => [
                'static_pages' => $settings['static_pages'] ?? [],
                'default_static_pages' => $this->defaultStaticPageSettings(),
            ],
            'locales' => $this->staticPageLocaleOptions($settings['enabled_locales'] ?? []),
            'actions' => [
                'update' => route('admin.settings.static-pages.update'),
            ],
        ]);
    }

    public function staticPagesUpdate(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $supportedLocales = $this->supportedLocaleKeys();
        $validated = $request->validate($this->staticPagesValidationRules());

        $tenant->siteSetting()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['static_pages' => $this->staticPagesPayload($validated, $supportedLocales)]
        );

        return back()->with('success', __('Static pages saved successfully.'));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);
        $supportedLocales = $this->supportedLocaleKeys();

        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            // Allow both absolute URLs and local storage paths (e.g. /storage/...).
            'logo_url' => ['nullable', 'string', 'max:1000'],
            'logo_temp_folders' => ['array'],
            'logo_temp_folders.*' => ['string'],
            'logo_removed_files' => ['array'],
            'logo_removed_files.*' => ['integer'],
            'favicon_url' => ['nullable', 'string', 'max:1000'],
            'favicon_temp_folders' => ['array'],
            'favicon_temp_folders.*' => ['string'],
            'favicon_removed_files' => ['array'],
            'favicon_removed_files.*' => ['integer'],
            'primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'secondary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'market_location.country_code' => ['nullable', 'string', 'size:2'],
            'market_location.country_name' => ['nullable', 'string', 'max:120'],
            'market_location.region' => ['nullable', 'string', 'max:120'],
            'market_location.city' => ['nullable', 'string', 'max:120'],
            'market_location.market_area' => ['nullable', 'string', 'max:255'],
            'market_location.timezone' => ['nullable', 'string', 'max:120'],
            'market_location.currency_code' => ['nullable', 'string', 'size:3'],
            'market_location.enabled_currency_codes' => ['nullable', 'array'],
            'market_location.enabled_currency_codes.*' => ['string', 'size:3'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'enabled_locales' => ['nullable', 'array'],
            'enabled_locales.*' => ['string', Rule::in($supportedLocales)],
            'translations' => ['nullable', 'array'],

            'hero.title.en' => ['nullable', 'string', 'max:255'],
            'hero.title.ar' => ['nullable', 'string', 'max:255'],
            'hero.description.en' => ['nullable', 'string', 'max:2000'],
            'hero.description.ar' => ['nullable', 'string', 'max:2000'],
            'hero.button_text.en' => ['nullable', 'string', 'max:100'],
            'hero.button_text.ar' => ['nullable', 'string', 'max:100'],
            'hero.button_link' => ['nullable', 'string', 'max:500'],

            'about.title.en' => ['nullable', 'string', 'max:255'],
            'about.title.ar' => ['nullable', 'string', 'max:255'],
            'about.subtitle.en' => ['nullable', 'string', 'max:2000'],
            'about.subtitle.ar' => ['nullable', 'string', 'max:2000'],
            'about.story_title.en' => ['nullable', 'string', 'max:255'],
            'about.story_title.ar' => ['nullable', 'string', 'max:255'],
            'about.story_p1.en' => ['nullable', 'string', 'max:2000'],
            'about.story_p1.ar' => ['nullable', 'string', 'max:2000'],
            'about.story_p2.en' => ['nullable', 'string', 'max:2000'],
            'about.story_p2.ar' => ['nullable', 'string', 'max:2000'],
            'about.mission_title.en' => ['nullable', 'string', 'max:255'],
            'about.mission_title.ar' => ['nullable', 'string', 'max:255'],
            'about.mission_subtitle.en' => ['nullable', 'string', 'max:2000'],
            'about.mission_subtitle.ar' => ['nullable', 'string', 'max:2000'],
            'about.cta_title.en' => ['nullable', 'string', 'max:255'],
            'about.cta_title.ar' => ['nullable', 'string', 'max:255'],
            'about.cta_subtitle.en' => ['nullable', 'string', 'max:2000'],
            'about.cta_subtitle.ar' => ['nullable', 'string', 'max:2000'],
            'about.cta_browse_text.en' => ['nullable', 'string', 'max:100'],
            'about.cta_browse_text.ar' => ['nullable', 'string', 'max:100'],
            'about.cta_contact_text.en' => ['nullable', 'string', 'max:100'],
            'about.cta_contact_text.ar' => ['nullable', 'string', 'max:100'],

            'contact.phone' => ['nullable', 'string', 'max:100'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.address.en' => ['nullable', 'string', 'max:500'],
            'contact.address.ar' => ['nullable', 'string', 'max:500'],

            'contact_page.title.en' => ['nullable', 'string', 'max:255'],
            'contact_page.title.ar' => ['nullable', 'string', 'max:255'],
            'contact_page.subtitle.en' => ['nullable', 'string', 'max:2000'],
            'contact_page.subtitle.ar' => ['nullable', 'string', 'max:2000'],
            'contact_page.form_title.en' => ['nullable', 'string', 'max:255'],
            'contact_page.form_title.ar' => ['nullable', 'string', 'max:255'],
            'contact_page.info_title.en' => ['nullable', 'string', 'max:255'],
            'contact_page.info_title.ar' => ['nullable', 'string', 'max:255'],
            'contact_page.hours.en' => ['nullable', 'string', 'max:1000'],
            'contact_page.hours.ar' => ['nullable', 'string', 'max:1000'],
            'contact_page.quick_links_title.en' => ['nullable', 'string', 'max:255'],
            'contact_page.quick_links_title.ar' => ['nullable', 'string', 'max:255'],

            'seo.defaults.title_suffix.en' => ['nullable', 'string', 'max:255'],
            'seo.defaults.title_suffix.ar' => ['nullable', 'string', 'max:255'],
            'seo.defaults.default_description.en' => ['nullable', 'string', 'max:500'],
            'seo.defaults.default_description.ar' => ['nullable', 'string', 'max:500'],
            'seo.defaults.og_image' => ['nullable', 'string', 'max:1000'],
            'seo_og_image_temp_folders' => ['array'],
            'seo_og_image_temp_folders.*' => ['string'],
            'seo_og_image_removed_files' => ['array'],
            'seo_og_image_removed_files.*' => ['integer'],
            'seo.defaults.og_image_alt.en' => ['nullable', 'string', 'max:255'],
            'seo.defaults.og_image_alt.ar' => ['nullable', 'string', 'max:255'],
            'seo.defaults.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.home.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.home.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.home.focus_keyword.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.focus_keyword.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.fleet.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.fleet.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.fleet.focus_keyword.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.focus_keyword.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.about.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.about.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.about.focus_keyword.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.focus_keyword.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.contact.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.contact.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.contact.focus_keyword.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.focus_keyword.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.car.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.car.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.car.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.car.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.car.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.car.focus_keyword.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.car.focus_keyword.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_checkout.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_checkout.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_checkout.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_checkout.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_checkout.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.booking_checkout.focus_keyword.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_checkout.focus_keyword.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_confirmation.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_confirmation.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_confirmation.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_confirmation.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_confirmation.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.booking_confirmation.focus_keyword.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_confirmation.focus_keyword.ar' => ['nullable', 'string', 'max:255'],

            'pdf_header.company_name.en' => ['nullable', 'string', 'max:255'],
            'pdf_header.company_name.ar' => ['nullable', 'string', 'max:255'],
            'pdf_header.cr_number' => ['nullable', 'string', 'max:100'],
            'pdf_header.po_box' => ['nullable', 'string', 'max:100'],
            'pdf_header.pc' => ['nullable', 'string', 'max:100'],
            'pdf_header.country.en' => ['nullable', 'string', 'max:255'],
            'pdf_header.country.ar' => ['nullable', 'string', 'max:255'],
            'pdf_header.gsm_1' => ['nullable', 'string', 'max:100'],
            'pdf_header.gsm_2' => ['nullable', 'string', 'max:100'],
            'pdf_header.gsm_3' => ['nullable', 'string', 'max:100'],
            'pdf_header.registry_label.en' => ['nullable', 'string', 'max:100'],
            'pdf_header.registry_label.ar' => ['nullable', 'string', 'max:100'],
            'pdf_templates.contract' => ['nullable', 'string', Rule::in(TenantPdfTemplateRegistry::contractTemplateValues())],

            'footer.description.en' => ['nullable', 'string', 'max:2000'],
            'footer.description.ar' => ['nullable', 'string', 'max:2000'],
        ]);

        $existingSettings = TenantSiteSetting::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        $taxPercentage = array_key_exists('tax_percentage', $validated)
            ? round((float) $validated['tax_percentage'], 2)
            : (float) ($existingSettings?->tax_percentage ?? 7.0);
        $enabledLocales = array_key_exists('enabled_locales', $validated)
            ? $this->sanitizeEnabledLocales($validated['enabled_locales'])
            : $this->sanitizeEnabledLocales($existingSettings?->enabled_locales ?? $supportedLocales);
        $translations = array_key_exists('translations', $validated)
            ? collect($supportedLocales)->mapWithKeys(fn (string $locale) => [
                $locale => $this->sanitizeLocaleOverrides(data_get($validated, "translations.$locale", [])),
            ])->all()
            : collect($supportedLocales)->mapWithKeys(fn (string $locale) => [
                $locale => $this->sanitizeLocaleOverrides(data_get($existingSettings?->translations, $locale, [])),
            ])->all();
        $pdfTemplates = [
            'contract' => (string) (
                data_get($validated, 'pdf_templates.contract')
                ?: data_get($existingSettings?->pdf_templates, 'contract')
                ?: TenantPdfTemplateRegistry::DEFAULT_CONTRACT_TEMPLATE
            ),
        ];
        $baseCurrencyCode = $this->upperNullableString(data_get($validated, 'market_location.currency_code'));
        $enabledCurrencyCodes = $this->sanitizeEnabledCurrencyCodes(
            data_get($validated, 'market_location.enabled_currency_codes', data_get($existingSettings?->market_location, 'enabled_currency_codes', [])),
            $baseCurrencyCode
        );

        $siteSetting = TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'site_name' => $this->nullableString($validated['site_name'] ?? null),
                'logo_url' => $this->nullableString($validated['logo_url'] ?? null),
                'favicon_url' => $this->nullableString($validated['favicon_url'] ?? null),
                'primary_color' => strtolower((string) $validated['primary_color']),
                'secondary_color' => strtolower((string) $validated['secondary_color']),
                'market_location' => [
                    'country_code' => $this->upperNullableString(data_get($validated, 'market_location.country_code')),
                    'country_name' => $this->nullableString(data_get($validated, 'market_location.country_name')),
                    'region' => $this->nullableString(data_get($validated, 'market_location.region')),
                    'city' => $this->nullableString(data_get($validated, 'market_location.city')),
                    'market_area' => $this->nullableString(data_get($validated, 'market_location.market_area')),
                    'timezone' => $this->nullableString(data_get($validated, 'market_location.timezone')),
                    'currency_code' => $baseCurrencyCode,
                    'enabled_currency_codes' => $enabledCurrencyCodes,
                ],
                'tax_percentage' => max(0, min(100, $taxPercentage)),
                'enabled_locales' => $enabledLocales,
                'hero' => [
                    'title' => [
                        'en' => $this->nullableString(data_get($validated, 'hero.title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'hero.title.ar')),
                    ],
                    'description' => [
                        'en' => $this->nullableString(data_get($validated, 'hero.description.en')),
                        'ar' => $this->nullableString(data_get($validated, 'hero.description.ar')),
                    ],
                    'button_text' => [
                        'en' => $this->nullableString(data_get($validated, 'hero.button_text.en')),
                        'ar' => $this->nullableString(data_get($validated, 'hero.button_text.ar')),
                    ],
                    'button_link' => $this->nullableString(data_get($validated, 'hero.button_link')),
                ],
                'about' => [
                    'title' => [
                        'en' => $this->nullableString(data_get($validated, 'about.title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.title.ar')),
                    ],
                    'subtitle' => [
                        'en' => $this->nullableString(data_get($validated, 'about.subtitle.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.subtitle.ar')),
                    ],
                    'story_title' => [
                        'en' => $this->nullableString(data_get($validated, 'about.story_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.story_title.ar')),
                    ],
                    'story_p1' => [
                        'en' => $this->nullableString(data_get($validated, 'about.story_p1.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.story_p1.ar')),
                    ],
                    'story_p2' => [
                        'en' => $this->nullableString(data_get($validated, 'about.story_p2.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.story_p2.ar')),
                    ],
                    'mission_title' => [
                        'en' => $this->nullableString(data_get($validated, 'about.mission_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.mission_title.ar')),
                    ],
                    'mission_subtitle' => [
                        'en' => $this->nullableString(data_get($validated, 'about.mission_subtitle.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.mission_subtitle.ar')),
                    ],
                    'cta_title' => [
                        'en' => $this->nullableString(data_get($validated, 'about.cta_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.cta_title.ar')),
                    ],
                    'cta_subtitle' => [
                        'en' => $this->nullableString(data_get($validated, 'about.cta_subtitle.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.cta_subtitle.ar')),
                    ],
                    'cta_browse_text' => [
                        'en' => $this->nullableString(data_get($validated, 'about.cta_browse_text.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.cta_browse_text.ar')),
                    ],
                    'cta_contact_text' => [
                        'en' => $this->nullableString(data_get($validated, 'about.cta_contact_text.en')),
                        'ar' => $this->nullableString(data_get($validated, 'about.cta_contact_text.ar')),
                    ],
                ],
                'contact' => [
                    'phone' => $this->nullableString(data_get($validated, 'contact.phone')),
                    'email' => $this->nullableString(data_get($validated, 'contact.email')),
                    'address' => [
                        'en' => $this->nullableString(data_get($validated, 'contact.address.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact.address.ar')),
                    ],
                ],
                'contact_page' => [
                    'title' => [
                        'en' => $this->nullableString(data_get($validated, 'contact_page.title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact_page.title.ar')),
                    ],
                    'subtitle' => [
                        'en' => $this->nullableString(data_get($validated, 'contact_page.subtitle.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact_page.subtitle.ar')),
                    ],
                    'form_title' => [
                        'en' => $this->nullableString(data_get($validated, 'contact_page.form_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact_page.form_title.ar')),
                    ],
                    'info_title' => [
                        'en' => $this->nullableString(data_get($validated, 'contact_page.info_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact_page.info_title.ar')),
                    ],
                    'hours' => [
                        'en' => $this->nullableString(data_get($validated, 'contact_page.hours.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact_page.hours.ar')),
                    ],
                    'quick_links_title' => [
                        'en' => $this->nullableString(data_get($validated, 'contact_page.quick_links_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contact_page.quick_links_title.ar')),
                    ],
                ],
                'seo' => [
                    'defaults' => [
                        'title_suffix' => [
                            'en' => $this->nullableString(data_get($validated, 'seo.defaults.title_suffix.en')),
                            'ar' => $this->nullableString(data_get($validated, 'seo.defaults.title_suffix.ar')),
                        ],
                        'default_description' => [
                            'en' => $this->nullableString(data_get($validated, 'seo.defaults.default_description.en')),
                            'ar' => $this->nullableString(data_get($validated, 'seo.defaults.default_description.ar')),
                        ],
                        'og_image' => $this->nullableString(data_get($validated, 'seo.defaults.og_image')),
                        'og_image_alt' => [
                            'en' => $this->nullableString(data_get($validated, 'seo.defaults.og_image_alt.en')),
                            'ar' => $this->nullableString(data_get($validated, 'seo.defaults.og_image_alt.ar')),
                        ],
                        'robots' => $this->nullableString(data_get($validated, 'seo.defaults.robots')) ?: 'index,follow',
                    ],
                    'pages' => [
                        'home' => [
                            'title' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.home.title.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.home.title.ar')),
                            ],
                            'description' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.home.description.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.home.description.ar')),
                            ],
                            'canonical_url' => $this->nullableString(data_get($validated, 'seo.pages.home.canonical_url')),
                        ],
                        'fleet' => [
                            'title' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.fleet.title.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.fleet.title.ar')),
                            ],
                            'description' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.fleet.description.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.fleet.description.ar')),
                            ],
                            'canonical_url' => $this->nullableString(data_get($validated, 'seo.pages.fleet.canonical_url')),
                        ],
                        'about' => [
                            'title' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.about.title.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.about.title.ar')),
                            ],
                            'description' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.about.description.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.about.description.ar')),
                            ],
                            'canonical_url' => $this->nullableString(data_get($validated, 'seo.pages.about.canonical_url')),
                        ],
                        'contact' => [
                            'title' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.contact.title.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.contact.title.ar')),
                            ],
                            'description' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.contact.description.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.contact.description.ar')),
                            ],
                            'canonical_url' => $this->nullableString(data_get($validated, 'seo.pages.contact.canonical_url')),
                        ],
                        'car' => [
                            'title' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.car.title.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.car.title.ar')),
                            ],
                            'description' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.car.description.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.car.description.ar')),
                            ],
                            'canonical_url' => $this->nullableString(data_get($validated, 'seo.pages.car.canonical_url')),
                        ],
                        'booking_checkout' => [
                            'title' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.title.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.title.ar')),
                            ],
                            'description' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.description.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.description.ar')),
                            ],
                            'canonical_url' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.canonical_url')),
                        ],
                        'booking_confirmation' => [
                            'title' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.title.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.title.ar')),
                            ],
                            'description' => [
                                'en' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.description.en')),
                                'ar' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.description.ar')),
                            ],
                            'canonical_url' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.canonical_url')),
                        ],
                    ],
                ],
                'pdf_header' => [
                    'company_name' => [
                        'en' => $this->nullableString(data_get($validated, 'pdf_header.company_name.en')),
                        'ar' => $this->nullableString(data_get($validated, 'pdf_header.company_name.ar')),
                    ],
                    'cr_number' => $this->nullableString(data_get($validated, 'pdf_header.cr_number')),
                    'po_box' => $this->nullableString(data_get($validated, 'pdf_header.po_box')),
                    'pc' => $this->nullableString(data_get($validated, 'pdf_header.pc')),
                    'country' => [
                        'en' => $this->nullableString(data_get($validated, 'pdf_header.country.en')),
                        'ar' => $this->nullableString(data_get($validated, 'pdf_header.country.ar')),
                    ],
                    'gsm_1' => $this->nullableString(data_get($validated, 'pdf_header.gsm_1')),
                    'gsm_2' => $this->nullableString(data_get($validated, 'pdf_header.gsm_2')),
                    'gsm_3' => $this->nullableString(data_get($validated, 'pdf_header.gsm_3')),
                    'registry_label' => [
                        'en' => $this->nullableString(data_get($validated, 'pdf_header.registry_label.en')),
                        'ar' => $this->nullableString(data_get($validated, 'pdf_header.registry_label.ar')),
                    ],
                ],
                'pdf_templates' => $pdfTemplates,
                'translations' => $translations,
                'footer' => [
                    'description' => [
                        'en' => $this->nullableString(data_get($validated, 'footer.description.en')),
                        'ar' => $this->nullableString(data_get($validated, 'footer.description.ar')),
                    ],
                ],
            ]
        );

        $tempFolders = $request->input('logo_temp_folders', []);
        $removedIds = $request->input('logo_removed_files', []);

        if (!empty($tempFolders)) {
            $existingIds = $siteSetting->files()->where('collection', 'logo')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
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

        $faviconTempFolders = $request->input('favicon_temp_folders', []);
        $faviconRemovedIds = $request->input('favicon_removed_files', []);

        if (!empty($faviconTempFolders)) {
            $existingFaviconIds = $siteSetting->files()->where('collection', 'favicon')->pluck('id')->all();
            $faviconRemovedIds = array_values(array_unique(array_merge($faviconRemovedIds, $existingFaviconIds)));
        }

        $this->filePondService->handleFileUpdates(
            $siteSetting,
            is_array($faviconTempFolders) ? $faviconTempFolders : [],
            is_array($faviconRemovedIds) ? $faviconRemovedIds : [],
            'favicon'
        );

        $this->syncSeoOgImageUpload($request, $siteSetting);

        return back()->with('success', 'Website settings updated successfully.');
    }

    public function seoUpdate(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $validated = $request->validate($this->seoValidationRules());
        $this->validateSeoRedirectRules($validated);

        TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'seo' => $this->buildSeoPayload($validated),
            ]
        );

        $siteSetting = TenantSiteSetting::query()
            ->where('tenant_id', $tenant->id)
            ->with('files')
            ->first();

        if ($siteSetting) {
            $this->syncSeoOgImageUpload($request, $siteSetting);
        }

        return back()->with('success', 'SEO settings updated successfully.');
    }

    public function policeNoticeEdit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenant->loadMissing('siteSetting.files');

        return Inertia::render('Admin/Settings/PoliceNotice', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'actions' => [
                'update' => url()->current(),
            ],
        ]);
    }

    public function contractPdfEdit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenant->loadMissing('siteSetting.files');
        $previewContractId = Contract::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->value('id');

        return Inertia::render('Admin/Settings/ContractPdf', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'contractPdfDefaults' => $this->contractPdfDefaults(),
            'contractSignatureFiles' => $this->contractSignatureFiles($tenant->siteSetting),
            'previewUrl' => $previewContractId ? route('admin.contracts.pdf', ['contract' => $previewContractId, 'lang' => app()->getLocale()]) : null,
            'actions' => [
                'update' => url()->current(),
            ],
        ]);
    }

    public function mrtaPdfEdit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenant->loadMissing('siteSetting.files');
        $previewAccidentReportId = AccidentReport::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->value('id');
        $mrtaLogoFiles = $this->mrtaLogoFiles($tenant->siteSetting);

        return Inertia::render('Admin/Settings/MrtaPdf', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'mrtaPdfDefaults' => MrtaPdfSettings::defaults(),
            'mrtaLogoFiles' => $mrtaLogoFiles,
            'previewUrl' => $previewAccidentReportId ? route('admin.accident-reports.mrta-form', ['accidentReport' => $previewAccidentReportId]) : null,
            'actions' => [
                'update' => url()->current(),
            ],
        ]);
    }

    public function policeNoticeUpdate(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'pdf_header.company_name.en' => ['nullable', 'string', 'max:255'],
            'pdf_header.company_name.ar' => ['nullable', 'string', 'max:255'],
            'pdf_header.cr_number' => ['nullable', 'string', 'max:100'],
            'pdf_header.po_box' => ['nullable', 'string', 'max:100'],
            'pdf_header.pc' => ['nullable', 'string', 'max:100'],
            'pdf_header.country.en' => ['nullable', 'string', 'max:255'],
            'pdf_header.country.ar' => ['nullable', 'string', 'max:255'],
            'pdf_header.gsm_1' => ['nullable', 'string', 'max:100'],
            'pdf_header.gsm_2' => ['nullable', 'string', 'max:100'],
            'pdf_header.gsm_3' => ['nullable', 'string', 'max:100'],
            'pdf_header.registry_label.en' => ['nullable', 'string', 'max:100'],
            'pdf_header.registry_label.ar' => ['nullable', 'string', 'max:100'],

            'police_notice.company_name.en' => ['nullable', 'string', 'max:255'],
            'police_notice.company_name.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.registry_label.en' => ['nullable', 'string', 'max:100'],
            'police_notice.registry_label.ar' => ['nullable', 'string', 'max:100'],
            'police_notice.subject.en' => ['nullable', 'string', 'max:255'],
            'police_notice.subject.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.greeting.en' => ['nullable', 'string', 'max:255'],
            'police_notice.greeting.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.intro.en' => ['nullable', 'string', 'max:4000'],
            'police_notice.intro.ar' => ['nullable', 'string', 'max:4000'],
            'police_notice.office_line.en' => ['nullable', 'string', 'max:255'],
            'police_notice.office_line.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.company_address.en' => ['nullable', 'string', 'max:500'],
            'police_notice.company_address.ar' => ['nullable', 'string', 'max:500'],
            'police_notice.company_phone.en' => ['nullable', 'string', 'max:100'],
            'police_notice.company_phone.ar' => ['nullable', 'string', 'max:100'],
            'police_notice.vehicle_section_title.en' => ['nullable', 'string', 'max:255'],
            'police_notice.vehicle_section_title.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.renter_section_title.en' => ['nullable', 'string', 'max:255'],
            'police_notice.renter_section_title.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.closing_1.en' => ['nullable', 'string', 'max:4000'],
            'police_notice.closing_1.ar' => ['nullable', 'string', 'max:4000'],
            'police_notice.closing_2.en' => ['nullable', 'string', 'max:4000'],
            'police_notice.closing_2.ar' => ['nullable', 'string', 'max:4000'],
            'police_notice.attachments_title.en' => ['nullable', 'string', 'max:255'],
            'police_notice.attachments_title.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.attachments.en' => ['nullable', 'string', 'max:4000'],
            'police_notice.attachments.ar' => ['nullable', 'string', 'max:4000'],
            'police_notice.signature_name_label.en' => ['nullable', 'string', 'max:255'],
            'police_notice.signature_name_label.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.signature_title_label.en' => ['nullable', 'string', 'max:255'],
            'police_notice.signature_title_label.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.signature_date_label.en' => ['nullable', 'string', 'max:255'],
            'police_notice.signature_date_label.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.footer_note.en' => ['nullable', 'string', 'max:1000'],
            'police_notice.footer_note.ar' => ['nullable', 'string', 'max:1000'],
        ]);

        TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'pdf_header' => [
                    'company_name' => [
                        'en' => $this->nullableString(data_get($validated, 'pdf_header.company_name.en')),
                        'ar' => $this->nullableString(data_get($validated, 'pdf_header.company_name.ar')),
                    ],
                    'cr_number' => $this->nullableString(data_get($validated, 'pdf_header.cr_number')),
                    'po_box' => $this->nullableString(data_get($validated, 'pdf_header.po_box')),
                    'pc' => $this->nullableString(data_get($validated, 'pdf_header.pc')),
                    'country' => [
                        'en' => $this->nullableString(data_get($validated, 'pdf_header.country.en')),
                        'ar' => $this->nullableString(data_get($validated, 'pdf_header.country.ar')),
                    ],
                    'gsm_1' => $this->nullableString(data_get($validated, 'pdf_header.gsm_1')),
                    'gsm_2' => $this->nullableString(data_get($validated, 'pdf_header.gsm_2')),
                    'gsm_3' => $this->nullableString(data_get($validated, 'pdf_header.gsm_3')),
                    'registry_label' => [
                        'en' => $this->nullableString(data_get($validated, 'pdf_header.registry_label.en')),
                        'ar' => $this->nullableString(data_get($validated, 'pdf_header.registry_label.ar')),
                    ],
                ],
                'police_notice' => [
                    'company_name' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.company_name.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.company_name.ar')),
                    ],
                    'registry_label' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.registry_label.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.registry_label.ar')),
                    ],
                    'subject' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.subject.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.subject.ar')),
                    ],
                    'greeting' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.greeting.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.greeting.ar')),
                    ],
                    'intro' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.intro.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.intro.ar')),
                    ],
                    'office_line' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.office_line.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.office_line.ar')),
                    ],
                    'company_address' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.company_address.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.company_address.ar')),
                    ],
                    'company_phone' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.company_phone.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.company_phone.ar')),
                    ],
                    'vehicle_section_title' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.vehicle_section_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.vehicle_section_title.ar')),
                    ],
                    'renter_section_title' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.renter_section_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.renter_section_title.ar')),
                    ],
                    'closing_1' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.closing_1.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.closing_1.ar')),
                    ],
                    'closing_2' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.closing_2.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.closing_2.ar')),
                    ],
                    'attachments_title' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.attachments_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.attachments_title.ar')),
                    ],
                    'attachments' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.attachments.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.attachments.ar')),
                    ],
                    'signature_name_label' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.signature_name_label.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.signature_name_label.ar')),
                    ],
                    'signature_title_label' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.signature_title_label.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.signature_title_label.ar')),
                    ],
                    'signature_date_label' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.signature_date_label.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.signature_date_label.ar')),
                    ],
                    'footer_note' => [
                        'en' => $this->nullableString(data_get($validated, 'police_notice.footer_note.en')),
                        'ar' => $this->nullableString(data_get($validated, 'police_notice.footer_note.ar')),
                    ],
                ],
            ]
        );

        return back()->with('success', 'Police notice settings updated successfully.');
    }

    public function contractPdfUpdate(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'contract_pdf.mileage_notice.en' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.mileage_notice.ar' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.rental_period_notice.en' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.rental_period_notice.ar' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.smoking_notice.en' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.smoking_notice.ar' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.unclean_notice.en' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.unclean_notice.ar' => ['nullable', 'string', 'max:1000'],
            'contract_pdf.delay_notice.en' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.delay_notice.ar' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.period_change_notice.en' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.period_change_notice.ar' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.accident_notice.en' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.accident_notice.ar' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.acknowledgement_title.en' => ['nullable', 'string', 'max:255'],
            'contract_pdf.acknowledgement_title.ar' => ['nullable', 'string', 'max:255'],
            'contract_pdf.acknowledgement_body.en' => ['nullable', 'string', 'max:4000'],
            'contract_pdf.acknowledgement_body.ar' => ['nullable', 'string', 'max:4000'],
            'contract_pdf.mobile_signature_text' => ['nullable', 'string', 'max:4000'],
            'contract_pdf.important_notice.en' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.important_notice.ar' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.closing_notice.en' => ['nullable', 'string', 'max:1500'],
            'contract_pdf.closing_notice.ar' => ['nullable', 'string', 'max:1500'],
            'contract_incharge_signature_temp_folders' => ['array'],
            'contract_incharge_signature_temp_folders.*' => ['string'],
            'contract_incharge_signature_removed_files' => ['array'],
            'contract_incharge_signature_removed_files.*' => ['integer'],
        ]);

        $siteSetting = TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'contract_pdf' => [
                    'mileage_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.mileage_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.mileage_notice.ar')),
                    ],
                    'rental_period_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.rental_period_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.rental_period_notice.ar')),
                    ],
                    'smoking_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.smoking_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.smoking_notice.ar')),
                    ],
                    'unclean_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.unclean_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.unclean_notice.ar')),
                    ],
                    'delay_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.delay_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.delay_notice.ar')),
                    ],
                    'period_change_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.period_change_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.period_change_notice.ar')),
                    ],
                    'accident_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.accident_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.accident_notice.ar')),
                    ],
                    'acknowledgement_title' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.acknowledgement_title.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.acknowledgement_title.ar')),
                    ],
                    'acknowledgement_body' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.acknowledgement_body.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.acknowledgement_body.ar')),
                    ],
                    'mobile_signature_text' => $this->nullableString(data_get($validated, 'contract_pdf.mobile_signature_text')),
                    'important_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.important_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.important_notice.ar')),
                    ],
                    'closing_notice' => [
                        'en' => $this->nullableString(data_get($validated, 'contract_pdf.closing_notice.en')),
                        'ar' => $this->nullableString(data_get($validated, 'contract_pdf.closing_notice.ar')),
                    ],
                ],
            ]
        );

        $this->syncSingleFileUpload($request, $siteSetting, 'contract_incharge_signature');

        return back()->with('success', 'Contract PDF text settings updated successfully.');
    }

    public function mrtaPdfUpdate(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'mrta_pdf.primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'mrta_pdf.liva_logo_text' => ['nullable', 'string', 'max:100'],
            'mrta_pdf.liva_logo_ar' => ['nullable', 'string', 'max:100'],
            'mrta_pdf.liva_contact_email' => ['nullable', 'string', 'max:255'],
            'mrta_pdf.liva_contact_website' => ['nullable', 'string', 'max:255'],
            'mrta_pdf.insurance_section_title_en' => ['nullable', 'string', 'max:255'],
            'mrta_pdf.insurance_section_title_ar' => ['nullable', 'string', 'max:255'],
            'mrta_pdf.footer_ar' => ['nullable', 'string', 'max:1000'],
            'mrta_pdf.footer_en' => ['nullable', 'string', 'max:1000'],
            'mrta_oman_logo_temp_folders' => ['array'],
            'mrta_oman_logo_temp_folders.*' => ['string'],
            'mrta_oman_logo_removed_files' => ['array'],
            'mrta_oman_logo_removed_files.*' => ['integer'],
            'mrta_rop_logo_temp_folders' => ['array'],
            'mrta_rop_logo_temp_folders.*' => ['string'],
            'mrta_rop_logo_removed_files' => ['array'],
            'mrta_rop_logo_removed_files.*' => ['integer'],
            'mrta_liva_logo_temp_folders' => ['array'],
            'mrta_liva_logo_temp_folders.*' => ['string'],
            'mrta_liva_logo_removed_files' => ['array'],
            'mrta_liva_logo_removed_files.*' => ['integer'],
        ]);

        $siteSetting = TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'mrta_pdf' => MrtaPdfSettings::normalize($validated['mrta_pdf'] ?? []),
            ]
        );

        $this->syncSingleFileUpload($request, $siteSetting, 'mrta_oman_logo');
        $this->syncSingleFileUpload($request, $siteSetting, 'mrta_rop_logo');
        $this->syncSingleFileUpload($request, $siteSetting, 'mrta_liva_logo');

        return back()->with('success', 'MRTA PDF settings updated successfully.');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string>  $supportedLocales
     */
    private function staticPagesValidationRules(): array
    {
        return [
            'static_pages' => ['nullable', 'array'],
            'static_pages.privacy_policy.title' => ['nullable', 'array'],
            'static_pages.privacy_policy.title.*' => ['nullable', 'string', 'max:255'],
            'static_pages.privacy_policy.content' => ['nullable', 'array'],
            'static_pages.privacy_policy.content.*' => ['nullable', 'string', 'max:50000'],
            'static_pages.terms_of_use.title' => ['nullable', 'array'],
            'static_pages.terms_of_use.title.*' => ['nullable', 'string', 'max:255'],
            'static_pages.terms_of_use.content' => ['nullable', 'array'],
            'static_pages.terms_of_use.content.*' => ['nullable', 'string', 'max:50000'],
            'static_pages.security_policy.title' => ['nullable', 'array'],
            'static_pages.security_policy.title.*' => ['nullable', 'string', 'max:255'],
            'static_pages.security_policy.content' => ['nullable', 'array'],
            'static_pages.security_policy.content.*' => ['nullable', 'string', 'max:50000'],
        ];
    }

    private function defaultStaticPageSettings(): array
    {
        $webPageContent = SiteSetting::query()
            ->where('key', self::WEB_PAGES_CONTENT_KEY)
            ->value('value');
        $staticPageContent = SiteSetting::query()
            ->where('key', self::STATIC_PAGES_CONTENT_KEY)
            ->value('value');

        $webPageContent = is_array($webPageContent) ? $webPageContent : [];
        $staticPageContent = is_array($staticPageContent) ? $staticPageContent : [];
        $tenantPages = data_get($webPageContent, 'tenant_pages');

        return [
            'privacy_policy' => $this->mergeStaticPageDefaultContent(
                data_get($tenantPages, 'privacy_policy'),
                data_get($webPageContent, 'privacy_policy'),
                data_get($staticPageContent, 'privacy_policy')
            ),
            'terms_of_use' => $this->mergeStaticPageDefaultContent(
                data_get($tenantPages, 'terms_of_use'),
                data_get($webPageContent, 'terms_of_use'),
                data_get($staticPageContent, 'terms_conditions')
            ),
            'security_policy' => $this->mergeStaticPageDefaultContent(
                data_get($tenantPages, 'security_policy'),
                data_get($webPageContent, 'security_policy'),
                data_get($staticPageContent, 'security_policy')
            ),
        ];
    }

    private function mergeStaticPageDefaultContent(mixed ...$sources): array
    {
        $supportedLocales = $this->supportedLocaleKeys();
        $content = [];

        foreach ($supportedLocales as $locale) {
            $value = '';
            foreach ($sources as $source) {
                if (is_array($source)) {
                    $val = trim((string) ($source[$locale] ?? ''));
                    if ($val !== '') {
                        $value = $val;
                        break;
                    }
                } elseif (is_string($source) && $locale === 'en') {
                    $val = trim($source);
                    if ($val !== '') {
                        $value = $val;
                        break;
                    }
                }
            }
            $content[$locale] = $value;
        }

        return $content;
    }

    /**
     * @param  list<string>  $enabledLocales
     */
    private function staticPageLocaleOptions(array $enabledLocales): array
    {
        $enabledLocales = empty($enabledLocales) ? $this->supportedLocaleKeys() : $enabledLocales;
        $configuredLocales = (array) config('laravellocalization.supportedLocales', []);

        return collect($enabledLocales)
            ->map(fn ($locale) => trim((string) $locale))
            ->filter()
            ->unique()
            ->map(function (string $locale) use ($configuredLocales): array {
                $config = (array) ($configuredLocales[$locale] ?? []);

                return [
                    'code' => $locale,
                    'name' => (string) ($config['name'] ?? strtoupper($locale)),
                    'native' => (string) ($config['native'] ?? $config['name'] ?? strtoupper($locale)),
                    'direction' => in_array($locale, ['ar', 'ur', 'fa'], true) ? 'rtl' : 'ltr',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $supportedLocales
     */
    private function staticPagesPayload(array $validated, array $supportedLocales): array
    {
        $pages = ['privacy_policy', 'terms_of_use', 'security_policy'];
        $payload = [];

        foreach ($pages as $page) {
            $payload[$page] = [
                'title' => $this->localizedTextPayload($validated, "static_pages.{$page}.title", $supportedLocales),
                'content' => $this->localizedHtmlPayload($validated, "static_pages.{$page}.content", $supportedLocales),
            ];
        }

        return $payload;
    }

    /**
     * @param  list<string>  $supportedLocales
     * @return array<string, string|null>
     */
    private function localizedTextPayload(array $validated, string $prefix, array $supportedLocales): array
    {
        $values = [];

        foreach ($supportedLocales as $locale) {
            $values[$locale] = $this->nullableString(data_get($validated, "{$prefix}.{$locale}"));
        }

        return $values;
    }

    /**
     * @param  list<string>  $supportedLocales
     * @return array<string, string|null>
     */
    private function localizedHtmlPayload(array $validated, string $prefix, array $supportedLocales): array
    {
        $values = [];

        foreach ($supportedLocales as $locale) {
            $values[$locale] = $this->sanitizeStaticPageHtml((string) data_get($validated, "{$prefix}.{$locale}", ''));
        }

        return $values;
    }

    private function sanitizeStaticPageHtml(string $content): ?string
    {
        $content = strip_tags($content, '<p><br><strong><b><em><i><u><s><blockquote><hr><h2><h3><h4><ul><ol><li><a>');
        $content = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $content) ?? $content;
        $content = preg_replace('/href\s*=\s*(["\'])\s*javascript:.*?\1/i', 'href="#"', $content) ?? $content;
        $content = trim($content);

        return $content === '' ? null : $content;
    }

    private function upperNullableString(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value === null ? null : strtoupper($value);
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer >= 1 ? $integer : null;
    }

    private function sanitizeEnabledLocales(mixed $value): array
    {
        $supported = $this->supportedLocaleKeys();
        $enabled = is_array($value) ? $value : [];
        $enabled = array_values(array_unique(array_intersect($supported, array_map('strval', $enabled))));

        return empty($enabled) ? $supported : $enabled;
    }

    /**
     * @return list<string>
     */
    private function sanitizeEnabledCurrencyCodes(mixed $value, ?string $baseCurrencyCode = null): array
    {
        $codes = collect(is_array($value) ? $value : [])
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->filter(fn (string $code): bool => preg_match('/^[A-Z]{3}$/', $code) === 1)
            ->values();

        if ($baseCurrencyCode !== null && preg_match('/^[A-Z]{3}$/', $baseCurrencyCode) === 1) {
            $codes->push($baseCurrencyCode);
        }

        return $codes
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeLocaleOverrides(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $key => $node) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $normalized = $this->sanitizeTranslationNode($node);
            if ($normalized === null) {
                continue;
            }

            $sanitized[$key] = $normalized;
        }

        return $sanitized;
    }

    private function sanitizeTranslationNode(mixed $value): array|string|null
    {
        if (is_array($value)) {
            $output = [];
            foreach ($value as $key => $node) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }

                $normalized = $this->sanitizeTranslationNode($node);
                if ($normalized === null) {
                    continue;
                }

                $output[$key] = $normalized;
            }

            return empty($output) ? null : $output;
        }

        if (is_scalar($value)) {
            $str = trim((string) $value);
            return $str === '' ? null : $str;
        }

        return null;
    }

    private function supportedLocaleKeys(): array
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));
        if (empty($supported)) {
            $supported = array_values((array) config('app.available_locales', ['en']));
        }

        $supported = array_values(array_unique(array_map('strval', $supported)));

        return empty($supported) ? ['en'] : $supported;
    }

    private function seoValidationRules(): array
    {
        $supportedLocales = $this->supportedLocaleKeys();
        $rules = [
            'seo.defaults.og_image' => ['nullable', 'string', 'max:1000'],
            'seo_og_image_temp_folders' => ['array'],
            'seo_og_image_temp_folders.*' => ['string'],
            'seo_og_image_removed_files' => ['array'],
            'seo_og_image_removed_files.*' => ['integer'],
            'seo.defaults.robots' => ['nullable', 'string', 'max:255'],
            'seo.technical.sitemap.pages' => ['nullable', 'array'],
            'seo.technical.sitemap.pages.*.path' => ['nullable', 'string', 'max:500'],
            'seo.technical.sitemap.pages.*.priority' => ['nullable', 'numeric', 'min:0.1', 'max:1.0'],
            'seo.technical.sitemap.pages.*.changeFreq' => ['nullable', Rule::in(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])],
            'seo.technical.sitemap.pages.*.lastmod' => ['nullable', 'date'],
            'seo.technical.robots.allowAll' => ['nullable', 'boolean'],
            'seo.technical.robots.disallowPaths' => ['nullable', 'array'],
            'seo.technical.robots.disallowPaths.*' => ['nullable', 'string', 'max:500'],
            'seo.technical.robots.crawlDelay' => ['nullable', 'integer', 'min:0', 'max:60'],
            'seo.technical.robots.requestRate' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'seo.technical.robots.sitemapUrl' => ['nullable', 'string', 'max:500'],
            'seo.technical.redirects.items' => ['nullable', 'array'],
            'seo.technical.redirects.items.*.id' => ['nullable', 'string', 'max:100'],
            'seo.technical.redirects.items.*.fromPath' => ['required_with:seo.technical.redirects.items', 'string', 'max:500'],
            'seo.technical.redirects.items.*.toPath' => ['required_with:seo.technical.redirects.items', 'string', 'max:500'],
            'seo.technical.redirects.items.*.statusCode' => ['required_with:seo.technical.redirects.items', Rule::in([301, 302, 307, 308])],
            'seo.technical.redirects.items.*.isPermanent' => ['nullable', 'boolean'],
            'seo.technical.redirects.items.*.isActive' => ['nullable', 'boolean'],
        ];

        foreach ($supportedLocales as $locale) {
            $rules["seo.defaults.title_suffix.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["seo.defaults.default_description.{$locale}"] = ['nullable', 'string', 'max:500'];
            $rules["seo.defaults.og_image_alt.{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        foreach ($this->seoPageKeys() as $pageKey) {
            $rules["seo.pages.{$pageKey}.canonical_url"] = ['nullable', 'string', 'max:1000'];
            $rules["seo.pages.{$pageKey}.robots"] = ['nullable', 'string', 'max:255'];

            foreach ($supportedLocales as $locale) {
                $rules["seo.pages.{$pageKey}.title.{$locale}"] = ['nullable', 'string', 'max:255'];
                $rules["seo.pages.{$pageKey}.description.{$locale}"] = ['nullable', 'string', 'max:500'];
                $rules["seo.pages.{$pageKey}.focus_keyword.{$locale}"] = ['nullable', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    private function buildSeoPayload(array $validated): array
    {
        $supportedLocales = $this->supportedLocaleKeys();
        $pages = [];

        foreach ($this->seoPageKeys() as $pageKey) {
            $pages[$pageKey] = [
                'title' => $this->localizedSeoPayload($validated, "seo.pages.{$pageKey}.title", $supportedLocales),
                'description' => $this->localizedSeoPayload($validated, "seo.pages.{$pageKey}.description", $supportedLocales),
                'canonical_url' => $this->nullableString(data_get($validated, "seo.pages.{$pageKey}.canonical_url")),
                'robots' => $this->nullableString(data_get($validated, "seo.pages.{$pageKey}.robots")),
                'focus_keyword' => $this->localizedSeoPayload($validated, "seo.pages.{$pageKey}.focus_keyword", $supportedLocales),
            ];
        }

        return [
            'defaults' => [
                'title_suffix' => $this->localizedSeoPayload($validated, 'seo.defaults.title_suffix', $supportedLocales),
                'default_description' => $this->localizedSeoPayload($validated, 'seo.defaults.default_description', $supportedLocales),
                'og_image' => $this->nullableString(data_get($validated, 'seo.defaults.og_image')),
                'og_image_alt' => $this->localizedSeoPayload($validated, 'seo.defaults.og_image_alt', $supportedLocales),
                'robots' => $this->nullableString(data_get($validated, 'seo.defaults.robots')) ?: 'index,follow',
            ],
            'pages' => $pages,
            'technical' => [
                'sitemap' => [
                    'pages' => collect((array) data_get($validated, 'seo.technical.sitemap.pages', []))
                        ->map(function ($page) {
                            $path = $this->nullableString(data_get($page, 'path'));
                            if ($path === null) {
                                return null;
                            }

                            $priority = data_get($page, 'priority');
                            $priority = is_numeric($priority) ? round((float) $priority, 1) : 0.5;

                            return [
                                'path' => str_starts_with($path, '/') ? $path : '/'.$path,
                                'priority' => max(0.1, min(1.0, $priority)),
                                'changeFreq' => (string) (data_get($page, 'changeFreq') ?: 'weekly'),
                                'lastmod' => $this->nullableString(data_get($page, 'lastmod')),
                            ];
                        })
                        ->filter()
                        ->values()
                        ->all(),
                ],
                'robots' => [
                    'allowAll' => (bool) data_get($validated, 'seo.technical.robots.allowAll', true),
                    'disallowPaths' => collect((array) data_get($validated, 'seo.technical.robots.disallowPaths', []))
                        ->map(fn ($path) => $this->nullableString($path))
                        ->filter()
                        ->values()
                        ->all(),
                    'crawlDelay' => (int) data_get($validated, 'seo.technical.robots.crawlDelay', 1),
                    'requestRate' => (int) data_get($validated, 'seo.technical.robots.requestRate', 30),
                    'sitemapUrl' => (string) (data_get($validated, 'seo.technical.robots.sitemapUrl') ?: '/sitemap.xml'),
                ],
                'redirects' => [
                    'items' => collect((array) data_get($validated, 'seo.technical.redirects.items', []))
                        ->map(function ($item) {
                            $fromPath = $this->nullableString(data_get($item, 'fromPath'));
                            $toPath = $this->nullableString(data_get($item, 'toPath'));
                            if ($fromPath === null || $toPath === null) {
                                return null;
                            }

                            $statusCode = (int) data_get($item, 'statusCode', 301);

                            return [
                                'id' => (string) (data_get($item, 'id') ?: uniqid('redirect_', true)),
                                'fromPath' => str_starts_with($fromPath, '/') ? $fromPath : '/'.$fromPath,
                                'toPath' => str_starts_with($toPath, '/') ? $toPath : '/'.$toPath,
                                'statusCode' => in_array($statusCode, [301, 302, 307, 308], true) ? $statusCode : 301,
                                'isPermanent' => (bool) data_get($item, 'isPermanent', in_array($statusCode, [301, 308], true)),
                                'isActive' => (bool) data_get($item, 'isActive', true),
                            ];
                        })
                        ->filter()
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function seoPageKeys(): array
    {
        return ['home', 'fleet', 'about', 'contact', 'car', 'booking_checkout', 'booking_confirmation'];
    }

    /**
     * @param  list<string>  $supportedLocales
     * @return array<string, string|null>
     */
    private function localizedSeoPayload(array $validated, string $prefix, array $supportedLocales): array
    {
        $values = [];

        foreach ($supportedLocales as $locale) {
            $values[$locale] = $this->nullableString(data_get($validated, "{$prefix}.{$locale}"));
        }

        return $values;
    }

    private function validateSeoRedirectRules(array $validated): void
    {
        $items = collect((array) data_get($validated, 'seo.technical.redirects.items', []))
            ->map(function ($item, $index) {
                $isActive = (bool) data_get($item, 'isActive', true);
                $fromPath = $this->normalizeRedirectPath(data_get($item, 'fromPath'));
                $toPath = $this->normalizeRedirectPath(data_get($item, 'toPath'));

                return [
                    'index' => $index,
                    'is_active' => $isActive,
                    'from' => $fromPath,
                    'to' => $toPath,
                ];
            })
            ->filter(fn (array $item) => $item['is_active'] && $item['from'] !== null && $item['to'] !== null)
            ->values();

        $errors = [];
        $activeFrom = [];

        foreach ($items as $item) {
            if ($item['from'] === $item['to']) {
                $errors["seo.technical.redirects.items.{$item['index']}.fromPath"] = 'Redirect source and destination cannot be the same.';
            }

            if (array_key_exists($item['from'], $activeFrom)) {
                $errors["seo.technical.redirects.items.{$item['index']}.fromPath"] = 'Duplicate active redirect source path is not allowed.';
            } else {
                $activeFrom[$item['from']] = $item['to'];
            }
        }

        foreach ($activeFrom as $from => $to) {
            if (isset($activeFrom[$to]) && $activeFrom[$to] === $from) {
                $errors['seo.technical.redirects.items'] = 'Two-way redirect loops are not allowed.';
                break;
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizeRedirectPath(mixed $value): ?string
    {
        $path = trim((string) ($value ?? ''));
        if ($path === '') {
            return null;
        }

        if (!str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return rtrim(preg_replace('#/+#', '/', $path) ?: $path, '/') ?: '/';
    }

    private function websitePageProps($tenant): array
    {
        $tenant->loadMissing('siteSetting.files');

        $logoFiles = $tenant->siteSetting
            ? $tenant->siteSetting->files()
                ->where('collection', 'logo')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => TenantSiteSetting::publicUrlFromPath($file->path),
                    ];
                })
                ->values()
                ->all()
            : [];

        $seoOgImageFiles = $tenant->siteSetting
            ? $tenant->siteSetting->files()
                ->where('collection', 'seo_og_image')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => TenantSiteSetting::publicUrlFromPath($file->path),
                    ];
                })
                ->values()
                ->all()
            : [];

        $faviconFiles = $tenant->siteSetting
            ? $tenant->siteSetting->files()
                ->where('collection', 'favicon')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => TenantSiteSetting::publicUrlFromPath($file->path),
                    ];
                })
                ->values()
                ->all()
            : [];

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'marketCountryOptions' => CountryOptions::all(),
            'marketCityOptionsByCountry' => BranchLocationOptions::cityOptionsByCountry(app()->getLocale()),
            'pdfTemplateOptions' => TenantPdfTemplateRegistry::contractTemplateOptions(),
            'logoFiles' => $logoFiles,
            'faviconFiles' => $faviconFiles,
            'seoOgImageFiles' => $seoOgImageFiles,
            'actions' => [
                'update' => route('admin.settings.website.update'),
                'website' => route('admin.settings.website.edit'),
                'seo_edit' => route('admin.settings.seo.edit'),
                'seo_audit' => route('admin.settings.seo-audit'),
            ],
        ];
    }

    private function seoPageProps($tenant): array
    {
        $tenant->loadMissing('siteSetting.files');

        $seoOgImageFiles = $tenant->siteSetting
            ? $tenant->siteSetting->files()
                ->where('collection', 'seo_og_image')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => TenantSiteSetting::publicUrlFromPath($file->path),
                    ];
                })
                ->values()
                ->all()
            : [];

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'seoOgImageFiles' => $seoOgImageFiles,
            'actions' => [
                'update' => route('admin.settings.seo.update'),
                'website' => route('admin.settings.website.edit'),
                'seo_audit' => route('admin.settings.seo-audit'),
            ],
    ];
    }

    private function mrtaLogoFiles(?TenantSiteSetting $siteSetting): array
    {
        $collections = [
            'oman' => 'mrta_oman_logo',
            'rop' => 'mrta_rop_logo',
            'liva' => 'mrta_liva_logo',
        ];

        return collect($collections)
            ->mapWithKeys(function (string $collection, string $key) use ($siteSetting) {
                if (! $siteSetting) {
                    return [$key => []];
                }

                $files = $siteSetting->files()
                    ->where('collection', $collection)
                    ->get()
                    ->map(fn ($file) => [
                        'id' => $file->id,
                        'url' => TenantSiteSetting::publicUrlFromPath($file->path),
                    ])
                    ->values()
                    ->all();

                return [$key => $files];
            })
            ->all();
    }

    private function contractSignatureFiles(?TenantSiteSetting $siteSetting): array
    {
        if (! $siteSetting) {
            return [];
        }

        return $siteSetting->files()
            ->where('collection', 'contract_incharge_signature')
            ->get()
            ->map(fn ($file) => [
                'id' => $file->id,
                'url' => TenantSiteSetting::publicUrlFromPath($file->path),
            ])
            ->values()
            ->all();
    }

    private function syncSingleFileUpload(Request $request, TenantSiteSetting $siteSetting, string $collection): void
    {
        $tempFoldersKey = "{$collection}_temp_folders";
        $removedFilesKey = "{$collection}_removed_files";

        $tempFolders = is_array($request->input($tempFoldersKey, []))
            ? array_values(array_filter($request->input($tempFoldersKey, [])))
            : [];
        $removedIds = is_array($request->input($removedFilesKey, []))
            ? array_values(array_filter($request->input($removedFilesKey, [])))
            : [];

        if (!empty($tempFolders)) {
            $existingIds = $siteSetting->files()->where('collection', $collection)->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $siteSetting,
            $tempFolders,
            $removedIds,
            $collection
        );
    }

    private function syncSeoOgImageUpload(Request $request, TenantSiteSetting $siteSetting): void
    {
        $tempFolders = is_array($request->input('seo_og_image_temp_folders', []))
            ? array_values(array_filter($request->input('seo_og_image_temp_folders', [])))
            : [];
        $removedIds = is_array($request->input('seo_og_image_removed_files', []))
            ? array_values(array_filter($request->input('seo_og_image_removed_files', [])))
            : [];
        $removedFileUrls = [];

        if (!empty($removedIds)) {
            $removedFileUrls = $siteSetting->files()
                ->where('collection', 'seo_og_image')
                ->whereIn('id', $removedIds)
                ->get()
                ->map(fn ($file) => TenantSiteSetting::publicUrlFromPath($file->path))
                ->filter()
                ->values()
                ->all();
        }

        if (!empty($tempFolders)) {
            $existingIds = $siteSetting->files()->where('collection', 'seo_og_image')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $siteSetting,
            $tempFolders,
            $removedIds,
            'seo_og_image'
        );

        $this->clearSeoOgImageReferenceIfNeeded($siteSetting, $removedFileUrls, $tempFolders);

        if (empty($tempFolders)) {
            return;
        }

        $ogImageFile = $siteSetting->files()
            ->where('collection', 'seo_og_image')
            ->latest('id')
            ->first();

        if (!$ogImageFile || !$ogImageFile->path) {
            return;
        }

        $seo = is_array($siteSetting->seo) ? $siteSetting->seo : [];
        data_set($seo, 'defaults.og_image', TenantSiteSetting::publicUrlFromPath($ogImageFile->path));

        $siteSetting->update(['seo' => $seo]);

        return;
    }

    private function contractPdfDefaults(): array
    {
        return [
            'mileage_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.mileage_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.mileage_notice.ar', [], 'ar'),
            ],
            'rental_period_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.rental_period_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.rental_period_notice.ar', [], 'ar'),
            ],
            'smoking_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.smoking_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.smoking_notice.ar', [], 'ar'),
            ],
            'unclean_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.unclean_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.unclean_notice.ar', [], 'ar'),
            ],
            'delay_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.delay_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.delay_notice.ar', [], 'ar'),
            ],
            'period_change_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.period_change_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.period_change_notice.ar', [], 'ar'),
            ],
            'accident_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.accident_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.accident_notice.ar', [], 'ar'),
            ],
            'acknowledgement_title' => [
                'en' => Lang::get('contracts.pdf.contract_texts.acknowledgement_title.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.acknowledgement_title.ar', [], 'ar'),
            ],
            'acknowledgement_body' => [
                'en' => Lang::get('contracts.pdf.contract_texts.acknowledgement_body.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.acknowledgement_body.ar', [], 'ar'),
            ],
            'mobile_signature_text' => Lang::get('contracts.pdf.contract_texts.mobile_signature_text', [], 'en'),
            'important_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.important_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.important_notice.ar', [], 'ar'),
            ],
            'closing_notice' => [
                'en' => Lang::get('contracts.pdf.contract_texts.closing_notice.en', [], 'en'),
                'ar' => Lang::get('contracts.pdf.contract_texts.closing_notice.ar', [], 'ar'),
            ],
        ];
    }

    private function clearSeoOgImageReferenceIfNeeded(TenantSiteSetting $siteSetting, array $removedFileUrls, array $tempFolders): void
    {
        if (!empty($tempFolders) || empty($removedFileUrls)) {
            return;
        }

        $remainingFileExists = $siteSetting->files()
            ->where('collection', 'seo_og_image')
            ->exists();

        if ($remainingFileExists) {
            return;
        }

        $seo = is_array($siteSetting->seo) ? $siteSetting->seo : [];
        $currentOgImage = trim((string) data_get($seo, 'defaults.og_image', ''));

        if ($currentOgImage === '' || !in_array($currentOgImage, $removedFileUrls, true)) {
            return;
        }

        data_set($seo, 'defaults.og_image', null);
        $siteSetting->update(['seo' => $seo]);
    }
}
