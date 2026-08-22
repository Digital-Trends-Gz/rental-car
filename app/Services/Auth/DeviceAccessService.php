<?php

namespace App\Services\Auth;

use App\Core\SecurityAccessSettings;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\TenantTranslations;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceAccessService
{
    public const WEB_DEVICE_COOKIE = 'rrc_device_id';

    public function isEnabledFor(User $user): bool
    {
        $settings = SecurityAccessSettings::load();

        if (!($settings['device_limit_enabled'] ?? false)) {
            return false;
        }

        if ($user->role === UserRole::SUPER_ADMIN) {
            return false;
        }

        $roles = (array) ($settings['device_limit_roles'] ?? []);
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        return in_array('all', $roles, true) || in_array($role, $roles, true);
    }

    public function maxDevices(): int
    {
        $settings = SecurityAccessSettings::load();

        return max(1, min(25, (int) ($settings['max_devices_per_user'] ?? 2)));
    }

    public function resolveWebDeviceId(Request $request): string
    {
        $value = trim((string) $request->cookie(self::WEB_DEVICE_COOKIE, ''));

        return $value !== '' ? $value : (string) Str::uuid();
    }

    public function resolveApiDeviceId(Request $request): string
    {
        $provided = trim((string) (
            $request->input('device_id')
            ?: $request->header('X-Device-ID')
            ?: ''
        ));

        if ($provided !== '') {
            return $provided;
        }

        return implode('|', [
            'api-fallback',
            trim((string) $request->input('device_name', 'mobile')),
            trim((string) $request->input('platform', '')),
            trim((string) $request->userAgent()),
        ]);
    }

    public function findOrCreateAllowedDevice(
        User $user,
        Request $request,
        string $source,
        string $deviceId,
        ?string $deviceName = null,
        ?string $platform = null,
        ?string $sessionId = null
    ): ?UserDevice {
        $hash = $this->hashDeviceId($deviceId);

        $existing = $user->devices()
            ->where('device_id_hash', $hash)
            ->first();

        if ($existing) {
            if ($existing->revoked_at !== null && $this->activeDeviceCount($user) >= $this->maxDevices()) {
                return null;
            }

            $existing->forceFill([
                'source' => $source,
                'device_name' => $deviceName ?: $existing->device_name,
                'platform' => $platform ?: $existing->platform,
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'session_id' => $sessionId ?: $existing->session_id,
                'last_used_at' => now(),
                'revoked_at' => null,
            ])->save();

            return $existing;
        }

        if ($this->isEnabledFor($user) && $this->activeDeviceCount($user) >= $this->maxDevices()) {
            return null;
        }

        return $user->devices()->create([
            'device_id_hash' => $hash,
            'source' => $source,
            'device_name' => $deviceName,
            'platform' => $platform,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'session_id' => $sessionId,
            'last_used_at' => now(),
        ]);
    }

    public function wouldAllowDevice(User $user, string $deviceId): bool
    {
        $hash = $this->hashDeviceId($deviceId);

        $existing = $user->devices()
            ->where('device_id_hash', $hash)
            ->first();

        if ($existing && $existing->revoked_at === null) {
            return true;
        }

        if (!$this->isEnabledFor($user)) {
            return true;
        }

        return $this->activeDeviceCount($user) < $this->maxDevices();
    }

    public function activeDeviceCount(User $user): int
    {
        return $user->devices()->whereNull('revoked_at')->count();
    }

    public function revokeWebDevice(User $user, Request $request): void
    {
        $deviceId = trim((string) $request->cookie(self::WEB_DEVICE_COOKIE, ''));
        if ($deviceId === '') {
            return;
        }

        $device = $user->devices()
            ->where('device_id_hash', $this->hashDeviceId($deviceId))
            ->whereNull('revoked_at')
            ->first();

        $device?->revoke();
    }

    public function limitReachedMessage(): string
    {
        return TenantTranslations::get(
            'security_access.device_limit.reached',
            app()->getLocale(),
            'Device limit reached. Please contact the administrator to remove an old device.'
        );
    }

    private function hashDeviceId(string $deviceId): string
    {
        return hash('sha256', $deviceId);
    }
}
