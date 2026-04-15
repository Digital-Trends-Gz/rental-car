<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class TenantSiteSetting extends Model
{
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'site_name',
        'logo_url',
        'primary_color',
        'secondary_color',
        'tax_percentage',
        'enabled_locales',
        'hero',
        'about',
        'contact',
        'contact_page',
        'pdf_header',
        'translations',
        'footer',
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
        'enabled_locales' => 'array',
        'hero' => 'array',
        'about' => 'array',
        'contact' => 'array',
        'contact_page' => 'array',
        'pdf_header' => 'array',
        'translations' => 'array',
        'footer' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function defaults(): array
    {
        $supportedLocales = self::supportedLocales();

        return [
            'site_name' => null,
            'logo_url' => null,
            'primary_color' => '#f97316',
            'secondary_color' => '#ea580c',
            'tax_percentage' => 7.0,
            'enabled_locales' => $supportedLocales,
            'hero' => [
                'title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'description' => [
                    'en' => null,
                    'ar' => null,
                ],
                'button_text' => [
                    'en' => null,
                    'ar' => null,
                ],
                'button_link' => null,
            ],
            'about' => [
                'title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'story_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'story_p1' => [
                    'en' => null,
                    'ar' => null,
                ],
                'story_p2' => [
                    'en' => null,
                    'ar' => null,
                ],
                'mission_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'mission_subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_browse_text' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_contact_text' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'contact' => [
                'phone' => null,
                'email' => null,
                'address' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'contact_page' => [
                'title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'form_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'info_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'hours' => [
                    'en' => null,
                    'ar' => null,
                ],
                'quick_links_title' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'pdf_header' => [
                'company_name' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cr_number' => null,
                'po_box' => null,
                'pc' => null,
                'country' => [
                    'en' => null,
                    'ar' => null,
                ],
                'gsm_1' => null,
                'gsm_2' => null,
                'gsm_3' => null,
                'registry_label' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'translations' => [
                ...array_fill_keys($supportedLocales, []),
            ],
            'footer' => [
                'description' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
        ];
    }

    public static function normalize(?self $settings): array
    {
        $defaults = self::defaults();
        $data = $settings?->toArray() ?? [];
        $logoUrl = null;

        if ($settings) {
            $file = $settings->relationLoaded('files')
                ? $settings->files->firstWhere('collection', 'logo')
                : $settings->files()->where('collection', 'logo')->first();

            if ($file && $file->path) {
                $logoUrl = self::publicUrlFromPath($file->path);
            }
        }

        return [
            'site_name' => self::nullableString($data['site_name'] ?? $defaults['site_name']),
            'logo_url' => self::nullableString($logoUrl ?: ($data['logo_url'] ?? $defaults['logo_url'])),
            'primary_color' => self::normalizeHexColor($data['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color']),
            'secondary_color' => self::normalizeHexColor($data['secondary_color'] ?? $defaults['secondary_color'], $defaults['secondary_color']),
            'tax_percentage' => self::normalizePercentage($data['tax_percentage'] ?? $defaults['tax_percentage'], 7.0),
            'enabled_locales' => self::normalizeEnabledLocales($data['enabled_locales'] ?? $defaults['enabled_locales']),
            'hero' => [
                'title' => [
                    'en' => self::nullableString(data_get($data, 'hero.title.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.title.ar')),
                ],
                'description' => [
                    'en' => self::nullableString(data_get($data, 'hero.description.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.description.ar')),
                ],
                'button_text' => [
                    'en' => self::nullableString(data_get($data, 'hero.button_text.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.button_text.ar')),
                ],
                'button_link' => self::nullableString(data_get($data, 'hero.button_link')),
            ],
            'about' => [
                'title' => [
                    'en' => self::nullableString(data_get($data, 'about.title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.title.ar')),
                ],
                'subtitle' => [
                    'en' => self::nullableString(data_get($data, 'about.subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'about.subtitle.ar')),
                ],
                'story_title' => [
                    'en' => self::nullableString(data_get($data, 'about.story_title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.story_title.ar')),
                ],
                'story_p1' => [
                    'en' => self::nullableString(data_get($data, 'about.story_p1.en')),
                    'ar' => self::nullableString(data_get($data, 'about.story_p1.ar')),
                ],
                'story_p2' => [
                    'en' => self::nullableString(data_get($data, 'about.story_p2.en')),
                    'ar' => self::nullableString(data_get($data, 'about.story_p2.ar')),
                ],
                'mission_title' => [
                    'en' => self::nullableString(data_get($data, 'about.mission_title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.mission_title.ar')),
                ],
                'mission_subtitle' => [
                    'en' => self::nullableString(data_get($data, 'about.mission_subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'about.mission_subtitle.ar')),
                ],
                'cta_title' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_title.ar')),
                ],
                'cta_subtitle' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_subtitle.ar')),
                ],
                'cta_browse_text' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_browse_text.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_browse_text.ar')),
                ],
                'cta_contact_text' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_contact_text.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_contact_text.ar')),
                ],
            ],
            'contact' => [
                'phone' => self::nullableString(data_get($data, 'contact.phone')),
                'email' => self::nullableString(data_get($data, 'contact.email')),
                'address' => [
                    'en' => self::nullableString(data_get($data, 'contact.address.en')),
                    'ar' => self::nullableString(data_get($data, 'contact.address.ar')),
                ],
            ],
            'contact_page' => [
                'title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.title.ar')),
                ],
                'subtitle' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.subtitle.ar')),
                ],
                'form_title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.form_title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.form_title.ar')),
                ],
                'info_title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.info_title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.info_title.ar')),
                ],
                'hours' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.hours.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.hours.ar')),
                ],
                'quick_links_title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.quick_links_title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.quick_links_title.ar')),
                ],
            ],
            'pdf_header' => [
                'company_name' => [
                    'en' => self::nullableString(data_get($data, 'pdf_header.company_name.en')),
                    'ar' => self::nullableString(data_get($data, 'pdf_header.company_name.ar')),
                ],
                'cr_number' => self::nullableString(data_get($data, 'pdf_header.cr_number')),
                'po_box' => self::nullableString(data_get($data, 'pdf_header.po_box')),
                'pc' => self::nullableString(data_get($data, 'pdf_header.pc')),
                'country' => [
                    'en' => self::nullableString(data_get($data, 'pdf_header.country.en')),
                    'ar' => self::nullableString(data_get($data, 'pdf_header.country.ar')),
                ],
                'gsm_1' => self::nullableString(data_get($data, 'pdf_header.gsm_1')),
                'gsm_2' => self::nullableString(data_get($data, 'pdf_header.gsm_2')),
                'gsm_3' => self::nullableString(data_get($data, 'pdf_header.gsm_3')),
                'registry_label' => [
                    'en' => self::nullableString(data_get($data, 'pdf_header.registry_label.en')),
                    'ar' => self::nullableString(data_get($data, 'pdf_header.registry_label.ar')),
                ],
            ],
            'translations' => self::normalizeTranslations($data['translations'] ?? $defaults['translations']),
            'footer' => [
                'description' => [
                    'en' => self::nullableString(data_get($data, 'footer.description.en')),
                    'ar' => self::nullableString(data_get($data, 'footer.description.ar')),
                ],
            ],
        ];
    }

    public static function forTenant(Tenant $tenant): array
    {
        return self::normalize($tenant->siteSetting);
    }

    public static function publicUrlFromPath(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return Storage::url($normalized);
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function normalizeHexColor(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return strtolower($value);
        }

        return strtolower($fallback);
    }

    private static function normalizePercentage(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            return $fallback;
        }

        return max(0, min(100, round($number, 2)));
    }

    private static function normalizeEnabledLocales(mixed $value): array
    {
        $supported = self::supportedLocales();
        $enabled = is_array($value) ? $value : [];
        $enabled = array_values(array_unique(array_intersect($supported, array_map('strval', $enabled))));

        return empty($enabled) ? $supported : $enabled;
    }

    private static function normalizeTranslations(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $supported = self::supportedLocales();
        $result = [];

        foreach ($supported as $locale) {
            $result[$locale] = is_array($value[$locale] ?? null) ? $value[$locale] : [];
        }

        return $result;
    }

    private static function supportedLocales(): array
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));
        if (empty($supported)) {
            $supported = array_values((array) config('app.available_locales', ['en']));
        }

        $supported = array_values(array_unique(array_map('strval', $supported)));

        return empty($supported) ? ['en'] : $supported;
    }
}
