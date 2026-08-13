<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (!Schema::hasColumn('plans', 'max_reservations_per_month')) {
                $table->unsignedInteger('max_reservations_per_month')->nullable()->after('max_contracts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'max_reservations_per_month')) {
                $table->dropColumn('max_reservations_per_month');
            }
        });
    }
};
