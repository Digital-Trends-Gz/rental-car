<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_documents', function (Blueprint $table) {
            $table->timestamp('five_day_reminder_sent_at')->nullable()->after('ten_day_reminder_sent_at');
            $table->timestamp('three_day_reminder_sent_at')->nullable()->after('five_day_reminder_sent_at');
            $table->timestamp('one_day_reminder_sent_at')->nullable()->after('three_day_reminder_sent_at');
            $table->timestamp('expiry_day_reminder_sent_at')->nullable()->after('one_day_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('car_documents', function (Blueprint $table) {
            $table->dropColumn([
                'five_day_reminder_sent_at',
                'three_day_reminder_sent_at',
                'one_day_reminder_sent_at',
                'expiry_day_reminder_sent_at',
            ]);
        });
    }
};
