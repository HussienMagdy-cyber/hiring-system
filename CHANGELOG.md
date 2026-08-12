# Changelog

## 2.0.3 (unreleased)

### Email notifications
- HTML emails with structured signature footer (appreciation line, closing text, logo, sender block, contact, company, office details)
- Email signature settings in admin with media library logo upload
- Live email preview panel on Settings page (AJAX, no test send required)
- Dedicated `hs-settings.js` for settings page scripts
- Plain-text AltBody for all HTML sends

### Application pipeline
- Removed `screening` status from UI, validation, and notifications
- DB migration: existing `screening` applications → `new`

### Careers frontend
- Removed `hs-careers-header` from job listing page
- Removed site logo from single job detail page

### Fixes
- Created missing `admin.js` to prevent script load failures
- Fixed logo upload by enqueueing `wp.media` with correct script dependencies

## 2.0.2

- Department heads feature
- Initial email template system (plain-text footer)

## 2.0.1

- Core ATS: jobs, applications, scoring, interviews, shortcodes, REST API
