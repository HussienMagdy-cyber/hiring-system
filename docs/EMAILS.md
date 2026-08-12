# Email Notifications

All hiring emails are sent via WordPress `wp_mail()` as **HTML** with a **plain-text alternative**. Message bodies are edited as plain text in admin; the **HTML signature footer** is appended automatically to every send.

---

## Architecture

| Layer | File | Role |
|-------|------|------|
| Settings | `class-hs-settings.php` | Defaults in `$defaults`; stored in `hs_settings` |
| Notifications | `class-hs-notifications.php` | Build vars, render HTML + footer, send |
| Admin UI | `class-hs-admin.php` | Signature fields, templates, live preview |
| Preview JS | `hs-settings.js` | Logo upload, AJAX preview |
| AJAX | `ajax-handlers.php` | `hs_preview_email` |
| Bootstrap | `hiring-system.php` | `wp_mail_from`, `wp_mail_from_name` |

---

## Email layout

```
[Greeting + message from template — plain text → HTML with nl2br]

──────────────────────────────  divider #d1d5db

With Appreciation,                    13px #6b7280
Closing assistance text...          13px #6b7280

[Logo 180×60 — optional]

Dedicated to your success,           15px #374151
Sender Name                         16px bold #111827
Sender Role                         14px #6b7280
M: {phone} | E: {email}             14px (email = blue mailto link)
{company_name} | {tagline}          14px bold #111827
T: {office_phone}                   14px
A: {office_address}                 14px multiline
```

### Excluded from footer

- Unsubscribe link
- Social icons
- Legal / disclaimer text
- “Powered by WordPress”
- Copyright line

---

## Email signature settings

**Hiring → Settings → Email Signature**

| Setting key | Default | Description |
|-------------|---------|-------------|
| `email_appreciation_line` | With Appreciation, | Line above closing text |
| `email_closing_text` | We always stand available… | Assistance paragraph |
| `email_logo_id` | 0 | Media library attachment ID (180×60 recommended) |
| `email_dedication_line` | Dedicated to your success, | Tagline under logo |
| `email_sender_name` | (empty) | Default sender when no logged-in user |
| `email_sender_role` | HR Team | Default role |
| `email_mobile_phone` | (empty) | Mobile on contact line |
| `email_contact_email` | (empty) | Footer email; fallback: notification email |
| `email_company_name` | (empty) | Fallback: site name |
| `email_company_tagline` | (empty) | Shown after company name |
| `email_office_phone` | (empty) | Office phone line |
| `email_office_address` | (empty) | Address line (multiline) |

### Sender resolution

| Trigger | Sender name | Sender role |
|---------|-------------|-------------|
| Admin changes status / schedules interview | Logged-in user display name | User meta `hs_job_title`, else default role |
| REST API application (no user) | `email_sender_name` setting | `email_sender_role` setting |

---

## Email types (6)

### 1. Application received — candidate

- **Trigger:** Application submitted (REST API)
- **To:** Candidate
- **Settings:** `email_app_received_subject`, `email_app_received_body`

### 2. Application received — admin

- **Trigger:** Same as #1
- **To:** Notification email or admin email
- **Settings:** `email_app_received_admin_subject`, `email_app_received_admin_body`

### 3. Status changed — general

- **Trigger:** Status update except `offer` and `rejected`
- **To:** Candidate
- **Settings:** `email_status_changed_subject`, `email_status_changed_body`
- **Skipped** when old status equals new status

### 4. Status changed — offer

- **Trigger:** Status set to `offer`
- **Settings:** `email_offer_subject`, `email_offer_body`

### 5. Status changed — rejected

- **Trigger:** Status set to `rejected`
- **Settings:** `email_rejected_subject`, `email_rejected_body`

### 6. Interview scheduled

- **Trigger:** Interview created
- **To:** Candidate
- **Settings:** `email_interview_subject`, `email_interview_body`

---

## Template placeholders

Simple `{key}` string replace — no templating engine.

### Common (all candidate emails)

| Placeholder | Source |
|-------------|--------|
| `{candidate_name}` | Candidate full name |
| `{job_title}` | Job title |
| `{portal_url}` | `{site}/candidate-portal?app_id={id}&token={token}` |
| `{site_name}` | Site title |
| `{site_url}` | Home URL |

### Application received (admin)

| Placeholder | Source |
|-------------|--------|
| `{candidate_email}` | Candidate email |
| `{score}` | Application score |
| `{admin_url}` | Admin application view URL |

### Status changed

| Placeholder | Source |
|-------------|--------|
| `{old_status}` | Localized label |
| `{new_status}` | Localized label |

### Interview

| Placeholder | Source |
|-------------|--------|
| `{interview_date}` | WP date/time format |
| `{duration}` | Minutes |
| `{type}` | Phone / Video / In-person |
| `{meeting_link}` | `Meeting Link: {url}` or empty |

---

## Routing logic

```
application submitted
  → #1 candidate
  → #2 admin

status changed
  → offer     → #4
  → rejected  → #5
  → else      → #3

interview created
  → #6 candidate
```

---

## From / To

- **From email:** `notification_email` → fallback `admin_email`
- **From name:** Site title (`get_bloginfo('name')`)
- **Content-Type:** `text/html; charset=UTF-8` (+ plain AltBody)

---

## Live preview (Settings page)

The settings page includes a sticky **Email Preview** panel:

- Template dropdown (all 6 types)
- Updates ~500ms after editing signature or template fields
- Uses sample data: Jane Doe, Software Engineer
- AJAX action: `hs_preview_email`
- Does not send email

**Logo upload:** Uses WordPress media library via `hs-settings.js` (requires Save Settings to persist).

---

## Default template bodies

Bodies are plain text with `\n` line breaks. Sign-off is handled by the HTML footer, not duplicated in templates.

**Application received (candidate):**
```
Hi {candidate_name},

We've received your application for '{job_title}'.

Track your status: {portal_url}
```

**Status changed:**
```
Hi {candidate_name},

Your application for '{job_title}' has been updated.

Status: {old_status} -> {new_status}

Track your application: {portal_url}
```

See `class-hs-settings.php` `$defaults` for all default subject/body strings.

---

## Developer notes

### Preview programmatically

```php
$html = HS_Notifications::preview_email(
    $subject_template,
    $body_template,
    $signature_overrides // optional array matching settings keys
);
// Returns ['subject' => '...', 'html' => '...']
```

### Signature overrides from POST

```php
$overrides = HS_Notifications::signature_overrides_from_post($_POST);
```

### Key classes

- `HS_Notifications::application_received($app_id)`
- `HS_Notifications::status_changed($app_id, $old, $new)`
- `HS_Notifications::interview_scheduled($interview_id)`
