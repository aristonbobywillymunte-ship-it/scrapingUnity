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

## Project AI Skills
All project AI agents MUST load and apply relevant skills from `.ai/skills/` before performing any tasks to ensure they strictly follow approved project rules.

## Mandatory External UI/UX Skill
UI UX Pro Max must be loaded/applied for every UI/UX-related task.

## Current Stage
Platform #1 POC Plan — Ready for Owner Review

## Next Stage
Platform #1 POC Execution
