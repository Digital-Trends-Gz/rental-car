<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_return_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('damage_report_id')->nullable()->constrained('car_damage_reports')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_number');
            $table->string('status')->default('finalized');
            $table->dateTime('actual_return_time')->nullable();
            $table->string('return_location')->nullable();
            $table->unsignedInteger('return_odometer')->nullable();
            $table->string('return_fuel_level')->nullable();
            $table->string('vehicle_condition_after')->nullable();
            $table->decimal('extra_kilometers', 10, 2)->default(0);
            $table->decimal('kilometer_rate', 10, 2)->default(0);
            $table->decimal('cleaning_fee', 10, 2)->default(0);
            $table->decimal('fuel_fee', 10, 2)->default(0);
            $table->decimal('late_hours', 10, 2)->default(0);
            $table->decimal('late_hour_rate', 10, 2)->default(0);
            $table->decimal('damage_fee', 10, 2)->default(0);
            $table->decimal('maintenance_fee', 10, 2)->default(0);
            $table->decimal('other_fee', 10, 2)->default(0);
            $table->decimal('total_extra_charges', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_id'], 'contract_return_reports_tenant_contract_uniq');
            $table->unique(['tenant_id', 'report_number'], 'contract_return_reports_tenant_report_number_uniq');
            $table->index(['tenant_id', 'reservation_id'], 'contract_return_reports_tenant_reservation_idx');
            $table->index(['tenant_id', 'car_id'], 'contract_return_reports_tenant_car_idx');
            $table->index(['tenant_id', 'damage_report_id'], 'contract_return_reports_tenant_damage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_return_reports');
    }
};
