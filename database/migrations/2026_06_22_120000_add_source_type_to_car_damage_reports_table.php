<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_damage_reports', function (Blueprint $table): void {
            $table->string('source_type', 20)->default('employee')->after('created_by');
        });

        DB::table('car_damage_reports')
            ->whereNull('source_type')
            ->update(['source_type' => 'employee']);
    }

    public function down(): void
    {
        Schema::table('car_damage_reports', function (Blueprint $table): void {
            $table->dropColumn('source_type');
        });
    }
};
