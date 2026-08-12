<?php
if (!defined('ABSPATH')) exit;

class HS_Notifications {

    private static function signature_keys() {
        return [
            'email_appreciation_line',
            'email_closing_text',
            'email_logo_id',
            'email_dedication_line',
            'email_sender_name',
            'email_sender_role',
            'email_mobile_phone',
            'email_contact_email',
            'email_company_name',
            'email_company_tagline',
            'email_office_phone',
            'email_office_address',
            'notification_email',
        ];
    }

    private static function signature_config(array $overrides = []) {
        $config = [];
        foreach (self::signature_keys() as $key) {
            $config[$key] = array_key_exists($key, $overrides) ? $overrides[$key] : HS_Settings::get($key);
        }
        return $config;
    }

    private static function parse_template($template, $vars) {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }
        return $template;
    }

    private static function sender_vars(array $overrides = [], $preview = false) {
        $sig = self::signature_config($overrides);

        if ($preview) {
            $name = $sig['email_sender_name'];
            $role = $sig['email_sender_role'];
        } else {
            $user = wp_get_current_user();
            if ($user && $user->ID) {
                $name = $user->display_name ?: $sig['email_sender_name'];
                $role = get_user_meta($user->ID, 'hs_job_title', true) ?: $sig['email_sender_role'];
            } else {
                $name = $sig['email_sender_name'];
                $role = $sig['email_sender_role'];
            }
        }

        $contact = $sig['email_contact_email'] ?: $sig['notification_email'] ?: get_option('admin_email');
        $company = $sig['email_company_name'] ?: get_bloginfo('name');

        return [
            'sender_name' => $name,
            'sender_role' => $role,
            'mobile_phone' => $sig['email_mobile_phone'],
            'contact_email' => $contact,
            'company_name' => $company,
            'company_tagline' => $sig['email_company_tagline'],
            'office_phone' => $sig['email_office_phone'],
            'office_address' => $sig['email_office_address'],
        ];
    }

    private static function base_vars(array $overrides = [], $preview = false) {
        return array_merge(self::sender_vars($overrides, $preview), [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
        ]);
    }

    private static function sample_vars(array $overrides = []) {
        $portal = add_query_arg(['app_id' => 1, 'token' => 'preview'], site_url('/candidate-portal'));
        return array_merge(self::base_vars($overrides, true), [
            'candidate_name' => 'Jane Doe',
            'candidate_email' => 'jane.doe@example.com',
            'job_title' => 'Software Engineer',
            'portal_url' => $portal,
            'score' => 85,
            'admin_url' => admin_url('admin.php?page=hs-applications&action=view&id=1'),
            'old_status' => __('Shortlisted', 'hiring-system'),
            'new_status' => __('Interview', 'hiring-system'),
            'interview_date' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime('+7 days')),
            'duration' => 60,
            'type' => 'Video',
            'meeting_link' => 'Meeting Link: https://meet.example.com/preview',
        ]);
    }

    private static function text_to_html($text) {
        $text = esc_html($text);
        $text = nl2br($text);
        $text = preg_replace(
            '#(https?://[^\s<&]+)#',
            '<a href="$1" style="color:#2563eb;text-decoration:none;">$1</a>',
            $text
        );
        return $text;
    }

    private static function render_footer_html(array $overrides = [], $preview = false) {
        $sig = self::signature_config($overrides);
        $logo_id = (int) $sig['email_logo_id'];
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
        $company = $sig['email_company_name'] ?: get_bloginfo('name');
        $contact = $sig['email_contact_email'] ?: $sig['notification_email'] ?: get_option('admin_email');
        $sender = self::sender_vars($overrides, $preview);

        $appreciation = esc_html($sig['email_appreciation_line']);
        $closing = esc_html($sig['email_closing_text']);
        $dedication = esc_html($sig['email_dedication_line']);

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:8px;">';
        $html .= '<tr><td style="padding:24px 0 16px;border-top:1px solid #d1d5db;"></td></tr>';
        $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;line-height:1.5;">' . $appreciation . '</td></tr>';
        $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;line-height:1.5;padding-bottom:20px;">' . $closing . '</td></tr>';

        if ($logo_url) {
            $html .= '<tr><td style="padding-bottom:16px;">';
            $html .= '<img src="' . esc_url($logo_url) . '" width="180" height="60" alt="' . esc_attr($company) . '" style="display:block;border:0;object-fit:contain;max-width:180px;max-height:60px;">';
            $html .= '</td></tr>';
        }

        $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;padding-bottom:4px;">' . $dedication . '</td></tr>';

        if ($sender['sender_name']) {
            $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#111827;padding-bottom:4px;">' . esc_html($sender['sender_name']) . '</td></tr>';
        }
        if ($sender['sender_role']) {
            $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#6b7280;padding-bottom:8px;">' . esc_html($sender['sender_role']) . '</td></tr>';
        }

        $contact_parts = [];
        if ($sender['mobile_phone']) {
            $contact_parts[] = 'M: ' . esc_html($sender['mobile_phone']);
        }
        if ($contact) {
            $contact_parts[] = 'E: <a href="mailto:' . esc_attr($contact) . '" style="color:#2563eb;text-decoration:none;">' . esc_html($contact) . '</a>';
        }
        if ($contact_parts) {
            $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;padding-bottom:4px;">' . implode(' | ', $contact_parts) . '</td></tr>';
        }

        $company_line = esc_html($company);
        if ($sender['company_tagline']) {
            $company_line .= ' | ' . esc_html($sender['company_tagline']);
        }
        $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#111827;padding-bottom:4px;">' . $company_line . '</td></tr>';

        if ($sender['office_phone']) {
            $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;padding-bottom:4px;">T: ' . esc_html($sender['office_phone']) . '</td></tr>';
        }

        if ($sender['office_address']) {
            $address = nl2br(esc_html($sender['office_address']));
            $html .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;line-height:1.5;">A: ' . $address . '</td></tr>';
        }

        $html .= '</table>';
        return $html;
    }

    private static function build_html_email($template, $vars, array $signature_overrides = [], $preview = false) {
        $body = self::parse_template($template, $vars);
        $content = self::text_to_html($body);
        $footer = self::render_footer_html($signature_overrides, $preview);

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#111827;line-height:1.6;background:#f3f4f6;">'
            . '<div style="max-width:600px;margin:0 auto;padding:20px;">'
            . '<div style="background:#ffffff;padding:24px;border-radius:4px;">'
            . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#111827;line-height:1.6;">' . $content . '</div>'
            . $footer
            . '</div></div></body></html>';
    }

    public static function preview_email($subject_template, $body_template, array $signature_overrides = []) {
        $vars = self::sample_vars($signature_overrides);
        return [
            'subject' => self::parse_template($subject_template, $vars),
            'html' => self::build_html_email($body_template, $vars, $signature_overrides, true),
        ];
    }

    public static function signature_overrides_from_post(array $post) {
        return [
            'email_appreciation_line' => sanitize_text_field(wp_unslash($post['email_appreciation_line'] ?? '')),
            'email_closing_text' => sanitize_textarea_field(wp_unslash($post['email_closing_text'] ?? '')),
            'email_logo_id' => (int) ($post['email_logo_id'] ?? 0),
            'email_dedication_line' => sanitize_text_field(wp_unslash($post['email_dedication_line'] ?? '')),
            'email_sender_name' => sanitize_text_field(wp_unslash($post['email_sender_name'] ?? '')),
            'email_sender_role' => sanitize_text_field(wp_unslash($post['email_sender_role'] ?? '')),
            'email_mobile_phone' => sanitize_text_field(wp_unslash($post['email_mobile_phone'] ?? '')),
            'email_contact_email' => sanitize_email(wp_unslash($post['email_contact_email'] ?? '')),
            'email_company_name' => sanitize_text_field(wp_unslash($post['email_company_name'] ?? '')),
            'email_company_tagline' => sanitize_text_field(wp_unslash($post['email_company_tagline'] ?? '')),
            'email_office_phone' => sanitize_text_field(wp_unslash($post['email_office_phone'] ?? '')),
            'email_office_address' => sanitize_textarea_field(wp_unslash($post['email_office_address'] ?? '')),
            'notification_email' => sanitize_email(wp_unslash($post['notification_email'] ?? '')),
        ];
    }

    private static function build_plain_email($template, $vars, array $signature_overrides = []) {
        $body = self::parse_template($template, $vars);
        $sig = self::signature_config($signature_overrides);
        $sender = self::sender_vars($signature_overrides);
        $contact = $sender['contact_email'];

        $footer = $sig['email_appreciation_line'] . "\n"
            . $sig['email_closing_text'] . "\n\n"
            . $sig['email_dedication_line'] . "\n"
            . $sender['sender_name'] . "\n"
            . $sender['sender_role'] . "\n";

        if ($sender['mobile_phone'] || $contact) {
            $parts = [];
            if ($sender['mobile_phone']) $parts[] = 'M: ' . $sender['mobile_phone'];
            if ($contact) $parts[] = 'E: ' . $contact;
            $footer .= implode(' | ', $parts) . "\n";
        }

        $company_line = $sender['company_name'];
        if ($sender['company_tagline']) $company_line .= ' | ' . $sender['company_tagline'];
        $footer .= $company_line . "\n";

        if ($sender['office_phone']) $footer .= 'T: ' . $sender['office_phone'] . "\n";
        if ($sender['office_address']) $footer .= 'A: ' . $sender['office_address'] . "\n";

        return rtrim($body) . "\n\n" . ltrim($footer);
    }

    private static function send_mail($to, $subject, $html_body, $plain_body) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        add_action('phpmailer_init', [self::class, 'set_alt_body'], 10, 1);
        self::$alt_body = $plain_body;
        $sent = wp_mail($to, $subject, $html_body, $headers);
        remove_action('phpmailer_init', [self::class, 'set_alt_body'], 10);
        self::$alt_body = '';
        return $sent;
    }

    private static $alt_body = '';

    public static function set_alt_body($phpmailer) {
        if (self::$alt_body) {
            $phpmailer->AltBody = self::$alt_body;
        }
    }

    private static function portal_url($app_id, $access_token) {
        return add_query_arg([
            'app_id' => $app_id,
            'token' => $access_token,
        ], site_url('/candidate-portal'));
    }

    private static function status_labels() {
        return [
            'new' => __('New', 'hiring-system'),
            'shortlisted' => __('Shortlisted', 'hiring-system'),
            'interview' => __('Interview', 'hiring-system'),
            'offer' => __('Offer', 'hiring-system'),
            'hired' => __('Hired', 'hiring-system'),
            'rejected' => __('Rejected', 'hiring-system'),
            'withdrawn' => __('Withdrawn', 'hiring-system'),
        ];
    }

    private static function dispatch($to, $subject_template, $body_template, $vars) {
        $subject = self::parse_template($subject_template, $vars);
        $html = self::build_html_email($body_template, $vars);
        $plain = self::build_plain_email($body_template, $vars);
        return self::send_mail($to, $subject, $html, $plain);
    }

    public static function application_received($app_id) {
        $app = HS_Application::find_with_relations($app_id);
        if (!$app) return;

        $vars = array_merge(self::base_vars(), [
            'candidate_name' => $app->candidate->full_name,
            'candidate_email' => $app->candidate->email,
            'job_title' => $app->job->title,
            'portal_url' => self::portal_url($app_id, $app->access_token),
            'score' => round($app->score, 1),
            'admin_url' => admin_url('admin.php?page=hs-applications&action=view&id=' . $app_id),
        ]);

        self::dispatch(
            $app->candidate->email,
            HS_Settings::get('email_app_received_subject'),
            HS_Settings::get('email_app_received_body'),
            $vars
        );

        $admin_email = HS_Settings::get('notification_email') ?: get_option('admin_email');
        self::dispatch(
            $admin_email,
            HS_Settings::get('email_app_received_admin_subject'),
            HS_Settings::get('email_app_received_admin_body'),
            $vars
        );
    }

    public static function status_changed($app_id, $old_status, $new_status) {
        if ($old_status === $new_status) return;

        $app = HS_Application::find_with_relations($app_id);
        if (!$app) return;

        $labels = self::status_labels();
        $vars = array_merge(self::base_vars(), [
            'candidate_name' => $app->candidate->full_name,
            'job_title' => $app->job->title,
            'old_status' => $labels[$old_status] ?? $old_status,
            'new_status' => $labels[$new_status] ?? $new_status,
            'portal_url' => self::portal_url($app_id, $app->access_token),
        ]);

        if ($new_status === 'offer') {
            $subject_key = 'email_offer_subject';
            $body_key = 'email_offer_body';
        } elseif ($new_status === 'rejected') {
            $subject_key = 'email_rejected_subject';
            $body_key = 'email_rejected_body';
        } else {
            $subject_key = 'email_status_changed_subject';
            $body_key = 'email_status_changed_body';
        }

        self::dispatch(
            $app->candidate->email,
            HS_Settings::get($subject_key),
            HS_Settings::get($body_key),
            $vars
        );
    }

    public static function interview_scheduled($interview_id) {
        $interview = HS_Interview::find($interview_id);
        if (!$interview) return false;

        $app = HS_Application::find_with_relations($interview->application_id);
        if (!$app) return false;

        $vars = array_merge(self::base_vars(), [
            'candidate_name' => $app->candidate->full_name,
            'job_title' => $app->job->title,
            'interview_date' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($interview->scheduled_at)),
            'duration' => $interview->duration_minutes,
            'type' => ucfirst($interview->type),
            'meeting_link' => $interview->meeting_link ? 'Meeting Link: ' . $interview->meeting_link : '',
        ]);

        return self::dispatch(
            $app->candidate->email,
            HS_Settings::get('email_interview_subject'),
            HS_Settings::get('email_interview_body'),
            $vars
        );
    }
}
