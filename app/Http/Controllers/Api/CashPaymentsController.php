<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Rentals\RentalStatusSyncService;
use App\Support\BranchAccess;
use App\Support\CurrencyCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class CashPaymentsController extends Controller
{
    private const ATTACHMENT_COLLECTION = 'cash_payment_attachments';

    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly FilePondService $filePondService,
        private readonly RentalStatusSyncService $rentalStatusSyncService
    ) {
    }

    public function storeReservationPayment(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $this->authorizeCashPaymentUser($request);
        $reservation->loadMissing(['tenant.subscriptionPlan', 'car:id,branch_id', 'contract']);
        abort_unless($this->canAccessReservation($reservation, $user), 403);
        $this->ensureCashPaymentsFeature($reservation->tenant);

        $validated = $this->validatePaymentRequest($request);
        $attachments = $this->uploadedAttachments($request);
        $this->validateAttachments($attachments);

        $payment = null;
        $summary = [];

        DB::transaction(function () use ($reservation, $user, $validated, $attachments, &$payment, &$summary): void {
            $lockedReservation = Reservation::query()
                ->with(['contract'])
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReservation->status === ReservationStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'reservation' => [$this->message('Cancelled reservations cannot receive cash payments.', 'لا يمكن تحصيل دفعات نقدية على حجز ملغي.')],
                ]);
            }

            $balance = $this->reservationBalance($lockedReservation);
            $amount = $this->requestedAmount($validated, $balance);

            $payment = Payment::create([
                'tenant_id' => $lockedReservation->tenant_id,
                'reservation_id' => $lockedReservation->id,
                'user_id' => $lockedReservation->user_id,
                'amount' => $amount,
                'currency' => $this->reservationCurrency($lockedReservation),
                'payment_method' => PaymentMethod::CASH,
                'status' => PaymentStatus::COMPLETED,
                'processed_at' => $this->processedAt($validated),
                'notes' => $validated['notes'] ?? null,
                'gateway_data' => [
                    'cash_source' => [
                        'type' => 'reservation',
                        'id' => $lockedReservation->id,
                    ],
                    'collected_by_user_id' => $user->id,
                ],
            ]);

            $this->storeAttachments($payment, $attachments, $validated['attachment_temp_folders'] ?? []);

            $remaining = round(max(0, $balance - $amount), 2);
            if ($remaining <= 0) {
                $lockedReservation->forceFill([
                    'status' => $lockedReservation->contract
                        ? ReservationStatus::COMPLETED
                        : ReservationStatus::COMPLETED_WAIT_CONTRACT,
                ])->save();

                $this->rentalStatusSyncService->syncCarsByIds([$lockedReservation->car_id]);
            }

            $summary = $this->reservationPaymentSummary($lockedReservation->fresh(['payments.files']));
        });

        return response()->json([
            'status' => 'success',
            'message' => $this->message('Cash payment collected successfully.', 'تم تحصيل الدفعة النقدية بنجاح.'),
            'payment' => $this->paymentPayload($payment->fresh('files')),
            'reservation' => $summary,
        ], 201);
    }

    public function storeReturnReportPayment(Request $request, ContractReturnReport $contractReturnReport): JsonResponse
    {
        $user = $this->authorizeCashPaymentUser($request);
        $contractReturnReport->loadMissing(['tenant.subscriptionPlan', 'contract.reservation.car', 'reservation.payments.files', 'payment.files']);
        abort_unless($this->canAccessReturnReport($contractReturnReport, $user), 403);
        $this->ensureCashPaymentsFeature($contractReturnReport->tenant);

        return $this->collectReturnReportPayment($request, $contractReturnReport, $user);
    }

    public function storeContractReturnReportPayment(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeCashPaymentUser($request);
        $contract->loadMissing(['tenant.subscriptionPlan', 'reservation.car:id,branch_id', 'returnStatusReport']);
        abort_unless($this->canAccessContract($contract, $user), 403);
        $this->ensureCashPaymentsFeature($contract->tenant);

        $report = $contract->returnStatusReport;
        abort_unless($report, 404, 'Return status report was not found for this contract.');
        $report->loadMissing(['tenant.subscriptionPlan', 'contract.reservation.car', 'reservation.payments.files', 'payment.files']);

        return $this->collectReturnReportPayment($request, $report, $user);
    }

    private function collectReturnReportPayment(Request $request, ContractReturnReport $contractReturnReport, User $user): JsonResponse
    {
        $validated = $this->validatePaymentRequest($request);
        $attachments = $this->uploadedAttachments($request);
        $this->validateAttachments($attachments);

        $payment = null;
        $summary = [];

        DB::transaction(function () use ($contractReturnReport, $user, $validated, $attachments, &$payment, &$summary): void {
            $report = ContractReturnReport::query()
                ->with(['contract.reservation', 'reservation.payments'])
                ->whereKey($contractReturnReport->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balance = $this->returnReportBalance($report);
            $amount = $this->requestedAmount($validated, $balance);
            $processedAt = $this->processedAt($validated);

            $payment = $this->reusablePendingReturnReportPayment($report) ?? new Payment();
            $payment->forceFill([
                'tenant_id' => $report->tenant_id,
                'reservation_id' => $report->reservation_id,
                'user_id' => $report->reservation?->user_id,
                'amount' => $amount,
                'currency' => $this->contractCurrency($report),
                'payment_method' => PaymentMethod::CASH,
                'status' => PaymentStatus::COMPLETED,
                'processed_at' => $processedAt,
                'notes' => $validated['notes'] ?? trim(sprintf('Cash collection for return status report %s.', $report->report_number)),
                'gateway_data' => [
                    'cash_source' => [
                        'type' => 'contract_return_report',
                        'id' => $report->id,
                    ],
                    'contract_id' => $report->contract_id,
                    'collected_by_user_id' => $user->id,
                ],
            ])->save();

            $this->storeAttachments($payment, $attachments, $validated['attachment_temp_folders'] ?? []);

            $remaining = round(max(0, $balance - $amount), 2);
            $report->forceFill([
                'payment_id' => $payment->id,
                'payment_status' => $remaining <= 0 ? 'paid' : 'not_paid',
            ])->save();

            $summary = $this->returnReportPaymentSummary($report->fresh(['payment.files', 'reservation.payments.files']));
        });

        return response()->json([
            'status' => 'success',
            'message' => $this->message('Cash payment collected successfully.', 'تم تحصيل الدفعة النقدية بنجاح.'),
            'payment' => $this->paymentPayload($payment->fresh('files')),
            'return_status_report' => $summary,
        ], 201);
    }

    private function authorizeCashPaymentUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }

    /**
     * @return array{amount: mixed, notes?: string|null, collected_at?: string|null, attachment_temp_folders?: array<int, string>}
     */
    private function validatePaymentRequest(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'collected_at' => ['nullable', 'date'],
            'attachment_temp_folders' => ['nullable', 'array', 'max:10'],
            'attachment_temp_folders.*' => ['string'],
        ]);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedAttachments(Request $request): array
    {
        $files = $request->file('attachments', []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter(
            is_array($files) ? $files : [],
            static fn ($file): bool => $file instanceof UploadedFile
        ));
    }

    /**
     * @param array<int, UploadedFile> $attachments
     */
    private function validateAttachments(array $attachments): void
    {
        foreach ($attachments as $index => $attachment) {
            $validator = Validator::make(
                ['attachment' => $attachment],
                ['attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "attachments.$index" => $validator->errors()->first('attachment'),
                ]);
            }
        }
    }

    /**
     * @param array<int, UploadedFile> $attachments
     * @param array<int, string> $tempFolders
     */
    private function storeAttachments(Payment $payment, array $attachments, array $tempFolders): void
    {
        $disk = config('vilt-filepond.storage_disk', 'public');
        $order = 0;

        foreach ($attachments as $attachment) {
            $path = $attachment->store("cash-payments/{$payment->id}", $disk);

            $payment->files()->create([
                'original_name' => $attachment->getClientOriginalName(),
                'filename' => basename($path),
                'path' => $path,
                'mime_type' => $attachment->getMimeType(),
                'size' => (int) $attachment->getSize(),
                'collection' => self::ATTACHMENT_COLLECTION,
                'order' => $order++,
            ]);
        }

        foreach (array_values(array_filter($tempFolders)) as $folder) {
            $this->filePondService->moveTempFileToModel($payment, (string) $folder, self::ATTACHMENT_COLLECTION, $order++);
        }
    }

    private function reservationBalance(Reservation $reservation): float
    {
        $paid = $this->completedReservationPayments($reservation)
            ->reject(fn (Payment $payment): bool => $this->isReturnReportPayment($payment, $reservation))
            ->sum(fn (Payment $payment): float => (float) $payment->amount);

        return round(max(0, (float) $reservation->total_amount - (float) $paid), 2);
    }

    private function returnReportBalance(ContractReturnReport $report): float
    {
        $paid = $this->completedReservationPayments($report->reservation)
            ->filter(fn (Payment $payment): bool => $this->isPaymentForReturnReport($payment, $report))
            ->sum(fn (Payment $payment): float => (float) $payment->amount);

        return round(max(0, (float) $report->total_extra_charges - (float) $paid), 2);
    }

    private function completedReservationPayments(?Reservation $reservation)
    {
        if (!$reservation) {
            return collect();
        }

        $reservation->loadMissing(['payments', 'contract.returnStatusReport']);

        return $reservation->payments
            ->filter(fn (Payment $payment): bool => $this->paymentStatusValue($payment) === PaymentStatus::COMPLETED->value)
            ->values();
    }

    private function requestedAmount(array $validated, float $balance): float
    {
        if ($balance <= 0) {
            throw ValidationException::withMessages([
                'amount' => [$this->message('There is no remaining amount to collect.', 'لا يوجد مبلغ متبق للتحصيل.')],
            ]);
        }

        $amount = round((float) $validated['amount'], 2);

        if ($amount <= 0 || $amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => [$this->message('The cash amount must be greater than zero and not exceed the remaining balance.', 'يجب أن يكون مبلغ الكاش أكبر من صفر ولا يتجاوز المبلغ المتبقي.')],
            ]);
        }

        return $amount;
    }

    private function reusablePendingReturnReportPayment(ContractReturnReport $report): ?Payment
    {
        if (!$report->payment_id) {
            return null;
        }

        $payment = Payment::query()
            ->where('tenant_id', $report->tenant_id)
            ->whereKey($report->payment_id)
            ->first();

        if (!$payment || $this->paymentStatusValue($payment) !== PaymentStatus::PENDING->value) {
            return null;
        }

        return $payment;
    }

    private function isReturnReportPayment(Payment $payment, Reservation $reservation): bool
    {
        if ((string) data_get($payment->gateway_data, 'cash_source.type') === 'contract_return_report') {
            return true;
        }

        $returnPaymentIds = $reservation->contract?->returnStatusReport?->payment_id
            ? [(int) $reservation->contract->returnStatusReport->payment_id]
            : [];

        return in_array((int) $payment->id, $returnPaymentIds, true);
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

    private function reservationPaymentSummary(Reservation $reservation): array
    {
        $paid = $this->completedReservationPayments($reservation)
            ->reject(fn (Payment $payment): bool => $this->isReturnReportPayment($payment, $reservation))
            ->sum(fn (Payment $payment): float => (float) $payment->amount);
        $remaining = round(max(0, (float) $reservation->total_amount - (float) $paid), 2);

        return [
            'id' => $reservation->id,
            'total_amount' => (float) $reservation->total_amount,
            'paid_amount' => round((float) $paid, 2),
            'remaining_amount' => $remaining,
            'payment_status' => $remaining <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'not_paid'),
            'status' => $reservation->status instanceof ReservationStatus ? $reservation->status->value : (string) $reservation->status,
        ];
    }

    private function returnReportPaymentSummary(ContractReturnReport $report): array
    {
        $paid = $this->completedReservationPayments($report->reservation)
            ->filter(fn (Payment $payment): bool => $this->isPaymentForReturnReport($payment, $report))
            ->sum(fn (Payment $payment): float => (float) $payment->amount);
        $remaining = round(max(0, (float) $report->total_extra_charges - (float) $paid), 2);

        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'total_amount' => (float) $report->total_extra_charges,
            'paid_amount' => round((float) $paid, 2),
            'remaining_amount' => $remaining,
            'payment_status' => $remaining <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'not_paid'),
            'payment_id' => $report->payment_id,
        ];
    }

    private function paymentPayload(Payment $payment): array
    {
        $payment->loadMissing('files');

        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'payment_method' => $payment->payment_method instanceof PaymentMethod ? $payment->payment_method->value : (string) $payment->payment_method,
            'status' => $this->paymentStatusValue($payment),
            'processed_at' => optional($payment->processed_at)->toIso8601String(),
            'notes' => $payment->notes,
            'attachments' => $payment->files
                ->where('collection', self::ATTACHMENT_COLLECTION)
                ->values()
                ->map(fn ($file): array => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => (int) $file->size,
                    'url' => $this->fileUrl((string) $file->path),
                ])
                ->all(),
        ];
    }

    private function paymentStatusValue(Payment $payment): string
    {
        return $payment->status instanceof PaymentStatus ? $payment->status->value : (string) $payment->status;
    }

    private function processedAt(array $validated): Carbon
    {
        return !empty($validated['collected_at'])
            ? Carbon::parse((string) $validated['collected_at'])
            : now();
    }

    private function reservationCurrency(Reservation $reservation): string
    {
        return CurrencyCatalog::normalizeCode($reservation->contract?->currency, CurrencyCatalog::codeForTenantId($reservation->tenant_id));
    }

    private function contractCurrency(ContractReturnReport $report): string
    {
        return CurrencyCatalog::normalizeCode(
            $report->contract?->currency,
            CurrencyCatalog::codeForTenantId($report->tenant_id ?? $report->contract?->tenant_id)
        );
    }

    private function canAccessReservation(Reservation $reservation, User $user): bool
    {
        $reservation->loadMissing('car:id,branch_id');

        return $this->branchAccess->canAccessBranchId($user, $reservation->car?->branch_id ? (int) $reservation->car->branch_id : null);
    }

    private function canAccessReturnReport(ContractReturnReport $report, User $user): bool
    {
        $report->loadMissing('contract.reservation.car:id,branch_id');
        $branchId = $report->branch_id
            ? (int) $report->branch_id
            : ($report->contract?->reservation?->car?->branch_id ? (int) $report->contract->reservation->car->branch_id : null);

        return $this->branchAccess->canAccessBranchId($user, $branchId);
    }

    private function canAccessContract(Contract $contract, User $user): bool
    {
        $contract->loadMissing('reservation.car:id,branch_id');
        $branchId = $contract->branch_id
            ? (int) $contract->branch_id
            : ($contract->reservation?->car?->branch_id ? (int) $contract->reservation->car->branch_id : null);

        return $this->branchAccess->canAccessBranchId($user, $branchId);
    }

    private function ensureCashPaymentsFeature($tenant): void
    {
        if (!$tenant) {
            return;
        }

        $tenant->loadMissing('subscriptionPlan');
        abort_unless($tenant->supportsFeature('cash_payments'), 403, 'This feature is not included in your current plan.');
    }

    private function fileUrl(string $path): string
    {
        $normalized = ltrim(preg_replace('/^storage\//', '', $path) ?: $path, '/');

        return Storage::disk(config('vilt-filepond.storage_disk', 'public'))->url($normalized);
    }

    private function message(string $en, string $ar): string
    {
        $locale = strtolower(substr((string) request()->header('Accept-Language', app()->getLocale()), 0, 2));

        return $locale === 'ar' ? $ar : $en;
    }
}
