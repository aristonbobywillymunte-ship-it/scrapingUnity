---
name: ui-crud-ux
description: Enforces the mandatory Terminal Core UI baseline and strict global CRUD modal/toast UX patterns.
---

# UI CRUD UX Skill

## Purpose
Ensures that all frontend implementation strictly adheres to the approved Terminal Core UI baseline and standardizes the user feedback loop for all Create, Read, Update, and Delete actions.

## When to Apply
Apply this skill whenever designing or implementing Livewire components, Blade views, or frontend interactions that involve data mutation or user feedback.

## Mandatory Rules (MANDATORY — LOCKED)

### Base UI
Use the approved Terminal Core UI baseline (Dark Console theme, Geist/JetBrains Mono fonts).

### Create
`Create → Backend Result → Toast`
- Use the approved modal/form pattern.
- Success → Success Toast.
- Failure → inline validation + Error Toast.

### Update
`Update → Backend Result → Toast`
- Success → Success Toast.
- Failure → inline validation + Error Toast.

### Delete
`Delete → Confirmation Modal → Confirm → Backend Action → Toast`
- Never immediately execute a delete action.

### Sensitive Actions
Require a confirmation modal for the following actions:
- Suspend User
- Disable User
- Revoke API Key
- Delete Webhook
- Disable Proxy
- Proxy Cooldown
- Cancel Job
- Approve/Activate Parser Candidate
- Rollback Parser
- Disable Parser Version
- Provider reset/removal
- Any destructive/security-sensitive action

### Toast Standard
- **Supported types:** success, error, warning, info
- **Rules:** compact, non-blocking, consistent placement, auto-dismiss for success/info.
- **Security:** No secrets, no stack traces, no raw exceptions, no API keys, no session data in toasts.

## Forbidden Patterns
Never use:
- browser `alert()`
- browser `confirm()`
- silent successful CRUD (actions without user feedback)
- destructive action without confirmation

## Required Verification
- Verify that destructive actions cannot be triggered with a single click without a modal.
