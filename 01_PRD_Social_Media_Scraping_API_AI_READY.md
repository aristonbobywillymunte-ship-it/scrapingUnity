# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## Social Media Scraping API / Social Data Service

Version: 2.0 - AI Ready Baseline
Status: LOCKED FOR NEXT STAGE
Target infrastructure: Docker Compose on VPS 4 GB RAM
Current stage: PRD complete
Next stage: System Design complete -> Database Design / ERD

## 1. Purpose
Dokumen ini adalah source of truth kebutuhan produk untuk aplikasi scraping sosial media mandiri. Dokumen dibuat agar engineer atau AI coding agent dapat melanjutkan proyek tanpa mengulang keputusan produk.

Aplikasi ini berdiri sendiri sebagai Social Media Data API / Scraping API. Integrasi dengan aplikasi lain, termasuk aplikasi utama milik owner, diperlakukan sebagai future API consumer dan tidak menjadi scope implementasi saat ini.

## 2. Product Vision
Menyediakan layanan pengambilan data sosial publik melalui REST API dan alat manual Admin, dengan hasil JSON terstruktur, asynchronous job processing, caching, deduplication, proxy management, observability, dan mekanisme pemeliharaan parser ketika struktur platform berubah.

Target platform jangka panjang:
- Facebook
- Instagram
- Threads
- X / Twitter

Prinsip utama: satu platform yang stabil, observable, aman, dan mudah diperbaiki lebih penting daripada empat platform yang rapuh.

## 3. Locked Product Decisions
| ID | Decision | Status |
|---|---|---|
| P-01 | Aplikasi adalah service scraping mandiri | LOCKED |
| P-02 | Laravel + Livewire + Blade menjadi control plane | LOCKED |
| P-03 | Python menjadi scraping/data plane terpisah | LOCKED |
| P-04 | PostgreSQL adalah source of truth | LOCKED |
| P-05 | Redis untuk queue, cache, locks, limiter, temporary state | LOCKED |
| P-06 | Deployment MVP memakai Docker Compose | LOCKED |
| P-07 | VPS awal 4 GB RAM | LOCKED |
| P-08 | HTTP-first, Playwright/Chromium hanya fallback | LOCKED |
| P-09 | Browser concurrency MVP maksimal 1 job | LOCKED |
| P-10 | Hanya dua role: Admin dan User | LOCKED |
| P-11 | User dibuat oleh Admin; tidak ada public registration | LOCKED |
| P-12 | Admin dapat manual scraping lewat Scraping Lab | LOCKED |
| P-13 | Admin dapat melihat seluruh hasil API/manual/diagnostic yang disimpan | LOCKED |
| P-14 | User hanya melihat resource miliknya | LOCKED |
| P-15 | Job customer dipisah dari actual scrape execution | LOCKED |
| P-16 | Request identik harus dapat dideduplikasi/coalesced | LOCKED |
| P-17 | Proxy dipilih berbasis health/score, bukan random agresif | LOCKED |
| P-18 | Parser versioning + rollback wajib | LOCKED |
| P-19 | AI hanya memberi kandidat perbaikan parser; Python menguji; Admin approve | LOCKED |
| P-20 | pgvector bukan dependency MVP; masuk Phase 2 | LOCKED |

AI/engineer tidak boleh mengubah keputusan LOCKED tanpa keputusan baru dari owner.

## 4. Users and Access Model
### 4.1 Admin
Admin dapat:
- Membuat, mengubah, suspend, dan disable User.
- Menetapkan quota, plan internal, max jobs, max items, dan allowed platforms.
- Melihat seluruh jobs, executions, results, errors, dan usage.
- Menjalankan Scraping Lab.
- Mengelola platform capability, parser, proxy, worker, queue, health, logs, settings.
- Menjalankan diagnosis parser, meminta kandidat AI, menguji kandidat, approve/reject, dan rollback parser.

### 4.2 User
User dapat:
- Login ke dashboard.
- Membuat/revoke API key.
- Membuat scraping job melalui REST API.
- Melihat jobs/results/usage/quota miliknya.
- Mengelola webhook miliknya.
- Membaca API documentation.

User tidak dapat melihat manual diagnostic Admin, proxy credentials, parser internals, atau data User lain.

### 4.3 Provisioning
Tidak ada endpoint public registration.
Flow: Admin -> Create User -> Assign quota/platform access -> Activate -> User login -> Create API key.

## 5. Core Product Flows
### 5.1 API Scraping Flow
Client -> Nginx -> Laravel API -> API key auth -> authorization -> quota -> rate limit -> validation -> cache -> dedupe -> customer job -> Redis -> Python worker -> platform -> parser -> normalizer -> PostgreSQL -> API polling/webhook.

### 5.2 Manual Scraping Flow
Admin -> Scraping Lab -> pilih platform/operation/target/mode -> diagnostic execution -> Python -> result -> preview/JSON/diagnostic -> optional persist.

Manual diagnostic tidak menggunakan quota User dan tidak billable.

### 5.3 Parser Recovery Flow
Parser failure -> DOM diagnostic snapshot -> sanitizer -> structure extractor -> AI selector assistant -> candidate(s) -> Python fixture/live validation -> score -> Admin review -> new parser version -> monitor -> rollback jika diperlukan.

## 6. Platform Capability Model
Setiap platform memiliki capability registry. Contoh operation:
- profile
- single_post
- profile_posts/timeline
- replies/comments
- search/keyword bila capability dan policy memungkinkan
- hashtag bila capability dan policy memungkinkan

Setiap capability minimal memiliki:
- platform
- operation
- enabled
- status
- http_supported
- browser_supported
- session_required
- max_items
- max_pages
- timeout
- cache_ttl
- active_parser_version

Tidak semua operation harus tersedia pada semua platform.

## 7. REST API Requirements
Gunakan universal job API, bukan endpoint terpisah untuk setiap platform.

Minimum external endpoints:
- POST /api/v1/jobs
- GET /api/v1/jobs
- GET /api/v1/jobs/{id}
- GET /api/v1/jobs/{id}/items
- DELETE /api/v1/jobs/{id}
- GET /api/v1/results
- GET /api/v1/platforms
- GET /api/v1/usage
- GET /api/v1/me
- POST /api/v1/api-keys
- GET /api/v1/api-keys
- DELETE /api/v1/api-keys/{id}
- POST /api/v1/webhooks
- GET /api/v1/webhooks
- DELETE /api/v1/webhooks/{id}

Create job harus asynchronous dan mengembalikan HTTP 202 + Job ID.

Job status:
- queued
- waiting
- processing
- completed
- partial
- failed
- cancelled
- expired

## 8. Standard Result Requirements
Normalized common fields:
- platform
- content_type
- external_id
- canonical_url
- author object
- username/display_name jika tersedia
- text/content
- published_at
- media array
- metrics object
- platform_fields object
- collected_at
- parser_version

Rule: angka 0 berarti diketahui bernilai nol; null berarti tidak tersedia/tidak diketahui.

Platform-specific data tidak boleh dipaksa ke common schema. Simpan di platform_fields/metadata.

## 9. Data Source Classification
Semua execution memiliki source_type:
- api
- manual
- diagnostic

Rules:
- API: memiliki customer/user ownership.
- Manual: dimulai Admin dan dapat disimpan sebagai manual result.
- Diagnostic: dapat temporary, default tidak menjadi permanent result.

Admin Data Center menampilkan semua source sesuai filter. User hanya melihat source_type=api miliknya.

## 10. Scraping Lab Requirements
Admin-only module untuk:
- platform/operation selector
- target type + target input
- Auto / HTTP Only / Browser Only
- max items/max pages yang dibatasi
- proxy selection Auto atau specific healthy proxy untuk diagnosis
- parser version selection untuk test
- bypass cache hanya untuk diagnostic
- optional save result
- result summary
- field diagnostic
- normalized JSON preview
- execution diagnostics
- structure diagnosis
- AI candidate generation
- candidate validation
- approve/reject
- parser rollback
- test history

Scraping Lab tidak menyediakan CAPTCHA bypass, auth bypass, fingerprint spoofing, atau aggressive restriction bypass.

## 11. Reliability Requirements
MUST HAVE:
- cache
- request fingerprint/deduplication
- active execution coalescing
- per-user API rate limit
- per-platform outbound limiter
- conservative concurrency
- classified retry
- exponential backoff + jitter
- Retry-After handling
- circuit breaker per platform
- platform health status
- proxy health status
- worker heartbeat
- parser health

Retryable: transient network timeout, temporary DNS, selected 5xx.
Tidak aggressive retry: CAPTCHA/challenge, access restriction, unsupported target, parser structural failure, normal 404.

## 12. Proxy Requirements
Proxy subsystem harus menyediakan:
- provider inventory
- encrypted credentials
- type/country metadata
- latency
- success/failure counters
- recent success rate
- status healthy/degraded/cooldown/disabled
- cooldown_until
- platform compatibility metadata
- score-based selection
- health test

Proxy dipakai untuk reliability/regional egress dan pemisahan workload. Jangan merancang proxy rotation untuk mengakali CAPTCHA/access controls.

## 13. Session Requirements
MVP memprioritaskan public/no-login scraping. Authenticated session pool bukan dependency MVP.

Jika Phase 2 memerlukan authorized session:
- encrypted at rest
- no plaintext cookies in logs/UI
- worker-only access
- revocable
- status healthy/cooldown/expired/disabled
- sticky session-proxy consistency jika dibutuhkan

Tidak ada mass account rotation atau account farming.

## 14. Parser Maintenance Requirements
Setiap platform parser harus versioned.
Simpan parser_version pada execution/item yang relevan.

Parser health memonitor:
- required field coverage
- parser success rate
- structural mismatch
- field null spike
- parsing error rate

AI parser recovery hanya dijalankan pada failure/manual diagnosis, bukan setiap scrape.
AI output dianggap candidate, bukan truth.
Candidate harus lulus automated validation sebelum Admin dapat mengaktifkan.
Rollback ke parser sebelumnya wajib tersedia.

## 15. Security Requirements
Critical MVP requirements:
- HTTPS
- session auth untuk dashboard
- hashed external API keys, plaintext ditampilkan sekali
- API key prefix + revoke + expiry support
- authorization/tenant isolation
- SSRF protection
- strict target validation
- allowed platform hostnames only
- DNS/IP validation termasuk redirects
- block localhost, private networks, link-local, Docker internal, metadata endpoints
- webhook HMAC signature + timestamp + replay protection
- webhook URL SSRF validation
- encrypted proxy/session credentials
- masked secrets in logs/admin
- PostgreSQL/Redis not public
- non-root Python containers
- least privilege DB access
- audit logs
- APP_DEBUG=false production

## 16. Docker and Infrastructure Requirements
MVP memakai Docker Compose.
Logical services:
- nginx
- laravel_app
- laravel_queue
- laravel_scheduler
- postgres
- redis
- python_http_worker
- python_browser_worker

Python browser worker dipisahkan dari HTTP worker.
PostgreSQL dan Redis hanya internal network.
Python workers memerlukan controlled outbound access.

Initial resource principle on 4 GB VPS:
- HTTP worker concurrency konservatif: sekitar 2 workers / maksimal sekitar 4 lightweight in-flight requests.
- Browser concurrency: 1.
- AI maintenance: 1 request at a time.
- Media tidak didownload default.
- Raw DOM diagnostic short retention.

## 17. Storage and Retention
MVP:
- normalized results: default 30 days configurable
- diagnostic DOM/HTML fragment: 24-72 hours
- operational logs: 14-30 days
- audit logs: longer retention
- media binaries: do not download by default

Support deletion and expiration workflows.

## 18. UI Requirements - Admin
Main navigation:
- Dashboard
- Users
- Data Center
  - Semua Hasil
  - API Results
  - Manual Results
  - Diagnostic/Failed
- Scraping
  - Jobs
  - Scraping Lab
  - Test History
- Platforms
- Parser
  - Versions
  - Failures
  - AI Candidates
  - Validation
  - Rollback
- Infrastructure
  - Proxy Pool
  - Workers
  - Queues
  - Platform Health
- System
  - API & Provider
  - Logs
  - Audit Logs
  - Settings

## 19. UI Requirements - User
Navigation:
- Dashboard
- Jobs
- Results
- API Keys
- Usage / Quota
- Webhooks
- Documentation
- Account

User Dashboard minimum metrics:
- requests today
- monthly usage
- quota remaining
- active jobs
- success rate
- failed jobs
- recent jobs

## 20. Observability Requirements
API:
- requests/min
- latency
- 4xx/5xx

Scraping:
- success/failure rate
- duration
- items/job
- browser fallback rate

Platform:
- HEALTHY/DEGRADED/DOWN/MAINTENANCE
- success rate
- 429 rate
- parser failure rate
- challenge/access restriction rate
- last successful collection

Proxy:
- health
- latency
- success rate
- recent failures

Worker/Queue:
- heartbeat
- CPU/RAM
- active jobs
- pending jobs
- oldest job
- failed jobs

## 21. MVP Scope
### MUST HAVE
- Admin/User roles
- Admin-created Users
- login/session auth
- API keys
- quota/platform permissions
- universal async Job API
- one stable platform adapter first
- Python HTTP worker
- Redis queues/cache/locks/limiter
- PostgreSQL
- Docker Compose
- Nginx
- basic proxy manager + health
- cache + dedupe/coalescing
- rate limiting + retry + circuit breaker
- normalized results
- Admin Data Center
- Scraping Lab
- parser versioning/health/failure detection
- basic AI parser candidate assistant
- Python candidate validation
- Admin approval/rollback
- SSRF protection
- logs/audit/platform health

### SHOULD HAVE / NICE TO HAVE
- webhooks
- browser fallback where truly needed
- proxy scoring UI
- usage charts
- parser fixture regression suite

### PHASE 2
- second/third platform
- authorized session pool if justified
- pgvector
- embeddings/semantic search
- customer AI analysis (sentiment/topic/entity/summary)
- automated billing
- scheduled parser canary tests

### PHASE 3
- fourth platform
- dedicated scraper servers
- multiple Python nodes
- dedicated browser nodes
- object storage
- managed PostgreSQL/Redis if justified
- enterprise scaling

### DO NOT BUILD YET
- four platforms simultaneously
- CAPTCHA solver
- fake-account system
- mass account rotation
- aggressive proxy rotation
- browser farm
- Kubernetes
- Kafka
- multi-region
- full media archive
- AI auto-deploy parser
- large pgvector/HNSW dependency
- complex RBAC

## 22. MVP Acceptance Criteria
MVP dianggap siap pilot jika:
1. Admin dapat membuat dan mengaktifkan User.
2. User dapat login dan membuat API Key.
3. API key disimpan hash dan plaintext hanya terlihat sekali.
4. User hanya dapat mengakses resource sendiri.
5. User dapat membuat async scraping job dan menerima 202 + Job ID.
6. Job diproses oleh Python melalui Redis, bukan subprocess dari HTTP request Laravel.
7. Satu platform adapter dapat menghasilkan normalized JSON konsisten.
8. Cache mencegah fetch ulang saat data masih fresh.
9. Request identik dapat share satu scrape execution.
10. Per-platform rate limiter dan circuit breaker bekerja terpisah.
11. Proxy health/cooldown dapat dipantau.
12. Parser version dicatat dan parser failure dapat terdeteksi.
13. Admin dapat menjalankan manual scraping di Scraping Lab.
14. Admin dapat melihat hasil API/manual di Data Center.
15. AI dapat menghasilkan candidate repair; Python dapat memvalidasi; Admin dapat approve/reject/rollback.
16. SSRF internal targets dan invalid redirects diblokir.
17. PostgreSQL/Redis tidak exposed ke public network.
18. Browser concurrent job tidak lebih dari 1 pada MVP config.
19. VPS 4 GB tidak mengalami OOM pada load target pilot.
20. Logs tidak mengandung API key penuh, cookie, session secret, proxy password, atau OpenAI secret.

## 23. Out-of-Scope Boundary
Aplikasi lain boleh menjadi future API consumer, tetapi logic aplikasi consumer tidak boleh masuk ke service ini.
Scraping service berhenti pada: collect -> extract -> normalize -> store -> return.
Business monitoring, keyword intelligence, notifications, media intelligence, dan domain-specific workflow milik consumer berada di luar scope service ini.

## 24. Stage Gate for AI / Engineer
PRD ini selesai. Jangan coding dari PRD langsung.
Urutan wajib berikut:
1. System Design (sudah tersedia dan harus dibaca bersama PRD)
2. Database Design / ERD
3. API Specification
4. UI/UX Specification detail
5. Implementation Plan
6. Development
7. QA
8. Security Audit
9. Deployment / Pilot

Pada tahap berikutnya, AI hanya boleh membuat Database Design/ERD. Jangan membuat migration atau application code sebelum stage tersebut disetujui.
