<?php
if (!defined('ABSPATH')) exit;

class HS_Interview {
    public static function find($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hs_interviews WHERE id = %d", $id));
    }

    public static function get_by_application($application_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.display_name as interviewer_name 
             FROM {$wpdb->prefix}hs_interviews i
             LEFT JOIN {$wpdb->users} u ON i.interviewer_id = u.ID
             WHERE i.application_id = %d ORDER BY i.scheduled_at DESC",
            $application_id
        ));
    }

    public static function save($data) {
        global $wpdb;
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);
            $wpdb->update($wpdb->prefix . 'hs_interviews', $data, ['id' => $id]);
            return $id;
        }
        $wpdb->insert($wpdb->prefix . 'hs_interviews', $data);
        return $wpdb->insert_id;
    }

    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'hs_interviews', ['id' => (int)$id]);
    }
}
