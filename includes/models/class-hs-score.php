<?php
if (!defined('ABSPATH')) exit;

class HS_Score {
    public static function save($data) {
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}hs_scores WHERE application_id = %d AND category = %s",
            $data['application_id'], $data['category']
        ));
        if ($existing) {
            return $wpdb->update($wpdb->prefix . 'hs_scores', $data, ['id' => $existing]);
        }
        return $wpdb->insert($wpdb->prefix . 'hs_scores', $data);
    }

    public static function get_by_application($application_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hs_scores WHERE application_id = %d",
            $application_id
        ));
    }

    public static function delete_by_application($application_id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'hs_scores', ['application_id' => (int)$application_id]);
    }
}
