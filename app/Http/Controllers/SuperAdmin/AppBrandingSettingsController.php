<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\AppBrandingSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\BrandLogoImageResizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class AppBrandingSettingsController extends Controller
{
    public function __construct(
        private readonly FilePondService $filePondService,
        private readonly BrandLogoImageResizer $brandLogoImageResizer,
    ) {}

    public function edit(): Response
    {
        $brandingSetting = SiteSetting::query()
            ->with('files')
            ->where('key', AppBrandingSettings::KEY)
            ->first();

        $logoFiles = $brandingSetting
            ? $brandingSetting->files()
                ->where('collection', 'logo')
                ->get()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => SiteSetting::publicUrlFromPath($file->path),
                ])
                ->values()
                ->all()
            : [];

        $faviconFiles = $brandingSetting
            ? $brandingSetting->files()
                ->where('collection', 'favicon')
                ->get()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => SiteSetting::publicUrlFromPath($file->path),
                ])
                ->values()
                ->all()
            : [];
        $supportedLocales = $this->supportedLocaleOptions();
        $registerHeroFiles = [];

        foreach ($supportedLocales as $locale) {
            $code = (string) $locale['code'];
            $collection = AppBrandingSettings::registerHeroCollection($code);
            $registerHeroFiles[$code] = $brandingSetting
                ? $brandingSetting->files()
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

        return Inertia::render('SuperAdmin/Settings/Branding', [
            'settings' => AppBrandingSettings::normalize($brandingSetting),
            'logoFiles' => $logoFiles,
            'faviconFiles' => $faviconFiles,
            'registerHeroFiles' => $registerHeroFiles,
            'supportedLocales' => $supportedLocales,
            'actions' => [
                'update' => route('superadmin.settings.branding.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2000'],
            'favicon_url' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'secondary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'logo_temp_folders' => ['array'],
            'logo_temp_folders.*' => ['string'],
            'logo_removed_files' => ['array'],
            'logo_removed_files.*' => ['integer'],
            'favicon_temp_folders' => ['array'],
            'favicon_temp_folders.*' => ['string'],
            'favicon_removed_files' => ['array'],
            'favicon_removed_files.*' => ['integer'],
            'register_hero_temp_folders' => ['array'],
            'register_hero_temp_folders.*' => ['array'],
            'register_hero_temp_folders.*.*' => ['string'],
            'register_hero_removed_files' => ['array'],
            'register_hero_removed_files.*' => ['array'],
            'register_hero_removed_files.*.*' => ['integer'],
        ]);

        $brandingSetting = SiteSetting::query()->updateOrCreate(
            ['key' => AppBrandingSettings::KEY],
            ['value' => AppBrandingSettings::normalize($validated)]
        );

        $tempFolders = $request->input('logo_temp_folders', []);
        $removedIds = $request->input('logo_removed_files', []);

        if (!empty($tempFolders)) {
            $existingIds = $brandingSetting->files()->where('collection', 'logo')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $brandingSetting,
            is_array($tempFolders) ? $tempFolders : [],
            is_array($removedIds) ? $removedIds : [],
            'logo'
        );

        if (!empty($tempFolders)) {
            $logoFile = $brandingSetting->files()
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
            $existingFaviconIds = $brandingSetting->files()->where('collection', 'favicon')->pluck('id')->all();
            $faviconRemovedIds = array_values(array_unique(array_merge($faviconRemovedIds, $existingFaviconIds)));
        }

        $this->filePondService->handleFileUpdates(
            $brandingSetting,
            is_array($faviconTempFolders) ? $faviconTempFolders : [],
            is_array($faviconRemovedIds) ? $faviconRemovedIds : [],
            'favicon'
        );

        $settings = AppBrandingSettings::normalize($brandingSetting->fresh('files'));
        $registerHeroImages = is_array($settings['register_hero_images'] ?? null)
            ? $settings['register_hero_images']
            : [];
        $registerHeroTempFolders = is_array($request->input('register_hero_temp_folders', []))
            ? $request->input('register_hero_temp_folders', [])
            : [];
        $registerHeroRemovedFiles = is_array($request->input('register_hero_removed_files', []))
            ? $request->input('register_hero_removed_files', [])
            : [];

        foreach ($this->supportedLocaleCodes() as $locale) {
            $collection = AppBrandingSettings::registerHeroCollection($locale);
            $localeTempFolders = is_array($registerHeroTempFolders[$locale] ?? null)
                ? array_values(array_filter($registerHeroTempFolders[$locale]))
                : [];
            $localeRemovedIds = is_array($registerHeroRemovedFiles[$locale] ?? null)
                ? array_values(array_unique(array_filter($registerHeroRemovedFiles[$locale])))
                : [];

            if (!empty($localeTempFolders)) {
                $existingIds = $brandingSetting->files()->where('collection', $collection)->pluck('id')->all();
                $localeRemovedIds = array_values(array_unique(array_merge($localeRemovedIds, $existingIds)));
            }

            $this->filePondService->handleFileUpdates(
                $brandingSetting,
                $localeTempFolders,
                $localeRemovedIds,
                $collection
            );

            if (!empty($localeTempFolders)) {
                $heroFile = $brandingSetting->files()
                    ->where('collection', $collection)
                    ->latest('id')
                    ->first();

                $registerHeroImages[$locale] = $heroFile
                    ? (SiteSetting::publicUrlFromPath($heroFile->path) ?? null)
                    : ($registerHeroImages[$locale] ?? null);
            } elseif (!empty($localeRemovedIds)) {
                $registerHeroImages[$locale] = null;
            }
        }

        $brandingSetting->update([
            'value' => AppBrandingSettings::normalize([
                ...($brandingSetting->value ?? []),
                'register_hero_images' => $registerHeroImages,
            ]),
        ]);

        return back()->with('success', 'Application branding updated successfully.');
    }

    /**
     * @return array<int, array{code: string, name: string, native: string}>
     */
    private function supportedLocaleOptions(): array
    {
        $meta = LaravelLocalization::getSupportedLocales();

        return array_map(function (string $code) use ($meta): array {
            $localeMeta = (array) ($meta[$code] ?? []);

            return [
                'code' => $code,
                'name' => (string) ($localeMeta['name'] ?? strtoupper($code)),
                'native' => (string) ($localeMeta['native'] ?? strtoupper($code)),
            ];
        }, $this->supportedLocaleCodes());
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocaleCodes(): array
    {
        $locales = array_values(array_filter(array_map(
            'strval',
            (array) config('app.available_locales', ['en'])
        )));

        return empty($locales) ? ['en'] : $locales;
    }
}
