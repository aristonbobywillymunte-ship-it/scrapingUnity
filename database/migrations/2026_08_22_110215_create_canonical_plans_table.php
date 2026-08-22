<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->integer('monthly_quota');
                $table->integer('rate_limit_rpm')->default(60);
                $table->integer('max_concurrency')->default(2);
                $table->jsonb('allowed_modes');
                $table->timestampsTz();
            });
            
            DB::table('plans')->insert([
                'name' => 'Default MVP Plan',
                'monthly_quota' => 1000000,
                'rate_limit_rpm' => 60,
                'max_concurrency' => 2,
                'allowed_modes' => json_encode(['http', 'browser']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasColumn('users', 'plan_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            });
            
            $defaultPlanId = DB::table('plans')->where('name', 'Default MVP Plan')->value('id');
            if ($defaultPlanId) {
                DB::table('users')->update(['plan_id' => $defaultPlanId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'plan_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
                $table->dropColumn('plan_id');
            });
        }
        Schema::dropIfExists('plans');
    }
};
