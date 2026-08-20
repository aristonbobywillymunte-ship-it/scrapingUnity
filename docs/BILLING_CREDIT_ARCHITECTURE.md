# BILLING & CREDIT ARCHITECTURE

This document defines the authoritative financial, billing, credit, and cost architecture for the platform. It strictly enforces the separation of customer-facing credits, internal operating costs, and revenue accounting.

---

## 1. CORE DISTINCTIONS

The architecture maintains strict isolation between four financial domains:

- **Credit**: The customer-facing consumption unit (e.g., "100 Credits").
- **Revenue**: The economic/monetary value actually earned from the customer for those credits.
- **Internal Cost**: The actual platform operating cost (proxy bandwidth, compute, browser duration, etc.).
- **Gross Profit**: `Revenue` - `Internal Cost`.

*These ledgers are logically separated. Customer UI must NEVER leak internal costs or margins.*

---

## 2. CREDIT SOURCES & LOTS

Credits are distributed in **Lots** to support distinct expiration policies and monetary value attribution.

**Lot Sources:**
- `SUBSCRIPTION`: Monthly/Annual package credits.
- `TOP_UP`: Manually purchased bulk credits.
- `BONUS`: Promotional credits.
- `ADJUSTMENT`: CS/Admin compensation.
- `REFUND`: Credits returned from failed Runs.

**Lot Schema Requirements:**
Each lot must preserve: `source`, `original_quantity`, `remaining_quantity`, `effective_monetary_value`, `created_at`, `expires_at`, and `reference`.

**Consumption Priority:**
The billing engine consumes credits using **FEFO (First Expire, First Out)**, allocating from the earliest-expiring eligible credit lot first.

---

## 3. RUN BILLING LIFECYCLE & ACCOUNTING

Every Run strictly follows a Reservation-Hold lifecycle to prevent overspending, race conditions, and double-debiting.

**Accounting Model (The Hold Pattern)**:
- `AVAILABLE CREDIT` -> `RESERVE/HOLD` -> `SETTLE` actual usage -> `RELEASE` unused hold back to `AVAILABLE`.
- `RESERVE` must NOT represent final consumption.

**Lifecycle Steps**:
1. **Estimate**: Run configuration calculates `estimated_amount`.
2. **Reserve**: Atomic deduction from `AVAILABLE` to `RESERVED`. (Total unconsumed credits remain unchanged).
3. **Run Execution**: Scraper engine executes.
4. **Usage Events**: Scraper emits idempotent usage signals.
5. **Settlement**: Run transitions to terminal state. Actual billable usage is calculated. `RESERVED` balance decreases by actual usage, and `CONSUMED` balance increases. `AVAILABLE` is NOT debited again.
6. **Release**: Any unused reserved credits decrease `RESERVED` and increase `AVAILABLE`.

**Example**:
- Start: `available=100`, `reserved=0`, `consumed=0`
- Reserve 100: `available=0`, `reserved=100`, `consumed=0`
- Settle usage 60 & Release 40: `available=40`, `reserved=0`, `consumed=60`
*(Net customer charge = 60, never 120).*

---

## 4. FINANCIAL INVARIANTS

The system strictly enforces the following accounting invariants:
- `available_balance` cannot become negative from concurrent reservations.
- `reserved_amount >= settled_amount + released_amount`.
- A terminal reservation eventually has a `reserved` remainder of exactly 0.
- One Run cannot settle twice.
- `settlement + release` cannot exceed `reservation`.
- Duplicate usage/settlement events are strictly idempotent.

---

## 5. BILLING EVENTS & IDEMPOTENCY

Every financial mutation uses idempotent identities:
- Credit Reservation: `reserve_{run_id}`
- Usage Event: `usage_{task_id}_{attempt_id}`
- Settlement: `settle_{run_id}`
- Release: `release_{run_id}`
- Refund: `refund_{reference_id}`
- Manual Adjustment: `adj_{audit_id}`

Duplicate Run, Task, or Retry events **must not** double-charge the customer.

---

## 6. PRICING & SNAPSHOTS

Pricing is configurable and versioned per scraper capability.
- **Concept**: Credits per valid result + optional minimum run charge.
- **Rule**: NEVER hardcode pricing into scraper code.

**Snapshotting**:
Every Run must snapshot the `pricing_version` and pricing rules at creation. Settlement uses the snapshotted rules, ensuring a price change during execution does not retroactively alter the Run's cost.

---

## 7. BILLABLE RESULT SEMANTICS

- **Valid Collected Result**: Billed according to pricing snapshot.
- **Duplicate within same Run**: Billed exactly once. Duplicate deliveries inside a single Run are NOT billed twice.
- **Previously Known Canonical Data**: A canonical result that already exists from *another* Run/Tenant, but was legitimately gathered during *this* Run's collection work, **IS** billable. The work was performed.
- **Invalid/Skipped Item**: Zero charge.
- **Failed Item**: Zero charge.
- **Retry Work**: Internal system/network retries **must not** double-charge.
- **Invalid Input (Run Setup)**: If rejected before dispatch, zero charge.

---

## 8. CREDIT LEDGER

The Ledger is an append-only, immutable transaction log. Historical entries are NEVER edited or deleted. Corrections use compensating entries.

**Transaction Event Classification**:
- **Balance Movement / Hold**: `RESERVE` (Moves Available -> Reserved), `RELEASE` (Moves Reserved -> Available).
- **Final Consumption**: `USAGE` (Deducts from Reserved permanently).
- **Compensating Entries / Adjustments**: `REFUND`, `ADJUSTMENT`.
- **Top-Ups / Generation**: `PACKAGE_CREDIT`, `PURCHASE`, `BONUS`.
- **Destruction**: `EXPIRED`.

---

## 9. CREDIT RESERVATION SCHEMA

A reservation ensures atomic balance tracking:
- `reservation_id`
- `organization_id`
- `run_id`
- `estimated_amount`
- `reserved_amount`
- `settled_amount`
- `released_amount`
- `status` (`PENDING`, `SETTLED`, `CANCELLED`, `EXPIRED`)
- `pricing_version`
- `created_at`
- `expires_at`

*One Run must not settle twice.*

---

## 10. SUBSCRIPTION / PACKAGE

- **Package Master**: Defines template prices, features, and limits.
- **Subscription Snapshot**: When a user subscribes, the exact Package state is snapshotted into the Subscription record (price, credits, duration, scraper access, resource limits, retention).
*Package master changes MUST NOT mutate an existing active Subscription snapshot.*

---

## 11. CREDIT EXPIRATION & PACKAGE EXPIRY

- Subscription credits expire at the end of the billing cycle.
- Top-up credits have configuration/policy-driven expiry.
- A reconciliation cron sweeps expired lots, generating `EXPIRED` ledger events. Unused balances are not silently deleted.

**Package Expiry Constraints**:
When a subscription expires, the customer may login, view retained history, and access invoices/payments. They CANNOT create new paid Runs unless an eligible active subscription or top-up credit policy allows it.

---

## 12. PAYMENT & REFUNDS

**Payment**:
- Separation of concerns: `Payment` -> `Invoice` -> `Subscription` -> `Credit Allocation`.
- Payment success is strictly idempotent. Provider webhook duplicates must NOT allocate packages/credits twice.

**Refunds**:
- `Payment Refund` (Fiat money) is distinct from `Run/Credit Refund` (Platform credits).
- **Maker-Checker**: Sensitive/large refunds follow explicit permission, MFA, and re-auth rules.
- Credit compensation and money refund must have explicit linkage references.

---

## 13. INTERNAL COST & REVENUE ATTRIBUTION

**Internal Cost**:
Tracked independently from billing. Dimensions include: `compute`, `memory`, `browser_runtime`, `proxy`, `bandwidth`, `external_provider`, `storage`, `export`, `notification`, `AI`.
Internal cost events have idempotent event identities. Internal costs are NEVER exposed to the customer API/UI.

**Revenue Attribution**:
Consumed credits obtain their revenue (economic value) from their parent `Credit Lot`.
- Subscription / Top-up: Pro-rata monetary value.
- Bonus / Refund: Zero or policy-defined monetary value.

---

## 14. PROFITABILITY REPORTING

The architecture supports calculating: `Revenue` - `Internal Cost` = `Gross Profit` (and `Margin`).
Reporting dimensions:
- Per Run
- Per Customer / Organization
- Per Package
- Per Scraper Capability
- Per Period

*Do not store unnecessary derived values in the database when they are reliably calculable via aggregated reporting views.*

---

## 15. RECONCILIATION & FAILURE SAFETY

**Failure Safety**:
If the Billing Service or database fails, scraper usage facts are durably queued and processed idempotently. Settlement is not marked successful until the durable financial state is committed.

**Reconciliation Scenarios (Idempotent & Auditable)**:
- **Stale Reservation**: Run crashed terminal but unsettled -> Auto-settle based on actual saved run_results, release remainder.
- **Duplicate Settlement**: Rejected via idempotency key.
- **Payment Paid / No Credits**: Reconciled via webhook / manual sweep.
- **Credits Allocated / No Payment**: Audited and flagged.
- **Expired Lots**: Swept daily via cron.

---

## 16. AUDIT & SECURITY

**Audit Log**:
Every financial mutation records: `actor`, `organization`, `reference`, `reason`, `request_id`, `run_id`, `previous_effect`, `current_effect`, `timestamp`. Manual adjustments require an explicit reason.

**Security Boundaries**:
- **Customer View**: Credit balance, Usage, Package, Invoice, Payment.
- **Finance/Owner View**: Revenue, Internal Cost, Profit, Margin, Refund Approvals, Platform adjustments.

---

## 17. FINAL MATRICES

### A. Run Status → Billing Behavior Matrix
| Run Status | Action | Settle Amount | Release Amount |
|---|---|---|---|
| `COMPLETED` | Finalize | Exact valid billable count | Remaining held reservation |
| `PARTIAL` | Finalize | Exact valid billable count | Remaining held reservation |
| `FAILED` | Finalize | 0 (No charge) | 100% of held reservation |
| `CANCELLED` | Finalize | Exact valid billable count | Remaining held reservation |

### B. Credit Transaction Type Matrix
| Type | Classification | Source | Destination |
|---|---|---|---|
| `PACKAGE_CREDIT` | Generation | System | Available Balance |
| `PURCHASE` | Generation | System | Available Balance |
| `RESERVE` | Balance Hold | Available Balance | Reserved Balance |
| `RELEASE` | Hold Release | Reserved Balance | Available Balance |
| `USAGE` | Final Consumption | Reserved Balance | Consumed (Permanent) |
| `REFUND` | Compensating | System | Available Balance |
| `BONUS` | Generation | System | Available Balance |
| `ADJUSTMENT` | Compensating | System | Available Balance |
| `EXPIRED` | Destruction | Available Balance | System Void |

### C. Credit Source / Expiry Matrix
| Source | Expiry Policy | Monetary Value | Consumption Priority |
|---|---|---|---|
| `SUBSCRIPTION` | End of billing cycle | Pro-rata sub price | Earliest expiry (FEFO) |
| `TOP_UP` | Policy-driven | Paid price | Earliest expiry (FEFO) |
| `BONUS` | Policy-driven | Zero / Configured | Earliest expiry (FEFO) |
| `REFUND` | Inherits original lot expiry | Original | Earliest expiry (FEFO) |

### D. Financial Event Idempotency Matrix
| Operation | Idempotency Key | Effect on Duplicate |
|---|---|---|
| Reserve | `reserve_{run_id}` | Return existing reservation |
| Usage Event | `usage_{task_id}_{attempt_id}` | Ignore/No-op |
| Settle | `settle_{run_id}` | Ignore/No-op |
| Release | `release_{run_id}` | Ignore/No-op |
| Payment | `payment_{provider_tx_id}` | Ignore/No-op |

### E. Customer vs Internal Finance Visibility Matrix
| Domain | Customer | Internal (Admin/Finance) |
|---|---|---|
| Credit Balance | YES | YES |
| Invoices / Payments | YES | YES |
| Package / Subscription | YES | YES |
| Run Credits Consumed | YES | YES |
| Internal Cost (Proxy/Compute) | **NO** | YES |
| Revenue / Gross Profit | **NO** | YES |
| Refund Approvals | **NO** | YES |

