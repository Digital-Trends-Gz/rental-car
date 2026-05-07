<?php

namespace App\Http\Middleware;

use App\Core\SecurityAccessSettings;
use App\Support\RequestClientContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnforceSecurityAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        $settings = SecurityAccessSettings::load();
        $ip = RequestClientContext::resolveIp($request);
        $publicIp = RequestClientContext::publicIp($request);
        $country = RequestClientContext::detectCountry($request);

        if ($publicIp !== '' && $this->matchesIpList($publicIp, $settings['website_blocked_ips'])) {
            return $this->deny($request, 'website_blocked_ip', $ip, $country);
        }

        if (!$this->isSuperAdminRequest($request)) {
            return $next($request);
        }

        if ($publicIp !== '' && $this->matchesIpList($publicIp, $settings['superadmin_blocked_ips'])) {
            return $this->deny($request, 'superadmin_blocked_ip', $ip, $country);
        }

        if (!empty($settings['superadmin_allowed_ips']) && ($publicIp === '' || !$this->matchesIpList($publicIp, $settings['superadmin_allowed_ips']))) {
            return $this->deny($request, 'superadmin_ip_not_allowed', $ip, $country);
        }

        if (!empty($settings['superadmin_allowed_countries']) && (!$country || !in_array($country, $settings['superadmin_allowed_countries'], true))) {
            return $this->deny($request, 'superadmin_country_not_allowed', $ip, $country);
        }

        return $next($request);
    }

    private function isSuperAdminRequest(Request $request): bool
    {
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));
        $availableLocales = config('app.available_locales', [config('app.locale', 'en')]);

        if (!empty($segments) && in_array($segments[0], $availableLocales, true)) {
            array_shift($segments);
        }

        return ($segments[0] ?? null) === 'superadmin';
    }

    /**
     * @param  array<int, string>  $rules
     */
    private function matchesIpList(string $ip, array $rules): bool
    {
        if ($ip === '') {
            return false;
        }

        foreach ($rules as $rule) {
            if ($this->ipMatchesRule($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesRule(string $ip, string $rule): bool
    {
        $rule = trim($rule);

        if ($rule === '') {
            return false;
        }

        if (!str_contains($rule, '/')) {
            return strcasecmp($ip, $rule) === 0;
        }

        [$subnet, $bits] = explode('/', $rule, 2);
        $subnet = trim($subnet);
        $bits = (int) $bits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($bits < 0 || $bits > 32) {
                return false;
            }

            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                return false;
            }

            $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

            return (($ipLong & $mask) === ($subnetLong & $mask));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($bits < 0 || $bits > 128) {
                return false;
            }

            $ipBinary = inet_pton($ip);
            $subnetBinary = inet_pton($subnet);

            if ($ipBinary === false || $subnetBinary === false) {
                return false;
            }

            $bytes = intdiv($bits, 8);
            $remainder = $bits % 8;

            if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
                return false;
            }

            if ($remainder === 0) {
                return true;
            }

            $mask = chr((0xFF << (8 - $remainder)) & 0xFF);

            return ((ord($ipBinary[$bytes]) & ord($mask)) === (ord($subnetBinary[$bytes]) & ord($mask)));
        }

        return false;
    }

    private function deny(Request $request, string $reason, string $ip, ?string $country): Response
    {
        Log::warning('Security access denied.', [
            'reason' => $reason,
            'ip' => $ip,
            'request_ip' => (string) ($request->ip() ?? ''),
            'cf_connecting_ip' => $request->headers->get('CF-Connecting-IP'),
            'true_client_ip' => $request->headers->get('True-Client-IP'),
            'x_real_ip' => $request->headers->get('X-Real-IP'),
            'x_forwarded_for' => $request->headers->get('X-Forwarded-For'),
            'candidate_ips' => RequestClientContext::candidateIps($request),
            'public_ip' => RequestClientContext::publicIp($request),
            'country' => $country,
            'host' => $request->getHost(),
            'path' => $request->path(),
        ]);

        $message = 'Access to this area is restricted for your current IP or country.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
