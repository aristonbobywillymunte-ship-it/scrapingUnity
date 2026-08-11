# PLATFORM #1 POC PLAN
## Social Media Scraping API / Social Data Service

**Stage:** 06 — Platform #1 POC Plan  
**Status:** READY FOR OWNER REVIEW  
**Primary Output:** Execution plan for the first platform Proof of Concept.

---

## 1. Executive Summary
This document defines the execution plan for the first platform Proof of Concept (POC) for the Social Media Scraping API. The POC aims to validate whether one narrowly scoped platform and operation can safely produce normalized public data using the approved HTTP-first architecture without starting full implementation or compromising locked security boundaries.

## 2. POC Purpose
The POC exists to validate whether one narrowly scoped platform + operation can safely produce normalized public data using the approved architecture. The plan defines exactly what evidence is required before implementation can be considered successful.

## 3. Scope
- Definition of the execution flow for the first platform capability.
- Validation parameters for HTTP fetch, parsing, normalizing, and persistence.
- Bounding the error handling, retries, stop conditions, and resource usage.
- Specifying necessary testing fixtures and the controlled live validation steps.

## 4. Non-Goals
- DO NOT write scraper code.
- DO NOT create migrations.
- DO NOT modify runtime configuration.
- DO NOT run live scraping.
- DO NOT start implementation yet.

## 5. Locked Architecture References
- HTTP-first architecture; Playwright fallback.
- Python acting purely as an execution Data Plane.
- Redis transient-only rule.
- PostgreSQL least-privilege persistence rule.
- Request fingerprint SHA-256.
- Implementation-neutral credential crypto and item fingerprint.
- `RATE_LIMITED` internal classification.
- ACCESS_RESTRICTED no-retry rule.

## 6. Owner Decisions Required
The owner has explicitly selected:
Platform #1: `Facebook`
Operations:
- `profile`
- `single_post`
- `profile_posts`
- `replies`
- `search_posts`

## 7. Platform Selection Gate
The owner has selected Platform #1: `Facebook`.

Historical context: The approved project platforms to select from were:
* Facebook
* Instagram
* Threads
* X

## 8. Operation Selection Gate
The owner has replaced the old single-operation assumption with:

`Facebook Platform #1 Capability Bundle`

Operations to test: profile, single_post, profile_posts, replies, search_posts.

## 9. Target Type Definition
The POC will focus on the following approved target types for the selected operations:
- profile → username, url, id
- single_post → url, post_id
- profile_posts → username, url, id
- replies → url, post_id, comment_id
- search_posts → keyword, hashtag

## 10. POC Execution Flow
The intended POC flow:
`Approved Target`
→ `Laravel/Internal Execution Contract`
→ `Redis Queue`
→ `Python HTTP Worker`
→ `Platform Adapter`
→ `Fetch`
→ `Parse`
→ `Normalize`
→ `Validate`
→ `PostgreSQL`
→ `Execution Summary`

Browser fallback may be tested separately only if the selected capability permits it. Do not implement this flow yet.

## 11. HTTP-First Validation Plan
The POC must validate HTTP collection first.
Evidence should include:
- request completed safely
- HTTP status
- content type
- bytes fetched
- fetch latency
- parser version
- items discovered
- normalized items valid
- invalid items
- retry count
- stop reason

HTTP success is preferred over Browser fallback where equivalent public data is obtainable safely.

## 12. Browser Fallback Validation Plan
Browser is fallback only.
Initial: `browser concurrency = 1`
Browser must NOT be used to bypass:
- CAPTCHA
- login wall
- access restrictions
- challenge pages
- private content

No stealth plugins. No fingerprint spoofing. No anti-detection system.

## 13. Platform Adapter Boundary
The adapter isolates platform-specific orchestrations away from the core execution loop. It validates targets, orchestrates the fetch, triggers the right parser, and standardizes output.

## 14. Parser POC Boundary
Parsers are restricted from fetching network data. They rely solely on the provided payload (DOM/JSON) to extract required fields, calculating item coverage strictly.

## 15. Normalizer Validation
The normalizer must translate raw extracted fields into the universal `scraping_items` structure without hallucinatory or extrapolated data.

## 16. Normalized Item Acceptance
POC output must conform to the locked normalized item schema:
- platform
- content_type
- external_id
- canonical_url
- author
- text
- published_at
- media
- metrics
- platform_fields
- collected_at
- parser_version

Rules:
- confirmed numeric zero = `0`
- unknown/unavailable = `null`
- never invent missing values

## 17. Pagination Validation
If the operation supports pagination, the POC must validate the collection loop limits, cursor progression, deduplication per execution loop, and boundary protections.

## 18. Deduplication Validation
Execution-level deduplication must prevent collecting the same item multiple times during the single scraper execution loop. Cross-job deduplication remains Laravel's responsibility.

## 19. Proxy Validation
Validating proxy routing (if enabled), rotation on safe network errors, and ensuring no proxies are wasted against hard security stops.

## 20. Retry Validation
POC must prove:
Retryable examples:
- selected transient network failures
- selected safe 5xx
- proxy connectivity failures

Non-retryable:
- CHALLENGE_PRESENT
- AUTH_REQUIRED
- ACCESS_RESTRICTED

Verify:
- bounded attempts
- backoff
- jitter
- Retry-After handling
- total execution timeout wins
- no retry storm
- no proxy rotation for access/challenge/auth restrictions

## 21. Error Classification Validation
Validate at minimum:
- RATE_LIMITED
- ACCESS_RESTRICTED
- CHALLENGE_PRESENT
- AUTH_REQUIRED
- NETWORK_ERROR
- UPSTREAM_ERROR
- PARSING_FAILED
- PROXY_UNAVAILABLE
- NORMAL (successful flow)

Confirm Laravel/public API mapping remains separate from Python internal classifications.

## 22. Rate Limit Validation
Ensure `Retry-After` headers are correctly identified, classified as `RATE_LIMITED`, and bubbled up correctly without causing retry storms.

## 23. Challenge / Auth / Access Restriction Tests
Ensure hard stops occur securely on CAPTCHAs, authentication barriers, and private access restrictions, emitting the correct execution summary without endless loop retries.

## 24. SSRF / Target Safety Tests
POC safety tests must cover:
- approved platform hostname
- target canonicalization
- DNS resolution
- resolved IP validation
- redirect revalidation
- IPv4 private ranges
- IPv6 private/unique-local ranges
- loopback
- link-local
- metadata endpoints
- Docker/internal services
- PostgreSQL
- Redis
- Laravel internal endpoints
- Python internal endpoints

No arbitrary crawler behavior.

## 25. Secret / Log Redaction Tests
Validation of the diagnostic/logging pipeline ensuring no secrets leak. Redact proxy credentials, cookies, customer keys, admin credentials, and `APP_KEY`.

## 26. Diagnostic Capture Validation
Validation that on structural failure, the system captures reduced, sanitized DOM snapshots without storing sensitive data.

## 27. Resource Budget
Respect initial 4 GB VPS baseline.
POC plan should preserve:
- 2 HTTP workers initially
- roughly 4 lightweight HTTP requests globally in flight maximum as baseline
- Browser concurrency 1
- bounded response size
- bounded pagination
- bounded execution timeout
- bounded diagnostics
- worker cleanup
- headroom for PostgreSQL, Redis, Laravel, and OS

These are POC/runtime tuning baselines, not permanent scale limits.

## 28. Worker Concurrency
Confirm HTTP requests don't exceed initial bounds and only one browser instance executes concurrently per VPS constraints.

## 29. Fixture Plan
Before controlled live validation, require sanitized fixtures covering:
- success
- empty response
- changed structure
- missing optional field
- missing required field
- pagination
- duplicate item
- rate limit
- challenge
- access restriction
- authentication required
- malformed response

No secrets or unauthorized private data.

## 30. Controlled Live Validation Plan
Live validation is allowed only AFTER:
1. Platform #1 is selected by owner.
2. Operation is selected by owner.
3. Policy/legal gate permits the controlled validation.
4. Fixtures pass.
5. Target is approved public data.
6. Safety controls are enabled.

The Stage 06 planning task itself MUST NOT perform live validation.

## 31. Test Dataset / Sample Requirements
Future POC samples must:
- match the owner-approved Platform #1
- match the owner-approved operation
- match the approved target type
- contain public data only
- avoid private/restricted/authenticated targets
- contain no credentials or private session data
- include representative successful samples
- include controlled negative/error fixtures where applicable
- be safe to reference in diagnostics and reports
- respect the platform policy/legal review gate

## 32. Success Metrics
Define measurable POC evidence including:
- successful execution count
- successful normalized records
- required-field coverage
- invalid-item rate
- HTTP fetch success
- parser success
- normalization success
- latency
- bytes fetched
- retries
- proxy success/failure where applicable
- error classifications
- memory/resource observation
- no secret leakage
- no SSRF violations
- no access-control bypass behavior

Do not invent a required numeric pass threshold unless already locked.

## 33. Failure Criteria
The POC must be considered unsuccessful or blocked if:
- required public data cannot be safely obtained
- parser cannot reliably produce required normalized fields
- access requires unsupported authentication
- challenge/CAPTCHA blocks collection
- access restriction prevents lawful/public retrieval
- resource use is incompatible with initial VPS
- SSRF/security controls fail
- secrets leak
- repeated parser failure occurs
- uncontrolled retry behavior occurs
- platform policy/legal gate blocks activation

Do not attempt bypasses.

## 34. Acceptance Criteria
The future POC may be marked PASS only when all required applicable criteria pass:
- owner-selected platform is explicitly recorded
- owner-selected operation is explicitly recorded
- target type is explicitly defined
- policy/legal gate permits controlled validation
- HTTP-first path is tested
- Browser is used only if capability permits/requires it
- execution contract validation passes
- target safety / SSRF validation passes
- fetch completes within bounded resource rules
- parser produces structured output
- required-field coverage is measured
- normalized schema validation passes
- numeric zero vs null semantics are preserved
- execution-level deduplication works
- pagination boundaries work where applicable
- retry/backoff behavior works for transient errors
- CHALLENGE_PRESENT causes stop/no retry
- AUTH_REQUIRED causes stop/no retry
- ACCESS_RESTRICTED causes stop/no retry
- no prohibited proxy rotation occurs
- diagnostics are sanitized
- secrets/log redaction passes
- PostgreSQL persistence contract is respected
- Redis remains transient-only
- worker cleanup succeeds
- resource usage remains compatible with initial VPS constraints
- required evidence/artifacts are captured
- no prohibited bypass/evasion behavior occurs

If an applicable mandatory criterion fails, POC cannot be PASS.

### PASS
All applicable mandatory acceptance criteria pass.

### PARTIAL
Core public-data extraction works, but one or more non-safety/non-policy capability criteria remain incomplete.

### FAIL
The selected capability cannot reliably satisfy required technical acceptance criteria.

### BLOCKED
Execution cannot proceed because of owner decision, policy/legal gate, access/auth/challenge restriction, security constraint, or another approved hard blocker.

Safety, SSRF, secret leakage, or prohibited bypass failures MUST NOT be classified as PARTIAL success.

## 35. Evidence / Artifacts Required
The future POC execution report must preserve evidence such as:
- platform
- operation
- target type
- safe target reference
- mode used
- parser version
- HTTP status
- execution duration
- items found
- valid items
- invalid items
- field coverage
- retries
- stop reason
- error classifications
- bytes fetched
- proxy reference masked
- resource observations
- fixture results
- controlled live validation results
- policy/legal gate status

No credentials or session secrets.

## 36. POC Result Report Template
Define a future POC result format containing:

## Platform
## Operation
## Target Type
## Scope
## HTTP Result
## Browser Result
## Parser Result
## Normalization Result
## Pagination Result
## Proxy Result
## Safety Result
## Resource Result
## Errors Observed
## Evidence
## Acceptance Criteria
## POC Status

POC Status must be one of: PASS, PARTIAL, FAIL, BLOCKED. Do not execute the report now.

## 37. Stop Conditions
Future POC execution must stop when any applicable condition occurs:
- requested item limit reached
- no next page/cursor
- duplicate/repeated cursor detected
- configured page bound reached
- total execution timeout reached
- cancellation received
- RATE_LIMITED policy requires stop/cooldown
- CHALLENGE_PRESENT
- AUTH_REQUIRED
- ACCESS_RESTRICTED
- unrecoverable PARSING_FAILED
- unrecoverable upstream/permanent error
- resource safety threshold reached
- SSRF/target safety validation fails
- secret/security boundary fails
- platform policy/legal gate blocks execution

Do not attempt bypasses after a hard stop.

## 38. Policy / Legal Gate
Commercial/platform connector activation requires completion of the approved platform policy/legal review gate and explicit owner authorization.

## 39. Change Recording Requirements
Every change must be documented.
Final report must include:
- Problem
- Fix
- Risk If Not Fixed
- Files Changed
- Verification
- Behavior Impact (Explicitly stating status of Backend, DB, API, UI, Migrations, Secrets, Git rewriting)
- Current Stage
- Next Stage

No undocumented changes.

## 40. Next Stage
Platform #1 POC Execution
