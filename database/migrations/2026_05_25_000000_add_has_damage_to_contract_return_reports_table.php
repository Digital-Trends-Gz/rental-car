<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_return_reports', function (Blueprint $table): void {
            if (!Schema::hasColumn('contract_return_reports', 'has_damage')) {
                $table->boolean('has_damage')->nullable()->after('vehicle_condition_after');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contract_return_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('contract_return_reports', 'has_damage')) {
                $table->dropColumn('has_damage');
            }
        });
    }
};
