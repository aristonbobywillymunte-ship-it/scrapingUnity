# ERROR AND RETRY MATRIX

This document defines the authoritative taxonomy of scraper failures, runtime errors, and the associated retry semantics managed by the Retry Engine. It governs how the system recovers from errors safely without violating billing contracts, resource isolation, or canonical identity.

---

## 1. ERROR CATEGORIES

The system classifies all failures into the following authoritative categories.

### 1.1. `invalid_input`
- **Definition**: The requested target URL, capability, or parameters are malformed, unsupported, or logically impossible.
- **Examples**: `ERR_INVALID_URL`, `ERR_UNSUPPORTED_PLATFORM`
- **Retryable**: NO
- **Terminal State**: FAILED

### 1.2. `authentication_session`
- **Definition**: The leased social account session is invalid, logged out, or requires re-authentication (checkpoint/verify).
- **Examples**: `ERR_SESSION_INVALID`, `ERR_CHECKPOINT_REQUIRED`
- **Retryable**: YES (Task level)
- **Action**: Rotate Account. Mark original account `CHECKPOINT` or `DEGRADED`.

### 1.3. `account_restricted`
- **Definition**: The leased social account is actively restricted, action-blocked, or banned by the target platform.
- **Examples**: `ERR_ACTION_BLOCKED`, `ERR_ACCOUNT_BANNED`
- **Retryable**: YES (Task level)
- **Action**: Rotate Account. Mark original account `BLOCKED`, `BANNED`, or `COOLDOWN`.

### 1.4. `proxy_network`
- **Definition**: The HTTP request failed due to proxy connection timeout, DNS failure, or proxy-level restriction (403/407).
- **Examples**: `ERR_PROXY_TIMEOUT`, `ERR_PROXY_AUTH_FAILED`
- **Retryable**: YES (Collector-local for safe transients; Task level for hard fails)
- **Action**: Rotate Proxy. Mark original proxy `COOLDOWN` or `DEGRADED`. (One timeout does NOT equal a ban).

### 1.5. `target_rate_limit`
- **Definition**: The target platform returned a rate limit response (e.g., HTTP 429).
- **Examples**: `ERR_RATE_LIMITED`
- **Retryable**: YES (Task level)
- **Action**: Delayed retry with backoff. Rotate account/proxy ONLY if policy indicates the limit is bound to the resource rather than the global target.

### 1.6. `target_unavailable`
- **Definition**: The target platform is down, returning 5xx errors, or experiencing temporary outages.
- **Examples**: `ERR_PLATFORM_503`, `ERR_CONNECTION_REFUSED`
- **Retryable**: YES (Task level)
- **Action**: Delayed retry with exponential backoff. Permanent removed targets (HTTP 404) become terminal.

### 1.7. `selector_parse`
- **Definition**: The collector successfully fetched the page/API but failed to parse the expected data structures.
- **Examples**: `ERR_PARSE_FAILURE`, `ERR_SCHEMA_CHANGED`
- **Retryable**: YES (Only if alternate deterministic fallback exists, e.g., Browser Fallback). Otherwise, requires Operator Review.
- **Action**: No blind infinite retries.

### 1.8. `content_not_found`
- **Definition**: The target exists but contains no data matching the criteria (e.g., an empty profile, deleted post).
- **Examples**: `ERR_EMPTY_RESULT`, `ERR_POST_DELETED`
- **Retryable**: NO (unless distinguishing temporary vs permanent). Permanent is terminal.
- **Terminal State**: COMPLETED (target_unmet=true) if valid empty state; FAILED if deleted before collection.

### 1.9. `resource_exhausted`
- **Definition**: No eligible account or proxy is currently available in the pool to service the task.
- **Examples**: `ERR_NO_ACCOUNTS_AVAILABLE`, `ERR_NO_PROXIES`
- **Retryable**: YES (Task level)
- **Action**: Move to `RETRY_WAIT`. Do not busy-loop. Escalate to Operator Review after threshold.

### 1.10. `worker_timeout`
- **Definition**: The worker exceeded its maximum bounded execution time for a task attempt.
- **Examples**: `ERR_EXECUTION_TIMEOUT`
- **Retryable**: YES (Task level)
- **Action**: Reconcile leases. Idempotent retry.

### 1.11. `worker_crash`
- **Definition**: The worker process died unexpectedly (OOM, panic, node death) without finalizing the task.
- **Examples**: `ERR_ORPHANED_LEASE`
- **Retryable**: YES (Task level, via Reconciliation Cron)
- **Action**: Release orphan leases. Requeue attempt idempotently.

### 1.12. `internal_system`
- **Definition**: Failure communicating with internal dependencies (Database, Redis, AI Provider).
- **Examples**: `ERR_DB_TIMEOUT`, `ERR_CACHE_UNAVAILABLE`
- **Retryable**: YES (Task level)
- **Action**: Exponential backoff. Trigger circuit breaker if widespread.

### 1.13. `billing_quota`
- **Definition**: The tenant lacks sufficient credits or quota to execute the task.
- **Examples**: `ERR_INSUFFICIENT_CREDITS`
- **Retryable**: NO (Not an execution retry condition).
- **Terminal State**: FAILED / CANCELLED. The task may only be newly scheduled/resumed after the billing/quota condition is resolved according to Billing architecture.

### 1.14. `cancelled`
- **Definition**: The user or system cooperatively requested cancellation.
- **Examples**: `ERR_CANCEL_REQUESTED`
- **Retryable**: NO
- **Terminal State**: CANCELLED / PARTIAL (if partial data was saved).

---

## 2. RETRY STRATEGY MATRIX

| Category | Retryable | Collector Retry | Task Retry | Backoff | Account Action | Proxy Action | Browser Fallback | Resource Health Effect | Billing Effect | Operator Review | Terminal Outcome |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `invalid_input` | NO | NO | NO | None | None | None | NO | None | Refund/No-charge | NO | FAILED |
| `authentication_session`| YES | NO | YES | Base | Rotate | Keep | NO | DEGRADED/CHECKPOINT | No-charge | NO | FAILED |
| `account_restricted` | YES | NO | YES | Base | Rotate | Keep | NO | BLOCKED/BANNED | No-charge | NO | FAILED/PARTIAL |
| `proxy_network` | YES | YES (Bounded) | YES | Base | Keep | Rotate | NO | DEGRADED/COOLDOWN | No-charge | NO | FAILED/PARTIAL |
| `target_rate_limit`| YES | NO | YES | Exponential| Keep/Rotate| Keep/Rotate| NO | None | No-charge | NO | FAILED/PARTIAL |
| `target_unavailable`| YES | NO | YES | Exponential| Keep | Keep | NO | None | No-charge | NO | FAILED |
| `selector_parse` | YES* | NO | YES* | Base | Keep | Keep | YES (If config) | None | No-charge | YES (Threshold) | FAILED |
| `content_not_found`| NO | NO | NO | None | Keep | Keep | NO | None | Safe usage log | NO | COMPLETED/FAIL |
| `resource_exhausted`| YES | NO | YES | Delayed | Wait | Wait | NO | None | No-charge | YES (Threshold) | FAILED |
| `worker_timeout` | YES | NO | YES | Base | Release | Release| NO | None | No-charge | YES (Threshold) | FAILED/PARTIAL |
| `worker_crash` | YES | NO | YES | Base | Release | Release| NO | None | No-charge | YES (Threshold) | FAILED/PARTIAL |
| `internal_system` | YES | YES (Bounded) | YES | Exponential| Release | Release| NO | None | No-charge | YES (Threshold) | FAILED |
| `billing_quota` | NO | NO | NO | None | None | None | NO | None | Terminal Block | NO | FAILED |
| `cancelled` | NO | NO | NO | None | Release | Release| NO | None | Bill completed | NO | CANCELLED/PARTIAL|

*(Asterisk `*`: `selector_parse` retries ONLY if a fallback adapter exists; otherwise triggers operator review immediately).*

---

## 3. RETRY ENGINE POLICIES

### 3.1. Exponential Backoff Policy
- Base delay is configuration-driven.
- Delay multiplies for sequential failures of the same category (e.g., `target_unavailable`).
- A maximum delay cap (`max_backoff`) prevents tasks from stalling indefinitely.

### 3.2. Jitter Policy
- All delayed retries apply randomized jitter (defined by configuration `retry_jitter_ratio`) to prevent thundering herd problems against target platforms and internal queues.

### 3.3. Attempt Counting & Retry Budget
- Every execution generates a unique `attempt_id`.
- Tasks have a configured `max_attempts` ceiling.
- Reaching `max_attempts` without successful collection forces the Task into a Terminal Outcome.

### 3.4. RETRY_WAIT Semantics
- A Task in `RETRY_WAIT` is removed from active worker concurrency limits.
- It resides in a delayed queue or sorted set until its backoff timestamp expires, at which point it becomes `QUEUED`.

### 3.5. Cooldown Semantics
- **Account/Proxy Cooldown**: A temporary health state. Resources in `COOLDOWN` are excluded from the eligible pool for a configured duration (`cooldown_duration` based on the resource and failure type).

### 3.6. Circuit Breaker Interaction
- If a capability or specific target experiences an extreme failure rate exceeding the `circuit_breaker_failure_threshold` (evaluated only if attempts exceed `circuit_breaker_minimum_sample_size`), the circuit breaker trips.
- Tasks are paused or rejected. The capability enters `DEGRADED` or `MAINTENANCE` status.

### 3.7. Maintenance Interaction
- Tasks in `QUEUED` or `RETRY_WAIT` will NOT transition to `RUNNING` if the capability is in `MAINTENANCE`. They defer execution.

### 3.8. Cancellation Interaction
- If `cancel_requested` is true, the Retry Engine aborts any pending `RETRY_WAIT` logic and immediately transitions the Task to a terminal state (`CANCELLED` or `PARTIAL`).

### 3.9. Reconciliation After Crash
- The Reconciliation Cron detects orphan leases (Task marked `RUNNING` but lease `expires_at` has passed and worker heartbeat is dead).
- Action:
  1. Release Account/Proxy leases safely.
  2. Increment attempt count.
  3. If under `max_attempts`, transition Task to `QUEUED`. If over, transition to `FAILED`.

### 3.10. Billing and Idempotency Safety
- **CRITICAL**: Duplicate retries must NOT duplicate canonical data, must NOT duplicate `run_result` linkage unsafely, and must NOT duplicate billable usage.
- Billing usage is emitted per successful action with an idempotent `usage_event_id` encompassing `task_id` and `attempt_id`.
- If an attempt fails, previously emitted partial billing (if allowed by policy) is final, but retries only emit billing for *new* work accomplished.
- **Rule**: Failed attempts causing a retry do NOT charge the customer for the failure.

### 3.11. Final Run Status Aggregation
The Run Engine rolls up Task outcomes into the overall Run status:
- All Tasks `COMPLETED` -> Run `COMPLETED`.
- Some Tasks `FAILED` / `PARTIAL`, some `COMPLETED` -> Run `PARTIAL`.
- All Tasks `FAILED` -> Run `FAILED`.
- Any Task `CANCELLED` -> Run `CANCELLED` or `PARTIAL`.

---

## 4. OPEN DECISIONS
- No blocking decisions remain regarding runtime error handling and retry semantics.

