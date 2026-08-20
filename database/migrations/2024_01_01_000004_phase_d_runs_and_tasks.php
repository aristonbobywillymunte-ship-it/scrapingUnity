<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::unprepared('CREATE TYPE run_status AS ENUM (
    \'QUEUED\', \'RUNNING\', \'COMPLETED\', \'PARTIAL\', \'FAILED\', \'CANCELLED\'
);

CREATE TYPE task_status AS ENUM (
    \'QUEUED\', \'LEASED\', \'RUNNING\', \'RETRY_WAIT\', \'COMPLETED\', \'FAILED\', \'CANCELLED\'
);

CREATE TYPE error_category AS ENUM (
    \'invalid_input\', \'authentication_session\', \'account_restricted\', \'proxy_network\',
    \'target_rate_limit\', \'target_unavailable\', \'selector_parse\', \'content_not_found\',
    \'resource_exhausted\', \'worker_timeout\', \'worker_crash\', \'internal_system\',
    \'billing_quota\', \'cancelled\'
);

-- 1. runs
CREATE TABLE runs (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    actor_id UUID,
    origin VARCHAR(50),
    capability VARCHAR(100) NOT NULL,
    scraper_contract_version VARCHAR(50),
    request_id VARCHAR(255),
    reference_id VARCHAR(255),
    status run_status NOT NULL DEFAULT \'QUEUED\',
    pricing_snapshot_id UUID,
    counters JSONB,
    error_category error_category,
    safe_error_metadata JSONB,
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    cancel_requested_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (id, organization_id)
);

-- 2. run_requests
CREATE TABLE run_requests (
    run_id UUID PRIMARY KEY REFERENCES runs(id) ON DELETE RESTRICT,
    target_type VARCHAR(100),
    target_url TEXT,
    normalized_target_url TEXT,
    source_canonical_identity_id UUID REFERENCES canonical_entities(id) ON DELETE RESTRICT,
    parent_canonical_identity_id UUID REFERENCES canonical_entities(id) ON DELETE RESTRICT,
    capability VARCHAR(100),
    limit_value INT,
    options JSONB,
    reference_id VARCHAR(255),
    request_snapshot JSONB,
    scraper_contract_version VARCHAR(50),
    payload_version VARCHAR(50)
);

-- 3. tasks
CREATE TABLE tasks (
    id UUID PRIMARY KEY,
    run_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    capability VARCHAR(100),
    payload_version VARCHAR(50),
    scraper_contract_version VARCHAR(50),
    status task_status NOT NULL DEFAULT \'QUEUED\',
    attempt_count INT NOT NULL DEFAULT 0,
    max_attempts_reference VARCHAR(50),
    next_retry_at TIMESTAMPTZ,
    active_lease_id UUID,
    lease_expires_at TIMESTAMPTZ,
    heartbeat_at TIMESTAMPTZ,
    worker_identity VARCHAR(255),
    queued_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    error_category error_category,
    error_code VARCHAR(255),
    safe_error_metadata JSONB,
    FOREIGN KEY (run_id, organization_id) REFERENCES runs(id, organization_id) ON DELETE RESTRICT,
    UNIQUE (id, run_id, organization_id)
);

-- 4. run_results
CREATE TABLE run_results (
    id UUID PRIMARY KEY,
    run_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    canonical_entity_id UUID NOT NULL REFERENCES canonical_entities(id) ON DELETE RESTRICT,
    source_task_id UUID NOT NULL,
    billable_status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (run_id, organization_id) REFERENCES runs(id, organization_id) ON DELETE RESTRICT,
    FOREIGN KEY (source_task_id, run_id, organization_id) REFERENCES tasks(id, run_id, organization_id) ON DELETE RESTRICT,
    UNIQUE (run_id, canonical_entity_id)
);

-- 5. task_attempts
CREATE TABLE task_attempts (
    id UUID PRIMARY KEY,
    task_id UUID NOT NULL,
    run_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    attempt_number INT NOT NULL,
    worker_identity VARCHAR(255),
    account_lease_id UUID,
    proxy_lease_id UUID,
    outcome VARCHAR(50),
    error_category error_category,
    error_code VARCHAR(255),
    safe_diagnostics JSONB,
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    FOREIGN KEY (task_id, run_id, organization_id) REFERENCES tasks(id, run_id, organization_id) ON DELETE RESTRICT,
    UNIQUE (task_id, attempt_number)
);

-- 6. dead_letter_queue_records
CREATE TABLE dead_letter_queue_records (
    id UUID PRIMARY KEY,
    task_id UUID NOT NULL REFERENCES tasks(id) ON DELETE RESTRICT,
    run_id UUID NOT NULL REFERENCES runs(id) ON DELETE RESTRICT,
    attempt_id UUID NOT NULL REFERENCES task_attempts(id) ON DELETE RESTRICT,
    error_category error_category,
    error_code VARCHAR(255),
    safe_diagnostics JSONB,
    retry_exhausted BOOLEAN NOT NULL DEFAULT false,
    failed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    operator_replay_reference VARCHAR(255),
    reconciled_at TIMESTAMPTZ,
    resolution VARCHAR(255)
);

-- 7. task_leases
CREATE TABLE task_leases (
    id UUID PRIMARY KEY,
    task_id UUID NOT NULL REFERENCES tasks(id) ON DELETE RESTRICT,
    worker_identity VARCHAR(255) NOT NULL,
    acquired_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    heartbeat_at TIMESTAMPTZ,
    released_at TIMESTAMPTZ,
    status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    release_reason VARCHAR(255),
    UNIQUE (id, task_id)
);


CREATE UNIQUE INDEX idx_task_leases_one_active ON task_leases (task_id) WHERE released_at IS NULL AND status IN (\'ACTIVE\');

ALTER TABLE tasks
ADD CONSTRAINT tasks_active_lease_id_fkey
FOREIGN KEY (active_lease_id, id)
REFERENCES task_leases(id, task_id)
ON DELETE RESTRICT;
');
    }

    public function down()
    {
        // DB::unprepared(...);
    }
};
