<?php
if (!defined('ABSPATH')) exit;

require_once HS_PLUGIN_DIR . 'includes/class-hs-database.php';
require_once HS_PLUGIN_DIR . 'includes/class-hs-settings.php';
require_once HS_PLUGIN_DIR . 'includes/functions-user-profile.php';
require_once HS_PLUGIN_DIR . 'includes/class-hs-rate-limiter.php';
require_once HS_PLUGIN_DIR . 'includes/functions-permissions.php';

require_once HS_PLUGIN_DIR . 'includes/models/class-hs-job.php';
require_once HS_PLUGIN_DIR . 'includes/models/class-hs-candidate.php';
require_once HS_PLUGIN_DIR . 'includes/models/class-hs-score.php';
require_once HS_PLUGIN_DIR . 'includes/models/class-hs-event.php';
require_once HS_PLUGIN_DIR . 'includes/models/class-hs-note.php';
require_once HS_PLUGIN_DIR . 'includes/models/class-hs-interview.php';
require_once HS_PLUGIN_DIR . 'includes/models/class-hs-application.php';

require_once HS_PLUGIN_DIR . 'includes/class-hs-notifications.php';
require_once HS_PLUGIN_DIR . 'includes/functions-scoring.php';
require_once HS_PLUGIN_DIR . 'includes/admin/class-hs-admin.php';

require_once HS_PLUGIN_DIR . 'includes/handlers/admin-handlers.php';
require_once HS_PLUGIN_DIR . 'includes/handlers/job-handlers.php';
require_once HS_PLUGIN_DIR . 'includes/handlers/application-handlers.php';
require_once HS_PLUGIN_DIR . 'includes/api/rest-api.php';

require_once HS_PLUGIN_DIR . 'includes/shortcodes/class-hs-shortcodes.php';
require_once HS_PLUGIN_DIR . 'includes/ajax/ajax-handlers.php';
require_once HS_PLUGIN_DIR . 'includes/cron/gdpr-cleanup.php';
require_once HS_PLUGIN_DIR . 'includes/dashboard/dashboard-widget.php';
