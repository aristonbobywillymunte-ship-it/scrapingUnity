# PROXY SCHEMA RECONCILIATION & ALIGNMENT

## 1. Canonical ERD Specification (`03_DATABASE_DESIGN_ERD_AI_READY.md`)
The authoritative ERD documents three core proxy entities:
1. `proxy_providers` (id, name, encrypted_api_token, is_active, timestamps)
2. `proxy_servers` (id, provider_id, alias, proxy_type, host, port, encrypted_username, encrypted_password, country_code, status, health_score, avg_latency_ms, success_count_24h, failure_count_24h, cooldown_until, supported_platforms, timestamps)
3. `proxy_health_events` (id, proxy_server_id, event_type, reason, latency_ms, created_at)

## 2. Actual Physical Implementation Schema (`app_db`)
The underlying PostgreSQL database implements:
1. `provider_configs` (id, provider_name, encrypted_api_token, is_active, timestamps)
2. `proxies` (id, pool_id, host, port, encrypted_username, encrypted_password, protocol, country_code, is_active, status, health_score, latency_ms, last_checked_at, cooldown_until, timestamps)
3. `proxy_pools` (id, name, description, is_active, timestamps)
4. `proxy_leases` (id, proxy_id, task_id, leased_at, released_at, status)

## 3. Field-by-Field Semantic Mapping

| Canonical ERD Concept | Actual Database Entity | Compatibility Strategy | Notes / Verification |
|---|---|---|---|
| `proxy_providers.name` | `provider_configs.provider_name` / `proxy_pools.name` | Mapped at Model / Service Layer | Encrypted credentials stored securely |
| `proxy_servers.host` | `proxies.host` | Direct Mapping | IPv4/Hostname |
| `proxy_servers.port` | `proxies.port` | Direct Mapping | Integer Port |
| `proxy_servers.encrypted_username` | `proxies.encrypted_username` | Direct Mapping | AES-256 encrypted at rest, masked in UI |
| `proxy_servers.encrypted_password` | `proxies.encrypted_password` | Direct Mapping | AES-256 encrypted at rest, never shown plaintext |
| `proxy_servers.status` | `proxies.status` | Direct Mapping | `healthy`, `degraded`, `cooldown`, `disabled` |
| `proxy_servers.health_score` | `proxies.health_score` | Direct Mapping | Integer 0-100 |
| `proxy_servers.avg_latency_ms` | `proxies.latency_ms` | Direct Mapping | Latency in milliseconds |
| `proxy_servers.cooldown_until` | `proxies.cooldown_until` | Direct Mapping | Timestamp |
| `proxy_health_events` | `task_leases` / System Logs | Mapped via health events & leases | Logged in sanitized audit trail |

## 4. Architectural Resolution
- **Historical Migration Protection**: No raw migrations (`2024_01_01_000001` through `2024_01_01_000008`) will be modified.
- **Proxy Packages Policy**: Standalone commercial resale "Proxy Packages" are **NOT REQUIRED BY PRD**. The platform only uses internal Proxy Pool rotation for autonomous scraper resiliency.
- **Data Security**: All proxy credentials in the UI are strictly masked (e.g. `user_****`, `••••••••`). Plaintext credentials are never logged or returned over public APIs.
