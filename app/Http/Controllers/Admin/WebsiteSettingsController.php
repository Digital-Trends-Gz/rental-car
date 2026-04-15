<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\TenantSiteSetting;
use App\Support\BrandLogoImageResizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        return Inertia::render('Admin/Settings/Website', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => TenantSiteSetting::forTenant($tenant),
            'logoFiles' => $logoFiles,
            'actions' => [
                // Use current localized URL so PUT does not lose locale prefix (/ar or /en).
                'update' => url()->current(),
            ],
        ]);
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

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
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
}
