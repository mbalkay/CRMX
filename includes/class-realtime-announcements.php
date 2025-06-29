<?php
/**
 * Enhanced Real-time Announcement System
 * 
 * @package Insurance_CRM
 * @version 2.0.0
 * @since 1.9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Realtime_Announcements {
    
    private $table_name;
    private $wp_object_cache_key = 'insurance_crm_announcements_';
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'insurance_crm_notifications';
        
        // Initialize hooks
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_insurance_crm_get_announcements', array($this, 'ajax_get_announcements'));
        add_action('wp_ajax_insurance_crm_mark_read', array($this, 'ajax_mark_read'));
        add_action('wp_ajax_insurance_crm_poll_announcements', array($this, 'ajax_poll_announcements'));
        
        // Real-time push notifications
        add_action('wp_ajax_insurance_crm_register_push', array($this, 'ajax_register_push'));
        add_action('insurance_crm_announcement_created', array($this, 'send_push_notification'), 10, 1);
        
        // SSE support
        add_action('wp_ajax_insurance_crm_sse_stream', array($this, 'sse_stream'));
        add_action('wp_ajax_nopriv_insurance_crm_sse_stream', array($this, 'sse_stream'));
    }
    
    public function init() {
        // Enqueue scripts for real-time features
        add_action('admin_enqueue_scripts', array($this, 'enqueue_realtime_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_realtime_scripts'));
    }
    
    public function enqueue_realtime_scripts() {
        wp_enqueue_script(
            'insurance-crm-realtime-announcements',
            plugin_dir_url(__FILE__) . '../assets/js/realtime-announcements.js',
            array('jquery'),
            '2.0.0',
            true
        );
        
        // Localize script with configuration
        wp_localize_script('insurance-crm-realtime-announcements', 'insuranceCrmRealtime', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('insurance_crm_realtime_nonce'),
            'userId' => get_current_user_id(),
            'pollInterval' => apply_filters('insurance_crm_poll_interval', 30000), // 30 seconds
            'enableSSE' => apply_filters('insurance_crm_enable_sse', true),
            'enablePush' => apply_filters('insurance_crm_enable_push', true),
            'vapidPublicKey' => get_option('insurance_crm_vapid_public_key', ''),
            'sounds' => array(
                'notification' => plugin_dir_url(__FILE__) . '../assets/sounds/notification.mp3',
                'urgent' => plugin_dir_url(__FILE__) . '../assets/sounds/urgent.mp3'
            )
        ));
    }
    
    /**
     * AJAX handler for getting announcements
     */
    public function ajax_get_announcements() {
        check_ajax_referer('insurance_crm_realtime_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $last_check = sanitize_text_field($_POST['last_check'] ?? '');
        $limit = intval($_POST['limit'] ?? 20);
        $unread_only = isset($_POST['unread_only']) ? (bool) $_POST['unread_only'] : false;
        
        $announcements = $this->get_announcements($user_id, $last_check, $limit, $unread_only);
        
        wp_send_json_success(array(
            'announcements' => $announcements,
            'count' => count($announcements),
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * AJAX handler for marking announcements as read
     */
    public function ajax_mark_read() {
        check_ajax_referer('insurance_crm_realtime_nonce', 'nonce');
        
        $announcement_ids = array_map('intval', $_POST['announcement_ids'] ?? array());
        $user_id = get_current_user_id();
        
        if (empty($announcement_ids)) {
            wp_send_json_error('No announcement IDs provided');
        }
        
        $result = $this->mark_announcements_read($announcement_ids, $user_id);
        
        if ($result) {
            wp_send_json_success(array(
                'marked_count' => count($announcement_ids),
                'message' => 'Announcements marked as read'
            ));
        } else {
            wp_send_json_error('Failed to mark announcements as read');
        }
    }
    
    /**
     * AJAX handler for polling new announcements
     */
    public function ajax_poll_announcements() {
        check_ajax_referer('insurance_crm_realtime_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $last_timestamp = sanitize_text_field($_POST['last_timestamp'] ?? '');
        
        $new_announcements = $this->get_new_announcements($user_id, $last_timestamp);
        $unread_count = $this->get_unread_count($user_id);
        
        wp_send_json_success(array(
            'new_announcements' => $new_announcements,
            'new_count' => count($new_announcements),
            'total_unread' => $unread_count,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Server-Sent Events stream for real-time updates
     */
    public function sse_stream() {
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Last-Event-ID');
        
        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $user_id = get_current_user_id();
        $last_event_id = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? intval($_SERVER['HTTP_LAST_EVENT_ID']) : 0;
        
        // Send initial data
        $this->send_sse_event('connected', array(
            'message' => 'Connected to announcement stream',
            'timestamp' => current_time('mysql'),
            'user_id' => $user_id
        ));
        
        // Keep the connection alive and send updates
        $max_execution_time = 300; // 5 minutes
        $start_time = time();
        
        while (time() - $start_time < $max_execution_time) {
            // Check for new announcements
            $new_announcements = $this->get_announcements_since_id($user_id, $last_event_id);
            
            if (!empty($new_announcements)) {
                foreach ($new_announcements as $announcement) {
                    $this->send_sse_event('announcement', $announcement, $announcement['id']);
                    $last_event_id = max($last_event_id, $announcement['id']);
                }
            }
            
            // Send heartbeat
            $this->send_sse_event('heartbeat', array(
                'timestamp' => current_time('mysql'),
                'unread_count' => $this->get_unread_count($user_id)
            ));
            
            // Sleep for 10 seconds before checking again
            sleep(10);
            
            // Check if connection is still alive
            if (connection_aborted()) {
                break;
            }
        }
        
        exit;
    }
    
    /**
     * Send SSE event
     */
    private function send_sse_event($type, $data, $id = null) {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        echo "event: {$type}\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Register device for push notifications
     */
    public function ajax_register_push() {
        check_ajax_referer('insurance_crm_realtime_nonce', 'nonce');
        
        $subscription = json_decode(stripslashes($_POST['subscription'] ?? ''), true);
        $user_id = get_current_user_id();
        
        if (empty($subscription)) {
            wp_send_json_error('Invalid subscription data');
        }
        
        // Store subscription in database
        $result = $this->store_push_subscription($user_id, $subscription);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Push notifications registered successfully'
            ));
        } else {
            wp_send_json_error('Failed to register push notifications');
        }
    }
    
    /**
     * Send push notification when new announcement is created
     */
    public function send_push_notification($announcement_id) {
        global $wpdb;
        
        $announcement = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $announcement_id
        ), ARRAY_A);
        
        if (!$announcement) {
            return;
        }
        
        // Get all push subscriptions
        $subscriptions = $this->get_push_subscriptions();
        
        if (empty($subscriptions)) {
            return;
        }
        
        $payload = array(
            'title' => $announcement['title'],
            'body' => wp_trim_words($announcement['message'], 20),
            'icon' => plugin_dir_url(__FILE__) . '../assets/images/icon-192x192.png',
            'badge' => plugin_dir_url(__FILE__) . '../assets/images/badge-72x72.png',
            'data' => array(
                'announcement_id' => $announcement_id,
                'url' => admin_url('admin.php?page=insurance-crm-announcements&id=' . $announcement_id)
            ),
            'actions' => array(
                array(
                    'action' => 'view',
                    'title' => 'Görüntüle'
                ),
                array(
                    'action' => 'dismiss',
                    'title' => 'Kapat'
                )
            )
        );
        
        // Send push notifications (this would require a proper push service implementation)
        $this->send_web_push_notifications($subscriptions, $payload);
    }
    
    /**
     * Get announcements with caching
     */
    private function get_announcements($user_id, $last_check = '', $limit = 20, $unread_only = false) {
        global $wpdb;
        
        $cache_key = $this->wp_object_cache_key . $user_id . '_' . md5($last_check . $limit . $unread_only);
        $announcements = wp_cache_get($cache_key);
        
        if ($announcements === false) {
            $where_clause = "WHERE (user_id = 0 OR user_id = %d)";
            $params = array($user_id);
            
            if ($last_check) {
                $where_clause .= " AND created_at > %s";
                $params[] = $last_check;
            }
            
            if ($unread_only) {
                $where_clause .= " AND is_read = 0";
            }
            
            $sql = "SELECT * FROM {$this->table_name} 
                    {$where_clause}
                    ORDER BY created_at DESC
                    LIMIT %d";
            
            $params[] = $limit;
            
            $announcements = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
            
            // Cache for 1 minute
            wp_cache_set($cache_key, $announcements, '', 60);
        }
        
        return $announcements;
    }
    
    /**
     * Get new announcements since timestamp
     */
    private function get_new_announcements($user_id, $last_timestamp) {
        global $wpdb;
        
        if (empty($last_timestamp)) {
            return array();
        }
        
        $sql = "SELECT * FROM {$this->table_name} 
                WHERE (user_id = 0 OR user_id = %d)
                AND created_at > %s
                ORDER BY created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id, $last_timestamp), ARRAY_A);
    }
    
    /**
     * Get announcements since specific ID
     */
    private function get_announcements_since_id($user_id, $last_id) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table_name} 
                WHERE (user_id = 0 OR user_id = %d)
                AND id > %d
                ORDER BY created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id, $last_id), ARRAY_A);
    }
    
    /**
     * Get unread count for user
     */
    private function get_unread_count($user_id) {
        global $wpdb;
        
        $cache_key = $this->wp_object_cache_key . 'unread_' . $user_id;
        $count = wp_cache_get($cache_key);
        
        if ($count === false) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE (user_id = 0 OR user_id = %d) AND is_read = 0",
                $user_id
            ));
            
            wp_cache_set($cache_key, $count, '', 60);
        }
        
        return intval($count);
    }
    
    /**
     * Mark announcements as read
     */
    private function mark_announcements_read($announcement_ids, $user_id) {
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($announcement_ids), '%d'));
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
            SET is_read = 1, read_at = %s 
            WHERE id IN ({$placeholders}) AND (user_id = 0 OR user_id = %d)",
            array_merge(array(current_time('mysql')), $announcement_ids, array($user_id))
        ));
        
        // Clear cache
        wp_cache_delete($this->wp_object_cache_key . 'unread_' . $user_id);
        
        return $result !== false;
    }
    
    /**
     * Store push subscription
     */
    private function store_push_subscription($user_id, $subscription) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'insurance_crm_push_subscriptions';
        
        // Create table if it doesn't exist
        $this->create_push_subscriptions_table();
        
        return $wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'subscription_data' => json_encode($subscription),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get push subscriptions
     */
    private function get_push_subscriptions() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'insurance_crm_push_subscriptions';
        
        return $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            ARRAY_A
        );
    }
    
    /**
     * Create push subscriptions table
     */
    private function create_push_subscriptions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'insurance_crm_push_subscriptions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            subscription_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Send web push notifications (placeholder for actual implementation)
     */
    private function send_web_push_notifications($subscriptions, $payload) {
        // This would require implementing the Web Push Protocol
        // For now, we'll just log the notification
        error_log('Insurance CRM: Would send push notification to ' . count($subscriptions) . ' devices');
        error_log('Payload: ' . json_encode($payload));
        
        // In a real implementation, you would use a library like:
        // - Minishlink\WebPush\WebPush for PHP
        // - Or integrate with a service like Firebase Cloud Messaging
    }
}

// Initialize the class
new Insurance_CRM_Realtime_Announcements();