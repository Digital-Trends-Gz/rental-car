<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_damage_items', function (Blueprint $table): void {
            $table->string('source_type', 20)->default('employee')->after('car_damage_report_id');
        });

        $aiReportIds = DB::table('car_damage_reports')
            ->where('source_type', 'ai')
            ->select('id');

        DB::table('car_damage_items')
            ->whereIn('car_damage_report_id', $aiReportIds)
            ->update(['source_type' => 'ai']);
    }

    public function down(): void
    {
        Schema::table('car_damage_items', function (Blueprint $table): void {
            $table->dropColumn('source_type');
        });
    }
};
