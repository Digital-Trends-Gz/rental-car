<?php

namespace App\Models;

use App\Enums\MaintenanceRecordStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarMaintenance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'branch_id',
        'maintenance_type_id',
        'maintenance_workshop_id',
        'status',
        'scheduled_date',
        'started_at',
        'completed_at',
        'cost',
        'odometer',
        'workshop_name',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => MaintenanceRecordStatus::class,
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cost' => 'decimal:2',
        'odometer' => 'integer',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
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
