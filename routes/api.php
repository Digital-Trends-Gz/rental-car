<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:api-password-reset-request');

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:api-password-reset');
});
