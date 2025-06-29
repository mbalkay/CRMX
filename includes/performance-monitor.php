<?php
/**
 * Performance Monitoring AJAX Handler
 * Handles performance metrics and error logging
 * 
 * @package Insurance_CRM
 * @version 2.0.0
 * @since 1.9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

// AJAX handler for performance logging
add_action('wp_ajax_insurance_crm_performance_log', 'insurance_crm_handle_performance_log');
add_action('wp_ajax_nopriv_insurance_crm_performance_log', 'insurance_crm_handle_performance_log');

function insurance_crm_handle_performance_log() {
    // Check if performance monitoring is enabled
    if (!get_option('insurance_crm_enable_performance_monitoring', true)) {
        wp_die('Performance monitoring disabled');
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        wp_send_json_error('Invalid data');
    }
    
    // Validate data structure
    $required_fields = array('type', 'timestamp', 'url', 'user_agent');
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            wp_send_json_error('Missing required field: ' . $field);
        }
    }
    
    // Log performance data
    if ($data['type'] === 'performance' && isset($data['metrics'])) {
        insurance_crm_log_performance_metrics($data);
    } elseif ($data['type'] === 'error') {
        insurance_crm_log_error($data);
    }
    
    wp_send_json_success('Logged');
}

function insurance_crm_log_performance_metrics($data) {
    $metrics = $data['metrics'];
    
    // Calculate derived metrics
    $page_load_time = isset($metrics['windowLoaded']) ? $metrics['windowLoaded'] : 0;
    $dom_ready_time = isset($metrics['domContentLoaded']) ? $metrics['domContentLoaded'] : 0;
    $first_paint = isset($metrics['firstPaint']) ? $metrics['firstPaint'] : 0;
    
    // Create log entry
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'type' => 'performance',
        'url' => sanitize_url($data['url']),
        'user_agent' => sanitize_text_field($data['user_agent']),
        'page_load_time' => $page_load_time,
        'dom_ready_time' => $dom_ready_time,
        'first_paint' => $first_paint,
        'user_id' => get_current_user_id()
    );
    
    // Store in database or log file
    if (get_option('insurance_crm_performance_storage', 'log') === 'database') {
        insurance_crm_store_performance_db($log_entry);
    } else {
        insurance_crm_store_performance_log($log_entry);
    }
    
    // Send to external analytics if configured
    $analytics_endpoint = get_option('insurance_crm_analytics_endpoint', '');
    if (!empty($analytics_endpoint)) {
        wp_remote_post($analytics_endpoint, array(
            'body' => json_encode($log_entry),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 5,
            'blocking' => false
        ));
    }
}

function insurance_crm_log_error($data) {
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'type' => 'error',
        'error_type' => sanitize_text_field($data['error_type'] ?? 'unknown'),
        'message' => sanitize_text_field($data['message'] ?? ''),
        'file' => sanitize_text_field($data['file'] ?? ''),
        'line' => intval($data['line'] ?? 0),
        'url' => sanitize_url($data['url']),
        'user_agent' => sanitize_text_field($data['user_agent']),
        'user_id' => get_current_user_id()
    );
    
    // Always log errors to WordPress error log
    error_log(sprintf(
        '[Insurance CRM] %s: %s in %s:%d (URL: %s)',
        $log_entry['error_type'],
        $log_entry['message'],
        $log_entry['file'],
        $log_entry['line'],
        $log_entry['url']
    ));
    
    // Store additional copy if enabled
    if (get_option('insurance_crm_store_js_errors', true)) {
        insurance_crm_store_performance_log($log_entry);
    }
}

function insurance_crm_store_performance_db($data) {
    global $wpdb;
    
    // Create performance table if it doesn't exist
    $table_name = $wpdb->prefix . 'insurance_crm_performance';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            type varchar(20) NOT NULL,
            url text NOT NULL,
            user_agent text,
            page_load_time int DEFAULT NULL,
            dom_ready_time int DEFAULT NULL,
            first_paint int DEFAULT NULL,
            error_type varchar(50) DEFAULT NULL,
            message text DEFAULT NULL,
            file varchar(255) DEFAULT NULL,
            line int DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY type (type),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Insert data
    $wpdb->insert($table_name, $data);
}

function insurance_crm_store_performance_log($data) {
    $log_dir = wp_upload_dir()['basedir'] . '/insurance-crm-logs';
    
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        
        // Create .htaccess to protect log files
        file_put_contents($log_dir . '/.htaccess', "deny from all\n");
    }
    
    $log_file = $log_dir . '/performance-' . date('Y-m-d') . '.log';
    $log_line = date('c') . ' | ' . json_encode($data) . "\n";
    
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
}

// Admin menu to view performance data
add_action('admin_menu', 'insurance_crm_add_performance_menu');

function insurance_crm_add_performance_menu() {
    if (current_user_can('manage_options')) {
        add_submenu_page(
            'insurance-crm',
            'Performance Monitor',
            'Performance',
            'manage_options',
            'insurance-crm-performance',
            'insurance_crm_performance_page'
        );
    }
}

function insurance_crm_performance_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'insurance_crm_performance';
    
    // Get recent performance data
    $performance_data = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE type = 'performance' ORDER BY timestamp DESC LIMIT 100",
        ARRAY_A
    );
    
    // Get recent errors
    $error_data = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE type = 'error' ORDER BY timestamp DESC LIMIT 50",
        ARRAY_A
    );
    
    // Calculate averages
    $avg_load_time = $wpdb->get_var(
        "SELECT AVG(page_load_time) FROM $table_name WHERE type = 'performance' AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    
    ?>
    <div class="wrap">
        <h1>Performance Monitor</h1>
        
        <div class="insurance-crm-stats">
            <div class="insurance-crm-stat-card">
                <div class="insurance-crm-stat-number"><?php echo round($avg_load_time); ?>ms</div>
                <div class="insurance-crm-stat-label">Avg Load Time (7 days)</div>
            </div>
            <div class="insurance-crm-stat-card">
                <div class="insurance-crm-stat-number"><?php echo count($performance_data); ?></div>
                <div class="insurance-crm-stat-label">Recent Measurements</div>
            </div>
            <div class="insurance-crm-stat-card">
                <div class="insurance-crm-stat-number"><?php echo count($error_data); ?></div>
                <div class="insurance-crm-stat-label">Recent Errors</div>
            </div>
        </div>
        
        <h2>Recent Performance Data</h2>
        <div class="insurance-crm-table-container">
            <table class="insurance-crm-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>URL</th>
                        <th>Load Time</th>
                        <th>DOM Ready</th>
                        <th>First Paint</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($performance_data, 0, 20) as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['timestamp']); ?></td>
                        <td><?php echo esc_html(parse_url($row['url'], PHP_URL_PATH)); ?></td>
                        <td><?php echo esc_html($row['page_load_time']); ?>ms</td>
                        <td><?php echo esc_html($row['dom_ready_time']); ?>ms</td>
                        <td><?php echo esc_html($row['first_paint']); ?>ms</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($error_data)): ?>
        <h2>Recent Errors</h2>
        <div class="insurance-crm-table-container">
            <table class="insurance-crm-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>File</th>
                        <th>Line</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($error_data, 0, 10) as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['timestamp']); ?></td>
                        <td><?php echo esc_html($row['error_type']); ?></td>
                        <td><?php echo esc_html(substr($row['message'], 0, 100)); ?></td>
                        <td><?php echo esc_html(basename($row['file'])); ?></td>
                        <td><?php echo esc_html($row['line']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <h2>Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('insurance_crm_performance'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Performance Monitoring</th>
                    <td>
                        <input type="checkbox" name="insurance_crm_enable_performance_monitoring" value="1" 
                               <?php checked(get_option('insurance_crm_enable_performance_monitoring', true)); ?> />
                        <p class="description">Monitor page load times and JavaScript errors</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Sample Rate</th>
                    <td>
                        <input type="number" name="insurance_crm_performance_sample_rate" step="0.01" min="0" max="1" 
                               value="<?php echo esc_attr(get_option('insurance_crm_performance_sample_rate', 0.1)); ?>" />
                        <p class="description">Fraction of requests to monitor (0.1 = 10%)</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    
    <style>
    .insurance-crm-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    .insurance-crm-stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }
    .insurance-crm-stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #2271b1;
        margin: 10px 0;
    }
    .insurance-crm-stat-label {
        color: #666;
        font-size: 14px;
    }
    .insurance-crm-table-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        margin: 20px 0;
    }
    .insurance-crm-table {
        width: 100%;
        border-collapse: collapse;
    }
    .insurance-crm-table th,
    .insurance-crm-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    .insurance-crm-table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    </style>
    <?php
}

// Register settings
add_action('admin_init', 'insurance_crm_register_performance_settings');

function insurance_crm_register_performance_settings() {
    register_setting('insurance_crm_performance', 'insurance_crm_enable_performance_monitoring');
    register_setting('insurance_crm_performance', 'insurance_crm_performance_sample_rate');
}