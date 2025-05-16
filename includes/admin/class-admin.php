<?php
/**
 * Admin sınıfı
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Esen_GT_Admin sınıfı
 */
class Esen_GT_Admin {
    
    /**
     * Sınıf örneği
     *
     * @var Esen_GT_Admin
     */
    private static $instance = null;
    
    /**
     * Sınıf örneğini döndürür
     *
     * @return Esen_GT_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Kurucu metod
     */
    private function __construct() {
        // Admin menüsü ekle
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin stil ve script'lerini ekle
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Admin AJAX işlemleri
        add_action('wp_ajax_esen_gt_refresh_trends', array($this, 'ajax_refresh_trends'));
        
        // Ayarları kaydet
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Admin menüsü ekle
     */
    public function add_admin_menu() {
        // Ana menü
        add_menu_page(
            __('Esen Trends Dashboard', 'esen-trends-dashboard'),
            __('Esen Trends Dashboard', 'esen-trends-dashboard'),
            'edit_posts',
            'esen-trends-dashboard',
            array($this, 'render_admin_page'),
            'dashicons-chart-line',
            30
        );
        
        // Alt menü - Ana sayfa (Ana menüyle aynı sayfa)
        add_submenu_page(
            'esen-trends-dashboard',
            __('Google Trends', 'esen-trends-dashboard'),
            __('Trends', 'esen-trends-dashboard'),
            'edit_posts',
            'esen-trends-dashboard',
            array($this, 'render_admin_page')
        );
        
        // Alt menü - Ayarlar sayfası
        add_submenu_page(
            'esen-trends-dashboard',
            __('Google Trends Settings', 'esen-trends-dashboard'),
            __('Settings', 'esen-trends-dashboard'),
            'manage_options', // Sadece yöneticiler ayarları değiştirebilir
            'esen-trends-dashboard-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Admin sayfasını render et
     */
    public function render_admin_page() {
        // Varsayılan ülke kodunu al
        $default_geo = get_option('esen_gt_default_country', 'TR');
        
        // URL parametrelerini doğrula
        $geo = $default_geo;
        
        // Eğer geo parametresi varsa ve geçerli bir istekse doğrula
        if (isset($_GET['geo'])) {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'esen_gt_filter_nonce')) {
                $geo = sanitize_text_field(wp_unslash($_GET['geo']));
            }
        }
        
        $country_codes = esen_gt_get_country_codes();
        $trends = esen_gt_get_cached_trends($geo);
        
        // Admin sayfası HTML'ini ekle
        include ESEN_GT_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    /**
     * Ayarlar sayfasını render et
     */
    public function render_settings_page() {
        include ESEN_GT_PLUGIN_PATH . 'templates/settings-page.php';
    }
    
    /**
     * Ayarları kaydet
     */
    public function register_settings() {
        // Ayarlar grubu
        register_setting('esen_gt_settings', 'esen_gt_default_country', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('esen_gt_settings', 'esen_gt_show_in_dashboard', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('esen_gt_settings', 'esen_gt_show_in_posts', array('sanitize_callback' => 'sanitize_text_field'));
        
        // Varsayılan ayarları ekle (ilk kurulumda)
        if (false === get_option('esen_gt_default_country')) {
            update_option('esen_gt_default_country', 'TR');
        }
        
        if (false === get_option('esen_gt_show_in_dashboard')) {
            update_option('esen_gt_show_in_dashboard', '1');
        }
        
        if (false === get_option('esen_gt_show_in_posts')) {
            update_option('esen_gt_show_in_posts', '1');
        }
    }
    
    /**
     * Admin stil ve scriptleri ekle
     */
    public function enqueue_admin_assets($hook) {
        // Sadece bizim eklenti sayfalarında yükle
        if ($hook === 'toplevel_page_esen-trends-dashboard' || $hook === 'google-trends_page_esen-trends-dashboard-settings') {
            // CSS
            wp_enqueue_style(
                'esen-gt-admin-styles',
                ESEN_GT_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                ESEN_GT_VERSION
            );
            
            // JavaScript
            wp_enqueue_script(
                'esen-gt-admin-scripts',
                ESEN_GT_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                ESEN_GT_VERSION,
                true
            );
            
            // AJAX için değişkenleri localize et
            wp_localize_script('esen-gt-admin-scripts', 'esenGT', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('esen_gt_nonce'),
                'refreshing' => __('Refreshing...', 'esen-trends-dashboard'),
                'refresh' => __('Refresh', 'esen-trends-dashboard'),
                'error' => __('An error occurred', 'esen-trends-dashboard')
            ));
        }
    }
    
    /**
     * AJAX ile trend verilerini yenile
     */
    public function ajax_refresh_trends() {
        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'esen_gt_nonce')) {
            wp_send_json_error(array('message' => __('Security validation failed', 'esen-trends-dashboard')));
        }
        
        // Yetki kontrolü
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission', 'esen-trends-dashboard')));
        }
        
        $geo = isset($_POST['geo']) ? sanitize_text_field(wp_unslash($_POST['geo'])) : get_option('esen_gt_default_country', 'TR');
        
        // Önbelleği temizle
        delete_transient('esen_gt_trends_' . $geo);
        
        // Yeni verileri al
        $trends = esen_gt_get_trends($geo);
        
        if (is_wp_error($trends)) {
            wp_send_json_error(array('message' => $trends->get_error_message()));
        }
        
        // Verileri önbelleğe al
        set_transient('esen_gt_trends_' . $geo, $trends, HOUR_IN_SECONDS);
        
        // HTML oluştur
        $html = '';
        foreach ($trends as $trend) {
            $html .= esen_gt_get_trend_card_html($trend);
        }
        
        wp_send_json_success(array(
            'html' => $html,
            'message' => __('Trends successfully updated', 'esen-trends-dashboard')
        ));
    }
}

// Sınıfı başlat
Esen_GT_Admin::get_instance(); 