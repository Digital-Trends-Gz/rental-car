<?php

namespace App\Models;

use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ReservationStatus;
use App\Traits\BelongsToTenant;

class Car extends Model
{
    use SoftDeletes;
    use HasFiles;
    use BelongsToTenant;

    public const ADDITIONAL_PHOTO_TYPES = [
        'right',
        'left',
        'front',
        'rear',
        'inside',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'make',
        'model',
        'year',
        'license_plate',
        'color',
        'price_per_day',
        'mileage',
        'transmission',
        'seats',
        'fuel_type',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'year' => 'integer',
        'branch_id' => 'integer',
        'price_per_day' => 'decimal:2',
        'mileage' => 'integer',
        'seats' => 'integer',
        'status' => CarStatus::class,
        'fuel_type' => FuelType::class,
        'color' => CarColor::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'image_url',
        'images',
        'additional_photos',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the formatted price attribute.
     *
     * @return string
     */
    public function getFormattedPriceAttribute()
    {
        return config('app.currency_symbol') . number_format($this->price_per_day, 2);
    }

    /**
     * Get the full car name (make + model + year).
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->year} {$this->make} {$this->model}";
    }

    /**
     * Get the car image URL. Uses the first file in the 'image' collection if available,
     * otherwise falls back to the default public image.
     */
    public function getImageUrlAttribute(): string
    {
        // If the relation is already loaded, use it to avoid N+1
        $file = null;
        if ($this->relationLoaded('files')) {
            $file = $this->files->firstWhere('collection', 'image');
        }

        // Otherwise query for the first image file
        if (!$file) {
            $file = $this->files()->where('collection', 'image')->first();
        }

        if ($file && $file->path) {
            return Storage::url($file->path);
        }

        // Fallback to the public default image
        return asset('images/car-default.jpg');
    }

    /**
     * Get all car image URLs for gallery usage.
     *
     * @return array<int, array{id:int|null,url:string,alt:string}>
     */
    public function getImagesAttribute(): array
    {
        $files = $this->relationLoaded('files')
            ? $this->files->where('collection', 'image')->values()
            : $this->files()->where('collection', 'image')->get();

        if ($files->isEmpty()) {
            return [[
                'id' => null,
                'url' => asset('images/car-default.jpg'),
                'alt' => $this->full_name,
            ]];
        }

        return $files
            ->map(fn ($file) => [
                'id' => $file->id,
                'url' => Storage::url($file->path),
                'alt' => $this->full_name,
            ])
            ->values()
            ->all();
    }

    /**
     * Get typed additional photo URLs.
     *
     * @return array<string, array{id:int|null,url:string,alt:string}>
     */
    public function getAdditionalPhotosAttribute(): array
    {
        return collect(self::ADDITIONAL_PHOTO_TYPES)
            ->mapWithKeys(function (string $type) {
                $collection = self::additionalPhotoCollection($type);

                $file = $this->relationLoaded('files')
                    ? $this->files->firstWhere('collection', $collection)
                    : $this->files()->where('collection', $collection)->first();

                return [
                    $type => [
                        'id' => $file?->id,
                        'url' => $file?->path ? Storage::url($file->path) : null,
                        'alt' => "{$this->full_name} {$type} photo",
                    ],
                ];
            })
            ->all();
    }

    public static function additionalPhotoCollection(string $type): string
    {
        return 'additional_photo_'.$type;
    }

    /**
     * Get the reservations for the car.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(CarMaintenance::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(CarViolation::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(CarDamageReport::class);
    }

    public function damageCases(): HasMany
    {
        return $this->hasMany(CarDamageCase::class)->orderBy('zone_code')->orderBy('id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CarDocument::class)->latest('expiry_date')->latest('id');
    }

    public function carDiscounts(): HasMany
    {
        return $this->hasMany(CarDiscount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if car is available for given date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $excludeReservationId
     * @return bool
     */
    public function isAvailable(string $startDate, string $endDate, ?int $excludeReservationId = null): bool
    {
        $query = $this->reservations()
            ->whereIn('status', [
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
            ])
            ->betweenDates($startDate, $endDate);

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return $query->count() === 0;
    }
}
