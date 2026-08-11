# AI HANDOFF - SOCIAL MEDIA SCRAPING API

## Current State
- Research/feasibility: completed conceptually.
- PRD v2.0: completed and locked.
- System Design + UI/UX baseline v2.0: completed and locked.
- Full product implementation must not start yet. Minimal POC implementation is allowed only within the owner-approved Facebook Platform #1 POC scope and only under Stage 06 safety/policy gates.

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

## Platform #1 POC Scope
- Platform #1: Facebook
- POC Operations:
  - profile
  - single_post
  - profile_posts
  - replies
  - search_posts
- Target Types:
  - profile → username, url, id
  - single_post → url, post_id
  - profile_posts → username, url, id
  - replies → url, post_id, comment_id
  - search_posts → keyword, hashtag

## Current Stage
Facebook Platform #1 POC Execution

Current POC Phase: Phase A — Feasibility / Policy Gate — Ready for Owner Review

Next Phase:
Phase B — Offline Fixtures / Contract Validation

## Next Stage
Implementation Plan
