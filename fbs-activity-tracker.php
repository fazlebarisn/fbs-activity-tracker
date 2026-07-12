<?php
/**
 * Plugin Name: FBS Activity Tracker
 * Plugin URI: https://github.com/fazlebarisn/fbs-secure-optimize
 * Description: A modern, granular user activity and audit log WordPress plugin with a custom-designed dashboard interface.
 * Version: 1.1.1
 * Author: Fazle Bari
 * Author URI: https://github.com/fazlebarisn
 * Text Domain: fbs-activity-tracker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FBS_ACTIVITY_TRACKER_VERSION', '1.1.1');
define('FBS_ACTIVITY_TRACKER_PLUGIN_FILE', __FILE__);
define('FBS_ACTIVITY_TRACKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FBS_ACTIVITY_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FBS_ACTIVITY_TRACKER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main FBS Activity Tracker Class
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */
class FBS_Activity_Tracker {

    /**
     * Single instance of the class
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var FBS_Activity_Tracker
     */
    private static $instance = null;

    /**
     * Get single instance
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @return FBS_Activity_Tracker
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize hooks
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
    }

    /**
     * Load plugin dependencies
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function load_dependencies() {
        require_once FBS_ACTIVITY_TRACKER_PLUGIN_DIR . 'includes/class-fbs-activity-tracker-database.php';
        require_once FBS_ACTIVITY_TRACKER_PLUGIN_DIR . 'includes/class-fbs-activity-tracker-logger.php';
        require_once FBS_ACTIVITY_TRACKER_PLUGIN_DIR . 'includes/class-fbs-activity-tracker-ajax.php';
        require_once FBS_ACTIVITY_TRACKER_PLUGIN_DIR . 'includes/class-fbs-activity-tracker-assets.php';
        
        if (is_admin()) {
            require_once FBS_ACTIVITY_TRACKER_PLUGIN_DIR . 'admin/class-fbs-activity-tracker-admin.php';
        }
    }

    /**
     * Initialize plugin
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function init() {
        // Initialize components
        FBS_Activity_Tracker_Database::get_instance();
        FBS_Activity_Tracker_Logger::get_instance();
        FBS_Activity_Tracker_Assets::get_instance();
        
        if (is_admin()) {
            FBS_Activity_Tracker_Admin::get_instance();
        }
        
        // Initialize AJAX handlers
        FBS_Activity_Tracker_Ajax::get_instance();
    }

    /**
     * Plugin activation
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function activate() {
        // Create database table
        FBS_Activity_Tracker_Database::get_instance()->create_table();
        
        // Set default options
        add_option('fbsat_retention_days', 30);
        add_option('fbsat_auto_cleanup', true);
        
        // Schedule cleanup event
        if (!wp_next_scheduled('fbsat_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'fbsat_cleanup_logs');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('fbsat_cleanup_logs');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 * @return FBS_Activity_Tracker
 */
function fbsat_activity_tracker() {
    return FBS_Activity_Tracker::get_instance();
}

/**
 * Public helper to log custom activity events.
 *
 * @param array $event_data Event payload for logger.
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.1.0
 * @return void
 */
function fbsat_log_event($event_data) {
    if (!is_array($event_data)) {
        return;
    }

    /**
     * Fires when a custom event should be recorded by FBS Activity Tracker.
     *
     * @param array $event_data Event payload.
     */
    do_action('fbsat_log_event', $event_data);
}

// Start the plugin
fbsat_activity_tracker();