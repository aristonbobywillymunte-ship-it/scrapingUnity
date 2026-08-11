---
name: security-ssrf
description: Enforces target safety, SSRF boundaries, tenant isolation, and strict secret management.
---

# Security & SSRF Skill

## Purpose
Ensures that the API and scraping workers safely handle user-supplied URLs and targets, preventing Server-Side Request Forgery (SSRF) and ensuring absolute tenant isolation and secret protection.

## When to Apply
Apply this skill whenever implementing request fetching, URL validation, webhook delivery, proxy configuration, or data access queries.

## Mandatory Rules

### Target Safety
- Strict supported-platform target validation.
- HTTPS where required.
- Enforce hostname allowlists.
- Apply URL canonicalization.
- Perform DNS/IP validation.
- Enforce redirect revalidation.

### Blocked Targets (SSRF Boundaries)
Always block requests to:
- localhost
- loopback
- RFC1918/private networks
- link-local
- Docker internal services
- PostgreSQL
- Redis
- cloud metadata endpoints
- arbitrary internal network access

### Secrets Management
- API keys must be hashed in the database.
- Proxy, provider, and webhook credentials must be encrypted at rest.
- Secrets must be masked in all UI and logs.
- Never log: API keys, proxy passwords, cookies, session secrets, or authorization headers.

### Tenant Isolation
- Access must strictly follow: `Authenticated User → User Job → Execution → Items`
- Shared/coalesced executions must not expose another customer's identity, metadata, or data.

## Forbidden Capabilities
Never implement or design systems for:
- CAPTCHA solving
- Auth bypass or access-control bypass
- Fingerprint spoofing
- Stealth or anti-detection systems
- Stolen sessions
- Account farming
- Aggressive proxy rotation intended to bypass restrictions

## Required Verification
- Verify that every outbound request (HTTP worker or webhook delivery) passes through the SSRF filtering logic.
- Verify that any endpoint querying data enforces tenant ownership checks.
