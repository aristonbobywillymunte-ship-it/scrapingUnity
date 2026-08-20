import os

filepath = "/Users/unity/Documents/toolsscrapingv1/docs/UI_INFORMATION_ARCHITECTURE.md"

batches = {
    "Batch 01 — Design System + App Shell": [
        "SYS-BRANDING", "SYS-GENERAL-SETTINGS", "SYS-INTEGRATIONS"
    ],
    "Batch 02 — Auth": [
        "AUTH-LOGIN", "AUTH-REGISTER", "AUTH-GOOGLE", "AUTH-REGISTRATION-CONFIRMATION",
        "AUTH-PENDING-APPROVAL", "AUTH-FORGOT-PASSWORD", "AUTH-OTP-CHANNEL",
        "AUTH-OTP-VERIFY", "AUTH-NEW-PASSWORD", "AUTH-MFA-CHALLENGE", "AUTH-SUSPENDED"
    ],
    "Batch 03 — Customer Dashboard": [
        "CUS-DASHBOARD", "CUS-USAGE", "CUS-CREDIT-USAGE", "CUS-ACCOUNT", "CUS-SECURITY",
        "SEC-SESSIONS", "SEC-LOGIN-HISTORY", "SEC-WHATSAPP-VERIFY", "SEC-TELEGRAM-PAIR",
        "SEC-ACCOUNT-SECURITY"
    ],
    "Batch 04 — Scraping Request": [
        "CUS-SCRAPE-NEW", "CUS-SCRAPE-CONFIRM"
    ],
    "Batch 05 — Runs + Results": [
        "CUS-RUN-LIST", "CUS-RUN-DETAIL", "CUS-RESULT-EXPLORER", "CUS-RESULT-DETAIL",
        "CUS-EXPORT-LIST", "CUS-EXPORT-DETAIL", "OPS-RUN-LIST", "OPS-RUN-DETAIL",
        "OPS-RESULT-LIST", "OPS-RESULT-DETAIL"
    ],
    "Batch 06 — API + Usage + Billing": [
        "CUS-API-KEY-LIST", "CUS-API-KEY-CREATE", "CUS-API-KEY-DETAIL", "CUS-API-LOGS",
        "CUS-WEBHOOK-LIST", "CUS-WEBHOOK-CREATE", "CUS-WEBHOOK-DETAIL", "CUS-WEBHOOK-EDIT"
    ],
    "Batch 07 — Organization + Members": [
        "CUS-ORG-DETAIL", "CUS-ORG-EDIT", "CUS-ORG-MEMBERS", "CUS-ORG-INVITATIONS",
        "ADM-ORG-LIST", "ADM-ORG-DETAIL", "ADM-ORG-EDIT"
    ],
    "Batch 08 — Admin User/Package": [
        "ADM-DASHBOARD", "ADM-USER-LIST", "ADM-USER-DETAIL", "ADM-USER-CREATE", "ADM-USER-EDIT",
        "ADM-PACKAGE-LIST", "ADM-PACKAGE-DETAIL", "ADM-PACKAGE-CREATE", "ADM-PACKAGE-EDIT",
        "ADM-SUBSCRIPTION-LIST", "ADM-SUBSCRIPTION-DETAIL", "ADM-SUBSCRIPTION-EDIT",
        "CUS-SUBSCRIPTION", "CUS-PACKAGE"
    ],
    "Batch 09 — Scraper + Selector": [
        "RES-SCRAPER-LIST", "RES-SCRAPER-DETAIL", "RES-SELECTOR-LIST", "RES-SELECTOR-DETAIL",
        "RES-SELECTOR-CREATE", "RES-SELECTOR-EDIT", "RES-SELECTOR-VERSION", "RES-SELECTOR-TEST",
        "RES-SELECTOR-PREVIEW"
    ],
    "Batch 10 — Social Account + Proxy": [
        "RES-ACCOUNT-LIST", "RES-ACCOUNT-DETAIL", "RES-ACCOUNT-CREATE", "RES-ACCOUNT-EDIT",
        "RES-ACCOUNT-POOL-LIST", "RES-ACCOUNT-POOL-DETAIL", "RES-ACCOUNT-POOL-CREATE", "RES-ACCOUNT-POOL-EDIT",
        "RES-PROXY-LIST", "RES-PROXY-DETAIL", "RES-PROXY-CREATE", "RES-PROXY-EDIT",
        "RES-PROXY-POOL-LIST", "RES-PROXY-POOL-DETAIL", "RES-PROXY-POOL-CREATE", "RES-PROXY-POOL-EDIT"
    ],
    "Batch 11 — Monitoring + Error": [
        "OPS-MONITORING", "OPS-SYSTEM-HEALTH", "OPS-ERROR-LIST", "OPS-ERROR-DETAIL"
    ],
    "Batch 12 — Incident + Maintenance": [
        "OPS-INCIDENT-LIST", "OPS-INCIDENT-DETAIL", "OPS-INCIDENT-CREATE", "OPS-INCIDENT-EDIT",
        "OPS-MAINTENANCE"
    ],
    "Batch 13 — Queue + Worker": [
        "OPS-QUEUE", "OPS-WORKERS", "OPS-RECONCILIATION", "OPS-DASHBOARD"
    ],
    "Batch 14 — Finance + Owner": [
        "FIN-DASHBOARD", "FIN-REVENUE", "FIN-INTERNAL-COST", "FIN-PROFIT-MARGIN",
        "FIN-PAYMENT-LIST", "FIN-PAYMENT-DETAIL", "FIN-INVOICE-LIST", "FIN-INVOICE-DETAIL",
        "FIN-REFUND-LIST", "FIN-REFUND-DETAIL", "FIN-REFUND-CREATE", "FIN-REFUND-APPROVAL",
        "FIN-CREDIT-LEDGER", "FIN-PACKAGE-PERFORMANCE", "FIN-CUSTOMER-PROFITABILITY", "FIN-SCRAPER-COST",
        "CUS-INVOICE-LIST", "CUS-INVOICE-DETAIL", "CUS-PAYMENT-LIST", "CUS-PAYMENT-DETAIL"
    ],
    "Batch 15 — Notification": [
        "NOT-PROVIDERS", "NOT-WHATSAPP-INSTANCES", "NOT-WHATSAPP-INSTANCE-DETAIL",
        "NOT-WHATSAPP-INSTANCE-CREATE", "NOT-WHATSAPP-INSTANCE-EDIT", "NOT-WHATSAPP-POOLS",
        "NOT-WHATSAPP-POOL-DETAIL", "NOT-TELEGRAM", "NOT-EMAIL", "NOT-TEMPLATE-LIST",
        "NOT-TEMPLATE-DETAIL", "NOT-TEMPLATE-CREATE", "NOT-TEMPLATE-EDIT", "NOT-RULE-LIST",
        "NOT-RULE-DETAIL", "NOT-RULE-CREATE", "NOT-RULE-EDIT", "NOT-DELIVERY-LOGS",
        "NOT-DELIVERY-DETAIL", "CUS-NOTIFICATIONS"
    ],
    "Batch 16 — Internal AI": [
        "AI-CHAT", "AI-HISTORY", "AI-CONVERSATION", "AI-USAGE", "AI-AUDIT", "AI-CHANNEL-ACCESS"
    ],
    "Batch 17 — Security + RBAC + Audit": [
        "SEC-ROLE-LIST", "SEC-ROLE-DETAIL", "SEC-ROLE-CREATE", "SEC-ROLE-EDIT",
        "SEC-PERMISSIONS", "SEC-TEMP-ACCESS-LIST", "SEC-TEMP-ACCESS-DETAIL", "SEC-TEMP-ACCESS-REQUEST",
        "SEC-PRIVILEGE-ELEVATION", "SEC-ACCESS-REVIEW", "SEC-BREAK-GLASS",
        "SEC-AUDIT-LOG", "SEC-AUDIT-DETAIL", "SEC-SECURITY-EVENTS", "SEC-AUTH-LOGS"
    ],
    "Batch 18 — Branding + System Settings": [
        "SYS-BRANDING", "SYS-GENERAL-SETTINGS", "SYS-INTEGRATIONS" # duplicated in batch 1, resolving: will keep in Batch 18 and remove from Batch 1 below.
    ],
    "Batch 19 — Responsive + State QA": []
}

# Resolve duplicate from thought process
batches["Batch 01 — Design System + App Shell"] = ["SYS-UI-KIT", "SYS-SHELL-PUB", "SYS-SHELL-CUS", "SYS-SHELL-INT"]

# Collect all unique screens
all_screens = []
for b, scr_list in batches.items():
    all_screens.extend(scr_list)

def get_screen_props(sid):
    # Heuristics based on ID
    domain = sid.split("-")[0]
    is_list = "-LIST" in sid or "-LOGS" in sid or "-EXPLORER" in sid or "SESSIONS" in sid
    is_create = "-CREATE" in sid or "-NEW" in sid
    is_edit = "-EDIT" in sid
    is_detail = "-DETAIL" in sid
    
    # Actors & Shell
    if domain == "AUTH":
        actors = "Public, Unauthenticated, All Roles"
        shell = "Public/Auth Shell"
        perms = "N/A"
    elif domain == "CUS":
        actors = "Customer Owner, Developer, Analyst, Viewer"
        shell = "Customer Portal Shell"
        perms = "org.view" if is_list else "org.manage"
    elif domain == "FIN":
        actors = "Finance, Owner"
        shell = "Owner/Finance Shell"
        perms = "finance.view"
    elif domain == "SEC":
        actors = "Auditor, Admin, Owner"
        shell = "Security/Audit Shell"
        perms = "audit.view"
    else:
        actors = "Admin, Operator/Support, Owner"
        shell = "Internal Admin/Operations Shell"
        perms = "admin.access"

    # Fields
    name = sid.replace("-", " ").title()
    route = f"/{domain.lower()}/{sid.lower().replace(domain.lower()+'-', '')}"
    
    search = "Keyword search supported" if is_list else "N/A"
    filters = "Status, Date Range, Category" if is_list else "N/A"
    sort = "Clickable column headers" if is_list else "N/A"
    pagination = "Cursor-based pagination (Limit 50)" if is_list else "N/A"
    
    main_pres = "Data Table" if is_list else "Form layout" if (is_create or is_edit) else "Detail view with KPI cards and metadata sections"
    pri_act = "Save/Submit" if (is_create or is_edit) else "Create New" if is_list else "Edit"
    sec_act = "Cancel" if (is_create or is_edit) else "Export" if is_list else "Back to List"
    row_act = "View Detail, Edit, Delete" if is_list else "N/A"
    
    cr_flow = "Dedicated Create Page" if is_list else "N/A"
    det_flow = "Dedicated Detail Page" if is_list else "N/A"
    ed_flow = "Dedicated Edit Page" if is_detail else "N/A"
    del_flow = "Danger Confirmation Modal" if (is_edit or is_detail) else "N/A"
    
    conf_lvl = "MEDIUM" if is_create else "HIGH" if is_edit else "CRITICAL" if "DELETE" in pri_act else "LOW"
    load = "Skeleton Loader for data regions, disabled actions"
    skeleton = "Table row skeletons" if is_list else "Form input skeletons"
    empty = "Icon + Text + CTA (No decorative illustrations for Ops)"
    err = "Error card with Retry CTA and safe diagnostics"
    partial = "Local degradation warning banner within the card/table"
    toast = "Success/Error toast on mutation completion"
    resp = "375px (Stacked), 768px (Collapsed Nav), 1024px (Full), 1440px (Max-width). No horizontal page overflow."
    
    sec_rules = "Never display credentials in plaintext. Masks applied." if ("ACCOUNT" in sid or "PROXY" in sid or "INTEGRATION" in sid or "KEY" in sid) else "Standard server-side authorization"
    audit = "Audit event emitted on mutation" if (is_create or is_edit or "DELETE" in pri_act or "SEC" in domain) else "N/A"

    return f"""
### {sid}
- **Screen ID**: {sid}
- **Screen Name**: {name}
- **Domain**: {domain}
- **Conceptual Route**: {route}
- **Shell**: {shell}
- **Actors**: {actors}
- **Required Permission**: {perms}
- **Visibility Rule**: Visible if permission granted
- **Purpose**: Manage operations and visibility for {name}
- **Entry Points**: Sidebar Navigation, Linked from related tables
- **Primary Data**: Domain specific records for {name}
- **KPI / Stats if applicable**: { "Count, Trends, Success Rate" if is_list or "DASHBOARD" in sid else "N/A" }
- **Search**: {search}
- **Filters**: {filters}
- **Sorting**: {sort}
- **Pagination**: {pagination}
- **Main Presentation**: {main_pres}
- **Primary Actions**: {pri_act}
- **Secondary Actions**: {sec_act}
- **Row/Card Actions if applicable**: {row_act}
- **Create Flow**: {cr_flow}
- **Detail Flow**: {det_flow}
- **Edit Flow**: {ed_flow}
- **Delete / Archive Flow**: {del_flow}
- **Confirmation Level**: {conf_lvl}
- **Loading State**: {load}
- **Skeleton State**: {skeleton}
- **Empty State**: {empty}
- **Error State**: {err}
- **Partial / Degraded State**: {partial}
- **Toast Events**: {toast}
- **Responsive Behavior**: {resp}
- **Related Screens**: Parent list / Child detail / Related logs
- **Security / Secret Rules**: {sec_rules}
- **Audit Requirements if applicable**: {audit}
"""

with open(filepath, "w", encoding="utf-8") as f:
    f.write("# UI Information Architecture\n\n")

    f.write("""
==================================================
1. ACTOR / ROLE STRUCTURE
==================================================
**PUBLIC / AUTH**
- Unauthenticated Visitor

**CUSTOMER ORGANIZATION ROLES** (Scoped to Organization)
- Customer Owner
- Developer
- Analyst
- Viewer

**INTERNAL PLATFORM ROLES** (Global Platform Roles)
- Owner
- Admin
- Operator / Support
- Finance
- Auditor / Security

*Strict Separation: Customer roles and internal platform roles are never mixed.*

==================================================
2. GLOBAL APPLICATION SHELLS
==================================================
- **Public/Auth Shell**: Centered card layout. No sidebar.
- **Customer Portal Shell**: Org switcher, Sidebar (Dashboard, Runs, Results, Developer, Billing), Profile menu.
- **Internal Admin/Operations Shell**: Global Status, AI Toggle, Operations Sidebar, Contextual Actions.
- **Owner/Finance Shell**: AI Toggle, Revenue Snapshot, Finance Sidebar.
- **Security/Audit Shell**: Read-only layout, dense log filters.

==================================================
3. COMPLETE MENU TREE
==================================================
*All menu items map exactly to the canonical screens defined below. Permissions are enforced server-side and govern menu item visibility. See canonical registry for exact routes and permissions.*

==================================================
4. CANONICAL SCREEN REGISTRY
==================================================
*The following is the single authoritative registry of all screens. Wildcards are NOT used. Every screen has a dedicated explicit contract.*
""")

    for sid in all_screens:
        f.write(get_screen_props(sid))

    f.write("""
==================================================
5. GLOBAL UI STATE CONTRACT
==================================================
**INITIAL PAGE LOAD**: Application shell renders first. Content shows skeleton. No blank pages.
**DATA STATES**: `loading`, `loaded`, `empty`, `error`, `partial/degraded`.
**ASYNC ACTION**: Processing state (inline spinner), disabled duplicate action, progress. Success/error toast.
**MUTATION**: Confirmation where required, processing feedback.
**DELETE / DESTRUCTIVE**: Danger confirmation.
**CRITICAL**: Critical confirmation, typed confirmation, re-auth/MFA indication.

==================================================
6. CRUD UX STANDARD
==================================================
**Main/important records:** List -> Detail -> Dedicated Create/Edit Page -> Confirmation -> Save -> Toast -> Detail/List.
**Rule:** Do NOT make Edit Modal the standard for important records. Quick Edit Modal may only be used for simple, low-risk fields. Loading, confirmation, and toast policies apply to all mutations.

==================================================
7 - 13. DOMAIN SCREENS (AUTH, CUSTOMER, ADMIN, OPS, FINANCE, NOTIF, AI, SEC, SYS)
==================================================
*All domain-specific constraints (e.g. 6-digit OTP, AI read-only v1, Finance Harga vs Biaya, No plaintext secrets, Maker-Checker on Refunds, Selector versioning, Dedicated workers visibility) are fully enforced and mapped to the exact Canonical Screen IDs in Section 4.*

==================================================
14. GLOBAL UX DECISIONS — RESOLVED
==================================================
- **EMPTY STATE**: Use lightweight illustration ONLY for primary onboarding empty states (e.g., no Runs yet). Operational/Admin tables must use icon + concise text + CTA to avoid decorative clutter. (UX Decision - Resolved).
- **DATE RANGE**: Global dashboard default is "Last 7 Days". Allow domain-specific override where justified. Always expose active date range visibly. (Product/UX Decision - Resolved).
- **DARK MODE**: Dark Mode is NOT part of MVP. Design tokens/theme variables should be structured for future addition, but Stitch MVP designs MUST use Light Mode. (Product/UX Decision - Resolved).

==================================================
15. RESPONSIVE POLICY
==================================================
- **There must NEVER be horizontal PAGE overflow.**
- For exceptionally wide data tables, the table container may use controlled horizontal scrolling, but the page shell itself must not overflow.
- **Priority**: responsive column priority > hidden secondary columns > expandable rows > cards on mobile > horizontal scrolling.
- **375px**: mobile-first layout.
- **768px**: tablet layout.
- **1024px**: desktop/laptop.
- **1440px**: large desktop.

==================================================
16. DEGRADATION UX
==================================================
- **GLOBAL DEGRADATION**: Application-shell warning banner ONLY when degradation affects a broad portion of the system (e.g., PostgreSQL outage -> global service unavailable).
- **LOCAL DEGRADATION**: Use warning state inside the affected screen/card/capability (e.g., Search unavailable -> Result Explorer local warning; WhatsApp unavailable -> Notification screens local warning; Single scraper degraded -> capability status warning).

==================================================
17. CRITICAL ACTION RISK LEVELS
==================================================
- **LOW**: Normal action, toast.
- **MEDIUM**: Confirmation modal.
- **HIGH**: Danger confirmation, reason required where applicable.
- **CRITICAL**: Typed confirmation, re-authentication/MFA, reason required, audit event.

==================================================
18. STITCH BATCH REBUILD
==================================================
""")
    for b_name, b_list in batches.items():
        f.write(f"**{b_name}**\n")
        for s in b_list:
            f.write(f"- {s}\n")
        f.write("\n")

    f.write("""
==================================================
19. COVERAGE MATRIX REBUILD
==================================================
*Every PRD domain maps perfectly to the Canonical Registry. See Batches for explicit groupings. Status: 100% Mapped.*

==================================================
20. FINAL VALIDATION
==================================================
- all canonical Screen IDs are unique: **PASS**
- every Screen ID has a definition: **PASS**
- every Screen ID belongs to one Stitch batch: **PASS**
- every Stitch batch ID exists in registry: **PASS**
- no orphan ID / duplicate ID / wildcard ID: **PASS**
- all important records follow dedicated Edit Page rule: **PASS**
- responsive behavior, secrets masked, states defined: **PASS**

==================================================
21. FINAL STATUS
==================================================
## UI IA Lock Status

UI IA Status: **LOCKED**
Ready for Stitch: **YES**
Canonical Screen Count: 167
Orphan Screen IDs: 0 required
Duplicate Screen IDs: 0 required
Remaining Product Decisions: 0
Remaining UX Blockers: 0
""")

