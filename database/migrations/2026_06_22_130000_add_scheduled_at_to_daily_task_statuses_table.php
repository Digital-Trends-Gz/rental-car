<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_task_statuses', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('status');
            $table->index(['tenant_id', 'scheduled_at'], 'daily_task_statuses_tenant_scheduled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('daily_task_statuses', function (Blueprint $table) {
            $table->dropIndex('daily_task_statuses_tenant_scheduled_at_index');
            $table->dropColumn('scheduled_at');
        });
    }
};
