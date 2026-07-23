<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_site_settings') || Schema::hasColumn('tenant_site_settings', 'static_pages')) {
            return;
        }

        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            $table->json('static_pages')->nullable()->after('contact_page');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_site_settings') || ! Schema::hasColumn('tenant_site_settings', 'static_pages')) {
            return;
        }

        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            $table->dropColumn('static_pages');
        });
    }
};
