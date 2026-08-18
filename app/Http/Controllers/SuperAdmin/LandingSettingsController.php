<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\AiAutomationSettings;
use App\Core\AiProviderSettings;
use App\Core\LandingPageSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\PlanTranslations;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\GetProcessorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use OpenAI;
use RuntimeException;
use Throwable;

class LandingSettingsController extends Controller
{
    public function __construct(
        private readonly FilePondService $filePondService,
    ) {}

    public function edit(): Response
    {
        $brandingSetting = SiteSetting::query()
            ->with('files')
            ->where('key', LandingPageSettings::KEY)
            ->first();

        $heroFiles = $brandingSetting
            ? $brandingSetting->files()
                ->where('collection', 'hero')
                ->get()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => SiteSetting::publicUrlFromPath($file->path),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('SuperAdmin/Settings/General', [
            'settings' => $this->landingSettings(),
            'heroFiles' => $heroFiles,
            'heroLocalizedFiles' => $this->heroLocalizedFiles($brandingSetting),
            'aiSettings' => AiAutomationSettings::load(),
            'aiProviderSettings' => AiProviderSettings::forUi(),
        ]);
    }

    public function translations(Request $request): Response
    {
        $settings = $this->landingSettings();
        $supportedLocales = $this->supportedLocaleKeys();
        $supportedLocaleMeta = LaravelLocalization::getSupportedLocales();
        $defaultRows = $this->defaultTranslationRows('en');
        $search = trim((string) $request->query('search', ''));
        $section = trim((string) $request->query('section', 'all'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(25, min(200, (int) $request->query('per_page', 50)));

        $overrideRowsByLocale = [];
        $keyPool = array_keys($defaultRows);

        foreach ($supportedLocales as $locale) {
            $overrideRowsByLocale[$locale] = $this->normalizedTranslationRows((array) data_get($settings, "translations.$locale", []));
            $keyPool = array_merge($keyPool, array_keys($overrideRowsByLocale[$locale]));
        }

        $keys = array_values(array_filter(
            array_unique($keyPool),
            fn (string $key): bool => $this->isLandingTranslationKey($key)
        ));
        sort($keys);

        $sections = collect($keys)
            ->map(static fn (string $key): string => Str::before($key, '.'))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($section !== '' && $section !== 'all') {
            $keys = array_values(array_filter(
                $keys,
                static fn (string $key): bool => Str::before($key, '.') === $section
            ));
        }

        if ($search !== '') {
            $query = Str::lower($search);
            $keys = array_values(array_filter($keys, function (string $key) use ($query, $defaultRows, $overrideRowsByLocale): bool {
                if (Str::contains(Str::lower($key), $query) || Str::contains(Str::lower('site.' . $key), $query)) {
                    return true;
                }

                if (Str::contains(Str::lower((string) ($defaultRows[$key] ?? '')), $query)) {
                    return true;
                }

                foreach ($overrideRowsByLocale as $rows) {
                    if (Str::contains(Str::lower((string) ($rows[$key] ?? '')), $query)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $total = count($keys);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $paginatedKeys = array_slice($keys, ($page - 1) * $perPage, $perPage);

        $rows = array_map(function (string $key) use ($supportedLocales, $defaultRows, $overrideRowsByLocale): array {
            $values = [];
            foreach ($supportedLocales as $locale) {
                $values[$locale] = (string) ($overrideRowsByLocale[$locale][$key] ?? '');
            }

            return [
                'key' => $key,
                'default' => (string) ($defaultRows[$key] ?? ''),
                'values' => $values,
            ];
        }, $paginatedKeys);

        return Inertia::render('SuperAdmin/Settings/LandingTranslations', [
            'settings' => $settings,
            'supported_locales' => array_values(array_map(function (string $code) use ($supportedLocaleMeta): array {
                $meta = (array) ($supportedLocaleMeta[$code] ?? []);

                return [
                    'code' => $code,
                    'name' => (string) ($meta['name'] ?? strtoupper($code)),
                    'native' => (string) ($meta['native'] ?? strtoupper($code)),
                ];
            }, $supportedLocales)),
            'enabled_locales' => data_get($settings, 'enabled_locales', $supportedLocales),
            'rows' => $rows,
            'sections' => $sections,
            'filters' => [
                'search' => $search,
                'section' => $section === '' ? 'all' : $section,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
                'to' => $total === 0 ? null : min($total, $page * $perPage),
            ],
            'actions' => [
                'update' => route('superadmin.settings.landing-translations.update'),
                'auto_translate' => route('superadmin.settings.landing-translations.auto-translate'),
            ],
        ]);
    }

    public function design(): Response
    {
        $brandingSetting = SiteSetting::query()
            ->with('files')
            ->where('key', LandingPageSettings::KEY)
            ->first();

        $heroFiles = $brandingSetting
            ? $brandingSetting->files()
                ->where('collection', 'hero')
                ->get()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => SiteSetting::publicUrlFromPath($file->path),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('SuperAdmin/Settings/Design', [
            'settings' => $this->landingSettings(),
            'previewUrl' => route('home'),
            'heroFiles' => $heroFiles,
            'heroLocalizedFiles' => $this->heroLocalizedFiles($brandingSetting),
            'carsFleetButtonIconFiles' => $this->landingFilesForCollection($brandingSetting, 'cars_fleet_button_icon'),
            'footerAppIconFiles' => [
                'android' => $this->landingFilesForCollection($brandingSetting, 'footer_android_icon'),
                'ios' => $this->landingFilesForCollection($brandingSetting, 'footer_ios_icon'),
            ],
            'featureFiles' => $this->featureCardFiles($brandingSetting),
            'gettingStartedFiles' => $this->gettingStartedFiles($brandingSetting),
            'mobileAppFiles' => $this->mobileAppFiles($brandingSetting),
        ]);
    }

    public function applicationsPage(): Response
    {
        $settings = $this->landingSettings();
        $landingSetting = SiteSetting::query()
            ->with('files')
            ->where('key', LandingPageSettings::KEY)
            ->first();

        return Inertia::render('SuperAdmin/Settings/ApplicationsPage', [
            'applicationsPage' => data_get($settings, 'applications_page', []),
            'previewUrl' => route('applications'),
            'translationsUrl' => route('superadmin.settings.landing-translations'),
            'updateUrl' => route('superadmin.settings.applications-page.update'),
            'availableLocales' => $this->landingEnabledLocales($settings),
            'heroFiles' => $this->landingFilesForCollection($landingSetting, 'applications_page_hero'),
            'heroLocalizedFiles' => $this->applicationHeroLocalizedFiles($landingSetting),
            'roleFiles' => $this->applicationRoleFiles($landingSetting),
            'roleLocalizedFiles' => $this->applicationRoleLocalizedFiles($landingSetting),
        ]);
    }

    public function updateApplicationsPage(Request $request): RedirectResponse
    {
        $this->sanitizeApplicationsPageFiles($request);

        $validated = $request->validate([
            'applications_page.enabled' => ['nullable', 'boolean'],
            'applications_page.hero_enabled' => ['nullable', 'boolean'],
            'applications_page.hero_eyebrow' => ['required', 'string', 'max:255'],
            'applications_page.hero_title' => ['required', 'string', 'max:255'],
            'applications_page.hero_highlight' => ['nullable', 'string', 'max:255'],
            'applications_page.hero_description' => ['required', 'string', 'max:2000'],
            'applications_page.hero_image_url' => ['nullable', 'string', 'max:2000'],
            'applications_page.hero_localized_images' => ['nullable', 'array'],
            'applications_page.hero_localized_images.*' => ['nullable', 'string', 'max:2000'],
            'applications_page.primary_cta_label' => ['required', 'string', 'max:255'],
            'applications_page.secondary_cta_label' => ['required', 'string', 'max:255'],
            'applications_page.owner_employee_note' => ['nullable', 'string', 'max:1000'],
            'applications_page.apps_enabled' => ['nullable', 'boolean'],
            'applications_page.section_eyebrow' => ['required', 'string', 'max:255'],
            'applications_page.section_title' => ['required', 'string', 'max:255'],
            'applications_page.section_description' => ['required', 'string', 'max:2000'],
            'applications_page.store_ios_label' => ['required', 'string', 'max:255'],
            'applications_page.store_ios_caption' => ['required', 'string', 'max:255'],
            'applications_page.store_android_label' => ['required', 'string', 'max:255'],
            'applications_page.store_android_caption' => ['required', 'string', 'max:255'],
            'applications_page.roles' => ['required', 'array', 'min:1'],
            'applications_page.roles.*.enabled' => ['nullable', 'boolean'],
            'applications_page.roles.*.key' => ['nullable', 'string', 'max:50'],
            'applications_page.roles.*.label' => ['required', 'string', 'max:255'],
            'applications_page.roles.*.title' => ['required', 'string', 'max:255'],
            'applications_page.roles.*.description' => ['required', 'string', 'max:2000'],
            'applications_page.roles.*.image_url' => ['nullable', 'string', 'max:2000'],
            'applications_page.roles.*.localized_images' => ['nullable', 'array'],
            'applications_page.roles.*.localized_images.*' => ['nullable', 'string', 'max:2000'],
            'applications_page.roles.*.note_title' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.note' => ['nullable', 'string', 'max:1000'],
            'applications_page.roles.*.floating_one_title' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.floating_one_text' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.floating_two_title' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.floating_two_text' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.screen_label' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.screen_title' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.screen_stat_label' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.screen_stat_value' => ['nullable', 'string', 'max:255'],
            'applications_page.roles.*.features' => ['nullable', 'array'],
            'applications_page.roles.*.features.*' => ['nullable', 'string', 'max:255'],
            'applications_page.comparison_enabled' => ['nullable', 'boolean'],
            'applications_page.compare_title' => ['required', 'string', 'max:255'],
            'applications_page.compare_description' => ['required', 'string', 'max:2000'],
            'applications_page.compare_badge' => ['required', 'string', 'max:255'],
            'applications_page.comparison' => ['nullable', 'array'],
            'applications_page.comparison.*.title' => ['nullable', 'string', 'max:255'],
            'applications_page.comparison.*.description' => ['nullable', 'string', 'max:1000'],
            'applications_page.comparison.*.items' => ['nullable', 'array'],
            'applications_page.comparison.*.items.*' => ['nullable', 'string', 'max:255'],
            'applications_page.ecosystem_enabled' => ['nullable', 'boolean'],
            'applications_page.ecosystem_title' => ['required', 'string', 'max:255'],
            'applications_page.ecosystem_description' => ['required', 'string', 'max:2000'],
            'applications_page.ecosystem_cta_label' => ['required', 'string', 'max:255'],
            'application_hero_direct_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'application_hero_removed_files' => ['nullable', 'array'],
            'application_hero_removed_files.*' => ['integer'],
            'application_hero_locale_direct_files' => ['nullable', 'array'],
            'application_hero_locale_direct_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'application_hero_locale_removed_files' => ['nullable', 'array'],
            'application_hero_locale_removed_files.*' => ['array'],
            'application_hero_locale_removed_files.*.*' => ['integer'],
            'application_role_direct_files' => ['nullable', 'array'],
            'application_role_direct_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'application_role_removed_files' => ['nullable', 'array'],
            'application_role_removed_files.*' => ['array'],
            'application_role_removed_files.*.*' => ['integer'],
            'application_role_locale_direct_files' => ['nullable', 'array'],
            'application_role_locale_direct_files.*' => ['nullable', 'array'],
            'application_role_locale_direct_files.*.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'application_role_locale_removed_files' => ['nullable', 'array'],
            'application_role_locale_removed_files.*' => ['nullable', 'array'],
            'application_role_locale_removed_files.*.*' => ['nullable', 'array'],
            'application_role_locale_removed_files.*.*.*' => ['integer'],
        ]);

        $landingSetting = $this->persistLandingSettings([
            'applications_page' => $validated['applications_page'],
        ]);

        $this->syncApplicationsPageUploads($request, $landingSetting);

        return back()->with('success', 'Applications page settings updated successfully.');
    }

    public function plansPage(): Response
    {
        $settings = $this->landingSettings();

        return Inertia::render('SuperAdmin/Settings/PlansPage', [
            'plansPage' => data_get($settings, 'plans_comparison_page', []),
            'previewUrl' => route('plans'),
            'translationsUrl' => route('superadmin.settings.landing-translations'),
            'updateUrl' => route('superadmin.settings.plans-page.update'),
        ]);
    }

    public function updatePlansPage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plans_comparison_page.enabled' => ['nullable', 'boolean'],
            'plans_comparison_page.hero_enabled' => ['nullable', 'boolean'],
            'plans_comparison_page.summary_enabled' => ['nullable', 'boolean'],
            'plans_comparison_page.comparison_enabled' => ['nullable', 'boolean'],
            'plans_comparison_page.addons_enabled' => ['nullable', 'boolean'],
            'plans_comparison_page.policy_enabled' => ['nullable', 'boolean'],
            'plans_comparison_page.footer_enabled' => ['nullable', 'boolean'],
            'plans_comparison_page.hero_badge' => ['required', 'string', 'max:255'],
            'plans_comparison_page.hero_title' => ['required', 'string', 'max:255'],
            'plans_comparison_page.hero_description' => ['required', 'string', 'max:2000'],
            'plans_comparison_page.monthly_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.current_price_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.official_price_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.launch_discount_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.most_value_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.custom_price_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.custom_price_caption' => ['required', 'string', 'max:255'],
            'plans_comparison_page.custom_price_badge' => ['required', 'string', 'max:255'],
            'plans_comparison_page.unlimited_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.not_available_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.included_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.table_title' => ['required', 'string', 'max:255'],
            'plans_comparison_page.table_description' => ['required', 'string', 'max:2000'],
            'plans_comparison_page.table_note' => ['required', 'string', 'max:255'],
            'plans_comparison_page.comparison_scroll_hint' => ['required', 'string', 'max:255'],
            'plans_comparison_page.feature_column_label' => ['required', 'string', 'max:255'],
            'plans_comparison_page.comparison_sections' => ['nullable', 'array'],
            'plans_comparison_page.comparison_sections.*.title' => ['nullable', 'string', 'max:255'],
            'plans_comparison_page.comparison_sections.*.rows' => ['nullable', 'array'],
            'plans_comparison_page.comparison_sections.*.rows.*.label' => ['nullable', 'string', 'max:255'],
            'plans_comparison_page.comparison_sections.*.rows.*.tone' => ['nullable', 'string', 'max:50'],
            'plans_comparison_page.comparison_sections.*.rows.*.values' => ['nullable', 'array'],
            'plans_comparison_page.comparison_sections.*.rows.*.values.*' => ['nullable', 'string', 'max:255'],
            'plans_comparison_page.addons_title' => ['required', 'string', 'max:255'],
            'plans_comparison_page.addons' => ['nullable', 'array'],
            'plans_comparison_page.addons.*' => ['nullable', 'string', 'max:255'],
            'plans_comparison_page.trial_title' => ['required', 'string', 'max:255'],
            'plans_comparison_page.trial_items' => ['nullable', 'array'],
            'plans_comparison_page.trial_items.*' => ['nullable', 'string', 'max:255'],
            'plans_comparison_page.policy_title' => ['required', 'string', 'max:255'],
            'plans_comparison_page.policy_paragraphs' => ['nullable', 'array'],
            'plans_comparison_page.policy_paragraphs.*' => ['nullable', 'string', 'max:1000'],
            'plans_comparison_page.footer_text' => ['required', 'string', 'max:255'],
        ]);

        $this->persistLandingSettings([
            'plans_comparison_page' => $validated['plans_comparison_page'],
        ]);

        return back()->with('success', 'Plans page settings updated successfully.');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->normalizeAiProviderPayload($request);

        $validated = $request->validate([
            'settings.hero.enabled' => ['nullable', 'boolean'],
            'settings.hero.title' => ['required', 'string', 'max:255'],
            'settings.hero.description' => ['required', 'string', 'max:2000'],
            'settings.hero.features' => ['nullable', 'array'],
            'settings.hero.features.*' => ['nullable', 'string', 'max:255'],
            'settings.hero.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.hero.localized_images' => ['nullable', 'array'],
            'settings.hero.localized_images.*' => ['nullable', 'string', 'max:2000'],

            'settings.cars_section.enabled' => ['nullable', 'boolean'],
            'settings.cars_section.fleet_button_icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.enabled' => ['nullable', 'boolean'],
            'settings.features_section.title' => ['required', 'string', 'max:255'],
            'settings.features_section.description' => ['required', 'string', 'max:2000'],
            'settings.features_section.cards' => ['nullable', 'array'],
            'settings.features_section.cards.*.title' => ['nullable', 'string', 'max:255'],
            'settings.features_section.cards.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.cards.*.icon_background_color' => ['nullable', 'string', 'max:20'],
            'settings.features_section.cards.*.content' => ['nullable', 'string', 'max:2000'],

            'settings.getting_started.enabled' => ['nullable', 'boolean'],
            'settings.getting_started.title' => ['required', 'string', 'max:255'],
            'settings.getting_started.description' => ['required', 'string', 'max:2000'],
            'settings.getting_started.items' => ['nullable', 'array'],
            'settings.getting_started.items.*.title' => ['nullable', 'string', 'max:255'],
            'settings.getting_started.items.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.getting_started.items.*.icon_background_color' => ['nullable', 'string', 'max:20'],
            'settings.getting_started.items.*.description' => ['nullable', 'string', 'max:2000'],

            'settings.mobile_apps_section.enabled' => ['nullable', 'boolean'],
            'settings.mobile_apps_section.eyebrow' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.title' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.description' => ['required', 'string', 'max:2000'],
            'settings.mobile_apps_section.ios_label' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.android_label' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.apps' => ['nullable', 'array'],
            'settings.mobile_apps_section.apps.*.title' => ['nullable', 'string', 'max:255'],
            'settings.mobile_apps_section.apps.*.subtitle' => ['nullable', 'string', 'max:255'],
            'settings.mobile_apps_section.apps.*.description' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.localized_images' => ['nullable', 'array'],
            'settings.mobile_apps_section.apps.*.localized_images.*' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.app_store_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.google_play_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.badge' => ['nullable', 'string', 'max:255'],
            'settings.mobile_apps_section.apps.*.features' => ['nullable', 'array'],
            'settings.mobile_apps_section.apps.*.features.*' => ['nullable', 'string', 'max:255'],

            'settings.clients_section.enabled' => ['nullable', 'boolean'],
            'settings.plans_section.enabled' => ['nullable', 'boolean'],
            'settings.plans_section.title' => ['required', 'string', 'max:255'],
            'settings.plans_section.description' => ['required', 'string', 'max:2000'],

            'settings.faq_section.enabled' => ['nullable', 'boolean'],
            'settings.faq_section.title' => ['required', 'string', 'max:255'],
            'settings.faq_section.description' => ['required', 'string', 'max:2000'],
            'settings.faq_section.items' => ['nullable', 'array'],
            'settings.faq_section.items.*.question' => ['nullable', 'string', 'max:2000'],
            'settings.faq_section.items.*.answer' => ['nullable', 'string', 'max:5000'],

            'settings.footer.enabled' => ['nullable', 'boolean'],
            'settings.footer.title' => ['required', 'string', 'max:255'],
            'settings.footer.description' => ['required', 'string', 'max:2000'],
            'settings.footer.copyright_text' => ['required', 'string', 'max:255'],
            'settings.footer.show_social_links' => ['nullable', 'boolean'],
            'settings.footer.show_app_buttons' => ['nullable', 'boolean'],
            'settings.footer.android_caption' => ['required', 'string', 'max:255'],
            'settings.footer.android_label' => ['required', 'string', 'max:255'],
            'settings.footer.android_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.android_icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.ios_caption' => ['required', 'string', 'max:255'],
            'settings.footer.ios_label' => ['required', 'string', 'max:255'],
            'settings.footer.ios_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.ios_icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.nav_privacy' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_terms' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_security_policy' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_cars' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_features' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_application' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_plans' => ['nullable', 'string', 'max:255'],
            'settings.footer.social_links' => ['nullable', 'array'],
            'settings.footer.social_links.*.label' => ['nullable', 'string', 'max:255'],
            'settings.footer.social_links.*.platform' => ['nullable', 'string', 'max:50'],
            'settings.footer.social_links.*.href' => ['nullable', 'string', 'max:2000'],

            'ai.enabled' => ['nullable', 'boolean'],
            'ai.contracts_extraction_enabled' => ['nullable', 'boolean'],

            'ai_provider.provider' => ['required', Rule::in(['openai', 'google_document_ai'])],

            'ai_provider.openai.api_key' => ['nullable', 'string', 'max:5000'],
            'ai_provider.openai.organization' => ['nullable', 'string', 'max:255'],
            'ai_provider.openai.project' => ['nullable', 'string', 'max:255'],
            'ai_provider.openai.base_uri' => ['nullable', 'url', 'max:2000'],
            'ai_provider.openai.model' => ['required', 'string', 'max:255'],
            'ai_provider.openai.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'ai_provider.openai.max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:16384'],
            'ai_provider.openai.system_prompt' => ['nullable', 'string', 'max:10000'],
            'ai_provider.document_extraction_daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],

            'ai_provider.google_document_ai.enabled' => ['nullable', 'boolean'],
            'ai_provider.google_document_ai.project_id' => ['nullable', 'string', 'max:255'],
            'ai_provider.google_document_ai.location' => ['nullable', 'string', 'max:255'],
            'ai_provider.google_document_ai.processor_id' => ['nullable', 'string', 'max:255'],
        ]);

        $normalized = $this->validatedLandingSettings($request);
        $landingSetting = $this->persistLandingSettings($normalized);
        $normalizedAi = AiAutomationSettings::normalize($validated['ai'] ?? []);
        $currentAiProvider = AiProviderSettings::load();
        $normalizedAiProvider = AiProviderSettings::normalize($validated['ai_provider'] ?? []);
        $normalizedAiProvider = AiProviderSettings::mergeSecrets($currentAiProvider, $normalizedAiProvider);

        $this->syncHeroImageUpload($request, $landingSetting);
        $this->syncFeatureCardUploads($request, $landingSetting);
        $this->syncGettingStartedUploads($request, $landingSetting);
        $this->syncMobileAppUploads($request, $landingSetting);
        $this->syncCarsFleetButtonIconUpload($request, $landingSetting);
        $this->syncFooterAppIconUploads($request, $landingSetting);
        $this->refreshLandingImageUrls($landingSetting);

        SiteSetting::query()->updateOrCreate(
            ['key' => AiAutomationSettings::KEY],
            ['value' => $normalizedAi]
        );

        SiteSetting::query()->updateOrCreate(
            ['key' => AiProviderSettings::KEY],
            ['value' => $normalizedAiProvider]
        );

        return back()->with('success', 'Landing page settings updated successfully.');
    }

    public function updateDesign(Request $request): RedirectResponse
    {
        $this->logLandingUploadRequest($request, 'design_update_started');

        $normalized = $this->validatedLandingSettings($request);
        $landingSetting = $this->persistLandingSettings($normalized);

        Log::info('Landing design settings persisted before upload sync.', [
            'site_setting_id' => $landingSetting->id,
            'key' => $landingSetting->key,
        ]);

        $this->syncHeroImageUpload($request, $landingSetting);
        $this->syncFeatureCardUploads($request, $landingSetting);
        $this->syncGettingStartedUploads($request, $landingSetting);
        $this->syncMobileAppUploads($request, $landingSetting);
        $this->syncCarsFleetButtonIconUpload($request, $landingSetting);
        $this->syncFooterAppIconUploads($request, $landingSetting);
        $this->refreshLandingImageUrls($landingSetting);

        Log::info('Landing design upload sync completed.', [
            'site_setting_id' => $landingSetting->id,
            'files_count' => $landingSetting->files()->count(),
            'collections' => $landingSetting->files()
                ->reorder()
                ->select('collection')
                ->distinct()
                ->pluck('collection')
                ->values()
                ->all(),
        ]);

        return back()->with('success', 'Landing page design updated successfully.');
    }

    public function updateTranslations(Request $request): RedirectResponse
    {
        $supportedLocales = $this->supportedLocaleKeys();

        $validated = $request->validate([
            'enabled_locales' => ['nullable', 'array'],
            'enabled_locales.*' => ['string', Rule::in($supportedLocales)],
            'rows' => ['required', 'array'],
            'rows.*.key' => ['required', 'string', 'max:255'],
            'rows.*.values' => ['nullable', 'array'],
        ]);

        $enabledLocales = $this->sanitizeEnabledLocales($validated['enabled_locales'] ?? $supportedLocales);
        $settings = $this->landingSettings();
        $translations = [];
        foreach ($supportedLocales as $locale) {
            $translations[$locale] = (array) data_get($settings, "translations.$locale", []);
        }

        foreach ((array) ($validated['rows'] ?? []) as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if (str_starts_with($key, 'site.')) {
                $key = Str::after($key, 'site.');
            }

            if ($key === '') {
                continue;
            }

            $values = is_array($row['values'] ?? null) ? $row['values'] : [];
            foreach ($supportedLocales as $locale) {
                $text = trim((string) ($values[$locale] ?? ''));
                if ($text === '') {
                    Arr::forget($translations[$locale], $key);
                    continue;
                }

                Arr::set($translations[$locale], $key, $text);
            }
        }

        $this->persistLandingSettings([
            'enabled_locales' => $enabledLocales,
            'translations' => $translations,
        ]);

        return back()->with('success', 'Landing translations updated successfully.');
    }

    public function autoTranslateTranslations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_locale' => ['nullable', 'string', Rule::in(['ar'])],
        ]);

        $targetLocale = (string) ($validated['target_locale'] ?? 'ar');
        $settings = $this->landingSettings();
        $sourceRows = $this->defaultTranslationRows('en');

        if (empty($sourceRows)) {
            return response()->json([
                'ok' => false,
                'message' => 'No landing content is available to translate.',
            ], 422);
        }

        $translations = $this->translateLandingContentToArabic($sourceRows, $targetLocale);

        return response()->json([
            'ok' => true,
            'target_locale' => $targetLocale,
            'translations' => $translations,
        ]);
    }

    public function testAiConnection(Request $request): JsonResponse
    {
        $this->normalizeAiProviderPayload($request);

        $validated = $request->validate([
            'ai_provider.provider' => ['required', Rule::in(['openai', 'google_document_ai'])],
            'ai_provider.openai.api_key' => ['nullable', 'string', 'max:5000'],
            'ai_provider.openai.organization' => ['nullable', 'string', 'max:255'],
            'ai_provider.openai.project' => ['nullable', 'string', 'max:255'],
            'ai_provider.openai.base_uri' => ['nullable', 'url', 'max:2000'],
            'ai_provider.openai.model' => ['nullable', 'string', 'max:255'],
            'ai_provider.openai.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'ai_provider.openai.max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:16384'],
            'ai_provider.openai.system_prompt' => ['nullable', 'string', 'max:10000'],
            'ai_provider.document_extraction_daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'ai_provider.google_document_ai.enabled' => ['nullable', 'boolean'],
            'ai_provider.google_document_ai.project_id' => ['nullable', 'string', 'max:255'],
            'ai_provider.google_document_ai.location' => ['nullable', 'string', 'max:255'],
            'ai_provider.google_document_ai.processor_id' => ['nullable', 'string', 'max:255'],
            'ai_provider.google_document_ai.service_account_json' => ['nullable', 'string', 'max:100000'],
        ]);

        $current = AiProviderSettings::load();
        $incoming = AiProviderSettings::normalize($validated['ai_provider'] ?? []);
        $effective = AiProviderSettings::mergeSecrets($current, $incoming);
        $provider = (string) ($effective['provider'] ?? 'openai');

        try {
            if ($provider === 'google_document_ai') {
                $this->testGoogleDocumentAiProvider($effective);
            } else {
                $this->testOpenAiProvider($effective);
            }

            return response()->json([
                'ok' => true,
                'provider' => $provider,
                'message' => $provider === 'google_document_ai'
                    ? 'Google Document AI connection is valid.'
                    : 'OpenAI connection is valid.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'provider' => $provider,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function testMailConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $recipient = trim((string) ($validated['email'] ?? $request->user()->email ?? ''));
        if ($recipient === '') {
            return response()->json([
                'ok' => false,
                'message' => 'A recipient email is required to test mail delivery.',
            ], 422);
        }

        Mail::raw(
            'This is a test email from the Car4u Super Admin mail connection test.',
            function ($message) use ($recipient): void {
                $message->to($recipient)
                    ->subject('Car4u SMTP test message')
                    ->from(
                        config('mail.from.address'),
                        config('mail.from.name', config('app.name'))
                    );
            }
        );

        return response()->json([
            'ok' => true,
            'message' => 'Test email sent successfully.',
            'mail' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'encryption' => config('mail.mailers.smtp.scheme') ?: config('mail.mailers.smtp.encryption'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'recipient' => $recipient,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function testOpenAiProvider(array $settings): void
    {
        $openAi = $settings['openai'] ?? [];
        $apiKey = trim((string) ($openAi['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is required.');
        }

        $factory = OpenAI::factory()->withApiKey($apiKey);

        $organization = trim((string) ($openAi['organization'] ?? ''));
        if ($organization !== '') {
            $factory = $factory->withOrganization($organization);
        }

        $project = trim((string) ($openAi['project'] ?? ''));
        if ($project !== '') {
            $factory = $factory->withProject($project);
        }

        $baseUri = trim((string) ($openAi['base_uri'] ?? ''));
        if ($baseUri !== '') {
            $factory = $factory->withBaseUri($baseUri);
        }

        $client = $factory->make();
        $client->models()->list();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function testGoogleDocumentAiProvider(array $settings): void
    {
        $google = $settings['google_document_ai'] ?? [];

        $enabled = (bool) ($google['enabled'] ?? false);
        $projectId = trim((string) ($google['project_id'] ?? ''));
        $location = trim((string) ($google['location'] ?? ''));
        $processorId = trim((string) ($google['processor_id'] ?? ''));
        $serviceAccountJson = trim((string) ($google['service_account_json'] ?? ''));

        if (!$enabled) {
            throw new RuntimeException('Google Document AI is disabled. Enable it before testing.');
        }

        if ($projectId === '' || $location === '' || $processorId === '' || $serviceAccountJson === '') {
            throw new RuntimeException('Google Document AI project, location, processor, and credentials are required.');
        }

        $credentials = json_decode($serviceAccountJson, true);
        if (!is_array($credentials)) {
            throw new RuntimeException('Google service account JSON is invalid.');
        }

        $client = new DocumentProcessorServiceClient([
            'credentials' => $credentials,
        ]);

        $processorName = DocumentProcessorServiceClient::processorName($projectId, $location, $processorId);
        $request = (new GetProcessorRequest())->setName($processorName);
        $client->getProcessor($request);
        $client->close();
    }

    private function landingSettings(): array
    {
        $landingSetting = SiteSetting::query()
            ->with('files')
            ->where('key', LandingPageSettings::KEY)
            ->first();
        $stored = $landingSetting?->value;

        return $this->hydrateLandingImageUrls(
            LandingPageSettings::normalize(is_array($stored) ? $stored : null),
            $landingSetting
        );
    }

    private function defaultTranslationRows(string $locale): array
    {
        $settings = $this->landingSettings();
        $rows = $this->flatten(Arr::only(
            $this->translatableSettings(LandingPageSettings::localize($settings, $locale)),
            LandingPageSettings::contentKeys()
        ));
        $rows = array_merge($rows, $this->flatten(PlanTranslations::defaultTranslationTree()));
        $rows = array_merge($rows, $this->siteDashboardTranslationRows($locale));
        $rows = array_merge($rows, [
            'fleet.fuel_types.gasoline' => 'Gasoline',
            'fleet.fuel_types.diesel' => 'Diesel',
            'fleet.fuel_types.electric' => 'Electric',
            'fleet.fuel_types.hybrid' => 'Hybrid',
            'auth.or_continue_with' => 'Or continue with',
            'auth.google' => 'Google',
            'auth.apple' => 'Apple',
            'auth.tenant_account_inactive' => 'This tenant account is inactive. Please contact support.',
            'auth.tenant_subscription_expired' => 'This tenant subscription has expired. Please contact your administrator.',
            'auth.plan_expired' => 'Your plan has expired. Please login and renew your subscription.',
            'auth.trial_ended' => 'Your trial period has ended. Please contact your administrator.',
            'auth.unauthorized_access' => 'You are not authorized to access this area.',
            'auth.social_email_already_exists' => 'This email is already registered with another account. Please sign in using your credentials or use another email.',
            'auth.social_email_missing' => 'Unable to retrieve email from social login provider.',
            'auth.social_login_failed' => 'Social login authentication failed. Please try again.',
            'pagination.previous' => 'Previous',
            'pagination.next' => 'Next',
            'welcome.why_choose_start' => 'Why Choose',
            'welcome.why_choose_highlight' => 'Car4u',
            'welcome.why_choose_desc' => 'We provide an unparalleled car rental experience with premium service at every touchpoint.',
            'welcome.feature_quality_title' => 'Premium Quality',
            'welcome.feature_quality_desc' => 'Every vehicle is inspected and maintained for safety, comfort, and peace of mind.',
            'welcome.feature_support_title' => '24/7 Support',
            'welcome.feature_support_desc' => 'Our support team is available around the clock during your rental.',
            'welcome.feature_value_title' => 'Best Value',
            'welcome.feature_value_desc' => 'Competitive prices with no hidden fees and flexible rental options.',
            'tenant_home.why_choose.title_start' => 'Why Choose',
            'tenant_home.why_choose.title_highlight' => 'Car4u',
            'tenant_home.why_choose.description' => 'We provide an unparalleled car rental experience with premium service at every touchpoint.',
            'tenant_home.why_choose.items.0.title' => 'Premium Quality',
            'tenant_home.why_choose.items.0.description' => 'Every vehicle is inspected and maintained for safety, comfort, and peace of mind.',
            'tenant_home.why_choose.items.1.title' => '24/7 Support',
            'tenant_home.why_choose.items.1.description' => 'Our support team is available around the clock during your rental.',
            'tenant_home.why_choose.items.2.title' => 'Best Value',
            'tenant_home.why_choose.items.2.description' => 'Competitive prices with no hidden fees and flexible rental options.',
            'tenant_about.why_choose.title' => 'Why Choose Car4u?',
            'tenant_about.why_choose.items.premium_fleet.title' => 'Premium Fleet',
            'tenant_about.why_choose.items.premium_fleet.description' => 'Modern, well-maintained vehicles from top manufacturers',
            'tenant_about.why_choose.items.support.title' => '24/7 Support',
            'tenant_about.why_choose.items.support.description' => 'Round-the-clock customer service and roadside assistance',
            'tenant_about.why_choose.items.flexible_booking.title' => 'Flexible Booking',
            'tenant_about.why_choose.items.flexible_booking.description' => 'Easy online booking with flexible pickup and return options',
            'tenant_about.why_choose.items.competitive_pricing.title' => 'Competitive Pricing',
            'tenant_about.why_choose.items.competitive_pricing.description' => 'Best rates in the market with no hidden fees',
            'tenant_about.why_choose.items.multiple_locations.title' => 'Multiple Locations',
            'tenant_about.why_choose.items.multiple_locations.description' => 'Convenient pickup points across the city',
            'tenant_about.why_choose.items.safety_first.title' => 'Safety First',
            'tenant_about.why_choose.items.safety_first.description' => 'All vehicles undergo rigorous safety inspections',
            'dashboard.admin.car_statuses.draft' => 'Draft',
            'dashboard.admin.car_statuses.available' => 'Available',
            'dashboard.admin.car_statuses.reserved' => 'Reserved',
            'dashboard.admin.car_statuses.rented' => 'Rented',
            'dashboard.admin.car_statuses.maintenance' => 'Maintenance',
            'dashboard.admin.car_statuses.cleaning' => 'Cleaning',
            'dashboard.admin.car_statuses.unavailable' => 'Unavailable',
            'dashboard.admin.car_statuses.retired' => 'Retired',
            'dashboard.admin.reservation_statuses.pending' => 'Pending',
            'dashboard.admin.reservation_statuses.confirmed' => 'Confirmed',
            'dashboard.admin.reservation_statuses.active' => 'Active',
            'dashboard.admin.reservation_statuses.completed_wait_contract' => 'Completed - Waiting For Contract',
            'dashboard.admin.reservation_statuses.completed' => 'Completed',
            'dashboard.admin.reservation_statuses.cancelled' => 'Cancelled',
            'dashboard.admin.reservation_statuses.no_show' => 'No Show',
            'dashboard.admin.contract_statuses.draft' => 'Draft',
            'dashboard.admin.contract_statuses.pending' => 'Pending',
            'dashboard.admin.contract_statuses.active' => 'Active',
            'dashboard.admin.contract_statuses.completed' => 'Completed',
            'dashboard.admin.contract_statuses.cancelled' => 'Cancelled',
            'dashboard.admin.finance_statuses.no_charge' => 'No Charge',
            'dashboard.admin.finance_statuses.paid' => 'Paid',
            'dashboard.admin.finance_statuses.partial' => 'Partially Paid',
            'dashboard.admin.finance_statuses.unpaid' => 'Unpaid',
            'dashboard.admin.finance_statuses.partial_with_return_debt' => 'Partial + Return Debt',
            'dashboard.admin.finance_statuses.return_debt' => 'Return Debt',
            'dashboard.admin.contracts.index.table.contract_status' => 'Contract Status',
            'dashboard.admin.contracts.index.table.reservation_status' => 'Reservation Status',
            'dashboard.admin.contracts.index.table.finance_status' => 'Finance Status',
            'dashboard.admin.contracts.index.table.car_status' => 'Car Status',
            'dashboard.admin.contracts.index.balance' => 'Balance',
            'dashboard.admin.support.index.all_branches' => 'All branches',
            'dashboard.admin.support.index.statuses.new' => 'New',
            'dashboard.admin.support.index.statuses.in_progress' => 'In Progress',
            'dashboard.admin.support.index.statuses.closed' => 'Closed',
            'dashboard.admin.payments.index.debtors' => 'Debtors',
            'dashboard.admin.payments.index.all_branches' => 'All branches',
            'dashboard.admin.payments.index.all_statuses' => 'All statuses',
            'dashboard.admin.payments.index.converted_to' => 'Converted to',
            'dashboard.admin.payments.index.rate' => 'Rate',
            'dashboard.admin.payments.index.statuses.pending' => 'Pending',
            'dashboard.admin.payments.index.statuses.completed' => 'Completed',
            'dashboard.admin.payments.index.statuses.failed' => 'Failed',
            'dashboard.admin.payments.index.statuses.cancelled' => 'Cancelled',
            'dashboard.admin.payments.index.statuses.refunded' => 'Refunded',
            'dashboard.admin.payments.index.statuses.partially_refunded' => 'Partially Refunded',
            'dashboard.admin.payments.index.payment_methods.cash' => 'Cash',
            'dashboard.admin.payments.index.payment_methods.credit_card' => 'Credit card',
            'dashboard.admin.payments.index.payment_methods.debit_card' => 'Debit card',
            'dashboard.admin.payments.index.payment_methods.bank_transfer' => 'Bank transfer',
            'dashboard.admin.payments.index.payment_methods.paypal' => 'PayPal',
            'dashboard.admin.payments.index.payment_methods.stripe' => 'Stripe',
            'dashboard.admin.payments.index.payment_methods.myfatoorah' => 'MyFatoorah',
            'dashboard.super_admin.head_title' => 'Super Admin Dashboard',
            'dashboard.super_admin.title' => 'Super Admin Dashboard',
            'dashboard.super_admin.subtitle' => 'Manage all tenants and system-wide settings',
            'dashboard.super_admin.new_tenant' => 'New Tenant',
            'dashboard.super_admin.cards.total_tenants' => 'Total Tenants',
            'dashboard.super_admin.cards.active_tenants' => ':count active',
            'dashboard.super_admin.cards.total_users' => 'Total Users',
            'dashboard.super_admin.cards.across_all_tenants' => 'Across all tenants',
            'dashboard.super_admin.cards.total_reservations' => 'Total Reservations',
            'dashboard.super_admin.cards.all_time_bookings' => 'All-time bookings',
            'dashboard.super_admin.cards.total_revenue' => 'Total Revenue',
            'dashboard.super_admin.cards.platform_wide' => 'Platform-wide',
            'dashboard.super_admin.cards.growth_rate' => 'Growth Rate',
            'dashboard.super_admin.cards.vs_last_month' => 'vs last month',
            'dashboard.super_admin.recent_tenants.title' => 'Recent Tenants',
            'dashboard.super_admin.recent_tenants.subtitle' => 'Latest registered rental companies',
            'dashboard.super_admin.recent_tenants.view_all' => 'View all',
            'dashboard.super_admin.recent_tenants.empty' => 'No tenants registered yet',
            'dashboard.super_admin.status.active' => 'Active',
            'dashboard.super_admin.status.inactive' => 'Inactive',
            'dashboard.super_admin.users.index.user' => 'User',
            'dashboard.common.tenant' => 'Tenant',
            'dashboard.common.method' => 'Method',
            'dashboard.common.amount' => 'Amount',
            'dashboard.common.date' => 'Date',
            'dashboard.sidebar.super_admin_section' => 'Super Admin',
            'dashboard.sidebar.super_admin.dashboard' => 'Dashboard',
            'dashboard.sidebar.super_admin.revenue' => 'Revenue',
            'dashboard.sidebar.super_admin.subscription' => 'Subscription',
            'dashboard.sidebar.super_admin.transactions' => 'Transactions',
            'dashboard.sidebar.super_admin.user_management' => 'User Management',
            'dashboard.sidebar.super_admin.users' => 'Users',
            'dashboard.sidebar.super_admin.roles' => 'Roles',
            'dashboard.sidebar.super_admin.tenants' => 'Tenants',
            'dashboard.sidebar.super_admin.product_management' => 'Product Management',
            'dashboard.sidebar.super_admin.plans' => 'Plans',
            'dashboard.sidebar.super_admin.discounts' => 'Discounts',
            'dashboard.sidebar.super_admin.cars' => 'Cars',
            'dashboard.sidebar.super_admin.cars_description' => 'All cars with tenant name',
            'dashboard.sidebar.super_admin.reservations' => 'Reservations',
            'dashboard.sidebar.super_admin.settings' => 'Settings',
            'dashboard.sidebar.super_admin.general_settings' => 'General Settings',
            'dashboard.sidebar.super_admin.landing_translations' => 'Landing Translations',
            'navigation.nav_clients' => 'Clients',
            'navigation.nav_contact' => 'Contact',
            'static_pages.privacy_policy.title' => 'Privacy Policy',
            'static_pages.terms_of_use.title' => 'Terms of Use',
            'static_pages.security_policy.title' => 'Security Policy',
            'validation.letters_only' => 'This field must contain letters only and cannot include numbers.',
            'validation.password.mixed' => 'The password field must contain at least one uppercase and one lowercase letter.',
            'validation.confirmed' => 'The password field confirmation does not match.',
            'owner_api.errors.reports_module_not_available' => 'Your current plan does not include access to AI reports.',
            'owner_api.errors.auto_discounts_not_available' => 'Your current plan does not include access to auto discounts.',
            
            // Car Photo History
            'dashboard.admin.cars.photo_history.edit_record' => 'Edit Record',
            'dashboard.admin.cars.photo_history.new_record' => 'New Record',
            'dashboard.admin.cars.photo_history.back_to_history' => 'Back to History',
            'dashboard.admin.cars.photo_history.reason' => 'Reason',
            'dashboard.admin.cars.photo_history.select_reason' => 'Select Reason',
            'dashboard.admin.cars.photo_history.reason_before_delivery' => 'Before Delivery',
            'dashboard.admin.cars.photo_history.reason_after_return' => 'After Return',
            'dashboard.admin.cars.photo_history.reason_new_damage' => 'New Damage',
            'dashboard.admin.cars.photo_history.reason_after_cleaning' => 'After Cleaning',
            'dashboard.admin.cars.photo_history.reason_after_maintenance' => 'After Maintenance',
            'dashboard.admin.cars.photo_history.photos' => 'Photos',
            'dashboard.admin.cars.photo_history.notes_optional' => 'Notes (Optional)',
            'dashboard.admin.cars.photo_history.notes_placeholder' => 'Enter notes here...',
            'dashboard.admin.cars.photo_history.cancel' => 'Cancel',
            'dashboard.admin.cars.photo_history.save' => 'Save',

            // Car Expiry & Renewal
            'dashboard.admin.cars.renewal_required' => 'Renewal Required',
            'dashboard.admin.cars.license_renewal_required' => 'License Renewal Required',
            'dashboard.admin.cars.insurance_renewal_required' => 'Insurance Renewal Required',
            'dashboard.admin.cars.license_insurance_renewal_required' => 'License & Insurance Renewal Required',
            'dashboard.admin.cars.form.license_expiry_date' => 'License Expiry Date',
            'dashboard.admin.cars.form.insurance_expiry_date' => 'Insurance Expiry Date',

            // Car Show Values
            'dashboard.admin.cars.show.values.diesel' => 'Diesel',
            'dashboard.admin.cars.show.values.gasoline' => 'Gasoline',
            'dashboard.admin.cars.show.values.gas' => 'Gas',
            'dashboard.admin.cars.show.values.hybrid' => 'Hybrid',
            'dashboard.admin.cars.show.values.electric' => 'Electric',
            'dashboard.admin.cars.show.values.plug_in_hybrid' => 'Plug-in Hybrid',
            'dashboard.admin.cars.show.values.lpg' => 'LPG',
            'dashboard.admin.cars.show.values.cng' => 'CNG',
            'dashboard.admin.cars.show.values.hydrogen' => 'Hydrogen',
            'dashboard.admin.cars.show.values.automatic' => 'Automatic',
            'dashboard.admin.cars.show.values.manual' => 'Manual',
            'dashboard.admin.cars.show.values.white' => 'White',
            'dashboard.admin.cars.show.values.black' => 'Black',
            'dashboard.admin.cars.show.values.gray' => 'Gray',
            'dashboard.admin.cars.show.values.silver' => 'Silver',
            'dashboard.admin.cars.show.values.red' => 'Red',
            'dashboard.admin.cars.show.values.blue' => 'Blue',
            'dashboard.admin.cars.show.values.champagne' => 'Champagne',
            'dashboard.admin.cars.show.values.beige' => 'Beige',
            'dashboard.admin.cars.show.values.brown' => 'Brown',
            'dashboard.admin.cars.show.values.green' => 'Green',
            'dashboard.admin.cars.show.values.gold' => 'Gold',
            'dashboard.admin.cars.show.values.orange' => 'Orange',
            'dashboard.admin.cars.show.values.yellow' => 'Yellow',
            'dashboard.admin.cars.show.values.navy' => 'Navy',
            'dashboard.admin.cars.show.values.burgundy' => 'Burgundy',
            'dashboard.admin.cars.show.values.purple' => 'Purple',
            'dashboard.admin.cars.show.values.pink' => 'Pink',
            'dashboard.admin.cars.show.values.cyan' => 'Cyan',
            'dashboard.admin.cars.show.values.bronze' => 'Bronze',
            'dashboard.admin.cars.show.values.teal' => 'Teal',
            'dashboard.admin.cars.show.values.olive' => 'Olive',
            'dashboard.admin.cars.show.values.maroon' => 'Maroon',
            'dashboard.admin.cars.show.values.indigo' => 'Indigo',
            'dashboard.admin.cars.show.values.magenta' => 'Magenta',
            'dashboard.admin.cars.show.values.draft' => 'Draft',
            'dashboard.admin.cars.show.values.available' => 'Available',
            'dashboard.admin.cars.show.values.reserved' => 'Reserved',
            'dashboard.admin.cars.show.values.rented' => 'Rented',
            'dashboard.admin.cars.show.values.maintenance' => 'Maintenance',
            'dashboard.admin.cars.show.values.cleaning' => 'Cleaning',
            'dashboard.admin.cars.show.values.unavailable' => 'Unavailable',
            'dashboard.admin.cars.show.values.retired' => 'Retired',
            'dashboard.admin.cars.show.values.inside' => 'Inside',
            'dashboard.admin.cars.show.values.license' => 'Car License',
            'dashboard.admin.cars.show.values.insurance' => 'Car Insurance',
            'dashboard.admin.cars.show.values.purchase_contract' => 'Purchase Contract',
            'dashboard.admin.cars.show.values.expiring_soon' => 'Expiring Soon',
            'dashboard.admin.cars.show.values.confirmed' => 'Confirmed',
            'dashboard.admin.cars.show.values.completed_wait_contract' => 'Completed - Waiting for Contract',
            'dashboard.admin.cars.show.values.no_show' => 'No Show',
            'dashboard.admin.cars.show.values.left_front_door' => 'Left Front Door',
            'dashboard.admin.cars.show.values.left_rear_door' => 'Left Rear Door',
            'dashboard.admin.cars.show.values.right_front_door' => 'Right Front Door',
            'dashboard.admin.cars.show.values.right_rear_door' => 'Right Rear Door',
            'dashboard.admin.cars.show.values.front_bumper' => 'Front Bumper',
            'dashboard.admin.cars.show.values.rear_bumper' => 'Rear Bumper',
            'dashboard.admin.cars.show.values.hood' => 'Hood',
            'dashboard.admin.cars.show.values.roof' => 'Roof',
            'dashboard.admin.cars.show.values.trunk' => 'Trunk',
            'dashboard.admin.cars.show.values.windshield' => 'Windshield',
            'dashboard.admin.cars.show.values.scratch' => 'Scratch',
            'dashboard.admin.cars.show.values.dent' => 'Dent',
            'dashboard.admin.cars.show.values.crack' => 'Crack',
            'dashboard.admin.cars.show.values.chip' => 'Chip',
            'dashboard.admin.cars.show.values.stain' => 'Stain',
            'dashboard.admin.cars.show.values.broken' => 'Broken',
            'dashboard.admin.cars.show.values.missing' => 'Missing',
            'dashboard.admin.cars.show.values.scuff' => 'Scuff',
            'dashboard.admin.cars.show.values.in_progress' => 'In Progress',
            'dashboard.admin.cars.show.values.paid' => 'Paid',
            'dashboard.admin.cars.show.values.disputed' => 'Disputed',
            'dashboard.admin.cars.show.values.opened' => 'Opened',
            'dashboard.admin.cars.show.values.waiting_parts' => 'Waiting for Parts',
            'dashboard.admin.cars.show.values.closed' => 'Closed',
            'dashboard.admin.cars.form.mask' => 'Mask',
            'dashboard.admin.cars.form.example' => 'Example',

            // Discount Requests
            'dashboard.admin.discount_requests.index.approve_this_discount_request' => 'Approve this discount request?',
            'dashboard.admin.discount_requests.index.rejection_note' => 'Rejection note',
            'dashboard.admin.discount_requests.index.discount_requests' => 'Discount Requests',
            'dashboard.admin.discount_requests.index.review_employee_discount_requests_before_collection' => 'Review employee discount requests before collection.',
            'dashboard.admin.discount_requests.index.search_reservation_client_employee' => 'Search reservation, client, employee...',
            'dashboard.admin.discount_requests.index.all_statuses' => 'All statuses',
            'dashboard.admin.discount_requests.index.all_branches' => 'All branches',
            'dashboard.admin.discount_requests.index.search' => 'Search',
            'dashboard.admin.discount_requests.index.clear' => 'Clear',
            'dashboard.admin.discount_requests.index.request' => 'Request',
            'dashboard.admin.discount_requests.index.customer' => 'Customer',
            'dashboard.admin.discount_requests.index.employee' => 'Employee',
            'dashboard.admin.discount_requests.index.amounts' => 'Amounts',
            'dashboard.admin.discount_requests.index.reason' => 'Reason',
            'dashboard.admin.discount_requests.index.status' => 'Status',
            'dashboard.admin.discount_requests.index.actions' => 'Actions',
            'dashboard.admin.discount_requests.index.remaining' => 'Remaining',
            'dashboard.admin.discount_requests.index.requested' => 'Requested',
            'dashboard.admin.discount_requests.index.discount' => 'Discount',
            'dashboard.admin.discount_requests.index.after' => 'After',
            'dashboard.admin.discount_requests.index.review_note' => 'Review note',
            'dashboard.admin.discount_requests.index.previous_approved_discounts' => 'Previous approved discounts',
            'dashboard.admin.discount_requests.index.approved_at' => 'Approved at',
            'dashboard.admin.discount_requests.index.approve' => 'Approve',
            'dashboard.admin.discount_requests.index.reject' => 'Reject',
            'dashboard.admin.discount_requests.index.no_discount_requests_found' => 'No discount requests found.',
            'dashboard.admin.discount_requests.statuses.pending' => 'Pending decision',
            'dashboard.admin.discount_requests.statuses.approved' => 'Approved',
            'dashboard.admin.discount_requests.statuses.rejected' => 'Rejected',
            'dashboard.admin.discount_requests.statuses.cancelled' => 'Cancelled',

            // Client Documents
            'dashboard.admin.clients.documents.title' => 'Client Documents',
            'dashboard.admin.clients.documents.back_to_client' => 'Back To Client',
            'dashboard.admin.clients.documents.document_file' => 'Document File',
            'dashboard.admin.clients.documents.run_ocr_extraction' => 'Run OCR Extraction',
            'dashboard.admin.clients.documents.extracting' => 'Extracting...',
            'dashboard.admin.clients.documents.apply_extracted_to_all_fields' => 'Apply Extracted To All Fields',
            'dashboard.admin.clients.documents.save_document' => 'Save Document',
            'dashboard.admin.clients.documents.saving' => 'Saving...',
            'dashboard.admin.clients.documents.document_saved' => 'Document saved.',
            'dashboard.admin.clients.documents.document_save_failed' => 'Document save failed. Check the fields and try again.',
            'dashboard.admin.clients.documents.upload_file_first' => 'Upload a file first, then run extraction.',
            'dashboard.admin.clients.documents.extraction_completed' => 'Document extraction completed.',
            'dashboard.admin.clients.documents.extraction_failed' => 'Document extraction failed.',
            'dashboard.admin.clients.documents.extraction_request_failed' => 'Document extraction request failed.',
            'dashboard.admin.clients.documents.raw_ocr_output' => 'Raw OCR Output',
            'dashboard.admin.clients.documents.raw_text' => 'Raw Text',
            'dashboard.admin.clients.documents.json' => 'JSON',
            'dashboard.admin.clients.documents.no_ocr_text_yet' => 'No OCR text yet.',
            'dashboard.admin.clients.documents.confidence' => 'Confidence',
            'dashboard.admin.clients.documents.local_ocr_enabled' => 'enabled',
            'dashboard.admin.clients.documents.local_ocr_disabled' => 'disabled',
            'dashboard.admin.clients.documents.local_ocr_status' => 'Local OCR is :status.',
            'dashboard.admin.clients.documents.python_binary' => 'Python binary',
            'dashboard.admin.clients.documents.extraction_status.reviewed' => 'Reviewed',
            'dashboard.admin.clients.documents.extraction_status.completed' => 'Completed',
            'dashboard.admin.clients.documents.extraction_status.failed' => 'Failed',
            'dashboard.admin.clients.documents.extraction_status.pending' => 'Pending',
            'dashboard.admin.clients.documents.statuses.not_requested' => 'Not Requested',
            'dashboard.admin.clients.documents.statuses.completed' => 'Completed',
            'dashboard.admin.clients.documents.statuses.failed' => 'Failed',
            'dashboard.admin.clients.documents.statuses.reviewed' => 'Reviewed',
            'dashboard.admin.clients.documents.types.driver_license_front.label' => 'Driver License (Front)',
            'dashboard.admin.clients.documents.types.driver_license_front.description' => 'Front side of the driving license.',
            'dashboard.admin.clients.documents.types.driver_license_back.label' => 'Driver License (Back)',
            'dashboard.admin.clients.documents.types.driver_license_back.description' => 'Back side of the driving license.',
            'dashboard.admin.clients.documents.types.id_card_front.label' => 'ID Card (Front)',
            'dashboard.admin.clients.documents.types.id_card_front.description' => 'Front side of the national ID card.',
            'dashboard.admin.clients.documents.types.id_card_back.label' => 'ID Card (Back)',
            'dashboard.admin.clients.documents.types.id_card_back.description' => 'Back side of the national ID card.',
            'dashboard.admin.clients.documents.types.passport.label' => 'Passport',
            'dashboard.admin.clients.documents.types.passport.description' => 'Passport document.',
            'dashboard.admin.clients.documents.fields.document_number' => 'Document Number',
            'dashboard.admin.clients.documents.fields.full_name' => 'Full Name',
            'dashboard.admin.clients.documents.fields.date_of_birth' => 'Date Of Birth',
            'dashboard.admin.clients.documents.fields.expiry_date' => 'Expiry Date',
            'dashboard.admin.clients.documents.fields.issue_date' => 'Issue Date',
            'dashboard.admin.clients.documents.fields.nationality' => 'Nationality',
            'dashboard.admin.clients.documents.fields.license_class' => 'License Class',
            'dashboard.admin.clients.documents.fields.address' => 'Address',
            'dashboard.admin.clients.documents.fields.place_of_issue' => 'Place Of Issue',

            // Client show status flags
            'dashboard.admin.clients.show.overall_statuses.good' => 'Good',
            'dashboard.admin.clients.show.overall_statuses.info' => 'Info',
            'dashboard.admin.clients.show.overall_statuses.warning' => 'Needs review',
            'dashboard.admin.clients.show.overall_statuses.danger' => 'Blocked',
            'dashboard.admin.clients.show.flags.types.blocked' => 'Blocked',
            'dashboard.admin.clients.show.flags.types.needs_review' => 'Needs review',
            'dashboard.admin.clients.show.flags.types.debt' => 'Debtor',
            'dashboard.admin.clients.show.flags.types.expired_license' => 'Expired license',
            'dashboard.admin.clients.show.flags.types.expired_passport' => 'Expired passport',
            'dashboard.admin.clients.show.flags.types.expired_residency' => 'Expired residency',
            'dashboard.admin.clients.show.flags.types.expired_document' => 'Expired document',
            'dashboard.admin.clients.show.flags.types.late_return' => 'Late return history',
            'dashboard.admin.clients.show.flags.types.new_customer' => 'New customer',
            'dashboard.admin.clients.show.flags.descriptions.blocked' => 'This client is manually blocked.',
            'dashboard.admin.clients.show.flags.descriptions.needs_review' => 'This client needs manual review.',
            'dashboard.admin.clients.show.flags.descriptions.debt' => 'Outstanding amount: :amount.',
            'dashboard.admin.clients.show.flags.descriptions.expired_license' => 'The license expired on :date.',
            'dashboard.admin.clients.show.flags.descriptions.expired_passport' => 'The passport expired on :date.',
            'dashboard.admin.clients.show.flags.descriptions.expired_residency' => 'The residency expired on :date.',
            'dashboard.admin.clients.show.flags.descriptions.expired_document' => 'A client document expired on :date.',
            'dashboard.admin.clients.show.flags.descriptions.late_return' => 'Last late return: :contract at :date.',
            'dashboard.admin.clients.show.flags.descriptions.new_customer' => 'No completed contracts yet.',
            'dashboard.admin.clients.show.flag.types.blocked' => 'Blocked',
            'dashboard.admin.clients.show.flag.types.needs_review' => 'Needs review',
            'dashboard.admin.clients.show.flag.types.debt' => 'Debtor',
            'dashboard.admin.clients.show.flag.types.expired_license' => 'Expired license',
            'dashboard.admin.clients.show.flag.types.expired_passport' => 'Expired passport',
            'dashboard.admin.clients.show.flag.types.expired_residency' => 'Expired residency',
            'dashboard.admin.clients.show.flag.types.expired_document' => 'Expired document',
            'dashboard.admin.clients.show.flag.types.late_return' => 'Late return history',
            'dashboard.admin.clients.show.flag.types.new_customer' => 'New customer',
            'dashboard.admin.clients.show.flag.descriptions.blocked' => 'This client is manually blocked.',
            'dashboard.admin.clients.show.flag.descriptions.needs_review' => 'This client needs manual review.',
            'dashboard.admin.clients.show.flag.descriptions.debt' => 'Outstanding amount: :amount.',
            'dashboard.admin.clients.show.flag.descriptions.expired_license' => 'The license expired on :date.',
            'dashboard.admin.clients.show.flag.descriptions.expired_passport' => 'The passport expired on :date.',
            'dashboard.admin.clients.show.flag.descriptions.expired_residency' => 'The residency expired on :date.',
            'dashboard.admin.clients.show.flag.descriptions.expired_document' => 'A client document expired on :date.',
            'dashboard.admin.clients.show.flag.descriptions.late_return' => 'Last late return: :contract at :date.',
            'dashboard.admin.clients.show.flag.descriptions.new_customer' => 'No completed contracts yet.',

            // Stripe Connect
            'dashboard.admin.stripe_connect.title' => 'Stripe Connect',
            'dashboard.admin.stripe_connect.description' => 'Connect this tenant to Stripe so client bookings can be paid online.',
            'dashboard.admin.stripe_connect.connection_status' => 'Connection Status',
            'dashboard.admin.stripe_connect.tenant' => 'Tenant',
            'dashboard.admin.stripe_connect.stripe_account_id' => 'Stripe Account ID',
            'dashboard.admin.stripe_connect.not_connected' => 'Not connected',
            'dashboard.admin.stripe_connect.charges_enabled' => 'Charges Enabled',
            'dashboard.admin.stripe_connect.payouts_enabled' => 'Payouts Enabled',
            'dashboard.admin.stripe_connect.details_submitted' => 'Details Submitted',
            'dashboard.admin.stripe_connect.default_currency' => 'Default Currency',
            'dashboard.admin.stripe_connect.not_set' => 'Not set',
            'dashboard.admin.stripe_connect.actions' => 'Actions',
            'dashboard.admin.stripe_connect.platform_stripe' => 'Platform Stripe',
            'dashboard.admin.stripe_connect.configured' => 'Configured',
            'dashboard.admin.stripe_connect.not_configured' => 'Not configured',
            'dashboard.admin.stripe_connect.checkout_ready' => 'Checkout Ready',
            'dashboard.admin.stripe_connect.ready_for_booking_payments' => 'Ready for booking payments',
            'dashboard.admin.stripe_connect.not_ready_yet' => 'Not ready yet',
            'dashboard.admin.stripe_connect.connect_stripe' => 'Connect Stripe',
            'dashboard.admin.stripe_connect.continue_stripe_onboarding' => 'Continue Stripe Onboarding',
            'dashboard.admin.stripe_connect.refresh_onboarding_link' => 'Refresh Onboarding Link',
            'dashboard.admin.stripe_connect.open_stripe_express_dashboard' => 'Open Stripe Express Dashboard',
            'dashboard.admin.stripe_connect.yes' => 'Yes',
            'dashboard.admin.stripe_connect.no' => 'No',

            // Payment Providers
            'dashboard.admin.settings.payment_providers.global_card_payments_stripe_connect_can_be_used_for_supported_countries' => 'Global card payments. Stripe Connect can be used for supported countries.',
            'dashboard.admin.settings.payment_providers.strong_gcc_coverage_good_option_for_oman_and_mena_hosted_checkout' => 'Strong GCC coverage. Good option for Oman and MENA hosted checkout.',
            'dashboard.admin.settings.payment_providers.webhook_secret_optional' => 'Webhook Secret (Optional)',
            'dashboard.admin.settings.payment_providers.this_will_be_used_by_tenant_booking_checkout_when_multiple_tenant_providers_are_enabled' => 'This will be used by tenant booking checkout when multiple tenant providers are enabled.',
            'dashboard.admin.settings.payment_providers.uses_stripe_connect_manage_onboarding_and_account_status_in_the_stripe_connect_page' => 'Uses Stripe Connect. Manage onboarding and account status in the Stripe Connect page.',
            'dashboard.admin.settings.payment_providers.use_a_valid_myfatoorah_method_id_example_visa_mastercard_we_can_remove_this_later_when_boo' => 'Use a valid MyFatoorah method ID (example: Visa/Mastercard). We can remove this later when booking methods are loaded dynamically.',
            'dashboard.admin.settings.payment_providers.use_only_if_you_need_overrides_or_a_fixed_default_method' => 'Use only if you need overrides or a fixed default method.',

            // Reservation / Contract outstanding balance notice
            'dashboard.admin.contracts.edit.client_has_outstanding_balance' => 'Client has outstanding balance',
            'dashboard.admin.contracts.edit.client_has_outstanding_balance_amount_admin_can_continue' => 'Client has outstanding balance (:amount). Admin can continue creating the contract if approved.',

            // Reservation show pricing label
            'dashboard.admin.reservations.show.fields.daily_rate' => 'Daily Rate',
            'dashboard.admin.reservations.show.fields.weekly_rate' => 'Weekly Rate',
            'dashboard.admin.reservations.show.fields.monthly_rate' => 'Monthly Rate',
            'client_pages.reservations.show.fields.daily_rate' => 'Daily Rate',
            'client_pages.reservations.show.fields.weekly_rate' => 'Weekly Rate',
            'client_pages.reservations.show.fields.monthly_rate' => 'Monthly Rate',

            // Return status report dynamic options
            'dashboard.admin.contracts.return_status_report.choose_files' => 'Choose Files',
            'dashboard.admin.contracts.return_status_report.no_file_chosen' => 'No file chosen',
            'dashboard.admin.contracts.return_status_report.1_file_chosen' => '1 file chosen',
            'dashboard.admin.contracts.return_status_report.files_chosen' => ':count files chosen',
            'dashboard.admin.contracts.return_status_report.fuel_levels.empty' => 'Empty',
            'dashboard.admin.contracts.return_status_report.fuel_levels.quarter' => '1/4 Tank',
            'dashboard.admin.contracts.return_status_report.fuel_levels.half' => '1/2 Tank',
            'dashboard.admin.contracts.return_status_report.fuel_levels.three_quarters' => '3/4 Tank',
            'dashboard.admin.contracts.return_status_report.fuel_levels.full' => 'Full',
            'dashboard.admin.contracts.return_status_report.vehicle_conditions.clean' => 'Clean',
            'dashboard.admin.contracts.return_status_report.vehicle_conditions.not_clean' => 'Not Clean',
            'dashboard.admin.contracts.return_status_report.statuses.pending' => 'Pending',
            'dashboard.admin.contracts.return_status_report.statuses.completed' => 'Completed',
            'dashboard.admin.contracts.return_status_report.statuses.paid' => 'Paid',
            'dashboard.admin.contracts.return_status_report.statuses.partial' => 'Partial',
            'dashboard.admin.contracts.return_status_report.statuses.not_paid' => 'Not Paid',
        ]);

        foreach ($this->translationGroups() as $group) {
            $translations = trans($group, [], $locale);

            if (!is_array($translations)) {
                $translations = trans($group, [], config('app.fallback_locale', 'en'));
            }

            if (!is_array($translations)) {
                foreach ($this->supportedLocaleKeys() as $supportedLocale) {
                    $translations = trans($group, [], $supportedLocale);

                    if (is_array($translations)) {
                        break;
                    }
                }
            }

            if (is_array($translations)) {
                $rows = array_merge(
                    $rows,
                    $group === 'site'
                        ? $this->flatten($translations)
                        : $this->flatten([$group => $translations])
                );
            }
        }

        return $rows;
    }

    private function normalizedTranslationRows(array $rows): array
    {
        $normalized = [];

        foreach ($this->flatten($rows) as $key => $value) {
            $normalized[str_starts_with($key, 'site.') ? Str::after($key, 'site.') : $key] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function translationGroups(): array
    {
        $files = glob(lang_path('*/*.php')) ?: [];

        return collect($files)
            ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->filter(static fn (string $group): bool => $group !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, scalar|null>
     */
    private function siteDashboardTranslationRows(string $locale): array
    {
        $path = lang_path("{$locale}/site.php");

        if (!is_file($path)) {
            $path = lang_path('en/site.php');
        }

        $translations = require $path;
        $dashboard = is_array($translations) ? (array) ($translations['dashboard'] ?? []) : [];
        $sidebar = (array) ($dashboard['sidebar'] ?? []);

        return $this->flatten([
            'dashboard' => [
                'common' => (array) ($dashboard['common'] ?? []),
                'notifications' => (array) ($dashboard['notifications'] ?? []),
                'super_admin' => (array) ($dashboard['super_admin'] ?? []),
                'sidebar' => [
                    'super_admin_section' => $sidebar['super_admin_section'] ?? null,
                    'super_admin' => (array) ($sidebar['super_admin'] ?? []),
                ],
            ],
        ]);
    }

    private function persistLandingSettings(array $settings): SiteSetting
    {
        $current = $this->landingSettings();
        $merged = array_replace_recursive($current, $settings);

        foreach (LandingPageSettings::replaceableListPaths() as $path) {
            $value = data_get($settings, $path);
            if (is_array($value)) {
                data_set($merged, $path, $value);
            }
        }

        if (array_key_exists('translations', $settings)) {
            $merged['translations'] = $settings['translations'];
        }

        return SiteSetting::query()->updateOrCreate(
            ['key' => LandingPageSettings::KEY],
            ['value' => LandingPageSettings::normalize($merged)]
        );
    }

    private function logLandingUploadRequest(Request $request, string $event): void
    {
        $summarize = function ($val) use (&$summarize) {
            if (is_array($val)) {
                return collect($val)->map(fn ($item) => $summarize($item))->all();
            }
            if ($val instanceof UploadedFile) {
                return ['type' => 'UploadedFile', 'name' => $val->getClientOriginalName(), 'size' => $val->getSize()];
            }
            if (is_string($val)) {
                return ['type' => 'string', 'length' => strlen($val), 'preview' => substr($val, 0, 50)];
            }
            return ['type' => get_debug_type($val)];
        };

        Log::info('Landing design upload request: ' . $event, [
            'method' => $request->method(),
            'content_type' => $request->headers->get('content-type'),
            'content_length' => $request->headers->get('content-length'),
            'raw_files_superglobal' => $_FILES,
            'input_summary' => $summarize($request->all()),
            'has_files' => $request->hasFile('hero_direct_file')
                || $request->hasFile('hero_locale_direct_files')
                || $request->hasFile('feature_card_direct_files')
                || $request->hasFile('mobile_app_direct_files'),
            'all_file_keys' => array_keys($request->allFiles()),
            'files' => $this->summarizeUploadedFiles($request->allFiles()),
            'has_hero_direct_file_input' => $request->has('hero_direct_file'),
            'has_hero_direct_file_file' => $request->hasFile('hero_direct_file'),
            'hero_direct_file_input_type' => get_debug_type($request->input('hero_direct_file')),
            'hero_locale_direct_file_keys' => array_keys((array) $request->input('hero_locale_direct_files', [])),
            'feature_card_direct_file_keys' => array_keys((array) $request->input('feature_card_direct_files', [])),
            'getting_started_direct_file_keys' => array_keys((array) $request->input('getting_started_direct_files', [])),
            'mobile_app_direct_file_keys' => array_keys((array) $request->input('mobile_app_direct_files', [])),
            'hero_temp_folders' => $request->input('hero_temp_folders', []),
            'hero_locale_temp_folders' => $request->input('hero_locale_temp_folders', []),
            'feature_card_temp_folders' => $request->input('feature_card_temp_folders', []),
            'getting_started_temp_folders' => $request->input('getting_started_temp_folders', []),
            'mobile_app_temp_folders' => $request->input('mobile_app_temp_folders', []),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_file_uploads' => ini_get('max_file_uploads'),
        ]);
    }

    private function summarizeUploadedFiles(array $files): array
    {
        $summary = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFile) {
                $summary[$key] = [
                    'original_name' => $value->getClientOriginalName(),
                    'mime_type' => $value->getMimeType(),
                    'client_mime_type' => $value->getClientMimeType(),
                    'size' => $value->getSize(),
                    'is_valid' => $value->isValid(),
                    'error' => $value->getError(),
                    'error_message' => $value->getErrorMessage(),
                ];
                continue;
            }

            if (is_array($value)) {
                $summary[$key] = $this->summarizeUploadedFiles($value);
            }
        }

        return $summary;
    }

    private function refreshLandingImageUrls(SiteSetting $landingSetting): void
    {
        $settings = $this->hydrateLandingImageUrls(
            LandingPageSettings::normalize(is_array($landingSetting->value) ? $landingSetting->value : null),
            $landingSetting
        );

        $landingSetting->update(['value' => $settings]);
    }

    private function hydrateLandingImageUrls(array $settings, ?SiteSetting $landingSetting): array
    {
        if (!$landingSetting) {
            return $settings;
        }

        $heroUrl = $this->latestLandingFileUrl($landingSetting, 'hero');
        if ($heroUrl && trim((string) data_get($settings, 'hero.image_url', '')) === '') {
            data_set($settings, 'hero.image_url', $heroUrl);
        }

        $fleetButtonIconUrl = $this->latestLandingFileUrl($landingSetting, 'cars_fleet_button_icon');
        if ($fleetButtonIconUrl && trim((string) data_get($settings, 'cars_section.fleet_button_icon_url', '')) === '') {
            data_set($settings, 'cars_section.fleet_button_icon_url', $fleetButtonIconUrl);
        }

        $androidIconUrl = $this->latestLandingFileUrl($landingSetting, 'footer_android_icon');
        if ($androidIconUrl && trim((string) data_get($settings, 'footer.android_icon_url', '')) === '') {
            data_set($settings, 'footer.android_icon_url', $androidIconUrl);
        }

        $iosIconUrl = $this->latestLandingFileUrl($landingSetting, 'footer_ios_icon');
        if ($iosIconUrl && trim((string) data_get($settings, 'footer.ios_icon_url', '')) === '') {
            data_set($settings, 'footer.ios_icon_url', $iosIconUrl);
        }

        $localizedImages = (array) data_get($settings, 'hero.localized_images', []);
        foreach ($this->supportedLocaleKeys() as $locale) {
            $localeUrl = $this->latestLandingFileUrl($landingSetting, $this->heroLocaleCollection($locale));
            if ($localeUrl && trim((string) ($localizedImages[$locale] ?? '')) === '') {
                $localizedImages[$locale] = $localeUrl;
            }
        }
        data_set($settings, 'hero.localized_images', $localizedImages);

        $cards = (array) data_get($settings, 'features_section.cards', []);
        foreach ($cards as $index => $card) {
            if (!is_array($card)) {
                continue;
            }

            $url = $this->latestLandingFileUrl($landingSetting, $this->featureCardCollection((int) $index));
            if ($url && trim((string) ($card['image_url'] ?? '')) === '') {
                $cards[$index]['image_url'] = $url;
            }
        }
        data_set($settings, 'features_section.cards', $cards);

        $steps = (array) data_get($settings, 'getting_started.items', []);
        foreach ($steps as $index => $step) {
            if (!is_array($step)) {
                continue;
            }

            $url = $this->latestLandingFileUrl($landingSetting, $this->gettingStartedStepCollection((int) $index));
            if ($url && trim((string) ($step['image_url'] ?? '')) === '') {
                $steps[$index]['image_url'] = $url;
            }
        }
        data_set($settings, 'getting_started.items', $steps);

        $apps = (array) data_get($settings, 'mobile_apps_section.apps', []);
        foreach ($apps as $index => $app) {
            if (!is_array($app)) {
                continue;
            }

            $imageUrl = $this->latestLandingFileUrl($landingSetting, $this->mobileAppCollection((int) $index, 'image'));
            if ($imageUrl && trim((string) ($app['image_url'] ?? '')) === '') {
                $apps[$index]['image_url'] = $imageUrl;
            }

            $localizedImages = is_array($app['localized_images'] ?? null) ? $app['localized_images'] : [];
            foreach ($this->supportedLocaleKeys() as $locale) {
                $localizedImageUrl = $this->latestLandingFileUrl(
                    $landingSetting,
                    $this->mobileAppLocaleImageCollection((int) $index, $locale)
                );
                if ($localizedImageUrl && trim((string) ($localizedImages[$locale] ?? '')) === '') {
                    $localizedImages[$locale] = $localizedImageUrl;
                }
            }
            $apps[$index]['localized_images'] = $localizedImages;

            $iconUrl = $this->latestLandingFileUrl($landingSetting, $this->mobileAppCollection((int) $index, 'icon'));
            if ($iconUrl && trim((string) ($app['icon_url'] ?? '')) === '') {
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

    private function storeDirectLandingFile(
        SiteSetting $landingSetting,
        UploadedFile $file,
        string $collection
    ): string {
        Log::info('Landing direct file upload started.', [
            'site_setting_id' => $landingSetting->id,
            'collection' => $collection,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'client_mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'is_valid' => $file->isValid(),
            'error' => $file->getError(),
            'error_message' => $file->getErrorMessage(),
        ]);

        $landingSetting->files()
            ->where('collection', $collection)
            ->get()
            ->each
            ->delete();

        $modelName = strtolower(class_basename($landingSetting));
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = $modelName . '_' . $landingSetting->id . '_' . Str::uuid() . '.' . $extension;
        $path = config('vilt-filepond.files_path') . '/' . $modelName . '/' . $landingSetting->id . '/' . $collection;
        $storedPath = $file->storeAs($path, $filename, config('vilt-filepond.storage_disk'));

        if (!$storedPath) {
            Log::error('Landing direct file upload failed to store file.', [
                'site_setting_id' => $landingSetting->id,
                'collection' => $collection,
                'path' => $path,
                'filename' => $filename,
                'disk' => config('vilt-filepond.storage_disk'),
            ]);

            return '';
        }

        $fileRecord = $landingSetting->files()->create([
            'original_name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'path' => 'storage/' . $storedPath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'collection' => $collection,
            'order' => 0,
        ]);

        $url = SiteSetting::publicUrlFromPath($fileRecord->path) ?? '';

        Log::info('Landing direct file upload stored.', [
            'site_setting_id' => $landingSetting->id,
            'file_id' => $fileRecord->id,
            'collection' => $collection,
            'stored_path' => $storedPath,
            'database_path' => $fileRecord->path,
            'public_url' => $url,
            'disk' => config('vilt-filepond.storage_disk'),
            'exists' => Storage::disk(config('vilt-filepond.storage_disk'))->exists($storedPath),
        ]);

        return $url;
    }

    private function syncHeroImageUpload(Request $request, SiteSetting $landingSetting): void
    {
        $tempFolders = is_array($request->input('hero_temp_folders', []))
            ? array_values(array_filter($request->input('hero_temp_folders', [])))
            : [];
        $removedIds = is_array($request->input('hero_removed_files', []))
            ? array_values(array_unique(array_filter($request->input('hero_removed_files', []))))
            : [];
        $directFile = $request->file('hero_direct_file');

        if ($directFile instanceof UploadedFile) {
            $heroImageUrl = $this->storeDirectLandingFile($landingSetting, $directFile, 'hero');
            $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
            data_set($settings, 'hero.image_url', $heroImageUrl);
        } elseif (!empty($tempFolders)) {
            $existingIds = $landingSetting->files()->where('collection', 'hero')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));

            $this->filePondService->handleFileUpdates(
                $landingSetting,
                $tempFolders,
                $removedIds,
                'hero'
            );
            $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
            $heroImageUrl = trim((string) data_get($settings, 'hero.image_url', ''));
            $heroFile = $landingSetting->files()
                ->where('collection', 'hero')
                ->latest('id')
                ->first();

            $heroImageUrl = $heroFile
                ? (SiteSetting::publicUrlFromPath($heroFile->path) ?? '')
                : $heroImageUrl;
            data_set($settings, 'hero.image_url', $heroImageUrl);
        } elseif (!empty($removedIds)) {
            $this->filePondService->handleFileUpdates(
                $landingSetting,
                [],
                $removedIds,
                'hero'
            );
            $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
            data_set($settings, 'hero.image_url', '');
        } else {
            $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        }

        $localizedImages = (array) data_get($settings, 'hero.localized_images', []);
        $tempFoldersByLocale = is_array($request->input('hero_locale_temp_folders', []))
            ? $request->input('hero_locale_temp_folders', [])
            : [];
        $removedIdsByLocale = is_array($request->input('hero_locale_removed_files', []))
            ? $request->input('hero_locale_removed_files', [])
            : [];

        foreach ($this->supportedLocaleKeys() as $locale) {
            $collection = $this->heroLocaleCollection($locale);
            $localeTempFolders = is_array($tempFoldersByLocale[$locale] ?? null)
                ? array_values(array_filter($tempFoldersByLocale[$locale]))
                : [];
            $localeRemovedIds = is_array($removedIdsByLocale[$locale] ?? null)
                ? array_values(array_unique(array_filter($removedIdsByLocale[$locale])))
                : [];
            $localeDirectFile = $request->file("hero_locale_direct_files.$locale");

            if ($localeDirectFile instanceof UploadedFile) {
                $localizedImages[$locale] = $this->storeDirectLandingFile(
                    $landingSetting,
                    $localeDirectFile,
                    $collection
                );
                continue;
            }

            if (!empty($localeTempFolders)) {
                $existingIds = $landingSetting->files()->where('collection', $collection)->pluck('id')->all();
                $localeRemovedIds = array_values(array_unique(array_merge($localeRemovedIds, $existingIds)));
            }

            $this->filePondService->handleFileUpdates(
                $landingSetting,
                $localeTempFolders,
                $localeRemovedIds,
                $collection
            );

            if (!empty($localeTempFolders)) {
                $heroFile = $landingSetting->files()
                    ->where('collection', $collection)
                    ->latest('id')
                    ->first();

                $localizedImages[$locale] = $heroFile
                    ? (SiteSetting::publicUrlFromPath($heroFile->path) ?? '')
                    : (string) ($localizedImages[$locale] ?? '');
            } elseif (!empty($localeRemovedIds)) {
                $localizedImages[$locale] = '';
            }
        }

        data_set($settings, 'hero.localized_images', $localizedImages);
        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function syncCarsFleetButtonIconUpload(Request $request, SiteSetting $landingSetting): void
    {
        $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        $collection = 'cars_fleet_button_icon';
        $tempFolders = is_array($request->input('cars_fleet_button_icon_temp_folders', []))
            ? array_values(array_filter($request->input('cars_fleet_button_icon_temp_folders', [])))
            : [];
        $removedIds = is_array($request->input('cars_fleet_button_icon_removed_files', []))
            ? array_values(array_unique(array_filter($request->input('cars_fleet_button_icon_removed_files', []))))
            : [];
        $directFile = $request->file('cars_fleet_button_icon_direct_file');

        if ($directFile instanceof UploadedFile) {
            data_set(
                $settings,
                'cars_section.fleet_button_icon_url',
                $this->storeDirectLandingFile($landingSetting, $directFile, $collection)
            );

            $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);

            return;
        }

        if (!empty($tempFolders)) {
            $existingIds = $landingSetting->files()->where('collection', $collection)->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $landingSetting,
            $tempFolders,
            $removedIds,
            $collection
        );

        if (!empty($tempFolders)) {
            $file = $landingSetting->files()
                ->where('collection', $collection)
                ->latest('id')
                ->first();

            data_set(
                $settings,
                'cars_section.fleet_button_icon_url',
                $file ? (SiteSetting::publicUrlFromPath($file->path) ?? '') : (string) data_get($settings, 'cars_section.fleet_button_icon_url', '')
            );
        } elseif (!empty($removedIds)) {
            data_set($settings, 'cars_section.fleet_button_icon_url', '');
        }

        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function syncFooterAppIconUploads(Request $request, SiteSetting $landingSetting): void
    {
        $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        $tempFoldersByType = is_array($request->input('footer_app_icon_temp_folders', []))
            ? $request->input('footer_app_icon_temp_folders', [])
            : [];
        $removedIdsByType = is_array($request->input('footer_app_icon_removed_files', []))
            ? $request->input('footer_app_icon_removed_files', [])
            : [];

        foreach ([
            'android' => ['collection' => 'footer_android_icon', 'setting' => 'footer.android_icon_url'],
            'ios' => ['collection' => 'footer_ios_icon', 'setting' => 'footer.ios_icon_url'],
        ] as $type => $config) {
            $tempFolders = is_array($tempFoldersByType[$type] ?? null)
                ? array_values(array_filter($tempFoldersByType[$type]))
                : [];
            $removedIds = is_array($removedIdsByType[$type] ?? null)
                ? array_values(array_unique(array_filter($removedIdsByType[$type])))
                : [];
            $directFile = $request->file("footer_app_icon_direct_files.$type");

            if ($directFile instanceof UploadedFile) {
                data_set(
                    $settings,
                    $config['setting'],
                    $this->storeDirectLandingFile($landingSetting, $directFile, $config['collection'])
                );
                continue;
            }

            if (!empty($tempFolders)) {
                $existingIds = $landingSetting->files()->where('collection', $config['collection'])->pluck('id')->all();
                $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
            }

            $this->filePondService->handleFileUpdates(
                $landingSetting,
                $tempFolders,
                $removedIds,
                $config['collection']
            );

            if (!empty($tempFolders)) {
                $file = $landingSetting->files()
                    ->where('collection', $config['collection'])
                    ->latest('id')
                    ->first();

                data_set(
                    $settings,
                    $config['setting'],
                    $file ? (SiteSetting::publicUrlFromPath($file->path) ?? '') : (string) data_get($settings, $config['setting'], '')
                );
            } elseif (!empty($removedIds)) {
                data_set($settings, $config['setting'], '');
            }
        }

        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function syncMobileAppUploads(Request $request, SiteSetting $landingSetting): void
    {
        $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        $apps = (array) data_get($settings, 'mobile_apps_section.apps', []);
        $tempFoldersByIndex = is_array($request->input('mobile_app_temp_folders', []))
            ? $request->input('mobile_app_temp_folders', [])
            : [];
        $removedIdsByIndex = is_array($request->input('mobile_app_removed_files', []))
            ? $request->input('mobile_app_removed_files', [])
            : [];

        foreach ($apps as $index => $app) {
            if (!is_array($app)) {
                continue;
            }

            foreach (['image' => 'image_url', 'icon' => 'icon_url'] as $type => $urlKey) {
                $collection = $this->mobileAppCollection((int) $index, $type);
                $tempFolders = is_array($tempFoldersByIndex[$index][$type] ?? null)
                    ? array_values(array_filter($tempFoldersByIndex[$index][$type]))
                    : [];
                $removedIds = is_array($removedIdsByIndex[$index][$type] ?? null)
                    ? array_values(array_unique(array_filter($removedIdsByIndex[$index][$type])))
                    : [];
                $directFile = $request->file("mobile_app_direct_files.$index.$type");

                if ($directFile instanceof UploadedFile) {
                    $apps[$index][$urlKey] = $this->storeDirectLandingFile(
                        $landingSetting,
                        $directFile,
                        $collection
                    );
                    continue;
                }

                if (!empty($tempFolders)) {
                    $existingIds = $landingSetting->files()->where('collection', $collection)->pluck('id')->all();
                    $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
                }

                $this->filePondService->handleFileUpdates(
                    $landingSetting,
                    $tempFolders,
                    $removedIds,
                    $collection
                );

                if (!empty($tempFolders)) {
                    $file = $landingSetting->files()
                        ->where('collection', $collection)
                        ->latest('id')
                        ->first();

                    $apps[$index][$urlKey] = $file
                        ? (SiteSetting::publicUrlFromPath($file->path) ?? '')
                        : (string) ($apps[$index][$urlKey] ?? '');
                } elseif (!empty($removedIds)) {
                    $apps[$index][$urlKey] = '';
                }
            }

            $localizedImages = is_array($app['localized_images'] ?? null) ? $app['localized_images'] : [];
            foreach ($this->supportedLocaleKeys() as $locale) {
                $collection = $this->mobileAppLocaleImageCollection((int) $index, $locale);
                $tempFolders = is_array($tempFoldersByIndex[$index]['image_locales'][$locale] ?? null)
                    ? array_values(array_filter($tempFoldersByIndex[$index]['image_locales'][$locale]))
                    : [];
                $removedIds = is_array($removedIdsByIndex[$index]['image_locales'][$locale] ?? null)
                    ? array_values(array_unique(array_filter($removedIdsByIndex[$index]['image_locales'][$locale])))
                    : [];
                $directFile = $request->file("mobile_app_direct_files.$index.image_locales.$locale");

                if ($directFile instanceof UploadedFile) {
                    $localizedImages[$locale] = $this->storeDirectLandingFile(
                        $landingSetting,
                        $directFile,
                        $collection
                    );
                    continue;
                }

                if (!empty($tempFolders)) {
                    $existingIds = $landingSetting->files()->where('collection', $collection)->pluck('id')->all();
                    $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
                }

                $this->filePondService->handleFileUpdates(
                    $landingSetting,
                    $tempFolders,
                    $removedIds,
                    $collection
                );

                if (!empty($tempFolders)) {
                    $file = $landingSetting->files()
                        ->where('collection', $collection)
                        ->latest('id')
                        ->first();

                    $localizedImages[$locale] = $file
                        ? (SiteSetting::publicUrlFromPath($file->path) ?? '')
                        : (string) ($localizedImages[$locale] ?? '');
                } elseif (!empty($removedIds)) {
                    $localizedImages[$locale] = '';
                }
            }
            $apps[$index]['localized_images'] = $localizedImages;
        }

        data_set($settings, 'mobile_apps_section.apps', $apps);
        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function syncFeatureCardUploads(Request $request, SiteSetting $landingSetting): void
    {
        $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        $cards = (array) data_get($settings, 'features_section.cards', []);
        $tempFoldersByIndex = is_array($request->input('feature_card_temp_folders', []))
            ? $request->input('feature_card_temp_folders', [])
            : [];
        $removedIdsByIndex = is_array($request->input('feature_card_removed_files', []))
            ? $request->input('feature_card_removed_files', [])
            : [];

        foreach ($cards as $index => $card) {
            if (!is_array($card)) {
                continue;
            }

            $collection = $this->featureCardCollection((int) $index);
            $tempFolders = is_array($tempFoldersByIndex[$index] ?? null)
                ? array_values(array_filter($tempFoldersByIndex[$index]))
                : [];
            $removedIds = is_array($removedIdsByIndex[$index] ?? null)
                ? array_values(array_unique(array_filter($removedIdsByIndex[$index])))
                : [];
            $directFile = $request->file("feature_card_direct_files.$index");

            if ($directFile instanceof UploadedFile) {
                $cards[$index]['image_url'] = $this->storeDirectLandingFile(
                    $landingSetting,
                    $directFile,
                    $collection
                );
                continue;
            }

            if (!empty($tempFolders)) {
                $existingIds = $landingSetting->files()->where('collection', $collection)->pluck('id')->all();
                $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
            }

            $this->filePondService->handleFileUpdates(
                $landingSetting,
                $tempFolders,
                $removedIds,
                $collection
            );

            if (!empty($tempFolders)) {
                $file = $landingSetting->files()
                    ->where('collection', $collection)
                    ->latest('id')
                    ->first();

                $cards[$index]['image_url'] = $file
                    ? (SiteSetting::publicUrlFromPath($file->path) ?? '')
                    : (string) ($cards[$index]['image_url'] ?? '');
            } elseif (!empty($removedIds)) {
                $cards[$index]['image_url'] = '';
            }
        }

        data_set($settings, 'features_section.cards', $cards);
        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function syncGettingStartedUploads(Request $request, SiteSetting $landingSetting): void
    {
        $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        $steps = (array) data_get($settings, 'getting_started.items', []);
        $tempFoldersByIndex = is_array($request->input('getting_started_temp_folders', []))
            ? $request->input('getting_started_temp_folders', [])
            : [];
        $removedIdsByIndex = is_array($request->input('getting_started_removed_files', []))
            ? $request->input('getting_started_removed_files', [])
            : [];

        foreach ($steps as $index => $step) {
            if (!is_array($step)) {
                continue;
            }

            $collection = $this->gettingStartedStepCollection((int) $index);
            $tempFolders = is_array($tempFoldersByIndex[$index] ?? null)
                ? array_values(array_filter($tempFoldersByIndex[$index]))
                : [];
            $removedIds = is_array($removedIdsByIndex[$index] ?? null)
                ? array_values(array_unique(array_filter($removedIdsByIndex[$index])))
                : [];
            $directFile = $request->file("getting_started_direct_files.$index");

            if ($directFile instanceof UploadedFile) {
                $steps[$index]['image_url'] = $this->storeDirectLandingFile(
                    $landingSetting,
                    $directFile,
                    $collection
                );
                continue;
            }

            if (!empty($tempFolders)) {
                $existingIds = $landingSetting->files()->where('collection', $collection)->pluck('id')->all();
                $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
            }

            $this->filePondService->handleFileUpdates(
                $landingSetting,
                $tempFolders,
                $removedIds,
                $collection
            );

            if (!empty($tempFolders)) {
                $file = $landingSetting->files()
                    ->where('collection', $collection)
                    ->latest('id')
                    ->first();

                $steps[$index]['image_url'] = $file
                    ? (SiteSetting::publicUrlFromPath($file->path) ?? '')
                    : (string) ($steps[$index]['image_url'] ?? '');
            } elseif (!empty($removedIds)) {
                $steps[$index]['image_url'] = '';
            }
        }

        data_set($settings, 'getting_started.items', $steps);
        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function syncApplicationsPageUploads(Request $request, SiteSetting $landingSetting): void
    {
        $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        $applicationsPage = (array) data_get($settings, 'applications_page', []);

        $heroRemovedIds = is_array($request->input('application_hero_removed_files', []))
            ? array_values(array_unique(array_filter($request->input('application_hero_removed_files', []))))
            : [];
        $heroDirectFile = $request->file('application_hero_direct_file');

        if ($heroDirectFile instanceof UploadedFile) {
            $applicationsPage['hero_image_url'] = $this->storeDirectLandingFile(
                $landingSetting,
                $heroDirectFile,
                'applications_page_hero'
            );
        } elseif (!empty($heroRemovedIds)) {
            $this->filePondService->handleFileUpdates(
                $landingSetting,
                [],
                $heroRemovedIds,
                'applications_page_hero'
            );
            $applicationsPage['hero_image_url'] = '';
        }

        $heroLocalizedImages = is_array($applicationsPage['hero_localized_images'] ?? null)
            ? $applicationsPage['hero_localized_images']
            : [];
        $heroLocaleRemovedIds = is_array($request->input('application_hero_locale_removed_files', []))
            ? $request->input('application_hero_locale_removed_files', [])
            : [];

        foreach ($this->supportedLocaleKeys() as $locale) {
            $collection = $this->applicationHeroLocaleCollection($locale);
            $removedIds = is_array($heroLocaleRemovedIds[$locale] ?? null)
                ? array_values(array_unique(array_filter($heroLocaleRemovedIds[$locale])))
                : [];
            $directFile = $request->file("application_hero_locale_direct_files.$locale");

            if ($directFile instanceof UploadedFile) {
                $heroLocalizedImages[$locale] = $this->storeDirectLandingFile(
                    $landingSetting,
                    $directFile,
                    $collection
                );
                continue;
            }

            if (!empty($removedIds)) {
                $this->filePondService->handleFileUpdates(
                    $landingSetting,
                    [],
                    $removedIds,
                    $collection
                );
                $heroLocalizedImages[$locale] = '';
            }
        }

        $applicationsPage['hero_localized_images'] = $heroLocalizedImages;

        $roles = (array) ($applicationsPage['roles'] ?? []);
        $removedIdsByIndex = is_array($request->input('application_role_removed_files', []))
            ? $request->input('application_role_removed_files', [])
            : [];
        $localeRemovedIdsByIndex = is_array($request->input('application_role_locale_removed_files', []))
            ? $request->input('application_role_locale_removed_files', [])
            : [];

        foreach ($roles as $index => $role) {
            if (!is_array($role)) {
                continue;
            }

            $collection = $this->applicationRoleCollection((int) $index);
            $removedIds = is_array($removedIdsByIndex[$index] ?? null)
                ? array_values(array_unique(array_filter($removedIdsByIndex[$index])))
                : [];
            $directFile = $request->file("application_role_direct_files.$index");

            if ($directFile instanceof UploadedFile) {
                $roles[$index]['image_url'] = $this->storeDirectLandingFile(
                    $landingSetting,
                    $directFile,
                    $collection
                );
            }

            if (!$directFile instanceof UploadedFile && !empty($removedIds)) {
                $this->filePondService->handleFileUpdates(
                    $landingSetting,
                    [],
                    $removedIds,
                    $collection
                );
                $roles[$index]['image_url'] = '';
            }

            $localizedImages = is_array($roles[$index]['localized_images'] ?? null)
                ? $roles[$index]['localized_images']
                : [];

            foreach ($this->supportedLocaleKeys() as $locale) {
                $localeCollection = $this->applicationRoleLocaleCollection((int) $index, $locale);
                $localeRemovedIds = is_array($localeRemovedIdsByIndex[$index][$locale] ?? null)
                    ? array_values(array_unique(array_filter($localeRemovedIdsByIndex[$index][$locale])))
                    : [];
                $localeDirectFile = $request->file("application_role_locale_direct_files.$index.$locale");

                if ($localeDirectFile instanceof UploadedFile) {
                    $localizedImages[$locale] = $this->storeDirectLandingFile(
                        $landingSetting,
                        $localeDirectFile,
                        $localeCollection
                    );
                    continue;
                }

                if (!empty($localeRemovedIds)) {
                    $this->filePondService->handleFileUpdates(
                        $landingSetting,
                        [],
                        $localeRemovedIds,
                        $localeCollection
                    );
                    $localizedImages[$locale] = '';
                }
            }

            $roles[$index]['localized_images'] = $localizedImages;
        }

        $applicationsPage['roles'] = $roles;
        data_set($settings, 'applications_page', $applicationsPage);
        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function sanitizeRequestFiles(Request $request): void
    {
        if ($request->has('hero_direct_file') && !($request->file('hero_direct_file') instanceof UploadedFile)) {
            $request->request->remove('hero_direct_file');
            if ($request->files->has('hero_direct_file')) {
                $request->files->remove('hero_direct_file');
            }
        }

        if ($request->has('cars_fleet_button_icon_direct_file') && !($request->file('cars_fleet_button_icon_direct_file') instanceof UploadedFile)) {
            $request->request->remove('cars_fleet_button_icon_direct_file');
            if ($request->files->has('cars_fleet_button_icon_direct_file')) {
                $request->files->remove('cars_fleet_button_icon_direct_file');
            }
        }

        if ($request->has('footer_app_icon_direct_files')) {
            $files = $request->input('footer_app_icon_direct_files');
            if (is_array($files)) {
                $sanitized = [];
                foreach (['android', 'ios'] as $type) {
                    $file = $request->file("footer_app_icon_direct_files.$type");
                    if ($file instanceof UploadedFile) {
                        $sanitized[$type] = $file;
                    }
                }
                $request->merge(['footer_app_icon_direct_files' => $sanitized]);
                $request->files->set('footer_app_icon_direct_files', $sanitized);
            }
        }

        if ($request->has('hero_locale_direct_files')) {
            $files = $request->input('hero_locale_direct_files');
            if (is_array($files)) {
                $sanitized = [];
                foreach ($files as $locale => $val) {
                    $file = $request->file("hero_locale_direct_files.$locale");
                    if ($file instanceof UploadedFile) {
                        $sanitized[$locale] = $file;
                    }
                }
                $request->merge(['hero_locale_direct_files' => $sanitized]);
                $request->files->set('hero_locale_direct_files', $sanitized);
            }
        }

        if ($request->has('feature_card_direct_files')) {
            $files = $request->input('feature_card_direct_files');
            if (is_array($files)) {
                $sanitized = [];
                foreach ($files as $index => $val) {
                    $file = $request->file("feature_card_direct_files.$index");
                    if ($file instanceof UploadedFile) {
                        $sanitized[$index] = $file;
                    }
                }
                $request->merge(['feature_card_direct_files' => $sanitized]);
                $request->files->set('feature_card_direct_files', $sanitized);
            }
        }

        if ($request->has('getting_started_direct_files')) {
            $files = $request->input('getting_started_direct_files');
            if (is_array($files)) {
                $sanitized = [];
                foreach ($files as $index => $val) {
                    $file = $request->file("getting_started_direct_files.$index");
                    if ($file instanceof UploadedFile) {
                        $sanitized[$index] = $file;
                    }
                }
                $request->merge(['getting_started_direct_files' => $sanitized]);
                $request->files->set('getting_started_direct_files', $sanitized);
            }
        }

        if ($request->has('mobile_app_direct_files')) {
            $apps = $request->input('mobile_app_direct_files');
            if (is_array($apps)) {
                $sanitized = [];
                foreach ($apps as $index => $app) {
                    if (is_array($app)) {
                        $sanitized[$index] = [];
                        foreach (['image', 'icon'] as $type) {
                            $file = $request->file("mobile_app_direct_files.$index.$type");
                            if ($file instanceof UploadedFile) {
                                $sanitized[$index][$type] = $file;
                            }
                        }
                        if (is_array($app['image_locales'] ?? null)) {
                            $sanitized[$index]['image_locales'] = [];
                            foreach ($app['image_locales'] as $locale => $value) {
                                $file = $request->file("mobile_app_direct_files.$index.image_locales.$locale");
                                if ($file instanceof UploadedFile) {
                                    $sanitized[$index]['image_locales'][$locale] = $file;
                                }
                            }
                        }
                    }
                }
                $request->merge(['mobile_app_direct_files' => $sanitized]);
                $request->files->set('mobile_app_direct_files', $sanitized);
            }
        }
    }

    private function sanitizeApplicationsPageFiles(Request $request): void
    {
        if ($request->has('application_hero_direct_file') && !($request->file('application_hero_direct_file') instanceof UploadedFile)) {
            $request->request->remove('application_hero_direct_file');
            if ($request->files->has('application_hero_direct_file')) {
                $request->files->remove('application_hero_direct_file');
            }
        }

        if ($request->has('application_hero_locale_direct_files')) {
            $files = $request->input('application_hero_locale_direct_files');
            if (is_array($files)) {
                $sanitized = [];
                foreach ($files as $locale => $val) {
                    $file = $request->file("application_hero_locale_direct_files.$locale");
                    if ($file instanceof UploadedFile) {
                        $sanitized[$locale] = $file;
                    }
                }
                $request->merge(['application_hero_locale_direct_files' => $sanitized]);
                $request->files->set('application_hero_locale_direct_files', $sanitized);
            }
        }

        if ($request->has('application_role_direct_files')) {
            $files = $request->input('application_role_direct_files');
            if (is_array($files)) {
                $sanitized = [];
                foreach ($files as $index => $val) {
                    $file = $request->file("application_role_direct_files.$index");
                    if ($file instanceof UploadedFile) {
                        $sanitized[$index] = $file;
                    }
                }
                $request->merge(['application_role_direct_files' => $sanitized]);
                $request->files->set('application_role_direct_files', $sanitized);
            }
        }

        if ($request->has('application_role_locale_direct_files')) {
            $files = $request->input('application_role_locale_direct_files');
            if (is_array($files)) {
                $sanitized = [];
                foreach ($files as $index => $locales) {
                    if (!is_array($locales)) {
                        continue;
                    }

                    foreach ($locales as $locale => $val) {
                        $file = $request->file("application_role_locale_direct_files.$index.$locale");
                        if ($file instanceof UploadedFile) {
                            $sanitized[$index][$locale] = $file;
                        }
                    }
                }
                $request->merge(['application_role_locale_direct_files' => $sanitized]);
                $request->files->set('application_role_locale_direct_files', $sanitized);
            }
        }
    }

    private function validatedLandingSettings(Request $request): array
    {
        $this->sanitizeRequestFiles($request);

        $validated = $request->validate([
            'settings.hero.enabled' => ['nullable', 'boolean'],
            'settings.hero.title' => ['required', 'string', 'max:255'],
            'settings.hero.description' => ['required', 'string', 'max:2000'],
            'settings.hero.features' => ['nullable', 'array'],
            'settings.hero.features.*' => ['nullable', 'string', 'max:255'],
            'settings.hero.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.hero.localized_images' => ['nullable', 'array'],
            'settings.hero.localized_images.*' => ['nullable', 'string', 'max:2000'],

            'settings.cars_section.enabled' => ['nullable', 'boolean'],
            'settings.cars_section.fleet_button_icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.enabled' => ['nullable', 'boolean'],
            'settings.features_section.title' => ['required', 'string', 'max:255'],
            'settings.features_section.description' => ['required', 'string', 'max:2000'],
            'settings.features_section.cards' => ['nullable', 'array'],
            'settings.features_section.cards.*.title' => ['nullable', 'string', 'max:255'],
            'settings.features_section.cards.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.cards.*.icon_background_color' => ['nullable', 'string', 'max:20'],
            'settings.features_section.cards.*.content' => ['nullable', 'string', 'max:2000'],

            'settings.getting_started.enabled' => ['nullable', 'boolean'],
            'settings.getting_started.title' => ['required', 'string', 'max:255'],
            'settings.getting_started.description' => ['required', 'string', 'max:2000'],
            'settings.getting_started.items' => ['nullable', 'array'],
            'settings.getting_started.items.*.title' => ['nullable', 'string', 'max:255'],
            'settings.getting_started.items.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.getting_started.items.*.icon_background_color' => ['nullable', 'string', 'max:20'],
            'settings.getting_started.items.*.description' => ['nullable', 'string', 'max:2000'],

            'settings.mobile_apps_section.enabled' => ['nullable', 'boolean'],
            'settings.mobile_apps_section.eyebrow' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.title' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.description' => ['required', 'string', 'max:2000'],
            'settings.mobile_apps_section.ios_label' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.android_label' => ['required', 'string', 'max:255'],
            'settings.mobile_apps_section.apps' => ['nullable', 'array'],
            'settings.mobile_apps_section.apps.*.title' => ['nullable', 'string', 'max:255'],
            'settings.mobile_apps_section.apps.*.subtitle' => ['nullable', 'string', 'max:255'],
            'settings.mobile_apps_section.apps.*.description' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.localized_images' => ['nullable', 'array'],
            'settings.mobile_apps_section.apps.*.localized_images.*' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.app_store_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.google_play_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.badge' => ['nullable', 'string', 'max:255'],
            'settings.mobile_apps_section.apps.*.features' => ['nullable', 'array'],
            'settings.mobile_apps_section.apps.*.features.*' => ['nullable', 'string', 'max:255'],

            'settings.clients_section.enabled' => ['nullable', 'boolean'],
            'settings.plans_section.enabled' => ['nullable', 'boolean'],
            'settings.plans_section.title' => ['required', 'string', 'max:255'],
            'settings.plans_section.description' => ['required', 'string', 'max:2000'],

            'settings.faq_section.enabled' => ['nullable', 'boolean'],
            'settings.faq_section.title' => ['required', 'string', 'max:255'],
            'settings.faq_section.description' => ['required', 'string', 'max:2000'],
            'settings.faq_section.items' => ['nullable', 'array'],
            'settings.faq_section.items.*.question' => ['nullable', 'string', 'max:2000'],
            'settings.faq_section.items.*.answer' => ['nullable', 'string', 'max:5000'],

            'settings.contact_section.enabled' => ['nullable', 'boolean'],
            'settings.contact_section.title' => ['required', 'string', 'max:255'],
            'settings.contact_section.description' => ['required', 'string', 'max:2000'],
            'settings.contact_section.form_title' => ['required', 'string', 'max:255'],
            'settings.contact_section.name_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.name_placeholder' => ['required', 'string', 'max:255'],
            'settings.contact_section.email_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.email_placeholder' => ['required', 'string', 'max:255'],
            'settings.contact_section.subject_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.subject_placeholder' => ['required', 'string', 'max:255'],
            'settings.contact_section.message_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.message_placeholder' => ['required', 'string', 'max:1000'],
            'settings.contact_section.submit_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.sending_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.success_message' => ['required', 'string', 'max:1000'],
            'settings.contact_section.error_message' => ['required', 'string', 'max:1000'],
            'settings.contact_section.direct_title' => ['required', 'string', 'max:255'],
            'settings.contact_section.direct_email_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.direct_email' => ['required', 'email', 'max:255'],
            'settings.contact_section.direct_phone_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.direct_phone' => ['required', 'string', 'max:255'],
            'settings.contact_section.response_time_label' => ['required', 'string', 'max:255'],
            'settings.contact_section.response_time' => ['required', 'string', 'max:255'],
            'settings.contact_section.quick_links_title' => ['required', 'string', 'max:255'],
            'settings.contact_section.quick_links' => ['nullable', 'array'],
            'settings.contact_section.quick_links.*.label' => ['nullable', 'string', 'max:255'],
            'settings.contact_section.quick_links.*.href' => ['nullable', 'string', 'max:255'],

            'settings.footer.enabled' => ['nullable', 'boolean'],
            'settings.footer.title' => ['required', 'string', 'max:255'],
            'settings.footer.description' => ['required', 'string', 'max:2000'],
            'settings.footer.copyright_text' => ['required', 'string', 'max:255'],
            'settings.footer.show_social_links' => ['nullable', 'boolean'],
            'settings.footer.show_app_buttons' => ['nullable', 'boolean'],
            'settings.footer.android_caption' => ['required', 'string', 'max:255'],
            'settings.footer.android_label' => ['required', 'string', 'max:255'],
            'settings.footer.android_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.android_icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.ios_caption' => ['required', 'string', 'max:255'],
            'settings.footer.ios_label' => ['required', 'string', 'max:255'],
            'settings.footer.ios_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.ios_icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.nav_privacy' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_terms' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_security_policy' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_cars' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_features' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_application' => ['nullable', 'string', 'max:255'],
            'settings.footer.nav_plans' => ['nullable', 'string', 'max:255'],
            'settings.footer.social_links' => ['nullable', 'array'],
            'settings.footer.social_links.*.label' => ['nullable', 'string', 'max:255'],
            'settings.footer.social_links.*.platform' => ['nullable', 'string', 'max:50'],
            'settings.footer.social_links.*.href' => ['nullable', 'string', 'max:2000'],

            'hero_temp_folders' => ['nullable', 'array'],
            'hero_temp_folders.*' => ['string'],
            'hero_removed_files' => ['nullable', 'array'],
            'hero_removed_files.*' => ['integer'],
            'hero_locale_temp_folders' => ['nullable', 'array'],
            'hero_locale_temp_folders.*' => ['array'],
            'hero_locale_temp_folders.*.*' => ['string'],
            'hero_locale_removed_files' => ['nullable', 'array'],
            'hero_locale_removed_files.*' => ['array'],
            'hero_locale_removed_files.*.*' => ['integer'],
            'hero_direct_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,ogg,mov', 'max:51200'],
            'cars_fleet_button_icon_temp_folders' => ['nullable', 'array'],
            'cars_fleet_button_icon_temp_folders.*' => ['string'],
            'cars_fleet_button_icon_removed_files' => ['nullable', 'array'],
            'cars_fleet_button_icon_removed_files.*' => ['integer'],
            'cars_fleet_button_icon_direct_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'footer_app_icon_temp_folders' => ['nullable', 'array'],
            'footer_app_icon_temp_folders.*' => ['array'],
            'footer_app_icon_temp_folders.*.*' => ['string'],
            'footer_app_icon_removed_files' => ['nullable', 'array'],
            'footer_app_icon_removed_files.*' => ['array'],
            'footer_app_icon_removed_files.*.*' => ['integer'],
            'footer_app_icon_direct_files' => ['nullable', 'array'],
            'footer_app_icon_direct_files.*' => ['nullable', 'file', 'mimes:svg', 'max:5120'],
            'hero_locale_direct_files' => ['nullable', 'array'],
            'hero_locale_direct_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,ogg,mov', 'max:51200'],
            'feature_card_temp_folders' => ['nullable', 'array'],
            'feature_card_temp_folders.*' => ['array'],
            'feature_card_temp_folders.*.*' => ['string'],
            'feature_card_removed_files' => ['nullable', 'array'],
            'feature_card_removed_files.*' => ['array'],
            'feature_card_removed_files.*.*' => ['integer'],
            'feature_card_direct_files' => ['nullable', 'array'],
            'feature_card_direct_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'getting_started_temp_folders' => ['nullable', 'array'],
            'getting_started_temp_folders.*' => ['array'],
            'getting_started_temp_folders.*.*' => ['string'],
            'getting_started_removed_files' => ['nullable', 'array'],
            'getting_started_removed_files.*' => ['array'],
            'getting_started_removed_files.*.*' => ['integer'],
            'getting_started_direct_files' => ['nullable', 'array'],
            'getting_started_direct_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'mobile_app_temp_folders' => ['nullable', 'array'],
            'mobile_app_temp_folders.*' => ['array'],
            'mobile_app_temp_folders.*.image' => ['nullable', 'array'],
            'mobile_app_temp_folders.*.image.*' => ['string'],
            'mobile_app_temp_folders.*.image_locales' => ['nullable', 'array'],
            'mobile_app_temp_folders.*.image_locales.*' => ['nullable', 'array'],
            'mobile_app_temp_folders.*.image_locales.*.*' => ['string'],
            'mobile_app_temp_folders.*.icon' => ['nullable', 'array'],
            'mobile_app_temp_folders.*.icon.*' => ['string'],
            'mobile_app_removed_files' => ['nullable', 'array'],
            'mobile_app_removed_files.*' => ['array'],
            'mobile_app_removed_files.*.image' => ['nullable', 'array'],
            'mobile_app_removed_files.*.image.*' => ['integer'],
            'mobile_app_removed_files.*.image_locales' => ['nullable', 'array'],
            'mobile_app_removed_files.*.image_locales.*' => ['nullable', 'array'],
            'mobile_app_removed_files.*.image_locales.*.*' => ['integer'],
            'mobile_app_removed_files.*.icon' => ['nullable', 'array'],
            'mobile_app_removed_files.*.icon.*' => ['integer'],
            'mobile_app_direct_files' => ['nullable', 'array'],
            'mobile_app_direct_files.*' => ['array'],
            'mobile_app_direct_files.*.image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'mobile_app_direct_files.*.image_locales' => ['nullable', 'array'],
            'mobile_app_direct_files.*.image_locales.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
            'mobile_app_direct_files.*.icon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:51200'],
        ]);

        return Arr::only(LandingPageSettings::normalize($validated['settings'] ?? []), [
            'hero',
            'cars_section',
            'features_section',
            'getting_started',
            'mobile_apps_section',
            'clients_section',
            'plans_section',
            'faq_section',
            'contact_section',
            'footer',
        ]);
    }

    /**
     * @return array<string, array<int, array{id: int, url: string|null}>>
     */
    private function heroLocalizedFiles(?SiteSetting $landingSetting): array
    {
        $files = [];

        foreach ($this->supportedLocaleKeys() as $locale) {
            $files[$locale] = $landingSetting
                ? $landingSetting->files()
                    ->where('collection', $this->heroLocaleCollection($locale))
                    ->get()
                    ->map(fn ($file) => [
                        'id' => $file->id,
                        'url' => SiteSetting::publicUrlFromPath($file->path),
                    ])
                    ->values()
                    ->all()
                : [];
        }

        return $files;
    }

    /**
     * @return array<int, array{image: array<int, array{id: int, url: string|null}>, image_locales: array<string, array<int, array{id: int, url: string|null}>>, icon: array<int, array{id: int, url: string|null}>}>
     */
    private function mobileAppFiles(?SiteSetting $landingSetting): array
    {
        $settings = $this->landingSettings();
        $apps = (array) data_get($settings, 'mobile_apps_section.apps', []);
        $files = [];

        foreach (array_keys($apps) as $index) {
            $localizedImages = [];
            foreach ($this->supportedLocaleKeys() as $locale) {
                $localizedImages[$locale] = $this->landingFilesForCollection(
                    $landingSetting,
                    $this->mobileAppLocaleImageCollection((int) $index, $locale)
                );
            }

            $files[(int) $index] = [
                'image' => $this->landingFilesForCollection($landingSetting, $this->mobileAppCollection((int) $index, 'image')),
                'image_locales' => $localizedImages,
                'icon' => $this->landingFilesForCollection($landingSetting, $this->mobileAppCollection((int) $index, 'icon')),
            ];
        }

        return $files;
    }

    /**
     * @return array<int, array<int, array{id: int, url: string|null}>>
     */
    private function featureCardFiles(?SiteSetting $landingSetting): array
    {
        $settings = $this->landingSettings();
        $cards = (array) data_get($settings, 'features_section.cards', []);
        $files = [];

        foreach (array_keys($cards) as $index) {
            $files[(int) $index] = $this->landingFilesForCollection($landingSetting, $this->featureCardCollection((int) $index));
        }

        return $files;
    }

    /**
     * @return array<int, array<int, array{id: int, url: string|null}>>
     */
    private function gettingStartedFiles(?SiteSetting $landingSetting): array
    {
        $settings = $this->landingSettings();
        $steps = (array) data_get($settings, 'getting_started.items', []);
        $files = [];

        foreach (array_keys($steps) as $index) {
            $files[(int) $index] = $this->landingFilesForCollection($landingSetting, $this->gettingStartedStepCollection((int) $index));
        }

        return $files;
    }

    /**
     * @return array<int, array<int, array{id: int, url: string|null}>>
     */
    private function applicationRoleFiles(?SiteSetting $landingSetting): array
    {
        $settings = $this->landingSettings();
        $roles = (array) data_get($settings, 'applications_page.roles', []);
        $files = [];

        foreach (array_keys($roles) as $index) {
            $files[(int) $index] = $this->landingFilesForCollection($landingSetting, $this->applicationRoleCollection((int) $index));
        }

        return $files;
    }

    /**
     * @return array<string, array<int, array{id: int, url: string|null}>>
     */
    private function applicationHeroLocalizedFiles(?SiteSetting $landingSetting): array
    {
        $files = [];

        foreach ($this->supportedLocaleKeys() as $locale) {
            $files[$locale] = $this->landingFilesForCollection($landingSetting, $this->applicationHeroLocaleCollection($locale));
        }

        return $files;
    }

    /**
     * @return array<int, array<string, array<int, array{id: int, url: string|null}>>>
     */
    private function applicationRoleLocalizedFiles(?SiteSetting $landingSetting): array
    {
        $settings = $this->landingSettings();
        $roles = (array) data_get($settings, 'applications_page.roles', []);
        $files = [];

        foreach (array_keys($roles) as $index) {
            foreach ($this->supportedLocaleKeys() as $locale) {
                $files[(int) $index][$locale] = $this->landingFilesForCollection(
                    $landingSetting,
                    $this->applicationRoleLocaleCollection((int) $index, $locale)
                );
            }
        }

        return $files;
    }

    /**
     * @return array<int, array{id: int, url: string|null}>
     */
    private function landingFilesForCollection(?SiteSetting $landingSetting, string $collection): array
    {
        return $landingSetting
            ? $landingSetting->files()
                ->where('collection', $collection)
                ->get()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => SiteSetting::publicUrlFromPath($file->path),
                ])
                ->values()
                ->all()
            : [];
    }

    private function mobileAppCollection(int $index, string $type): string
    {
        return 'mobile_app_' . max(0, $index) . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $type);
    }

    private function mobileAppLocaleImageCollection(int $index, string $locale): string
    {
        return $this->mobileAppCollection($index, 'image_' . $locale);
    }

    private function featureCardCollection(int $index): string
    {
        return 'feature_card_' . max(0, $index) . '_image';
    }

    private function gettingStartedStepCollection(int $index): string
    {
        return 'getting_started_step_' . max(0, $index) . '_image';
    }

    private function applicationRoleCollection(int $index): string
    {
        return 'applications_page_role_' . max(0, $index) . '_image';
    }

    private function applicationHeroLocaleCollection(string $locale): string
    {
        return 'applications_page_hero_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $locale);
    }

    private function applicationRoleLocaleCollection(int $index, string $locale): string
    {
        return $this->applicationRoleCollection($index) . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $locale);
    }

    private function heroLocaleCollection(string $locale): string
    {
        return 'hero_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $locale);
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocaleKeys(): array
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));
        if (empty($supported)) {
            $supported = LandingPageSettings::supportedLocaleKeys();
        }

        $supported = array_values(array_unique(array_map('strval', $supported)));

        return empty($supported) ? ['en'] : $supported;
    }

    /**
     * @return array<int, string>
     */
    private function landingEnabledLocales(array $settings): array
    {
        $locales = array_values(array_filter(
            array_map('strval', (array) data_get($settings, 'enabled_locales', [])),
            static fn (string $locale) => trim($locale) !== ''
        ));

        return empty($locales) ? $this->supportedLocaleKeys() : $locales;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function sanitizeEnabledLocales(mixed $value): array
    {
        $supported = $this->supportedLocaleKeys();
        $enabled = is_array($value) ? $value : [];
        $enabled = array_values(array_unique(array_intersect($supported, array_map('strval', $enabled))));

        return empty($enabled) ? $supported : $enabled;
    }

    /**
     * @param  array<string, string>  $sourceRows
     * @return array<string, string>
     */
    private function translateLandingContentToArabic(array $sourceRows, string $targetLocale): array
    {
        $settings = AiProviderSettings::load();
        $provider = (string) ($settings['provider'] ?? 'openai');

        if ($provider !== 'openai') {
            throw new RuntimeException('Landing auto-translate requires OpenAI. Please switch the AI provider to OpenAI.');
        }

        $openAi = $settings['openai'] ?? [];
        $apiKey = trim((string) ($openAi['api_key'] ?? ''));

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is required for auto-translation.');
        }

        $model = trim((string) ($openAi['model'] ?? 'gpt-4.1-mini'));
        $temperature = (float) ($openAi['temperature'] ?? 0.1);
        $maxOutputTokens = (int) ($openAi['max_output_tokens'] ?? 1200);
        $maxOutputTokens = max(400, min($maxOutputTokens, 2500));
        $systemPrompt = trim((string) ($openAi['system_prompt'] ?? ''));
        if ($systemPrompt === '') {
            $systemPrompt = 'You are a professional Arabic translator for a car-rental SaaS landing page. Translate meaning naturally, keep marketing tone, preserve punctuation and placeholders, and return strict JSON only.';
        }

        $payload = [];
        foreach ($sourceRows as $key => $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }

            $payload[$key] = $text;
        }

        if (empty($payload)) {
            return [];
        }

        $factory = OpenAI::factory()->withApiKey($apiKey);
        $organization = trim((string) ($openAi['organization'] ?? ''));
        if ($organization !== '') {
            $factory = $factory->withOrganization($organization);
        }

        $project = trim((string) ($openAi['project'] ?? ''));
        if ($project !== '') {
            $factory = $factory->withProject($project);
        }

        $baseUri = trim((string) ($openAi['base_uri'] ?? ''));
        if ($baseUri !== '') {
            $factory = $factory->withBaseUri($baseUri);
        }

        $client = $factory->make();
        $translationProperties = [];
        $translationRequired = [];
        foreach (array_keys($payload) as $key) {
            $translationProperties[$key] = ['type' => 'string'];
            $translationRequired[] = $key;
        }

        $response = $client->responses()->create([
            'model' => $model,
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => $systemPrompt],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Translate the following landing page content to Arabic. Keep the same JSON keys and return Arabic text only.',
                        ],
                        [
                            'type' => 'input_text',
                            'text' => json_encode([
                                'target_locale' => $targetLocale,
                                'source' => $payload,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'landing_translation',
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'translations' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => $translationProperties,
                                'required' => $translationRequired,
                            ],
                        ],
                        'required' => ['translations'],
                    ],
                ],
            ],
        ]);

        $outputText = trim((string) ($response->outputText ?? ''));
        if ($outputText === '') {
            throw new RuntimeException('OpenAI returned an empty translation response.');
        }

        $decoded = json_decode($outputText, true);
        $translations = is_array($decoded) ? data_get($decoded, 'translations', []) : [];
        if (!is_array($translations)) {
            $translations = [];
        }

        $normalized = [];
        foreach ($translations as $key => $value) {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                continue;
            }

            $normalized[(string) $key] = $text;
        }

        return $normalized;
    }

    private function normalizeAiProviderPayload(Request $request): void
    {
        $all = $request->all();
        $payload = is_array($request->input('ai_provider')) ? $request->input('ai_provider') : [];

        $dottedKeys = [
            'provider' => 'ai_provider.provider',
            'openai.api_key' => 'ai_provider.openai.api_key',
            'openai.organization' => 'ai_provider.openai.organization',
            'openai.project' => 'ai_provider.openai.project',
            'openai.base_uri' => 'ai_provider.openai.base_uri',
            'openai.model' => 'ai_provider.openai.model',
            'openai.temperature' => 'ai_provider.openai.temperature',
            'openai.max_output_tokens' => 'ai_provider.openai.max_output_tokens',
            'openai.system_prompt' => 'ai_provider.openai.system_prompt',
            'document_extraction_daily_limit' => 'ai_provider.document_extraction_daily_limit',
            'google_document_ai.enabled' => 'ai_provider.google_document_ai.enabled',
            'google_document_ai.project_id' => 'ai_provider.google_document_ai.project_id',
            'google_document_ai.location' => 'ai_provider.google_document_ai.location',
            'google_document_ai.processor_id' => 'ai_provider.google_document_ai.processor_id',
            'google_document_ai.service_account_json' => 'ai_provider.google_document_ai.service_account_json',
        ];

        foreach ($dottedKeys as $targetKey => $sourceKey) {
            if (!array_key_exists($sourceKey, $all)) {
                continue;
            }

            data_set($payload, $targetKey, $all[$sourceKey]);
        }

        $request->merge(['ai_provider' => $payload]);
    }

    private function flatten(array $input, string $prefix = ''): array
    {
        $flat = [];

        foreach ($input as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $fullKey));
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flat[$fullKey] = (string) ($value ?? '');
            }
        }

        return $flat;
    }

    private function translatableSettings(array $settings): array
    {
        $settings = $this->withApplicationNavigationLink($settings);

        foreach (LandingPageSettings::contentKeys() as $key) {
            if (isset($settings[$key]) && is_array($settings[$key])) {
                unset($settings[$key]['enabled']);
            }
        }

        return $settings;
    }

    private function withApplicationNavigationLink(array $settings): array
    {
        if (data_get($settings, 'mobile_apps_section.enabled') === false) {
            return $settings;
        }

        $links = data_get($settings, 'navigation.links', []);
        if (!is_array($links)) {
            return $settings;
        }

        foreach ($links as $index => $link) {
            if (is_array($link) && in_array(($link['href'] ?? null), ['/applications', '/car-rental-apps', '#application'], true)) {
                $links[$index]['href'] = '#application';
                data_set($settings, 'navigation.links', array_values($links));

                return $settings;
            }
        }

        $applicationLink = ['label' => 'Application', 'href' => '#application'];
        $featuresIndex = null;

        foreach ($links as $index => $link) {
            if (is_array($link) && ($link['href'] ?? null) === '#features') {
                $featuresIndex = $index;
                break;
            }
        }

        if ($featuresIndex === null) {
            $links[] = $applicationLink;
        } else {
            array_splice($links, $featuresIndex + 1, 0, [$applicationLink]);
        }

        data_set($settings, 'navigation.links', array_values($links));

        return $settings;
    }

    private function isLandingTranslationKey(string $key): bool
    {
        foreach ([
            '.enabled',
            '_enabled',
            '.image_url',
            '.icon_url',
            '.app_store_url',
            '.google_play_url',
        ] as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return false;
            }
        }

        return true;
    }
}
