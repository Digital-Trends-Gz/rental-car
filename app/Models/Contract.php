<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Traits\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class Contract extends Model
{
    use BelongsToTenant;
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'reservation_id',
        'contract_number',
        'status',
        'contract_date',
        'renter_name',
        'renter_id_number',
        'renter_phone',
        'car_details',
        'plate_number',
        'vehicle_odometer',
        'vehicle_fuel_level',
        'price_per_day',
        'price_per_week',
        'price_per_month',
        'allowed_km_per_day',
        'allowed_km_per_week',
        'allowed_km_per_month',
        'return_odometer',
        'return_fuel_level',
        'vehicle_condition_before',
        'vehicle_condition_after',
        'actual_return_time',
        'start_date',
        'end_date',
        'total_amount',
        'currency',
        'notes',
        'handover_state',
        'ai_extraction_status',
        'ai_extracted_data',
    ];

    protected $casts = [
        'status' => ContractStatus::class,
        'contract_date' => 'date',
        'vehicle_odometer' => 'integer',
        'price_per_day' => 'decimal:2',
        'price_per_week' => 'decimal:2',
        'price_per_month' => 'decimal:2',
        'allowed_km_per_day' => 'integer',
        'allowed_km_per_week' => 'integer',
        'allowed_km_per_month' => 'integer',
        'return_odometer' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'total_amount' => 'decimal:2',
        'actual_return_time' => 'datetime',
        'handover_state' => 'array',
        'ai_extracted_data' => 'array',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(ContractDriver::class);
    }

    public function primaryDriver(): HasOne
    {
        return $this->hasOne(ContractDriver::class)->where('role', 'primary');
    }

    public function additionalDrivers(): HasMany
    {
        return $this->hasMany(ContractDriver::class)->where('role', 'additional');
    }

    public function archiveFiles(): HasMany
    {
        return $this->hasMany(ContractArchiveFile::class);
    }

    public function handoverPhotos(): HasMany
    {
        return $this->hasMany(ContractHandoverPhoto::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(CarDamageReport::class);
    }

    public function accidentReports(): HasMany
    {
        return $this->hasMany(AccidentReport::class);
    }

    public function returnStatusReport(): HasOne
    {
        return $this->hasOne(ContractReturnReport::class);
    }

    public function openedDamageCases(): HasMany
    {
        return $this->hasMany(CarDamageCase::class, 'opened_in_contract_id');
    }

    public function extensionRequests(): HasMany
    {
        return $this->hasMany(RentalExtensionRequest::class);
    }

    public function scopePendingReturnTask(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->where('status', ContractStatus::ACTIVE->value)
            ->whereDoesntHave('returnStatusReport', function (Builder $returnReportQuery): void {
                $returnReportQuery->where('status', 'finalized');
            })
            ->whereNotExists(function ($taskQuery) use ($date): void {
                $taskQuery->selectRaw('1')
                    ->from((new DailyTaskStatus())->getTable())
                    ->whereColumn('daily_task_statuses.tenant_id', 'contracts.tenant_id')
                    ->whereColumn('daily_task_statuses.source_id', 'contracts.id')
                    ->where('daily_task_statuses.task_type', 'return')
                    ->where('daily_task_statuses.source_type', 'contract')
                    ->where('daily_task_statuses.status', 'completed')
                    ->whereDate('daily_task_statuses.completed_at', $date);
            });
    }
}
