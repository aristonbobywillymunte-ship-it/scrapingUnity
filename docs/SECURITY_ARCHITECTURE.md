# SECURITY ARCHITECTURE

This document defines the authoritative security architecture, access controls, data protection policies, and threat boundaries for the platform.

---

## 1. ACTOR TYPES

### Customer Actors
- **Owner**: Full tenant access, billing, and member management.
- **Developer**: API keys, technical configurations.
- **Analyst**: Scraping execution, exports, and reporting.
- **Viewer**: Read-only access to results and runs.

### Internal Actors (Platform)
- **Owner**: Super administrator.
- **Admin**: Broad platform configuration.
- **Operator/Support**: Scraper and infrastructure management.
- **Finance**: Billing, refunds, packages, ledger.
- **Auditor/Security**: Read-only audit logs, compliance, and security monitoring.

### System Actors
- **Service Actors**: Non-human identities for inter-service and cron execution.
- **Temporary Support Access**: Time-bound scoped access for troubleshooting.
- **Break-glass Actor Context**: Emergency elevated context with max auditing.

---

## 2. AUTHENTICATION & SESSIONS

**Customer Authentication**: Email/Password, Google Auth (if already locked by policy).
**Internal Authentication**: **MFA is strictly REQUIRED** for all internal roles.

**Passwords**: Hashed only (Argon2id or bcrypt). NEVER reversible storage.

**Session Security**:
- Secure cookie (HttpOnly, SameSite, Secure flag).
- CSRF protection for session APIs (Developer API uses Bearer API key without cookie dependency).
- Session rotation, idle/absolute expiry, revocation, force logout, logout-all.
- Device/session metadata tracked for anomaly detection.
*No raw session secrets in logs.*

---

## 3. PASSWORD RESET OTP

Password reset uses out-of-band OTP.
**Channels allowed**: EMAIL, WHATSAPP. (*NOT Telegram*).

**Strict Rules**:
- 6 digits
- 5-minute expiry
- Max 3 requests / day / account / channel
- Max 5 verification attempts per OTP
- Single-use
- Hashed storage

*Prevents account enumeration. (Telegram pairing OTP remains entirely separate).*

---

## 4. MFA, REAUTH & MAKER-CHECKER

- **MFA**: Required for all internal roles.
- **Re-auth/MFA for Critical Actions**: Refund approval, Break-glass, critical permission changes, and sensitive integration credential replacement.
- **Maker-Checker**: Required for sensitive financial/security workflows (e.g., Refund). Maker and Checker MUST be different actors.

---

## 5. AUTHORIZATION, RBAC & TENANT ISOLATION

- **Server-Side Deny by Default**: Route prefixes or UI visibility are NOT authorization.
- **Permissions**: Formatted as `domain:action` (e.g., `run:read`, `refund:approve`).
- **Least Privilege**: Finance does not gain Operator rights; Operator does not gain Finance rights.

**Tenant Isolation**:
- Customer access ONLY through owned organization scope.
- Canonical scraping data NEVER creates cross-tenant visibility. Result access remains through owned `Run`/`RunResult` lineage.
- Internal access requires explicit permission.

---

## 6. CREDENTIAL MANAGEMENT

**API Keys**:
- Store ONLY secure hash/fingerprint.
- Plaintext shown once on creation.
- Explicit definition of: scopes, org ownership, status, `last_used_at`, revocation, and rotation.
- *Never log full key.*

**Provider Secrets (Encrypted Storage)**:
- Reversible credentials (social sessions, proxy credentials, Evolution API token, Telegram token, SMTP password, integration secrets) MUST use **Envelope-Encryption**.
- Store `ciphertext`, `key reference`, `encryption version`. Supports rotation.
- *No plaintext in Redis, queues, logs, audit payloads, or errors.*

**Secret Rotation**:
- Define rotation lifecycle for API keys, provider credentials, encryption key versions, service credentials, and webhook secrets. Supports overlap/grace periods.

**Database / Service Credentials**:
- Stored encrypted/protected by a secret-management system.
- Plaintext exists only transiently at the authorized runtime execution edge.
- *Never logged or committed to source/config repository.*

---

## 7. PRIVILEGE ELEVATION & TEMPORARY ACCESS

**Temporary Support Access**:
- Time-bound, least privilege.
- Request, Approval, Scope, Organization, Reason, Start, Expiry, Revocation, Audit.
- *No permanent hidden support access.*

**JIT Privilege**:
- Temporary privilege elevation. Request, approval, specific permissions, expiry, automatic revoke, full audit.

**Break-Glass**:
- Emergency-only. Requires strong auth/MFA, reason, limited duration, full audit, immediate security notification, post-event review, automatic expiry.
- *No silent permanent privilege.*

**Access Review**:
- Periodic review of internal users, roles, temporary access, JIT, service actors, and break-glass usage. Record reviewer and decision.

---

## 8. SERVICE ACTORS

- Non-human identity for jobs/crons.
- Explicit purpose, permissions, credential identity, environment, rotation, owner, and audit context.
- *Do not share human credentials.*

---

## 9. AUDIT & SECURITY EVENTS

**Immutable Audit Log**:
- Records: `actor`, `actor_type`, `organization`, `action`, `target`, `request_id`, `timestamp`, safe metadata, before/after. *Never store secrets.*

**Security Events**:
- Failed login anomalies, MFA failure, password reset, API key create/revoke, role/permission changes, temp access, JIT, break-glass, secret replacement, suspicious session activity.
- Integrates with Notification Architecture.

---

## 10. RATE LIMITING & ABUSE

Security-sensitive throttling (configuration-driven except locked OTP limits):
- Login
- OTP request
- OTP verify
- Password reset
- API key use
- Sensitive internal actions

---

## 11. WEBHOOK SECURITY & IDEMPOTENCY

**Outgoing Webhooks**: HMAC signing, timestamp, event ID, replay protection.
**Incoming Provider Callbacks**: Signature/auth verification (where supported), idempotency, replay protection, safe state transitions.

**Idempotency & Replay Protection**:
`Idempotency-Key` is mandatory ONLY for replay-sensitive operations defined by locked API/OpenAPI contracts (e.g., creating a run, processing a refund). Other mutations rely on appropriate protections such as CSRF, authorization, state-transition validation, unique constraints, version/concurrency controls, and replay protection where applicable.

---

## 12. CORS CONTRACT

- **Configuration-Driven Allowlist**: Origins must be strictly matched.
- **No Wildcard with Credentials**: `Access-Control-Allow-Origin: *` is NEVER permitted alongside `Access-Control-Allow-Credentials: true`.
- **Session API**: Accessible ONLY from trusted application origins.
- **Developer API**: Remains Bearer API-based.
- **Preflight**: Handled safely without altering server state.
- *CORS is NOT authorization. It is purely a browser-side access control mechanism.*

---

## 13. SSRF, XSS & INPUT/OUTPUT SAFETY

**SSRF Protection**:
- Scrape URLs are validated. Scheme validation. Block unsafe internal/private network targets (localhost, cloud metadata `169.254.169.254`, `10.x.x.x`). Control redirects. Apply DNS/IP validation policy. *Do not allow scraping input to become SSRF against platform infra.*

**XSS / Input Output Safety**:
- Validate structured inputs. Escape UI output.
- Prevent stored/reflected XSS from scraped content. Downloaded/exported user-facing data must be safely encoded.

---

## 14. FILE / EXPORT SECURITY

- Exports are tenant-scoped.
- Authorization checked at creation AND download.
- Signed/temporary access URLs (where applicable). Retention expiry enforced. *No cross-tenant file access.*

---

## 15. DATA CLASSIFICATION & AT REST/TRANSIT

**Classes**:
- **Public**: Safe, non-identifiable.
- **Internal**: Telemetry, system states.
- **Confidential**: Scraped results, Customer Data, Financial data.
- **Secret**: PII, Sessions, API Keys, Provider Credentials, Audit Data.

**Protection**:
- TLS required in transit.
- Sensitive secrets encrypted at rest (Envelope).
- Database backups/storage preserve access controls and encryption.

---

## 16. LOGGING & OBSERVABILITY

- Logs include safe identifiers: `request_id`, `organization_id`, `actor_id/type`, `run_id`, `task_id`.
- *Never log*: Password, OTP plaintext, API keys, cookies, session credentials, proxy passwords, provider tokens.

---

## 17. ACCOUNT SUSPENSION & RECOVERY

- **Password Reset**: Does not bypass tenant status, account suspension, or security controls. Invalidates relevant sessions after successful password change.
- **User Suspension**: Blocks that human user's session authentication and personal credentials. API keys are organization-scoped credentials. Suspending the human user who created an API key MUST NOT automatically invalidate an organization-owned key unless explicit security/ownership policy requires revocation.
- **Org Suspension**: Blocks API and new operational actions while retaining authorized historical access according to organization suspension policy.

---

## 18. INTERNAL AI SECURITY BOUNDARY

- **AI Assistant**: **INTERNAL ONLY**. Customer has NO AI Assistant.
- **Scope**: Read-only v1. Permission-aware. Uses AI Tool Gateway.
- **Hard Boundaries**: NO raw SQL. NO arbitrary unrestricted internal API access.
- **WA/TG AI Access**: Requires verified internal-user binding, `ai.use` permission, and MFA/security posture where required.

---

## 19. DATABASE SECURITY

- Application DB roles use least privilege.
- Migration/admin credentials separate from runtime credentials.
- *No application feature executes arbitrary SQL from AI/user input.*

---

## 20. SECURITY MATRICES

### A. Role → Security Capability
| Role | Scraper Ops | Financials | API Key Management | Break-glass / JIT | Audit Log Read |
|---|---|---|---|---|---|
| Cust. Owner | YES (Own) | YES (Own) | YES (Own) | NO | YES (Own) |
| Cust. Dev | YES (Own) | NO | YES (Own) | NO | NO |
| Cust. Analyst | YES (Own) | NO | NO | NO | NO |
| Cust. Viewer | NO (Read) | NO | NO | NO | NO |
| Int. Owner | YES (Global)| YES (Global)| YES (Global) | YES | YES |
| Int. Admin | YES (Global)| NO | NO | YES | YES |
| Int. Oper/Supp| YES (Global)| NO | NO | YES (Scoped) | NO |
| Int. Finance | NO | YES (Global)| NO | YES (Scoped) | NO |
| Int. Aud/Sec | NO | NO | NO | YES (Scoped) | YES |

### B. Critical Action → MFA/Reauth/Maker-Checker
| Action | Requires MFA / Reauth | Requires Maker-Checker |
|---|---|---|
| Refund Approval | YES | YES |
| Break-glass Activation | YES | NO (Post-event review) |
| Replace Provider Secret | YES | YES |
| Critical Permission Change | YES | YES |

### C. Credential Type → Storage/Rotation
| Credential | Storage | Rotation |
|---|---|---|
| Password | One-way Hash (Argon2id) | On Demand / Reset |
| API Key | One-way Hash (SHA-256) | User Initiated |
| OTP | One-way Hash | 5-minute expiry |
| Social Session | Envelope Encryption | When Invalidated |
| Provider Token | Envelope Encryption | Configuration / Policy |
| DB / Svc Pass | Secrets Manager | DevOps Lifecycle |

### D. Security Event → Audit/Notification
| Event | Audit Trail | Notification Channel |
|---|---|---|
| Failed Login Anomaly | YES | Email / In-App |
| Break-glass Activation | YES | Email / In-App / WA (Critical) |
| API Key Created | YES | In-App / Email |
| Secret Replacement | YES | Email (Internal Sec) |

### E. Actor Type → Allowed Authentication
| Actor Type | Email/Pass | Google SSO | MFA Required | API Key |
|---|---|---|---|---|
| Customer User| YES | YES | Optional/Policy | NO (API Key is Dev API) |
| Customer Svc | NO | NO | N/A | YES |
| Internal User| YES | YES | **YES** | NO |
| Service Actor| NO | NO | N/A | Vault / TLS |

### F. Data Classification → Protection
| Data Type | Classification | Storage Protection | Logging Protection |
|---|---|---|---|
| API Key (Full) | Secret | Never Stored | Masked/Never Logged |
| Proxy Pass | Secret | Envelope Encrypted | Masked/Never Logged |
| Scraped Data | Confidential | Tenant Isolated DB | Safe Metadata Only |
| Audit Trail | Secret | Immutable / DB | N/A |
| Telemetry | Internal | Secure DB | Plaintext |

---

## 21. THREAT BOUNDARIES EXPLICITLY ADDRESSED
- **Cross-tenant access**: Enforced via API context and DB ownership scoping.
- **Credential leakage**: Prevented via hashed storage, envelope encryption, and strict logging redaction.
- **Session hijack**: Prevented via HttpOnly, Secure, SameSite cookies, session ID rotation, anomaly detection.
- **API key theft**: Plaintext never stored; shown once; hashed at rest.
- **SSRF**: Strict URL validation, internal IP blocking, DNS resolution filtering.
- **Webhook replay**: Timestamps, idempotency keys, HMAC signatures.
- **Callback spoofing**: Authentication and signature checks for inbound webhooks.
- **Privilege escalation**: Server-side deny by default, strictly explicit grants.
- **Support impersonation**: Time-bound scoped access with immutable audit trail.
- **Double-action/replay**: Idempotency keys required for mutations.
- **Scraped-content XSS**: Output encoding, structured API responses.
- **Secret leakage**: Code scanning, strict redaction logging middleware, secrets manager isolation.

---

## 22. OPEN DECISIONS
- No blocking security architecture decisions remain. All critical boundaries are defined and locked.
