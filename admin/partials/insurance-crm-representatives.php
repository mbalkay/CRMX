<?php
/**
 * Modern Müşteri Temsilcileri Sayfası
 *
 * @package    Insurance_CRM
 * @subpackage Insurance_CRM/admin/partials
 * @author     Anadolu Birlik
 * @since      1.0.3
 * @version    2.0.0 - Modern UI Implementation
 */

if (!defined("WPINC")) {
    die;
}

// Include dashboard functions for modern features
if (file_exists(plugin_dir_path(__FILE__) . '../../includes/dashboard-functions.php')) {
    require_once plugin_dir_path(__FILE__) . '../../includes/dashboard-functions.php';
}

// Get current action and parameters
$action = isset($_GET["action"]) ? sanitize_text_field($_GET["action"]) : "";
$rep_id = isset($_GET["edit"]) ? intval($_GET["edit"]) : 0;
$editing = ($rep_id > 0); // Restore original behavior: editing when edit parameter exists
$adding = ($action === "new");
$edit_rep = null;

// View settings
$view_mode = isset($_GET["view"]) ? sanitize_text_field($_GET["view"]) : "grid"; // grid or list
$status_filter = isset($_GET["status"]) ? sanitize_text_field($_GET["status"]) : "all";
$role_filter = isset($_GET["role"]) ? sanitize_text_field($_GET["role"]) : "all";

if ($editing) {
    global $wpdb;
    $table_reps = $wpdb->prefix . "insurance_crm_representatives";
    $table_users = $wpdb->users;
    
    $edit_rep = $wpdb->get_row($wpdb->prepare(
        "SELECT r.*, u.user_email as email, u.display_name, u.user_login as username,
                u.first_name, u.last_name
         FROM $table_reps r 
         LEFT JOIN $table_users u ON r.user_id = u.ID 
         WHERE r.id = %d",
        $rep_id
    ));
    
    if (!$edit_rep) {
        $editing = false;
    }
}

// Role definitions
$role_definitions = array(
    1 => 'Patron',
    2 => 'Müdür', 
    3 => 'Müdür Yardımcısı',
    4 => 'Ekip Lideri',
    5 => 'Müşteri Temsilcisi'
);

if (isset($_POST["submit_representative"]) && isset($_POST["representative_nonce"]) && 
    wp_verify_nonce($_POST["representative_nonce"], "add_edit_representative")) {
    
    if ($editing) {
        // Get all form data
        $rep_data = array(
            "title" => sanitize_text_field($_POST["title"]),
            "phone" => sanitize_text_field($_POST["phone"]),
            "department" => sanitize_text_field($_POST["department"]),
            "monthly_target" => floatval($_POST["monthly_target"]),
            "target_policy_count" => intval($_POST["target_policy_count"]),
            "role" => intval($_POST["role"]),
            "status" => sanitize_text_field($_POST["status"]),
            "notes" => sanitize_textarea_field($_POST["notes"]),
            "updated_at" => current_time("mysql")
        );
        
        // Personal information
        if (!empty($_POST["birth_date"])) {
            $rep_data["birth_date"] = sanitize_text_field($_POST["birth_date"]);
        }
        if (!empty($_POST["wedding_anniversary"])) {
            $rep_data["wedding_anniversary"] = sanitize_text_field($_POST["wedding_anniversary"]);
        }
        
        // Children birthdays (JSON format)
        $children_birthdays = array();
        if (isset($_POST["children_birthdays"]) && is_array($_POST["children_birthdays"])) {
            foreach ($_POST["children_birthdays"] as $child_data) {
                if (!empty($child_data["name"]) && !empty($child_data["birth_date"])) {
                    $children_birthdays[] = array(
                        "name" => sanitize_text_field($child_data["name"]),
                        "birth_date" => sanitize_text_field($child_data["birth_date"])
                    );
                }
            }
        }
        if (!empty($children_birthdays)) {
            $rep_data["children_birthdays"] = json_encode($children_birthdays);
        }
        
        // Permission fields
        $rep_data["customer_edit"] = isset($_POST["customer_edit"]) ? 1 : 0;
        $rep_data["customer_delete"] = isset($_POST["customer_delete"]) ? 1 : 0;
        $rep_data["policy_edit"] = isset($_POST["policy_edit"]) ? 1 : 0;
        $rep_data["policy_delete"] = isset($_POST["policy_delete"]) ? 1 : 0;
        $rep_data["task_edit"] = isset($_POST["task_edit"]) ? 1 : 0;
        $rep_data["export_data"] = isset($_POST["export_data"]) ? 1 : 0;
        $rep_data["can_change_customer_representative"] = isset($_POST["can_change_customer_representative"]) ? 1 : 0;
        $rep_data["can_change_policy_representative"] = isset($_POST["can_change_policy_representative"]) ? 1 : 0;
        $rep_data["can_change_task_representative"] = isset($_POST["can_change_task_representative"]) ? 1 : 0;
        $rep_data["can_view_deleted_policies"] = isset($_POST["can_view_deleted_policies"]) ? 1 : 0;
        $rep_data["can_restore_deleted_policies"] = isset($_POST["can_restore_deleted_policies"]) ? 1 : 0;
        
        global $wpdb;
        $table_reps = $wpdb->prefix . "insurance_crm_representatives";
        
        $wpdb->update(
            $table_reps,
            $rep_data,
            array("id" => $rep_id)
        );
        
        if (isset($_POST["first_name"]) && isset($_POST["last_name"]) && isset($_POST["email"])) {
            $user_id = $edit_rep->user_id;
            wp_update_user(array(
                "ID" => $user_id,
                "first_name" => sanitize_text_field($_POST["first_name"]),
                "last_name" => sanitize_text_field($_POST["last_name"]),
                "display_name" => sanitize_text_field($_POST["first_name"]) . " " . sanitize_text_field($_POST["last_name"]),
                "user_email" => sanitize_email($_POST["email"])
            ));
        }
        
        if (!empty($_POST["password"]) && !empty($_POST["confirm_password"]) && $_POST["password"] == $_POST["confirm_password"]) {
            wp_set_password($_POST["password"], $edit_rep->user_id);
        }
        
        echo '<div class="notice notice-success"><p>Müşteri temsilcisi güncellendi.</p></div>';
        
        echo '<script>window.location.href = "' . admin_url("admin.php?page=insurance-crm-representatives") . '";</script>';
    } else {
        if (isset($_POST["username"]) && isset($_POST["password"]) && isset($_POST["confirm_password"])) {
            $username = sanitize_user($_POST["username"]);
            $password = $_POST["password"];
            $confirm_password = $_POST["confirm_password"];
            
            if (empty($username) || empty($password) || empty($confirm_password)) {
                echo '<div class="notice notice-error"><p>Kullanıcı adı ve şifre alanlarını doldurunuz.</p></div>';
            } else if ($password !== $confirm_password) {
                echo '<div class="notice notice-error"><p>Şifreler eşleşmiyor.</p></div>';
            } else if (username_exists($username)) {
                echo '<div class="notice notice-error"><p>Bu kullanıcı adı zaten kullanımda.</p></div>';
            } else if (email_exists($_POST["email"])) {
                echo '<div class="notice notice-error"><p>Bu e-posta adresi zaten kullanımda.</p></div>';
            } else {
                $user_id = wp_create_user($username, $password, sanitize_email($_POST["email"]));
                
                if (!is_wp_error($user_id)) {
                    wp_update_user(
                        array(
                            "ID" => $user_id,
                            "first_name" => sanitize_text_field($_POST["first_name"]),
                            "last_name" => sanitize_text_field($_POST["last_name"]),
                            "display_name" => sanitize_text_field($_POST["first_name"]) . " " . sanitize_text_field($_POST["last_name"])
                        )
                    );
                    
                    $user = new WP_User($user_id);
                    $user->set_role("insurance_representative");
                    
                    global $wpdb;
                    $table_name = $wpdb->prefix . "insurance_crm_representatives";
                    
                    $insert_data = array(
                        "user_id" => $user_id,
                        "title" => sanitize_text_field($_POST["title"]),
                        "phone" => sanitize_text_field($_POST["phone"]),
                        "department" => sanitize_text_field($_POST["department"]),
                        "monthly_target" => floatval($_POST["monthly_target"]),
                        "target_policy_count" => intval($_POST["target_policy_count"]),
                        "role" => intval($_POST["role"]),
                        "status" => "active",
                        "created_at" => current_time("mysql"),
                        "updated_at" => current_time("mysql")
                    );
                    
                    // Add permission defaults based on role
                    $role = intval($_POST["role"]);
                    if ($role <= 2) { // Patron or Müdür
                        $insert_data["customer_edit"] = 1;
                        $insert_data["customer_delete"] = 1;
                        $insert_data["policy_edit"] = 1;
                        $insert_data["policy_delete"] = 1;
                        $insert_data["task_edit"] = 1;
                        $insert_data["export_data"] = 1;
                        $insert_data["can_change_customer_representative"] = 1;
                        $insert_data["can_change_policy_representative"] = 1;
                        $insert_data["can_change_task_representative"] = 1;
                        $insert_data["can_view_deleted_policies"] = 1;
                        $insert_data["can_restore_deleted_policies"] = 1;
                    } else {
                        $insert_data["customer_edit"] = 1;
                        $insert_data["customer_delete"] = 0;
                        $insert_data["policy_edit"] = 1;
                        $insert_data["policy_delete"] = 0;
                        $insert_data["task_edit"] = 1;
                        $insert_data["export_data"] = 0;
                        $insert_data["can_change_customer_representative"] = 0;
                        $insert_data["can_change_policy_representative"] = 0;
                        $insert_data["can_change_task_representative"] = 0;
                        $insert_data["can_view_deleted_policies"] = 0;
                        $insert_data["can_restore_deleted_policies"] = 0;
                    }
                    
                    $wpdb->insert($table_name, $insert_data);
                    
                    echo '<div class="notice notice-success"><p>Müşteri temsilcisi başarıyla eklendi.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Kullanıcı oluşturulurken bir hata oluştu: ' . $user_id->get_error_message() . '</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error"><p>Gerekli alanlar doldurulmadı.</p></div>';
        }
    }
}

// Get representatives with filtering
global $wpdb;
$table_name = $wpdb->prefix . "insurance_crm_representatives";

// Build WHERE clause for filtering
$where_conditions = array();
$where_params = array();

if ($status_filter !== "all") {
    $where_conditions[] = "r.status = %s";
    $where_params[] = $status_filter;
} else {
    // Default behavior: show only active representatives (matching original behavior)
    $where_conditions[] = "r.status = %s";
    $where_params[] = 'active';
}

if ($role_filter !== "all") {
    $where_conditions[] = "r.role = %d";
    $where_params[] = intval($role_filter);
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT r.*, u.user_email as email, u.display_name, u.user_login as username,
                 u.first_name, u.last_name, u.ID as wp_user_id
          FROM $table_name r 
          LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
          WHERE $where_clause
          ORDER BY r.role ASC, r.created_at DESC";

if (!empty($where_params)) {
    $representatives = $wpdb->get_results($wpdb->prepare($query, ...$where_params));
} else {
    $representatives = $wpdb->get_results($query);
}

// Debug information (for development only - should be removed in production)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'Query: ' . $query . '<br>';
    echo 'Where params: ' . print_r($where_params, true) . '<br>';
    echo 'Representatives count: ' . count($representatives) . '<br>';
    if ($wpdb->last_error) {
        echo 'DB Error: ' . $wpdb->last_error . '<br>';
    }
    echo '</div>';
}

// Calculate statistics
$total_representatives = count($representatives);
$active_representatives = 0;
$inactive_representatives = 0;
$roles_stats = array_fill_keys(array_keys($role_definitions), 0);

foreach ($representatives as $rep) {
    if ($rep->status === 'active') {
        $active_representatives++;
    } else {
        $inactive_representatives++;
    }
    if (isset($roles_stats[$rep->role])) {
        $roles_stats[$rep->role]++;
    }
}

// Get teams info (if available)
$settings = get_option('insurance_crm_settings', array());
$teams = isset($settings['teams_settings']['teams']) ? $settings['teams_settings']['teams'] : array();
$active_teams_count = count($teams);

// Get performance data for each representative
foreach ($representatives as &$rep) {
    // Get avatar URL
    if (function_exists('get_user_avatar_url')) {
        $rep->avatar_url = get_user_avatar_url($rep->user_id);
    } else {
        $rep->avatar_url = get_avatar_url($rep->user_id, array('size' => 96));
    }
    
    // Get last login
    $rep->last_login = get_user_meta($rep->user_id, 'last_login', true);
    
    // Get performance stats
    $rep->customer_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_customers WHERE representative_id = %d",
        $rep->id
    )) ?: 0;
    
    $rep->policy_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies 
         WHERE representative_id = %d AND cancellation_date IS NULL",
        $rep->id
    )) ?: 0;
    
    $rep->total_premium = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(policy_fee) FROM {$wpdb->prefix}insurance_crm_policies 
         WHERE representative_id = %d AND cancellation_date IS NULL",
        $rep->id
    )) ?: 0;
}
unset($rep); // Break reference
?>

<!-- Modern Admin Styles -->
<style>
/* Include modern representative panel global styles */
:root {
    --primary-500: #667eea;
    --primary-600: #5a67d8;
    --success-500: #38a169;
    --warning-500: #ed8936;
    --error-500: #e53e3e;
    --gray-50: #f7fafc;
    --gray-100: #edf2f7;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e0;
    --gray-500: #718096;
    --gray-600: #4a5568;
    --gray-700: #2d3748;
    --gray-800: #1a202c;
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.5rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
    --radius-md: 8px;
    --radius-lg: 12px;
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --transition-fast: 0.15s ease;
}

.modern-admin-container {
    padding: var(--spacing-xl);
    background-color: var(--gray-50);
    min-height: 100vh;
}

.modern-page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: var(--radius-lg);
    margin-bottom: var(--spacing-xl);
    color: white;
    box-shadow: var(--shadow-md);
}

.header-main {
    padding: var(--spacing-xl);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.header-left h1 {
    font-size: 28px;
    font-weight: 600;
    margin: 0 0 var(--spacing-sm) 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.header-subtitle {
    font-size: 16px;
    opacity: 0.9;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.btn-modern {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-md);
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    transition: all var(--transition-fast);
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-modern.btn-primary {
    background: white;
    color: var(--primary-600);
}

.btn-modern.btn-primary:hover {
    background: var(--gray-100);
    transform: translateY(-1px);
}

.btn-modern.btn-secondary {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
}

.btn-modern.btn-secondary:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-1px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
    transition: all var(--transition-fast);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-500);
}

.stat-card.success::before { background: var(--success-500); }
.stat-card.warning::before { background: var(--warning-500); }
.stat-card.error::before { background: var(--error-500); }

.stat-content {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.stat-info h3 {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    color: var(--gray-800);
}

.stat-info p {
    font-size: 14px;
    color: var(--gray-600);
    margin: 0;
}

.filter-section {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
}

.filter-controls {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.filter-group label {
    font-size: 12px;
    font-weight: 500;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group select {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-md);
    background: white;
    font-size: 14px;
}

.view-toggle {
    display: flex;
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.view-toggle-btn {
    padding: var(--spacing-sm) var(--spacing-md);
    background: white;
    border: none;
    cursor: pointer;
    transition: all var(--transition-fast);
    font-size: 14px;
}

.view-toggle-btn.active {
    background: var(--primary-500);
    color: white;
}

.representatives-container {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
}

.representatives-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--spacing-lg);
}

.rep-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    transition: all var(--transition-fast);
    position: relative;
}

.rep-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.rep-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.rep-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-200);
    font-weight: 600;
    color: var(--gray-600);
}

.rep-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.rep-info h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--gray-800);
}

.rep-info p {
    margin: 0;
    font-size: 14px;
    color: var(--gray-600);
}

.rep-role {
    display: inline-block;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--radius-md);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rep-role.role-1 { background: #fef2f2; color: #991b1b; }
.rep-role.role-2 { background: #fef3c7; color: #92400e; }
.rep-role.role-3 { background: #ecfdf5; color: #065f46; }
.rep-role.role-4 { background: #eff6ff; color: #1e40af; }
.rep-role.role-5 { background: #f3f4f6; color: #374151; }

.rep-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--spacing-sm);
    margin: var(--spacing-md) 0;
}

.rep-stat {
    text-align: center;
    padding: var(--spacing-sm);
    background: var(--gray-50);
    border-radius: var(--radius-md);
}

.rep-stat .stat-number {
    font-size: 18px;
    font-weight: 700;
    color: var(--gray-800);
}

.rep-stat .stat-label {
    font-size: 12px;
    color: var(--gray-600);
}

.rep-actions {
    display: flex;
    gap: var(--spacing-xs);
    justify-content: flex-end;
}

.rep-action-btn {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-md);
    background: white;
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
    font-size: 12px;
    color: var(--gray-600);
}

.rep-action-btn:hover {
    background: var(--gray-50);
    transform: translateY(-1px);
}

.rep-action-btn.edit { color: var(--primary-600); border-color: var(--primary-300); }
.rep-action-btn.delete { color: var(--error-600); border-color: var(--error-300); }

.representatives-table {
    width: 100%;
    border-collapse: collapse;
}

.representatives-table th,
.representatives-table td {
    padding: var(--spacing-md);
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.representatives-table th {
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-700);
}

.user-cell {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.user-avatar-table {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-200);
    font-size: 12px;
    font-weight: 600;
    color: var(--gray-600);
}

.user-avatar-table img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.status-badge {
    display: inline-block;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--radius-md);
    font-size: 12px;
    font-weight: 600;
}

.status-badge.active {
    background: #dcfce7;
    color: #166534;
}

.status-badge.inactive {
    background: #fef2f2;
    color: #991b1b;
}

@media (max-width: 768px) {
    .modern-admin-container {
        padding: var(--spacing-md);
    }
    
    .header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .representatives-grid {
        grid-template-columns: 1fr;
    }
}

/* Form Styles */
.modern-form-container {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    margin-top: var(--spacing-xl);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
}

.form-tabs {
    display: flex;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: var(--spacing-xl);
    gap: var(--spacing-md);
}

.tab-link {
    padding: var(--spacing-md) var(--spacing-lg);
    text-decoration: none;
    color: var(--gray-600);
    border-bottom: 3px solid transparent;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-weight: 500;
}

.tab-link.active {
    color: var(--primary-600);
    border-bottom-color: var(--primary-600);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.form-section {
    margin-bottom: var(--spacing-xl);
}

.form-section h2 {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 var(--spacing-sm) 0;
    color: var(--gray-800);
}

.form-section p {
    color: var(--gray-600);
    margin: 0 0 var(--spacing-lg) 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--spacing-lg);
}

.form-group {
    margin-bottom: var(--spacing-lg);
}

.form-group.col-span-2 {
    grid-column: span 2;
}

.form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: var(--spacing-xs);
    color: var(--gray-700);
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: border-color var(--transition-fast);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-500);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-tip {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: var(--spacing-xs);
}

.required {
    color: var(--error-500);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
    margin-bottom: var(--spacing-sm);
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.permission-section {
    background: var(--gray-50);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.permission-section h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 var(--spacing-md) 0;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-md);
}

.form-actions {
    display: flex;
    gap: var(--spacing-md);
    justify-content: flex-end;
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--gray-200);
}

.empty-state {
    text-align: center;
    padding: var(--spacing-xl);
}

.empty-state h3 {
    color: var(--gray-700);
    margin: var(--spacing-md) 0;
    font-size: 18px;
    font-weight: 600;
}

.empty-state p {
    color: var(--gray-600);
    margin: 0 0 var(--spacing-lg) 0;
    font-size: 14px;
}

.no-representatives-message {
    grid-column: 1 / -1;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px;
}
</style>

<div class="modern-admin-container">
    <!-- Modern Header -->
    <div class="modern-page-header">
        <div class="header-main">
            <div class="header-content">
                <div class="header-left">
                    <h1>
                        <i class="dashicons dashicons-admin-users"></i>
                        Müşteri Temsilcileri
                    </h1>
                    <p class="header-subtitle">Personel yönetimi ve performans takibi</p>
                </div>
                <div class="header-actions">
                    <?php if (!$editing && !$adding): ?>
                        <a href="<?php echo admin_url('admin.php?page=insurance-crm-representatives&action=new'); ?>" 
                           class="btn-modern btn-primary">
                            <i class="dashicons dashicons-plus"></i> Yeni Temsilci
                        </a>
                    <?php endif; ?>
                    <?php if ($editing): ?>
                        <a href="<?php echo admin_url('admin.php?page=insurance-crm-representatives'); ?>" 
                           class="btn-modern btn-secondary">
                            <i class="dashicons dashicons-arrow-left-alt"></i> Geri Dön
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$editing && !$adding): ?>
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="dashicons dashicons-admin-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_representatives); ?></h3>
                    <p>Toplam Temsilci</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-content">
                <div class="stat-icon" style="background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);">
                    <i class="dashicons dashicons-yes"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($active_representatives); ?></h3>
                    <p>Aktif Temsilci</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-content">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);">
                    <i class="dashicons dashicons-warning"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($inactive_representatives); ?></h3>
                    <p>Pasif Temsilci</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="dashicons dashicons-groups"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($active_teams_count); ?></h3>
                    <p>Aktif Ekip</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="insurance-crm-representatives">
            <div class="filter-controls">
                <div class="filter-group">
                    <label>Rol</label>
                    <select name="role">
                        <option value="all" <?php selected($role_filter, 'all'); ?>>Tüm Roller</option>
                        <?php foreach ($role_definitions as $role_id => $role_name): ?>
                        <option value="<?php echo $role_id; ?>" <?php selected($role_filter, (string)$role_id); ?>>
                            <?php echo $role_name; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Durum</label>
                    <select name="status">
                        <option value="all" <?php selected($status_filter, 'all'); ?>>Tüm Durumlar</option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>>Aktif</option>
                        <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Pasif</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-modern btn-primary">
                        <i class="dashicons dashicons-filter"></i> Filtrele
                    </button>
                </div>
                
                <div class="filter-group">
                    <label>Görünüm</label>
                    <div class="view-toggle">
                        <button type="button" class="view-toggle-btn <?php echo $view_mode === 'grid' ? 'active' : ''; ?>" 
                                onclick="toggleView('grid')">
                            <i class="dashicons dashicons-grid-view"></i>
                        </button>
                        <button type="button" class="view-toggle-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>" 
                                onclick="toggleView('list')">
                            <i class="dashicons dashicons-list-view"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Representatives Container -->
    <div class="representatives-container">
        <?php if ($view_mode === 'grid'): ?>
        <!-- Grid View -->
        <div class="representatives-grid" id="gridView">
            <?php foreach ($representatives as $rep): ?>
            <div class="rep-card">
                <div class="rep-header">
                    <div class="rep-avatar">
                        <?php if (!empty($rep->avatar_url) && $rep->avatar_url !== get_avatar_url(0)): ?>
                            <img src="<?php echo esc_url($rep->avatar_url); ?>" alt="<?php echo esc_attr($rep->display_name); ?>">
                        <?php else: ?>
                            <?php echo esc_html(strtoupper(substr($rep->display_name ?: 'U', 0, 2))); ?>
                        <?php endif; ?>
                    </div>
                    <div class="rep-info">
                        <h3><?php echo esc_html($rep->display_name); ?></h3>
                        <p><?php echo esc_html($rep->title ?: 'Temsilci'); ?></p>
                    </div>
                    <span class="rep-role role-<?php echo $rep->role; ?>">
                        <?php echo $role_definitions[$rep->role] ?? 'Temsilci'; ?>
                    </span>
                </div>
                
                <div class="rep-stats">
                    <div class="rep-stat">
                        <div class="stat-number"><?php echo number_format($rep->customer_count); ?></div>
                        <div class="stat-label">Müşteri</div>
                    </div>
                    <div class="rep-stat">
                        <div class="stat-number"><?php echo number_format($rep->policy_count); ?></div>
                        <div class="stat-label">Poliçe</div>
                    </div>
                    <div class="rep-stat">
                        <div class="stat-number">₺<?php echo number_format($rep->total_premium, 0, ',', '.'); ?></div>
                        <div class="stat-label">Prim</div>
                    </div>
                </div>
                
                <div style="margin: var(--spacing-md) 0;">
                    <p style="font-size: 12px; color: var(--gray-600); margin: 0;">
                        <i class="dashicons dashicons-email"></i> <?php echo esc_html($rep->email); ?>
                    </p>
                    <?php if ($rep->phone): ?>
                    <p style="font-size: 12px; color: var(--gray-600); margin: 0;">
                        <i class="dashicons dashicons-phone"></i> <?php echo esc_html($rep->phone); ?>
                    </p>
                    <?php endif; ?>
                    <p style="font-size: 12px; margin: var(--spacing-xs) 0 0 0;">
                        <span class="status-badge <?php echo $rep->status; ?>">
                            <?php echo $rep->status === 'active' ? 'Aktif' : 'Pasif'; ?>
                        </span>
                    </p>
                </div>
                
                <div class="rep-actions">
                    <a href="<?php echo admin_url('admin.php?page=insurance-crm-representatives&action=edit&edit=' . $rep->id); ?>" 
                       class="rep-action-btn edit" title="Düzenle">
                        <i class="dashicons dashicons-edit"></i>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=insurance-crm-representatives&action=delete&id=' . $rep->id), 'delete_representative_' . $rep->id); ?>" 
                       class="rep-action-btn delete" title="Sil"
                       onclick="return confirm('Bu müşteri temsilcisini silmek istediğinizden emin misiniz?');">
                        <i class="dashicons dashicons-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($representatives)): ?>
            <div class="no-representatives-message">
                <div class="empty-state">
                    <i class="dashicons dashicons-admin-users" style="font-size: 48px; color: var(--gray-400);"></i>
                    <h3>Henüz temsilci bulunmuyor</h3>
                    <p>Sistemde hiç temsilci kaydı bulunamadı. İlk temsilciyi eklemek için aşağıdaki butona tıklayın.</p>
                    <a href="<?php echo admin_url('admin.php?page=insurance-crm-representatives&action=new'); ?>" 
                       class="btn-modern btn-primary">
                        <i class="dashicons dashicons-plus"></i> Yeni Temsilci Ekle
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- List View -->
        <div id="listView">
            <table class="representatives-table">
                <thead>
                    <tr>
                        <th>Temsilci</th>
                        <th>Rol</th>
                        <th>İletişim</th>
                        <th>Performans</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($representatives as $rep): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-table">
                                    <?php if (!empty($rep->avatar_url) && $rep->avatar_url !== get_avatar_url(0)): ?>
                                        <img src="<?php echo esc_url($rep->avatar_url); ?>" alt="<?php echo esc_attr($rep->display_name); ?>">
                                    <?php else: ?>
                                        <?php echo esc_html(strtoupper(substr($rep->display_name ?: 'U', 0, 2))); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo esc_html($rep->display_name); ?></strong><br>
                                    <small><?php echo esc_html($rep->title ?: 'Temsilci'); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="rep-role role-<?php echo $rep->role; ?>">
                                <?php echo $role_definitions[$rep->role] ?? 'Temsilci'; ?>
                            </span>
                        </td>
                        <td>
                            <div>
                                <div style="font-size: 12px; margin-bottom: 2px;">
                                    <i class="dashicons dashicons-email"></i> <?php echo esc_html($rep->email); ?>
                                </div>
                                <?php if ($rep->phone): ?>
                                <div style="font-size: 12px;">
                                    <i class="dashicons dashicons-phone"></i> <?php echo esc_html($rep->phone); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12px;">
                                <div><?php echo number_format($rep->customer_count); ?> müşteri</div>
                                <div><?php echo number_format($rep->policy_count); ?> poliçe</div>
                                <div>₺<?php echo number_format($rep->total_premium, 0, ',', '.'); ?> prim</div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $rep->status; ?>">
                                <?php echo $rep->status === 'active' ? 'Aktif' : 'Pasif'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="rep-actions">
                                <a href="<?php echo admin_url('admin.php?page=insurance-crm-representatives&action=edit&edit=' . $rep->id); ?>" 
                                   class="rep-action-btn edit" title="Düzenle">
                                    <i class="dashicons dashicons-edit"></i>
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=insurance-crm-representatives&action=delete&id=' . $rep->id), 'delete_representative_' . $rep->id); ?>" 
                                   class="rep-action-btn delete" title="Sil"
                                   onclick="return confirm('Bu müşteri temsilcisini silmek istediğinizden emin misiniz?');">
                                    <i class="dashicons dashicons-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($representatives)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <i class="dashicons dashicons-admin-users" style="font-size: 48px; color: var(--gray-400);"></i>
                                <h3>Henüz temsilci bulunmuyor</h3>
                                <p>Sistemde hiç temsilci kaydı bulunamadı. İlk temsilciyi eklemek için aşağıdaki butona tıklayın.</p>
                                <a href="<?php echo admin_url('admin.php?page=insurance-crm-representatives&action=new'); ?>" 
                                   class="btn-modern btn-primary">
                                    <i class="dashicons dashicons-plus"></i> Yeni Temsilci Ekle
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($editing || $adding): ?>
    <!-- Modern Form -->
    <div class="modern-form-container">
        <h2><?php echo $editing ? 'Müşteri Temsilcisini Düzenle' : 'Yeni Müşteri Temsilcisi Ekle'; ?></h2>
        
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field("add_edit_representative", "representative_nonce"); ?>
            <?php if ($editing): ?>
                <input type="hidden" name="rep_id" value="<?php echo $rep_id; ?>">
            <?php endif; ?>
            
            <div class="form-tabs">
                <a href="#basic" class="tab-link active" data-tab="basic">
                    <i class="dashicons dashicons-admin-users"></i> Temel Bilgiler
                </a>
                <a href="#targets" class="tab-link" data-tab="targets">
                    <i class="dashicons dashicons-chart-bar"></i> Hedefler
                </a>
                <a href="#role" class="tab-link" data-tab="role">
                    <i class="dashicons dashicons-admin-tools"></i> Rol ve Yetki
                </a>
                <a href="#security" class="tab-link" data-tab="security">
                    <i class="dashicons dashicons-shield"></i> Güvenlik
                </a>
            </div>
            
            <!-- Basic Information Tab -->
            <div class="tab-content active" id="basic">
                <div class="form-section">
                    <h2>Temel Bilgiler</h2>
                    <p>Temsilcinin kişisel ve iletişim bilgilerini güncelleyin.</p>
                    
                    <div class="form-grid">
                        <?php if (!$editing): ?>
                        <div class="form-group">
                            <label for="username">Kullanıcı Adı <span class="required">*</span></label>
                            <input type="text" name="username" id="username" required>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="first_name">Ad <span class="required">*</span></label>
                            <input type="text" name="first_name" id="first_name" required
                                   value="<?php echo $editing ? esc_attr($edit_rep->first_name) : ""; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Soyad <span class="required">*</span></label>
                            <input type="text" name="last_name" id="last_name" required
                                   value="<?php echo $editing ? esc_attr($edit_rep->last_name) : ""; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-posta <span class="required">*</span></label>
                            <input type="email" name="email" id="email" required
                                   value="<?php echo $editing ? esc_attr($edit_rep->email) : ""; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefon</label>
                            <input type="tel" name="phone" id="phone"
                                   value="<?php echo $editing ? esc_attr($edit_rep->phone) : ""; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Ünvan <span class="required">*</span></label>
                            <input type="text" name="title" id="title" required
                                   value="<?php echo $editing ? esc_attr($edit_rep->title) : ""; ?>">
                            <p class="form-tip">Örnek: Müşteri Temsilcisi, Uzman, Yönetici vs.</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="department">Departman</label>
                            <input type="text" name="department" id="department"
                                   value="<?php echo $editing ? esc_attr($edit_rep->department) : ""; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Durum</label>
                            <select name="status" id="status">
                                <option value="active" <?php echo ($editing && $edit_rep->status === 'active') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="inactive" <?php echo ($editing && $edit_rep->status === 'inactive') ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                        </div>
                        
                        <div class="form-group col-span-2">
                            <label for="notes">Notlar</label>
                            <textarea name="notes" id="notes" rows="3"><?php echo $editing ? esc_textarea($edit_rep->notes ?? '') : ""; ?></textarea>
                            <p class="form-tip">İç notlar ve özel bilgiler</p>
                        </div>
                    </div>
                </div>
                
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h3 style="color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-bottom: 20px;">
                        <i class="dashicons dashicons-heart"></i> Kişisel ve Aile Bilgileri
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="birth_date">
                                <i class="dashicons dashicons-calendar"></i> Doğum Tarihi
                            </label>
                            <input type="date" name="birth_date" id="birth_date" 
                                   value="<?php echo $editing ? esc_attr($edit_rep->birth_date ?? '') : ''; ?>">
                            <p class="form-tip">Doğum günü kutlamaları için kullanılacaktır</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="wedding_anniversary">
                                <i class="dashicons dashicons-heart"></i> Evlilik Yıl Dönümü
                            </label>
                            <input type="date" name="wedding_anniversary" id="wedding_anniversary" 
                                   value="<?php echo $editing ? esc_attr($edit_rep->wedding_anniversary ?? '') : ''; ?>">
                            <p class="form-tip">Evlilik yıl dönümü kutlamaları için kullanılacaktır</p>
                        </div>
                        
                        <div class="form-group col-span-2">
                            <label>
                                <i class="dashicons dashicons-groups"></i> Çocukların Doğum Günleri
                            </label>
                            <div id="children-birthdays-container">
                                <?php 
                                $children_birthdays = [];
                                if ($editing && !empty($edit_rep->children_birthdays)) {
                                    $children_birthdays = json_decode($edit_rep->children_birthdays, true) ?: [];
                                }
                                
                                if (empty($children_birthdays)) {
                                    $children_birthdays = [['name' => '', 'birth_date' => '']]; // At least one empty row
                                }
                                
                                foreach ($children_birthdays as $index => $child): ?>
                                <div class="child-birthday-row" data-index="<?php echo $index; ?>" style="margin-bottom: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="text" name="children_birthdays[<?php echo $index; ?>][name]" 
                                               placeholder="Çocuğun adı" value="<?php echo esc_attr($child['name'] ?? ''); ?>" 
                                               style="flex: 1; min-width: 150px;">
                                        <input type="date" name="children_birthdays[<?php echo $index; ?>][birth_date]" 
                                               value="<?php echo esc_attr($child['birth_date'] ?? ''); ?>" 
                                               style="flex: 1;">
                                        <button type="button" class="remove-child-btn" onclick="removeChildRow(this)"
                                                style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                            <i class="dashicons dashicons-minus" style="font-size: 12px;"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn-modern btn-secondary" onclick="addChildRow()" style="margin-top: 10px;">
                                <i class="dashicons dashicons-plus"></i> Çocuk Ekle
                            </button>
                            <p class="form-tip">Çocukların doğum günü kutlamaları için kullanılacaktır</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Targets Tab -->
            <div class="tab-content" id="targets">
                <div class="form-section">
                    <h2>Hedefler ve Performans</h2>
                    <p>Temsilcinin aylık hedeflerini ve performans göstergelerini belirleyin.</p>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="monthly_target">Aylık Prim Hedefi (₺) <span class="required">*</span></label>
                            <input type="number" step="0.01" name="monthly_target" id="monthly_target" required
                                   value="<?php echo $editing ? esc_attr($edit_rep->monthly_target) : ""; ?>">
                            <p class="form-tip">Temsilcinin aylık satış hedefi (₺)</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="target_policy_count">Aylık Poliçe Hedefi (Adet)</label>
                            <input type="number" name="target_policy_count" id="target_policy_count"
                                   value="<?php echo $editing ? esc_attr($edit_rep->target_policy_count ?? '') : ""; ?>">
                            <p class="form-tip">Temsilcinin aylık poliçe satış hedefi (adet)</p>
                        </div>
                    </div>
                    
                    <?php if ($editing): ?>
                    <div class="form-section">
                        <h3>Mevcut Performans</h3>
                        <div class="performance-summary" style="background: #f8f9fa; border-radius: 8px; padding: 20px;">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                                <div style="text-align: center;">
                                    <h4 style="margin: 0; color: #495057;">Müşteri Sayısı</h4>
                                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #28a745;">
                                        <?php 
                                        $customer_count = $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_customers WHERE representative_id = %d",
                                            $edit_rep->id
                                        )) ?: 0;
                                        echo number_format($customer_count);
                                        ?>
                                    </p>
                                </div>
                                <div style="text-align: center;">
                                    <h4 style="margin: 0; color: #495057;">Aktif Poliçe</h4>
                                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #007bff;">
                                        <?php 
                                        $policy_count = $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies 
                                             WHERE representative_id = %d AND cancellation_date IS NULL",
                                            $edit_rep->id
                                        )) ?: 0;
                                        echo number_format($policy_count);
                                        ?>
                                    </p>
                                </div>
                                <div style="text-align: center;">
                                    <h4 style="margin: 0; color: #495057;">Toplam Prim</h4>
                                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #fd7e14;">
                                        ₺<?php 
                                        $total_premium = $wpdb->get_var($wpdb->prepare(
                                            "SELECT SUM(policy_fee) FROM {$wpdb->prefix}insurance_crm_policies 
                                             WHERE representative_id = %d AND cancellation_date IS NULL",
                                            $edit_rep->id
                                        )) ?: 0;
                                        echo number_format($total_premium, 2, ',', '.');
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Role & Permissions Tab -->
            <div class="tab-content" id="role">
                <div class="form-section">
                    <h2>Rol ve Yetki Yönetimi</h2>
                    <p>Temsilcinin sistem içindeki rolünü ve yetkilerini belirleyin.</p>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="role">Rol <span class="required">*</span></label>
                            <select name="role" id="role" required>
                                <?php foreach ($role_definitions as $role_id => $role_name): ?>
                                <option value="<?php echo $role_id; ?>" 
                                        <?php echo ($editing && $edit_rep->role == $role_id) ? 'selected' : ''; ?>>
                                    <?php echo $role_name; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="form-tip">Rol, temsilcinin sistem içindeki yetki seviyesini belirler</p>
                        </div>
                    </div>
                    
                    <div class="permission-section">
                        <h3><i class="dashicons dashicons-admin-users"></i> Müşteri Yönetimi Yetkileri</h3>
                        <div class="permissions-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="customer_edit" value="1" 
                                       <?php echo ($editing && $edit_rep->customer_edit) ? 'checked' : 'checked'; ?>>
                                Müşteri Düzenleme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="customer_delete" value="1" 
                                       <?php echo ($editing && $edit_rep->customer_delete) ? 'checked' : ''; ?>>
                                Müşteri Silme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_change_customer_representative" value="1" 
                                       <?php echo ($editing && $edit_rep->can_change_customer_representative) ? 'checked' : ''; ?>>
                                Müşteri Temsilcisi Değiştirme
                            </label>
                        </div>
                    </div>
                    
                    <div class="permission-section">
                        <h3><i class="dashicons dashicons-media-document"></i> Poliçe Yönetimi Yetkileri</h3>
                        <div class="permissions-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="policy_edit" value="1" 
                                       <?php echo ($editing && $edit_rep->policy_edit) ? 'checked' : 'checked'; ?>>
                                Poliçe Düzenleme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="policy_delete" value="1" 
                                       <?php echo ($editing && $edit_rep->policy_delete) ? 'checked' : ''; ?>>
                                Poliçe Silme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_change_policy_representative" value="1" 
                                       <?php echo ($editing && $edit_rep->can_change_policy_representative) ? 'checked' : ''; ?>>
                                Poliçe Temsilcisi Değiştirme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_view_deleted_policies" value="1" 
                                       <?php echo ($editing && $edit_rep->can_view_deleted_policies) ? 'checked' : ''; ?>>
                                Silinmiş Poliçeleri Görüntüleme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_restore_deleted_policies" value="1" 
                                       <?php echo ($editing && $edit_rep->can_restore_deleted_policies) ? 'checked' : ''; ?>>
                                Silinmiş Poliçeleri Geri Getirme
                            </label>
                        </div>
                    </div>
                    
                    <div class="permission-section">
                        <h3><i class="dashicons dashicons-clipboard"></i> Görev ve Veri Yönetimi Yetkileri</h3>
                        <div class="permissions-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="task_edit" value="1" 
                                       <?php echo ($editing && $edit_rep->task_edit) ? 'checked' : 'checked'; ?>>
                                Görev Düzenleme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="can_change_task_representative" value="1" 
                                       <?php echo ($editing && $edit_rep->can_change_task_representative) ? 'checked' : ''; ?>>
                                Görev Temsilcisi Değiştirme
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="export_data" value="1" 
                                       <?php echo ($editing && $edit_rep->export_data) ? 'checked' : ''; ?>>
                                Veri Dışa Aktarma
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-content" id="security">
                <div class="form-section">
                    <h2>Güvenlik Ayarları</h2>
                    <p>Kullanıcının giriş bilgilerini ve güvenlik ayarlarını yönetin.</p>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password">Şifre</label>
                            <input type="password" name="password" id="password" <?php echo !$editing ? "required" : ""; ?>>
                            <p class="form-tip">
                                <?php echo $editing ? "Değiştirmek istemiyorsanız boş bırakın." : "En az 8 karakter uzunluğunda olmalıdır."; ?>
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Şifre (Tekrar)</label>
                            <input type="password" name="confirm_password" id="confirm_password" <?php echo !$editing ? "required" : ""; ?>>
                        </div>
                    </div>
                    
                    <?php if ($editing): ?>
                    <div class="form-section">
                        <h3>Güvenlik Bilgileri</h3>
                        <div style="background: #f8f9fa; border-radius: 8px; padding: 20px;">
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                <div>
                                    <h4 style="margin: 0 0 10px 0; color: #495057;">Son Giriş</h4>
                                    <p style="margin: 0; color: #6c757d;">
                                        <?php 
                                        $last_login = get_user_meta($edit_rep->user_id, 'last_login', true);
                                        if ($last_login) {
                                            echo date('d.m.Y H:i', strtotime($last_login));
                                        } else {
                                            echo 'Henüz giriş yapmamış';
                                        }
                                        ?>
                                    </p>
                                </div>
                                <div>
                                    <h4 style="margin: 0 0 10px 0; color: #495057;">Hesap Durumu</h4>
                                    <p style="margin: 0;">
                                        <span class="status-badge <?php echo $edit_rep->status; ?>">
                                            <?php echo $edit_rep->status === 'active' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="submit_representative" class="btn-modern btn-primary">
                    <i class="dashicons dashicons-saved"></i>
                    <?php echo $editing ? "Temsilciyi Güncelle" : "Müşteri Temsilcisi Ekle"; ?>
                </button>
                <a href="<?php echo admin_url("admin.php?page=insurance-crm-representatives"); ?>" 
                   class="btn-modern btn-secondary">
                    <i class="dashicons dashicons-arrow-left-alt"></i> İptal
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?><?php include 'insurance-crm-representatives-js.php'; ?>
