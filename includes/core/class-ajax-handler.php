<?php
/**
 * Insurance CRM AJAX Handler
 * Centralized AJAX request handling with caching and optimization
 * 
 * @package Insurance_CRM
 * @version 2.0.0
 * @since 1.9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Ajax_Handler {
    
    private $allowed_actions = array();
    private $cache_timeout = 300; // 5 minutes default cache
    
    public function __construct() {
        $this->setup_allowed_actions();
        $this->init_hooks();
    }
    
    /**
     * Setup allowed AJAX actions
     */
    private function setup_allowed_actions() {
        $this->allowed_actions = array(
            // Customer operations
            'get_customers' => array(
                'callback' => array($this, 'get_customers'),
                'capability' => 'read_insurance_crm',
                'cache' => true
            ),
            'add_customer' => array(
                'callback' => array($this, 'add_customer'),
                'capability' => 'edit_insurance_crm',
                'cache' => false
            ),
            'update_customer' => array(
                'callback' => array($this, 'update_customer'),
                'capability' => 'edit_insurance_crm',
                'cache' => false
            ),
            'delete_customer' => array(
                'callback' => array($this, 'delete_customer'),
                'capability' => 'manage_insurance_crm',
                'cache' => false
            ),
            
            // Policy operations
            'get_policies' => array(
                'callback' => array($this, 'get_policies'),
                'capability' => 'read_insurance_crm',
                'cache' => true
            ),
            'add_policy' => array(
                'callback' => array($this, 'add_policy'),
                'capability' => 'edit_insurance_crm',
                'cache' => false
            ),
            'update_policy' => array(
                'callback' => array($this, 'update_policy'),
                'capability' => 'edit_insurance_crm',
                'cache' => false
            ),
            
            // Task operations
            'get_tasks' => array(
                'callback' => array($this, 'get_tasks'),
                'capability' => 'read_insurance_crm',
                'cache' => true
            ),
            'add_task' => array(
                'callback' => array($this, 'add_task'),
                'capability' => 'edit_insurance_crm',
                'cache' => false
            ),
            'update_task_status' => array(
                'callback' => array($this, 'update_task_status'),
                'capability' => 'edit_insurance_crm',
                'cache' => false
            ),
            
            // Dashboard data
            'get_dashboard_stats' => array(
                'callback' => array($this, 'get_dashboard_stats'),
                'capability' => 'read_insurance_crm',
                'cache' => true,
                'cache_time' => 600 // 10 minutes
            ),
            'get_recent_activities' => array(
                'callback' => array($this, 'get_recent_activities'),
                'capability' => 'read_insurance_crm',
                'cache' => true,
                'cache_time' => 120 // 2 minutes
            ),
            
            // Search and filters
            'search_customers' => array(
                'callback' => array($this, 'search_customers'),
                'capability' => 'read_insurance_crm',
                'cache' => true,
                'cache_time' => 180 // 3 minutes
            ),
            'filter_policies' => array(
                'callback' => array($this, 'filter_policies'),
                'capability' => 'read_insurance_crm',
                'cache' => true,
                'cache_time' => 180
            ),
            
            // Export operations
            'export_data' => array(
                'callback' => array($this, 'export_data'),
                'capability' => 'manage_insurance_crm',
                'cache' => false
            ),
            
            // Utility functions
            'validate_tc_no' => array(
                'callback' => array($this, 'validate_tc_no'),
                'capability' => 'read_insurance_crm',
                'cache' => true,
                'cache_time' => 3600 // 1 hour
            )
        );
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        foreach ($this->allowed_actions as $action => $config) {
            add_action('wp_ajax_insurance_crm_' . $action, array($this, 'handle_ajax_request'));
            
            // Add nopriv hook for public actions if needed
            if (isset($config['public']) && $config['public']) {
                add_action('wp_ajax_nopriv_insurance_crm_' . $action, array($this, 'handle_ajax_request'));
            }
        }
    }
    
    /**
     * Handle AJAX request with security and caching
     */
    public function handle_ajax_request() {
        // Get action name
        $action = str_replace('insurance_crm_', '', current_action());
        $action = str_replace('wp_ajax_', '', $action);
        $action = str_replace('wp_ajax_nopriv_', '', $action);
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'insurance_crm_nonce')) {
            wp_send_json_error(array(
                'message' => 'Güvenlik doğrulaması başarısız.',
                'code' => 'NONCE_FAILED'
            ));
        }
        
        // Check if action is allowed
        if (!isset($this->allowed_actions[$action])) {
            wp_send_json_error(array(
                'message' => 'Geçersiz işlem.',
                'code' => 'INVALID_ACTION'
            ));
        }
        
        $config = $this->allowed_actions[$action];
        
        // Check capabilities
        if (!current_user_can($config['capability'])) {
            wp_send_json_error(array(
                'message' => 'Bu işlem için yetkiniz bulunmuyor.',
                'code' => 'INSUFFICIENT_PERMISSIONS'
            ));
        }
        
        // Check cache if enabled
        if ($config['cache']) {
            $cache_key = $this->get_cache_key($action, $_POST);
            $cached_data = wp_cache_get($cache_key, 'insurance_crm_ajax');
            
            if ($cached_data !== false) {
                wp_send_json_success($cached_data);
            }
        }
        
        // Execute callback
        try {
            $result = call_user_func($config['callback']);
            
            // Cache result if caching is enabled
            if ($config['cache']) {
                $cache_time = $config['cache_time'] ?? $this->cache_timeout;
                wp_cache_set($cache_key, $result, 'insurance_crm_ajax', $cache_time);
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            error_log('Insurance CRM AJAX Error (' . $action . '): ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => 'İşlem sırasında bir hata oluştu.',
                'code' => 'PROCESSING_ERROR'
            ));
        }
    }
    
    /**
     * Generate cache key for request
     */
    private function get_cache_key($action, $data) {
        // Remove nonce from cache key calculation
        unset($data['nonce']);
        unset($data['action']);
        
        return 'ajax_' . $action . '_' . md5(serialize($data)) . '_' . get_current_user_id();
    }
    
    /**
     * Get customers with pagination and filtering
     */
    public function get_customers() {
        global $wpdb;
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);
        $search = sanitize_text_field($_POST['search'] ?? '');
        $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'created_at');
        $sort_order = sanitize_text_field($_POST['sort_order'] ?? 'DESC');
        
        $offset = ($page - 1) * $per_page;
        
        $table_name = $wpdb->prefix . 'insurance_crm_customers';
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $params = array();
        
        if (!empty($search)) {
            $where_conditions[] = "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params = array_merge($params, array($search_term, $search_term, $search_term, $search_term));
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        $total = $wpdb->get_var(!empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql);
        
        // Get customers
        $allowed_sort_columns = array('first_name', 'last_name', 'email', 'phone', 'created_at');
        $sort_by = in_array($sort_by, $allowed_sort_columns) ? $sort_by : 'created_at';
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$sort_by} {$sort_order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $customers = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        return array(
            'customers' => $customers,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Add new customer
     */
    public function add_customer() {
        global $wpdb;
        
        $required_fields = array('first_name', 'last_name');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                return array(
                    'error' => true,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' alanı zorunludur.'
                );
            }
        }
        
        $customer_data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'phone2' => sanitize_text_field($_POST['phone2'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'birth_date' => sanitize_text_field($_POST['birth_date'] ?? ''),
            'tc_no' => sanitize_text_field($_POST['tc_no'] ?? ''),
            'gender' => sanitize_text_field($_POST['gender'] ?? ''),
            'marital_status' => sanitize_text_field($_POST['marital_status'] ?? ''),
            'profession' => sanitize_text_field($_POST['profession'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'ilk_kayit_eden' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        // Validate email if provided
        if (!empty($customer_data['email']) && !is_email($customer_data['email'])) {
            return array(
                'error' => true,
                'message' => 'Geçersiz e-posta adresi.'
            );
        }
        
        // Check for duplicate email
        if (!empty($customer_data['email'])) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}insurance_crm_customers WHERE email = %s",
                $customer_data['email']
            ));
            
            if ($existing) {
                return array(
                    'error' => true,
                    'message' => 'Bu e-posta adresi zaten kayıtlı.'
                );
            }
        }
        
        $table_name = $wpdb->prefix . 'insurance_crm_customers';
        $result = $wpdb->insert($table_name, $customer_data);
        
        if ($result === false) {
            return array(
                'error' => true,
                'message' => 'Müşteri eklenirken hata oluştu: ' . $wpdb->last_error
            );
        }
        
        $customer_id = $wpdb->insert_id;
        
        // Clear cache
        wp_cache_flush_group('insurance_crm_ajax');
        
        // Trigger action for notifications
        do_action('insurance_crm_customer_added', $customer_id, $customer_data);
        
        return array(
            'success' => true,
            'message' => 'Müşteri başarıyla eklendi.',
            'customer_id' => $customer_id
        );
    }
    
    /**
     * Update customer
     */
    public function update_customer() {
        global $wpdb;
        
        $customer_id = intval($_POST['customer_id'] ?? 0);
        
        if (!$customer_id) {
            return array(
                'error' => true,
                'message' => 'Geçersiz müşteri ID.'
            );
        }
        
        $customer_data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'phone2' => sanitize_text_field($_POST['phone2'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'birth_date' => sanitize_text_field($_POST['birth_date'] ?? ''),
            'tc_no' => sanitize_text_field($_POST['tc_no'] ?? ''),
            'gender' => sanitize_text_field($_POST['gender'] ?? ''),
            'marital_status' => sanitize_text_field($_POST['marital_status'] ?? ''),
            'profession' => sanitize_text_field($_POST['profession'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'customer_notes_updated_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $table_name = $wpdb->prefix . 'insurance_crm_customers';
        $result = $wpdb->update(
            $table_name,
            $customer_data,
            array('id' => $customer_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return array(
                'error' => true,
                'message' => 'Müşteri güncellenirken hata oluştu: ' . $wpdb->last_error
            );
        }
        
        // Clear cache
        wp_cache_flush_group('insurance_crm_ajax');
        
        // Trigger action for notifications
        do_action('insurance_crm_customer_updated', $customer_id, $customer_data);
        
        return array(
            'success' => true,
            'message' => 'Müşteri başarıyla güncellendi.'
        );
    }
    
    /**
     * Delete customer
     */
    public function delete_customer() {
        global $wpdb;
        
        $customer_id = intval($_POST['customer_id'] ?? 0);
        
        if (!$customer_id) {
            return array(
                'error' => true,
                'message' => 'Geçersiz müşteri ID.'
            );
        }
        
        // Check if customer has policies
        $policy_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies WHERE customer_id = %d",
            $customer_id
        ));
        
        if ($policy_count > 0) {
            return array(
                'error' => true,
                'message' => 'Bu müşteriye ait poliçeler bulunduğu için silinemez.'
            );
        }
        
        $table_name = $wpdb->prefix . 'insurance_crm_customers';
        $result = $wpdb->delete($table_name, array('id' => $customer_id), array('%d'));
        
        if ($result === false) {
            return array(
                'error' => true,
                'message' => 'Müşteri silinirken hata oluştu.'
            );
        }
        
        // Clear cache
        wp_cache_flush_group('insurance_crm_ajax');
        
        // Trigger action for cleanup
        do_action('insurance_crm_customer_deleted', $customer_id);
        
        return array(
            'success' => true,
            'message' => 'Müşteri başarıyla silindi.'
        );
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total customers
        $stats['total_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_customers");
        
        // Total policies
        $stats['total_policies'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies");
        
        // Active policies
        $stats['active_policies'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies WHERE status = 'active'");
        
        // Pending tasks
        $stats['pending_tasks'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_tasks WHERE status = 'pending'");
        
        // This month's new customers
        $stats['monthly_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_customers WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
        
        // Expiring policies (next 30 days)
        $stats['expiring_policies'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies WHERE end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) AND status = 'active'");
        
        return $stats;
    }
    
    /**
     * Get recent activities
     */
    public function get_recent_activities() {
        global $wpdb;
        
        $limit = intval($_POST['limit'] ?? 10);
        
        $activities = array();
        
        // Recent customers
        $recent_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, created_at, 'customer' as type FROM {$wpdb->prefix}insurance_crm_customers ORDER BY created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        
        foreach ($recent_customers as $customer) {
            $activities[] = array(
                'type' => 'customer_added',
                'title' => 'Yeni Müşteri',
                'description' => $customer['first_name'] . ' ' . $customer['last_name'] . ' eklendi',
                'timestamp' => $customer['created_at'],
                'icon' => 'dashicons-admin-users'
            );
        }
        
        // Recent policies
        $recent_policies = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.policy_number, p.created_at, c.first_name, c.last_name FROM {$wpdb->prefix}insurance_crm_policies p LEFT JOIN {$wpdb->prefix}insurance_crm_customers c ON p.customer_id = c.id ORDER BY p.created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        
        foreach ($recent_policies as $policy) {
            $activities[] = array(
                'type' => 'policy_added',
                'title' => 'Yeni Poliçe',
                'description' => $policy['first_name'] . ' ' . $policy['last_name'] . ' için ' . $policy['policy_number'] . ' poliçesi eklendi',
                'timestamp' => $policy['created_at'],
                'icon' => 'dashicons-media-document'
            );
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, $limit);
    }
    
    /**
     * Search customers
     */
    public function search_customers() {
        global $wpdb;
        
        $search_term = sanitize_text_field($_POST['q'] ?? '');
        $limit = intval($_POST['limit'] ?? 10);
        
        if (strlen($search_term) < 2) {
            return array(
                'results' => array(),
                'message' => 'En az 2 karakter giriniz.'
            );
        }
        
        $search_pattern = '%' . $wpdb->esc_like($search_term) . '%';
        
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, email, phone FROM {$wpdb->prefix}insurance_crm_customers 
             WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s 
             ORDER BY first_name, last_name LIMIT %d",
            $search_pattern, $search_pattern, $search_pattern, $search_pattern, $limit
        ), ARRAY_A);
        
        return array(
            'results' => $customers,
            'count' => count($customers)
        );
    }
    
    /**
     * Validate Turkish ID number
     */
    public function validate_tc_no() {
        $tc_no = sanitize_text_field($_POST['tc_no'] ?? '');
        
        if (strlen($tc_no) !== 11 || !is_numeric($tc_no)) {
            return array(
                'valid' => false,
                'message' => 'TC kimlik numarası 11 haneli olmalıdır.'
            );
        }
        
        // TC kimlik numarası algoritması
        $digits = str_split($tc_no);
        $sum_odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $sum_even = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        
        $check1 = ($sum_odd * 7 - $sum_even) % 10;
        $check2 = ($sum_odd + $sum_even + $digits[9]) % 10;
        
        $is_valid = ($check1 == $digits[9]) && ($check2 == $digits[10]);
        
        return array(
            'valid' => $is_valid,
            'message' => $is_valid ? 'Geçerli TC kimlik numarası.' : 'Geçersiz TC kimlik numarası.'
        );
    }
    
    /**
     * Clear cache for specific action
     */
    public function clear_cache($action = null) {
        if ($action) {
            wp_cache_delete_group('insurance_crm_ajax_' . $action);
        } else {
            wp_cache_flush_group('insurance_crm_ajax');
        }
    }
}

// Initialize AJAX handler
new Insurance_CRM_Ajax_Handler();