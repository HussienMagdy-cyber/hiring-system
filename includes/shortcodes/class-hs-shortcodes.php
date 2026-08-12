<?php
if (!defined('ABSPATH')) exit;

class HS_Shortcodes {
    public static function init() {
        add_shortcode('hs_careers', [self::class, 'careers']);
        add_shortcode('hs_apply_form', [self::class, 'apply_form']);
        add_shortcode('hs_candidate_portal', [self::class, 'portal']);
    }

    public static function careers($atts) {
        $job_slug = sanitize_text_field($_GET['job'] ?? '');
        if ($job_slug) {
            $job = HS_Job::find_by_slug($job_slug);
            if ($job && $job->status === 'published') return self::job_detail($job);
            return '<div class="hs-error">' . __('Job not found.', 'hiring-system') . '</div>';
        }
        return self::job_list();
    }

    private static function job_list() {
        $department = sanitize_text_field($_GET['dept'] ?? '');
        $search = sanitize_text_field($_GET['hs_search'] ?? '');

        $result = HS_Job::query([
            'status' => 'published',
            'department' => $department,
            'search' => $search,
            'per_page' => 50,
        ]);
        $jobs = $result['items'];
        $departments = HS_Job::get_departments();

        ob_start();
        ?>
        <div class="hs-careers-page">
            <!-- Department Filter -->
            <?php if (!empty($departments)): ?>
            <div class="hs-dept-filter">
                <a href="<?php echo esc_url(remove_query_arg(['dept', 'paged', 'hs_search'])); ?>" class="<?php echo empty($department) ? 'active' : ''; ?>"><?php _e('All', 'hiring-system'); ?></a>
                <?php foreach ($departments as $dept): ?>
                <a href="<?php echo esc_url(add_query_arg(['dept' => $dept], remove_query_arg(['paged', 'hs_search']))); ?>" class="<?php echo $department === $dept ? 'active' : ''; ?>"><?php echo esc_html($dept); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <!-- <form class="hs-search-bar" method="get" action="<?php echo esc_url(add_query_arg([])); ?>">
                <?php if ($department): ?><input type="hidden" name="dept" value="<?php echo esc_attr($department); ?>"><?php endif; ?>
                <div class="hs-search-input-wrap">
                    <svg class="hs-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="search" name="hs_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search by title, keyword...', 'hiring-system'); ?>">
                </div>
                <button type="submit" class="hs-btn hs-btn-primary"><?php _e('Search', 'hiring-system'); ?></button>
                <?php if ($search): ?>
                <a href="<?php echo esc_url(remove_query_arg(['hs_search', 'paged'])); ?>" class="hs-btn hs-btn-ghost"><?php _e('Clear', 'hiring-system'); ?></a>
                <?php endif; ?>
            </form> -->

            <!-- Jobs Grid -->
            <?php if (empty($jobs)): ?>
                <div class="hs-empty-state">
                    <div class="hs-empty-icon">🔍</div>
                    <h3><?php _e('No positions found', 'hiring-system'); ?></h3>
                    <p><?php _e('Try adjusting your filters or search terms.', 'hiring-system'); ?></p>
                    <a href="<?php echo esc_url(remove_query_arg(['dept', 'hs_search'])); ?>" class="hs-btn hs-btn-primary"><?php _e('View All Positions', 'hiring-system'); ?></a>
                </div>
            <?php else: ?>
                <div class="hs-jobs-grid">
                <?php foreach ($jobs as $job):
                    $diff = current_time('timestamp') - strtotime($job->created_at);
$posted_days = floor($diff / DAY_IN_SECONDS);
if ($posted_days < 1) {
    $posted_text = __('Today', 'hiring-system');
} else {
    $posted_text = sprintf(_n('%d day ago', '%d days ago', $posted_days, 'hiring-system'), $posted_days);
}
                ?>
                <div class="hs-job-card">
                    <div class="hs-card-content">
                        <div class="hs-card-top">
                            <div class="hs-job-badges">
                                <span class="hs-badge hs-badge-type"><?php echo esc_html($job->employment_type); ?></span>
                            </div>
                            <div class="hs-share-dropdown">
                                <button class="hs-share-btn" onclick="this.nextElementSibling.classList.toggle('show')" type="button" aria-label="<?php _e('Share', 'hiring-system'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                </button>
                                <div class="hs-share-menu">
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(add_query_arg('job', $job->slug, get_permalink())); ?>" target="_blank" rel="noopener">LinkedIn</a>
                                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($job->title . ' - ' . get_bloginfo('name')); ?>&url=<?php echo urlencode(add_query_arg('job', $job->slug, get_permalink())); ?>" target="_blank" rel="noopener">Twitter</a>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(add_query_arg('job', $job->slug, get_permalink())); ?>" target="_blank" rel="noopener">Facebook</a>
                                    <a href="mailto:?subject=<?php echo urlencode($job->title); ?>&body=<?php echo urlencode(add_query_arg('job', $job->slug, get_permalink())); ?>">Email</a>
                                    <button onclick="navigator.clipboard.writeText('<?php echo esc_js(add_query_arg('job', $job->slug, get_permalink())); ?>');this.textContent='<?php _e('Copied!', 'hiring-system'); ?>'" type="button"><?php _e('Copy Link', 'hiring-system'); ?></button>
                                </div>
                            </div>
                        </div>

                        <h3 class="hs-job-title">
                            <a href="?job=<?php echo esc_attr($job->slug); ?>"><?php echo esc_html($job->title); ?></a>
                        </h3>

                        <p class="hs-job-desc"><?php echo wp_kses_post(wp_trim_words($job->description, 20)); ?></p>

                        <div class="hs-card-footer">
                            <div class="hs-job-meta">
                                <?php if ($job->salary_min || $job->salary_max): ?>
                                <span class="hs-salary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    <?php if ($job->salary_min && $job->salary_max): ?>
                                        $<?php echo number_format($job->salary_min); ?> - $<?php echo number_format($job->salary_max); ?>
                                    <?php elseif ($job->salary_min): ?>
                                        From $<?php echo number_format($job->salary_min); ?>
                                    <?php else: ?>
                                        Up to $<?php echo number_format($job->salary_max); ?>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                                <span class="hs-posted">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <?php 
    if ($posted_days < 1) {
        _e('Today', 'hiring-system');
    } else {
        printf(_n('%d day ago', '%d days ago', $posted_days, 'hiring-system'), $posted_days);
    }
    ?>
</span>
                            </div>
                            <a href="?job=<?php echo esc_attr($job->slug); ?>" class="hs-btn-apply"><?php _e('APPLY NOW', 'hiring-system'); ?></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
        /* ===== Base ===== */
        .hs-careers-page { max-width:1000px; margin:0 auto; padding:60px 20px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,sans-serif; }

        /* ===== Department Filter ===== */
        .hs-dept-filter { display:flex; justify-content:center; gap:10px; margin-bottom:32px; flex-wrap:wrap; }
        .hs-dept-filter a { display:inline-block; padding:10px 24px; border-radius:50px; font-size:13px; font-weight:600; color:#666; text-decoration:none; background:#f8f8f8; transition:all 0.25s; border:1px solid transparent; }
        .hs-dept-filter a:hover { background:#fff; color:#B9184A; border-color:#f0e0e5; box-shadow:0 2px 8px rgba(185,24,74,0.08); }
        .hs-dept-filter a.active { background:#B9184A; color:#fff; box-shadow:0 4px 14px rgba(185,24,74,0.25); }

        /* ===== Search ===== */
        .hs-search-bar { display:flex; justify-content:center; gap:10px; margin-bottom:40px; flex-wrap:wrap; }
        .hs-search-input-wrap { position:relative; width:360px; }
        .hs-search-icon { position:absolute; left:16px; top:50%; transform:translateY(-50%); color:#ccc; width:18px; height:18px; }
        .hs-search-input-wrap input { width:100%; padding:14px 18px 14px 46px; border:1px solid #e8e8e8; border-radius:12px; font-size:14px; background:#fafafa; transition:all 0.25s; box-sizing:border-box; color:#333; }
        .hs-search-input-wrap input:focus { outline:none; border-color:#B9184A; background:#fff; box-shadow:0 0 0 4px rgba(185,24,74,0.08); }

        /* ===== Buttons ===== */
        .hs-btn { display:inline-flex; align-items:center; gap:6px; padding:12px 28px; border-radius:10px; font-size:14px; font-weight:600; text-decoration:none; border:none; cursor:pointer; transition:all 0.25s; }
        .hs-btn-primary { background:#B9184A; color:#fff; box-shadow:0 4px 14px rgba(185,24,74,0.25); }
        .hs-btn-primary:hover { background:#96123D; transform:translateY(-2px); box-shadow:0 6px 20px rgba(185,24,74,0.35); }
        .hs-btn-ghost { background:#fff; color:#666; border:1px solid #e8e8e8; }
        .hs-btn-ghost:hover { background:#fafafa; border-color:#B9184A; color:#B9184A; }

        /* ===== Jobs Grid - 2 Columns ===== */
        .hs-jobs-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:28px; }
        @media (max-width:640px) { .hs-jobs-grid { grid-template-columns:1fr; } }

        /* ===== Job Card ===== */
        .hs-job-card { background:#fff; border-radius:20px; padding:32px; box-shadow:0 4px 20px rgba(0,0,0,0.04); transition:all 0.35s cubic-bezier(0.4,0,0.2,1); border:1px solid #f5f5f5; position:relative; overflow:hidden; }
        .hs-job-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #B9184A, #e85d8a); opacity:0; transition:opacity 0.35s; }
        .hs-job-card:hover { transform:translateY(-6px); box-shadow:0 12px 40px rgba(0,0,0,0.08); border-color:#f0e0e5; }
        .hs-job-card:hover::before { opacity:1; }

        .hs-card-content { display:flex; flex-direction:column; height:100%; position:relative; z-index:1; }

        /* Card Top */
        .hs-card-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; }
        .hs-job-badges { display:flex; gap:8px; flex-wrap:wrap; }
        .hs-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 16px; border-radius:50px; font-size:12px; font-weight:600; }
        .hs-badge-type { background:rgba(185,24,74,0.08); color:#B9184A; }

        /* Share */
        .hs-share-dropdown { position:relative; }
        .hs-share-btn { background:none; border:none; padding:10px; border-radius:10px; cursor:pointer; color:#ccc; transition:all 0.2s; }
        .hs-share-btn:hover { background:#fafafa; color:#B9184A; }
        .hs-share-menu { position:absolute; top:100%; right:0; background:#fff; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,0.12); padding:8px; min-width:170px; opacity:0; visibility:hidden; transform:translateY(-8px); transition:all 0.2s; z-index:10; }
        .hs-share-menu.show { opacity:1; visibility:visible; transform:translateY(6px); }
        .hs-share-menu a, .hs-share-menu button { display:flex; align-items:center; width:100%; padding:12px 16px; border-radius:10px; color:#555; text-decoration:none; font-size:13px; font-weight:500; border:none; background:none; cursor:pointer; transition:all 0.15s; }
        .hs-share-menu a:hover, .hs-share-menu button:hover { background:#fff5f8; color:#B9184A; }

        /* Title & Desc */
        .hs-job-title { font-size:20px; font-weight:700; margin:0 0 14px; line-height:1.35; }
        .hs-job-title a { color:#1a1a2e; text-decoration:none; transition:color 0.2s; }
        .hs-job-title a:hover { color:#B9184A; }
        .hs-job-desc { color:#888; font-size:14px; line-height:1.7; margin:0 0 24px; flex:1; }

        /* Card Footer */
        .hs-card-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:auto; padding-top:20px; border-top:1px solid #f5f5f5; flex-wrap:wrap; }
        .hs-job-meta { display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
        .hs-salary, .hs-posted { display:flex; align-items:center; gap:6px; color:#999; font-size:13px; font-weight:500; }
        .hs-salary { color:#B9184A; }
        .hs-salary svg, .hs-posted svg { color:#ccc; }

        /* Apply Button */
        .hs-btn-apply { display:inline-block; padding:12px 28px; background:#B9184A; color:#fff; font-size:12px; font-weight:700; text-decoration:none; border-radius:10px; text-transform:uppercase; letter-spacing:0.8px; transition:all 0.3s; box-shadow:0 4px 14px rgba(185,24,74,0.2); }
        .hs-btn-apply:hover { background:#96123D; color:#fff; transform:translateY(-2px); box-shadow:0 6px 20px rgba(185,24,74,0.3); }

        /* ===== Empty State ===== */
        .hs-empty-state { text-align:center; padding:100px 20px; }
        .hs-empty-icon { font-size:56px; margin-bottom:20px; opacity:0.6; }
        .hs-empty-state h3 { font-size:22px; font-weight:700; color:#1a1a2e; margin:0 0 10px; }
        .hs-empty-state p { color:#aaa; margin:0 0 28px; font-size:15px; }
        </style>
        <?php
        return ob_get_clean();
    }

            private static function job_detail($job) {
$diff = current_time('timestamp') - strtotime($job->created_at);
$posted_days = floor($diff / DAY_IN_SECONDS);
if ($posted_days < 1) {
    $posted_text = __('Today', 'hiring-system');
} else {
    $posted_text = sprintf(_n('%d day ago', '%d days ago', $posted_days, 'hiring-system'), $posted_days);
}        ob_start();
        ?>
        <div class="hs-job-detail">
            <!-- Back Link -->
            <a href="?" class="hs-back">← <?php _e('Back to all jobs', 'hiring-system'); ?></a>

            <!-- Job Header -->
            <div class="hs-detail-header">
                <div class="hs-detail-header-content">
                    <div class="hs-detail-meta-top">
                        <span class="hs-detail-type"><?php echo esc_html(ucfirst($job->employment_type)); ?></span>
                        <span class="hs-detail-posted">
    <?php 
    if ($posted_days < 1) {
        _e('Posted today', 'hiring-system');
    } else {
        printf(_n('Posted %d day ago', 'Posted %d days ago', $posted_days, 'hiring-system'), $posted_days);
    }
    ?>
</span>
                    </div>
                    <h1 class="hs-detail-title"><?php echo esc_html($job->title); ?></h1>
                    <?php if ($job->department): ?>
                    <p class="hs-detail-dept"><?php echo esc_html($job->department); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Bar -->
            <div class="hs-info-bar">
                <?php if ($job->location): ?>
                <div class="hs-info-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span><?php echo esc_html($job->location); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($job->salary_min || $job->salary_max): ?>
                <div class="hs-info-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>
                        <?php if ($job->salary_min && $job->salary_max): ?>
                            $<?php echo number_format($job->salary_min); ?> - $<?php echo number_format($job->salary_max); ?>
                        <?php elseif ($job->salary_min): ?>
                            From $<?php echo number_format($job->salary_min); ?>
                        <?php else: ?>
                            Up to $<?php echo number_format($job->salary_max); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="hs-info-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>
    <?php 
    if ($posted_days < 1) {
        _e('Today', 'hiring-system');
    } else {
        printf(_n('%d day ago', '%d days ago', $posted_days, 'hiring-system'), $posted_days);
    }
    ?>
</span>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="hs-detail-layout">
                <!-- Main Content -->
                <div class="hs-detail-main">
                    <div class="hs-detail-card">
                        <h3><?php _e('About the Role', 'hiring-system'); ?></h3>
                        <div class="hs-detail-body">
                            <?php echo wp_kses_post(wpautop($job->description)); ?>
                        </div>
                    </div>

                    <?php if ($job->requirements): ?>
                    <div class="hs-detail-card">
                        <h3><?php _e('Requirements', 'hiring-system'); ?></h3>
                        <div class="hs-detail-body">
                            <?php echo wp_kses_post(wpautop($job->requirements)); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="hs-detail-sidebar">
                    <!-- Quick Apply CTA -->
                    <div class="hs-sidebar-card hs-apply-cta">
                        <h4><?php _e('Interested in this role?', 'hiring-system'); ?></h4>
                        <p><?php _e('Submit your application and we will get back to you within 48 hours.', 'hiring-system'); ?></p>
                        <a href="#hs-apply-section" class="hs-btn-apply-lg"><?php _e('Apply Now', 'hiring-system'); ?></a>                    </div>

                    <!-- Share -->
                    <div class="hs-sidebar-card">
                        <h4><?php _e('Share this job', 'hiring-system'); ?></h4>
                        <div class="hs-share-grid">
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener" class="hs-share-link">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                LinkedIn
                            </a>
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($job->title . ' - ' . get_bloginfo('name')); ?>&url=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener" class="hs-share-link">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.953 4.57a10 10 0 0 1-2.825.775 4.958 4.958 0 0 0 2.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 0 0-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 0 0-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 0 1-2.228-.616v.06a4.923 4.923 0 0 0 3.946 4.827 4.996 4.996 0 0 1-2.212.085 4.936 4.936 0 0 0 4.604 3.417 9.867 9.867 0 0 1-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 0 0 7.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0 0 24 4.59z"/></svg>
                                Twitter
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener" class="hs-share-link">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Facebook
                            </a>
                            <a href="mailto:?subject=<?php echo urlencode($job->title); ?>&body=<?php echo urlencode(get_permalink()); ?>" class="hs-share-link">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                Email
                            </a>
                            <button onclick="navigator.clipboard.writeText('<?php echo esc_js(get_permalink()); ?>');this.querySelector('span').textContent='<?php _e('Copied!', 'hiring-system'); ?>'" type="button" class="hs-share-link">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span><?php _e('Copy Link', 'hiring-system'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Form -->
            <div id="hs-apply-section" class="hs-apply-section">
                <div class="hs-apply-header">
                    <h3><?php _e('Apply for this Position', 'hiring-system'); ?></h3>
                    <p><?php _e('Fill out the form below to submit your application.', 'hiring-system'); ?></p>
                </div>
                <?php echo self::apply_form(['job_id' => $job->id]); ?>
            </div>
        </div>

        <style>
        /* ===== Job Detail Page - High Specificity to Override Elementor ===== */
        .hs-job-detail { max-width:1100px; margin:0 auto; padding:40px 20px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,sans-serif !important; }

        /* Back Link */
        .hs-job-detail .hs-back { display:inline-flex !important; align-items:center !important; gap:8px !important; color:#999 !important; text-decoration:none !important; font-size:14px !important; font-weight:500 !important; margin-bottom:20px !important; transition:all 0.25s !important; background:none !important; border:none !important; padding:0 !important; }
        .hs-job-detail .hs-back:hover { color:#B9184A !important; transform:translateX(-4px); }

        /* Header */
        .hs-job-detail .hs-detail-header { margin-bottom:20px !important; }
        .hs-job-detail .hs-detail-meta-top { display:flex !important; align-items:center !important; gap:16px !important; margin-bottom:14px !important; }
        .hs-job-detail .hs-detail-type { display:inline-block !important; padding:8px 18px !important; background:rgba(185,24,74,0.08) !important; color:#B9184A !important; font-size:12px !important; font-weight:700 !important; border-radius:50px !important; text-transform:capitalize !important; }
        .hs-job-detail .hs-detail-posted { font-size:13px !important; color:#aaa !important; font-weight:500 !important; }
        .hs-job-detail .hs-detail-title { font-size:36px !important; font-weight:800 !important; color:#1a1a2e !important; margin:0 0 10px !important; line-height:1.2 !important; letter-spacing:-0.3px !important; }
        .hs-job-detail .hs-detail-dept { font-size:16px !important; color:#999 !important; margin:0 !important; font-weight:500 !important; }

        /* Info Bar */
        .hs-job-detail .hs-info-bar { display:flex !important; gap:28px !important; flex-wrap:wrap !important; padding:18px 0 !important; margin-bottom:36px !important; border-top:1px solid #f0f0f0 !important; border-bottom:1px solid #f0f0f0 !important; }
        .hs-job-detail .hs-info-item { display:flex !important; align-items:center !important; gap:10px !important; color:#666 !important; font-size:14px !important; font-weight:500 !important; }
        .hs-job-detail .hs-info-item svg { color:#ccc !important; flex-shrink:0 !important; }

        /* Two Column Layout */
        .hs-job-detail .hs-detail-layout { display:grid !important; grid-template-columns:1fr 340px !important; gap:36px !important; margin-bottom:48px !important; }
        @media (max-width:900px) { .hs-job-detail .hs-detail-layout { grid-template-columns:1fr !important; } }

        /* Main Content Cards */
        .hs-job-detail .hs-detail-main { display:flex !important; flex-direction:column !important; gap:28px !important; }
        .hs-job-detail .hs-detail-card { background:#fff !important; border-radius:20px !important; padding:36px !important; box-shadow:0 4px 20px rgba(0,0,0,0.04) !important; border:1px solid #f5f5f5 !important; position:relative !important; overflow:hidden !important; }
        .hs-job-detail .hs-detail-card::before { content:'' !important; position:absolute !important; top:0 !important; left:0 !important; right:0 !important; height:4px !important; background:linear-gradient(90deg, #B9184A, #e85d8a) !important; }
        .hs-job-detail .hs-detail-card h3 { font-size:22px !important; font-weight:700 !important; color:#1a1a2e !important; margin:0 0 20px !important; padding-bottom:14px !important; border-bottom:2px solid #f5f5f5 !important; }
        .hs-job-detail .hs-detail-body { color:#555 !important; font-size:15px !important; line-height:1.8 !important; }
        .hs-job-detail .hs-detail-body p { margin:0 0 14px !important; }
        .hs-job-detail .hs-detail-body p:last-child { margin-bottom:0 !important; }
        .hs-job-detail .hs-detail-body ul, .hs-job-detail .hs-detail-body ol { margin:0 0 14px !important; padding-left:24px !important; }
        .hs-job-detail .hs-detail-body li { margin-bottom:8px !important; }
        .hs-job-detail .hs-detail-body strong { color:#1a1a2e !important; }

        /* Sidebar */
        .hs-job-detail .hs-detail-sidebar { display:flex !important; flex-direction:column !important; gap:24px !important; }
        .hs-job-detail .hs-sidebar-card { background:#fff !important; border-radius:20px !important; padding:28px !important; box-shadow:0 4px 20px rgba(0,0,0,0.04) !important; border:1px solid #f5f5f5 !important; }
        .hs-job-detail .hs-sidebar-card h4 { font-size:17px !important; font-weight:700 !important; color:#1a1a2e !important; margin:0 0 16px !important; }

        /* Apply CTA */
        .hs-job-detail .hs-apply-cta { text-align:center !important; background:linear-gradient(135deg, #1a1a3e 0%, #2d2d5a 100%) !important; color:#fff !important; border:none !important; position:relative !important; overflow:hidden !important; }
        .hs-job-detail .hs-apply-cta::before { content:'' !important; position:absolute !important; top:0 !important; left:0 !important; right:0 !important; height:4px !important; background:linear-gradient(90deg, #B9184A, #e85d8a) !important; }
        .hs-job-detail .hs-apply-cta h4 { color:#fff !important; font-size:20px !important; margin-bottom:10px !important; position:relative !important; z-index:1 !important; }
        .hs-job-detail .hs-apply-cta p { color:rgba(255,255,255,0.75) !important; font-size:14px !important; margin:0 0 24px !important; line-height:1.6 !important; position:relative !important; z-index:1 !important; }
        .hs-job-detail .hs-btn-apply-lg { display:block !important; width:100% !important; padding:16px !important; background:#B9184A !important; color:#fff !important; font-size:14px !important; font-weight:700 !important; text-decoration:none !important; border-radius:12px !important; text-transform:uppercase !important; letter-spacing:1px !important; transition:all 0.3s !important; text-align:center !important; border:none !important; cursor:pointer !important; position:relative !important; z-index:1 !important; box-shadow:0 4px 16px rgba(185,24,74,0.3) !important; }
        .hs-job-detail .hs-btn-apply-lg:hover { background:#96123D !important; transform:translateY(-3px) !important; box-shadow:0 8px 24px rgba(185,24,74,0.4) !important; }

        /* Share Grid */
        .hs-job-detail .hs-share-grid { display:grid !important; gap:10px !important; }
        .hs-job-detail .hs-share-link { display:flex !important; align-items:center !important; gap:12px !important; padding:12px 16px !important; border-radius:12px !important; color:#555 !important; font-size:14px !important; font-weight:600 !important; text-decoration:none !important; background:#f8f8f8 !important; transition:all 0.25s !important; border:none !important; cursor:pointer !important; width:100% !important; }
        .hs-job-detail .hs-share-link:hover { background:#fff5f8 !important; color:#B9184A !important; transform:translateX(4px); }
        .hs-job-detail .hs-share-link svg { flex-shrink:0 !important; }

        /* Apply Section */
        .hs-job-detail .hs-apply-section { background:#fff !important; border-radius:20px !important; padding:48px !important; box-shadow:0 4px 20px rgba(0,0,0,0.04) !important; border:1px solid #f5f5f5 !important; position:relative !important; overflow:hidden !important; }
        .hs-job-detail .hs-apply-section::before { content:'' !important; position:absolute !important; top:0 !important; left:0 !important; right:0 !important; height:4px !important; background:linear-gradient(90deg, #B9184A, #e85d8a) !important; }
        .hs-job-detail .hs-apply-header { text-align:center !important; margin-bottom:36px !important; }
        .hs-job-detail .hs-apply-header h3 { font-size:26px !important; font-weight:800 !important; color:#1a1a2e !important; margin:0 0 10px !important; letter-spacing:-0.3px !important; }
        .hs-job-detail .hs-apply-header p { color:#999 !important; margin:0 !important; font-size:15px !important; }

        /* ===== FORM OVERRIDES - Force against Elementor ===== */
        .hs-job-detail #hs-apply-wrapper .hs-apply-form { display:grid !important; gap:18px !important; }
        .hs-job-detail #hs-apply-wrapper .hs-form-row { display:grid !important; gap:18px !important; }
        .hs-job-detail #hs-apply-wrapper .hs-form-row-2 { grid-template-columns:1fr 1fr !important; }
        @media (max-width:600px) { .hs-job-detail #hs-apply-wrapper .hs-form-row-2 { grid-template-columns:1fr !important; } }
        
        .hs-job-detail #hs-apply-wrapper .hs-form-group { display:flex !important; flex-direction:column !important; gap:6px !important; margin:0 !important; padding:0 !important; border:none !important; }
        .hs-job-detail #hs-apply-wrapper .hs-form-group label { font-weight:700 !important; font-size:14px !important; color:#333 !important; margin:0 !important; }
        .hs-job-detail #hs-apply-wrapper .hs-form-group input,
        .hs-job-detail #hs-apply-wrapper .hs-form-group select,
        .hs-job-detail #hs-apply-wrapper .hs-form-group textarea {
            padding:14px 18px !important;
            border:1px solid #e8e8e8 !important;
            border-radius:12px !important;
            font-size:14px !important;
            width:100% !important;
            box-sizing:border-box !important;
            background:#fafafa !important;
            color:#333 !important;
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif !important;
            line-height:1.5 !important;
            margin:0 !important;
            box-shadow:none !important;
            outline:none !important;
            min-height:auto !important;
            transition:all 0.25s !important;
        }
        .hs-job-detail #hs-apply-wrapper .hs-form-group input:focus,
        .hs-job-detail #hs-apply-wrapper .hs-form-group select:focus,
        .hs-job-detail #hs-apply-wrapper .hs-form-group textarea:focus {
            border-color:#B9184A !important;
            background:#fff !important;
            box-shadow:0 0 0 4px rgba(185,24,74,0.08) !important;
        }
        .hs-job-detail #hs-apply-wrapper .hs-form-group small { color:#aaa !important; font-size:12px !important; }
        .hs-job-detail #hs-apply-wrapper .hs-checkbox { display:flex !important; align-items:flex-start !important; gap:10px !important; font-weight:normal !important; cursor:pointer !important; color:#555 !important; font-size:14px !important; line-height:1.5 !important; }
        .hs-job-detail #hs-apply-wrapper .hs-checkbox input { width:auto !important; margin-top:3px !important; min-height:auto !important; padding:0 !important; accent-color:#B9184A !important; }
        
        .hs-job-detail #hs-apply-wrapper #hs-submit-btn {
            background:#B9184A !important;
            color:#fff !important;
            padding:16px 36px !important;
            font-size:16px !important;
            font-weight:700 !important;
            border:none !important;
            border-radius:12px !important;
            cursor:pointer !important;
            transition:all 0.3s !important;
            display:inline-block !important;
            width:auto !important;
            min-height:auto !important;
            box-shadow:0 4px 16px rgba(185,24,74,0.3) !important;
            text-transform:uppercase !important;
            letter-spacing:1px !important;
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif !important;
        }
        .hs-job-detail #hs-apply-wrapper #hs-submit-btn:hover {
            background:#96123D !important;
            transform:translateY(-3px) !important;
            box-shadow:0 8px 24px rgba(185,24,74,0.4) !important;
        }
        .hs-job-detail #hs-apply-wrapper #hs-submit-btn:disabled {
            opacity:0.6 !important;
            cursor:not-allowed !important;
            transform:none !important;
            box-shadow:none !important;
        }
        
        .hs-job-detail #hs-apply-wrapper .hs-form-message {
            padding:16px !important;
            border-radius:12px !important;
            margin-top:18px !important;
            font-size:14px !important;
            font-weight:500 !important;
        }
        .hs-job-detail #hs-apply-wrapper .hs-form-message.success { background:#f0f9f4 !important; color:#2e7d32 !important; border:1px solid #c8e6c9 !important; }
        .hs-job-detail #hs-apply-wrapper .hs-form-message.error { background:#fff5f5 !important; color:#c62828 !important; border:1px solid #ffcdd2 !important; }
        </style>
        <?php
        return ob_get_clean();
    }

    public static function apply_form($atts) {
        $job_id = !empty($atts['job_id']) ? (int)$atts['job_id'] : 0;
        wp_enqueue_script('jquery');

        ob_start();
        ?>
        <div id="hs-apply-wrapper">
            <form id="hs-apply-form" class="hs-apply-form">
                <input type="hidden" name="job_id" value="<?php echo esc_attr($job_id); ?>">
                <input type="hidden" name="source" value="website">

                <div class="hs-form-row hs-form-row-2">
                    <div class="hs-form-group">
                        <label><?php _e('Full Name *', 'hiring-system'); ?></label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="hs-form-group">
                        <label><?php _e('Email *', 'hiring-system'); ?></label>
                        <input type="email" name="email" required>
                    </div>
                </div>

                <div class="hs-form-row hs-form-row-2">
                    <div class="hs-form-group">
                        <label><?php _e('Phone', 'hiring-system'); ?></label>
                        <input type="tel" name="phone">
                    </div>
                    <div class="hs-form-group">
                        <label><?php _e('Experience (years)', 'hiring-system'); ?></label>
                        <input type="number" name="experience_years" min="0" max="50" value="0">
                    </div>
                </div>

                <div class="hs-form-row hs-form-row-2">
                    <div class="hs-form-group">
                        <label><?php _e('Country', 'hiring-system'); ?></label>
                        <input type="text" name="country">
                    </div>
                    <div class="hs-form-group">
                        <label><?php _e('City', 'hiring-system'); ?></label>
                        <input type="text" name="city">
                    </div>
                </div>

                <div class="hs-form-row hs-form-row-2">
                    <div class="hs-form-group">
                        <label><?php _e('Education', 'hiring-system'); ?></label>
                        <select name="education_level">
                            <option value=""><?php _e('Select...', 'hiring-system'); ?></option>
                            <option value="high-school"><?php _e('High School', 'hiring-system'); ?></option>
                            <option value="associate"><?php _e('Associate', 'hiring-system'); ?></option>
                            <option value="bachelor"><?php _e('Bachelor', 'hiring-system'); ?></option>
                            <option value="master"><?php _e('Master', 'hiring-system'); ?></option>
                            <option value="phd"><?php _e('PhD', 'hiring-system'); ?></option>
                        </select>
                    </div>
                    <div class="hs-form-group">
                        <label><?php _e('LinkedIn URL', 'hiring-system'); ?></label>
                        <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/...">
                    </div>
                </div>

                <div class="hs-form-group">
                    <label><?php _e('Portfolio URL', 'hiring-system'); ?></label>
                    <input type="url" name="portfolio_url" placeholder="https://...">
                </div>

                <div class="hs-form-group">
                    <label><?php _e('Skills', 'hiring-system'); ?></label>
                    <textarea name="skills" rows="2" placeholder="PHP, JavaScript, WordPress, React..."></textarea>
                    <small><?php _e('Separate skills with commas', 'hiring-system'); ?></small>
                </div>

                <div class="hs-form-group">
                    <label><?php _e('Cover Letter', 'hiring-system'); ?></label>
                    <textarea name="cover_letter" rows="4"></textarea>
                </div>

                                <div class="hs-form-group">
                    <label><?php _e('Resume/CV * (PDF, DOC, DOCX, max 5MB)', 'hiring-system'); ?></label>
                    <input type="file" name="cv" id="hs_cv" accept=".pdf,.doc,.docx" required>
                </div>

                <div class="hs-form-group">
                    <label><?php _e('Portfolio  (PDF, DOC, DOCX, max 5MB)', 'hiring-system'); ?></label>
                    <input type="file" name="portfolio" id="hs_portfolio" accept=".pdf,.doc,.docx" >
                    <small><?php _e('Upload your portfolio, work samples, or supporting documents.', 'hiring-system'); ?></small>
                </div>

                <div class="hs-form-group">
                    <label class="hs-checkbox">
                        <input type="checkbox" name="privacy_consent" value="1" required>
                        <?php _e('I agree to the privacy policy and consent to processing my personal data for this application.', 'hiring-system'); ?>
                    </label>
                </div>

                <button type="submit" id="hs-submit-btn" class="hs-btn hs-btn-primary hs-btn-large">
                    <?php _e('Submit Application', 'hiring-system'); ?>
                </button>

                <div id="hs-form-message" class="hs-form-message" style="display:none;"></div>
            </form>
        </div>

        <style>
        .hs-apply-form { display:grid; gap:15px; }
        .hs-form-row { display:grid; gap:15px; }
        .hs-form-row-2 { grid-template-columns:1fr 1fr; }
        .hs-form-group { display:flex; flex-direction:column; gap:5px; }
        .hs-form-group label { font-weight:600; font-size:14px; }
        .hs-form-group input, .hs-form-group select, .hs-form-group textarea {
            padding:10px; border:1px solid #ddd; border-radius:4px; font-size:14px; width:100%; box-sizing:border-box;
        }
        .hs-form-group small { color:#646970; font-size:12px; }
        .hs-checkbox { display:flex; align-items:flex-start; gap:8px; font-weight:normal !important; cursor:pointer; }
        .hs-checkbox input { width:auto; margin-top:2px; }
        .hs-btn-large { padding:14px 32px; font-size:16px; }
        .hs-form-message { padding:15px; border-radius:4px; margin-top:15px; }
        .hs-form-message.success { background:#e8f5e9; color:#2e7d32; }
        .hs-form-message.error { background:#ffebee; color:#c62828; }
        @media (max-width:600px) { .hs-form-row-2 { grid-template-columns:1fr; } }
        </style>

                <script>
        (function() {
            var form = document.getElementById('hs-apply-form');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var btn = document.getElementById('hs-submit-btn');
                var msg = document.getElementById('hs-form-message');
                var fileInput = document.getElementById('hs_cv');

                // Validate CV only
                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    showMessage('<?php _e('Please select a CV file.', 'hiring-system'); ?>', 'error');
                    return false;
                }

                var file = fileInput.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    showMessage('<?php _e('CV file exceeds 5MB limit.', 'hiring-system'); ?>', 'error');
                    return false;
                }

                btn.disabled = true;
                btn.textContent = '<?php _e('Submitting...', 'hiring-system'); ?>';
                msg.style.display = 'none';

                var formData = new FormData(form);

                fetch('<?php echo esc_url(rest_url('hs/v1/apply')); ?>', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' },
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showMessage(
                            '<?php _e('Application submitted successfully!', 'hiring-system'); ?>' +
                            (data.portal_url ? ' <a href="' + data.portal_url + '"><?php _e('Track your status', 'hiring-system'); ?></a>' : ''),
                            'success'
                        );
                        form.reset();
                    } else {
                        showMessage(data.message || '<?php _e('An error occurred. Please try again.', 'hiring-system'); ?>', 'error');
                    }
                })
                .catch(function(err) {
                    showMessage('<?php _e('Error:', 'hiring-system'); ?> ' + err.message, 'error');
                })
                .finally(function() {
                    btn.disabled = false;
                    btn.textContent = '<?php _e('Submit Application', 'hiring-system'); ?>';
                });

                return false;
            });

            function showMessage(text, type) {
                var msg = document.getElementById('hs-form-message');
                msg.className = 'hs-form-message ' + type;
                msg.innerHTML = text;
                msg.style.display = 'block';
                msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public static function portal($atts) {
    $app_id = absint($_GET['app_id'] ?? 0);
    $token = sanitize_text_field($_GET['token'] ?? '');

    if (!$app_id || !$token) {
        return self::portal_error(__('Please access this page using the link from your confirmation email.', 'hiring-system'));
    }

    $app = HS_Application::find_with_relations($app_id);
    if (!$app || !hash_equals($app->access_token, $token)) {
        return self::portal_error(__('Invalid or expired access link.', 'hiring-system'), true);
    }

    $status_labels = [
        'new' => __('Application Received', 'hiring-system'),
        'shortlisted' => __('Shortlisted', 'hiring-system'),
        'interview' => __('Interview Stage', 'hiring-system'),
        'offer' => __('Offer Extended', 'hiring-system'),
        'hired' => __('Hired!', 'hiring-system'),
        'rejected' => __('Not Selected', 'hiring-system'),
        'withdrawn' => __('Withdrawn', 'hiring-system'),
    ];

    $status_descriptions = [
        'new' => __('Your application has been received and is awaiting initial review.', 'hiring-system'),
        'shortlisted' => __('Congratulations! You have been shortlisted for the next stage.', 'hiring-system'),
        'interview' => __('You are currently in the interview process.', 'hiring-system'),
        'offer' => __('We have extended an offer for this position. Please check your email for details.', 'hiring-system'),
        'hired' => __('Welcome to the team!', 'hiring-system'),
        'rejected' => __('Thank you for your interest. We have decided to move forward with other candidates.', 'hiring-system'),
        'withdrawn' => __('This application has been withdrawn.', 'hiring-system'),
    ];

    $status_colors = [
        'new' => '#B9184A',
        'shortlisted' => '#6a1b9a',
        'interview' => '#2e7d32',
        'offer' => '#f57f17',
        'hired' => '#1b5e20',
        'rejected' => '#c62828',
        'withdrawn' => '#616161',
    ];

    $status_bg = [
        'new' => 'rgba(185,24,74,0.08)',
        'shortlisted' => '#f3e5f5',
        'interview' => '#e8f5e9',
        'offer' => '#fff8e1',
        'hired' => '#c8e6c9',
        'rejected' => '#ffebee',
        'withdrawn' => '#f5f5f5',
    ];

    $site_logo = get_custom_logo() ? wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full')[0] : '';

    ob_start();
    ?>
    <div class="hs-portal">
        <!-- Site Logo -->
        <?php if ($site_logo): ?>
        <div class="hs-portal-logo">
            <img src="<?php echo esc_url($site_logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" loading="lazy">
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="hs-portal-header">
            <span class="hs-portal-label"><?php _e('CANDIDATE PORTAL', 'hiring-system'); ?></span>
            <h1><?php _e('Application Status', 'hiring-system'); ?></h1>
            <p class="hs-portal-subtitle"><?php echo esc_html($app->job->title); ?></p>
        </div>

        <!-- Status Card -->
        <div class="hs-portal-status" style="border-color:<?php echo $status_colors[$app->status]; ?>">
            <div class="hs-status-icon" style="background:<?php echo $status_colors[$app->status]; ?>">
                <?php echo strtoupper(substr($app->status, 0, 1)); ?>
            </div>
            <div class="hs-status-info">
                <h2 style="color:<?php echo $status_colors[$app->status]; ?>"><?php echo esc_html($status_labels[$app->status]); ?></h2>
                <p><?php echo esc_html($status_descriptions[$app->status]); ?></p>
            </div>
        </div>

        <!-- Score Card -->
        <div class="hs-portal-score">
            <div class="hs-score-circle">
                <svg viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#f5f5f5" stroke-width="8"/>
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#B9184A" stroke-width="8"
                        stroke-dasharray="<?php echo 283 * (($app->score ?: 0) / 100); ?> 283"
                        stroke-linecap="round" transform="rotate(-90 50 50)"/>
                </svg>
                <div class="hs-score-number"><?php echo $app->score ? round($app->score, 1) : '—'; ?></div>
            </div>
            <p class="hs-score-label"><?php _e('Application Score', 'hiring-system'); ?> <span>/100</span></p>
        </div>

        <!-- Two Column Layout -->
        <div class="hs-portal-layout">
            <!-- Main: Details -->
            <div class="hs-portal-main">
                <div class="hs-portal-card">
                    <h3><?php _e('Application Details', 'hiring-system'); ?></h3>
                    <div class="hs-detail-rows">
                        <div class="hs-detail-row">
                            <span class="hs-detail-label"><?php _e('Position', 'hiring-system'); ?></span>
                            <span class="hs-detail-value"><?php echo esc_html($app->job->title); ?></span>
                        </div>
                        <div class="hs-detail-row">
                            <span class="hs-detail-label"><?php _e('Department', 'hiring-system'); ?></span>
                            <span class="hs-detail-value"><?php echo esc_html($app->job->department); ?></span>
                        </div>
                        <div class="hs-detail-row">
                            <span class="hs-detail-label"><?php _e('Location', 'hiring-system'); ?></span>
                            <span class="hs-detail-value"><?php echo esc_html($app->job->location); ?></span>
                        </div>
                        <div class="hs-detail-row">
                            <span class="hs-detail-label"><?php _e('Applied On', 'hiring-system'); ?></span>
                            <span class="hs-detail-value"><?php echo date_i18n(get_option('date_format'), strtotime($app->applied_at)); ?></span>
                        </div>
                        <div class="hs-detail-row">
                            <span class="hs-detail-label"><?php _e('Application ID', 'hiring-system'); ?></span>
                            <span class="hs-detail-value">#<?php echo $app->id; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="hs-portal-card">
                    <h3><?php _e('Activity Timeline', 'hiring-system'); ?></h3>
                    <div class="hs-timeline">
                        <?php foreach ($app->events as $event): ?>
                        <div class="hs-timeline-entry">
                            <div class="hs-timeline-dot"></div>
                            <div class="hs-timeline-body">
                                <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $event->event_type))); ?></strong>
                                <span class="hs-timeline-date"><?php echo human_time_diff(strtotime($event->created_at), current_time('timestamp')); ?> ago</span>
                                <p><?php echo esc_html($event->message); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($app->events)): ?>
                        <p class="hs-no-data"><?php _e('No activity yet.', 'hiring-system'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="hs-portal-sidebar">
                <div class="hs-portal-card hs-portal-cta">
                    <h4><?php _e('Need Help?', 'hiring-system'); ?></h4>
                    <p><?php _e('If you have questions about your application, feel free to reach out.', 'hiring-system'); ?></p>
                    <a href="mailto:<?php echo esc_attr(HS_Settings::get('notification_email') ?: get_option('admin_email')); ?>?subject=<?php echo urlencode('Re: Application #' . $app->id); ?>" class="hs-btn-portal">
                        <?php _e('Contact Us', 'hiring-system'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* ===== Candidate Portal — Matches Job Detail Design ===== */
    .hs-portal { max-width:1100px; margin:0 auto; padding:40px 20px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,sans-serif; }

    /* Site Logo */
    .hs-portal-logo { text-align:center; margin-bottom:20px; }
    .hs-portal-logo img { max-height:70px; width:auto; object-fit:contain; display:block; margin:0 auto; opacity:0.75; transition:opacity 0.3s, transform 0.3s; }
    .hs-portal-logo img:hover { opacity:1; transform:scale(1.03); }

    /* Header */
    .hs-portal-header { text-align:center; margin-bottom:48px; }
    .hs-portal-label { display:inline-block; font-size:11px; font-weight:700; color:#B9184A; text-transform:uppercase; letter-spacing:3px; margin-bottom:16px; }
    .hs-portal-header h1 { font-size:36px; font-weight:800; color:#1a1a2e; margin:0 0 10px; line-height:1.2; letter-spacing:-0.3px; }
    .hs-portal-subtitle { color:#999; font-size:16px; margin:0; font-weight:500; }

    /* Status Card */
    .hs-portal-status { display:flex; align-items:center; gap:20px; background:#fff; padding:28px 36px; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #f5f5f5; border-left:4px solid; margin-bottom:28px; position:relative; overflow:hidden; }
    .hs-portal-status::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #B9184A, #e85d8a); opacity:0.3; }
    .hs-status-icon { width:60px; height:60px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; flex-shrink:0; box-shadow:0 4px 14px rgba(0,0,0,0.15); }
    .hs-status-info h2 { margin:0 0 6px; font-size:22px; font-weight:700; }
    .hs-status-info p { margin:0; color:#888; font-size:14px; line-height:1.6; }

    /* Score Card */
    .hs-portal-score { text-align:center; background:#fff; padding:36px; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #f5f5f5; margin-bottom:28px; position:relative; overflow:hidden; }
    .hs-portal-score::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #B9184A, #e85d8a); }
    .hs-score-circle { width:140px; height:140px; margin:0 auto 16px; position:relative; }
    .hs-score-circle svg { width:100%; height:100%; }
    .hs-score-number { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:32px; font-weight:800; color:#1a1a2e; }
    .hs-score-label { margin:0; color:#999; font-size:14px; font-weight:500; }
    .hs-score-label span { color:#B9184A; font-weight:700; }

    /* Two Column Layout */
    .hs-portal-layout { display:grid; grid-template-columns:1fr 340px; gap:28px; }
    @media (max-width:900px) { .hs-portal-layout { grid-template-columns:1fr; } }

    /* Cards */
    .hs-portal-card { background:#fff; border-radius:20px; padding:28px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #f5f5f5; margin-bottom:20px; position:relative; overflow:hidden; }
    .hs-portal-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #B9184A, #e85d8a); }
    .hs-portal-card h3 { font-size:20px; font-weight:700; color:#1a1a2e; margin:0 0 20px; padding-bottom:14px; border-bottom:2px solid #f5f5f5; }
    .hs-portal-card h4 { font-size:17px; font-weight:700; color:#1a1a2e; margin:0 0 12px; }

    /* Detail Rows */
    .hs-detail-rows { display:flex; flex-direction:column; gap:0; }
    .hs-detail-row { display:flex; justify-content:space-between; align-items:center; padding:14px 0; border-bottom:1px solid #f5f5f5; }
    .hs-detail-row:last-child { border-bottom:none; }
    .hs-detail-label { color:#999; font-size:14px; font-weight:500; }
    .hs-detail-value { color:#1a1a2e; font-size:14px; font-weight:600; text-align:right; }

    /* Timeline */
    .hs-timeline { position:relative; padding-left:24px; }
    .hs-timeline::before { content:''; position:absolute; left:5px; top:0; bottom:0; width:2px; background:#f0f0f0; }
    .hs-timeline-entry { position:relative; margin-bottom:20px; }
    .hs-timeline-dot { position:absolute; left:-22px; top:6px; width:10px; height:10px; border-radius:50%; background:#B9184A; border:2px solid #fff; box-shadow:0 0 0 3px rgba(185,24,74,0.1); }
    .hs-timeline-body strong { color:#1a1a2e; font-size:14px; }
    .hs-timeline-date { color:#aaa; font-size:12px; margin-left:8px; font-weight:500; }
    .hs-timeline-body p { margin:6px 0 0; color:#888; font-size:13px; line-height:1.6; }
    .hs-no-data { color:#aaa; font-size:14px; text-align:center; padding:20px; margin:0; }

    /* Sidebar CTA */
    .hs-portal-cta { text-align:center; background:linear-gradient(135deg, #1a1a3e 0%, #2d2d5a 100%) !important; color:#fff !important; border:none !important; }
    .hs-portal-cta::before { background:linear-gradient(90deg, #B9184A, #e85d8a) !important; }
    .hs-portal-cta h4 { color:#fff !important; font-size:18px !important; margin-bottom:8px !important; position:relative; z-index:1; }
    .hs-portal-cta p { color:rgba(255,255,255,0.75) !important; font-size:13px !important; margin:0 0 20px !important; line-height:1.6 !important; position:relative; z-index:1; }
    .hs-btn-portal { display:block !important; width:100% !important; padding:14px !important; background:#B9184A !important; color:#fff !important; font-size:13px !important; font-weight:700 !important; text-decoration:none !important; border-radius:12px !important; text-transform:uppercase !important; letter-spacing:1px !important; transition:all 0.3s !important; text-align:center !important; border:none !important; cursor:pointer !important; position:relative !important; z-index:1 !important; box-shadow:0 4px 16px rgba(185,24,74,0.3) !important; }
    .hs-btn-portal:hover { background:#96123D !important; transform:translateY(-2px) !important; box-shadow:0 8px 24px rgba(185,24,74,0.4) !important; }

    /* Error State */
    .hs-portal-error { max-width:600px; margin:60px auto; text-align:center; padding:48px; background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #f5f5f5; position:relative; overflow:hidden; }
    .hs-portal-error::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #B9184A, #e85d8a); }
    .hs-portal-error h2 { font-size:28px; font-weight:800; color:#1a1a2e; margin:0 0 12px; }
    .hs-portal-error p { color:#999; font-size:15px; margin:0; line-height:1.7; }
    </style>
    <?php
    return ob_get_clean();
}

private static function portal_error($message, $is_denied = false) {
    $site_logo = get_custom_logo() ? wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full')[0] : '';
    $title = $is_denied ? __('Access Denied', 'hiring-system') : __('Candidate Portal', 'hiring-system');
    
    ob_start();
    ?>
    <div class="hs-portal">
        <?php if ($site_logo): ?>
        <div class="hs-portal-logo">
            <img src="<?php echo esc_url($site_logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" loading="lazy">
        </div>
        <?php endif; ?>
        <div class="hs-portal-error">
            <h2><?php echo esc_html($title); ?></h2>
            <p><?php echo esc_html($message); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
}
