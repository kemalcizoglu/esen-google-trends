<?php
/**
 * Plugin Name: Esen Google Trends
 * Plugin URI: https://github.com/kemalcizoglu/esen-google-trends
 * Description: Display Google Trends RSS data directly in your WordPress dashboard and post editor. Access trending topics to optimize your content strategy.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Kemal CIZOĞLU
 * Author URI: https://cizoglubilisi.com
 * Text Domain: esen-google-trends
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Eklenti sınıfını tanımla
class Esen_Google_Trends {
    
    // Sınıf örneği
    private static $instance = null;
    
    // Sınıf örneğini döndür (Singleton)
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Kurucu metod
    private function __construct() {
        // Eklenti sabitleri
        $this->define_constants();
        
        // Çekirdek dosyaları dahil et
        $this->includes();
        
        // Kancaları tanımla
        $this->init_hooks();
    }
    
    // Sabitleri tanımla
    private function define_constants() {
        define('ESEN_GT_PLUGIN_PATH', plugin_dir_path(__FILE__));
        define('ESEN_GT_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('ESEN_GT_VERSION', '1.0.0');
        define('ESEN_GT_RSS_URL', 'https://trends.google.com/trending/rss?geo=TR');
    }
    
    // Gerekli dosyaları dahil et
    private function includes() {
        // Yardımcı fonksiyonlar
        require_once ESEN_GT_PLUGIN_PATH . 'includes/helper-functions.php';
        
        // Admin sınıfı
        require_once ESEN_GT_PLUGIN_PATH . 'includes/admin/class-admin.php';
        
        // Dashboard widget sınıfı
        require_once ESEN_GT_PLUGIN_PATH . 'includes/admin/class-dashboard-widget.php';
        
        // Metabox sınıfı
        require_once ESEN_GT_PLUGIN_PATH . 'includes/admin/class-metabox.php';
        
        // API sınıfı
        require_once ESEN_GT_PLUGIN_PATH . 'includes/api/class-rss-api.php';
    }
    
    // Kancaları başlat
    private function init_hooks() {
        // Aktivasyon ve deaktivasyon kancaları
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Dil dosyalarını yükle
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    // Eklenti aktif edildiğinde çalıştırılacak işlemler
    public function activate() {
        // Burada aktivasyon işlemleri yapılabilir
    }
    
    // Eklenti devre dışı bırakıldığında çalıştırılacak işlemler
    public function deactivate() {
        // Burada deaktivasyon işlemleri yapılabilir
    }
    
    // Dil dosyalarını yükle
    public function load_textdomain() {
        load_plugin_textdomain('esen-google-trends', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Eklentiyi başlat
function esen_google_trends() {
    return Esen_Google_Trends::get_instance();
}

// Eklentiyi başlat
esen_google_trends(); 