<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accident_reports', function (Blueprint $table): void {
            $table->foreignId('contract_id')->nullable()->change();
            $table->foreignId('employee_id')->nullable()->after('reported_by')->constrained('users')->nullOnDelete();
            $table->string('accident_context', 30)->default('contract')->after('employee_id');
            $table->string('responsibility', 50)->nullable()->after('accident_context');
            $table->string('location_type', 50)->nullable()->after('responsibility');
        });
    }

    public function down(): void
    {
        Schema::table('accident_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['accident_context', 'responsibility', 'location_type']);
            $table->foreignId('contract_id')->nullable(false)->change();
        });
    }
};
