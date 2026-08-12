<?php
if (!defined('ABSPATH')) exit;

class HS_Settings {
private static $defaults = [
    'notification_email' => '',
    'scoring_weights' => [
        'experience' => 0.35,
        'skills' => 0.40,
        'education' => 0.10,
        'portfolio' => 0.15,
    ],
    'auto_reject_score' => 0,
    'gdpr_retention_days' => 365,
    'enable_rate_limiting' => 1,

    // Email signature (HTML footer appended to all emails)
    'email_appreciation_line' => 'With Appreciation,',
    'email_closing_text' => 'We always stand available to provide any clarifications or assistance whenever needed.',
    'email_logo_id' => 0,
    'email_dedication_line' => 'Dedicated to your success,',
    'email_sender_name' => '',
    'email_sender_role' => 'HR Team',
    'email_mobile_phone' => '',
    'email_contact_email' => '',
    'email_company_name' => '',
    'email_company_tagline' => '',
    'email_office_phone' => '',
    'email_office_address' => '',

    // Application received
    'email_app_received_subject' => 'Application Received: {job_title}',
    'email_app_received_body' => "Hi {candidate_name},\n\nWe've received your application for '{job_title}'.\n\nTrack your status: {portal_url}",
    'email_app_received_admin_subject' => 'New Application: {candidate_name} for {job_title}',
    'email_app_received_admin_body' => "A new application has been received.\n\nCandidate: {candidate_name}\nEmail: {candidate_email}\nJob: {job_title}\nScore: {score}/100\n\nView in admin: {admin_url}",

    // Status changed — general
    'email_status_changed_subject' => 'Application Update: {job_title}',
    'email_status_changed_body' => "Hi {candidate_name},\n\nYour application for '{job_title}' has been updated.\n\nStatus: {old_status} -> {new_status}\n\nTrack your application: {portal_url}",

    // Status changed — offer (special)
    'email_offer_subject' => 'Congratulations! Job Offer for {job_title}',
    'email_offer_body' => "Hi {candidate_name},\n\nWe are delighted to offer you the position of '{job_title}'.\n\nPlease log in to your candidate portal to review and respond to your offer:\n{portal_url}\n\nWe look forward to having you on the team!",

    // Status changed — rejected (special)
    'email_rejected_subject' => 'Update on your application for {job_title}',
    'email_rejected_body' => "Hi {candidate_name},\n\nThank you for your interest in the '{job_title}' position and for the time you invested in your application.\n\nAfter careful consideration, we have decided to move forward with other candidates whose experience more closely aligns with our current needs.\n\nWe wish you all the best in your job search and future endeavors.",

    // Interview scheduled
    'email_interview_subject' => 'Interview Scheduled: {job_title}',
    'email_interview_body' => "Hi {candidate_name},\n\nYou have been scheduled for an interview for '{job_title}'.\n\nDate: {interview_date}\nDuration: {duration} minutes\nType: {type}\n{meeting_link}",
];

    public static function get($key = null) {
        $settings = wp_parse_args(get_option('hs_settings', []), self::$defaults);
        if ($key === null) return $settings;
        return $settings[$key] ?? null;
    }

    public static function save($data) {
        $current = self::get();
        $new = wp_parse_args($data, $current);
        update_option('hs_settings', $new);
        return true;
    }
}
