<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('converted_reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->string('status')->default('locked_plan_limit');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('total_days')->default(1);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('return_location_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->text('customer_name')->nullable();
            $table->text('customer_email')->nullable();
            $table->text('customer_phone')->nullable();
            $table->text('pickup_location')->nullable();
            $table->text('return_location')->nullable();
            $table->text('coupon_code')->nullable();
            $table->json('meta')->nullable();
            $table->string('locked_reason')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
