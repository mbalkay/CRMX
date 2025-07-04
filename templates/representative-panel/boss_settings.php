<?php
/**
 * Yönetim Ayarları Sayfası
 * 
 * @package    Insurance_CRM
 * @subpackage Insurance_CRM/templates/representative-panel
 * @author     Anadolu Birlik
 * @since      1.0.0
 * @version    1.0.1 (2025-05-28)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    wp_safe_redirect(home_url('/temsilci-girisi/'));
    exit;
}

$current_user = wp_get_current_user();
global $wpdb;

/**
 * Helper functions to check user-based permissions
 */
if (!function_exists('check_user_permission')) {
    function check_user_permission($permission, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        
        // Get user's role and individual permissions
        $rep = $wpdb->get_row($wpdb->prepare(
            "SELECT role, {$permission} FROM {$wpdb->prefix}insurance_crm_representatives WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        if (!$rep) {
            return false;
        }
        
        $role_id = intval($rep->role);
        
        // Patron (role 1) and Müdür (role 2) have all permissions
        if ($role_id === 1 || $role_id === 2) {
            return true;
        }
        
        // For other roles, check individual user permission
        $permission_value = isset($rep->$permission) ? intval($rep->$permission) : 0;
        
        return $permission_value === 1;
    }
}

if (!function_exists('can_change_customer_representative')) {
    function can_change_customer_representative($user_id = null) {
        return check_user_permission('can_change_customer_representative', $user_id);
    }
}

if (!function_exists('can_change_policy_representative')) {
    function can_change_policy_representative($user_id = null) {
        return check_user_permission('can_change_policy_representative', $user_id);
    }
}

if (!function_exists('can_change_task_representative')) {
    function can_change_task_representative($user_id = null) {
        return check_user_permission('can_change_task_representative', $user_id);
    }
}

if (!function_exists('can_delete_policy_permission')) {
    function can_delete_policy_permission($user_id = null) {
        return check_user_permission('policy_delete', $user_id);
    }
}

if (!function_exists('can_view_deleted_policies')) {
    function can_view_deleted_policies($user_id = null) {
        return check_user_permission('can_view_deleted_policies', $user_id);
    }
}

if (!function_exists('can_restore_deleted_policies')) {
    function can_restore_deleted_policies($user_id = null) {
        return check_user_permission('can_restore_deleted_policies', $user_id);
    }
}

if (!function_exists('can_delete_customer')) {
    function can_delete_customer($user_id = null) {
        return check_user_permission('customer_delete', $user_id);
    }
}

if (!function_exists('can_view_deleted_customers')) {
    function can_view_deleted_customers($user_id = null) {
        return check_user_permission('customer_delete', $user_id);
    }
}

if (!function_exists('can_export_data')) {
    function can_export_data($user_id = null) {
        // Check if export_data column exists, fallback to customer_edit
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}insurance_crm_representatives LIKE 'export_data'");
        
        if (!empty($columns)) {
            return check_user_permission('export_data', $user_id);
        } else {
            // Fallback to customer_edit permission
            return check_user_permission('customer_edit', $user_id);
        }
    }
}

// Yetki kontrolü - patron ve müdür ayarlara erişebilir
if (!has_full_admin_access($current_user->ID)) {
    wp_die('Bu sayfaya erişim yetkiniz bulunmuyor.');
}

// Mevcut ayarları al
$settings = get_option('insurance_crm_settings', array());

// Varsayılan değerler
if (!isset($settings['company_name'])) {
    $settings['company_name'] = get_bloginfo('name');
}
if (!isset($settings['company_email'])) {
    $settings['company_email'] = get_bloginfo('admin_email');
}
if (!isset($settings['renewal_reminder_days'])) {
    $settings['renewal_reminder_days'] = 30;
}
if (!isset($settings['task_reminder_days'])) {
    $settings['task_reminder_days'] = 7;
}
if (!isset($settings['default_policy_types'])) {
    $settings['default_policy_types'] = array('Kasko', 'Trafik', 'Konut', 'DASK', 'Sağlık');
}
if (!isset($settings['insurance_companies'])) {
    $settings['insurance_companies'] = array('Allianz', 'Anadolu Sigorta', 'AXA', 'Axa Sigorta', 'Acıbadem', 'Ankara Sigorta', 'Groupama', 'Güneş Sigorta', 'HDI', 'Mapfre', 'Sompo Japan', 'Türkiye Sigorta');
}
if (!isset($settings['default_task_types'])) {
    $settings['default_task_types'] = array('Telefon Görüşmesi', 'Yüz Yüze Görüşme', 'Teklif Hazırlama', 'Evrak İmza', 'Dosya Takibi');
}
if (!isset($settings['payment_options'])) {
    $settings['payment_options'] = array('Peşin', '3 Taksit', '6 Taksit', '8 Taksit', '9 Taksit', '12 Taksit', 'Ödenmedi', 'Nakit', 'Kredi Kartı', 'Havale', 'Diğer');
}
if (!isset($settings['occupation_settings']['default_occupations'])) {
    $settings['occupation_settings']['default_occupations'] = array('Doktor', 'Mühendis', 'Öğretmen', 'Avukat', 'Muhasebeci', 'İşçi', 'Memur', 'Emekli');
}

// Default values for update announcements system
if (!isset($settings['update_announcements'])) {
    $settings['update_announcements'] = array(
        'enabled' => false,
        'title' => 'Sistem Güncellemeleri',
        'content' => '<h3><i class="fas fa-rocket"></i> Kullanıcı Bazlı Yetki Sistemi</h3>
<p>Artık her temsilci için ayrı ayrı yetki tanımlanabilir. Patron ve Müdür rolleri tüm yetkiler sahipken, diğer kullanıcılar için bireysel yetkiler atanabilir.</p>
<ul>
<li><strong>Müşteri Düzenleme/Silme Yetkileri</strong></li>
<li><strong>Poliçe Düzenleme/Silme Yetkileri</strong></li>
<li><strong>Görev Düzenleme Yetkileri</strong></li>
<li><strong>Veri Dışa Aktarma Yetkileri</strong></li>
<li><strong>Toplu İşlem Yetkileri</strong></li>
</ul>
<h3><i class="fas fa-cog"></i> Yönetici Duyuru Sistemi</h3>
<p>Yöneticiler artık ayarlar bölümünden güncelleme duyuruları yayınlayabilir ve tüm kullanıcılara otomatik olarak gösterebilir.</p>',
        'version' => '1.9.5',
        'show_to_all' => false,
        'last_updated' => current_time('mysql')
    );
}

// Form gönderildiğinde
if (isset($_POST['submit_settings']) && isset($_POST['settings_nonce']) && 
    wp_verify_nonce($_POST['settings_nonce'], 'save_settings')) {
    
    $tab = isset($_POST['active_tab']) ? $_POST['active_tab'] : 'general';
    $error_messages = array();
    $success_message = '';
    
    // Genel ayarlar
    if ($tab === 'general') {
        $settings['company_name'] = sanitize_text_field($_POST['company_name']);
        $settings['company_email'] = sanitize_email($_POST['company_email']);
        $settings['renewal_reminder_days'] = intval($_POST['renewal_reminder_days']);
        $settings['task_reminder_days'] = intval($_POST['task_reminder_days']);
    }
    // Poliçe türleri
    elseif ($tab === 'policy_types') {
        $settings['default_policy_types'] = array_map('sanitize_text_field', explode("\n", trim($_POST['default_policy_types'])));
    }
    // Sigorta şirketleri
    elseif ($tab === 'insurance_companies') {
        $settings['insurance_companies'] = array_map('sanitize_text_field', explode("\n", trim($_POST['insurance_companies'])));
    }
    // Görev türleri
    elseif ($tab === 'task_types') {
        $settings['default_task_types'] = array_map('sanitize_text_field', explode("\n", trim($_POST['default_task_types'])));
    }
    // Ödeme bilgileri
    elseif ($tab === 'payment_info') {
        $settings['payment_options'] = array_map('sanitize_text_field', explode("\n", trim($_POST['payment_options'])));
    }
    // Bildirim ayarları
    elseif ($tab === 'notifications') {
        $settings['notification_settings']['email_notifications'] = isset($_POST['email_notifications']);
        $settings['notification_settings']['renewal_notifications'] = isset($_POST['renewal_notifications']);
        $settings['notification_settings']['task_notifications'] = isset($_POST['task_notifications']);
        $settings['notification_settings']['new_policy_notifications'] = isset($_POST['new_policy_notifications']);
        $settings['notification_settings']['new_customer_notifications'] = isset($_POST['new_customer_notifications']);
        $settings['notification_settings']['new_task_notifications'] = isset($_POST['new_task_notifications']);
    }
    // E-posta şablonları
    elseif ($tab === 'email_templates') {
        $settings['email_templates']['renewal_reminder'] = wp_kses_post($_POST['renewal_reminder_template']);
        $settings['email_templates']['task_reminder'] = wp_kses_post($_POST['task_reminder_template']);
        $settings['email_templates']['new_policy'] = wp_kses_post($_POST['new_policy_template']);
    }
    // Site görünümü
    elseif ($tab === 'site_appearance') {
        // Handle file upload for logo
        if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
            
            if (in_array($_FILES['logo_upload']['type'], $allowed_types)) {
                $filename = 'logo_' . time() . '_' . sanitize_file_name($_FILES['logo_upload']['name']);
                $file_path = $upload_dir['path'] . '/' . $filename;
                
                if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $file_path)) {
                    $settings['site_appearance']['login_logo'] = $upload_dir['url'] . '/' . $filename;
                } else {
                    $error_messages[] = 'Logo yüklenirken bir hata oluştu.';
                }
            } else {
                $error_messages[] = 'Sadece JPG, PNG ve GIF dosyaları yükleyebilirsiniz.';
            }
        } else {
            $settings['site_appearance']['login_logo'] = esc_url_raw($_POST['login_logo']);
        }
        
        $settings['site_appearance']['font_family'] = sanitize_text_field($_POST['font_family']);
        $settings['site_appearance']['primary_color'] = sanitize_hex_color($_POST['primary_color']);
        $settings['site_appearance']['secondary_color'] = sanitize_hex_color($_POST['secondary_color']);
        $settings['site_appearance']['sidebar_color'] = sanitize_hex_color($_POST['sidebar_color']);
    }
    // Dosya yükleme ayarları
    elseif ($tab === 'file_upload') {
        $settings['file_upload_settings']['allowed_file_types'] = isset($_POST['allowed_file_types']) ? array_map('sanitize_text_field', $_POST['allowed_file_types']) : array();
    }
    // Meslekler
    elseif ($tab === 'occupations') {
        $settings['occupation_settings']['default_occupations'] = array_map('sanitize_text_field', explode("\n", trim($_POST['default_occupations'])));
    }
    // Güncelleme Duyuruları
    elseif ($tab === 'updates') {
        $settings['update_announcements']['enabled'] = isset($_POST['enable_announcements']);
        $settings['update_announcements']['title'] = sanitize_text_field($_POST['announcement_title']);
        $settings['update_announcements']['content'] = wp_kses_post($_POST['announcement_content']);
        $settings['update_announcements']['version'] = sanitize_text_field($_POST['announcement_version']);
        $settings['update_announcements']['show_to_all'] = isset($_POST['show_to_all']);
        $settings['update_announcements']['last_updated'] = current_time('mysql');
    }
    
    // Ayarları kaydet
    update_option('insurance_crm_settings', $settings);
    $success_message = 'Ayarlar başarıyla kaydedildi.';
}

// Aktif sekme
$active_tab = isset($_POST['active_tab']) ? $_POST['active_tab'] : 'general';

// Statistics for the modern cards
$total_settings = 10; // Number of setting categories (added payment_info)
$total_companies = count($settings['insurance_companies']);
$total_policy_types = count($settings['default_policy_types']);
$total_task_types = count($settings['default_task_types']);
?>

<div class="modern-settings-container">
    <!-- Modern Header -->
    <div class="page-header-modern">
        <div class="header-main">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-cog"></i> Yönetim Ayarları</h1>
                </div>
                <div class="header-right">
                    <a href="<?php echo generate_panel_url('all_personnel'); ?>" class="btn-modern btn-primary">
                        <i class="fas fa-users"></i> Personel Yönetimi
                    </a>
                </div>
            </div>
        </div>
        <div class="header-subtitle-section">
            <p class="header-subtitle">Sistem ayarlarını yapılandırın ve özelleştirin</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fas fa-cogs"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_settings; ?></h3>
                <p>Ayar Kategorisi</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #5ee7df 0%, #66a6ff 100%);">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_companies; ?></h3>
                <p>Sigorta Şirketi</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_policy_types; ?></h3>
                <p>Poliçe Türü</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_task_types; ?></h3>
                <p>Görev Türü</p>
            </div>
        </div>
    </div>
    
    <?php if (!empty($error_messages)): ?>
        <div class="modern-message-box error-box">
            <i class="fas fa-exclamation-circle"></i>
            <div class="message-content">
                <h4>Hata</h4>
                <ul>
                    <?php foreach ($error_messages as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="modern-message-box success-box">
            <i class="fas fa-check-circle"></i>
            <div class="message-content">
                <h4>Başarılı</h4>
                <p><?php echo esc_html($success_message); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="modern-settings-container-content">
        <div class="modern-settings-sidebar">
            <div class="sidebar-header-modern">
                <h3><i class="fas fa-sliders-h"></i> Ayar Kategorileri</h3>
            </div>
            <ul class="modern-settings-menu">
                <li class="<?php echo $active_tab === 'general' ? 'active' : ''; ?>" data-tab="general">
                    <i class="fas fa-home"></i> Genel Ayarlar
                </li>
                <li class="<?php echo $active_tab === 'policy_types' ? 'active' : ''; ?>" data-tab="policy_types">
                    <i class="fas fa-file-invoice"></i> Poliçe Türleri
                </li>
                <li class="<?php echo $active_tab === 'insurance_companies' ? 'active' : ''; ?>" data-tab="insurance_companies">
                    <i class="fas fa-building"></i> Sigorta Şirketleri
                </li>
                <li class="<?php echo $active_tab === 'task_types' ? 'active' : ''; ?>" data-tab="task_types">
                    <i class="fas fa-tasks"></i> Görev Türleri
                </li>
                <li class="<?php echo $active_tab === 'payment_info' ? 'active' : ''; ?>" data-tab="payment_info">
                    <i class="fas fa-credit-card"></i> Ödeme Bilgileri
                </li>
                <li class="<?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" data-tab="notifications">
                    <i class="fas fa-bell"></i> Bildirim Ayarları
                </li>
                <li class="<?php echo $active_tab === 'email_templates' ? 'active' : ''; ?>" data-tab="email_templates">
                    <i class="fas fa-envelope"></i> E-posta Şablonları
                </li>
                <li class="<?php echo $active_tab === 'site_appearance' ? 'active' : ''; ?>" data-tab="site_appearance">
                    <i class="fas fa-paint-brush"></i> Site Görünümü
                </li>
                <li class="<?php echo $active_tab === 'file_upload' ? 'active' : ''; ?>" data-tab="file_upload">
                    <i class="fas fa-cloud-upload-alt"></i> Dosya Yükleme
                </li>
                <li class="<?php echo $active_tab === 'occupations' ? 'active' : ''; ?>" data-tab="occupations">
                    <i class="fas fa-briefcase"></i> Meslekler
                </li>
                <li class="<?php echo $active_tab === 'updates' ? 'active' : ''; ?>" data-tab="updates">
                    <i class="fas fa-bullhorn"></i> Güncelleme Duyuruları
                </li>
            </ul>
        </div>
        
        <div class="modern-settings-content">
            <form method="post" action="" class="modern-settings-form" enctype="multipart/form-data">
                <?php wp_nonce_field('save_settings', 'settings_nonce'); ?>
                <input type="hidden" name="active_tab" id="active_tab" value="<?php echo esc_attr($active_tab); ?>">
                
                <!-- Genel Ayarlar -->
                <div class="settings-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>" id="general-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-home"></i> Genel Ayarlar</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="company_name">Şirket Adı</label>
                            <input type="text" name="company_name" id="company_name" class="form-control" 
                                  value="<?php echo esc_attr($settings['company_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="company_email">Şirket E-posta</label>
                            <input type="email" name="company_email" id="company_email" class="form-control" 
                                  value="<?php echo esc_attr($settings['company_email']); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="renewal_reminder_days">Yenileme Hatırlatma (Gün)</label>
                                <input type="number" name="renewal_reminder_days" id="renewal_reminder_days" class="form-control" 
                                      value="<?php echo esc_attr($settings['renewal_reminder_days']); ?>" min="1" max="90">
                                <div class="form-hint">Poliçe yenileme hatırlatması için kaç gün önceden bildirim gönderilsin?</div>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="task_reminder_days">Görev Hatırlatma (Gün)</label>
                                <input type="number" name="task_reminder_days" id="task_reminder_days" class="form-control" 
                                      value="<?php echo esc_attr($settings['task_reminder_days']); ?>" min="1" max="30">
                                <div class="form-hint">Görev hatırlatması için kaç gün önceden bildirim gönderilsin?</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Poliçe Türleri -->
                <div class="settings-tab <?php echo $active_tab === 'policy_types' ? 'active' : ''; ?>" id="policy_types-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-file-invoice"></i> Poliçe Türleri</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="default_policy_types">Varsayılan Poliçe Türleri</label>
                            <textarea name="default_policy_types" id="default_policy_types" class="form-control" rows="10"><?php echo esc_textarea(implode("\n", $settings['default_policy_types'])); ?></textarea>
                            <div class="form-hint">Her satıra bir poliçe türü yazın. Bu liste poliçe formlarında seçenek olarak sunulacaktır.</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Sigorta Şirketleri -->
                <div class="settings-tab <?php echo $active_tab === 'insurance_companies' ? 'active' : ''; ?>" id="insurance_companies-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-building"></i> Sigorta Şirketleri</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="insurance_companies">Sigorta Firmaları Listesi</label>
                            <textarea name="insurance_companies" id="insurance_companies" class="form-control" rows="10"><?php echo esc_textarea(implode("\n", $settings['insurance_companies'])); ?></textarea>
                            <div class="form-hint">Her satıra bir sigorta firması adı yazın. Bu liste poliçe formlarında seçenek olarak sunulacaktır.</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Görev Türleri -->
                <div class="settings-tab <?php echo $active_tab === 'task_types' ? 'active' : ''; ?>" id="task_types-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-tasks"></i> Görev Türleri</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="default_task_types">Varsayılan Görev Türleri</label>
                            <textarea name="default_task_types" id="default_task_types" class="form-control" rows="10"><?php echo esc_textarea(implode("\n", $settings['default_task_types'])); ?></textarea>
                            <div class="form-hint">Her satıra bir görev türü yazın. Bu liste görev formlarında seçenek olarak sunulacaktır.</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Ödeme Bilgileri -->
                <div class="settings-tab <?php echo $active_tab === 'payment_info' ? 'active' : ''; ?>" id="payment_info-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-credit-card"></i> Ödeme Bilgileri</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="payment_options">Ödeme Seçenekleri</label>
                            <textarea name="payment_options" id="payment_options" class="form-control" rows="12"><?php echo esc_textarea(implode("\n", $settings['payment_options'])); ?></textarea>
                            <div class="form-hint">Her satıra bir ödeme seçeneği yazın. Bu liste poliçe formlarında ödeme bilgisi olarak sunulacaktır. Örnek: Peşin, 3 Taksit, 6 Taksit, 8 Taksit, 9 Taksit, 12 Taksit, Ödenmedi, Nakit, Kredi Kartı, Havale, Diğer</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Bildirim Ayarları -->
                <div class="settings-tab <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" id="notifications-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-bell"></i> Bildirim Ayarları</h2>
                        <p>Sistem bildirimleri ve otomatik e-posta gönderimlerini yapılandırın</p>
                    </div>
                    <div class="tab-content">
                        <div class="permission-section">
                            <h3><i class="fas fa-envelope"></i> Genel E-posta Bildirimleri</h3>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="email_notifications" id="email_notifications" 
                                           <?php checked(isset($settings['notification_settings']['email_notifications']) ? $settings['notification_settings']['email_notifications'] : false); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>E-posta bildirimlerini etkinleştir</strong>
                                        <p>Sistem bildirimleri e-posta yoluyla da gönderilir.</p>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="renewal_notifications" id="renewal_notifications"
                                           <?php checked(isset($settings['notification_settings']['renewal_notifications']) ? $settings['notification_settings']['renewal_notifications'] : false); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>Poliçe yenileme bildirimlerini etkinleştir</strong>
                                        <p>Poliçe yenilemeleri için bildirim gönderilir.</p>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="task_notifications" id="task_notifications"
                                           <?php checked(isset($settings['notification_settings']['task_notifications']) ? $settings['notification_settings']['task_notifications'] : false); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>Görev bildirimlerini etkinleştir</strong>
                                        <p>Görevler için bildirim gönderilir.</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="permission-section">
                            <h3><i class="fas fa-plus-circle"></i> Yeni Kayıt Bildirimleri</h3>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="new_policy_notifications" id="new_policy_notifications"
                                           <?php checked(isset($settings['notification_settings']['new_policy_notifications']) ? $settings['notification_settings']['new_policy_notifications'] : false); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>Yeni poliçe eklendiğinde e-posta gönder</strong>
                                        <p>Yeni poliçe oluşturulduğunda yöneticilere bildirim e-postası gönderilir.</p>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="new_customer_notifications" id="new_customer_notifications"
                                           <?php checked(isset($settings['notification_settings']['new_customer_notifications']) ? $settings['notification_settings']['new_customer_notifications'] : false); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>Yeni müşteri eklendiğinde e-posta gönder</strong>
                                        <p>Yeni müşteri kaydedildiğinde yöneticilere bildirim e-postası gönderilir.</p>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="new_task_notifications" id="new_task_notifications"
                                           <?php checked(isset($settings['notification_settings']['new_task_notifications']) ? $settings['notification_settings']['new_task_notifications'] : true); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>Yeni görev eklendiğinde e-posta gönder</strong>
                                        <p>Yeni görev oluşturulduğunda yöneticilere bildirim e-postası gönderilir.</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="permission-section">
                            <h3><i class="fas fa-paper-plane"></i> Manuel Günlük E-posta Gönderimi</h3>
                            <p class="section-description">
                                Günlük hatırlatma e-postalarını manuel olarak göndermek için bu butonları kullanın.
                            </p>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <button type="button" id="send-manager-emails" class="btn-modern btn-save" style="width: 100%;">
                                        <i class="fas fa-user-tie"></i> Yöneticilere Gönder
                                    </button>
                                    <div class="form-hint">Patron ve Müdürlere günlük özet e-postası gönderir</div>
                                    <div id="manager-email-result" class="email-result"></div>
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <button type="button" id="send-representative-emails" class="btn-modern btn-secondary" style="width: 100%;">
                                        <i class="fas fa-users"></i> Temsilcilere Gönder
                                    </button>
                                    <div class="form-hint">Tüm temsilcilere günlük özet e-postası gönderir</div>
                                    <div id="representative-email-result" class="email-result"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- E-posta Şablonları -->
                <div class="settings-tab <?php echo $active_tab === 'email_templates' ? 'active' : ''; ?>" id="email_templates-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-envelope"></i> E-posta Şablonları</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="renewal_reminder_template">Yenileme Hatırlatma Şablonu</label>
                            <textarea name="renewal_reminder_template" id="renewal_reminder_template" class="form-control" rows="8"><?php 
                                echo isset($settings['email_templates']['renewal_reminder']) ? esc_textarea($settings['email_templates']['renewal_reminder']) : ''; 
                            ?></textarea>
                            <div class="form-hint">
                                Kullanılabilir değişkenler: {customer_name}, {policy_number}, {policy_type}, {end_date}, {premium_amount}
                            </div>
                            <div class="form-actions" style="margin-top: 10px;">
                                <button type="button" class="btn btn-secondary test-template-btn" data-template="renewal_reminder">
                                    <i class="fas fa-paper-plane"></i> Test E-postası Gönder
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_reminder_template">Görev Hatırlatma Şablonu</label>
                            <textarea name="task_reminder_template" id="task_reminder_template" class="form-control" rows="8"><?php 
                                echo isset($settings['email_templates']['task_reminder']) ? esc_textarea($settings['email_templates']['task_reminder']) : ''; 
                            ?></textarea>
                            <div class="form-hint">
                                Kullanılabilir değişkenler: {customer_name}, {task_description}, {due_date}, {priority}
                            </div>
                            <div class="form-actions" style="margin-top: 10px;">
                                <button type="button" class="btn btn-secondary test-template-btn" data-template="task_reminder">
                                    <i class="fas fa-paper-plane"></i> Test E-postası Gönder
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_policy_template">Yeni Poliçe Bildirimi</label>
                            <textarea name="new_policy_template" id="new_policy_template" class="form-control" rows="8"><?php 
                                echo isset($settings['email_templates']['new_policy']) ? esc_textarea($settings['email_templates']['new_policy']) : ''; 
                            ?></textarea>
                            <div class="form-hint">
                                Kullanılabilir değişkenler: {customer_name}, {policy_number}, {policy_type}, {start_date}, {end_date}, {premium_amount}
                            </div>
                            <div class="form-actions" style="margin-top: 10px;">
                                <button type="button" class="btn btn-secondary test-template-btn" data-template="new_policy">
                                    <i class="fas fa-paper-plane"></i> Test E-postası Gönder
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Site Görünümü -->
                <div class="settings-tab <?php echo $active_tab === 'site_appearance' ? 'active' : ''; ?>" id="site_appearance-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-paint-brush"></i> Site Görünümü</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="login_logo">Giriş Paneli Logo</label>
                            <div class="logo-upload-section">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="logo_upload">Logo Dosyası Yükle</label>
                                        <input type="file" name="logo_upload" id="logo_upload" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif">
                                        <div class="form-hint">JPG, PNG veya GIF formatında maksimum 2MB dosya yükleyebilirsiniz.</div>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="login_logo">Veya Logo URL'si</label>
                                        <input type="text" name="login_logo" id="login_logo" class="form-control" 
                                              value="<?php echo esc_attr(isset($settings['site_appearance']['login_logo']) ? $settings['site_appearance']['login_logo'] : ''); ?>">
                                        <div class="form-hint">Giriş sayfasında görüntülenecek logo URL'si.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($settings['site_appearance']['login_logo'])): ?>
                                <div class="logo-preview" style="margin-top: 15px;">
                                    <strong>Mevcut Logo Önizleme:</strong><br>
                                    <img src="<?php echo esc_url($settings['site_appearance']['login_logo']); ?>" alt="Logo Önizleme" style="max-height: 100px; border: 1px solid #ddd; padding: 5px; border-radius: 5px; margin-top: 10px;">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="font_family">Font Ailesi</label>
                                <input type="text" name="font_family" id="font_family" class="form-control" 
                                       value="<?php echo esc_attr(isset($settings['site_appearance']['font_family']) ? $settings['site_appearance']['font_family'] : 'Arial, sans-serif'); ?>">
                                <div class="form-hint">Örnek: "Arial, sans-serif" veya "Open Sans, sans-serif"</div>
                            </div>
                            
                            <div class="form-group col-md-4">
                                <label for="primary_color">Ana Renk</label>
                                <div class="color-picker-container">
                                    <input type="color" name="primary_color" id="primary_color" class="color-picker" 
                                           value="<?php echo esc_attr(isset($settings['site_appearance']['primary_color']) ? $settings['site_appearance']['primary_color'] : '#3498db'); ?>">
                                    <span class="color-value"><?php echo esc_attr(isset($settings['site_appearance']['primary_color']) ? $settings['site_appearance']['primary_color'] : '#3498db'); ?></span>
                                </div>
                                <div class="form-hint">Giriş paneli, butonlar ve firma adı için ana renk.</div>
                            </div>
                            
                            <div class="form-group col-md-4">
                                <label for="secondary_color">İkinci Ana Renk</label>
                                <div class="color-picker-container">
                                    <input type="color" name="secondary_color" id="secondary_color" class="color-picker" 
                                           value="<?php echo esc_attr(isset($settings['site_appearance']['secondary_color']) ? $settings['site_appearance']['secondary_color'] : '#ffd93d'); ?>">
                                    <span class="color-value"><?php echo esc_attr(isset($settings['site_appearance']['secondary_color']) ? $settings['site_appearance']['secondary_color'] : '#ffd93d'); ?></span>
                                </div>
                                <div class="form-hint">Doğum günü tablosu ve özel paneller için ikinci ana renk.</div>
                            </div>
                            
                            <div class="form-group col-md-4">
                                <label for="sidebar_color">Sol Menü Rengi</label>
                                <div class="color-picker-container">
                                    <input type="color" name="sidebar_color" id="sidebar_color" class="color-picker" 
                                           value="<?php echo esc_attr(isset($settings['site_appearance']['sidebar_color']) ? $settings['site_appearance']['sidebar_color'] : '#2c3e50'); ?>">
                                    <span class="color-value"><?php echo esc_attr(isset($settings['site_appearance']['sidebar_color']) ? $settings['site_appearance']['sidebar_color'] : '#2c3e50'); ?></span>
                                </div>
                                <div class="form-hint">Sol menü ve yan panel için ana renk.</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Dosya Yükleme Ayarları -->
                <div class="settings-tab <?php echo $active_tab === 'file_upload' ? 'active' : ''; ?>" id="file_upload-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-cloud-upload-alt"></i> Dosya Yükleme Ayarları</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label>İzin Verilen Dosya Formatları</label>
                            <div class="file-types-grid">
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="jpg" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('jpg', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">JPEG Resim (.jpg)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="jpeg" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('jpeg', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">JPEG Resim (.jpeg)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="png" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('png', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">PNG Resim (.png)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="pdf" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('pdf', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">PDF Dokümanı (.pdf)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="doc" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('doc', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">Word Dokümanı (.doc)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="docx" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('docx', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">Word Dokümanı (.docx)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="xls" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('xls', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">Excel Tablosu (.xls)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="xlsx" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('xlsx', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">Excel Tablosu (.xlsx)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="txt" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('txt', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">Metin Dosyası (.txt)</span>
                                    </label>
                                </div>
                                
                                <div class="file-type-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allowed_file_types[]" value="zip" 
                                               <?php checked(isset($settings['file_upload_settings']['allowed_file_types']) && in_array('zip', $settings['file_upload_settings']['allowed_file_types'])); ?>>
                                        <span class="checkbox-text">Arşiv Dosyası (.zip)</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-hint">Sistem içinde yüklenebilecek dosya türlerini seçin. Seçili olmayan dosya türleri yüklenemez.</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Meslekler -->
                <div class="settings-tab <?php echo $active_tab === 'occupations' ? 'active' : ''; ?>" id="occupations-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-briefcase"></i> Meslekler</h2>
                    </div>
                    <div class="tab-content">
                        <div class="form-group">
                            <label for="default_occupations">Varsayılan Meslekler</label>
                            <textarea name="default_occupations" id="default_occupations" class="form-control" rows="10"><?php echo esc_textarea(implode("\n", $settings['occupation_settings']['default_occupations'])); ?></textarea>
                            <div class="form-hint">Her satıra bir meslek adı yazın. Bu liste müşteri formlarında seçenek olarak sunulacaktır.</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn-modern btn-save">
                                <i class="fas fa-save"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Güncelleme Duyuruları -->
                <div class="settings-tab <?php echo $active_tab === 'updates' ? 'active' : ''; ?>" id="updates-tab">
                    <div class="tab-header">
                        <h2><i class="fas fa-bullhorn"></i> Güncelleme Duyuruları</h2>
                        <p>Kullanıcılara gösterilecek güncellemeler ve duyuruları yönetin.</p>
                    </div>
                    <div class="tab-content">
                        <div class="update-section">
                            <h3><i class="fas fa-toggle-on"></i> Duyuru Ayarları</h3>
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="enable_announcements" value="1" 
                                           <?php checked(isset($settings['update_announcements']['enabled']) && $settings['update_announcements']['enabled']); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>Güncelleme Duyurularını Etkinleştir</strong>
                                        <p>Bu seçenek aktif edildiğinde, kullanıcılar giriş yaptıklarında güncelleme bildirimi görecekler.</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="update-content-section">
                            <h3><i class="fas fa-edit"></i> Duyuru İçeriği</h3>
                            
                            <div class="form-group">
                                <label for="announcement_title">Duyuru Başlığı</label>
                                <input type="text" name="announcement_title" id="announcement_title" class="form-control" 
                                       value="<?php echo esc_attr(isset($settings['update_announcements']['title']) ? $settings['update_announcements']['title'] : ''); ?>"
                                       placeholder="Örn: Yeni Özellikler ve Güncellemeler">
                                <div class="form-hint">Popup penceresinde görünecek başlık</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="announcement_version">Versiyon</label>
                                <input type="text" name="announcement_version" id="announcement_version" class="form-control" 
                                       value="<?php echo esc_attr(isset($settings['update_announcements']['version']) ? $settings['update_announcements']['version'] : '1.9.5'); ?>"
                                       placeholder="Örn: 1.8.3">
                                <div class="form-hint">Bu versiyonu daha önce gören kullanıcılara tekrar gösterilmeyecek</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="announcement_content">Duyuru İçeriği</label>
                                <div class="content-editor-toolbar">
                                    <button type="button" class="format-btn" onclick="insertFormatting('h3', 'Başlık')">
                                        <i class="fas fa-heading"></i> Başlık
                                    </button>
                                    <button type="button" class="format-btn" onclick="insertFormatting('strong', 'Kalın Yazı')">
                                        <i class="fas fa-bold"></i> Kalın
                                    </button>
                                    <button type="button" class="format-btn" onclick="insertFormatting('p', 'Paragraf')">
                                        <i class="fas fa-paragraph"></i> Paragraf
                                    </button>
                                    <button type="button" class="format-btn" onclick="insertList()">
                                        <i class="fas fa-list"></i> Liste
                                    </button>
                                </div>
                                <textarea name="announcement_content" id="announcement_content" class="form-control content-editor" rows="12" placeholder="Yeni özellikler ve güncellemeler hakkında bilgi verin..."><?php 
                                    echo isset($settings['update_announcements']['content']) ? esc_textarea($settings['update_announcements']['content']) : ''; 
                                ?></textarea>
                                <div class="form-hint">
                                    <strong>💡 İpucu:</strong> Yukarıdaki butonları kullanarak kolayca biçimlendirme ekleyebilirsiniz.
                                    <br>• Emoji kullanabilirsiniz: 🎉 ✨ 🚀 ⚡ 📊 🔧
                                    <br>• Basit HTML etiketleri desteklenir
                                </div>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="show_to_all" value="1" 
                                           <?php checked(isset($settings['update_announcements']['show_to_all']) && $settings['update_announcements']['show_to_all']); ?>>
                                    <span class="checkmark"></span>
                                    <div class="checkbox-content">
                                        <strong>Tüm Kullanıcılara Göster</strong>
                                        <p>Bu seçenek aktif edildiğinde, duyuru tüm kullanıcılara gösterilir. Pasif ise sadece yeni kullanıcılara gösterilir.</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <?php if (isset($settings['update_announcements']['last_updated'])): ?>
                        <div class="update-info-section">
                            <h3><i class="fas fa-info-circle"></i> Son Güncelleme Bilgileri</h3>
                            <p><strong>Son Güncelleme:</strong> <?php echo date('d.m.Y H:i', strtotime($settings['update_announcements']['last_updated'])); ?></p>
                            <p><strong>Aktif Versiyon:</strong> <?php echo esc_html($settings['update_announcements']['version'] ?? 'Belirtilmemiş'); ?></p>
                            <p><strong>Durum:</strong> 
                                <?php if (isset($settings['update_announcements']['enabled']) && $settings['update_announcements']['enabled']): ?>
                                    <span style="color: #28a745; font-weight: bold;">Aktif</span>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-weight: bold;">Pasif</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_settings" class="btn-modern btn-save">
                                <i class="fas fa-save"></i> Duyuru Ayarlarını Kaydet
                            </button>
                            <button type="button" class="btn-modern btn-preview" onclick="previewAnnouncement()">
                                <i class="fas fa-eye"></i> Önizleme <span class="preview-indicator">Canlı</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Modern Settings Container */
.modern-settings-container {
    padding: 30px;
    background-color: #f8f9fa;
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Modern Header */
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    margin-bottom: 30px;
    color: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.header-main {
    padding: 40px 40px 20px 40px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left h1 {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-subtitle-section {
    padding: 0 40px 30px 40px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    margin-top: 20px;
    padding-top: 20px;
}

.header-subtitle {
    font-size: 18px;
    opacity: 0.9;
    margin: 0;
    line-height: 1.4;
}

/* Modern Buttons */
.btn-modern {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: white;
    color: #667eea;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.btn-save {
    background: #667eea;
    color: white;
}

.btn-save:hover {
    background: #5a67d8;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #e2e8f0;
    color: #64748b;
}

.btn-secondary:hover {
    background: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(203, 213, 225, 0.3);
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-content h3 {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    color: #1e293b;
}

.stat-content p {
    font-size: 14px;
    color: #64748b;
    margin: 5px 0 0 0;
}

/* Modern Message Boxes */
.modern-message-box {
    display: flex;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    animation: fadeIn 0.3s ease;
}

.modern-message-box.error-box {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
}

.modern-message-box.success-box {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
}

.modern-message-box i {
    margin-right: 15px;
    font-size: 20px;
    margin-top: 2px;
}

.message-content h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
}

.message-content ul {
    margin: 0;
    padding-left: 20px;
}

.message-content p {
    margin: 0;
}

/* Modern Settings Content */
.modern-settings-container-content {
    display: flex;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.modern-settings-sidebar {
    width: 280px;
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.sidebar-header-modern {
    padding: 25px;
    border-bottom: 1px solid #e2e8f0;
    background: white;
}

.sidebar-header-modern h3 {
    margin: 0;
    font-size: 16px;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.modern-settings-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.modern-settings-menu li {
    padding: 15px 25px;
    font-size: 14px;
    cursor: pointer;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.modern-settings-menu li i {
    width: 20px;
    text-align: center;
    color: #64748b;
}

.modern-settings-menu li:hover {
    background: #f1f5f9;
    padding-left: 30px;
}

.modern-settings-menu li.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
}

.modern-settings-menu li.active i {
    color: white;
}

.modern-settings-content {
    flex: 1;
    padding: 30px;
    max-height: 800px;
    overflow-y: auto;
}

.settings-tab {
    display: none;
}

.settings-tab.active {
    display: block;
}

.tab-header {
    margin-bottom: 25px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 15px;
}

.tab-header h2 {
    margin: 0;
    font-size: 24px;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 700;
}

.tab-content {
    background: #f8fafc;
    border-radius: 10px;
    padding: 25px;
}

/* Form Styling */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.form-control, .form-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background-color: white;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-hint {
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
    font-style: italic;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

/* Form Layout */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.col-md-6 {
    flex: 1;
}

.col-md-4 {
    flex: 1;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
}

/* Button Styling */
.btn {
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 10px 16px;
    font-size: 14px;
    line-height: 1.5;
    border-radius: 8px;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
    text-decoration: none;
}

.btn i {
    margin-right: 8px;
}

.btn-primary {
    color: #fff;
    background-color: #667eea;
    border-color: #667eea;
}

.btn-primary:hover, .btn-primary:focus {
    background-color: #5a67d8;
    border-color: #5a67d8;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

/* Checkbox Styling */
.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #667eea;
}

.checkbox-item label {
    margin: 0;
    font-weight: 500;
    cursor: pointer;
}

/* Permission Settings Styling */
.permission-section {
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.permission-section h3 {
    margin: 0 0 20px 0;
    color: #495057;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.permission-section h3 i {
    color: #667eea;
}

.checkbox-label {
    display: block;
    cursor: pointer;
    position: relative;
    padding-left: 35px;
    margin: 0;
    line-height: 1.5;
}

.checkbox-label input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: absolute;
    top: 2px;
    left: 0;
    height: 20px;
    width: 20px;
    background-color: #ffffff;
    border: 2px solid #dee2e6;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.checkbox-label:hover input ~ .checkmark {
    border-color: #667eea;
}

.checkbox-label input:checked ~ .checkmark {
    background-color: #667eea;
    border-color: #667eea;
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.checkbox-label input:checked ~ .checkmark:after {
    display: block;
}

.checkbox-content {
    margin-top: 5px;
}

.checkbox-content strong {
    display: block;
    color: #495057;
    font-size: 16px;
    margin-bottom: 8px;
}

.checkbox-content p {
    margin: 0 0 8px 0;
    color: #6c757d;
    font-size: 14px;
    line-height: 1.5;
}

.checkbox-content .text-danger {
    color: #dc3545 !important;
    font-weight: 500;
}

/* File Types Grid */
.file-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

/* Color Picker Styling */
.color-picker-container {
    display: flex;
    align-items: center;
    gap: 15px;
}

.color-picker-container input[type="color"] {
    width: 50px;
    height: 50px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
}

.color-value {
    background: #f1f5f9;
    padding: 8px 12px;
    border-radius: 6px;
    font-family: monospace;
    font-weight: 600;
    color: #374151;
}

/* Role-based Permissions Styling */
.section-description {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 25px;
    line-height: 1.6;
}

.role-permissions-group {
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.role-permissions-group h4 {
    color: #495057;
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid #f8f9fa;
    padding-bottom: 10px;
}

.role-permissions-group h4 i {
    color: #6c757d;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.permission-desc {
    font-size: 12px !important;
    color: #6c757d !important;
    font-style: italic;
    margin-top: 5px !important;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-main {
        padding: 25px;
    }
    
    .header-subtitle-section {
        padding: 0 25px 25px 25px;
    }
    
    .modern-settings-container-content {
        flex-direction: column;
    }
    
    .modern-settings-sidebar {
        width: 100%;
    }
    
    .modern-settings-menu {
        display: flex;
        flex-wrap: wrap;
    }
    
    .modern-settings-menu li {
        flex: 0 0 50%;
        box-sizing: border-box;
    }
    
    .header-content {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .col-md-6 {
        flex: none;
    }
}

@media (max-width: 480px) {
    .modern-settings-container {
        padding: 20px;
    }
    
    .header-main {
        padding: 20px;
    }
    
    .header-subtitle-section {
        padding: 0 20px 20px 20px;
    }
    
    .header-left h1 {
        font-size: 24px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .modern-settings-menu li {
        flex: 0 0 100%;
    }
}

/* Content Editor Styling */
.content-editor-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.format-btn {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
    color: #495057;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

.format-btn:hover {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.format-btn i {
    font-size: 12px;
}

.content-editor {
    min-height: 300px !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
}

.preview-indicator {
    display: inline-block;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin-left: 10px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

/* Email Result Styling */
.email-result {
    margin-top: 10px;
    padding: 10px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    display: none;
}

.email-result.success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border: 1px solid #c3e6cb;
    display: block;
}

.email-result.error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border: 1px solid #f5c6cb;
    display: block;
}

.email-result.loading {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
    border: 1px solid #bee5eb;
    display: block;
}

.email-result i {
    margin-right: 8px;
}

/* Loading spinner for email buttons */
.btn-loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn-loading i {
    animation: fa-spin 1s infinite linear;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sekme değiştirme fonksiyonu
    const menuItems = document.querySelectorAll('.modern-settings-menu li');
    const tabs = document.querySelectorAll('.settings-tab');
    const activeTabInput = document.getElementById('active_tab');
    
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            const tabId = item.dataset.tab;
            
            // Aktif menü öğesini değiştir
            menuItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            
            // Aktif sekmeyi değiştir
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.id === tabId + '-tab') {
                    tab.classList.add('active');
                    activeTabInput.value = tabId;
                }
            });
        });
    });
    
    // Renk seçici değiştiğinde değeri güncelle
    const colorPicker = document.getElementById('primary_color');
    const colorValue = document.querySelector('.color-value');
    const sidebarColorPicker = document.getElementById('sidebar_color');
    const sidebarColorValues = document.querySelectorAll('.color-value');
    
    if (colorPicker && colorValue) {
        colorPicker.addEventListener('input', function() {
            colorValue.textContent = this.value;
        });
    }
    
    if (sidebarColorPicker && sidebarColorValues[1]) {
        sidebarColorPicker.addEventListener('input', function() {
            sidebarColorValues[1].textContent = this.value;
        });
    }
    
    // Test email functionality
    const testButtons = document.querySelectorAll('.test-template-btn');
    
    testButtons.forEach(button => {
        button.addEventListener('click', function() {
            const templateType = this.dataset.template;
            const originalText = this.innerHTML;
            
            // Disable button and show loading
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            
            // Prepare AJAX data
            const formData = new FormData();
            formData.append('action', 'insurance_crm_test_template_email');
            formData.append('nonce', '<?php echo wp_create_nonce('insurance_crm_test_template_email'); ?>');
            formData.append('template_type', templateType);
            
            // Send AJAX request
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                this.disabled = false;
                this.innerHTML = originalText;
                
                // Show result
                if (data.success) {
                    alert('✅ ' + data.data);
                } else {
                    alert('❌ ' + data.data);
                }
            })
            .catch(error => {
                // Reset button on error
                this.disabled = false;
                this.innerHTML = originalText;
                alert('❌ Bir hata oluştu: ' + error.message);
            });
        });
    });
    
    // Manual email send functionality
    const managerEmailBtn = document.getElementById('send-manager-emails');
    const representativeEmailBtn = document.getElementById('send-representative-emails');
    const managerResult = document.getElementById('manager-email-result');
    const representativeResult = document.getElementById('representative-email-result');
    
    if (managerEmailBtn) {
        managerEmailBtn.addEventListener('click', function() {
            sendManualEmails('managers', this, managerResult);
        });
    }
    
    if (representativeEmailBtn) {
        representativeEmailBtn.addEventListener('click', function() {
            sendManualEmails('representatives', this, representativeResult);
        });
    }
    
    function sendManualEmails(type, button, resultDiv) {
        // Disable button and show loading
        button.classList.add('btn-loading');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
        
        // Show loading result
        resultDiv.className = 'email-result loading';
        resultDiv.innerHTML = '<i class="fas fa-clock"></i> E-postalar gönderiliyor...';
        
        // Prepare AJAX data
        const formData = new FormData();
        formData.append('action', 'insurance_crm_send_manual_daily_emails');
        formData.append('nonce', '<?php echo wp_create_nonce('insurance_crm_manual_daily_emails'); ?>');
        formData.append('email_type', type);
        
        // Send AJAX request
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Reset button
            button.classList.remove('btn-loading');
            button.innerHTML = originalText;
            
            // Show result
            if (data.success) {
                resultDiv.className = 'email-result success';
                resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.data;
            } else {
                resultDiv.className = 'email-result error';
                resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.data;
            }
            
            // Hide result after 5 seconds
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 8000);
        })
        .catch(error => {
            // Reset button on error
            button.classList.remove('btn-loading');
            button.innerHTML = originalText;
            
            resultDiv.className = 'email-result error';
            resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Bir hata oluştu: ' + error.message;
            
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 8000);
        });
    }
});

// Announcement preview function
function previewAnnouncement() {
    const title = document.getElementById('announcement_title').value || 'Önizleme Başlığı';
    const content = document.getElementById('announcement_content').value || 'Önizleme içeriği...';
    const version = document.getElementById('announcement_version').value || '1.9.5';
    
    // Create modal HTML
    const modalHTML = `
        <div id="announcement-preview-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 20px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 20px 20px 0 0; text-align: center;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div></div>
                        <button onclick="closeAnnouncementPreview()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 50%; margin-right: 20px;">
                            <i class="fas fa-rocket" style="font-size: 28px;"></i>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-start;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <h2 style="margin: 0; font-size: 24px; font-weight: 600;">${title}</h2>
                                <div style="background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: 500;">
                                    v${version}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="padding: 30px;">
                    <div style="color: #4a5568; line-height: 1.6; font-size: 14px;">
                        ${content}
                    </div>
                    <div style="margin-top: 30px; text-align: center;">
                        <button onclick="closeAnnouncementPreview()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 30px; border-radius: 25px; font-size: 16px; font-weight: 500; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                            <i class="fas fa-check"></i> Anladım
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeAnnouncementPreview() {
    const modal = document.getElementById('announcement-preview-modal');
    if (modal) {
        modal.remove();
    }
}

// Content formatting functions
function insertFormatting(tag, defaultText) {
    const textarea = document.getElementById('announcement_content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const textToInsert = selectedText || defaultText;
    
    let formattedText;
    if (tag === 'h3') {
        formattedText = `<h3>${textToInsert}</h3>`;
    } else if (tag === 'strong') {
        formattedText = `<strong>${textToInsert}</strong>`;
    } else if (tag === 'p') {
        formattedText = `<p>${textToInsert}</p>`;
    }
    
    const newText = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
    textarea.value = newText;
    
    // Focus back on textarea
    textarea.focus();
    const newPos = start + formattedText.length;
    textarea.setSelectionRange(newPos, newPos);
}

function insertList() {
    const textarea = document.getElementById('announcement_content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    const listText = `<ul>
<li>Yeni özellik 1</li>
<li>Yeni özellik 2</li>
<li>Yeni özellik 3</li>
</ul>`;
    
    const newText = textarea.value.substring(0, start) + listText + textarea.value.substring(end);
    textarea.value = newText;
    
    textarea.focus();
    const newPos = start + listText.length;
    textarea.setSelectionRange(newPos, newPos);
}
</script>