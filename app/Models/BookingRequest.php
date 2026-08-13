<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRequest extends Model
{
    use BelongsToTenant;

    public const STATUS_LOCKED_PLAN_LIMIT = 'locked_plan_limit';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'car_id',
        'user_id',
        'converted_reservation_id',
        'status',
        'start_date',
        'end_date',
        'total_days',
        'daily_rate',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'return_location_fee',
        'total_amount',
        'currency',
        'customer_name',
        'customer_email',
        'customer_phone',
        'pickup_location',
        'return_location',
        'coupon_code',
        'meta',
        'locked_reason',
        'unlocked_at',
        'converted_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'integer',
        'daily_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'return_location_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'customer_name' => 'encrypted',
        'customer_email' => 'encrypted',
        'customer_phone' => 'encrypted',
        'pickup_location' => 'encrypted',
        'return_location' => 'encrypted',
        'coupon_code' => 'encrypted',
        'meta' => 'array',
        'unlocked_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convertedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'converted_reservation_id');
    }
}
