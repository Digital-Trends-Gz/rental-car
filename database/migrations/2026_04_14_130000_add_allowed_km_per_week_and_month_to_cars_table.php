<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table): void {
            if (! Schema::hasColumn('cars', 'allowed_km_per_day')) {
                $table->unsignedInteger('allowed_km_per_day')->nullable();
            }

            if (! Schema::hasColumn('cars', 'allowed_km_per_week')) {
                $table->unsignedInteger('allowed_km_per_week')->nullable();
            }

            if (! Schema::hasColumn('cars', 'allowed_km_per_month')) {
                $table->unsignedInteger('allowed_km_per_month')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table): void {
            if (Schema::hasColumn('cars', 'allowed_km_per_month')) {
                $table->dropColumn('allowed_km_per_month');
            }

            if (Schema::hasColumn('cars', 'allowed_km_per_week')) {
                $table->dropColumn('allowed_km_per_week');
            }
        });
    }
};
