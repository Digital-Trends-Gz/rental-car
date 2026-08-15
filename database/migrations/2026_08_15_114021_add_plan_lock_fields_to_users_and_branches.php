<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'plan_locked_at')) {
                $table->timestamp('plan_locked_at')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('users', 'plan_lock_reason')) {
                $table->string('plan_lock_reason')->nullable()->after('plan_locked_at');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'plan_locked_at')) {
                $table->timestamp('plan_locked_at')->nullable()->after('manager_civil_number');
            }

            if (!Schema::hasColumn('branches', 'plan_lock_reason')) {
                $table->string('plan_lock_reason')->nullable()->after('plan_locked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'plan_lock_reason')) {
                $table->dropColumn('plan_lock_reason');
            }

            if (Schema::hasColumn('users', 'plan_locked_at')) {
                $table->dropColumn('plan_locked_at');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'plan_lock_reason')) {
                $table->dropColumn('plan_lock_reason');
            }

            if (Schema::hasColumn('branches', 'plan_locked_at')) {
                $table->dropColumn('plan_locked_at');
            }
        });
    }
};
