<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_site_settings', 'mrta_pdf')) {
                $table->json('mrta_pdf')->nullable()->after('contract_pdf');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_site_settings', 'mrta_pdf')) {
                $table->dropColumn('mrta_pdf');
            }
        });
    }
};
