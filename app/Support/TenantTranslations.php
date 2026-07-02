<?php

namespace App\Support;

use App\Core\LandingPageSettings;
use App\Core\TenantContext;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use Throwable;
use Illuminate\Support\Facades\Lang;

class TenantTranslations
{
    public static function get(string $key, ?string $locale = null, ?string $fallback = null, ?Tenant $tenant = null): string
    {
        $locale = self::normalizeLocale($locale ?: app()->getLocale());
        $tenant = $tenant ?: self::resolveTenant();

        if ($tenant) {
            $settings = TenantSiteSetting::forTenant($tenant);
            $override = trim((string) data_get($settings, "translations.{$locale}.{$key}", ''));

            if ($override !== '') {
                return $override;
            }
        }

        $globalOverride = self::globalOverride($key, $locale);
        if ($globalOverride !== null) {
            return $globalOverride;
        }

        if (Lang::has($key, $locale)) {
            $translated = Lang::get($key, [], $locale);

            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        return $fallback ?? $key;
    }

    private static function globalOverride(string $key, string $locale): ?string
    {
        try {
            $stored = SiteSetting::query()
                ->where('key', LandingPageSettings::KEY)
                ->value('value');

            $settings = LandingPageSettings::normalize(is_array($stored) ? $stored : null);
            $override = trim((string) data_get($settings, "translations.{$locale}.{$key}", ''));

            return $override !== '' ? $override : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function resolveTenant(): ?Tenant
    {
        $tenant = TenantContext::get();

        if ($tenant) {
            return $tenant;
        }

        $user = request()?->user();

        if ($user && $user->tenant_id) {
            return Tenant::query()->with('siteSetting')->find((int) $user->tenant_id);
        }

        return null;
    }

    private static function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim(str_replace('_', '-', $locale)));
        $locale = explode('-', $locale)[0] ?? $locale;

        return $locale !== '' ? $locale : 'en';
    }
}
