<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id_hash', 64);
            $table->string('source', 20)->default('web');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('session_id')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id_hash']);
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('user_device_id')
                ->nullable()
                ->after('tokenable_id')
                ->constrained('user_devices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_device_id');
        });

        Schema::dropIfExists('user_devices');
    }
};
