<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'run_status') THEN
                    CREATE TYPE run_status AS ENUM ('QUEUED', 'RUNNING', 'COMPLETED', 'PARTIAL', 'FAILED', 'CANCELLED');
                END IF;
            END \$\$;
        ");
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'task_status') THEN
                    CREATE TYPE task_status AS ENUM ('QUEUED', 'LEASED', 'RUNNING', 'RETRY_WAIT', 'COMPLETED', 'FAILED', 'CANCELLED');
                END IF;
            END \$\$;
        ");
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'error_category') THEN
                    CREATE TYPE error_category AS ENUM ('invalid_input', 'authentication_session', 'account_restricted', 'proxy_network', 'target_rate_limit', 'target_unavailable', 'selector_parse', 'content_not_found', 'resource_exhausted', 'worker_timeout', 'worker_crash', 'internal_system', 'billing_quota', 'cancelled');
                END IF;
            END \$\$;
        ");

        Schema::create('runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('actor_id')->nullable();
            $table->string('origin', 50)->nullable();
            $table->string('capability', 100);
            $table->string('scraper_contract_version', 50)->nullable();
            $table->string('request_id', 255)->nullable();
            $table->string('reference_id', 255)->nullable();
            $table->string('status')->default('QUEUED');
            $table->uuid('pricing_snapshot_id')->nullable();
            $table->jsonb('counters')->nullable();
            $table->string('error_category')->nullable();
            $table->jsonb('safe_error_metadata')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancel_requested_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->unique(['id', 'organization_id']);
        });

        Schema::create('run_requests', function (Blueprint $table) {
            $table->uuid('run_id')->primary();
            $table->string('target_type', 100)->nullable();
            $table->text('target_url')->nullable();
            $table->text('normalized_target_url')->nullable();
            $table->uuid('source_canonical_identity_id')->nullable();
            $table->uuid('parent_canonical_identity_id')->nullable();
            $table->string('capability', 100)->nullable();
            $table->integer('limit_value')->nullable();
            $table->jsonb('options')->nullable();
            $table->string('reference_id', 255)->nullable();
            $table->jsonb('request_snapshot')->nullable();
            $table->string('scraper_contract_version', 50)->nullable();
            $table->string('payload_version', 50)->nullable();
            $table->foreign('run_id')->references('id')->on('runs')->onDelete('restrict');
            $table->foreign('source_canonical_identity_id')->references('id')->on('canonical_entities')->onDelete('restrict');
            $table->foreign('parent_canonical_identity_id')->references('id')->on('canonical_entities')->onDelete('restrict');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('organization_id');
            $table->string('capability', 100)->nullable();
            $table->string('payload_version', 50)->nullable();
            $table->string('scraper_contract_version', 50)->nullable();
            $table->string('status')->default('QUEUED');
            $table->integer('attempt_count')->default(0);
            $table->string('max_attempts_reference', 50)->nullable();
            $table->timestampTz('next_retry_at')->nullable();
            $table->uuid('active_lease_id')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('heartbeat_at')->nullable();
            $table->string('worker_identity', 255)->nullable();
            $table->timestampTz('queued_at')->useCurrent();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('error_category')->nullable();
            $table->string('error_code', 255)->nullable();
            $table->jsonb('safe_error_metadata')->nullable();
            $table->foreign(['run_id', 'organization_id'])->references(['id', 'organization_id'])->on('runs')->onDelete('restrict');
            $table->unique(['id', 'run_id', 'organization_id']);
        });

        Schema::create('run_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('organization_id');
            $table->uuid('canonical_entity_id');
            $table->uuid('source_task_id');
            $table->string('billable_status', 50)->default('ACTIVE');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign(['run_id', 'organization_id'])->references(['id', 'organization_id'])->on('runs')->onDelete('restrict');
            $table->foreign(['source_task_id', 'run_id', 'organization_id'])->references(['id', 'run_id', 'organization_id'])->on('tasks')->onDelete('restrict');
            $table->foreign('canonical_entity_id')->references('id')->on('canonical_entities')->onDelete('restrict');
            $table->unique(['run_id', 'canonical_entity_id']);
        });

        Schema::create('task_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->uuid('run_id');
            $table->uuid('organization_id');
            $table->integer('attempt_number');
            $table->string('worker_identity', 255)->nullable();
            $table->uuid('account_lease_id')->nullable();
            $table->uuid('proxy_lease_id')->nullable();
            $table->string('outcome', 50)->nullable();
            $table->string('error_category')->nullable();
            $table->string('error_code', 255)->nullable();
            $table->jsonb('safe_diagnostics')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreign(['task_id', 'run_id', 'organization_id'])->references(['id', 'run_id', 'organization_id'])->on('tasks')->onDelete('restrict');
            $table->unique(['task_id', 'attempt_number']);
        });

        Schema::create('dead_letter_queue_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->uuid('run_id');
            $table->uuid('attempt_id');
            $table->string('error_category')->nullable();
            $table->string('error_code', 255)->nullable();
            $table->jsonb('safe_diagnostics')->nullable();
            $table->boolean('retry_exhausted')->default(false);
            $table->timestampTz('failed_at')->useCurrent();
            $table->string('operator_replay_reference', 255)->nullable();
            $table->timestampTz('reconciled_at')->nullable();
            $table->string('resolution', 255)->nullable();
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('restrict');
            $table->foreign('run_id')->references('id')->on('runs')->onDelete('restrict');
            $table->foreign('attempt_id')->references('id')->on('task_attempts')->onDelete('restrict');
        });

        Schema::create('task_leases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->string('worker_identity', 255);
            $table->timestampTz('acquired_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('heartbeat_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->string('release_reason', 255)->nullable();
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('restrict');
            $table->unique(['id', 'task_id']);
        });
        
        DB::statement("CREATE UNIQUE INDEX idx_task_leases_one_active ON task_leases (task_id) WHERE released_at IS NULL AND status IN ('ACTIVE')");

        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_active_lease_id_fkey FOREIGN KEY (active_lease_id, id) REFERENCES task_leases(id, task_id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE runs ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE runs ALTER COLUMN status TYPE run_status USING status::run_status');
        DB::statement("ALTER TABLE runs ALTER COLUMN status SET DEFAULT 'QUEUED'::run_status");
        DB::statement('ALTER TABLE tasks ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE tasks ALTER COLUMN status TYPE task_status USING status::task_status');
        DB::statement("ALTER TABLE tasks ALTER COLUMN status SET DEFAULT 'QUEUED'::task_status");
        DB::statement('ALTER TABLE runs ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE runs ALTER COLUMN error_category TYPE error_category USING error_category::error_category');
        DB::statement('ALTER TABLE tasks ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE tasks ALTER COLUMN error_category TYPE error_category USING error_category::error_category');
        DB::statement('ALTER TABLE task_attempts ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE task_attempts ALTER COLUMN error_category TYPE error_category USING error_category::error_category');
        DB::statement('ALTER TABLE dead_letter_queue_records ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE dead_letter_queue_records ALTER COLUMN error_category TYPE error_category USING error_category::error_category');

        DB::statement('ALTER TABLE runs ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE runs ALTER COLUMN status TYPE run_status USING status::run_status');
        DB::statement("ALTER TABLE runs ALTER COLUMN status SET DEFAULT 'QUEUED'::run_status");
        DB::statement('ALTER TABLE tasks ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE tasks ALTER COLUMN status TYPE task_status USING status::task_status');
        DB::statement("ALTER TABLE tasks ALTER COLUMN status SET DEFAULT 'QUEUED'::task_status");
        DB::statement('ALTER TABLE runs ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE runs ALTER COLUMN error_category TYPE error_category USING error_category::error_category');
        DB::statement('ALTER TABLE tasks ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE tasks ALTER COLUMN error_category TYPE error_category USING error_category::error_category');
        DB::statement('ALTER TABLE task_attempts ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE task_attempts ALTER COLUMN error_category TYPE error_category USING error_category::error_category');
        DB::statement('ALTER TABLE dead_letter_queue_records ALTER COLUMN error_category DROP DEFAULT');
        DB::statement('ALTER TABLE dead_letter_queue_records ALTER COLUMN error_category TYPE error_category USING error_category::error_category');
    }

    public function down(): void {
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_active_lease_id_fkey');
        Schema::dropIfExists('task_leases');
        Schema::dropIfExists('dead_letter_queue_records');
        Schema::dropIfExists('task_attempts');
        Schema::dropIfExists('run_results');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('run_requests');
        Schema::dropIfExists('runs');
        DB::statement('DROP TYPE IF EXISTS error_category');
        DB::statement('DROP TYPE IF EXISTS task_status');
        DB::statement('DROP TYPE IF EXISTS run_status');
    }
};
