<?php

namespace App\Models;

use App\Enums\DamageRepairStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageRepair extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'branch_id',
        'car_damage_case_id',
        'maintenance_workshop_id',
        'repair_number',
        'status',
        'opened_at',
        'started_at',
        'completed_at',
        'estimated_cost',
        'actual_cost',
        'workshop_name',
        'notes',
        'completion_notes',
        'created_by',
    ];

    protected $casts = [
        'status' => DamageRepairStatus::class,
        'opened_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function damageCase(): BelongsTo
    {
        return $this->belongsTo(CarDamageCase::class, 'car_damage_case_id');
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkshop::class, 'maintenance_workshop_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
