<?php
/**
 * Manager Daily Report Email Template
 * 
 * Available variables:
 * - {manager_name}
 * - {today_date}, {today_day}
 * - {total_pending_tasks}, {total_expiring_policies}
 * - {total_active_representatives}
 * - {system_stats} (array)
 * - {critical_alerts} (array)
 * - {representative_performance} (array)
 * - {pending_tasks_by_rep} (array)
 * - {expiring_policies_by_rep} (array)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="email-section">
    <h2>📈 Günlük Sistem Raporu</h2>
    <p style="font-size: 16px; margin-bottom: 25px;">
        <strong>{manager_name}</strong> için <strong>{today_day}, {today_date}</strong> sistem durumu ve özet bilgiler.
    </p>
</div>

<!-- System Overview -->
<div class="info-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 25px;">
    <h3 style="color: white; margin-bottom: 15px;">🏢 Sistem Genel Bakış</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px;">
        <div style="text-align: center;">
            <div style="font-size: 28px; font-weight: bold;">
                <?php echo isset($variables['system_stats']['total_policies']) ? $variables['system_stats']['total_policies'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Aktif Poliçe</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 28px; font-weight: bold;">
                <?php echo isset($variables['system_stats']['total_customers']) ? $variables['system_stats']['total_customers'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Toplam Müşteri</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 28px; font-weight: bold;">{total_active_representatives}</div>
            <div style="font-size: 12px; opacity: 0.9;">Aktif Temsilci</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 28px; font-weight: bold;">
                <?php echo isset($variables['system_stats']['policies_this_month']) ? $variables['system_stats']['policies_this_month'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Bu Ay Poliçe</div>
        </div>
    </div>
</div>

<!-- Critical Alerts -->
<?php if (!empty($variables['critical_alerts'])): ?>
<div class="info-card" style="border-left: 4px solid #e74c3c; background-color: #fff5f5;">
    <h3 style="color: #e74c3c;">⚠️ Kritik Uyarılar</h3>
    <?php foreach ($variables['critical_alerts'] as $alert): ?>
        <div style="background-color: #fee; border: 1px solid #fcc; padding: 10px; margin-bottom: 10px; border-radius: 4px;">
            <strong style="color: #c33;">🚨 <?php echo esc_html($alert); ?></strong>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="info-card" style="border-left: 4px solid #27ae60; background-color: #f0fff4;">
    <h3 style="color: #27ae60;">✅ Sistem Durumu</h3>
    <p style="color: #27ae60; text-align: center; padding: 15px;">
        Kritik uyarı bulunmuyor. Sistem normal çalışıyor.
    </p>
</div>
<?php endif; ?>

<!-- Overdue Tasks Alert -->
<?php if (isset($variables['system_stats']['overdue_tasks']) && $variables['system_stats']['overdue_tasks'] > 0): ?>
<div class="info-card" style="border-left: 4px solid #dc3545; background-color: #f8d7da;">
    <h3 style="color: #dc3545;">⏰ Gecikmiş Görevler</h3>
    <p style="color: #721c24; font-size: 16px; font-weight: bold;">
        Toplam <?php echo $variables['system_stats']['overdue_tasks']; ?> adet gecikmiş görev bulunmaktadır!
    </p>
    <p style="color: #856404; font-size: 14px;">
        Bu görevlerin acilen takip edilmesi önerilir.
    </p>
</div>
<?php endif; ?>

<!-- Today's Priority Tasks -->
<div class="info-card">
    <h3 style="color: #dc3545;">🎯 Bugün ve Yakın Zamanda Tamamlanması Gereken İşler</h3>
    <p style="color: #666; margin-bottom: 15px;">Bugün ve önümüzdeki 3 gün içinde tamamlanması planlanan görevler:</p>
    
    <?php if (!empty($variables['all_pending_tasks'])): ?>
        <?php foreach (array_slice($variables['all_pending_tasks'], 0, 8) as $task): ?>
            <div class="info-row" style="border-left: 3px solid #dc3545; padding-left: 10px; margin-bottom: 8px;">
                <div>
                    <strong><?php echo esc_html($task->task_description); ?></strong>
                    <br>
                    <span style="color: #666; font-size: 14px;">
                        Müşteri: <?php echo esc_html($task->first_name . ' ' . $task->last_name); ?> | 
                        Temsilci: <?php echo esc_html($task->rep_first_name . ' ' . $task->rep_last_name); ?>
                    </span>
                    <br>
                    <small style="color: #dc3545; font-weight: bold;">
                        Son Tarih: <?php echo date('d.m.Y H:i', strtotime($task->due_date)); ?>
                        <?php 
                        $hours_left = (strtotime($task->due_date) - time()) / 3600;
                        if ($hours_left < 0) {
                            echo " (GECİKMİŞ!)";
                        } elseif ($hours_left < 24) {
                            echo " (" . round($hours_left) . " saat kaldı)";
                        } else {
                            echo " (" . ceil($hours_left / 24) . " gün kaldı)";
                        }
                        ?>
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($variables['all_pending_tasks']) > 8): ?>
            <p style="text-align: center; color: #666; font-style: italic; margin-top: 15px;">
                ... ve <?php echo count($variables['all_pending_tasks']) - 8; ?> adet daha
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="color: #28a745; text-align: center; padding: 20px;">
            ✅ Yakın zamanda tamamlanması gereken kritik görev bulunmuyor.
        </p>
    <?php endif; ?>
</div>

<!-- Expiring Policies -->
<div class="info-card">
    <h3 style="color: #f39c12;">🔄 Yaklaşan Poliçe Yenilemeleri (30 Gün İçinde)</h3>
    
    <?php if (!empty($variables['all_expiring_policies'])): ?>
        <?php foreach (array_slice($variables['all_expiring_policies'], 0, 10) as $policy): ?>
            <div class="info-row" style="border-left: 3px solid #f39c12; padding-left: 10px; margin-bottom: 8px;">
                <div>
                    <strong><?php echo esc_html($policy->policy_number); ?></strong> - <?php echo esc_html($policy->policy_type); ?>
                    <br>
                    <span style="color: #666; font-size: 14px;">
                        Müşteri: <?php echo esc_html($policy->first_name . ' ' . $policy->last_name); ?> | 
                        Temsilci: <?php echo esc_html($policy->rep_first_name . ' ' . $policy->rep_last_name); ?>
                    </span>
                    <br>
                    <small style="color: #f39c12; font-weight: bold;">
                        Bitiş: <?php echo date('d.m.Y', strtotime($policy->end_date)); ?>
                        <?php 
                        $days_left = ceil((strtotime($policy->end_date) - time()) / (60 * 60 * 24));
                        echo " ({$days_left} gün kaldı)";
                        if ($days_left <= 7) {
                            echo " ⚠️";
                        }
                        ?>
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($variables['all_expiring_policies']) > 10): ?>
            <p style="text-align: center; color: #666; font-style: italic; margin-top: 15px;">
                ... ve <?php echo count($variables['all_expiring_policies']) - 10; ?> adet daha
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="color: #28a745; text-align: center; padding: 20px;">
            ✅ Önümüzdeki 30 gün içinde yenilenecek poliçe bulunmuyor.
        </p>
    <?php endif; ?>
</div>

<!-- Representative Performance Summary -->
<?php if (!empty($variables['representative_performance'])): ?>
<div class="info-card">
    <h3 style="color: #6f42c1;">👥 Temsilci Performans Özeti</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Temsilci</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px; text-align: center;">Aktif Poliçe</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px; text-align: center;">Bekleyen Görev</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px; text-align: center;">Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($variables['representative_performance'], 0, 10) as $rep): ?>
                    <tr>
                        <td style="border: 1px solid #dee2e6; padding: 8px;">
                            <?php echo esc_html($rep->first_name . ' ' . $rep->last_name); ?>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px; text-align: center;">
                            <span style="color: #28a745; font-weight: bold;"><?php echo $rep->policy_count; ?></span>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px; text-align: center;">
                            <span style="color: <?php echo $rep->pending_task_count > 5 ? '#dc3545' : '#6c757d'; ?>; font-weight: bold;">
                                <?php echo $rep->pending_task_count; ?>
                            </span>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px; text-align: center;">
                            <?php if ($rep->pending_task_count > 10): ?>
                                <span style="color: #dc3545;">⚠️ Yoğun</span>
                            <?php elseif ($rep->pending_task_count > 5): ?>
                                <span style="color: #ffc107;">⚡ Normal</span>
                            <?php else: ?>
                                <span style="color: #28a745;">✅ İyi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($variables['representative_performance']) > 10): ?>
            <p style="text-align: center; color: #666; font-style: italic; margin-top: 10px;">
                ... ve <?php echo count($variables['representative_performance']) - 10; ?> temsilci daha
            </p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Tasks by Representative -->
<?php if (!empty($variables['pending_tasks_by_rep'])): ?>
<div class="info-card">
    <h3 style="color: #17a2b8;">📋 Temsilcilere Göre Bekleyen Görevler</h3>
    <?php foreach (array_slice($variables['pending_tasks_by_rep'], 0, 8) as $rep): ?>
        <div class="info-row">
            <span class="info-label"><?php echo esc_html($rep->first_name . ' ' . $rep->last_name); ?>:</span>
            <span class="info-value" style="color: <?php echo $rep->task_count > 10 ? '#dc3545' : '#6c757d'; ?>; font-weight: bold;">
                <?php echo $rep->task_count; ?> görev
                <?php if ($rep->task_count > 10): ?>
                    ⚠️
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Quick Action Links -->
<div class="info-card" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <h3 style="color: white; margin-bottom: 20px;">🚀 Yönetim Paneli Erişim</h3>
    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <a href="<?php echo admin_url('admin.php?page=insurance-crm-dashboard'); ?>" 
           class="button" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3);">
            📊 Ana Panel
        </a>
        <a href="<?php echo admin_url('admin.php?page=insurance-crm-tasks'); ?>" 
           class="button" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3);">
            📋 Tüm Görevler
        </a>
        <a href="<?php echo admin_url('admin.php?page=insurance-crm-policies'); ?>" 
           class="button" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3);">
            📄 Poliçeler
        </a>
        <a href="<?php echo admin_url('admin.php?page=insurance-crm-reports'); ?>" 
           class="button" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3);">
            📈 Raporlar
        </a>
    </div>
</div>

<!-- Summary Footer -->
<div style="text-align: center; margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
    <h4 style="color: #495057; margin-bottom: 15px;">📋 Günlük Özet</h4>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 15px;">
        <div>
            <strong style="color: #dc3545;">{total_pending_tasks}</strong>
            <br><small>Bekleyen Görev</small>
        </div>
        <div>
            <strong style="color: #f39c12;">{total_expiring_policies}</strong>
            <br><small>Yenilenecek Poliçe</small>
        </div>
        <div>
            <strong style="color: #28a745;">{total_active_representatives}</strong>
            <br><small>Aktif Temsilci</small>
        </div>
    </div>
    <p style="font-size: 14px; color: #6c757d; margin: 0;">
        Başarılı bir gün geçirin ve ekibinizi yönlendirin! 💼
    </p>
</div>