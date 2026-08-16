<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CarsController;
use App\Http\Controllers\Admin\CarPhotoHistoryController;
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
use App\Http\Controllers\Admin\DiscountRequestsController;
use App\Http\Controllers\Admin\PlateFormatSettingsController;
use App\Http\Controllers\Admin\ReservationSettingsController;
use App\Http\Controllers\Admin\WebsiteSettingsController;
use App\Http\Controllers\Admin\TranslationSettingsController;
use App\Http\Controllers\Admin\ContractsController;
use App\Http\Controllers\Admin\ContractReturnReportsController;
use App\Http\Controllers\Admin\CouponsController;
use App\Http\Controllers\Admin\CarDiscountsController;
use App\Http\Controllers\Admin\CarDamageReportsController;
use App\Http\Controllers\Admin\DamageRepairsController;
use App\Http\Controllers\Admin\AccidentReportsController;
use App\Http\Controllers\Admin\AiInsightsController;
use App\Http\Controllers\Admin\CarDocumentsController;
use App\Http\Controllers\Admin\DashboardController;

Route::middleware(['auth', 'tenant_verified', 'active', 'admin', 'tenant.subscription'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:tenant-dashboard.view')
            ->name('dashboard');
        Route::redirect('/', '/admin/dashboard')->name('home');

        // Cars
        Route::post('cars/catalog-entries', [CarsController::class, 'storeCatalogEntry'])
            ->middleware('permission:tenant-cars.create')
            ->name('cars.catalog-entries.store');
        Route::get('cars/create', [CarsController::class, 'create'])
            ->middleware(['tenant.plan.limit:cars', 'permission:tenant-cars.create'])
            ->name('cars.create');
        Route::resource('cars', CarsController::class)
            ->except(['create'])
            ->middlewareFor(['store'], 'tenant.plan.limit:cars')
            ->middlewareFor(['index', 'show'], 'permission:tenant-cars.view')
            ->middlewareFor(['edit', 'update'], 'permission:tenant-cars.update')
            ->middlewareFor(['store'], 'permission:tenant-cars.create')
            ->middlewareFor(['destroy'], 'permission:tenant-cars.delete');
        Route::get('cars/{car}/calendar', [CarsController::class, 'calendar'])
            ->middleware(['permission:tenant-cars.calendar.view', 'tenant.feature:booking_calendar'])
            ->name('cars.calendar');
        Route::get('cars/{car}/availability-calendar', [CarsController::class, 'availabilityCalendar'])
            ->middleware(['permission:tenant-cars.calendar.view', 'tenant.feature:booking_calendar'])
            ->name('cars.availability-calendar');
            
        Route::resource('cars.photo-histories', CarPhotoHistoryController::class)
            ->middleware('permission:tenant-cars.update')
            ->except(['show']);
        Route::middleware('tenant.feature:car_documents')->group(function () {
            Route::get('cars/{car}/documents', [CarDocumentsController::class, 'index'])
                ->middleware('permission:tenant-cars.documents.manage')
                ->name('cars.documents.index');
            Route::get('cars/{car}/documents/create', [CarDocumentsController::class, 'create'])
                ->middleware('permission:tenant-cars.documents.manage')
                ->name('cars.documents.create');
            Route::post('cars/{car}/documents', [CarDocumentsController::class, 'store'])
                ->middleware('permission:tenant-cars.documents.manage')
                ->name('cars.documents.store');
            Route::get('cars/{car}/documents/{document}/edit', [CarDocumentsController::class, 'edit'])
                ->middleware('permission:tenant-cars.documents.manage')
                ->name('cars.documents.edit');
            Route::put('cars/{car}/documents/{document}', [CarDocumentsController::class, 'update'])
                ->middleware('permission:tenant-cars.documents.manage')
                ->name('cars.documents.update');
            Route::delete('cars/{car}/documents/{document}', [CarDocumentsController::class, 'destroy'])
                ->middleware('permission:tenant-cars.documents.manage')
                ->name('cars.documents.destroy');
        });

        // Maintenance Types
        Route::middleware('tenant.feature:maintenance_module')->group(function () {
            Route::resource('maintenance-types', MaintenanceTypesController::class)
                ->except(['show'])
                ->middleware('permission:tenant-maintenance.manage');

            // Maintenance Records
            Route::resource('maintenance-records', MaintenanceRecordsController::class)
                ->except(['show'])
                ->parameters(['maintenance-records' => 'maintenance'])
                ->middleware('permission:tenant-maintenance.manage');
        });

        // Car Violations
        Route::middleware('tenant.feature:violations_module')->group(function () {
            Route::resource('violation-types', ViolationTypesController::class)
                ->except(['show'])
                ->middleware('permission:tenant-violations.manage');
            Route::resource('car-violations', CarViolationsController::class)
                ->except(['show'])
                ->parameters(['car-violations' => 'carViolation'])
                ->middleware('permission:tenant-violations.manage');
            Route::get('car-violations/{carViolation}/notice', [CarViolationsController::class, 'noticeEdit'])
                ->middleware('permission:tenant-violations.manage')
                ->name('car-violations.notice.edit');
            Route::put('car-violations/{carViolation}/notice', [CarViolationsController::class, 'noticeUpdate'])
                ->middleware('permission:tenant-violations.manage')
                ->name('car-violations.notice.update');
            Route::get('car-violations/{carViolation}/notice/pdf', [CarViolationsController::class, 'noticePdf'])
                ->middleware('permission:tenant-violations.manage')
                ->name('car-violations.notice.pdf');
            Route::get('car-violations/{carViolation}/notice/print', [CarViolationsController::class, 'noticePrint'])
                ->middleware('permission:tenant-violations.manage')
                ->name('car-violations.notice.print');
        });

        Route::middleware('tenant.feature:damage_reports')->group(function () {
            Route::resource('car-damage-reports', CarDamageReportsController::class)
                ->except(['show'])
                ->parameters(['car-damage-reports' => 'carDamageReport'])
                ->middleware('permission:tenant-damages.manage');
            Route::resource('damage-repairs', DamageRepairsController::class)
                ->except(['show'])
                ->parameters(['damage-repairs' => 'damageRepair'])
                ->middleware('permission:tenant-damages.manage');
        });

        // Reservations
        Route::get('reservations/create', [ReservationsController::class, 'create'])
            ->middleware(['tenant.plan.limit:reservations', 'permission:tenant-reservations.create'])
            ->name('reservations.create');
        Route::resource('reservations', ReservationsController::class)
            ->only(['index', 'store', 'show', 'edit', 'update'])
            ->middlewareFor(['store'], 'tenant.plan.limit:reservations')
            ->middlewareFor(['index', 'show'], 'permission:tenant-reservations.view')
            ->middlewareFor(['edit', 'update'], 'permission:tenant-reservations.update')
            ->middlewareFor(['store'], 'permission:tenant-reservations.create');
        Route::post('booking-requests/{bookingRequest}/convert', [ReservationsController::class, 'convertBookingRequest'])
            ->middleware(['tenant.plan.limit:reservations', 'permission:tenant-reservations.create'])
            ->name('booking-requests.convert');
        Route::post('reservations/{reservation}/cash-payment', [ReservationsController::class, 'collectCashPayment'])
            ->middleware(['permission:tenant-payments.collect', 'tenant.feature:cash_payments'])
            ->name('reservations.cash-payment');
        Route::get('reservations/{reservation}/print', [ReservationsController::class, 'print'])
            ->middleware(['permission:tenant-reservations.print', 'tenant.feature:pdf_export'])
            ->name('reservations.print');

        // Contracts
        Route::middleware('tenant.feature:ai_contract_extraction')->group(function () {
            Route::post('contracts/extract', [ContractsController::class, 'extract'])
                ->middleware('permission:tenant-contracts.create')
                ->name('contracts.extract');
            Route::post('contracts/drivers/extract', [ContractsController::class, 'extractDriverDocument'])
                ->middleware('permission:tenant-contracts.create')
                ->name('contracts.drivers.extract');
            Route::post('contracts/drivers/photo/extract', [ContractsController::class, 'extractDriverCustomerPhoto'])
                ->middleware('permission:tenant-contracts.create')
                ->name('contracts.drivers.photo.extract');
        });
        Route::post('contracts/{contract}/extension-request', [ContractsController::class, 'requestExtension'])
            ->middleware(['permission:tenant-contracts.handover', 'tenant.feature:extension_request'])
            ->name('contracts.request-extension');
        Route::post('contracts/{contract}/extend', [ContractsController::class, 'extend'])
            ->middleware(['permission:tenant-contracts.handover', 'tenant.feature:force_extend_contract'])
            ->name('contracts.extend');
        Route::post('contracts/{contract}/deliver', [ContractsController::class, 'deliver'])
            ->middleware('permission:tenant-contracts.handover')
            ->name('contracts.deliver');
        Route::get('contracts/{contract}/return-status-report', [ContractReturnReportsController::class, 'create'])
            ->middleware('permission:tenant-contracts.return-reports.view|tenant-contracts.return-reports.update|tenant-payments.collect')
            ->name('contracts.return-report');
        Route::post('contracts/{contract}/return-status-report', [ContractReturnReportsController::class, 'store'])
            ->middleware('permission:tenant-contracts.return-reports.update')
            ->name('contracts.return-report.store');
        Route::post('contracts/{contract}/return-status-report/cash-payment', [ContractReturnReportsController::class, 'collectCashPayment'])
            ->middleware(['permission:tenant-payments.collect', 'tenant.feature:cash_payments'])
            ->name('contracts.return-report.cash-payment');
        Route::get('contracts/{contractId}/return-status-report/pdf', [ContractReturnReportsController::class, 'pdf'])
            ->middleware(['permission:tenant-contracts.pdf', 'tenant.feature:pdf_export'])
            ->name('contracts.return-report.pdf');
        Route::get('contracts/{contract}/pdf', [ContractsController::class, 'pdf'])
            ->middleware(['permission:tenant-contracts.pdf', 'tenant.feature:pdf_export'])
            ->name('contracts.pdf');
        Route::get('contracts/create', [ContractsController::class, 'create'])
            ->middleware(['tenant.plan.limit:contracts', 'permission:tenant-contracts.create'])
            ->name('contracts.create');
        Route::resource('contracts', ContractsController::class)
            ->only(['index', 'store', 'show', 'edit', 'update'])
            ->middlewareFor(['store'], 'tenant.plan.limit:contracts')
            ->middlewareFor(['index', 'show'], 'permission:tenant-contracts.view')
            ->middlewareFor(['edit', 'update'], 'permission:tenant-contracts.update')
            ->middlewareFor(['store'], 'permission:tenant-contracts.create');
        Route::get('accident-reports/{accidentReport}/mrta-form', [AccidentReportsController::class, 'mrtaForm'])
            ->middleware(['permission:tenant-damages.manage', 'tenant.feature:damage_reports'])
            ->name('accident-reports.mrta-form');
        Route::resource('accident-reports', AccidentReportsController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->middleware(['permission:tenant-damages.manage', 'tenant.feature:damage_reports']);

        // Clients
        Route::resource('clients', ClientsController::class)
            ->only(['index', 'show', 'create', 'store'])
            ->middlewareFor(['index', 'show'], 'permission:tenant-clients.view')
            ->middlewareFor(['create', 'store'], 'permission:tenant-clients.create');
        Route::get('clients/{client}/documents', [ClientsController::class, 'documents'])
            ->middleware('permission:tenant-clients.update')
            ->name('clients.documents');
        Route::post('clients/{client}/documents/extract', [ClientsController::class, 'extractDocument'])
            ->middleware('permission:tenant-clients.update')
            ->name('clients.documents.extract');
        Route::post('clients/{client}/documents/save', [ClientsController::class, 'saveDocument'])
            ->middleware('permission:tenant-clients.update')
            ->name('clients.documents.save');
        Route::post('clients/{client}/notes', [ClientsController::class, 'storeNote'])
            ->middleware('permission:tenant-clients.update')
            ->name('clients.notes.store');
        Route::patch('clients/{client}/suspend', [ClientsController::class, 'suspend'])
            ->middleware('permission:tenant-clients.update')
            ->name('clients.suspend');
        Route::patch('clients/{client}/activate', [ClientsController::class, 'activate'])
            ->middleware('permission:tenant-clients.update')
            ->name('clients.activate');

        // Payments
        Route::get('payments/debtors', [PaymentsController::class, 'debtors'])
            ->middleware(['permission:tenant-debtors.view', 'tenant.feature:cash_payments'])
            ->name('payments.debtors');
        Route::resource('payments', PaymentsController::class)
            ->only(['index'])
            ->middleware(['permission:tenant-payments.view', 'tenant.feature:cash_payments']);
        Route::get('discount-requests', [DiscountRequestsController::class, 'index'])
            ->middleware(['permission:tenant-discounts.manage', 'tenant.feature:cash_payments'])
            ->name('discount-requests.index');
        Route::post('discount-requests/{discountRequest}/approve', [DiscountRequestsController::class, 'approve'])
            ->middleware(['permission:tenant-discounts.manage', 'tenant.feature:cash_payments'])
            ->name('discount-requests.approve');
        Route::post('discount-requests/{discountRequest}/reject', [DiscountRequestsController::class, 'reject'])
            ->middleware(['permission:tenant-discounts.manage', 'tenant.feature:cash_payments'])
            ->name('discount-requests.reject');

        // Coupons
        Route::resource('coupons', CouponsController::class)
            ->except(['show'])
            ->middleware(['permission:tenant-discounts.manage', 'tenant.feature:coupon_system']);
        Route::resource('car-discounts', CarDiscountsController::class)
            ->except(['show'])
            ->parameters(['car-discounts' => 'carDiscount'])
            ->middleware(['permission:tenant-discounts.manage', 'tenant.feature:auto_discounts']);

        // Reports
        Route::get('reports/executive/pdf', [ReportsController::class, 'exportExecutivePdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.executive.pdf');
        Route::get('reports/executive/excel', [ReportsController::class, 'exportExecutiveExcel'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.executive.excel');
        Route::get('reports/financial/pdf', [ReportsController::class, 'exportFinancialPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.financial.pdf');
        Route::get('reports/financial/excel', [ReportsController::class, 'exportFinancialExcel'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.financial.excel');
        Route::get('reports/reservations/pdf', [ReportsController::class, 'exportReservationsPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.reservations.pdf');
        Route::get('reports/reservations/excel', [ReportsController::class, 'exportReservationsExcel'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.reservations.excel');
        Route::get('reports/fleet/pdf', [ReportsController::class, 'exportFleetPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.fleet.pdf');
        Route::get('reports/vehicle-profitability/pdf', [ReportsController::class, 'exportVehicleProfitabilityPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.vehicle-profitability.pdf');
        Route::get('reports/vehicle-profitability/excel', [ReportsController::class, 'exportVehicleProfitabilityExcel'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.vehicle-profitability.excel');
        Route::get('reports/customers/pdf', [ReportsController::class, 'exportCustomersPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.customers.pdf');
        Route::get('reports/customers/excel', [ReportsController::class, 'exportCustomersExcel'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.customers.excel');
        Route::get('reports/damages/pdf', [ReportsController::class, 'exportDamagesPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.damages.pdf');
        Route::get('reports/traffic-violations/pdf', [ReportsController::class, 'exportTrafficViolationsPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.traffic-violations.pdf');
        Route::get('reports/open-contracts/pdf', [ReportsController::class, 'exportOpenContractsPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.open-contracts.pdf');
        Route::get('reports/collections/pdf', [ReportsController::class, 'exportCollectionsPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.collections.pdf');
        Route::get('reports/collections/excel', [ReportsController::class, 'exportCollectionsExcel'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.collections.excel');
        Route::get('reports/staff-performance/pdf', [ReportsController::class, 'exportStaffPerformancePdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('reports.staff-performance.pdf');
        Route::resource('reports', ReportsController::class)
            ->except(['show'])
            ->middleware(['permission:tenant-reports.view', 'tenant.feature:reports_module']);
        Route::get('ai-insights', [AiInsightsController::class, 'index'])
            ->middleware(['permission:tenant-ai-insights.manage', 'tenant.feature:reports_module'])
            ->name('ai-insights.index');
        Route::post('ai-insights/generate', [AiInsightsController::class, 'generate'])
            ->middleware(['permission:tenant-ai-insights.manage', 'tenant.feature:reports_module'])
            ->name('ai-insights.generate');
        Route::post('ai-insights/{aiInsightReport}/analyze', [AiInsightsController::class, 'analyze'])
            ->middleware(['permission:tenant-ai-insights.manage', 'tenant.feature:reports_module'])
            ->name('ai-insights.analyze');
        Route::post('ai-insights/apply-pricing', [AiInsightsController::class, 'applyPricing'])
            ->middleware(['permission:tenant-cars.update', 'tenant.feature:reports_module'])
            ->name('ai-insights.apply-pricing');
        Route::get('ai-insights/{aiInsightReport}/pdf', [AiInsightsController::class, 'exportPdf'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('ai-insights.pdf');
        Route::get('ai-insights/{aiInsightReport}/excel', [AiInsightsController::class, 'exportExcel'])
            ->middleware(['permission:tenant-reports.export', 'tenant.feature:reports_module'])
            ->name('ai-insights.excel');

        // Support
        Route::resource('support', SupportController::class)
            ->only(['index'])
            ->middleware('permission:tenant-support.view');
        Route::get('/support/tickets/{ticket}', [SupportController::class, 'show'])
        ->middleware('permission:tenant-support.view')
        ->name('support.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportController::class, 'reply'])
        ->middleware('permission:tenant-support.reply')
        ->name('support.reply');
        Route::post('/support/tickets/{ticket}/close', [SupportController::class, 'close'])
        ->middleware('permission:tenant-support.reply')
        ->name('support.close');

        // Tenant -> Super Admin Support
        Route::get('/support/platform', [PlatformSupportController::class, 'index'])
            ->middleware('permission:tenant-support.view')
            ->name('support.platform.index');
        Route::post('/support/platform', [PlatformSupportController::class, 'store'])
            ->middleware('permission:tenant-support.reply')
            ->name('support.platform.store');
        Route::get('/support/platform/{ticket}', [PlatformSupportController::class, 'show'])
            ->middleware('permission:tenant-support.view')
            ->name('support.platform.show');
        Route::post('/support/platform/{ticket}/reply', [PlatformSupportController::class, 'reply'])
            ->middleware('permission:tenant-support.reply')
            ->name('support.platform.reply');
        Route::post('/support/platform/{ticket}/close', [PlatformSupportController::class, 'close'])
            ->middleware('permission:tenant-support.reply')
            ->name('support.platform.close');

        // Branches
        Route::get('branches/location-options/cities', [BranchesController::class, 'cities'])
            ->middleware('permission:tenant-branches.view')
            ->name('branches.cities');
        Route::get('branches/create', [BranchesController::class, 'create'])
            ->middleware(['tenant.plan.limit:branches', 'permission:tenant-branches.create'])
            ->name('branches.create');
        Route::resource('branches', BranchesController::class)
            ->except(['show', 'create'])
            ->middlewareFor(['store'], 'tenant.plan.limit:branches')
            ->middlewareFor(['index'], 'permission:tenant-branches.view')
            ->middlewareFor(['store'], 'permission:tenant-branches.create')
            ->middlewareFor(['edit', 'update'], 'permission:tenant-branches.update')
            ->middlewareFor(['destroy'], 'permission:tenant-branches.delete');

        // Employees
        Route::resource('employees', EmployeesController::class)
            ->except(['show'])
            ->middlewareFor(['create', 'store'], 'tenant.plan.limit:employees')
            ->middlewareFor(['index'], 'permission:tenant-employees.view')
            ->middlewareFor(['create', 'store'], 'permission:tenant-employees.create')
            ->middlewareFor(['edit', 'update'], 'permission:tenant-employees.update')
            ->middlewareFor(['destroy'], 'permission:tenant-employees.delete');

        // Roles
        Route::resource('roles', RolesController::class)
            ->except(['show'])
            ->middleware(['permission:tenant-roles.manage', 'tenant.feature:roles_and_permissions']);

        // Tenant payment gateway (Stripe Connect)
        Route::get('settings/payment-providers', [PaymentProvidersController::class, 'edit'])
            ->middleware(['permission:tenant-billing.manage', 'tenant.feature:stripe_connect'])
            ->name('settings.payment-providers.edit');
        Route::put('settings/payment-providers', [PaymentProvidersController::class, 'update'])
            ->middleware(['permission:tenant-billing.manage', 'tenant.feature:stripe_connect'])
            ->name('settings.payment-providers.update');

        Route::get('settings/website', [WebsiteSettingsController::class, 'edit'])
            ->middleware(['permission:tenant-settings.update', 'tenant.feature:custom_branding'])
            ->name('settings.website.edit');
        Route::get('settings/static-pages', [WebsiteSettingsController::class, 'staticPagesEdit'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.static-pages.edit');
        Route::get('settings/seo', [WebsiteSettingsController::class, 'seoEdit'])
            ->middleware('permission:tenant-seo.manage')
            ->name('settings.seo.edit');
        Route::get('settings/seo-audit', [WebsiteSettingsController::class, 'seoAudit'])
            ->middleware('permission:tenant-seo.manage')
            ->name('settings.seo-audit');
        Route::put('settings/website', [WebsiteSettingsController::class, 'update'])
            ->middleware(['permission:tenant-settings.update', 'tenant.feature:custom_branding'])
            ->name('settings.website.update');
        Route::put('settings/static-pages', [WebsiteSettingsController::class, 'staticPagesUpdate'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.static-pages.update');
        Route::put('settings/seo', [WebsiteSettingsController::class, 'seoUpdate'])
            ->middleware('permission:tenant-seo.manage')
            ->name('settings.seo.update');
        Route::get('settings/reservation-settings', [ReservationSettingsController::class, 'edit'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.reservation-settings.edit');
        Route::put('settings/reservation-settings', [ReservationSettingsController::class, 'update'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.reservation-settings.update');
        Route::get('settings/plate-formats', [PlateFormatSettingsController::class, 'edit'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.plate-formats.edit');
        Route::put('settings/plate-formats', [PlateFormatSettingsController::class, 'update'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.plate-formats.update');
        Route::get('settings/police-notice', [WebsiteSettingsController::class, 'policeNoticeEdit'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.police-notice.edit');
        Route::put('settings/police-notice', [WebsiteSettingsController::class, 'policeNoticeUpdate'])
            ->middleware('permission:tenant-settings.update')
            ->name('settings.police-notice.update');
        Route::get('settings/contract-pdf', [WebsiteSettingsController::class, 'contractPdfEdit'])
            ->middleware(['permission:tenant-settings.update', 'tenant.feature:pdf_export'])
            ->name('settings.contract-pdf.edit');
        Route::put('settings/contract-pdf', [WebsiteSettingsController::class, 'contractPdfUpdate'])
            ->middleware(['permission:tenant-settings.update', 'tenant.feature:pdf_export'])
            ->name('settings.contract-pdf.update');
        Route::get('settings/mrta-pdf', [WebsiteSettingsController::class, 'mrtaPdfEdit'])
            ->middleware(['permission:tenant-settings.update', 'tenant.feature:pdf_export'])
            ->name('settings.mrta-pdf.edit');
        Route::put('settings/mrta-pdf', [WebsiteSettingsController::class, 'mrtaPdfUpdate'])
            ->middleware(['permission:tenant-settings.update', 'tenant.feature:pdf_export'])
            ->name('settings.mrta-pdf.update');
        Route::get('settings/translations', [TranslationSettingsController::class, 'edit'])
            ->middleware('permission:tenant-translations.manage')
            ->name('settings.translations.edit');
        Route::put('settings/translations', [TranslationSettingsController::class, 'update'])
            ->middleware('permission:tenant-translations.manage')
            ->name('settings.translations.update');

        Route::get('settings/stripe-connect', [StripeConnectController::class, 'edit'])
            ->middleware(['permission:tenant-billing.manage', 'tenant.feature:stripe_connect'])
            ->name('settings.stripe-connect.edit');
        Route::post('settings/stripe-connect/connect', [StripeConnectController::class, 'connect'])
            ->middleware(['permission:tenant-billing.manage', 'tenant.feature:stripe_connect'])
            ->name('settings.stripe-connect.connect');
        Route::get('settings/stripe-connect/refresh', [StripeConnectController::class, 'refresh'])
            ->middleware(['permission:tenant-billing.manage', 'tenant.feature:stripe_connect'])
            ->name('settings.stripe-connect.refresh');
        Route::get('settings/stripe-connect/return', [StripeConnectController::class, 'returned'])
            ->middleware(['permission:tenant-billing.manage', 'tenant.feature:stripe_connect'])
            ->name('settings.stripe-connect.return');
        Route::post('settings/stripe-connect/login-link', [StripeConnectController::class, 'loginLink'])
            ->middleware(['permission:tenant-billing.manage', 'tenant.feature:stripe_connect'])
            ->name('settings.stripe-connect.login-link');

    });
