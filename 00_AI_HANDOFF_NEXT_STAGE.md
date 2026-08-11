# AI HANDOFF - SOCIAL MEDIA SCRAPING API

## Current State
- Research/feasibility: completed conceptually.
- PRD v2.0: completed and locked.
- System Design + UI/UX baseline v2.0: completed and locked.
- No implementation should start yet.

## Required Reading Order
1. `01_PRD_Social_Media_Scraping_API_AI_READY.md`
2. `02_SYSTEM_DESIGN_Social_Media_Scraping_API_AI_READY.md`
3. This file.

## Locked Baseline
- Standalone scraping service.
- Laravel + Livewire + Blade control plane.
- Python independent scraper workers.
- PostgreSQL source of truth.
- Redis queue/cache/locks/limiters.
- Docker Compose on initial 4 GB VPS.
- Nginx entry point.
- HTTP-first; Playwright fallback; browser concurrency 1.
- Admin + User only; Admin creates User; no public registration.
- Admin Scraping Lab + unified Data Center.
- User sees own API resources only.
- Customer Job != Scrape Execution.
- Cache + request coalescing/deduplication.
- Proxy health/scoring, no aggressive restriction bypass.
- Parser versioning/health/recovery.
- AI proposes parser candidate; Python validates; Admin approves.
- pgvector Phase 2.

## Safety / Product Boundary
Do not design or implement CAPTCHA bypass, authentication bypass, access-control bypass, fingerprint spoofing, account farming, mass account rotation, or aggressive proxy cycling to evade restrictions.

## Next Stage
Create DATABASE DESIGN / ERD ONLY.

Expected deliverable:
- entity inventory
- conceptual ERD
- logical table design
- PK/FK relationships
- indexes and unique constraints
- JSONB vs normalized columns decisions
- soft delete / retention rules
- tenant ownership rules
- status enums/value sets conceptually
- data lifecycle
- storage estimates where useful
- migration order proposal only (no migration files)

## Mandatory entities to consider
Users/access:
- users
- plans / quota policy if retained
- api_keys
- user_platform_permissions
- usage_ledger

Scraping:
- scraping_jobs
- scrape_executions
- scraping_items
- scraping_errors / execution_attempts
- cache/result linkage as needed

Platform/parser:
- platforms
- platform_capabilities
- platform_health
- parser_versions
- parser_failures
- parser_candidates
- parser_validation_runs

Proxy/runtime:
- proxy_providers
- proxy_servers
- proxy_health_events
- scraper_workers / worker heartbeat history only if persistent history is justified
- scraper_sessions only as Phase 2 schema or deferred entity

Webhooks/audit:
- webhooks
- webhook_deliveries
- audit_logs

AI/Phase 2:
- ai_analyses
- embeddings / pgvector only as deferred schema section

## Do Not Skip Stage
After Database Design, next is API Specification, then detailed UI/UX Specification, then Implementation Plan, then Development.
