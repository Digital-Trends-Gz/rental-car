<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_task_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('task_type', 32);
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('status', 32)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'task_type', 'source_type', 'source_id'], 'daily_task_status_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'task_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_task_statuses');
    }
};
