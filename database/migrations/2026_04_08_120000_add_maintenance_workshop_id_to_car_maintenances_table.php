<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_maintenances', function (Blueprint $table) {
            $table->foreignId('maintenance_workshop_id')
                ->nullable()
                ->after('maintenance_type_id')
                ->constrained('maintenance_workshops')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('car_maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_workshop_id');
        });
    }
};
