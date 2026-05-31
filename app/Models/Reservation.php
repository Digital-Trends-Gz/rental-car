<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Services\Rentals\RentalStatusSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Traits\BelongsToTenant;

class Reservation extends Model
{
    use SoftDeletes;
    use BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'reservation_number',
        'user_id',
        'car_id',
        'start_date',
        'end_date',
        'pickup_time',
        'return_time',
        'pickup_location',
        'return_location',
        'return_location_fee',
        'total_days',
        'daily_rate',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'coupon_id',
        'coupon_code',
        'auto_discount_id',
        'auto_discount_amount',
        'total_amount',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pickup_time' => 'datetime:H:i',
        'return_time' => 'datetime:H:i',
        'return_location_fee' => 'decimal:2',
        'total_days' => 'integer',
        'daily_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'coupon_id' => 'integer',
        'auto_discount_id' => 'integer',
        'auto_discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => ReservationStatus::class,
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            if (empty($reservation->reservation_number)) {
                $reservation->reservation_number = 'RES-' . strtoupper(Str::random(8));
            }
        });

        static::saved(function (self $reservation): void {
            app(RentalStatusSyncService::class)->syncCarsByIds([$reservation->car_id]);
        });

        static::deleted(function (self $reservation): void {
            app(RentalStatusSyncService::class)->syncCarsByIds([$reservation->car_id]);
        });

        static::restored(function (self $reservation): void {
            app(RentalStatusSyncService::class)->syncCarsByIds([$reservation->car_id]);
        });
    }

    /**
     * Get the user that owns the reservation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the car that is reserved.
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function autoDiscount(): BelongsTo
    {
        return $this->belongsTo(CarDiscount::class, 'auto_discount_id');
    }

    /**
     * Get the payments for the reservation.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returnStatusReport(): HasOne
    {
        return $this->hasOne(ContractReturnReport::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(CarDamageReport::class);
    }

    public function accidentReports(): HasMany
    {
        return $this->hasMany(AccidentReport::class);
    }

    public function openedDamageCases(): HasMany
    {
        return $this->hasMany(CarDamageCase::class, 'opened_in_reservation_id');
    }

    public function extensionRequests(): HasMany
    {
        return $this->hasMany(RentalExtensionRequest::class);
    }

    /**
     * Get the formatted total amount attribute.
     *
     * @return string
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return config('app.currency_symbol') . number_format($this->total_amount, 2);
    }

    /**
     * Get the duration in days.
     *
     * @return int
     */
    public function getDurationAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Check if reservation is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === ReservationStatus::ACTIVE;
    }

    /**
     * Check if reservation can be cancelled.
     *
     * @return bool
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            ReservationStatus::PENDING,
            ReservationStatus::CONFIRMED
        ]);
    }

    /**
     * Scope for active reservations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', ReservationStatus::ACTIVE->value);
    }

    /**
     * Scope for reservations by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        });
    }
}
