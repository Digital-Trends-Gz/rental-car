<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarDamageCase extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'branch_id',
        'opened_in_contract_id',
        'opened_in_reservation_id',
        'last_report_id',
        'created_by',
        'zone_code',
        'view_side',
        'damage_type',
        'severity',
        'damage_timing',
        'quantity',
        'marker_x',
        'marker_y',
        'estimated_cost',
        'notes',
        'status',
        'first_detected_at',
        'last_detected_at',
        'repaired_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'marker_x' => 'decimal:2',
        'marker_y' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'repaired_at' => 'datetime',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedInContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'opened_in_contract_id');
    }

    public function openedInReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'opened_in_reservation_id');
    }

    public function lastReport(): BelongsTo
    {
        return $this->belongsTo(CarDamageReport::class, 'last_report_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(DamageRepair::class)->latest('opened_at')->latest('id');
    }
}
