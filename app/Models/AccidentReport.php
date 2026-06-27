<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccidentReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'reservation_id',
        'car_id',
        'branch_id',
        'reported_by',
        'employee_id',
        'accident_context',
        'responsibility',
        'location_type',
        'accident_number',
        'status',
        'accident_at',
        'location',
        'latitude',
        'longitude',
        'description',
        'police_report_number',
        'has_injuries',
        'third_party_involved',
        'third_party_details',
        'notes',
    ];

    protected $casts = [
        'accident_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'has_injuries' => 'boolean',
        'third_party_involved' => 'boolean',
        'third_party_details' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AccidentReportPhoto::class);
    }
}
