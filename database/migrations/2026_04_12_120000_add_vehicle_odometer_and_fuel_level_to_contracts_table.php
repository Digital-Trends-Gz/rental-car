<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedInteger('vehicle_odometer')->nullable()->after('plate_number');
            $table->string('vehicle_fuel_level', 50)->nullable()->after('vehicle_odometer');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['vehicle_odometer', 'vehicle_fuel_level']);
        });
    }
};
