<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CarsController;
use App\Http\Controllers\Admin\ReservationsController;
use App\Http\Controllers\Admin\ClientsController;
use App\Http\Controllers\Admin\PaymentsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\PlatformSupportController;
use App\Http\Controllers\Admin\BranchesController;
use App\Http\Controllers\Admin\EmployeesController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\MaintenanceTypesController;
use App\Http\Controllers\Admin\MaintenanceRecordsController;
use App\Http\Controllers\Admin\CarViolationsController;
use App\Http\Controllers\Admin\ViolationTypesController;
use App\Http\Controllers\Admin\StripeConnectController;
use App\Http\Controllers\Admin\PaymentProvidersController;
use App\Http\Controllers\Admin\WebsiteSettingsController;
use App\Http\Controllers\Admin\TranslationSettingsController;
use App\Http\Controllers\Admin\ContractsController;
use App\Http\Controllers\Admin\CouponsController;
use App\Http\Controllers\Admin\CarDiscountsController;
use App\Http\Controllers\Admin\CarDamageReportsController;
use App\Http\Controllers\Admin\CarDocumentsController;
use App\Http\Controllers\Admin\DashboardController;

Route::middleware(['auth', 'tenant_verified', 'active', 'admin', 'tenant.subscription'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::redirect('/', '/admin/dashboard')->name('home');

        // Cars
        Route::resource('cars', CarsController::class)
            ->middleware('permission:tenant-manage-cars');
        Route::get('cars/{car}/calendar', [CarsController::class, 'calendar'])
            ->middleware('permission:tenant-manage-cars')
            ->name('cars.calendar');
        Route::get('cars/{car}/documents', [CarDocumentsController::class, 'index'])
            ->middleware('permission:tenant-manage-cars')
            ->name('cars.documents.index');
        Route::get('cars/{car}/documents/create', [CarDocumentsController::class, 'create'])
            ->middleware('permission:tenant-manage-cars')
            ->name('cars.documents.create');
        Route::post('cars/{car}/documents', [CarDocumentsController::class, 'store'])
            ->middleware('permission:tenant-manage-cars')
            ->name('cars.documents.store');
        Route::get('cars/{car}/documents/{document}/edit', [CarDocumentsController::class, 'edit'])
            ->middleware('permission:tenant-manage-cars')
            ->name('cars.documents.edit');
        Route::put('cars/{car}/documents/{document}', [CarDocumentsController::class, 'update'])
            ->middleware('permission:tenant-manage-cars')
            ->name('cars.documents.update');
        Route::delete('cars/{car}/documents/{document}', [CarDocumentsController::class, 'destroy'])
            ->middleware('permission:tenant-manage-cars')
            ->name('cars.documents.destroy');

        // Maintenance Types
        Route::resource('maintenance-types', MaintenanceTypesController::class)
            ->except(['show'])
            ->middleware('permission:tenant-manage-cars');

        // Maintenance Records
        Route::resource('maintenance-records', MaintenanceRecordsController::class)
            ->except(['show'])
            ->parameters(['maintenance-records' => 'maintenance'])
            ->middleware('permission:tenant-manage-cars');

        // Car Violations
        Route::resource('violation-types', ViolationTypesController::class)
            ->except(['show'])
            ->middleware('permission:tenant-manage-cars');
        Route::resource('car-violations', CarViolationsController::class)
            ->except(['show'])
            ->parameters(['car-violations' => 'carViolation'])
            ->middleware('permission:tenant-manage-cars');
        Route::resource('car-damage-reports', CarDamageReportsController::class)
            ->except(['show'])
            ->parameters(['car-damage-reports' => 'carDamageReport'])
            ->middleware('permission:tenant-manage-cars');

        // Reservations
        Route::resource('reservations', ReservationsController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
            ->middleware('permission:tenant-manage-reservations');
        Route::get('reservations/{reservation}/print', [ReservationsController::class, 'print'])
            ->middleware('permission:tenant-manage-reservations')
            ->name('reservations.print');

        // Contracts
        Route::post('contracts/extract', [ContractsController::class, 'extract'])
            ->middleware('permission:tenant-manage-reservations')
            ->name('contracts.extract');
        Route::post('contracts/drivers/extract', [ContractsController::class, 'extractDriverDocument'])
            ->middleware('permission:tenant-manage-reservations')
            ->name('contracts.drivers.extract');
        Route::post('contracts/drivers/photo/extract', [ContractsController::class, 'extractDriverCustomerPhoto'])
            ->middleware('permission:tenant-manage-reservations')
            ->name('contracts.drivers.photo.extract');
        Route::get('contracts/{contract}/pdf', [ContractsController::class, 'pdf'])
            ->middleware('permission:tenant-manage-reservations')
            ->name('contracts.pdf');
        Route::resource('contracts', ContractsController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
            ->middleware('permission:tenant-manage-reservations');

        // Clients
        Route::resource('clients', ClientsController::class)
            ->only(['index', 'show'])
            ->middleware('permission:tenant-manage-clients');
        Route::get('clients/{client}/documents', [ClientsController::class, 'documents'])
            ->middleware('permission:tenant-manage-clients')
            ->name('clients.documents');
        Route::post('clients/{client}/documents/extract', [ClientsController::class, 'extractDocument'])
            ->middleware('permission:tenant-manage-clients')
            ->name('clients.documents.extract');
        Route::post('clients/{client}/documents/save', [ClientsController::class, 'saveDocument'])
            ->middleware('permission:tenant-manage-clients')
            ->name('clients.documents.save');
        Route::patch('clients/{client}/suspend', [ClientsController::class, 'suspend'])
            ->middleware('permission:tenant-manage-clients')
            ->name('clients.suspend');
        Route::patch('clients/{client}/activate', [ClientsController::class, 'activate'])
            ->middleware('permission:tenant-manage-clients')
            ->name('clients.activate');

        // Payments
        Route::resource('payments', PaymentsController::class)
            ->only(['index'])
            ->middleware('permission:tenant-manage-payments');

        // Coupons
        Route::resource('coupons', CouponsController::class)
            ->except(['show'])
            ->middleware('permission:tenant-manage-payments');
        Route::resource('car-discounts', CarDiscountsController::class)
            ->except(['show'])
            ->parameters(['car-discounts' => 'carDiscount'])
            ->middleware('permission:tenant-manage-payments');

        // Reports
        Route::resource('reports', ReportsController::class)
            ->except(['show'])
            ->middleware('permission:tenant-view-reports');

        // Support
        Route::resource('support', SupportController::class)
            ->only(['index'])
            ->middleware('permission:tenant-manage-support');
        Route::get('/support/tickets/{ticket}', [SupportController::class, 'show'])
        ->middleware('permission:tenant-manage-support')
        ->name('support.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportController::class, 'reply'])
        ->middleware('permission:tenant-manage-support')
        ->name('support.reply');
        Route::post('/support/tickets/{ticket}/close', [SupportController::class, 'close'])
        ->middleware('permission:tenant-manage-support')
        ->name('support.close');

        // Tenant -> Super Admin Support
        Route::get('/support/platform', [PlatformSupportController::class, 'index'])
            ->middleware('permission:tenant-manage-support')
            ->name('support.platform.index');
        Route::post('/support/platform', [PlatformSupportController::class, 'store'])
            ->middleware('permission:tenant-manage-support')
            ->name('support.platform.store');
        Route::get('/support/platform/{ticket}', [PlatformSupportController::class, 'show'])
            ->middleware('permission:tenant-manage-support')
            ->name('support.platform.show');
        Route::post('/support/platform/{ticket}/reply', [PlatformSupportController::class, 'reply'])
            ->middleware('permission:tenant-manage-support')
            ->name('support.platform.reply');
        Route::post('/support/platform/{ticket}/close', [PlatformSupportController::class, 'close'])
            ->middleware('permission:tenant-manage-support')
            ->name('support.platform.close');

        // Branches
        Route::get('branches/location-options/cities', [BranchesController::class, 'cities'])
            ->middleware('permission:tenant-manage-branches')
            ->name('branches.cities');
        Route::resource('branches', BranchesController::class)
            ->except(['show'])
            ->middleware('permission:tenant-manage-branches');

        // Employees
        Route::resource('employees', EmployeesController::class)
            ->except(['show'])
            ->middleware('permission:tenant-manage-employees');

        // Roles
        Route::resource('roles', RolesController::class)
            ->except(['show'])
            ->middleware('permission:tenant-manage-employees');

        // Tenant payment gateway (Stripe Connect)
        Route::get('settings/payment-providers', [PaymentProvidersController::class, 'edit'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.payment-providers.edit');
        Route::put('settings/payment-providers', [PaymentProvidersController::class, 'update'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.payment-providers.update');

        Route::get('settings/website', [WebsiteSettingsController::class, 'edit'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.website.edit');
        Route::put('settings/website', [WebsiteSettingsController::class, 'update'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.website.update');
        Route::get('settings/translations', [TranslationSettingsController::class, 'edit'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.translations.edit');
        Route::put('settings/translations', [TranslationSettingsController::class, 'update'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.translations.update');

        Route::get('settings/stripe-connect', [StripeConnectController::class, 'edit'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.stripe-connect.edit');
        Route::post('settings/stripe-connect/connect', [StripeConnectController::class, 'connect'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.stripe-connect.connect');
        Route::get('settings/stripe-connect/refresh', [StripeConnectController::class, 'refresh'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.stripe-connect.refresh');
        Route::get('settings/stripe-connect/return', [StripeConnectController::class, 'returned'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.stripe-connect.return');
        Route::post('settings/stripe-connect/login-link', [StripeConnectController::class, 'loginLink'])
            ->middleware('permission:tenant-manage-settings')
            ->name('settings.stripe-connect.login-link');

    });




