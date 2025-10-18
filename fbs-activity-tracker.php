<?php
/**
 * Plugin Name: FBS Activity Tracker
 * Plugin URI: https://your-website.com/fbs-activity-tracker
 * Description: A modern, granular user activity and audit log WordPress plugin with a custom-designed dashboard interface.
 * Version: 1.0.0
 * Author: Fazle Bari
 * Author URI: https://your-website.com
 * Text Domain: fbs-activity-tracker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FBS_ACTIVITY_TRACKER_VERSION', '1.0.0');
define('FBS_ACTIVITY_TRACKER_PLUGIN_FILE', __FILE__);
define('FBS_ACTIVITY_TRACKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FBS_ACTIVITY_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FBS_ACTIVITY_TRACKER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main FBS Activity Tracker Class
 */
class FBS_Activity_Tracker {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Load plugin dependencies
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
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'fbs-activity-tracker',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database table
        FBS_Activity_Tracker_Database::get_instance()->create_table();
        
        // Set default options
        add_option('fbs_at_retention_days', 30);
        add_option('fbs_at_auto_cleanup', true);
        
        // Schedule cleanup event
        if (!wp_next_scheduled('fbs_at_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'fbs_at_cleanup_logs');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('fbs_at_cleanup_logs');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function fbs_activity_tracker() {
    return FBS_Activity_Tracker::get_instance();
}

// Start the plugin
fbs_activity_tracker();
