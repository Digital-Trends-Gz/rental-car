<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('contract_return_report_id')->nullable()->constrained('contract_return_reports')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('base_amount', 12, 2);
            $table->string('discount_type');
            $table->decimal('discount_value', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('final_amount', 12, 2);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['reservation_id', 'status']);
            $table->index(['contract_id', 'status']);
            $table->index(['contract_return_report_id', 'status']);
            $table->index(['requested_by_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_requests');
    }
};
