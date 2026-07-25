<?php

namespace App\Http\Controllers;

use App\Core\ReservationSettings;
use App\Core\TenantContext;
use App\Enums\CarStatus;
use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\CarDiscount;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Support\Payments\MyFatoorahSubscriptionProvider;
use App\Support\ClientReturnDebt;
use App\Support\CurrencyCatalog;
use App\Support\TenantSeoResolver;
use App\Support\TenantStripeConnect;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

class BookingController extends Controller
{
    private function isWebsiteBookableCarStatus(CarStatus $status): bool
    {
        return in_array($status, [
            CarStatus::AVAILABLE,
            CarStatus::RESERVED,
            CarStatus::RENTED,
        ], true);
    }

    public function show(Request $request, Car $car)
    {
        $tenantSlug = $this->tenantSlug();
        $tenant = TenantContext::get();
        $tenantId = TenantContext::id();
        $today = now()->startOfDay();

        $request->validate([
            'window_start' => ['nullable', 'date_format:Y-m-d'],
        ]);

        // Check if car is available for booking
        if (!$this->isWebsiteBookableCarStatus($car->status)) {
            return redirect()->route('tenant.fleet', ['subdomain' => $tenantSlug])->with('error', 'This car is not available for booking.');
        }

        $now = now();
        $hasCoupons = Coupon::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->exists();

        $selectedWindowStart = $request->string('window_start')->toString();
        $windowStart = $selectedWindowStart !== ''
            ? Carbon::createFromFormat('Y-m-d', $selectedWindowStart)->startOfDay()
            : $today->copy();
        $windowEnd = $windowStart->copy()->addDays(29)->endOfDay();

        $windowMeta = [
            'starts_at' => $windowStart->toDateString(),
            'ends_at' => $windowEnd->toDateString(),
            'label' => sprintf('%s - %s', $windowStart->translatedFormat('M j, Y'), $windowEnd->translatedFormat('M j, Y')),
            'previous' => $windowStart->copy()->subDays(30)->toDateString(),
            'next' => $windowStart->copy()->addDays(30)->toDateString(),
        ];

        $blockedRanges = Reservation::query()
            ->where('car_id', $car->id)
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
            ])
            ->whereDate('start_date', '<=', $windowEnd->toDateString())
            ->whereDate('end_date', '>=', $windowStart->toDateString())
            ->orderBy('start_date')
            ->get(['start_date', 'end_date'])
            ->map(static function (Reservation $reservation) {
                return [
                    'start_date' => optional($reservation->start_date)->toDateString(),
                    'end_date' => optional($reservation->end_date)->toDateString(),
                ];
            })
            ->values();

        return inertia('Booking', [
            'car' => $car,
            'hasCoupons' => $hasCoupons,
            'seo' => TenantSeoResolver::forCar($tenant, $car),
            'availabilityCalendar' => [
                'window_starts_at' => $windowStart->toDateString(),
                'window_ends_at' => $windowEnd->toDateString(),
                'today' => $today->toDateString(),
                'window' => $windowMeta,
                'blocked_ranges' => $blockedRanges,
            ],
            'couponPreviewUrl' => route('tenant.fleet.coupon.preview', [
                'subdomain' => $tenantSlug,
                'car' => $car->id,
            ]),
        ]);
    }

    public function previewCoupon(Car $car, Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'return_location' => 'nullable|string|max:255',
            'coupon_code' => 'nullable|string|max:100',
        ]);

        $tenant = TenantContext::get();
        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'message' => 'Tenant context not found.',
            ], 422);
        }

        $startDate = Carbon::parse((string) $request->input('start_date'));
        $endDate = Carbon::parse((string) $request->input('end_date'));
        $pricing = $this->calculateBookingTotals(
            $car,
            $startDate,
            $endDate,
            $tenant,
            (string) $request->input('return_location', '')
        );
        $subtotal = (float) $pricing['subtotal'];
        $taxAmount = (float) $pricing['tax_amount'];
        $returnLocationFee = (float) $pricing['return_location_fee'];

        [$autoDiscount, $autoDiscountAmount] = $this->resolveAutoDiscountForBooking(
            $tenant,
            $car,
            $startDate,
            $endDate,
            $subtotal
        );

        $coupon = null;
        $couponError = null;
        $couponDiscount = 0.0;
        $couponCode = strtoupper(trim((string) $request->input('coupon_code', '')));
        if ($couponCode !== '') {
            [$coupon, $couponError] = $this->resolveCouponForBooking(
                $tenant,
                $car,
                $couponCode,
                $startDate,
                $endDate,
                max(0, $subtotal - $autoDiscountAmount)
            );

            if (!$coupon) {
                return response()->json([
                    'ok' => false,
                    'message' => $couponError ?: 'Invalid coupon code.',
                ], 422);
            }

            $couponDiscount = $this->calculateCouponDiscount($coupon, max(0, $subtotal - $autoDiscountAmount));
        }

        $totalDiscount = min($subtotal, $autoDiscountAmount + $couponDiscount);
        $totalBeforeDiscount = $subtotal + $taxAmount + $returnLocationFee;
        $totalAfterDiscount = max(0, $totalBeforeDiscount - $totalDiscount);

        return response()->json([
            'ok' => true,
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
            ] : null,
            'auto_discount' => $autoDiscount ? [
                'id' => $autoDiscount->id,
                'name' => $autoDiscount->name,
            ] : null,
            'amounts' => [
                'days' => $pricing['days'],
                'daily_rate' => $pricing['daily_rate'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'return_location_fee' => $returnLocationFee,
                'auto_discount_amount' => $autoDiscountAmount,
                'coupon_discount_amount' => $couponDiscount,
                'discount_amount' => $totalDiscount,
                'total_before_discount' => $totalBeforeDiscount,
                'total_after_discount' => $totalAfterDiscount,
            ],
        ]);
    }

    public function book(Car $car, Request $request, TenantStripeConnect $stripeConnect)
    {
        $tenantSlug = $this->tenantSlug();
        $tenant = TenantContext::get();

        // check car is available for booking
        if (!$this->isWebsiteBookableCarStatus($car->status)) {
            return redirect()->route('tenant.fleet', ['subdomain' => $tenantSlug])->with('error', 'This car is not available for booking.');
        }

        // check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('tenant.login', ['subdomain' => $tenantSlug])->with('error', 'You must be logged in to book a car.');
        }

        // form validation
        $request->validate([
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after:start_date',
            'pickup_location'  => 'required|string|max:255',
            'return_location'  => 'required|string|max:255',
            'coupon_code'      => 'nullable|string|max:100',
        ]);

        $outstandingReturnDebt = ClientReturnDebt::outstandingTotal((int) TenantContext::id(), (int) Auth::id());
        if ($outstandingReturnDebt > 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_date' => ClientReturnDebt::blockingMessage($outstandingReturnDebt),
                ]);
        }

        // convert dates to Carbon
        $startDate = Carbon::parse($request->start_date);
        $endDate   = Carbon::parse($request->end_date);

        // Block booking if the car already has an overlapping reservation in this period.
        $hasDateConflict = Reservation::query()
            ->where('car_id', $car->id)
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
            ])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_date', '<=', $endDate->toDateString())
                    ->whereDate('end_date', '>=', $startDate->toDateString());
            })
            ->exists();

        if ($hasDateConflict) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_date' => 'This car is not available for the selected dates.',
                    'end_date' => 'Please choose another date range.',
                ]);
        }

        $pricing = $this->calculateBookingTotals(
            $car,
            $startDate,
            $endDate,
            $tenant,
            $request->return_location
        );
        $subtotal = (float) $pricing['subtotal'];
        $taxAmount = (float) $pricing['tax_amount'];
        $returnLocationFee = (float) $pricing['return_location_fee'];
        $days = (int) $pricing['days'];
        $dailyRate = (float) $pricing['daily_rate'];
        $autoDiscount = null;
        $autoDiscountAmount = 0.0;
        $coupon = null;
        $couponDiscountAmount = 0.0;
        $discount = 0.0;

        if ($tenant) {
            [$autoDiscount, $autoDiscountAmount] = $this->resolveAutoDiscountForBooking(
                $tenant,
                $car,
                $startDate,
                $endDate,
                $subtotal
            );
        }

        $couponCode = strtoupper(trim((string) $request->input('coupon_code', '')));
        if ($couponCode !== '') {
            if (!$tenant) {
                return back()->withInput()->withErrors([
                    'coupon_code' => 'Coupon cannot be validated for this tenant.',
                ]);
            }

            [$coupon, $couponError] = $this->resolveCouponForBooking(
                $tenant,
                $car,
                $couponCode,
                $startDate,
                $endDate,
                max(0, $subtotal - $autoDiscountAmount)
            );
            if (!$coupon) {
                return back()->withInput()->withErrors([
                    'coupon_code' => $couponError ?: 'Invalid coupon code.',
                ]);
            }

            $couponDiscountAmount = $this->calculateCouponDiscount($coupon, max(0, $subtotal - $autoDiscountAmount));
        }

        $discount = min($subtotal, $autoDiscountAmount + $couponDiscountAmount);
        $total = max(0, $subtotal + $taxAmount + $returnLocationFee - $discount);
        $providerCode = $this->resolveTenantBookingProvider($tenant, $stripeConnect);
        $reservation = null;
        $payment = null;

        DB::transaction(function () use (
            &$reservation,
            &$payment,
            $car,
            $startDate,
            $endDate,
            $request,
            $days,
            $dailyRate,
            $subtotal,
            $taxAmount,
            $returnLocationFee,
            $autoDiscount,
            $autoDiscountAmount,
            $couponDiscountAmount,
            $discount,
            $total,
            $coupon,
            $couponCode,
            $providerCode
        ) {
            $reservation = Reservation::create([
                'car_id' => $car->id,
                'user_id' => Auth::id(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'pickup_location' => $request->pickup_location,
                'return_location' => $request->return_location,
                'return_location_fee' => $returnLocationFee,
                'total_days' => $days,
                'daily_rate' => $dailyRate,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discount,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code ?? ($couponCode !== '' ? $couponCode : null),
                'auto_discount_id' => $autoDiscount?->id,
                'auto_discount_amount' => $autoDiscountAmount,
                'total_amount' => $total,
                'status' => ReservationStatus::PENDING->value,
            ]);

            if ($coupon) {
                CouponRedemption::create([
                    'tenant_id' => $reservation->tenant_id,
                    'coupon_id' => $coupon->id,
                    'reservation_id' => $reservation->id,
                    'user_id' => Auth::id(),
                    'code' => $coupon->code,
                    'discount_amount' => $couponDiscountAmount,
                    'subtotal_amount' => $subtotal,
                    'total_before_discount' => $subtotal + $taxAmount,
                    'total_after_discount' => max(0, $subtotal + $taxAmount - $couponDiscountAmount),
                    'meta' => [
                        'car_id' => $car->id,
                    ],
                    'redeemed_at' => now(),
                ]);

                $coupon->increment('used_count');
            }

            $payment = Payment::firstOrCreate(
                [
                    'reservation_id' => $reservation->id,
                    'user_id' => Auth::id(),
                ],
                [
                    'tenant_id' => TenantContext::id(),
                    'amount' => $total,
                    'currency' => $this->bookingCurrency(),
                    'payment_method' => $this->mapBookingProviderToPaymentMethod($providerCode),
                    'status' => PaymentStatus::PENDING,
                    'notes' => 'Booking checkout initiated',
                    'gateway_data' => [
                        'provider_code' => $providerCode,
                        'auto_discount_name' => $autoDiscount?->name,
                        'auto_discount_amount' => $autoDiscountAmount,
                        'coupon_code' => $coupon?->code,
                        'coupon_discount_amount' => $couponDiscountAmount,
                        'discount_amount' => $discount,
                    ],
                ]
            );

            if (!$payment->wasRecentlyCreated) {
                $payment->forceFill([
                    'tenant_id' => TenantContext::id(),
                    'amount' => $total,
                    'payment_method' => $this->mapBookingProviderToPaymentMethod($providerCode),
                    'gateway_data' => array_merge((array) $payment->gateway_data, [
                        'provider_code' => $providerCode,
                        'auto_discount_name' => $autoDiscount?->name,
                        'auto_discount_amount' => $autoDiscountAmount,
                        'coupon_code' => $coupon?->code,
                        'coupon_discount_amount' => $couponDiscountAmount,
                        'discount_amount' => $discount,
                    ]),
                ])->save();
            }
        });

        if ($providerCode) {
            return redirect()->route('tenant.booking.checkout', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ]);
        }

        // $car->update([
        //     'status' => CarStatus::RESERVED,
        // ]);

        return redirect()->route('tenant.booking.confirmation', [
            'subdomain' => $tenantSlug,
            'reservation' => $reservation,
        ])->with('warning', $payment->exists ? 'Online payment is not available for this tenant yet. Reservation was created as pending.' : null);
    }

    public function checkout(Reservation $reservation, Request $request, TenantStripeConnect $stripeConnect, MyFatoorahSubscriptionProvider $myFatoorah)
    {
        $tenantSlug = $this->tenantSlug();

        if (!Auth::check()) {
            return redirect()->route('tenant.login', ['subdomain' => $tenantSlug]);
        }

        if ($reservation->user_id !== Auth::id()) {
            return redirect()->route('tenant.fleet', ['subdomain' => $tenantSlug]);
        }

        $tenant = TenantContext::get();
        if (!$tenant) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Tenant payment gateway is not connected yet.');
        }

        $payment = Payment::query()
            ->where('reservation_id', $reservation->id)
            ->where('user_id', Auth::id())
            ->latest('id')
            ->first();

        if (!$payment) {
            $payment = Payment::create([
                'tenant_id' => TenantContext::id(),
                'reservation_id' => $reservation->id,
                'user_id' => Auth::id(),
                'amount' => (float) $reservation->total_amount,
                'currency' => $this->bookingCurrency($tenant->stripe_currency),
                'payment_method' => $this->mapBookingProviderToPaymentMethod($this->resolveTenantBookingProvider($tenant, $stripeConnect)),
                'status' => PaymentStatus::PENDING,
                'notes' => 'Booking checkout session created',
                'gateway_data' => [
                    'provider_code' => $this->resolveTenantBookingProvider($tenant, $stripeConnect),
                    'auto_discount_amount' => (float) $reservation->auto_discount_amount,
                    'coupon_code' => $reservation->coupon_code,
                    'coupon_discount_amount' => max(0, (float) $reservation->discount_amount - (float) $reservation->auto_discount_amount),
                    'discount_amount' => (float) $reservation->discount_amount,
                ],
            ]);
        }

        if ($payment->status === PaymentStatus::COMPLETED) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ]);
        }

        $availableProviders = $this->availableBookingProviders($tenant, $stripeConnect);
        $requestedProvider = strtolower(trim((string) $request->query('provider', '')));

        if ($requestedProvider === '' && count($availableProviders) > 1) {
            return Inertia::render('BookingCheckout', [
                'reservation' => [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'total_amount' => (float) $reservation->total_amount,
                    'currency' => $this->bookingCurrency($tenant->stripe_currency),
                    'car' => [
                        'make' => $reservation->car?->make,
                        'model' => $reservation->car?->model,
                        'year' => $reservation->car?->year,
                        'image_url' => $reservation->car?->image_url,
                    ],
                ],
                'providers' => $availableProviders,
                'selectedProvider' => strtolower(trim((string) (data_get($payment->gateway_data, 'provider_code') ?: $availableProviders[0]['code'] ?? ''))),
                'actions' => [
                    'checkout' => route('tenant.booking.checkout', [
                        'subdomain' => $tenantSlug,
                        'reservation' => $reservation->id,
                    ]),
                    'confirmation' => route('tenant.booking.confirmation', [
                        'subdomain' => $tenantSlug,
                        'reservation' => $reservation->id,
                    ]),
                ],
                'seo' => TenantSeoResolver::forReservation($tenant, $reservation->loadMissing('car', 'user'), 'booking_checkout'),
            ]);
        }

        $providerCode = in_array($requestedProvider, array_column($availableProviders, 'code'), true)
            ? $requestedProvider
            : $this->resolveBookingProviderForPayment($payment, $tenant, $stripeConnect);
        if (!$providerCode) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Tenant payment gateway is not configured for this booking.');
        }

        $payment->forceFill([
            'payment_method' => $this->mapBookingProviderToPaymentMethod($providerCode),
            'gateway_data' => array_merge((array) $payment->gateway_data, [
                'provider_code' => $providerCode,
            ]),
        ])->save();

        if ($providerCode === 'myfatoorah') {
            return $this->startMyFatoorahBookingCheckout($reservation, $payment, $tenant, $tenantSlug, $myFatoorah);
        }

        if (!$stripeConnect->canAcceptCheckout($tenant)) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Tenant Stripe Connect is not ready yet.');
        }

        try {
            $stripe = $stripeConnect->client();
            $amountMinor = (int) round(((float) $reservation->total_amount) * 100);
            $currency = strtolower($this->bookingCurrency($tenant->stripe_currency));

            $session = $stripe->checkout->sessions->create(
                [
                    'mode' => 'payment',
                    'payment_method_types' => ['card'],
                    'client_reference_id' => (string) $reservation->id,
                    'customer_email' => (string) (Auth::user()?->email ?? ''),
                    'success_url' => route('tenant.booking.payment.success', [
                        'subdomain' => $tenantSlug,
                        'reservation' => $reservation->id,
                    ]).'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('tenant.booking.payment.cancel', [
                        'subdomain' => $tenantSlug,
                        'reservation' => $reservation->id,
                    ]),
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => max(50, $amountMinor),
                            'product_data' => [
                                'name' => sprintf('Car Booking %s', $reservation->reservation_number),
                                'description' => sprintf(
                                    '%s %s %s (%s)',
                                    $reservation->car?->year ?? '',
                                    $reservation->car?->make ?? '',
                                    $reservation->car?->model ?? '',
                                    $reservation->car?->license_plate ?? ''
                                ),
                            ],
                        ],
                    ]],
                    'metadata' => [
                        'tenant_id' => (string) $tenant->id,
                        'tenant_slug' => (string) $tenant->slug,
                        'reservation_id' => (string) $reservation->id,
                        'payment_id' => (string) $payment->id,
                        'user_id' => (string) Auth::id(),
                    ],
                ],
                [
                    'stripe_account' => $tenant->stripe_account_id,
                ]
            );

            $payment->forceFill([
                'status' => PaymentStatus::PENDING,
                'payment_method' => PaymentMethod::STRIPE,
                'gateway_response' => 'stripe_checkout_created',
                'gateway_data' => array_merge((array) $payment->gateway_data, [
                    'provider_code' => 'stripe',
                    'checkout_session_id' => (string) $session->id,
                    'checkout_mode' => 'payment',
                    'stripe_account_id' => $tenant->stripe_account_id,
                ]),
            ])->save();

            return Inertia::location((string) $session->url);
        } catch (Throwable $e) {
            Log::error('Booking Stripe checkout creation failed', [
                'tenant_id' => $tenant?->id,
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Could not create Stripe checkout session for this booking.');
        }
    }

    public function paymentSuccess(Reservation $reservation, Request $request, TenantStripeConnect $stripeConnect, MyFatoorahSubscriptionProvider $myFatoorah)
    {
        $tenantSlug = $this->tenantSlug();

        if (!Auth::check() || $reservation->user_id !== Auth::id()) {
            return redirect()->route('tenant.login', ['subdomain' => $tenantSlug]);
        }

        $tenant = TenantContext::get();
        if (!$tenant) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Payment verification is not available.');
        }

        $payment = Payment::query()
            ->where('reservation_id', $reservation->id)
            ->where('user_id', Auth::id())
            ->latest('id')
            ->first();

        if (!$payment) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Booking payment record was not found.');
        }

        $providerCode = strtolower(trim((string) (
            $request->query('provider')
            ?: data_get($payment->gateway_data, 'provider_code')
            ?: 'stripe'
        )));

        if ($providerCode === 'myfatoorah') {
            return $this->handleMyFatoorahBookingSuccess($reservation, $payment, $tenantSlug, $tenant, $request, $myFatoorah);
        }

        if (!$tenant->stripe_account_id || !$stripeConnect->isConfigured()) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Stripe verification is not available.');
        }

        $sessionId = $this->requestQueryValue($request, 'session_id') ?? '';
        if ($sessionId === '') {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Missing Stripe checkout session.');
        }

        try {
            $session = $stripeConnect->client()->checkout->sessions->retrieve(
                $sessionId,
                ['expand' => ['payment_intent']],
                ['stripe_account' => $tenant->stripe_account_id]
            );

            $paymentStatus = (string) ($session->payment_status ?? '');
            $checkoutStatus = (string) ($session->status ?? '');

            $gatewayData = array_merge((array) $payment->gateway_data, [
                'checkout_session_id' => (string) ($session->id ?? $sessionId),
                'payment_status' => $paymentStatus,
                'checkout_status' => $checkoutStatus,
            ]);

            if ($checkoutStatus === 'complete' && $paymentStatus === 'paid') {
                $payment->forceFill([
                    'status' => PaymentStatus::COMPLETED,
                    'payment_method' => PaymentMethod::STRIPE,
                    'transaction_id' => $this->extractTransactionId($session),
                    'gateway_response' => 'paid',
                    'gateway_data' => $gatewayData,
                    'processed_at' => now(),
                ])->save();

                if ($reservation->status === ReservationStatus::PENDING) {
                    $reservation->update([
                        'status' => ReservationStatus::CONFIRMED,
                    ]);
                }

                return redirect()->route('tenant.booking.confirmation', [
                    'subdomain' => $tenantSlug,
                    'reservation' => $reservation,
                ])->with('success', 'Payment completed successfully.');
            }

            $payment->forceFill([
                'status' => PaymentStatus::PENDING,
                'gateway_response' => $paymentStatus !== '' ? $paymentStatus : 'pending',
                'gateway_data' => $gatewayData,
            ])->save();

            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('warning', 'Payment is not completed yet.');
        } catch (Throwable $e) {
            Log::error('Booking Stripe checkout verification failed', [
                'tenant_id' => $tenant->id,
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Could not verify booking payment session.');
        }
    }

    public function paymentCancel(Reservation $reservation, Request $request)
    {
        $tenantSlug = $this->tenantSlug();

        if (!Auth::check() || $reservation->user_id !== Auth::id()) {
            return redirect()->route('tenant.login', ['subdomain' => $tenantSlug]);
        }

        $payment = Payment::query()
            ->where('reservation_id', $reservation->id)
            ->where('user_id', Auth::id())
            ->latest('id')
            ->first();

        if ($payment && $payment->status === PaymentStatus::PENDING) {
            $providerCode = strtolower(trim((string) (
                $request->query('provider')
                ?: data_get($payment->gateway_data, 'provider_code')
                ?: 'stripe'
            )));
            $payment->forceFill([
                'gateway_response' => $providerCode.'_cancelled',
                'payment_method' => $this->mapBookingProviderToPaymentMethod($providerCode),
                'gateway_data' => array_merge((array) $payment->gateway_data, [
                    'provider_code' => $providerCode,
                    'cancel_query' => $request->query(),
                ]),
            ])->save();
        }

        $providerLabel = strtolower((string) $request->query('provider', '')) === 'myfatoorah' ? 'MyFatoorah' : 'Stripe';

        return redirect()->route('tenant.booking.confirmation', [
            'subdomain' => $tenantSlug,
            'reservation' => $reservation,
        ])->with('warning', $providerLabel.' checkout was cancelled.');
    }

    public function confirmation(Reservation $reservation)
    {
        $tenantSlug = $this->tenantSlug();
        $tenant = TenantContext::get();

        // Make sure user can only see their own reservations
        if (!Auth::check() || $reservation->user_id !== Auth::user()->id) {
            return redirect()->route('tenant.fleet', ['subdomain' => $tenantSlug]);
        }

        $reservation->load(['car', 'user']);

        return inertia('BookingConfirmation', [
            'reservation' => $reservation,
            'seo' => TenantSeoResolver::forReservation($tenant, $reservation, 'booking_confirmation'),
        ]);
    }

    private function tenantSlug(): ?string
    {
        return TenantContext::get()?->slug;
    }

    private function tenantHasConnectCheckoutReady(TenantStripeConnect $stripeConnect): bool
    {
        $tenant = TenantContext::get();

        return $tenant ? $stripeConnect->canAcceptCheckout($tenant) : false;
    }

    private function tenantPaymentGatewaySettings(Tenant $tenant): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $gateways = $settings['payment_gateways'] ?? [];

        return is_array($gateways) ? $gateways : [];
    }

    private function tenantStripeEnabledForBookings(Tenant $tenant): bool
    {
        return (bool) data_get($this->tenantPaymentGatewaySettings($tenant), 'stripe.enabled', false);
    }

    private function tenantMyFatoorahEnabledForBookings(Tenant $tenant): bool
    {
        return (bool) data_get($this->tenantPaymentGatewaySettings($tenant), 'myfatoorah.enabled', false);
    }

    private function tenantHasMyFatoorahBookingConfig(Tenant $tenant): bool
    {
        $mf = data_get($this->tenantPaymentGatewaySettings($tenant), 'myfatoorah', []);
        if (!is_array($mf)) {
            return false;
        }

        $apiToken = trim((string) ($mf['api_token'] ?? ''));
        $paymentMethodId = trim((string) ($mf['payment_method_id'] ?? ''));

        return $apiToken !== ''
            && ctype_digit($paymentMethodId)
            && (int) $paymentMethodId > 0;
    }

    private function resolveTenantBookingProvider(?Tenant $tenant, TenantStripeConnect $stripeConnect): ?string
    {
        if (!$tenant) {
            return null;
        }

        $settings = $this->tenantPaymentGatewaySettings($tenant);
        $default = strtolower(trim((string) ($settings['default_provider'] ?? '')));
        $stripeEnabled = $this->tenantStripeEnabledForBookings($tenant);
        $myFatoorahEnabled = $this->tenantMyFatoorahEnabledForBookings($tenant);
        $stripeReady = $stripeEnabled && $stripeConnect->canAcceptCheckout($tenant);
        $myFatoorahReady = $myFatoorahEnabled && $this->tenantHasMyFatoorahBookingConfig($tenant);

        if ($default === 'myfatoorah' && $myFatoorahReady) {
            return 'myfatoorah';
        }

        if ($default === 'stripe' && $stripeReady) {
            return 'stripe';
        }

        if ($stripeReady) {
            return 'stripe';
        }

        if ($myFatoorahReady) {
            return 'myfatoorah';
        }

        return null;
    }

    private function availableBookingProviders(Tenant $tenant, TenantStripeConnect $stripeConnect): array
    {
        $providers = [];

        if ($this->tenantStripeEnabledForBookings($tenant) && $stripeConnect->canAcceptCheckout($tenant)) {
            $providers[] = [
                'code' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Pay securely with cards via Stripe.',
                'is_ready' => true,
            ];
        }

        if ($this->tenantMyFatoorahEnabledForBookings($tenant) && $this->tenantHasMyFatoorahBookingConfig($tenant)) {
            $providers[] = [
                'code' => 'myfatoorah',
                'name' => 'MyFatoorah',
                'description' => 'Pay via MyFatoorah hosted checkout.',
                'is_ready' => true,
            ];
        }

        return $providers;
    }

    private function resolveBookingProviderForPayment(Payment $payment, Tenant $tenant, TenantStripeConnect $stripeConnect): ?string
    {
        $providerCode = strtolower(trim((string) data_get($payment->gateway_data, 'provider_code', '')));

        if ($providerCode === 'stripe' && $this->tenantStripeEnabledForBookings($tenant) && $stripeConnect->canAcceptCheckout($tenant)) {
            return 'stripe';
        }

        if ($providerCode === 'myfatoorah' && $this->tenantMyFatoorahEnabledForBookings($tenant) && $this->tenantHasMyFatoorahBookingConfig($tenant)) {
            return 'myfatoorah';
        }

        return $this->resolveTenantBookingProvider($tenant, $stripeConnect);
    }

    private function mapBookingProviderToPaymentMethod(?string $providerCode): PaymentMethod
    {
        return match (strtolower(trim((string) $providerCode))) {
            'myfatoorah' => PaymentMethod::MYFATOORAH,
            'stripe' => PaymentMethod::STRIPE,
            default => PaymentMethod::CREDIT_CARD,
        };
    }

    private function resolveTenantMyFatoorahProvider(Tenant $tenant): ?PaymentProvider
    {
        if (!$this->tenantMyFatoorahEnabledForBookings($tenant)) {
            return null;
        }

        $platformProvider = PaymentProvider::query()
            ->where('code', 'myfatoorah')
            ->where('is_enabled', true)
            ->where('supports_tenant_payments', true)
            ->first();

        if (!$platformProvider) {
            return null;
        }

        $tenantMf = data_get($this->tenantPaymentGatewaySettings($tenant), 'myfatoorah', []);
        if (!is_array($tenantMf)) {
            return null;
        }

        $config = array_merge(
            is_array($platformProvider->config) ? $platformProvider->config : [],
            [
                'country' => trim((string) ($tenantMf['country'] ?? '')),
                'api_token' => trim((string) ($tenantMf['api_token'] ?? '')),
                'api_base_url' => trim((string) ($tenantMf['api_base_url'] ?? '')),
                'payment_method_id' => trim((string) ($tenantMf['payment_method_id'] ?? '')),
                'callback_url' => trim((string) ($tenantMf['callback_url'] ?? '')),
                'error_url' => trim((string) ($tenantMf['error_url'] ?? '')),
                'webhook_secret' => trim((string) ($tenantMf['webhook_secret'] ?? '')),
            ]
        );

        if (trim((string) ($config['api_token'] ?? '')) === '') {
            return null;
        }

        $provider = new PaymentProvider();
        $provider->forceFill([
            'id' => $platformProvider->id,
            'code' => 'myfatoorah',
            'name' => $platformProvider->name,
            'driver' => $platformProvider->driver,
            'mode' => $platformProvider->mode,
            'config' => $config,
            'is_enabled' => true,
            'supports_tenant_payments' => true,
        ]);

        return $provider;
    }

    private function startMyFatoorahBookingCheckout(
        Reservation $reservation,
        Payment $payment,
        Tenant $tenant,
        ?string $tenantSlug,
        MyFatoorahSubscriptionProvider $myFatoorah
    )
    {
        $provider = $this->resolveTenantMyFatoorahProvider($tenant);
        if (!$provider) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'Tenant MyFatoorah settings are incomplete.');
        }

        $callbackUrl = trim((string) data_get($provider->config, 'callback_url', ''));
        if ($callbackUrl === '') {
            $callbackUrl = route('tenant.booking.payment.success', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation->id,
            ]).'?provider=myfatoorah';
        }

        $errorUrl = trim((string) data_get($provider->config, 'error_url', ''));
        if ($errorUrl === '') {
            $errorUrl = route('tenant.booking.payment.cancel', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation->id,
            ]).'?provider=myfatoorah';
        }

        try {
            $checkout = $myFatoorah->createCheckout([
                'amount' => (float) $reservation->total_amount,
                'currency' => $this->bookingCurrency(),
                'callback_url' => $callbackUrl,
                'error_url' => $errorUrl,
                'customer_name' => (string) (Auth::user()?->name ?? ''),
                'customer_email' => (string) (Auth::user()?->email ?? ''),
                'customer_mobile' => (string) (Auth::user()?->phone ?? ''),
                'customer_reference' => (string) $payment->id,
                'user_defined_field' => 'reservation:'.$reservation->id.'|tenant:'.$tenant->id,
                'items' => [[
                    'ItemName' => sprintf('Car Booking %s', $reservation->reservation_number),
                    'Quantity' => 1,
                    'UnitPrice' => (float) $reservation->total_amount,
                ]],
            ], $provider);

            $payment->forceFill([
                'status' => PaymentStatus::PENDING,
                'payment_method' => PaymentMethod::MYFATOORAH,
                'gateway_response' => 'myfatoorah_checkout_created',
                'gateway_data' => array_merge((array) $payment->gateway_data, [
                    'provider_code' => 'myfatoorah',
                    'invoice_id' => (string) ($checkout['invoice_id'] ?? ''),
                    'callback_url' => $callbackUrl,
                    'error_url' => $errorUrl,
                ]),
            ])->save();

            return Inertia::location((string) $checkout['payment_url']);
        } catch (Throwable $e) {
            Log::error('Booking MyFatoorah checkout creation failed', [
                'tenant_id' => $tenant->id,
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', config('app.debug')
                ? 'MyFatoorah error: '.$e->getMessage()
                : 'Could not create MyFatoorah checkout session for this booking.');
        }
    }

    private function handleMyFatoorahBookingSuccess(
        Reservation $reservation,
        Payment $payment,
        ?string $tenantSlug,
        Tenant $tenant,
        Request $request,
        MyFatoorahSubscriptionProvider $myFatoorah
    )
    {
        $provider = $this->resolveTenantMyFatoorahProvider($tenant);
        if (!$provider) {
            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', 'MyFatoorah verification is not available for this tenant.');
        }

        try {
            $verification = $myFatoorah->verifyPaymentStatus($request->query(), $provider);
        } catch (Throwable $e) {
            Log::error('Booking MyFatoorah payment verification failed', [
                'tenant_id' => $tenant->id,
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('error', config('app.debug')
                ? 'MyFatoorah verification error: '.$e->getMessage()
                : 'Could not verify booking payment.');
        }

        $gatewayData = array_merge((array) $payment->gateway_data, [
            'provider_code' => 'myfatoorah',
            'invoice_id' => (string) ($verification['invoice_id'] ?? ''),
            'payment_id' => (string) ($verification['payment_id'] ?? ''),
            'provider_transaction_id' => (string) ($verification['transaction_id'] ?? ''),
            'invoice_status' => (string) ($verification['invoice_status'] ?? ''),
            'verify_raw' => $verification['raw'] ?? [],
        ]);

        if (($verification['is_paid'] ?? false) === true) {
            $payment->forceFill([
                'status' => PaymentStatus::COMPLETED,
                'payment_method' => PaymentMethod::MYFATOORAH,
                'transaction_id' => (string) (($verification['transaction_id'] ?? '') ?: ($verification['payment_id'] ?? '') ?: ($payment->transaction_id ?? '')),
                'gateway_response' => 'paid',
                'gateway_data' => $gatewayData,
                'processed_at' => now(),
            ])->save();

            if ($reservation->status === ReservationStatus::PENDING) {
                $reservation->update([
                    'status' => ReservationStatus::CONFIRMED,
                ]);
            }

            return redirect()->route('tenant.booking.confirmation', [
                'subdomain' => $tenantSlug,
                'reservation' => $reservation,
            ])->with('success', 'Payment completed successfully.');
        }

        $failed = (bool) ($verification['is_failed'] ?? false);
        $payment->forceFill([
            'status' => $failed ? PaymentStatus::FAILED : PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::MYFATOORAH,
            'gateway_response' => (string) (($verification['invoice_status'] ?? '') ?: 'pending'),
            'gateway_data' => $gatewayData,
        ])->save();

        return redirect()->route('tenant.booking.confirmation', [
            'subdomain' => $tenantSlug,
            'reservation' => $reservation,
        ])->with(
            $failed ? 'error' : 'warning',
            (string) (($verification['failure_reason'] ?? null) ?: ($failed ? 'Payment failed.' : 'Payment is not completed yet.'))
        );
    }

    /**
     * @return array{days:int,daily_rate:float,subtotal:float,tax_amount:float,return_location_fee:float}
     */
    private function calculateBookingTotals(
        Car $car,
        Carbon $startDate,
        Carbon $endDate,
        ?Tenant $tenant = null,
        string $returnLocation = ''
    ): array
    {
        $days = max(1, $startDate->diffInDays($endDate));
        $pricing = $this->calculateTieredRentalPricing($car, $days);
        $dailyRate = $pricing['daily_rate'];
        $subtotal = $pricing['subtotal'];
        $taxPercentage = $this->resolveBookingTaxPercentage($tenant);
        $taxAmount = $subtotal * ($taxPercentage / 100);
        $returnLocationFee = $this->resolveBookingReturnLocationFee($tenant, $returnLocation);

        return [
            'days' => $days,
            'daily_rate' => $dailyRate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'return_location_fee' => $returnLocationFee,
        ];
    }

    private function resolveBookingReturnLocationFee(?Tenant $tenant, ?string $returnLocation): float
    {
        $tenant = $tenant ?: TenantContext::get();
        if (!$tenant) {
            return 0.0;
        }

        $tenant->loadMissing('siteSetting');
        $settings = ReservationSettings::normalize(
            is_array($tenant->siteSetting?->reservation_settings)
                ? $tenant->siteSetting->reservation_settings
                : null
        );

        return ReservationSettings::resolveLocationFee($settings, $returnLocation, 'return');
    }

    /**
     * @return array{daily_rate:float,subtotal:float}
     */
    private function calculateTieredRentalPricing(Car $car, int $days): array
    {
        $days = max(1, $days);
        $dailyRate = max(0, abs((float) $car->price_per_day));
        $weeklyRate = max(0, abs((float) ($car->price_per_week ?? 0)));
        $monthlyRate = max(0, abs((float) ($car->price_per_month ?? 0)));
        $remainingDays = $days;
        $subtotal = 0.0;

        $months = intdiv($remainingDays, 30);
        if ($months > 0) {
            $subtotal += $months * ($monthlyRate > 0 ? $monthlyRate : $dailyRate * 30);
            $remainingDays -= $months * 30;
        }

        $weeks = intdiv($remainingDays, 7);
        if ($weeks > 0) {
            $subtotal += $weeks * ($weeklyRate > 0 ? $weeklyRate : $dailyRate * 7);
            $remainingDays -= $weeks * 7;
        }

        if ($remainingDays > 0) {
            $subtotal += $remainingDays * $dailyRate;
        }

        return [
            'daily_rate' => round($dailyRate, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }

    private function resolveBookingTaxPercentage(?Tenant $tenant): float
    {
        $tenant = $tenant ?: TenantContext::get();
        if (!$tenant) {
            return 7.0;
        }

        $siteSetting = $tenant->relationLoaded('siteSetting')
            ? $tenant->siteSetting
            : $tenant->siteSetting()->first();

        $value = $siteSetting?->tax_percentage;
        if ($value === null || $value === '') {
            return 7.0;
        }

        $number = (float) $value;
        if (!is_finite($number)) {
            return 7.0;
        }

        return max(0, min(100, round($number, 2)));
    }

    /**
     * @return array{0:CarDiscount|null,1:float}
     */
    private function resolveAutoDiscountForBooking(
        Tenant $tenant,
        Car $car,
        Carbon $startDate,
        Carbon $endDate,
        float $subtotal
    ): array {
        $days = max(1, $startDate->diffInDays($endDate));
        $now = now();

        $candidates = CarDiscount::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where(function ($query) use ($car) {
                $query->whereNull('car_id')->orWhere('car_id', $car->id);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->get();

        $bestDiscount = null;
        $bestAmount = 0.0;

        foreach ($candidates as $candidate) {
            if ($candidate->min_days && $days < (int) $candidate->min_days) {
                continue;
            }

            if ($candidate->min_total_amount !== null && $subtotal < (float) $candidate->min_total_amount) {
                continue;
            }

            $amount = $this->calculateAutoDiscountAmount($candidate, $subtotal);
            if ($amount <= 0) {
                continue;
            }

            if ($amount > $bestAmount) {
                $bestDiscount = $candidate;
                $bestAmount = $amount;
            }
        }

        return [$bestDiscount, $bestAmount];
    }

    private function calculateAutoDiscountAmount(CarDiscount $discount, float $subtotal): float
    {
        $value = (float) $discount->value;
        $amount = 0.0;
        $type = $discount->type instanceof CouponType ? $discount->type : CouponType::from((string) $discount->type);

        if ($type === CouponType::PERCENTAGE) {
            $amount = $subtotal * ($value / 100);
        } else {
            $amount = $value;
        }

        if ($discount->max_discount_amount !== null) {
            $amount = min($amount, (float) $discount->max_discount_amount);
        }

        return max(0, min($amount, $subtotal));
    }

    /**
     * @return array{0:Coupon|null,1:string|null}
     */
    private function resolveCouponForBooking(
        Tenant $tenant,
        Car $car,
        string $inputCode,
        Carbon $startDate,
        Carbon $endDate,
        float $subtotal
    ): array {
        $code = strtoupper(trim($inputCode));
        if ($code === '') {
            return [null, null];
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->where('is_active', true)
            ->where(function ($query) use ($car) {
                $query->whereNull('car_id')->orWhere('car_id', $car->id);
            })
            ->first();

        if (!$coupon) {
            return [null, 'Coupon code is invalid or not allowed for this car.'];
        }

        $now = now();
        if ($coupon->starts_at && $coupon->starts_at->gt($now)) {
            return [null, 'Coupon is not active yet.'];
        }

        if ($coupon->ends_at && $coupon->ends_at->lt($now)) {
            return [null, 'Coupon has expired.'];
        }

        $days = max(1, $startDate->diffInDays($endDate));
        if ($coupon->min_days && $days < (int) $coupon->min_days) {
            return [null, "Coupon requires at least {$coupon->min_days} rental days."];
        }

        if ($coupon->min_total_amount !== null && $subtotal < (float) $coupon->min_total_amount) {
            return [null, 'Coupon minimum order amount is not reached.'];
        }

        if ($coupon->usage_limit !== null && (int) $coupon->used_count >= (int) $coupon->usage_limit) {
            return [null, 'Coupon usage limit reached.'];
        }

        return [$coupon, null];
    }

    private function calculateCouponDiscount(Coupon $coupon, float $subtotal): float
    {
        $discount = 0.0;
        $type = $coupon->type instanceof CouponType ? $coupon->type : CouponType::from((string) $coupon->type);

        if ($type === CouponType::PERCENTAGE) {
            $discount = $subtotal * (((float) $coupon->value) / 100);
        } else {
            $discount = (float) $coupon->value;
        }

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        return max(0, min($discount, $subtotal));
    }

    private function requestQueryValue(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));
        if ($value !== '') {
            return $value;
        }

        $requestUri = (string) $request->server('REQUEST_URI', '');
        if ($requestUri === '') {
            return null;
        }

        $query = parse_url($requestUri, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $fallback = trim((string) ($params[$key] ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    private function bookingCurrency(?string $tenantCurrency = null): string
    {
        $tenant = TenantContext::get();

        return CurrencyCatalog::codeForTenant(
            $tenant,
            $tenantCurrency ?: config('cashier.currency') ?: config('app.currency_code', 'USD')
        );
    }

    private function extractTransactionId(object $session): ?string
    {
        $paymentIntent = data_get($session, 'payment_intent');

        if (is_string($paymentIntent) && trim($paymentIntent) !== '') {
            return $paymentIntent;
        }

        if (is_object($paymentIntent) && isset($paymentIntent->id)) {
            return (string) $paymentIntent->id;
        }

        return isset($session->id) ? (string) $session->id : null;
    }
}
