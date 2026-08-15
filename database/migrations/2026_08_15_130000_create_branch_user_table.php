<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branch_user')) {
            Schema::create('branch_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['branch_id', 'user_id']);
                $table->index(['tenant_id', 'user_id']);
            });
        }

        if (Schema::hasColumn('users', 'branch_id')) {
            DB::table('users')
                ->whereNotNull('branch_id')
                ->whereNotNull('tenant_id')
                ->orderBy('id')
                ->select(['id', 'tenant_id', 'branch_id'])
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        DB::table('branch_user')->updateOrInsert(
                            [
                                'branch_id' => $user->branch_id,
                                'user_id' => $user->id,
                            ],
                            [
                                'tenant_id' => $user->tenant_id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
    }
};
