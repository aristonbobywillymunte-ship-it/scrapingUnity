# OPENAPI COVERAGE AUDIT

## Overview
This document cross-references the implementation-grade `docs/openapi.yaml` against the authoritative `docs/API_ARCHITECTURE.md`.

## Detailed Domain Audit

| Domain | Required Actions | Actual Operations | Security Verified | Permission Verified | Status |
|---|---|---|---|---|---|
| Auth / Login | 5 | 5 | Yes | N/A (Public) | COMPLETE |
| Recovery | 3 | 3 | Yes | N/A (Public) | COMPLETE |
| OTP | Included in Recovery | Included | Yes | N/A | COMPLETE |
| MFA | 2 | 2 | Yes | Yes | COMPLETE |
| Sessions | 3 | 3 | Yes | Yes | COMPLETE |
| Organization | 2 | 2 | Yes | Yes | COMPLETE |
| Members | 3 | 3 | Yes | Yes | COMPLETE |
| Runs | 4 | 4 | Yes | Yes | COMPLETE |
| Results | 1 | 1 | Yes | Yes | COMPLETE |
| Exports | 4 | 4 | Yes | Yes | COMPLETE |
| API Keys | 4 | 4 | Yes | Yes | COMPLETE |
| Webhooks | 7 | 7 | Yes | Yes | COMPLETE |
| Usage & Billing | 8 | 8 | Yes | Yes | COMPLETE |
| Packages & Subs | 8 | 8 | Yes | Yes | COMPLETE |
| Refunds | 5 | 5 | Yes | Yes | COMPLETE |
| Admin Users | 7 | 7 | Yes | Yes | COMPLETE |
| Selectors | 11 | 11 | Yes | Yes | COMPLETE |
| Social Accounts | 13 | 13 | Yes | Yes | COMPLETE |
| Proxies & Pools | 13 | 13 | Yes | Yes | COMPLETE |
| Operations/Health | 9 | 9 | Yes | Yes | COMPLETE |
| Errors & Incidents | 11 | 11 | Yes | Yes | COMPLETE |
| Notifications / WA | 15 | 15 | Yes | Yes | COMPLETE |
| Telegram / Email | 4 | 4 | Yes | Yes | COMPLETE |
| AI Assistant | 7 | 7 | Yes | Yes | COMPLETE |
| RBAC / Security | 6 | 6 | Yes | Yes | COMPLETE |
| Break-glass / Temp | 8 | 8 | Yes | Yes | COMPLETE |
| Audit Logs | 4 | 4 | Yes | Yes | COMPLETE |
| Branding / Settings | 8 | 8 | Yes | Yes | COMPLETE |

## Verification Checklist
- [x] Unauthenticated login/recovery security correctly represented as `security: []`.
- [x] Password recovery strictly models EMAIL and WHATSAPP.
- [x] API key secret returned explicitly only on creation (`ApiKeyCreatedResponse`).
- [x] Refund maker-checker explicitly modeled with typed requests and approvals.
- [x] MFA (`x-requires-mfa`) documented for critical actions like Break-glass and Refund approval.
- [x] Cursor pagination properly attached to high-volume lists (`limit` and `cursor` params).
- [x] `Idempotency-Key` header strictly required (`required: true`) for runs, exports, and refunds.
- [x] Every mutation endpoint has an explicitly typed `requestBody`.
- [x] Every path parameter (e.g., `{id}`) is fully declared.
- [x] Stable `operationId` used across all 171 operations.

## Conclusion
The generated OpenAPI specification has been elevated to an implementation-grade contract.

**OPENAPI VALIDATOR**: PASS (Redocly CLI)
**CRITICAL Gaps Remaining**: 0
**HIGH Gaps Remaining**: 0



### UPDATE AUDIT:
- Generic `POST /api/v1/runs` removed.
- 13 Public capability-specific create operations added.
- 13 App capability-specific create operations added.
- Stories explicitly excluded.
- Idempotency-Key required on every create.
- 202 Accepted response enforced.
