<?php
/**
 * FBS Activity Tracker - Plugin Test File
 * 
 * This file can be used to test the plugin functionality
 * Remove this file in production
 * 
 * @package FBS_Activity_Tracker
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test function to verify plugin installation
 * 
 * @return array Test results
 */
function fbs_at_test_plugin() {
    $results = array(
        'plugin_loaded' => false,
        'classes_available' => array(),
        'database_table' => false,
        'admin_menu' => false,
        'assets_loaded' => false
    );

    // Test if plugin is loaded
    if (class_exists('FBS_Activity_Tracker')) {
        $results['plugin_loaded'] = true;
    }

    // Test if all classes are available
    $classes = array(
        'FBS_Activity_Tracker_Database',
        'FBS_Activity_Tracker_Logger',
        'FBS_Activity_Tracker_Ajax',
        'FBS_Activity_Tracker_Assets',
        'FBS_Activity_Tracker_Admin'
    );

    foreach ($classes as $class) {
        $results['classes_available'][$class] = class_exists($class);
    }

    // Test database table
    global $wpdb;
    $table_name = $wpdb->prefix . 'fbs_activity_logs';
    $results['database_table'] = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    // Test admin menu
    $results['admin_menu'] = has_action('admin_menu', array('FBS_Activity_Tracker_Admin', 'add_admin_menu'));

    // Test assets
    $results['assets_loaded'] = has_action('admin_enqueue_scripts', array('FBS_Activity_Tracker_Assets', 'enqueue_admin_assets'));

    return $results;
}

/**
 * Display test results in admin
 */
function fbs_at_display_test_results() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $results = fbs_at_test_plugin();
    
    echo '<div class="wrap">';
    echo '<h1>FBS Activity Tracker - Plugin Test Results</h1>';
    
    echo '<table class="widefat">';
    echo '<thead><tr><th>Test</th><th>Status</th><th>Details</th></tr></thead>';
    echo '<tbody>';
    
    // Plugin loaded
    echo '<tr>';
    echo '<td>Plugin Loaded</td>';
    echo '<td>' . ($results['plugin_loaded'] ? '✅ Pass' : '❌ Fail') . '</td>';
    echo '<td>Main plugin class available</td>';
    echo '</tr>';
    
    // Classes available
    foreach ($results['classes_available'] as $class => $available) {
        echo '<tr>';
        echo '<td>Class: ' . $class . '</td>';
        echo '<td>' . ($available ? '✅ Pass' : '❌ Fail') . '</td>';
        echo '<td>Class definition loaded</td>';
        echo '</tr>';
    }
    
    // Database table
    echo '<tr>';
    echo '<td>Database Table</td>';
    echo '<td>' . ($results['database_table'] ? '✅ Pass' : '❌ Fail') . '</td>';
    echo '<td>Custom table created</td>';
    echo '</tr>';
    
    // Admin menu
    echo '<tr>';
    echo '<td>Admin Menu</td>';
    echo '<td>' . ($results['admin_menu'] ? '✅ Pass' : '❌ Fail') . '</td>';
    echo '<td>Admin menu hook registered</td>';
    echo '</tr>';
    
    // Assets
    echo '<tr>';
    echo '<td>Assets Management</td>';
    echo '<td>' . ($results['assets_loaded'] ? '✅ Pass' : '❌ Fail') . '</td>';
    echo '<td>Asset enqueue hook registered</td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    
    // Overall status
    $all_passed = $results['plugin_loaded'] && 
                  $results['database_table'] && 
                  $results['admin_menu'] && 
                  $results['assets_loaded'] &&
                  !in_array(false, $results['classes_available']);
    
    echo '<div class="notice notice-' . ($all_passed ? 'success' : 'error') . '">';
    echo '<p><strong>Overall Status: ' . ($all_passed ? 'All tests passed!' : 'Some tests failed.') . '</strong></p>';
    echo '</div>';
    
    echo '</div>';
}

// Add test page to admin menu (only for testing)
if (is_admin()) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'fbs-activity-tracker',
            'Plugin Test',
            'Plugin Test',
            'manage_options',
            'fbs-activity-tracker-test',
            'fbs_at_display_test_results'
        );
    });
}
