<?php

namespace App\Models;

use App\Enums\RentalExtensionRequestStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalExtensionRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'reservation_id',
        'requested_by_admin_id',
        'responded_by_user_id',
        'new_end_date',
        'extra_days',
        'extra_amount',
        'reason',
        'client_notes',
        'status',
        'responded_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'new_end_date' => 'date',
        'extra_days' => 'integer',
        'extra_amount' => 'decimal:2',
        'status' => RentalExtensionRequestStatus::class,
        'responded_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requestedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_admin_id');
    }

    public function respondedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
