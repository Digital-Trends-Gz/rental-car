<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\DeviceAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request): RedirectResponse
    {
        $user = auth()->user();
        $deviceAccess = app(DeviceAccessService::class);
        $deviceId = (string) (
            $request->session()->pull('auth.pending_device_id')
            ?: $deviceAccess->resolveWebDeviceId($request)
        );

        if ($user instanceof User) {
            $device = $deviceAccess->findOrCreateAllowedDevice(
                $user,
                $request,
                'web',
                $deviceId,
                $this->webDeviceName($request),
                null,
                $request->session()->getId()
            );

            if (!$device) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->to($this->loginUrl($request))
                    ->withErrors(['email' => $deviceAccess->limitReachedMessage()]);
            }

            Cookie::queue(DeviceAccessService::WEB_DEVICE_COOKIE, $deviceId, 60 * 24 * 365);
        }

        // Redirect based on user role
        return match ($user->role) {
            UserRole::SUPER_ADMIN => redirect()->intended('/superadmin'),
            UserRole::ADMIN => redirect()->intended('/admin/cars'),
            UserRole::CLIENT => redirect()->intended('/client/reservations'),
            default => redirect()->intended('/'),
        };
    }

    private function webDeviceName($request): string
    {
        $agent = strtolower((string) $request->userAgent());

        $browser = str_contains($agent, 'edg/') ? 'Edge'
            : (str_contains($agent, 'chrome/') ? 'Chrome'
            : (str_contains($agent, 'firefox/') ? 'Firefox'
            : (str_contains($agent, 'safari/') ? 'Safari' : 'Browser')));

        $platform = str_contains($agent, 'windows') ? 'Windows'
            : (str_contains($agent, 'mac os') ? 'macOS'
            : (str_contains($agent, 'iphone') ? 'iPhone'
            : (str_contains($agent, 'android') ? 'Android' : 'Device')));

        return "{$browser} on {$platform}";
    }

    private function loginUrl($request): string
    {
        $subdomain = $request->route('subdomain');

        if ($subdomain) {
            return route('tenant.login', ['subdomain' => $subdomain]);
        }

        return route('login');
    }
}
