<?php
/**
 * Dashboard Widget sınıfı
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Esen_GT_Dashboard_Widget sınıfı
 */
class Esen_GT_Dashboard_Widget {
    
    /**
     * Sınıf örneği
     *
     * @var Esen_GT_Dashboard_Widget
     */
    private static $instance = null;
    
    /**
     * Sınıf örneğini döndürür
     *
     * @return Esen_GT_Dashboard_Widget
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
        // Ayarlarda etkinse widget'i ekle
        if (get_option('esen_gt_show_in_dashboard', '1') === '1') {
            // Dashboard widget'i ekle - Daha yüksek bir öncelikle (önceden kaldırılan dashboard widgetlerinden sonra çalışması için)
            add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'), 100000);
            
            // Dashboard widget için stil ve script'leri ekle
            add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        }
    }
    
    /**
     * Dashboard widget'i ekle
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'esen_gt_dashboard_widget',
            __('Google Trends', 'esen-google-trends'),
            array($this, 'render_dashboard_widget'),
            array($this, 'dashboard_widget_control')
        );
    }
    
    /**
     * Dashboard widget stil ve scriptleri ekle
     */
    public function enqueue_dashboard_assets($hook) {
        if ($hook !== 'index.php') {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'esen-gt-dashboard-styles',
            ESEN_GT_PLUGIN_URL . 'assets/css/dashboard-widget.css',
            array(),
            ESEN_GT_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'esen-gt-dashboard-scripts',
            ESEN_GT_PLUGIN_URL . 'assets/js/dashboard-widget.js',
            array('jquery'),
            ESEN_GT_VERSION,
            true
        );
        
        // AJAX için değişkenleri localize et
        wp_localize_script('esen-gt-dashboard-scripts', 'esenGT', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('esen_gt_nonce'),
            'refreshing' => __('Yenileniyor...', 'esen-google-trends'),
            'refresh' => __('Yenile', 'esen-google-trends'),
            'error' => __('Bir hata oluştu', 'esen-google-trends'),
            'loading' => __('Yükleniyor...', 'esen-google-trends')
        ));
    }
    
    /**
     * Dashboard widget içeriğini render et
     */
    public function render_dashboard_widget() {
        $options = $this->get_dashboard_widget_options();
        $geo = $options['geo'];
        $limit = $options['limit'];
        
        $trends = esen_gt_get_cached_trends($geo);
        
        if (is_wp_error($trends)) {
            echo '<p class="esen-gt-error">' . esc_html($trends->get_error_message()) . '</p>';
            return;
        }
        
        // Limiti uygula
        $trends = array_slice($trends, 0, $limit);
        
        echo '<div class="esen-gt-dashboard-widget">';
        
        // Ülke seçimi ve yenileme butonu
        echo '<div class="esen-gt-dashboard-header">';
        
        echo '<select id="esen-gt-geo-select" class="esen-gt-geo-select">';
        foreach (esen_gt_get_country_codes() as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($geo, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        
        echo '<button type="button" class="button esen-gt-refresh-button" data-geo="' . esc_attr($geo) . '">' . esc_html__('Refresh', 'esen-google-trends') . '</button>';
        
        echo '</div>'; // .esen-gt-dashboard-header
        
        // Trendler (maksimum yükseklikle sınırlandırılmış)
        echo '<div id="esen-gt-trends-container" class="esen-gt-trends-container">';
        
        foreach ($trends as $trend) {
            echo wp_kses_post(esen_gt_get_trend_card_html($trend));
        }
        
        echo '</div>'; // .esen-gt-trends-container
        
        // Eklenti ana sayfasına giden buton
        echo '<div class="esen-gt-dashboard-footer">';
        echo '<a href="' . esc_url(esen_gt_admin_url()) . '" class="button button-primary esen-gt-view-all-button" rel="noopener noreferrer">' . esc_html__('View All Trends', 'esen-google-trends') . '</a>';
        echo '</div>';
        
        echo '</div>'; // .esen-gt-dashboard-widget
    }
    
    /**
     * Dashboard widget ayarları
     */
    public function dashboard_widget_control() {
        // Ayarları kaydet
        if (isset($_POST['esen_gt_dashboard_submit'])) {
            // Güvenlik kontrolü
            check_admin_referer('esen_gt_dashboard_widget', 'esen_gt_dashboard_nonce');
            
            $options = $this->get_dashboard_widget_options();
            
            if (isset($_POST['esen_gt_geo'])) {
                $options['geo'] = sanitize_text_field(wp_unslash($_POST['esen_gt_geo']));
            }
            
            if (isset($_POST['esen_gt_limit'])) {
                $options['limit'] = absint(wp_unslash($_POST['esen_gt_limit']));
            }
            
            update_option('esen_gt_dashboard_widget_options', $options);
        }
        
        // Ayarları getir
        $options = $this->get_dashboard_widget_options();
        $geo = $options['geo'];
        $limit = $options['limit'];
        
        // Ayarlar formunu göster
        ?>
        <?php wp_nonce_field('esen_gt_dashboard_widget', 'esen_gt_dashboard_nonce'); ?>
        <p>
            <label for="esen_gt_geo"><?php esc_html_e('Country:', 'esen-google-trends'); ?></label>
            <select id="esen_gt_geo" name="esen_gt_geo" class="widefat">
                <?php foreach (esen_gt_get_country_codes() as $code => $name) : ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($geo, $code); ?>><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="esen_gt_limit"><?php esc_html_e('Number of trends to display:', 'esen-google-trends'); ?></label>
            <input type="number" id="esen_gt_limit" name="esen_gt_limit" value="<?php echo esc_attr($limit); ?>" min="1" max="20" class="widefat" />
        </p>
        <input type="hidden" name="esen_gt_dashboard_submit" value="1" />
        <?php
    }
    
    /**
     * Dashboard widget ayarlarını getir
     *
     * @return array Ayarlar
     */
    private function get_dashboard_widget_options() {
        $defaults = array(
            'geo' => get_option('esen_gt_default_country', 'TR'),
            'limit' => 5,
        );
        
        $options = get_option('esen_gt_dashboard_widget_options', $defaults);
        
        return wp_parse_args($options, $defaults);
    }
}

// Sınıfı başlat
Esen_GT_Dashboard_Widget::get_instance(); 