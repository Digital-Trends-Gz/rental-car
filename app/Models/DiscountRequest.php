<?php

namespace App\Models;

use App\Enums\DiscountRequestStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reservation_id',
        'contract_id',
        'contract_return_report_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'base_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'final_amount',
        'reason',
        'status',
        'review_note',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'status' => DiscountRequestStatus::class,
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function returnReport(): BelongsTo
    {
        return $this->belongsTo(ContractReturnReport::class, 'contract_return_report_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
