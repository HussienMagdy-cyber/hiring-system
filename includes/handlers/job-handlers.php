<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_hs_delete_job', 'hs_handle_delete_job');

function hs_handle_delete_job() {
    if (!current_user_can('hs_edit_jobs')) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_delete_job')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    $job_id = (int)($_POST['job_id'] ?? 0);
    
    if ($job_id && !hs_user_can_access_job($job_id)) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }
    
    if ($job_id) {
        HS_Job::delete($job_id);
    }
    
    wp_redirect(admin_url('admin.php?page=hs-jobs&deleted=1'));
    exit;
}
