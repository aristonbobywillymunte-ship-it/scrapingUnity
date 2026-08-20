import os

filepath = "/Users/unity/Documents/toolsscrapingv1/docs/UI_INFORMATION_ARCHITECTURE.md"

def write_section(content, mode="a"):
    with open(filepath, mode, encoding="utf-8") as f:
        f.write(content + "\n")

# Initialize file
write_section("# UI Information Architecture\n", "w")

write_section("""
==================================================
1. ACTOR / ROLE STRUCTURE
==================================================
**PUBLIC / AUTH**
- Unauthenticated Visitor

**CUSTOMER ORGANIZATION ROLES** (Scoped to Organization)
- Customer Owner: Full access to org billing, runs, members, API keys.
- Developer: Access to API keys, webhooks, runs, scraping configs.
- Analyst: Access to results, exports, run list.
- Viewer: Read-only access to dashboard and results.

**INTERNAL PLATFORM ROLES** (Scoped globally / by permission)
- Owner: Full system access, finance, pricing, audit.
- Admin: User/package management, scraping ops, maintenance.
- Operator / Support: Queue, worker, monitoring, error center, limited troubleshooting.
- Finance: Ledger, refunds, revenue, costs.
- Auditor / Security: Read-only access to audit logs, security events, roles.

*Note: Customer roles and internal platform roles are strictly separated.*
""")

write_section("""
==================================================
2. GLOBAL APPLICATION SHELLS
==================================================
**A. Public/Auth Shell**
- Centered card layout. Clean branding. No sidebar.

**B. Customer Portal Shell**
- Topbar: Org switcher, User Profile, Notifications, Quick Usage/Credit balance.
- Sidebar: Dashboard, Scraping, Runs, Results, Developer (API/Webhooks), Organization, Billing, Settings.
- Breadcrumbs: Required for nested pages (e.g., Runs / Run-123 / Result).

**C. Internal Admin/Operations Shell**
- Topbar: Global Environment Status, AI Assistant toggle, Notifications, Profile.
- Sidebar: Operations, Queue, Resources, Monitoring, Maintenance, Errors, Selector Management.
- Contextual Actions: Sticky action bars on list pages for bulk actions.

**D. Owner/Finance Shell**
- Topbar: AI Assistant toggle, Revenue Snapshot, Profile.
- Sidebar: Finance Dashboard, Profit Margin, Ledger, Payments, Refunds.

**E. Security/Audit Shell**
- Read-only focused layout with extensive filters in the sidebar/topbar for logs.
""")

write_section("""
==================================================
3. COMPLETE MENU TREE
==================================================
| Screen Name | Conceptual Route | Role | Permission | Visibility | Purpose |
|---|---|---|---|---|---|
| Customer Dashboard | `/dashboard` | Customer | `org.view` | Customer Auth | Overview of usage, recent runs |
| New Scraping Request | `/scrape/new` | Customer (Owner/Dev) | `runs.create` | Customer Auth | Init run |
| My Runs | `/runs` | Customer | `runs.view_own` | Customer Auth | Run tracking |
| My Results | `/results` | Customer | `results.view_own` | Customer Auth | Result explorer |
| API Keys | `/developer/api-keys` | Customer (Owner/Dev) | `api_keys.manage_own` | Customer Auth | API auth management |
| Webhooks | `/developer/webhooks` | Customer (Owner/Dev) | `webhooks.manage_own` | Customer Auth | Event listeners |
| Subscription | `/billing/subscription` | Customer (Owner) | `billing.manage_own` | Customer Auth | Package management |
| Admin Dashboard | `/admin/dashboard` | Admin | `admin.access` | Internal Auth | High-level system stats |
| User Management | `/admin/users` | Admin | `users.view` | Internal Auth | Manage identities |
| Error Center | `/ops/errors` | Operator | `monitoring.view` | Internal Auth | Centralized failure tracking |
| Queue & Workers | `/ops/queue` | Operator | `monitoring.view` | Internal Auth | Redis/Worker health |
| Social Accounts | `/resources/accounts` | Admin/Operator | `social_accounts.view` | Internal Auth | Source accounts |
| Selectors | `/ops/selectors` | Admin | `selectors.view` | Internal Auth | Parsing rules |
| Finance Dash | `/finance/dashboard` | Finance/Owner | `finance.view` | Internal Auth | Financial overview |
| Audit Logs | `/audit/logs` | Auditor/Owner | `audit.view` | Internal Auth | Security tracing |
""")

write_section("""
==================================================
4. SCREEN INVENTORY
==================================================
*(Format shortened for brevity but strictly enforcing PRD rules. Over 80+ screens mapped to batches below)*

**[AUTH-LOGIN] Login**
- Actors: All
- Purpose: Identity verification
- Entry: Public URL
- Layout: Centered Card
- Actions: Submit, SSO (Google)

**[CUS-SCRAPE-NEW] New Scraping Request**
- Actors: Customer Owner, Developer
- Purpose: Configure and dispatch scraping job
- Primary Data: Platform select, Input target, Limits
- KPI/Stats: Credit Estimate
- Actions: Confirm (Primary), Cancel (Secondary)
- Confirmation: Medium Risk (deducts reserve)

**[CUS-RUN-DETAIL] Run Detail**
- Actors: Customer
- Purpose: Track specific run progress
- Primary Data: Run ID, Status, Start/End, Collected items, Errors (safe)
- Detail Navigation: Links to Result Detail, Export
- Actions: Cancel Run (Danger Confirmation)

**[OPS-ERROR-LIST] Error Center**
- Actors: Operator, Admin
- Purpose: Triage system failures
- Data: Error counts by category, severity, scraper
- Filters: Scraper, Date, Severity, Retryability
- Actions: Bulk Retry (Confirmation), View Detail

**[RES-ACCOUNT-EDIT] Edit Social Account**
- Actors: Admin
- Purpose: Dedicated Edit Page for important resource
- Rules: NO EDIT MODAL. Dedicated route `/resources/accounts/{id}/edit`. Secrets masked. Unsaved changes warning.

**[FIN-DASHBOARD] Finance Dashboard**
- Actors: Finance, Owner
- Primary Data: Revenue vs Internal Cost, Gross Profit, Margins.

**[SYS-ROLE-EDIT] Edit Role**
- Rules: Requires Re-auth / MFA indication.
""")

write_section("""
==================================================
5. GLOBAL UI STATE CONTRACT
==================================================
- **INITIAL PAGE LOAD**: App shell renders immediately. Content area shows Skeleton loaders. No blank white pages.
- **DATA STATES**: `loading`, `loaded` (data grid), `empty` (illustration + CTA), `error` (Try Again CTA), `partial/degraded` (warning banner).
- **ASYNC ACTION**: Buttons show inline spinner, disable double-clicks.
- **MUTATION**: Standard confirmation modal for updates. Toast (Success/Error) upon completion.
- **DELETE / DESTRUCTIVE**: Danger confirmation modal requiring explicit action.
- **CRITICAL**: Requires typed confirmation (e.g., typing "DELETE") and triggers re-auth/MFA if configured for the role.
""")

write_section("""
==================================================
6. CRUD UX STANDARD
==================================================
**Rule: Main/important records NEVER use Edit Modals.**
Flow: List -> Detail -> Dedicated Edit Page (`/edit`) -> Save -> Confirmation Modal -> Processing -> Success Toast -> Redirect to Detail/List.
Quick Edit Modal is heavily restricted to non-critical toggles (e.g., activating/deactivating a notification rule).
All state changes enforce loading indicators and toast policies.
""")

write_section("""
==================================================
7. AUTHENTICATION / SECURITY SCREENS
==================================================
Screens:
- AUTH-LOGIN, AUTH-REGISTER, AUTH-GOOGLE, AUTH-PENDING-APPROVAL
- AUTH-FORGOT-PWD, AUTH-OTP-CHANNEL, AUTH-OTP-VERIFY (6-digit, 5m expiry, max 3 req/day, max 5 verify attempts)
- AUTH-NEW-PWD, AUTH-MFA-CHALLENGE, AUTH-SUSPENDED
- SEC-SESSION-MGMT, SEC-LOGIN-HISTORY, SEC-WA-VERIFY, SEC-TG-PAIR, SEC-SETTINGS
""")

write_section("""
==================================================
8. CUSTOMER PORTAL
==================================================
Screens mapped to Organization Ownership:
- CUS-DASHBOARD: Overview of usage and active runs.
- CUS-SCRAPE-NEW, CUS-RUN-LIST, CUS-RUN-DETAIL: Request creation and tracking.
- CUS-RESULT-EXPLORER, CUS-RESULT-DETAIL, CUS-EXPORT-LIST.
- CUS-API-KEYS, CUS-API-LOGS, CUS-WEBHOOKS.
- CUS-USAGE, CUS-SUBSCRIPTION, CUS-PACKAGE, CUS-INVOICE, CUS-PAYMENT.
- CUS-ORG-MEMBERS, CUS-ORG-INVITES, CUS-SECURITY.
""")

write_section("""
==================================================
9. SCRAPING REQUEST UX
==================================================
Flow:
1. Select Platform (e.g., Instagram, Facebook, News).
2. Select Capability (e.g., Reels, Comments - explicitly separated).
3. Input Target (URL, Hashtag, Username).
4. Configure Limits (Max items).
5. Validation & Credit Estimate (Pre-flight).
6. Confirm & Create Run.
7. Immediate redirect to CUS-RUN-DETAIL (UI does not block waiting for scraping completion).
""")

write_section("""
==================================================
10. RUN UX & 11. RESULT UX
==================================================
**Run Detail**: Displays Platform, Scraper, Status (queued, running, completed, partial, failed, cancelled), Reserved Credit, Actual Credit, Progress bar, Safe errors.
**Result Explorer**: Filters (Platform, Date), Pagination, Content Preview. Cross-tenant access is physically impossible as reads are filtered via Organization Lineage (`run_results`).
""")

write_section("""
==================================================
12, 13, 14, 15, 16, 17. ADMIN / OPS / RESOURCES
==================================================
- **Admin**: ADM-USERS, ADM-PACKAGES, ADM-SUBSCRIPTIONS.
- **Operations & Maintenance**: OPS-MAINTENANCE (Capability-level statuses: Normal, Limited, Degraded, Maintenance, Unavailable. Example: Facebook Comments vs Instagram Reels). Affects Run queueing transparently.
- **Error Center**: OPS-ERROR-LIST, OPS-ERROR-DETAIL. Identifies severity, retryability, attempts. No exposed secrets.
- **Resource Management**: RES-SOC-ACCOUNTS, RES-ACC-POOLS, RES-PROXIES, RES-PRX-POOLS. Operational info: lease status, health, concurrency. Credentials masked.
- **Selector Management**: RES-SELECTOR-LIST, RES-SELECTOR-EDIT (Draft -> Preview -> Activate -> Confirm -> Audit).
- **Queue & Worker**: OPS-QUEUE-WORKER. Dedicated visibility into FB, IG, TikTok, YT, News, Export, Notification queues.
""")

write_section("""
==================================================
18. FINANCE / OWNER
==================================================
Screens: FIN-DASHBOARD, FIN-REVENUE, FIN-COSTS, FIN-PROFIT, FIN-PAYMENTS, FIN-REFUNDS, FIN-LEDGER.
Strict separation of *Harga ke User* (Credit value) vs *Biaya Sebenarnya* (Proxy/Compute cost). Maker-checker workflows applied to FIN-REFUNDS.
""")

write_section("""
==================================================
19. NOTIFICATION & 20. INTERNAL AI ASSISTANT
==================================================
- **Notification**: NOT-PROVIDERS (WhatsApp Evolution API instances, TG, Email), NOT-TEMPLATES, NOT-LOGS.
- **AI Assistant**: AI-CHAT (Internal only via Web/WA/TG), AI-USAGE, AI-AUDIT. Follows explicit RBAC permissions. Read-only v1. No Customer access.
""")

write_section("""
==================================================
21. RBAC / SECURITY / AUDIT & 22. BRANDING
==================================================
- **Security**: SEC-ROLES, SEC-PERMS, SEC-TEMP-ACCESS (Support access), SEC-ELEVATION (JIT), SEC-AUDIT-LOG.
- **Branding**: SYS-BRANDING, SYS-SETTINGS. Secrets masked.
""")

write_section("""
==================================================
23. RESPONSIVE BEHAVIOR
==================================================
- 375px (Mobile): Bottom sheet modals, stacked cards instead of tables, hidden sidebar (hamburger menu).
- 768px (Tablet): Collapsed sidebar, horizontal scroll on wide tables.
- 1024px (Laptop): Full sidebar, standard tables, 2-column detail layouts.
- 1440px (Desktop): 3-column layouts where applicable, max-width containers to prevent horizontal stretch. No horizontal page overflow.
""")

write_section("""
==================================================
24. STITCH DESIGN BATCHES
==================================================
**Batch 01 — Design System + App Shell**: SYS-UI-KIT, SYS-SHELL-PUB, SYS-SHELL-CUS, SYS-SHELL-INT
**Batch 02 — Auth**: AUTH-LOGIN, AUTH-REGISTER, AUTH-OTP, AUTH-MFA
**Batch 03 — Customer Dashboard**: CUS-DASHBOARD
**Batch 04 — Scraping Request**: CUS-SCRAPE-NEW, CUS-SCRAPE-CONFIRM
**Batch 05 — Runs + Results**: CUS-RUN-LIST, CUS-RUN-DETAIL, CUS-RESULT-EXPLORER, CUS-RESULT-DETAIL, CUS-EXPORT-LIST
**Batch 06 — API + Usage + Billing**: CUS-API-KEYS, CUS-WEBHOOKS, CUS-USAGE, CUS-BILLING
**Batch 07 — Organization + Members**: CUS-ORG-MEMBERS, CUS-ORG-SETTINGS
**Batch 08 — Admin User/Package**: ADM-USERS, ADM-PACKAGES, ADM-SUBS
**Batch 09 — Scraper + Selector**: RES-SCRAPER-LIST, RES-SELECTOR-LIST, RES-SELECTOR-EDIT
**Batch 10 — Social Account + Proxy**: RES-ACCOUNT-LIST, RES-ACCOUNT-EDIT, RES-PROXY-LIST
**Batch 11 — Monitoring + Error**: OPS-MONITORING, OPS-ERROR-LIST, OPS-ERROR-DETAIL
**Batch 12 — Incident + Maintenance**: OPS-INCIDENT-LIST, OPS-MAINTENANCE
**Batch 13 — Queue + Worker**: OPS-QUEUE-LIST, OPS-WORKER-HEALTH
**Batch 14 — Finance + Owner**: FIN-DASHBOARD, FIN-REVENUE, FIN-COSTS, FIN-REFUNDS
**Batch 15 — Notification**: NOT-PROVIDERS, NOT-LOGS
**Batch 16 — Internal AI**: AI-CHAT, AI-AUDIT
**Batch 17 — Security + RBAC + Audit**: SEC-ROLES, SEC-AUDIT, SEC-TEMP-ACCESS
**Batch 18 — Branding + System Settings**: SYS-BRANDING, SYS-INTEGRATION
**Batch 19 — Responsive + State QA**: All responsive breakpoints and error/empty states.
""")

write_section("""
==================================================
25. MERMAID USER FLOWS
==================================================
### 1. Authentication Flow
```mermaid
graph TD
    A[Login Page] --> B{Valid Credentials?}
    B -->|Yes| C{MFA Required?}
    B -->|No| D[Show Error]
    C -->|Yes| E[MFA Challenge]
    C -->|No| F[Redirect to Dashboard]
    E --> G{OTP Valid?}
    G -->|Yes| F
    G -->|No| E
```

### 2. Customer Scraping Request Flow
```mermaid
graph TD
    A[Select Scraper] --> B[Input Target & Config]
    B --> C[Validate & Estimate Credit]
    C --> D[Confirmation Modal]
    D --> E[Create Run ID]
    E --> F[Redirect to Run Detail]
```

### 3. Run → Result → Export Flow
```mermaid
graph TD
    A[Run Detail] --> B{Status?}
    B -->|Completed/Partial| C[View Results]
    B -->|Running| A
    C --> D[Result Explorer]
    D --> E[Select Export Format]
    E --> F[Async Export Generation]
    F --> G[Download Link Generated]
```

### 4. Admin Error Investigation Flow
```mermaid
graph TD
    A[Error Center] --> B[Filter by Scraper/Severity]
    B --> C[Select Error]
    C --> D[View Error Detail & Fingerprint]
    D --> E[View Affected Runs/Resources]
    E --> F[Execute Bulk Retry if Retryable]
```

### 5. Maintenance Flow
```mermaid
graph TD
    A[Admin selects Scraper] --> B[Trigger Maintenance Mode]
    B --> C[Danger Confirmation]
    C --> D[New Runs Blocked & Alert Displayed]
    D --> E[Existing Runs Finish Gracefully]
    E --> F[Admin Resolves Maintenance]
```

### 6. Finance Refund Approval Flow (Maker-Checker)
```mermaid
graph TD
    A[Finance Ops] --> B[Draft Refund Request]
    B --> C{Above Threshold?}
    C -->|No| D[Auto Approve & Execute]
    C -->|Yes| E[Pending Approval]
    E --> F[Owner Reviews]
    F -->|Approve| D
    F -->|Reject| G[Request Cancelled]
```

### 7. Internal AI Access Flow
```mermaid
graph TD
    A[Web/WA/TG] --> B{Is Actor Internal?}
    B -->|Yes| C{Has ai.use Permission?}
    B -->|No| D[Access Denied]
    C -->|Yes| E[Query AI Tool Gateway]
    C -->|No| D
    E --> F[Read-only System State Returned]
```

### 8. Support Temporary-Access Flow
```mermaid
graph TD
    A[Support Agent] --> B[Request Temporary Tenant Access]
    B --> C[Provide Ticket/Reason]
    C --> D[Audit Log Created]
    D --> E[Read-only Access Granted with Time Limit]
    E --> F[Auto Expire Access]
```
""")

write_section("""
==================================================
26. FINAL UI COVERAGE MATRIX
==================================================
| PRD Domain | Screen IDs | Roles | Status |
|---|---|---|---|
| Auth & Security | AUTH-*, SEC-* | All | Mapped |
| Dashboard & Run | CUS-DASHBOARD, CUS-RUN-* | Customer | Mapped |
| Result & Export | CUS-RESULT-*, CUS-EXPORT-* | Customer | Mapped |
| API & Billing | CUS-API-*, CUS-BILLING, CUS-USAGE | Customer | Mapped |
| User & Package Admin | ADM-USERS, ADM-PACKAGES | Admin | Mapped |
| Scraper & Resources | RES-SCRAPER-*, RES-PROXY-* | Admin/Ops | Mapped |
| Ops & Monitoring | OPS-MONITORING, OPS-ERROR-* | Ops | Mapped |
| Maintenance & Queue | OPS-MAINTENANCE, OPS-QUEUE-* | Ops/Admin | Mapped |
| Finance | FIN-* | Finance/Owner | Mapped |
| Notification & AI | NOT-*, AI-* | Ops/Owner | Mapped |
""")

write_section("""
==================================================
27. OPEN UI DECISIONS
==================================================
1. **Empty State Illustrations vs Plain Text (UX Decision)**
   - *Recommendation*: Use lightweight SVG illustrations for primary empty states (e.g., "No Runs Yet") to improve customer onboarding UX.
2. **Date Range Selection Standard (UX Decision)**
   - *Recommendation*: Default to "Last 7 Days" on dashboards, using a standard standardized DatePicker component across Customer and Admin views.
3. **Dark Mode Support (Product/UX Decision)**
   - *Recommendation*: Implement CSS variables for themes from Batch 01, but launch MVP in Light Mode to accelerate delivery, enabling Dark Mode post-MVP.
""")

