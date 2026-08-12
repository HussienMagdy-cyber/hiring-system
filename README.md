# Hiring System — ATS Plugin

WordPress Applicant Tracking System: jobs, applications, scoring, interviews, and HTML email notifications.

**Version:** 2.0.2 · **DB version:** 2.0.3

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL/MariaDB

---

## Installation

1. Place the `hiring-system` folder in `wp-content/plugins/`.
2. Activate **Hiring System - ATS** in WordPress admin.
3. Configure **Hiring → Settings** (notification email, scoring, email signature).
4. Create pages and add shortcodes (see below).

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[hs_careers]` | Job listing, single job detail (`?job=slug`), and apply form |
| `[hs_apply_form]` | Standalone application form (optional `job_id` attribute) |
| `[hs_candidate_portal]` | Token-based candidate status portal |

### Careers page

- Lists published jobs with department filter.
- Single job view: back link, job header, info bar, description, requirements, sidebar CTA, apply form.
- No site logo or careers header block on listing or detail pages.

### Candidate portal URL

```
{candidate-portal-page}?app_id={id}&token={access_token}
```

---

## Application pipeline

Statuses (in order):

```
new → shortlisted → interview → offer → hired
                              ↘ rejected
                              ↘ withdrawn
```

> **Note:** The `screening` status was removed in DB 2.0.3. Existing `screening` applications are migrated to `new`.

---

## Admin menu

| Page | Capability | Purpose |
|------|------------|---------|
| Dashboard | `hs_view_applications` | Stats, pipeline overview, recent activity |
| Jobs | `hs_edit_jobs` | Create/edit job postings |
| Applications | `hs_view_applications` | View and manage applications |
| Pipeline | `hs_view_applications` | Kanban-style status board |
| Department Heads | `hs_manage_hiring` | Assign department heads |
| Settings | `hs_manage_hiring` | Scoring, GDPR, email signature & templates |

### Roles

- **Administrator** — full access
- **HR Manager** (`hs_hr_manager`) — manage hiring, jobs, applications, interviews
- **Head of Department** (`hs_head_dept`) — view applications (dept-filtered), manage interviews

---

## REST API

Applications can be submitted via the plugin REST endpoint (see `includes/api/rest-api.php`). A successful submission triggers scoring and **Application Received** emails.

---

## Settings overview

Stored in `wp_options` key `hs_settings`.

| Area | Keys / notes |
|------|----------------|
| Notification | `notification_email` (fallback: admin email) |
| Scoring | `scoring_weights`, `auto_reject_score` |
| GDPR | `gdpr_retention_days` |
| Rate limiting | `enable_rate_limiting` (5 applications/hour/IP) |
| Email signature | See [docs/EMAILS.md](docs/EMAILS.md) |
| Email templates | 6 types × subject + body |

---

## File structure

```
hiring-system/
├── hiring-system.php          # Bootstrap, wp_mail filters
├── admin.js                   # General admin scripts
├── admin.css                  # Admin + settings preview styles
├── hs-settings.js             # Settings page: logo upload, email preview
├── README.md
├── docs/
│   └── EMAILS.md              # Email system documentation
└── includes/
    ├── loader.php
    ├── class-hs-database.php
    ├── class-hs-settings.php
    ├── class-hs-notifications.php
    ├── admin/class-hs-admin.php
    ├── api/rest-api.php
    ├── shortcodes/class-hs-shortcodes.php
    ├── models/
    ├── handlers/
    └── ajax/ajax-handlers.php
```

---

## Database tables

| Table | Purpose |
|-------|---------|
| `wp_hs_jobs` | Job postings |
| `wp_hs_candidates` | Candidate profiles |
| `wp_hs_applications` | Applications + status + access token |
| `wp_hs_scores` | Scoring breakdown |
| `wp_hs_events` | Application timeline |
| `wp_hs_notes` | Internal notes |
| `wp_hs_interviews` | Scheduled interviews |

---

## Further reading

- **[DOCUMENTATION.md](DOCUMENTATION.md)** — Full plugin documentation
- [Email notifications & signature](docs/EMAILS.md)
- [Changelog](CHANGELOG.md)
