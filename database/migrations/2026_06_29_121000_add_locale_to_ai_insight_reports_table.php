<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_insight_reports')) {
            return;
        }

        if (Schema::hasColumn('ai_insight_reports', 'locale')) {
            return;
        }

        Schema::table('ai_insight_reports', function (Blueprint $table): void {
            $table->string('locale', 10)->default('en')->after('period');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ai_insight_reports')) {
            return;
        }

        if (!Schema::hasColumn('ai_insight_reports', 'locale')) {
            return;
        }

        Schema::table('ai_insight_reports', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
