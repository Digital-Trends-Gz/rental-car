<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('description');
        });

        DB::table('plans')
            ->orderBy('monthly_price')
            ->orderBy('id')
            ->select(['id'])
            ->get()
            ->values()
            ->each(function ($plan, int $index): void {
                DB::table('plans')
                    ->where('id', $plan->id)
                    ->update(['sort_order' => $index]);
            });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
