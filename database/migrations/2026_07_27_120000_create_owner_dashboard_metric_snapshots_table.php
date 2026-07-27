<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_dashboard_metric_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('branch_scope');
            $table->string('metric_key');
            $table->date('metric_date');
            $table->decimal('value', 15, 2)->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_scope', 'metric_key', 'metric_date'], 'owner_metric_snapshot_unique');
            $table->index(['tenant_id', 'metric_date']);
            $table->index(['branch_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_dashboard_metric_snapshots');
    }
};
