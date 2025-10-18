<?php
/**
 * Admin dashboard class for FBS Activity Tracker
 *
 * @package FBS_Activity_Tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FBS Activity Tracker Admin Class
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */
class FBS_Activity_Tracker_Admin {

    /**
     * Single instance of the class
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var FBS_Activity_Tracker_Admin
     */
    private static $instance = null;

    /**
     * Database instance
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var FBS_Activity_Tracker_Database
     */
    private $database;

    /**
     * Get single instance
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @return FBS_Activity_Tracker_Admin
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
        $this->database = FBS_Activity_Tracker_Database::get_instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }

    /**
     * Add admin menu
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Activity Tracker', 'fbs-activity-tracker'),
            __('Activity Tracker', 'fbs-activity-tracker'),
            'manage_options',
            'fbs-activity-tracker',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );
    }

    /**
     * Admin init
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function admin_init() {
        // Register settings if needed
        register_setting('fbs_at_settings', 'fbs_at_retention_days');
        register_setting('fbs_at_settings', 'fbs_at_auto_cleanup');
    }

    /**
     * Admin page callback
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function admin_page() {
        // Get users for filter dropdown
        $users = get_users(array(
            'fields' => array('ID', 'display_name', 'user_email'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        // Get action types for filter dropdown
        $action_types = $this->get_action_types();

        // Get object types for filter dropdown
        $object_types = $this->get_object_types();

        ?>
        <div class="fbs-at-admin-container">
            <!-- Header -->
            <div class="fbs-at-header">
                <div class="fbs-at-header-content">
                    <h1 class="fbs-at-title">
                        <span class="fbs-at-icon">📊</span>
                        <?php _e('Activity Tracker', 'fbs-activity-tracker'); ?>
                    </h1>
                    <p class="fbs-at-subtitle">
                        <?php _e('Monitor user activities and system changes in real-time', 'fbs-activity-tracker'); ?>
                    </p>
                </div>
                <div class="fbs-at-header-actions">
                    <button type="button" class="fbs-at-btn fbs-at-btn-secondary" id="fbs-at-refresh-btn">
                        <span class="fbs-at-btn-icon">🔄</span>
                        <?php _e('Refresh', 'fbs-activity-tracker'); ?>
                    </button>
                    <button type="button" class="fbs-at-btn fbs-at-btn-primary" id="fbs-at-export-btn">
                        <span class="fbs-at-btn-icon">📥</span>
                        <?php _e('Export', 'fbs-activity-tracker'); ?>
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="fbs-at-stats-grid" id="fbs-at-stats-container">
                <div class="fbs-at-stat-card">
                    <div class="fbs-at-stat-icon fbs-at-stat-icon-primary">📈</div>
                    <div class="fbs-at-stat-content">
                        <div class="fbs-at-stat-value" id="fbs-at-today-count">-</div>
                        <div class="fbs-at-stat-label"><?php _e('Today\'s Activity', 'fbs-activity-tracker'); ?></div>
                    </div>
                </div>
                <div class="fbs-at-stat-card">
                    <div class="fbs-at-stat-icon fbs-at-stat-icon-success">👥</div>
                    <div class="fbs-at-stat-content">
                        <div class="fbs-at-stat-value" id="fbs-at-active-users">-</div>
                        <div class="fbs-at-stat-label"><?php _e('Active Users', 'fbs-activity-tracker'); ?></div>
                    </div>
                </div>
                <div class="fbs-at-stat-card">
                    <div class="fbs-at-stat-icon fbs-at-stat-icon-warning">📝</div>
                    <div class="fbs-at-stat-content">
                        <div class="fbs-at-stat-value" id="fbs-at-total-logs">-</div>
                        <div class="fbs-at-stat-label"><?php _e('Total Logs', 'fbs-activity-tracker'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="fbs-at-filters">
                <div class="fbs-at-filters-row">
                    <div class="fbs-at-filter-group">
                        <label for="fbs-at-user-filter" class="fbs-at-filter-label">
                            <?php _e('User', 'fbs-activity-tracker'); ?>
                        </label>
                        <select id="fbs-at-user-filter" class="fbs-at-filter-select">
                            <option value=""><?php _e('All Users', 'fbs-activity-tracker'); ?></option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fbs-at-filter-group">
                        <label for="fbs-at-action-filter" class="fbs-at-filter-label">
                            <?php _e('Action Type', 'fbs-activity-tracker'); ?>
                        </label>
                        <select id="fbs-at-action-filter" class="fbs-at-filter-select">
                            <option value=""><?php _e('All Actions', 'fbs-activity-tracker'); ?></option>
                            <?php foreach ($action_types as $type => $label): ?>
                                <option value="<?php echo esc_attr($type); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fbs-at-filter-group">
                        <label for="fbs-at-object-filter" class="fbs-at-filter-label">
                            <?php _e('Object Type', 'fbs-activity-tracker'); ?>
                        </label>
                        <select id="fbs-at-object-filter" class="fbs-at-filter-select">
                            <option value=""><?php _e('All Objects', 'fbs-activity-tracker'); ?></option>
                            <?php foreach ($object_types as $type => $label): ?>
                                <option value="<?php echo esc_attr($type); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fbs-at-filter-group">
                        <label for="fbs-at-date-range" class="fbs-at-filter-label">
                            <?php _e('Date Range', 'fbs-activity-tracker'); ?>
                        </label>
                        <select id="fbs-at-date-range" class="fbs-at-filter-select">
                            <option value=""><?php _e('All Time', 'fbs-activity-tracker'); ?></option>
                            <option value="today"><?php _e('Today', 'fbs-activity-tracker'); ?></option>
                            <option value="yesterday"><?php _e('Yesterday', 'fbs-activity-tracker'); ?></option>
                            <option value="last7days"><?php _e('Last 7 days', 'fbs-activity-tracker'); ?></option>
                            <option value="last30days"><?php _e('Last 30 days', 'fbs-activity-tracker'); ?></option>
                            <option value="thismonth"><?php _e('This month', 'fbs-activity-tracker'); ?></option>
                            <option value="lastmonth"><?php _e('Last month', 'fbs-activity-tracker'); ?></option>
                            <option value="custom"><?php _e('Custom range', 'fbs-activity-tracker'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="fbs-at-filters-row" id="fbs-at-custom-date-row" style="display: none;">
                    <div class="fbs-at-filter-group">
                        <label for="fbs-at-date-from" class="fbs-at-filter-label">
                            <?php _e('From', 'fbs-activity-tracker'); ?>
                        </label>
                        <input type="date" id="fbs-at-date-from" class="fbs-at-filter-input">
                    </div>
                    <div class="fbs-at-filter-group">
                        <label for="fbs-at-date-to" class="fbs-at-filter-label">
                            <?php _e('To', 'fbs-activity-tracker'); ?>
                        </label>
                        <input type="date" id="fbs-at-date-to" class="fbs-at-filter-input">
                    </div>
                </div>

                <div class="fbs-at-filters-row">
                    <div class="fbs-at-filter-group fbs-at-filter-group-search">
                        <label for="fbs-at-search" class="fbs-at-filter-label">
                            <?php _e('Search', 'fbs-activity-tracker'); ?>
                        </label>
                        <div class="fbs-at-search-container">
                            <input type="text" id="fbs-at-search" class="fbs-at-filter-input fbs-at-search-input" 
                                    placeholder="<?php esc_attr_e('Search in logs...', 'fbs-activity-tracker'); ?>">
                            <button type="button" class="fbs-at-search-btn" id="fbs-at-search-btn">
                                <span class="fbs-at-search-icon">🔍</span>
                            </button>
                        </div>
                    </div>
                    <div class="fbs-at-filter-actions">
                        <button type="button" class="fbs-at-btn fbs-at-btn-primary" id="fbs-at-apply-filters">
                            <?php _e('Apply Filters', 'fbs-activity-tracker'); ?>
                        </button>
                        <button type="button" class="fbs-at-btn fbs-at-btn-secondary" id="fbs-at-clear-filters">
                            <?php _e('Clear', 'fbs-activity-tracker'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="fbs-at-bulk-actions" id="fbs-at-bulk-actions" style="display: none;">
                <div class="fbs-at-bulk-info">
                    <span id="fbs-at-selected-count">0</span> <?php _e('logs selected', 'fbs-activity-tracker'); ?>
                </div>
                <div class="fbs-at-bulk-buttons">
                    <button type="button" class="fbs-at-btn fbs-at-btn-danger" id="fbs-at-bulk-delete">
                        <span class="fbs-at-btn-icon">🗑️</span>
                        <?php _e('Delete Selected', 'fbs-activity-tracker'); ?>
                    </button>
                    <button type="button" class="fbs-at-btn fbs-at-btn-secondary" id="fbs-at-bulk-export">
                        <span class="fbs-at-btn-icon">📥</span>
                        <?php _e('Export Selected', 'fbs-activity-tracker'); ?>
                    </button>
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="fbs-at-activity-container">
                <div class="fbs-at-activity-header">
                    <h2 class="fbs-at-activity-title">
                        <?php _e('Activity Feed', 'fbs-activity-tracker'); ?>
                    </h2>
                    <div class="fbs-at-activity-controls">
                        <label class="fbs-at-checkbox-label">
                            <input type="checkbox" id="fbs-at-select-all" class="fbs-at-checkbox">
                            <span class="fbs-at-checkbox-text"><?php _e('Select All', 'fbs-activity-tracker'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="fbs-at-activity-feed" id="fbs-at-activity-feed">
                    <!-- Activity logs will be loaded here via AJAX -->
                </div>

                <div class="fbs-at-loading" id="fbs-at-loading" style="display: none;">
                    <div class="fbs-at-spinner"></div>
                    <span class="fbs-at-loading-text"><?php _e('Loading...', 'fbs-activity-tracker'); ?></span>
                </div>

                <div class="fbs-at-no-results" id="fbs-at-no-results" style="display: none;">
                    <div class="fbs-at-no-results-icon">📭</div>
                    <h3 class="fbs-at-no-results-title"><?php _e('No activity logs found', 'fbs-activity-tracker'); ?></h3>
                    <p class="fbs-at-no-results-text">
                        <?php _e('Try adjusting your filters or check back later for new activity.', 'fbs-activity-tracker'); ?>
                    </p>
                </div>

                <div class="fbs-at-load-more" id="fbs-at-load-more" style="display: none;">
                    <button type="button" class="fbs-at-btn fbs-at-btn-secondary fbs-at-btn-large" id="fbs-at-load-more-btn">
                        <?php _e('Load More', 'fbs-activity-tracker'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden form for AJAX requests -->
        <form id="fbs-at-ajax-form" style="display: none;">
            <input type="hidden" name="action" value="fbs_at_get_activity_logs">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('fbs_at_nonce'); ?>">
            <input type="hidden" name="limit" value="50">
            <input type="hidden" name="offset" value="0">
        </form>
        <?php
    }

    /**
     * Get action types for filter dropdown
     *
     * @return array Action types
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function get_action_types() {
        return array(
            'user_login' => __('User Login', 'fbs-activity-tracker'),
            'user_logout' => __('User Logout', 'fbs-activity-tracker'),
            'login_failed' => __('Login Failed', 'fbs-activity-tracker'),
            'post_created' => __('Post Created', 'fbs-activity-tracker'),
            'post_updated' => __('Post Updated', 'fbs-activity-tracker'),
            'post_published' => __('Post Published', 'fbs-activity-tracker'),
            'post_drafted' => __('Post Drafted', 'fbs-activity-tracker'),
            'post_trashed' => __('Post Trashed', 'fbs-activity-tracker'),
            'post_untrashed' => __('Post Restored', 'fbs-activity-tracker'),
            'post_deleted' => __('Post Deleted', 'fbs-activity-tracker'),
            'post_private' => __('Post Made Private', 'fbs-activity-tracker'),
            'plugin_activated' => __('Plugin Activated', 'fbs-activity-tracker'),
            'plugin_deactivated' => __('Plugin Deactivated', 'fbs-activity-tracker'),
            'theme_switched' => __('Theme Switched', 'fbs-activity-tracker'),
            'user_profile_updated' => __('Profile Updated', 'fbs-activity-tracker'),
            'user_registered' => __('User Registered', 'fbs-activity-tracker'),
            'option_updated' => __('Setting Updated', 'fbs-activity-tracker')
        );
    }

    /**
     * Get object types for filter dropdown
     *
     * @return array Object types
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function get_object_types() {
        return array(
            'user' => __('User', 'fbs-activity-tracker'),
            'post' => __('Post', 'fbs-activity-tracker'),
            'page' => __('Page', 'fbs-activity-tracker'),
            'plugin' => __('Plugin', 'fbs-activity-tracker'),
            'theme' => __('Theme', 'fbs-activity-tracker'),
            'option' => __('Setting', 'fbs-activity-tracker')
        );
    }
}