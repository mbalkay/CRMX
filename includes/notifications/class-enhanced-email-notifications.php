<?php
/**
 * Enhanced Email Notifications
 * 
 * @package    Insurance_CRM
 * @subpackage Insurance_CRM/includes/notifications
 * @author     Anadolu Birlik
 * @since      1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Enhanced_Email_Notifications {

    /**
     * Send daily summary email to representative
     */
    public function send_representative_daily_summary($representative_id) {
        global $wpdb;
        
        // Get representative data
        $representative = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, u.user_email, u.display_name, u.user_login
             FROM {$wpdb->prefix}insurance_crm_representatives r
             JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.id = %d AND r.status = 'active'",
            $representative_id
        ));
        
        if (!$representative) {
            return false;
        }
        
        // Check if user wants daily email notifications
        $email_notifications = get_user_meta($representative->user_id, 'crm_email_notifications', true);
        $daily_emails = get_user_meta($representative->user_id, 'crm_daily_email_notifications', true);
        
        if (!$email_notifications || !$daily_emails) {
            return false; // User has disabled email notifications
        }
        
        // Get representative's customers and data
        $data = $this->get_representative_daily_data($representative->user_id);
        
        // Prepare email variables
        $variables = array(
            'representative_name' => $representative->display_name,
            'today_date' => date('d.m.Y'),
            'today_day' => $this->get_turkish_day_name(date('l')),
            'tasks_today_count' => count($data['tasks_today']),
            'tasks_upcoming_count' => count($data['tasks_upcoming']),
            'policies_expiring_count' => count($data['policies_expiring']),
            'active_quotes_count' => count($data['active_quotes']),
            'total_customers' => $data['total_customers'],
            'tasks_today' => $data['tasks_today'],
            'tasks_upcoming' => $data['tasks_upcoming'],
            'policies_expiring' => $data['policies_expiring'],
            'active_quotes' => $data['active_quotes'],
            'quick_stats' => $data['quick_stats'],
            'yesterday_stats' => $data['yesterday_stats'],
            'goal_tracking' => $data['goal_tracking']
        );
        
        // Get email template
        $this->current_variables = $variables; // Store for template access
        $template_content = $this->get_representative_email_template();
        
        // Send email
        $subject = '📊 Günlük Özet - ' . date('d.m.Y');
        
        return $this->send_template_email(
            $representative->user_email,
            $subject,
            $template_content,
            $variables
        );
    }
    
    /**
     * Send daily report email to managers
     */
    public function send_manager_daily_report($manager_user_id) {
        global $wpdb;
        
        // Get manager data
        $manager = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, u.user_email, u.display_name
             FROM {$wpdb->prefix}insurance_crm_representatives r
             JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.user_id = %d AND r.status = 'active' AND r.role IN (1, 2, 3)",
            $manager_user_id
        ));
        
        if (!$manager) {
            return false;
        }
        
        // Check if manager wants daily email notifications
        $email_notifications = get_user_meta($manager_user_id, 'crm_email_notifications', true);
        $daily_emails = get_user_meta($manager_user_id, 'crm_daily_email_notifications', true);
        
        if (!$email_notifications || !$daily_emails) {
            return false;
        }
        
        // Get system-wide data for managers
        $data = $this->get_manager_daily_data();
        
        // Prepare email variables
        $variables = array(
            'manager_name' => $manager->display_name,
            'today_date' => date('d.m.Y'),
            'today_day' => $this->get_turkish_day_name(date('l')),
            'total_pending_tasks' => count($data['all_pending_tasks']),
            'total_expiring_policies' => count($data['all_expiring_policies']),
            'total_active_representatives' => $data['total_active_representatives'],
            'system_stats' => $data['system_stats'],
            'critical_alerts' => $data['critical_alerts'],
            'representative_performance' => $data['representative_performance'],
            'yesterday_performance' => $data['yesterday_performance'],
            'pending_tasks_by_rep' => $data['pending_tasks_by_rep'],
            'expiring_policies_by_rep' => $data['expiring_policies_by_rep'],
            'all_pending_tasks' => $data['all_pending_tasks'],
            'all_expiring_policies' => $data['all_expiring_policies']
        );
        
        // Get email template
        $this->current_variables = $variables; // Store for template access
        $template_content = $this->get_manager_email_template();
        
        // Send email
        $subject = '📈 Yönetici Günlük Raporu - ' . date('d.m.Y');
        
        return $this->send_template_email(
            $manager->user_email,
            $subject,
            $template_content,
            $variables
        );
    }
    
    /**
     * Get representative's daily data
     */
    private function get_representative_daily_data($user_id) {
        global $wpdb;
        
        $data = array();
        
        // Today's tasks
        $data['tasks_today'] = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, c.first_name, c.last_name, p.policy_number
             FROM {$wpdb->prefix}insurance_crm_tasks t
             JOIN {$wpdb->prefix}insurance_crm_customers c ON t.customer_id = c.id
             LEFT JOIN {$wpdb->prefix}insurance_crm_policies p ON t.policy_id = p.id
             WHERE t.representative_id = (
                 SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                 WHERE user_id = %d AND status = 'active'
             )
             AND t.status = 'pending'
             AND DATE(t.due_date) = CURDATE()
             ORDER BY t.priority DESC, t.due_date ASC",
            $user_id
        ));
        
        // Upcoming tasks (next 7 days)
        $data['tasks_upcoming'] = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, c.first_name, c.last_name, p.policy_number
             FROM {$wpdb->prefix}insurance_crm_tasks t
             JOIN {$wpdb->prefix}insurance_crm_customers c ON t.customer_id = c.id
             LEFT JOIN {$wpdb->prefix}insurance_crm_policies p ON t.policy_id = p.id
             WHERE t.representative_id = (
                 SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                 WHERE user_id = %d AND status = 'active'
             )
             AND t.status = 'pending'
             AND DATE(t.due_date) > CURDATE()
             AND DATE(t.due_date) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             ORDER BY t.due_date ASC",
            $user_id
        ));
        
        // Expiring policies (next 7 days as requested)
        $data['policies_expiring'] = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, c.first_name, c.last_name, c.email, c.phone
             FROM {$wpdb->prefix}insurance_crm_policies p
             JOIN {$wpdb->prefix}insurance_crm_customers c ON p.customer_id = c.id
             WHERE p.representative_id = (
                 SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                 WHERE user_id = %d AND status = 'active'
             )
             AND p.status = 'aktif'
             AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             ORDER BY p.end_date ASC",
            $user_id
        ));
        
        // Active quotes (sample - adjust based on your quotes table structure)
        $data['active_quotes'] = array(); // Placeholder - implement based on your quotes system
        
        // Total customers count
        $data['total_customers'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_customers c
             WHERE c.representative_id = (
                 SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                 WHERE user_id = %d AND status = 'active'
             )",
            $user_id
        ));
        
        // Quick stats with financial data
        $data['quick_stats'] = array(
            'policies_this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies p
                 WHERE p.representative_id = (
                     SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                     WHERE user_id = %d AND status = 'active'
                 )
                 AND MONTH(p.created_at) = MONTH(CURDATE())
                 AND YEAR(p.created_at) = YEAR(CURDATE())",
                $user_id
            )),
            'completed_tasks_this_week' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_tasks t
                 WHERE t.representative_id = (
                     SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                     WHERE user_id = %d AND status = 'active'
                 )
                 AND t.status = 'completed'
                 AND WEEK(t.updated_at) = WEEK(CURDATE())
                 AND YEAR(t.updated_at) = YEAR(CURDATE())",
                $user_id
            ))
        );
        
        // Yesterday's performance data
        $data['yesterday_stats'] = array(
            'new_customers' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_customers c
                 WHERE c.representative_id = (
                     SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                     WHERE user_id = %d AND status = 'active'
                 )
                 AND DATE(c.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                $user_id
            )),
            'sold_policies' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies p
                 WHERE p.representative_id = (
                     SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                     WHERE user_id = %d AND status = 'active'
                 )
                 AND DATE(p.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                $user_id
            )),
            'total_premium' => $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(p.premium_amount), 0) FROM {$wpdb->prefix}insurance_crm_policies p
                 WHERE p.representative_id = (
                     SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                     WHERE user_id = %d AND status = 'active'
                 )
                 AND DATE(p.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                $user_id
            ))
        );
        
        // Monthly goal tracking
        $rep_data = $wpdb->get_row($wpdb->prepare(
            "SELECT monthly_target, minimum_policy_count, minimum_premium_amount 
             FROM {$wpdb->prefix}insurance_crm_representatives 
             WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        $data['goal_tracking'] = array(
            'monthly_target' => $rep_data ? $rep_data->monthly_target : 0,
            'min_policy_count' => $rep_data ? $rep_data->minimum_policy_count : 10,
            'min_premium_amount' => $rep_data ? $rep_data->minimum_premium_amount : 300000,
            'current_month_policies' => $data['quick_stats']['policies_this_month'],
            'current_month_premium' => $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(p.premium_amount), 0) FROM {$wpdb->prefix}insurance_crm_policies p
                 WHERE p.representative_id = (
                     SELECT id FROM {$wpdb->prefix}insurance_crm_representatives 
                     WHERE user_id = %d AND status = 'active'
                 )
                 AND MONTH(p.created_at) = MONTH(CURDATE())
                 AND YEAR(p.created_at) = YEAR(CURDATE())",
                $user_id
            ))
        );
        
        // Calculate goal percentages
        if ($data['goal_tracking']['min_policy_count'] > 0) {
            $data['goal_tracking']['policy_goal_percentage'] = min(100, ($data['goal_tracking']['current_month_policies'] / $data['goal_tracking']['min_policy_count']) * 100);
        } else {
            $data['goal_tracking']['policy_goal_percentage'] = 0;
        }
        
        if ($data['goal_tracking']['min_premium_amount'] > 0) {
            $data['goal_tracking']['premium_goal_percentage'] = min(100, ($data['goal_tracking']['current_month_premium'] / $data['goal_tracking']['min_premium_amount']) * 100);
        } else {
            $data['goal_tracking']['premium_goal_percentage'] = 0;
        }
        
        return $data;
    }
    
    /**
     * Get manager's daily data
     */
    private function get_manager_daily_data() {
        global $wpdb;
        
        $data = array();
        
        // All pending tasks
        $data['all_pending_tasks'] = $wpdb->get_results(
            "SELECT t.*, c.first_name, c.last_name, r.first_name as rep_first_name, r.last_name as rep_last_name
             FROM {$wpdb->prefix}insurance_crm_tasks t
             JOIN {$wpdb->prefix}insurance_crm_customers c ON t.customer_id = c.id
             JOIN {$wpdb->prefix}insurance_crm_representatives r ON t.representative_id = r.id
             WHERE t.status = 'pending'
             AND DATE(t.due_date) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
             ORDER BY t.due_date ASC"
        );
        
        // All expiring policies
        $data['all_expiring_policies'] = $wpdb->get_results(
            "SELECT p.*, c.first_name, c.last_name, r.first_name as rep_first_name, r.last_name as rep_last_name
             FROM {$wpdb->prefix}insurance_crm_policies p
             JOIN {$wpdb->prefix}insurance_crm_customers c ON p.customer_id = c.id
             JOIN {$wpdb->prefix}insurance_crm_representatives r ON p.representative_id = r.id
             WHERE p.status = 'aktif'
             AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY p.end_date ASC"
        );
        
        // Active representatives count
        $data['total_active_representatives'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_representatives WHERE status = 'active'"
        );
        
        // System stats
        $data['system_stats'] = array(
            'total_policies' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies WHERE status = 'aktif'"),
            'total_customers' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_customers"),
            'policies_this_month' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies WHERE status = 'aktif' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"),
            'overdue_tasks' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_tasks WHERE status = 'pending' AND due_date < NOW()")
        );
        
        // Critical alerts
        $data['critical_alerts'] = array();
        
        if ($data['system_stats']['overdue_tasks'] > 0) {
            $data['critical_alerts'][] = $data['system_stats']['overdue_tasks'] . ' adet gecikmiş görev bulunmaktadır.';
        }
        
        $expiring_today = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}insurance_crm_policies WHERE status = 'aktif' AND DATE(end_date) = CURDATE()");
        if ($expiring_today > 0) {
            $data['critical_alerts'][] = $expiring_today . ' adet poliçe bugün sona eriyor.';
        }
        
        // Representative performance summary with financial data
        $data['representative_performance'] = $wpdb->get_results(
            "SELECT r.first_name, r.last_name, u.display_name, r.monthly_target, r.minimum_policy_count, r.minimum_premium_amount,
                    COALESCE(policy_counts.policy_count, 0) as policy_count,
                    COALESCE(task_counts.pending_task_count, 0) as pending_task_count,
                    COALESCE(policy_sums.total_premium, 0) as total_premium,
                    COALESCE(monthly_counts.monthly_policies, 0) as monthly_policies,
                    COALESCE(monthly_sums.monthly_premium, 0) as monthly_premium
             FROM {$wpdb->prefix}insurance_crm_representatives r
             JOIN {$wpdb->users} u ON r.user_id = u.ID
             LEFT JOIN (
                 SELECT representative_id, COUNT(*) as policy_count 
                 FROM {$wpdb->prefix}insurance_crm_policies 
                 WHERE status = 'aktif' 
                 GROUP BY representative_id
             ) policy_counts ON r.id = policy_counts.representative_id
             LEFT JOIN (
                 SELECT representative_id, COUNT(*) as pending_task_count 
                 FROM {$wpdb->prefix}insurance_crm_tasks 
                 WHERE status = 'pending' 
                 GROUP BY representative_id
             ) task_counts ON r.id = task_counts.representative_id
             LEFT JOIN (
                 SELECT representative_id, SUM(premium_amount) as total_premium 
                 FROM {$wpdb->prefix}insurance_crm_policies 
                 WHERE status = 'aktif' 
                 GROUP BY representative_id
             ) policy_sums ON r.id = policy_sums.representative_id
             LEFT JOIN (
                 SELECT representative_id, COUNT(*) as monthly_policies 
                 FROM {$wpdb->prefix}insurance_crm_policies 
                 WHERE status = 'aktif' 
                 AND MONTH(created_at) = MONTH(CURDATE()) 
                 AND YEAR(created_at) = YEAR(CURDATE())
                 GROUP BY representative_id
             ) monthly_counts ON r.id = monthly_counts.representative_id
             LEFT JOIN (
                 SELECT representative_id, SUM(premium_amount) as monthly_premium 
                 FROM {$wpdb->prefix}insurance_crm_policies 
                 WHERE status = 'aktif' 
                 AND MONTH(created_at) = MONTH(CURDATE()) 
                 AND YEAR(created_at) = YEAR(CURDATE())
                 GROUP BY representative_id
             ) monthly_sums ON r.id = monthly_sums.representative_id
             WHERE r.status = 'active'
             ORDER BY monthly_policies DESC, monthly_premium DESC"
        );
        
        // Yesterday's performance by representative
        $data['yesterday_performance'] = $wpdb->get_results(
            "SELECT r.first_name, r.last_name, u.display_name,
                    COALESCE(customer_counts.new_customers, 0) as new_customers,
                    COALESCE(policy_counts.sold_policies, 0) as sold_policies,
                    COALESCE(policy_sums.premium_total, 0) as premium_total
             FROM {$wpdb->prefix}insurance_crm_representatives r
             JOIN {$wpdb->users} u ON r.user_id = u.ID
             LEFT JOIN (
                 SELECT representative_id, COUNT(*) as new_customers 
                 FROM {$wpdb->prefix}insurance_crm_customers 
                 WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                 GROUP BY representative_id
             ) customer_counts ON r.id = customer_counts.representative_id
             LEFT JOIN (
                 SELECT representative_id, COUNT(*) as sold_policies 
                 FROM {$wpdb->prefix}insurance_crm_policies 
                 WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                 GROUP BY representative_id
             ) policy_counts ON r.id = policy_counts.representative_id
             LEFT JOIN (
                 SELECT representative_id, SUM(premium_amount) as premium_total 
                 FROM {$wpdb->prefix}insurance_crm_policies 
                 WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                 GROUP BY representative_id
             ) policy_sums ON r.id = policy_sums.representative_id
             WHERE r.status = 'active'
             ORDER BY r.first_name, r.last_name"
        );
        
        // Pending tasks by representative
        $data['pending_tasks_by_rep'] = $wpdb->get_results(
            "SELECT r.first_name, r.last_name, COUNT(t.id) as task_count
             FROM {$wpdb->prefix}insurance_crm_representatives r
             LEFT JOIN {$wpdb->prefix}insurance_crm_tasks t ON r.id = t.representative_id 
             WHERE r.status = 'active' AND t.status = 'pending'
             GROUP BY r.id
             HAVING task_count > 0
             ORDER BY task_count DESC"
        );
        
        // Expiring policies by representative  
        $data['expiring_policies_by_rep'] = $wpdb->get_results(
            "SELECT r.first_name, r.last_name, COUNT(p.id) as policy_count
             FROM {$wpdb->prefix}insurance_crm_representatives r
             LEFT JOIN {$wpdb->prefix}insurance_crm_policies p ON r.id = p.representative_id 
             WHERE r.status = 'active' AND p.status = 'aktif'
             AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             GROUP BY r.id
             HAVING policy_count > 0
             ORDER BY policy_count DESC"
        );
        
        return $data;
    }
    
    /**
     * Get representative email template
     */
    private function get_representative_email_template() {
        // Load template file
        $template_file = dirname(__FILE__) . '/email-templates/representative-daily-summary.php';
        if (file_exists($template_file)) {
            ob_start();
            // Make variables available to template
            $variables = isset($this->current_variables) ? $this->current_variables : array();
            include $template_file;
            return ob_get_clean();
        }
        
        // Fallback template
        return $this->get_default_representative_template();
    }
    
    /**
     * Get manager email template  
     */
    private function get_manager_email_template() {
        // Load template file
        $template_file = dirname(__FILE__) . '/email-templates/manager-daily-report.php';
        if (file_exists($template_file)) {
            ob_start();
            // Make variables available to template
            $variables = isset($this->current_variables) ? $this->current_variables : array();
            include $template_file;
            return ob_get_clean();
        }
        
        // Fallback template
        return $this->get_default_manager_template();
    }
    
    /**
     * Send template email
     */
    private function send_template_email($to, $subject, $template_content, $variables = array()) {
        // Include enhanced email base template for daily notifications
        require_once dirname(__FILE__) . '/email-templates/email-base-template.php';
        
        // Use enhanced base template for daily notifications
        $base_template = insurance_crm_get_daily_email_base_template();
        
        // Replace content in base template
        $email_html = str_replace('{email_content}', $template_content, $base_template);
        $email_html = str_replace('{email_subject}', esc_html($subject), $email_html);
        
        // Replace variables in final email
        foreach ($variables as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $email_html = str_replace('{' . $key . '}', esc_html($value), $email_html);
            }
        }
        
        // Set headers for HTML email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        );
        
        return wp_mail($to, $subject, $email_html, $headers);
    }
    
    /**
     * Default representative template
     */
    private function get_default_representative_template() {
        return '
        <div class="email-section">
            <h2>🌅 Günaydın {representative_name}!</h2>
            <p><strong>{today_day}, {today_date}</strong> günü için özet bilgileriniz:</p>
        </div>
        
        <div class="info-card">
            <h3>📋 Bugünkü Görevleriniz ({tasks_today_count})</h3>
            <!-- Tasks will be populated by template -->
        </div>
        
        <div class="info-card">
            <h3>🔄 Yaklaşan Poliçe Yenilemeleri ({policies_expiring_count})</h3>
            <!-- Policies will be populated by template -->
        </div>
        
        <div class="info-card">
            <h3>📊 Hızlı İstatistikler</h3>
            <div class="info-row">
                <span class="info-label">Toplam Müşteri:</span>
                <span class="info-value">{total_customers}</span>
            </div>
        </div>';
    }
    
    /**
     * Default manager template
     */
    private function get_default_manager_template() {
        return '
        <div class="email-section">
            <h2>📈 Günlük Sistem Raporu</h2>
            <p><strong>{manager_name}</strong> için <strong>{today_day}, {today_date}</strong> sistem özeti:</p>
        </div>
        
        <div class="info-card">
            <h3>⚠️ Kritik Durumlar</h3>
            <p>Toplam bekleyen görev: {total_pending_tasks}</p>
            <p>Süresi dolan poliçeler: {total_expiring_policies}</p>
        </div>
        
        <div class="info-card">
            <h3>📊 Sistem İstatistikleri</h3>
            <p>Aktif temsilci sayısı: {total_active_representatives}</p>
        </div>';
    }
    
    /**
     * Get Turkish day name
     */
    private function get_turkish_day_name($english_day) {
        $days = array(
            'Monday' => 'Pazartesi',
            'Tuesday' => 'Salı', 
            'Wednesday' => 'Çarşamba',
            'Thursday' => 'Perşembe',
            'Friday' => 'Cuma',
            'Saturday' => 'Cumartesi',
            'Sunday' => 'Pazar'
        );
        
        return isset($days[$english_day]) ? $days[$english_day] : $english_day;
    }
    
    /**
     * Format currency for display
     */
    private function format_currency($amount) {
        return number_format($amount, 2, ',', '.') . ' ₺';
    }
}