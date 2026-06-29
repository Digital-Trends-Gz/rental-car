<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('site_name')->nullable();
            $table->string('logo_url', 1000)->nullable();
            $table->string('primary_color', 20)->default('#f97316');
            $table->string('secondary_color', 20)->default('#ea580c');
            $table->json('market_location')->nullable();
            $table->json('hero')->nullable();
            $table->json('contact')->nullable();
            $table->json('footer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_site_settings');
    }
};
