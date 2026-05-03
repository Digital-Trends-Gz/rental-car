<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_return_reports') && ! Schema::hasColumn('contract_return_reports', 'discount')) {
            Schema::table('contract_return_reports', function (Blueprint $table) {
                $table->decimal('discount', 10, 2)->default(0)->after('other_fee');
            });
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['name' => 'tenant-edit-return-reports', 'tenant_id' => null],
                [
                    'display_name' => 'Edit Return Reports',
                    'description' => 'Create and edit return status reports and their extra charges.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_return_reports') && Schema::hasColumn('contract_return_reports', 'discount')) {
            Schema::table('contract_return_reports', function (Blueprint $table) {
                $table->dropColumn('discount');
            });
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'tenant-edit-return-reports')
                ->whereNull('tenant_id')
                ->delete();
        }
    }
};
