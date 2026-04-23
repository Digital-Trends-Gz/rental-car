<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('cr_number')->nullable()->after('whatsapp');
            $table->string('manager_name')->nullable()->after('cr_number');
            $table->string('manager_civil_number')->nullable()->after('manager_name');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'cr_number',
                'manager_name',
                'manager_civil_number',
            ]);
        });
    }
};
