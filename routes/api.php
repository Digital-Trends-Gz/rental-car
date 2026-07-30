<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccidentReportsController;
use App\Http\Controllers\Api\CashPaymentsController;
use App\Http\Controllers\Api\CarPhotoHistoryController;
use App\Http\Controllers\Api\CarsController;
use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\ContractsController;
use App\Http\Controllers\Api\DailyTasksController;
use App\Http\Controllers\Api\DiscountRequestsController;
use App\Http\Controllers\Api\ReservationsController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\OwnerDashboardController;
use App\Http\Controllers\Api\OwnerDiscountRequestsController;
use App\Http\Controllers\Api\OwnerFinanceController;
use App\Http\Controllers\Api\OwnerFleetController;
use App\Http\Controllers\Api\OwnerNotificationsController;
use App\Http\Controllers\Api\OwnerReservationsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StaticPageContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:api-otp-verify');

     Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('switch-mode', [AuthController::class, 'switchMode']);
    });

    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:api-password-reset-request');

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:api-password-reset');
});

Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('summary', [DashboardController::class, 'summary'])->name('api.dashboard.summary');
});

Route::middleware('auth:sanctum')->prefix('owner')->group(function () {
    Route::get('branches', [OwnerDashboardController::class, 'branches'])->name('api.owner.branches');
    Route::get('dashboard/summary', [OwnerDashboardController::class, 'summary'])->name('api.owner.dashboard.summary');
    Route::get('finance/summary', [OwnerFinanceController::class, 'summary'])->name('api.owner.finance.summary');
    Route::get('fleet/statuses', [OwnerFleetController::class, 'statuses'])->name('api.owner.fleet.statuses');
    Route::get('fleet', [OwnerFleetController::class, 'index'])->name('api.owner.fleet.index');
    Route::get('fleet/{car}', [OwnerFleetController::class, 'show'])->name('api.owner.fleet.show');
    Route::get('reservations/statuses', [OwnerReservationsController::class, 'statuses'])->name('api.owner.reservations.statuses');
    Route::get('reservations/summary', [OwnerReservationsController::class, 'summary'])->name('api.owner.reservations.summary');
    Route::get('reservations', [OwnerReservationsController::class, 'index'])->name('api.owner.reservations.index');
    Route::get('reservations/{reservation}', [OwnerReservationsController::class, 'show'])->name('api.owner.reservations.show');
    Route::get('notifications', [OwnerNotificationsController::class, 'index'])->name('api.owner.notifications.index');
    Route::get('notifications/count', [OwnerNotificationsController::class, 'count'])->name('api.owner.notifications.count');
    Route::post('notifications/read-all', [OwnerNotificationsController::class, 'readAll'])->name('api.owner.notifications.read-all');
    Route::get('discount-requests', [OwnerDiscountRequestsController::class, 'index'])->name('api.owner.discount-requests.index');
    Route::get('discount-requests/count', [OwnerDiscountRequestsController::class, 'count'])->name('api.owner.discount-requests.count');
    Route::get('discount-requests/{discountRequest}', [OwnerDiscountRequestsController::class, 'show'])->name('api.owner.discount-requests.show');
    Route::post('discount-requests/{discountRequest}/approve', [OwnerDiscountRequestsController::class, 'approve'])->name('api.owner.discount-requests.approve');
    Route::post('discount-requests/{discountRequest}/reject', [OwnerDiscountRequestsController::class, 'reject'])->name('api.owner.discount-requests.reject');
});

Route::middleware('auth:sanctum')->prefix('tasks')->group(function () {
    Route::get('today', [DailyTasksController::class, 'today'])->name('api.tasks.today');
    Route::get('status', [DailyTasksController::class, 'status'])->name('api.tasks.status');
    Route::post('start', [DailyTasksController::class, 'start'])->name('api.tasks.start');
    Route::post('complete', [DailyTasksController::class, 'complete'])->name('api.tasks.complete');
    Route::post('schedule', [DailyTasksController::class, 'schedule'])->name('api.tasks.schedule');
});

Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationsController::class, 'index'])->name('api.notifications.index');
    Route::get('count', [NotificationsController::class, 'count'])->name('api.notifications.count');
    Route::post('read-all', [NotificationsController::class, 'readAll'])->name('api.notifications.read-all');
});

Route::middleware('auth:sanctum')->prefix('clients')->group(function () {
    Route::get('{client}/status', [ClientsController::class, 'status'])->name('api.clients.status');
});

Route::middleware('auth:sanctum')->prefix('cars')->group(function () {
    Route::get('/', [CarsController::class, 'index'])->name('api.cars.index');
    Route::get('status', [CarsController::class, 'status'])->name('api.cars.status');
    Route::get('photo-history/status', [CarPhotoHistoryController::class, 'status'])->name('api.cars.photo-history.status');
    Route::get('{car}/photo-histories', [CarPhotoHistoryController::class, 'index'])->name('api.cars.photo-histories.index');
    Route::post('{car}/photo-histories', [CarPhotoHistoryController::class, 'store'])->name('api.cars.photo-histories.store');
    Route::delete('{car}/photo-histories/{photoHistory}', [CarPhotoHistoryController::class, 'destroy'])->name('api.cars.photo-histories.destroy');
});

Route::middleware('auth:sanctum')->prefix('reservations')->group(function () {
    Route::get('task-types', [ReservationsController::class, 'taskTypes'])->name('api.reservations.task-types');
    Route::get('status', [ReservationsController::class, 'status'])->name('api.reservations.status');
    Route::get('tasks', [ReservationsController::class, 'tasks'])->name('api.reservations.tasks');
    Route::get('today-pickups', [ReservationsController::class, 'todayPickups'])->name('api.reservations.today-pickups');
    Route::get('returns', [ReservationsController::class, 'returns'])->name('api.reservations.returns');
    Route::post('{reservation}/cash-payments', [CashPaymentsController::class, 'storeReservationPayment'])->name('api.reservations.cash-payments.store');
    Route::get('{reservation}/handover', [ContractsController::class, 'handover'])->name('api.reservations.handover');
    Route::post('{reservation}/note', [ReservationsController::class, 'updateNote'])->name('api.reservations.note');
    Route::get('{reservation}', [ReservationsController::class, 'show'])->name('api.reservations.show');
});

Route::middleware('auth:sanctum')->prefix('contract-return-reports')->group(function () {
    Route::post('{contractReturnReport}/cash-payments', [CashPaymentsController::class, 'storeReturnReportPayment'])->name('api.contract-return-reports.cash-payments.store');
});

Route::middleware('auth:sanctum')->prefix('contracts')->group(function () {
    Route::get('active-today', [ContractsController::class, 'activeToday'])->name('api.contracts.active-today');
    Route::get('damage-options', [ContractsController::class, 'damageOptions'])->name('api.contracts.damage-options');
    Route::get('damage-options/{group}', [ContractsController::class, 'damageOptionGroup'])->name('api.contracts.damage-options.group');
    Route::get('{contract}/documents', [ContractsController::class, 'documents'])->name('api.contracts.documents');
    Route::get('{contract}/accident-reports', [AccidentReportsController::class, 'contractIndex'])->name('api.contracts.accident-reports.index');
    Route::post('{contract}/accident-reports', [AccidentReportsController::class, 'contractStore'])->name('api.contracts.accident-reports.store');
    Route::get('{contract}/damage-report-status', [ContractsController::class, 'damageReportStatus'])->name('api.contracts.damage-report-status');
    Route::post('{contract}/damage-items', [ContractsController::class, 'storeDamageItem'])->name('api.contracts.damage-items.store');
    Route::match(['post', 'put', 'patch'], '{contract}/damage-items/{damageItem}', [ContractsController::class, 'updateDamageItem'])->name('api.contracts.damage-items.update');
    Route::delete('{contract}/damage-items/{damageItem}', [ContractsController::class, 'deleteDamageItem'])->name('api.contracts.damage-items.delete');
    Route::get('{contract}/pdf', [\App\Http\Controllers\Admin\ContractsController::class, 'pdf'])->name('api.contracts.pdf');
    Route::get('{contract}/return-status-report/pdf', [\App\Http\Controllers\Admin\ContractReturnReportsController::class, 'pdf'])->name('api.contracts.return-report.pdf');
    Route::post('{contract}/return-status-report/cash-payments', [CashPaymentsController::class, 'storeContractReturnReportPayment'])->name('api.contracts.return-report.cash-payments.store');
    Route::get('{contract}/return-status-report/discount-request', [DiscountRequestsController::class, 'showForContract'])->name('api.contracts.return-report.discount-request.show');
    Route::post('{contract}/return-status-report/discount-requests', [DiscountRequestsController::class, 'store'])->name('api.contracts.return-report.discount-requests.store');
    Route::match(['post', 'patch'], '{contract}/handover', [ContractsController::class, 'updateHandover'])->name('api.contracts.handover');
});

Route::get('accident-reports/{accidentReport}/mrta-form-file', [AccidentReportsController::class, 'publicMrtaForm'])
    ->middleware('signed:relative')
    ->name('api.accident-reports.mrta-form.public');

Route::middleware('auth:sanctum')->prefix('accident-reports')->group(function () {
    Route::get('options', [AccidentReportsController::class, 'options'])->name('api.accident-reports.options');
    Route::get('context-options', [AccidentReportsController::class, 'contextOptions'])->name('api.accident-reports.context-options');
    Route::get('responsibility-options', [AccidentReportsController::class, 'responsibilityOptionList'])->name('api.accident-reports.responsibility-options');
    Route::get('location-type-options', [AccidentReportsController::class, 'locationTypeOptionList'])->name('api.accident-reports.location-type-options');
    Route::get('mrta-accident-type-options', [AccidentReportsController::class, 'mrtaAccidentTypeOptionList'])->name('api.accident-reports.mrta-accident-type-options');
    Route::get('mrta-accident-cause-options', [AccidentReportsController::class, 'mrtaAccidentCauseOptionList'])->name('api.accident-reports.mrta-accident-cause-options');
    Route::get('branch-options', [AccidentReportsController::class, 'branchOptionList'])->name('api.accident-reports.branch-options');
    Route::get('car-options', [AccidentReportsController::class, 'carOptionList'])->name('api.accident-reports.car-options');
    Route::get('employee-options', [AccidentReportsController::class, 'employeeOptionList'])->name('api.accident-reports.employee-options');
    Route::get('contract-options', [AccidentReportsController::class, 'contractOptionList'])->name('api.accident-reports.contract-options');
    Route::get('/', [AccidentReportsController::class, 'index'])->name('api.accident-reports.index');
    Route::post('/', [AccidentReportsController::class, 'store'])->name('api.accident-reports.store');
    Route::get('{accidentReport}/mrta-form', [AccidentReportsController::class, 'mrtaForm'])->name('api.accident-reports.mrta-form');
    Route::get('{accidentReport}', [AccidentReportsController::class, 'show'])->name('api.accident-reports.show');
});

Route::prefix('settings')->group(function () {
    Route::get('general', [SettingsController::class, 'general'])->name('api.settings.general');
    Route::get('currencies', [SettingsController::class, 'currencies'])->name('api.settings.currencies');
    Route::middleware('auth:sanctum')->get('tenant', [SettingsController::class, 'tenant'])->name('api.settings.tenant');
});

Route::prefix('static-pages')->group(function () {
    Route::get('/', [StaticPageContentController::class, 'index'])->name('api.static-pages.index');
    Route::get('support', [StaticPageContentController::class, 'support'])->name('api.static-pages.support');
    Route::get('privacy-policy', [StaticPageContentController::class, 'privacyPolicy'])->name('api.static-pages.privacy-policy');
    Route::get('terms-conditions', [StaticPageContentController::class, 'termsConditions'])->name('api.static-pages.terms-conditions');
    Route::get('security-policy', [StaticPageContentController::class, 'securityPolicy'])->name('api.static-pages.security-policy');
});
