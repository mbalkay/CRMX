<?php
/**
 * Frontend Controller sınıfı
 * Frontend sayfalarının yönetimi için
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Frontend_Controller {
    /**
     * Eksik tabloları oluştur
     */
    public static function create_missing_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // İşlemler tablosu
        $table_interactions = $wpdb->prefix . 'insurance_crm_interactions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_interactions'") != $table_interactions) {
            $sql = "CREATE TABLE $table_interactions (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                representative_id bigint(20) NOT NULL,
                customer_id bigint(20) NOT NULL,
                type varchar(50) NOT NULL,
                notes text NOT NULL,
                interaction_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY representative_id (representative_id),
                KEY customer_id (customer_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        // Bildirimler tablosu
        $table_notifications = $wpdb->prefix . 'insurance_crm_notifications';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_notifications'") != $table_notifications) {
            $sql = "CREATE TABLE $table_notifications (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                message text NOT NULL,
                related_id bigint(20) DEFAULT 0,
                related_type varchar(50) DEFAULT '',
                is_read tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY is_read (is_read)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Dashboard shortcode ile sayfayı yönet
        add_shortcode('temsilci_dashboard', array($this, 'render_dashboard_page'));
        
        // Eksik tabloları oluştur
        self::create_missing_tables();
        
        // ChartJS ve diğer scriptleri ekle
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Gerekli scriptleri ekle - Enhanced Version 2.0
     */
    public function enqueue_scripts() {
        // Load optimized assets for CRM pages
        if (is_page('temsilci-paneli') || $this->is_crm_page()) {
            // Core dependencies
            wp_enqueue_script('jquery');
            
            // Critical CSS first
            wp_enqueue_style(
                'insurance-crm-critical',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-optimized.css',
                array(),
                '2.0.0',
                'all'
            );
            
            // Real-time announcements
            wp_enqueue_style(
                'insurance-crm-realtime',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/realtime-announcements.css',
                array(),
                '2.0.0'
            );
            
            wp_enqueue_script(
                'insurance-crm-realtime',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/realtime-announcements.js',
                array('jquery'),
                '2.0.0',
                true
            );
            
            // Representative panel specific assets
            if (is_page('temsilci-paneli')) {
                // jQuery UI for datepickers
                wp_enqueue_script('jquery-ui-core');
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css');
                
                // ChartJS for dashboard widgets (modern version)
                wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js', array('jquery'), '4.4.0', true);
                
                // Dashicons
                wp_enqueue_style('dashicons');
                
                // Check for minified versions first
                $css_file = file_exists(plugin_dir_path(dirname(__FILE__)) . 'public/css/representative-panel.min.css') 
                    ? 'public/css/representative-panel.min.css' 
                    : 'public/css/representative-panel.css';
                    
                $js_file = file_exists(plugin_dir_path(dirname(__FILE__)) . 'assets/js/representative-panel.min.js')
                    ? 'assets/js/representative-panel.min.js'
                    : 'assets/js/representative-panel.js';
                
                wp_enqueue_style('insurance-crm-representative', plugin_dir_url(dirname(__FILE__)) . $css_file, array('insurance-crm-critical'), '2.0.0');
                wp_enqueue_script('insurance-crm-representative', plugin_dir_url(dirname(__FILE__)) . $js_file, array('jquery', 'chartjs'), '2.0.0', true);
            }
            
            // Enhanced AJAX configuration
            wp_localize_script('insurance-crm-representative', 'insurance_crm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('insurance_crm_nonce')
            ));
            
            // Real-time configuration
            wp_localize_script('insurance-crm-realtime', 'insuranceCrmRealtime', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('insurance_crm_realtime_nonce'),
                'userId' => get_current_user_id(),
                'pollInterval' => apply_filters('insurance_crm_poll_interval', 30000),
                'enableSSE' => apply_filters('insurance_crm_enable_sse', true),
                'enablePush' => apply_filters('insurance_crm_enable_push', true),
                'vapidPublicKey' => get_option('insurance_crm_vapid_public_key', ''),
                'sounds' => array(
                    'notification' => plugin_dir_url(dirname(__FILE__)) . 'assets/sounds/notification.mp3',
                    'urgent' => plugin_dir_url(dirname(__FILE__)) . 'assets/sounds/urgent.mp3'
                )
            ));
            
            // Performance monitoring
            wp_localize_script('insurance-crm-representative', 'insuranceCrmPerformance', array(
                'enableMonitoring' => get_option('insurance_crm_enable_performance_monitoring', true),
                'sampleRate' => apply_filters('insurance_crm_performance_sample_rate', 0.1),
                'endpoint' => admin_url('admin-ajax.php?action=insurance_crm_performance_log')
            ));
        }
    }
    
    /**
     * Check if current page is a CRM page
     */
    private function is_crm_page() {
        global $post;
        
        // Check for shortcodes
        if (isset($post->post_content) && has_shortcode($post->post_content, 'insurance_crm_panel')) {
            return true;
        }
        
        // Check for admin pages
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'insurance-crm') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Dashboard sayfasını render eder
     */
    public function render_dashboard_page() {
        ob_start();
        
        // Kullanıcı giriş yapmamışsa login sayfasına yönlendir
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/temsilci-girisi/'));
            exit;
        }

        // Kullanıcı müşteri temsilcisi değilse ana sayfaya yönlendir
        $user = wp_get_current_user();
        if (!in_array('insurance_representative', (array)$user->roles)) {
            wp_safe_redirect(home_url());
            exit;
        }
        
        // Hangi sayfanın gösterileceğini belirle
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dashboard';
        
        // Navigasyon menüsü
        echo '<div class="insurance-crm-wrapper">';
        
        // Navigation şablonunu dahil et
        $this->load_template('navigation');
        
        echo '<div class="insurance-crm-main-content">';
        
        // Template dosyasını dahil et
        switch ($page) {
            case 'customers':
                $this->load_template('customers');
                break;
                
            case 'policies':
                $this->load_template('policies');
                break;
                
            case 'offers':
                $this->load_template('offers');
                break;
                
            case 'tasks':
                $this->load_template('tasks');
                break;
                
            case 'universal-import':
                $this->load_template('universal-import');
                break;
                
            case 'reports':
                $this->load_template('reports');
                break;
                
            case 'helpdesk':
                $this->load_template('helpdesk');
                break;
                
            case 'settings':
                $this->load_template('settings');
                break;
                
            default:
                $this->load_template('dashboard');
                break;
        }
        
        echo '</div>'; // .insurance-crm-main-content
        echo '</div>'; // .insurance-crm-wrapper
        
        return ob_get_clean();
    }
    
    /**
     * Şablon dosyasını yükler
     */
    private function load_template($template) {
        $template_file = plugin_dir_path(dirname(__FILE__)) . 'templates/representative-panel/' . $template . '.php';
        
        if (file_exists($template_file)) {
            include_once $template_file;
        } else {
            // Şablon bulunamadığında hata göster
            echo '<div class="insurance-crm-error">';
            echo '<h1>Sayfa bulunamadı</h1>';
            echo '<p>İstediğiniz sayfa şu anda mevcut değil veya erişim yetkiniz yok.</p>';
            echo '<a href="' . add_query_arg('page', 'dashboard', remove_query_arg(array('action', 'id'))) . '" class="button">Dashboard\'a Dön</a>';
            echo '</div>';
        }
    }
}

// Sınıfı başlat
new Insurance_CRM_Frontend_Controller();