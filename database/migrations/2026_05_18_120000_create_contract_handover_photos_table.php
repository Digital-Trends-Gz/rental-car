<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_handover_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('damage_report_id')->nullable()->constrained('car_damage_reports')->nullOnDelete();
            $table->string('phase', 50)->default('delivery');
            $table->string('photo_type', 50)->default('damage');
            $table->string('view_side', 50)->nullable();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extraction_status', 50)->nullable();
            $table->json('extracted_data')->nullable();
            $table->string('extracted_value')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'phase', 'photo_type']);
            $table->index(['damage_report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_handover_photos');
    }
};
