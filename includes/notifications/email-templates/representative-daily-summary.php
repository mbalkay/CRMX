<?php
/**
 * Representative Daily Summary Email Template - Corporate Dashboard Style
 * 
 * Available variables:
 * - {representative_name}
 * - {today_date}, {today_day}
 * - {tasks_today_count}, {tasks_upcoming_count}
 * - {policies_expiring_count}, {total_customers}
 * - {tasks_today} (array)
 * - {tasks_upcoming} (array) 
 * - {policies_expiring} (array)
 * - {quick_stats} (array)
 * - {yesterday_stats} (array)
 * - {goal_tracking} (array)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="email-section">
    <h2>Günlük Performans Raporu</h2>
    <p style="font-size: 16px; margin-bottom: 25px; color: #495057;">
        <strong>{representative_name}</strong> • <strong>{today_day}, {today_date}</strong>
    </p>
</div>

<!-- Yesterday's Performance Dashboard -->
<div class="info-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 25px;">
    <h3 style="color: white; margin-bottom: 20px;">Dünkü Performans Özeti</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px;">
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                <?php echo isset($variables['yesterday_stats']['new_customers']) ? $variables['yesterday_stats']['new_customers'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Yeni Müşteri</div>
        </div>
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">
                <?php echo isset($variables['yesterday_stats']['sold_policies']) ? $variables['yesterday_stats']['sold_policies'] : 0; ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Satılan Poliçe</div>
        </div>
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <div style="font-size: 24px; font-weight: bold; margin-bottom: 5px;">
                <?php 
                $amount = isset($variables['yesterday_stats']['total_premium']) ? $variables['yesterday_stats']['total_premium'] : 0;
                echo number_format($amount, 0, ',', '.') . ' ₺';
                ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9;">Üretim Tutarı</div>
        </div>
    </div>
</div>

<!-- Goal Progress Dashboard -->
<div class="info-card" style="background-color: #f8f9fa; border-left: 4px solid #28a745;">
    <h3 style="color: #495057; margin-bottom: 20px;">Bu Ay Hedef Durumu</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-weight: 600; color: #495057;">Poliçe Hedefi</span>
                <span style="font-weight: bold; color: #28a745;">
                    <?php echo isset($variables['goal_tracking']['current_month_policies']) ? $variables['goal_tracking']['current_month_policies'] : 0; ?> / 
                    <?php echo isset($variables['goal_tracking']['min_policy_count']) ? $variables['goal_tracking']['min_policy_count'] : 10; ?>
                </span>
            </div>
            <div style="background-color: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background-color: #28a745; height: 100%; width: <?php echo isset($variables['goal_tracking']['policy_goal_percentage']) ? min(100, $variables['goal_tracking']['policy_goal_percentage']) : 0; ?>%; transition: width 0.3s ease;"></div>
            </div>
            <div style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                %<?php echo isset($variables['goal_tracking']['policy_goal_percentage']) ? number_format($variables['goal_tracking']['policy_goal_percentage'], 1) : '0.0'; ?> tamamlandı
            </div>
        </div>
        <div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-weight: 600; color: #495057;">Prim Hedefi</span>
                <span style="font-weight: bold; color: #17a2b8;">
                    <?php 
                    $current = isset($variables['goal_tracking']['current_month_premium']) ? $variables['goal_tracking']['current_month_premium'] : 0;
                    $target = isset($variables['goal_tracking']['min_premium_amount']) ? $variables['goal_tracking']['min_premium_amount'] : 300000;
                    echo number_format($current, 0, ',', '.') . ' ₺ / ' . number_format($target, 0, ',', '.') . ' ₺';
                    ?>
                </span>
            </div>
            <div style="background-color: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background-color: #17a2b8; height: 100%; width: <?php echo isset($variables['goal_tracking']['premium_goal_percentage']) ? min(100, $variables['goal_tracking']['premium_goal_percentage']) : 0; ?>%; transition: width 0.3s ease;"></div>
            </div>
            <div style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                %<?php echo isset($variables['goal_tracking']['premium_goal_percentage']) ? number_format($variables['goal_tracking']['premium_goal_percentage'], 1) : '0.0'; ?> tamamlandı
            </div>
        </div>
    </div>
</div>

<!-- Today's Tasks -->
<?php if (!empty($variables['tasks_today'])): ?>
<div class="info-card">
    <h3 style="color: #dc3545; margin-bottom: 15px;">Bugünkü Görevleriniz (<?php echo count($variables['tasks_today']); ?>)</h3>
    <?php foreach (array_slice($variables['tasks_today'], 0, 5) as $task): ?>
        <div style="border-left: 3px solid #dc3545; padding: 12px 15px; margin-bottom: 12px; background-color: #fff5f5; border-radius: 4px;">
            <div style="font-weight: 600; color: #dc3545; margin-bottom: 5px;">
                <?php echo esc_html($task->task_description); ?>
            </div>
            <div style="color: #6c757d; font-size: 14px; margin-bottom: 3px;">
                Müşteri: <?php echo esc_html($task->first_name . ' ' . $task->last_name); ?>
                <?php if ($task->policy_number): ?>
                    | Poliçe: <?php echo esc_html($task->policy_number); ?>
                <?php endif; ?>
            </div>
            <div style="font-size: 12px; color: #dc3545;">
                Öncelik: <?php echo esc_html($task->priority); ?> | Son Tarih: <?php echo date('H:i', strtotime($task->due_date)); ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (count($variables['tasks_today']) > 5): ?>
        <div style="text-align: center; margin-top: 15px;">
            <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" 
               style="color: #667eea; text-decoration: none; font-weight: 600;">
                +<?php echo count($variables['tasks_today']) - 5; ?> görevi daha göster
            </a>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="info-card">
    <h3 style="color: #28a745;">Bugünkü Görevleriniz</h3>
    <div style="text-align: center; padding: 30px; color: #28a745;">
        <div style="font-size: 48px; margin-bottom: 10px;">✓</div>
        <div style="font-weight: 600;">Bugün için planlanmış göreviniz bulunmuyor</div>
    </div>
</div>
<?php endif; ?>

<!-- Expiring Policies -->
<?php if (!empty($variables['policies_expiring'])): ?>
<div class="info-card">
    <h3 style="color: #f39c12; margin-bottom: 15px;">Yaklaşan Poliçe Yenilemeleri (<?php echo count($variables['policies_expiring']); ?>)</h3>
    <?php foreach (array_slice($variables['policies_expiring'], 0, 5) as $policy): ?>
        <div style="border-left: 3px solid #f39c12; padding: 12px 15px; margin-bottom: 12px; background-color: #fff8e1; border-radius: 4px;">
            <div style="font-weight: 600; color: #f39c12; margin-bottom: 5px;">
                <?php echo esc_html($policy->policy_number); ?> - <?php echo esc_html($policy->policy_type); ?>
            </div>
            <div style="color: #6c757d; font-size: 14px; margin-bottom: 3px;">
                Müşteri: <?php echo esc_html($policy->first_name . ' ' . $policy->last_name); ?>
            </div>
            <div style="font-size: 12px; color: #f39c12; font-weight: 600;">
                Bitiş Tarihi: <?php echo date('d.m.Y', strtotime($policy->end_date)); ?>
                <?php 
                $days_left = ceil((strtotime($policy->end_date) - time()) / (60 * 60 * 24));
                if ($days_left <= 7) {
                    echo " (Acil - {$days_left} gün kaldı)";
                } else {
                    echo " ({$days_left} gün kaldı)";
                }
                ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (count($variables['policies_expiring']) > 5): ?>
        <div style="text-align: center; margin-top: 15px;">
            <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" 
               style="color: #667eea; text-decoration: none; font-weight: 600;">
                +<?php echo count($variables['policies_expiring']) - 5; ?> poliçeyi daha göster
            </a>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- This Month Performance Summary -->
<div class="info-card" style="background-color: #f8f9fa;">
    <h3 style="color: #495057; margin-bottom: 20px;">Bu Ay Performans Özeti</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
        <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 28px; font-weight: bold; color: #28a745; margin-bottom: 5px;">
                <?php echo isset($variables['quick_stats']['policies_this_month']) ? $variables['quick_stats']['policies_this_month'] : 0; ?>
            </div>
            <div style="font-size: 14px; color: #6c757d;">Satılan Poliçe</div>
        </div>
        <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 28px; font-weight: bold; color: #17a2b8; margin-bottom: 5px;">
                <?php echo isset($variables['quick_stats']['completed_tasks_this_week']) ? $variables['quick_stats']['completed_tasks_this_week'] : 0; ?>
            </div>
            <div style="font-size: 14px; color: #6c757d;">Bu Hafta Tamamlanan Görev</div>
        </div>
        <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 28px; font-weight: bold; color: #6f42c1; margin-bottom: 5px;">
                <?php echo isset($variables['total_customers']) ? $variables['total_customers'] : 0; ?>
            </div>
            <div style="font-size: 14px; color: #6c757d;">Toplam Müşteri</div>
        </div>
    </div>
</div>

<!-- Quick Access Links -->
<div class="info-card" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <h3 style="color: white; margin-bottom: 20px;">Hızlı Erişim</h3>
    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <a href="<?php echo home_url('/temsilci-paneli/'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Ana Panel
        </a>
        <a href="<?php echo home_url('/temsilci-paneli/?section=customers'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Müşteriler
        </a>
        <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Görevler
        </a>
        <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); font-weight: 600;">
            Poliçeler
        </a>
    </div>
</div>