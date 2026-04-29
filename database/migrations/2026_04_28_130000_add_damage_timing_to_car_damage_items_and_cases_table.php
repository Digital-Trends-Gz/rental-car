<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_damage_items', function (Blueprint $table) {
            $table->string('damage_timing')->nullable()->after('severity');
        });

        Schema::table('car_damage_cases', function (Blueprint $table) {
            $table->string('damage_timing')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('car_damage_items', function (Blueprint $table) {
            $table->dropColumn('damage_timing');
        });

        Schema::table('car_damage_cases', function (Blueprint $table) {
            $table->dropColumn('damage_timing');
        });
    }
};
