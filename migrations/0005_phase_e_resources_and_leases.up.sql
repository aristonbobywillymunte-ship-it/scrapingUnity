CREATE TYPE resource_health_status AS ENUM (
    'HEALTHY', 'DEGRADED', 'UNHEALTHY'
);

-- 1. resource_pools
CREATE TABLE resource_pools (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    platform VARCHAR(50),
    max_concurrency INT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    UNIQUE (name)
);

-- 2. proxy_pools
CREATE TABLE proxy_pools (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    max_concurrency INT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    UNIQUE (name)
);

-- 3. social_accounts
CREATE TABLE social_accounts (
    id UUID PRIMARY KEY,
    platform VARCHAR(50) NOT NULL,
    pool_id UUID REFERENCES resource_pools(id) ON DELETE RESTRICT,
    health_status resource_health_status NOT NULL DEFAULT 'HEALTHY',
    operational_state VARCHAR(50) NOT NULL DEFAULT 'AVAILABLE',
    cooldown_until TIMESTAMPTZ,
    affinity_metadata JSONB,
    max_concurrency INT NOT NULL DEFAULT 1,
    encrypted_credentials VARCHAR(2048) NOT NULL,
    key_reference VARCHAR(255) NOT NULL,
    encryption_version VARCHAR(50) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);
CREATE INDEX idx_social_accounts_pool ON social_accounts(pool_id);
CREATE INDEX idx_social_accounts_platform ON social_accounts(platform);

-- 4. social_sessions
CREATE TABLE social_sessions (
    id UUID PRIMARY KEY,
    account_id UUID NOT NULL REFERENCES social_accounts(id) ON DELETE RESTRICT,
    encrypted_session VARCHAR(4096) NOT NULL,
    key_reference VARCHAR(255) NOT NULL,
    encryption_version VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    revoked_at TIMESTAMPTZ
);
CREATE INDEX idx_social_sessions_account ON social_sessions(account_id);

-- 5. proxies
CREATE TABLE proxies (
    id UUID PRIMARY KEY,
    pool_id UUID REFERENCES proxy_pools(id) ON DELETE RESTRICT,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL CHECK (port >= 1 AND port <= 65535),
    health_status resource_health_status NOT NULL DEFAULT 'HEALTHY',
    operational_state VARCHAR(50) NOT NULL DEFAULT 'AVAILABLE',
    cooldown_until TIMESTAMPTZ,
    max_concurrency INT NOT NULL DEFAULT 1,
    encrypted_credentials VARCHAR(2048),
    key_reference VARCHAR(255),
    encryption_version VARCHAR(50),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);
CREATE INDEX idx_proxies_pool ON proxies(pool_id);

-- 6. account_leases
CREATE TABLE account_leases (
    id UUID PRIMARY KEY,
    account_id UUID NOT NULL REFERENCES social_accounts(id) ON DELETE RESTRICT,
    task_id UUID NOT NULL REFERENCES tasks(id) ON DELETE RESTRICT,
    worker_identity VARCHAR(255) NOT NULL,
    acquired_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    heartbeat_at TIMESTAMPTZ,
    released_at TIMESTAMPTZ,
    status VARCHAR(50) NOT NULL DEFAULT 'ACQUIRED',
    release_reason VARCHAR(255)
);
-- Important: No UNIQUE(account_id) for active leases, allowing concurrent use up to max_concurrency
-- Concurrency limit will be enforced via PostgreSQL advisory locks or FOR UPDATE SKIP LOCKED at runtime.
CREATE INDEX idx_account_leases_active ON account_leases(account_id) WHERE released_at IS NULL;
CREATE INDEX idx_account_leases_task ON account_leases(task_id);
CREATE INDEX idx_account_leases_recovery ON account_leases(expires_at, released_at) WHERE released_at IS NULL;

-- 7. proxy_leases
CREATE TABLE proxy_leases (
    id UUID PRIMARY KEY,
    proxy_id UUID NOT NULL REFERENCES proxies(id) ON DELETE RESTRICT,
    task_id UUID NOT NULL REFERENCES tasks(id) ON DELETE RESTRICT,
    worker_identity VARCHAR(255) NOT NULL,
    acquired_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    heartbeat_at TIMESTAMPTZ,
    released_at TIMESTAMPTZ,
    status VARCHAR(50) NOT NULL DEFAULT 'ACQUIRED',
    release_reason VARCHAR(255)
);
-- Concurrency up to max_concurrency is allowed.
CREATE INDEX idx_proxy_leases_active ON proxy_leases(proxy_id) WHERE released_at IS NULL;
CREATE INDEX idx_proxy_leases_task ON proxy_leases(task_id);
CREATE INDEX idx_proxy_leases_recovery ON proxy_leases(expires_at, released_at) WHERE released_at IS NULL;

-- Add FKs to Phase D tables
ALTER TABLE task_attempts 
  ADD CONSTRAINT task_attempts_account_lease_id_fkey 
  FOREIGN KEY (account_lease_id) REFERENCES account_leases(id) ON DELETE RESTRICT;

ALTER TABLE task_attempts 
  ADD CONSTRAINT task_attempts_proxy_lease_id_fkey 
  FOREIGN KEY (proxy_lease_id) REFERENCES proxy_leases(id) ON DELETE RESTRICT;

