<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_site_settings') || Schema::hasColumn('tenant_site_settings', 'market_location')) {
            return;
        }

        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            $table->json('market_location')->nullable()->after('secondary_color');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_site_settings') || !Schema::hasColumn('tenant_site_settings', 'market_location')) {
            return;
        }

        Schema::table('tenant_site_settings', function (Blueprint $table): void {
            $table->dropColumn('market_location');
        });
    }
};
