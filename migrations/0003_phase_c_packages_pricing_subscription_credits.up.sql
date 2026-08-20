-- 1. packages
CREATE TABLE packages (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    is_custom BOOLEAN NOT NULL DEFAULT false,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    duration_days INT,
    retention_days INT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. package_entitlements
CREATE TABLE package_entitlements (
    id UUID PRIMARY KEY,
    package_id UUID NOT NULL REFERENCES packages(id) ON DELETE RESTRICT,
    capability VARCHAR(100) NOT NULL,
    limits JSONB NOT NULL DEFAULT '{}',
    UNIQUE (package_id, capability)
);

-- 3. pricing_versions
CREATE TABLE pricing_versions (
    id UUID PRIMARY KEY,
    capability VARCHAR(100) NOT NULL,
    credits_per_result BIGINT NOT NULL CHECK (credits_per_result >= 0),
    valid_from TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    valid_until TIMESTAMPTZ,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
-- Prevent ambiguous overlapping ACTIVE pricing per capability
CREATE UNIQUE INDEX idx_pricing_versions_active_capability 
ON pricing_versions(capability) WHERE status = 'ACTIVE';

-- 4. subscriptions
CREATE TABLE subscriptions (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    package_id UUID NOT NULL REFERENCES packages(id) ON DELETE RESTRICT,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    starts_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 5. subscription_snapshots
CREATE TABLE subscription_snapshots (
    id UUID PRIMARY KEY,
    subscription_id UUID NOT NULL REFERENCES subscriptions(id) ON DELETE RESTRICT,
    snapshot_data JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 6. credit_lots
CREATE TABLE credit_lots (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    source VARCHAR(50) NOT NULL,
    source_reference VARCHAR(255),
    original_quantity BIGINT NOT NULL,
    remaining_quantity BIGINT NOT NULL,
    effective_monetary_value_cents BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    CHECK (original_quantity >= 0),
    CHECK (remaining_quantity >= 0),
    CHECK (remaining_quantity <= original_quantity),
    CHECK (source IN ('SUBSCRIPTION', 'TOP_UP', 'BONUS', 'ADJUSTMENT', 'REFUND')),
    UNIQUE (id, organization_id)
);

-- Indexes for FEFO reservation
CREATE INDEX idx_credit_lots_fefo ON credit_lots(organization_id, expires_at ASC) WHERE remaining_quantity > 0;
CREATE INDEX idx_credit_lots_org ON credit_lots(organization_id);
