<?php
if (!defined('ABSPATH')) exit;

class HS_Application {
    public static function find($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hs_applications WHERE id = %d", $id));
    }

    public static function find_with_relations($id) {
        global $wpdb;
        $app = self::find($id);
        if (!$app) return null;
        $app->candidate = HS_Candidate::find($app->candidate_id);
        $app->job = HS_Job::find($app->job_id);
        $app->scores = HS_Score::get_by_application($id);
        $app->notes = HS_Note::get_by_application($id);
        $app->interviews = HS_Interview::get_by_application($id);
        $app->events = self::get_timeline($id);
        return $app;
    }

    public static function find_by_token($token) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hs_applications WHERE access_token = %s", $token));
    }

    public static function save($data) {
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}hs_applications WHERE candidate_id = %d AND job_id = %d",
            $data['candidate_id'], $data['job_id']
        ));
        if ($existing) {
            return new WP_Error('duplicate', __('You have already applied for this position.', 'hiring-system'));
        }
        $data['access_token'] = wp_generate_password(32, false);
        $result = $wpdb->insert($wpdb->prefix . 'hs_applications', $data);
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save application. Please try again.', 'hiring-system'));
        }
        return $wpdb->insert_id;
    }

    public static function update_status($app_id, $new_status, $note = '') {
        global $wpdb;
        $valid = ['new','shortlisted','interview','offer','hired','rejected','withdrawn'];
        if (!in_array($new_status, $valid, true)) return false;

        $app = self::find($app_id);
        if (!$app) return false;
        $old_status = $app->status;

        $wpdb->update($wpdb->prefix . 'hs_applications', ['status' => $new_status], ['id' => (int)$app_id]);
        HS_Event::log($app_id, 'status_changed', sprintf('Status: %s -> %s%s', $old_status, $new_status, $note ? ' | Note: ' . $note : ''));
        HS_Notifications::status_changed($app_id, $old_status, $new_status);
        return true;
    }

    public static function update_score($app_id, $score) {
        global $wpdb;
        return $wpdb->update($wpdb->prefix . 'hs_applications', ['score' => $score], ['id' => (int)$app_id]);
    }

    public static function query($args = []) {
        global $wpdb;
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'job_id' => 0,
            'search' => '',
            'employment_type' => '',
            'orderby' => 'applied_at',
            'order' => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = "a.status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['job_id'])) {
            $where[] = "a.job_id = %d";
            $params[] = (int)$args['job_id'];
        }
        if (!empty($args['search'])) {
            $where[] = "(c.full_name LIKE %s OR c.email LIKE %s OR j.title LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        if (!empty($args['employment_type'])) {
            $allowed_types = ['full-time', 'part-time', 'contract', 'remote', 'internship'];
            if (in_array($args['employment_type'], $allowed_types, true)) {
                $where[] = "j.employment_type = %s";
                $params[] = $args['employment_type'];
            }
        }

        $allowed_depts = hs_user_get_allowed_departments();
        if (!empty($allowed_depts)) {
            $where[] = "j.department IN (" . implode(',', array_fill(0, count($allowed_depts), '%s')) . ")";
            foreach ($allowed_depts as $dept) {
                $params[] = $dept;
            }
        }

        $allowed_orderby = ['applied_at', 'score', 'full_name', 'title', 'experience_years'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'applied_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        switch ($orderby) {
            case 'full_name':
                $order_sql = "c.full_name {$order}";
                break;
            case 'title':
                $order_sql = "j.title {$order}";
                break;
            case 'experience_years':
                $order_sql = "c.experience_years {$order}";
                break;
            default:
                $order_sql = "a.{$orderby} {$order}";
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS a.*, c.full_name, c.email, c.experience_years, j.title as job_title, j.employment_type 
                FROM {$wpdb->prefix}hs_applications a
                LEFT JOIN {$wpdb->prefix}hs_candidates c ON a.candidate_id = c.id
                LEFT JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$order_sql} LIMIT %d OFFSET %d";

        $params[] = (int)$args['per_page'];
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        $total = $wpdb->get_var("SELECT FOUND_ROWS()");

        return ['items' => $results, 'total' => (int)$total, 'pages' => ceil($total / $args['per_page']), 'page' => $args['page']];
    }

    public static function get_timeline($application_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.display_name as user_name 
             FROM {$wpdb->prefix}hs_events e
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.application_id = %d ORDER BY e.created_at DESC",
            $application_id
        ));
    }

    public static function get_pipeline_counts() {
    global $wpdb;
    $allowed_depts = hs_user_get_allowed_departments();
    
    if (!empty($allowed_depts)) {
        $dept_in = "'" . implode("','", array_map('esc_sql', $allowed_depts)) . "'";
        $results = $wpdb->get_results(
            "SELECT a.status, COUNT(*) as count 
             FROM {$wpdb->prefix}hs_applications a
             JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id
             WHERE j.department IN ({$dept_in})
             GROUP BY a.status"
        );
    } else {
        $results = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$wpdb->prefix}hs_applications GROUP BY status");
    }
    
    $counts = array_fill_keys(['new', 'shortlisted', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'], 0);
    foreach ($results as $row) {
        $counts[$row->status] = (int)$row->count;
    }
    return $counts;
}

    public static function bulk_update_status($ids, $status) {
        $success = 0;
        foreach ($ids as $id) {
            if (self::update_status((int)$id, $status)) $success++;
        }
        return $success;
    }

    public static function delete($id) {
        global $wpdb;
        $app = self::find($id);
        if (!$app) {
            return false;
        }

        $candidate_id = (int)$app->candidate_id;

        HS_Score::delete_by_application($id);
        $wpdb->delete($wpdb->prefix . 'hs_notes', ['application_id' => (int)$id]);
        $wpdb->delete($wpdb->prefix . 'hs_events', ['application_id' => (int)$id]);
        $wpdb->delete($wpdb->prefix . 'hs_interviews', ['application_id' => (int)$id]);
        $wpdb->delete($wpdb->prefix . 'hs_applications', ['id' => (int)$id]);

        $remaining = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hs_applications WHERE candidate_id = %d",
            $candidate_id
        ));

        if ($remaining === 0) {
            $candidate = HS_Candidate::find($candidate_id);
            if ($candidate) {
                if (!empty($candidate->cv_attachment_id)) {
                    wp_delete_attachment((int)$candidate->cv_attachment_id, true);
                }
                if (!empty($candidate->portfolio_attachment_id)) {
                    wp_delete_attachment((int)$candidate->portfolio_attachment_id, true);
                }
                HS_Candidate::delete($candidate_id);
            }
        }

        delete_transient('hs_dashboard_stats');
        return true;
    }

    public static function bulk_delete($ids) {
        $success = 0;
        foreach ($ids as $id) {
            if (!hs_user_can_access_application((int)$id)) {
                continue;
            }
            if (self::delete((int)$id)) {
                $success++;
            }
        }
        return $success;
    }
}
