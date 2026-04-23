<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('car_documents', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('issue_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('car_documents', function (Blueprint $table) {
            if (Schema::hasColumn('car_documents', 'purchase_date')) {
                $table->dropColumn('purchase_date');
            }
        });
    }
};
