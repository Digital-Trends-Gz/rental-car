<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ProfileController;
use App\Http\Controllers\Client\ReservationsController;
use App\Http\Controllers\Client\SupportController;

Route::middleware(['auth', 'tenant_verified', 'active', 'client', 'tenant.subscription', 'tenant.feature:client_portal'])
    ->prefix('client')
    ->as('client.')
    ->group(function () {
        // Redirect '/client' to '/client/reservations' with a named route we can reference
        Route::redirect('/', '/client/reservations')->name('home');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/reservations', [ReservationsController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/{id}', [ReservationsController::class, 'show'])->name('reservations.show');
        Route::get('/reservations/{id}/print', [ReservationsController::class, 'print'])->name('reservations.print');
        Route::get('/reservations/{id}/contract/download', [ReservationsController::class, 'downloadContract'])
            ->middleware('tenant.feature:pdf_export')
            ->name('reservations.contract.download');
        Route::middleware('tenant.feature:extension_request')->group(function () {
            Route::post('/reservations/{reservation}/extension-requests/{extensionRequest}/approve', [ReservationsController::class, 'approveExtensionRequest'])->name('reservations.extension-requests.approve');
            Route::post('/reservations/{reservation}/extension-requests/{extensionRequest}/reject', [ReservationsController::class, 'rejectExtensionRequest'])->name('reservations.extension-requests.reject');
        });

        // Support
        Route::get('/support', [SupportController::class, 'index'])->name('support.index');
        Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');
        Route::get('/support/{id}', [SupportController::class, 'show'])->name('support.show');
        Route::post('/support/{id}/reply', [SupportController::class, 'reply'])->name('support.reply');

    });
