<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_workshops', function (Blueprint $table) {
            $table->string('country', 10)->nullable()->after('rate');
            $table->string('city')->nullable()->after('country');
            $table->string('street_name')->nullable()->after('city');
            $table->string('street_number', 50)->nullable()->after('street_name');
            $table->string('building_number', 50)->nullable()->after('street_number');
            $table->string('office_number', 50)->nullable()->after('building_number');
            $table->string('post_code', 50)->nullable()->after('office_number');
            $table->string('google_map_url', 1000)->nullable()->after('post_code');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_workshops', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'city',
                'street_name',
                'street_number',
                'building_number',
                'office_number',
                'post_code',
                'google_map_url',
            ]);
        });
    }
};
