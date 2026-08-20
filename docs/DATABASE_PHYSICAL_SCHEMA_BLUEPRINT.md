# DATABASE PHYSICAL SCHEMA BLUEPRINT

This blueprint defines the precise physical database schema design intended for PostgreSQL, establishing table names, columns, data types, constraints, and relationships. It forms the physical basis for verifying migration readiness.

## ENUMS (PostgreSQL ENUM types)

- `RunStatus`: `QUEUED`, `RUNNING`, `COMPLETED`, `PARTIAL`, `FAILED`, `CANCELLED`
- `TaskStatus`: `QUEUED`, `LEASED`, `RUNNING`, `RETRY_WAIT`, `COMPLETED`, `FAILED`, `CANCELLED`
- `NotificationDeliveryStatus`: `QUEUED`, `SENDING`, `DELIVERED`, `FAILED`
- `ExportStatus`: `QUEUED`, `PROCESSING`, `READY`, `EXPIRED`, `FAILED`
- `RefundStatus`: `PENDING`, `APPROVED`, `REJECTED`
- `SelectorVersionStatus`: `DRAFT`, `TESTING`, `ACTIVE`, `INACTIVE`, `DEPRECATED`
- `ResourceHealthStatus`: `HEALTHY`, `DEGRADED`, `UNHEALTHY`
- `CreditTransactionType`: `PACKAGE_CREDIT`, `PURCHASE`, `RESERVE`, `RELEASE`, `USAGE`, `REFUND`, `BONUS`, `ADJUSTMENT`, `EXPIRED`
- `ErrorCategory`: `invalid_input`, `authentication_session`, `account_restricted`, `proxy_network`, `target_rate_limit`, `target_unavailable`, `selector_parse`, `content_not_found`, `resource_exhausted`, `worker_timeout`, `worker_crash`, `internal_system`, `billing_quota`, `cancelled`
- `CanonicalEntityType`: `PROFILE`, `POST`, `VIDEO`, `ARTICLE`, `COMMENT`, `REPLY`, `PAGE`, `FEED`

---

## 1. IDENTITY, ORGANIZATION & RBAC

**`organizations`**
- PK: `id` (uuid)
- `name` (varchar, not null)
- `status` (varchar, default 'ACTIVE')
- `created_at` (timestamptz), `updated_at` (timestamptz), `deleted_at` (timestamptz)

**`users`**
- PK: `id` (uuid)
- `email` (varchar, not null, UNIQUE)
- `password_hash` (varchar, null)
- `mfa_enabled` (boolean, not null, default false)
- `status` (varchar, default 'ACTIVE')
- `created_at`, `updated_at`, `deleted_at`

**`roles`**
- PK: `id` (varchar) 
  *Customer*: `owner`, `developer`, `analyst`, `viewer`. 
  *Internal*: `internal_owner`, `internal_admin`, `internal_operator`, `internal_finance`, `internal_security`.
- `is_internal_role` (boolean)
- `description` (varchar)

**`permissions`**
- PK: `id` (varchar)

**`role_permissions`**
- PK: `role_id` (varchar, FK to roles), `permission_id` (varchar, FK to permissions)

**`organization_memberships`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK to organizations, RESTRICT)
- `user_id` (uuid, FK to users, RESTRICT)
- `role_id` (varchar, FK to roles, RESTRICT)
- UNIQUE (`organization_id`, `user_id`)
- CHECK: role_id must not be an internal role.

**`internal_user_assignments`**
- PK: `id` (uuid)
- `user_id` (uuid, FK to users, RESTRICT)
- `role_id` (varchar, FK to roles, RESTRICT)
- UNIQUE (`user_id`, `role_id`)
- CHECK: role_id must be an internal role.

**`auth_sessions`**
- PK: `id` (uuid)
- `user_id` (uuid, FK to users, CASCADE)
- `token_hash` (varchar, UNIQUE)
- `device_metadata` (jsonb)
- `ip_address` (inet)
- `expires_at` (timestamptz)
- `created_at`, `revoked_at`

**`auth_logs`**
- PK: `id` (uuid)
- `user_id` (uuid, FK to users, RESTRICT)
- `event_type` (varchar)
- `ip_address` (inet)
- `device_metadata` (jsonb)
- `created_at` (timestamptz)

---

## 2. TEMPORARY & SERVICE ACTORS

**`service_actors`**
- PK: `id` (uuid)
- `name` (varchar)
- `credentials_hash` (varchar)
- `status` (varchar)
- `created_at`, `deleted_at`

**`temporary_access_grants`**
- PK: `id` (uuid)
- `user_id` (uuid, FK to users, RESTRICT)
- `role_id` (varchar, FK to roles, RESTRICT)
- `approver_id` (uuid, FK to users, RESTRICT)
- `reason` (text)
- `starts_at` (timestamptz)
- `expires_at` (timestamptz)
- `revoked_at` (timestamptz)
- `audit_reference` (varchar)

**`jit_privilege_grants`**
- PK: `id` (uuid)
- `user_id` (uuid, FK to users, RESTRICT)
- `requested_by` (uuid, FK to users, RESTRICT)
- `approved_by` (uuid, FK to users, RESTRICT)
- `permission_id` (varchar, FK to permissions, RESTRICT)
- `scope` (varchar)
- `reason` (text)
- `status` (varchar)
- `starts_at` (timestamptz)
- `expires_at` (timestamptz)
- `revoked_at` (timestamptz)
- `audit_reference` (varchar)
- `created_at` (timestamptz)

**`break_glass_activations`**
- PK: `id` (uuid)
- `user_id` (uuid, FK to users, RESTRICT)
- `reason` (text)
- `starts_at`, `expires_at`, `revoked_at`
- `audit_reference` (varchar)

**`access_reviews`**
- PK: `id` (uuid)
- `target_user_id` (uuid, FK to users, RESTRICT)
- `reviewer_id` (uuid, FK to users, RESTRICT)
- `status` (varchar)
- `reviewed_at` (timestamptz)

---

## 3. OTP & CHANNEL BINDINGS

**`otp_rate_buckets`**
- PK: `user_id`, `channel`, `date`
- `request_count` (int, default 1)
- CHECK (`request_count <= 3`)
- `updated_at` (timestamptz)

**`otp_requests`**
- PK: `id` (uuid)
- `user_id` (uuid, FK to users, CASCADE)
- `channel` (varchar) (EMAIL, WHATSAPP)
- `otp_hash` (varchar, not null)
- `purpose` (varchar)
- `attempt_count` (int, default 0)
- `expires_at` (timestamptz, not null)
- `used_at` (timestamptz, null)
- `created_at` (timestamptz)
- CHECK (`attempt_count <= 5`)
- CHECK (`channel IN ('EMAIL', 'WHATSAPP')`)

**`channel_bindings`**
- PK: `id` (uuid)
- `user_id` (uuid, FK users, CASCADE)
- `channel` (varchar) (WHATSAPP, TELEGRAM)
- `external_identity` (varchar, not null)
- `verified_at` (timestamptz)
- `status` (varchar)
- `revoked_at` (timestamptz)
- `safe_metadata` (jsonb)
- UNIQUE (`channel`, `external_identity`)

---

## 4. API KEYS & IDEMPOTENCY

**`api_keys`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `created_by` (uuid, FK users, RESTRICT)
- `key_hash` (varchar, not null, UNIQUE)
- `key_prefix` (varchar, not null)
- `scopes` (jsonb)
- `status` (varchar)
- `last_used_at`, `revoked_at`, `created_at`

**`api_idempotency_keys`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `actor_identity` (varchar, not null)
- `operation_id` (varchar, not null)
- `key_hash` (varchar, not null)
- `request_fingerprint` (varchar, not null)
- `status` (varchar)
- `response_reference` (jsonb)
- `created_at`, `expires_at`
- UNIQUE (`organization_id`, `actor_identity`, `operation_id`, `key_hash`)

---

## 5. CANONICAL IDENTITY

**`canonical_entities`**
- PK: `id` (uuid)
- `platform` (varchar, not null)
- `entity_type` (CanonicalEntityType, not null)
- `stable_source_id` (varchar, null)
- `normalized_url` (varchar, null)
- `identity_hash` (varchar, not null, UNIQUE)
- `created_at`, `updated_at`
- UNIQUE (`id`, `entity_type`)

**`canonical_profiles`**
- PK: `canonical_entity_id` (uuid)
- `entity_type` (CanonicalEntityType)
- FK `(canonical_entity_id, entity_type)` -> `canonical_entities(id, entity_type)` RESTRICT
- CHECK (`entity_type = 'PROFILE'`)
- `username` (varchar)
- `display_name` (varchar)

**`canonical_posts`**
- PK: `canonical_entity_id` (uuid)
- `entity_type` (CanonicalEntityType)
- FK `(canonical_entity_id, entity_type)` -> `canonical_entities(id, entity_type)` RESTRICT
- CHECK (`entity_type = 'POST'`)
- `author_profile_id` (uuid, FK canonical_entities, RESTRICT)
- `text_content` (text)

**`canonical_videos`**
- PK: `canonical_entity_id` (uuid)
- `entity_type` (CanonicalEntityType)
- FK `(canonical_entity_id, entity_type)` -> `canonical_entities(id, entity_type)` RESTRICT
- CHECK (`entity_type = 'VIDEO'`)
- `author_profile_id` (uuid, FK canonical_entities, RESTRICT)

**`canonical_articles`**
- PK: `canonical_entity_id` (uuid)
- `entity_type` (CanonicalEntityType)
- FK `(canonical_entity_id, entity_type)` -> `canonical_entities(id, entity_type)` RESTRICT
- CHECK (`entity_type = 'ARTICLE'`)
- `canonical_url` (varchar)

**`canonical_comments`**
- PK: `canonical_entity_id` (uuid)
- `entity_type` (CanonicalEntityType)
- FK `(canonical_entity_id, entity_type)` -> `canonical_entities(id, entity_type)` RESTRICT
- CHECK (`entity_type = 'COMMENT'`)
- `parent_content_id` (uuid, FK canonical_entities, RESTRICT)

**`canonical_replies`**
- PK: `canonical_entity_id` (uuid)
- `entity_type` (CanonicalEntityType)
- FK `(canonical_entity_id, entity_type)` -> `canonical_entities(id, entity_type)` RESTRICT
- CHECK (`entity_type = 'REPLY'`)
- `root_content_id` (uuid, FK canonical_entities, RESTRICT)
- `parent_comment_id` (uuid, FK canonical_entities, RESTRICT)

---

## 6. RUNS, TASKS & LEASES

**`runs`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `actor_id` (uuid, null)
- `origin` (varchar)
- `capability` (varchar, not null)
- `scraper_contract_version` (varchar)
- `request_id` (varchar)
- `reference_id` (varchar)
- `status` (RunStatus, not null, default 'QUEUED')
- `pricing_snapshot_id` (uuid)
- `counters` (jsonb)
- `error_category` (ErrorCategory, null)
- `safe_error_metadata` (jsonb)
- `started_at`, `completed_at`, `cancel_requested_at`, `created_at`
- UNIQUE (`id`, `organization_id`)

**`run_requests`**
- PK: `run_id` (uuid, FK runs, RESTRICT, UNIQUE)
- `target_type` (varchar)
- `target_url` (varchar)
- `normalized_target_url` (varchar)
- `source_canonical_identity_id` (uuid, FK canonical_entities, RESTRICT)
- `parent_canonical_identity_id` (uuid, FK canonical_entities, RESTRICT)
- `capability` (varchar)
- `limit_value` (int)
- `options` (jsonb)
- `reference_id` (varchar)
- `request_snapshot` (jsonb)
- `scraper_contract_version` (varchar)
- `payload_version` (varchar)

**`run_results`**
- PK: `id` (uuid)
- `run_id` (uuid)
- `organization_id` (uuid)
- FK `(run_id, organization_id)` -> `runs(id, organization_id)` RESTRICT
- `canonical_entity_id` (uuid, FK canonical_entities, RESTRICT)
- `source_task_id` (uuid) -- Defined correctly in next table
- `billable_status` (varchar)
- `created_at` (timestamptz)
- UNIQUE (`run_id`, `canonical_entity_id`)

**`tasks`**
- PK: `id` (uuid)
- `run_id` (uuid)
- `organization_id` (uuid)
- FK `(run_id, organization_id)` -> `runs(id, organization_id)` RESTRICT
- `capability` (varchar)
- `payload_version` (varchar)
- `scraper_contract_version` (varchar)
- `status` (TaskStatus)
- `attempt_count` (int, default 0)
- `max_attempts_reference` (varchar)
- `next_retry_at` (timestamptz)
- `active_lease_id` (uuid)
- `lease_expires_at` (timestamptz)
- `heartbeat_at` (timestamptz)
- `worker_identity` (varchar)
- `queued_at`, `started_at`, `completed_at`
- `error_category` (ErrorCategory)
- `error_code` (varchar)
- `safe_error_metadata` (jsonb)
- UNIQUE (`id`, `run_id`, `organization_id`)

*(Foreign Key setup on `run_results`: FK `(source_task_id, run_id, organization_id)` -> `tasks(id, run_id, organization_id)` RESTRICT).*

**`task_attempts`**
- PK: `id` (uuid)
- `task_id` (uuid)
- `run_id` (uuid)
- `organization_id` (uuid)
- FK `(task_id, run_id, organization_id)` -> `tasks(id, run_id, organization_id)` RESTRICT
- `attempt_number` (int)
- `worker_identity` (varchar)
- `account_lease_id` (uuid, null)
- `proxy_lease_id` (uuid, null)
- `outcome` (varchar)
- `error_category` (ErrorCategory)
- `error_code` (varchar)
- `safe_diagnostics` (jsonb)
- `started_at`, `completed_at`

**`dead_letter_queue_records`**
- PK: `id` (uuid)
- `task_id` (uuid, FK tasks, RESTRICT)
- `run_id` (uuid, FK runs, RESTRICT)
- `attempt_id` (uuid, FK task_attempts, RESTRICT)
- `error_category` (ErrorCategory)
- `error_code` (varchar)
- `safe_diagnostics` (jsonb)
- `retry_exhausted` (boolean)
- `failed_at` (timestamptz)
- `operator_replay_reference` (varchar)
- `reconciled_at` (timestamptz)
- `resolution` (varchar)

---

## 7. RESOURCE LEASES & POOLS

**`task_leases`**
- PK: `id` (uuid)
- `task_id` (uuid, FK tasks, RESTRICT)
- `worker_identity` (varchar)
- `acquired_at`, `expires_at`, `heartbeat_at`, `released_at`
- `status` (varchar), `release_reason` (varchar)

**`resource_pools`**
- PK: `id` (uuid)
- `name` (varchar), `status` (varchar)

**`proxy_pools`**
- PK: `id` (uuid)
- `name` (varchar), `status` (varchar)

**`social_accounts`**
- PK: `id` (uuid)
- `platform` (varchar)
- `pool_id` (uuid, FK resource_pools, RESTRICT)
- `health_status` (ResourceHealthStatus)
- `operational_state` (varchar) (e.g. BANNED, COOLDOWN, DISABLED, AVAILABLE)
- `encrypted_credentials` (varchar)
- `key_reference` (varchar)
- `encryption_version` (varchar)
- `cooldown_until` (timestamptz)

**`social_sessions`**
- PK: `id` (uuid)
- `account_id` (uuid, FK social_accounts, RESTRICT)
- `encrypted_session` (varchar)
- `key_reference` (varchar)
- `encryption_version` (varchar)
- `expires_at` (timestamptz)

**`proxies`**
- PK: `id` (uuid)
- `pool_id` (uuid, FK proxy_pools, RESTRICT)
- `host`, `port` (varchar)
- `encrypted_credentials`, `key_reference`, `encryption_version`
- `health_status` (ResourceHealthStatus)
- `operational_state` (varchar) (e.g. COOLDOWN, DISABLED, AVAILABLE)
- `cooldown_until` (timestamptz)

**`account_leases`**
- PK: `id` (uuid)
- `account_id` (uuid, FK social_accounts, RESTRICT)
- `task_id` (uuid, FK tasks, RESTRICT)
- `worker_identity` (varchar)
- `acquired_at`, `expires_at`, `heartbeat_at`, `released_at`, `status`, `release_reason`

**`proxy_leases`**
- PK: `id` (uuid)
- `proxy_id` (uuid, FK proxies, RESTRICT)
- `task_id` (uuid, FK tasks, RESTRICT)
- `acquired_at`, `expires_at`, `heartbeat_at`, `released_at`, `status`, `release_reason`

---

## 8. BILLING & CREDITS

**`credit_lots`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `source` (varchar) (SUBSCRIPTION, TOP_UP, BONUS, ADJUSTMENT, REFUND)
- `original_quantity` (bigint)
- `remaining_quantity` (bigint)
- `effective_monetary_value_cents` (bigint)
- `created_at`, `expires_at`
- CHECK (`remaining_quantity >= 0`)
- UNIQUE (`id`, `organization_id`)

**`billing_reservations`**
- PK: `id` (uuid)
- `run_id` (uuid)
- `organization_id` (uuid)
- FK `(run_id, organization_id)` -> `runs(id, organization_id)` RESTRICT
- `estimated` (bigint)
- `reserved` (bigint)
- `settled` (bigint)
- `released` (bigint)
- `status` (varchar)
- `pricing_version` (uuid, FK pricing_versions)
- `created_at`, `updated_at`
- CHECK (`reserved >= 0 AND settled >= 0 AND released >= 0`)
- CHECK (`settled + released <= reserved`)
- UNIQUE (`id`, `organization_id`)
- UNIQUE (`run_id`)

**`credit_reservation_allocations`**
- PK: `id` (uuid)
- `reservation_id` (uuid)
- `organization_id` (uuid)
- FK `(reservation_id, organization_id)` -> `billing_reservations(id, organization_id)` RESTRICT
- `credit_lot_id` (uuid)
- FK `(credit_lot_id, organization_id)` -> `credit_lots(id, organization_id)` RESTRICT
- `reserved_quantity` (bigint)
- `settled_quantity` (bigint, default 0)
- `released_quantity` (bigint, default 0)
- `economic_value_snapshot` (bigint)
- `allocation_order` (int)
- `created_at`, `updated_at`
- CHECK (`reserved_quantity >= 0 AND settled_quantity >= 0 AND released_quantity >= 0`)
- CHECK (`settled_quantity + released_quantity <= reserved_quantity`)
- UNIQUE (`reservation_id`, `credit_lot_id`)

**`credit_ledger`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `event_idempotency_key` (varchar, UNIQUE)
- `transaction_type` (CreditTransactionType)
- `credit_lot_id` (uuid)
- FK `(credit_lot_id, organization_id)` -> `credit_lots(id, organization_id)` RESTRICT
- `reservation_id` (uuid, null)
- `reservation_allocation_id` (uuid, FK credit_reservation_allocations, RESTRICT)
- `run_id` (uuid, null)
- `quantity` (bigint)
- `economic_value_reference` (bigint)
- `actor_id` (uuid, null)
- `reason` (varchar)
- `created_at` (timestamptz)

---

## 9. PRICING, PACKAGES & PAYMENTS

**`packages`**
- PK: `id` (uuid)
- `name` (varchar), `is_custom` (boolean)

**`package_entitlements`**
- PK: `id` (uuid)
- `package_id` (uuid, FK packages, RESTRICT), `capability` (varchar), `limits` (jsonb)

**`pricing_versions`**
- PK: `id` (uuid)
- `capability` (varchar), `credits_per_result` (bigint), `valid_from`, `valid_until`

**`subscriptions`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `package_id` (uuid, FK packages, RESTRICT)
- `status` (varchar), `started_at`, `expires_at`

**`subscription_snapshots`**
- PK: `id` (uuid)
- `subscription_id` (uuid, FK subscriptions, RESTRICT)
- `snapshot_data` (jsonb)

**`invoices`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `total_cents` (bigint), `status`, `created_at`

**`payments`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `invoice_id` (uuid, FK invoices, RESTRICT)
- `provider_transaction_id` (varchar, UNIQUE)
- `currency` (varchar), `amount_cents` (bigint)
- `status`, `created_at`, `updated_at`

**`payment_webhook_events`**
- PK: `id` (uuid)
- `provider` (varchar)
- `provider_event_id` (varchar, UNIQUE)
- `payment_id` (uuid, FK payments, RESTRICT)
- `provider_transaction_reference` (varchar)
- `event_type` (varchar)
- `received_at` (timestamptz)
- `processed_at` (timestamptz)
- `processing_status` (varchar)
- `safe_payload_metadata` (jsonb)
- `safe_error` (jsonb)
- `created_at` (timestamptz)

**`credit_allocations`**
- PK: `id` (uuid)
- `payment_id` (uuid, FK payments, RESTRICT)
- `credit_lot_id` (uuid, FK credit_lots, RESTRICT)

**`refund_approvals`**
- PK: `id` (uuid)
- `maker_id` (uuid, FK users, RESTRICT)
- `checker_id` (uuid, FK users, RESTRICT)
- CHECK (`maker_id != checker_id`)
- `status` (RefundStatus)

**`refunds`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `payment_id` (uuid, FK payments, RESTRICT)
- `run_id` (uuid, FK runs, RESTRICT)
- `approval_id` (uuid, FK refund_approvals, RESTRICT)
- `amount_cents` (bigint, null)
- `credit_quantity` (bigint, null)
- `status` (RefundStatus)
- `reason` (varchar)
- `idempotency_key` (varchar, UNIQUE)

**`internal_costs`**
- PK: `id` (uuid)
- `event_idempotency_key` (varchar, UNIQUE)
- `category` (varchar)
- `amount_cents` (bigint)
- `created_at` (timestamptz)

---

## 10. NOTIFICATIONS

**`notification_events`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `event_type` (varchar)
- `event_version` (varchar)
- `dedupe_key` (varchar, UNIQUE)
- `safe_payload` (jsonb)
- `occurred_at` (timestamptz)
- UNIQUE (`id`, `organization_id`)

**`notification_rules`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `event_type` (varchar), `channel` (varchar)

**`notification_templates`**
- PK: `id` (uuid)
- `name` (varchar), `locale` (varchar), `version` (varchar)

**`logical_notifications`**
- PK: `id` (uuid)
- `event_id` (uuid)
- `organization_id` (uuid)
- FK `(event_id, organization_id)` -> `notification_events(id, organization_id)` RESTRICT
- `recipient_id` (uuid, FK users, RESTRICT)
- `channel` (varchar)
- `template_id` (uuid, FK notification_templates, RESTRICT)
- `created_at` (timestamptz)
- UNIQUE (`event_id`, `recipient_id`, `channel`) 

**`notification_deliveries`**
- PK: `id` (uuid)
- `logical_notification_id` (uuid, FK logical_notifications, RESTRICT)
- `recipient_binding_id` (uuid, FK channel_bindings, RESTRICT)
- `status` (NotificationDeliveryStatus)
- `created_at` (timestamptz)

**`notification_delivery_attempts`**
- PK: `id` (uuid)
- `delivery_id` (uuid, FK notification_deliveries, RESTRICT)
- `attempt_number` (int)
- `provider_instance_reference` (varchar)
- `provider_event_id` (varchar, UNIQUE) 
- `runtime_phase` (varchar)
- `safe_error` (jsonb)
- `latency_ms` (int)
- `started_at`, `completed_at`

**`in_app_notifications`**
- PK: `id` (uuid)
- `user_id` (uuid, FK users, RESTRICT)
- `event_id` (uuid, FK notification_events, RESTRICT)
- `read_at` (timestamptz)

**`wa_pools`**
- PK: `id` (uuid)
- `name` (varchar), `status` (varchar)

**`wa_instances`**
- PK: `id` (uuid)
- `pool_id` (uuid, FK wa_pools, RESTRICT)
- `name` (varchar), `status` (varchar)

**`provider_configs`**
- PK: `id` (uuid)
- `provider_name` (varchar)
- `encrypted_credentials` (varchar)
- `key_reference` (varchar)
- `encryption_version` (varchar)

---

## 11. EXPORTS & FILE SECURITY

**`exports`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT)
- `requested_by` (uuid, FK users, RESTRICT)
- `format` (varchar) (CSV, XLSX, JSON, PDF)
- `status` (ExportStatus)
- `request_snapshot` (jsonb)
- `retention_policy_snapshot` (jsonb)
- `storage_reference` (varchar)
- `download_metadata` (jsonb)
- `ready_at`, `expires_at`, `created_at`

---

## 12. SELECTORS, SEARCH, MAINTENANCE, WEBHOOKS

**`selectors`**
- PK: `id` (uuid)
- `platform`, `scraper`, `source`, `page_type`

**`selector_versions`**
- PK: `id` (uuid)
- `selector_id` (uuid, FK selectors, RESTRICT)
- `version` (varchar)
- `status` (SelectorVersionStatus)
- `config_payload` (jsonb)
- `test_metadata` (jsonb)
- `activated_at` (timestamptz)

**`search_indexing_states`**
- PK: `id` (uuid)
- `target_type` (varchar), `last_indexed_version` (varchar)

**`system_maintenance`**
- PK: `id` (uuid)
- `scope` (varchar), `reason` (varchar), `actor_id` (uuid, FK users, RESTRICT), `starts_at`, `ends_at`

**`outgoing_webhooks`**
- PK: `id` (uuid)
- `organization_id` (uuid, FK organizations, RESTRICT), `events` (jsonb), `encrypted_secret` (varchar), `key_reference` (varchar), `encryption_version` (varchar)

**`webhook_deliveries`**
- PK: `id` (uuid)
- `webhook_id` (uuid, FK outgoing_webhooks, RESTRICT), `event_id` (uuid, FK notification_events, RESTRICT), `status` (varchar), `timestamps`

---

## 13. AUDIT & AI ASSISTANT

**`audit_logs`**
- PK: `id` (uuid)
- `actor_id` (uuid), `actor_type` (varchar)
- `organization_id` (uuid)
- `action` (varchar), `target` (varchar), `request_id` (varchar)
- `safe_metadata` (jsonb)
- `created_at` (timestamptz)
- IMMUTABLE.

**`security_events`**
- PK: `id` (uuid)
- `user_id` (uuid), `event_type` (varchar), `created_at`

**`ai_conversations`**
- PK: `id` (uuid)
- `actor_id` (uuid, FK users, RESTRICT)
- `channel` (varchar)
- `safe_metadata` (jsonb)

**`ai_messages`**
- PK: `id` (uuid)
- `conversation_id` (uuid, FK ai_conversations, RESTRICT)
- `role` (varchar), `content` (text)

**`ai_tool_audits`**
- PK: `id` (uuid)
- `tool_name` (varchar), `actor_id` (uuid, FK users, RESTRICT), `request_id` (varchar), `latency_ms` (int), `success` (boolean)

**`ai_usage`**
- PK: `id` (uuid)
- `provider` (varchar), `model` (varchar), `tokens_in`, `tokens_out`, `internal_cost_cents`, `idempotent_event_id` (varchar, UNIQUE)

---

## 14. RECONCILIATION

**`reconciliation_runs`**
- PK: `id` (uuid)
- `started_at` (timestamptz)
- `completed_at` (timestamptz)
- `status` (varchar)

**`reconciliation_findings`**
- PK: `id` (uuid)
- `run_id` (uuid, FK reconciliation_runs, RESTRICT)
- `finding_type` (varchar)
- `object_reference` (varchar)
- `detected_at` (timestamptz)
- `status` (varchar)
- `safe_details` (jsonb)
- `resolved_at` (timestamptz)
- `resolution` (varchar)
- `actor_reference` (varchar)

