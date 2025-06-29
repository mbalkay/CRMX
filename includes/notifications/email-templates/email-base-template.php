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
        $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($company_name) . '" style="max-height: 50px; max-width: 150px; width: auto; height: auto; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;">';
    }
    
    return '
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{email_subject}</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                background-color: #f8f9fa;
            }
            .email-container {
                max-width: 700px;
                margin: 20px auto;
                background-color: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            }
            .email-header {
                background: linear-gradient(135deg, ' . $primary_color . ', ' . $primary_color . 'dd);
                color: #ffffff;
                padding: 30px 30px 25px 30px;
                text-align: center;
                position: relative;
            }
            .email-header::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.05\'%3E%3Ccircle cx=\'30\' cy=\'30\' r=\'2\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
                opacity: 0.3;
            }
            .email-header h1 {
                position: relative;
                z-index: 2;
                margin: 0;
                font-size: 26px;
                font-weight: 700;
                letter-spacing: -0.5px;
            }
            .email-content {
                padding: 35px 30px;
            }
            .email-section {
                margin-bottom: 30px;
            }
            .email-content h2 {
                color: ' . $primary_color . ';
                font-size: 22px;
                margin-bottom: 20px;
                border-bottom: 3px solid #e9ecef;
                padding-bottom: 12px;
                font-weight: 600;
            }
            .email-content h3 {
                color: #2c3e50;
                font-size: 18px;
                margin-bottom: 15px;
                font-weight: 600;
            }
            .info-card {
                background-color: #ffffff;
                border: 1px solid #e9ecef;
                border-left: 4px solid ' . $primary_color . ';
                padding: 20px 25px;
                margin: 20px 0;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                padding: 8px 0;
                border-bottom: 1px solid #f1f3f4;
            }
            .info-row:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            .info-label {
                font-weight: 600;
                color: #495057;
                flex: 1;
            }
            .info-value {
                color: #212529;
                font-weight: 500;
                text-align: right;
            }
            .email-footer {
                background-color: #f8f9fa;
                padding: 25px 30px;
                text-align: center;
                color: #6c757d;
                font-size: 14px;
                border-top: 1px solid #e9ecef;
            }
            .button {
                display: inline-block;
                background-color: ' . $primary_color . ';
                color: #ffffff !important;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                margin: 8px 4px;
                transition: background-color 0.3s ease;
            }
            .button:hover {
                background-color: ' . $primary_color . 'dd;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .stat-item {
                text-align: center;
                padding: 15px;
                background: rgba(255, 255, 255, 0.9);
                border-radius: 8px;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .stat-label {
                font-size: 12px;
                opacity: 0.9;
            }
            
            /* Responsive Design */
            @media only screen and (max-width: 600px) {
                .email-container {
                    margin: 10px;
                    border-radius: 8px;
                }
                .email-header, .email-content, .email-footer {
                    padding: 20px 15px;
                }
                .email-content h2 {
                    font-size: 20px;
                }
                .info-row {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 5px;
                }
                .info-value {
                    text-align: left;
                }
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                }
                .button {
                    display: block;
                    margin: 10px 0;
                    text-align: center;
                }
            }
            
            /* Dark mode support */
            @media (prefers-color-scheme: dark) {
                .email-container {
                    background-color: #1e1e1e;
                    color: #ffffff;
                }
                .info-card {
                    background-color: #2a2a2a;
                    border-color: #404040;
                }
                .email-footer {
                    background-color: #2a2a2a;
                    border-color: #404040;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                ' . $logo_html . '
                <h1>' . esc_html($company_name) . '</h1>
            </div>
            <div class="email-content">
                {email_content}
            </div>
            <div class="email-footer">
                <p style="margin-bottom: 10px;">
                    <strong>' . esc_html($company_name) . ' Günlük Bildirim Sistemi</strong>
                </p>
                <p style="margin-bottom: 15px;">
                    Bu e-posta ' . date('d.m.Y H:i') . ' tarihinde otomatik olarak gönderilmiştir.
                </p>
                <p style="font-size: 12px; color: #999;">
                    Bu bildirimleri almak istemiyorsanız, 
                    <a href="' . home_url('/temsilci-paneli/?section=settings') . '" 
                       style="color: ' . $primary_color . ';">ayarlar sayfasından</a> 
                    günlük e-posta bildirimlerini kapatabilirsiniz.
                </p>
            </div>
        </div>
    </body>
    </html>';
}