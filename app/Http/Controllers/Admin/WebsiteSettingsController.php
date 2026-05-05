<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\TenantSiteSetting;
use App\Support\BrandLogoImageResizer;
use App\Support\TenantPdfTemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class WebsiteSettingsController extends Controller
{
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
            'primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'secondary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
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

        $siteSetting = TenantSiteSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'site_name' => $this->nullableString($validated['site_name'] ?? null),
                'logo_url' => $this->nullableString($validated['logo_url'] ?? null),
                'primary_color' => strtolower((string) $validated['primary_color']),
                'secondary_color' => strtolower((string) $validated['secondary_color']),
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

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
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
        return [
            'seo.defaults.title_suffix.en' => ['nullable', 'string', 'max:255'],
            'seo.defaults.title_suffix.ar' => ['nullable', 'string', 'max:255'],
            'seo.defaults.default_description.en' => ['nullable', 'string', 'max:500'],
            'seo.defaults.default_description.ar' => ['nullable', 'string', 'max:500'],
            'seo.defaults.og_image' => ['nullable', 'string', 'max:1000'],
            'seo.defaults.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.home.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.home.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.home.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.home.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.fleet.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.fleet.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.fleet.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.fleet.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.about.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.about.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.about.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.about.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.contact.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.contact.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.contact.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.contact.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.car.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.car.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.car.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.car.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.car.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.car.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_checkout.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_checkout.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_checkout.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_checkout.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_checkout.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.booking_checkout.robots' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_confirmation.title.en' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_confirmation.title.ar' => ['nullable', 'string', 'max:255'],
            'seo.pages.booking_confirmation.description.en' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_confirmation.description.ar' => ['nullable', 'string', 'max:500'],
            'seo.pages.booking_confirmation.canonical_url' => ['nullable', 'string', 'max:1000'],
            'seo.pages.booking_confirmation.robots' => ['nullable', 'string', 'max:255'],
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
    }

    private function buildSeoPayload(array $validated): array
    {
        return [
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
                    'robots' => $this->nullableString(data_get($validated, 'seo.pages.home.robots')),
                    'focus_keyword' => [
                        'en' => $this->nullableString(data_get($validated, 'seo.pages.home.focus_keyword.en')),
                        'ar' => $this->nullableString(data_get($validated, 'seo.pages.home.focus_keyword.ar')),
                    ],
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
                    'robots' => $this->nullableString(data_get($validated, 'seo.pages.fleet.robots')),
                    'focus_keyword' => [
                        'en' => $this->nullableString(data_get($validated, 'seo.pages.fleet.focus_keyword.en')),
                        'ar' => $this->nullableString(data_get($validated, 'seo.pages.fleet.focus_keyword.ar')),
                    ],
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
                    'robots' => $this->nullableString(data_get($validated, 'seo.pages.about.robots')),
                    'focus_keyword' => [
                        'en' => $this->nullableString(data_get($validated, 'seo.pages.about.focus_keyword.en')),
                        'ar' => $this->nullableString(data_get($validated, 'seo.pages.about.focus_keyword.ar')),
                    ],
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
                    'robots' => $this->nullableString(data_get($validated, 'seo.pages.contact.robots')),
                    'focus_keyword' => [
                        'en' => $this->nullableString(data_get($validated, 'seo.pages.contact.focus_keyword.en')),
                        'ar' => $this->nullableString(data_get($validated, 'seo.pages.contact.focus_keyword.ar')),
                    ],
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
                    'robots' => $this->nullableString(data_get($validated, 'seo.pages.car.robots')),
                    'focus_keyword' => [
                        'en' => $this->nullableString(data_get($validated, 'seo.pages.car.focus_keyword.en')),
                        'ar' => $this->nullableString(data_get($validated, 'seo.pages.car.focus_keyword.ar')),
                    ],
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
                    'robots' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.robots')),
                    'focus_keyword' => [
                        'en' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.focus_keyword.en')),
                        'ar' => $this->nullableString(data_get($validated, 'seo.pages.booking_checkout.focus_keyword.ar')),
                    ],
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
                    'robots' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.robots')),
                    'focus_keyword' => [
                        'en' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.focus_keyword.en')),
                        'ar' => $this->nullableString(data_get($validated, 'seo.pages.booking_confirmation.focus_keyword.ar')),
                    ],
                ],
            ],
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

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'pdfTemplateOptions' => TenantPdfTemplateRegistry::contractTemplateOptions(),
            'logoFiles' => $logoFiles,
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
        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'actions' => [
                'update' => route('admin.settings.seo.update'),
                'website' => route('admin.settings.website.edit'),
                'seo_audit' => route('admin.settings.seo-audit'),
            ],
        ];
    }
}
