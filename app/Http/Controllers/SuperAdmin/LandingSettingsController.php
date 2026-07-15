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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Arr;
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
            $overrideRowsByLocale[$locale] = $this->flatten((array) data_get($settings, "translations.$locale", []));
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
            'featureFiles' => $this->featureCardFiles($brandingSetting),
            'mobileAppFiles' => $this->mobileAppFiles($brandingSetting),
        ]);
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
            'settings.features_section.enabled' => ['nullable', 'boolean'],
            'settings.features_section.title' => ['required', 'string', 'max:255'],
            'settings.features_section.description' => ['required', 'string', 'max:2000'],
            'settings.features_section.cards' => ['nullable', 'array'],
            'settings.features_section.cards.*.title' => ['nullable', 'string', 'max:255'],
            'settings.features_section.cards.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.cards.*.content' => ['nullable', 'string', 'max:2000'],

            'settings.getting_started.enabled' => ['nullable', 'boolean'],
            'settings.getting_started.title' => ['required', 'string', 'max:255'],
            'settings.getting_started.description' => ['required', 'string', 'max:2000'],
            'settings.getting_started.items' => ['nullable', 'array'],
            'settings.getting_started.items.*.title' => ['nullable', 'string', 'max:255'],
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
            'settings.mobile_apps_section.apps.*.icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.app_store_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.google_play_url' => ['nullable', 'string', 'max:2000'],
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
            'settings.footer.android_label' => ['required', 'string', 'max:255'],
            'settings.footer.android_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.ios_label' => ['required', 'string', 'max:255'],
            'settings.footer.ios_url' => ['nullable', 'string', 'max:2000'],
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
        $this->syncMobileAppUploads($request, $landingSetting);

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
        $normalized = $this->validatedLandingSettings($request);
        $landingSetting = $this->persistLandingSettings($normalized);
        $this->syncHeroImageUpload($request, $landingSetting);
        $this->syncFeatureCardUploads($request, $landingSetting);
        $this->syncMobileAppUploads($request, $landingSetting);

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
        $stored = SiteSetting::query()
            ->where('key', LandingPageSettings::KEY)
            ->value('value');

        return LandingPageSettings::normalize(is_array($stored) ? $stored : null);
    }

    private function defaultTranslationRows(string $locale): array
    {
        $settings = $this->landingSettings();
        $rows = $this->flatten(Arr::only(
            $this->translatableSettings(LandingPageSettings::localize($settings, $locale)),
            LandingPageSettings::contentKeys()
        ));
        $rows = array_merge($rows, $this->flatten(PlanTranslations::defaultTranslationTree()));

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
                $rows = array_merge($rows, $this->flatten([$group => $translations]));
            }
        }

        return $rows;
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

    private function syncHeroImageUpload(Request $request, SiteSetting $landingSetting): void
    {
        $tempFolders = is_array($request->input('hero_temp_folders', []))
            ? array_values(array_filter($request->input('hero_temp_folders', [])))
            : [];
        $removedIds = is_array($request->input('hero_removed_files', []))
            ? array_values(array_unique(array_filter($request->input('hero_removed_files', []))))
            : [];

        if (!empty($tempFolders)) {
            $existingIds = $landingSetting->files()->where('collection', 'hero')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $landingSetting,
            $tempFolders,
            $removedIds,
            'hero'
        );

        $settings = is_array($landingSetting->value) ? $landingSetting->value : $this->landingSettings();
        $heroImageUrl = trim((string) data_get($settings, 'hero.image_url', ''));

        if (!empty($tempFolders)) {
            $heroFile = $landingSetting->files()
                ->where('collection', 'hero')
                ->latest('id')
                ->first();

            $heroImageUrl = $heroFile
                ? (SiteSetting::publicUrlFromPath($heroFile->path) ?? '')
                : $heroImageUrl;
        } elseif (!empty($removedIds)) {
            $heroImageUrl = '';
        }

        data_set($settings, 'hero.image_url', $heroImageUrl);

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

    private function validatedLandingSettings(Request $request): array
    {
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
            'settings.features_section.enabled' => ['nullable', 'boolean'],
            'settings.features_section.title' => ['required', 'string', 'max:255'],
            'settings.features_section.description' => ['required', 'string', 'max:2000'],
            'settings.features_section.cards' => ['nullable', 'array'],
            'settings.features_section.cards.*.title' => ['nullable', 'string', 'max:255'],
            'settings.features_section.cards.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.cards.*.content' => ['nullable', 'string', 'max:2000'],

            'settings.getting_started.enabled' => ['nullable', 'boolean'],
            'settings.getting_started.title' => ['required', 'string', 'max:255'],
            'settings.getting_started.description' => ['required', 'string', 'max:2000'],
            'settings.getting_started.items' => ['nullable', 'array'],
            'settings.getting_started.items.*.title' => ['nullable', 'string', 'max:255'],
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
            'settings.mobile_apps_section.apps.*.icon_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.app_store_url' => ['nullable', 'string', 'max:2000'],
            'settings.mobile_apps_section.apps.*.google_play_url' => ['nullable', 'string', 'max:2000'],
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
            'settings.footer.android_label' => ['required', 'string', 'max:255'],
            'settings.footer.android_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.ios_label' => ['required', 'string', 'max:255'],
            'settings.footer.ios_url' => ['nullable', 'string', 'max:2000'],
            'settings.footer.social_links' => ['nullable', 'array'],
            'settings.footer.social_links.*.label' => ['nullable', 'string', 'max:255'],
            'settings.footer.social_links.*.platform' => ['nullable', 'string', 'max:50'],
            'settings.footer.social_links.*.href' => ['nullable', 'string', 'max:2000'],
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
     * @return array<int, array{image: array<int, array{id: int, url: string|null}>, icon: array<int, array{id: int, url: string|null}>}>
     */
    private function mobileAppFiles(?SiteSetting $landingSetting): array
    {
        $settings = $this->landingSettings();
        $apps = (array) data_get($settings, 'mobile_apps_section.apps', []);
        $files = [];

        foreach (array_keys($apps) as $index) {
            $files[(int) $index] = [
                'image' => $this->landingFilesForCollection($landingSetting, $this->mobileAppCollection((int) $index, 'image')),
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

    private function featureCardCollection(int $index): string
    {
        return 'feature_card_' . max(0, $index) . '_image';
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

        foreach ($links as $link) {
            if (is_array($link) && ($link['href'] ?? null) === '#application') {
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
