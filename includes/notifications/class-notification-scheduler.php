<?php
/**
 * Notification Scheduler
 * 
 * @package    Insurance_CRM
 * @subpackage Insurance_CRM/includes/notifications
 * @author     Anadolu Birlik
 * @since      1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Notification_Scheduler {

    /**
     * Initialize the scheduler
     */
    public function __construct() {
        add_action('init', array($this, 'schedule_daily_notifications'));
        add_action('insurance_crm_daily_email_notifications', array($this, 'send_all_daily_notifications'));
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
    }

    /**
     * Add custom cron schedules
     */
    public function add_custom_cron_schedules($schedules) {
        // Add 8 AM daily schedule
        $schedules['daily_8am'] = array(
            'interval' => DAY_IN_SECONDS,
            'display'  => __('Daily at 8:00 AM', 'insurance-crm')
        );
        
        return $schedules;
    }

    /**
     * Schedule daily notifications if not already scheduled
     */
    public function schedule_daily_notifications() {
        if (!wp_next_scheduled('insurance_crm_daily_email_notifications')) {
            // Schedule for 8:00 AM daily
            $eight_am_today = strtotime('today 08:00:00');
            
            // If it's already past 8 AM today, schedule for tomorrow
            if (current_time('timestamp') > $eight_am_today) {
                $eight_am_today = strtotime('tomorrow 08:00:00');
            }
            
            wp_schedule_event($eight_am_today, 'daily_8am', 'insurance_crm_daily_email_notifications');
            
            // Log the scheduling
            error_log('Insurance CRM: Daily email notifications scheduled for ' . date('Y-m-d H:i:s', $eight_am_today));
        }
    }

    /**
     * Cancel old notification schedules
     */
    public function cancel_old_notifications() {
        // Clear any existing daily notifications that might conflict
        wp_clear_scheduled_hook('insurance_crm_daily_notifications');
        wp_clear_scheduled_hook('insurance_crm_daily_email_notifications');
        
        // Log the cancellation
        error_log('Insurance CRM: Old notification schedules cancelled');
    }

    /**
     * Send all daily notifications
     */
    public function send_all_daily_notifications() {
        global $wpdb;
        
        // Check if daily notifications are enabled in settings
        $settings = get_option('insurance_crm_settings', array());
        if (!isset($settings['daily_email_notifications']) || !$settings['daily_email_notifications']) {
            error_log('Insurance CRM: Daily email notifications are disabled in settings');
            return;
        }
        
        // Get enhanced notifications class
        require_once dirname(__FILE__) . '/class-enhanced-email-notifications.php';
        $notifications = new Insurance_CRM_Enhanced_Email_Notifications();
        
        $sent_count = 0;
        $error_count = 0;
        
        // Send notifications to representatives
        $representatives = $wpdb->get_results(
            "SELECT id, user_id FROM {$wpdb->prefix}insurance_crm_representatives 
             WHERE status = 'active'"
        );
        
        foreach ($representatives as $rep) {
            try {
                $result = $notifications->send_representative_daily_summary($rep->id);
                if ($result) {
                    $sent_count++;
                    error_log("Insurance CRM: Daily summary sent to representative ID: {$rep->id}");
                } else {
                    error_log("Insurance CRM: Failed to send daily summary to representative ID: {$rep->id} (notifications disabled or no email)");
                }
            } catch (Exception $e) {
                $error_count++;
                error_log("Insurance CRM: Error sending daily summary to representative ID: {$rep->id} - " . $e->getMessage());
            }
        }
        
        // Send notifications to managers (role 1, 2, 3)
        $managers = $wpdb->get_results(
            "SELECT user_id FROM {$wpdb->prefix}insurance_crm_representatives 
             WHERE status = 'active' AND role IN (1, 2, 3)"
        );
        
        foreach ($managers as $manager) {
            try {
                $result = $notifications->send_manager_daily_report($manager->user_id);
                if ($result) {
                    $sent_count++;
                    error_log("Insurance CRM: Daily report sent to manager user ID: {$manager->user_id}");
                } else {
                    error_log("Insurance CRM: Failed to send daily report to manager user ID: {$manager->user_id} (notifications disabled or no email)");
                }
            } catch (Exception $e) {
                $error_count++;
                error_log("Insurance CRM: Error sending daily report to manager user ID: {$manager->user_id} - " . $e->getMessage());
            }
        }
        
        // Update last run time
        update_option('insurance_crm_last_daily_email_run', current_time('mysql'));
        
        // Log summary
        error_log("Insurance CRM: Daily notifications completed. Sent: {$sent_count}, Errors: {$error_count}");
        
        // Optional: Store statistics
        $this->update_notification_stats($sent_count, $error_count);
    }

    /**
     * Update notification statistics
     */
    private function update_notification_stats($sent_count, $error_count) {
        $stats = get_option('insurance_crm_notification_stats', array());
        
        $today = date('Y-m-d');
        $stats[$today] = array(
            'sent' => $sent_count,
            'errors' => $error_count,
            'timestamp' => current_time('mysql')
        );
        
        // Keep only last 30 days of stats
        $stats = array_slice($stats, -30, 30, true);
        
        update_option('insurance_crm_notification_stats', $stats);
    }

    /**
     * Test daily notifications (for manual testing)
     */
    public function test_daily_notifications() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'insurance-crm'));
        }
        
        error_log('Insurance CRM: Manual test of daily notifications initiated');
        $this->send_all_daily_notifications();
        
        return array(
            'success' => true,
            'message' => __('Test daily notifications sent. Check logs for details.', 'insurance-crm'),
            'last_run' => get_option('insurance_crm_last_daily_email_run')
        );
    }

    /**
     * Get notification statistics
     */
    public function get_notification_stats() {
        $stats = get_option('insurance_crm_notification_stats', array());
        $last_run = get_option('insurance_crm_last_daily_email_run');
        
        return array(
            'stats' => $stats,
            'last_run' => $last_run,
            'next_scheduled' => wp_next_scheduled('insurance_crm_daily_email_notifications')
        );
    }

    /**
     * Reschedule notifications (for settings changes)
     */
    public function reschedule_notifications() {
        $this->cancel_old_notifications();
        $this->schedule_daily_notifications();
        
        return true;
    }

    /**
     * Manual trigger for specific user (for testing)
     */
    public function send_test_notification($user_id, $type = 'representative') {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        require_once dirname(__FILE__) . '/class-enhanced-email-notifications.php';
        $notifications = new Insurance_CRM_Enhanced_Email_Notifications();
        
        if ($type === 'manager') {
            return $notifications->send_manager_daily_report($user_id);
        } else {
            // Get representative ID from user ID
            global $wpdb;
            $rep_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}insurance_crm_representatives WHERE user_id = %d AND status = 'active'",
                $user_id
            ));
            
            if ($rep_id) {
                return $notifications->send_representative_daily_summary($rep_id);
            }
        }
        
        return false;
    }
}