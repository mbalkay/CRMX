<?php
/**
 * Manager Daily Report Email Template - Simple Corporate Style
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
 * - {all_pending_tasks} (array)
 * - {all_expiring_policies} (array)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helper function to get representative name
function get_rep_name($rep) {
    $name = '';
    if (!empty($rep->display_name)) {
        $name = $rep->display_name;
    } elseif (!empty($rep->first_name) && !empty($rep->last_name)) {
        $name = trim($rep->first_name . ' ' . $rep->last_name);
    } elseif (!empty($rep->first_name)) {
        $name = $rep->first_name;
    } elseif (!empty($rep->last_name)) {
        $name = $rep->last_name;
    } else {
        $name = 'İsimsiz Temsilci';
    }
    return $name;
}
?>

<h2 class="section-title">Yönetici Günlük Raporu</h2>
<p style="font-size: 14px; margin-bottom: 20px; color: #495057;">
    <strong>{manager_name}</strong> • <strong>{today_day}, {today_date}</strong>
</p>

<!-- System Overview -->
<h3 class="section-title">Sistem Genel Bakış</h3>
<div class="stats-box">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="text-align: center; border: none; width: 25%;">
                <div class="stats-number"><?php echo isset($variables['system_stats']['total_policies']) ? $variables['system_stats']['total_policies'] : 0; ?></div>
                <div class="stats-label">Aktif Poliçe</div>
            </td>
            <td style="text-align: center; border: none; width: 25%;">
                <div class="stats-number"><?php echo isset($variables['system_stats']['total_customers']) ? $variables['system_stats']['total_customers'] : 0; ?></div>
                <div class="stats-label">Toplam Müşteri</div>
            </td>
            <td style="text-align: center; border: none; width: 25%;">
                <div class="stats-number">{total_active_representatives}</div>
                <div class="stats-label">Aktif Temsilci</div>
            </td>
            <td style="text-align: center; border: none; width: 25%;">
                <div class="stats-number"><?php echo isset($variables['system_stats']['policies_this_month']) ? $variables['system_stats']['policies_this_month'] : 0; ?></div>
                <div class="stats-label">Bu Ay Poliçe</div>
            </td>
        </tr>
    </table>
</div>

<!-- Critical Alerts -->
<?php if (!empty($variables['critical_alerts'])): ?>
<h3 class="section-title">Kritik Uyarılar</h3>
<div style="background-color: #fff5f5; border: 1px solid #f5c6cb; padding: 15px; margin: 15px 0;">
    <?php foreach ($variables['critical_alerts'] as $alert): ?>
        <p style="color: #721c24; margin: 5px 0; font-weight: bold;">• <?php echo esc_html($alert); ?></p>
    <?php endforeach; ?>
</div>
<?php else: ?>
<h3 class="section-title">Sistem Durumu</h3>
<div class="stats-box" style="background-color: #f0fff4;">
    <p style="color: #28a745; font-weight: bold;">✓ Kritik uyarı bulunmuyor - Sistem normal çalışıyor</p>
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
<h3 class="section-title">Dünkü Temsilci Performansları</h3>
<table class="info-table">
    <thead>
        <tr>
            <th>Temsilci</th>
            <th>Yeni Müşteri</th>
            <th>Satılan Poliçe</th>
            <th>Üretim Tutarı</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($filtered_yesterday as $rep): ?>
            <tr>
                <td><?php echo esc_html(get_rep_name($rep)); ?></td>
                <td style="text-align: center; color: #28a745; font-weight: bold;">
                    <?php echo isset($rep->new_customers) ? intval($rep->new_customers) : 0; ?>
                </td>
                <td style="text-align: center; color: #17a2b8; font-weight: bold;">
                    <?php echo isset($rep->sold_policies) ? intval($rep->sold_policies) : 0; ?>
                </td>
                <td style="text-align: center; color: #6f42c1; font-weight: bold;">
                    <?php echo number_format(isset($rep->premium_total) ? floatval($rep->premium_total) : 0, 0, ',', '.') . ' ₺'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Today's Priority Tasks -->
<h3 class="section-title">Bugün ve Yakın Zamanda Tamamlanması Gereken Görevler</h3>
<?php if (!empty($variables['all_pending_tasks'])): ?>
<table class="info-table">
    <thead>
        <tr>
            <th>Görev</th>
            <th>Müşteri</th>
            <th>Temsilci</th>
            <th>Son Tarih</th>
            <th>Durum</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_slice($variables['all_pending_tasks'], 0, 10) as $task): ?>
            <tr>
                <td><?php echo esc_html($task->task_description); ?></td>
                <td><?php echo esc_html($task->first_name . ' ' . $task->last_name); ?></td>
                <td><?php echo esc_html($task->rep_first_name . ' ' . $task->rep_last_name); ?></td>
                <td><?php echo date('d.m.Y H:i', strtotime($task->due_date)); ?></td>
                <td>
                    <?php 
                    $hours_left = (strtotime($task->due_date) - time()) / 3600;
                    if ($hours_left < 0) {
                        echo "<span style='color: #dc3545; font-weight: bold;'>GECİKMİŞ!</span>";
                    } elseif ($hours_left < 24) {
                        echo "<span style='color: #ffc107; font-weight: bold;'>" . round($hours_left) . " saat kaldı</span>";
                    } else {
                        echo "<span style='color: #28a745;'>" . ceil($hours_left / 24) . " gün kaldı</span>";
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if (count($variables['all_pending_tasks']) > 10): ?>
<p style="text-align: center; margin: 10px 0;">
    <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" class="link-button">
        +<?php echo count($variables['all_pending_tasks']) - 10; ?> görevi daha göster
    </a>
</p>
<?php endif; ?>
<?php else: ?>
<div class="stats-box">
    <p style="color: #28a745; font-weight: bold;">✓ Yakın zamanda tamamlanması gereken kritik görev bulunmuyor</p>
</div>
<?php endif; ?>

<!-- Expiring Policies -->
<h3 class="section-title">Yaklaşan Poliçe Yenilemeleri (30 Gün İçinde)</h3>
<?php if (!empty($variables['all_expiring_policies'])): ?>
<table class="info-table">
    <thead>
        <tr>
            <th>Poliçe No</th>
            <th>Müşteri</th>
            <th>Temsilci</th>
            <th>Poliçe Türü</th>
            <th>Bitiş Tarihi</th>
            <th>Kalan Gün</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_slice($variables['all_expiring_policies'], 0, 10) as $policy): ?>
            <tr>
                <td><?php echo esc_html($policy->policy_number); ?></td>
                <td><?php echo esc_html($policy->first_name . ' ' . $policy->last_name); ?></td>
                <td><?php echo esc_html($policy->rep_first_name . ' ' . $policy->rep_last_name); ?></td>
                <td><?php echo esc_html($policy->policy_type); ?></td>
                <td><?php echo date('d.m.Y', strtotime($policy->end_date)); ?></td>
                <td>
                    <?php 
                    $days_left = ceil((strtotime($policy->end_date) - time()) / (60 * 60 * 24));
                    if ($days_left <= 1) {
                        echo "<span style='color: #dc3545; font-weight: bold;'>BUGÜN!</span>";
                    } elseif ($days_left <= 7) {
                        echo "<span style='color: #ffc107; font-weight: bold;'>{$days_left} gün - ACİL!</span>";
                    } else {
                        echo "<span style='color: #28a745;'>{$days_left} gün</span>";
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if (count($variables['all_expiring_policies']) > 10): ?>
<p style="text-align: center; margin: 10px 0;">
    <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" class="link-button">
        +<?php echo count($variables['all_expiring_policies']) - 10; ?> poliçeyi daha göster
    </a>
</p>
<?php endif; ?>
<?php else: ?>
<div class="stats-box">
    <p style="color: #28a745; font-weight: bold;">✓ Önümüzdeki 30 gün içinde yenilenecek poliçe bulunmuyor</p>
</div>
<?php endif; ?>

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
<h3 class="section-title">Bu Ay Temsilci Performans Özeti</h3>
<table class="info-table">
    <thead>
        <tr>
            <th>Temsilci</th>
            <th>Bu Ay Poliçe</th>
            <th>Bu Ay Prim</th>
            <th>Hedef %</th>
            <th>Bekleyen Görev</th>
            <th>Durum</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_slice($filtered_performance, 0, 10) as $rep): ?>
            <tr>
                <td><?php echo esc_html(get_rep_name($rep)); ?></td>
                <td style="text-align: center; color: #28a745; font-weight: bold;">
                    <?php echo isset($rep->monthly_policies) ? intval($rep->monthly_policies) : 0; ?>
                    <?php if (isset($rep->minimum_policy_count) && $rep->minimum_policy_count > 0): ?>
                        <br><small style="color: #6c757d;">/ <?php echo intval($rep->minimum_policy_count); ?></small>
                    <?php endif; ?>
                </td>
                <td style="text-align: center; color: #17a2b8; font-weight: bold;">
                    <?php echo number_format(isset($rep->monthly_premium) ? floatval($rep->monthly_premium) : 0, 0, ',', '.') . ' ₺'; ?>
                </td>
                <td style="text-align: center;">
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
                <td style="text-align: center;">
                    <?php $pending_tasks = isset($rep->pending_task_count) ? intval($rep->pending_task_count) : 0; ?>
                    <span style="color: <?php echo $pending_tasks > 10 ? '#dc3545' : ($pending_tasks > 5 ? '#ffc107' : '#28a745'); ?>; font-weight: bold;">
                        <?php echo $pending_tasks; ?>
                    </span>
                </td>
                <td style="text-align: center;">
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
<p style="text-align: center; margin: 10px 0; color: #6c757d; font-style: italic;">
    +<?php echo count($filtered_performance) - 10; ?> temsilci daha
</p>
<?php endif; ?>
<?php endif; ?>

<!-- Quick Access Links -->
<h3 class="section-title">Yönetim Paneli Erişim</h3>
<div style="text-align: center; margin: 20px 0;">
    <a href="<?php echo home_url('/temsilci-paneli/'); ?>" class="link-button">Ana Panel</a>
    <a href="<?php echo home_url('/temsilci-paneli/?section=tasks'); ?>" class="link-button">Tüm Görevler</a>
    <a href="<?php echo home_url('/temsilci-paneli/?section=policies'); ?>" class="link-button">Poliçeler</a>
    <a href="<?php echo home_url('/temsilci-paneli/?section=reports'); ?>" class="link-button">Raporlar</a>
</div>

<!-- Summary Footer -->
<div class="stats-box">
    <h4 style="margin: 0 0 10px 0; color: #495057;">Günlük Özet</h4>
    <table style="width: 100%; border: none;">
        <tr>
            <td style="text-align: center; border: none;">
                <div class="stats-number" style="color: #dc3545;">{total_pending_tasks}</div>
                <div class="stats-label">Bekleyen Görev</div>
            </td>
            <td style="text-align: center; border: none;">
                <div class="stats-number" style="color: #f39c12;">{total_expiring_policies}</div>
                <div class="stats-label">Yenilenecek Poliçe</div>
            </td>
            <td style="text-align: center; border: none;">
                <div class="stats-number" style="color: #28a745;">{total_active_representatives}</div>
                <div class="stats-label">Aktif Temsilci</div>
            </td>
        </tr>
    </table>
    <p style="font-size: 12px; color: #6c757d; margin: 10px 0 0 0; text-align: center;">
        Başarılı bir gün geçirin ve ekibinizi yönlendirin.
    </p>
</div>