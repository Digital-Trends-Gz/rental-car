<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class Branch extends Model
{
    use HasFactory, BelongsToTenant, HasFiles;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'phone',
        'email',
        'country',
        'city',
        'street_name',
        'street_number',
        'building_number',
        'office_number',
        'post_code',
        'google_map_url',
        'phone_1',
        'phone_2',
        'whatsapp',
        'cr_number',
        'manager_name',
        'manager_civil_number',
        'plan_locked_at',
        'plan_lock_reason',
    ];

    protected $appends = [
        'showroom_image_url',
    ];

    protected $casts = [
        'plan_locked_at' => 'datetime',
    ];

    /**
     * Get the users assigned to this branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function getShowroomImageUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'showroom')
            : $this->files()->where('collection', 'showroom')->first();

        if ($file && $file->path) {
            return Storage::url($file->path);
        }

        return null;
    }
}
