<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::unprepared('-- Enum definitions
CREATE TYPE credit_transaction_type AS ENUM (
    \'PACKAGE_CREDIT\', \'PURCHASE\', \'RESERVE\', \'RELEASE\', \'USAGE\', \'REFUND\', \'BONUS\', \'ADJUSTMENT\', \'EXPIRED\'
);

CREATE TYPE refund_status AS ENUM (
    \'PENDING\', \'APPROVED\', \'REJECTED\'
);

-- Fix Phase D reference
ALTER TABLE runs ADD CONSTRAINT runs_pricing_snapshot_id_fkey FOREIGN KEY (pricing_snapshot_id) REFERENCES pricing_versions(id) ON DELETE RESTRICT;

-- 1. billing_reservations
CREATE TABLE billing_reservations (
    id UUID PRIMARY KEY,
    run_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    estimated BIGINT NOT NULL,
    reserved BIGINT NOT NULL,
    settled BIGINT NOT NULL DEFAULT 0,
    released BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL,
    pricing_version_id UUID REFERENCES pricing_versions(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (run_id),
    UNIQUE (id, organization_id),
    FOREIGN KEY (run_id, organization_id) REFERENCES runs(id, organization_id) ON DELETE RESTRICT,
    CHECK (estimated >= 0),
    CHECK (reserved >= 0),
    CHECK (settled >= 0),
    CHECK (released >= 0),
    CHECK (settled + released <= reserved)
);

-- 2. credit_reservation_allocations
CREATE TABLE credit_reservation_allocations (
    id UUID PRIMARY KEY,
    reservation_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    credit_lot_id UUID NOT NULL,
    reserved_quantity BIGINT NOT NULL,
    settled_quantity BIGINT NOT NULL DEFAULT 0,
    released_quantity BIGINT NOT NULL DEFAULT 0,
    economic_value_snapshot JSONB,
    allocation_order INT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (reservation_id, organization_id) REFERENCES billing_reservations(id, organization_id) ON DELETE RESTRICT,
    FOREIGN KEY (credit_lot_id, organization_id) REFERENCES credit_lots(id, organization_id) ON DELETE RESTRICT,
    UNIQUE (reservation_id, credit_lot_id),
    CHECK (reserved_quantity >= 0),
    CHECK (settled_quantity >= 0),
    CHECK (released_quantity >= 0),
    CHECK (settled_quantity + released_quantity <= reserved_quantity)
);
CREATE INDEX idx_cra_reservation_order ON credit_reservation_allocations(reservation_id, allocation_order);

-- 3. credit_ledger
CREATE TABLE credit_ledger (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    event_idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    transaction_type credit_transaction_type NOT NULL,
    credit_lot_id UUID NOT NULL REFERENCES credit_lots(id) ON DELETE RESTRICT,
    reservation_id UUID,
    reservation_allocation_id UUID,
    run_id UUID,
    quantity BIGINT NOT NULL,
    economic_value_reference JSONB,
    actor_id UUID REFERENCES users(id) ON DELETE RESTRICT,
    reason VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (reservation_id, organization_id) REFERENCES billing_reservations(id, organization_id) ON DELETE RESTRICT,
    FOREIGN KEY (run_id, organization_id) REFERENCES runs(id, organization_id) ON DELETE RESTRICT
);

CREATE OR REPLACE FUNCTION prevent_credit_ledger_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION \'credit_ledger is append-only\';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_credit_ledger_append_only
BEFORE UPDATE OR DELETE ON credit_ledger
FOR EACH ROW
EXECUTE FUNCTION prevent_credit_ledger_modification();

-- 4. invoices
CREATE TABLE invoices (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    total_cents BIGINT NOT NULL CHECK (total_cents >= 0),
    currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
    status VARCHAR(50) NOT NULL,
    issued_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    due_at TIMESTAMPTZ,
    paid_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (id, organization_id)
);

-- 5. payments
CREATE TABLE payments (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    invoice_id UUID NOT NULL,
    provider VARCHAR(100) NOT NULL,
    provider_transaction_id VARCHAR(255) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    amount_cents BIGINT NOT NULL CHECK (amount_cents >= 0),
    status VARCHAR(50) NOT NULL,
    paid_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (invoice_id, organization_id) REFERENCES invoices(id, organization_id) ON DELETE RESTRICT,
    UNIQUE (provider, provider_transaction_id),
    UNIQUE (id, organization_id)
);

-- 6. payment_webhook_events
CREATE TABLE payment_webhook_events (
    id UUID PRIMARY KEY,
    provider VARCHAR(100) NOT NULL,
    provider_event_id VARCHAR(255) NOT NULL,
    payment_id UUID REFERENCES payments(id) ON DELETE RESTRICT,
    provider_transaction_reference VARCHAR(255),
    event_type VARCHAR(100) NOT NULL,
    received_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    processed_at TIMESTAMPTZ,
    processing_status VARCHAR(50) NOT NULL,
    safe_payload_metadata JSONB,
    safe_error JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider, provider_event_id)
);

-- 7. credit_allocations
CREATE TABLE credit_allocations (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    payment_id UUID NOT NULL,
    credit_lot_id UUID NOT NULL,
    quantity BIGINT NOT NULL CHECK (quantity >= 0),
    allocation_reference VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (payment_id, organization_id) REFERENCES payments(id, organization_id) ON DELETE RESTRICT,
    FOREIGN KEY (credit_lot_id, organization_id) REFERENCES credit_lots(id, organization_id) ON DELETE RESTRICT
);

-- 8. refund_approvals
CREATE TABLE refund_approvals (
    id UUID PRIMARY KEY,
    maker_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    checker_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status refund_status NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    reviewed_at TIMESTAMPTZ,
    CHECK (maker_id <> checker_id)
);

-- 9. refunds
CREATE TABLE refunds (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    payment_id UUID REFERENCES payments(id) ON DELETE RESTRICT,
    run_id UUID,
    approval_id UUID NOT NULL REFERENCES refund_approvals(id) ON DELETE RESTRICT,
    amount_cents BIGINT,
    credit_quantity BIGINT,
    status refund_status NOT NULL,
    reason VARCHAR(255) NOT NULL,
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (run_id, organization_id) REFERENCES runs(id, organization_id) ON DELETE RESTRICT,
    CHECK (amount_cents >= 0),
    CHECK (credit_quantity >= 0),
    CHECK (COALESCE(amount_cents, 0) > 0 OR COALESCE(credit_quantity, 0) > 0)
);

-- 10. internal_costs
CREATE TABLE internal_costs (
    id UUID PRIMARY KEY,
    event_idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    run_id UUID REFERENCES runs(id) ON DELETE RESTRICT,
    task_id UUID REFERENCES tasks(id) ON DELETE RESTRICT,
    category VARCHAR(100) NOT NULL,
    provider_reference VARCHAR(255),
    amount_cents BIGINT NOT NULL CHECK (amount_cents >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

');
    }

    public function down()
    {
        // DB::unprepared(...);
    }
};
