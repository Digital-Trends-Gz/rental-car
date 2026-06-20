<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('notification_key', 160);
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['user_id', 'notification_key'], 'operational_notification_reads_user_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notification_reads');
    }
};
