<?php
/**
 * Plugin Name: Hiring System - ATS
 * Description: Complete Applicant Tracking System - Jobs, Applications, Scoring, Interviews, Notifications
 * Version: 2.0.2
 * Author: Hiring System
 * Text Domain: hiring-system
 */

if (!defined('ABSPATH')) exit;

define('HS_VERSION', '2.0.1');
define('HS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HS_DB_VERSION', '2.0.3');

require_once HS_PLUGIN_DIR . 'includes/loader.php';

register_activation_hook(__FILE__, ['HS_Database', 'activate']);
register_deactivation_hook(__FILE__, ['HS_Database', 'deactivate']);
register_uninstall_hook(__FILE__, ['HS_Database', 'uninstall']);

add_action('init', ['HS_Database', 'maybe_upgrade'], 1);

add_action('show_user_profile', 'hs_user_dept_field');
add_action('edit_user_profile', 'hs_user_dept_field');
add_action('personal_options_update', 'hs_save_user_dept_field');
add_action('edit_user_profile_update', 'hs_save_user_dept_field');

add_filter('wp_mail_from', function ($from_email) {
    $custom = HS_Settings::get('notification_email');
    return $custom ?: get_option('admin_email');
});

add_filter('wp_mail_from_name', function ($from_name) {
    return get_bloginfo('name');
});

HS_Admin::init();
HS_Shortcodes::init();
