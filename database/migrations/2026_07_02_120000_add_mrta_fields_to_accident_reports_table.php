<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accident_reports', function (Blueprint $table): void {
            $table->json('mrta_accident_types')->nullable()->after('third_party_details');
            $table->json('mrta_first_party')->nullable()->after('mrta_accident_types');
            $table->json('mrta_second_party')->nullable()->after('mrta_first_party');
            $table->json('mrta_witnesses')->nullable()->after('mrta_second_party');
            $table->json('mrta_accident_causes')->nullable()->after('mrta_witnesses');
            $table->json('mrta_vehicle_damages')->nullable()->after('mrta_accident_causes');
            $table->json('mrta_insurance')->nullable()->after('mrta_vehicle_damages');
            $table->json('mrta_signatures')->nullable()->after('mrta_insurance');
        });
    }

    public function down(): void
    {
        Schema::table('accident_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'mrta_accident_types',
                'mrta_first_party',
                'mrta_second_party',
                'mrta_witnesses',
                'mrta_accident_causes',
                'mrta_vehicle_damages',
                'mrta_insurance',
                'mrta_signatures',
            ]);
        });
    }
};
