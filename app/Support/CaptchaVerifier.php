<?php

namespace App\Support;

use App\Core\CaptchaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class CaptchaVerifier
{
    public static function validate(Request $request, string $form): void
    {
        if (!CaptchaSettings::enabledFor($form)) {
            return;
        }

        $token = trim((string) $request->input('cf-turnstile-response', ''));

        if ($token === '') {
            self::fail();
        }

        $settings = CaptchaSettings::load();
        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $settings['secret_key'],
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (Throwable) {
            self::fail();
        }

        if (!isset($response) || !$response->ok() || $response->json('success') !== true) {
            self::fail();
        }
    }

    private static function fail(): void
    {
        throw ValidationException::withMessages([
            'captcha' => trans('validation.captcha', [], app()->getLocale()) === 'validation.captcha'
                ? 'Captcha verification failed. Please try again.'
                : trans('validation.captcha'),
        ]);
    }
}
