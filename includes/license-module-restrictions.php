<?php
/**
 * Enhanced License Module Restrictions
 * 
 * Advanced module restriction system with caching and performance optimization
 * 
 * @package Insurance_CRM
 * @author  Anadolu Birlik
 * @since   1.1.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Module_Restrictions {
    
    /**
     * Cache key for module access data
     */
    private $cache_key = 'insurance_crm_module_access_cache';
    
    /**
     * Cache expiration time (5 minutes)
     */
    private $cache_expiration = 300;
    
    /**
     * Available modules with their metadata
     */
    private $available_modules = array(
        'dashboard' => array(
            'name' => 'Dashboard',
            'description' => 'Ana gösterge paneli ve istatistikler',
            'admin_pages' => array('insurance-crm'),
            'capabilities' => array('view_dashboard'),
            'priority' => 1
        ),
        'customers' => array(
            'name' => 'Müşteriler',
            'description' => 'Müşteri yönetimi ve işlemleri',
            'admin_pages' => array('insurance-crm-customers'),
            'capabilities' => array('manage_customers'),
            'priority' => 2
        ),
        'policies' => array(
            'name' => 'Poliçeler',
            'description' => 'Poliçe yönetimi ve takibi',
            'admin_pages' => array('insurance-crm-policies'),
            'capabilities' => array('manage_policies'),
            'priority' => 3
        ),
        'quotes' => array(
            'name' => 'Teklifler',
            'description' => 'Teklif hazırlama ve yönetimi',
            'admin_pages' => array('insurance-crm-quotes'),
            'capabilities' => array('manage_quotes'),
            'priority' => 4
        ),
        'tasks' => array(
            'name' => 'Görevler',
            'description' => 'Görev yönetimi ve takibi',
            'admin_pages' => array('insurance-crm-tasks'),
            'capabilities' => array('manage_tasks'),
            'priority' => 5
        ),
        'reports' => array(
            'name' => 'Raporlar',
            'description' => 'Detaylı raporlama ve analizler',
            'admin_pages' => array('insurance-crm-reports'),
            'capabilities' => array('view_reports'),
            'priority' => 6
        ),
        'data_transfer' => array(
            'name' => 'Veri Aktarımı',
            'description' => 'Veri içe/dışa aktarma işlemleri',
            'admin_pages' => array('insurance-crm-data-transfer'),
            'capabilities' => array('manage_data_transfer'),
            'priority' => 7
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress
        add_action('init', array($this, 'init_hooks'));
        add_action('admin_init', array($this, 'check_page_access'), 1);
        add_action('wp_ajax_check_module_access', array($this, 'ajax_check_module_access'));
        add_action('wp_ajax_nopriv_check_module_access', array($this, 'ajax_check_module_access'));
        
        // Clear cache when license status changes
        add_action('insurance_crm_license_status_changed', array($this, 'clear_module_cache'));
        add_action('update_option_insurance_crm_license_modules', array($this, 'clear_module_cache'));
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Add module access filter
        add_filter('insurance_crm_module_access', array($this, 'filter_module_access'), 10, 2);
        
        // Add admin menu filter for hiding restricted items
        add_action('admin_menu', array($this, 'filter_admin_menu'), 999);
    }
    
    /**
     * Check if module access is allowed with caching
     * 
     * @param string $module Module name
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool True if access is allowed
     */
    public function is_module_accessible($module, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Create cache key for this specific check
        $cache_key = $this->cache_key . '_' . $module . '_' . $user_id;
        
        // Check cache first
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return (bool) $cached_result;
        }
        
        // Perform actual access check
        $has_access = $this->perform_module_access_check($module, $user_id);
        
        // Cache the result
        set_transient($cache_key, $has_access ? 1 : 0, $this->cache_expiration);
        
        return $has_access;
    }
    
    /**
     * Perform the actual module access check
     * 
     * @param string $module Module name
     * @param int $user_id User ID
     * @return bool True if access is allowed
     */
    private function perform_module_access_check($module, $user_id) {
        global $insurance_crm_license_manager;
        
        // Check if module exists
        if (!isset($this->available_modules[$module])) {
            return false;
        }
        
        // Admin users always have access
        if (user_can($user_id, 'administrator')) {
            return true;
        }
        
        // Check license bypass
        if ($insurance_crm_license_manager && 
            $insurance_crm_license_manager->license_api && 
            $insurance_crm_license_manager->license_api->is_license_bypassed()) {
            return true;
        }
        
        // Check basic license validity
        if (!$insurance_crm_license_manager || !$insurance_crm_license_manager->can_access_data()) {
            return false;
        }
        
        // Check if module is in licensed modules list
        if (!$insurance_crm_license_manager->is_module_allowed($module)) {
            return false;
        }
        
        // Check user capabilities
        $module_caps = $this->available_modules[$module]['capabilities'];
        foreach ($module_caps as $cap) {
            if (!user_can($user_id, $cap)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get module restriction message with enhanced details
     * 
     * @param string $module Module name
     * @return array Restriction message data
     */
    public function get_module_restriction_details($module) {
        $module_info = isset($this->available_modules[$module]) ? $this->available_modules[$module] : null;
        
        if (!$module_info) {
            return array(
                'title' => 'Modül Bulunamadı',
                'message' => 'Belirtilen modül sistemde bulunamadı.',
                'type' => 'error',
                'module' => $module
            );
        }
        
        return array(
            'title' => 'Modül Erişimi Kısıtlı',
            'message' => sprintf(
                'Bu modüle (%s) erişim için lisansınız yeterli değil. %s',
                $module_info['name'],
                $module_info['description']
            ),
            'type' => 'module_restriction',
            'module' => $module,
            'module_name' => $module_info['name'],
            'module_description' => $module_info['description'],
            'upgrade_message' => 'Lütfen lisansınızı yükseltin veya uygun modülleri içeren bir lisans satın alın.',
            'contact_info' => array(
                'support_url' => admin_url('admin.php?page=insurance-crm-license'),
                'support_text' => 'Lisans Yönetimine Git'
            )
        );
    }
    
    /**
     * Check current page access and redirect if necessary
     */
    public function check_page_access() {
        // Only check on admin pages
        if (!is_admin() || !isset($_GET['page'])) {
            return;
        }
        
        // Skip license page itself
        if ($_GET['page'] === 'insurance-crm-license') {
            return;
        }
        
        // Find which module this page belongs to
        $current_page = $_GET['page'];
        $restricted_module = null;
        
        foreach ($this->available_modules as $module => $module_info) {
            if (in_array($current_page, $module_info['admin_pages'])) {
                $restricted_module = $module;
                break;
            }
        }
        
        // If this is a CRM page but not in our module list, apply general restrictions
        if (!$restricted_module && strpos($current_page, 'insurance-crm') === 0) {
            global $insurance_crm_license_manager;
            if ($insurance_crm_license_manager && !$insurance_crm_license_manager->can_access_data()) {
                wp_redirect(admin_url('admin.php?page=insurance-crm-license&restriction=data'));
                exit;
            }
        }
        
        // Check module access
        if ($restricted_module && !$this->is_module_accessible($restricted_module)) {
            // Store restriction details for the redirect page
            set_transient('insurance_crm_restriction_details_' . get_current_user_id(), 
                $this->get_module_restriction_details($restricted_module), 60);
            
            wp_redirect(admin_url('admin.php?page=insurance-crm-license&restriction=module&module=' . $restricted_module));
            exit;
        }
    }
    
    /**
     * Filter admin menu to hide restricted items
     */
    public function filter_admin_menu() {
        global $submenu;
        
        // Check if we have the main CRM menu
        if (!isset($submenu['insurance-crm'])) {
            return;
        }
        
        // Get current user
        $user_id = get_current_user_id();
        
        // Filter submenu items
        foreach ($submenu['insurance-crm'] as $index => $menu_item) {
            $page_slug = $menu_item[2];
            
            // Find which module this page belongs to
            $module_found = false;
            foreach ($this->available_modules as $module => $module_info) {
                if (in_array($page_slug, $module_info['admin_pages'])) {
                    if (!$this->is_module_accessible($module, $user_id)) {
                        unset($submenu['insurance-crm'][$index]);
                    }
                    $module_found = true;
                    break;
                }
            }
        }
    }
    
    /**
     * AJAX handler for checking module access
     */
    public function ajax_check_module_access() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'insurance_crm_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $module = isset($_POST['module']) ? sanitize_text_field($_POST['module']) : '';
        
        if (empty($module)) {
            wp_send_json_error(array('message' => 'Module name required'));
            return;
        }
        
        $has_access = $this->is_module_accessible($module);
        
        $response = array(
            'has_access' => $has_access,
            'module' => $module
        );
        
        if (!$has_access) {
            $response['restriction_details'] = $this->get_module_restriction_details($module);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Filter module access - hook for other plugins/code
     * 
     * @param bool $has_access Current access status
     * @param string $module Module name
     * @return bool Filtered access status
     */
    public function filter_module_access($has_access, $module) {
        // Allow other plugins to modify access
        return $this->is_module_accessible($module);
    }
    
    /**
     * Clear module access cache
     */
    public function clear_module_cache() {
        global $wpdb;
        
        // Delete all transients starting with our cache key
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $this->cache_key . '%'
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $this->cache_key . '%'
        ));
    }
    
    /**
     * Get all available modules
     * 
     * @return array Available modules with metadata
     */
    public function get_available_modules() {
        return $this->available_modules;
    }
    
    /**
     * Get accessible modules for current user
     * 
     * @param int $user_id User ID (optional)
     * @return array Accessible modules
     */
    public function get_accessible_modules($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $accessible = array();
        
        foreach ($this->available_modules as $module => $module_info) {
            if ($this->is_module_accessible($module, $user_id)) {
                $accessible[$module] = $module_info;
            }
        }
        
        return $accessible;
    }
}

// Initialize the module restrictions system
global $insurance_crm_module_restrictions;
$insurance_crm_module_restrictions = new Insurance_CRM_Module_Restrictions();

/**
 * Helper function to check module access
 * 
 * @param string $module Module name
 * @return bool True if accessible
 */
function insurance_crm_is_module_accessible($module) {
    global $insurance_crm_module_restrictions;
    
    if (!$insurance_crm_module_restrictions) {
        return true;
    }
    
    return $insurance_crm_module_restrictions->is_module_accessible($module);
}

/**
 * Helper function to get module restriction details
 * 
 * @param string $module Module name
 * @return array Restriction details
 */
function insurance_crm_get_module_restriction_details($module) {
    global $insurance_crm_module_restrictions;
    
    if (!$insurance_crm_module_restrictions) {
        return array();
    }
    
    return $insurance_crm_module_restrictions->get_module_restriction_details($module);
}