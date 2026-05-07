<?php

namespace App\Support;

use Illuminate\Http\Request;

class RequestClientContext
{
    public static function detectCountry(Request $request): ?string
    {
        $candidates = [
            $request->headers->get('CF-IPCountry'),
            $request->headers->get('CloudFront-Viewer-Country'),
            $request->headers->get('X-Country-Code'),
            $request->headers->get('X-Country'),
            $request->server('GEOIP_COUNTRY_CODE'),
        ];

        foreach ($candidates as $value) {
            $country = strtoupper(trim((string) $value));

            if (preg_match('/^[A-Z]{2}$/', $country)) {
                return $country;
            }
        }

        return null;
    }

    public static function resolveIp(Request $request): string
    {
        $publicIp = self::publicIp($request);

        if ($publicIp !== '') {
            return $publicIp;
        }

        foreach (self::candidateIps($request) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return '';
    }

    public static function publicIp(Request $request): string
    {
        foreach (self::candidateIps($request) as $candidate) {
            if (self::isPublicIp($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    public static function candidateIps(Request $request): array
    {
        $candidates = [
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('True-Client-IP'),
            $request->headers->get('X-Real-IP'),
        ];

        $forwardedFor = $request->headers->get('X-Forwarded-For');
        if (is_string($forwardedFor) && trim($forwardedFor) !== '') {
            foreach (explode(',', $forwardedFor) as $forwardedIp) {
                $candidates[] = $forwardedIp;
            }
        }

        $candidates[] = $request->server('REMOTE_ADDR');
        $candidates[] = $request->ip();

        $normalized = array_map(static fn ($candidate) => trim((string) $candidate), $candidates);
        $filtered = array_filter($normalized, static fn ($candidate) => $candidate !== '');

        return array_values(array_unique($filtered));
    }

    public static function isPublicIp(?string $ip): bool
    {
        $value = trim((string) $ip);

        return $value !== '' && filter_var(
            $value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
