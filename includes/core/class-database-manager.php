<?php
/**
 * Insurance CRM Core Database Manager
 * Handles database operations, table creation, and schema updates
 * 
 * @package Insurance_CRM
 * @version 2.0.0
 * @since 1.9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Database_Manager {
    
    private $wpdb;
    private $db_version = '2.0.0';
    private static $check_done = false;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        add_action('init', array($this, 'maybe_update_database'), 5);
        add_action('plugins_loaded', array($this, 'check_db_tables'));
    }
    
    /**
     * Check and update database if needed
     */
    public function maybe_update_database() {
        if (self::$check_done) {
            return;
        }
        
        $current_version = get_option('insurance_crm_db_version', '1.0.0');
        
        if (version_compare($current_version, $this->db_version, '<')) {
            $this->update_database($current_version);
            update_option('insurance_crm_db_version', $this->db_version);
        }
        
        self::$check_done = true;
    }
    
    /**
     * Update database based on version
     */
    private function update_database($from_version) {
        // Incremental updates based on version
        if (version_compare($from_version, '1.5.0', '<')) {
            $this->update_to_1_5_0();
        }
        
        if (version_compare($from_version, '2.0.0', '<')) {
            $this->update_to_2_0_0();
        }
        
        // Force update for missing columns (legacy compatibility)
        $this->force_update_crm_db();
    }
    
    /**
     * Update to version 1.5.0
     */
    private function update_to_1_5_0() {
        $this->add_offer_columns();
        $this->add_representative_personal_fields();
    }
    
    /**
     * Update to version 2.0.0
     */
    private function update_to_2_0_0() {
        $this->create_push_subscriptions_table();
        $this->add_performance_indexes();
        $this->optimize_table_structure();
    }
    
    /**
     * Legacy function - maintained for compatibility
     * TODO: Remove in version 3.0.0
     */
    public function force_update_crm_db() {
        $table_name = $this->wpdb->prefix . 'insurance_crm_customers';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $this->add_offer_columns();
        $this->add_customer_columns();
        $this->add_policy_columns();
        $this->add_representative_personal_fields();
    }
    
    /**
     * Add offer-related columns
     */
    private function add_offer_columns() {
        $table_name = $this->wpdb->prefix . 'insurance_crm_customers';
        
        $columns_to_add = array(
            'has_offer' => 'TINYINT(1) DEFAULT 0',
            'offer_insurance_type' => 'VARCHAR(100) DEFAULT NULL',
            'offer_amount' => 'DECIMAL(10,2) DEFAULT NULL',
            'offer_expiry_date' => 'DATE DEFAULT NULL',
            'offer_notes' => 'TEXT DEFAULT NULL',
            'offer_reminder' => 'TINYINT(1) DEFAULT 0'
        );
        
        foreach ($columns_to_add as $column_name => $column_definition) {
            $this->add_column_if_not_exists($table_name, $column_name, $column_definition);
        }
    }
    
    /**
     * Add customer-related columns
     */
    private function add_customer_columns() {
        $table_name = $this->wpdb->prefix . 'insurance_crm_customers';
        
        $columns_to_add = array(
            'phone2' => 'VARCHAR(20) DEFAULT NULL',
            'ilk_kayit_eden' => 'BIGINT(20) DEFAULT NULL',
            'customer_notes_updated_at' => 'DATETIME DEFAULT NULL'
        );
        
        foreach ($columns_to_add as $column_name => $column_definition) {
            $this->add_column_if_not_exists($table_name, $column_name, $column_definition);
        }
    }
    
    /**
     * Add policy-related columns
     */
    private function add_policy_columns() {
        $table_name = $this->wpdb->prefix . 'insurance_crm_policies';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $this->add_column_if_not_exists($table_name, 'gross_premium', 'DECIMAL(10,2) DEFAULT NULL AFTER `premium_amount`');
    }
    
    /**
     * Add representative personal information fields
     */
    private function add_representative_personal_fields() {
        $table_name = $this->wpdb->prefix . 'insurance_crm_representatives';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $columns_to_add = array(
            'birth_date' => 'DATE DEFAULT NULL',
            'wedding_anniversary' => 'DATE DEFAULT NULL',
            'children_birthdays' => 'TEXT DEFAULT NULL'
        );
        
        foreach ($columns_to_add as $column_name => $column_definition) {
            $this->add_column_if_not_exists($table_name, $column_name, $column_definition);
        }
    }
    
    /**
     * Create push subscriptions table
     */
    private function create_push_subscriptions_table() {
        $table_name = $this->wpdb->prefix . 'insurance_crm_push_subscriptions';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            subscription_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Add performance indexes
     */
    private function add_performance_indexes() {
        $tables_indexes = array(
            'insurance_crm_customers' => array(
                'idx_email' => 'email',
                'idx_phone' => 'phone',
                'idx_created_at' => 'created_at',
                'idx_ilk_kayit_eden' => 'ilk_kayit_eden',
                'idx_has_offer' => 'has_offer'
            ),
            'insurance_crm_policies' => array(
                'idx_customer_id' => 'customer_id',
                'idx_policy_number' => 'policy_number',
                'idx_start_date' => 'start_date',
                'idx_end_date' => 'end_date',
                'idx_status' => 'status'
            ),
            'insurance_crm_tasks' => array(
                'idx_assigned_to' => 'assigned_to',
                'idx_related_id_type' => 'related_id, related_type',
                'idx_due_date' => 'due_date',
                'idx_status' => 'status',
                'idx_priority' => 'priority'
            ),
            'insurance_crm_notifications' => array(
                'idx_user_id' => 'user_id',
                'idx_is_read' => 'is_read',
                'idx_created_at' => 'created_at',
                'idx_type_category' => 'type, category'
            )
        );
        
        foreach ($tables_indexes as $table_suffix => $indexes) {
            $table_name = $this->wpdb->prefix . $table_suffix;
            
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                continue;
            }
            
            foreach ($indexes as $index_name => $columns) {
                $this->add_index_if_not_exists($table_name, $index_name, $columns);
            }
        }
    }
    
    /**
     * Optimize table structure
     */
    private function optimize_table_structure() {
        $tables = array(
            $this->wpdb->prefix . 'insurance_crm_customers',
            $this->wpdb->prefix . 'insurance_crm_policies',
            $this->wpdb->prefix . 'insurance_crm_tasks',
            $this->wpdb->prefix . 'insurance_crm_notifications',
            $this->wpdb->prefix . 'insurance_crm_representatives'
        );
        
        foreach ($tables as $table) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $this->wpdb->query("OPTIMIZE TABLE `$table`");
            }
        }
    }
    
    /**
     * Add column if it doesn't exist
     */
    private function add_column_if_not_exists($table, $column, $definition) {
        $column_exists = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        
        if (empty($column_exists)) {
            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
            $result = $this->wpdb->query($sql);
            
            if ($result === false) {
                error_log("Insurance CRM: Failed to add column {$column} to {$table}: " . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Add index if it doesn't exist
     */
    private function add_index_if_not_exists($table, $index_name, $columns) {
        $indexes = $this->wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index_name}'");
        
        if (empty($indexes)) {
            $sql = "ALTER TABLE `{$table}` ADD INDEX `{$index_name}` ({$columns})";
            $result = $this->wpdb->query($sql);
            
            if ($result === false) {
                error_log("Insurance CRM: Failed to add index {$index_name} to {$table}: " . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Check database tables health
     */
    public function check_db_tables() {
        $required_tables = array(
            'insurance_crm_customers',
            'insurance_crm_policies', 
            'insurance_crm_tasks',
            'insurance_crm_notifications',
            'insurance_crm_representatives'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table_suffix) {
            $table_name = $this->wpdb->prefix . $table_suffix;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $missing_tables[] = $table_suffix;
            }
        }
        
        if (!empty($missing_tables)) {
            add_action('admin_notices', function() use ($missing_tables) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Insurance CRM:</strong> Eksik veritabanı tabloları tespit edildi: ' . implode(', ', $missing_tables);
                echo ' <a href="' . wp_nonce_url(admin_url('admin.php?page=insurance-crm&insurance_crm_create_tables=1'), 'insurance_crm_create_tables') . '" class="button">Tabloları Oluştur</a>';
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Create all required tables
     */
    public function create_all_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Create customers table
        $this->create_customers_table($charset_collate);
        
        // Create policies table
        $this->create_policies_table($charset_collate);
        
        // Create tasks table
        $this->create_tasks_table($charset_collate);
        
        // Create notifications table
        $this->create_notifications_table($charset_collate);
        
        // Create representatives table
        $this->create_representatives_table($charset_collate);
        
        // Create push subscriptions table
        $this->create_push_subscriptions_table();
        
        // Add performance indexes
        $this->add_performance_indexes();
        
        update_option('insurance_crm_db_version', $this->db_version);
    }
    
    /**
     * Create customers table
     */
    private function create_customers_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'insurance_crm_customers';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            first_name tinytext NOT NULL,
            last_name tinytext NOT NULL,
            email varchar(100) DEFAULT '',
            phone varchar(20) DEFAULT '',
            phone2 varchar(20) DEFAULT NULL,
            address text,
            birth_date date DEFAULT NULL,
            tc_no varchar(11) DEFAULT '',
            gender enum('M','F') DEFAULT NULL,
            marital_status enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
            profession varchar(100) DEFAULT '',
            notes text,
            customer_notes_updated_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            has_offer tinyint(1) DEFAULT 0,
            offer_insurance_type varchar(100) DEFAULT NULL,
            offer_amount decimal(10,2) DEFAULT NULL,
            offer_expiry_date date DEFAULT NULL,
            offer_notes text DEFAULT NULL,
            offer_reminder tinyint(1) DEFAULT 0,
            ilk_kayit_eden bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_phone (phone),
            KEY idx_created_at (created_at),
            KEY idx_ilk_kayit_eden (ilk_kayit_eden),
            KEY idx_has_offer (has_offer)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create policies table
     */
    private function create_policies_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'insurance_crm_policies';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9) NOT NULL,
            policy_number varchar(50) NOT NULL,
            insurance_type varchar(50) NOT NULL,
            company varchar(100) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            premium_amount decimal(10,2) NOT NULL,
            gross_premium decimal(10,2) DEFAULT NULL,
            commission_rate decimal(5,2) DEFAULT NULL,
            status enum('active','expired','cancelled') DEFAULT 'active',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_id (customer_id),
            KEY idx_policy_number (policy_number),
            KEY idx_start_date (start_date),
            KEY idx_end_date (end_date),
            KEY idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create tasks table
     */
    private function create_tasks_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'insurance_crm_tasks';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            description text,
            assigned_to bigint(20) NOT NULL,
            related_id mediumint(9) DEFAULT NULL,
            related_type enum('customer','policy') DEFAULT NULL,
            due_date datetime DEFAULT NULL,
            status enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
            priority enum('low','medium','high','urgent') DEFAULT 'medium',
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_assigned_to (assigned_to),
            KEY idx_related_id_type (related_id, related_type),
            KEY idx_due_date (due_date),
            KEY idx_status (status),
            KEY idx_priority (priority)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create notifications table
     */
    private function create_notifications_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'insurance_crm_notifications';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            category varchar(50) DEFAULT 'general',
            title varchar(200) NOT NULL,
            message text NOT NULL,
            related_id mediumint(9) DEFAULT NULL,
            related_type varchar(50) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            read_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_is_read (is_read),
            KEY idx_created_at (created_at),
            KEY idx_type_category (type, category)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create representatives table
     */
    private function create_representatives_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'insurance_crm_representatives';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) DEFAULT '',
            hire_date date DEFAULT NULL,
            commission_rate decimal(5,2) DEFAULT NULL,
            territory varchar(100) DEFAULT '',
            status enum('active','inactive','terminated') DEFAULT 'active',
            birth_date date DEFAULT NULL,
            wedding_anniversary date DEFAULT NULL,
            children_birthdays text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY idx_email (email),
            KEY idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Get database statistics
     */
    public function get_db_stats() {
        $stats = array();
        
        $tables = array(
            'customers' => 'insurance_crm_customers',
            'policies' => 'insurance_crm_policies',
            'tasks' => 'insurance_crm_tasks',
            'notifications' => 'insurance_crm_notifications',
            'representatives' => 'insurance_crm_representatives'
        );
        
        foreach ($tables as $key => $table_suffix) {
            $table_name = $this->wpdb->prefix . $table_suffix;
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
            $stats[$key] = (int) $count;
        }
        
        return $stats;
    }
}

// Initialize the database manager
new Insurance_CRM_Database_Manager();