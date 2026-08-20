<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::unprepared('CREATE TYPE export_status AS ENUM (
    \'QUEUED\', \'PROCESSING\', \'READY\', \'EXPIRED\', \'FAILED\'
);

CREATE TYPE selector_version_status AS ENUM (
    \'DRAFT\', \'TESTING\', \'ACTIVE\', \'INACTIVE\', \'DEPRECATED\'
);

-- 1. exports
CREATE TABLE exports (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    requested_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    format VARCHAR(50) NOT NULL,
    status export_status NOT NULL DEFAULT \'QUEUED\',
    request_snapshot JSONB,
    retention_policy_snapshot JSONB,
    storage_reference VARCHAR(255),
    download_metadata JSONB,
    ready_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. selectors
CREATE TABLE selectors (
    id UUID PRIMARY KEY,
    platform VARCHAR(50) NOT NULL,
    scraper VARCHAR(100) NOT NULL,
    source VARCHAR(100) NOT NULL,
    page_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 3. selector_versions
CREATE TABLE selector_versions (
    id UUID PRIMARY KEY,
    selector_id UUID NOT NULL REFERENCES selectors(id) ON DELETE RESTRICT,
    status selector_version_status NOT NULL DEFAULT \'DRAFT\',
    version_tag VARCHAR(50) NOT NULL,
    selector_data JSONB NOT NULL,
    test_metadata JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX idx_selector_versions_active ON selector_versions(selector_id) WHERE status = \'ACTIVE\';

-- 4. search_indexing_states
CREATE TABLE search_indexing_states (
    id UUID PRIMARY KEY,
    index_name VARCHAR(255) NOT NULL UNIQUE,
    last_checkpoint VARCHAR(255),
    status VARCHAR(50) NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 5. system_maintenance
CREATE TABLE system_maintenance (
    id UUID PRIMARY KEY,
    scope VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    actor_id UUID REFERENCES users(id) ON DELETE RESTRICT,
    starts_at TIMESTAMPTZ NOT NULL,
    ends_at TIMESTAMPTZ NOT NULL,
    status VARCHAR(50) NOT NULL,
    config JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 6. audit_logs
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY,
    actor_id UUID,
    actor_type VARCHAR(50) NOT NULL,
    organization_id UUID REFERENCES organizations(id) ON DELETE RESTRICT,
    action VARCHAR(255) NOT NULL,
    target VARCHAR(255),
    request_id VARCHAR(255),
    safe_metadata JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE FUNCTION prevent_audit_logs_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION \'audit_logs is append-only\';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER trg_audit_logs_append_only
BEFORE UPDATE OR DELETE ON audit_logs
FOR EACH ROW
EXECUTE FUNCTION prevent_audit_logs_modification();

-- 7. security_events
CREATE TABLE security_events (
    id UUID PRIMARY KEY,
    event_type VARCHAR(255) NOT NULL,
    actor_id UUID,
    organization_id UUID REFERENCES organizations(id) ON DELETE RESTRICT,
    safe_context JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 8. ai_conversations
CREATE TABLE ai_conversations (
    id UUID PRIMARY KEY,
    actor_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    channel VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    safe_metadata JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 9. ai_messages
CREATE TABLE ai_messages (
    id UUID PRIMARY KEY,
    conversation_id UUID NOT NULL REFERENCES ai_conversations(id) ON DELETE RESTRICT,
    role VARCHAR(50) NOT NULL,
    content_text TEXT,
    idempotency_key VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 10. ai_tool_audits
CREATE TABLE ai_tool_audits (
    id UUID PRIMARY KEY,
    message_id UUID NOT NULL REFERENCES ai_messages(id) ON DELETE RESTRICT,
    tool_name VARCHAR(255) NOT NULL,
    safe_arguments JSONB,
    safe_result JSONB,
    execution_latency_ms INT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 11. ai_usage
CREATE TABLE ai_usage (
    id UUID PRIMARY KEY,
    message_id UUID NOT NULL REFERENCES ai_messages(id) ON DELETE RESTRICT,
    provider VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    prompt_tokens INT NOT NULL,
    completion_tokens INT NOT NULL,
    internal_cost_cents BIGINT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 12. reconciliation_runs
CREATE TABLE reconciliation_runs (
    id UUID PRIMARY KEY,
    type VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    actor_reference VARCHAR(255),
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ,
    safe_details JSONB
);

-- 13. reconciliation_findings
CREATE TABLE reconciliation_findings (
    id UUID PRIMARY KEY,
    reconciliation_run_id UUID NOT NULL REFERENCES reconciliation_runs(id) ON DELETE RESTRICT,
    finding_type VARCHAR(100) NOT NULL,
    object_reference VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    safe_details JSONB,
    detected_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    resolved_at TIMESTAMPTZ,
    resolution VARCHAR(255)
);
');
    }

    public function down()
    {
        // DB::unprepared(...);
    }
};
