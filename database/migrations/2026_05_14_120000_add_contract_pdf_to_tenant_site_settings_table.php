<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_site_settings', 'contract_pdf')) {
                $table->json('contract_pdf')->nullable()->after('pdf_templates');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_site_settings', 'contract_pdf')) {
                $table->dropColumn('contract_pdf');
            }
        });
    }
};
