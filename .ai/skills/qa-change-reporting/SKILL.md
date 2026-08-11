---
name: qa-change-reporting
description: Enforces strict change reporting and QA verification formats for all AI tasks.
---

# QA & Change Reporting Skill

## Purpose
Ensures absolute transparency across AI handoffs by forcing agents to explicitly declare what was changed, why, what was verified, and the downstream impact, preventing silent regressions.

## When to Apply
Apply this skill at the end of every task when compiling the final report for the user.

## Mandatory Rules

Every completed task must report using EXACTLY these headings:

### Problem
Exact issue or missing requirement.

### Fix
Exact change made.

### Risk If Not Fixed
Operational/security/data/UI/maintenance risk.

### Files Changed
Only files actually changed. Do not list unmodified files.

### Verification
Only checks actually performed. Do not claim tests that were not run.

### Behavior Impact
Always report the following exact checklist (Yes/No):
* Backend behavior changed: Yes/No
* Database changed: Yes/No
* Route/API runtime changed: Yes/No
* UI runtime changed: Yes/No
* UI specification changed: Yes/No
* Migration added/run: Yes/No
* Scraping behavior changed: Yes/No
* Secrets exposed: Yes/No
* Documentation changed: Yes/No

### Current Stage
Use the approved current project stage.

### Next Stage
Use only the owner-approved next stage. Do not recommend another next step.

## Forbidden Actions
- Creating custom report formats.
- Omitting the Behavior Impact checklist.
- Claiming verification steps that were assumed but not actually executed.
- Recommending a different Next Stage than the one specified by the owner.
