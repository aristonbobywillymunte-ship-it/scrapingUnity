---
name: api-contract
description: Enforces the exact external API specification, endpoints, payload formats, and quota logic.
---

# API Contract Skill

## Purpose
Ensures all API design and implementation exactly matches the finalized API specification, preserving the `04_API_SPECIFICATION_AI_READY.md` document as the single source of truth for the external API contract.

## When to Apply
Apply this skill whenever creating Laravel routes, controllers, request validations, response resources, or when integrating external API access.

## Mandatory Rules
- **Base Path:** Ensure all endpoints use `/api/v1`.
- **Universal Interface:** Maintain the universal Job API. Do not split job endpoints by platform.
- **Asynchronous Flow:** `POST /jobs` must be asynchronous and return `202 Accepted`.
- **Authentication:** Only API Key Bearer authentication is allowed for the external API.
- **Identifiers:** Use public ULID identifiers, never internal database IDs.
- **Job != Execution:** Customer Jobs must remain decoupled from Scrape Executions.
- **Standards:** Enforce idempotency behavior, cursor pagination, standard success/error envelopes, and standard error codes.
- **Job Lifecycle:** Implement the approved Job status lifecycle.
- **Optimization:** Preserve cache and coalescing behavior transparency.
- **Tenant Isolation:** Ensure absolute User tenant isolation on all API resources.
- **Data Shape:** The normalized result schema must be respected. Adhere strictly to null vs. zero semantics (0 means known zero, null means unknown).
- **Webhooks & Telemetry:** Follow the defined webhook event contract and ensure request IDs are included in every response. Adhere to rate-limit behavior.

### Quota Rule (MANDATORY)
Quota is based strictly on successful normalized records delivered to the customer.
- Live 50 successful records → 50 quota
- Coalesced 50 successful records → 50 quota
- Cached 50 successful records → 50 quota
- Failed internal execution with no result → 0
- Admin Scraping Lab → 0 customer quota

### API Key Management
- API-key management endpoints must be dashboard/session-authenticated.
- External API keys must not create other API keys.

## Forbidden Actions
- Do not add new public endpoints unless explicitly approved.
- Do not expose internal proxy IDs, execution IDs, or worker IDs in the API.

## Required Verification
- Verify that API payloads match the `04_API_SPECIFICATION_AI_READY.md` exactly before implementation.
