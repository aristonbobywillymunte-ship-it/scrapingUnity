# UI & INFORMATION ARCHITECTURE DOCUMENTATION

## 1. Executive Summary
This document establishes the official Information Architecture, sitemap, and page purposes for the Social Media Scraping Platform application, adhering to the locked **PRD v2.0 Baseline**.

## 2. Role-Based Navigation Architecture
The platform enforces a strict separation between customer/tenant capabilities and administrative operational tooling:

### A. Customer / User Application
- **Dashboard (`/dashboard`)**: At-a-glance overview of active jobs, completed results, failed executions, and quota balance.
- **Jobs (`/runs`)**:
  - `Daftar Pekerjaan (Jobs)`: Paginated list of user's scraping jobs with live status badges.
  - `Buat Job Baru (`/runs/create`)`: Dynamic capability form with mode toggling (Kata Kunci, Hashtag, Target Konten).
  - `Detail Job (`/runs/{id}`)`: Execution lifecycle inspection.
- **Hasil Scraping (`/results`)**: Normalized output items with rich JSON payloads, metrics, and author details.
- **Kunci API (`/api-keys`)**: SHA-256 hashed API key management.
- **Kuota & Penggunaan (`/billing`)**: Read-only quota usage ledger.
- **Akun (`/profile`, `/profile/security`)**: User account credentials and security settings.

### B. Admin Application
- **Admin Overview (`/admin`)**: Global system metrics (Total Users, Organizations, Runs).
- **Pusat Operasional & Scraping Lab (`/admin/operations`)**:
  - Live Redis Queue status (`scrape:executions`).
  - Dead Letter Queue (DLQ) failure monitoring.
  - Runtime architecture status and Python scraping worker health.

## 3. UI/UX Principles & Skill Integration
Applied guidelines from `docs/skill/ui-ux-pro-max-skill`:
- Clean, compact, professional card layouts with clear typography.
- Standardized Indonesian localization for interactive user controls.
- Responsive breakpoints verified at 375px, 768px, 1024px, and 1440px.
- Real Playwright browser automated testing proving 100% route health.
