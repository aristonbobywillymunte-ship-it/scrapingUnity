---
name: Terminal Core
colors:
  surface: '#0b1326'
  surface-dim: '#0b1326'
  surface-bright: '#31394d'
  surface-container-lowest: '#060e20'
  surface-container-low: '#131b2e'
  surface-container: '#171f33'
  surface-container-high: '#222a3d'
  surface-container-highest: '#2d3449'
  on-surface: '#dae2fd'
  on-surface-variant: '#c2c6d6'
  inverse-surface: '#dae2fd'
  inverse-on-surface: '#283044'
  outline: '#8c909f'
  outline-variant: '#424754'
  surface-tint: '#adc6ff'
  primary: '#adc6ff'
  on-primary: '#002e6a'
  primary-container: '#4d8eff'
  on-primary-container: '#00285d'
  inverse-primary: '#005ac2'
  secondary: '#4ae176'
  on-secondary: '#003915'
  secondary-container: '#00b954'
  on-secondary-container: '#004119'
  tertiary: '#ffb95f'
  on-tertiary: '#472a00'
  tertiary-container: '#ca8100'
  on-tertiary-container: '#3e2400'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#d8e2ff'
  primary-fixed-dim: '#adc6ff'
  on-primary-fixed: '#001a42'
  on-primary-fixed-variant: '#004395'
  secondary-fixed: '#6bff8f'
  secondary-fixed-dim: '#4ae176'
  on-secondary-fixed: '#002109'
  on-secondary-fixed-variant: '#005321'
  tertiary-fixed: '#ffddb8'
  tertiary-fixed-dim: '#ffb95f'
  on-tertiary-fixed: '#2a1700'
  on-tertiary-fixed-variant: '#653e00'
  background: '#0b1326'
  on-background: '#dae2fd'
  surface-variant: '#2d3449'
typography:
  display-lg:
    fontFamily: Geist
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Geist
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  body-base:
    fontFamily: Geist
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  data-mono:
    fontFamily: JetBrains Mono
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
  label-caps:
    fontFamily: JetBrains Mono
    fontSize: 11px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  container-padding: 24px
  gutter: 16px
  stack-sm: 8px
  stack-md: 16px
  stack-lg: 32px
---

## Brand & Style
The design system is engineered for high-performance infrastructure management, evoking the precision of a low-level terminal interface. The brand personality is technical, reliable, and authoritative, targeting DevOps engineers and backend developers who prioritize data density and system transparency. 

The aesthetic combines **Modern Minimalism** with **Cyber-Terminal** influences. It utilizes deep charcoal canvases to reduce eye strain during long-tail monitoring sessions, punctuated by vibrant functional colors that signal system health. Visual interest is generated through subtle glows and precise borders rather than heavy imagery, ensuring the UI feels like a high-speed command center.

## Colors
This design system utilizes a "Dark Console" palette designed for high contrast and functional signaling.
- **Primary (Cyber Blue):** Used for primary actions, focus states, and active navigation indicators.
- **Success (Green):** Reserved for active API streams, "200 OK" statuses, and healthy system metrics.
- **Warning/Error (Amber/Red):** High-visibility tokens for rate-limiting alerts and failed scraping jobs.
- **Neutral/Background:** The base layer is `#0F172A`, with surface elevations using `#1E293B`. Borders should remain subtle using `#334155` to maintain the "grid" feel without distracting from the data.

## Typography
The typography strategy distinguishes between **Interface Controls** and **System Data**.
- **Geist Sans:** Used for the structural interface, headings, and primary navigation to ensure the dashboard feels like a modern SaaS application.
- **JetBrains Mono:** The primary choice for all dynamic content, including API keys, JSON payloads, status codes, and timestamps. 

All mono text should have a slightly reduced font size compared to sans-serif text to maintain vertical alignment. For density, use `body-base` for descriptions and `data-mono` for all values returned from the scraper.

## Layout & Spacing
The design system employs a **Fixed Grid** system for dashboard views to ensure consistency in data visualization. 
- **Desktop:** 12-column grid with 16px gutters and a 24px outer margin.
- **Information Density:** High. Use a 4px base unit. Component padding should be tight (e.g., 8px vertical / 12px horizontal for buttons).
- **Sidebars:** Fixed at 240px. Main content area scrolls independently.
- **Modular Panels:** Content is housed in "Cells" that align strictly to the grid, creating a technical, blueprint-like appearance.

## Elevation & Depth
In this design system, depth is achieved through **Tonal Layering** and **Low-Contrast Outlines** rather than traditional shadows.
- **Base Level (Layer 0):** `#0F172A` - The global background.
- **Surface Level (Layer 1):** `#1E293B` - Used for cards, panels, and input backgrounds.
- **Borders:** All interactive elements must have a 1px border (`#334155`). 
- **Glow Effects:** Use `box-shadow` only for status indicators. A successful "Live" stream should have a soft `0 0 8px` green glow. Avoid shadows on larger containers to maintain a flat, "HUD" (Heads-Up Display) aesthetic.

## Shapes
To maintain the professional developer vibe, the design system uses **Soft (0.25rem)** rounding. This provides a subtle modern touch while retaining the structural rigidity of a terminal.
- **Small Elements:** Buttons, tags, and inputs use `rounded-sm` (4px).
- **Containers:** Dashboard cards and modals use `rounded-md` (8px).
- **Icons:** Always use sharp or slightly rounded geometric icons (2px corner radius) to match the monospace aesthetic.

## Components
- **Buttons:** Primary buttons use a solid Cyber Blue background with white text. Secondary buttons are ghost-style with a 1px border and no fill until hover.
- **Status Chips:** High-contrast background with a circular 6px "indicator dot" on the left. Active states feature a subtle pulsing glow.
- **Data Tables:** No vertical lines. Horizontal lines only between rows (`#1E293B`). Use `data-mono` for all row content.
- **Input Fields:** Darker background than the card surface. On focus, the border changes to Primary Blue with a subtle outer glow.
- **Metric Cards:** Large `display-lg` numbers with a small `label-caps` title above and a sparkline (mini-graph) at the bottom.

## Global CRUD UX Rules
MANDATORY — LOCKED

### Create
Flow: `Create → Backend Success/Failure → Toast`
- Use approved modal/form pattern.
- Success → Success Toast.
- Failure → inline validation + Error Toast.
- Never use browser `alert()`.

### Update
Flow: `Edit → Save → Backend Success/Failure → Toast`
- Success → Success Toast.
- Failure → inline validation + Error Toast.

### Delete
Deletion must never execute immediately.
Flow: `Delete → Confirmation Modal → Confirm → Backend Action → Toast`
Confirmation modal must contain:
- affected record name/identifier
- clear delete/destructive warning
- Cancel button
- Confirm/Delete button
Result: Success → Success Toast, Failure → Error Toast

### Sensitive / Destructive Actions
The same confirmation flow is mandatory for:
- Suspend User
- Disable User
- Revoke API Key
- Delete Webhook
- Disable Proxy
- Trigger Proxy Cooldown
- Cancel Job
- Approve/Activate Parser Candidate
- Rollback Parser
- Disable Parser Version
- Remove/Reset Provider Configuration
- any other destructive or security-sensitive action

Flow: `Action → Confirmation Modal → Execute → Toast`

### Toast Standard
Supported types: success, error, warning, info
Rules:
- non-blocking
- compact
- consistent placement
- success/info auto-dismiss
- error may remain longer
- no stack traces
- no secrets, API keys, proxy passwords, or session data

### Validation
Field validation must appear near the affected field.
Toast may additionally show: `Please review the highlighted fields.`
Do not place full validation payloads inside toast.

### Forbidden Patterns
Do not use:
- browser `alert()`
- browser `confirm()`
- silent destructive action
- destructive action without confirmation
- successful CRUD without user feedback
