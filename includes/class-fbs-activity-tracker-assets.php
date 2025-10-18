<?php
/**
 * Assets management class for FBS Activity Tracker
 *
 * @package FBS_Activity_Tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FBS Activity Tracker Assets Class
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */
class FBS_Activity_Tracker_Assets {

    /**
     * Single instance of the class
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var FBS_Activity_Tracker_Assets
     */
    private static $instance = null;

    /**
     * Get single instance
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @return FBS_Activity_Tracker_Assets
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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'fbs-activity-tracker') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'fbs-activity-tracker-admin',
            FBS_ACTIVITY_TRACKER_PLUGIN_URL . 'admin/css/fbs-activity-tracker-admin.css',
            array(),
            FBS_ACTIVITY_TRACKER_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'fbs-activity-tracker-admin',
            FBS_ACTIVITY_TRACKER_PLUGIN_URL . 'admin/js/fbs-activity-tracker-admin.js',
            array(),
            FBS_ACTIVITY_TRACKER_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('fbs-activity-tracker-admin', 'fbsActivityTracker', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fbs_at_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'fbs-activity-tracker'),
                'error' => __('An error occurred. Please try again.', 'fbs-activity-tracker'),
                'noResults' => __('No activity logs found.', 'fbs-activity-tracker'),
                'confirmDelete' => __('Are you sure you want to delete the selected logs?', 'fbs-activity-tracker'),
                'deleteSuccess' => __('Selected logs have been deleted.', 'fbs-activity-tracker'),
                'exportSuccess' => __('Logs exported successfully.', 'fbs-activity-tracker'),
                'selectLogs' => __('Please select at least one log to delete.', 'fbs-activity-tracker'),
                'today' => __('Today', 'fbs-activity-tracker'),
                'yesterday' => __('Yesterday', 'fbs-activity-tracker'),
                'last7days' => __('Last 7 days', 'fbs-activity-tracker'),
                'last30days' => __('Last 30 days', 'fbs-activity-tracker'),
                'thisMonth' => __('This month', 'fbs-activity-tracker'),
                'lastMonth' => __('Last month', 'fbs-activity-tracker'),
                'custom' => __('Custom range', 'fbs-activity-tracker')
            ),
            'actions' => array(
                'getLogs' => 'fbs_at_get_activity_logs',
                'getStats' => 'fbs_at_get_statistics',
                'deleteLogs' => 'fbs_at_delete_logs',
                'exportLogs' => 'fbs_at_export_logs'
            )
        ));
    }

    /**
     * Enqueue frontend assets (if needed)
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Frontend assets can be added here if needed in the future
        // For now, the plugin is admin-only
    }

    /**
     * Get asset URL
     *
     * @param string $path Asset path relative to plugin directory
     * @return string Full asset URL
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_asset_url($path) {
        return FBS_ACTIVITY_TRACKER_PLUGIN_URL . ltrim($path, '/');
    }

    /**
     * Get asset path
     *
     * @param string $path Asset path relative to plugin directory
     * @return string Full asset path
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_asset_path($path) {
        return FBS_ACTIVITY_TRACKER_PLUGIN_DIR . ltrim($path, '/');
    }

    /**
     * Check if asset file exists
     *
     * @param string $path Asset path relative to plugin directory
     * @return bool True if file exists
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function asset_exists($path) {
        return file_exists($this->get_asset_path($path));
    }

    /**
     * Get asset version for cache busting
     *
     * @param string $path Asset path
     * @return string Asset version
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_asset_version($path) {
        $file_path = $this->get_asset_path($path);
        
        if (file_exists($file_path)) {
            return filemtime($file_path);
        }
        
        return FBS_ACTIVITY_TRACKER_VERSION;
    }
}
