<?php

namespace App\Core;

use App\Models\SiteSetting;

class AppBrandingSettings
{
    public const KEY = 'app_branding';

    public static function defaults(): array
    {
        return [
            'app_name' => config('app.name', 'Real Rent Car'),
            'logo_url' => null,
            'favicon_url' => null,
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

        if ($source instanceof SiteSetting) {
            $file = $source->relationLoaded('files')
                ? $source->files->firstWhere('collection', 'logo')
                : $source->files()->where('collection', 'logo')->first();

            if ($file && $file->path) {
                $logoUrl = SiteSetting::publicUrlFromPath($file->path);
            }

            $faviconFile = $source->relationLoaded('files')
                ? $source->files->firstWhere('collection', 'favicon')
                : $source->files()->where('collection', 'favicon')->first();

            if ($faviconFile && $faviconFile->path) {
                $faviconUrl = SiteSetting::publicUrlFromPath($faviconFile->path);
            }
        }

        return [
            'app_name' => trim((string) ($data['app_name'] ?? $defaults['app_name'])) ?: $defaults['app_name'],
            'logo_url' => self::nullableString($logoUrl ?: ($data['logo_url'] ?? $defaults['logo_url'])),
            'favicon_url' => self::nullableString($faviconUrl ?: ($data['favicon_url'] ?? $defaults['favicon_url'])),
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
}
