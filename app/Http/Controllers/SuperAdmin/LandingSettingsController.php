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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
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

    public function design(): Response
    {
        return Inertia::render('SuperAdmin/Settings/Design', [
            'settings' => $this->landingSettings(),
            'previewUrl' => route('home'),
        ]);
    }

    public function update(Request $request): RedirectResponse
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

        $this->syncHeroImageUpload($request, $landingSetting, $normalized);

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
        $this->syncHeroImageUpload($request, $landingSetting, $normalized);

        return back()->with('success', 'Landing page design updated successfully.');
    }

    public function testAiConnection(Request $request): JsonResponse
    {
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

    private function persistLandingSettings(array $settings): SiteSetting
    {
        return SiteSetting::query()->updateOrCreate(
            ['key' => LandingPageSettings::KEY],
            ['value' => $settings]
        );
    }

    private function syncHeroImageUpload(Request $request, SiteSetting $landingSetting, array &$settings): void
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
        $landingSetting->update(['value' => $settings]);
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
}
