<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contract_drivers', 'visa_number')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->string('visa_number')->nullable()->after('passport_expiry_date');
            });
        }

        if (! Schema::hasColumn('contract_drivers', 'visa_expiry_date')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->date('visa_expiry_date')->nullable()->after('visa_number');
            });
        }

        if (! Schema::hasColumn('contracts', 'vehicle_condition_before')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('vehicle_condition_before')->nullable()->after('return_fuel_level');
            });
        }

        if (! Schema::hasColumn('contracts', 'vehicle_condition_after')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('vehicle_condition_after')->nullable()->after('vehicle_condition_before');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_drivers', 'visa_expiry_date')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->dropColumn('visa_expiry_date');
            });
        }

        if (Schema::hasColumn('contract_drivers', 'visa_number')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->dropColumn('visa_number');
            });
        }

        if (Schema::hasColumn('contracts', 'vehicle_condition_after')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('vehicle_condition_after');
            });
        }

        if (Schema::hasColumn('contracts', 'vehicle_condition_before')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('vehicle_condition_before');
            });
        }
    }
};
