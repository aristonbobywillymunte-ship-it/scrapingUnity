# SCRAPER CONTRACTS

This document defines the authoritative runtime contract between the Run Engine, Task Queue, Dedicated Worker, Resource Manager (Social Accounts/Proxies), Platform Collector, Canonical Result Pipeline, Billing, and the Error/Retry Engine.

It strictly adheres to the locked PRD and Architecture boundaries.

---

## 1. COMMON SCRAPER CONTRACT

Every capability follows a standardized, conceptual runtime lifecycle:

1. **Input Validation**: Check target format and constraint limits.
2. **Target Normalization**: Resolve user input to canonical platform identifiers or fallback deterministic identities.
3. **Resource Acquisition**: Request Account and/or Proxy leases from the Resource Manager.
4. **Execution / Collection**: Interact with the platform safely.
5. **Raw Normalization**: Convert raw payload to the standard canonical result envelope.
6. **Validation**: Validate the normalized item against required fields.
7. **Deduplication**: Compare canonical identity to existing records.
8. **Persistence**: Save canonical result (if new/updated).
9. **Result Linking**: Attach the canonical result to the specific Tenant's `run_result` lineage.
10. **Usage Emission**: Dispatch structured progress, billing, and internal cost signals.
11. **Resource Release**: Safely unlease Accounts and Proxies.
12. **Task Completion**: Update Task state to terminal.

---

## 2. NON-OFFICIAL COLLECTION & PLATFORM ADAPTERS

### 2.1 Facebook Production Transport Implementation (E1 Data Plane)
- **Transport Strategy**: Self-hosted, HTTP-First architecture with SSRF protection via `FacebookTransportService`.
- **SSRF Whitelist**: `facebook.com`, `www.facebook.com`, `m.facebook.com`, `mbasic.facebook.com`, `touch.facebook.com`. All local, private, or arbitrary external destinations are rejected.
- **Classification Engine**:
  - `SUCCESS`: HTTP 2xx without security checkpoints.
  - `CHALLENGE`: Security checkpoint, CAPTCHA challenge, or bot verification requested.
  - `LOGIN_REQUIRED`: Authentication gate intercepted.
  - `BLOCKED`: HTTP 401/403 access denial.
  - `RATE_LIMITED`: HTTP 429 or temporary rate limit block.
  - `NOT_FOUND`: HTTP 404 or unavailable content.
- **Extraction & Normalization**: `FacebookParserService` parses real OpenGraph meta tags and structured DOM article blocks. Zero synthetic fields are generated. Blocked or challenge pages are never persisted as canonical articles.

---

## 3. RUN TO TASK CONTRACT & QUEUE ISOLATION

### Task Payload Versioning
Every task payload must include `scraper_contract_version` (and optionally `payload_version`).
Workers must reject or safely defer incompatible payload versions. Atomic worker deployments are NOT required.

**Task Payload Contract:**
```json
{
  "task_id": "tsk_12345",
  "run_id": "run_98765",
  "organization_id": "org_555",
  "scraper_contract_version": "v1.0",
  "payload_version": "v1.0",
  "capability": "facebook_posts",
  "target": "https://facebook.com/example",
  "limit": 100,
  "options": {
    "date_from": "2023-01-01"
  },
  "attempt": 1,
  "priority": "normal",
  "created_at": "2023-10-01T10:00:00Z"
}
```
*Note: The Task payload NEVER contains plaintext social account credentials or proxy passwords.*

### Redis Queue Contract
Redis is for runtime coordination only, NOT the durable source of truth.

**Logical Queue Naming Strategy:**
- `queue:facebook_posts`
- `queue:facebook_comments`
- `queue:instagram_posts`
- `queue:instagram_reels`
- `queue:instagram_comments`
- `queue:tiktok_videos`
- `queue:tiktok_comments`
- `queue:youtube_videos`
- `queue:youtube_comments`
- `queue:x_posts`
- `queue:x_replies`
- `queue:news`
- `queue:web`

**Ancillary Logical Queues:**
- `queue:exports`
- `queue:webhooks`
- `queue:notifications_whatsapp`
- `queue:notifications_telegram`
- `queue:notifications_email`
- `queue:search_index`
- `queue:reconciliation`

Low-volume logical queues MAY share physical worker hosts, but queue identity and concurrency remain logically isolated. Failure of one platform worker group must not crash another.

---

## 4. LEASES AND EXHAUSTION

### Task Lease vs Resource Lease
- **Task Execution Lease**: Governs the time a worker owns a Task execution attempt.
- **Account Lease**: Governs the assignment of an Account to a specific task/worker.
- **Proxy Lease**: Governs the assignment of a Proxy to a specific task/worker.
*A Task lease expiring must trigger reconciliation of its associated resource leases.*

### Account Lease Contract
```json
{
  "account_id": "acc_123",
  "pool_id": "pool_456",
  "task_id": "tsk_789",
  "worker_id": "wrk_999",
  "lease_id": "lse_abc",
  "leased_at": "2023-10-01T10:00:00Z",
  "expires_at": "2023-10-01T10:30:00Z",
  "last_renewed_at": "2023-10-01T10:00:00Z",
  "released_at": null,
  "release_reason": null
}
```
- Configurable lease TTL, heartbeat renewal.
- Concurrency slot enforcement (must not lease above configured limits).
- Sticky/affinity support during pagination.
- Cooldown, safe release, expired lease/worker crash recovery.
- Credentials NEVER in payload.

### Proxy Lease Contract
```json
{
  "proxy_id": "prx_321",
  "pool_id": "pool_654",
  "task_id": "tsk_789",
  "worker_id": "wrk_999",
  "lease_id": "lse_xyz",
  "leased_at": "2023-10-01T10:00:00Z",
  "expires_at": "2023-10-01T10:30:00Z",
  "last_renewed_at": "2023-10-01T10:00:00Z",
  "released_at": null,
  "release_reason": null
}
```
- Configurable TTL, concurrency, region requirements, health checking, cooldown, sticky/affinity, rotation, safe release.
- Proxy rotation must be driven by classified errors (not every generic request failure).

### Resource Exhaustion
If no eligible account or proxy exists, the Task must NOT busy-loop.
The worker returns controlled resource state: `RESOURCE_EXHAUSTED`.
The Retry Engine may choose: `retry_wait`, `delayed_queue_retry`, `operator_review`, or `terminal_fail` after policy threshold. Polling Redis in a tight loop is prohibited.

---

## 5. NORMALIZATION & CANONICAL IDENTITY

### Canonical Identity Priority
A. **Stable source/platform ID available**
`canonical identity = platform + capability + stable_source_identifier`

B. **Stable ID unavailable** (Deterministic Fallback)
`canonical identity = platform + capability + normalized_canonical/source_url`
OR
`canonical identity = platform + capability + normalized_target_identity + deterministic_content_fingerprint`

**Fallback MUST**:
- Be deterministic and stable across retries.
- Not include `organization_id`.
- Avoid volatile metrics (likes, views), `collected_at`, and mutable engagement fields.

`dedupe_hash` is documented separately from `source_identifier`.

### News / Web Identity
News discovery distinguishes feed/listing URLs vs article-detail URLs. Only valid article-detail content becomes canonical. AI must NOT be required for extraction.
Identity preference:
1. Canonical URL
2. Normalized Detail URL
3. Source-provided article ID
4. Deterministic URL/content fingerprint fallback.

**URL Normalization**: Conceptually handles scheme/host normalization, fragment removal, known tracking parameters removal, and extracting canonical links where trustworthy. Query parameters that materially identify different content are preserved.

**Article Validation Signals**: Title presence, body threshold, detail URL evidence, canonical URL, published date.

### Comments / Replies Lineage
Harden lineage fields:
- `parent_canonical_identifier`
- `parent_source_identifier`
- `parent_source_url`
- `thread_identifier`
- `reply_to_identifier` (where available)
- `depth` (where relevant)
*A child result may link to a parent canonical item when authorized, but tenant reads still flow through tenant-owned run_result lineage.*

---

## 6. EXECUTION BOUNDARIES & SEMANTICS

### Partial vs Target Unmet
- **COMPLETED (target_unmet=true)**: Source exhausts normally before requested_limit, no system/runtime fault prevented normal exhaustion.
- **PARTIAL**: Valid results were saved, but execution could not finish normally because of a failure or unrecoverable operational constraint (e.g. account restricted, proxy dead and no replacement available).
- **FAILED**: Terminal authentication failure, 0 saved.
*Duplicates alone do not imply PARTIAL.*

### Counter Semantics
- `discovered_count`: Items seen.
- `accepted_count`: Items matching logical criteria.
- `saved_new_count`: Items persisted canonically for the first time.
- `duplicate_count`: Items already existing canonically.
- `skipped_invalid_count`: Items safely skipped.
- `failed_item_count`: Items causing processing errors.
*Note: `saved_new_count != accepted_count` because accepted items may already exist canonically. Duplicate canonical records still create tenant `run_result` associations.*

### Worker Heartbeat
- `heartbeat_interval`, `last_heartbeat_at`, lease renewal relationship, and `stale_threshold` remain configuration-driven. Do not hardcode production timings.

### Browser Resource Boundary
Browser usage facts emitted: `browser_started`, `navigation_count`, `browser_duration_ms`, `render_failures`.
Browser crashes must not leak resources. When used as fallback, primary HTTP failure must be classified BEFORE invoking browser.

### Media & Screenshots
- Media metadata collection is separate from binary media download.
- Screenshot capture is a separate downstream capability (unless explicitly requested). Screenshot failure must not fail successful scraping.

### Raw Payload Policy
Classes: `temporary response payload`, `safe diagnostic excerpt`, `normalized item`.
Raw payload bounds: Bounded size, bounded retention, encrypted storage (if sensitive), secret scrubbing before persistence. NEVER contains account sessions or proxy auth.

---

## 7. SIGNALS, BILLING & RETRY ENGINE

### Billing Event Identity
Usage events emitted by workers have stable idempotency identity:
`usage_event_id, run_id, task_id, attempt_id, metric, quantity`.
Duplicate task delivery MUST NOT create duplicate billable usage.

### Internal Cost Event Identity
Internal cost telemetry (proxy bandwidth, browser duration, compute duration, external provider charges) has separate idempotent event identities. Do not use customer credit events as internal cost events.

### Health Event Safety
One timeout must not automatically mark a resource BANNED.
Classified signals map to states: `success`, `degraded`, `cooldown`, `checkpoint`, `blocked`, `banned`. Resource Manager aggregates health events.

### Structured Error Category Alignment
Categories (to be mapped in ERROR_RETRY_MATRIX):
`invalid_input`, `authentication_session`, `account_restricted`, `proxy_network`, `target_rate_limit`, `target_unavailable`, `selector_parse`, `content_not_found`, `resource_exhausted`, `worker_timeout`, `worker_crash`, `internal_system`, `billing_quota`, `cancelled`.

### Retry Ownership
- **Collector-Local Retry**: Bounded transport-level retry ONLY for safe transient network ops (if configured).
- **Retry Engine**: Authoritative for Task-level retry decisions (account rotation, proxy rotation, cooldown, retry_wait, attempt scheduling, terminal failure). Collector does NOT perform unbounded retry loops.

### Cancellation State
`cancel_requested` is a cooperative intermediate intent. Worker periodically checks durable state. Cancellation releases leases and preserves valid completed work. (Do not confuse `cancel_requested` with terminal `cancelled`).

---

## 8. PLATFORM CAPABILITY MATRIX

| Capability | Queue | Worker Group | Account | Proxy | Browser | Input | Output | Pagination | Primary Identity | Usage Signal |
|---|---|---|---|---|---|---|---|---|---|---|
| FB Posts | `queue:facebook_posts` | `facebook` | REQ | REQ | OPT/FB | URL | Post | Cursor | `platform+cap+id` | req, proxy, comp |
| FB Comments | `queue:facebook_comments`| `facebook` | REQ | REQ | OPT/FB | URL | Comment| Cursor | `platform+cap+id` | req, proxy, comp |
| IG Posts | `queue:instagram_posts` | `instagram` | REQ | REQ | NONE | URL | Post | Cursor | `platform+cap+id` | req, proxy, comp |
| IG Reels | `queue:instagram_reels` | `instagram` | REQ | REQ | NONE | URL | Video | Cursor | `platform+cap+id` | req, proxy, comp |
| IG Comments| `queue:instagram_comments`| `instagram` | REQ | REQ | NONE | URL | Comment| Cursor | `platform+cap+id` | req, proxy, comp |
| TT Videos | `queue:tiktok_videos` | `tiktok` | OPT | REQ | FALLBK | URL | Video | Cursor | `platform+cap+id` | req, proxy, brws |
| TT Comments| `queue:tiktok_comments` | `tiktok` | REQ | REQ | FALLBK | URL | Comment| Cursor | `platform+cap+id` | req, proxy, brws |
| YT Videos | `queue:youtube_videos` | `youtube` | NONE | REQ | NONE | URL | Video | Cursor | `platform+cap+id` | req, proxy, comp |
| YT Comments| `queue:youtube_comments` | `youtube` | NONE | REQ | NONE | URL | Comment| Cursor | `platform+cap+id` | req, proxy, comp |
| X Posts | `queue:x_posts` | `x` | REQ | REQ | NONE | URL | Post | Cursor | `platform+cap+id` | req, proxy, comp |
| X Replies | `queue:x_replies` | `x` | REQ | REQ | NONE | URL | Reply | Cursor | `platform+cap+id` | req, proxy, comp |
| News | `queue:news` | `news` | NONE | OPT | FALLBK | Feed/URL| Article| Page/Link| `platform+cap+canonical_url`| req, proxy, brws |
| Web | `queue:web` | `web` | NONE | OPT | FALLBK | URL | Article| Page/Link| `platform+cap+canonical_url`| req, proxy, brws |

*(FB = Fallback, OPT = Optional, REQ = Required)*

---

## 9. NORMALIZED RESULT EXAMPLES

### Post
```json
{
  "platform": "facebook",
  "capability": "posts",
  "source_identifier": "101589123456",
  "source_url": "https://facebook.com/example/posts/1015...",
  "dedupe_hash": "a1b2c3d4...",
  "author": { "username": "example" },
  "published_at": "2023-10-01T12:00:00Z",
  "collected_at": "2023-10-02T08:00:00Z",
  "text_content": "Hello world!",
  "engagement_metadata": { "likes": 500, "shares": 20 }
}
```

### Video
```json
{
  "platform": "tiktok",
  "capability": "videos",
  "source_identifier": "70011223344",
  "source_url": "https://tiktok.com/@user/video/7001...",
  "dedupe_hash": "x9y8z7...",
  "author": { "username": "user" },
  "published_at": "2023-10-01T12:00:00Z",
  "text_content": "Check this out #viral",
  "media_references": [{"type": "video", "url": "https://..."}],
  "engagement_metadata": { "views": 15000 }
}
```

### Comment
```json
{
  "platform": "instagram",
  "capability": "comments",
  "source_identifier": "c_99887766",
  "parent_source_identifier": "p_123456",
  "parent_canonical_identifier": "instagram_posts_p_123456",
  "dedupe_hash": "b2c3d4e5...",
  "author": { "username": "user1" },
  "published_at": "2023-10-02T10:00:00Z",
  "text_content": "Great post!"
}
```

### Reply
```json
{
  "platform": "x",
  "capability": "replies",
  "source_identifier": "t_99887766",
  "reply_to_identifier": "t_112233",
  "thread_identifier": "t_112233",
  "parent_canonical_identifier": "x_posts_t_112233",
  "depth": 1,
  "dedupe_hash": "c3d4e5f6...",
  "author": { "username": "reply_user" },
  "text_content": "I agree completely."
}
```

### News Article
```json
{
  "platform": "news",
  "capability": "article",
  "source_identifier": "article-1234",
  "canonical_url": "https://news.example.com/tech/2023/article-1234.html",
  "source_url": "https://news.example.com/tech/2023/article-1234.html?utm_source=twitter",
  "dedupe_hash": "d4e5f6g7...",
  "author": { "name": "Jane Journalist" },
  "title": "Technology Advances Quickly",
  "published_at": "2023-10-03T14:30:00Z",
  "text_content": "In recent developments, technology has shown..."
}
```

---

## 10. RECONCILIATION & IDEMPOTENCY
Reconciliation recovers stale task leases, crashed workers, orphan account/proxy leases. Reconciliation is idempotent.
Idempotency at the worker level guarantees duplicate Task delivery will not double-create canonical data, will not double-create unsafe linkage, and will not double-charge the customer for the same work.
