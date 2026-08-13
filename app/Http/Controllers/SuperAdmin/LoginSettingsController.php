<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\CaptchaSettings;
use App\Core\SocialLoginSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('SuperAdmin/Settings/Login', [
            'socialLoginSettings' => SocialLoginSettings::forUi(),
            'socialLoginRedirectUris' => [
                'google' => route('social-login.callback', ['provider' => 'google']),
                'apple' => route('social-login.callback', ['provider' => 'apple']),
            ],
            'captchaSettings' => CaptchaSettings::forUi(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'social_login.google.enabled' => ['nullable', 'boolean'],
            'social_login.google.client_id' => ['nullable', 'string', 'max:1000'],
            'social_login.google.client_secret' => ['nullable', 'string', 'max:1000'],

            'social_login.apple.enabled' => ['nullable', 'boolean'],
            'social_login.apple.client_id' => ['nullable', 'string', 'max:1000'],
            'social_login.apple.client_secret' => ['nullable', 'string', 'max:1000'],

            'captcha.enabled' => ['nullable', 'boolean'],
            'captcha.site_key' => ['nullable', 'string', 'max:1000'],
            'captcha.secret_key' => ['nullable', 'string', 'max:1000'],
            'captcha.forms.login' => ['nullable', 'boolean'],
            'captcha.forms.register' => ['nullable', 'boolean'],
        ]);

        $currentSocialLogin = SocialLoginSettings::load();
        $normalizedSocialLogin = SocialLoginSettings::normalize($validated['social_login'] ?? []);
        $normalizedSocialLogin = SocialLoginSettings::mergeSecrets($currentSocialLogin, $normalizedSocialLogin);

        foreach (['google', 'apple'] as $provider) {
            if (! (bool) data_get($normalizedSocialLogin, "{$provider}.enabled")) {
                continue;
            }

            if (trim((string) data_get($normalizedSocialLogin, "{$provider}.client_id")) === '') {
                return back()->withErrors([
                    "social_login.{$provider}.client_id" => 'Client ID is required when this provider is enabled.',
                ])->withInput();
            }

            if (trim((string) data_get($normalizedSocialLogin, "{$provider}.client_secret")) === '') {
                return back()->withErrors([
                    "social_login.{$provider}.client_secret" => 'Client Secret is required when this provider is enabled.',
                ])->withInput();
            }
        }

        SiteSetting::query()->updateOrCreate(
            ['key' => SocialLoginSettings::KEY],
            ['value' => $normalizedSocialLogin]
        );

        $currentCaptcha = CaptchaSettings::load();
        $normalizedCaptcha = CaptchaSettings::normalize($validated['captcha'] ?? []);
        $normalizedCaptcha = CaptchaSettings::mergeSecrets($currentCaptcha, $normalizedCaptcha);

        SiteSetting::query()->updateOrCreate(
            ['key' => CaptchaSettings::KEY],
            ['value' => $normalizedCaptcha]
        );

        return back()->with('success', 'Login settings updated successfully.');
    }
}
