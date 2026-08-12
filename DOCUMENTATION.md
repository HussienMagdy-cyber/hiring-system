# Hiring System — Documentation

Complete reference for the **Hiring System - ATS** WordPress plugin.

| | |
|---|---|
| **Plugin version** | 2.0.2 |
| **Database version** | 2.0.3 |
| **Text domain** | `hiring-system` |
| **Settings option** | `hs_settings` |

---

## Table of contents

1. [Overview](#overview)
2. [Installation & setup](#installation--setup)
3. [Frontend shortcodes](#frontend-shortcodes)
4. [Application pipeline](#application-pipeline)
5. [Admin panel](#admin-panel)
6. [User roles & permissions](#user-roles--permissions)
7. [REST API](#rest-api)
8. [Scoring](#scoring)
9. [Email notifications](#email-notifications)
10. [Settings reference](#settings-reference)
11. [Database schema](#database-schema)
12. [File structure](#file-structure)
13. [Hooks & developer reference](#hooks--developer-reference)
14. [Changelog summary](#changelog-summary)

---

## Overview

Hiring System is an Applicant Tracking System (ATS) built for WordPress. It provides:

- Job posting management
- Online applications with CV/portfolio upload
- Automated candidate scoring
- Interview scheduling
- HTML email notifications with branded signature footer
- Token-based candidate portal
- Department-scoped access for heads of department
- GDPR data retention cleanup

---

## Installation & setup

### Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL or MariaDB

### Steps

1. Copy the `hiring-system` folder to `wp-content/plugins/`.
2. Activate **Hiring System - ATS** in **Plugins**.
3. Go to **Hiring → Settings** and configure:
   - Notification email
   - Scoring weights
   - Email signature (logo, company details, sender)
   - Email templates
4. Create WordPress pages:

| Page | Shortcode | Slug example |
|------|-----------|--------------|
| Careers | `[hs_careers]` | `/careers` |
| Candidate portal | `[hs_candidate_portal]` | `/candidate-portal` |

5. Publish jobs under **Hiring → Jobs** with status **Published**.

---

## Frontend shortcodes

### `[hs_careers]`

Main careers experience. Supports:

- **Job listing** — department filter, job cards with apply links
- **Single job** — `?job={slug}` query parameter
- **Apply form** — embedded on job detail page

The listing page does **not** include a careers header block or site logo. Job detail pages also omit the site logo.

### `[hs_apply_form]`

Standalone application form.

```
[hs_apply_form job_id="123"]
```

If `job_id` is omitted, the form may prompt or require job context depending on implementation.

### `[hs_candidate_portal]`

Secure status page for candidates. Requires URL parameters:

```
https://yoursite.com/candidate-portal?app_id=1&token={access_token}
```

The `access_token` is generated per application and sent via email after submission.

---

## Application pipeline

### Valid statuses

```
new → shortlisted → interview → offer → hired
                              ↘ rejected
                              ↘ withdrawn
```

| Status | Description |
|--------|-------------|
| `new` | Application just received |
| `shortlisted` | Passed initial review |
| `interview` | In interview process |
| `offer` | Offer extended |
| `hired` | Accepted / hired |
| `rejected` | Not selected |
| `withdrawn` | Candidate withdrew |

> The `screening` status was removed in DB 2.0.3. Existing records are migrated to `new`.

### Status change emails

| New status | Email template used |
|------------|---------------------|
| `offer` | Offer (dedicated) |
| `rejected` | Rejected (dedicated) |
| Any other change | Status changed (general) |

No email is sent when old and new status are identical.

---

## Admin panel

| Menu item | Capability | Description |
|-----------|------------|-------------|
| Dashboard | `hs_view_applications` | KPIs, pipeline bar, recent activity |
| Jobs | `hs_edit_jobs` | CRUD for job postings |
| Applications | `hs_view_applications` | List, filter, view, update status |
| Pipeline | `hs_view_applications` | Visual pipeline board |
| Department Heads | `hs_manage_hiring` | Manage dept head user assignments |
| Settings | `hs_manage_hiring` | Scoring, GDPR, emails, signature |

### Application detail view

- Candidate info, CV, scores, timeline
- Status update with optional note
- Internal notes (public/private)
- Interview scheduling
- Email candidate link

### Settings page features

- **Email Signature** — logo upload (media library), company block, sender defaults
- **Email Templates** — 6 notification types with subject/body textareas
- **Email Preview** — live sticky panel; updates as you edit (sample data, no send)

---

## User roles & permissions

### Capabilities

| Capability | Description |
|------------|-------------|
| `hs_manage_hiring` | Full hiring admin (settings, dept heads) |
| `hs_view_applications` | View/manage applications |
| `hs_edit_jobs` | Create and edit jobs |
| `hs_manage_interviews` | Schedule interviews |

### Roles

| Role | Slug | Access |
|------|------|--------|
| Administrator | — | All capabilities |
| HR Manager | `hs_hr_manager` | Full hiring except WP admin |
| Head of Department | `hs_head_dept` | Dept-filtered applications, interviews |

Department filtering uses user meta configured on the user profile and **Department Heads** admin page.

---

## REST API

Base namespace: `hs/v1`

All routes are public (`permission_callback: __return_true`). Apply rate limiting is enforced server-side.

### `POST /wp-json/hs/v1/apply`

Submit an application.

**Body parameters (multipart for files):**

| Parameter | Required | Notes |
|-----------|----------|-------|
| `job_id` | Yes | Must be a published job |
| `full_name` | Yes | |
| `email` | Yes | |
| `privacy_consent` | Yes | Must be truthy |
| `phone`, `country`, `city` | No | |
| `linkedin_url`, `portfolio_url` | No | |
| `experience_years`, `education_level`, `skills` | No | |
| `cover_letter`, `source` | No | |
| `cv` | No | PDF, DOC, DOCX — max 5 MB |
| `portfolio` | No | PDF, DOC, DOCX — max 5 MB |

**Success response (201):**

```json
{
  "success": true,
  "application_id": 1,
  "score": 72.5,
  "portal_url": "https://yoursite.com/candidate-portal?app_id=1&token=...",
  "message": "Application submitted successfully!"
}
```

**Side effects:** scoring, timeline event, candidate + admin emails.

**Rate limit:** 20 requests per hour per IP (independent of settings rate limit on forms).

### `GET /wp-json/hs/v1/jobs`

Returns all published jobs.

### `GET /wp-json/hs/v1/jobs/{slug}`

Returns a single published job by slug.

---

## Scoring

Configured under **Settings → Scoring Weights**. Default weights:

| Category | Weight |
|----------|--------|
| Experience | 0.35 |
| Skills | 0.40 |
| Education | 0.10 |
| Portfolio | 0.15 |

Weights must sum to **1.0**.

**Auto-reject threshold:** Set `auto_reject_score` (0–100). `0` disables auto-rejection.

Scoring runs automatically on application submit via `hs_calculate_score()`.

---

## Email notifications

### Format

- **HTML** primary body (`Content-Type: text/html`)
- **Plain-text** alternative (`AltBody`)
- Message content: plain text templates with `{placeholders}` → converted to HTML
- Signature footer: fixed HTML block appended to every email

### Email structure

```
[Greeting + message from template]

──────────────────────────────  (#d1d5db)

With Appreciation,
Closing assistance text...

[Logo 180×60 — if uploaded]

Dedicated to your success,
Sender Name
Sender Role
M: {mobile} | E: {email}
{company_name} | {tagline}
T: {office_phone}
A: {office_address}
```

Footer excludes: unsubscribe, social icons, legal disclaimer, copyright, “Powered by WordPress”.

### Six email types

| # | Event | Recipient | Settings keys |
|---|-------|-----------|---------------|
| 1 | Application received | Candidate | `email_app_received_*` |
| 2 | Application received | Admin | `email_app_received_admin_*` |
| 3 | Status changed (general) | Candidate | `email_status_changed_*` |
| 4 | Status → offer | Candidate | `email_offer_*` |
| 5 | Status → rejected | Candidate | `email_rejected_*` |
| 6 | Interview scheduled | Candidate | `email_interview_*` |

### Template placeholders

| Placeholder | Used in |
|-------------|---------|
| `{candidate_name}` | Candidate emails |
| `{candidate_email}` | Admin email |
| `{job_title}` | All |
| `{portal_url}` | Candidate emails |
| `{site_name}`, `{site_url}` | All |
| `{score}`, `{admin_url}` | Admin email |
| `{old_status}`, `{new_status}` | Status changed |
| `{interview_date}`, `{duration}`, `{type}`, `{meeting_link}` | Interview |

Parsing is simple `{key}` → value string replace.

### Sender in footer

| Context | Name | Role |
|---------|------|------|
| Admin triggers status/interview | Logged-in display name | User meta `hs_job_title` or default |
| REST API application | `email_sender_name` setting | `email_sender_role` setting |

### From address

- **Email:** `notification_email` setting → fallback WordPress admin email
- **Name:** Site title

### Live preview

**Hiring → Settings** includes a preview panel powered by:

- AJAX action: `hs_preview_email`
- Script: `hs-settings.js`
- Sample recipient: Jane Doe · Sample job: Software Engineer

See also: [docs/EMAILS.md](docs/EMAILS.md) for extended email reference.

---

## Settings reference

All settings stored in `wp_options` → `hs_settings`.

### General

| Key | Default | Description |
|-----|---------|-------------|
| `notification_email` | (empty) | From address + admin notifications |
| `auto_reject_score` | 0 | Auto-reject below this score |
| `gdpr_retention_days` | 365 | Days before GDPR cleanup |
| `enable_rate_limiting` | 1 | 5 applications/hour/IP on forms |

### Scoring weights

| Key | Default |
|-----|---------|
| `scoring_weights.experience` | 0.35 |
| `scoring_weights.skills` | 0.40 |
| `scoring_weights.education` | 0.10 |
| `scoring_weights.portfolio` | 0.15 |

### Email signature

| Key | Default |
|-----|---------|
| `email_appreciation_line` | With Appreciation, |
| `email_closing_text` | We always stand available to provide any clarifications or assistance whenever needed. |
| `email_logo_id` | 0 |
| `email_dedication_line` | Dedicated to your success, |
| `email_sender_name` | (empty) |
| `email_sender_role` | HR Team |
| `email_mobile_phone` | (empty) |
| `email_contact_email` | (empty) |
| `email_company_name` | (empty) |
| `email_company_tagline` | (empty) |
| `email_office_phone` | (empty) |
| `email_office_address` | (empty) |

---

## Database schema

Prefix: `{wp_prefix}hs_`

### `hs_jobs`

Job postings — title, slug, department, location, employment type, description, requirements, salary range, status (`draft` / `published` / `closed`).

### `hs_candidates`

Candidate profiles — contact info, skills, education, CV/portfolio attachment IDs, privacy consent, IP.

### `hs_applications`

Applications — `candidate_id`, `job_id`, `status`, `score`, cover letter, source, `access_token`, timestamps.

Unique constraint: one application per candidate per job.

### `hs_scores`

Per-application scoring breakdown by category.

### `hs_events`

Application timeline / audit log.

### `hs_notes`

Internal recruiter notes (optional private flag).

### `hs_interviews`

Scheduled interviews — datetime, duration, type (`phone` / `video` / `in-person`), meeting link, feedback, score, status.

### Migrations

Version tracked in `hs_db_version` option. Notable migrations:

- **2.0.3** — `screening` status → `new`

---

## File structure

```
hiring-system/
├── hiring-system.php       Plugin bootstrap, activation, wp_mail filters
├── admin.js                General admin scripts
├── admin.css               Admin styles + email preview layout
├── hs-settings.js          Settings: logo upload, email preview AJAX
├── DOCUMENTATION.md        This file
├── README.md               Quick start
├── CHANGELOG.md            Version history
├── docs/
│   └── EMAILS.md           Detailed email reference
└── includes/
    ├── loader.php
    ├── class-hs-database.php
    ├── class-hs-settings.php
    ├── class-hs-notifications.php
    ├── class-hs-rate-limiter.php
    ├── admin/class-hs-admin.php
    ├── api/rest-api.php
    ├── shortcodes/class-hs-shortcodes.php
    ├── ajax/ajax-handlers.php
    ├── handlers/           Form/action handlers
    ├── models/             Data layer (Job, Application, Candidate, etc.)
    ├── cron/gdpr-cleanup.php
    └── functions-*.php     Scoring, permissions, user profile
```

---

## Hooks & developer reference

### Key classes

```php
HS_Settings::get($key);           // Read setting
HS_Settings::save($data);          // Merge and save settings

HS_Notifications::application_received($app_id);
HS_Notifications::status_changed($app_id, $old_status, $new_status);
HS_Notifications::interview_scheduled($interview_id);

HS_Notifications::preview_email($subject, $body, $signature_overrides);
// Returns ['subject' => '...', 'html' => '...']
```

### Models

```php
HS_Job::find($id);
HS_Job::find_by_slug($slug);
HS_Job::query($args);

HS_Candidate::find($id);
HS_Candidate::save($data);

HS_Application::find($id);
HS_Application::find_with_relations($id);
HS_Application::update_status($app_id, $new_status, $note);
HS_Application::save($data);

HS_Interview::find($id);
HS_Interview::get_by_application($app_id);
```

### AJAX actions (admin)

| Action | Purpose |
|--------|---------|
| `hs_preview_email` | Render email preview HTML |
| `hs_update_pipeline` | Update status from pipeline board |

### WordPress filters (bootstrap)

```php
add_filter('wp_mail_from', ...);      // notification_email or admin_email
add_filter('wp_mail_from_name', ...); // site name
```

### GDPR cron

`includes/cron/gdpr-cleanup.php` anonymizes candidate data older than `gdpr_retention_days`.

---

## Changelog summary

| Version | Highlights |
|---------|------------|
| **2.0.3** | Removed screening status; HTML email signature; live preview; careers UI cleanup |
| **2.0.2** | Department heads; email templates |
| **2.0.1** | Core ATS release |

Full history: [CHANGELOG.md](CHANGELOG.md)

---

## Support checklist

| Issue | Check |
|-------|-------|
| Emails not sending | SMTP plugin, `notification_email`, spam folder |
| Logo not in email | Save settings after upload; preview panel first |
| Portal link invalid | `app_id` + `token` must match database |
| Application blocked | Rate limit, duplicate candidate+job, job not published |
| Dept head sees all jobs | User department meta and dept head assignment |

---

*Last updated for plugin DB version 2.0.3*
