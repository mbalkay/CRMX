<?php
/**
 * Base Email Template for Daily Notifications
 * 
 * @package    Insurance_CRM
 * @subpackage Insurance_CRM/includes/notifications/email-templates
 * @author     Anadolu Birlik
 * @since      1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get enhanced email base template for daily notifications
 */
function insurance_crm_get_daily_email_base_template() {
    $settings = get_option('insurance_crm_settings', array());
    $company_name = isset($settings['company_name']) ? $settings['company_name'] : get_bloginfo('name');
    $primary_color = isset($settings['site_appearance']['primary_color']) ? $settings['site_appearance']['primary_color'] : '#667eea';
    
    // Logo URL with fallback to default logo if not set
    $logo_url = !empty($settings['site_appearance']['login_logo']) 
        ? $settings['site_appearance']['login_logo'] 
        : plugins_url('assets/images/Insurance-logo.png', dirname(dirname(dirname(__FILE__))));
    
    $logo_html = '';
    if (!empty($logo_url)) {
        $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($company_name) . '" style="max-height: 20px !important; max-width: 40px !important; width: 40px !important; height: 20px !important; margin-bottom: 5px; display: block; margin-left: auto; margin-right: auto;">';
    }
    
    return '
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{email_subject}</title>
        <style>
            body {
                margin: 0 !important;
                padding: 0 !important;
                font-family: Arial, sans-serif !important;
                line-height: 1.4 !important;
                color: #333333 !important;
                background-color: #f5f5f5 !important;
            }
            .email-container {
                max-width: 600px !important;
                margin: 0 auto !important;
                background-color: #ffffff !important;
                border: 1px solid #dddddd !important;
            }
            .email-header {
                background-color: ' . $primary_color . ' !important;
                color: #ffffff !important;
                padding: 15px !important;
                text-align: center !important;
            }
            .email-header h1 {
                margin: 0 !important;
                font-size: 18px !important;
                font-weight: bold !important;
            }
            .email-content {
                padding: 20px !important;
            }
            .section-title {
                color: ' . $primary_color . ' !important;
                font-size: 16px !important;
                font-weight: bold !important;
                margin: 20px 0 10px 0 !important;
                border-bottom: 2px solid #e9ecef !important;
                padding-bottom: 5px !important;
            }
            .info-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 15px 0 !important;
                border: 1px solid #dddddd !important;
            }
            .info-table th {
                background-color: #f8f9fa !important;
                color: #333333 !important;
                font-weight: bold !important;
                padding: 8px !important;
                text-align: left !important;
                border: 1px solid #dddddd !important;
                font-size: 12px !important;
            }
            .info-table td {
                padding: 8px !important;
                border: 1px solid #dddddd !important;
                color: #333333 !important;
                font-size: 12px !important;
                background-color: #ffffff !important;
            }
            .stats-box {
                background-color: #f8f9fa !important;
                border: 1px solid #dddddd !important;
                padding: 15px !important;
                margin: 15px 0 !important;
                text-align: center !important;
            }
            .stats-number {
                font-size: 24px !important;
                font-weight: bold !important;
                color: ' . $primary_color . ' !important;
                margin: 5px 0 !important;
            }
            .stats-label {
                font-size: 12px !important;
                color: #666666 !important;
            }
            .task-item {
                background-color: #fff5f5 !important;
                border-left: 3px solid #dc3545 !important;
                padding: 10px !important;
                margin: 8px 0 !important;
            }
            .policy-item {
                background-color: #fff8e1 !important;
                border-left: 3px solid #f39c12 !important;
                padding: 10px !important;
                margin: 8px 0 !important;
            }
            .email-footer {
                background-color: #f8f9fa !important;
                padding: 15px !important;
                text-align: center !important;
                color: #666666 !important;
                font-size: 11px !important;
                border-top: 1px solid #dddddd !important;
            }
            .link-button {
                display: inline-block !important;
                background-color: ' . $primary_color . ' !important;
                color: #ffffff !important;
                padding: 8px 16px !important;
                text-decoration: none !important;
                border-radius: 3px !important;
                font-size: 12px !important;
                margin: 5px !important;
            }
        </style>
    </head>
    <body>
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5;">
            <tr>
                <td align="center" style="padding: 20px 10px;">
                    <table class="email-container" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td class="email-header">
                                ' . $logo_html . '
                                <h1>' . esc_html($company_name) . '</h1>
                            </td>
                        </tr>
                        <tr>
                            <td class="email-content">
                                {email_content}
                            </td>
                        </tr>
                        <tr>
                            <td class="email-footer">
                                <p style="margin: 5px 0;"><strong>' . esc_html($company_name) . ' Günlük Bildirim Sistemi</strong></p>
                                <p style="margin: 5px 0;">Bu e-posta ' . date('d.m.Y H:i') . ' tarihinde otomatik olarak gönderilmiştir.</p>
                                <p style="margin: 5px 0;">
                                    Bu bildirimleri almak istemiyorsanız, 
                                    <a href="' . home_url('/temsilci-paneli/?section=settings') . '" style="color: ' . $primary_color . ';">ayarlar sayfasından</a> 
                                    günlük e-posta bildirimlerini kapatabilirsiniz.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}