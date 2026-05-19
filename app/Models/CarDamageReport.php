<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class CarDamageReport extends Model
{
    use BelongsToTenant;
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'branch_id',
        'contract_id',
        'reservation_id',
        'created_by',
        'report_number',
        'report_type',
        'status',
        'inspected_at',
        'odometer',
        'summary',
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
        'odometer' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CarDamageReport $report): void {
            $report->files()->each(static function ($file): void {
                $file->delete();
            });
        });
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarDamageItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
