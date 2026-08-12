<?php
if (!defined('ABSPATH')) exit;

class HS_Job {
    public static function find($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hs_jobs WHERE id = %d", $id));
        return $row ?: null;
    }

    public static function find_by_slug($slug) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hs_jobs WHERE slug = %s", $slug));
        return $row ?: null;
    }

    public static function query($args = []) {
        global $wpdb;
        $defaults = ['status' => 'published', 'page' => 1, 'per_page' => 20, 'search' => '', 'department' => '', 'location' => ''];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if ($args['status'] !== null) {
            $where[] = "status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['department'])) {
            $where[] = "department = %s";
            $params[] = $args['department'];
        }
        if (!empty($args['location'])) {
            $where[] = "location = %s";
            $params[] = $args['location'];
        }
        if (!empty($args['search'])) {
            $where[] = "(title LIKE %s OR description LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

            // Department restriction for Head of Dept role
    $allowed_depts = hs_user_get_allowed_departments();
    if (!empty($allowed_depts)) {
        $placeholders = implode(',', array_fill(0, count($allowed_depts), '%s'));
        $where[] = "department IN ({$placeholders})";
        foreach ($allowed_depts as $dept) {
            $params[] = $dept;
        }
    }

        $offset = ($args['page'] - 1) * $args['per_page'];
        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->prefix}hs_jobs WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = (int)$args['per_page'];
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        $total = $wpdb->get_var("SELECT FOUND_ROWS()");

        return ['items' => $results, 'total' => (int)$total, 'pages' => ceil($total / $args['per_page']), 'page' => $args['page']];
    }

    public static function save($data) {
        global $wpdb;
        $data = wp_parse_args($data, [
            'title' => '',
            'slug' => '',
            'department' => '',
            'location' => '',
            'employment_type' => 'full-time',
            'description' => '',
            'requirements' => '',
            'salary_min' => null,
            'salary_max' => null,
            'status' => 'draft',
        ]);

        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);
            $wpdb->update($wpdb->prefix . 'hs_jobs', $data, ['id' => $id]);
            return $id;
        }
        $data['slug'] = !empty($data['slug']) ? sanitize_title($data['slug']) : sanitize_title($data['title']);
        $base_slug = $data['slug'];
        $suffix = 2;
        while (self::find_by_slug($data['slug'])) {
            $data['slug'] = $base_slug . '-' . $suffix;
            $suffix++;
        }
        $data['created_by'] = get_current_user_id();
        $wpdb->insert($wpdb->prefix . 'hs_jobs', $data);
        return $wpdb->insert_id;
    }

    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'hs_jobs', ['id' => (int)$id]);
    }

    public static function count_active() {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_jobs WHERE status = 'published'");
    }

    public static function get_departments() {
        global $wpdb;
        return $wpdb->get_col("SELECT DISTINCT department FROM {$wpdb->prefix}hs_jobs WHERE department IS NOT NULL AND department != '' ORDER BY department");
    }

    public static function get_locations() {
        global $wpdb;
        return $wpdb->get_col("SELECT DISTINCT location FROM {$wpdb->prefix}hs_jobs WHERE location IS NOT NULL AND location != '' ORDER BY location");
    }
}
