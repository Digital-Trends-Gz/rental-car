<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\AiAutomationSettings;
use App\Core\AiProviderSettings;
use App\Core\LandingPageSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
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

        $keys = array_values(array_unique($keyPool));
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
        return Inertia::render('SuperAdmin/Settings/Design', [
            'settings' => $this->landingSettings(),
            'previewUrl' => route('home'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->normalizeAiProviderPayload($request);

        $validated = $request->validate([
            'settings.hero.title' => ['required', 'string', 'max:255'],
            'settings.hero.description' => ['required', 'string', 'max:2000'],
            'settings.hero.features' => ['nullable', 'array'],
            'settings.hero.features.*' => ['nullable', 'string', 'max:255'],
            'settings.hero.image_url' => ['nullable', 'string', 'max:2000'],

            'settings.features_section.title' => ['required', 'string', 'max:255'],
            'settings.features_section.description' => ['required', 'string', 'max:2000'],
            'settings.features_section.cards' => ['nullable', 'array'],
            'settings.features_section.cards.*.title' => ['nullable', 'string', 'max:255'],
            'settings.features_section.cards.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.cards.*.content' => ['nullable', 'string', 'max:2000'],

            'settings.getting_started.title' => ['required', 'string', 'max:255'],
            'settings.getting_started.description' => ['required', 'string', 'max:2000'],
            'settings.getting_started.items' => ['nullable', 'array'],
            'settings.getting_started.items.*.title' => ['nullable', 'string', 'max:255'],
            'settings.getting_started.items.*.description' => ['nullable', 'string', 'max:2000'],

            'settings.plans_section.title' => ['required', 'string', 'max:255'],
            'settings.plans_section.description' => ['required', 'string', 'max:2000'],

            'settings.faq_section.title' => ['required', 'string', 'max:255'],
            'settings.faq_section.description' => ['required', 'string', 'max:2000'],
            'settings.faq_section.items' => ['nullable', 'array'],
            'settings.faq_section.items.*.question' => ['nullable', 'string', 'max:2000'],
            'settings.faq_section.items.*.answer' => ['nullable', 'string', 'max:5000'],

            'settings.footer.title' => ['required', 'string', 'max:255'],
            'settings.footer.description' => ['required', 'string', 'max:2000'],

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
            LandingPageSettings::localize($settings, $locale),
            LandingPageSettings::contentKeys()
        ));

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
        $landingSetting->update(['value' => LandingPageSettings::normalize($settings)]);
    }

    private function validatedLandingSettings(Request $request): array
    {
        $validated = $request->validate([
            'settings.hero.title' => ['required', 'string', 'max:255'],
            'settings.hero.description' => ['required', 'string', 'max:2000'],
            'settings.hero.features' => ['nullable', 'array'],
            'settings.hero.features.*' => ['nullable', 'string', 'max:255'],
            'settings.hero.image_url' => ['nullable', 'string', 'max:2000'],

            'settings.features_section.title' => ['required', 'string', 'max:255'],
            'settings.features_section.description' => ['required', 'string', 'max:2000'],
            'settings.features_section.cards' => ['nullable', 'array'],
            'settings.features_section.cards.*.title' => ['nullable', 'string', 'max:255'],
            'settings.features_section.cards.*.image_url' => ['nullable', 'string', 'max:2000'],
            'settings.features_section.cards.*.content' => ['nullable', 'string', 'max:2000'],

            'settings.getting_started.title' => ['required', 'string', 'max:255'],
            'settings.getting_started.description' => ['required', 'string', 'max:2000'],
            'settings.getting_started.items' => ['nullable', 'array'],
            'settings.getting_started.items.*.title' => ['nullable', 'string', 'max:255'],
            'settings.getting_started.items.*.description' => ['nullable', 'string', 'max:2000'],

            'settings.plans_section.title' => ['required', 'string', 'max:255'],
            'settings.plans_section.description' => ['required', 'string', 'max:2000'],

            'settings.faq_section.title' => ['required', 'string', 'max:255'],
            'settings.faq_section.description' => ['required', 'string', 'max:2000'],
            'settings.faq_section.items' => ['nullable', 'array'],
            'settings.faq_section.items.*.question' => ['nullable', 'string', 'max:2000'],
            'settings.faq_section.items.*.answer' => ['nullable', 'string', 'max:5000'],

            'settings.footer.title' => ['required', 'string', 'max:255'],
            'settings.footer.description' => ['required', 'string', 'max:2000'],
        ]);

        return LandingPageSettings::normalize($validated['settings'] ?? []);
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
}
