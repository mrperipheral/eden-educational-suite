# Roadmap

High-level milestone order. Each milestone ships working, tested software and
updates `PROJECT_STATUS.md`. **Do not start a milestone before the previous one
is accepted.**

## ✅ Milestone 1 — Platform Foundation (complete, 2026-09-10)

Project inspection; conventions for controllers / models / Form Requests /
policies / services / views / components / tests; base responsive Blade shell;
small reusable UI kit; `TenantContext` seam; `/health` endpoint; testing
foundation; config & `.gitignore` safety review; documentation structure.

No domain modules, no website features, no artificial school limit.

## ▶ Milestone 2 — Authentication & User Foundation

Login / logout / password reset, session hardening, the `users` domain profile,
**role & permission model** (evaluate Spatie Permission here), platform-admin vs
school-user separation, auth feature tests.

## Milestone 3 — Multi-School Core

`schools` table and model, `EnforceTenant` middleware, `BelongsToSchool` trait +
global scope + `creating` hook, tenant-scoped validation helpers, cross-tenant
isolation test suite, school switching for multi-school users.

## Milestone 4 — School Onboarding

School registration/provisioning flow, initial admin user, school settings,
academic session / term setup.

## Milestone 5+ — Domain Modules

Staff · Students & Guardians · Classes/Sections/Subjects · Enrolment ·
Attendance · Assessments & Results · Fees / Invoices / Payments (Paystack) ·
CBT · Role-specific portals & dashboards · Notifications · Reporting.

## Cross-cutting, introduced when first needed

Queues & Redis, object/S3 storage abstraction for uploads, audit logging,
full-text search, caching layer, background exports.

## Permanently out of scope

Public school websites, website builder, themes, website engine, public school
pages, public content management.
