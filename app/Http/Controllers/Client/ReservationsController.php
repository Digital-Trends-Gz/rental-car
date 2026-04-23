<?php

namespace App\Http\Controllers\Client;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalExtensionRequestStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RentalExtensionRequest;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationsController extends Controller
{
    public function index(Request $request)
    {

        $reservations = Reservation::where('user_id', auth()->user()->id)
            ->with('car')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $extensionRequests = RentalExtensionRequest::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('status', RentalExtensionRequestStatus::PENDING)
            ->whereHas('reservation', fn ($query) => $query->where('user_id', auth()->id()))
            ->with([
                'reservation.car:id,year,make,model,license_plate',
                'contract:id,contract_number',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RentalExtensionRequest $request) => [
                'id' => $request->id,
                'reservation_id' => $request->reservation_id,
                'reservation_number' => $request->reservation?->reservation_number,
                'contract_number' => $request->contract?->contract_number,
                'car_name' => $request->reservation?->car
                    ? trim(sprintf(
                        '%s %s %s',
                        (string) ($request->reservation->car->year ?? ''),
                        (string) ($request->reservation->car->make ?? ''),
                        (string) ($request->reservation->car->model ?? '')
                    ))
                    : null,
                'license_plate' => $request->reservation?->car?->license_plate,
                'new_end_date' => optional($request->new_end_date)?->toDateString(),
                'extra_days' => $request->extra_days,
                'extra_amount' => (float) $request->extra_amount,
                'reason' => $request->reason,
                'status' => $request->status instanceof RentalExtensionRequestStatus ? $request->status->value : (string) $request->status,
                'status_label' => $request->status instanceof RentalExtensionRequestStatus ? $request->status->label() : ucfirst((string) $request->status),
                'approve_url' => route('client.reservations.extension-requests.approve', [
                    'subdomain' => request()->route('subdomain'),
                    'reservation' => $request->reservation_id,
                    'extensionRequest' => $request->id,
                ]),
                'reject_url' => route('client.reservations.extension-requests.reject', [
                    'subdomain' => request()->route('subdomain'),
                    'reservation' => $request->reservation_id,
                    'extensionRequest' => $request->id,
                ]),
            ])
            ->values();

        return inertia('Client/Reservations/Index', [
            'reservations' => $reservations,
            'extensionRequests' => $extensionRequests,
        ]);
    }

    public function show($id)
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);
        $reservation->load(['user', 'car', 'payments']);

        return inertia('Client/Reservations/Show', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
        ]);
    }

    public function print($id)
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);
        $reservation->load(['user', 'car', 'payments']);

        $pdf = Pdf::loadView('admin.reservations.print', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
            'currency' => config('app.currency_symbol'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($reservation->reservation_number . '.pdf');
    }

    public function approveExtensionRequest(Request $request): RedirectResponse
    {
        $reservationId = (int) $request->route('reservation');
        $extensionRequestId = (int) $request->route('extensionRequest');
        $reservation = Reservation::withoutGlobalScope('tenant')->findOrFail($reservationId);
        $extensionRequest = RentalExtensionRequest::withoutGlobalScope('tenant')->findOrFail($extensionRequestId);

        abort_unless($reservation->user_id === $request->user()->id, 403);
        abort_unless($extensionRequest->reservation_id === $reservation->id, 404);

        if ($extensionRequest->status !== RentalExtensionRequestStatus::PENDING) {
            throw ValidationException::withMessages([
                'extension_request' => 'This extension request is no longer pending.',
            ]);
        }

        DB::transaction(function () use ($extensionRequest, $request, $reservation): void {
            $contract = $extensionRequest->contract()->with('reservation')->firstOrFail();
            $currentEndDate = $contract->end_date
                ? CarbonImmutable::parse($contract->end_date->toDateString())
                : null;
            if (!$currentEndDate) {
                throw ValidationException::withMessages([
                    'extension_request' => 'The contract no longer has an end date.',
                ]);
            }

            $newEndDate = CarbonImmutable::parse($extensionRequest->new_end_date)->startOfDay();
            if ($newEndDate->lessThanOrEqualTo($currentEndDate)) {
                throw ValidationException::withMessages([
                    'extension_request' => 'The requested end date is not valid anymore.',
                ]);
            }

            $extraAmount = (float) $extensionRequest->extra_amount;
            $baseContractTotal = (float) ($contract->total_amount ?? 0);
            $baseReservationTotal = (float) ($reservation->total_amount ?? 0);

            $contract->end_date = $newEndDate->toDateString();
            $contract->total_amount = round($baseContractTotal + $extraAmount, 2);
            $contract->save();

            $reservation->end_date = $newEndDate->toDateString();
            $reservation->total_days = $reservation->start_date
                ? $reservation->start_date->diffInDays($newEndDate) + 1
                : $reservation->total_days;
            $reservation->subtotal = round((float) ($reservation->subtotal ?? 0) + $extraAmount, 2);
            $reservation->total_amount = round($baseReservationTotal + $extraAmount, 2);
            $reservation->save();

            $extensionRequest->status = RentalExtensionRequestStatus::APPROVED;
            $extensionRequest->responded_by_user_id = $request->user()->id;
            $extensionRequest->responded_at = now();
            $extensionRequest->approved_at = now();
            $extensionRequest->save();

            Payment::create([
                'tenant_id' => $reservation->tenant_id,
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'amount' => number_format($extraAmount, 2, '.', ''),
                'currency' => strtoupper((string) config('app.currency_code', 'USD')),
                'payment_method' => PaymentMethod::CASH,
                'status' => PaymentStatus::PENDING,
                'notes' => 'Rental extension approved by client. Payment pending collection.',
            ]);
        });

        return redirect()
            ->route('client.reservations.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', 'Extension request approved.');
    }

    public function rejectExtensionRequest(Request $request): RedirectResponse
    {
        $reservationId = (int) $request->route('reservation');
        $extensionRequestId = (int) $request->route('extensionRequest');
        $reservation = Reservation::withoutGlobalScope('tenant')->findOrFail($reservationId);
        $extensionRequest = RentalExtensionRequest::withoutGlobalScope('tenant')->findOrFail($extensionRequestId);

        abort_unless($reservation->user_id === $request->user()->id, 403);
        abort_unless($extensionRequest->reservation_id === $reservation->id, 404);

        if ($extensionRequest->status !== RentalExtensionRequestStatus::PENDING) {
            throw ValidationException::withMessages([
                'extension_request' => 'This extension request is no longer pending.',
            ]);
        }

        $extensionRequest->status = RentalExtensionRequestStatus::REJECTED;
        $extensionRequest->responded_by_user_id = $request->user()->id;
        $extensionRequest->responded_at = now();
        $extensionRequest->rejected_at = now();
        $extensionRequest->save();

        return redirect()
            ->route('client.reservations.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', 'Extension request rejected.');
    }
}
