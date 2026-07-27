<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerDashboardMetricSnapshot extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'branch_scope',
        'metric_key',
        'metric_date',
        'value',
        'captured_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'branch_id' => 'integer',
        'metric_date' => 'date',
        'value' => 'decimal:2',
        'captured_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
