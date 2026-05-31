<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accident_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accident_number')->unique();
            $table->string('status', 50)->default('reported');
            $table->dateTime('accident_at')->nullable();
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description');
            $table->string('police_report_number', 100)->nullable();
            $table->boolean('has_injuries')->default(false);
            $table->boolean('third_party_involved')->default(false);
            $table->json('third_party_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status']);
            $table->index(['reservation_id', 'car_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_reports');
    }
};
