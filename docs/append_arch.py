import os

with open("/Users/unity/Documents/toolsscrapingv1/docs/SYSTEM_ARCHITECTURE.md", "a", encoding="utf-8") as f:
    f.write("""
## 15b. Search Architecture
Pencarian (search) dan filtering berjalan di sisi server. Database merupakan source of truth.
Search Index (e.g., Elasticsearch atau index berbasis PostgreSQL) dibangun secara asynchronous (via Redis Queue `search_index`) dan tidak membocorkan data antar-tenant.

## 15c. Observability & OTP Delivery
- **Observability**: Operasi penting dapat ditelusuri via `request_id`, `run_id`, `task_id`, `event_id`. 
- **OTP Delivery**: Delivery OTP difasilitasi oleh Notification Engine ke channel yang sesuai (Email / WA). OTP divalidasi dengan batasan expiry, single-use, max attempts, dan disimpan dalam bentuk hash.
""")

