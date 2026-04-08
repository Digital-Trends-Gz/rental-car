<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('car_violations', function (Blueprint $table) {
            $table->foreignId('violation_type_id')
                ->nullable()
                ->after('violation_date')
                ->constrained('violation_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('car_violations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('violation_type_id');
        });

        Schema::dropIfExists('violation_types');
    }
};
