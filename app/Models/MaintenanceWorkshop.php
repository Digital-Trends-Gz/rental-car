<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class MaintenanceWorkshop extends Model
{
    use BelongsToTenant;
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'maintenance_type_id',
        'name',
        'phone',
        'rate',
        'country',
        'city',
        'street_name',
        'street_number',
        'building_number',
        'office_number',
        'post_code',
        'google_map_url',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
    ];

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function getFrontImageUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'front_image')
            : $this->files()->where('collection', 'front_image')->first();

        return $file?->path ? \Storage::url($file->path) : null;
    }
}
