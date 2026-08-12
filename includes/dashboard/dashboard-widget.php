<?php
if (!defined('ABSPATH')) exit;

add_action('wp_dashboard_setup', function() {
    if (current_user_can('hs_view_applications')) {
        wp_add_dashboard_widget('hs_dashboard_widget', __('Hiring Overview', 'hiring-system'), function() {
            $stats = get_transient('hs_dashboard_stats');
            if (false === $stats) {
                global $wpdb;
                $stats = [
                    'active_jobs' => HS_Job::count_active(),
                    'total_applications' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_applications"),
                    'pipeline' => HS_Application::get_pipeline_counts(),
                ];
            }
            ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div style="text-align:center;padding:15px;background:#f6f7f7;border-radius:6px;">
                    <div style="font-size:24px;font-weight:700;color:#2271b1;"><?php echo $stats['active_jobs']; ?></div>
                    <div style="font-size:12px;color:#646970;"><?php _e('Active Jobs', 'hiring-system'); ?></div>
                </div>
                <div style="text-align:center;padding:15px;background:#f6f7f7;border-radius:6px;">
                    <div style="font-size:24px;font-weight:700;color:#2271b1;"><?php echo $stats['total_applications']; ?></div>
                    <div style="font-size:12px;color:#646970;"><?php _e('Applications', 'hiring-system'); ?></div>
                </div>
            </div>
            <div style="margin-top:15px;">
                <strong><?php _e('Pipeline:', 'hiring-system'); ?></strong>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                    <?php foreach (['new','shortlisted','interview','offer','hired'] as $s): ?>
                    <span style="background:#e3f2fd;padding:3px 10px;border-radius:12px;font-size:11px;">
                        <?php echo ucfirst($s); ?>: <?php echo $stats['pipeline'][$s] ?? 0; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <p style="margin-top:15px;">
                <a href="<?php echo admin_url('admin.php?page=hiring-system'); ?>" class="button"><?php _e('View Dashboard', 'hiring-system'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=hs-applications'); ?>" class="button"><?php _e('View Applications', 'hiring-system'); ?></a>
            </p>
            <?php
        });
    }
});
