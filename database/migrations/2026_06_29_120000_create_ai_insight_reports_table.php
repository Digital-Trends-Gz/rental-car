<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insight_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period', 50);
            $table->string('locale', 10)->default('en');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 50)->default('internal_ready');
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->json('internal_payload');
            $table->json('ai_result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'period', 'period_start', 'period_end'], 'ai_reports_tenant_period_idx');
            $table->index(['tenant_id', 'branch_id', 'created_at'], 'ai_reports_tenant_branch_created_idx');
            $table->index(['tenant_id', 'status'], 'ai_reports_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insight_reports');
    }
};
