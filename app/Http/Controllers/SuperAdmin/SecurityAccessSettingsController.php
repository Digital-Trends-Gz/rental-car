<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\SecurityAccessSettings;
use App\Http\Controllers\Controller;
use App\Support\CountryOptions;
use App\Support\RequestClientContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityAccessSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $settings = SecurityAccessSettings::load();

        return Inertia::render('SuperAdmin/Settings/SecurityAccess', [
            'settings' => [
                'superadmin_allowed_countries' => $settings['superadmin_allowed_countries'],
                'superadmin_allowed_ips' => implode("\n", $settings['superadmin_allowed_ips']),
                'superadmin_blocked_ips' => implode("\n", $settings['superadmin_blocked_ips']),
                'website_blocked_ips' => implode("\n", $settings['website_blocked_ips']),
                'device_limit_enabled' => (bool) $settings['device_limit_enabled'],
                'max_devices_per_user' => (int) $settings['max_devices_per_user'],
                'device_limit_roles' => $settings['device_limit_roles'],
            ],
            'countries' => CountryOptions::all(),
            'currentRequest' => [
                'ip' => RequestClientContext::resolveIp($request),
                'country' => RequestClientContext::detectCountry($request),
            ],
            'actions' => [
                'update' => route('superadmin.settings.security-access.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings.superadmin_allowed_countries' => ['nullable', 'array'],
            'settings.superadmin_allowed_countries.*' => ['string', 'size:2'],
            'settings.superadmin_allowed_ips' => ['nullable', 'string', 'max:10000'],
            'settings.superadmin_blocked_ips' => ['nullable', 'string', 'max:10000'],
            'settings.website_blocked_ips' => ['nullable', 'string', 'max:10000'],
            'settings.device_limit_enabled' => ['nullable', 'boolean'],
            'settings.max_devices_per_user' => ['nullable', 'integer', 'min:1', 'max:25'],
            'settings.device_limit_roles' => ['nullable', 'array'],
            'settings.device_limit_roles.*' => ['string', 'in:all,admin,client'],
        ]);

        SecurityAccessSettings::persist([
            'superadmin_allowed_countries' => data_get($validated, 'settings.superadmin_allowed_countries', []),
            'superadmin_allowed_ips' => SecurityAccessSettings::parseIpInput(data_get($validated, 'settings.superadmin_allowed_ips')),
            'superadmin_blocked_ips' => SecurityAccessSettings::parseIpInput(data_get($validated, 'settings.superadmin_blocked_ips')),
            'website_blocked_ips' => SecurityAccessSettings::parseIpInput(data_get($validated, 'settings.website_blocked_ips')),
            'device_limit_enabled' => (bool) data_get($validated, 'settings.device_limit_enabled', false),
            'max_devices_per_user' => (int) data_get($validated, 'settings.max_devices_per_user', 2),
            'device_limit_roles' => data_get($validated, 'settings.device_limit_roles', ['client', 'admin']),
        ]);

        return back()->with('success', 'Security access settings updated successfully.');
    }
}
