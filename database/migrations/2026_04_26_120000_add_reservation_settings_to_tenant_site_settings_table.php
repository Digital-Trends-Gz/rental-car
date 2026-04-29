<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_site_settings', 'reservation_settings')) {
                $table->json('reservation_settings')->nullable()->after('police_notice');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_site_settings', 'reservation_settings')) {
                $table->dropColumn('reservation_settings');
            }
        });
    }
};
