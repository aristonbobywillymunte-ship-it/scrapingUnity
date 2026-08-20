-- 1. organizations
CREATE TABLE organizations (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- 2. users
CREATE TABLE users (
    id UUID PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    mfa_enabled BOOLEAN NOT NULL DEFAULT false,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- 3. roles
CREATE TABLE roles (
    id VARCHAR(50) PRIMARY KEY,
    is_internal_role BOOLEAN NOT NULL,
    description VARCHAR(255),
    UNIQUE (id, is_internal_role)
);

-- 4. permissions
CREATE TABLE permissions (
    id VARCHAR(100) PRIMARY KEY
);

-- 5. role_permissions
CREATE TABLE role_permissions (
    role_id VARCHAR(50) NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id VARCHAR(100) NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

-- 6. organization_memberships
CREATE TABLE organization_memberships (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    role_id VARCHAR(50) NOT NULL,
    role_is_internal BOOLEAN NOT NULL DEFAULT false,
    CHECK (role_is_internal = false),
    FOREIGN KEY (role_id, role_is_internal) REFERENCES roles(id, is_internal_role) ON DELETE RESTRICT,
    UNIQUE (organization_id, user_id)
);

-- 7. internal_user_assignments
CREATE TABLE internal_user_assignments (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    role_id VARCHAR(50) NOT NULL,
    role_is_internal BOOLEAN NOT NULL DEFAULT true,
    CHECK (role_is_internal = true),
    FOREIGN KEY (role_id, role_is_internal) REFERENCES roles(id, is_internal_role) ON DELETE RESTRICT,
    UNIQUE (user_id, role_id)
);

-- 8. auth_sessions
CREATE TABLE auth_sessions (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    device_metadata JSONB,
    ip_address INET,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    revoked_at TIMESTAMPTZ
);
CREATE INDEX idx_auth_sessions_expires_at ON auth_sessions(expires_at);

-- 9. auth_logs
CREATE TABLE auth_logs (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    event_type VARCHAR(100) NOT NULL,
    ip_address INET,
    device_metadata JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_auth_logs_created_at ON auth_logs(created_at);

-- 10. otp_rate_buckets
CREATE TABLE otp_rate_buckets (
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    channel VARCHAR(50) NOT NULL,
    bucket_date DATE NOT NULL,
    request_count INT NOT NULL DEFAULT 1,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, channel, bucket_date),
    CHECK (request_count <= 3),
    CHECK (channel IN ('EMAIL', 'WHATSAPP'))
);

-- 11. otp_requests
CREATE TABLE otp_requests (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    channel VARCHAR(50) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (attempt_count <= 5),
    CHECK (channel IN ('EMAIL', 'WHATSAPP'))
);
CREATE INDEX idx_otp_requests_user_created ON otp_requests(user_id, created_at);

-- 12. channel_bindings
CREATE TABLE channel_bindings (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    channel VARCHAR(50) NOT NULL,
    external_identity VARCHAR(255) NOT NULL,
    verified_at TIMESTAMPTZ,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    revoked_at TIMESTAMPTZ,
    safe_metadata JSONB,
    UNIQUE (channel, external_identity),
    CHECK (channel IN ('WHATSAPP', 'TELEGRAM'))
);

-- 13. api_keys
CREATE TABLE api_keys (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    key_hash VARCHAR(255) NOT NULL UNIQUE,
    key_prefix VARCHAR(50) NOT NULL,
    scopes JSONB,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    last_used_at TIMESTAMPTZ,
    revoked_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 14. api_idempotency_keys
CREATE TABLE api_idempotency_keys (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    actor_identity VARCHAR(255) NOT NULL,
    operation_id VARCHAR(255) NOT NULL,
    key_hash VARCHAR(255) NOT NULL,
    request_fingerprint VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'PROCESSING',
    response_reference JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    UNIQUE (organization_id, actor_identity, operation_id, key_hash)
);

-- 15. service_actors
CREATE TABLE service_actors (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    credentials_hash VARCHAR(255) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    owner_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    environment VARCHAR(50) NOT NULL,
    credential_rotation_reference VARCHAR(255),
    last_rotated_at TIMESTAMPTZ,
    audit_reference VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- 15.5. service_actor_permissions
CREATE TABLE service_actor_permissions (
    service_actor_id UUID NOT NULL REFERENCES service_actors(id) ON DELETE CASCADE,
    permission_id VARCHAR(100) NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (service_actor_id, permission_id)
);

-- 16. temporary_access_grants
CREATE TABLE temporary_access_grants (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    role_id VARCHAR(50) NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
    approver_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    organization_id UUID REFERENCES organizations(id) ON DELETE RESTRICT,
    reason TEXT NOT NULL,
    starts_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ,
    audit_reference VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 17. jit_privilege_grants
CREATE TABLE jit_privilege_grants (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    requested_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    approved_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    permission_id VARCHAR(100) NOT NULL REFERENCES permissions(id) ON DELETE RESTRICT,
    organization_id UUID REFERENCES organizations(id) ON DELETE RESTRICT,
    reason TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    starts_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ,
    audit_reference VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 18. break_glass_activations
CREATE TABLE break_glass_activations (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    reason TEXT NOT NULL,
    starts_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ,
    audit_reference VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 19. access_reviews
CREATE TABLE access_reviews (
    id UUID PRIMARY KEY,
    target_user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    reviewer_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status VARCHAR(50) NOT NULL,
    reviewed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
