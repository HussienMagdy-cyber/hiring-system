<?php
if (!defined('ABSPATH')) exit;

class HS_Candidate {
    public static function find($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hs_candidates WHERE id = %d", $id));
    }

    public static function find_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hs_candidates WHERE email = %s", $email));
    }

    public static function save($data) {
        global $wpdb;
        $existing = !empty($data['email']) ? self::find_by_email($data['email']) : null;
        if ($existing) {
            $id = $existing->id;
            unset($data['email']);
            $wpdb->update($wpdb->prefix . 'hs_candidates', $data, ['id' => $id]);
            return $id;
        }
        $wpdb->insert($wpdb->prefix . 'hs_candidates', $data);
        return $wpdb->insert_id;
    }

    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'hs_candidates', ['id' => (int)$id]);
    }
}
