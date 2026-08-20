import os

with open("/Users/unity/Documents/toolsscrapingv1/docs/SYSTEM_ARCHITECTURE.md", "a", encoding="utf-8") as f:
    f.write("""

## 19. Architecture Closure Checklist

Berikut adalah daftar elemen arsitektur (hardening tasks) yang telah diidentifikasi dan ditangani di dalam sistem ini:

- [x] **Task Lifecycle**: Run memecah pekerjaan menjadi Task. Status lifecycle: `queued` -> `running` -> (`completed` / `partial` / `failed` / `cancelled`).
- [x] **Dedicated Workers (termasuk YouTube/News/Web)**: Tiap workload (mis. `youtube_videos`, `news`, `web_crawler`) memiliki queue dan worker process terisolasi untuk menghindari _noisy neighbor effect_.
- [x] **Resource Manager Leases**: Worker meminjam (_lease_) Proxy & Account saat task dieksekusi. Lease otomatis hangus (_expired_) saat worker _crash_ / _timeout_ sehingga resource dikembalikan ke pool (koordinasi via Redis).
- [x] **Billing/Credit Lifecycle**: _Atomic reservation_ saat Run dimulai -> _Actual usage execution_ -> _Settlement_ di akhir eksekusi, dengan refund otomatis untuk _retryable internal errors_ agar tidak terjadi double-charge.
- [x] **Error Classification**: Error diisolasi ke dalam kategori spesifik (contoh: Network, Account Blocked, Quota Limit) yang terhubung pada Retry Engine, memastikan non-retryable errors langsung gagal.
- [x] **Tenant/Canonical-data Boundary**: Data dasar (canonical data) dilepaskan dari spesifik Run dan diagregasi secara sistem, namun di tingkat query/UI, semua _results_ tetap disaring via `run_results` agar isolasi _tenant_ (Organization) tidak bocor.
- [x] **Maintenance Behavior**: Maintenance bisa berjalan parsial per platform/scraper. Run berjalan tidak terputus, namun pembatasan Run baru dapat dilakukan (menahan kredit, mendiskon reserve).
- [x] **Observability**: Tiap _operation_ dilacak (_traceable_) dengan `request_id`, `run_id`, `event_id`, dan `task_id` untuk kemudahan _troubleshooting_.
- [x] **Graceful Degradation**: Kegagalan subsistem seperti Telegram Bot, Object Storage lambat, atau AI Provider mati tidak boleh memengaruhi layanan scraping inti atau billing pelanggan.
- [x] **Security Boundaries**: _Secret/Credentials_ proxy dienkripsi. Identitas sistem & _Service Account_ dipisah dari pelanggan/admin (_human users_). Limit memory / resource dipasang di masing-masing worker (_hard limits_).
- [x] **Mermaid Diagram Review**: Total 10 diagram Mermaid telah diverifikasi keberadaannya (meliputi konteks, komponen, run lifecycle, worker, resource, credit, retry, notifikasi, AI, dan scraping flow).
""")

