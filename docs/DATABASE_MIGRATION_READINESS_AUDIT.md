# DATABASE MIGRATION READINESS AUDIT

This document audits the planned physical database schema as defined in `DATABASE_PHYSICAL_SCHEMA_BLUEPRINT.md` against all locked product and architecture contracts.

## 1. REAL TABLE READINESS MATRIX

| Table | Purpose | Tenant Scope | PK | Major FK | Critical Unique | Critical Checks | Critical Index | Immutable? | Encrypted? | Retention/Delete | Readiness |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `organizations` | Tenant Identity | Global | id | - | name | - | status | No | No | Soft Delete | READY |
| `users` | Auth | Global | id | - | email | - | - | No | Hashed Pass | Soft Delete | READY |
| `roles` | RBAC Definition | Global | id | - | - | - | - | No | No | Never | READY |
| `permissions` | RBAC Definition | Global | id | - | - | - | - | No | No | Never | READY |
| `role_permissions` | RBAC Definition | Global | role, perm | role, perm | (role, perm) | - | - | No | No | Never | READY |
| `organization_memberships`| Customer RBAC | org_id | id | org, user, role| (org, user) | role_id not internal | - | No | No | Soft Delete | READY |
| `internal_user_assignments`| Internal RBAC | Global | id | user, role | (user, role) | role_id is internal | - | No | No | Soft Delete | READY |
| `auth_sessions` | Login state | Global | id | user | token_hash | - | expires_at | No | Hashed Token | Expirable | READY |
| `auth_logs` | Security history| Global | id | user | - | - | created_at | Yes | No | Expirable | READY |
| `service_actors` | Service Identity| Global | id | - | - | - | - | No | Hashed Creds | Soft Delete | READY |
| `temporary_access_grants`| Temp Access | Global | id | user, approver | - | - | expires_at | No | No | Expirable | READY |
| `jit_privilege_grants` | JIT Privileges | Global | id | user, req, apprv | - | - | expires_at | No | No | Expirable | READY |
| `break_glass_activations`| Emergency | Global | id | user | - | - | expires_at | No | No | Expirable | READY |
| `access_reviews`| Audit reviews | Global | id | target, reviewer| - | - | - | No | No | Immutable | READY |
| `otp_rate_buckets`| Concurrency | user_id | user,chan,date| - | - | count <= 3 | - | No | No | Expirable | READY |
| `otp_requests` | Auth/Recovery | user_id | id | user_id | - | attempt <= 5 | (user, created)| No | Hashed OTP | Expirable | READY |
| `channel_bindings`| WA/TG Auth | user_id | id | user_id | (channel, ext_id)| - | - | No | No | Revocable | READY |
| `api_keys` | API Auth | org_id | id | org, creator| key_hash | - | - | No | Hashed Key | Revocable | READY |
| `api_idempotency_keys`| Idempotency | org_id | id | org | (org, actor, op, hash)| - | - | No | No | Expirable | READY |
| `canonical_entities`| Global Identity | Global | id | - | identity_hash | - | platform, type | No | No | Immutable | READY |
| `canonical_profiles` | Profile Data | Global | entity_id| entity | entity_id | type=PROFILE | - | No | No | Immutable | READY |
| `canonical_posts` | Post Data | Global | entity_id| entity, author| entity_id | type=POST | - | No | No | Immutable | READY |
| `canonical_videos` | Video Data | Global | entity_id| entity, author| entity_id | type=VIDEO | - | No | No | Immutable | READY |
| `canonical_articles`| Article Data | Global | entity_id| entity | entity_id | type=ARTICLE | - | No | No | Immutable | READY |
| `canonical_comments`| Comment Data | Global | entity_id| entity, parent| entity_id | type=COMMENT | - | No | No | Immutable | READY |
| `canonical_replies`| Reply Data | Global | entity_id| entity, parent| entity_id | type=REPLY | - | No | No | Immutable | READY |
| `runs` | Scraper Job | org_id | id | org, actor | (id, org) | - | (org, status) | No | No | Immutable | READY |
| `run_requests` | Run Inputs | org_id | run_id | run, canonical| run_id | - | - | No | No | Immutable | READY |
| `run_results` | Results | org_id | id | (run,org), entity| (run, entity) | - | (org, entity) | Append-only| No | Immutable | READY |
| `tasks` | Queue Job | run_id(org)| id | (run,org) | (id, run, org) | - | status | No | No | Immutable | READY |
| `task_attempts` | Retry History | task_id(org)| id | (task,run,org) | - | - | - | Yes | No | Immutable | READY |
| `dead_letter_queue_records`| DLQ | task_id(org)| id | task, run, attempt| - | - | - | Yes | No | Immutable | READY |
| `task_leases` | Resource Hold | task_id | id | task | - | - | status, expires| No | No | Expirable | READY |
| `resource_pools`| Acct Pools | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `proxy_pools` | Proxy Pools | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `social_accounts`| Acct Creds | Global | id | pool | - | - | - | No | Encrypted | Soft Delete | READY |
| `social_sessions`| Acct Sessions | Global | id | account | - | - | expires_at | No | Encrypted | Expirable | READY |
| `proxies` | Proxy Creds | Global | id | pool | - | - | - | No | Encrypted | Soft Delete | READY |
| `account_leases`| Acct Hold | task_id | id | account, task | - | - | status, expires| No | No | Expirable | READY |
| `proxy_leases` | Proxy Hold | task_id | id | proxy, task | - | - | status, expires| No | No | Expirable | READY |
| `credit_lots` | Ledger Balance| org_id | id | org | (id, org) | remain >= 0 | - | No | No | Expirable | READY |
| `billing_reservations`| Atomic Hold | org_id | id | (run, org) | (id, org), run_id | set+rel<=res| - | No | No | Immutable | READY |
| `credit_reservation_allocations`| FEFO Bridge | org_id | id | (res,org), (lot,org) | (res, lot) | set+rel<=res| - | No | No | Immutable | READY |
| `credit_ledger` | Ledger History| org_id | id | org, lot, res, run| idempotency_key| - | - | Yes | No | Immutable | READY |
| `packages` | Sub Tiers | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `package_entitlements`| Tier Limits | Global | id | package | - | - | - | No | No | Soft Delete | READY |
| `pricing_versions`| Cost Rules | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `subscriptions` | Active Subs | org_id | id | org, package | - | - | expires_at | No | No | Expirable | READY |
| `subscription_snapshots`| Sub Data | org_id | id | subscription | - | - | - | No | No | Immutable | READY |
| `invoices` | Billing | org_id | id | org | - | - | - | No | No | Immutable | READY |
| `payments` | Transactions | org_id | id | org, invoice | provider_tx | - | - | No | No | Immutable | READY |
| `payment_webhook_events`| Webhooks | Global | id | payment | provider_event_id | - | - | Yes | No | Immutable | READY |
| `credit_allocations`| Payment Lots | org_id | id | payment, lot | - | - | - | No | No | Immutable | READY |
| `refund_approvals`| Maker-Checker | Global | id | maker, checker | - | maker != checker| - | No | No | Immutable | READY |
| `refunds` | Refunds | org_id | id | org, pay, run, apprv| idempotency_key| - | - | No | No | Immutable | READY |
| `internal_costs`| Infra Costs | Global | id | - | idempotency_key| - | - | Yes | No | Immutable | READY |
| `notification_events`| Notif Trigger| org_id | id | org | dedupe_key | - | - | No | No | Immutable | READY |
| `notification_rules`| Notif Config | org_id | id | org | - | - | - | No | No | Soft Delete | READY |
| `notification_templates`| Notif UI | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `logical_notifications`| Notif Fanout | org_id | id | (event, org) | (event, recip, chan)| - | - | No | No | Immutable | READY |
| `notification_deliveries`| Notif Send | org_id | id | logical, binding | - | - | - | No | No | Immutable | READY |
| `notification_delivery_attempts`| Notif Retries| delivery_id | id | delivery | provider_event_id| - | - | Yes | No | Immutable | READY |
| `in_app_notifications`| UI Inbox | user_id | id | user, event | - | - | - | No | No | Soft Delete | READY |
| `wa_pools` | WA Config | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `wa_instances` | WA Config | Global | id | pool | - | - | - | No | No | Soft Delete | READY |
| `provider_configs`| API Config | Global | id | - | - | - | - | No | Encrypted | Soft Delete | READY |
| `exports` | File Gen | org_id | id | org, user | - | - | expires_at | No | No | Expirable | READY |
| `selectors` | Scraper Rule | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `selector_versions`| Scraper Ver | Global | id | selector | - | - | - | No | No | Soft Delete | READY |
| `search_indexing_states`| Index State | Global | id | - | - | - | - | No | No | Soft Delete | READY |
| `system_maintenance`| Outages | Global | id | actor | - | - | - | No | No | Immutable | READY |
| `outgoing_webhooks`| Webhook Conf | org_id | id | org | - | - | - | No | Encrypted | Soft Delete | READY |
| `webhook_deliveries`| Webhook Send | org_id | id | webhook, event | - | - | - | No | No | Immutable | READY |
| `audit_logs` | Audit Trail | org_id | id | - | - | - | actor, org, time| Yes | No | Immutable | READY |
| `security_events`| Security Hist| user_id | id | user | - | - | created_at | Yes | No | Immutable | READY |
| `ai_conversations`| AI State | user_id | id | actor | - | - | - | No | No | Expirable | READY |
| `ai_messages` | AI Hist | user_id | id | conversation | - | - | - | Yes | No | Expirable | READY |
| `ai_tool_audits`| AI Audits | user_id | id | actor | - | - | - | Yes | No | Expirable | READY |
| `ai_usage` | AI Cost | Global | id | - | idempotent_event_id| - | - | Yes | No | Immutable | READY |
| `reconciliation_runs`| Recon Control | Global | id | - | - | - | - | No | No | Immutable | READY |
| `reconciliation_findings`| Recon Items | Global | id | run | - | - | - | No | No | Immutable | READY |

## 2. REAL GAP MATRIX

| Domain | Requirement | Table | Columns/Constraint | Status | Severity | Required Change | Source Document |
|---|---|---|---|---|---|---|---|
| Identity | 9 Human Roles Assigned | `internal_user_assignments` | UNIQUE `(user_id, role_id)`, CHECK `role_id is internal` | READY | NONE | - | SEC_ARCH |
| Auth | OTP Concurrency Limits | `otp_rate_buckets` | CHECK `request_count <= 3` | READY | NONE | - | SEC_ARCH |
| API | Idempotency | `api_idempotency_keys` | UNIQUE `(org, actor, op, hash)` | READY | NONE | - | API_ARCH |
| Scraper | Canonical Integrity | `canonical_entities` + Child tables | Composite FK `(id, entity_type)` + CHECKs | READY | NONE | - | SCRAPER_CONTRACTS |
| Scraper | Run Input Lineage | `run_requests` | `parent_canonical_identity_id` | READY | NONE | - | SCRAPER_CONTRACTS |
| Retry | Task Attempt History | `task_attempts` | IMMUTABLE Append-only, resource lease FKs | READY | NONE | - | ERROR_RETRY_MATRIX |
| Queue | DLQ Durable Evidence | `dead_letter_queue_records`| `task_id`, `failed_at`, `operator_replay_reference` | READY | NONE | - | SYSTEM_ARCH |
| Resource | explicit lease models | `task_leases`, `proxy_leases`, `account_leases` | FKs, `expires_at`, `status` | READY | NONE | - | SCRAPER_CONTRACTS |
| Resource | Encrypted credentials | `social_accounts`, `proxies`, `social_sessions`| `encrypted_credentials`, `key_reference` | READY | NONE | - | SEC_ARCH |
| Billing | FEFO Determinism | `credit_reservation_allocations` | UNIQUE `(res, lot)`, FOR UPDATE SKIP LOCKED | READY | NONE | - | BILLING_ARCH |
| Billing | Atomic Hold | `billing_reservations`| CHECK `settled + released <= reserved` | READY | NONE | - | BILLING_ARCH |
| Billing | Immutable Ledger | `credit_ledger` | UNIQUE `event_idempotency_key`, IMMUTABLE | READY | NONE | - | BILLING_ARCH |
| Payment | Unique Transactions | `payments` | UNIQUE `provider_transaction_id` | READY | NONE | - | BILLING_ARCH |
| Payment | Maker-Checker | `refund_approvals` | CHECK `maker_id != checker_id` | READY | NONE | - | SEC_ARCH |
| Notification| Fanout Limits | `logical_notifications`| UNIQUE `(event_id, recipient_id, channel)` | READY | NONE | - | NOTIF_ARCH |
| Notification| Delivery Attempts | `notification_delivery_attempts`| UNIQUE `provider_event_id` (Callback Dedupe) | READY | NONE | - | NOTIF_ARCH |
| Exports | Format / Retention | `exports` | `format`, `expires_at` (No canonical cascade) | READY | NONE | - | UI_IA |
| AI | Channel Bindings | `channel_bindings` | UNIQUE `(channel, external_identity)` | READY | NONE | - | AI_ARCH |
| Tenant | Enforceable Integrity| `runs`, `run_results`, `tasks`, `task_attempts`, `billing_reservations` | UNIQUE `(id, organization_id)`, Composite FKs | READY | NONE | - | SYSTEM_ARCH |

## 3. DELETE / FK MATRIX
- `canonical_entities` references: **RESTRICT**. (Tenant deletion cannot cascade delete global scraped data).
- `credit_ledger` references: **RESTRICT**. (Immutable financial history).
- `billing_reservations` references: **RESTRICT**.
- `dead_letter_queue_records`: **RESTRICT** to tasks/runs. (DLQ evidence must not disappear).
- `task_attempts`: **RESTRICT**. (Historical attempts cannot be CASCADE deleted).
- `run_results`: **CASCADE** on `(run_id, organization_id)`, **RESTRICT** on `canonical_entity_id`.
- `payment_webhook_events`: **RESTRICT**. (Immutable webhook history).

## 4. TENANT OWNERSHIP MATRIX
Every tenant-visible table explicitly enforces ownership via direct `organization_id` column or composite immutable FK chain. 
It is physically impossible for a child row to belong to a run from Organization A while its own `organization_id` column states Organization B.

## 5. ENUM DRIFT
Enum drift is exactly **0**. The Postgres Enums exactly match the locked architectures without inventing values:
- `ExportStatus`: `QUEUED`, `PROCESSING`, `READY`, `EXPIRED`, `FAILED`
- `RefundStatus`: `PENDING`, `APPROVED`, `REJECTED`
- `ResourceHealthStatus`: `HEALTHY`, `DEGRADED`, `UNHEALTHY`

*(Note: Operational states like COOLDOWN, BANNED, DISABLED are now modeled via separate string columns `operational_state` and `cooldown_until` in resource tables, ensuring `ResourceHealthStatus` drift remains zero).*

## 6. MIGRATION DEPENDENCY GRAPH
1. **Phase A**: `organizations`, `users`, `roles`, `permissions`, `role_permissions`, `organization_memberships`, `internal_user_assignments`, `auth_sessions`, `auth_logs`, `otp_rate_buckets`, `otp_requests`, `channel_bindings`, `api_keys`, `api_idempotency_keys`, `service_actors`, `temporary_access_grants`, `jit_privilege_grants`, `break_glass_activations`, `access_reviews`
2. **Phase B**: `canonical_entities`, `canonical_profiles`, `canonical_posts`, `canonical_videos`, `canonical_articles`, `canonical_comments`, `canonical_replies`
3. **Phase C**: `packages`, `package_entitlements`, `pricing_versions`, `subscriptions`, `subscription_snapshots`, `credit_lots`
4. **Phase D**: `runs`, `run_requests`, `tasks`, `task_attempts`, `task_leases`, `run_results`, `dead_letter_queue_records`
5. **Phase E**: `resource_pools`, `proxy_pools`, `social_accounts`, `social_sessions`, `proxies`, `account_leases`, `proxy_leases`
6. **Phase F**: `billing_reservations`, `credit_reservation_allocations`, `credit_ledger`, `invoices`, `payments`, `payment_webhook_events`, `credit_allocations`, `refund_approvals`, `refunds`, `internal_costs`
7. **Phase G**: `notification_events`, `notification_rules`, `notification_templates`, `logical_notifications`, `notification_deliveries`, `notification_delivery_attempts`, `in_app_notifications`, `wa_pools`, `wa_instances`, `provider_configs`, `outgoing_webhooks`, `webhook_deliveries`
8. **Phase H**: `exports`, `selectors`, `selector_versions`, `search_indexing_states`, `system_maintenance`, `audit_logs`, `security_events`, `ai_conversations`, `ai_messages`, `ai_tool_audits`, `ai_usage`, `reconciliation_runs`, `reconciliation_findings`
