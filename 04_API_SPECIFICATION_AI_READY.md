# API SPECIFICATION
## Social Media Scraping API / Social Data Service

Version: 2.0 - AI Ready Final Baseline
Status: FINALIZED FOR OWNER REVIEW
Target Runtime: Laravel REST API
Current stage: API Specification
Next stage: Python Scraper Technical Specification

---

## 1. Executive Summary

Dokumen ini mendefinisikan spesifikasi kontrak REST API eksternal (customer-facing) untuk Social Media Scraping API. Spesifikasi ini dirancang dengan prinsip HTTP-first, struktur respons seragam, isolasi tenant yang ketat, dan dukungan untuk operasi scraping asinkron yang memanfaatkan mekanisme deduplikasi (coalescing) dan caching secara transparan.

## 2. API Principles

- **RESTful & Predictable:** Menggunakan HTTP verbs standar dan kode status HTTP yang semantik.
- **Asynchronous Execution:** Permintaan scraping yang berat dikelola sebagai Jobs.
- **Universal Interface:** Satu endpoint `/jobs` melayani semua platform, memisahkan rute dari detail implementasi platform.
- **Security-First:** Tenant isolation absolut berbasis ID Pekerjaan pelanggan, validasi SSRF ketat, dan manajemen secret (API keys, webhooks).
- **Graceful Reliability:** Mendukung Idempotency, Request Fingerprinting transparan, dan batasan rate-limiting eksplisit.

## 3. Base URL & Versioning

- **Base URL:** `https://api.example.com` (disesuaikan dengan domain produksi nantinya).
- **Version Path:** `/api/v1`
- **Versioning Policy:** Perubahan *non-breaking* (penambahan field) akan dipertahankan di `v1`. Perubahan *breaking* akan memerlukan `v2`.

## 4. Authentication

Otentikasi menggunakan HTTP Header standar:

```http
Authorization: Bearer <API_KEY>
```

- **Invalid Key:** Mengembalikan `401 Unauthorized`.
- **Revoked / Expired Key:** Mengembalikan `401 Unauthorized`.
- **Suspended / Disabled User:** Mengembalikan `403 Forbidden` jika pengguna di-suspend, meskipun kuncinya valid.
- **Scopes Validation:** Mengembalikan `403 Forbidden` jika kunci tidak memiliki scope yang diperlukan.
- Kunci lengkap (plaintext) **TIDAK PERNAH** dikembalikan oleh API manapun setelah pembuatannya.

## 5. Request IDs

Setiap permintaan API (berhasil atau gagal) secara otomatis menghasilkan `request_id` unik untuk keperluan pelacakan dan audit. `request_id` dikembalikan dalam meta response. Pelanggan dapat opsional mengirimkan header `X-Request-ID` untuk pelacakan end-to-end mereka.

## 6. Standard Response Envelope

Semua respons (kecuali download file atau webhook) menggunakan format JSON seragam.

**Success Response Format:**
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "request_id": "req_01H...",
    "pagination": { ... } // Opsional jika paginated
  }
}
```

## 7. Standard Error Envelope

**Error Response Format:**
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable error message."
  },
  "meta": {
    "request_id": "req_01H..."
  }
}
```
*Catatan: Stack trace server internal TIDAK PERNAH dikembalikan dalam environment produksi.*

## 8. Error Code Matrix

| Error Code | HTTP Status | Retryable | Description |
|---|---|---|---|
| `INVALID_TARGET` | 400 | No | The supplied target is invalid or malformed. |
| `UNSUPPORTED_PLATFORM` | 400 | No | The requested platform is not supported or disabled. |
| `UNSUPPORTED_OPERATION` | 400 | No | The requested operation is not supported on this platform. |
| `INVALID_API_KEY` | 401 | No | The provided API key is missing, invalid, or expired. |
| `PLATFORM_NOT_ALLOWED` | 403 | No | The authenticated user does not have permission for this platform. |
| `AUTH_REQUIRED` | 403 | No | Target requires authenticated session which is not supported/configured. |
| `ACCESS_RESTRICTED` | 403 | No | Target restricts access (e.g., private profile). |
| `QUOTA_EXCEEDED` | 403 | No | The user has exceeded their monthly quota of successful records. |
| `API_RATE_LIMITED` | 429 | Yes | User exceeded API request limits. Honor `Retry-After`. |
| `PLATFORM_RATE_LIMITED`| 429 | Yes | Upstream platform is rate limiting requests. Try again later. |
| `CHALLENGE_PRESENT` | 403 | No | Upstream CAPTCHA or challenge blocked the request. |
| `PLATFORM_UNAVAILABLE` | 503 | Yes | Upstream platform is down or degraded. |
| `PROXY_UNAVAILABLE` | 503 | Yes | No healthy proxies available for this request. |
| `SCRAPER_TIMEOUT` | 504 | Yes | Upstream request timed out. |
| `PARSING_FAILED` | 500 | No | Structural change upstream broke the parser. Admin intervention required. |
| `INTERNAL_ERROR` | 500 | No | Unhandled internal service failure. |

## 9. Rate Limiting

- Respons mengembalikan header:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset`
- Melebihi batas mengembalikan `429 Too Many Requests` (Error Code: `API_RATE_LIMITED`).

## 10. Idempotency

Klien direkomendasikan mengirim header `Idempotency-Key: <unique_string>` untuk permintaan POST.

- **Sama User + Sama Idempotency-Key + Sama Payload:** Mengembalikan respons `202 Accepted` atau status Job yang ada secara semantik sama dengan permintaan awal tanpa memotong kuota ganda.
- **Sama User + Sama Idempotency-Key + Beda Payload:** Mengembalikan `409 Conflict`.
- **Retention:** Idempotency-Key berlaku selama **24 jam**.

## 11. Pagination / Filtering / Sorting

- **Metode Pagination Utama:** Cursor-based pagination (menggunakan parameter query `cursor`).
- **Response Pagination Object (di dalam `meta.pagination`):**
  ```json
  "pagination": {
    "limit": 50,
    "next_cursor": "eyJpZ...==",
    "has_more": true
  }
  ```
- **Filter Utama:** `?platform=instagram`, `?status=completed`
- **Default Sort:** Descending (terbaru).

## 12. Job Lifecycle

Sebuah pekerjaan memiliki salah satu dari status berikut:

1. `queued`: Diterima oleh sistem, menunggu worker tersedia.
2. `waiting`: Coalesced atau tertunda oleh platform circuit breaker.
3. `processing`: Sedang dieksekusi oleh worker.
4. `completed`: Selesai berhasil (termasuk dari cache).
5. `partial`: Selesai dengan hasil sebagian (e.g., timeout sebelum mencapai limit, tapi ada item yang diselamatkan).
6. `failed`: Gagal total tanpa hasil.
7. `cancelled`: Dibatalkan oleh pengguna sebelum mulai memproses.
8. `expired`: Job TTL habis tanpa eksekusi.

*(Transisi tipikal: `queued` → `processing` → `completed`)*

## 13. POST /api/v1/jobs

Membuat permintaan scraping asinkron baru.

- **Request Body:**
  ```json
  {
    "platform": "instagram",
    "operation": "profile_posts",
    "target": {
      "type": "username",
      "value": "cristiano"
    },
    "options": {
      "limit": 50
    }
  }
  ```
- **Validation:**
  - `platform`, `operation`, `target.type`, `target.value` wajib.
  - `options.limit` max: sesuai dengan konfigurasi platform capability (`max_recommended_items`).
- **Success Response (202 Accepted):**
  ```json
  {
    "success": true,
    "data": {
      "job_id": "01H...",
      "status": "queued",
      "platform": "instagram",
      "operation": "profile_posts",
      "created_at": "2026-08-12T00:00:00Z"
    },
    "meta": { "request_id": "req_123" }
  }
  ```

## 14. GET /api/v1/jobs

Mendapatkan daftar Job milik pengguna yang terotentikasi.

- **Query Parameters:** `?platform=`, `?status=`, `?operation=`, `?cursor=`, `?limit=` (default 20, max 100).
- **Response:** Array of Job summary objects.

## 15. GET /api/v1/jobs/{job_id}

Mendapatkan status detail sebuah Job.

- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "job_id": "01H...",
      "platform": "instagram",
      "operation": "profile_posts",
      "target": {
        "type": "username",
        "value": "cristiano"
      },
      "status": "completed",
      "requested_items": 50,
      "successful_records": 50,
      "created_at": "...",
      "started_at": "...",
      "completed_at": "..."
    },
    "meta": {
      "request_id": "req_...",
      "resolution": "live" // "live", "cache", "coalesced"
    }
  }
  ```
- **Isolation:** Mengembalikan `404 Not Found` jika Job ID bukan milik pengguna ini.

## 16. GET /api/v1/jobs/{job_id}/items

Mengambil data terstruktur hasil dari sebuah Job.

- **Syarat:** Job harus berstatus `completed` atau `partial`.
- **Response:** Array of `Normalized Item Schema` objects (lihat bagian 24). Mendukung cursor pagination.

## 17. DELETE /api/v1/jobs/{job_id}

Membatalkan Job.

- **Aturan:** Hanya bisa membatalkan Job dengan status `queued` atau `waiting`.
- **Coalescing Safety:** Membatalkan Job A **tidak** membatalkan `scrape_execution` internal jika Job B (milik pengguna lain) juga bergantung padanya. Klien akan menerima `200 OK` (berhasil dibatalkan).

## 18. GET /api/v1/results

Mendapatkan histori hasil scraping dari *semua* Jobs milik pengguna secara kolektif (hanya Job status `completed`/`partial`). Mendukung paginasi dan filter (`platform`, `date_range`).

## 19. Platforms API

- **`GET /api/v1/platforms`**: Menampilkan daftar platform yang didukung dan tersedia untuk pengguna tersebut.
- **`GET /api/v1/platforms/{platform}`**:
  - **Response:**
    ```json
    {
      "success": true,
      "data": {
        "code": "instagram",
        "name": "Instagram",
        "availability": "healthy",
        "supported_operations": ["profile", "profile_posts"],
        "max_recommended_items": 100
      },
      "meta": { "request_id": "req_..." }
    }
    ```

## 20. Usage API

- **`GET /api/v1/usage`**: Menampilkan penggunaan kuota bulan ini.
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "billing_period": "2026-08",
      "quota": 50000,
      "used": 32410,
      "remaining": 17590,
      "utilization_pct": 64.82,
      "period_start": "2026-08-01T00:00:00Z",
      "period_end": "2026-08-31T23:59:59Z"
    },
    "meta": { "request_id": "req_..." }
  }
  ```

## 21. API Keys (Dashboard API)

Endpoint manajemen, biasanya digunakan via Dashboard App (Session auth), tapi diuraikan demi kelengkapan.
- `GET /api/v1/api-keys`
- `POST /api/v1/api-keys` (Menampilkan secret penuh `key` HANYA pada respons ini).
- `DELETE /api/v1/api-keys/{key_id}` (Mencabut akses kunci selamanya).

## 22. Webhooks

- `GET /api/v1/webhooks`
- `POST /api/v1/webhooks`
- `GET /api/v1/webhooks/{webhook_id}`
- `DELETE /api/v1/webhooks/{webhook_id}`
- `PATCH /api/v1/webhooks/{webhook_id}`

Setiap webhook memiliki parameter URL (divalidasi SSRF), `events` array, dan status `is_active`.

## 23. Webhook Event Payloads

- **Header Security:** `X-Hub-Signature-256` memuat HMAC-SHA256 signature, dihitung dari raw request body menggunakan webhook secret pelanggan. Header `X-Webhook-Timestamp` mencegah replay attacks.
- **Events:** `job.completed`, `job.partial`, `job.failed`.
- **Payload Data Structure:**
  ```json
  {
    "event": "job.completed",
    "event_id": "evt_...",
    "created_at": "...",
    "data": {
      "job_id": "01H...",
      "status": "completed",
      "successful_records": 50,
      "error": null
    }
  }
  ```
*Item payload actual (JSON array ratusan entitas) TIDAK DIKIRIM dalam webhook payload untuk menghemat overhead dan alasan keamanan.*

## 24. Normalized Item Schema

- Fields wajib (selalu hadir, nilai mungkin null jika informasinya memang tidak ada): `id`, `platform`, `content_type`, `external_id`, `canonical_url`, `author` (object), `text`, `published_at`, `media` (array), `metrics` (object), `platform_fields` (object), `collected_at`, `parser_version`.
- Aturan Metric: `0` berarti secara pasti diketahui nol. `null` berarti tidak diketahui/tidak dapat diambil.

## 25. Cache & Coalescing Behavior

Tiga jenis penyelesaian Job:
1. **Live:** Eksekusi baru ke upstream.
2. **Coalesced:** Menggabungkan dengan eksekusi internal yang sedang berjalan.
3. **Cache Reuse:** Memulihkan data dari hasil scraping sebelumnya di dalam database (diberikan metadata `resolution: "cache"`).

Kedua metode penghematan sumber daya (2 dan 3) sepenuhnya transparan ke pengguna (dianggap berhasil secara logika), namun memengaruhi field `meta.resolution` (untuk info) dan *dapat menghemat* pemotongan kuota bergantung pada billing model di PRD (Saat ini kuota dipotong berdasarkan *sukses rekam data* yang dirasakan pengguna).

## 26. Tenant Isolation

Isolasi mutlak. Jika Pengguna A meminta Job ID milik Pengguna B, kembalikan HTTP `404 Not Found` seolah-olah data itu tidak ada, BUKAN `403 Forbidden` (mencegah ID enumeration attack yang memverifikasi eksistensi resource).

## 27. Target Validation & SSRF Boundary

- Sistem melakukan validasi target sebelum antrean. Format `target.value` dipastikan wajar berdasarkan `target.type`.
- Panggilan webhook tidak dapat memicu endpoint `localhost`, range IP internal (10.x.x.x, 192.168.x.x, 172.16.x.x, dsb), atau endpoint AWS metadata.

## 28. Security Rules

- **HTTPS Only:** Koneksi non-TLS akan ditolak.
- **Data Masking:** ID Internal (seperti bigint id di Postgres, proxy id, execution id) tidak boleh bocor via response atau error messages API.

## 29. API Examples

*GET /api/v1/me*
```json
{
  "success": true,
  "data": {
    "user_id": "usr_01H...",
    "name": "Acme Corp",
    "plan": "Pro Tier"
  },
  "meta": { "request_id": "req_881" }
}
```

## 30. Acceptance Criteria

- [x] Desain API RESTful dengan respons amplop standar (success/error).
- [x] Pemisahan endpoint Job vs Execution, API hanya mengekspos Job.
- [x] Idempotency didukung penuh.
- [x] Payload item distandarisasi dan null aman, tidak otomatis 0.
- [x] Webhook payload aman (tidak overload) dan ditandatangani.
- [x] Model pagination Cursor di-deploy seragam di list results dan items.
- [x] Tidak ada API Keys / secrets tereskpos selain di endpoint creation awal.

## 31. Open Owner Decisions

- Apakah Webhook perlu sistem manual trigger/resend melalui REST API oleh pengguna nantinya? (MVP belum diuraikan, tetapi arsitektur mendasar sudah mendukungnya).

## 32. Next Stage

Python Scraper Technical Specification.
