<?php
/**
 * Representative Daily Summary Email Template - Simple Corporate Style
 * 
 * Available variables:
 * - {representative_name}
 * - {today_date}, {today_day}
 * - {tasks_today_count}, {tasks_upcoming_count}
 * - {policies_expiring_count}
 * - {tasks_today} (array)
 * - {tasks_upcoming} (array) 
 * - {policies_expiring} (array)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<h2 class="section-title">Günlük Özet Raporu</h2>
<p style="font-size: 14px; margin-bottom: 20px; color: #495057;">
    <strong>{representative_name}</strong> • <strong>{today_day}, {today_date}</strong>
</p>

<!-- Today's Tasks -->
<?php if (!empty($variables['tasks_today']) && count($variables['tasks_today']) > 0): ?>
<h3 class="section-title">Bugünkü Görevleriniz (<?php echo count($variables['tasks_today']); ?>)</h3>
<table class="info-table">
    <thead>
        <tr>
            <th>Görev</th>
            <th>Müşteri</th>
            <th>Son Tarih</th>
            <th>Öncelik</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_slice($variables['tasks_today'], 0, 10) as $task): ?>
        <tr>
            <td><?php echo esc_html($task->task_description); ?></td>
            <td><?php echo esc_html($task->first_name . ' ' . $task->last_name); ?></td>
            <td><?php echo date('H:i', strtotime($task->due_date)); ?></td>
            <td><?php echo esc_html($task->priority); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if (count($variables['tasks_today']) > 10): ?>
<p style="text-align: center; margin: 10px 0;">
    <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" class="link-button">
        +<?php echo count($variables['tasks_today']) - 10; ?> görevi daha göster
    </a>
</p>
<?php endif; ?>
<?php else: ?>
<h3 class="section-title">Bugünkü Görevleriniz</h3>
<div class="stats-box">
    <p style="color: #28a745; font-weight: bold;">Bugün için planlanmış göreviniz bulunmuyor</p>
</div>
<?php endif; ?>

<!-- This Week's Tasks -->
<?php if (!empty($variables['tasks_upcoming']) && count($variables['tasks_upcoming']) > 0): ?>
<h3 class="section-title">Bu Haftaki Görevleriniz (<?php echo count($variables['tasks_upcoming']); ?>)</h3>
<table class="info-table">
    <thead>
        <tr>
            <th>Görev</th>
            <th>Müşteri</th>
            <th>Son Tarih</th>
            <th>Öncelik</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_slice($variables['tasks_upcoming'], 0, 10) as $task): ?>
        <tr>
            <td><?php echo esc_html($task->task_description); ?></td>
            <td><?php echo esc_html($task->first_name . ' ' . $task->last_name); ?></td>
            <td><?php echo date('d.m.Y H:i', strtotime($task->due_date)); ?></td>
            <td><?php echo esc_html($task->priority); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if (count($variables['tasks_upcoming']) > 10): ?>
<p style="text-align: center; margin: 10px 0;">
    <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" class="link-button">
        +<?php echo count($variables['tasks_upcoming']) - 10; ?> görevi daha göster
    </a>
</p>
<?php endif; ?>
<?php endif; ?>

<!-- Expiring Policies (7 days) -->
<?php if (!empty($variables['policies_expiring']) && count($variables['policies_expiring']) > 0): ?>
<h3 class="section-title">Yaklaşan Poliçe Yenilemeleri - 7 Gün İçinde (<?php echo count($variables['policies_expiring']); ?>)</h3>
<table class="info-table">
    <thead>
        <tr>
            <th>Poliçe No</th>
            <th>Müşteri</th>
            <th>Poliçe Türü</th>
            <th>Bitiş Tarihi</th>
            <th>Kalan Gün</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_slice($variables['policies_expiring'], 0, 10) as $policy): ?>
        <tr>
            <td><?php echo esc_html($policy->policy_number); ?></td>
            <td><?php echo esc_html($policy->first_name . ' ' . $policy->last_name); ?></td>
            <td><?php echo esc_html($policy->policy_type); ?></td>
            <td><?php echo date('d.m.Y', strtotime($policy->end_date)); ?></td>
            <td>
                <?php 
                $days_left = ceil((strtotime($policy->end_date) - time()) / (60 * 60 * 24));
                if ($days_left <= 1) {
                    echo "<span style='color: #dc3545; font-weight: bold;'>BUGÜN!</span>";
                } else {
                    echo $days_left . " gün";
                }
                ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if (count($variables['policies_expiring']) > 10): ?>
<p style="text-align: center; margin: 10px 0;">
    <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" class="link-button">
        +<?php echo count($variables['policies_expiring']) - 10; ?> poliçeyi daha göster
    </a>
</p>
<?php endif; ?>
<?php endif; ?>

<!-- Quick Access Links -->
<h3 class="section-title">Hızlı Erişim</h3>
<div style="text-align: center; margin: 20px 0;">
    <a href="<?php echo home_url('/temsilci-paneli/'); ?>" class="link-button">Ana Panel</a>
    <a href="<?php echo home_url('/temsilci-paneli/?section=customers'); ?>" class="link-button">Müşteriler</a>
    <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" class="link-button">Görevler</a>
    <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" class="link-button">Poliçeler</a>
</div>

<!-- Summary Stats -->
<div class="stats-box">
    <h4 style="margin: 0 0 10px 0; color: #495057;">Günlük Özet</h4>
    <table style="width: 100%; border: none;">
        <tr>
            <td style="text-align: center; border: none;">
                <div class="stats-number">{tasks_today_count}</div>
                <div class="stats-label">Bugünkü Görev</div>
            </td>
            <td style="text-align: center; border: none;">
                <div class="stats-number">{tasks_upcoming_count}</div>
                <div class="stats-label">Bu Haftaki Görev</div>
            </td>
            <td style="text-align: center; border: none;">
                <div class="stats-number">{policies_expiring_count}</div>
                <div class="stats-label">Yenilenecek Poliçe</div>
            </td>
        </tr>
    </table>
</div>