<?php
/**
 * Logger class for FBS Activity Tracker
 *
 * @package FBS_Activity_Tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FBS Activity Tracker Logger Class
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */
class FBS_Activity_Tracker_Logger {

    /**
     * Single instance of the class
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     * @var FBS_Activity_Tracker_Logger
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
     * @return FBS_Activity_Tracker_Logger
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
     * Initialize WordPress hooks
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function init_hooks() {
        // User authentication hooks
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        add_action('wp_login_failed', array($this, 'log_login_failed'));

        // Post/Page hooks
        add_action('transition_post_status', array($this, 'log_post_status_change'), 10, 3);
        add_action('delete_post', array($this, 'log_post_deleted'));
        add_action('untrash_post', array($this, 'log_post_untrashed'));

        // Plugin hooks
        add_action('activated_plugin', array($this, 'log_plugin_activated'));
        add_action('deactivated_plugin', array($this, 'log_plugin_deactivated'));

        // Theme hooks
        add_action('switch_theme', array($this, 'log_theme_switched'));

        // User profile hooks
        add_action('profile_update', array($this, 'log_user_profile_updated'), 10, 2);
        add_action('user_register', array($this, 'log_user_registered'));

        // Settings hooks
        add_action('updated_option', array($this, 'log_option_updated'), 10, 3);
    }

    /**
     * Log user login
     *
     * @param string $user_login Username
     * @param WP_User $user User object
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_user_login($user_login, $user) {
        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'user_login',
            'object_type' => 'user',
            'object_id' => $user->ID,
            'object_name' => $user->display_name,
            'details' => sprintf(__('User logged in from IP: %s', 'fbs-activity-tracker'), $this->get_user_ip()),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log user logout
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_user_logout() {
        $user = wp_get_current_user();
        
        if ($user->ID > 0) {
            $this->insert_log(array(
                'user_id' => $user->ID,
                'user_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_ip' => $this->get_user_ip(),
                'action_type' => 'user_logout',
                'object_type' => 'user',
                'object_id' => $user->ID,
                'object_name' => $user->display_name,
                'details' => __('User logged out', 'fbs-activity-tracker'),
                'timestamp' => current_time('mysql')
            ));
        }
    }

    /**
     * Log failed login attempt
     *
     * @param string $username Username that failed to login
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_login_failed($username) {
        $this->insert_log(array(
            'user_id' => 0,
            'user_name' => sanitize_text_field($username),
            'user_email' => '',
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'login_failed',
            'object_type' => 'user',
            'object_id' => 0,
            'object_name' => sanitize_text_field($username),
            'details' => sprintf(__('Failed login attempt for username: %s from IP: %s', 'fbs-activity-tracker'), $username, $this->get_user_ip()),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log post status changes
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_post_status_change($new_status, $old_status, $post) {
        // Skip auto-drafts and revisions
        if (in_array($post->post_type, array('revision', 'nav_menu_item'))) {
            return;
        }

        $user = wp_get_current_user();
        $action_type = '';
        $details = '';

        switch ($new_status) {
            case 'publish':
                if ($old_status === 'new' || $old_status === 'auto-draft') {
                    $action_type = 'post_created';
                    $details = sprintf(__('Created new %s: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                } else {
                    $action_type = 'post_published';
                    $details = sprintf(__('Published %s: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                }
                break;
            case 'draft':
                $action_type = 'post_drafted';
                $details = sprintf(__('Moved %s to draft: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                break;
            case 'trash':
                $action_type = 'post_trashed';
                $details = sprintf(__('Trashed %s: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                break;
            case 'private':
                $action_type = 'post_private';
                $details = sprintf(__('Made %s private: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                break;
        }

        // Also log updates to published posts
        if ($new_status === 'publish' && $old_status === 'publish') {
            $action_type = 'post_updated';
            $details = sprintf(__('Updated %s: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
        }

        if ($action_type) {
            $this->insert_log(array(
                'user_id' => $user->ID,
                'user_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_ip' => $this->get_user_ip(),
                'action_type' => $action_type,
                'object_type' => $post->post_type,
                'object_id' => $post->ID,
                'object_name' => $post->post_title,
                'details' => $details,
                'timestamp' => current_time('mysql')
            ));
        }
    }

    /**
     * Log post deletion
     *
     * @param int $post_id Post ID
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_post_deleted($post_id) {
        $post = get_post($post_id);
        
        if (!$post || in_array($post->post_type, array('revision', 'nav_menu_item'))) {
            return;
        }

        $user = wp_get_current_user();
        
        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'post_deleted',
            'object_type' => $post->post_type,
            'object_id' => $post->ID,
            'object_name' => $post->post_title,
            'details' => sprintf(__('Permanently deleted %s: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log post untrash
     *
     * @param int $post_id Post ID
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_post_untrashed($post_id) {
        $post = get_post($post_id);
        
        if (!$post || in_array($post->post_type, array('revision', 'nav_menu_item'))) {
            return;
        }

        $user = wp_get_current_user();
        
        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'post_untrashed',
            'object_type' => $post->post_type,
            'object_id' => $post->ID,
            'object_name' => $post->post_title,
            'details' => sprintf(__('Restored %s from trash: %s', 'fbs-activity-tracker'), $post->post_type, $post->post_title),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log plugin activation
     *
     * @param string $plugin Plugin file path
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_plugin_activated($plugin) {
        $user = wp_get_current_user();
        $plugin_name = $this->get_plugin_name($plugin);
        
        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'plugin_activated',
            'object_type' => 'plugin',
            'object_id' => 0,
            'object_name' => $plugin_name,
            'details' => sprintf(__('Activated plugin: %s', 'fbs-activity-tracker'), $plugin_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log plugin deactivation
     *
     * @param string $plugin Plugin file path
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_plugin_deactivated($plugin) {
        $user = wp_get_current_user();
        $plugin_name = $this->get_plugin_name($plugin);
        
        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'plugin_deactivated',
            'object_type' => 'plugin',
            'object_id' => 0,
            'object_name' => $plugin_name,
            'details' => sprintf(__('Deactivated plugin: %s', 'fbs-activity-tracker'), $plugin_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log theme switch
     *
     * @param string $new_name New theme name
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_theme_switched($new_name) {
        $user = wp_get_current_user();
        
        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'theme_switched',
            'object_type' => 'theme',
            'object_id' => 0,
            'object_name' => $new_name,
            'details' => sprintf(__('Switched to theme: %s', 'fbs-activity-tracker'), $new_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log user profile update
     *
     * @param int $user_id User ID
     * @param WP_User $old_user_data Old user data
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_user_profile_updated($user_id, $old_user_data) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }

        $current_user = wp_get_current_user();
        
        $this->insert_log(array(
            'user_id' => $current_user->ID,
            'user_name' => $current_user->display_name,
            'user_email' => $current_user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'user_profile_updated',
            'object_type' => 'user',
            'object_id' => $user_id,
            'object_name' => $user->display_name,
            'details' => sprintf(__('Updated profile for user: %s', 'fbs-activity-tracker'), $user->display_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log user registration
     *
     * @param int $user_id User ID
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_user_registered($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }

        $this->insert_log(array(
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'user_registered',
            'object_type' => 'user',
            'object_id' => $user_id,
            'object_name' => $user->display_name,
            'details' => sprintf(__('New user registered: %s', 'fbs-activity-tracker'), $user->display_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log option updates (WordPress settings)
     *
     * @param string $option_name Option name
     * @param mixed $old_value Old value
     * @param mixed $value New value
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    public function log_option_updated($option_name, $old_value, $value) {
        // Skip certain options that change frequently
        $skip_options = array(
            'cron',
            'recovery_mode_email_last_sent',
            'auto_core_update_notified',
            '_transient_',
            '_site_transient_',
            'widget_',
            'theme_mods_',
            'recently_activated'
        );

        foreach ($skip_options as $skip) {
            if (strpos($option_name, $skip) === 0) {
                return;
            }
        }

        $user = wp_get_current_user();
        
        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'option_updated',
            'object_type' => 'option',
            'object_id' => 0,
            'object_name' => $option_name,
            'details' => sprintf(__('Updated WordPress setting: %s', 'fbs-activity-tracker'), $option_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Insert log entry
     *
     * @param array $log_data Log data
     * @return int|false Log ID on success, false on failure
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function insert_log($log_data) {
        return $this->database->insert_log($log_data);
    }

    /**
     * Get user IP address
     *
     * @return string User IP address
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Get plugin name from file path
     *
     * @param string $plugin_file Plugin file path
     * @return string Plugin name
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.0.0
     */
    private function get_plugin_name($plugin_file) {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
        
        return !empty($plugin_data['Name']) ? $plugin_data['Name'] : basename($plugin_file);
    }
}