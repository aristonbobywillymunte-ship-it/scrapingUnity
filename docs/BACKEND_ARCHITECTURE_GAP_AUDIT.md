# BACKEND ARCHITECTURE GAP AUDIT

## Overview
This document audits the draft `DATABASE_ARCHITECTURE.md` and `API_ARCHITECTURE.md` against the locked `PRD_FINAL_v1.4.md`, `SYSTEM_ARCHITECTURE.md`, and `UI_INFORMATION_ARCHITECTURE.md` to identify missing domains, incorrect boundaries, and missing lifecycle requirements before any coding begins.

---

## 1. CANONICAL DATA BOUNDARY

### Result Ownership & Deduplication
- **Domain**: Canonical Scraped Data
- **Requirement**: The system must decouple canonical scraped data (deduplicated global repository) from tenant-specific run outputs to optimize storage and allow cross-tenant data reuse (e.g. shared public profiles).
- **Current State**: `results` table has `organization_id` directly hardcoded.
- **Gap**: Canonical data is improperly coupled to tenant ownership. 
- **Severity**: CRITICAL
- **Recommended Architecture**: Split into `canonical_results` (hash-based global data) and `run_results` (M2M or direct mapping of `run_id` to `canonical_result_id`). Tenant queries must route through `runs -> run_results -> canonical_results`.
- **Source Document**: SYSTEM_ARCHITECTURE.md
- **Blocking Before Coding**: YES

---

## 2. DATABASE DOMAIN COVERAGE GAPS

### Security & RBAC
- **Domain**: Role-Based Access Control
- **Requirement**: Granular RBAC supporting custom customer roles, internal roles, temporary access, privilege elevation, break-glass, and permission scopes.
- **Current State**: Uses simple `internal_role` and `role` string ENUMs.
- **Gap**: Missing dynamic `roles`, `permissions`, `role_permissions`, `user_role_assignments`, `temporary_access_grants`, `security_events` tables.
- **Severity**: HIGH
- **Recommended Architecture**: Full normalized RBAC schema separating Customer RBAC from Internal Platform RBAC.
- **Blocking Before Coding**: YES

### Billing & Financial Lifecycle
- **Domain**: Billing
- **Requirement**: Atomic credit reservations (lock funds before scrape), actual usage tracking, and asynchronous settlement/refunds. Tracking internal costs vs revenue (profit margin).
- **Current State**: A single append-only `credit_ledgers` table.
- **Gap**: Missing `credit_reservations`, `credit_settlements`, `invoices`, `payments`, `refund_requests`, `internal_costs` tables.
- **Severity**: CRITICAL
- **Recommended Architecture**: Implement Reservation-Usage-Settlement lifecycle tables to prevent double-charging and race conditions.
- **Blocking Before Coding**: YES

### Notification Architecture
- **Domain**: Notifications
- **Requirement**: Full support for WhatsApp Evolution API (pools, multiple instances), Telegram, Email, Notification Rules, Templates, and Delivery Logs.
- **Current State**: Completely missing.
- **Gap**: No tables for notification providers, rules, templates, or delivery tracking.
- **Severity**: HIGH
- **Recommended Architecture**: Add `providers`, `whatsapp_instances`, `notification_templates`, `notification_rules`, `notification_deliveries` (partitioned).
- **Blocking Before Coding**: YES

### AI Assistant & Persistence
- **Domain**: AI
- **Requirement**: Persistence for AI chat history, tool calls, audit, and usage/cost tracking.
- **Current State**: Completely missing.
- **Gap**: No tables for `ai_conversations`, `ai_messages`, `ai_tool_calls`, `ai_usage`.
- **Severity**: MEDIUM
- **Recommended Architecture**: Add AI domain tables for chat context and audit billing.
- **Blocking Before Coding**: YES

### Selector Versioning
- **Domain**: Scraper Engine
- **Requirement**: Granular selector versions (Draft, Testing, Preview, Active), activation history, and rollback capabilities.
- **Current State**: `selector_templates` only has a `version` string.
- **Gap**: Missing `selector_versions`, `selector_tests`, `selector_activation_history`.
- **Severity**: HIGH
- **Recommended Architecture**: Implement proper versioning schema with separate active pointer and test results payload.
- **Blocking Before Coding**: YES

### Resource Lease Model
- **Domain**: Infrastructure
- **Requirement**: Durable state/audit for Social Account and Proxy leases to prevent collisions, tracking cooldowns and health.
- **Current State**: Proxies and Accounts exist, but lack leasing state.
- **Gap**: Missing `proxy_leases`, `account_leases` tables.
- **Severity**: HIGH
- **Recommended Architecture**: Even if Redis handles rapid locking, Postgres must track `leased_at`, `expires_at`, `released_at`, `worker_id` for durable reconciliation and cooldown tracking.
- **Blocking Before Coding**: YES

### Operational Errors & Incidents
- **Domain**: Operations
- **Requirement**: Error fingerprinting, incident tracking, maintenance scheduling.
- **Current State**: Basic `incidents` table.
- **Gap**: Missing `error_fingerprints`, `incident_events`, `maintenance_windows`.
- **Severity**: MEDIUM
- **Recommended Architecture**: Expand operational schema to support automated error grouping.
- **Blocking Before Coding**: YES

---

## 3. RUN / TASK LIFECYCLE AUDIT

- **Domain**: Execution Engine
- **Requirement**: Precise state machine: `queued`, `running`, `completed`, `partial`, `failed`, `cancelled`.
- **Current State**: Draft uses `Queued, Leased, Running, Completed, Retry_Wait, Failed, Cancelled`.
- **Gap**: Inconsistent terminology (e.g. `partial` is missing). Missing `task_attempts` table to log discrete retries. Missing robust `error_fingerprint_id` on failures.
- **Severity**: HIGH
- **Recommended Architecture**: Standardize enums. Add `task_attempts` for detailed retry tracking.
- **Blocking Before Coding**: YES

---

## 4. API COVERAGE GAPS

- **Domain**: API Architecture
- **Requirement**: Exhaustive coverage matching all 168 canonical screens from UI_INFORMATION_ARCHITECTURE.
- **Current State**: Only a handful of representative routes defined.
- **Gap**: Missing routes for MFA, Sessions, Devices, Recovery, AI Chat, Notifications, Detailed Billing, Selectors, Account Pools, Security, Audit Logs, and System Settings.
- **Severity**: CRITICAL
- **Recommended Architecture**: API_ARCHITECTURE.md must exhaustively list resources for every domain mentioned in the UI IA.
- **Blocking Before Coding**: YES

---

## 5. ENUM / STATE MACHINE STANDARDIZATION

- **Current State**: Terminology drifts between UI ("Partial"), DB ("Processing", "Running"), and PRD.
- **Gap**: Lack of centralized glossary for status transitions.
- **Recommended Architecture**: 
  - Execution Status: `QUEUED`, `RUNNING`, `COMPLETED`, `PARTIAL`, `FAILED`, `CANCELLED`.
  - Delete "Pending" and "Processing". Use "QUEUED" and "RUNNING".
  - Capitalization: `camelCase` for JSON, `UPPER_SNAKE` for enums.

---

## 6. DELETE / RETENTION AUDIT

- **Current State**: `deleted_at` mentioned globally. Partitioning mentioned for `results`.
- **Gap**: Hard vs Soft delete rules not fully mapped per domain. No definition for `export_metadata` retention vs `export_file` physical object deletion (which must be idempotent/retryable).
- **Severity**: HIGH
- **Recommended Architecture**: Explicit retention matrices for Canonical Data (hard drop partitions), Run Results (soft delete cascades), Audit (immutable partitions), Exports (async blob deletion + meta soft delete).

---

## 7. SECURITY AUDIT

- **Current State**: Basic mention of encrypted strings.
- **Gap**: Fails to distinguish between non-reversible hashes (Passwords, API Keys) and two-way encrypted credentials (SMTP passwords, Proxies, Social Sessions).
- **Severity**: CRITICAL
- **Recommended Architecture**: Define Envelope Encryption strategy for `social_accounts.credentials`, `proxy.auth_pass`, `whatsapp_instances.token`. Define bcrypt/Argon2 for passwords, SHA-256 for API Key verification.


---

## 8. FINAL SUMMARY

**Database Domains Expected**: 20 (IAM, Billing, Scrapers, Execution, Data, Resources, Operations, AI, Notifications, Security, etc.)
**Database Domains Covered**: 6
**Database Domains Partial**: 4
**Database Domains Missing**: 10 (AI, Notifications, Detailed RBAC, Detailed Billing, Selectors, Leases, Error Fingerprints, Security Logs, Temporary Access, Brand/Settings)

**API Domains Expected**: 25+
**API Domains Covered**: 5
**API Domains Partial**: 3
**API Domains Missing**: 17+ (MFA, Notifications, Selectors, Proxy Pools, Social Pools, AI, Audit, Security, Refund, Margins, etc.)

**Critical Gaps**: 4 (Canonical Data Boundary, Detailed Billing/Reservations, Security Payload Encryption, Exhaustive API Routes)
**High Gaps**: 5 (Notifications, Selectors, RBAC, Leases, Execution State Machine)
**Medium Gaps**: 2 (AI, Operational Incidents)
**Low Gaps**: 1 (Enum Casing consistency)

**Canonical data boundary correct**: NO
**Billing lifecycle complete**: NO
**RBAC complete**: NO
**Resource leases complete**: NO
**Notification architecture complete**: NO
**AI persistence complete**: NO
**Security persistence complete**: NO

**DATABASE_ARCHITECTURE Status**:
DRAFT / READY FOR HARDENING

**API_ARCHITECTURE Status**:
DRAFT / READY FOR HARDENING

**Ready for migrations**: NO
**Ready for coding**: NO

