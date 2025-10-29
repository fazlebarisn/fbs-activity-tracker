<?php
/**
 * AJAX handlers class for FBS Activity Tracker
 *
 * @package FBS_Activity_Tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FBS Activity Tracker AJAX Class
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */
class FBS_Activity_Tracker_Ajax {

    /**
     * Single instance of the class
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var FBS_Activity_Tracker_Ajax
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
     * @return FBS_Activity_Tracker_Ajax
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
     * Initialize AJAX hooks
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function init_hooks() {
        // AJAX actions for logged-in users
        add_action('wp_ajax_fbsat_get_activity_logs', array($this, 'get_activity_logs'));
        add_action('wp_ajax_fbsat_get_statistics', array($this, 'get_statistics'));
        add_action('wp_ajax_fbsat_delete_logs', array($this, 'delete_logs'));
        add_action('wp_ajax_fbsat_export_logs', array($this, 'export_logs'));
    }

    /**
     * Get activity logs via AJAX
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_activity_logs() {
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'fbsat_nonce')) {
            wp_die(esc_html__('Security check failed.', 'fbs-activity-tracker'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'fbs-activity-tracker'));
        }

        // Sanitize and prepare filters
        $filters = array();
        
        if (!empty($_POST['user_id'])) {
            $filters['user_id'] = intval($_POST['user_id']);
        }
        
        if (!empty($_POST['action_type'])) {
            $filters['action_type'] = sanitize_text_field(wp_unslash($_POST['action_type']));
        }
        
        if (!empty($_POST['object_type'])) {
            $filters['object_type'] = sanitize_text_field(wp_unslash($_POST['object_type']));
        }
        
        if (!empty($_POST['date_from'])) {
            $filters['date_from'] = sanitize_text_field(wp_unslash($_POST['date_from']));
        }
        
        if (!empty($_POST['date_to'])) {
            $filters['date_to'] = sanitize_text_field(wp_unslash($_POST['date_to']));
        }
        
        if (!empty($_POST['search'])) {
            $filters['search'] = sanitize_text_field(wp_unslash($_POST['search']));
        }

        // Pagination
        $limit = intval($_POST['limit'] ?? 50);
        $offset = intval($_POST['offset'] ?? 0);

        // Get logs and total count
        $logs = $this->database->get_logs($filters, $limit, $offset);
        $total_count = $this->database->get_logs_count($filters);

        // Format logs for frontend
        $formatted_logs = array();
        foreach ($logs as $log) {
            $formatted_logs[] = array(
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user_name,
                'user_email' => $log->user_email,
                'user_ip' => $log->user_ip,
                'action_type' => $log->action_type,
                'object_type' => $log->object_type,
                'object_id' => $log->object_id,
                'object_name' => $log->object_name,
                'details' => $log->details,
                'timestamp' => $log->timestamp,
                'formatted_time' => $this->format_timestamp($log->timestamp),
                'action_label' => $this->get_action_label($log->action_type),
                'action_color' => $this->get_action_color($log->action_type),
                'user_avatar' => $this->get_user_avatar($log->user_id, $log->user_email)
            );
        }

        wp_send_json_success(array(
            'logs' => $formatted_logs,
            'total_count' => $total_count,
            'has_more' => ($offset + $limit) < $total_count
        ));
    }

    /**
     * Get statistics via AJAX
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_statistics() {
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'fbsat_nonce')) {
            wp_die(esc_html__('Security check failed.', 'fbs-activity-tracker'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'fbs-activity-tracker'));
        }

        $stats = $this->database->get_statistics();

        // Format statistics for frontend
        $formatted_stats = array(
            'today_count' => intval($stats['today_count']),
            'total_logs' => intval($stats['total_logs']),
            'top_users' => array(),
            'action_types' => array()
        );

        // Format top users
        foreach ($stats['top_users'] as $user) {
            $formatted_stats['top_users'][] = array(
                'user_id' => $user->user_id,
                'user_name' => $user->user_name,
                'activity_count' => intval($user->activity_count),
                'avatar' => $this->get_user_avatar($user->user_id)
            );
        }

        // Format action types
        foreach ($stats['action_types'] as $action) {
            $formatted_stats['action_types'][] = array(
                'action_type' => $action->action_type,
                'count' => intval($action->count),
                'label' => $this->get_action_label($action->action_type),
                'color' => $this->get_action_color($action->action_type)
            );
        }

        wp_send_json_success($formatted_stats);
    }

    /**
     * Delete logs via AJAX
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function delete_logs() {
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'fbsat_nonce')) {
            wp_die(esc_html__('Security check failed.', 'fbs-activity-tracker'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'fbs-activity-tracker'));
        }

        $log_ids = array();
        
        // Check if log_ids is sent as an array (log_ids[0], log_ids[1], etc.)
        if (isset($_POST['log_ids']) && is_array($_POST['log_ids'])) {
            $log_ids = array_map('intval', wp_unslash($_POST['log_ids']));
        }
        // Check if log_ids is sent as individual parameters (log_ids[0], log_ids[1], etc.)
        elseif (isset($_POST['log_ids[0]'])) {
            $log_ids = array();
            $i = 0;
            while (isset($_POST["log_ids[$i]"])) {
                $log_ids[] = intval(wp_unslash($_POST["log_ids[$i]"]));
                $i++;
            }
        }
        // Check if log_ids is sent as a JSON string
        elseif (isset($_POST['log_ids']) && is_string($_POST['log_ids'])) {
            $decoded_log_ids = json_decode(sanitize_text_field(wp_unslash($_POST['log_ids'])), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_log_ids)) {
                $log_ids = $decoded_log_ids;
            }
        }
        
        if (empty($log_ids) || !is_array($log_ids)) {
            wp_send_json_error(esc_html__('No logs selected for deletion.', 'fbs-activity-tracker'));
        }

        $deleted_count = $this->database->delete_logs($log_ids);

        if ($deleted_count !== false) {
            wp_send_json_success(array(
                // translators: %d is the number of logs deleted
                'message' => sprintf(esc_html__('%d logs deleted successfully.', 'fbs-activity-tracker'), $deleted_count),
                'deleted_count' => $deleted_count
            ));
        } else {
            wp_send_json_error(esc_html__('Failed to delete logs.', 'fbs-activity-tracker'));
        }
    }

    /**
     * Export logs via AJAX
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function export_logs() {
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'fbsat_nonce')) {
            wp_die(esc_html__('Security check failed.', 'fbs-activity-tracker'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'fbs-activity-tracker'));
        }

        // Prepare filters (same as get_activity_logs)
        $filters = array();
        
        if (!empty($_POST['user_id'])) {
            $filters['user_id'] = intval($_POST['user_id']);
        }
        
        if (!empty($_POST['action_type'])) {
            $filters['action_type'] = sanitize_text_field(wp_unslash($_POST['action_type']));
        }
        
        if (!empty($_POST['object_type'])) {
            $filters['object_type'] = sanitize_text_field(wp_unslash($_POST['object_type']));
        }
        
        if (!empty($_POST['date_from'])) {
            $filters['date_from'] = sanitize_text_field(wp_unslash($_POST['date_from']));
        }
        
        if (!empty($_POST['date_to'])) {
            $filters['date_to'] = sanitize_text_field(wp_unslash($_POST['date_to']));
        }
        
        if (!empty($_POST['search'])) {
            $filters['search'] = sanitize_text_field(wp_unslash($_POST['search']));
        }

        // Get all logs for export (no pagination)
        $logs = $this->database->get_logs($filters, 10000, 0);

        // Format for JSON export
        $export_data = array(
            'export_date' => current_time('mysql'),
            'total_logs' => count($logs),
            'filters_applied' => $filters,
            'logs' => array()
        );

        foreach ($logs as $log) {
            $export_data['logs'][] = array(
                'id' => $log->id,
                'user_name' => $log->user_name,
                'user_email' => $log->user_email,
                'user_ip' => $log->user_ip,
                'action_type' => $log->action_type,
                'action_label' => $this->get_action_label($log->action_type),
                'object_type' => $log->object_type,
                'object_name' => $log->object_name,
                'details' => $log->details,
                'timestamp' => $log->timestamp,
                'formatted_time' => $this->format_timestamp($log->timestamp)
            );
        }

        // Set headers for download
        $filename = 'fbs-activity-logs-' . gmdate('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($export_data, JSON_PRETTY_PRINT)));
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Format timestamp for display
     *
     * @param string $timestamp MySQL timestamp
     * @return string Formatted timestamp
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function format_timestamp($timestamp) {
        $time = strtotime($timestamp);
        $now = current_time('timestamp');
        $diff = $now - $time;

        if ($diff < 60) {
            return esc_html__('Just now', 'fbs-activity-tracker');
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            // translators: %d is the number of minutes
            return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'fbs-activity-tracker'), $minutes);
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            // translators: %d is the number of hours
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'fbs-activity-tracker'), $hours);
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            // translators: %d is the number of days
            return sprintf(_n('%d day ago', '%d days ago', $days, 'fbs-activity-tracker'), $days);
        } else {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $time);
        }
    }

    /**
     * Get action label
     *
     * @param string $action_type Action type
     * @return string Action label
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function get_action_label($action_type) {
        $labels = array(
            'user_login' => esc_html__('User Login', 'fbs-activity-tracker'),
            'user_logout' => esc_html__('User Logout', 'fbs-activity-tracker'),
            'login_failed' => esc_html__('Login Failed', 'fbs-activity-tracker'),
            'post_created' => esc_html__('Post Created', 'fbs-activity-tracker'),
            'post_updated' => esc_html__('Post Updated', 'fbs-activity-tracker'),
            'post_published' => esc_html__('Post Published', 'fbs-activity-tracker'),
            'post_drafted' => esc_html__('Post Drafted', 'fbs-activity-tracker'),
            'post_trashed' => esc_html__('Post Trashed', 'fbs-activity-tracker'),
            'post_untrashed' => esc_html__('Post Restored', 'fbs-activity-tracker'),
            'post_deleted' => esc_html__('Post Deleted', 'fbs-activity-tracker'),
            'post_private' => esc_html__('Post Made Private', 'fbs-activity-tracker'),
            'plugin_activated' => esc_html__('Plugin Activated', 'fbs-activity-tracker'),
            'plugin_deactivated' => esc_html__('Plugin Deactivated', 'fbs-activity-tracker'),
            'theme_switched' => esc_html__('Theme Switched', 'fbs-activity-tracker'),
            'user_profile_updated' => esc_html__('Profile Updated', 'fbs-activity-tracker'),
            'user_registered' => esc_html__('User Registered', 'fbs-activity-tracker'),
            'option_updated' => esc_html__('Setting Updated', 'fbs-activity-tracker')
        );

        return $labels[$action_type] ?? ucwords(str_replace('_', ' ', $action_type));
    }

    /**
     * Get action color
     *
     * @param string $action_type Action type
     * @return string Color class
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function get_action_color($action_type) {
        $colors = array(
            'user_login' => 'success',
            'user_logout' => 'secondary',
            'login_failed' => 'danger',
            'post_created' => 'success',
            'post_updated' => 'primary',
            'post_published' => 'success',
            'post_drafted' => 'warning',
            'post_trashed' => 'danger',
            'post_untrashed' => 'success',
            'post_deleted' => 'danger',
            'post_private' => 'warning',
            'plugin_activated' => 'success',
            'plugin_deactivated' => 'warning',
            'theme_switched' => 'primary',
            'user_profile_updated' => 'primary',
            'user_registered' => 'success',
            'option_updated' => 'primary'
        );

        return $colors[$action_type] ?? 'secondary';
    }

    /**
     * Get user avatar
     *
     * @param int $user_id User ID
     * @param string $user_email User email (fallback)
     * @return string Avatar HTML
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function get_user_avatar($user_id, $user_email = '') {
        if ($user_id > 0) {
            return get_avatar($user_id, 32, '', '', array('class' => 'fbs-at-avatar'));
        } elseif (!empty($user_email)) {
            return get_avatar($user_email, 32, '', '', array('class' => 'fbs-at-avatar'));
        } else {
            return '<div class="fbs-at-avatar fbs-at-avatar-placeholder">?</div>';
        }
    }
}