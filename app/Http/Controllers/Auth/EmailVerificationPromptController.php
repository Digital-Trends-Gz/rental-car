<?php

namespace App\Http\Controllers\Auth;

use App\Core\LandingPageSettings;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    /**
     * Show the email verification prompt page.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->to($this->redirectUrlFor($request->user()))
                    : Inertia::render('auth/VerifyEmail', [
                        'status' => $request->session()->get('status'),
                        'content' => $this->verifyEmailContent(),
                    ]);
    }

    private function redirectUrlFor(object $user): string
    {
        if (($user->role ?? null) === UserRole::SUPER_ADMIN) {
            return route('superadmin.dashboard');
        }

        if (in_array($user->role ?? null, [UserRole::ADMIN, UserRole::CLIENT], true)) {
            $tenantSlug = Tenant::query()
                ->whereKey((int) ($user->tenant_id ?? 0))
                ->value('slug');

            if (is_string($tenantSlug) && $tenantSlug !== '') {
                return $user->role === UserRole::ADMIN
                    ? route('admin.home', ['subdomain' => $tenantSlug])
                    : route('client.home', ['subdomain' => $tenantSlug]);
            }
        }

        return route('dashboard');
    }

    /**
     * @return array<string, string>
     */
    private function verifyEmailContent(): array
    {
        $stored = SiteSetting::query()
            ->where('key', LandingPageSettings::KEY)
            ->first()
            ?->value;

        $settings = LandingPageSettings::localize(
            LandingPageSettings::normalize(is_array($stored) ? $stored : null),
            app()->getLocale()
        );

        return array_map(
            static fn (mixed $value): string => (string) $value,
            (array) data_get($settings, 'auth_verify_email', [])
        );
    }
}
