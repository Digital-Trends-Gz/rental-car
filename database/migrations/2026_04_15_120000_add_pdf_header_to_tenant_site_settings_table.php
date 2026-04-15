<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_site_settings', 'pdf_header')) {
                $table->json('pdf_header')->nullable()->after('contact_page');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_site_settings', 'pdf_header')) {
                $table->dropColumn('pdf_header');
            }
        });
    }
};
