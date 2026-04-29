<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('contract_return_reports')
            ->whereNull('payment_id')
            ->update(['payment_status' => 'not_paid']);

        DB::table('contract_return_reports')
            ->whereNotNull('payment_id')
            ->update(['payment_status' => 'paid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('contract_return_reports')
            ->whereNull('payment_id')
            ->update(['payment_status' => 'paid']);

        DB::table('contract_return_reports')
            ->whereNotNull('payment_id')
            ->update(['payment_status' => 'paid']);
    }
};
