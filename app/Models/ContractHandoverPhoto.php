<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContractHandoverPhoto extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'damage_report_id',
        'phase',
        'photo_type',
        'view_side',
        'title',
        'notes',
        'file_path',
        'file_name',
        'mime_type',
        'extraction_status',
        'extracted_data',
        'extracted_value',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ContractHandoverPhoto $photo): void {
            $path = ltrim((string) preg_replace('/^storage\//', '', (string) $photo->file_path), '/');
            if ($path !== '' && Storage::disk(config('vilt-filepond.storage_disk'))->exists($path)) {
                Storage::disk(config('vilt-filepond.storage_disk'))->delete($path);
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(CarDamageReport::class, 'damage_report_id');
    }
}
