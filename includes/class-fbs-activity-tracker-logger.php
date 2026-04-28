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
        add_action('set_user_role', array($this, 'log_user_role_changed'), 10, 3);
        add_action('after_password_reset', array($this, 'log_password_reset'), 10, 2);

        // Settings hooks
        add_action('updated_option', array($this, 'log_option_updated'), 10, 3);

        // Media hooks
        add_action('add_attachment', array($this, 'log_media_uploaded'));
        add_action('delete_attachment', array($this, 'log_media_deleted'));

        // Comment hooks
        add_action('wp_insert_comment', array($this, 'log_comment_created'), 10, 2);
        add_action('transition_comment_status', array($this, 'log_comment_status_changed'), 10, 3);

        // Custom events from other plugins/themes.
        add_action('fbsat_log_event', array($this, 'log_custom_event'));
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
            // translators: %s is the user's IP address
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
            // translators: %1$s is the username, %2$s is the IP address
            'details' => sprintf(__('Failed login attempt for username: %1$s from IP: %2$s', 'fbs-activity-tracker'), $username, $this->get_user_ip()),
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
                    // translators: %1$s is the post type, %2$s is the post title
                    $details = sprintf(__('Created new %1$s: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                } else {
                    $action_type = 'post_published';
                    // translators: %1$s is the post type, %2$s is the post title
                    $details = sprintf(__('Published %1$s: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                }
                break;
            case 'draft':
                $action_type = 'post_drafted';
                // translators: %1$s is the post type, %2$s is the post title
                $details = sprintf(__('Moved %1$s to draft: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                break;
            case 'trash':
                $action_type = 'post_trashed';
                // translators: %1$s is the post type, %2$s is the post title
                $details = sprintf(__('Trashed %1$s: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                break;
            case 'private':
                $action_type = 'post_private';
                // translators: %1$s is the post type, %2$s is the post title
                $details = sprintf(__('Made %1$s private: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
                break;
        }

        // Also log updates to published posts
        if ($new_status === 'publish' && $old_status === 'publish') {
            $action_type = 'post_updated';
            // translators: %1$s is the post type, %2$s is the post title
            $details = sprintf(__('Updated %1$s: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title);
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
            // translators: %1$s is the post type, %2$s is the post title
            'details' => sprintf(__('Permanently deleted %1$s: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title),
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
            // translators: %1$s is the post type, %2$s is the post title
            'details' => sprintf(__('Restored %1$s from trash: %2$s', 'fbs-activity-tracker'), $post->post_type, $post->post_title),
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
            // translators: %s is the plugin name
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
            // translators: %s is the plugin name
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
            // translators: %s is the theme name
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
            // translators: %s is the user's display name
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
            // translators: %s is the user's display name
            'details' => sprintf(__('New user registered: %s', 'fbs-activity-tracker'), $user->display_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log user role changes.
     *
     * @param int    $user_id   User ID.
     * @param string $new_role  New role.
     * @param array  $old_roles Previous roles.
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.1.0
     */
    public function log_user_role_changed($user_id, $new_role, $old_roles) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $current_user = wp_get_current_user();
        $old_roles_string = is_array($old_roles) ? implode(', ', array_map('sanitize_text_field', $old_roles)) : '';

        $this->insert_log(array(
            'user_id' => $current_user->ID,
            'user_name' => $current_user->display_name,
            'user_email' => $current_user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'user_role_changed',
            'object_type' => 'user',
            'object_id' => $user_id,
            'object_name' => $user->display_name,
            /* translators: 1: user display name, 2: old role list, 3: new role */
            'details' => sprintf(__('Changed user role for %1$s from [%2$s] to [%3$s]', 'fbs-activity-tracker'), $user->display_name, $old_roles_string, $new_role),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log password reset events.
     *
     * @param WP_User $user     User object.
     * @param string  $new_pass New password (not logged).
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.1.0
     */
    public function log_password_reset($user, $new_pass) {
        unset($new_pass); // Never log raw passwords.
        if (!$user || !($user instanceof WP_User)) {
            return;
        }

        $current_user = wp_get_current_user();

        $this->insert_log(array(
            'user_id' => $current_user->ID > 0 ? $current_user->ID : $user->ID,
            'user_name' => $current_user->ID > 0 ? $current_user->display_name : $user->display_name,
            'user_email' => $current_user->ID > 0 ? $current_user->user_email : $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'user_password_reset',
            'object_type' => 'user',
            'object_id' => $user->ID,
            'object_name' => $user->display_name,
            /* translators: %s is the user's display name */
            'details' => sprintf(__('Password reset for user: %s', 'fbs-activity-tracker'), $user->display_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log media uploads.
     *
     * @param int $attachment_id Attachment ID.
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.1.0
     */
    public function log_media_uploaded($attachment_id) {
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            return;
        }

        $user = wp_get_current_user();
        $filename = wp_basename(get_attached_file($attachment_id));

        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'media_uploaded',
            'object_type' => 'attachment',
            'object_id' => $attachment_id,
            'object_name' => $post->post_title,
            /* translators: %s is the uploaded filename */
            'details' => sprintf(__('Uploaded media file: %s', 'fbs-activity-tracker'), $filename),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log media deletion.
     *
     * @param int $attachment_id Attachment ID.
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.1.0
     */
    public function log_media_deleted($attachment_id) {
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            return;
        }

        $user = wp_get_current_user();

        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'media_deleted',
            'object_type' => 'attachment',
            'object_id' => $attachment_id,
            'object_name' => $post->post_title,
            /* translators: %s is the media title */
            'details' => sprintf(__('Deleted media file: %s', 'fbs-activity-tracker'), $post->post_title),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log comment creation.
     *
     * @param int        $comment_id       Comment ID.
     * @param WP_Comment $comment_object   Comment object.
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.1.0
     */
    public function log_comment_created($comment_id, $comment_object) {
        if (!($comment_object instanceof WP_Comment)) {
            return;
        }

        $user = wp_get_current_user();
        $post_title = get_the_title($comment_object->comment_post_ID);
        $comment_author = $comment_object->comment_author ? $comment_object->comment_author : __('Unknown', 'fbs-activity-tracker');

        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'comment_created',
            'object_type' => 'comment',
            'object_id' => $comment_id,
            'object_name' => $comment_author,
            /* translators: 1: comment author, 2: post title */
            'details' => sprintf(__('New comment by %1$s on: %2$s', 'fbs-activity-tracker'), $comment_author, $post_title),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log comment status transitions.
     *
     * @param string     $new_status New status.
     * @param string     $old_status Old status.
     * @param WP_Comment $comment    Comment object.
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.1.0
     */
    public function log_comment_status_changed($new_status, $old_status, $comment) {
        if (!($comment instanceof WP_Comment) || $new_status === $old_status) {
            return;
        }

        $user = wp_get_current_user();
        $post_title = get_the_title($comment->comment_post_ID);

        $this->insert_log(array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => 'comment_status_changed',
            'object_type' => 'comment',
            'object_id' => $comment->comment_ID,
            'object_name' => __('Comment', 'fbs-activity-tracker'),
            /* translators: 1: old status, 2: new status, 3: post title */
            'details' => sprintf(__('Comment status changed from %1$s to %2$s on: %3$s', 'fbs-activity-tracker'), $old_status, $new_status, $post_title),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Log custom events from other plugins/themes.
     *
     * @param array $event_data Custom event payload.
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @since 1.1.0
     */
    public function log_custom_event($event_data) {
        if (!is_array($event_data) || empty($event_data['action_type'])) {
            return;
        }

        $user = wp_get_current_user();
        $defaults = array(
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_ip' => $this->get_user_ip(),
            'action_type' => '',
            'object_type' => 'custom',
            'object_id' => 0,
            'object_name' => '',
            'details' => '',
            'timestamp' => current_time('mysql'),
        );

        $data = wp_parse_args($event_data, $defaults);
        $this->insert_log($data);
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
        // Skip certain options that change frequently and create audit noise.
        $skip_options = array(
            'cron',
            'recovery_mode_email_last_sent',
            'auto_core_update_notified',
            '_transient_',
            '_site_transient_',
            'widget_',
            'theme_mods_',
            'recently_activated',
            '_elementor_pro_api_requests_lock',
            'woocommerce_admin_notices',
            'frmpro_db_version',
            'frm_inbox_cache',
            'frm_sales_cache'
        );

        /**
         * Filter list of option-name prefixes or exact names to skip.
         *
         * @param array  $skip_options Default skip list.
         * @param string $option_name  Current option name.
         */
        $skip_options = apply_filters('fbsat_skip_option_updates', $skip_options, $option_name);

        foreach ($skip_options as $skip) {
            $skip = (string) $skip;
            if ($skip === '') {
                continue;
            }

            // Support both exact option names and prefix matches.
            if ($option_name === $skip || strpos($option_name, $skip) === 0) {
                return;
            }
        }

        // Skip common volatile runtime keys.
        $volatile_fragments = array('_lock', '_cache');
        foreach ($volatile_fragments as $fragment) {
            if (strpos($option_name, $fragment) !== false) {
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
            // translators: %s is the option name
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
                foreach (explode(',', sanitize_text_field(wp_unslash($_SERVER[$key]))) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
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