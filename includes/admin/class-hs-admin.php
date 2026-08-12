<?php
if (!defined('ABSPATH')) exit;

class HS_Admin {
    public static function init() {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_init', [self::class, 'handle_actions']);
    }

public static function menu() {
    add_menu_page('Hiring', 'Hiring', 'hs_view_applications', 'hiring-system', [self::class, 'dashboard'], 'dashicons-businessman', 30);
    add_submenu_page('hiring-system', 'Dashboard', 'Dashboard', 'hs_view_applications', 'hiring-system', [self::class, 'dashboard']);
    
    if (current_user_can('hs_edit_jobs')) {
        add_submenu_page('hiring-system', 'Jobs', 'Jobs', 'hs_edit_jobs', 'hs-jobs', [self::class, 'jobs']);
    }
    
    add_submenu_page('hiring-system', 'Applications', 'Applications', 'hs_view_applications', 'hs-applications', [self::class, 'applications']);
    add_submenu_page('hiring-system', 'Pipeline', 'Pipeline', 'hs_view_applications', 'hs-pipeline', [self::class, 'pipeline']);
    
    // NEW: Department Heads management page
    if (current_user_can('hs_manage_hiring')) {
        add_submenu_page('hiring-system', 'Department Heads', 'Department Heads', 'hs_manage_hiring', 'hs-dept-heads', [self::class, 'dept_heads']);
    }
    
    if (current_user_can('hs_manage_hiring')) {
        add_submenu_page('hiring-system', 'Settings', 'Settings', 'hs_manage_hiring', 'hs-settings', [self::class, 'settings']);
    }
}

    public static function assets($hook) {
        if (strpos($hook, 'hiring') === false && strpos($hook, 'hs-') === false) return;
        wp_enqueue_style('hs-admin', HS_PLUGIN_URL . 'admin.css', [], HS_VERSION);

        if (strpos($hook, 'hs-settings') !== false) {
            wp_enqueue_media();
            wp_enqueue_script(
                'hs-settings',
                HS_PLUGIN_URL . 'hs-settings.js',
                ['jquery', 'media-upload', 'media-views'],
                HS_VERSION,
                true
            );
            wp_localize_script('hs-settings', 'hs_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hs_admin_nonce'),
            ]);
            wp_localize_script('hs-settings', 'hs_settings', [
                'i18n' => [
                    'select_logo' => __('Select Email Logo', 'hiring-system'),
                    'use_logo' => __('Use this logo', 'hiring-system'),
                ],
            ]);
            return;
        }

        wp_enqueue_script('hs-admin', HS_PLUGIN_URL . 'admin.js', ['jquery'], HS_VERSION, true);
        wp_localize_script('hs-admin', 'hs_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hs_admin_nonce'),
        ]);
    }

    public static function handle_actions() {
        if (!isset($_POST['hs_action'])) return;

         // DEBUG
    error_log('HS ACTION: ' . sanitize_text_field($_POST['hs_action']));
    error_log('HS NONCE: ' . ($_POST['hs_nonce'] ?? 'MISSING'));

        $action = sanitize_text_field($_POST['hs_action']);

if (!wp_verify_nonce($_POST['hs_nonce'] ?? '', 'hs_dept_heads')) {
            wp_die(__('Security check failed.', 'hiring-system'));
        }

        
    }

    public static function dashboard() {
    $allowed_depts = hs_user_get_allowed_departments();
    $dept_where = '';
    $dept_join = '';
    
    if (!empty($allowed_depts)) {
        $dept_in = "'" . implode("','", array_map('esc_sql', $allowed_depts)) . "'";
        $dept_where = " AND j.department IN ({$dept_in})";
        $dept_join = "JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id";
    }
    
    $stats = get_transient('hs_dashboard_stats');
    if (false === $stats) {
        global $wpdb;
        
        if (!empty($allowed_depts)) {
            $dept_in = "'" . implode("','", array_map('esc_sql', $allowed_depts)) . "'";
            $stats = [
                'active_jobs' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_jobs WHERE status = 'published' AND department IN ({$dept_in})"),
                'total_candidates' => (int)$wpdb->get_var("SELECT COUNT(DISTINCT c.id) FROM {$wpdb->prefix}hs_candidates c JOIN {$wpdb->prefix}hs_applications a ON c.id = a.candidate_id JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id WHERE 1=1 {$dept_where}"),
                'total_applications' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_applications a JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id WHERE 1=1 {$dept_where}"),
                'pipeline' => HS_Application::get_pipeline_counts(),
                'recent' => HS_Event::get_recent(5),
                'avg_score' => (float)$wpdb->get_var("SELECT AVG(a.score) FROM {$wpdb->prefix}hs_applications a JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id WHERE a.score > 0 {$dept_where}"),
            ];
        } else {
            $stats = [
                'active_jobs' => HS_Job::count_active(),
                'total_candidates' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_candidates"),
                'total_applications' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_applications"),
                'pipeline' => HS_Application::get_pipeline_counts(),
                'recent' => HS_Event::get_recent(5),
                'avg_score' => (float)$wpdb->get_var("SELECT AVG(score) FROM {$wpdb->prefix}hs_applications WHERE score > 0"),
            ];
        }
        set_transient('hs_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS);
    }
        ?>
        <div class="wrap">
            <h1><?php _e('Hiring Dashboard', 'hiring-system'); ?></h1>
            <div class="hs-stats-grid">
                <div class="hs-stat-card">
                    <div class="hs-stat-number"><?php echo $stats['active_jobs']; ?></div>
                    <div class="hs-stat-label"><?php _e('Active Jobs', 'hiring-system'); ?></div>
                </div>
                <div class="hs-stat-card">
                    <div class="hs-stat-number"><?php echo $stats['total_candidates']; ?></div>
                    <div class="hs-stat-label"><?php _e('Candidates', 'hiring-system'); ?></div>
                </div>
                <div class="hs-stat-card">
                    <div class="hs-stat-number"><?php echo $stats['total_applications']; ?></div>
                    <div class="hs-stat-label"><?php _e('Applications', 'hiring-system'); ?></div>
                </div>
                <div class="hs-stat-card">
                    <div class="hs-stat-number"><?php echo $stats['avg_score'] ? round($stats['avg_score'], 1) : '—'; ?></div>
                    <div class="hs-stat-label"><?php _e('Avg Score', 'hiring-system'); ?></div>
                </div>
            </div>

            <div class="hs-pipeline-overview">
                <h2><?php _e('Pipeline Overview', 'hiring-system'); ?></h2>
                <div class="hs-pipeline-bar">
                    <?php
                    $status_labels = ['new'=>'New','shortlisted'=>'Shortlisted','interview'=>'Interview','offer'=>'Offer','hired'=>'Hired','rejected'=>'Rejected'];
                    $colors = ['new'=>'#e3f2fd','shortlisted'=>'#f3e5f5','interview'=>'#e8f5e9','offer'=>'#fff8e1','hired'=>'#e8f5e9','rejected'=>'#ffebee'];
                    $total = array_sum($stats['pipeline']);
                    foreach ($status_labels as $status => $label):
                        $count = $stats['pipeline'][$status] ?? 0;
                        $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                    ?>
                    <div class="hs-pipeline-segment" style="width:<?php echo $pct; ?>%;background:<?php echo $colors[$status]; ?>" title="<?php echo esc_attr($label . ': ' . $count); ?>">
                        <span><?php echo $count; ?></span>
                        <small><?php echo $label; ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hs-recent-activity">
                <h2><?php _e('Recent Activity', 'hiring-system'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Candidate', 'hiring-system'); ?></th>
                            <th><?php _e('Job', 'hiring-system'); ?></th>
                            <th><?php _e('Event', 'hiring-system'); ?></th>
                            <th><?php _e('By', 'hiring-system'); ?></th>
                            <th><?php _e('Time', 'hiring-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent'] as $event): ?>
                        <tr>
                            <td><?php echo esc_html($event->candidate_name ?: 'Unknown'); ?></td>
                            <td><?php echo esc_html($event->job_title ?: 'Unknown'); ?></td>
                            <td><span class="hs-badge hs-badge-<?php echo esc_attr($event->event_type); ?>"><?php echo esc_html($event->event_type); ?></span></td>
                            <td><?php echo esc_html($event->user_name ?: 'System'); ?></td>
                            <td><?php echo human_time_diff(strtotime($event->created_at), current_time('timestamp')); ?> ago</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <style>
        .hs-stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin:20px 0; }
        .hs-stat-card { background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); text-align:center; }
        .hs-stat-number { font-size:28px; font-weight:700; color:#2271b1; }
        .hs-stat-label { color:#646970; margin-top:5px; }
        .hs-pipeline-overview { background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin:20px 0; }
        .hs-pipeline-bar { display:flex; height:60px; border-radius:6px; overflow:hidden; margin-top:15px; }
        .hs-pipeline-segment { display:flex; flex-direction:column; align-items:center; justify-content:center; min-width:40px; border-right:1px solid rgba(0,0,0,0.05); }
        .hs-pipeline-segment span { font-weight:700; font-size:14px; }
        .hs-pipeline-segment small { font-size:10px; opacity:0.7; }
        .hs-recent-activity { background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin:20px 0; }
        .hs-badge { text-transform:capitalize; padding:3px 10px; border-radius:4px; font-size:12px; background:#f0f0f1; color:#646970; }
        .hs-badge-applied { background:#e3f2fd; color:#1565c0; }
        .hs-badge-status_changed { background:#fff3e0; color:#e65100; }
        .hs-badge-interview_scheduled { background:#f3e5f5; color:#6a1b9a; }
        @media (max-width:782px) { .hs-stats-grid { grid-template-columns:repeat(2,1fr); } }
        </style>
        <?php
    }

    public static function jobs() {
        if (!current_user_can('hs_edit_jobs')) {
            wp_die(__('You do not have permission to manage jobs.', 'hiring-system'));
        }

        $action = $_GET['action'] ?? 'list';

        

        if ($action === 'delete' && !empty($_GET['id'])) {
    if (!hs_user_can_access_job((int)$_GET['id'])) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }
    if (!wp_verify_nonce($_GET['hs_nonce'] ?? '', 'hs_delete_job')) {
                wp_die(__('Security check failed.', 'hiring-system'));
            }
            HS_Job::delete((int)$_GET['id']);
            wp_redirect(admin_url('admin.php?page=hs-jobs&deleted=1'));
            exit;
        }

        if ($action === 'duplicate' && !empty($_GET['id'])) {
    if (!hs_user_can_access_job((int)$_GET['id'])) {
        wp_die(__('Unauthorized.', 'hiring-system'));
    }
    if (!wp_verify_nonce($_GET['hs_nonce'] ?? '', 'hs_duplicate_job')) {
                wp_die(__('Security check failed.', 'hiring-system'));
            }
            $job = HS_Job::find((int)$_GET['id']);
            if ($job) {
                $data = (array)$job;
                unset($data['id'], $data['created_at'], $data['updated_at']);
                $data['title'] = $data['title'] . ' (Copy)';
                $data['slug'] = '';
                $data['status'] = 'draft';
                HS_Job::save($data);
            }
            wp_redirect(admin_url('admin.php?page=hs-jobs&duplicated=1'));
            exit;
        }

        if ($action === 'edit' || $action === 'add') {
    $job = !empty($_GET['id']) ? HS_Job::find((int)$_GET['id']) : null;
    if ($job && !hs_user_can_access_job($job->id)) {
        wp_die(__('You do not have permission to edit this job.', 'hiring-system'));
    }
            $departments = HS_Job::get_departments();
            $locations = HS_Job::get_locations();
            ?>
            <div class="wrap">
                <h1><?php echo $job ? __('Edit Job', 'hiring-system') : __('Add New Job', 'hiring-system'); ?></h1>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('hs_save_job', 'hs_nonce'); ?>
    <input type="hidden" name="action" value="hs_save_job">
                    <?php if ($job): ?><input type="hidden" name="id" value="<?php echo $job->id; ?>"><?php endif; ?>
                    <table class="form-table">
                        <tr><th><?php _e('Title', 'hiring-system'); ?></th><td><input type="text" name="title" class="regular-text" value="<?php echo esc_attr($job->title ?? ''); ?>" required></td></tr>
                        <tr><th><?php _e('Slug', 'hiring-system'); ?></th><td><input type="text" name="slug" class="regular-text" value="<?php echo esc_attr($job->slug ?? ''); ?>" placeholder="auto-generated"></td></tr>
                        <tr><th><?php _e('Department', 'hiring-system'); ?></th><td>
                            <input type="text" name="department" class="regular-text" value="<?php echo esc_attr($job->department ?? ''); ?>" list="dept-list">
                            <datalist id="dept-list">
                                <?php foreach ($departments as $dept): ?><option value="<?php echo esc_attr($dept); ?>"><?php endforeach; ?>
                            </datalist>
                        </td></tr>
                        <tr><th><?php _e('Location', 'hiring-system'); ?></th><td>
                            <input type="text" name="location" class="regular-text" value="<?php echo esc_attr($job->location ?? ''); ?>" list="loc-list">
                            <datalist id="loc-list">
                                <?php foreach ($locations as $loc): ?><option value="<?php echo esc_attr($loc); ?>"><?php endforeach; ?>
                            </datalist>
                        </td></tr>
                        <tr><th><?php _e('Employment Type', 'hiring-system'); ?></th><td>
                            <select name="employment_type">
                                <option value="full-time" <?php selected($job->employment_type ?? '', 'full-time'); ?>><?php _e('Full-time', 'hiring-system'); ?></option>
                                <option value="part-time" <?php selected($job->employment_type ?? '', 'part-time'); ?>><?php _e('Part-time', 'hiring-system'); ?></option>
                                <option value="contract" <?php selected($job->employment_type ?? '', 'contract'); ?>><?php _e('Contract', 'hiring-system'); ?></option>
                                <option value="remote" <?php selected($job->employment_type ?? '', 'remote'); ?>><?php _e('Remote', 'hiring-system'); ?></option>
                                <option value="internship" <?php selected($job->employment_type ?? '', 'internship'); ?>><?php _e('Internship', 'hiring-system'); ?></option>
                            </select>
                        </td></tr>
                        <tr><th><?php _e('Salary Range', 'hiring-system'); ?></th><td>
                            <input type="number" name="salary_min" value="<?php echo esc_attr($job->salary_min ?? ''); ?>" placeholder="Min" style="width:120px;"> -
                            <input type="number" name="salary_max" value="<?php echo esc_attr($job->salary_max ?? ''); ?>" placeholder="Max" style="width:120px;">
                        </td></tr>
                        <tr><th><?php _e('Status', 'hiring-system'); ?></th><td>
                            <select name="status">
                                <option value="draft" <?php selected($job->status ?? '', 'draft'); ?>><?php _e('Draft', 'hiring-system'); ?></option>
                                <option value="published" <?php selected($job->status ?? '', 'published'); ?>><?php _e('Published', 'hiring-system'); ?></option>
                                <option value="closed" <?php selected($job->status ?? '', 'closed'); ?>><?php _e('Closed', 'hiring-system'); ?></option>
                            </select>
                        </td></tr>
                        <tr><th><?php _e('Description', 'hiring-system'); ?></th><td><?php wp_editor($job->description ?? '', 'description', ['textarea_name' => 'description', 'textarea_rows' => 10]); ?></td></tr>
                        <tr><th><?php _e('Requirements', 'hiring-system'); ?></th><td><?php wp_editor($job->requirements ?? '', 'requirements', ['textarea_name' => 'requirements', 'textarea_rows' => 10]); ?></td></tr>
                    </table>
                    <?php submit_button($job ? __('Update Job', 'hiring-system') : __('Create Job', 'hiring-system')); ?>
                </form>
            </div>
            <?php
            return;
        }

        $jobs = HS_Job::query(['status' => null, 'per_page' => 50]);
        ?>
        <div class="wrap">
            <h1><?php _e('Jobs', 'hiring-system'); ?> <a href="<?php echo admin_url('admin.php?page=hs-jobs&action=add'); ?>" class="page-title-action"><?php _e('Add New', 'hiring-system'); ?></a></h1>
            <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success"><p><?php _e('Job saved.', 'hiring-system'); ?></p></div><?php endif; ?>
            <?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success"><p><?php _e('Job deleted.', 'hiring-system'); ?></p></div><?php endif; ?>
            <?php if (!empty($_GET['duplicated'])): ?><div class="notice notice-success"><p><?php _e('Job duplicated.', 'hiring-system'); ?></p></div><?php endif; ?>
            <table class="wp-list-table widefat striped">
                <thead><tr><th><?php _e('Title', 'hiring-system'); ?></th><th><?php _e('Department', 'hiring-system'); ?></th><th><?php _e('Location', 'hiring-system'); ?></th><th><?php _e('Type', 'hiring-system'); ?></th><th><?php _e('Status', 'hiring-system'); ?></th><th><?php _e('Actions', 'hiring-system'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($jobs['items'] as $job): ?>
                    <tr>
                        <td><strong><?php echo esc_html($job->title); ?></strong><br><small><?php echo esc_html($job->slug); ?></small></td>
                        <td><?php echo esc_html($job->department); ?></td>
                        <td><?php echo esc_html($job->location); ?></td>
                        <td><?php echo esc_html($job->employment_type); ?></td>
                        <td><span class="hs-status-badge hs-status-<?php echo esc_attr($job->status); ?>"><?php echo esc_html($job->status); ?></span></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=hs-jobs&action=edit&id=' . $job->id); ?>" class="button button-small"><?php _e('Edit', 'hiring-system'); ?></a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=hs-jobs&action=duplicate&id=' . $job->id), 'hs_duplicate_job', 'hs_nonce'); ?>" class="button button-small"><?php _e('Duplicate', 'hiring-system'); ?></a>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
    <?php wp_nonce_field('hs_delete_job', 'hs_nonce'); ?>
    <input type="hidden" name="action" value="hs_delete_job">
    <input type="hidden" name="job_id" value="<?php echo $job->id; ?>">
    <button type="submit" class="button button-small" onclick="return confirm('<?php _e('Delete this job?', 'hiring-system'); ?>');"><?php _e('Delete', 'hiring-system'); ?></button>
</form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>
        .hs-status-badge { text-transform:capitalize; padding:3px 10px; border-radius:4px; font-size:12px; }
        .hs-status-published { background:#e8f5e9; color:#1b5e20; }
        .hs-status-draft { background:#fff3e0; color:#e65100; }
        .hs-status-closed { background:#ffebee; color:#c62828; }
        </style>
        <?php
    }

    public static function applications() {
        if (!current_user_can('hs_view_applications')) {
            wp_die(__('You do not have permission to view applications.', 'hiring-system'));
        }

        $action = $_GET['action'] ?? 'list';

        if ($action === 'view' && !empty($_GET['id'])) {
            self::application_detail((int)$_GET['id']);
            return;
        }

        $status = sanitize_text_field($_GET['status'] ?? '');
        $job_id = (int)($_GET['job_id'] ?? 0);
        $search = sanitize_text_field($_GET['search'] ?? '');
        $employment_type = sanitize_text_field($_GET['employment_type'] ?? '');
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'applied_at');
        $order = sanitize_text_field($_GET['order'] ?? 'DESC');
        $page = max(1, (int)($_GET['paged'] ?? 1));
        $per_page = 20;

        $result = HS_Application::query([
            'status' => $status,
            'job_id' => $job_id,
            'search' => $search,
            'employment_type' => $employment_type,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => $per_page,
        ]);

        $jobs = HS_Job::query(['status' => null, 'per_page' => 999]);
        $pipeline = HS_Application::get_pipeline_counts();
        $employment_types = [
            'full-time' => __('Full-time', 'hiring-system'),
            'part-time' => __('Part-time', 'hiring-system'),
            'contract' => __('Contract', 'hiring-system'),
            'remote' => __('Remote', 'hiring-system'),
            'internship' => __('Internship', 'hiring-system'),
        ];
        $list_query_args = array_filter([
            'page' => 'hs-applications',
            'status' => $status,
            'job_id' => $job_id ?: null,
            'search' => $search,
            'employment_type' => $employment_type,
            'orderby' => $orderby !== 'applied_at' ? $orderby : null,
            'order' => $order !== 'DESC' ? $order : null,
        ]);
        $has_filters = $status || $job_id || $search || $employment_type || $orderby !== 'applied_at' || $order !== 'DESC';
        ?>
        <div class="wrap">
            <h1><?php _e('Applications', 'hiring-system'); ?></h1>

            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success"><p><?php _e('Status updated.', 'hiring-system'); ?></p></div><?php endif; ?>
            <?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success"><p><?php _e('Application deleted.', 'hiring-system'); ?></p></div><?php endif; ?>
            <?php if (!empty($_GET['bulk_updated'])): ?><div class="notice notice-success"><p><?php printf(__('Updated %d applications.', 'hiring-system'), (int)$_GET['bulk_updated']); ?></p></div><?php endif; ?>
            <?php if (!empty($_GET['bulk_deleted'])): ?><div class="notice notice-success"><p><?php printf(__('Deleted %d applications.', 'hiring-system'), (int)$_GET['bulk_deleted']); ?></p></div><?php endif; ?>
            <?php if (!empty($_GET['noted'])): ?><div class="notice notice-success"><p><?php _e('Note added.', 'hiring-system'); ?></p></div><?php endif; ?>
            <?php if (!empty($_GET['interview'])): ?><div class="notice notice-success"><p><?php _e('Interview scheduled.', 'hiring-system'); ?></p></div><?php endif; ?>

            <div class="hs-filter-bar">
                <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                    <input type="hidden" name="page" value="hs-applications">
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'hiring-system'); ?></option>
                        <?php foreach (['new','shortlisted','interview','offer','hired','rejected','withdrawn'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php selected($status, $s); ?>><?php echo ucfirst($s); ?> (<?php echo $pipeline[$s] ?? 0; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select name="job_id">
                        <option value=""><?php _e('All Jobs', 'hiring-system'); ?></option>
                        <?php foreach ($jobs['items'] as $j): ?>
                        <option value="<?php echo $j->id; ?>" <?php selected($job_id, $j->id); ?>><?php echo esc_html($j->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="employment_type">
                        <option value=""><?php _e('All Job Types', 'hiring-system'); ?></option>
                        <?php foreach ($employment_types as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($employment_type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="orderby">
                        <option value="applied_at" <?php selected($orderby, 'applied_at'); ?>><?php _e('Sort: Applied date', 'hiring-system'); ?></option>
                        <option value="experience_years" <?php selected($orderby, 'experience_years'); ?>><?php _e('Sort: Experience years', 'hiring-system'); ?></option>
                    </select>
                    <select name="order">
                        <option value="DESC" <?php selected($order, 'DESC'); ?>><?php _e('Descending', 'hiring-system'); ?></option>
                        <option value="ASC" <?php selected($order, 'ASC'); ?>><?php _e('Ascending', 'hiring-system'); ?></option>
                    </select>
                    <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search candidates...', 'hiring-system'); ?>">
                    <button type="submit" class="button"><?php _e('Filter', 'hiring-system'); ?></button>
                    <?php if ($has_filters): ?>
                    <a href="<?php echo admin_url('admin.php?page=hs-applications'); ?>" class="button"><?php _e('Clear', 'hiring-system'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

                <form id="hs-bulk-applications-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('hs_bulk_status', 'hs_nonce'); ?>
                <input type="hidden" name="action" value="hs_bulk_status">
                <div class="tablenav top">
                    <?php if (current_user_can('hs_manage_hiring')): ?>
                    <div class="alignleft actions">
                        <select name="bulk_status">
                            <option value=""><?php _e('Bulk Actions', 'hiring-system'); ?></option>
                            <?php foreach (['shortlisted','interview','offer','hired','rejected'] as $s): ?>
                            <option value="<?php echo $s; ?>"><?php printf(__('Move to %s', 'hiring-system'), ucfirst($s)); ?></option>
                            <?php endforeach; ?>
                            <option value="delete"><?php _e('Delete', 'hiring-system'); ?></option>
                        </select>
                        <button type="submit" class="button action hs-bulk-apply"><?php _e('Apply', 'hiring-system'); ?></button>
                    </div>
                    <?php endif; ?>
                    <div class="tablenav-pages">
                        <?php if ($result['pages'] > 1): ?>
                        <span class="displaying-num"><?php printf(__('%d items', 'hiring-system'), $result['total']); ?></span>
                        <span class="pagination-links">
                            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                            <a href="<?php echo esc_url(add_query_arg(array_merge($list_query_args, ['paged' => $i]), admin_url('admin.php'))); ?>" class="button <?php echo $i === $page ? 'disabled' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                </form>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb"><input type="checkbox" class="hs-check-all" <?php echo current_user_can('hs_manage_hiring') ? 'form="hs-bulk-applications-form"' : ''; ?>></td>
                            <th><?php _e('Candidate', 'hiring-system'); ?></th>
                            <th><?php _e('Job', 'hiring-system'); ?></th>
                            <th><?php _e('Job Type', 'hiring-system'); ?></th>
                            <th><?php _e('Experience', 'hiring-system'); ?></th>
                            <th><?php _e('Status', 'hiring-system'); ?></th>
                            <th><?php _e('Score', 'hiring-system'); ?></th>
                            <th><?php _e('Source', 'hiring-system'); ?></th>
                            <th><?php _e('Applied', 'hiring-system'); ?></th>
                            <th><?php _e('Actions', 'hiring-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['items'] as $app): ?>
                        <tr>
                            <td><input type="checkbox" name="app_ids[]" value="<?php echo $app->id; ?>" form="hs-bulk-applications-form"></td>
                            <td>
                                <strong><?php echo esc_html($app->full_name); ?></strong><br>
                                <small><a href="mailto:<?php echo esc_attr($app->email); ?>"><?php echo esc_html($app->email); ?></a></small>
                            </td>
                            <td><?php echo esc_html($app->job_title); ?></td>
                            <td><?php echo esc_html($employment_types[$app->employment_type ?? ''] ?? ucfirst($app->employment_type ?? '')); ?></td>
                            <td><?php echo (int)($app->experience_years ?? 0); ?> <?php _e('years', 'hiring-system'); ?></td>
                            <td><span class="hs-status-badge hs-status-<?php echo esc_attr($app->status); ?>"><?php echo esc_html($app->status); ?></span></td>
                            <td><?php echo $app->score ? '<strong>' . round($app->score, 1) . '</strong>/100' : '—'; ?></td>
                            <td><?php echo esc_html($app->source ?? 'website'); ?></td>
                            <td><?php echo human_time_diff(strtotime($app->applied_at), current_time('timestamp')); ?> ago</td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=hs-applications&action=view&id=' . $app->id); ?>" class="button button-small"><?php _e('View', 'hiring-system'); ?></a>
                                <?php if (current_user_can('hs_manage_hiring') && hs_user_can_access_application($app->id)): ?>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                    <?php wp_nonce_field('hs_delete_application', 'hs_nonce'); ?>
                                    <input type="hidden" name="action" value="hs_delete_application">
                                    <input type="hidden" name="app_id" value="<?php echo $app->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Delete this application? This cannot be undone.', 'hiring-system')); ?>');"><?php _e('Delete', 'hiring-system'); ?></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($result['items'])): ?>
                        <tr><td colspan="10" style="text-align:center;padding:40px;"><?php _e('No applications found.', 'hiring-system'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
        </div>
        <style>
        .hs-filter-bar { background:#fff; padding:15px; margin:15px 0; border-radius:4px; border:1px solid #c3c4c7; }
        .hs-filter-bar form { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .hs-filter-bar select, .hs-filter-bar input { min-width:150px; }
        .hs-status-badge { text-transform:capitalize; padding:3px 10px; border-radius:4px; font-size:12px; }
        .hs-status-new { background:#e3f2fd; color:#1565c0; }
        .hs-status-shortlisted { background:#f3e5f5; color:#6a1b9a; }
        .hs-status-interview { background:#e8f5e9; color:#2e7d32; }
        .hs-status-offer { background:#fff8e1; color:#f57f17; }
        .hs-status-hired { background:#e8f5e9; color:#1b5e20; font-weight:600; }
        .hs-status-rejected { background:#ffebee; color:#c62828; }
        .hs-status-withdrawn { background:#f5f5f5; color:#616161; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            $('.hs-check-all').on('change', function() {
                $('input[name="app_ids[]"]').prop('checked', this.checked);
            });

            $('#hs-bulk-applications-form').on('submit', function(e) {
                if ($(this).find('[name="bulk_status"]').val() === 'delete') {
                    var checked = $('input[name="app_ids[]"]:checked').length;
                    if (!checked) {
                        alert('<?php echo esc_js(__('Please select at least one application.', 'hiring-system')); ?>');
                        e.preventDefault();
                        return;
                    }
                    if (!confirm('<?php echo esc_js(__('Delete the selected applications? This cannot be undone.', 'hiring-system')); ?>')) {
                        e.preventDefault();
                    }
                }
            });
        });
        </script>
        <?php
    }

                            

    private static function application_detail($app_id) {
       // Department access check
    if (!hs_user_can_access_application($app_id)) {
        wp_die(__('You do not have permission to view this application.', 'hiring-system'));
    }
    
        $app = HS_Application::find_with_relations($app_id);
        if (!$app) {
            wp_die(__('Application not found.', 'hiring-system'));
        }

        $statuses = ['new','shortlisted','interview','offer','hired','rejected','withdrawn'];
        $interviewers = get_users(['role__in' => ['administrator','hs_hr_manager'], 'fields' => ['ID','display_name']]);
        ?>
        <div class="wrap hs-app-detail">
            <h1><?php printf(__('Application: %s', 'hiring-system'), esc_html($app->candidate->full_name)); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=hs-applications'); ?>" class="button">← <?php _e('Back to list', 'hiring-system'); ?></a>

            <div class="hs-detail-grid">
                <div class="hs-detail-main">
                    <!-- Candidate Info -->
                    <div class="hs-card">
                        <h2><?php _e('Candidate Information', 'hiring-system'); ?></h2>
                        <table class="widefat striped">
                            <tr><th><?php _e('Name', 'hiring-system'); ?></th><td><?php echo esc_html($app->candidate->full_name); ?></td></tr>
                            <tr><th><?php _e('Email', 'hiring-system'); ?></th><td><a href="mailto:<?php echo esc_attr($app->candidate->email); ?>"><?php echo esc_html($app->candidate->email); ?></a></td></tr>
                            <tr><th><?php _e('Phone', 'hiring-system'); ?></th><td><?php echo esc_html($app->candidate->phone); ?></td></tr>
                            <tr><th><?php _e('Location', 'hiring-system'); ?></th><td><?php echo esc_html($app->candidate->city . ($app->candidate->country ? ', ' . $app->candidate->country : '')); ?></td></tr>
                            <tr><th><?php _e('Experience', 'hiring-system'); ?></th><td><?php echo (int)$app->candidate->experience_years; ?> <?php _e('years', 'hiring-system'); ?></td></tr>
                            <tr><th><?php _e('Education', 'hiring-system'); ?></th><td><?php echo esc_html(ucfirst(str_replace('-', ' ', $app->candidate->education_level))); ?></td></tr>
                            <tr><th><?php _e('Skills', 'hiring-system'); ?></th><td><?php echo esc_html($app->candidate->skills); ?></td></tr>
                            <?php if ($app->candidate->linkedin_url): ?>
                            <tr><th><?php _e('LinkedIn', 'hiring-system'); ?></th><td><a href="<?php echo esc_url($app->candidate->linkedin_url); ?>" target="_blank"><?php echo esc_html($app->candidate->linkedin_url); ?></a></td></tr>
                            <?php endif; ?>
                            <?php if ($app->candidate->portfolio_url): ?>
                            <tr><th><?php _e('Portfolio', 'hiring-system'); ?></th><td><a href="<?php echo esc_url($app->candidate->portfolio_url); ?>" target="_blank"><?php echo esc_html($app->candidate->portfolio_url); ?></a></td></tr>
                            <?php endif; ?>
                                                        <?php if ($app->candidate->cv_attachment_id): ?>
                            <tr><th><?php _e('CV/Resume', 'hiring-system'); ?></th><td><a href="<?php echo wp_get_attachment_url($app->candidate->cv_attachment_id); ?>" target="_blank" class="button"><?php _e('Download CV', 'hiring-system'); ?></a></td></tr>
                            <?php endif; ?>
                            <?php if ($app->candidate->portfolio_attachment_id): ?>
                            <tr><th><?php _e('Portfolio', 'hiring-system'); ?></th><td><a href="<?php echo wp_get_attachment_url($app->candidate->portfolio_attachment_id); ?>" target="_blank" class="button"><?php _e('Download Portfolio', 'hiring-system'); ?></a></td></tr>
                            <?php endif; ?>
                            <tr><th><?php _e('Applied', 'hiring-system'); ?></th><td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($app->applied_at)); ?></td></tr>
                            <tr><th><?php _e('Source', 'hiring-system'); ?></th><td><?php echo esc_html($app->source ?? 'website'); ?></td></tr>
                        </table>
                    </div>

                    <!-- Cover Letter -->
                    <?php if ($app->cover_letter): ?>
                    <div class="hs-card">
                        <h2><?php _e('Cover Letter', 'hiring-system'); ?></h2>
                        <div class="hs-cover-letter"><?php echo wpautop(esc_html($app->cover_letter)); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Score Breakdown -->
                    <div class="hs-card">
                        <h2><?php _e('Score Breakdown', 'hiring-system'); ?></h2>
                        <div class="hs-score-grid">
                            <?php foreach ($app->scores as $score): ?>
                            <div class="hs-score-item">
                                <div class="hs-score-label"><?php echo esc_html(ucfirst($score->category)); ?> (<?php echo round($score->weight * 100); ?>%)</div>
                                <div class="hs-score-bar">
                                    <div class="hs-score-fill" style="width:<?php echo $score->value; ?>%"></div>
                                </div>
                                <div class="hs-score-value"><?php echo round($score->value, 1); ?>/100 → <?php echo round($score->final_score, 1); ?> pts</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hs-total-score">
                            <?php _e('Total Score:', 'hiring-system'); ?> <strong><?php echo $app->score ? round($app->score, 1) : '—'; ?></strong>/100
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="hs-card">
                        <h2><?php _e('Internal Notes', 'hiring-system'); ?></h2>
                        <?php foreach ($app->notes as $note): ?>
                        <div class="hs-note <?php echo $note->is_private ? 'hs-note-private' : ''; ?>">
                            <div class="hs-note-meta">
                                <strong><?php echo esc_html($note->user_name); ?></strong>
                                <?php if ($note->is_private): ?><span class="hs-private-label"><?php _e('Private', 'hiring-system'); ?></span><?php endif; ?>
                                <span class="hs-note-time"><?php echo human_time_diff(strtotime($note->created_at), current_time('timestamp')); ?> ago</span>
                            </div>
                            <div class="hs-note-content"><?php echo esc_html($note->note); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($app->notes)): ?><p class="hs-no-notes"><?php _e('No notes yet.', 'hiring-system'); ?></p><?php endif; ?>

                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="hs-note-form">
                            <?php wp_nonce_field('hs_add_note', 'hs_nonce'); ?>
                            <input type="hidden" name="action" value="hs_add_note">
                            <input type="hidden" name="app_id" value="<?php echo $app->id; ?>">
                            <textarea name="note" rows="3" placeholder="<?php _e('Add a note...', 'hiring-system'); ?>" required></textarea>
                            <label><input type="checkbox" name="is_private" value="1"> <?php _e('Private note', 'hiring-system'); ?></label>
                            <?php submit_button(__('Add Note', 'hiring-system'), 'secondary', 'submit', false); ?>
                        </form>
                    </div>

                    <!-- Timeline -->
                    <div class="hs-card">
                        <h2><?php _e('Timeline', 'hiring-system'); ?></h2>
                        <div class="hs-timeline">
                            <?php foreach ($app->events as $event): ?>
                            <div class="hs-timeline-item">
                                <div class="hs-timeline-dot"></div>
                                <div class="hs-timeline-content">
                                    <strong><?php echo esc_html($event->event_type); ?></strong>
                                    <span class="hs-timeline-time"><?php echo human_time_diff(strtotime($event->created_at), current_time('timestamp')); ?> ago</span>
                                    <p><?php echo esc_html($event->message); ?></p>
                                    <?php if ($event->user_name): ?><small>by <?php echo esc_html($event->user_name); ?></small><?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="hs-detail-sidebar">
                    <!-- Status Management -->
                    <div class="hs-card hs-status-card">
                        <h3><?php _e('Status', 'hiring-system'); ?></h3>
                        <div class="hs-current-status hs-status-<?php echo esc_attr($app->status); ?>">
                            <?php echo esc_html(ucfirst($app->status)); ?>
                        </div>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('hs_update_status', 'hs_nonce'); ?>
                            <input type="hidden" name="action" value="hs_update_status">
                            <input type="hidden" name="app_id" value="<?php echo $app->id; ?>">
                            <select name="status">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo $s; ?>" <?php selected($app->status, $s); ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <textarea name="note" rows="2" placeholder="<?php _e('Optional note...', 'hiring-system'); ?>"></textarea>
                            <?php submit_button(__('Update Status', 'hiring-system'), 'primary', 'submit', false); ?>
                        </form>
                    </div>

                    <!-- Quick Actions -->
                                        <!-- Quick Actions -->
                    <div class="hs-card">
                        <h3><?php _e('Quick Actions', 'hiring-system'); ?></h3>
                        
                        <a href="mailto:<?php echo esc_attr($app->candidate->email); ?>?subject=<?php echo urlencode('Re: ' . $app->job->title); ?>" class="button button-block"><?php _e('Email Candidate', 'hiring-system'); ?></a>
                        
                        <?php if ($app->candidate->cv_attachment_id): ?>
                        <a href="<?php echo wp_get_attachment_url($app->candidate->cv_attachment_id); ?>" target="_blank" class="button button-block"><?php _e('View CV', 'hiring-system'); ?></a>
                        <?php endif; ?>
                        
                        <?php if ($app->candidate->portfolio_attachment_id): ?>
                        <a href="<?php echo wp_get_attachment_url($app->candidate->portfolio_attachment_id); ?>" target="_blank" class="button button-block"><?php _e('View Portfolio', 'hiring-system'); ?></a>
                        <?php endif; ?>
                        
                        <?php if ($app->candidate->phone): ?>
                        <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $app->candidate->phone)); ?>?text=<?php echo urlencode(__('Hi ', 'hiring-system') . $app->candidate->full_name . __(', regarding your application for ', 'hiring-system') . $app->job->title); ?>" target="_blank" class="button button-block hs-btn-whatsapp"><?php _e('WhatsApp Message', 'hiring-system'); ?></a>
                        
                        <a href="tel:<?php echo esc_attr($app->candidate->phone); ?>" class="button button-block hs-btn-call"><?php _e('Call Candidate', 'hiring-system'); ?></a>
                        <?php endif; ?>

                        <?php if (current_user_can('hs_manage_hiring')): ?>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="hs-delete-form">
                            <?php wp_nonce_field('hs_delete_application', 'hs_nonce'); ?>
                            <input type="hidden" name="action" value="hs_delete_application">
                            <input type="hidden" name="app_id" value="<?php echo $app->id; ?>">
                            <button type="submit" class="button button-block button-link-delete" onclick="return confirm('<?php echo esc_js(__('Delete this application? This cannot be undone.', 'hiring-system')); ?>');"><?php _e('Delete Application', 'hiring-system'); ?></button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <!-- Interviews -->
                    <div class="hs-card">
                        <h3><?php _e('Interviews', 'hiring-system'); ?></h3>
                        <?php foreach ($app->interviews as $interview): ?>
                        <div class="hs-interview-item">
                            <strong><?php echo esc_html(ucfirst($interview->type)); ?></strong>
                            <div><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($interview->scheduled_at)); ?></div>
                            <div><?php echo esc_html($interview->interviewer_name); ?> (<?php echo $interview->duration_minutes; ?> min)</div>
                            <?php if ($interview->meeting_link): ?><a href="<?php echo esc_url($interview->meeting_link); ?>" target="_blank"><?php _e('Join Meeting', 'hiring-system'); ?></a><?php endif; ?>
                            <div class="hs-interview-status hs-interview-<?php echo esc_attr($interview->status); ?>"><?php echo esc_html($interview->status); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($app->interviews)): ?><p class="hs-no-interviews"><?php _e('No interviews scheduled.', 'hiring-system'); ?></p><?php endif; ?>

                                                <?php if (current_user_can('hs_manage_interviews')): ?>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="hs-interview-form">
                            <?php wp_nonce_field('hs_schedule_interview', 'hs_nonce'); ?>
                            <input type="hidden" name="action" value="hs_schedule_interview">
                            <input type="hidden" name="app_id" value="<?php echo $app->id; ?>">
                            <select name="interviewer_id" required>
                                <option value=""><?php _e('Select Interviewer', 'hiring-system'); ?></option>
                                <?php foreach ($interviewers as $u): ?>
                                <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="datetime-local" name="scheduled_at" required>
                            <input type="number" name="duration" value="60" min="15" max="240" placeholder="<?php _e('Minutes', 'hiring-system'); ?>">
                            <select name="type">
                                <option value="phone"><?php _e('Phone', 'hiring-system'); ?></option>
                                <option value="video" selected><?php _e('Video', 'hiring-system'); ?></option>
                                <option value="in-person"><?php _e('In-person', 'hiring-system'); ?></option>
                            </select>
                            <input type="url" name="meeting_link" placeholder="<?php _e('Meeting URL (optional)', 'hiring-system'); ?>">
                            <?php submit_button(__('Schedule Interview', 'hiring-system'), 'secondary', 'submit', false); ?>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <style>
        .hs-app-detail { max-width:1400px; }
        .hs-detail-grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-top:20px; }
        .hs-card { background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:20px; }
        .hs-card h2, .hs-card h3 { margin-top:0; padding-bottom:10px; border-bottom:1px solid #f0f0f1; }
        .hs-score-grid { display:grid; gap:15px; }
        .hs-score-item { display:grid; gap:5px; }
        .hs-score-bar { height:8px; background:#f0f0f1; border-radius:4px; overflow:hidden; }
        .hs-score-fill { height:100%; background:#2271b1; border-radius:4px; transition:width 0.3s; }
        .hs-score-value { font-size:12px; color:#646970; }
        .hs-total-score { margin-top:15px; padding-top:15px; border-top:2px solid #f0f0f1; font-size:18px; text-align:center; }
        .hs-total-score strong { color:#2271b1; font-size:24px; }
        .hs-note { padding:12px; border-radius:6px; background:#f6f7f7; margin-bottom:10px; }
        .hs-note-private { background:#fff3e0; }
        .hs-note-meta { display:flex; gap:10px; align-items:center; margin-bottom:5px; font-size:12px; }
        .hs-private-label { background:#e65100; color:#fff; padding:1px 6px; border-radius:3px; font-size:10px; }
        .hs-note-time { color:#646970; margin-left:auto; }
        .hs-note-form { margin-top:15px; padding-top:15px; border-top:1px solid #f0f0f1; }
        .hs-note-form textarea { width:100%; margin-bottom:10px; }
        .hs-timeline { position:relative; padding-left:20px; }
        .hs-timeline::before { content:''; position:absolute; left:5px; top:0; bottom:0; width:2px; background:#f0f0f1; }
        .hs-timeline-item { position:relative; margin-bottom:20px; }
        .hs-timeline-dot { position:absolute; left:-18px; top:4px; width:10px; height:10px; border-radius:50%; background:#2271b1; border:2px solid #fff; }
        .hs-timeline-time { color:#646970; font-size:12px; margin-left:10px; }
        .hs-status-card .hs-current-status { text-align:center; padding:15px; border-radius:6px; font-size:18px; font-weight:600; text-transform:capitalize; margin-bottom:15px; }
        .hs-status-new { background:#e3f2fd; color:#1565c0; }
        .hs-status-shortlisted { background:#f3e5f5; color:#6a1b9a; }
        .hs-status-interview { background:#e8f5e9; color:#2e7d32; }
        .hs-status-offer { background:#fff8e1; color:#f57f17; }
        .hs-status-hired { background:#c8e6c9; color:#1b5e20; }
        .hs-status-rejected { background:#ffcdd2; color:#c62828; }
        .hs-status-withdrawn { background:#f5f5f5; color:#616161; }
        .hs-status-card select, .hs-status-card textarea { width:100%; margin-bottom:10px; }
        .button-block { display:block; width:100%; margin-bottom:8px; text-align:center; }
        .hs-btn-whatsapp { background:#25D366 !important; color:#fff !important; border-color:#25D366 !important; }
        .hs-btn-whatsapp:hover { background:#128C7E !important; border-color:#128C7E !important; }
        .hs-btn-call { background:#2271b1 !important; color:#fff !important; border-color:#2271b1 !important; }
        .hs-btn-call:hover { background:#135e96 !important; border-color:#135e96 !important; }        .hs-interview-item { padding:10px; background:#f6f7f7; border-radius:6px; margin-bottom:10px; font-size:13px; }
        .hs-interview-status { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; margin-top:5px; }
        .hs-interview-scheduled { background:#e3f2fd; color:#1565c0; }
        .hs-interview-completed { background:#e8f5e9; color:#2e7d32; }
        .hs-interview-cancelled { background:#ffebee; color:#c62828; }
        .hs-interview-form { margin-top:15px; padding-top:15px; border-top:1px solid #f0f0f1; }
        .hs-interview-form input, .hs-interview-form select { width:100%; margin-bottom:8px; }
        @media (max-width:782px) { .hs-detail-grid { grid-template-columns:1fr; } }
        </style>
        <?php
    }

    public static function pipeline() {
        if (!current_user_can('hs_view_applications')) {
            wp_die(__('You do not have permission to view the pipeline.', 'hiring-system'));
        }

        $statuses = ['new','shortlisted','interview','offer','hired','rejected'];
        $colors = ['new'=>'#e3f2fd','shortlisted'=>'#f3e5f5','interview'=>'#e8f5e9','offer'=>'#fff8e1','hired'=>'#c8e6c9','rejected'=>'#ffcdd2'];
        ?>
        <div class="wrap">
            <h1><?php _e('Hiring Pipeline', 'hiring-system'); ?></h1>
            <div class="hs-pipeline-board">
                <?php foreach ($statuses as $status):
                    $apps = HS_Application::query(['status' => $status, 'per_page' => 50]);
                ?>
                <div class="hs-pipeline-column" data-status="<?php echo $status; ?>">
                    <div class="hs-pipeline-header" style="background:<?php echo $colors[$status]; ?>">
                        <strong><?php echo ucfirst($status); ?></strong>
                        <span class="hs-count"><?php echo $apps['total']; ?></span>
                    </div>
                    <div class="hs-pipeline-cards">
                        <?php foreach ($apps['items'] as $app): ?>
                        <div class="hs-pipeline-card" data-id="<?php echo $app->id; ?>">
                            <div class="hs-card-name"><?php echo esc_html($app->full_name); ?></div>
                            <div class="hs-card-job"><?php echo esc_html($app->job_title); ?></div>
                            <div class="hs-card-score"><?php echo $app->score ? round($app->score, 1) . '/100' : '—'; ?></div>
                            <a href="<?php echo admin_url('admin.php?page=hs-applications&action=view&id=' . $app->id); ?>" class="hs-card-link"><?php _e('View', 'hiring-system'); ?></a>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($apps['items'])): ?>
                        <div class="hs-pipeline-empty"><?php _e('No candidates', 'hiring-system'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <style>
        .hs-pipeline-board { display:grid; grid-template-columns:repeat(7, 1fr); gap:15px; overflow-x:auto; }
        .hs-pipeline-column { background:#f6f7f7; border-radius:8px; min-width:180px; }
        .hs-pipeline-header { padding:12px; border-radius:8px 8px 0 0; display:flex; justify-content:space-between; align-items:center; }
        .hs-count { background:rgba(255,255,255,0.8); padding:2px 8px; border-radius:12px; font-size:12px; font-weight:700; }
        .hs-pipeline-cards { padding:10px; min-height:200px; }
        .hs-pipeline-card { background:#fff; padding:12px; border-radius:6px; margin-bottom:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05); cursor:pointer; transition:transform 0.2s, box-shadow 0.2s; }
        .hs-pipeline-card:hover { transform:translateY(-2px); box-shadow:0 4px 8px rgba(0,0,0,0.1); }
        .hs-card-name { font-weight:600; font-size:13px; margin-bottom:4px; }
        .hs-card-job { font-size:11px; color:#646970; margin-bottom:6px; }
        .hs-card-score { font-size:12px; color:#2271b1; font-weight:600; }
        .hs-card-link { font-size:11px; color:#2271b1; text-decoration:none; }
        .hs-pipeline-empty { text-align:center; color:#646970; font-size:12px; padding:20px; }
        @media (max-width:1200px) { .hs-pipeline-board { grid-template-columns:repeat(4, 1fr); } }
        @media (max-width:782px) { .hs-pipeline-board { grid-template-columns:repeat(2, 1fr); } }
        </style>
        <?php
    }

        public static function dept_heads() {
        if (!current_user_can('hs_manage_hiring')) {
            wp_die(__('You do not have permission to manage department heads.', 'hiring-system'));
        }

        // POST handling moved to hs_handle_dept_heads_post() via admin_init hook

        // Get all users with Head of Department role
        $heads = get_users([
            'role' => 'hs_head_dept',
            'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
        ]);

        // Get all users who could be promoted (don't already have the role)
        $all_users = get_users([
            'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
            'number' => 500,
        ]);
        $eligible_users = array_filter($all_users, function($u) {
            $user = get_user_by('id', $u->ID);
            return $user && !in_array('hs_head_dept', $user->roles);
        });

        $departments = HS_Job::get_departments();
        ?>

        <div class="wrap">
            <h1><?php _e('Department Heads', 'hiring-system'); ?></h1>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success"><p><?php _e('Department assignment updated.', 'hiring-system'); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['removed'])): ?>
                <div class="notice notice-success"><p><?php _e('Department assignment removed.', 'hiring-system'); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['added'])): ?>
                <div class="notice notice-success"><p><?php _e('Department head added.', 'hiring-system'); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['role_removed'])): ?>
                <div class="notice notice-success"><p><?php _e('Department head role removed from user.', 'hiring-system'); ?></p></div>
            <?php endif; ?>

            <!-- Add New Head Form -->
            <div class="hs-card" style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:20px;">
                <h2><?php _e('Add New Department Head', 'hiring-system'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('hs_dept_heads', 'hs_nonce'); ?>
                    <input type="hidden" name="hs_action" value="add_head">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="new_user_id"><?php _e('Select User', 'hiring-system'); ?></label></th>
                            <td>
                                <select name="new_user_id" id="new_user_id" required style="min-width:300px;">
                                    <option value=""><?php _e('— Choose a user —', 'hiring-system'); ?></option>
                                    <?php foreach ($eligible_users as $u): ?>
                                    <option value="<?php echo $u->ID; ?>">
                                        <?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Only users without the Head of Department role are shown.', 'hiring-system'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="new_dept"><?php _e('Assign Department', 'hiring-system'); ?></label></th>
                            <td>
                                <select name="department" id="new_dept">
                                    <option value=""><?php _e('— None (assign later) —', 'hiring-system'); ?></option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo esc_attr($dept); ?>"><?php echo esc_html($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Add Department Head', 'hiring-system')); ?>
                </form>
            </div>

            <!-- Existing Heads Table -->
            <h2><?php _e('Current Department Heads', 'hiring-system'); ?></h2>
            
            <?php if (empty($heads)): ?>
                <div class="notice notice-warning">
                    <p><?php _e('No department heads found. Use the form above to add one.', 'hiring-system'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'hiring-system'); ?></th>
                            <th><?php _e('Email', 'hiring-system'); ?></th>
                            <th><?php _e('Assigned Department', 'hiring-system'); ?></th>
                            <th><?php _e('Applications', 'hiring-system'); ?></th>
                            <th><?php _e('Actions', 'hiring-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        global $wpdb;
                        foreach ($heads as $head): 
                            $assigned_dept = get_user_meta($head->ID, 'hs_department', true);
                            
                            // Count applications in their department
                            $app_count = 0;
                            if ($assigned_dept) {
                                $app_count = (int)$wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}hs_applications a
                                     JOIN {$wpdb->prefix}hs_jobs j ON a.job_id = j.id
                                     WHERE j.department = %s",
                                    $assigned_dept
                                ));
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($head->display_name); ?></strong><br>
                                <small><?php echo esc_html($head->user_login); ?></small>
                            </td>
                            <td><a href="mailto:<?php echo esc_attr($head->user_email); ?>"><?php echo esc_html($head->user_email); ?></a></td>
                            <td>
                                <?php if ($assigned_dept): ?>
                                    <span class="hs-badge hs-badge-dept" style="background:#e3f2fd; color:#1565c0; padding:3px 10px; border-radius:20px; font-size:12px;">
                                        <?php echo esc_html($assigned_dept); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#c62828; font-style:italic;"><?php _e('Not assigned', 'hiring-system'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($assigned_dept): ?>
                                    <a href="<?php echo admin_url('admin.php?page=hs-applications'); ?>">
                                        <?php echo number_format($app_count); ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Quick Assign/Change Department -->
                                <form method="post" style="display:inline-block; margin-right:5px;">
                                    <?php wp_nonce_field('hs_dept_heads', 'hs_nonce'); ?>
                                    <input type="hidden" name="hs_action" value="assign_dept">
                                    <input type="hidden" name="user_id" value="<?php echo $head->ID; ?>">
                                    <select name="department" onchange="this.form.submit()" style="min-width:150px;">
                                        <option value=""><?php _e('— Change Dept —', 'hiring-system'); ?></option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo esc_attr($dept); ?>" <?php selected($assigned_dept, $dept); ?>>
                                            <?php echo esc_html($dept); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>

                                <?php if ($assigned_dept): ?>
                                <form method="post" style="display:inline-block; margin-right:5px;">
                                    <?php wp_nonce_field('hs_dept_heads', 'hs_nonce'); ?>
                                    <input type="hidden" name="hs_action" value="remove_dept">
                                    <input type="hidden" name="user_id" value="<?php echo $head->ID; ?>">
                                    <button type="submit" class="button" onclick="return confirm('<?php _e('Remove department assignment?', 'hiring-system'); ?>')">
                                        <?php _e('Remove Dept', 'hiring-system'); ?>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="post" style="display:inline-block;">
                                    <?php wp_nonce_field('hs_dept_heads', 'hs_nonce'); ?>
                                    <input type="hidden" name="hs_action" value="remove_role">
                                    <input type="hidden" name="user_id" value="<?php echo $head->ID; ?>">
                                    <button type="submit" class="button" style="color:#c62828;" onclick="return confirm('<?php _e('Remove Head of Department role from this user? They will lose all hiring access.', 'hiring-system'); ?>')">
                                        <?php _e('Remove Role', 'hiring-system'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Department Summary -->
            <?php if (!empty($departments)): ?>
            <h2 style="margin-top:30px;"><?php _e('Department Coverage', 'hiring-system'); ?></h2>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Department', 'hiring-system'); ?></th>
                        <th><?php _e('Active Jobs', 'hiring-system'); ?></th>
                        <th><?php _e('Head Assigned', 'hiring-system'); ?></th>
                        <th><?php _e('Head Name', 'hiring-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($departments as $dept):
                        $job_count = (int)$wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}hs_jobs WHERE department = %s AND status = 'published'",
                            $dept
                        ));
                        
                        $head_user = $wpdb->get_row($wpdb->prepare(
                            "SELECT u.ID, u.display_name FROM {$wpdb->users} u
                             JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                             WHERE um.meta_key = 'hs_department' AND um.meta_value = %s
                             LIMIT 1",
                            $dept
                        ));
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($dept); ?></strong></td>
                        <td><?php echo number_format($job_count); ?></td>
                        <td>
                            <?php if ($head_user): ?>
                                <span style="color:#2e7d32;">✓ <?php _e('Yes', 'hiring-system'); ?></span>
                            <?php else: ?>
                                <span style="color:#c62828;">✗ <?php _e('No', 'hiring-system'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($head_user): ?>
                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $head_user->ID); ?>">
                                    <?php echo esc_html($head_user->display_name); ?>
                                </a>
                            <?php else: ?>
                                <em><?php _e('Unassigned', 'hiring-system'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    

    public static function settings() {
        if (!current_user_can('hs_manage_hiring')) {
            wp_die(__('You do not have permission to manage settings.', 'hiring-system'));
        }
        $settings = HS_Settings::get();
        $logo_id = (int) ($settings['email_logo_id'] ?? 0);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        $initial_preview = HS_Notifications::preview_email(
            $settings['email_app_received_subject'] ?? '',
            $settings['email_app_received_body'] ?? '',
            []
        );
        ?>
        <div class="wrap hs-settings-wrap">
            <h1><?php _e('Hiring System Settings', 'hiring-system'); ?></h1>
            <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success"><p><?php _e('Settings saved.', 'hiring-system'); ?></p></div><?php endif; ?>

            <div class="hs-settings-layout">
                <div class="hs-settings-form-column">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="hs-settings-form">
                <?php wp_nonce_field('hs_save_settings', 'hs_nonce'); ?>
                <input type="hidden" name="action" value="hs_save_settings">

                <table class="form-table">
                    <tr>
                        <th><?php _e('Notification Email', 'hiring-system'); ?></th>
                        <td>
                            <input type="email" name="notification_email" value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text">
                            <p class="description"><?php _e('Leave empty to use admin email.', 'hiring-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Scoring Weights', 'hiring-system'); ?></th>
                        <td>
                            <div class="hs-weights">
                                <label>Experience: <input type="number" name="weight_experience" value="<?php echo $settings['scoring_weights']['experience']; ?>" step="0.05" min="0" max="1" style="width:80px;"></label><br>
                                <label>Skills: <input type="number" name="weight_skills" value="<?php echo $settings['scoring_weights']['skills']; ?>" step="0.05" min="0" max="1" style="width:80px;"></label><br>
                                <label>Education: <input type="number" name="weight_education" value="<?php echo $settings['scoring_weights']['education']; ?>" step="0.05" min="0" max="1" style="width:80px;"></label><br>
                                <label>Portfolio: <input type="number" name="weight_portfolio" value="<?php echo $settings['scoring_weights']['portfolio']; ?>" step="0.05" min="0" max="1" style="width:80px;"></label>
                            </div>
                            <p class="description"><?php _e('Weights must sum to 1.0', 'hiring-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Auto-Reject Threshold', 'hiring-system'); ?></th>
                        <td>
                            <input type="number" name="auto_reject_score" value="<?php echo $settings['auto_reject_score']; ?>" step="1" min="0" max="100" style="width:80px;"> /100
                            <p class="description"><?php _e('Set to 0 to disable auto-rejection.', 'hiring-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('GDPR Data Retention', 'hiring-system'); ?></th>
                        <td>
                            <input type="number" name="gdpr_retention_days" value="<?php echo $settings['gdpr_retention_days']; ?>" min="1" style="width:80px;"> <?php _e('days', 'hiring-system'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Rate Limiting', 'hiring-system'); ?></th>
                        <td>
                            <label><input type="checkbox" name="enable_rate_limiting" value="1" <?php checked($settings['enable_rate_limiting'], 1); ?>> <?php _e('Enable rate limiting on applications (5 per hour per IP)', 'hiring-system'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2"><h2 style="margin:1em 0 0;padding:0;"><?php _e('Email Signature', 'hiring-system'); ?></h2></th>
                    </tr>
                    <tr>
                        <th><?php _e('Appreciation Line', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_appreciation_line" value="<?php echo esc_attr($settings['email_appreciation_line'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Closing Text', 'hiring-system'); ?></th>
                        <td>
                            <textarea name="email_closing_text" rows="2" class="large-text"><?php echo esc_textarea($settings['email_closing_text'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Logo', 'hiring-system'); ?></th>
                        <td>
                            <input type="hidden" name="email_logo_id" id="hs-email-logo-id" value="<?php echo esc_attr($logo_id); ?>">
                            <div id="hs-email-logo-preview" style="margin-bottom:10px;">
                                <?php if ($logo_url): ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" style="max-width:180px;max-height:60px;object-fit:contain;display:block;">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="hs-email-logo-upload"><?php _e('Upload Logo', 'hiring-system'); ?></button>
                            <button type="button" class="button" id="hs-email-logo-remove" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>><?php _e('Remove', 'hiring-system'); ?></button>
                            <p class="description"><?php _e('Recommended size: 180×60 px. Shown in the email footer when set.', 'hiring-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Dedication Line', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_dedication_line" value="<?php echo esc_attr($settings['email_dedication_line'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Default Sender Name', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_sender_name" value="<?php echo esc_attr($settings['email_sender_name'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php _e('Used when no logged-in user triggers the email.', 'hiring-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Default Sender Role', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_sender_role" value="<?php echo esc_attr($settings['email_sender_role'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Mobile Phone', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_mobile_phone" value="<?php echo esc_attr($settings['email_mobile_phone'] ?? ''); ?>" class="regular-text" placeholder="+20 100...">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Contact Email', 'hiring-system'); ?></th>
                        <td>
                            <input type="email" name="email_contact_email" value="<?php echo esc_attr($settings['email_contact_email'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php _e('Leave empty to use notification email.', 'hiring-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Company Name', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_company_name" value="<?php echo esc_attr($settings['email_company_name'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php _e('Leave empty to use site name.', 'hiring-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Company Tagline', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_company_tagline" value="<?php echo esc_attr($settings['email_company_tagline'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Office Phone', 'hiring-system'); ?></th>
                        <td>
                            <input type="text" name="email_office_phone" value="<?php echo esc_attr($settings['email_office_phone'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Office Address', 'hiring-system'); ?></th>
                        <td>
                            <textarea name="email_office_address" rows="3" class="large-text"><?php echo esc_textarea($settings['email_office_address'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2"><h2 style="margin:1em 0 0;padding:0;"><?php _e('Email Templates', 'hiring-system'); ?></h2></th>
                    </tr>
                    <tr>
    <th><?php _e('Status: Offer — Candidate Email', 'hiring-system'); ?></th>
    <td>
        <label><?php _e('Subject', 'hiring-system'); ?><br>
        <input type="text" name="email_offer_subject" value="<?php echo esc_attr($settings['email_offer_subject'] ?? ''); ?>" class="regular-text"></label>
        <p class="description"><?php _e('Placeholders: {candidate_name}, {job_title}, {portal_url}, {site_name}', 'hiring-system'); ?></p>
        <br>
        <label><?php _e('Body', 'hiring-system'); ?><br>
        <textarea name="email_offer_body" rows="6" class="large-text"><?php echo esc_textarea($settings['email_offer_body'] ?? ''); ?></textarea></label>
    </td>
</tr>
<tr>
    <th><?php _e('Status: Rejected — Candidate Email', 'hiring-system'); ?></th>
    <td>
        <label><?php _e('Subject', 'hiring-system'); ?><br>
        <input type="text" name="email_rejected_subject" value="<?php echo esc_attr($settings['email_rejected_subject'] ?? ''); ?>" class="regular-text"></label>
        <p class="description"><?php _e('Placeholders: {candidate_name}, {job_title}, {portal_url}, {site_name}', 'hiring-system'); ?></p>
        <br>
        <label><?php _e('Body', 'hiring-system'); ?><br>
        <textarea name="email_rejected_body" rows="6" class="large-text"><?php echo esc_textarea($settings['email_rejected_body'] ?? ''); ?></textarea></label>
    </td>
</tr>
                    <tr>
    <th><?php _e('Application Received — Candidate Email', 'hiring-system'); ?></th>
    <td>
        <label><?php _e('Subject', 'hiring-system'); ?><br>
        <input type="text" name="email_app_received_subject" value="<?php echo esc_attr($settings['email_app_received_subject'] ?? ''); ?>" class="regular-text" placeholder="Application Received: {job_title}"></label>
        <p class="description"><?php _e('Placeholders: {candidate_name}, {job_title}, {portal_url}, {site_name}', 'hiring-system'); ?></p>
        <br>
        <label><?php _e('Body', 'hiring-system'); ?><br>
        <textarea name="email_app_received_body" rows="6" class="large-text"><?php echo esc_textarea($settings['email_app_received_body'] ?? ''); ?></textarea></label>
    </td>
</tr>
<tr>
    <th><?php _e('Application Received — Admin Email', 'hiring-system'); ?></th>
    <td>
        <label><?php _e('Subject', 'hiring-system'); ?><br>
        <input type="text" name="email_app_received_admin_subject" value="<?php echo esc_attr($settings['email_app_received_admin_subject'] ?? ''); ?>" class="regular-text"></label>
        <p class="description"><?php _e('Placeholders: {candidate_name}, {candidate_email}, {job_title}, {score}, {admin_url}, {site_name}', 'hiring-system'); ?></p>
        <br>
        <label><?php _e('Body', 'hiring-system'); ?><br>
        <textarea name="email_app_received_admin_body" rows="6" class="large-text"><?php echo esc_textarea($settings['email_app_received_admin_body'] ?? ''); ?></textarea></label>
    </td>
</tr>
<tr>
    <th><?php _e('Status Changed — Candidate Email', 'hiring-system'); ?></th>
    <td>
        <label><?php _e('Subject', 'hiring-system'); ?><br>
        <input type="text" name="email_status_changed_subject" value="<?php echo esc_attr($settings['email_status_changed_subject'] ?? ''); ?>" class="regular-text"></label>
        <p class="description"><?php _e('Placeholders: {candidate_name}, {job_title}, {old_status}, {new_status}, {portal_url}, {site_name}', 'hiring-system'); ?></p>
        <br>
        <label><?php _e('Body', 'hiring-system'); ?><br>
        <textarea name="email_status_changed_body" rows="6" class="large-text"><?php echo esc_textarea($settings['email_status_changed_body'] ?? ''); ?></textarea></label>
    </td>
</tr>
<tr>
    <th><?php _e('Interview Scheduled — Candidate Email', 'hiring-system'); ?></th>
    <td>
        <label><?php _e('Subject', 'hiring-system'); ?><br>
        <input type="text" name="email_interview_subject" value="<?php echo esc_attr($settings['email_interview_subject'] ?? ''); ?>" class="regular-text"></label>
        <p class="description"><?php _e('Placeholders: {candidate_name}, {job_title}, {interview_date}, {duration}, {type}, {meeting_link}, {site_name}', 'hiring-system'); ?></p>
        <br>
        <label><?php _e('Body', 'hiring-system'); ?><br>
        <textarea name="email_interview_body" rows="6" class="large-text"><?php echo esc_textarea($settings['email_interview_body'] ?? ''); ?></textarea></label>
    </td>
</tr>
                </table>
                <?php submit_button(__('Save Settings', 'hiring-system')); ?>
            </form>
                </div>

                <div class="hs-settings-preview-column">
                    <div class="hs-email-preview-panel">
                        <h2><?php _e('Email Preview', 'hiring-system'); ?></h2>
                        <p class="description"><?php _e('Live preview with sample data. Updates as you edit fields below.', 'hiring-system'); ?></p>

                        <p>
                            <label for="hs-preview-template"><strong><?php _e('Template', 'hiring-system'); ?></strong></label><br>
                            <select id="hs-preview-template" class="regular-text">
                                <option value="app_received"><?php _e('Application Received — Candidate', 'hiring-system'); ?></option>
                                <option value="app_received_admin"><?php _e('Application Received — Admin', 'hiring-system'); ?></option>
                                <option value="status_changed"><?php _e('Status Changed', 'hiring-system'); ?></option>
                                <option value="offer"><?php _e('Offer', 'hiring-system'); ?></option>
                                <option value="rejected"><?php _e('Rejected', 'hiring-system'); ?></option>
                                <option value="interview"><?php _e('Interview Scheduled', 'hiring-system'); ?></option>
                            </select>
                        </p>

                        <p class="hs-preview-subject-line">
                            <strong><?php _e('Subject:', 'hiring-system'); ?></strong>
                            <span id="hs-preview-subject"><?php echo esc_html($initial_preview['subject']); ?></span>
                        </p>

                        <div class="hs-preview-frame-wrap">
                            <iframe id="hs-email-preview-frame" title="<?php esc_attr_e('Email preview', 'hiring-system'); ?>" sandbox="allow-same-origin" srcdoc="<?php echo esc_attr($initial_preview['html']); ?>"></iframe>
                        </div>

                        <p class="description hs-preview-note"><?php _e('Sample recipient: Jane Doe · Sample job: Software Engineer', 'hiring-system'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
