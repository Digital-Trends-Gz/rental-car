<?php

namespace App\Http\Controllers\Api;

use App\Core\AppBrandingSettings;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function general(): JsonResponse
    {
        $branding = AppBrandingSettings::load();
        $siteName = $this->nullableString($branding['app_name'] ?? null) ?? config('app.name', 'Real Rent Car');

        return response()->json([
            'source' => 'super_admin',
            'site_name' => $siteName,
            'app_name' => $siteName,
            'logo_url' => $this->nullableString($branding['logo_url'] ?? null),
            'primary_color' => (string) ($branding['primary_color'] ?? '#3b82f6'),
            'secondary_color' => (string) ($branding['secondary_color'] ?? '#6d28d9'),
        ]);
    }

    public function tenant(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (empty($user->tenant_id)) {
            return response()->json([
                'message' => 'Tenant not found for this account.',
            ], 403);
        }

        $tenant = Tenant::query()->with('siteSetting.files')->find((int) $user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found.',
            ], 404);
        }

        $settings = TenantSiteSetting::forTenant($tenant);
        $siteName = $this->nullableString(data_get($settings, 'site_name')) ?? $tenant->name;

        return response()->json([
            'source' => 'tenant',
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
            ],
            'site_name' => $siteName,
            'app_name' => $siteName,
            'logo_url' => $this->nullableString(data_get($settings, 'logo_url')),
            'primary_color' => $this->normalizeHexColor(data_get($settings, 'primary_color'), '#f97316'),
            'secondary_color' => $this->normalizeHexColor(data_get($settings, 'secondary_color'), '#ea580c'),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeHexColor(mixed $value, string $fallback): string
    {
        $value = strtolower(trim((string) ($value ?? '')));

        if ($value !== '' && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value)) {
            return $value;
        }

        return $fallback;
    }
}
