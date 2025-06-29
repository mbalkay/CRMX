<?php
/**
 * Manager Daily Report Email Template - Corporate Dashboard Style
 * 
 * Available variables:
 * - {manager_name}
 * - {today_date}, {today_day}
 * - {total_pending_tasks}, {total_expiring_policies}
 * - {total_active_representatives}
 * - {system_stats} (array)
 * - {critical_alerts} (array)
 * - {representative_performance} (array)
 * - {yesterday_performance} (array)
 * - {pending_tasks_by_rep} (array)
 * - {expiring_policies_by_rep} (array)
 * - {all_pending_tasks} (array)
 * - {all_expiring_policies} (array)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="email-section">
    <h2>Yönetici Günlük Raporu</h2>
    <p style="font-size: 16px; margin-bottom: 25px; color: #495057;">
        <strong>{manager_name}</strong> • <strong>{today_day}, {today_date}</strong>
    </p>
</div>

<!-- System Overview Dashboard -->
<div class="info-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 25px;">
    <h3 style="color: white; margin-bottom: 20px;">Sistem Genel Bakış</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                <?php echo isset($variables['system_stats']['total_policies']) ? $variables['system_stats']['total_policies'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Aktif Poliçe</div>
        </div>
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                <?php echo isset($variables['system_stats']['total_customers']) ? $variables['system_stats']['total_customers'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Toplam Müşteri</div>
        </div>
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">{total_active_representatives}</div>
            <div style="font-size: 12px; opacity: 0.9;">Aktif Temsilci</div>
        </div>
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                <?php echo isset($variables['system_stats']['policies_this_month']) ? $variables['system_stats']['policies_this_month'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Bu Ay Poliçe</div>
        </div>
    </div>
</div>

<!-- Critical Alerts -->
<?php if (!empty($variables['critical_alerts'])): ?>
<div class="info-card" style="border-left: 4px solid #dc3545; background-color: #fff5f5;">
    <h3 style="color: #dc3545; margin-bottom: 15px;">Kritik Uyarılar</h3>
    <?php foreach ($variables['critical_alerts'] as $alert): ?>
        <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; margin-bottom: 10px; border-radius: 4px; color: #721c24;">
            <strong><?php echo esc_html($alert); ?></strong>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="info-card" style="border-left: 4px solid #28a745; background-color: #f0fff4;">
    <h3 style="color: #28a745;">Sistem Durumu</h3>
    <div style="text-align: center; padding: 20px; color: #28a745;">
        <div style="font-size: 48px; margin-bottom: 10px;">✓</div>
        <div style="font-weight: 600;">Kritik uyarı bulunmuyor - Sistem normal çalışıyor</div>
    </div>
</div>
<?php endif; ?>

<!-- Yesterday's Performance Summary -->
<?php 
$filtered_yesterday = array();
if (!empty($variables['yesterday_performance'])) {
    foreach ($variables['yesterday_performance'] as $rep) {
        if ((isset($rep->new_customers) && $rep->new_customers > 0) || 
            (isset($rep->sold_policies) && $rep->sold_policies > 0) || 
            (isset($rep->premium_total) && $rep->premium_total > 0)) {
            $filtered_yesterday[] = $rep;
        }
    }
}
?>
<?php if (!empty($filtered_yesterday) && count($filtered_yesterday) > 0): ?>
<div class="info-card">
    <h3 style="color: #495057; margin-bottom: 20px;">Dünkü Temsilci Performansları</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: left; font-weight: 600;">Temsilci</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Yeni Müşteri</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Satılan Poliçe</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Üretim Tutarı</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered_yesterday as $rep): ?>
                    <tr>
                        <td style="border: 1px solid #dee2e6; padding: 12px; font-weight: 600;">
                            <?php 
                            $name = '';
                            if (!empty($rep->display_name)) {
                                $name = $rep->display_name;
                            } elseif (!empty($rep->first_name) || !empty($rep->last_name)) {
                                $name = trim($rep->first_name . ' ' . $rep->last_name);
                            }
                            if (empty($name)) {
                                $name = 'İsimsiz Temsilci';
                            }
                            echo esc_html($name);
                            ?>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <span style="color: #28a745; font-weight: bold; font-size: 16px;"><?php echo isset($rep->new_customers) ? intval($rep->new_customers) : 0; ?></span>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <span style="color: #17a2b8; font-weight: bold; font-size: 16px;"><?php echo isset($rep->sold_policies) ? intval($rep->sold_policies) : 0; ?></span>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <span style="color: #6f42c1; font-weight: bold;">
                                <?php echo number_format(isset($rep->premium_total) ? floatval($rep->premium_total) : 0, 0, ',', '.') . ' ₺'; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Today's Priority Tasks -->
<div class="info-card">
    <h3 style="color: #dc3545; margin-bottom: 15px;">Bugün ve Yakın Zamanda Tamamlanması Gereken Görevler</h3>
    
    <?php if (!empty($variables['all_pending_tasks'])): ?>
        <?php foreach (array_slice($variables['all_pending_tasks'], 0, 8) as $task): ?>
            <div style="border-left: 3px solid #dc3545; padding: 12px 15px; margin-bottom: 12px; background-color: #fff5f5; border-radius: 4px;">
                <div style="font-weight: 600; color: #dc3545; margin-bottom: 5px;">
                    <?php echo esc_html($task->task_description); ?>
                </div>
                <div style="color: #6c757d; font-size: 14px; margin-bottom: 5px;">
                    Müşteri: <?php echo esc_html($task->first_name . ' ' . $task->last_name); ?> | 
                    Temsilci: <?php echo esc_html($task->rep_first_name . ' ' . $task->rep_last_name); ?>
                </div>
                <div style="font-size: 12px; color: #dc3545; font-weight: 600;">
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
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($variables['all_pending_tasks']) > 8): ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" 
                   style="color: #667eea; text-decoration: none; font-weight: 600;">
                    +<?php echo count($variables['all_pending_tasks']) - 8; ?> görevi daha göster
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; color: #28a745;">
            <div style="font-size: 48px; margin-bottom: 10px;">✓</div>
            <div style="font-weight: 600;">Yakın zamanda tamamlanması gereken kritik görev bulunmuyor</div>
        </div>
    <?php endif; ?>
</div>

<!-- Expiring Policies -->
<div class="info-card">
    <h3 style="color: #f39c12; margin-bottom: 15px;">Yaklaşan Poliçe Yenilemeleri (30 Gün İçinde)</h3>
    
    <?php if (!empty($variables['all_expiring_policies'])): ?>
        <?php foreach (array_slice($variables['all_expiring_policies'], 0, 8) as $policy): ?>
            <div style="border-left: 3px solid #f39c12; padding: 12px 15px; margin-bottom: 12px; background-color: #fff8e1; border-radius: 4px;">
                <div style="font-weight: 600; color: #f39c12; margin-bottom: 5px;">
                    <?php echo esc_html($policy->policy_number); ?> - <?php echo esc_html($policy->policy_type); ?>
                </div>
                <div style="color: #6c757d; font-size: 14px; margin-bottom: 5px;">
                    Müşteri: <?php echo esc_html($policy->first_name . ' ' . $policy->last_name); ?> | 
                    Temsilci: <?php echo esc_html($policy->rep_first_name . ' ' . $policy->rep_last_name); ?>
                </div>
                <div style="font-size: 12px; color: #f39c12; font-weight: 600;">
                    Bitiş Tarihi: <?php echo date('d.m.Y', strtotime($policy->end_date)); ?>
                    <?php 
                    $days_left = ceil((strtotime($policy->end_date) - time()) / (60 * 60 * 24));
                    echo " ({$days_left} gün kaldı)";
                    if ($days_left <= 7) {
                        echo " - ACİL!";
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($variables['all_expiring_policies']) > 8): ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" 
                   style="color: #667eea; text-decoration: none; font-weight: 600;">
                    +<?php echo count($variables['all_expiring_policies']) - 8; ?> poliçeyi daha göster
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; color: #28a745;">
            <div style="font-size: 48px; margin-bottom: 10px;">✓</div>
            <div style="font-weight: 600;">Önümüzdeki 30 gün içinde yenilenecek poliçe bulunmuyor</div>
        </div>
    <?php endif; ?>
</div>

<!-- Current Month Representative Performance -->
<?php 
$filtered_performance = array();
if (!empty($variables['representative_performance'])) {
    foreach ($variables['representative_performance'] as $rep) {
        if (isset($rep->monthly_policies) && $rep->monthly_policies > 0) {
            $filtered_performance[] = $rep;
        }
    }
}
?>
<?php if (!empty($filtered_performance) && count($filtered_performance) > 0): ?>
<div class="info-card">
    <h3 style="color: #6f42c1; margin-bottom: 20px;">Bu Ay Temsilci Performans Özeti</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: left; font-weight: 600;">Temsilci</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Bu Ay Poliçe</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Bu Ay Prim</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Hedef %</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Bekleyen Görev</th>
                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center; font-weight: 600;">Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($filtered_performance, 0, 10) as $rep): ?>
                    <tr>
                        <td style="border: 1px solid #dee2e6; padding: 12px; font-weight: 600;">
                            <?php 
                            $name = '';
                            if (!empty($rep->display_name)) {
                                $name = $rep->display_name;
                            } elseif (!empty($rep->first_name) || !empty($rep->last_name)) {
                                $name = trim($rep->first_name . ' ' . $rep->last_name);
                            }
                            if (empty($name)) {
                                $name = 'İsimsiz Temsilci';
                            }
                            echo esc_html($name);
                            ?>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <span style="color: #28a745; font-weight: bold; font-size: 16px;"><?php echo isset($rep->monthly_policies) ? intval($rep->monthly_policies) : 0; ?></span>
                            <?php if (isset($rep->minimum_policy_count) && $rep->minimum_policy_count > 0): ?>
                                <div style="font-size: 11px; color: #6c757d;">/ <?php echo intval($rep->minimum_policy_count); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <span style="color: #17a2b8; font-weight: bold;">
                                <?php echo number_format(isset($rep->monthly_premium) ? floatval($rep->monthly_premium) : 0, 0, ',', '.') . ' ₺'; ?>
                            </span>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <?php 
                            $policy_percentage = 0;
                            $monthly_policies = isset($rep->monthly_policies) ? intval($rep->monthly_policies) : 0;
                            $min_policy_count = isset($rep->minimum_policy_count) ? intval($rep->minimum_policy_count) : 0;
                            
                            if ($min_policy_count > 0) {
                                $policy_percentage = min(100, ($monthly_policies / $min_policy_count) * 100);
                            }
                            $color = $policy_percentage >= 100 ? '#28a745' : ($policy_percentage >= 70 ? '#ffc107' : '#dc3545');
                            ?>
                            <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                %<?php echo number_format($policy_percentage, 0); ?>
                            </span>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <?php $pending_tasks = isset($rep->pending_task_count) ? intval($rep->pending_task_count) : 0; ?>
                            <span style="color: <?php echo $pending_tasks > 10 ? '#dc3545' : ($pending_tasks > 5 ? '#ffc107' : '#28a745'); ?>; font-weight: bold;">
                                <?php echo $pending_tasks; ?>
                            </span>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                            <?php if ($pending_tasks > 10): ?>
                                <span style="color: #dc3545; font-weight: 600;">Yoğun</span>
                            <?php elseif ($pending_tasks > 5): ?>
                                <span style="color: #ffc107; font-weight: 600;">Normal</span>
                            <?php else: ?>
                                <span style="color: #28a745; font-weight: 600;">İyi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($filtered_performance) > 10): ?>
            <div style="text-align: center; margin-top: 15px; color: #6c757d; font-style: italic;">
                +<?php echo count($filtered_performance) - 10; ?> temsilci daha
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Quick Access Links -->
<div class="info-card" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <h3 style="color: white; margin-bottom: 20px;">Yönetim Paneli Erişim</h3>
    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <a href="<?php echo home_url('/temsilci-paneli/'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Ana Panel
        </a>
        <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Tüm Görevler
        </a>
        <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Poliçeler
        </a>
        <a href="<?php echo home_url('/temsilci-paneli/?section=reports'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Raporlar
        </a>
    </div>
</div>

<!-- Summary Footer -->
<div style="text-align: center; margin-top: 30px; padding: 25px; background-color: #f8f9fa; border-radius: 8px;">
    <h4 style="color: #495057; margin-bottom: 20px; font-size: 18px;">Günlük Özet</h4>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 24px; font-weight: bold; color: #dc3545; margin-bottom: 5px;">{total_pending_tasks}</div>
            <div style="font-size: 14px; color: #6c757d;">Bekleyen Görev</div>
        </div>
        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 24px; font-weight: bold; color: #f39c12; margin-bottom: 5px;">{total_expiring_policies}</div>
            <div style="font-size: 14px; color: #6c757d;">Yenilenecek Poliçe</div>
        </div>
        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 24px; font-weight: bold; color: #28a745; margin-bottom: 5px;">{total_active_representatives}</div>
            <div style="font-size: 14px; color: #6c757d;">Aktif Temsilci</div>
        </div>
    </div>
    <p style="font-size: 14px; color: #6c757d; margin: 0;">
        Başarılı bir gün geçirin ve ekibinizi yönlendirin.
    </p>
</div>