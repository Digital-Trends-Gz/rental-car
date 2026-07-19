<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin\TenantsController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\PlaceholderController;
use App\Http\Controllers\SuperAdmin\UsersController;
use App\Http\Controllers\SuperAdmin\RolesController;
use App\Http\Controllers\SuperAdmin\PlansController;
use App\Http\Controllers\SuperAdmin\DiscountsController;
use App\Http\Controllers\SuperAdmin\CarsController;
use App\Http\Controllers\SuperAdmin\ReservationsController;
use App\Http\Controllers\SuperAdmin\LandingSettingsController;
use App\Http\Controllers\SuperAdmin\LandingLeadsController;
use App\Http\Controllers\SuperAdmin\LoginSettingsController;
use App\Http\Controllers\SuperAdmin\AppBrandingSettingsController;
use App\Http\Controllers\SuperAdmin\RevenueSubscriptionController;
use App\Http\Controllers\SuperAdmin\RevenueTransactionsController;
use App\Http\Controllers\SuperAdmin\PaymentProvidersController;
use App\Http\Controllers\SuperAdmin\PlateFormatTemplatesController;
use App\Http\Controllers\SuperAdmin\LocalizationSettingsController;
use App\Http\Controllers\SuperAdmin\EmailTemplateSettingsController;
use App\Http\Controllers\SuperAdmin\SecurityAccessSettingsController;
use App\Http\Controllers\SuperAdmin\SupportController as SuperAdminSupportController;
use App\Http\Controllers\SuperAdmin\MainSiteSeoSettingsController;
use App\Http\Controllers\SuperAdmin\StaticPageContentSettingsController;
use App\Http\Controllers\SuperAdmin\WebPageContentSettingsController;

use App\Http\Controllers\SuperAdmin\Auth\LoginController;
use App\Http\Controllers\SuperAdmin\LogViewerController;

// Dedicated Super Admin Login
Route::middleware('guest')->group(function () {
    Route::get('superadmin/login', [LoginController::class, 'create'])->name('superadmin.login');
    Route::post('superadmin/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active', 'super_admin'])
    ->prefix('superadmin')
    ->as('superadmin.')
    ->group(function () {
        // Logout
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        // Log Viewer
        Route::middleware('permission:manage-settings')->get('logs', [LogViewerController::class, 'index'])->name('logs');

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Tenants Management
        Route::middleware('permission:manage-tenants')->resource('tenants', TenantsController::class);
        Route::middleware('permission:manage-tenants')->group(function () {
            Route::get('support/tenants', [SuperAdminSupportController::class, 'index'])->name('support.tenants.index');
            Route::get('support/tenants/{ticket}', [SuperAdminSupportController::class, 'show'])->name('support.tenants.show');
            Route::post('support/tenants/{ticket}/assign', [SuperAdminSupportController::class, 'assign'])->name('support.tenants.assign');
            Route::post('support/tenants/{ticket}/reply', [SuperAdminSupportController::class, 'reply'])->name('support.tenants.reply');
            Route::post('support/tenants/{ticket}/close', [SuperAdminSupportController::class, 'close'])->name('support.tenants.close');
        });

        Route::get('leads/landing', [LandingLeadsController::class, 'index'])->name('landing-leads.index');
        Route::get('leads/landing/{ticket}', [LandingLeadsController::class, 'show'])->name('landing-leads.show');
        Route::post('leads/landing/{ticket}/reply', [LandingLeadsController::class, 'reply'])->name('landing-leads.reply');
        Route::put('leads/landing/{ticket}/status', [LandingLeadsController::class, 'updateStatus'])->name('landing-leads.status');

        // Revenue
        Route::middleware('permission:manage-revenue')->group(function () {
            Route::get('revenue/subscription', [RevenueSubscriptionController::class, 'index'])->name('revenue.subscription');
            Route::get('revenue/transactions', [RevenueTransactionsController::class, 'index'])->name('revenue.transactions');
            Route::get('revenue/transactions/export/csv', [RevenueTransactionsController::class, 'exportCsv'])->name('revenue.transactions.export.csv');
            Route::get('revenue/transactions/export/pdf', [RevenueTransactionsController::class, 'exportPdf'])->name('revenue.transactions.export.pdf');
        });

        // User Management (Super Admin users: can log in to super admin area)
        Route::middleware('permission:manage-users')->group(function () {
            Route::get('users', [UsersController::class, 'index'])->name('users.index');
            Route::get('users/create', [UsersController::class, 'create'])->name('users.create');
            Route::post('users', [UsersController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UsersController::class, 'update'])->name('users.update');
        });

        // Roles: create role and assign permissions; users get permissions via their role(s)
        Route::middleware('permission:manage-roles')->group(function () {
            Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RolesController::class, 'create'])->name('roles.create');
            Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RolesController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RolesController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');
        });

        // Product Management
        Route::middleware('permission:manage-settings')->group(function () {
            Route::resource('plans', PlansController::class)->except(['show']);
            Route::resource('discounts', DiscountsController::class)->except(['show']);
        });

        // Cars (all cars with tenant name)
        Route::get('cars', [CarsController::class, 'index'])->name('cars.index')->middleware('permission:manage-cars');
        Route::get('reservations', [ReservationsController::class, 'index'])->name('reservations.index')->middleware('permission:manage-reservations');

        // Settings
        Route::middleware('permission:manage-settings')->group(function () {
            Route::get('settings/general', [LandingSettingsController::class, 'edit'])->name('settings.general');
            Route::put('settings/general', [LandingSettingsController::class, 'update'])->name('settings.general.update');
            Route::post('settings/general/test-ai-connection', [LandingSettingsController::class, 'testAiConnection'])->name('settings.general.test-ai-connection');
            Route::post('settings/general/test-mail-connection', [LandingSettingsController::class, 'testMailConnection'])->name('settings.general.test-mail-connection');
            Route::get('settings/branding', [AppBrandingSettingsController::class, 'edit'])->name('settings.branding');
            Route::put('settings/branding', [AppBrandingSettingsController::class, 'update'])->name('settings.branding.update');
            Route::get('settings/design', [LandingSettingsController::class, 'design'])->name('settings.design');
            Route::put('settings/design', [LandingSettingsController::class, 'updateDesign'])->name('settings.design.update');
            Route::post('settings/design', [LandingSettingsController::class, 'updateDesign'])->name('settings.design.update.post');
            Route::get('settings/applications-page', [LandingSettingsController::class, 'applicationsPage'])->name('settings.applications-page');
            Route::put('settings/applications-page', [LandingSettingsController::class, 'updateApplicationsPage'])->name('settings.applications-page.update');
            Route::get('settings/landing-translations', [LandingSettingsController::class, 'translations'])->name('settings.landing-translations');
            Route::put('settings/landing-translations', [LandingSettingsController::class, 'updateTranslations'])->name('settings.landing-translations.update');
            Route::post('settings/landing-translations/auto-translate', [LandingSettingsController::class, 'autoTranslateTranslations'])->name('settings.landing-translations.auto-translate');
            
            Route::get('settings/login', [LoginSettingsController::class, 'edit'])->name('settings.login');
            Route::put('settings/login', [LoginSettingsController::class, 'update'])->name('settings.login.update');

            Route::redirect('settings/stripe', 'settings/payment-providers', 302)->name('settings.stripe');
            Route::put('settings/stripe', function () {
                return redirect()
                    ->route('superadmin.settings.payment-providers')
                    ->with('error', 'Stripe settings page is deprecated. Use Payment Providers.');
            })->name('settings.stripe.update');
            Route::get('settings/payment-providers', [PaymentProvidersController::class, 'index'])->name('settings.payment-providers');
            Route::put('settings/payment-providers/{paymentProvider}', [PaymentProvidersController::class, 'update'])->name('settings.payment-providers.update');
            Route::get('settings/plate-format-templates', [PlateFormatTemplatesController::class, 'edit'])->name('settings.plate-format-templates');
            Route::put('settings/plate-format-templates', [PlateFormatTemplatesController::class, 'update'])->name('settings.plate-format-templates.update');
            Route::get('settings/languages', [LocalizationSettingsController::class, 'edit'])->name('settings.languages');
            Route::put('settings/languages', [LocalizationSettingsController::class, 'update'])->name('settings.languages.update');
            Route::get('settings/emails', [EmailTemplateSettingsController::class, 'edit'])->name('settings.emails');
            Route::put('settings/emails', [EmailTemplateSettingsController::class, 'update'])->name('settings.emails.update');
            Route::get('settings/security-access', [SecurityAccessSettingsController::class, 'edit'])->name('settings.security-access');
            Route::put('settings/security-access', [SecurityAccessSettingsController::class, 'update'])->name('settings.security-access.update');
            Route::get('settings/seo', [MainSiteSeoSettingsController::class, 'edit'])->name('settings.seo');
            Route::put('settings/seo', [MainSiteSeoSettingsController::class, 'update'])->name('settings.seo.update');
            Route::get('settings/web-pages-content', [WebPageContentSettingsController::class, 'edit'])->name('settings.web-pages-content');
            Route::put('settings/web-pages-content', [WebPageContentSettingsController::class, 'update'])->name('settings.web-pages-content.update');
            Route::get('settings/static-pages-content', [StaticPageContentSettingsController::class, 'edit'])->name('settings.static-pages-content');
            Route::put('settings/static-pages-content', [StaticPageContentSettingsController::class, 'update'])->name('settings.static-pages-content.update');
        });
    });
