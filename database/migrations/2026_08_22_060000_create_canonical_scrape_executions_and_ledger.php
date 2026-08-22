<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward migration for canonical scrape_executions, parser_validation_runs, and usage_ledger.
     */
    public function up(): void
    {
        if (!Schema::hasTable('scrape_executions')) {
            Schema::create('scrape_executions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('request_fingerprint', 64)->index();
                $table->string('platform', 32)->index();
                $table->string('operation', 64)->index();
                $table->string('target_type', 32);
                $table->text('target_value');
                $table->jsonb('options')->nullable();
                $table->string('status', 32)->default('QUEUED')->index(); // QUEUED, PROCESSING, COMPLETED, FAILED
                $table->string('transport_mode', 32)->nullable();
                $table->integer('http_status_code')->nullable();
                $table->string('classification', 64)->nullable();
                $table->integer('elapsed_ms')->nullable();
                $table->integer('items_count')->default(0);
                $table->text('error_message')->nullable();
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('usage_ledger')) {
            Schema::create('usage_ledger', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('job_id')->index();
                $table->string('platform', 32);
                $table->string('operation', 64);
                $table->integer('records_delivered')->default(0);
                $table->string('resolution', 32)->default('upstream'); // upstream, cache, coalesced
                $table->timestampTz('recorded_at');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('parser_validation_runs')) {
            Schema::create('parser_validation_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('candidate_id')->nullable()->index();
                $table->string('platform', 32);
                $table->string('operation', 64);
                $table->string('parser_version', 32);
                $table->string('validator_engine', 32)->default('PYTHON');
                $table->boolean('is_valid')->default(false);
                $table->float('coverage_score')->default(0.0);
                $table->jsonb('field_results')->nullable();
                $table->text('validation_output')->nullable();
                $table->timestampTz('run_at');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('scraping_jobs')) {
            Schema::table('scraping_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('scraping_jobs', 'resolution')) {
                    $table->string('resolution', 32)->default('UPSTREAM')->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parser_validation_runs');
        Schema::dropIfExists('usage_ledger');
        Schema::dropIfExists('scrape_executions');
    }
};
