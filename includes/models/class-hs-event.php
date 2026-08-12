<?php
if (!defined('ABSPATH')) exit;

class HS_Event {
    public static function log($application_id, $event_type, $message) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'hs_events', [
            'application_id' => (int)$application_id,
            'user_id' => get_current_user_id() ?: null,
            'event_type' => sanitize_text_field($event_type),
            'message' => sanitize_textarea_field($message),
            'created_at' => current_time('mysql')
        ]);
    }

    public static function get_recent($limit = 10) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, c.full_name as candidate_name, j.title as job_title, u.display_name as user_name
             FROM {$wpdb->prefix}hs_events e
             LEFT JOIN {$wpdb->prefix}hs_applications a ON e.application_id = a.id
             LEFT JOIN {$wpdb->prefix}hs_candidates c ON a.candidate_id = c.id
             LEFT JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             ORDER BY e.created_at DESC LIMIT %d", $limit
        ));
    }
}
