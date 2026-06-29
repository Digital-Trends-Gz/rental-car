<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsightReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'created_by',
        'period',
        'locale',
        'period_start',
        'period_end',
        'status',
        'provider',
        'model',
        'internal_payload',
        'ai_result',
        'error_message',
        'generated_at',
        'completed_at',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'created_by' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'internal_payload' => 'array',
        'ai_result' => 'array',
        'generated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
