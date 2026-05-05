<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\HomePagesController;
use App\Http\Controllers\LocalizationController;
use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

$baseDomain = parse_url(config('app.url'), PHP_URL_HOST);
$localizedGroup = [
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
];

// --- Subdomain Routes (Tenant Websites) ---
Route::domain('{subdomain}.' . $baseDomain)->group(function () use ($localizedGroup) {
    Route::get('/locale/{locale}', [LocalizationController::class, 'switch'])->name('tenant.locale.switch');
    Route::get('/sitemap.xml', [HomePagesController::class, 'sitemap'])->name('tenant.seo.sitemap');
    Route::get('/robots.txt', [HomePagesController::class, 'robots'])->name('tenant.seo.robots');

    Route::group($localizedGroup, function () {
        Route::middleware('auth')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read']);
            Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
        });

        Route::middleware(['tenant.subscription', 'tenant.seo.redirects'])->group(function () {
            Route::get('/', [HomePagesController::class, 'index'])->name('tenant.home');
            Route::get('/fleet', [HomePagesController::class, 'fleet'])->name('tenant.fleet');
            Route::get('/about', [HomePagesController::class, 'about'])->name('tenant.about');
            Route::get('/contact', [HomePagesController::class, 'contact'])->name('tenant.contact');
            Route::post('/contact/guestContact', [HomePagesController::class, 'guestContact'])->name('tenant.contact.guestContact');

            Route::get('/fleet/{car}', [BookingController::class, 'show'])->name('tenant.fleet.show');
            Route::post('/fleet/{car}/coupon/preview', [BookingController::class, 'previewCoupon'])->name('tenant.fleet.coupon.preview');
            Route::post('/fleet/{car}', [BookingController::class, 'book'])->name('tenant.fleet.book');
            Route::get('/booking/{reservation}/checkout', [BookingController::class, 'checkout'])->name('tenant.booking.checkout');
            Route::get('/booking/{reservation}/payment/success', [BookingController::class, 'paymentSuccess'])->name('tenant.booking.payment.success');
            Route::get('/booking/{reservation}/payment/cancel', [BookingController::class, 'paymentCancel'])->name('tenant.booking.payment.cancel');
            Route::get('/booking/{reservation}', [BookingController::class, 'confirmation'])->name('tenant.booking.confirmation');
        });

        Route::get('/login/social-callback', [\App\Http\Controllers\Auth\SocialLoginController::class, 'tenantCallback'])
            ->middleware('signed')
            ->name('tenant.social-login.callback');

        // Tenant-specific auth (prefixed to avoid collision with main domain auth)
        Route::as('tenant.')->group(function () {
            require __DIR__.'/auth.php';
        });

        require __DIR__.'/admin.php';
        require __DIR__.'/client.php';
    });
});

// --- Main Domain Routes (Central Landing & Super Admin) ---
Route::domain($baseDomain)->group(function () use ($localizedGroup) {
    Route::get('/sitemap.xml', [HomePagesController::class, 'sitemap'])->name('seo.sitemap');
    Route::get('/robots.txt', [HomePagesController::class, 'robots'])->name('seo.robots');

    Route::post('/payment/webhooks/subscriptions/{provider}', [RegisteredUserController::class, 'subscriptionProviderWebhook'])
        ->name('subscription.provider.webhook');

    Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Auth\SocialLoginController::class, 'redirect'])
        ->name('social-login.redirect');
    Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Auth\SocialLoginController::class, 'callback'])
        ->name('social-login.callback');

    Route::get('/locale/{locale}', [LocalizationController::class, 'switch'])->name('locale.switch');

    Route::group($localizedGroup, function () {
        Route::middleware('auth')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read']);
            Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
        });

        Route::middleware('tenant.seo.redirects')->group(function () {
            Route::get('/', [HomePagesController::class, 'index'])->name('home');
            Route::get('/fleet', [HomePagesController::class, 'fleet'])->name('fleet');
            Route::get('/about', [HomePagesController::class, 'about'])->name('about');
            Route::get('/contact', [HomePagesController::class, 'contact'])->name('contact');
        });
        Route::post('/contact/guestContact', [HomePagesController::class, 'landingContact'])->name('contact.guestContact');

        Route::get('/dashboard', function () {
            $user = Auth::user();

            if ($user && $user->role === UserRole::SUPER_ADMIN) {
                return redirect()->route('superadmin.dashboard');
            }

            if ($user && in_array($user->role, [UserRole::ADMIN, UserRole::CLIENT], true)) {
                $tenantSlug = Tenant::query()->whereKey($user->tenant_id)->value('slug');

                if (!$tenantSlug) {
                    Auth::logout();

                    return redirect()
                        ->route('home')
                        ->with('error', 'No tenant is assigned to this account.');
                }

                if ($user->role === UserRole::ADMIN) {
                    return redirect()->route('admin.home', ['subdomain' => $tenantSlug]);
                }

                return redirect()->route('client.home', ['subdomain' => $tenantSlug]);
            }

            return Inertia::render('Dashboard');
        })->middleware('auth')->name('dashboard');

        require __DIR__.'/auth.php';
        require __DIR__.'/superadmin.php';
        require __DIR__.'/settings.php';
    });
});
