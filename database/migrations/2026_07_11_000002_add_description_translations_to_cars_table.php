<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table): void {
            if (! Schema::hasColumn('cars', 'description_translations')) {
                $table->json('description_translations')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table): void {
            if (Schema::hasColumn('cars', 'description_translations')) {
                $table->dropColumn('description_translations');
            }
        });
    }
};
