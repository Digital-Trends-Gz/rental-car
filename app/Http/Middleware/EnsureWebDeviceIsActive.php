<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\UserDevice;
use App\Services\Auth\DeviceAccessService;
use App\Support\TenantTranslations;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebDeviceIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role === UserRole::SUPER_ADMIN) {
            return $next($request);
        }

        $deviceId = trim((string) $request->cookie(DeviceAccessService::WEB_DEVICE_COOKIE, ''));
        if ($deviceId === '') {
            return $next($request);
        }

        $device = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_id_hash', hash('sha256', $deviceId))
            ->first();

        if ($device && $device->revoked_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->to($this->loginUrl($request))
                ->with('error', TenantTranslations::get(
                    'security_access.device_limit.revoked',
                    app()->getLocale(),
                    'This device has been revoked. Please log in again.'
                ));
        }

        if ($device) {
            $device->forceFill([
                'last_used_at' => now(),
                'ip_address' => $request->ip(),
                'session_id' => $request->session()->getId(),
            ])->save();
        }

        return $next($request);
    }

    private function loginUrl(Request $request): string
    {
        $subdomain = $request->route('subdomain');

        if ($subdomain) {
            return route('tenant.login', ['subdomain' => $subdomain]);
        }

        return route('login');
    }
}
