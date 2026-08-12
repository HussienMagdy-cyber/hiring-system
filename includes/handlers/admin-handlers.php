<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', 'hs_handle_dept_heads_post', 5);

function hs_handle_dept_heads_post() {
    // Only run on our page with POST data
    if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'hs-dept-heads') {
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['hs_action'])) {
        return;
    }

    if (!current_user_can('hs_manage_hiring')) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_dept_heads')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    $action = sanitize_text_field($_POST['hs_action']);

    if ($action === 'assign_dept' && !empty($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $dept = sanitize_text_field($_POST['department'] ?? '');
        update_user_meta($user_id, 'hs_department', $dept);
        wp_redirect(add_query_arg(['page' => 'hs-dept-heads', 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    if ($action === 'remove_dept' && !empty($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        delete_user_meta($user_id, 'hs_department');
        wp_redirect(add_query_arg(['page' => 'hs-dept-heads', 'removed' => 1], admin_url('admin.php')));
        exit;
    }

    if ($action === 'add_head' && !empty($_POST['new_user_id'])) {
        $user_id = (int)$_POST['new_user_id'];
        $user = get_user_by('id', $user_id);
        if ($user) {
            $user->add_role('hs_head_dept');
            $dept = sanitize_text_field($_POST['department'] ?? '');
            if ($dept) {
                update_user_meta($user_id, 'hs_department', $dept);
            }
        }
        wp_redirect(add_query_arg(['page' => 'hs-dept-heads', 'added' => 1], admin_url('admin.php')));
        exit;
    }

    if ($action === 'remove_role' && !empty($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $user = get_user_by('id', $user_id);
        if ($user) {
            $user->remove_role('hs_head_dept');
            delete_user_meta($user_id, 'hs_department');
        }
        wp_redirect(add_query_arg(['page' => 'hs-dept-heads', 'role_removed' => 1], admin_url('admin.php')));
        exit;
    }
}
