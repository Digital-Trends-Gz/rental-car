<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AccidentReportPhoto extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'accident_report_id',
        'photo_type',
        'file_path',
        'file_name',
        'mime_type',
        'size',
        'notes',
    ];

    protected static function booted(): void
    {
        static::deleting(function (AccidentReportPhoto $photo): void {
            $path = ltrim((string) preg_replace('/^storage\//', '', (string) $photo->file_path), '/');
            $disk = Storage::disk(config('vilt-filepond.storage_disk'));

            if ($path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        });
    }

    public function accidentReport(): BelongsTo
    {
        return $this->belongsTo(AccidentReport::class);
    }
}
