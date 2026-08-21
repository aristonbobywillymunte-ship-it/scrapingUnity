# Web Scraping & Orchestration Platform (`toolsscrapingv1`)

A multi-tenant, capability-driven social data scraping and orchestration application built on Laravel, Livewire, PostgreSQL, and Redis.

## Features & Verification Status

- **Web UI & Navigation**: Complete Laravel Livewire web interface across all primary screens (Dashboard, Runs, New Run, Results, Billing, API Keys, Team, Profile, Security, Admin, Operations).
- **Run Orchestration Pipeline**: Full asynchronous execution lifecycle:
  `Run` → `Task` → `Queue (database)` → `WorkerExecutionPipeline` → `Capability Worker` → `Collector` → `Canonical Persistence` → `RunResult`.
- **Deduplicated Canonical Storage**: Extracted entities are stored in `canonical_entities` and partitioned payload tables (`canonical_posts`, etc.) keyed by `identity_hash` to guarantee deterministic idempotency.
- **Results Inspection**: Full Livewire results viewer (`/results`, `/results/{result}`) showing extracted canonical records and payloads.
- **Financial Architecture**: Read-only customer billing interface, FEFO credit allocation with reservation/settlement/release lifecycle, and ledger accounting.
- **Role-Based Access Control (RBAC)**: Enforced organization-level and internal system role authorization with direct route policy protection (`/admin`, `/admin/operations`).
- **Responsive Web Interface**: Validated across mobile and desktop viewports (`375x812`, `768x1024`, `1024x768`, `1440x900`).

## Known Limitations

> [!WARNING]
> **Financial Concurrency**: True parallel financial concurrency remains **NOT FULLY VERIFIED** in the current verification environment. Transactional locking (`lockForUpdate()`) and invariant tests are present, but this is not equivalent to a real multi-process race-condition test.

## Tech Stack & Architecture

- **Framework**: Laravel 11 / Livewire 3
- **Database**: PostgreSQL 15 (Canonical migrations under `database/migrations/`)
- **Queue & Cache**: PostgreSQL / Redis
- **Testing**: Pest PHP (Behavioral and regression test suite) + Playwright (Headless Chromium real browser acceptance)

## Running the Application

1. **Start Environment**:
   ```bash
   docker compose up -d
   ```

2. **Execute Migrations**:
   ```bash
   php artisan migrate
   ```

3. **Run Background Queue Workers**:
   ```bash
   php artisan queue:work
   ```

4. **Run Test Suite**:
   ```bash
   ./vendor/bin/pest
   ```
