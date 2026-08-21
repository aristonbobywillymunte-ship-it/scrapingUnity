<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'export_status') THEN
                    CREATE TYPE export_status AS ENUM ('QUEUED', 'PROCESSING', 'READY', 'EXPIRED', 'FAILED');
                END IF;
            END \$\$;
        ");
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'selector_version_status') THEN
                    CREATE TYPE selector_version_status AS ENUM ('DRAFT', 'TESTING', 'ACTIVE', 'INACTIVE', 'DEPRECATED');
                END IF;
            END \$\$;
        ");

        Schema::create('exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('requested_by');
            $table->string('format', 50);
            $table->string('status')->default('QUEUED');
            $table->jsonb('request_snapshot')->nullable();
            $table->jsonb('retention_policy_snapshot')->nullable();
            $table->string('storage_reference', 255)->nullable();
            $table->jsonb('download_metadata')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('selectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform', 50);
            $table->string('scraper', 100);
            $table->string('source', 100);
            $table->string('page_type', 100);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('selector_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('selector_id');
            $table->string('status')->default('DRAFT');
            $table->string('version_tag', 50);
            $table->jsonb('selector_data');
            $table->jsonb('test_metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('selector_id')->references('id')->on('selectors')->onDelete('restrict');
        });
        // index recreated below

        Schema::create('search_indexing_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('index_name', 255)->unique();
            $table->string('last_checkpoint', 255)->nullable();
            $table->string('status', 50);
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('system_maintenance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope', 255);
            $table->text('reason');
            $table->uuid('actor_id')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 50);
            $table->jsonb('config')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('actor_type', 50);
            $table->uuid('organization_id')->nullable();
            $table->string('action', 255);
            $table->string('target', 255)->nullable();
            $table->string('request_id', 255)->nullable();
            $table->jsonb('safe_metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
        });
        
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_audit_logs_modification()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_audit_logs_append_only
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_audit_logs_modification();
        ");

        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type', 255);
            $table->uuid('actor_id')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->jsonb('safe_context')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id');
            $table->string('channel', 100);
            $table->string('status', 50)->default('ACTIVE');
            $table->jsonb('safe_metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->string('role', 50);
            $table->text('content_text')->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->onDelete('restrict');
        });

        Schema::create('ai_tool_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->string('tool_name', 255);
            $table->jsonb('safe_arguments')->nullable();
            $table->jsonb('safe_result')->nullable();
            $table->integer('execution_latency_ms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('message_id')->references('id')->on('ai_messages')->onDelete('restrict');
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->string('provider', 100);
            $table->string('model', 100);
            $table->integer('prompt_tokens');
            $table->integer('completion_tokens');
            $table->bigInteger('internal_cost_cents');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('message_id')->references('id')->on('ai_messages')->onDelete('restrict');
        });

        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 100);
            $table->string('status', 50);
            $table->string('actor_reference', 255)->nullable();
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('safe_details')->nullable();
        });

        Schema::create('reconciliation_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reconciliation_run_id');
            $table->string('finding_type', 100);
            $table->string('object_reference', 255);
            $table->string('status', 50);
            $table->jsonb('safe_details')->nullable();
            $table->timestampTz('detected_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolution', 255)->nullable();
            $table->foreign('reconciliation_run_id')->references('id')->on('reconciliation_runs')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE exports ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE exports ALTER COLUMN status TYPE export_status USING status::export_status');
        DB::statement("ALTER TABLE exports ALTER COLUMN status SET DEFAULT 'QUEUED'::export_status");
        DB::statement('ALTER TABLE selector_versions ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE selector_versions ALTER COLUMN status TYPE selector_version_status USING status::selector_version_status');
        DB::statement("ALTER TABLE selector_versions ALTER COLUMN status SET DEFAULT 'DRAFT'::selector_version_status");
    }

    public function down(): void {
        Schema::dropIfExists('reconciliation_findings');
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_tool_audits');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('security_events');
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_audit_logs_append_only ON audit_logs;
            DROP FUNCTION IF EXISTS prevent_audit_logs_modification();
        ");
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_maintenance');
        Schema::dropIfExists('search_indexing_states');
        Schema::dropIfExists('selector_versions');
        Schema::dropIfExists('selectors');
        Schema::dropIfExists('exports');
        DB::statement('DROP TYPE IF EXISTS selector_version_status');
        DB::statement('DROP TYPE IF EXISTS export_status');
    }
};
