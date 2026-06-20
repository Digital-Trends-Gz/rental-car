<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTaskStatus extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'task_type',
        'source_type',
        'source_id',
        'status',
        'started_at',
        'completed_at',
        'started_by',
        'completed_by',
        'notes',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
