<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_hs_update_status', 'hs_handle_update_status');

function hs_handle_update_status() {
    $app_id = (int)($_POST['app_id'] ?? 0);
    
    if (!current_user_can('hs_manage_hiring') && !hs_user_can_access_application($app_id)) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_update_status')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    $status = sanitize_text_field($_POST['status'] ?? '');
    $note = sanitize_textarea_field($_POST['note'] ?? '');
    
    if ($app_id && $status) {
        HS_Application::update_status($app_id, $status, $note);
        wp_redirect(add_query_arg(['page' => 'hs-applications', 'updated' => 1], admin_url('admin.php')));
        exit;
    }
    
    wp_redirect(admin_url('admin.php?page=hs-applications'));
    exit;
}

// ============================================
// BULK STATUS UPDATE HANDLER (admin-post.php)
// ============================================
add_action('admin_post_hs_bulk_status', 'hs_handle_bulk_status');

function hs_handle_bulk_status() {
    if (!current_user_can('hs_manage_hiring')) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_bulk_status')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    $ids = array_map('intval', $_POST['app_ids'] ?? []);
    $status = sanitize_text_field($_POST['bulk_status'] ?? '');
    
    if ($ids && $status) {
        if ($status === 'delete') {
            $deleted = HS_Application::bulk_delete($ids);
            wp_redirect(add_query_arg(['page' => 'hs-applications', 'bulk_deleted' => $deleted], admin_url('admin.php')));
            exit;
        }

        $updated = HS_Application::bulk_update_status($ids, $status);
        wp_redirect(add_query_arg(['page' => 'hs-applications', 'bulk_updated' => $updated], admin_url('admin.php')));
        exit;
    }
    
    wp_redirect(admin_url('admin.php?page=hs-applications'));
    exit;
}

// ============================================
// DELETE APPLICATION HANDLER (admin-post.php)
// ============================================
add_action('admin_post_hs_delete_application', 'hs_handle_delete_application');

function hs_handle_delete_application() {
    if (!current_user_can('hs_manage_hiring')) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_delete_application')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    $app_id = (int)($_POST['app_id'] ?? 0);

    if (!$app_id || !hs_user_can_access_application($app_id)) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    HS_Application::delete($app_id);

    wp_redirect(add_query_arg(['page' => 'hs-applications', 'deleted' => 1], admin_url('admin.php')));
    exit;
}

// ============================================
// JOB SAVE HANDLER (admin-post.php)
// ============================================
add_action('admin_post_hs_save_job', 'hs_handle_save_job');

function hs_handle_save_job() {
    if (!current_user_can('hs_edit_jobs')) {
        wp_die(__('You do not have permission to manage jobs.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_save_job')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    $data = [
        'title' => sanitize_text_field($_POST['title'] ?? ''),
        'slug' => sanitize_title($_POST['slug'] ?? $_POST['title'] ?? ''),
        'department' => sanitize_text_field($_POST['department'] ?? ''),
        'location' => sanitize_text_field($_POST['location'] ?? ''),
        'employment_type' => sanitize_text_field($_POST['employment_type'] ?? 'full-time'),
        'description' => wp_kses_post($_POST['description'] ?? ''),
        'requirements' => wp_kses_post($_POST['requirements'] ?? ''),
        'salary_min' => !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null,
        'salary_max' => !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null,
        'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
    ];
    
    if (!empty($_POST['id'])) {
        $data['id'] = (int)$_POST['id'];
    }
    
    HS_Job::save($data);
    
$redirect_url = admin_url('admin.php?page=hs-jobs&saved=1');
echo '<script>window.location.href = "' . esc_url($redirect_url) . '";</script>';
exit;
}

// ============================================
// ADD NOTE HANDLER (admin-post.php)
// ============================================
add_action('admin_post_hs_add_note', 'hs_handle_add_note');

function hs_handle_add_note() {
    $app_id = (int)($_POST['app_id'] ?? 0);
    
    if (!current_user_can('hs_view_applications') || !hs_user_can_access_application($app_id)) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_add_note')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    $note = sanitize_textarea_field($_POST['note'] ?? '');
    $is_private = !empty($_POST['is_private']);
    
    if ($app_id && $note) {
        HS_Note::add($app_id, $note, $is_private);
        wp_redirect(add_query_arg(['page' => 'hs-applications', 'action' => 'view', 'id' => $app_id, 'noted' => 1], admin_url('admin.php')));
        exit;
    }
    
    wp_redirect(admin_url('admin.php?page=hs-applications&action=view&id=' . $app_id));
    exit;
}

// ============================================
// INTERVIEW SCHEDULING HANDLER (admin-post.php)
// ============================================
add_action('admin_post_hs_schedule_interview', 'hs_handle_schedule_interview');

function hs_handle_schedule_interview() {
    // Capability check
    if (!current_user_can('hs_manage_interviews')) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    // Nonce verification
    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_schedule_interview')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    // Application access check
    $app_id = (int)($_POST['app_id'] ?? 0);
    if (!hs_user_can_access_application($app_id)) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    // Validate required fields
    $interviewer_id = (int)($_POST['interviewer_id'] ?? 0);
    $scheduled_at = sanitize_text_field($_POST['scheduled_at'] ?? '');
    
    if (!$app_id || !$interviewer_id || !$scheduled_at) {
        wp_redirect(add_query_arg([
            'page' => 'hs-applications',
            'action' => 'view',
            'id' => $app_id,
            'error' => 'missing_fields'
        ], admin_url('admin.php')));
        exit;
    }

    // Save interview
    $data = [
        'application_id' => $app_id,
        'interviewer_id' => $interviewer_id,
        'scheduled_at' => $scheduled_at,
        'duration_minutes' => (int)($_POST['duration'] ?? 60),
        'type' => sanitize_text_field($_POST['type'] ?? 'video'),
        'meeting_link' => esc_url_raw($_POST['meeting_link'] ?? ''),
    ];

    $interview_id = HS_Interview::save($data);
    
    // Log event
    HS_Event::log($app_id, 'interview_scheduled', 'Interview scheduled for ' . $scheduled_at);
    
    // Send notification
    $mail_sent = HS_Notifications::interview_scheduled($interview_id);
error_log('Interview email for application #' . $app_id . ': ' . ($mail_sent ? 'SENT' : 'FAILED'));

    // Redirect back with success
    wp_redirect(add_query_arg([
        'page' => 'hs-applications',
        'action' => 'view',
        'id' => $app_id,
        'interview' => 1
    ], admin_url('admin.php')));
    exit;
}


// ============================================
// SETTINGS SAVE HANDLER (admin-post.php)
// ============================================
add_action('admin_post_hs_save_settings', 'hs_handle_save_settings');

function hs_handle_save_settings() {
    if (!current_user_can('hs_manage_hiring')) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }

    if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_save_settings')) {
        wp_die(__('Security check failed.', 'hiring-system'));
    }

    HS_Settings::save([
    'notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
    'scoring_weights' => [
        'experience' => (float)($_POST['weight_experience'] ?? 0.35),
        'skills' => (float)($_POST['weight_skills'] ?? 0.40),
        'education' => (float)($_POST['weight_education'] ?? 0.10),
        'portfolio' => (float)($_POST['weight_portfolio'] ?? 0.15),
    ],
    'auto_reject_score' => (float)($_POST['auto_reject_score'] ?? 0),
    'gdpr_retention_days' => (int)($_POST['gdpr_retention_days'] ?? 365),
    'enable_rate_limiting' => !empty($_POST['enable_rate_limiting']) ? 1 : 0,

    // Email signature
    'email_appreciation_line' => sanitize_text_field($_POST['email_appreciation_line'] ?? ''),
    'email_closing_text' => sanitize_textarea_field($_POST['email_closing_text'] ?? ''),
    'email_logo_id' => (int) ($_POST['email_logo_id'] ?? 0),
    'email_dedication_line' => sanitize_text_field($_POST['email_dedication_line'] ?? ''),
    'email_sender_name' => sanitize_text_field($_POST['email_sender_name'] ?? ''),
    'email_sender_role' => sanitize_text_field($_POST['email_sender_role'] ?? ''),
    'email_mobile_phone' => sanitize_text_field($_POST['email_mobile_phone'] ?? ''),
    'email_contact_email' => sanitize_email($_POST['email_contact_email'] ?? ''),
    'email_company_name' => sanitize_text_field($_POST['email_company_name'] ?? ''),
    'email_company_tagline' => sanitize_text_field($_POST['email_company_tagline'] ?? ''),
    'email_office_phone' => sanitize_text_field($_POST['email_office_phone'] ?? ''),
    'email_office_address' => sanitize_textarea_field($_POST['email_office_address'] ?? ''),
    
    // Application received
    'email_app_received_subject' => sanitize_text_field($_POST['email_app_received_subject'] ?? ''),
    'email_app_received_body' => sanitize_textarea_field($_POST['email_app_received_body'] ?? ''),
    'email_app_received_admin_subject' => sanitize_text_field($_POST['email_app_received_admin_subject'] ?? ''),
    'email_app_received_admin_body' => sanitize_textarea_field($_POST['email_app_received_admin_body'] ?? ''),
    
    // Status changed
    'email_status_changed_subject' => sanitize_text_field($_POST['email_status_changed_subject'] ?? ''),
    'email_status_changed_body' => sanitize_textarea_field($_POST['email_status_changed_body'] ?? ''),
    
    // Offer
    'email_offer_subject' => sanitize_text_field($_POST['email_offer_subject'] ?? ''),
    'email_offer_body' => sanitize_textarea_field($_POST['email_offer_body'] ?? ''),
    
    // Rejected
    'email_rejected_subject' => sanitize_text_field($_POST['email_rejected_subject'] ?? ''),
    'email_rejected_body' => sanitize_textarea_field($_POST['email_rejected_body'] ?? ''),
    
    // Interview
    'email_interview_subject' => sanitize_text_field($_POST['email_interview_subject'] ?? ''),
    'email_interview_body' => sanitize_textarea_field($_POST['email_interview_body'] ?? ''),
]);

    wp_redirect(add_query_arg(['page' => 'hs-settings', 'saved' => 1], admin_url('admin.php')));
    exit;
}
