<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractReturnReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'contract_id',
        'reservation_id',
        'car_id',
        'damage_report_id',
        'payment_id',
        'payment_status',
        'created_by',
        'report_number',
        'status',
        'actual_return_time',
        'return_location',
        'return_odometer',
        'return_fuel_level',
        'vehicle_condition_after',
        'has_damage',
        'extra_kilometers',
        'kilometer_rate',
        'cleaning_fee',
        'fuel_fee',
        'fuel_credit',
        'late_hours',
        'late_hour_rate',
        'damage_fee',
        'maintenance_fee',
        'other_fee',
        'discount',
        'total_extra_charges',
        'notes',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'contract_id' => 'integer',
        'reservation_id' => 'integer',
        'car_id' => 'integer',
        'damage_report_id' => 'integer',
        'payment_id' => 'integer',
        'created_by' => 'integer',
        'payment_status' => 'string',
        'actual_return_time' => 'datetime',
        'return_odometer' => 'integer',
        'has_damage' => 'boolean',
        'extra_kilometers' => 'decimal:2',
        'kilometer_rate' => 'decimal:2',
        'cleaning_fee' => 'decimal:2',
        'fuel_fee' => 'decimal:2',
        'fuel_credit' => 'decimal:2',
        'late_hours' => 'decimal:2',
        'late_hour_rate' => 'decimal:2',
        'damage_fee' => 'decimal:2',
        'maintenance_fee' => 'decimal:2',
        'other_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_extra_charges' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(CarDamageReport::class, 'damage_report_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
