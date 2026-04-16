<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_site_settings', 'police_notice')) {
                $table->json('police_notice')->nullable()->after('pdf_header');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_site_settings', 'police_notice')) {
                $table->dropColumn('police_notice');
            }
        });
    }
};
