<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('contracts', 'price_per_day')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->decimal('price_per_day', 12, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'price_per_week')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->decimal('price_per_week', 12, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'price_per_month')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->decimal('price_per_month', 12, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'allowed_km_per_day')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedInteger('allowed_km_per_day')->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'allowed_km_per_week')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedInteger('allowed_km_per_week')->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'allowed_km_per_month')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedInteger('allowed_km_per_month')->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'return_odometer')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedInteger('return_odometer')->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'return_fuel_level')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('return_fuel_level', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('contracts', 'actual_return_time')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dateTime('actual_return_time')->nullable();
            });
        }

        if (! Schema::hasColumn('contract_drivers', 'passport_number')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->string('passport_number')->nullable();
            });
        }

        if (! Schema::hasColumn('contract_drivers', 'passport_expiry_date')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->date('passport_expiry_date')->nullable();
            });
        }

        if (! Schema::hasColumn('contract_drivers', 'license_issue_date')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->date('license_issue_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_drivers', 'passport_number')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->dropColumn('passport_number');
            });
        }

        if (Schema::hasColumn('contract_drivers', 'passport_expiry_date')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->dropColumn('passport_expiry_date');
            });
        }

        if (Schema::hasColumn('contract_drivers', 'license_issue_date')) {
            Schema::table('contract_drivers', function (Blueprint $table) {
                $table->dropColumn('license_issue_date');
            });
        }

        foreach ([
            'price_per_day',
            'price_per_week',
            'price_per_month',
            'allowed_km_per_day',
            'allowed_km_per_week',
            'allowed_km_per_month',
            'return_odometer',
            'return_fuel_level',
            'actual_return_time',
        ] as $column) {
            if (Schema::hasColumn('contracts', $column)) {
                Schema::table('contracts', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
