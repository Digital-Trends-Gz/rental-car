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

    public function translations(): Response
    {
        $settings = $this->landingSettings();
        $supportedLocales = $this->supportedLocaleKeys();
        $supportedLocaleMeta = LaravelLocalization::getSupportedLocales();
        $defaultRows = $this->defaultTranslationRows('en');

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
        }, $keys);

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
        $translations = [];
        foreach ($supportedLocales as $locale) {
            $translations[$locale] = [];
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
        $rows = array_merge($rows, [
            'fleet.fuel_types.gasoline' => 'Gasoline',
            'fleet.fuel_types.diesel' => 'Diesel',
            'fleet.fuel_types.electric' => 'Electric',
            'fleet.fuel_types.hybrid' => 'Hybrid',
            'dashboard.admin.car_statuses.draft' => 'Draft',
            'dashboard.admin.car_statuses.available' => 'Available',
            'dashboard.admin.car_statuses.reserved' => 'Reserved',
            'dashboard.admin.car_statuses.rented' => 'Rented',
            'dashboard.admin.car_statuses.maintenance' => 'Maintenance',
            'dashboard.admin.car_statuses.cleaning' => 'Cleaning',
            'dashboard.admin.car_statuses.unavailable' => 'Unavailable',
            'dashboard.admin.car_statuses.retired' => 'Retired',
            'navigation.nav_clients' => 'Clients',
            'navigation.nav_contact' => 'Contact',
            'static_pages.privacy_policy.title' => 'Privacy Policy',
            'static_pages.terms_of_use.title' => 'Terms of Use',
            'static_pages.security_policy.title' => 'Security Policy',
            'validation.letters_only' => 'This field must contain letters only and cannot include numbers.',
            'validation.password.mixed' => 'The password field must contain at least one uppercase and one lowercase letter.',
            'validation.confirmed' => 'The password field confirmation does not match.',
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
