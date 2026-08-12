<?php
if (!defined('ABSPATH')) exit;

function hs_user_can_access_application($app_id) {
    if (current_user_can('hs_manage_hiring')) return true; // Admin/HR Manager = full access
    
    $user_id = get_current_user_id();
    $user_dept = get_user_meta($user_id, 'hs_department', true);
    
    if (!$user_dept || !current_user_can('hs_view_applications')) {
        return false;
    }
    
    // Head of Dept: check if application belongs to their department
    global $wpdb;
    $job_dept = $wpdb->get_var($wpdb->prepare(
        "SELECT j.department 
         FROM {$wpdb->prefix}hs_applications a
         JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id
         WHERE a.id = %d",
        $app_id
    ));
    
    return $job_dept === $user_dept;
}

function hs_user_can_access_job($job_id) {
    if (current_user_can('hs_manage_hiring')) return true;
    
    $user_id = get_current_user_id();
    $user_dept = get_user_meta($user_id, 'hs_department', true);
    
    if (!$user_dept || !current_user_can('hs_view_applications')) {
        return false;
    }
    
    $job = HS_Job::find($job_id);
    return $job && $job->department === $user_dept;
}

function hs_user_get_allowed_departments() {
    if (current_user_can('hs_manage_hiring')) return []; // Empty = all departments
    
    $user_dept = get_user_meta(get_current_user_id(), 'hs_department', true);
    return $user_dept ? [$user_dept] : [];
}
