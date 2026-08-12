<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_hs_update_pipeline', 'hs_ajax_update_pipeline');
add_action('wp_ajax_hs_preview_email', 'hs_ajax_preview_email');

function hs_ajax_preview_email() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'hs_admin_nonce') || !current_user_can('hs_manage_hiring')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'hiring-system')]);
    }

    $subject = sanitize_text_field(wp_unslash($_POST['preview_subject'] ?? ''));
    $body = sanitize_textarea_field(wp_unslash($_POST['preview_body'] ?? ''));
    $overrides = HS_Notifications::signature_overrides_from_post($_POST);

    wp_send_json_success(HS_Notifications::preview_email($subject, $body, $overrides));
}

function hs_ajax_update_pipeline() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'hs_admin_nonce') || !current_user_can('hs_manage_hiring')) {
        wp_send_json_error('Unauthorized');
    }
    $app_id = (int)($_POST['app_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    if ($app_id && $status) {
        HS_Application::update_status($app_id, $status, __('Updated via pipeline board', 'hiring-system'));
        wp_send_json_success(['status' => $status]);
    }
    wp_send_json_error('Invalid data');
}
