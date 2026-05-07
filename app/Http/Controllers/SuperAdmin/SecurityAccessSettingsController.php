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
        ]);

        SecurityAccessSettings::persist([
            'superadmin_allowed_countries' => data_get($validated, 'settings.superadmin_allowed_countries', []),
            'superadmin_allowed_ips' => SecurityAccessSettings::parseIpInput(data_get($validated, 'settings.superadmin_allowed_ips')),
            'superadmin_blocked_ips' => SecurityAccessSettings::parseIpInput(data_get($validated, 'settings.superadmin_blocked_ips')),
            'website_blocked_ips' => SecurityAccessSettings::parseIpInput(data_get($validated, 'settings.website_blocked_ips')),
        ]);

        return back()->with('success', 'Security access settings updated successfully.');
    }
}
