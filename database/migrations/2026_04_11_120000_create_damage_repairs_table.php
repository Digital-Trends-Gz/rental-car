<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('car_damage_case_id')->constrained('car_damage_cases')->cascadeOnDelete();
            $table->foreignId('maintenance_workshop_id')->nullable()->constrained('maintenance_workshops')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('repair_number');
            $table->string('status')->default('open');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->string('workshop_name')->nullable();
            $table->text('notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'repair_number'], 'damage_repairs_tenant_number_uniq');
            $table->index(['tenant_id', 'car_id'], 'damage_repairs_tenant_car_idx');
            $table->index(['tenant_id', 'branch_id'], 'damage_repairs_tenant_branch_idx');
            $table->index(['tenant_id', 'status'], 'damage_repairs_tenant_status_idx');
            $table->index(['tenant_id', 'car_damage_case_id'], 'damage_repairs_tenant_case_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_repairs');
    }
};
