<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accident_report_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accident_report_id')->constrained()->cascadeOnDelete();
            $table->string('photo_type', 50)->nullable();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['accident_report_id', 'photo_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_report_photos');
    }
};
