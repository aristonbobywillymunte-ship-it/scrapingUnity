# PROXY SCHEMA RECONCILIATION & ALIGNMENT

## 1. Canonical ERD Specification (`03_DATABASE_DESIGN_ERD_AI_READY.md`)
The authoritative ERD documents three core proxy entities:
1. `proxy_providers` (id, name, encrypted_api_token, is_active, timestamps)
2. `proxy_servers` (id, provider_id, alias, proxy_type, host, port, encrypted_username, encrypted_password, country_code, status, health_score, avg_latency_ms, success_count_24h, failure_count_24h, cooldown_until, supported_platforms, timestamps)
3. `proxy_health_events` (id, proxy_server_id, event_type, reason, latency_ms, created_at)

## 2. Actual Physical Implementation Schema (`app_db`)
The underlying PostgreSQL database implements:
1. `provider_configs` (id, provider_name, provider_type, status, safe_metadata, encrypted_credentials, key_reference, encryption_version, timestamps)
2. `proxies` (id, pool_id, host, port, health_status, operational_state, health_score, avg_latency_ms, success_count_24h, failure_count_24h, country_code, proxy_type, max_concurrency, encrypted_credentials, key_reference, encryption_version, cooldown_until, timestamps)
3. `proxy_pools` (id, name, status, max_concurrency, timestamps)
4. `proxy_leases` (id, proxy_id, task_id, worker_identity, acquired_at, expires_at, heartbeat_at, released_at, status, release_reason)

## 3. Field-by-Field Semantic Mapping

| Canonical ERD Concept | Actual Database Entity | Compatibility Strategy | Notes / Verification |
|---|---|---|---|
| `proxy_providers.name` | `provider_configs.provider_name` / `proxy_pools.name` | Mapped at Model / Service Layer | Encrypted credentials stored securely |
| `proxy_servers.host` | `proxies.host` | Direct Mapping | IPv4/Hostname |
| `proxy_servers.port` | `proxies.port` | Direct Mapping | Integer Port (1-65535) |
| `proxy_servers.encrypted_username` | `proxies.encrypted_credentials` | Merged Field | Encrypted at rest, masked in UI |
| `proxy_servers.encrypted_password` | `proxies.encrypted_credentials` | Merged Field | Encrypted at rest, never shown plaintext |
| `proxy_servers.status` | `proxies.health_status` | Direct Mapping | Enum (`HEALTHY`, `DEGRADED`, `UNHEALTHY`, `DRAINING`) |
| `proxy_servers.health_score` | `proxies.health_score` | Direct Column | Added via forward migration `2026_08_22_023839` (0-100 check constraint) |
| `proxy_servers.avg_latency_ms` | `proxies.avg_latency_ms` | Direct Column | Added via forward migration `2026_08_22_023839` (default: 0) |
| `proxy_servers.success_count_24h` | `proxies.success_count_24h` | Direct Column | Added via forward migration `2026_08_22_023839` (default: 0) |
| `proxy_servers.failure_count_24h` | `proxies.failure_count_24h` | Direct Column | Added via forward migration `2026_08_22_023839` (default: 0) |
| `proxy_servers.country_code` | `proxies.country_code` | Direct Column | Added via forward migration `2026_08_22_023839` (ISO 3166-1 alpha-2) |
| `proxy_servers.proxy_type` | `proxies.proxy_type` | Direct Column | Added via forward migration `2026_08_22_023839` (default: 'datacenter') |
| `proxy_servers.cooldown_until` | `proxies.cooldown_until` | Direct Mapping | Timestamp with time zone |
| `proxy_health_events` | `task_leases` / System Logs / `audit_logs` | Mapped via leases & logs | Logged in sanitized audit trail |

## 4. Architectural Resolution
- **Historical Migration Protection**: No raw historical migrations (`2024_01_01_000001` through `2024_01_01_000008`) are modified. Missing observability columns added via safe forward migration `2026_08_22_023839_add_health_score_and_latency_to_proxies_table.php`.
- **Proxy Packages Policy**: Standalone commercial resale "Proxy Packages" are **NOT REQUIRED BY PRD**. The platform only uses internal Proxy Pool rotation for autonomous scraper resiliency.
- **Data Security**: All proxy credentials in the UI are strictly masked (`••••••••`). Plaintext credentials are never logged or returned over public APIs.
