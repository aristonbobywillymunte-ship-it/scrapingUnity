<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0-2: Forward migration to add proxy health observability columns to the
     * runtime `proxies` table. These columns are required by:
     * - PRD §12: "health score", "latency", "success/failure counters"
     * - ERD §15 proxy_servers: health_score, avg_latency_ms, success_count_24h, failure_count_24h
     *
     * This is a NON-DESTRUCTIVE forward migration. Existing rows receive safe
     * defaults. No historical migrations are modified.
     */
    public function up(): void
    {
        Schema::table('proxies', function (Blueprint $table) {
            // Health score 0-100 (100 = fully healthy, unknown starts at 100)
            $table->integer('health_score')->default(100)->after('health_status');
            // Measured round-trip latency in ms; 0 means not yet measured
            $table->integer('avg_latency_ms')->default(0)->after('health_score');
            // Rolling 24h success count; reset by worker
            $table->integer('success_count_24h')->default(0)->after('avg_latency_ms');
            // Rolling 24h failure count; reset by worker
            $table->integer('failure_count_24h')->default(0)->after('success_count_24h');
            // ISO 3166-1 alpha-2 country code; nullable until set by operator
            $table->string('country_code', 2)->nullable()->after('failure_count_24h');
            // Proxy type per ERD: datacenter, residential, mobile
            $table->string('proxy_type', 30)->default('datacenter')->after('country_code');
        });

        // Enforce health_score range constraint at DB level
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE proxies ADD CONSTRAINT chk_proxy_health_score CHECK (health_score >= 0 AND health_score <= 100)'
        );

        // Index for score-based selection query (PRD §12: score-based selection)
        \Illuminate\Support\Facades\DB::statement(
            'CREATE INDEX idx_proxies_health ON proxies(health_status, health_score) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE proxies DROP CONSTRAINT IF EXISTS chk_proxy_health_score'
        );
        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS idx_proxies_health'
        );

        Schema::table('proxies', function (Blueprint $table) {
            $table->dropColumn([
                'health_score',
                'avg_latency_ms',
                'success_count_24h',
                'failure_count_24h',
                'country_code',
                'proxy_type',
            ]);
        });
    }
};
