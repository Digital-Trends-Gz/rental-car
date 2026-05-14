<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:api-otp-verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:api-password-reset-request');

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:api-password-reset');
});

Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('summary', [DashboardController::class, 'summary'])->name('api.dashboard.summary');
});

Route::prefix('settings')->group(function () {
    Route::get('general', [SettingsController::class, 'general'])->name('api.settings.general');
    Route::middleware('auth:sanctum')->get('tenant', [SettingsController::class, 'tenant'])->name('api.settings.tenant');
});
