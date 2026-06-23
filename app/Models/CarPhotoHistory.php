<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class CarPhotoHistory extends Model
{
    use BelongsToTenant;
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'user_id',
        'reason',
        'notes',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
