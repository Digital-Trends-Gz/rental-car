<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_site_settings', 'document_extraction_daily_limit')) {
                $table->unsignedInteger('document_extraction_daily_limit')
                    ->nullable()
                    ->after('tax_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_site_settings', 'document_extraction_daily_limit')) {
                $table->dropColumn('document_extraction_daily_limit');
            }
        });
    }
};
