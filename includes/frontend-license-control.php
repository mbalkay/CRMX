<?php
/**
 * Frontend License Control Functions
 * 
 * Handles license checking and warnings for representative panel modules
 * 
 * @package Insurance_CRM
 * @author  Anadolu Birlik
 * @since   1.1.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if module is accessible for frontend representative panel
 * 
 * @param string $module Module name
 * @return bool True if module is accessible
 */
function insurance_crm_frontend_can_access_module($module) {
    global $insurance_crm_license_manager;
    
    // Admin users always have access
    if (current_user_can('administrator')) {
        return true;
    }
    
    // Check if license manager exists
    if (!$insurance_crm_license_manager) {
        return true; // Allow access if license manager is not available
    }
    
    // Check license bypass first
    if ($insurance_crm_license_manager->license_api && 
        $insurance_crm_license_manager->license_api->is_license_bypassed()) {
        return true;
    }
    
    // Check basic license validity
    if (!$insurance_crm_license_manager->can_access_data()) {
        return false;
    }
    
    // Use the existing module restrictions system
    global $insurance_crm_module_restrictions;
    if ($insurance_crm_module_restrictions) {
        return $insurance_crm_module_restrictions->is_module_accessible($module);
    }
    
    // Fallback to license manager's module check
    return $insurance_crm_license_manager->is_module_allowed($module);
}

/**
 * Display inline license warning for frontend modules
 * 
 * @param string $module Module name
 * @param string $module_name Display name for the module
 */
function insurance_crm_display_frontend_license_warning($module, $module_name = '') {
    if (empty($module_name)) {
        $module_name = ucfirst($module);
    }
    
    echo '<div class="frontend-license-warning" style="
        background-color: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 15px 20px;
        margin: 20px 0;
        color: #856404;
        font-size: 14px;
        line-height: 1.5;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    ">';
    
    echo '<div style="display: flex; align-items: center; gap: 10px;">';
    echo '<div style="font-size: 18px;">⚠️</div>';
    echo '<div>';
    echo '<strong>Lisans Uyarısı:</strong> ';
    echo 'Lisansınız bu modülü (' . esc_html($module_name) . ') kapsamıyor, lütfen lisansınızı güncelleyin.';
    echo '</div>';
    echo '</div>';
    
    echo '<div style="margin-top: 10px; font-size: 13px;">';
    echo '<a href="' . generate_panel_url('license-management') . '" style="
        color: #856404;
        text-decoration: underline;
        font-weight: bold;
    ">Lisans Yönetimine Git</a>';
    echo '</div>';
    
    echo '</div>';
}

/**
 * Check and display license warning if module is not accessible
 * 
 * @param string $module Module name
 * @param string $module_name Display name for the module
 * @return bool True if module is accessible, false if warning was displayed
 */
function insurance_crm_check_frontend_module_access($module, $module_name = '') {
    if (!insurance_crm_frontend_can_access_module($module)) {
        insurance_crm_display_frontend_license_warning($module, $module_name);
        return false;
    }
    return true;
}

/**
 * Map view names to module names for license checking
 * 
 * @param string $view_name Current view name from $_GET['view']
 * @return string|null Module name or null if no mapping exists
 */
function insurance_crm_get_module_from_view($view_name) {
    $view_to_module_map = array(
        'customers' => 'customers',
        'policies' => 'policies',
        'team_policies' => 'policies',
        'tasks' => 'tasks',
        'team_tasks' => 'tasks',
        'reports' => 'reports',
        'team_reports' => 'reports',
        'offers' => 'quotes', // Frontend uses "offers" but backend uses "quotes"
        'veri_aktar' => 'data_transfer',
        'veri_aktar_facebook' => 'data_transfer',
        'iceri_aktarim' => 'data_transfer',
        'iceri_aktarim_new' => 'data_transfer',
        'import-system' => 'data_transfer'
    );
    
    return isset($view_to_module_map[$view_name]) ? $view_to_module_map[$view_name] : null;
}

/**
 * Get module display name for license warnings
 * 
 * @param string $module Module name
 * @return string Display name
 */
function insurance_crm_get_module_display_name($module) {
    $module_names = array(
        'customers' => 'Müşteriler',
        'policies' => 'Poliçeler',
        'tasks' => 'Görevler',
        'reports' => 'Raporlar',
        'quotes' => 'Teklifler',
        'offers' => 'Teklifler', // For backwards compatibility
        'data_transfer' => 'Veri Aktarımı'
    );
    
    return isset($module_names[$module]) ? $module_names[$module] : ucfirst($module);
}