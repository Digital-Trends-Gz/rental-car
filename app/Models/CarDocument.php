<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class CarDocument extends Model
{
    use BelongsToTenant;
    use HasFiles;
    use SoftDeletes;

    public const TYPES = [
        'license',
        'insurance',
        'purchase_contract',
    ];

    public const REMINDABLE_TYPES = [
        'license',
        'insurance',
    ];

    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_EXPIRING_SOON = 'expiring_soon';
    public const STATUS_NEW = 'new';
    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'tenant_id',
        'car_id',
        'type',
        'document_number',
        'issuer',
        'issue_date',
        'purchase_date',
        'expiry_date',
        'cost',
        'notes',
        'is_active',
        'ten_day_reminder_sent_at',
        'five_day_reminder_sent_at',
        'three_day_reminder_sent_at',
        'one_day_reminder_sent_at',
        'expiry_day_reminder_sent_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
        'ten_day_reminder_sent_at' => 'datetime',
        'five_day_reminder_sent_at' => 'datetime',
        'three_day_reminder_sent_at' => 'datetime',
        'one_day_reminder_sent_at' => 'datetime',
        'expiry_day_reminder_sent_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function reminderColumnForDays(int $daysRemaining): ?string
    {
        return match ($daysRemaining) {
            10 => 'ten_day_reminder_sent_at',
            5 => 'five_day_reminder_sent_at',
            3 => 'three_day_reminder_sent_at',
            1 => 'one_day_reminder_sent_at',
            0 => 'expiry_day_reminder_sent_at',
            default => null,
        };
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            'license' => 'Car License',
            'insurance' => 'Car Insurance',
            'purchase_contract' => 'Purchase Contract',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function getStatusKeyAttribute(): string
    {
        if (!$this->is_active) {
            return self::STATUS_INACTIVE;
        }

        if ($this->type === 'purchase_contract') {
            return self::STATUS_ACTIVE;
        }

        $today = Carbon::today();
        $expiryDate = $this->expiry_date ? Carbon::parse($this->expiry_date)->startOfDay() : null;

        if ($expiryDate && $expiryDate->lt($today)) {
            return self::STATUS_EXPIRED;
        }

        if ($expiryDate && $expiryDate->lte($today->copy()->addDays(10))) {
            return self::STATUS_EXPIRING_SOON;
        }

        $createdAt = $this->created_at ? Carbon::parse($this->created_at)->startOfDay() : null;
        if ($createdAt && $createdAt->gte($today->copy()->subDays(10))) {
            return self::STATUS_NEW;
        }

        return self::STATUS_ACTIVE;
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if ($this->type === 'purchase_contract') {
            return null;
        }

        if (!$this->expiry_date) {
            return null;
        }

        return Carbon::today()->diffInDays(Carbon::parse($this->expiry_date), false);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'attachment')
            : $this->files()->where('collection', 'attachment')->first();

        return $file?->path ? \Storage::url($file->path) : null;
    }

    public function getFrontImageUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? ($this->files->firstWhere('collection', 'front_image') ?? $this->files->firstWhere('collection', 'attachment'))
            : ($this->files()->where('collection', 'front_image')->first() ?? $this->files()->where('collection', 'attachment')->first());

        return $file?->path ? \Storage::url($file->path) : null;
    }

    public function getBackImageUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'back_image')
            : $this->files()->where('collection', 'back_image')->first();

        return $file?->path ? \Storage::url($file->path) : null;
    }
}
