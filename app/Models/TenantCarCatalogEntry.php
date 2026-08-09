<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantCarCatalogEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'make',
        'model',
        'year',
        'fuel_type',
        'transmission',
        'seats',
        'engine_power',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'year' => 'integer',
        'seats' => 'integer',
        'engine_power' => 'integer',
    ];
}
