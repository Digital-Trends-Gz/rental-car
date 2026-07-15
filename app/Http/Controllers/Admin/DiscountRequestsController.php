<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountRequestStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ContractReturnReport;
use App\Models\DiscountRequest;
use App\Models\Payment;
use App\Support\BranchAccess;
use App\Support\FinancialVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DiscountRequestsController extends Controller
{
    public function __construct(private readonly BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $canViewFinancialAmounts = FinancialVisibility::canViewFinancialAmounts($user);
        $search = $request->string('search')->toString();
        $status = $request->string('status', DiscountRequestStatus::PENDING->value)->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $query = DiscountRequest::query()
            ->with([
                'requestedBy:id,name,email,branch_id',
                'reviewedBy:id,name,email',
                'reservation:id,reservation_number,user_id,car_id,total_amount,discount_amount',
                'reservation.user:id,name,email',
                'reservation.car:id,branch_id,year,make,model,license_plate',
                'reservation.car.branch:id,name',
                'contract:id,contract_number',
                'returnReport:id,report_number,total_extra_charges,discount,payment_status',
            ]);

        $this->applyBranchScope($query, $user, $branchId);

        $query
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('reservation_number', 'like', "%{$search}%"))
                        ->orWhereHas('contract', fn (Builder $contractQuery) => $contractQuery->where('contract_number', 'like', "%{$search}%"))
                        ->orWhereHas('reservation.user', function (Builder $clientQuery) use ($search): void {
                            $clientQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('requestedBy', function (Builder $employeeQuery) use ($search): void {
                            $employeeQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status && $status !== 'all', fn (Builder $query) => $query->where('status', $status));

        $requests = $query
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [DiscountRequestStatus::PENDING->value])
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $requests->getCollection()->transform(function (DiscountRequest $discountRequest) use ($canViewFinancialAmounts) {
            return [
                'id' => $discountRequest->id,
                'base_amount' => FinancialVisibility::numericAmount($discountRequest->base_amount, $canViewFinancialAmounts),
                'discount_type' => $discountRequest->discount_type,
                'discount_value' => FinancialVisibility::numericAmount($discountRequest->discount_value, $canViewFinancialAmounts),
                'discount_amount' => FinancialVisibility::numericAmount($discountRequest->discount_amount, $canViewFinancialAmounts),
                'final_amount' => FinancialVisibility::numericAmount($discountRequest->final_amount, $canViewFinancialAmounts),
                'reason' => $discountRequest->reason,
                'status' => $discountRequest->status instanceof DiscountRequestStatus ? $discountRequest->status->value : (string) $discountRequest->status,
                'review_note' => $discountRequest->review_note,
                'created_at' => optional($discountRequest->created_at)->toDateTimeString(),
                'reviewed_at' => optional($discountRequest->reviewed_at)->toDateTimeString(),
                'reservation' => $discountRequest->reservation ? [
                    'id' => $discountRequest->reservation->id,
                    'reservation_number' => $discountRequest->reservation->reservation_number,
                    'url' => route('admin.reservations.show', $discountRequest->reservation),
                ] : null,
                'contract' => $discountRequest->contract ? [
                    'id' => $discountRequest->contract->id,
                    'contract_number' => $discountRequest->contract->contract_number,
                    'url' => route('admin.contracts.show', $discountRequest->contract),
                ] : null,
                'return_report' => $discountRequest->returnReport ? [
                    'id' => $discountRequest->returnReport->id,
                    'report_number' => $discountRequest->returnReport->report_number,
                    'url' => route('admin.contracts.return-report', $discountRequest->contract_id),
                ] : null,
                'client' => $discountRequest->reservation?->user ? [
                    'id' => $discountRequest->reservation->user->id,
                    'name' => $discountRequest->reservation->user->name,
                    'email' => $discountRequest->reservation->user->email,
                ] : null,
                'employee' => $discountRequest->requestedBy ? [
                    'id' => $discountRequest->requestedBy->id,
                    'name' => $discountRequest->requestedBy->name,
                    'email' => $discountRequest->requestedBy->email,
                ] : null,
                'reviewed_by' => $discountRequest->reviewedBy ? [
                    'id' => $discountRequest->reviewedBy->id,
                    'name' => $discountRequest->reviewedBy->name,
                    'email' => $discountRequest->reviewedBy->email,
                ] : null,
                'previous_approved_discounts' => $this->previousApprovedDiscountsPayload($discountRequest, $canViewFinancialAmounts),
                'car' => $discountRequest->reservation?->car ? [
                    'name' => trim(sprintf(
                        '%s %s %s',
                        (string) ($discountRequest->reservation->car->year ?? ''),
                        (string) ($discountRequest->reservation->car->make ?? ''),
                        (string) ($discountRequest->reservation->car->model ?? '')
                    )),
                    'license_plate' => $discountRequest->reservation->car->license_plate,
                ] : null,
                'branch_name' => $discountRequest->reservation?->car?->branch?->name,
                'approve_url' => route('admin.discount-requests.approve', $discountRequest),
                'reject_url' => route('admin.discount-requests.reject', $discountRequest),
            ];
        });

        return Inertia::render('Admin/DiscountRequests/Index', [
            'discountRequests' => $requests,
            'statuses' => $this->statusOptions(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'canViewFinancials' => $canViewFinancialAmounts,
            'indexUrl' => route('admin.discount-requests.index'),
            'currency' => [
                'symbol' => config('app.currency_symbol', '$'),
                'code' => strtoupper((string) config('app.currency_code', 'USD')),
            ],
        ]);
    }

    public function approve(Request $request, DiscountRequest $discountRequest): RedirectResponse
    {
        abort_unless($this->canAccessDiscountRequest($discountRequest, $request), 403);

        DB::transaction(function () use ($discountRequest, $request): void {
            $lockedRequest = DiscountRequest::query()
                ->whereKey($discountRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== DiscountRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'discount_request' => 'This discount request is no longer pending.',
                ]);
            }

            $report = ContractReturnReport::query()
                ->with(['reservation.payments'])
                ->whereKey($lockedRequest->contract_return_report_id)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingAmount = $this->returnReportBalance($report);
            if ($remainingAmount <= 0) {
                throw ValidationException::withMessages([
                    'discount_request' => 'There is no remaining return report amount to discount.',
                ]);
            }

            $appliedDiscount = round(min((float) $lockedRequest->discount_amount, $remainingAmount), 2);
            $newDiscountAmount = round((float) ($report->discount ?? 0) + $appliedDiscount, 2);
            $newTotalExtraCharges = round(max(0, (float) $report->total_extra_charges - $appliedDiscount), 2);

            $report->forceFill([
                'discount' => $newDiscountAmount,
                'total_extra_charges' => $newTotalExtraCharges,
                'payment_status' => $this->reportPaymentStatusAfterDiscount($report, $newTotalExtraCharges),
            ])->save();

            $lockedRequest->forceFill([
                'discount_amount' => $appliedDiscount,
                'final_amount' => round(max(0, $remainingAmount - $appliedDiscount), 2),
                'status' => DiscountRequestStatus::APPROVED,
                'reviewed_by_user_id' => $request->user()?->id,
                'reviewed_at' => now(),
                'approved_at' => now(),
            ])->save();
        });

        return back()->with('success', 'Discount request approved.');
    }

    public function reject(Request $request, DiscountRequest $discountRequest): RedirectResponse
    {
        abort_unless($this->canAccessDiscountRequest($discountRequest, $request), 403);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($discountRequest, $request, $validated): void {
            $lockedRequest = DiscountRequest::query()
                ->whereKey($discountRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== DiscountRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'discount_request' => 'This discount request is no longer pending.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => DiscountRequestStatus::REJECTED,
                'reviewed_by_user_id' => $request->user()?->id,
                'review_note' => trim((string) ($validated['review_note'] ?? '')) ?: null,
                'reviewed_at' => now(),
                'rejected_at' => now(),
            ])->save();
        });

        return back()->with('success', 'Discount request rejected.');
    }

    private function applyBranchScope(Builder $query, $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn (Builder $carQuery) => $carQuery->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('reservation.car', fn (Builder $carQuery) => $carQuery->where('branch_id', $userBranchId));
    }

    private function canAccessDiscountRequest(DiscountRequest $discountRequest, Request $request): bool
    {
        $discountRequest->loadMissing('reservation.car:id,branch_id');

        return $this->branchAccess->canAccessBranchId(
            $request->user(),
            $discountRequest->reservation?->car?->branch_id ? (int) $discountRequest->reservation->car->branch_id : null
        );
    }

    private function previousApprovedDiscountsPayload(DiscountRequest $discountRequest, bool $canViewFinancialAmounts): array
    {
        if (!$discountRequest->contract_return_report_id) {
            return [];
        }

        return DiscountRequest::query()
            ->with(['requestedBy:id,name,email', 'reviewedBy:id,name,email'])
            ->where('contract_return_report_id', $discountRequest->contract_return_report_id)
            ->where('status', DiscountRequestStatus::APPROVED->value)
            ->whereKeyNot($discountRequest->id)
            ->orderByDesc('approved_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DiscountRequest $previousRequest): array => [
                'id' => $previousRequest->id,
                'discount_type' => $previousRequest->discount_type,
                'discount_value' => FinancialVisibility::numericAmount($previousRequest->discount_value, $canViewFinancialAmounts),
                'discount_amount' => FinancialVisibility::numericAmount($previousRequest->discount_amount, $canViewFinancialAmounts),
                'final_amount' => FinancialVisibility::numericAmount($previousRequest->final_amount, $canViewFinancialAmounts),
                'reason' => $previousRequest->reason,
                'employee' => $previousRequest->requestedBy ? [
                    'id' => $previousRequest->requestedBy->id,
                    'name' => $previousRequest->requestedBy->name,
                    'email' => $previousRequest->requestedBy->email,
                ] : null,
                'reviewed_by' => $previousRequest->reviewedBy ? [
                    'id' => $previousRequest->reviewedBy->id,
                    'name' => $previousRequest->reviewedBy->name,
                    'email' => $previousRequest->reviewedBy->email,
                ] : null,
                'created_at' => optional($previousRequest->created_at)->toDateTimeString(),
                'approved_at' => optional($previousRequest->approved_at)->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    private function returnReportBalance(ContractReturnReport $report): float
    {
        $paid = $report->reservation?->payments
            ? $report->reservation->payments
            ->filter(fn (Payment $payment): bool => $this->paymentStatusValue($payment) === PaymentStatus::COMPLETED->value)
                ->filter(fn (Payment $payment): bool => $this->isPaymentForReturnReport($payment, $report))
                ->sum(fn (Payment $payment): float => (float) $payment->amount)
            : 0;

        return round(max(0, (float) $report->total_extra_charges - (float) $paid), 2);
    }

    private function reportPaymentStatusAfterDiscount(ContractReturnReport $report, float $newTotalExtraCharges): string
    {
        $paid = $report->reservation?->payments
            ? $report->reservation->payments
                ->filter(fn (Payment $payment): bool => $this->paymentStatusValue($payment) === PaymentStatus::COMPLETED->value)
                ->filter(fn (Payment $payment): bool => $this->isPaymentForReturnReport($payment, $report))
                ->sum(fn (Payment $payment): float => (float) $payment->amount)
            : 0;

        return round(max(0, $newTotalExtraCharges - (float) $paid), 2) <= 0 ? 'paid' : 'not_paid';
    }

    private function isPaymentForReturnReport(Payment $payment, ContractReturnReport $report): bool
    {
        $sourceType = (string) data_get($payment->gateway_data, 'cash_source.type');
        $sourceId = (int) data_get($payment->gateway_data, 'cash_source.id');

        if ($sourceType === 'contract_return_report' && $sourceId === (int) $report->id) {
            return true;
        }

        return $report->payment_id && (int) $payment->id === (int) $report->payment_id;
    }

    private function paymentStatusValue(Payment $payment): string
    {
        return $payment->status instanceof PaymentStatus ? $payment->status->value : (string) $payment->status;
    }

    private function statusOptions(): array
    {
        return collect(DiscountRequestStatus::cases())
            ->mapWithKeys(fn (DiscountRequestStatus $status) => [
                $status->value => [
                    'label' => $status->label(),
                ],
            ])
            ->all();
    }
}
