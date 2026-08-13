<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;

Route::middleware('guest')->group(function () {
    Route::get('auth', fn () => Inertia::render('auth/Landing'))
        ->name('auth.landing');

    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->name('register.store');
});

Route::get('register/plans', [RegisteredUserController::class, 'plans'])
    ->name('register.plans');

Route::post('register/plans', [RegisteredUserController::class, 'storePlan'])
    ->name('register.plans.store');

Route::get('register/checkout', [RegisteredUserController::class, 'checkout'])
    ->name('register.checkout');

Route::post('register/checkout', [RegisteredUserController::class, 'completeCheckout'])
    ->name('register.checkout.store');

Route::get('register/checkout/success', [RegisteredUserController::class, 'checkoutSuccess'])
    ->name('register.checkout.success');

Route::get('register/checkout/cancel', [RegisteredUserController::class, 'checkoutCancel'])
    ->name('register.checkout.cancel');

Route::get('register/checkout/provider/{provider}/return', [RegisteredUserController::class, 'checkoutProviderReturn'])
    ->name('register.checkout.provider.return');

Route::get('register/checkout/provider/{provider}/cancel', [RegisteredUserController::class, 'checkoutProviderCancel'])
    ->name('register.checkout.provider.cancel');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');

    Route::get('tenant/login', [AuthenticatedSessionController::class, 'tenantLogin'])
        ->name('tenant-login');

    Route::post('tenant/login', [AuthenticatedSessionController::class, 'storeAdminLogin'])
        ->name('tenant-login.store');

    Route::get('admin-secret-url', [AuthenticatedSessionController::class, 'adminLogin'])
        ->name('admin-login');

    Route::post('admin-secret-url', [AuthenticatedSessionController::class, 'storeAdminLogin'])
        ->name('admin-login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    Route::get('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
        ->name('two-factor.login');

    Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->name('two-factor.login.store');
});

Route::get('register/upgrade', [RegisteredUserController::class, 'upgrade'])
    ->middleware('auth')
    ->name('register.upgrade');

Route::get('post-payment-login/{user}', [RegisteredUserController::class, 'postPaymentLogin'])
    ->middleware('signed')
    ->name('post-payment-login');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Two Factor Authentication...
    Route::post('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
        ->name('two-factor.enable');

    Route::post('/user/confirmed-two-factor-authentication', [ConfirmedTwoFactorAuthenticationController::class, 'store'])
        ->name('two-factor.confirm');

    Route::delete('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
        ->name('two-factor.disable');

    Route::get('/user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])
        ->name('two-factor.qr-code');

    Route::get('/user/two-factor-secret-key', [TwoFactorSecretKeyController::class, 'show'])
        ->name('two-factor.secret-key');

    Route::get('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])
        ->name('two-factor.recovery-codes');

    Route::post('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])
        ->name('two-factor.regenerate-recovery-codes');

    // Password Confirmation...
    Route::get('/user/confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('/user/confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->name('password.confirm.store');

    // Profile & Passwords...
    Route::put('/user/profile-information', [ProfileInformationController::class, 'update'])
        ->name('user-profile-information.update');

    Route::put('/user/password', [PasswordController::class, 'update'])
        ->name('user-password.update');
});
