<?php

namespace App\Core;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SecurityAccessSettings
{
    public const KEY = 'security_access';

    public static function defaults(): array
    {
        return [
            'superadmin_allowed_countries' => [],
            'superadmin_allowed_ips' => [],
            'superadmin_blocked_ips' => [],
            'website_blocked_ips' => [],
            'device_limit_enabled' => false,
            'max_devices_per_user' => 2,
            'device_limit_roles' => ['client', 'admin'],
        ];
    }

    public static function load(): array
    {
        if (!Schema::hasTable('site_settings')) {
            return self::defaults();
        }

        return Cache::rememberForever(self::cacheKey(), function () {
            $stored = SiteSetting::query()
                ->where('key', self::KEY)
                ->value('value');

            return self::normalize(is_array($stored) ? $stored : null);
        });
    }

    public static function persist(array $data): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => self::normalize($data)]
        );

        Cache::forget(self::cacheKey());
    }

    public static function normalize(?array $data): array
    {
        return [
            'superadmin_allowed_countries' => self::normalizeCountries($data['superadmin_allowed_countries'] ?? []),
            'superadmin_allowed_ips' => self::normalizeIpList($data['superadmin_allowed_ips'] ?? []),
            'superadmin_blocked_ips' => self::normalizeIpList($data['superadmin_blocked_ips'] ?? []),
            'website_blocked_ips' => self::normalizeIpList($data['website_blocked_ips'] ?? []),
            'device_limit_enabled' => filter_var($data['device_limit_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'max_devices_per_user' => max(1, min(25, (int) ($data['max_devices_per_user'] ?? 2))),
            'device_limit_roles' => self::normalizeDeviceLimitRoles($data['device_limit_roles'] ?? ['client', 'admin']),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function parseCountryInput(?string $value): array
    {
        return self::normalizeCountries(self::splitLinesAndCsv($value));
    }

    /**
     * @return array<int, string>
     */
    public static function parseIpInput(?string $value): array
    {
        return self::normalizeIpList(self::splitLinesAndCsv($value));
    }

    private static function cacheKey(): string
    {
        return 'site_setting:'.self::KEY;
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, string>
     */
    private static function normalizeCountries(array $items): array
    {
        return array_values(array_unique(array_filter(array_map(function ($item) {
            $country = strtoupper(trim((string) $item));

            return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
        }, $items))));
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, string>
     */
    private static function normalizeIpList(array $items): array
    {
        return array_values(array_unique(array_filter(array_map(function ($item) {
            $value = trim((string) $item);

            return $value !== '' ? $value : null;
        }, $items))));
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, string>
     */
    private static function normalizeDeviceLimitRoles(array $items): array
    {
        $allowed = ['all', 'admin', 'client'];

        return array_values(array_unique(array_filter(array_map(function ($item) use ($allowed) {
            $value = strtolower(trim((string) $item));

            return in_array($value, $allowed, true) ? $value : null;
        }, $items)))) ?: ['client', 'admin'];
    }

    /**
     * @return array<int, string>
     */
    private static function splitLinesAndCsv(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $value) ?: [];

        return array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $parts)));
    }
}
