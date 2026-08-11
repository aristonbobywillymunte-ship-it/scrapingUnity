---
name: python-scraper-engineering
description: Enforces the exact architecture and boundary requirements for the Python scraper workers.
---

# Python Scraper Engineering Skill

## Purpose
Ensures that all AI agents implementing or designing the Python scraping workers adhere to the defined separation of concerns, concurrency limits, and error handling behaviors.

## When to Apply
Apply this skill when working on any Python scraping architecture, workers, parsers, or platform adapters.

## Mandatory Rules
- **Control & Data Planes:** Laravel is the control plane. Python is the scraping/data plane.
- **Worker Separation:** Separate HTTP and browser workers must exist.
- **HTTP-First:** Always use HTTP-first approaches. Playwright/browser fallback is used only when strictly required.
- **Concurrency:** Browser concurrency must remain conservative (1 per VPS MVP).
- **Modularity:** Platform adapters must remain modular. Parser and normalizer must be strictly separated.
- **Communication Contract:** Laravel and Python communicate strictly through approved job payload/result contracts via Redis and PostgreSQL.
- **No Secrets to Scraper:** Python does not receive the Laravel APP_KEY or Admin credentials.
- **Infrastructure:** Redis is used for queue and temporary coordination. PostgreSQL remains the durable source of truth.
- **Error Handling:** Use structured error classifications. Retry only transient failures (with backoff). Honor `Retry-After`. Stop on access restriction/challenge/auth requirement according to approved behavior. No retry storms.
- **Proxy Usage:** Proxies are for reliability/routing only, not aggressive evasion.
- **Parser Tracking & AI Recovery:** Parser versions must be tracked. Diagnostic output must be sanitized. AI parser recovery produces candidates only; Python validation must occur before Admin approval. AI never auto-deploys a parser.

## Forbidden Actions
- Creating a monolith combining Laravel and Python execution.
- Ignoring rate limits or implementing aggressive retry loops.
- Passing full Laravel environment secrets to Python workers.
- Implementing platform-specific assumptions unless approved for the current POC.

## Required Verification
- Verify that Redis is only used for temporary state/queues, and PostgreSQL for persistent data.
- Verify that AI parser candidate generation flow requires manual Admin approval.
