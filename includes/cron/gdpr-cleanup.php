<?php
if (!defined('ABSPATH')) exit;

add_action('hs_daily_cleanup', 'hs_gdpr_cleanup');
function hs_gdpr_cleanup() {
    $days = (int)HS_Settings::get('gdpr_retention_days');
    if ($days < 1) return;

    global $wpdb;
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    // Anonymize old rejected/withdrawn applications
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}hs_candidates c
         JOIN {$wpdb->prefix}hs_applications a ON c.id = a.candidate_id
         SET c.full_name = CONCAT('Anonymous_', a.id),
             c.email = CONCAT('anon_', a.id, '@deleted.local'),
             c.phone = NULL,
             c.linkedin_url = NULL,
             c.portfolio_url = NULL,
             c.skills = NULL,
             c.resume_text = NULL,
             c.cv_attachment_id = NULL
         WHERE a.status IN ('rejected','withdrawn') AND a.applied_at < %s",
        $cutoff
    ));
}

if (!wp_next_scheduled('hs_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'hs_daily_cleanup');
}
