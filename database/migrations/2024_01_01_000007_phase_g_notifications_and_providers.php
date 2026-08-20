<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::unprepared('CREATE TYPE notification_delivery_status AS ENUM (
    \'QUEUED\', \'SENDING\', \'DELIVERED\', \'FAILED\'
);

-- 10. provider_configs
CREATE TABLE provider_configs (
    id UUID PRIMARY KEY,
    provider_name VARCHAR(100) NOT NULL,
    provider_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    safe_metadata JSONB,
    encrypted_credentials VARCHAR(2048) NOT NULL,
    key_reference VARCHAR(255) NOT NULL,
    encryption_version VARCHAR(50) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 8. wa_pools
CREATE TABLE wa_pools (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    concurrency_config JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- 9. wa_instances
CREATE TABLE wa_instances (
    id UUID PRIMARY KEY,
    pool_id UUID NOT NULL REFERENCES wa_pools(id) ON DELETE RESTRICT,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    health_state VARCHAR(50) NOT NULL DEFAULT \'HEALTHY\',
    provider_config_id UUID REFERENCES provider_configs(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 1. notification_events
CREATE TABLE notification_events (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    event_type VARCHAR(255) NOT NULL,
    event_version VARCHAR(50) NOT NULL,
    dedupe_key VARCHAR(255) NOT NULL,
    safe_payload JSONB,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (organization_id, dedupe_key),
    UNIQUE (id, organization_id)
);

-- 2. notification_rules
CREATE TABLE notification_rules (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    event_type VARCHAR(255) NOT NULL,
    channels JSONB NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 3. notification_templates
CREATE TABLE notification_templates (
    id UUID PRIMARY KEY,
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    channel VARCHAR(50) NOT NULL,
    event_type VARCHAR(255) NOT NULL,
    template_content TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 4. logical_notifications
CREATE TABLE logical_notifications (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    event_id UUID NOT NULL,
    recipient_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    channel VARCHAR(50) NOT NULL,
    status notification_delivery_status NOT NULL DEFAULT \'QUEUED\',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (event_id, organization_id) REFERENCES notification_events(id, organization_id) ON DELETE RESTRICT,
    UNIQUE (event_id, recipient_id, channel)
);

-- 5. notification_deliveries
CREATE TABLE notification_deliveries (
    id UUID PRIMARY KEY,
    logical_notification_id UUID NOT NULL REFERENCES logical_notifications(id) ON DELETE RESTRICT,
    status notification_delivery_status NOT NULL DEFAULT \'QUEUED\',
    provider_reference VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 6. notification_delivery_attempts
CREATE TABLE notification_delivery_attempts (
    id UUID PRIMARY KEY,
    delivery_id UUID NOT NULL REFERENCES notification_deliveries(id) ON DELETE RESTRICT,
    attempt_number INT NOT NULL,
    provider_instance_id UUID REFERENCES wa_instances(id) ON DELETE RESTRICT,
    provider_event_id VARCHAR(255),
    runtime_phase VARCHAR(100),
    safe_error JSONB,
    latency_ms INT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (delivery_id, attempt_number)
);

-- 7. in_app_notifications
CREATE TABLE in_app_notifications (
    id UUID PRIMARY KEY,
    recipient_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    logical_notification_id UUID NOT NULL REFERENCES logical_notifications(id) ON DELETE RESTRICT,
    content JSONB NOT NULL,
    read_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 11. outgoing_webhooks
CREATE TABLE outgoing_webhooks (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    events JSONB NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
    endpoint_url TEXT NOT NULL,
    encrypted_secret VARCHAR(2048) NOT NULL,
    key_reference VARCHAR(255) NOT NULL,
    encryption_version VARCHAR(50) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (id, organization_id)
);

-- 12. webhook_deliveries
CREATE TABLE webhook_deliveries (
    id UUID PRIMARY KEY,
    webhook_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    event_id UUID NOT NULL,
    status notification_delivery_status NOT NULL,
    response_code INT,
    safe_error JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (webhook_id, organization_id) REFERENCES outgoing_webhooks(id, organization_id) ON DELETE RESTRICT,
    FOREIGN KEY (event_id, organization_id) REFERENCES notification_events(id, organization_id) ON DELETE RESTRICT
);
');
    }

    public function down()
    {
        // DB::unprepared(...);
    }
};
