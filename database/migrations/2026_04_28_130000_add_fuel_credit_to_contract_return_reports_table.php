<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_return_reports', function (Blueprint $table) {
            $table->decimal('fuel_credit', 10, 2)->default(0)->after('fuel_fee');
        });
    }

    public function down(): void
    {
        Schema::table('contract_return_reports', function (Blueprint $table) {
            $table->dropColumn('fuel_credit');
        });
    }
};
