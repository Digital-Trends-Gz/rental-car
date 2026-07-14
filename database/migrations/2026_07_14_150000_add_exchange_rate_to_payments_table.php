<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (!Schema::hasColumn('payments', 'base_currency')) {
                $table->string('base_currency', 3)->nullable()->after('currency');
            }

            if (!Schema::hasColumn('payments', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->default(1)->after('base_currency');
            }

            if (!Schema::hasColumn('payments', 'base_amount')) {
                $table->decimal('base_amount', 12, 2)->nullable()->after('exchange_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            foreach (['base_amount', 'exchange_rate', 'base_currency'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
