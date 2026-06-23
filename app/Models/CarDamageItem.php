<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarDamageItem extends Model
{
    use BelongsToTenant;

    public const SOURCE_TYPE_AI = 'ai';
    public const SOURCE_TYPE_EMPLOYEE = 'employee';

    protected $fillable = [
        'tenant_id',
        'car_damage_report_id',
        'source_type',
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
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'marker_x' => 'decimal:2',
        'marker_y' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'source_type' => self::SOURCE_TYPE_EMPLOYEE,
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(CarDamageReport::class, 'car_damage_report_id');
    }
}
