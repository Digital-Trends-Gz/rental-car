<?php

namespace App\Models;

use App\Enums\CarViolationStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarViolation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'branch_id',
        'reservation_id',
        'violation_type_id',
        'issued_to_user_id',
        'created_by',
        'violation_number',
        'violation_date',
        'type',
        'amount',
        'status',
        'due_date',
        'paid_at',
        'payment_reference',
        'authority',
        'location',
        'description',
        'notes',
    ];

    protected $casts = [
        'violation_date' => 'date',
        'amount' => 'decimal:2',
        'status' => CarViolationStatus::class,
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class);
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
