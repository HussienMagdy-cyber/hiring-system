<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    register_rest_route('hs/v1', '/apply', [
        'methods' => 'POST',
        'callback' => 'hs_rest_apply',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('hs/v1', '/jobs', [
        'methods' => 'GET',
        'callback' => 'hs_rest_jobs',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('hs/v1', '/jobs/(?P<slug>[a-z0-9-]+)', [
        'methods' => 'GET',
        'callback' => 'hs_rest_job_detail',
        'permission_callback' => '__return_true',
    ]);
});

function hs_rest_apply(WP_REST_Request $request) {
    if (!HS_Rate_Limiter::check('apply', 20, HOUR_IN_SECONDS)) {
        return new WP_Error('rate_limited', __('Too many applications. Please try again later.', 'hiring-system'), ['status' => 429]);
    }

    $job_id = (int)$request->get_param('job_id');
    $job = HS_Job::find($job_id);
    if (!$job_id || !$job || $job->status !== 'published') {
        return new WP_Error('invalid_job', __('Job not found or no longer accepting applications.', 'hiring-system'), ['status' => 400]);
    }

    $files = $request->get_file_params();
    $cv_id = null;
    $portfolio_id = null;
    
    // Handle CV upload
    if (!empty($files['cv'])) {
        $file = $files['cv'];
        $allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file['type'], $allowed, true)) {
            return new WP_Error('invalid_file', __('Only PDF, DOC, and DOCX files are allowed.', 'hiring-system'), ['status' => 400]);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File exceeds 5MB limit.', 'hiring-system'), ['status' => 400]);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($file, ['test_form' => false, 'mimes' => [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]]);
                if (!empty($uploaded['url'])) {
            $cv_id = wp_insert_attachment([
                'post_mime_type' => $uploaded['type'],
                'post_title' => sanitize_file_name($file['name']),
                'post_status' => 'inherit',
            ], $uploaded['file']);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            wp_update_attachment_metadata($cv_id, wp_generate_attachment_metadata($cv_id, $uploaded['file']));
        }
    }

    // Handle portfolio upload
    if (!empty($files['portfolio'])) {
        $port_file = $files['portfolio'];
        $allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (in_array($port_file['type'], $allowed, true)) {
            if ($port_file['size'] <= 5 * 1024 * 1024) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                $port_uploaded = wp_handle_upload($port_file, ['test_form' => false, 'mimes' => [
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ]]);
                if (!empty($port_uploaded['url'])) {
                    $portfolio_id = wp_insert_attachment([
                        'post_mime_type' => $port_uploaded['type'],
                        'post_title' => sanitize_file_name($port_file['name']),
                        'post_status' => 'inherit',
                    ], $port_uploaded['file']);
                    wp_update_attachment_metadata($portfolio_id, wp_generate_attachment_metadata($portfolio_id, $port_uploaded['file']));
                }
            }
        }
    }

    if (empty($request->get_param('privacy_consent'))) {
        return new WP_Error('no_consent', __('You must agree to the privacy policy.', 'hiring-system'), ['status' => 400]);
    }

    $candidate_id = HS_Candidate::save([
        'full_name' => sanitize_text_field($request->get_param('full_name')),
        'email' => sanitize_email($request->get_param('email')),
        'phone' => sanitize_text_field($request->get_param('phone') ?: ''),
        'country' => sanitize_text_field($request->get_param('country') ?: ''),
        'city' => sanitize_text_field($request->get_param('city') ?: ''),
        'linkedin_url' => esc_url_raw($request->get_param('linkedin_url') ?: ''),
        'portfolio_url' => esc_url_raw($request->get_param('portfolio_url') ?: ''),
        'experience_years' => absint($request->get_param('experience_years') ?: 0),
        'education_level' => sanitize_text_field($request->get_param('education_level') ?: ''),
        'skills' => sanitize_textarea_field($request->get_param('skills') ?: ''),
        'cv_attachment_id' => $cv_id,
        'portfolio_attachment_id' => $portfolio_id,
        'privacy_consent' => 1,
        'ip_address' => HS_Rate_Limiter::get_client_ip()
    ]);

    if (!$candidate_id || is_wp_error($candidate_id)) {
        return new WP_Error('candidate_error', __('Failed to save candidate information.', 'hiring-system'), ['status' => 500]);
    }

    $app_id = HS_Application::save([
        'candidate_id' => $candidate_id,
        'job_id' => $job_id,
        'status' => 'new',
        'cover_letter' => sanitize_textarea_field($request->get_param('cover_letter') ?: ''),
        'source' => sanitize_text_field($request->get_param('source') ?: 'website'),
    ]);

    if (is_wp_error($app_id)) {
        return $app_id;
    }

    if (!$app_id) {
        return new WP_Error('application_error', __('Failed to save application.', 'hiring-system'), ['status' => 500]);
    }

    HS_Event::log($app_id, 'applied', __('Application submitted via website', 'hiring-system'));
    $score = hs_calculate_score($app_id, $candidate_id, $job_id);
    HS_Notifications::application_received($app_id);

    $app_record = HS_Application::find($app_id);
    if (!$app_record) {
        return new WP_Error('application_error', __('Application saved but could not be retrieved.', 'hiring-system'), ['status' => 500]);
    }

    $portal_url = add_query_arg([
        'app_id' => $app_id,
        'token' => $app_record->access_token,
    ], site_url('/candidate-portal'));

    return new WP_REST_Response([
        'success' => true,
        'application_id' => $app_id,
        'score' => $score,
        'portal_url' => $portal_url,
        'message' => __('Application submitted successfully!', 'hiring-system'),
    ], 201);
}

function hs_rest_jobs() {
    $jobs = HS_Job::query(['status' => 'published']);
    return new WP_REST_Response($jobs['items']);
}

function hs_rest_job_detail($request) {
    $job = HS_Job::find_by_slug($request['slug']);
    if (!$job || $job->status !== 'published') {
        return new WP_Error('not_found', __('Job not found.', 'hiring-system'), ['status' => 404]);
    }
    return new WP_REST_Response($job);
}
