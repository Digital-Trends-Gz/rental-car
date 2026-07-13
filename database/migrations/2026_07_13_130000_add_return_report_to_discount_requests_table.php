<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('discount_requests') || Schema::hasColumn('discount_requests', 'contract_return_report_id')) {
            return;
        }

        Schema::table('discount_requests', function (Blueprint $table) {
            $table->foreignId('contract_return_report_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('contract_return_reports')
                ->nullOnDelete();

            $table->index(['contract_return_report_id', 'status']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('discount_requests') || !Schema::hasColumn('discount_requests', 'contract_return_report_id')) {
            return;
        }

        Schema::table('discount_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_return_report_id');
        });
    }
};
