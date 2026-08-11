# SYSTEM DESIGN + UI/UX DESIGN
## Social Media Scraping API / Social Data Service

Version: 2.0 - AI Ready Baseline
Status: LOCKED FOR DATABASE DESIGN
Runtime target: Docker Compose, single VPS 4 GB RAM
Technology: Laravel + Livewire + Blade, Python, PostgreSQL, Redis, Nginx, Playwright fallback

## 1. Design Goal
Menerjemahkan PRD menjadi boundary arsitektur yang cukup presisi untuk tahap Database Design, tanpa membuat migration atau source code.

## 2. Architecture Principles
- Modular monolith Laravel sebagai control plane.
- Independent Python workers sebagai scraping/data plane.
- PostgreSQL source of truth.
- Redis operational state: queue, cache, lock, limiter, circuit state, heartbeat.
- Asynchronous jobs.
- HTTP-first.
- Browser worker terpisah dan low concurrency.
- Platform adapter isolation.
- Request jobs dipisah dari scrape execution.
- Security boundaries eksplisit.
- Docker Compose cukup untuk MVP; jangan microservice/Kubernetes berlebihan.

## 3. High-Level Architecture
```text
                       Internet / API Client / Admin
                                  |
                               [Nginx]
                                  |
                         [Laravel App/API]
              _____________|___________|____________
             |             |           |            |
         Session/Auth   API Key     Quota/ACL    Dashboard
             |             |           |            |
             +-------------+-----------+------------+
                                  |
                     Cache + Dedupe Decision
                                  |
                         [PostgreSQL Jobs]
                                  |
                                [Redis]
                   _______________|_______________
                  |                               |
        [Python HTTP Worker]             [Python Browser Worker]
                  |                         Playwright/Chromium
                  +---------------+---------------+
                                  |
                         [Platform Adapter]
                                  |
                    [Rate Limit / Circuit Breaker]
                                  |
                     [Proxy / Optional Session]
                                  |
                  Facebook / Instagram / Threads / X
                                  |
                               [Parser]
                          ________|________
                         |                 |
                    success            failure
                         |                 |
                   [Normalizer]      [DOM Diagnostic]
                         |                 |
                   [PostgreSQL]       [AI Candidate]
                         |                 |
                  API / Webhook       Python Validation
                                           |
                                      Admin Approval
                                           |
                                     Parser Version
```

## 4. Service Boundaries
### 4.1 Nginx
Responsibilities:
- public HTTPS entry point
- reverse proxy to Laravel
- request size limits
- TLS termination
- basic security headers

No direct public exposure for PostgreSQL, Redis, or Python internal services.

### 4.2 Laravel App
Responsibilities:
- Admin/User session authentication
- API key authentication
- authorization/tenant isolation
- user provisioning
- plan/quota/platform permission
- external REST API
- request validation and target normalization
- SSRF pre-validation
- customer job creation
- usage ledger orchestration
- dashboard/Admin UI
- Data Center
- Scraping Lab orchestration
- webhook management
- parser/proxy/platform configuration UI
- audit log

Laravel must not perform social scraping itself.

### 4.3 Laravel Queue Worker
Responsibilities:
- app-internal asynchronous jobs such as webhook delivery, maintenance, selected non-scraper work
- may coordinate scraper job dispatch metadata but must not replace Python scraping worker

### 4.4 Laravel Scheduler
Responsibilities:
- cleanup/retention
- health aggregation
- scheduled controlled probes/canaries later
- stale job checks
- usage aggregation where needed

### 4.5 Redis
Namespaces/functions:
- queues
- result/cache pointers
- request coalescing locks
- user API limiters
- platform outbound limiters
- circuit breaker state
- proxy cooldown temporary state
- worker heartbeat

Redis is not source of truth.

### 4.6 PostgreSQL
Persistent source of truth for users, keys metadata/hash, jobs, executions, normalized results, proxy inventory, parser versions, health history, usage, webhooks, and audit.

### 4.7 Python HTTP Worker
Responsibilities:
- consume scrape execution jobs
- select platform adapter
- acquire limiter/circuit permission
- acquire healthy proxy when configured
- HTTP fetch
- response classification
- parse and normalize
- persist execution/result via defined data-access contract
- emit metrics/errors

Recommended libraries conceptually: httpx + selectolax/lxml + Pydantic-style schemas.

### 4.8 Python Browser Worker
Responsibilities:
- consume only browser-required jobs
- Playwright + Chromium
- isolated browser context per job
- browser lifecycle/restart
- strict timeout/resource limits
- parser/normalizer reuse

MVP browser concurrency = 1.

## 5. Docker Compose Topology
Logical services:
```text
nginx
laravel_app
laravel_queue
laravel_scheduler
postgres
redis
python_http_worker
python_browser_worker
```

Recommended network zones:
```text
public_net:
  nginx

app_net:
  nginx
  laravel_app
  laravel_queue
  laravel_scheduler

internal_net:
  laravel_app
  laravel_queue
  laravel_scheduler
  postgres
  redis
  python_http_worker
  python_browser_worker
```

Python workers also need controlled outbound internet access.

Persistent volumes:
- postgres_data
- app_storage where needed
- optional short diagnostic storage

Do not bind PostgreSQL or Redis to public interfaces.

## 6. Initial 4 GB Resource Budget
Target ranges, not hard guarantees:
| Component | Working Budget |
|---|---|
| OS + Docker overhead | 500-700 MB |
| PostgreSQL | 450-650 MB |
| Laravel PHP-FPM | 300-450 MB |
| Laravel queue + scheduler | 150-250 MB |
| Redis | 80-150 MB |
| Python HTTP worker(s) | 150-300 MB |
| Chromium/browser job | 400-800 MB |
| Nginx | 30-60 MB |

Operational rules:
- keep normal usage below approximately 3.2 GB when possible
- retain headroom for kernel/filesystem cache/spikes
- one browser job at a time
- 2 HTTP workers initially; cap roughly 4 lightweight in-flight requests globally
- use container memory limits and worker recycling
- small swap may be emergency cushion, not capacity strategy

## 7. Authentication and Authorization Flow
Dashboard:
Browser -> Laravel session auth -> CSRF -> role -> policies.

External API:
Bearer API Key -> identify prefix -> verify hash -> active/revoked/expiry -> User -> scope/platform access -> quota -> rate limit -> request.

Role model is deliberately simple:
- admin
- user

Resource ownership must be enforced at query/service layer.

## 8. API Job Flow
```text
POST /api/v1/jobs
  -> authenticate key
  -> user active?
  -> validate platform/operation
  -> platform allowed?
  -> validate input/hard limits
  -> normalize target
  -> SSRF/hostname validation
  -> quota/rate limit
  -> canonical request fingerprint
  -> cache lookup
     -> fresh hit: create/reuse customer-facing job/result reference
  -> active execution lookup/lock
     -> matching execution active: subscribe customer job
  -> create new scrape_execution
  -> enqueue execution to Redis
  -> return 202 + job_id
```

Customer-facing Job and internal Scrape Execution are separate persistent concepts.

## 9. Job vs Execution Model
Conceptual relationship:
```text
User A Job ----\
User B Job -----+--> Scrape Execution X --> Scraping Items
User C Job ----/
```

Why:
- dedupe/coalescing
- cache reuse
- billable ownership remains distinct
- audit
- customer authorization
- one upstream fetch can satisfy several jobs

## 10. Request Fingerprint and Cache
Canonical fingerprint includes:
- platform
- operation
- normalized target
- normalized options that affect result

Hash with SHA-256.

Redis can hold fast cache pointers/locks; PostgreSQL holds durable execution/result state.

Initial configurable TTL examples:
- profile: 15 min
- recent post: 3 min
- older stable post: 6 hours
- engagement: 5 min
- not-found negative cache: 10 min

Numbers must be tuning/configuration, not hardcoded business truth.

## 11. Platform Adapter Design
Conceptual interface:
```text
supports(operation)
validate_target(target, options)
build_request(...)
fetch(...)
parse(...)
normalize(...)
classify_response(...)
```

Implementations:
- FacebookAdapter
- InstagramAdapter
- ThreadsAdapter
- XAdapter

Each adapter owns platform-specific parsing/fetch logic but reuses core HTTP, proxy, error, metrics, normalization helpers.

## 12. HTTP vs Browser Decision
HTTP is default.
Browser may be used only when capability metadata says rendering is required or controlled diagnosis proves HTTP cannot obtain the needed public content.

Browser is NOT automatic fallback for:
- CAPTCHA/challenge
- 403 access restriction
- 429
- login wall
- account restriction

Those responses are classified and stopped/cooldowned.

## 13. Proxy Architecture
```text
Provider -> Proxy Inventory -> Health State -> Score -> Selector -> Worker
```

Persistent proxy metadata:
- provider
- type
- hostname/port
- encrypted username/password
- country/region
- enabled/status
- recent success/failure
- latency
- cooldown_until
- platform compatibility

Selection strategy:
1. filter enabled + compatible + not cooldown
2. prefer healthy
3. weighted/score by recent success and reasonable latency
4. penalize recent network failures

Network failure may cause another healthy proxy on limited retry.
Challenge/access restriction must not trigger endless proxy cycling.

## 14. Session Architecture
MVP: no authenticated session pool dependency.

Phase 2 optional authorized session entity:
- platform
- encrypted session/cookie state
- proxy_id sticky binding when appropriate
- status
- last_used_at
- expires_at
- failure_count
- cooldown_until

Secrets are worker-only and never rendered plaintext in UI/logs.

## 15. Response Classification and Retry
Internal response/error classes:
- NORMAL
- PLATFORM_RATE_LIMITED
- ACCESS_RESTRICTED
- CHALLENGE_PRESENT
- AUTH_REQUIRED
- NETWORK_ERROR
- UPSTREAM_ERROR
- PARSING_FAILED
- PROXY_UNAVAILABLE
- UNSUPPORTED_TARGET

Retry matrix concept:
| Class | Retry |
|---|---|
| network timeout/reset | limited exponential backoff + jitter |
| temporary DNS | limited |
| selected 5xx | limited |
| 429 | honor Retry-After/cooldown; no immediate storm |
| 404 normal not found | no |
| challenge/CAPTCHA | no |
| access restriction | no aggressive retry |
| parser structural failure | no repeated refetch storm; raise parser health event |

## 16. Circuit Breaker and Platform Health
One circuit per platform; one platform cannot stop others.
States:
- CLOSED
- OPEN
- HALF_OPEN

Health labels:
- HEALTHY
- DEGRADED
- DOWN
- MAINTENANCE

Input signals:
- success rate
- parser failures
- rate-limit rate
- challenge/access restriction rate
- latency
- last success

Thresholds are configuration and must be tuned from POC.

## 17. Parser Versioning
Each platform has parser versions with one active version per capability or version scope defined later in database design.

Every execution/item must be traceable to parser version.

Lifecycle:
```text
candidate -> validating -> approved/active -> previous/inactive -> rolled_back/disabled
```

Do not overwrite parser history.

## 18. AI Parser Recovery Design
Trigger conditions:
- repeated parser failure
- required-field coverage drop
- manual Admin diagnosis

Pipeline:
```text
Failure
 -> capture relevant DOM/HTML fragment
 -> sanitize scripts/style/secrets/noise
 -> structural candidate extraction
 -> send compact diagnostic context to OpenAI
 -> AI returns ranked candidate selectors/rules + confidence
 -> Python validates on fixtures and controlled live samples
 -> Admin sees coverage/validity
 -> Admin approve/reject
 -> create/activate new parser version
```

AI never activates production parser automatically.

## 19. Scraping Lab - UI Layout
Admin-only.

Desktop concept:
```text
+-------------------------------------------------------------+
| SCRAPING LAB                           Platform: HEALTHY     |
+----------------------+--------------------------------------+
| Configuration        | Test Result                          |
| Platform             | Status                               |
| Operation            | HTTP/Duration                        |
| Target type/target   | Parser / Proxy                       |
| Mode Auto/HTTP/Browser| Items found/valid/failed            |
| Max items/pages      |                                      |
| Proxy Auto/specific  | Field Diagnostic                     |
| Parser version       | Author          FOUND                |
| Bypass cache(test)   | Content         FOUND                |
| Save result          | Published At    FAILED               |
| [Run Test]           | [Diagnose Structure]                 |
|                      | [Preview][JSON][Diagnostic][Log]     |
+----------------------+--------------------------------------+
```

Rules:
- advanced options collapsed by default
- Browser Only is Admin diagnostic control
- bypass cache only for diagnostic test
- default Save Result OFF
- secrets never displayed

## 20. Unified Admin Data Center - UI
Navigation:
- Semua Hasil
- API Results
- Manual Results
- Diagnostic/Failed

Filters:
- platform
- source_type
- user/owner
- operation
- status
- date range
- parser version

Columns:
- source
- platform
- owner
- target summary
- item count
- status
- collected/completed time

Result detail tabs:
- Overview
- Items
- JSON
- Execution
- Parser
- Proxy (masked)
- Errors
- Logs

## 21. Admin Dashboard - UI
Top metrics:
- total active users
- active jobs
- results today
- success rate

Runtime metrics:
- HTTP queue
- browser queue
- healthy proxies
- platform alerts
- VPS RAM/CPU summary

Platform cards:
- status
- success rate
- active parser
- last success

Recent activity:
- jobs
- parser alerts
- proxy alerts
- admin audit activity

## 22. User Dashboard - UI
User sees only own API data.

Overview cards:
- requests today
- monthly usage
- quota remaining
- active jobs
- success rate
- failed jobs

Pages:
- Jobs
- Results
- API Keys
- Usage
- Webhooks
- Documentation
- Account

No proxy/parser/internal diagnostic controls.

## 23. API Key UI
Create key dialog:
- name
- optional scopes if enabled
- expiry optional

After creation:
- show full plaintext exactly once
- show warning to store securely

List thereafter:
- name
- prefix
- scopes
- created_at
- last_used_at
- expires_at
- status
- revoke action

Admin never sees full key.

## 24. Webhook Design
Customer config:
- URL
- subscribed events
- secret generated/stored securely
- enabled

Events:
- job.completed
- job.partial
- job.failed

Delivery:
- HMAC-SHA256 signature
- timestamp
- event/delivery ID
- retry with backoff
- replay protection guidance
- SSRF validate webhook URL and redirect behavior

Webhook failure must not lose result; polling remains available.

## 25. SSRF Boundary
User-supplied target is untrusted.
Validation steps:
1. parse URL/target
2. HTTPS only for URL operations unless explicit safe exception later
3. hostname exact/subdomain allowlist for supported social platform
4. resolve DNS
5. reject loopback/private/link-local/multicast/metadata/internal addresses
6. connect with controlled client
7. validate every redirect before follow

Webhook URLs require separate egress SSRF policy; cannot use social-host allowlist but must still block internal/private/metadata targets.

## 26. Scraper Isolation
Python containers:
- non-root
- minimal packages
- resource limits
- process limits where practical
- no Laravel APP_KEY/admin credentials/customer passwords
- DB permissions only what scraper needs
- no public inbound port required for queue-based design
- outbound restricted/observable where practical

## 27. Data Retention Flow
Scheduled cleanup:
- expire old normalized results based on policy
- delete diagnostic snapshots after 24-72h
- rotate operational logs
- keep audit logs longer
- remove orphan temporary files

Raw full HTML should not become permanent default storage.
Media binary download disabled by default.

## 28. Observability Correlation
Every request/execution should carry correlation identifiers such as:
- request_id
- job_id
- execution_id
- worker_id

Logs must be structured enough to trace:
API request -> customer job -> scrape execution -> worker -> proxy -> parser -> result/webhook.

Never log:
- full API key
- password
- full cookie/session state
- proxy password
- OpenAI API key
- Authorization headers

## 29. Graceful Degradation Matrix
| Failure | Expected behavior |
|---|---|
| Instagram parser broken | Instagram degraded/open circuit; other platforms continue |
| Browser worker down | Browser-required jobs wait/fail typed; HTTP jobs continue |
| One proxy down | Cooldown proxy; healthy pool continues |
| Proxy provider down | Use alternate configured healthy pool or PROXY_UNAVAILABLE |
| Redis down | API/dashboard can read durable data; new async processing degraded/stopped safely |
| PostgreSQL down | Writes fail safe; workers stop taking new work; no blind processing |
| AI provider down | Normal scraping continues; AI parser repair unavailable |
| Webhook endpoint down | Retry; result remains available by API |
| User floods jobs | API limits/quota/concurrency reject before queue overload |

## 30. Stage Gate / Next AI Task
This System Design is complete for the current stage.

NEXT TASK ONLY: DATABASE DESIGN / ERD.

The next AI/engineer must:
- read PRD v2 and System Design v2 completely
- preserve locked decisions
- design entities/tables/relationships/indexes/constraints/retention
- explicitly model customer jobs vs scrape executions
- explicitly model API/manual/diagnostic source ownership
- explicitly model parser versions/candidates/validation
- explicitly model proxy health and platform health
- explicitly model API keys/usage/webhooks/audit
- prepare ERD conceptual + logical schema

DO NOT in the next stage:
- create Laravel migrations
- write models/controllers/services
- create Docker files
- implement Python scraper
- add routes/UI

After Database Design approval, proceed to API Specification.
