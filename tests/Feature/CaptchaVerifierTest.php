<?php

namespace Tests\Feature;

use App\Core\CaptchaSettings;
use App\Models\SiteSetting;
use App\Support\CaptchaVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CaptchaVerifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/_test/captcha', function (Request $request) {
            CaptchaVerifier::validate($request, 'login');

            return 'ok';
        });
    }

    public function test_disabled_captcha_does_not_verify_with_turnstile(): void
    {
        Http::fake();

        $response = $this->post('/_test/captcha');

        $response->assertOk();
        Http::assertNothingSent();
    }

    public function test_enabled_captcha_requires_token(): void
    {
        $this->enableCaptcha();

        $response = $this->from('/login')->post('/_test/captcha');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('captcha');
    }

    public function test_enabled_captcha_accepts_successful_turnstile_response(): void
    {
        $this->enableCaptcha();

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
        ]);

        $response = $this->post('/_test/captcha', [
            'cf-turnstile-response' => 'valid-token',
        ]);

        $response->assertOk();
        Http::assertSent(fn ($request): bool => $request['secret'] === 'secret-key'
            && $request['response'] === 'valid-token');
    }

    private function enableCaptcha(): void
    {
        SiteSetting::query()->create([
            'key' => CaptchaSettings::KEY,
            'value' => [
                'enabled' => true,
                'provider' => 'turnstile',
                'site_key' => 'site-key',
                'secret_key' => 'secret-key',
                'forms' => [
                    'login' => true,
                    'register' => false,
                ],
            ],
        ]);
    }
}
