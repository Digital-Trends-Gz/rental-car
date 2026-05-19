<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractsController;
use App\Http\Controllers\Api\ReservationsController;
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

Route::middleware('auth:sanctum')->prefix('reservations')->group(function () {
    Route::get('task-types', [ReservationsController::class, 'taskTypes'])->name('api.reservations.task-types');
    Route::get('status', [ReservationsController::class, 'status'])->name('api.reservations.status');
    Route::get('tasks', [ReservationsController::class, 'tasks'])->name('api.reservations.tasks');
    Route::get('today-pickups', [ReservationsController::class, 'todayPickups'])->name('api.reservations.today-pickups');
    Route::get('returns', [ReservationsController::class, 'returns'])->name('api.reservations.returns');
    Route::get('{reservation}/handover', [ContractsController::class, 'handover'])->name('api.reservations.handover');
    Route::post('{reservation}/note', [ReservationsController::class, 'updateNote'])->name('api.reservations.note');
    Route::get('{reservation}', [ReservationsController::class, 'show'])->name('api.reservations.show');
});

Route::middleware('auth:sanctum')->prefix('contracts')->group(function () {
    Route::get('{contract}/documents', [ContractsController::class, 'documents'])->name('api.contracts.documents');
    Route::match(['post', 'patch'], '{contract}/handover', [ContractsController::class, 'updateHandover'])->name('api.contracts.handover');
});

Route::prefix('settings')->group(function () {
    Route::get('general', [SettingsController::class, 'general'])->name('api.settings.general');
    Route::middleware('auth:sanctum')->get('tenant', [SettingsController::class, 'tenant'])->name('api.settings.tenant');
});
