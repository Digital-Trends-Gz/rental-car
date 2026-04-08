<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_workshops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_type_id')->constrained('maintenance_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->decimal('rate', 3, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'maintenance_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_workshops');
    }
};
