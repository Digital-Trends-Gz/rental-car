<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Enums\ReservationStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReservationsController extends Controller
{
    public function index(Request $request)
    {
        $statusMeta = ReservationStatus::getMeta();
        $statusMeta = array_values(array_filter(
            $statusMeta,
            fn (array $meta) => $meta['value'] !== ReservationStatus::AWAITING_PAYMENT->value
        ));
        $statusValues = array_column($statusMeta, 'value');

        $reservations = Reservation::with(['car', 'user', 'tenant'])
            ->whereIn('status', ReservationStatus::realBookingValues())
            ->when($request->search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('reservation_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function($qu) use ($search) {
                          $qu->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('car', function($qc) use ($search) {
                          $qc->where('license_plate', 'like', "%{$search}%");
                      });
                });
            })
            ->when($request->status && in_array($request->status, $statusValues), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Get status counts for the filter
        $statuses = collect($statusMeta)->mapWithKeys(function($meta) {
            return [
                $meta['value'] => [
                    'label' => $meta['label'],
                    'count' => Reservation::where('status', $meta['value'])->count(),
                    'color' => $meta['color']
                ]
            ];
        });

        // Get currency info (could be centralized later)
        $currency = [
            'symbol' => config('app.currency_symbol', '$'),
            'code' => config('app.currency_code', 'USD')
        ];

        return Inertia::render('SuperAdmin/Reservations/Index', [
            'reservations' => $reservations,
            'filters' => $request->only(['search', 'status']),
            'statuses' => $statuses,
            'currency' => $currency
        ]);
    }
}
