<?php

namespace App\Core;

use App\Models\SiteSetting;

class AppBrandingSettings
{
    public const KEY = 'app_branding';

    public static function defaults(): array
    {
        return [
            'app_name' => config('app.name', 'Car4u'),
            'logo_url' => null,
            'favicon_url' => null,
            'register_hero_images' => [],
            'primary_color' => '#3b82f6',
            'secondary_color' => '#6d28d9',
        ];
    }

    public static function load(): array
    {
        $setting = SiteSetting::query()
            ->with('files')
            ->where('key', self::KEY)
            ->first();

        return self::normalize($setting);
    }

    public static function normalize(SiteSetting|array|null $source): array
    {
        $defaults = self::defaults();
        $data = $source instanceof SiteSetting ? ($source->value ?? []) : $source;
        $logoUrl = null;
        $faviconUrl = null;
        $registerHeroImages = [];
        $supportedLocales = self::supportedLocales();

        if ($source instanceof SiteSetting) {
            $file = $source->relationLoaded('files')
                ? $source->files->where('collection', 'logo')->sortByDesc('id')->first()
                : $source->files()->where('collection', 'logo')->latest('id')->first();

            if ($file && $file->path) {
                $logoUrl = SiteSetting::publicUrlFromPath($file->path);
            }

            $faviconFile = $source->relationLoaded('files')
                ? $source->files->where('collection', 'favicon')->sortByDesc('id')->first()
                : $source->files()->where('collection', 'favicon')->latest('id')->first();

            if ($faviconFile && $faviconFile->path) {
                $faviconUrl = SiteSetting::publicUrlFromPath($faviconFile->path);
            }

            foreach ($supportedLocales as $locale) {
                $collection = self::registerHeroCollection($locale);
                $heroFile = $source->relationLoaded('files')
                    ? $source->files->where('collection', $collection)->sortByDesc('id')->first()
                    : $source->files()->where('collection', $collection)->latest('id')->first();

                if ($heroFile && $heroFile->path) {
                    $registerHeroImages[$locale] = SiteSetting::publicUrlFromPath($heroFile->path);
                }
            }
        }

        foreach ($supportedLocales as $locale) {
            $storedUrl = data_get($data, "register_hero_images.$locale");
            $registerHeroImages[$locale] = self::nullableString($registerHeroImages[$locale] ?? $storedUrl);
        }

        return [
            'app_name' => trim((string) ($data['app_name'] ?? $defaults['app_name'])) ?: $defaults['app_name'],
            'logo_url' => self::nullableString($logoUrl ?: ($data['logo_url'] ?? $defaults['logo_url'])),
            'favicon_url' => self::nullableString($faviconUrl ?: ($data['favicon_url'] ?? $defaults['favicon_url'])),
            'register_hero_images' => $registerHeroImages,
            'primary_color' => self::normalizeHexColor($data['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color']),
            'secondary_color' => self::normalizeHexColor($data['secondary_color'] ?? $defaults['secondary_color'], $defaults['secondary_color']),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private static function normalizeHexColor(mixed $value, string $fallback): string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        if (!preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $normalized)) {
            return $fallback;
        }

        return $normalized;
    }

    public static function registerHeroCollection(string $locale): string
    {
        $locale = preg_replace('/[^a-z0-9_-]/i', '', strtolower($locale)) ?: 'default';

        return 'register_hero_'.$locale;
    }

    /**
     * @return array<int, string>
     */
    private static function supportedLocales(): array
    {
        $locales = array_values(array_filter(array_map(
            'strval',
            (array) config('app.available_locales', ['en'])
        )));

        return empty($locales) ? ['en'] : $locales;
    }
}
