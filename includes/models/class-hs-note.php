<?php
if (!defined('ABSPATH')) exit;

class HS_Note {
    public static function add($application_id, $note, $is_private = 0) {
        global $wpdb;
        return $wpdb->insert($wpdb->prefix . 'hs_notes', [
            'application_id' => (int)$application_id,
            'user_id' => get_current_user_id(),
            'note' => sanitize_textarea_field($note),
            'is_private' => (int)$is_private,
            'created_at' => current_time('mysql'),
        ]);
    }

    public static function get_by_application($application_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name 
             FROM {$wpdb->prefix}hs_notes n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.application_id = %d ORDER BY n.created_at DESC",
            $application_id
        ));
    }
}
