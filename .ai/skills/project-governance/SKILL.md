---
name: project-governance
description: Enforces project direction, PRD adherence, and strict staging boundaries for the Social Media Scraping API project.
---

# Project Governance Skill

## Purpose
Ensures that all AI agents respect the locked project architecture, PRD requirements, and the specific stage of the Social Media Scraping API project, preventing architectural drift and scope creep.

## When to Apply
Apply this skill at the beginning of every task, before making any architectural or workflow decisions.

## Mandatory Rules
- **Owner controls direction:** The Owner controls the project direction. Do not create your own recommendations or product direction.
- **PRD is mandatory:** The PRD is the absolute source of truth.
- **Locked decisions:** Locked decisions cannot be changed by AI. Do not silently redesign architecture.
- **Strict Staging:** AI must respect the current project stage. Do not skip stages. Do not start the next stage without explicit instruction.
- **Scope preservation:** Preserve the standalone scraping-service scope. Do not add unrelated features or remove approved features. Do not introduce Arusbawah business logic.
- **Architecture preservation:** Preserve the exact Laravel + Livewire + Blade + PostgreSQL + Redis + Python architecture.
- **Role preservation:** Preserve Admin/User-only roles.
- **Source of truth:** Read relevant source-of-truth files before starting work.

## Forbidden Actions
- Re-architecting or changing technologies.
- Implementing features outside the approved scope.
- Proceeding to a new stage (e.g., coding before design is complete) without Owner permission.

## Required Reporting
When blocked by a missing owner decision, report exactly:
`OWNER DECISION REQUIRED`
Do not recommend an alternative unless explicitly asked.
