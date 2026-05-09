<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_site_settings', 'default_locale')) {
                $table->string('default_locale', 10)->nullable()->after('tax_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_site_settings', 'default_locale')) {
                $table->dropColumn('default_locale');
            }
        });
    }
};
