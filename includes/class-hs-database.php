<?php
if (!defined('ABSPATH')) exit;

class HS_Database {
    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE {$wpdb->prefix}hs_jobs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(200) NOT NULL,
            department varchar(100) DEFAULT NULL,
            location varchar(150) DEFAULT NULL,
            employment_type varchar(50) DEFAULT 'full-time',
            description longtext,
            requirements longtext,
            salary_min decimal(10,2) DEFAULT NULL,
            salary_max decimal(10,2) DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$charset};";
        dbDelta($sql);

        $sql = "CREATE TABLE {$wpdb->prefix}hs_candidates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            full_name varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            linkedin_url varchar(255) DEFAULT NULL,
            portfolio_url varchar(255) DEFAULT NULL,
            experience_years tinyint(3) unsigned DEFAULT 0,
            education_level varchar(50) DEFAULT NULL,
            skills text,
            cv_attachment_id bigint(20) unsigned DEFAULT NULL,
            portfolio_attachment_id bigint(20) unsigned DEFAULT NULL,
            resume_text longtext,
            privacy_consent tinyint(1) DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) {$charset};";
        dbDelta($sql);

        $sql = "CREATE TABLE {$wpdb->prefix}hs_applications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            candidate_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            status varchar(30) DEFAULT 'new',
            score decimal(5,2) DEFAULT 0.00,
            cover_letter longtext,
            notes longtext,
            source varchar(50) DEFAULT 'website',
            access_token varchar(64) DEFAULT NULL,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY candidate_job (candidate_id, job_id),
            KEY access_token (access_token)
        ) {$charset};";
        dbDelta($sql);

        $sql = "CREATE TABLE {$wpdb->prefix}hs_scores (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            category varchar(50) NOT NULL,
            value decimal(5,2) DEFAULT 0.00,
            weight decimal(5,2) DEFAULT 0.00,
            final_score decimal(5,2) DEFAULT 0.00,
            PRIMARY KEY  (id),
            UNIQUE KEY app_category (application_id, category)
        ) {$charset};";
        dbDelta($sql);

        $sql = "CREATE TABLE {$wpdb->prefix}hs_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY application_id (application_id)
        ) {$charset};";
        dbDelta($sql);

        $sql = "CREATE TABLE {$wpdb->prefix}hs_notes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            note text NOT NULL,
            is_private tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY application_id (application_id)
        ) {$charset};";
        dbDelta($sql);

        $sql = "CREATE TABLE {$wpdb->prefix}hs_interviews (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            interviewer_id bigint(20) unsigned NOT NULL,
            scheduled_at datetime NOT NULL,
            duration_minutes int DEFAULT 60,
            type enum('phone','video','in-person') DEFAULT 'video',
            meeting_link varchar(255) DEFAULT NULL,
            feedback longtext,
            score decimal(5,2) DEFAULT NULL,
            status enum('scheduled','completed','cancelled','no-show') DEFAULT 'scheduled',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY application_id (application_id)
        ) {$charset};";
        dbDelta($sql);

        self::maybe_upgrade();

        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('hs_manage_hiring');
            $admin->add_cap('hs_view_applications');
            $admin->add_cap('hs_edit_jobs');
            $admin->add_cap('hs_manage_interviews');
        }

        if (!get_role('hs_hr_manager')) {
            add_role('hs_hr_manager', 'HR Manager', [
                'read' => true,
                'hs_manage_hiring' => true,
                'hs_view_applications' => true,
                'hs_edit_jobs' => true,
                'hs_manage_interviews' => true,
            ]);
        }
        // Head of Department role
if (!get_role('hs_head_dept')) {
    add_role('hs_head_dept', 'Head of Department', [
        'read' => true,
        'hs_view_applications' => true,  // Can view but filtered by dept
        'hs_manage_interviews' => true,  // Can schedule interviews for their dept
        // Note: NO 'hs_edit_jobs' and NO 'hs_manage_hiring'
    ]);
}

        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        global $wpdb;
        $current_db_version = get_option('hs_db_version', '1.0.0');
        if ($current_db_version === HS_DB_VERSION) return;

        $charset = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // === CREATE MISSING TABLES ===
        $tables = [
            'hs_jobs' => "CREATE TABLE {$wpdb->prefix}hs_jobs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                slug varchar(200) NOT NULL,
                department varchar(100) DEFAULT NULL,
                location varchar(150) DEFAULT NULL,
                employment_type varchar(50) DEFAULT 'full-time',
                description longtext,
                requirements longtext,
                salary_min decimal(10,2) DEFAULT NULL,
                salary_max decimal(10,2) DEFAULT NULL,
                status varchar(20) DEFAULT 'draft',
                created_by bigint(20) unsigned DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY slug (slug)
            ) {$charset};",
            'hs_candidates' => "CREATE TABLE {$wpdb->prefix}hs_candidates (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                full_name varchar(255) NOT NULL,
                email varchar(100) NOT NULL,
                phone varchar(50) DEFAULT NULL,
                country varchar(100) DEFAULT NULL,
                city varchar(100) DEFAULT NULL,
                linkedin_url varchar(255) DEFAULT NULL,
                portfolio_url varchar(255) DEFAULT NULL,
                experience_years tinyint(3) unsigned DEFAULT 0,
                education_level varchar(50) DEFAULT NULL,
                skills text,
                cv_attachment_id bigint(20) unsigned DEFAULT NULL,
                resume_text longtext,
                privacy_consent tinyint(1) DEFAULT 0,
                ip_address varchar(45) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY email (email)    
            ) {$charset};",
            'hs_applications' => "CREATE TABLE {$wpdb->prefix}hs_applications (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                candidate_id bigint(20) unsigned NOT NULL,
                job_id bigint(20) unsigned NOT NULL,
                status varchar(30) DEFAULT 'new',
                score decimal(5,2) DEFAULT 0.00,
                cover_letter longtext,
                notes longtext,
                source varchar(50) DEFAULT 'website',
                access_token varchar(64) DEFAULT NULL,
                applied_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY candidate_job (candidate_id, job_id),
                KEY access_token (access_token)
            ) {$charset};",
            'hs_scores' => "CREATE TABLE {$wpdb->prefix}hs_scores (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                application_id bigint(20) unsigned NOT NULL,
                category varchar(50) NOT NULL,
                value decimal(5,2) DEFAULT 0.00,
                weight decimal(5,2) DEFAULT 0.00,
                final_score decimal(5,2) DEFAULT 0.00,
                PRIMARY KEY  (id),
                UNIQUE KEY app_category (application_id, category)
            ) {$charset};",
            'hs_events' => "CREATE TABLE {$wpdb->prefix}hs_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                application_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned DEFAULT NULL,
                event_type varchar(50) NOT NULL,
                message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY application_id (application_id)
            ) {$charset};",
            'hs_notes' => "CREATE TABLE {$wpdb->prefix}hs_notes (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                application_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                note text NOT NULL,
                is_private tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY application_id (application_id)
            ) {$charset};",
            'hs_interviews' => "CREATE TABLE {$wpdb->prefix}hs_interviews (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                application_id bigint(20) unsigned NOT NULL,
                interviewer_id bigint(20) unsigned NOT NULL,
                scheduled_at datetime NOT NULL,
                duration_minutes int DEFAULT 60,
                type enum('phone','video','in-person') DEFAULT 'video',
                meeting_link varchar(255) DEFAULT NULL,
                feedback longtext,
                score decimal(5,2) DEFAULT NULL,
                status enum('scheduled','completed','cancelled','no-show') DEFAULT 'scheduled',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY application_id (application_id)
            ) {$charset};"
        ];

        foreach ($tables as $table => $sql) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . $table));
            if (!$exists) {
                dbDelta($sql);
            }
        }

        // === ADD MISSING COLUMNS (explicit ALTER TABLE - more reliable than dbDelta) ===
                // wp_hs_candidates
        $cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}hs_candidates");
        if (!empty($cols)) {
            $col_names = array_map('strtolower', wp_list_pluck($cols, 'Field'));
            if (!in_array('privacy_consent', $col_names)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hs_candidates ADD COLUMN privacy_consent tinyint(1) DEFAULT 0 AFTER resume_text");
            }
            if (!in_array('ip_address', $col_names)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hs_candidates ADD COLUMN ip_address varchar(45) DEFAULT NULL AFTER privacy_consent");
            }
            if (!in_array('portfolio_attachment_id', $col_names)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hs_candidates ADD COLUMN portfolio_attachment_id bigint(20) unsigned DEFAULT NULL AFTER cv_attachment_id");
            }
        }

        if (version_compare($current_db_version, '2.0.3', '<')) {
            $wpdb->query("UPDATE {$wpdb->prefix}hs_applications SET status = 'new' WHERE status = 'screening'");
        }

        if (version_compare($current_db_version, '2.0.2', '<')) {
    // Migration for dept head feature — nothing to alter, uses usermeta
    update_option('hs_db_version', '2.0.2');
}

        // wp_hs_applications
        $cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}hs_applications");
        if (!empty($cols)) {
            $col_names = array_map('strtolower', wp_list_pluck($cols, 'Field'));
            if (!in_array('source', $col_names)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hs_applications ADD COLUMN source varchar(50) DEFAULT 'website' AFTER notes");
            }
            if (!in_array('access_token', $col_names)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hs_applications ADD COLUMN access_token varchar(64) DEFAULT NULL AFTER source");
            }
            if (!in_array('notes', $col_names)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hs_applications ADD COLUMN notes longtext AFTER cover_letter");
            }
            if (!in_array('cover_letter', $col_names)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hs_applications ADD COLUMN cover_letter longtext AFTER score");
            }
        }

        update_option('hs_db_version', HS_DB_VERSION);
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function uninstall() {
        global $wpdb;
        $tables = ['hs_jobs','hs_candidates','hs_applications','hs_scores','hs_events','hs_notes','hs_interviews'];
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        delete_option('hs_settings');
        delete_option('hs_dashboard_stats');
        delete_option('hs_db_version');
    }
}
