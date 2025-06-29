<?php
/**
 * Enhanced Frontend Controller
 * Optimized asset management with critical CSS, lazy loading, and performance monitoring
 * 
 * @package Insurance_CRM
 * @version 2.0.0
 * @since 1.9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class Insurance_CRM_Enhanced_Frontend_Controller {
    
    private $version;
    private $plugin_name;
    private $asset_cache_buster;
    private $critical_css_loaded = false;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->asset_cache_buster = get_option('insurance_crm_asset_version', $version);
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_optimized_assets'));
        add_action('wp_head', array($this, 'add_critical_css'), 1);
        add_action('wp_head', array($this, 'add_preload_hints'), 2);
        add_action('wp_head', array($this, 'add_performance_monitoring'), 3);
        add_action('wp_footer', array($this, 'add_lazy_load_scripts'), 99);
        
        // PWA support
        add_action('wp_head', array($this, 'add_pwa_meta'));
        add_action('init', array($this, 'handle_service_worker'));
        
        // Performance optimizations
        add_filter('script_loader_tag', array($this, 'optimize_script_tags'), 10, 3);
        add_filter('style_loader_tag', array($this, 'optimize_style_tags'), 10, 4);
    }
    
    /**
     * Enqueue optimized assets with conditional loading
     */
    public function enqueue_optimized_assets() {
        // Only load on CRM pages
        if (!$this->is_crm_page()) {
            return;
        }
        
        $this->enqueue_core_assets();
        $this->enqueue_page_specific_assets();
        $this->enqueue_real_time_assets();
        $this->localize_scripts();
    }
    
    /**
     * Check if current page is a CRM page
     */
    private function is_crm_page() {
        global $post;
        
        // Check for representative panel page
        if (is_page('temsilci-paneli')) {
            return true;
        }
        
        // Check for shortcodes
        if (isset($post->post_content) && has_shortcode($post->post_content, 'insurance_crm_panel')) {
            return true;
        }
        
        // Check for admin pages
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'insurance-crm') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Enqueue core assets
     */
    private function enqueue_core_assets() {
        // jQuery and core dependencies
        wp_enqueue_script('jquery');
        
        // Modern CSS with critical path optimization
        if (!$this->critical_css_loaded) {
            wp_enqueue_style(
                'insurance-crm-critical',
                $this->get_asset_url('css/admin.critical.css'),
                array(),
                $this->asset_cache_buster,
                'all'
            );
        }
        
        // Main stylesheet with media query optimization
        wp_enqueue_style(
            'insurance-crm-main',
            $this->get_asset_url('css/representative-panel-global.min.css'),
            array(),
            $this->asset_cache_buster,
            'screen'
        );
        
        // Core JavaScript with defer loading
        wp_enqueue_script(
            'insurance-crm-core',
            $this->get_asset_url('js/representative-panel.min.js'),
            array('jquery'),
            $this->asset_cache_buster,
            true
        );
        
        // Module loader for dynamic imports
        wp_enqueue_script(
            'insurance-crm-loader',
            $this->get_asset_url('js/module-loader.js'),
            array(),
            $this->asset_cache_buster,
            true
        );
    }
    
    /**
     * Enqueue page-specific assets
     */
    private function enqueue_page_specific_assets() {
        $current_view = $this->get_current_view();
        
        switch ($current_view) {
            case 'dashboard':
                $this->enqueue_dashboard_assets();
                break;
                
            case 'representative-panel':
                $this->enqueue_representative_panel_assets();
                break;
                
            case 'admin':
                $this->enqueue_admin_assets();
                break;
        }
    }
    
    /**
     * Enqueue dashboard-specific assets
     */
    private function enqueue_dashboard_assets() {
        // Chart.js for dashboard widgets
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            array(),
            '4.4.0',
            true
        );
        
        // Dashboard widgets
        wp_enqueue_script(
            'insurance-crm-dashboard',
            $this->get_asset_url('js/dashboard-widgets.min.js'),
            array('jquery', 'chartjs'),
            $this->asset_cache_buster,
            true
        );
        
        // Dashboard-specific styles
        wp_enqueue_style(
            'insurance-crm-dashboard',
            $this->get_asset_url('css/dashboard-updates.css'),
            array('insurance-crm-main'),
            $this->asset_cache_buster
        );
    }
    
    /**
     * Enqueue representative panel assets
     */
    private function enqueue_representative_panel_assets() {
        // jQuery UI for datepickers and interactions
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style(
            'jquery-ui-theme',
            'https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css',
            array(),
            '1.13.2'
        );
        
        // Representative panel styles
        wp_enqueue_style(
            'insurance-crm-representative',
            $this->get_asset_url('public/css/representative-panel.min.css'),
            array('insurance-crm-main'),
            $this->asset_cache_buster
        );
        
        // Representative panel JavaScript
        wp_enqueue_script(
            'insurance-crm-representative',
            $this->get_asset_url('public/js/representative-panel.min.js'),
            array('jquery', 'jquery-ui-datepicker'),
            $this->asset_cache_buster,
            true
        );
    }
    
    /**
     * Enqueue admin-specific assets
     */
    private function enqueue_admin_assets() {
        // Admin styles
        wp_enqueue_style(
            'insurance-crm-admin',
            $this->get_asset_url('css/admin-optimized.css'),
            array(),
            $this->asset_cache_buster
        );
        
        // Admin JavaScript
        wp_enqueue_script(
            'insurance-crm-admin',
            $this->get_asset_url('js/admin-core.bundle.js'),
            array('jquery'),
            $this->asset_cache_buster,
            true
        );
    }
    
    /**
     * Enqueue real-time assets
     */
    private function enqueue_real_time_assets() {
        // Real-time announcements
        wp_enqueue_style(
            'insurance-crm-realtime',
            $this->get_asset_url('css/realtime-announcements.css'),
            array(),
            $this->asset_cache_buster
        );
        
        wp_enqueue_script(
            'insurance-crm-realtime',
            $this->get_asset_url('js/realtime-announcements.js'),
            array('jquery'),
            $this->asset_cache_buster,
            true
        );
        
        // Service worker for push notifications
        if ($this->should_load_service_worker()) {
            wp_enqueue_script(
                'insurance-crm-sw-register',
                $this->get_asset_url('js/sw-register.js'),
                array(),
                $this->asset_cache_buster,
                true
            );
        }
    }
    
    /**
     * Localize scripts with configuration
     */
    private function localize_scripts() {
        // Core configuration
        wp_localize_script('insurance-crm-core', 'insuranceCrmConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('insurance_crm_nonce'),
            'assetsUrl' => $this->get_assets_base_url(),
            'userId' => get_current_user_id(),
            'userRoles' => wp_get_current_user()->roles ?? array(),
            'pageType' => $this->get_current_view(),
            'version' => $this->version,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
        
        // Real-time configuration
        if (wp_script_is('insurance-crm-realtime', 'enqueued')) {
            wp_localize_script('insurance-crm-realtime', 'insuranceCrmRealtime', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('insurance_crm_realtime_nonce'),
                'userId' => get_current_user_id(),
                'pollInterval' => apply_filters('insurance_crm_poll_interval', 30000),
                'enableSSE' => apply_filters('insurance_crm_enable_sse', true),
                'enablePush' => apply_filters('insurance_crm_enable_push', true),
                'vapidPublicKey' => get_option('insurance_crm_vapid_public_key', ''),
                'sounds' => array(
                    'notification' => $this->get_asset_url('sounds/notification.mp3'),
                    'urgent' => $this->get_asset_url('sounds/urgent.mp3')
                )
            ));
        }
        
        // Performance monitoring configuration
        wp_localize_script('insurance-crm-core', 'insuranceCrmPerformance', array(
            'enableMonitoring' => get_option('insurance_crm_enable_performance_monitoring', true),
            'sampleRate' => apply_filters('insurance_crm_performance_sample_rate', 0.1),
            'endpoint' => admin_url('admin-ajax.php?action=insurance_crm_performance_log')
        ));
    }
    
    /**
     * Add critical CSS inline for above-the-fold content
     */
    public function add_critical_css() {
        if (!$this->is_crm_page() || $this->critical_css_loaded) {
            return;
        }
        
        $critical_css_file = plugin_dir_path(__FILE__) . '../../assets/css/admin.critical.css';
        
        if (file_exists($critical_css_file)) {
            $critical_css = file_get_contents($critical_css_file);
            if (!empty($critical_css)) {
                echo '<style id="insurance-crm-critical-css">' . $critical_css . '</style>';
                $this->critical_css_loaded = true;
            }
        }
    }
    
    /**
     * Add preload hints for performance
     */
    public function add_preload_hints() {
        if (!$this->is_crm_page()) {
            return;
        }
        
        // Preload critical assets
        $preload_assets = array(
            $this->get_asset_url('js/representative-panel.min.js') => 'script',
            $this->get_asset_url('css/representative-panel-global.min.css') => 'style',
            $this->get_asset_url('fonts/dashicons.woff2') => 'font'
        );
        
        foreach ($preload_assets as $asset_url => $asset_type) {
            if ($asset_type === 'font') {
                echo '<link rel="preload" href="' . esc_url($asset_url) . '" as="' . $asset_type . '" type="font/woff2" crossorigin>';
            } else {
                echo '<link rel="preload" href="' . esc_url($asset_url) . '" as="' . $asset_type . '">';
            }
        }
        
        // DNS prefetch for external resources
        echo '<link rel="dns-prefetch" href="//code.jquery.com">';
        echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">';
    }
    
    /**
     * Add performance monitoring script
     */
    public function add_performance_monitoring() {
        if (!$this->is_crm_page() || !get_option('insurance_crm_enable_performance_monitoring', true)) {
            return;
        }
        
        ?>
        <script>
        // Performance monitoring
        (function() {
            'use strict';
            
            const perfMonitor = {
                metrics: {
                    navigationStart: performance.timing.navigationStart,
                    domContentLoaded: 0,
                    windowLoaded: 0,
                    firstPaint: 0,
                    firstContentfulPaint: 0
                },
                
                init() {
                    this.measurePageLoad();
                    this.measurePaintTiming();
                    this.setupErrorTracking();
                },
                
                measurePageLoad() {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', () => {
                            this.metrics.domContentLoaded = Date.now() - this.metrics.navigationStart;
                        });
                    }
                    
                    window.addEventListener('load', () => {
                        this.metrics.windowLoaded = Date.now() - this.metrics.navigationStart;
                        this.sendMetrics();
                    });
                },
                
                measurePaintTiming() {
                    if ('PerformanceObserver' in window) {
                        const observer = new PerformanceObserver((list) => {
                            for (const entry of list.getEntries()) {
                                if (entry.name === 'first-paint') {
                                    this.metrics.firstPaint = entry.startTime;
                                } else if (entry.name === 'first-contentful-paint') {
                                    this.metrics.firstContentfulPaint = entry.startTime;
                                }
                            }
                        });
                        
                        observer.observe({ entryTypes: ['paint'] });
                    }
                },
                
                setupErrorTracking() {
                    window.addEventListener('error', (e) => {
                        this.logError('JavaScript Error', e.message, e.filename, e.lineno);
                    });
                    
                    window.addEventListener('unhandledrejection', (e) => {
                        this.logError('Unhandled Promise Rejection', e.reason);
                    });
                },
                
                logError(type, message, file = '', line = 0) {
                    if (typeof insuranceCrmPerformance !== 'undefined' && Math.random() < insuranceCrmPerformance.sampleRate) {
                        fetch(insuranceCrmPerformance.endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                type: 'error',
                                error_type: type,
                                message: message,
                                file: file,
                                line: line,
                                user_agent: navigator.userAgent,
                                url: window.location.href,
                                timestamp: Date.now()
                            })
                        }).catch(() => {}); // Silently fail
                    }
                },
                
                sendMetrics() {
                    if (typeof insuranceCrmPerformance !== 'undefined' && Math.random() < insuranceCrmPerformance.sampleRate) {
                        fetch(insuranceCrmPerformance.endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                type: 'performance',
                                metrics: this.metrics,
                                user_agent: navigator.userAgent,
                                url: window.location.href,
                                timestamp: Date.now()
                            })
                        }).catch(() => {}); // Silently fail
                    }
                }
            };
            
            perfMonitor.init();
        })();
        </script>
        <?php
    }
    
    /**
     * Add lazy loading scripts
     */
    public function add_lazy_load_scripts() {
        if (!$this->is_crm_page()) {
            return;
        }
        
        ?>
        <script>
        // Lazy loading and progressive enhancement
        (function() {
            'use strict';
            
            // Intersection Observer for lazy loading
            if ('IntersectionObserver' in window) {
                const lazyImages = document.querySelectorAll('img[data-src]');
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                lazyImages.forEach(img => imageObserver.observe(img));
            }
            
            // Progressive enhancement for form validation
            const forms = document.querySelectorAll('.insurance-crm-form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!this.checkValidity()) {
                        e.preventDefault();
                        this.classList.add('was-validated');
                    }
                });
            });
            
            // Service Worker registration
            if ('serviceWorker' in navigator && typeof insuranceCrmConfig !== 'undefined') {
                navigator.serviceWorker.register('/wp-content/plugins/insurance-crm/assets/js/sw.js')
                    .then(registration => {
                        console.log('SW registered:', registration);
                    })
                    .catch(error => {
                        console.log('SW registration failed:', error);
                    });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Add PWA meta tags
     */
    public function add_pwa_meta() {
        if (!$this->is_crm_page()) {
            return;
        }
        
        ?>
        <meta name="theme-color" content="#2271b1">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Insurance CRM">
        <link rel="apple-touch-icon" href="<?php echo $this->get_asset_url('images/icon-192x192.png'); ?>">
        <link rel="manifest" href="<?php echo home_url('/wp-content/plugins/insurance-crm/assets/manifest.json'); ?>">
        <?php
    }
    
    /**
     * Handle service worker requests
     */
    public function handle_service_worker() {
        if (isset($_GET['insurance_crm_sw']) && $_GET['insurance_crm_sw'] === '1') {
            header('Content-Type: application/javascript');
            header('Cache-Control: no-cache');
            
            $sw_file = plugin_dir_path(__FILE__) . '../../assets/js/sw.js';
            if (file_exists($sw_file)) {
                readfile($sw_file);
            }
            exit;
        }
    }
    
    /**
     * Optimize script tags
     */
    public function optimize_script_tags($tag, $handle, $src) {
        // Add defer to non-critical scripts
        $defer_scripts = array(
            'insurance-crm-dashboard',
            'insurance-crm-realtime',
            'chartjs'
        );
        
        if (in_array($handle, $defer_scripts)) {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
        
        // Add async to non-blocking scripts
        $async_scripts = array(
            'insurance-crm-sw-register'
        );
        
        if (in_array($handle, $async_scripts)) {
            $tag = str_replace('<script ', '<script async ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimize style tags
     */
    public function optimize_style_tags($tag, $handle, $href, $media) {
        // Add media queries for non-critical styles
        $print_styles = array(
            'insurance-crm-print'
        );
        
        if (in_array($handle, $print_styles)) {
            $tag = str_replace("media='all'", "media='print'", $tag);
        }
        
        return $tag;
    }
    
    /**
     * Utility methods
     */
    private function get_current_view() {
        if (is_admin()) {
            return 'admin';
        }
        
        if (is_page('temsilci-paneli')) {
            return 'representative-panel';
        }
        
        $view = get_query_var('view', '');
        if ($view === 'dashboard') {
            return 'dashboard';
        }
        
        return 'frontend';
    }
    
    private function get_asset_url($path) {
        return plugin_dir_url(__FILE__) . '../../assets/' . $path;
    }
    
    private function get_assets_base_url() {
        return plugin_dir_url(__FILE__) . '../../assets';
    }
    
    private function should_load_service_worker() {
        return apply_filters('insurance_crm_enable_service_worker', true) && 
               !is_admin() && 
               is_ssl();
    }
}

// Initialize enhanced frontend controller
if (!class_exists('Insurance_CRM_Frontend_Controller')) {
    class Insurance_CRM_Frontend_Controller extends Insurance_CRM_Enhanced_Frontend_Controller {
        // Backward compatibility wrapper
    }
}