# DATABASE DESIGN / ERD
## Social Media Scraping API / Social Data Service

Version: 2.0 - AI Ready Final Baseline
Status: FINALIZED FOR OWNER REVIEW
Target Database: PostgreSQL 16+ (Docker Compose, VPS 4 GB RAM)
Current stage: Database Design / ERD
Next stage: API Specification

---

## 1. Executive Summary

Dokumen ini menentukan rancangan database logikal dan fisik untuk **Social Media Scraping API** sebagai aplikasi scraping mandiri. 

Arsitektur database mengadopsi prinsip:
- **PostgreSQL 16+** sebagai durable single source of truth.
- **Pemisahan Entitas Utama:** Customer Job (`scraping_jobs`) dipisahkan penuh dari Scrape Execution (`scrape_executions`) untuk mendukung request deduplication, active execution coalescing, dan cache reuse tanpa mengorbankan tenant isolation, billing audit, atau idempotency per customer.
- **Unified Scraping Output:** Menyimpan hasil eksekusi dari 3 tipe sumber (`api`, `manual`, `diagnostic`) dengan model persistensi yang konsisten.
- **AI-Assisted Parser Recovery Pipeline:** Mendukung pencatatan failure snapshot, pembuatan kandidat AI, hasil pengujian Python, persetujuan manual Admin, dan rollback versi parser.
- **Proxy & Health Infrastructure:** Pelacakan performa proxy berbasis score/cooldown dan status kesehatan platform/circuit breaker.
- **Tenant Isolation & Security:** Menggunakan ULID untuk Identifier Publik (mencegah ID enumeration), hashing API Key yang aman, enkripsi rahasia saat at rest, dan pembatasan otorisasi tenant berbasis `scraping_jobs.user_id`.

---

## 2. Database Principles

1. **Durable Source of Truth:** Seluruh data akun, kuota, kunci API, definisi job, riwayat eksekusi, item hasil ter-normalisasi, repositori parser, proxy, webhook, dan audit log disimpan di PostgreSQL.
2. **Redis Boundary:** Redis menangani operational state tingkat lanjut (queue messaging, temporary cache pointer, coalescing locks, API rate limiters, circuit breaker status, proxy cooldown status, dan worker heartbeat). Redis **bukan** durable storage.
3. **Public ULID vs Internal Bigint PK:**
   - Primary Key internal menggunakan `bigint GENERATED ALWAYS AS IDENTITY` untuk efisiensi indeks, join, dan ukuran tabel.
   - Kolom public ID menggunakan `ulid` (VARCHAR(26)) yang terindeks UNIQUE untuk eksposur REST API dan URL dashboard.
4. **Tenant Isolation Model:**
   - Pengguna (`users`) hanya dapat mengakses `scraping_jobs` yang memiliki `user_id` milik pengguna tersebut.
   - Otorisasi ke `scrape_executions` dan `scraping_items` **WAJIB** melalui join/lookup ke `scraping_jobs` milik tenant.
5. **No Code / No Migration Generation:** Dokumen ini memberikan spesifikasi logikal lengkap dan urutan dependency migration tanpa mengeksekusi migrasi file Laravel pada tahap ini.

---

## 3. Complete Table Inventory (26 Tables)

### Identity & Access (4 Tables)
1. `users` — Akun pengguna (Admin / User), kuota, dan status akun.
2. `plans` — Definisi tier/paket kuota internal dan rate limit.
3. `user_platform_access` — Permissions platform dan batasan operasi per user.
4. `api_keys` — API key hashed (SHA-256) per user untuk autentikasi REST API.

### Platform Capability & Health (4 Tables)
5. `platforms` — Master platform sosial (Facebook, Instagram, Threads, X).
6. `platform_capabilities` — Matrix kemampuan operasi per platform (HTTP/Browser, max items, cache TTL).
7. `platform_health` — Status kesehatan agregat platform & circuit breaker.
8. `platform_health_events` — Log insiden/perubahan status circuit breaker platform.

### Scraping Core (5 Tables)
9. `scraping_jobs` — Customer-facing job request (terikat pada tenant).
10. `scrape_executions` — Scraper execution sejati (dapat di-share via dedupe/coalescing).
11. `scraping_items` — Data hasil scraping ter-normalisasi (common schema + platform_fields).
12. `scraping_errors` — Log error teknis dan klasifikasi kegagalan scraping.
13. `diagnostic_artifacts` — Metadata pointer untuk DOM snapshot/sanitized fixture.

### Proxy & Worker Infrastructure (3 Tables)
14. `proxy_providers` — Provider proxy (BrightData, Oxylabs, dll).
15. `proxy_servers` — Server proxy inventory, status score, latency, cooldown, credentials terenkripsi.
16. `proxy_health_events` — Catatan event perubahan kesehatan proxy.

### Parser & AI Recovery Subsystem (4 Tables)
17. `parser_versions` — Versioning parser per platform + operation.
18. `parser_failures` — Incident report parser failure / field coverage drop.
19. `parser_candidates` — Kandidat perbaikan parser yang dihasilkan oleh AI Assistant.
20. `parser_validation_runs` — Hasil pengujian otomatis kandidat parser oleh Python worker.

### Usage & Integration (4 Tables)
21. `usage_ledger` — Catatan akuntansi konsumsi kuota berbasis successful normalized records.
22. `webhooks` — Konfigurasi endpoint webhook milik user.
23. `webhook_deliveries` — Log status pengiriman payload webhook.
24. `provider_connections` — Kredensial penyedia AI (OpenAI API key encrypted).

### System & Audit (2 Tables)
25. `audit_logs` — Immutable audit trail untuk aktivitas penting Admin & sistem.
26. `system_settings` — Konfigurasi global aplikasi.

---

## 4. Table Specifications

### 4.1 Identity & Access

#### 1. `users`
- **Purpose:** Menyimpan data pengguna aplikasi (Admin & User).
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL) — Public ID
  - `name` (varchar(255), NOT NULL)
  - `email` (varchar(255), UNIQUE, NOT NULL)
  - `password` (varchar(255), NOT NULL) — Hashed
  - `role` (varchar(20), NOT NULL, Default: 'user') — 'admin', 'user'
  - `status` (varchar(20), NOT NULL, Default: 'active') — 'active', 'suspended', 'disabled'
  - `plan_id` (bigint, FK -> plans.id, NULLABLE)
  - `monthly_quota` (integer, NOT NULL, Default: 10000)
  - `max_concurrency` (integer, NOT NULL, Default: 2)
  - `remember_token` (varchar(100), NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `ulid` (UNIQUE), `email` (UNIQUE), `role`, `status`.
- **Retention:** Permanent.

#### 2. `plans`
- **Purpose:** Definisi paket kuota internal dan batasan rate limit.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `name` (varchar(100), UNIQUE, NOT NULL)
  - `monthly_quota` (integer, NOT NULL)
  - `rate_limit_rpm` (integer, NOT NULL, Default: 60)
  - `max_concurrency` (integer, NOT NULL, Default: 2)
  - `allowed_modes` (jsonb, NOT NULL) — e.g. `["http", "browser"]`
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Retention:** Permanent.

#### 3. `user_platform_access`
- **Purpose:** Mengontrol hak akses pengguna terhadap platform dan operasi tertentu.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `user_id` (bigint, FK -> users.id, NOT NULL)
  - `platform_id` (bigint, FK -> platforms.id, NOT NULL)
  - `is_allowed` (boolean, NOT NULL, Default: true)
  - `custom_max_items` (integer, NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Unique Constraint:** `(user_id, platform_id)`
- **Retention:** Permanent.

#### 4. `api_keys`
- **Purpose:** Otentikasi API Key ter-hash milik customer.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL)
  - `user_id` (bigint, FK -> users.id, NOT NULL)
  - `name` (varchar(100), NOT NULL)
  - `key_prefix` (varchar(16), NOT NULL) — Displayed in UI (e.g. `sm_live_99f8`)
  - `key_verification` (varchar(255), UNIQUE, NOT NULL) — Approved one-way verification representation of full key
  - `scopes` (jsonb, NULLABLE) — e.g. `["jobs:create", "jobs:read"]`
  - `last_used_at` (timestamp with time zone, NULLABLE)
  - `last_used_ip` (varchar(45), NULLABLE)
  - `expires_at` (timestamp with time zone, NULLABLE)
  - `revoked_at` (timestamp with time zone, NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `key_verification` (UNIQUE), `user_id`, `key_prefix`.
- **Retention:** Permanent (sampai di-delete/revoke).

---

### 4.2 Platform Capability & Health

#### 5. `platforms`
- **Purpose:** Master registrasi sosial media target.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `code` (varchar(50), UNIQUE, NOT NULL) — 'facebook', 'instagram', 'threads', 'x'
  - `name` (varchar(100), NOT NULL)
  - `is_enabled` (boolean, NOT NULL, Default: true)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Retention:** Permanent.

#### 6. `platform_capabilities`
- **Purpose:** Matrix fitur operasi per platform dan batas teknisnya.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `platform_id` (bigint, FK -> platforms.id, NOT NULL)
  - `operation` (varchar(50), NOT NULL) — 'profile', 'single_post', 'profile_posts', 'replies'
  - `is_enabled` (boolean, NOT NULL, Default: true)
  - `http_supported` (boolean, NOT NULL, Default: true)
  - `browser_supported` (boolean, NOT NULL, Default: false)
  - `session_required` (boolean, NOT NULL, Default: false)
  - `max_recommended_items` (integer, NOT NULL, Default: 100)
  - `default_cache_ttl_seconds` (integer, NOT NULL, Default: 900)
  - `active_parser_version_id` (bigint, NULLABLE) — Set via logic after parser approval
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Unique Constraint:** `(platform_id, operation)`
- **Retention:** Permanent.

#### 7. `platform_health`
- **Purpose:** Status kesehatan real-time dan state circuit breaker platform.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `platform_id` (bigint, FK -> platforms.id, UNIQUE, NOT NULL)
  - `status` (varchar(20), NOT NULL, Default: 'healthy') — 'healthy', 'degraded', 'down', 'maintenance'
  - `circuit_state` (varchar(20), NOT NULL, Default: 'closed') — 'closed', 'open', 'half_open'
  - `success_rate_24h` (numeric(5,2), NOT NULL, Default: 100.00)
  - `avg_latency_ms` (integer, NOT NULL, Default: 0)
  - `last_success_at` (timestamp with time zone, NULLABLE)
  - `last_failure_at` (timestamp with time zone, NULLABLE)
  - `consecutive_failures` (integer, NOT NULL, Default: 0)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Retention:** Permanent (updated dynamically).

#### 8. `platform_health_events`
- **Purpose:** Audit trail log insiden dan perubahan status circuit breaker platform.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `platform_id` (bigint, FK -> platforms.id, NOT NULL)
  - `previous_status` (varchar(20), NOT NULL)
  - `new_status` (varchar(20), NOT NULL)
  - `previous_circuit_state` (varchar(20), NOT NULL)
  - `new_circuit_state` (varchar(20), NOT NULL)
  - `reason` (text, NULLABLE)
  - `metrics_snapshot` (jsonb, NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `platform_id + created_at`.
- **Retention:** 90 hari.

---

### 4.3 Scraping Core

#### 9. `scraping_jobs`
- **Purpose:** Customer-facing job request (terikat pada user/tenant tertentu).
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL) — Public API Job ID
  - `user_id` (bigint, FK -> users.id, NOT NULL)
  - `platform_id` (bigint, FK -> platforms.id, NOT NULL)
  - `operation` (varchar(50), NOT NULL)
  - `target_type` (varchar(50), NOT NULL) — 'username', 'url', 'id'
  - `target_input` (text, NOT NULL)
  - `request_fingerprint` (varchar(64), NOT NULL) — SHA256 canonical
  - `idempotency_key` (varchar(100), NULLABLE)
  - `scrape_execution_id` (bigint, FK -> scrape_executions.id, NULLABLE)
  - `status` (varchar(20), NOT NULL, Default: 'queued') — 'queued', 'waiting', 'processing', 'completed', 'partial', 'failed', 'cancelled', 'expired'
  - `requested_items` (integer, NOT NULL, Default: 10)
  - `returned_items` (integer, NOT NULL, Default: 0)
  - `error_code` (varchar(50), NULLABLE)
  - `error_message` (text, NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
  - `completed_at` (timestamp with time zone, NULLABLE)
- **Unique Constraint:** Partial Unique `(user_id, idempotency_key)` WHERE `idempotency_key IS NOT NULL`.
- **Indexes:** `ulid` (UNIQUE), `user_id + created_at`, `request_fingerprint`, `status + created_at`, `scrape_execution_id`.
- **Retention:** 90 hari+.

#### 10. `scrape_executions`
- **Purpose:** Internal execution sejati yang menjalankan HTTP/Browser fetch dan parser.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL) — Internal Execution ID
  - `platform_id` (bigint, FK -> platforms.id, NOT NULL)
  - `operation` (varchar(50), NOT NULL)
  - `source_type` (varchar(20), NOT NULL, Default: 'api') — 'api', 'manual', 'diagnostic'
  - `mode_used` (varchar(20), NOT NULL) — 'http', 'browser'
  - `request_fingerprint` (varchar(64), NOT NULL)
  - `status` (varchar(20), NOT NULL, Default: 'queued') — 'queued', 'processing', 'completed', 'partial', 'failed', 'cancelled'
  - `parser_version_id` (bigint, FK -> parser_versions.id, NULLABLE)
  - `proxy_server_id` (bigint, FK -> proxy_servers.id, NULLABLE)
  - `http_status_code` (integer, NULLABLE)
  - `execution_time_ms` (integer, NULLABLE)
  - `items_count` (integer, NOT NULL, Default: 0)
  - `field_coverage_pct` (numeric(5,2), NULLABLE)
  - `is_cache_hit` (boolean, NOT NULL, Default: false)
  - `initiated_by_user_id` (bigint, FK -> users.id, NULLABLE) — Populated if manual/diagnostic
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
  - `completed_at` (timestamp with time zone, NULLABLE)
- **Indexes:** `ulid` (UNIQUE), `request_fingerprint + status`, `platform_id + created_at`, `source_type + created_at`.
- **Retention:** 90 hari+.

#### 11. `scraping_items`
- **Purpose:** Menyimpan item data hasil scraping yang ter-normalisasi.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL)
  - `scrape_execution_id` (bigint, FK -> scrape_executions.id, NOT NULL)
  - `platform_id` (bigint, FK -> platforms.id, NOT NULL)
  - `content_type` (varchar(50), NOT NULL) — 'post', 'profile', 'comment'
  - `external_id` (varchar(255), NULLABLE)
  - `item_fingerprint` (varchar(64), NOT NULL) — SHA256(platform + content_type + external_id)
  - `canonical_url` (text, NULLABLE)
  - `author_handle` (varchar(255), NULLABLE)
  - `author_name` (varchar(255), NULLABLE)
  - `content_text` (text, NULLABLE)
  - `published_at` (timestamp with time zone, NULLABLE)
  - `media_json` (jsonb, NULLABLE) — Array of media objects
  - `metrics_json` (jsonb, NULLABLE) — Likes, shares, comments count (0 for known zero, null for unknown)
  - `platform_fields` (jsonb, NULLABLE) — Raw/unmapped platform specific metadata
  - `parser_version_id` (bigint, FK -> parser_versions.id, NOT NULL)
  - `collected_at` (timestamp with time zone, NOT NULL)
  - `expires_at` (timestamp with time zone, NULLABLE)
- **Indexes:** `ulid` (UNIQUE), `scrape_execution_id`, `platform_id + external_id`, `platform_id + published_at`, `expires_at`.
- **Retention:** 30 hari default (dapat disesuaikan per plan).

#### 12. `scraping_errors`
- **Purpose:** Log klasifikasi kesalahan teknis saat scraping.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `scrape_execution_id` (bigint, FK -> scrape_executions.id, NOT NULL)
  - `error_class` (varchar(50), NOT NULL) — 'NETWORK_ERROR', 'PLATFORM_RATE_LIMITED', 'ACCESS_RESTRICTED', 'CHALLENGE_PRESENT', 'PARSING_FAILED', 'UPSTREAM_ERROR'
  - `error_code` (varchar(50), NOT NULL)
  - `message` (text, NOT NULL)
  - `http_status_code` (integer, NULLABLE)
  - `raw_response_snippet` (text, NULLABLE) — Sanitized short text
  - `created_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `scrape_execution_id`, `error_class + created_at`.
- **Retention:** 30–90 hari.

#### 13. `diagnostic_artifacts`
- **Purpose:** Metadata pointer menuju controlled storage (S3/local storage) untuk HTML/DOM snapshot.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `scrape_execution_id` (bigint, FK -> scrape_executions.id, NOT NULL)
  - `artifact_type` (varchar(50), NOT NULL) — 'sanitized_dom', 'raw_http_headers', 'parser_fixture'
  - `storage_path` (varchar(512), NOT NULL)
  - `file_size_bytes` (integer, NOT NULL)
  - `sanitized` (boolean, NOT NULL, Default: true)
  - `expires_at` (timestamp with time zone, NOT NULL) — Short TTL
  - `created_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `scrape_execution_id`, `expires_at`.
- **Retention:** 24–72 jam.

---

### 4.4 Proxy & Worker Infrastructure

#### 14. `proxy_providers`
- **Purpose:** Repositori provider proxy.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `name` (varchar(100), UNIQUE, NOT NULL) — e.g. 'BrightData', 'Oxylabs'
  - `encrypted_api_token` (text, NULLABLE) — Encrypted at rest
  - `is_active` (boolean, NOT NULL, Default: true)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Retention:** Permanent.

#### 15. `proxy_servers`
- **Purpose:** Inventory proxy server, credentials terenkripsi, score, dan status cooldown.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `provider_id` (bigint, FK -> proxy_providers.id, NOT NULL)
  - `alias` (varchar(100), NULLABLE)
  - `proxy_type` (varchar(30), NOT NULL, Default: 'datacenter') — 'datacenter', 'residential', 'mobile'
  - `host` (varchar(255), NOT NULL)
  - `port` (integer, NOT NULL)
  - `encrypted_username` (text, NULLABLE) — Encrypted at rest
  - `encrypted_password` (text, NULLABLE) — Encrypted at rest
  - `country_code` (varchar(2), NULLABLE)
  - `status` (varchar(20), NOT NULL, Default: 'healthy') — 'healthy', 'degraded', 'cooldown', 'disabled'
  - `health_score` (integer, NOT NULL, Default: 100) — Range 0..100
  - `avg_latency_ms` (integer, NOT NULL, Default: 0)
  - `success_count_24h` (integer, NOT NULL, Default: 0)
  - `failure_count_24h` (integer, NOT NULL, Default: 0)
  - `cooldown_until` (timestamp with time zone, NULLABLE)
  - `supported_platforms` (jsonb, NULLABLE) — e.g. `["facebook", "instagram"]`
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `status + health_score`, `cooldown_until`, `provider_id`.
- **Retention:** Permanent.

#### 16. `proxy_health_events`
- **Purpose:** Audit log riwayat perubahan status kesehatan proxy.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `proxy_server_id` (bigint, FK -> proxy_servers.id, NOT NULL)
  - `event_type` (varchar(50), NOT NULL) — 'cooldown_triggered', 'recovered', 'disabled_by_error'
  - `reason` (text, NULLABLE)
  - `latency_ms` (integer, NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `proxy_server_id + created_at`.
- **Retention:** 30 hari.

---

### 4.5 Parser & AI Recovery Subsystem

#### 17. `parser_versions`
- **Purpose:** Repositori versi parser terstruktur per platform & operation.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `platform_id` (bigint, FK -> platforms.id, NOT NULL)
  - `operation` (varchar(50), NOT NULL)
  - `version` (varchar(30), NOT NULL) — e.g. 'v2.1.4'
  - `state` (varchar(20), NOT NULL, Default: 'candidate') — 'candidate', 'active', 'previous', 'disabled'
  - `selectors_json` (jsonb, NOT NULL) — Structured CSS/XPath/Regex extraction rules
  - `required_fields` (jsonb, NOT NULL) — e.g. `["author_handle", "content_text"]`
  - `created_by_user_id` (bigint, FK -> users.id, NULLABLE)
  - `approved_by_user_id` (bigint, FK -> users.id, NULLABLE)
  - `approved_at` (timestamp with time zone, NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Unique Constraint:** Partial Unique `(platform_id, operation)` WHERE `state = 'active'`. (Memastikan **HANYA 1 ACTIVE PARSER** per platform + operation).
- **Indexes:** `platform_id + operation + state`.
- **Retention:** Permanent.

#### 18. `parser_failures`
- **Purpose:** Pencatatan kejadian parser failure atau penurunan field coverage.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `parser_version_id` (bigint, FK -> parser_versions.id, NOT NULL)
  - `scrape_execution_id` (bigint, FK -> scrape_executions.id, NOT NULL)
  - `field_coverage_pct` (numeric(5,2), NOT NULL)
  - `missing_required_fields` (jsonb, NOT NULL)
  - `diagnostic_artifact_id` (bigint, FK -> diagnostic_artifacts.id, NULLABLE)
  - `status` (varchar(20), NOT NULL, Default: 'open') — 'open', 'ai_proposed', 'resolved', 'ignored'
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `parser_version_id`, `status + created_at`.
- **Retention:** 90 hari.

#### 19. `parser_candidates`
- **Purpose:** Menyimpan rekomendasi kandidat perbaikan selector dari AI Assistant.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `parser_failure_id` (bigint, FK -> parser_failures.id, NOT NULL)
  - `suggested_selectors_json` (jsonb, NOT NULL)
  - `ai_confidence_score` (numeric(5,2), NOT NULL) — e.g. 96.50
  - `ai_reasoning` (text, NULLABLE)
  - `status` (varchar(20), NOT NULL, Default: 'pending_validation') — 'pending_validation', 'validated', 'validation_failed', 'approved', 'rejected'
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `parser_failure_id`, `status`.
- **Retention:** 90 hari.

#### 20. `parser_validation_runs`
- **Purpose:** Hasil pengujian otomatis kandidat parser oleh Python worker terhadap test fixtures.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `parser_candidate_id` (bigint, FK -> parser_candidates.id, NOT NULL)
  - `test_fixtures_count` (integer, NOT NULL)
  - `passed_count` (integer, NOT NULL)
  - `failed_count` (integer, NOT NULL)
  - `field_coverage_pct` (numeric(5,2), NOT NULL)
  - `validation_details_json` (jsonb, NULLABLE)
  - `executed_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `parser_candidate_id`.
- **Retention:** 90 hari.

---

### 4.6 Usage & Integration

#### 21. `usage_ledger`
- **Purpose:** Catatan transaksi akuntansi penggunaan kuota (Immutable).
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL)
  - `user_id` (bigint, FK -> users.id, NOT NULL)
  - `scraping_job_id` (bigint, FK -> scraping_jobs.id, NOT NULL)
  - `successful_records_count` (integer, NOT NULL) — Kuota dikonsumsi berdasarkan valid records
  - `billing_period` (varchar(7), NOT NULL) — Format 'YYYY-MM'
  - `occurred_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `user_id + occurred_at`, `user_id + billing_period`.
- **Retention:** Permanent (Ledger).

#### 22. `webhooks`
- **Purpose:** Registrasi endpoint webhook customer.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL)
  - `user_id` (bigint, FK -> users.id, NOT NULL)
  - `url` (varchar(1024), NOT NULL) — SSRF pre-validated
  - `events` (jsonb, NOT NULL) — e.g. `["job.completed", "job.failed"]`
  - `encrypted_secret` (text, NOT NULL) — Cryptographic signature secret (encrypted at rest)
  - `is_active` (boolean, NOT NULL, Default: true)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `user_id`.
- **Retention:** Permanent.

#### 23. `webhook_deliveries`
- **Purpose:** Log riwayat dan pengulangan (retry) pengiriman payload webhook.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `ulid` (varchar(26), UNIQUE, NOT NULL)
  - `webhook_id` (bigint, FK -> webhooks.id, NOT NULL)
  - `scraping_job_id` (bigint, FK -> scraping_jobs.id, NOT NULL)
  - `event_type` (varchar(50), NOT NULL)
  - `payload_json` (jsonb, NOT NULL)
  - `response_status_code` (integer, NULLABLE)
  - `response_body_snippet` (text, NULLABLE)
  - `attempt_number` (integer, NOT NULL, Default: 1)
  - `status` (varchar(20), NOT NULL, Default: 'pending') — 'pending', 'success', 'failed', 'retrying'
  - `delivered_at` (timestamp with time zone, NULLABLE)
  - `next_retry_at` (timestamp with time zone, NULLABLE)
  - `created_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `webhook_id + created_at`, `status + next_retry_at`.
- **Retention:** 30–90 hari.

#### 24. `provider_connections`
- **Purpose:** Penyimpanan kredensial terenkripsi untuk penyedia layanan pihak ketiga (OpenAI API Key).
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `provider_name` (varchar(50), UNIQUE, NOT NULL) — 'openai'
  - `encrypted_credentials` (text, NOT NULL) — Encrypted at rest
  - `is_active` (boolean, NOT NULL, Default: true)
  - `created_at` (timestamp with time zone, NOT NULL)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Retention:** Permanent.

---

### 4.7 System & Audit

#### 25. `audit_logs`
- **Purpose:** Immutable audit trail untuk aksi penting Admin dan perubahan sistem.
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `user_id` (bigint, FK -> users.id, NULLABLE) — Null if system automated
  - `action` (varchar(100), NOT NULL) — e.g. 'user.created', 'parser.approved', 'proxy.disabled'
  - `auditable_type` (varchar(100), NULLABLE)
  - `auditable_id` (bigint, NULLABLE)
  - `ip_address` (varchar(45), NULLABLE)
  - `user_agent` (varchar(255), NULLABLE)
  - `metadata_json` (jsonb, NULLABLE) — Strictly masked!
  - `created_at` (timestamp with time zone, NOT NULL)
- **Indexes:** `user_id + created_at`, `action + created_at`.
- **Retention:** Permanent.

#### 26. `system_settings`
- **Purpose:** Konfigurasi runtime global (key-value).
- **Columns:**
  - `id` (bigint, PK, auto-increment)
  - `key` (varchar(100), UNIQUE, NOT NULL)
  - `value` (text, NULLABLE)
  - `type` (varchar(20), NOT NULL, Default: 'string') — 'string', 'integer', 'boolean', 'json'
  - `description` (text, NULLABLE)
  - `updated_at` (timestamp with time zone, NOT NULL)
- **Retention:** Permanent.

---

## 5. Relationship Model

```text
users (1) ────< api_keys (N)
users (1) ────< user_platform_access (N)
users (1) ────< scraping_jobs (N)
users (1) ────< usage_ledger (N)
users (1) ────< webhooks (N)

platforms (1) ────< platform_capabilities (N)
platforms (1) ────< platform_health (1)
platforms (1) ────< platform_health_events (N)
platforms (1) ────< scraping_jobs (N)
platforms (1) ────< scrape_executions (N)
platforms (1) ────< parser_versions (N)

scraping_jobs (N) >──── (0..1) scrape_executions (1)
scrape_executions (1) ────< scraping_items (N)
scrape_executions (1) ────< scraping_errors (N)
scrape_executions (1) ────< diagnostic_artifacts (N)

proxy_providers (1) ────< proxy_servers (N)
proxy_servers (1) ────< proxy_health_events (N)
proxy_servers (1) ────< scrape_executions (N)

parser_versions (1) ────< scrape_executions (N)
parser_versions (1) ────< scraping_items (N)
parser_versions (1) ────< parser_failures (N)
parser_failures (1) ────< parser_candidates (N)
parser_candidates (1) ────< parser_validation_runs (N)

webhooks (1) ────< webhook_deliveries (N)
scraping_jobs (1) ────< webhook_deliveries (N)
scraping_jobs (1) ────< usage_ledger (1)
```

---

## 6. Job vs Execution Relationship & Lifecycle

Relationship cardinality: **Many Customer Jobs -> One Scrape Execution** (`N:1`).

```text
Customer A Job (#job_101) ──┐
Customer B Job (#job_102) ──┼──> Scrape Execution (#exc_500) ──> Scraping Items (#item_1..#item_50)
Customer C Job (#job_103) ──┘
```

### Scenario Breakdown:
1. **Live Scrape:** Bila tidak ada active execution atau cache fresh yang cocok dengan `request_fingerprint`, sistem membuat 1 `scrape_executions` baru dan memautkan `scraping_jobs.scrape_execution_id`.
2. **Active Coalescing:** Bila request identik tiba saat `scrape_executions` sedang berstatus `processing`, `scraping_jobs` baru langsung ditautkan ke `scrape_execution_id` yang sedang berjalan tanpa membuat job HTTP/Browser worker ganda.
3. **Cache Reuse:** Bila request identik tiba saat data dalam TTL fresh di DB, `scrape_executions` baru dibuat dengan flag `is_cache_hit = true` dan mengutip item dari eksekusi sebelumnya, kemudian `scraping_jobs` menunjuk ke eksekusi cache hit tersebut.

---

## 7. Tenant Isolation Authorization Path

Authorization **WAJIB** mengevaluasi kepemilikan melalui `scraping_jobs`.

```sql
-- Otorisasi Aman pengambilan Items milik Tenant (User A)
SELECT items.* 
FROM scraping_items items
JOIN scrape_executions execs ON items.scrape_execution_id = execs.id
JOIN scraping_jobs jobs ON jobs.scrape_execution_id = execs.id
WHERE jobs.ulid = :job_ulid 
  AND jobs.user_id = :authenticated_user_id;
```

*Aturan:* Tenant tidak bisa langsung melakukan query ke `scrape_executions` atau `scraping_items` hanya dengan ID eksekusi internal. Akses ditolak jika `jobs.user_id != authenticated_user_id`.

---

## 8. Deduplication & Fingerprint Rules

### Request Fingerprint (`request_fingerprint`)
- **Formula:** `SHA256(platform_code + "|" + operation + "|" + normalized_target + "|" + canonical_options_json)`
- **Eksklusi:** `user_id`, `api_key`, `timestamp`, `request_id`, `idempotency_key` **TIDAK BOLEH** masuk dalam SHA256.

### Item Fingerprint (`item_fingerprint`)
- **Formula:** `SHA256(platform_code + "|" + content_type + "|" + external_id)`
- **Constraint:** Digunakan untuk lookup deduplikasi item, namun **TIDAK UNIQUE GLOBAL** agar memperbolehkan pengambilan metrics terbaru pada waktu yang berbeda. Unique index minimal per `(scrape_execution_id, item_fingerprint)`.

---

## 9. Usage Accounting & Quota Ledger

- Quota dikonsumsi berdasarkan **Successful Normalized Records** yang dihasilkan (`usage_ledger.successful_records_count`).
- **Failure Non-Billable:** Internal error, proxy crash, atau parser failure yang menghasilkan 0 item **TIDAK MEMOTONG** kuota pengguna.
- **Manual Scraping Lab Non-Billable:** Eksekusi dari Scraping Lab (`source_type = 'manual'` / `'diagnostic'`) dipemicu oleh Admin dan **TIDAK MENCATAT** transaksi pada `usage_ledger`.

---

## 10. Parser Maintenance & AI Recovery Model

Flow database menggaransi keandalan parser:

```text
[parser_failures] (Status: open)
       │
       ▼
[parser_candidates] (AI Assistant menghasilkan selector baru)
       │
       ▼
[parser_validation_runs] (Python worker menguji fixture, status: validated)
       │
       ▼
[Admin Review UI] (Admin meninjau & menekan Approve)
       │
       ▼
[parser_versions] (New Version diciptakan & state diubah ke 'active')
       │
       ▼
(Partial Unique Constraint menonaktifkan versi sebelumnya secara otomatis)
```

- **Rollback Support:** Admin dapat mengubah versi lama kembali menjadi `active`.

---

## 11. Security Rules & Secret Protection

1. **API Keys:** Plaintext API Key `sm_live_xxx` ditampilkan **hanya 1 kali** pada modal pembuatan. Database hanya menyimpan nilai hash/representasi verifikasi satu arah yang disetujui (e.g. `key_verification`) dan `key_prefix` (e.g. `sm_live_99f8`).
2. **Encrypted Secrets:** Kredensial proxy (`encrypted_username`, `encrypted_password`), provider API token (`encrypted_api_token`), webhook secrets (`encrypted_secret`), dan OpenAI Key (`encrypted_credentials`) **WAJIB** terenkripsi at rest (algoritma enkripsi final akan ditentukan pada tahap Security/Implementation).
3. **Audit Data Masking:** Data rahasia di-masking sebelum ditulis ke `audit_logs` atau UI log.

---

## 12. Retention Policy Matrix

| Tabel / Domain | Retention Window | Purge Strategy / Automation |
|---|---|---|
| `scraping_jobs` | 90 hari+ | Scheduled Pruning (Laravel Scheduler) |
| `scrape_executions` | 90 hari+ | Scheduled Pruning |
| `scraping_items` | 30 hari default | Scheduled Pruning (Batch Delete by `expires_at`) |
| `diagnostic_artifacts` | 24–72 jam | Fast Pruning & S3 File Deletion |
| `scraping_errors` | 30–90 hari | Scheduled Pruning |
| `platform_health_events` | 90 hari | Scheduled Pruning |
| `proxy_health_events` | 30 hari | Scheduled Pruning |
| `webhook_deliveries` | 30–90 hari | Scheduled Pruning |
| `usage_ledger` | Permanent | Retained for accounting |
| `audit_logs` | Permanent | Retained for security compliance |
| `parser_versions` | Permanent | Retained for historical rollback |

---

## 13. Indexing Strategy

- **Lookup Cepat API / Dashboard:** All `ulid` columns (UNIQUE).
- **Tenant Job History:** `scraping_jobs(user_id, created_at DESC)`.
- **Active Execution Lookup:** `scrape_executions(request_fingerprint, status)`.
- **Deduplication / Coalescing:** `scraping_jobs(request_fingerprint)`.
- **Item Query by Execution:** `scraping_items(scrape_execution_id)`.
- **Item Expiration Pruning:** `scraping_items(expires_at)`.
- **Usage Accounting Aggregation:** `usage_ledger(user_id, billing_period)`.
- **Active Parser Enforcement:** Partial Unique Index `parser_versions(platform_id, operation) WHERE state = 'active'`.

---

## 14. Entity Relationship Diagram (Mermaid ERD)

```mermaid
erdiagram
    users ||--o{ api_keys : "owns"
    users ||--o{ user_platform_access : "configured_with"
    users ||--o{ scraping_jobs : "creates"
    users ||--o{ usage_ledger : "billed_in"
    users ||--o{ webhooks : "configures"
    plans ||--o{ users : "defines_limits"

    platforms ||--o{ platform_capabilities : "supports"
    platforms ||--|| platform_health : "monitored_by"
    platforms ||--o{ platform_health_events : "logs"
    platforms ||--o{ scraping_jobs : "targeted_in"
    platforms ||--o{ scrape_executions : "executed_on"
    platforms ||--o{ parser_versions : "parsed_by"

    scraping_jobs }o--o| scrape_executions : "points_to"
    scrape_executions ||--o{ scraping_items : "produces"
    scrape_executions ||--o{ scraping_errors : "logs"
    scrape_executions ||--o{ diagnostic_artifacts : "attaches"

    proxy_providers ||--o{ proxy_servers : "supplies"
    proxy_servers ||--o{ proxy_health_events : "logs"
    proxy_servers ||--o{ scrape_executions : "routes"

    parser_versions ||--o{ scrape_executions : "uses"
    parser_versions ||--o{ scraping_items : "extracts"
    parser_versions ||--o{ parser_failures : "encounters"
    parser_failures ||--o{ parser_candidates : "analyzed_by"
    parser_candidates ||--o{ parser_validation_runs : "tested_in"

    webhooks ||--o{ webhook_deliveries : "triggers"
    scraping_jobs ||--o{ webhook_deliveries : "notifies"
    scraping_jobs ||--|| usage_ledger : "generates"
```

---

## 15. Migration Dependency Order

Berikut adalah urutan eksekusi migrasi tabel yang aman dari konflik Foreign Key constraints:

1. `plans`
2. `users`
3. `api_keys`
4. `platforms`
5. `user_platform_access`
6. `platform_capabilities`
7. `platform_health`
8. `platform_health_events`
9. `proxy_providers`
10. `proxy_servers`
11. `proxy_health_events`
12. `parser_versions`
13. `scrape_executions`
14. `scraping_jobs`
15. `scraping_items`
16. `scraping_errors`
17. `diagnostic_artifacts`
18. `parser_failures`
19. `parser_candidates`
20. `parser_validation_runs`
21. `usage_ledger`
22. `webhooks`
23. `webhook_deliveries`
24. `provider_connections`
25. `audit_logs`
26. `system_settings`

*(Catatan: Tidak ada file migrasi buatan yang dieksekusi pada tahap ini).*

---

## 16. Open Decisions Required from Owner

1. **Partitioning Threshold:** Apakah tabel `scraping_items` perlu dipersiapkan untuk PostgreSQL Declarative Partitioning (by range `collected_at`) jika jumlah record melampaui 10 Juta item? *(Rekomendasi saat ini: Belum diperlukan untuk MVP).*
2. **Item Expiration TTL Default:** Apakah TTL retensi 30 hari untuk `scraping_items` sudah sesuai dengan kebutuhan storage VPS 4 GB RAM, atau perlu diperpendek menjadi 14 hari untuk menghemat disk space?

---

## 17. Acceptance Criteria Checklist

- [x] Pemisahan `scraping_jobs` dan `scrape_executions` dipertahankan (Many Jobs to One Execution).
- [x] Otorisasi tenant diisolasi via `scraping_jobs.user_id`.
- [x] API Key hanya disimpan dalam bentuk hash/representasi verifikasi yang aman dengan prefix publik.
- [x] Kredensial proxy dan rahasia webhook terenkripsi at rest.
- [x] Scraping Lab (Manual/Diagnostic) tidak memotong kuota customer.
- [x] AI Parser recovery membutuhkan approval Admin (tidak auto-deploy).
- [x] Partial Unique Constraint menjamin hanya ada 1 Active Parser per platform + operation.
- [x] Format Identifier Publik menggunakan ULID; Primary Key internal menggunakan Bigint.
- [x] Tidak ada tabel atau fitur dari Arusbawah / sentiment / evasion / bypass.
- [x] Tidak ada file migrasi Laravel atau kode yang dieksekusi pada tahap ini.

---

DATABASE DESIGN / ERD: READY FOR OWNER REVIEW
