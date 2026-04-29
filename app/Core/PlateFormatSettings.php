<?php

namespace App\Core;

use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\SiteSetting;
use App\Core\TenantContext;
use Illuminate\Support\Str;

class PlateFormatSettings
{
    public const KEY = 'plate_formats';
    public const GLOBAL_KEY = 'plate_format_templates';

    public static function defaults(): array
    {
        return [];
    }

    public static function load(?Tenant $tenant = null): array
    {
        $tenant ??= TenantContext::get();

        if (!$tenant) {
            return self::normalize(null);
        }

        $stored = TenantSiteSetting::query()
            ->where('tenant_id', $tenant->id)
            ->value(self::KEY);

        return self::normalize(is_array($stored) ? $stored : null);
    }

    public static function loadGlobal(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::GLOBAL_KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    public static function normalize(?array $data): array
    {
        $items = is_array($data) ? $data : [];
        $formats = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $country = trim((string) ($item['country'] ?? ''));
            $mask = self::normalizeMask($item['mask'] ?? '');
            $example = trim((string) ($item['example'] ?? ''));
            $isActive = array_key_exists('is_active', $item) ? (bool) $item['is_active'] : true;
            $code = trim((string) ($item['code'] ?? ''));

            if ($name === '' && $country === '' && $mask === '' && $example === '') {
                continue;
            }

            if ($code === '') {
                $code = self::generateCode($name !== '' ? $name : ($mask !== '' ? $mask : 'format'), $index);
            }

            $formats[] = [
                'code' => $code,
                'name' => $name,
                'country' => $country,
                'mask' => $mask,
                'example' => $example,
                'is_active' => $isActive,
            ];
        }

        return array_values($formats);
    }

    public static function activeFormats(array $settings): array
    {
        return array_values(array_filter(
            $settings,
            static fn ($item) => is_array($item) && !empty($item['is_active'])
        ));
    }

    public static function findByCode(array $settings, ?string $code): ?array
    {
        $needle = trim((string) ($code ?? ''));
        if ($needle === '') {
            return null;
        }

        foreach ($settings as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (strcasecmp((string) ($item['code'] ?? ''), $needle) === 0) {
                return $item;
            }
        }

        return null;
    }

    public static function matchesPlate(?string $plate, ?string $code, array $settings): bool
    {
        $plate = self::normalizePlate($plate);
        if ($plate === '') {
            return false;
        }

        $format = self::findByCode($settings, $code);
        if (!$format || empty($format['mask'])) {
            return true;
        }

        $regex = self::maskToRegex((string) $format['mask']);
        if ($regex === null) {
            return true;
        }

        return (bool) preg_match($regex, $plate);
    }

    public static function normalizePlate(?string $plate): string
    {
        $plate = trim((string) ($plate ?? ''));

        return preg_replace('/\s+/', ' ', Str::upper($plate)) ?? Str::upper($plate);
    }

    public static function maskToRegex(string $mask): ?string
    {
        $mask = self::normalizeMask($mask);
        if ($mask === '') {
            return null;
        }

        $regex = '';
        $length = mb_strlen($mask);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($mask, $i, 1);

            $regex .= match ($char) {
                '#', '9', 'N' => '\d',
                'A' => '[A-Z]',
                'X' => '[A-Z0-9]',
                ' ' => '\s+',
                default => preg_quote($char, '/'),
            };
        }

        return '/^' . $regex . '$/i';
    }

    private static function normalizeMask(mixed $mask): string
    {
        $mask = trim((string) ($mask ?? ''));
        if ($mask === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', Str::upper($mask)) ?? Str::upper($mask);
    }

    private static function generateCode(string $base, int $index): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'format';
        }

        return $slug . '-' . $index . '-' . Str::lower(Str::random(6));
    }
}
