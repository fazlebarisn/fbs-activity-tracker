<?php
/**
 * Database management class for FBS Activity Tracker
 *
 * @package FBS_Activity_Tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FBS Activity Tracker Database Class
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */
class FBS_Activity_Tracker_Database {

    /**
     * Single instance of the class
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var FBS_Activity_Tracker_Database
     */
    private static $instance = null;

    /**
     * Database table name
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var string
     */
    private $table_name;

    /**
     * WordPress database object
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var wpdb
     */
    private $wpdb;

    /**
     * Get single instance
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @return FBS_Activity_Tracker_Database
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
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'fbs_activity_logs';
        
        // Hook into cleanup event
        add_action('fbs_at_cleanup_logs', array($this, 'cleanup_old_logs'));
    }

    /**
     * Create the activity logs table
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            user_name VARCHAR(255),
            user_email VARCHAR(255),
            user_ip VARCHAR(45),
            action_type VARCHAR(100) NOT NULL,
            object_type VARCHAR(100),
            object_id BIGINT(20),
            object_name VARCHAR(255),
            details LONGTEXT,
            timestamp DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY timestamp (timestamp),
            KEY object_type (object_type),
            KEY object_id (object_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Update database version
        update_option('fbs_at_db_version', FBS_ACTIVITY_TRACKER_VERSION);
    }

    /**
     * Insert a new activity log
     *
     * @param array $log_data Log data array
     * @return int|false Log ID on success, false on failure
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function insert_log($log_data) {
        // Sanitize and validate data
        $sanitized_data = $this->sanitize_log_data($log_data);
        
        if (!$sanitized_data) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            $sanitized_data,
            array(
                '%d', // user_id
                '%s', // user_name
                '%s', // user_email
                '%s', // user_ip
                '%s', // action_type
                '%s', // object_type
                '%d', // object_id
                '%s', // object_name
                '%s', // details
                '%s'  // timestamp
            )
        );

        if ($result === false) {
            error_log('FBS Activity Tracker: Failed to insert log - ' . $this->wpdb->last_error);
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get activity logs with filters
     *
     * @param array $filters Filter parameters
     * @param int $limit Number of logs to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of log objects
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_logs($filters = array(), $limit = 50, $offset = 0) {
        $where_conditions = array('1=1');
        $where_values = array();

        // User filter
        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = intval($filters['user_id']);
        }

        // Action type filter
        if (!empty($filters['action_type'])) {
            $where_conditions[] = 'action_type = %s';
            $where_values[] = sanitize_text_field($filters['action_type']);
        }

        // Object type filter
        if (!empty($filters['object_type'])) {
            $where_conditions[] = 'object_type = %s';
            $where_values[] = sanitize_text_field($filters['object_type']);
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'timestamp >= %s';
            $where_values[] = sanitize_text_field($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'timestamp <= %s';
            $where_values[] = sanitize_text_field($filters['date_to']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search_term = '%' . $this->wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $where_conditions[] = '(user_name LIKE %s OR object_name LIKE %s OR details LIKE %s)';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE {$where_clause} 
             ORDER BY timestamp DESC 
             LIMIT %d OFFSET %d",
            array_merge($where_values, array($limit, $offset))
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get total count of logs with filters
     *
     * @param array $filters Filter parameters
     * @return int Total count
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_logs_count($filters = array()) {
        $where_conditions = array('1=1');
        $where_values = array();

        // Apply same filters as get_logs
        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = intval($filters['user_id']);
        }

        if (!empty($filters['action_type'])) {
            $where_conditions[] = 'action_type = %s';
            $where_values[] = sanitize_text_field($filters['action_type']);
        }

        if (!empty($filters['object_type'])) {
            $where_conditions[] = 'object_type = %s';
            $where_values[] = sanitize_text_field($filters['object_type']);
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'timestamp >= %s';
            $where_values[] = sanitize_text_field($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'timestamp <= %s';
            $where_values[] = sanitize_text_field($filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search_term = '%' . $this->wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $where_conditions[] = '(user_name LIKE %s OR object_name LIKE %s OR details LIKE %s)';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}",
            $where_values
        );

        return intval($this->wpdb->get_var($sql));
    }

    /**
     * Get dashboard statistics
     *
     * @return array Statistics data
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_statistics() {
        $stats = array();

        // Today's activity count
        $today = date('Y-m-d');
        $stats['today_count'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(timestamp) = %s",
            $today
        ));

        // Most active users (last 30 days)
        $stats['top_users'] = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT user_id, user_name, COUNT(*) as activity_count 
             FROM {$this->table_name} 
             WHERE timestamp >= %s 
             GROUP BY user_id, user_name 
             ORDER BY activity_count DESC 
             LIMIT 5",
            date('Y-m-d', strtotime('-30 days'))
        ));

        // Most common action types (last 30 days)
        $stats['action_types'] = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT action_type, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE timestamp >= %s 
             GROUP BY action_type 
             ORDER BY count DESC 
             LIMIT 10",
            date('Y-m-d', strtotime('-30 days'))
        ));

        // Total logs count
        $stats['total_logs'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        return $stats;
    }

    /**
     * Delete logs by IDs
     *
     * @param array $log_ids Array of log IDs to delete
     * @return int|false Number of deleted rows or false on failure
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function delete_logs($log_ids) {
        if (empty($log_ids) || !is_array($log_ids)) {
            return false;
        }

        // Sanitize IDs
        $sanitized_ids = array_map('intval', $log_ids);
        $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})",
            $sanitized_ids
        );

        return $this->wpdb->query($sql);
    }

    /**
     * Clean up old logs
     *
     * @param int $days Number of days to keep logs
     * @return int|false Number of deleted rows or false on failure
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function cleanup_old_logs($days = null) {
        if ($days === null) {
            $days = get_option('fbs_at_retention_days', 30);
        }

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE timestamp < %s",
            $cutoff_date
        ));

        if ($result !== false) {
            error_log("FBS Activity Tracker: Cleaned up {$result} old logs");
        }

        return $result;
    }

    /**
     * Get table name
     *
     * @return string Table name
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Sanitize log data before insertion
     *
     * @param array $log_data Raw log data
     * @return array|false Sanitized data or false if invalid
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function sanitize_log_data($log_data) {
        $required_fields = array('user_id', 'action_type', 'timestamp');
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (!isset($log_data[$field])) {
                return false;
            }
        }

        $sanitized = array(
            'user_id' => intval($log_data['user_id']),
            'user_name' => sanitize_text_field($log_data['user_name'] ?? ''),
            'user_email' => sanitize_email($log_data['user_email'] ?? ''),
            'user_ip' => sanitize_text_field($log_data['user_ip'] ?? ''),
            'action_type' => sanitize_text_field($log_data['action_type']),
            'object_type' => sanitize_text_field($log_data['object_type'] ?? ''),
            'object_id' => intval($log_data['object_id'] ?? 0),
            'object_name' => sanitize_text_field($log_data['object_name'] ?? ''),
            'details' => sanitize_textarea_field($log_data['details'] ?? ''),
            'timestamp' => sanitize_text_field($log_data['timestamp'])
        );

        // Validate timestamp format
        if (!strtotime($sanitized['timestamp'])) {
            $sanitized['timestamp'] = current_time('mysql');
        }

        return $sanitized;
    }
}
