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

        return Inertia::render('SuperAdmin/Settings/Branding', [
            'settings' => AppBrandingSettings::normalize($brandingSetting),
            'logoFiles' => $logoFiles,
            'faviconFiles' => $faviconFiles,
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

        return back()->with('success', 'Application branding updated successfully.');
    }
}
