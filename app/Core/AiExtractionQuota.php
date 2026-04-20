<?php

namespace App\Core;

use App\Models\Tenant;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class AiExtractionQuota
{
    public static function defaultDailyLimit(): int
    {
        $settings = AiProviderSettings::load();

        return max(1, (int) ($settings['document_extraction_daily_limit'] ?? 10));
    }

    public static function limitForTenant(?Tenant $tenant = null): int
    {
        if ($tenant) {
            $settings = TenantSiteSetting::forTenant($tenant);
            $tenantLimit = self::normalizeLimit($settings['document_extraction_daily_limit'] ?? null);

            if ($tenantLimit !== null) {
                return $tenantLimit;
            }
        }

        return self::defaultDailyLimit();
    }

    public static function ensureAvailable(?Tenant $tenant = null): void
    {
        $limit = self::limitForTenant($tenant);
        $key = self::rateLimitKey($tenant);
        $decaySeconds = self::secondsUntilEndOfDay();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = max(1, (int) ceil($seconds / 60));

            throw new RuntimeException(
                "Daily AI document extraction limit reached. Try again in {$minutes} minute(s) or increase the limit in settings."
            );
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private static function rateLimitKey(?Tenant $tenant = null): string
    {
        $tenantId = $tenant?->id ?? TenantContext::id() ?? 0;

        return 'ai:document-extraction:tenant:'.$tenantId;
    }

    private static function secondsUntilEndOfDay(): int
    {
        $now = now();
        $seconds = $now->diffInSeconds($now->copy()->endOfDay()) + 1;

        return max(60, $seconds);
    }

    private static function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $limit = (int) $value;

        return $limit >= 1 ? min($limit, 100000) : null;
    }
}
