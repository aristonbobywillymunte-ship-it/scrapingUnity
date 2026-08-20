# NOTIFICATION ARCHITECTURE

This document defines the authoritative, asynchronous notification and delivery architecture for the platform. It strictly enforces the isolation of notification side-effects from primary domain operations (Runs, Billing) and ensures robust, idempotent delivery across In-App, WhatsApp, Telegram, and Email channels.

---

## 1. EVENT FLOW & EVENT ENVELOPE

All notifications follow a standardized async lifecycle:

1. **Domain Event**: System emits an idempotent event (e.g., `run.completed`).
2. **Notification Rule**: System matches the event against Tenant/User configured Rules.
3. **Recipient Resolution**: Identifies the verified contact channels for the recipient scope.
4. **Template Rendering**: Merges event variables into versioned channel templates.
5. **Delivery Job**: Dispatches targeted jobs to logical channel queues.
6. **Provider/Instance Selection**: Resolves the specific healthy provider/instance (e.g., WA Evolution Node).
7. **Send**: Attempt delivery via the provider API.
8. **Delivery Attempt**: Persists attempt metrics and provider trace IDs.
9. **Provider Callback/Polling**: Asynchronously updates final status based on provider webhook/response.
10. **Final Status**: Updates delivery to a terminal state (`DELIVERED`, `FAILED`).

### Canonical Event Envelope
Every notification domain event strictly uses the following schema:
```json
{
  "event_id": "evt_abc123",
  "event_type": "run.completed",
  "event_version": "1.0",
  "organization_id": "org_555",
  "actor": "sys",
  "occurred_at": "2023-10-01T10:00:00Z",
  "payload": {
    "run_id": "run_98765",
    "saved_items": 100
  },
  "dedupe_key": "evt_abc123"
}
```
*The payload is schema-controlled and MUST NOT contain secrets.*

---

## 2. EVENTS

The event catalog is extensible and versioned. Core events include:
- `run.completed` / `run.partial` / `run.failed`
- `export.ready`
- `subscription.started` / `subscription.expired` / `package.updated`
- `payment.success` / `payment.failed`
- `refund.approved` / `refund.rejected`
- `security.login_new_device` / `security.breakglass_activated`
- `system.maintenance` / `operations.alert`

---

## 3. RECIPIENT RESOLUTION

Notifications resolve their physical destinations by:
- Organization membership / User identity.
- Evaluated Notification Rule.
- **Verified Contact Channel**: The system MUST NOT send to unverified WhatsApp/Telegram identities if the channel requires explicit verification/pairing.
- **Email Verification**: An arbitrary stored email address is NOT automatically trusted for sensitive security notifications. Email recipients must utilize the account/contact verification state required by authentication and notification policy.
- Permission context (e.g., Finance events only route to Finance/Owner roles).

---

## 4. WHATSAPP EVOLUTION (MULTI-INSTANCE)

WhatsApp delivery relies on an Evolution API integration supporting **multiple instances**.

- **Instance Pool**: Multiple connected numbers/sessions for load distribution.
- **Health & Availability**: Managed via heartbeat. States: `healthy`, `degraded`, `cooldown`, `unavailable`, `disabled`.
- **Selection**: A Delivery Job selects an eligible, healthy instance.
- **Concurrency & Lease**: An instance is leased for a single send to prevent rate-limit saturation.
- **Failover & Retry**: If one WA instance fails (e.g., network timeout), the job may failover to another eligible instance in the pool. *One instance failure must NOT block all WhatsApp delivery.*
- **Security**: Tokens are NEVER exposed or stored in queue payloads/logs.

---

## 5. TELEGRAM

- **Configuration**: Uses official Bot API configurations.
- **Binding**: Users/channels must complete a verified pairing flow to receive notifications.
- **Health & Retry**: Managed via API responses (HTTP 429 delays, 5xx backoffs). Transient errors use jittered backoff.

---

## 6. EMAIL

- **Configuration**: Standard SMTP or Email API provider.
- **Identity**: Uses a safe, authenticated sender identity (SPF/DKIM/DMARC).
- **Classification**: Bounces (Hard/Soft) and Spam complaints are parsed to classify failures and disable invalid recipient addresses.

---

## 7. IN-APP

In-App notifications are persistent ledger records accessible via the Customer Portal and Internal Admin Dashboard.
*Scope: In-App notifications serve both customer users AND authorized internal users (for operations/security alerts) according to event/rule/permission scope.*

Schema: `user_id`, `event_type`, `title`, `body`, `status` (`read`/`unread`), `created_at`, `read_at`, `action_reference`.

---

## 8. RULES & TEMPLATES

**Notification Rules**:
Support evaluation based on: `event_type`, `channels`, `recipient_scope`, `enabled`, `conditions`, `priority`.
*Rule evaluation uses deterministic criteria. Arbitrary executable code conditions are prohibited.*

**Templates**:
Versioned by `event_type`, `channel`, and `locale`.
Rendering strictly enforces an allowlist of safe variables.

---

## 9. DELIVERY LIFECYCLE & IDEMPOTENCY

The architecture enforces a strict distinction between the canonical API-visible delivery status (which MUST match the locked OpenAPI specification) and the internal operational runtime phases.

### 9.1 Canonical API-Visible `delivery_status`
The public API and Database Enums strictly use ONLY these locked states:
- `QUEUED`
- `SENDING`
- `DELIVERED`
- `FAILED`

### 9.2 Internal `runtime_phase` & Mappings
The system tracks detailed lifecycle metadata internally (`runtime_phase`, `failure_reason`), which maps directly onto the locked public statuses without violating the contract:

- **`QUEUED` + `runtime_phase=retry_wait`**: The notification hit a transient error and is waiting for backoff to retry.
- **`SENDING` + `runtime_phase=provider_accepted`**: The provider acknowledged receipt of the payload via API, but we are awaiting final callback confirmation (e.g. WhatsApp read/delivered ticks, SendGrid webhook). *Provider Accepted != Delivered.*
- **`FAILED` + `failure_reason=cancelled_before_send`**: The notification was cancelled cooperatively before being delivered.

### 9.3 Delivery Semantics
- `DELIVERED` means either a confirmed delivery receipt (where the provider supports it), OR provider-accepted terminal success when a channel has no stronger delivery confirmation (explicitly documented as such).
- Lost callbacks/status updates MUST be handled by Reconciliation.

### 9.4 Idempotency & Attempts
**Idempotency**: Each logical notification receives a stable identity (`notify_{event_id}_{channel}_{recipient_id}`). Duplicate domain events MUST NOT produce duplicate deliveries.
**Delivery Attempts**: Each attempt records: `delivery_id`, `attempt_number`, `provider_instance_safe_ref`, `started_at`, `completed_at`, `provider_reference`, `safe_error`, `latency`.

---

## 10. PROVIDER CALLBACK SECURITY

Inbound provider callbacks/webhooks (e.g., from WhatsApp Evolution, SendGrid) must adhere to:
- **Authentication**: Authenticate/verify provider callback signatures where supported.
- **Idempotency**: Processing must be strictly idempotent using `provider_event_id` or dedupe identity.
- **State Protection**: Reject duplicate or invalid state transitions safely.
- **Trust Boundary**: Never blindly trust arbitrary public callback payloads.
- **Safe Logging**: Mask PII/tokens in diagnostic logs.

---

## 11. RETRY, FAILOVER & HEALTH

**Retry Policy**:
- **Transient (Network/Provider)**: Move to `QUEUED` + `runtime_phase=retry_wait`. Use config-driven exponential backoff and jitter. No infinite loops.
- **Permanent (Invalid Destination/Blocked)**: Terminal `FAILED`.
- **Unhealthy Instance**: Safe failover to alternate instance (WA pool).

**Provider Health**:
Tracked as: `healthy`, `degraded`, `cooldown`, `unavailable`, `disabled`.
*One timeout MUST NOT permanently disable a provider/instance. Classification requires thresholds.*

---

## 12. QUEUES & PRIORITY

**Logical Queues**:
- `queue:notifications_inapp`
- `queue:notifications_whatsapp`
- `queue:notifications_telegram`
- `queue:notifications_email`
*(Queues may share underlying compute infrastructure but remain independently throttled and observable).*

**Priority Classes**:
`critical` > `high` > `normal` > `low`
*Security and System alerts (critical) preempt bulk Run notifications (normal).*

**Rate Limiting**:
Channel/provider-specific limits are enforced. Tenant-level debouncing prevents notification storms from a single tenant.

---

## 13. AGGREGATION, DIGEST & FAILURE SAFETY

**Digest/Dedupe**:
The system allows aggregation/digest configurations where permitted by product policy. Critical security alerts are NEVER collapsed.

**Failure Safety (CRITICAL)**:
Notification delivery is a downstream **side effect**.
Notification failure MUST NOT:
- Fail a successful scraping Run.
- Reverse completed Billing settlements.
- Corrupt domain transactions.

---

## 14. INTERNAL AI CHANNEL BOUNDARY

WhatsApp and Telegram output notification channels are **strictly separate** from the Internal AI Assistant's message handling flow.
- The Notification Engine does NOT route arbitrary notification replies into the AI pipeline.
- AI inbound flows require a verified internal-user binding AND explicit `ai.use` permissions.

---

## 15. RECONCILIATION

Idempotent recovery logic runs periodically to repair state inconsistencies:
- **Stuck `QUEUED`/`SENDING`**: Timeouts revert to `FAILED` based on attempt limits, or remain `QUEUED` if eligible for retry.
- **Orphan Provider Lease**: Expired leases are forcibly released back to the WA pool.
- **Status Callback Lost**: Polling/Reconciliation against the Provider API (where supported) to finalize `SENDING` + `runtime_phase=provider_accepted` jobs to `DELIVERED`.
- **Duplicate Domain Event**: Rejected at insertion via idempotency key.
- **Terminal Delivery pending job**: Cleanup of lingering Redis payloads whose DB state is already terminal.

---

## 16. ARCHITECTURE MATRICES

### A. Event → Allowed Channels
*(Channel eligibility is policy/rule controlled. No arbitrary architectural restrictions are invented without explicit PRD policy).*
| Event Type | In-App | Email | WhatsApp | Telegram |
|---|---|---|---|---|
| `run.*` | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED |
| `export.ready` | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED |
| `subscription.*` | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED |
| `payment.*` / `refund.*`| POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED |
| `security.*` | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED |
| `system.maintenance` | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED | POLICY/RULE CONTROLLED |

### B. Delivery Status Transition
| Current Canonical State | Internal Phase / Event | Next Canonical State |
|---|---|---|
| `QUEUED` | Job Picked | `SENDING` |
| `SENDING` | Provider Accepted Payload | `SENDING` (*runtime_phase=provider_accepted*) |
| `SENDING` | Callback Success / Confirmed Receipt | `DELIVERED` |
| `SENDING` | Transient Error + Retry Budget | `QUEUED` (*runtime_phase=retry_wait*) |
| `SENDING` | Hard Error or Callback Failure | `FAILED` |
| `QUEUED` | Cancellation Signal | `FAILED` (*failure_reason=cancelled_before_send*) |

### C. Provider Failure → Retry/Failover
| Failure Category | Multi-Instance Failover? | Retry Action |
|---|---|---|
| Transient Network Timeout | YES (Select another WA instance)| Delayed Retry (Backoff/Jitter)|
| Target Rate Limit (429) | NO | Delayed Retry |
| Hard Bounced / Invalid Dest | NO | Terminal FAILED |
| Auth Failure (Provider) | YES (Rotate to valid instance) | Delayed Retry |

### D. Recipient Verification Requirement
| Channel | Explicit User Pairing Required? | Verification Method |
|---|---|---|
| In-App | NO (Implicit via Auth) | Session |
| Email | YES (For sensitive policy) | Profile/Registration/Verification Token |
| WhatsApp| YES | OTP / Initial Ping verification |
| Telegram| YES | OAuth / Deep-link Bot Pairing |

### E. Customer vs Internal Notification Visibility
| Record Type | Customer Visible | Internal (Admin/Finance) |
|---|---|---|
| In-App Payload | YES (Customer scoped) | YES (Internal scoped) |
| Delivery Status/Logs | YES (Summarized) | YES (Detailed) |
| Notification Rules | YES (Tenant owned) | YES |
| Template Definitions | NO | YES |
| Provider Config/Tokens| NO | YES (Encrypted) |

---

## 17. OPEN DECISIONS
- No blocking decisions remain regarding notification delivery flow, queue isolation, callback handling, or event envelope schemas.

