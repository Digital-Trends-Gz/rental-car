<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            if (! Schema::hasColumn('cars', 'price_per_week')) {
                $table->decimal('price_per_week', 10, 2)->nullable()->after('price_per_day');
            }

            if (! Schema::hasColumn('cars', 'price_per_month')) {
                $table->decimal('price_per_month', 10, 2)->nullable()->after('price_per_week');
            }

            if (! Schema::hasColumn('cars', 'allowed_km_per_day')) {
                $table->unsignedInteger('allowed_km_per_day')->nullable()->after('price_per_month');
            }

            if (! Schema::hasColumn('cars', 'allowed_km_per_week')) {
                $table->unsignedInteger('allowed_km_per_week')->nullable()->after('allowed_km_per_day');
            }

            if (! Schema::hasColumn('cars', 'allowed_km_per_month')) {
                $table->unsignedInteger('allowed_km_per_month')->nullable()->after('allowed_km_per_week');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            if (Schema::hasColumn('cars', 'allowed_km_per_month')) {
                $table->dropColumn('allowed_km_per_month');
            }

            if (Schema::hasColumn('cars', 'allowed_km_per_week')) {
                $table->dropColumn('allowed_km_per_week');
            }

            if (Schema::hasColumn('cars', 'allowed_km_per_day')) {
                $table->dropColumn('allowed_km_per_day');
            }

            if (Schema::hasColumn('cars', 'price_per_month')) {
                $table->dropColumn('price_per_month');
            }

            if (Schema::hasColumn('cars', 'price_per_week')) {
                $table->dropColumn('price_per_week');
            }
        });
    }
};
