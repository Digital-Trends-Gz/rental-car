<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Support\FinancialVisibility;
use App\Support\BranchAccess;
use App\Support\CurrencyCatalog;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class PaymentsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $paymentsQuery = Payment::query()
            ->with(['user:id,name,email,branch_id', 'reservation:id,reservation_number,car_id', 'reservation.car:id,branch_id', 'reservation.car.branch:id,name']);

        $this->applyPaymentBranchScope($paymentsQuery, $user, $branchId);

        $payments = $paymentsQuery
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('reservation', fn ($rq) => $rq->where('reservation_number', 'like', "%{$search}%"));
                });
            })
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $payments->getCollection()->transform(function ($payment) use ($canViewFinancialAmounts) {
            return [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => FinancialVisibility::numericAmount($payment->amount, $canViewFinancialAmounts),
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method instanceof \BackedEnum ? $payment->payment_method->value : (string) $payment->payment_method,
                'status' => $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status,
                'processed_at' => optional($payment->processed_at)->toDateTimeString(),
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                    'email' => $payment->user->email,
                ] : null,
                'reservation' => $payment->reservation ? [
                    'id' => $payment->reservation->id,
                    'reservation_number' => $payment->reservation->reservation_number,
                ] : null,
                'branch_name' => $payment->reservation?->car?->branch?->name,
            ];
        });

        $statusCounts = [];
        foreach (PaymentStatus::cases() as $paymentStatus) {
            $statusQuery = Payment::query()->where('status', $paymentStatus->value);
            $this->applyPaymentBranchScope($statusQuery, $user, $branchId);
            $statusCounts[$paymentStatus->value] = $statusQuery->count();
        }

        $statuses = collect(PaymentStatus::cases())->mapWithKeys(function ($status) {
            return [
                $status->value => [
                    'label' => $status->label(),
                    'count' => 0,
                    'color' => $status->color(),
                ]
            ];
        })->map(function ($meta, $key) use ($statusCounts) {
            $meta['count'] = $statusCounts[$key] ?? 0;
            return $meta;
        })->toArray();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'statuses' => $statuses,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'canViewFinancials' => $canViewFinancialAmounts,
        ]);
    }

    public function debtors(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);
        $search = $request->string('search')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $debtQuery = ContractReturnReport::query()
            ->with([
                'payment:id,payment_number,status,currency',
                'contract:id,tenant_id,contract_number,currency',
                'reservation:id,reservation_number,user_id,car_id',
                'reservation.user:id,name,email',
                'reservation.car:id,branch_id,make,model,year,license_plate',
                'reservation.car.branch:id,name',
            ])
            ->where('payment_status', 'not_paid')
            ->where('total_extra_charges', '>', 0);

        $this->applyReturnReportBranchScope($debtQuery, $user, $branchId);

        $debtQuery->when($search, function (Builder $query) use ($search): void {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('report_number', 'like', "%{$search}%")
                    ->orWhereHas('contract', fn (Builder $contractQuery) => $contractQuery->where('contract_number', 'like', "%{$search}%"))
                    ->orWhereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('reservation_number', 'like', "%{$search}%"))
                    ->orWhereHas('reservation.user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        });

        $statsQuery = clone $debtQuery;
        $totalOutstanding = FinancialVisibility::numericAmount((clone $statsQuery)->sum('total_extra_charges'), $canViewFinancialAmounts);
        $clientCount = (clone $statsQuery)
            ->get(['id', 'reservation_id'])
            ->load('reservation:id,user_id')
            ->pluck('reservation.user_id')
            ->filter()
            ->unique()
            ->count();

        $reports = $debtQuery
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $reports->getCollection()->transform(function (ContractReturnReport $report) use ($canViewFinancialAmounts) {
            return [
                'id' => $report->id,
                'report_number' => $report->report_number,
                'amount' => FinancialVisibility::numericAmount($report->total_extra_charges, $canViewFinancialAmounts),
                'currency' => $report->payment?->currency
                    ?: CurrencyCatalog::normalizeCode($report->contract?->currency, CurrencyCatalog::codeForTenantId($report->contract?->tenant_id)),
                'created_at' => optional($report->created_at)->toDateTimeString(),
                'return_report_url' => url('/admin/contracts/'.$report->contract_id.'/return-status-report'),
                'client' => $report->reservation?->user ? [
                    'id' => $report->reservation->user->id,
                    'name' => $report->reservation->user->name,
                    'email' => $report->reservation->user->email,
                ] : null,
                'reservation' => $report->reservation ? [
                    'id' => $report->reservation->id,
                    'reservation_number' => $report->reservation->reservation_number,
                ] : null,
                'contract' => $report->contract ? [
                    'id' => $report->contract->id,
                    'contract_number' => $report->contract->contract_number,
                ] : null,
                'payment' => $report->payment ? [
                    'id' => $report->payment->id,
                    'payment_number' => $report->payment->payment_number,
                    'status' => $report->payment->status instanceof \BackedEnum ? $report->payment->status->value : (string) $report->payment->status,
                ] : null,
                'car' => $report->reservation?->car ? [
                    'id' => $report->reservation->car->id,
                    'name' => trim("{$report->reservation->car->year} {$report->reservation->car->make} {$report->reservation->car->model}"),
                    'license_plate' => $report->reservation->car->license_plate,
                ] : null,
                'branch_name' => $report->reservation?->car?->branch?->name,
            ];
        });

        return Inertia::render('Admin/Payments/Debtors', [
            'reports' => $reports,
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
            ],
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'canViewFinancials' => $canViewFinancialAmounts,
            'summary' => [
                'clients_count' => $clientCount,
                'reports_count' => $reports->total(),
                'total_outstanding' => $totalOutstanding,
            ],
        ]);
    }

    private function applyPaymentBranchScope($query, $user, ?int $branchId): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn ($carQuery) => $carQuery->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('reservation.car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
    }

    private function applyReturnReportBranchScope($query, $user, ?int $branchId): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->where(function (Builder $branchQuery) use ($branchId): void {
                    $branchQuery->where('branch_id', $branchId)
                        ->orWhereHas('reservation.car', fn (Builder $carQuery) => $carQuery->where('branch_id', $branchId));
                });
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function (Builder $branchQuery) use ($userBranchId): void {
            $branchQuery->where('branch_id', $userBranchId)
                ->orWhereHas('reservation.car', fn (Builder $carQuery) => $carQuery->where('branch_id', $userBranchId));
        });
    }
}
