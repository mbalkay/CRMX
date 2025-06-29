<?php
/**
 * Representative Daily Summary Email Template
 * 
 * Available variables:
 * - {representative_name}
 * - {today_date}, {today_day}
 * - {tasks_today_count}, {tasks_upcoming_count}
 * - {policies_expiring_count}, {active_quotes_count}
 * - {total_customers}
 * - {tasks_today} (array)
 * - {tasks_upcoming} (array) 
 * - {policies_expiring} (array)
 * - {active_quotes} (array)
 * - {quick_stats} (array)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="email-section">
    <h2>🌅 Günaydın {representative_name}!</h2>
    <p style="font-size: 16px; margin-bottom: 25px;">
        <strong>{today_day}, {today_date}</strong> günü için kişisel özet bilgileriniz hazır.
    </p>
</div>

<!-- Quick Stats Overview -->
<div class="info-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 25px;">
    <h3 style="color: white; margin-bottom: 15px;">📊 Hızlı Bakış</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold;">{tasks_today_count}</div>
            <div style="font-size: 12px; opacity: 0.9;">Bugün Görev</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold;">{policies_expiring_count}</div>
            <div style="font-size: 12px; opacity: 0.9;">Yenileme</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold;">{total_customers}</div>
            <div style="font-size: 12px; opacity: 0.9;">Müşteri</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold;">{tasks_upcoming_count}</div>
            <div style="font-size: 12px; opacity: 0.9;">Yaklaşan</div>
        </div>
    </div>
</div>

<!-- Today's Tasks -->
<?php if (!empty($variables['tasks_today'])): ?>
<div class="info-card">
    <h3 style="color: #e74c3c;">🎯 Bugünkü Görevleriniz ({tasks_today_count})</h3>
    <?php foreach ($variables['tasks_today'] as $task): ?>
        <div class="info-row" style="border-left: 3px solid #e74c3c; padding-left: 10px; margin-bottom: 10px;">
            <div>
                <strong><?php echo esc_html($task->task_description); ?></strong>
                <br>
                <span style="color: #666;">
                    Müşteri: <?php echo esc_html($task->first_name . ' ' . $task->last_name); ?>
                    <?php if ($task->policy_number): ?>
                        | Poliçe: <?php echo esc_html($task->policy_number); ?>
                    <?php endif; ?>
                </span>
                <br>
                <small style="color: #999;">
                    Öncelik: <?php echo esc_html($task->priority); ?> | 
                    Son: <?php echo date('H:i', strtotime($task->due_date)); ?>
                </small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="info-card">
    <h3 style="color: #27ae60;">🎯 Bugünkü Görevleriniz</h3>
    <p style="color: #27ae60; text-align: center; padding: 20px;">
        ✅ Bugün için planlanmış göreviniz bulunmuyor. Harika bir gün!
    </p>
</div>
<?php endif; ?>

<!-- Upcoming Policy Renewals -->
<?php if (!empty($variables['policies_expiring'])): ?>
<div class="info-card">
    <h3 style="color: #f39c12;">🔄 Yaklaşan Poliçe Yenilemeleri (30 Gün İçinde)</h3>
    <?php foreach (array_slice($variables['policies_expiring'], 0, 5) as $policy): ?>
        <div class="info-row" style="border-left: 3px solid #f39c12; padding-left: 10px; margin-bottom: 10px;">
            <div>
                <strong><?php echo esc_html($policy->policy_number); ?></strong> - <?php echo esc_html($policy->policy_type); ?>
                <br>
                <span style="color: #666;">
                    Müşteri: <?php echo esc_html($policy->first_name . ' ' . $policy->last_name); ?>
                </span>
                <br>
                <small style="color: #f39c12;">
                    <strong>Bitiş: <?php echo date('d.m.Y', strtotime($policy->end_date)); ?></strong>
                    <?php 
                    $days_left = ceil((strtotime($policy->end_date) - time()) / (60 * 60 * 24));
                    echo " ({$days_left} gün kaldı)";
                    ?>
                </small>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (count($variables['policies_expiring']) > 5): ?>
        <p style="text-align: center; color: #666; font-style: italic;">
            ... ve <?php echo count($variables['policies_expiring']) - 5; ?> adet daha
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Upcoming Tasks -->
<?php if (!empty($variables['tasks_upcoming'])): ?>
<div class="info-card">
    <h3 style="color: #3498db;">📅 Yaklaşan Görevler (7 Gün İçinde)</h3>
    <?php foreach (array_slice($variables['tasks_upcoming'], 0, 5) as $task): ?>
        <div class="info-row" style="border-left: 3px solid #3498db; padding-left: 10px; margin-bottom: 10px;">
            <div>
                <strong><?php echo esc_html($task->task_description); ?></strong>
                <br>
                <span style="color: #666;">
                    Müşteri: <?php echo esc_html($task->first_name . ' ' . $task->last_name); ?>
                </span>
                <br>
                <small style="color: #3498db;">
                    <strong><?php echo date('d.m.Y H:i', strtotime($task->due_date)); ?></strong>
                    <?php 
                    $days_left = ceil((strtotime($task->due_date) - time()) / (60 * 60 * 24));
                    if ($days_left > 0) {
                        echo " ({$days_left} gün sonra)";
                    } else {
                        echo " (Bugün)";
                    }
                    ?>
                </small>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (count($variables['tasks_upcoming']) > 5): ?>
        <p style="text-align: center; color: #666; font-style: italic;">
            ... ve <?php echo count($variables['tasks_upcoming']) - 5; ?> adet daha
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Performance Stats -->
<?php if (!empty($variables['quick_stats'])): ?>
<div class="info-card" style="background-color: #f8f9fa;">
    <h3 style="color: #6c757d;">📈 Bu Dönem Performansınız</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
            <div style="font-size: 20px; font-weight: bold; color: #28a745;">
                <?php echo isset($variables['quick_stats']['policies_this_month']) ? $variables['quick_stats']['policies_this_month'] : 0; ?>
            </div>
            <div style="font-size: 14px; color: #666;">Bu Ay Poliçe</div>
        </div>
        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
            <div style="font-size: 20px; font-weight: bold; color: #17a2b8;">
                <?php echo isset($variables['quick_stats']['completed_tasks_this_week']) ? $variables['quick_stats']['completed_tasks_this_week'] : 0; ?>
            </div>
            <div style="font-size: 14px; color: #666;">Bu Hafta Tamamlanan</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick Action Links -->
<div class="info-card" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <h3 style="color: white; margin-bottom: 20px;">🚀 Hızlı Erişim</h3>
    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <a href="<?php echo admin_url('admin.php?page=insurance-crm-representative'); ?>" 
           class="button" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3);">
            📋 Panel
        </a>
        <a href="<?php echo admin_url('admin.php?page=insurance-crm-representative&section=customers'); ?>" 
           class="button" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3);">
            👥 Müşteriler
        </a>
        <a href="<?php echo admin_url('admin.php?page=insurance-crm-representative&section=tasks'); ?>" 
           class="button" 
           style="background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3);">
            ✅ Görevler
        </a>
    </div>
</div>

<!-- Motivational Footer -->
<div style="text-align: center; margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
    <p style="font-size: 16px; color: #495057; margin-bottom: 10px;">
        <?php 
        $motivational_messages = array(
            "Bugün harika bir gün olacak! 💪",
            "Başarılarınız devam ediyor! 🌟", 
            "Müşterileriniz sizinle çalıştığı için şanslı! 🎯",
            "Her gün bir adım daha yaklaşıyorsunuz! 🚀",
            "Profesyonel hizmetiniz fark yaratıyor! ⭐"
        );
        echo $motivational_messages[array_rand($motivational_messages)];
        ?>
    </p>
    <p style="font-size: 14px; color: #6c757d; margin: 0;">
        Güzel bir gün geçirin ve başarılar dileriz!
    </p>
</div>