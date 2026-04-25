<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_employees')->nullable()->after('one_time_price_id');
            $table->unsignedInteger('max_branches')->nullable()->after('max_employees');
            $table->unsignedInteger('max_cars')->nullable()->after('max_branches');
            $table->unsignedInteger('max_contracts')->nullable()->after('max_cars');
            $table->unsignedInteger('openai_requests_per_day')->nullable()->after('max_contracts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'max_employees',
                'max_branches',
                'max_cars',
                'max_contracts',
                'openai_requests_per_day',
            ]);
        });
    }
};
