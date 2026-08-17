<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            if (!Schema::hasColumn('cars', 'plan_locked_at')) {
                $table->timestamp('plan_locked_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('cars', 'plan_lock_reason')) {
                $table->string('plan_lock_reason')->nullable()->after('plan_locked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            if (Schema::hasColumn('cars', 'plan_lock_reason')) {
                $table->dropColumn('plan_lock_reason');
            }

            if (Schema::hasColumn('cars', 'plan_locked_at')) {
                $table->dropColumn('plan_locked_at');
            }
        });
    }
};
