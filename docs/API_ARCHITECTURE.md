# API ARCHITECTURE

This document establishes the authoritative logical API contract architecture for the Scraping as a Service platform.

## 1. API BOUNDARIES & VERSIONING

The system exposes three distinct API namespaces, strictly isolated by authentication and authorization requirements:

1. **Public Developer API**
   - **Base URL**: `/api/v1`
   - **Consumers**: Customer scripts, external backends.
   - **Auth**: Bearer API Key (Hash verified).

2. **Customer Portal Application API**
   - **Base URL**: `/app/api/v1`
   - **Consumers**: Customer Web UI.
   - **Auth**: Secure Authenticated Session (Cookie) + CSRF protection.

3. **Internal Platform API**
   - **Base URL**: `/internal/api/v1`
   - **Consumers**: Ops/Admin Web UI, Internal AI Assistant.
   - **Auth**: Authenticated Session + Internal RBAC + MFA for critical operations.

*All endpoints strictly use JSON (`application/json`). Versioning is URI-based.*

---

## 2. STANDARD RESPONSE ENVELOPE

**Success**
```json
{
  "data": { ... },
  "meta": { ... },
  "request_id": "req_123456"
}
```

**Asynchronous Operation (202 Accepted)**
```json
{
  "data": {
    "id": "run_987",
    "status": "QUEUED"
  },
  "request_id": "req_123457"
}
```

---

## 3. STANDARD ERROR CONTRACT

Errors separate user-safe messages from internal diagnostics. Secrets and stack traces are never exposed.

```json
{
  "error": {
    "code": "INSUFFICIENT_CREDITS",
    "message": "Organization does not have enough credits to start this run.",
    "details": { "required": 500, "available": 200 }
  },
  "request_id": "req_123458"
}
```

**Standard HTTP Codes**:
- `400`: Validation Error
- `401`: Unauthenticated
- `403`: Unauthorized
- `404`: Not Found
- `409`: Conflict (e.g., Idempotency conflict)
- `422`: Domain Validation / Unprocessable Entity
- `429`: Rate Limit Exceeded
- `500`: Internal Server Error
- `503`: Service Unavailable / Maintenance

---

## 4. REQUEST CONTEXT & TRACE PROPAGATION

Every request automatically resolves and propagates:
- `request_id` (Unique Trace ID)
- `actor_id` (ID of the caller)
- `actor_type` (Enum: `user`, `api_key`, `service_account`, `system`, `ai`)
- `organization_id` (Tenant context, if applicable)
- `run_id`, `task_id`, `event_id` (Domain context, if applicable)

---

## 5. PAGINATION, FILTERING & IDEMPOTENCY

### 5.1. Pagination
- **Standard (Offset/Limit)**: Low-volume lists (`?limit=50&offset=0`).
- **Cursor/Keyset Pagination**: Mandatory for high-volume streams (`results`, `runs`, `tasks`, `logs`, `events`).
  - Params: `?limit=100&cursor=eyJpZ...`
  - Meta returns: `next_cursor`, `has_more`.

### 5.2. Filtering & Sorting
Filters are explicitly allowlisted per endpoint.
Common filters: `?status=`, `?platform=`, `?date_from=`, `?date_to=`, `?sort=-created_at`.

### 5.3. Idempotency Semantics
Mandatory for mutation endpoints carrying financial or operational risk:
- Run Creation
- Refund Requests
- Export Creation
- Payment Callbacks
- Credit Reservation/Settlement

**Behavior**: 
Client provides `Idempotency-Key` header. 
- If a duplicate key is received within the expiry window with the *same request fingerprint*, the system returns the **ORIGINAL** response (original HTTP status, original response body, original resource reference). It does NOT force replay to 200/201. (e.g., if original was 202, return 202 with original Run ID).
- If the same key is provided with a *different request fingerprint*, return `409 IDEMPOTENCY_CONFLICT`.

---

## 6. AUTHORIZATION MATRIX

Roles do not solely determine authorization; **permissions** (e.g., `run:write`, `org:manage`) are authoritative.

| Domain | Cust Owner | Cust Dev | Cust Analyst | Cust Viewer | Int Owner | Int Admin | Int Operator | Int Finance | Int Auditor | API Key |
|---|---|---|---|---|---|---|---|---|---|---|
| Auth/Sessions | WRITE | WRITE | WRITE | WRITE | WRITE | WRITE | WRITE | WRITE | WRITE | NONE |
| Org Manage | WRITE | READ | READ | READ | SCOPED | SCOPED | READ | READ | READ | NONE |
| Runs/Scraping | WRITE | WRITE | READ | READ | READ | READ | READ | NONE | READ | WRITE |
| Canonical Results | READ | READ | READ | READ | READ | READ | READ | NONE | READ | READ |
| Billing (Cust) | WRITE | READ | READ | NONE | READ | READ | NONE | READ | READ | NONE |
| API Keys/Webhooks | WRITE | WRITE | READ | READ | SCOPED | SCOPED | READ | NONE | READ | NONE |
| Refund Request | NONE | NONE | NONE | NONE | APPROVE | APPROVE | WRITE(Maker) | WRITE(Maker)| NONE | NONE |
| Refund Approve | NONE | NONE | NONE | NONE | APPROVE | APPROVE | NONE | APPROVE(Checker) | NONE | NONE |
| Internal Finance | NONE | NONE | NONE | NONE | READ | READ | NONE | READ | NONE | NONE |
| Resources/Proxies | NONE | NONE | NONE | NONE | WRITE | WRITE | WRITE | NONE | READ | NONE |
| Selectors | NONE | NONE | NONE | NONE | WRITE | WRITE | WRITE | NONE | READ | NONE |
| Ops/Monitoring | NONE | NONE | NONE | NONE | WRITE | WRITE | WRITE | NONE | READ | NONE |
| Notifications | NONE | NONE | NONE | NONE | WRITE | WRITE | WRITE | NONE | READ | NONE |
| AI Assistant | NONE | NONE | NONE | NONE | SCOPED | SCOPED | SCOPED | SCOPED | SCOPED | NONE |
| RBAC/Security | NONE | NONE | NONE | NONE | WRITE | WRITE | NONE | NONE | READ | NONE |

*(Note: Maker-Checker separation for refunds means an Operator or Finance member can request (Maker), but only an authorized Finance/Owner/Admin (Checker) can approve, provided Maker != Checker).*

---

## 7. ENDPOINT SPECIFICATION

*Permission canonical naming format used: `domain:action` (e.g., `run:read`, `org:write`).*

### 7.1. Auth & Account (Customer & Internal)
- **POST `/app/api/v1/auth/registration`** (Auth: None) - Register new customer.
- **POST `/app/api/v1/auth/google`** (Auth: None) - Google OAuth login.
- **POST `/app/api/v1/auth/login`** (Auth: None) - Authenticate, establish session.
- **POST `/app/api/v1/auth/logout`** (Auth: Session) - Destroy current session.
- **POST `/app/api/v1/auth/logout-all`** (Auth: Session) - Destroy all active sessions.
- **GET `/app/api/v1/auth/me`** (Auth: Session) - Current user.

**Password Recovery (Email/WhatsApp OTP)**
- **POST `/app/api/v1/auth/password-reset/request`** (Auth: None) - Request 6-digit OTP (5m expiry, max 3/day/channel).
- **POST `/app/api/v1/auth/password-reset/verify`** (Auth: None) - Verify OTP. Max 5 attempts.
- **POST `/app/api/v1/auth/password-reset/complete`** (Auth: None) - Set new password.

**MFA & Telegram Pairing**
- **POST `/app/api/v1/auth/mfa/challenge`** (Auth: Session) - Setup/Verify MFA.
- **POST `/app/api/v1/security/telegram/pairing/create`** (Auth: Session) - Initiate Telegram linking.
- **POST `/app/api/v1/security/telegram/pairing/confirm`** (Auth: Session) - Confirm Telegram link.
- **DELETE `/app/api/v1/security/telegram/link`** (Auth: Session) - Unlink channel.
- **POST `/app/api/v1/security/whatsapp/verification/request`** (Auth: Session) - Request WA link.
- **POST `/app/api/v1/security/whatsapp/verification/verify`** (Auth: Session) - Verify WA link.

**Sessions**
- **GET `/app/api/v1/auth/sessions`** (Auth: Session) - List active sessions/devices.
- **DELETE `/app/api/v1/auth/sessions/{id}`** (Auth: Session) - Revoke specific session.
- **GET `/app/api/v1/auth/login-history`** (Auth: Session) - Audit list of logins.

### 7.2. Organization & Members (Customer)
- **GET `/app/api/v1/organization`** (Perm: `org:read`) - Details.
- **PATCH `/app/api/v1/organization`** (Perm: `org:write`) - Edit.
- **GET `/app/api/v1/organization/members`** (Perm: `org:read`) - Member list.
- **POST `/app/api/v1/organization/invites`** (Perm: `org:write`) - Send invite.
- **GET `/app/api/v1/organization/invites`** (Perm: `org:read`) - List pending invites.
- **DELETE `/app/api/v1/organization/invites/{id}`** (Perm: `org:write`) - Revoke invite.
- **PATCH `/app/api/v1/organization/members/{id}/role`** (Perm: `org:write`) - Change role.
- **DELETE `/app/api/v1/organization/members/{id}`** (Perm: `org:write`) - Remove member.

### 7.3. Scraper Catalog (Public & Customer)
- **GET `/api/v1/scrapers`** (Perm: `catalog:read`) - List available scraping capabilities, inputs, limits, service status.

### 7.4. Scraping Runs & Tasks (Public, Customer, Internal)
- **POST `/api/v1/runs`** (Perm: `run:write`) - Submit new batch scraping job. Async. Returns 202 Accepted. Idempotency Required.
- **GET `/api/v1/runs`** (Perm: `run:read`) - Cursor-paginated run history.
- **GET `/api/v1/runs/{id}`** (Perm: `run:read`) - Status (`QUEUED`, `RUNNING`, `COMPLETED`, `PARTIAL`, `FAILED`, `CANCELLED`).
- **POST `/api/v1/runs/{id}/cancel`** (Perm: `run:write`) - Request cancellation.
*(Note: Comments & Replies are handled via independent scraper capabilities, preserving lineage via request inputs).*

**Internal Task Ops**
- **GET `/internal/api/v1/tasks`** (Perm: `ops:read`) - List worker tasks.
- **GET `/internal/api/v1/tasks/{id}`** (Perm: `ops:read`) - Detail & lease state.
- **GET `/internal/api/v1/tasks/{id}/attempts`** (Perm: `ops:read`) - Retry state & error classification.

### 7.5. Results & Exports (Public & Customer)
- **GET `/api/v1/runs/{id}/results`** (Perm: `result:read`) - Cursor-paginated tenant-scoped canonical results.
- **POST `/app/api/v1/exports`** (Perm: `export:write`) - Trigger async export (CSV, XLSX, PDF, JSON). Idempotency Required.
- **GET `/app/api/v1/exports`** (Perm: `export:read`) - List exports.
- **GET `/app/api/v1/exports/{id}`** (Perm: `export:read`) - Detail.
- **GET `/app/api/v1/exports/{id}/download`** (Perm: `export:read`) - Get pre-signed URL (blocked if EXPIRED).

### 7.6. API Keys & Webhooks (Customer)
- **POST `/app/api/v1/api-keys`** (Perm: `apikey:write`) - Create (returns plaintext ONCE).
- **GET `/app/api/v1/api-keys`** (Perm: `apikey:read`) - List (hash prefix only).
- **GET `/app/api/v1/api-keys/{id}`** (Perm: `apikey:read`) - Detail.
- **DELETE `/app/api/v1/api-keys/{id}`** (Perm: `apikey:write`) - Revoke.
- **POST `/app/api/v1/webhooks`** (Perm: `webhook:write`) - Create endpoint.
- **GET `/app/api/v1/webhooks`** (Perm: `webhook:read`) - List endpoints.
- **GET `/app/api/v1/webhooks/{id}`** (Perm: `webhook:read`) - Detail.
- **PATCH `/app/api/v1/webhooks/{id}`** (Perm: `webhook:write`) - Edit.
- **DELETE `/app/api/v1/webhooks/{id}`** (Perm: `webhook:write`) - Archive.
- **GET `/app/api/v1/webhooks/{id}/deliveries`** (Perm: `webhook:read`) - Cursor-paginated delivery logs.
- **GET `/app/api/v1/webhooks/deliveries/{id}`** (Perm: `webhook:read`) - Delivery detail.

### 7.7. API Logs & Usage (Customer)
- **GET `/app/api/v1/api-logs`** (Perm: `log:read`) - Read-only API usage logs.
- **GET `/app/api/v1/usage/credits`** (Perm: `billing:read`) - Current balance.
- **GET `/app/api/v1/usage/timeline`** (Perm: `billing:read`) - Usage timeline.
- **GET `/app/api/v1/usage/summary`** (Perm: `billing:read`) - Usage summary.

### 7.8. Billing, Packages & Subscriptions (Customer & Internal)
- **GET `/app/api/v1/billing/package`** (Perm: `billing:read`) - Current package features.
- **GET `/app/api/v1/billing/subscription`** (Perm: `billing:read`) - Current subscription.
- **GET `/app/api/v1/billing/invoices`** (Perm: `billing:read`) - Invoice list.
- **GET `/app/api/v1/billing/invoices/{id}`** (Perm: `billing:read`) - Detail.
- **GET `/app/api/v1/billing/payments`** (Perm: `billing:read`) - Payment list.
- **GET `/app/api/v1/billing/payments/{id}`** (Perm: `billing:read`) - Detail.

**Internal Package Management**
- **GET `/internal/api/v1/packages`** (Perm: `package:read`) - List packages.
- **POST `/internal/api/v1/packages`** (Perm: `package:write`) - Create.
- **PATCH `/internal/api/v1/packages/{id}`** (Perm: `package:write`) - Edit.
- **POST `/internal/api/v1/packages/{id}/activate`** (Perm: `package:write`) - Activate/Deactivate.
- **GET `/internal/api/v1/subscriptions`** (Perm: `billing:read`) - List subscriptions.

### 7.9. Finance, Refunds & Margins (Internal Only)
- **GET `/internal/api/v1/finance/revenue`** (Perm: `finance:read`) - Revenue report.
- **GET `/internal/api/v1/finance/cost`** (Perm: `finance:read`) - Internal cost.
- **GET `/internal/api/v1/finance/profit`** (Perm: `finance:read`) - Profit/Margin (Harga vs Biaya).
- **GET `/internal/api/v1/finance/scraper-cost`** (Perm: `finance:read`) - Scraper Cost Analysis.
- **GET `/internal/api/v1/finance/customer-profitability`** (Perm: `finance:read`) - Customer Profitability.
- **GET `/internal/api/v1/finance/credit-ledger`** (Perm: `finance:read`) - Credit ledger audit.

**Refund Maker-Checker**
- **GET `/internal/api/v1/refunds`** (Perm: `refund:read`) - List refund requests.
- **POST `/internal/api/v1/refunds/request`** (Perm: `refund:write`) - Submit refund request. Idempotency Required.
- **GET `/internal/api/v1/refunds/{id}`** (Perm: `refund:read`) - Detail.
- **POST `/internal/api/v1/refunds/{id}/approve`** (Perm: `refund:approve`) - Approve. Requires MFA. (Maker != Checker).
- **POST `/internal/api/v1/refunds/{id}/reject`** (Perm: `refund:approve`) - Reject.

### 7.10. Admin Users & Organizations (Internal)
- **GET `/internal/api/v1/users`** (Perm: `user:read`) - List users.
- **POST `/internal/api/v1/users`** (Perm: `user:write`) - Create user.
- **PATCH `/internal/api/v1/users/{id}`** (Perm: `user:write`) - Edit.
- **POST `/internal/api/v1/users/{id}/suspend`** (Perm: `user:write`) - Suspend.
- **POST `/internal/api/v1/users/{id}/reactivate`** (Perm: `user:write`) - Reactivate.
- **POST `/internal/api/v1/users/{id}/force-logout`** (Perm: `user:write`) - Force logout all sessions.
- **GET `/internal/api/v1/organizations`** (Perm: `org:read`) - List global orgs.
- **GET `/internal/api/v1/organizations/{id}`** (Perm: `org:read`) - Detail.
- **PATCH `/internal/api/v1/organizations/{id}`** (Perm: `org:write`) - Edit.

### 7.11. Selectors (Internal)
- **GET `/internal/api/v1/selectors`** (Perm: `selector:read`) - List.
- **POST `/internal/api/v1/selectors`** (Perm: `selector:write`) - Create.
- **GET `/internal/api/v1/selectors/{id}`** (Perm: `selector:read`) - Detail.
- **GET `/internal/api/v1/selectors/{id}/versions`** (Perm: `selector:read`) - Version list.
- **GET `/internal/api/v1/selectors/versions/{id}`** (Perm: `selector:read`) - Version detail.
- **POST `/internal/api/v1/selectors/{id}/versions`** (Perm: `selector:write`) - Draft create.
- **PATCH `/internal/api/v1/selectors/versions/{id}`** (Perm: `selector:write`) - Draft edit.
- **POST `/internal/api/v1/selectors/versions/{id}/test`** (Perm: `selector:test`) - Test version (does not activate).
- **POST `/internal/api/v1/selectors/versions/{id}/preview`** (Perm: `selector:test`) - Preview payload (does not activate).
- **POST `/internal/api/v1/selectors/versions/{id}/activate`** (Perm: `selector:activate`) - Promote to ACTIVE. High risk.
- **POST `/internal/api/v1/selectors/versions/{id}/rollback`** (Perm: `selector:activate`) - Rollback version.

### 7.12. Social Accounts & Pools (Internal)
- **GET `/internal/api/v1/social-accounts`** (Perm: `resource:read`) - List (status, health). No credentials.
- **POST `/internal/api/v1/social-accounts`** (Perm: `resource:write`) - Create.
- **GET `/internal/api/v1/social-accounts/{id}`** (Perm: `resource:read`) - Detail.
- **PATCH `/internal/api/v1/social-accounts/{id}`** (Perm: `resource:write`) - Edit.
- **POST `/internal/api/v1/social-accounts/{id}/enable`** (Perm: `resource:write`) - Enable/Disable.
- **POST `/internal/api/v1/social-accounts/{id}/test`** (Perm: `resource:write`) - Test session.
- **GET `/internal/api/v1/social-pools`** (Perm: `resource:read`) - Pool list.
- **POST `/internal/api/v1/social-pools`** (Perm: `resource:write`) - Create pool.
- **GET `/internal/api/v1/social-pools/{id}`** (Perm: `resource:read`) - Detail.
- **PATCH `/internal/api/v1/social-pools/{id}`** (Perm: `resource:write`) - Edit.
- **GET `/internal/api/v1/social-pools/{id}/members`** (Perm: `resource:read`) - List members.
- **POST `/internal/api/v1/social-pools/{id}/members`** (Perm: `resource:write`) - Add member.
- **DELETE `/internal/api/v1/social-pools/{id}/members/{accountId}`** (Perm: `resource:write`) - Remove member.

### 7.13. Proxies & Pools (Internal)
*(Same API structure as Social Accounts, mapped to `/proxies` and `/proxy-pools`. Never returns proxy passwords).*

### 7.14. Operations, Incidents & Maintenance (Internal)
- **GET `/internal/api/v1/monitoring/health`** (Perm: `ops:read`) - System Health.
- **GET `/internal/api/v1/monitoring/platform-health`** (Perm: `ops:read`)
- **GET `/internal/api/v1/monitoring/scraper-health`** (Perm: `ops:read`)
- **GET `/internal/api/v1/monitoring/pool-health`** (Perm: `ops:read`)
- **GET `/internal/api/v1/monitoring/queue-summary`** (Perm: `ops:read`)
- **GET `/internal/api/v1/monitoring/queue-detail`** (Perm: `ops:read`)
- **GET `/internal/api/v1/monitoring/worker-groups`** (Perm: `ops:read`)
- **GET `/internal/api/v1/monitoring/worker-detail`** (Perm: `ops:read`)
- **GET `/internal/api/v1/monitoring/reconciliation-status`** (Perm: `ops:read`)
*(Note: No unrestricted arbitrary Redis command exposure).*

**Errors & Incidents**
- **GET `/internal/api/v1/errors/fingerprints`** (Perm: `ops:read`) - List.
- **GET `/internal/api/v1/errors/fingerprints/{id}`** (Perm: `ops:read`) - Detail.
- **GET `/internal/api/v1/errors/occurrences`** (Perm: `ops:read`) - Occurrences.
- **GET `/internal/api/v1/errors/fingerprints/{id}/affected-runs`** (Perm: `ops:read`)
- **GET `/internal/api/v1/errors/fingerprints/{id}/affected-resources`** (Perm: `ops:read`)
- **GET `/internal/api/v1/incidents`** (Perm: `ops:read`) - List.
- **POST `/internal/api/v1/incidents`** (Perm: `ops:write`) - Create.
- **GET `/internal/api/v1/incidents/{id}`** (Perm: `ops:read`) - Detail.
- **PATCH `/internal/api/v1/incidents/{id}`** (Perm: `ops:write`) - Edit.
- **POST `/internal/api/v1/incidents/{id}/resolve`** (Perm: `ops:write`) - Resolve.
- **GET `/internal/api/v1/incidents/{id}/timeline`** (Perm: `ops:read`) - Timeline.

**Maintenance**
- **GET `/internal/api/v1/maintenance/history`** (Perm: `ops:read`) - History.
- **GET `/internal/api/v1/maintenance/capabilities`** (Perm: `ops:read`) - Capability Status.
- **POST `/internal/api/v1/maintenance/capabilities/{id}/enter`** (Perm: `ops:write`) - Enter Maintenance. CRITICAL.
- **POST `/internal/api/v1/maintenance/capabilities/{id}/exit`** (Perm: `ops:write`) - Exit Maintenance.

### 7.15. Notifications & WhatsApp (Internal)
- **GET `/internal/api/v1/notification-providers`** (Perm: `notify:read`) - List health.
- **GET `/internal/api/v1/notification-providers/telegram`** (Perm: `notify:read`) - Safe config.
- **GET `/internal/api/v1/notification-providers/email`** (Perm: `notify:read`) - Safe config.
- **POST `/internal/api/v1/notification-providers/{provider}/test`** (Perm: `notify:write`) - Test.

**WhatsApp Evolution API**
- **GET `/internal/api/v1/whatsapp/instances`** (Perm: `notify:read`) - List instances.
- **POST `/internal/api/v1/whatsapp/instances`** (Perm: `notify:write`) - Create.
- **GET `/internal/api/v1/whatsapp/instances/{id}`** (Perm: `notify:read`) - Detail.
- **PATCH `/internal/api/v1/whatsapp/instances/{id}`** (Perm: `notify:write`) - Edit.
- **POST `/internal/api/v1/whatsapp/instances/{id}/enable`** (Perm: `notify:write`) - Enable/Disable.
- **POST `/internal/api/v1/whatsapp/instances/{id}/test`** (Perm: `notify:write`) - Test.
- **GET `/internal/api/v1/whatsapp/pools`** (Perm: `notify:read`) - List.
- **POST `/internal/api/v1/whatsapp/pools`** (Perm: `notify:write`) - Create.
- **GET `/internal/api/v1/whatsapp/pools/{id}`** (Perm: `notify:read`) - Detail.
- **PATCH `/internal/api/v1/whatsapp/pools/{id}`** (Perm: `notify:write`) - Edit.
- **GET `/internal/api/v1/whatsapp/pools/{id}/members`** (Perm: `notify:read`) - Members.

**Templates, Rules, Delivery Logs**
- **GET `/internal/api/v1/notification-templates`** (Perm: `notify:read`) - List.
- **POST `/internal/api/v1/notification-templates`** (Perm: `notify:write`) - Create.
- **GET `/internal/api/v1/notification-templates/{id}`** (Perm: `notify:read`) - Detail.
- **PATCH `/internal/api/v1/notification-templates/{id}`** (Perm: `notify:write`) - Edit.
- **GET `/internal/api/v1/notification-rules`** (Perm: `notify:read`) - List.
- **POST `/internal/api/v1/notification-rules`** (Perm: `notify:write`) - Create.
- **GET `/internal/api/v1/notification-rules/{id}`** (Perm: `notify:read`) - Detail.
- **PATCH `/internal/api/v1/notification-rules/{id}`** (Perm: `notify:write`) - Edit.
- **GET `/internal/api/v1/notification-deliveries`** (Perm: `notify:read`) - Logs.
- **GET `/internal/api/v1/notification-deliveries/{id}`** (Perm: `notify:read`) - Detail.
- **POST `/internal/api/v1/notification-deliveries/{id}/retry`** (Perm: `notify:write`) - Retry (if allowed).

### 7.16. Internal AI Assistant (Internal Only)
*AI is strictly INTERNAL, READ-ONLY in v1, and inherits the user's actor permissions.*
- **GET `/internal/api/v1/ai/conversations`** (Perm: `ai:use`) - List.
- **POST `/internal/api/v1/ai/conversations`** (Perm: `ai:use`) - Create.
- **GET `/internal/api/v1/ai/conversations/{id}`** (Perm: `ai:use`) - Detail.
- **POST `/internal/api/v1/ai/conversations/{id}/messages`** (Perm: `ai:use`) - Send message (triggers tool execution).
- **GET `/internal/api/v1/ai/usage`** (Perm: `ai:audit`) - Token usage/cost.
- **GET `/internal/api/v1/ai/audit`** (Perm: `ai:audit`) - Audit trail.
- **GET `/internal/api/v1/ai/channel-access`** (Perm: `ai:manage`) - Access control list for WhatsApp/Telegram AI channels.

### 7.17. Security, RBAC & Access Governance (Internal)
- **GET `/internal/api/v1/security/roles`** (Perm: `security:read`) - List.
- **POST `/internal/api/v1/security/roles`** (Perm: `security:write`) - Create.
- **GET `/internal/api/v1/security/roles/{id}`** (Perm: `security:read`) - Detail.
- **PATCH `/internal/api/v1/security/roles/{id}`** (Perm: `security:write`) - Edit.
- **GET `/internal/api/v1/security/permissions`** (Perm: `security:read`) - Registry.
- **POST `/internal/api/v1/security/role-permissions`** (Perm: `security:write`) - Assign permissions.

**Temporary Access & Break-Glass**
- **GET `/internal/api/v1/security/temporary-access`** (Perm: `security:read`) - List.
- **POST `/internal/api/v1/security/temporary-access/request`** (Perm: `security:request`) - Request elevation.
- **GET `/internal/api/v1/security/temporary-access/{id}`** (Perm: `security:read`) - Detail.
- **POST `/internal/api/v1/security/temporary-access/{id}/approve`** (Perm: `security:approve`) - Approve.
- **POST `/internal/api/v1/security/temporary-access/{id}/revoke`** (Perm: `security:approve`) - Revoke.
- **GET `/internal/api/v1/security/access-reviews`** (Perm: `security:read`) - List reviews.
- **POST `/internal/api/v1/security/access-reviews/{id}/decision`** (Perm: `security:approve`) - Review Decision.
- **POST `/internal/api/v1/security/break-glass/activate`** (Perm: `security:breakglass`) - Activate emergency root access. Highly audited.
- **POST `/internal/api/v1/security/break-glass/{id}/deactivate`** (Perm: `security:breakglass`) - Deactivate.
- **GET `/internal/api/v1/security/break-glass/{id}/audit`** (Perm: `security:read`) - Break-glass audit trail.

**Audit & Security Events**
- **GET `/internal/api/v1/security/audit-logs`** (Perm: `audit:read`) - Cursor-paginated, filterable immutable logs.
- **GET `/internal/api/v1/security/audit-logs/{id}`** (Perm: `audit:read`) - Detail.
- **GET `/internal/api/v1/security/events`** (Perm: `audit:read`) - Security events stream.
- **GET `/internal/api/v1/security/authentication-logs`** (Perm: `audit:read`) - Authentication history.
*(No mutation endpoints for immutable audit logs).*

### 7.18. Branding & Integrations (Internal)
- **GET `/internal/api/v1/settings/branding`** (Perm: `settings:read`) - Safe GET.
- **PUT `/internal/api/v1/settings/branding`** (Perm: `settings:write`) - Update branding.
- **POST `/internal/api/v1/settings/branding/assets`** (Perm: `settings:write`) - Logo/Favicon upload.
- **GET `/internal/api/v1/settings/integrations`** (Perm: `settings:read`) - Configured state (Object Storage, AI Provider, Evolution API, Telegram, SMTP). NEVER returns plaintext secrets.
- **POST `/internal/api/v1/settings/integrations/{provider}`** (Perm: `settings:write`) - Write-only secret replacement.
- **POST `/internal/api/v1/settings/integrations/{provider}/test`** (Perm: `settings:write`) - Test connection.
- **GET `/internal/api/v1/settings/system`** (Perm: `settings:read`) - Read domain-grouped safe settings.
- **PATCH `/internal/api/v1/settings/system`** (Perm: `settings:write`) - Update settings (Schema validated).

---

## 8. COVERAGE MATRIX

| PRD/API Domain | API Boundary | Endpoint Types | Status |
|---|---|---|---|
| Auth / OTP / Telegram / WA | App / Internal | Login, Register, Recovery, Sessions, Pair, Verifications | COMPLETE |
| Organization / Members | App / Internal | Detail, Edit, List, Invite, Roles | COMPLETE |
| Runs / Results / Replies | Public / App | List, Detail, Create, Cancel, Canonical Result isolation | COMPLETE |
| Exports | App | Create, List, Detail, Download URL | COMPLETE |
| API Keys / Webhooks / Usage | App | CRUD, Delivery Logs, Timelines | COMPLETE |
| Billing / Packages / Invoice | App / Internal | Info, Lists, Edit, Detail | COMPLETE |
| Refunds / Finance / Cost | Internal | Maker-Checker Request/Approve, Margin, Profit, Ledger | COMPLETE |
| Scrapers / Selectors | Internal | Capabilities, DRAFT, TEST, PREVIEW, ACTIVATE, ROLLBACK | COMPLETE |
| Social Accounts / Proxies | Internal | Pools, Members, Sessions, Enable/Disable, CRUD | COMPLETE |
| Monitoring / Errors / Incidents | Internal | System Health, Fingerprints, Timelines, Queue, Workers | COMPLETE |
| Maintenance | Internal | Enter/Exit Maintenance, Capabilities, Status | COMPLETE |
| Notifications / Providers | Internal | WA Instances/Pools, Telegram, Email, Rules, Logs | COMPLETE |
| AI Assistant | Internal | INTERNAL ONLY, Read-Only, Channels, Usage, Audit | COMPLETE |
| Security / RBAC / Temp Access | Internal | Elevate, Break-glass, Access Review, Roles, Assign | COMPLETE |
| Audit Logs | Internal | Immutable GET, Filtering | COMPLETE |
| Branding / Settings | Internal | Replace secrets, Asset upload, Validation | COMPLETE |

---

## 9. OPEN DECISIONS
- No blocking product decisions remain for logical API architecture.


## RUN CREATION PATTERN

The system STRICTLY uses capability-specific run creation endpoints. 
`POST /api/v1/runs` is explicitly forbidden.

Instead, the following pattern is used:
`POST /api/v1/<platform>/<capability>/runs`
`POST /app/api/v1/<platform>/<capability>/runs` (Customer App)

Supported combinations:
- Facebook: posts, comments
- Instagram: posts, reels, comments
- TikTok: videos, comments
- YouTube: videos, comments
- X: posts, replies
- News: articles
- Web: pages

All create routes require:
- Idempotency-Key
- `run:write` permission
- Capability-specific typed request schema
- 202 Accepted response with `reference_id`


## RUN CREATION PREFLIGHT CONTRACT

Before any Run, Task, or Billing Reservation is created, the system MUST verify:
- The organization is active and not suspended.
- The actor is authorized (`run:write`).
- The capability is supported by the platform.
- The package/subscription entitlement allows this capability.
- Quota/credit eligibility is satisfied.
- The service is not in maintenance mode.

**If the preflight check blocks the request:**
- NO Task is created.
- NO resource lease is taken.
- NO billing reservation is created.
- The appropriate canonical API error is returned (e.g., 402 Payment Required, 403 Forbidden, 503 Service Unavailable).

*The OpenAPI documentation explicitly models these semantics.*
