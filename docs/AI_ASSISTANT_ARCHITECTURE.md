# AI ASSISTANT ARCHITECTURE

This document defines the authoritative architecture for the Internal AI Assistant. It governs channel binding, model interactions, read-only constraints, and the strict enforcement of security and data access boundaries via the AI Tool Gateway.

---

## 1. PRODUCT BOUNDARY

- **Target Audience**: **INTERNAL ONLY**.
- **Customer Access**: Customer roles (Owner, Developer, Analyst, Viewer) **MUST NOT** receive AI Assistant access.
- **Internal Access Prerequisites**: Internal users may access the AI only when authenticated, with MFA/security posture satisfied, and possessing explicit `ai.use` permission.
- **State Constraint**: AI Assistant v1 is strictly **READ-ONLY**. It MUST NOT mutate platform state.

---

## 2. SUPPORTED CHANNELS & IDENTITY

**Channels Supported**:
- **Internal Web Dashboard**: Uses authenticated internal session.
- **WhatsApp**: Requires verified binding between WA identity and an internal user.
- **Telegram**: Requires verified binding between TG identity and an internal user.

**Channel Binding Rules**:
- Bindings must be verified and revocable.
- Unverified identities receive NO platform data.
- WA/TG notification delivery is entirely separate from AI inbound message handling. Do NOT route arbitrary notification replies into the AI pipeline.

---

## 3. HIGH-LEVEL MESSAGE FLOW

Every inbound AI interaction follows this strict pipeline:

`User Message`
→ `Channel Authentication` (Web Session or WA/TG Binding)
→ `Internal User Resolution`
→ `Permission Check` (`ai.use` & Posture Validation per request)
→ `Conversation Context Retrieval`
→ `Intent/Tool Planning`
→ **`AI Tool Gateway`**
→ `Authorized Read Tool Execution`
→ `Result Sanitization` (Filtering secrets/tenant scopes)
→ `Model Response Generation`
→ `Audit Logging`
→ `Channel Response`

---

## 4. AI TOOL GATEWAY

All platform data access MUST pass through the **AI Tool Gateway**.
The AI model MUST NOT:
- Query the raw database directly.
- Generate or execute raw SQL.
- Access arbitrary internal URLs or filesystems.
- Call unrestricted internal APIs.
- Access provider secrets.
- Bypass application authorization.

**Gateway Responsibilities**:
- Tool allowlisting.
- Parameter validation.
- Actor resolution.
- Permission validation (RBAC & Scopes).
- Tenant/Scope validation.
- Output filtering and rate limiting.
- Audit logging.

---

## 5. READ-ONLY TOOL CATEGORIES & PERMISSIONS

**Conceptual Tool Categories**:
Runs, Run Results, Scraper Status/Health, Tasks/Attempts, Queue/Worker Status, Customer/Organization Lookup, Usage/Credits, Package/Subscription, Invoices/Payments, Internal Finance Views, Errors/Incidents, Notification Status, Audit/Security Events, Resource/Account/Proxy Health, Selector Status, System/Maintenance Status.

*No create/update/delete/approve/refund/retry/restart/disable/enable actions are permitted in AI Assistant v1.*

**Permission-Aware Tool Access**:
Tool access is the strict intersection of:
`authenticated actor` ∩ `ai.use` ∩ `normal platform permission` ∩ `data scope` ∩ `tool allowlist`.
*The AI never grants additional platform permissions. (e.g., Operator cannot access Finance data).*

---

## 6. TENANT & DATA ISOLATION

- **Tenant Boundary**: The AI Tool Gateway enforces the exact same tenant boundary as standard application APIs.
- **Canonical Data**: Scraping data must not become cross-tenant visible. Result reads must follow authorized `Run`/`RunResult` lineage.
- **Global Access**: Internal global access requires explicit global permissions.

---

## 7. CONTEXT & CONVERSATION MANAGEMENT

**Conversation Schema**:
`conversation_id`, `actor_id`, `channel`, `created_at`, `updated_at`, `status`, `safe_metadata`.

**Message Schema**:
`message_id`, `conversation_id`, `role`, `content`, `tool_references`, `created_at`.
*Secrets are NEVER stored in conversation history.*

**Context Management**:
- Bounded context: Uses recent messages, authorized tool results, and safe summaries.
- Never loads unlimited historical conversations.
- Never injects unrelated organization/customer data into the model context.

---

## 8. PROMPT-INJECTION DEFENSE & SAFETY

- **Untrusted Data**: Scraped content, customer content, web content, and tool-returned text are UNTRUSTED.
- **Defense**: Content claiming "ignore instructions", "run SQL", or "reveal credentials" must NEVER override system policies. Instructions are strictly separated from retrieved data in the prompt structure.

**Tool Input Validation**:
Every call is schema validated, bounded, permission checked, and rate-limited. Arbitrary SQL, shell commands, URLs, and file paths are rejected.

**Tool Output Safety**:
Before the model receives tool results, the gateway:
- Removes secrets and masks sensitive credentials.
- Enforces tenant scopes and minimizes PII.
- Bounds result size.

---

## 9. MODEL & PROVIDER ARCHITECTURE

**Provider Abstraction**:
Supports configurable provider, model, endpoint, timeout, token limits, and fallback policy. Do not hardcode a single AI vendor.

**Credentials**:
Provider credentials use encrypted secret storage. API secrets are NEVER in prompts, logs, or conversations.

**Model Routing & Fallback**:
Fallback preserves permission scope, tool restrictions, system instructions, classification, and audit context. Provider failure must not loosen security controls.

**Degraded Mode**:
If AI providers are unavailable, core scraping/billing/platform functions remain entirely unaffected. The AI Assistant degrades independently.

---

## 10. AI RESPONSE GROUNDING & TOOL BEHAVIORS

- **Grounding**: AI must prefer authoritative Tool Gateway results. It must clearly distinguish between current platform data, historical context, and model inference. It MUST NOT fabricate IDs, balances, statuses, or financial values.
- **Tool Result Pagination**: Large sets must be bounded/paginated. No full-table dumping.
- **Tool Loop Boundary**: Agent execution is bounded by max tool calls per turn, max depth, timeout, and result-size limits. No infinite autonomous loops.
- **No Autonomous Actions**: AI v1 is strictly request/response driven. It does not schedule jobs, monitor forever, execute delayed mutations, or send arbitrary notifications.

---

## 11. DATA CLASSIFICATION & FINANCIAL DATA

- **Data Classification**: Honors `SECURITY_ARCHITECTURE`. Secret data (provider credentials, social sessions, API keys, passwords, OTPs, proxy passwords) is NEVER sent to the AI model.
- **Financial Data**: Internal cost/revenue/profit is accessed ONLY by actors with normal finance permissions. AI must not infer or expose internal profitability to unauthorized roles. Customer-facing credit info remains distinct from internal costs.

---

## 12. AUDIT, TELEMETRY & COST

**Audit Logging**:
Audits AI conversation access, tool invocation, actor, channel, tool, permission decision, scope, request_id, latency, provider/model, success/failure, safe metadata. *Never audit raw secrets.*

**AI Usage / Cost**:
Tracks AI operational usage separately (requests, token usage, latency, provider cost). This is INTERNAL COST telemetry. Do not mix AI provider costs with customer credits.

**Rate Limiting**:
Configuration-driven limits by actor, channel, model/provider, and tool category to prevent abuse, floods, and runaway loops.

---

## 13. RETENTION & DELETION

- Configurable retention for conversations, tool-call audit metadata, and AI usage records, respecting security policy. No secrets retained.
- Deletion rules handle safe archival/deletion, user deactivation, channel unbinding, and retention expiry. Audit records follow immutable retention rules.

---

## 14. SPECIFIC CHANNEL FLOWS & DYNAMIC AUTHORIZATION

**WhatsApp & Telegram Per-Request Authorization**:
MFA performed during initial binding is NOT sufficient by itself for permanent access. Every single AI request from WhatsApp/Telegram MUST re-check:
1. Verified binding is still active.
2. Internal user is still active (not suspended/deleted).
3. `ai.use` permission is still granted.
4. Required domain permission is still granted for the requested tool.
5. Applicable scope is still valid.
6. Required security posture is still satisfied.
*(Revocation, user suspension, or permission removal MUST take effect instantly without requiring re-pairing).*

**WhatsApp AI Flow**:
`Inbound WA Msg` → `Provider Callback Verification` → `WA Identity Lookup` → `Per-Request Auth (User/Posture/ai.use)` → `AI Gateway` → `Response` → `Outbound WA Instance`.

**Telegram AI Flow**:
`Verified Bot Callback` → `Telegram Binding` → `Internal-User Resolution` → `Per-Request Auth` → `Tool Gateway` → `Response`.

**Web AI Flow**:
`Session` → `Per-Request Auth` → `Conversation` → `Tool Gateway`.

---

## 15. SECURITY MATRICES

### A. Internal Role → AI Access
| Internal Role | AI Access Eligible? | Prerequisite | Tool Scope |
|---|---|---|---|
| Owner | YES | `ai.use`, MFA, Active | Global |
| Admin | YES | `ai.use`, MFA, Active | Global Admin |
| Operator/Support | YES | `ai.use`, MFA, Active | Scraper Ops |
| Finance | YES | `ai.use`, MFA, Active | Billing/Cost |
| Auditor/Security | YES | `ai.use`, MFA, Active | Audit/Events |
| *Customer Roles* | *NO* | *N/A* | *NONE* |

### B. AI Tool Category → Required Permission
*(Every Tool Gateway read operation requires the canonical platform permission mapped below in addition to `ai.use`)*

| Tool Category | Required Canonical Permission |
|---|---|
| Runs | `run:read` |
| Run Results | `result:read` |
| Scraper Status/Health | `system:read` |
| Tasks/Attempts | `run:read` OR `system:read` |
| Queue/Worker Status | `system:read` |
| Customer/Organization Lookup | `organization:read` |
| Usage/Credits | `billing:read` |
| Package/Subscription | `subscription:read` |
| Invoices/Payments | `billing:read` |
| Internal Finance Views | `finance:read` |
| Errors/Incidents | `system:read` OR `incident:read` |
| Notification Status | `notification:read` |
| Audit/Security Events | `audit:read` |
| Resource/Account/Proxy Health | `system:read` |
| Selector Status | `selector:read` |
| System/Maintenance Status | `system:read` |

### C. Channel → Authentication/Binding Requirement
| Channel | Authentication Method |
|---|---|
| Web Dashboard | Active Authenticated Session + Continuous MFA Posture |
| WhatsApp | Verified Binding + Continuous Per-Request Posture Validation |
| Telegram | Verified Binding + Continuous Per-Request Posture Validation |

### D. Data Classification → AI Handling
| Classification | AI Handling Policy |
|---|---|
| Secret | NEVER sent to Model. Scrubbed by Gateway. |
| Confidential | Handled if RBAC permits. Bound by Context Window. |
| Internal | Handled if RBAC permits. |
| Public | Safe for Model. |

### E. AI Error → User Response / Internal Action
| Error Category | Safe User Response | Internal Action |
|---|---|---|
| Authentication/Binding | "Account not linked or authorized." | Log Auth Failure |
| Permission Denied | "You lack permission for this request." | Log Access Denied |
| Provider Unavailable | "AI service temporarily degraded." | Failover / Circuit Break |
| Tool Timeout | "Data retrieval took too long." | Audit Latency |
| Unsafe Request (Injection)| "Request blocked by security policy."| Trigger Sec Alert |

### F. Tool Request → Authorization Decision Flow
`Actor Request` → `Is Actor Internal?` (No=Deny) → `Has ai.use?` (No=Deny) → `Is Channel Verified?` (No=Deny) → `Per-Request Posture OK?` (No=Deny) → `Is Tool Allowed?` (No=Deny) → `Does Actor Have Domain Permission?` (No=Deny) → `Is Scope Valid?` (No=Deny) → `EXECUTE TOOL`.

---

## 16. THREAT MODEL

The architecture explicitly mitigates the following threats:
- **Prompt Injection (Direct/Indirect)**: Mitigated by system-prompt separation and Tool Gateway strict validation.
- **Cross-tenant Data Leakage**: Gateway enforces DB scoping rules overriding any AI requests.
- **Tool Privilege Escalation**: Tool capabilities match human permissions exactly.
- **Raw SQL Execution**: Completely prohibited. Gateway only offers typed structured endpoints.
- **SSRF Through Tools**: Tools cannot query arbitrary URLs.
- **Secret Exfiltration**: Secrets are scrubbed by the Gateway before model ingestion.
- **Malicious WA/TG Sender & Stolen Binding**: Binding is verified and posture re-evaluated strictly per-message.
- **Conversation Data Leakage**: Context windows are bounded and organizationally segregated.
- **Over-broad Tool Results & Runaway Loops**: Results are paginated; loops are strictly bounded by config.
- **Model/Provider Compromise**: Provider abstraction prevents credential leakage. Read-only limits blast radius.
- **Audit Leakage**: Audit logs are sanitized of PII and secrets.

---

## 17. FINAL STATUS & OPEN DECISIONS
- No blocking architecture decisions remain.
