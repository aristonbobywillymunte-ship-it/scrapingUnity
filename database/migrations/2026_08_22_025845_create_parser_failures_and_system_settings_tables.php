<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1 Forward Migration:
     * 1. `parser_failures`: Tracks parser failure incidents (PRD §14, §18 Parser Failures)
     * 2. `system_settings`: Safe mutable in-app system settings (PRD §17, §18 Settings)
     *
     * Non-destructive, append-only forward migration. Historical migrations preserved.
     */
    public function up(): void
    {
        if (!Schema::hasTable('parser_failures')) {
            Schema::create('parser_failures', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('platform', 50);
                $table->string('operation', 100);
                $table->string('parser_version', 50)->nullable();
                $table->string('failure_class', 100)->default('STRUCTURAL_MISMATCH');
                $table->text('error_message')->nullable();
                $table->jsonb('field_coverage')->nullable();
                $table->string('target_url', 500)->nullable();
                $table->uuid('task_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->string('category', 50)->default('general');
                $table->string('description', 255)->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            // Seed default PRD settings safely
            $now = now();
            \Illuminate\Support\Facades\DB::table('system_settings')->insertOrIgnore([
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'key' => 'results_retention_days',
                    'value' => '30',
                    'category' => 'retention',
                    'description' => 'Durasi penyimpanan hasil scraping ter-normalisasi (hari)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'key' => 'diagnostic_retention_hours',
                    'value' => '72',
                    'category' => 'retention',
                    'description' => 'Durasi penyimpanan diagnostik DOM / error snapshot (jam)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'key' => 'logs_retention_days',
                    'value' => '14',
                    'category' => 'retention',
                    'description' => 'Durasi retensi operational logs (hari)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'key' => 'max_browser_concurrency',
                    'value' => '1',
                    'category' => 'concurrency',
                    'description' => 'Batas konkurensi scraping berbasis browser (Playwright)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'key' => 'max_http_concurrency',
                    'value' => '4',
                    'category' => 'concurrency',
                    'description' => 'Batas konkurensi scraping HTTP worker',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parser_failures');
        Schema::dropIfExists('system_settings');
    }
};
