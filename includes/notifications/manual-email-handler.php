<?php
/**
 * Manual Email Handler for Boss Settings
 * 
 * @package    Insurance_CRM
 * @subpackage Insurance_CRM/includes/notifications
 * @author     Anadolu Birlik
 * @since      1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add AJAX handlers
add_action('wp_ajax_insurance_crm_send_manual_daily_emails', 'insurance_crm_handle_manual_daily_emails');

/**
 * Handle manual daily email sending via AJAX
 */
function insurance_crm_handle_manual_daily_emails() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'insurance_crm_manual_daily_emails')) {
        wp_die(json_encode(array(
            'success' => false,
            'data' => 'Güvenlik doğrulaması başarısız.'
        )));
    }
    
    // Check user permissions - only boss/managers can send manual emails
    if (!current_user_can('manage_options')) {
        wp_die(json_encode(array(
            'success' => false,
            'data' => 'Bu işlem için yetkiniz bulunmuyor.'
        )));
    }
    
    $email_type = sanitize_text_field($_POST['email_type']);
    
    try {
        // Include enhanced email notifications class if not already loaded
        if (!class_exists('Insurance_CRM_Enhanced_Email_Notifications')) {
            require_once(plugin_dir_path(__FILE__) . 'class-enhanced-email-notifications.php');
        }
        
        $email_notifications = new Insurance_CRM_Enhanced_Email_Notifications();
        $sent_count = 0;
        $error_count = 0;
        $messages = array();
        
        global $wpdb;
        
        if ($email_type === 'managers') {
            // Send to managers (roles 1, 2, 3)
            $managers = $wpdb->get_results(
                "SELECT r.user_id, u.display_name, u.user_email, r.role
                 FROM {$wpdb->prefix}insurance_crm_representatives r
                 JOIN {$wpdb->users} u ON r.user_id = u.ID
                 WHERE r.status = 'active' AND r.role IN (1, 2, 3)
                 ORDER BY r.role ASC"
            );
            
            foreach ($managers as $manager) {
                $result = $email_notifications->send_manager_daily_report($manager->user_id);
                if ($result) {
                    $sent_count++;
                    $messages[] = "✓ {$manager->display_name} ({$manager->user_email})";
                } else {
                    $error_count++;
                    $messages[] = "✗ {$manager->display_name} - Gönderim başarısız";
                }
            }
            
            $response_message = sprintf(
                "Yönetici e-postaları gönderildi.\n\n📊 Özet:\n• Başarılı: %d\n• Başarısız: %d\n\n📋 Detay:\n%s",
                $sent_count,
                $error_count,
                implode("\n", array_slice($messages, 0, 10))
            );
            
        } elseif ($email_type === 'representatives') {
            // Send to all representatives
            $representatives = $wpdb->get_results(
                "SELECT r.id, r.user_id, u.display_name, u.user_email
                 FROM {$wpdb->prefix}insurance_crm_representatives r
                 JOIN {$wpdb->users} u ON r.user_id = u.ID
                 WHERE r.status = 'active'
                 ORDER BY u.display_name ASC"
            );
            
            foreach ($representatives as $representative) {
                $result = $email_notifications->send_representative_daily_summary($representative->id);
                if ($result) {
                    $sent_count++;
                    $messages[] = "✓ {$representative->display_name} ({$representative->user_email})";
                } else {
                    // Check if user has disabled email notifications
                    $email_enabled = get_user_meta($representative->user_id, 'crm_email_notifications', true);
                    $daily_enabled = get_user_meta($representative->user_id, 'crm_daily_email_notifications', true);
                    
                    if (!$email_enabled || !$daily_enabled) {
                        $messages[] = "- {$representative->display_name} - E-posta bildirimleri kapalı";
                    } else {
                        $error_count++;
                        $messages[] = "✗ {$representative->display_name} - Gönderim başarısız";
                    }
                }
            }
            
            $response_message = sprintf(
                "Temsilci e-postaları gönderildi.\n\n📊 Özet:\n• Başarılı: %d\n• Başarısız: %d\n• Toplam Temsilci: %d\n\n📋 Detay:\n%s",
                $sent_count,
                $error_count,
                count($representatives),
                implode("\n", array_slice($messages, 0, 10))
            );
            
            if (count($messages) > 10) {
                $response_message .= "\n... ve " . (count($messages) - 10) . " daha.";
            }
        } else {
            throw new Exception('Geçersiz e-posta türü.');
        }
        
        // Log the manual send
        error_log("Manual daily emails sent - Type: {$email_type}, Sent: {$sent_count}, Errors: {$error_count}");
        
        wp_die(json_encode(array(
            'success' => true,
            'data' => $response_message
        )));
        
    } catch (Exception $e) {
        error_log('Manual daily email error: ' . $e->getMessage());
        wp_die(json_encode(array(
            'success' => false,
            'data' => 'E-posta gönderimi sırasında hata oluştu: ' . $e->getMessage()
        )));
    }
}

/**
 * Add notification methods to policy model
 */
function insurance_crm_send_new_policy_notification($policy_id) {
    $settings = get_option('insurance_crm_settings', array());
    
    // Check if new policy notifications are enabled
    if (!isset($settings['notification_settings']['new_policy_notifications']) || 
        !$settings['notification_settings']['new_policy_notifications']) {
        return;
    }
    
    global $wpdb;
    
    // Get policy data
    $policy = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, c.first_name, c.last_name, c.email as customer_email,
                r.display_name as representative_name
         FROM {$wpdb->prefix}insurance_crm_policies p
         LEFT JOIN {$wpdb->prefix}insurance_crm_customers c ON p.customer_id = c.id
         LEFT JOIN {$wpdb->prefix}users r ON p.representative_id = r.ID
         WHERE p.id = %d",
        $policy_id
    ));
    
    if (!$policy) {
        return;
    }
    
    $company_name = isset($settings['company_name']) ? $settings['company_name'] : get_bloginfo('name');
    $to_email = isset($settings['company_email']) ? $settings['company_email'] : get_option('admin_email');
    
    $subject = sprintf('[%s] Yeni Poliçe Eklendi: %s', $company_name, $policy->policy_number);
    
    $message = sprintf(
        "Yeni poliçe eklendi:\n\n" .
        "Poliçe Numarası: %s\n" .
        "Müşteri: %s %s\n" .
        "Poliçe Türü: %s\n" .
        "Sigorta Şirketi: %s\n" .
        "Başlangıç Tarihi: %s\n" .
        "Bitiş Tarihi: %s\n" .
        "Prim Tutarı: %s TL\n" .
        "Temsilci: %s\n\n" .
        "Poliçeyi görüntülemek için admin panelini ziyaret edin.",
        $policy->policy_number,
        $policy->first_name,
        $policy->last_name,
        $policy->policy_type,
        $policy->company,
        date('d.m.Y', strtotime($policy->start_date)),
        date('d.m.Y', strtotime($policy->end_date)),
        number_format($policy->premium_amount, 2),
        $policy->representative_name ?: 'Belirtilmemiş'
    );
    
    wp_mail($to_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
}

/**
 * Add notification methods to customer model
 */
function insurance_crm_send_new_customer_notification($customer_id) {
    $settings = get_option('insurance_crm_settings', array());
    
    // Check if new customer notifications are enabled
    if (!isset($settings['notification_settings']['new_customer_notifications']) || 
        !$settings['notification_settings']['new_customer_notifications']) {
        return;
    }
    
    global $wpdb;
    
    // Get customer data
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, r.display_name as representative_name
         FROM {$wpdb->prefix}insurance_crm_customers c
         LEFT JOIN {$wpdb->prefix}users r ON c.representative_id = r.ID
         WHERE c.id = %d",
        $customer_id
    ));
    
    if (!$customer) {
        return;
    }
    
    $company_name = isset($settings['company_name']) ? $settings['company_name'] : get_bloginfo('name');
    $to_email = isset($settings['company_email']) ? $settings['company_email'] : get_option('admin_email');
    
    $subject = sprintf('[%s] Yeni Müşteri Eklendi: %s %s', $company_name, $customer->first_name, $customer->last_name);
    
    $message = sprintf(
        "Yeni müşteri eklendi:\n\n" .
        "Ad Soyad: %s %s\n" .
        "E-posta: %s\n" .
        "Telefon: %s\n" .
        "TC Kimlik: %s\n" .
        "Adres: %s\n" .
        "Doğum Tarihi: %s\n" .
        "Meslek: %s\n" .
        "Temsilci: %s\n" .
        "Kayıt Tarihi: %s\n\n" .
        "Müşteriyi görüntülemek için admin panelini ziyaret edin.",
        $customer->first_name,
        $customer->last_name,
        $customer->email ?: 'Belirtilmemiş',
        $customer->phone ?: 'Belirtilmemiş',
        $customer->tc_identity ?: 'Belirtilmemiş',
        $customer->address ?: 'Belirtilmemiş',
        $customer->birth_date ? date('d.m.Y', strtotime($customer->birth_date)) : 'Belirtilmemiş',
        $customer->occupation ?: 'Belirtilmemiş',
        $customer->representative_name ?: 'Belirtilmemiş',
        date('d.m.Y H:i', strtotime($customer->created_at))
    );
    
    wp_mail($to_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
}

/**
 * Enhanced task notification
 */
function insurance_crm_send_new_task_notification($task_id) {
    $settings = get_option('insurance_crm_settings', array());
    
    // Check if new task notifications are enabled
    if (!isset($settings['notification_settings']['new_task_notifications']) || 
        !$settings['notification_settings']['new_task_notifications']) {
        return;
    }
    
    global $wpdb;
    
    // Get task data
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, c.first_name, c.last_name, c.email as customer_email,
                r.display_name as representative_name, p.policy_number
         FROM {$wpdb->prefix}insurance_crm_tasks t
         LEFT JOIN {$wpdb->prefix}insurance_crm_customers c ON t.customer_id = c.id
         LEFT JOIN {$wpdb->prefix}users r ON t.representative_id = r.ID
         LEFT JOIN {$wpdb->prefix}insurance_crm_policies p ON t.policy_id = p.id
         WHERE t.id = %d",
        $task_id
    ));
    
    if (!$task) {
        return;
    }
    
    $company_name = isset($settings['company_name']) ? $settings['company_name'] : get_bloginfo('name');
    $to_email = isset($settings['company_email']) ? $settings['company_email'] : get_option('admin_email');
    
    $subject = sprintf('[%s] Yeni Görev Eklendi: %s', $company_name, wp_trim_words($task->task_description, 5));
    
    $message = sprintf(
        "Yeni görev eklendi:\n\n" .
        "Görev: %s\n" .
        "Müşteri: %s %s\n" .
        "Poliçe: %s\n" .
        "Son Tarih: %s\n" .
        "Öncelik: %s\n" .
        "Durum: %s\n" .
        "Temsilci: %s\n" .
        "Oluşturma Tarihi: %s\n\n" .
        "Görevi görüntülemek için admin panelini ziyaret edin.",
        $task->task_description,
        $task->first_name ?: 'Belirtilmemiş',
        $task->last_name ?: '',
        $task->policy_number ?: 'Belirtilmemiş',
        $task->due_date ? date('d.m.Y H:i', strtotime($task->due_date)) : 'Belirtilmemiş',
        $task->priority ?: 'Normal',
        $task->status ?: 'Bekliyor',
        $task->representative_name ?: 'Belirtilmemiş',
        date('d.m.Y H:i', strtotime($task->created_at))
    );
    
    wp_mail($to_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
}