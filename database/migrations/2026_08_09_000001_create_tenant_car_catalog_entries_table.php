<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_car_catalog_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('transmission')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedInteger('engine_power')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'make', 'model', 'year'], 'tenant_car_catalog_unique');
            $table->index(['tenant_id', 'make', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_car_catalog_entries');
    }
};
