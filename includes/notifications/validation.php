<?php
/**
 * Daily Notification System Validation
 * 
 * Simple validation script to check if the daily notification system
 * is properly configured and all components are in place.
 * 
 * Usage: Run this from WordPress admin or via WP-CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

/**
 * Validate daily notification system
 */
function insurance_crm_validate_daily_notifications() {
    $validation_results = array();
    
    // Check if files exist
    $required_files = array(
        'includes/notifications/class-enhanced-email-notifications.php',
        'includes/notifications/class-notification-scheduler.php',
        'includes/notifications/email-templates/email-base-template.php',
        'includes/notifications/email-templates/representative-daily-summary.php',
        'includes/notifications/email-templates/manager-daily-report.php'
    );
    
    foreach ($required_files as $file) {
        $file_path = plugin_dir_path(__FILE__) . $file;
        $validation_results['files'][$file] = file_exists($file_path);
    }
    
    // Check if classes can be loaded
    try {
        if (file_exists(plugin_dir_path(__FILE__) . 'includes/notifications/class-notification-scheduler.php')) {
            require_once plugin_dir_path(__FILE__) . 'includes/notifications/class-notification-scheduler.php';
            $validation_results['classes']['scheduler'] = class_exists('Insurance_CRM_Notification_Scheduler');
        }
        
        if (file_exists(plugin_dir_path(__FILE__) . 'includes/notifications/class-enhanced-email-notifications.php')) {
            require_once plugin_dir_path(__FILE__) . 'includes/notifications/class-enhanced-email-notifications.php';
            $validation_results['classes']['notifications'] = class_exists('Insurance_CRM_Enhanced_Email_Notifications');
        }
    } catch (Exception $e) {
        $validation_results['classes']['error'] = $e->getMessage();
    }
    
    // Check settings
    $settings = get_option('insurance_crm_settings', array());
    $validation_results['settings']['daily_email_notifications'] = isset($settings['daily_email_notifications']);
    $validation_results['settings']['value'] = isset($settings['daily_email_notifications']) ? $settings['daily_email_notifications'] : false;
    
    // Check cron schedule
    $validation_results['cron']['scheduled'] = wp_next_scheduled('insurance_crm_daily_email_notifications');
    $validation_results['cron']['next_run'] = wp_next_scheduled('insurance_crm_daily_email_notifications') ? 
        date('Y-m-d H:i:s', wp_next_scheduled('insurance_crm_daily_email_notifications')) : 'Not scheduled';
    
    // Check database tables
    global $wpdb;
    $required_tables = array(
        'insurance_crm_representatives',
        'insurance_crm_customers', 
        'insurance_crm_policies',
        'insurance_crm_tasks'
    );
    
    foreach ($required_tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $validation_results['database'][$table] = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    }
    
    // Check for representative_id columns
    $policies_column = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}insurance_crm_policies LIKE 'representative_id'");
    $tasks_column = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}insurance_crm_tasks LIKE 'representative_id'");
    
    $validation_results['database']['policies_representative_id'] = !empty($policies_column);
    $validation_results['database']['tasks_representative_id'] = !empty($tasks_column);
    
    // Check if there are active representatives
    $active_reps = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_representatives WHERE status = 'active'");
    $validation_results['data']['active_representatives'] = intval($active_reps);
    
    // Check WordPress email functionality
    $validation_results['email']['wp_mail_function'] = function_exists('wp_mail');
    
    // Summary
    $validation_results['summary'] = array(
        'all_files_exist' => !in_array(false, $validation_results['files']),
        'classes_loaded' => isset($validation_results['classes']['scheduler']) && 
                           isset($validation_results['classes']['notifications']) &&
                           $validation_results['classes']['scheduler'] && 
                           $validation_results['classes']['notifications'],
        'settings_configured' => $validation_results['settings']['daily_email_notifications'],
        'cron_scheduled' => $validation_results['cron']['scheduled'] !== false,
        'database_ready' => !in_array(false, $validation_results['database']),
        'has_representatives' => $validation_results['data']['active_representatives'] > 0
    );
    
    $validation_results['summary']['overall_status'] = 
        $validation_results['summary']['all_files_exist'] &&
        $validation_results['summary']['classes_loaded'] &&
        $validation_results['summary']['database_ready'];
    
    return $validation_results;
}

/**
 * Display validation results (for admin use)
 */
function insurance_crm_display_validation_results() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $results = insurance_crm_validate_daily_notifications();
    
    echo '<div class="wrap">';
    echo '<h1>Daily Notification System Validation</h1>';
    
    // Overall status
    echo '<div class="notice ' . ($results['summary']['overall_status'] ? 'notice-success' : 'notice-error') . '">';
    echo '<p><strong>Overall Status: ' . ($results['summary']['overall_status'] ? 'READY' : 'NEEDS ATTENTION') . '</strong></p>';
    echo '</div>';
    
    // Detailed results
    echo '<h2>Detailed Results</h2>';
    
    // Files
    echo '<h3>Files</h3>';
    echo '<ul>';
    foreach ($results['files'] as $file => $exists) {
        echo '<li>' . $file . ': ' . ($exists ? '✅ EXISTS' : '❌ MISSING') . '</li>';
    }
    echo '</ul>';
    
    // Classes
    echo '<h3>Classes</h3>';
    echo '<ul>';
    if (isset($results['classes']['error'])) {
        echo '<li>❌ Error loading classes: ' . $results['classes']['error'] . '</li>';
    } else {
        foreach ($results['classes'] as $class => $loaded) {
            echo '<li>' . $class . ': ' . ($loaded ? '✅ LOADED' : '❌ NOT LOADED') . '</li>';
        }
    }
    echo '</ul>';
    
    // Settings
    echo '<h3>Settings</h3>';
    echo '<ul>';
    echo '<li>Daily email notifications setting: ' . ($results['settings']['daily_email_notifications'] ? '✅ CONFIGURED' : '❌ NOT CONFIGURED') . '</li>';
    echo '<li>Current value: ' . ($results['settings']['value'] ? 'ENABLED' : 'DISABLED') . '</li>';
    echo '</ul>';
    
    // Cron
    echo '<h3>Cron Schedule</h3>';
    echo '<ul>';
    echo '<li>Scheduled: ' . ($results['cron']['scheduled'] ? '✅ YES' : '❌ NO') . '</li>';
    echo '<li>Next run: ' . $results['cron']['next_run'] . '</li>';
    echo '</ul>';
    
    // Database
    echo '<h3>Database</h3>';
    echo '<ul>';
    foreach ($results['database'] as $item => $status) {
        echo '<li>' . $item . ': ' . ($status ? '✅ OK' : '❌ MISSING') . '</li>';
    }
    echo '</ul>';
    
    // Data
    echo '<h3>Data</h3>';
    echo '<ul>';
    echo '<li>Active representatives: ' . $results['data']['active_representatives'] . '</li>';
    echo '</ul>';
    
    // Recommendations
    echo '<h2>Recommendations</h2>';
    echo '<ul>';
    
    if (!$results['summary']['all_files_exist']) {
        echo '<li>❌ Some required files are missing. Please check the file paths.</li>';
    }
    
    if (!$results['summary']['classes_loaded']) {
        echo '<li>❌ Classes could not be loaded. Check for PHP syntax errors.</li>';
    }
    
    if (!$results['summary']['settings_configured']) {
        echo '<li>❌ Daily email notifications are not configured. Enable them in Settings > Notifications.</li>';
    }
    
    if (!$results['summary']['cron_scheduled']) {
        echo '<li>❌ Cron job is not scheduled. The system should auto-schedule on next page load.</li>';
    }
    
    if (!$results['summary']['database_ready']) {
        echo '<li>❌ Database structure is incomplete. Some required tables or columns are missing.</li>';
    }
    
    if ($results['data']['active_representatives'] == 0) {
        echo '<li>⚠️ No active representatives found. No emails will be sent.</li>';
    }
    
    if ($results['summary']['overall_status']) {
        echo '<li>✅ System is ready! Daily notifications should work properly.</li>';
    }
    
    echo '</ul>';
    echo '</div>';
}

// Hook to add validation page in admin (optional)
if (is_admin() && current_user_can('manage_options')) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'insurance-crm-dashboard',
            'Notification Validation',
            'Notification Validation', 
            'manage_options',
            'insurance-crm-notification-validation',
            'insurance_crm_display_validation_results'
        );
    });
}