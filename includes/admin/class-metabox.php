<?php
/**
 * Metabox sınıfı
 *
 * @phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Esen_GT_Metabox sınıfı
 */
class Esen_GT_Metabox {
    
    /**
     * Sınıf örneği
     *
     * @var Esen_GT_Metabox
     */
    private static $instance = null;
    
    /**
     * Sınıf örneğini döndürür
     *
     * @return Esen_GT_Metabox
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
        // Ayarlarda etkinse metabox'ı ekle
        if (get_option('esen_gt_show_in_posts', '1') === '1') {
            // Metabox'ı ekle
            add_action('add_meta_boxes', array($this, 'add_meta_box'));
            
            // Metabox stil ve scriptleri yükle
            add_action('admin_enqueue_scripts', array($this, 'enqueue_metabox_assets'));
            
            // Post kaydedildiğinde meta verisini kaydet
            add_action('save_post', array($this, 'save_meta_box_data'));
            
            // AJAX işlevleri
            add_action('wp_ajax_esen_gt_get_trend_details', array($this, 'ajax_get_trend_details'));
        }
    }
    
    /**
     * Metabox ekle
     */
    public function add_meta_box() {
        // Varsayılan olarak desteklenen içerik türleri
        $post_types = apply_filters('esen_gt_metabox_post_types', array('post'));
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'esen_gt_trends_metabox',
                __('Google Trends', 'esen-trends-dashboard'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Metabox içeriğini oluştur
     *
     * @param WP_Post $post
     */
    public function render_meta_box($post) {
        // Nonce oluştur
        wp_nonce_field('esen_gt_metabox', 'esen_gt_metabox_nonce');
        
        // Ülke kodlarını al
        $country_codes = esen_gt_get_country_codes();
        
        // Varsayılan ülke
        $selected_geo = get_post_meta($post->ID, '_esen_gt_country_code', true);
        if (empty($selected_geo)) {
            $selected_geo = get_option('esen_gt_default_country', 'TR');
        }
        
        // Metabox içeriği
        ?>
        <div class="esen-gt-metabox">
            <div class="esen-gt-metabox-header">
                <select id="esen-gt-metabox-geo" name="esen_gt_country_code">
                    <?php foreach ($country_codes as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($selected_geo, $code); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button esen-gt-refresh-button" data-geo="<?php echo esc_attr($selected_geo); ?>">
                    <?php esc_html_e('Get Trends', 'esen-trends-dashboard'); ?>
                </button>
            </div>
            
            <div class="esen-gt-metabox-content">
                <div id="esen-gt-metabox-trends-container" class="esen-gt-metabox-trends-container">
                    <p class="esen-gt-metabox-loading"><?php esc_html_e('Loading trends...', 'esen-trends-dashboard'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Metabox stil ve scriptleri
     *
     * @param string $hook
     */
    public function enqueue_metabox_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        // Metabox CSS
        wp_enqueue_style(
            'esen-gt-metabox-styles',
            ESEN_GT_PLUGIN_URL . 'assets/css/metabox.css',
            array(),
            ESEN_GT_VERSION
        );
        
        // Metabox JavaScript
        wp_enqueue_script(
            'esen-gt-metabox-scripts',
            ESEN_GT_PLUGIN_URL . 'assets/js/metabox.js',
            array('jquery'),
            ESEN_GT_VERSION,
            true
        );
        
        // AJAX için değişkenleri localize et
        wp_localize_script('esen-gt-metabox-scripts', 'esenGT', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('esen_gt_nonce'),
            'loading' => __('Loading...', 'esen-trends-dashboard'),
            'error' => __('An error occurred', 'esen-trends-dashboard'),
            'noTrends' => __('No trends found', 'esen-trends-dashboard')
        ));
    }
    
    /**
     * Metabox verilerini kaydet
     *
     * @param int $post_id
     */
    public function save_meta_box_data($post_id) {
        // Nonce kontrolü
        if (!isset($_POST['esen_gt_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['esen_gt_metabox_nonce'])), 'esen_gt_metabox')) {
            return;
        }
        
        // Otomatik kaydı kontrol et (otomatik taslak kaydı)
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Kullanıcı izinlerini kontrol et
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Ülke kodu
        if (isset($_POST['esen_gt_country_code'])) {
            $country_code = sanitize_text_field(wp_unslash($_POST['esen_gt_country_code']));
            update_post_meta($post_id, '_esen_gt_country_code', $country_code);
        }
    }
    
    /**
     * AJAX: Trend detaylarını getir
     */
    public function ajax_get_trend_details() {
        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'esen_gt_nonce')) {
            wp_send_json_error(array('message' => __('Security validation failed', 'esen-trends-dashboard')));
        }
        
        $geo = isset($_POST['geo']) ? sanitize_text_field(wp_unslash($_POST['geo'])) : get_option('esen_gt_default_country', 'TR');
        
        // Trendleri al
        $trends = esen_gt_get_cached_trends($geo);
        
        if (is_wp_error($trends)) {
            wp_send_json_error(array('message' => $trends->get_error_message()));
        }
        
        $html = '';
        
        if (empty($trends)) {
            $html = '<p class="esen-gt-no-trends">' . esc_html__('No trends found', 'esen-trends-dashboard') . '</p>';
        } else {
            $html .= '<ul class="esen-gt-metabox-trends-list">';
            
            foreach ($trends as $index => $trend) {
                $html .= '<li class="esen-gt-metabox-trend-item" data-trend="' . esc_attr(json_encode($trend)) . '">';
                
                // Trend başlığı ve arama hacmi
                $html .= '<div class="esen-gt-metabox-trend-title">';
                $html .= esc_html($trend['title']);
                
                if (!empty($trend['search_volume'])) {
                    $html .= ' <span class="esen-gt-search-volume">' . esc_html($trend['search_volume']) . '</span>';
                }
                $html .= '</div>';
                
                // İlgili haberler varsa göster
                if (!empty($trend['news_items'])) {
                    $html .= '<div class="esen-gt-related-news">';
                    $html .= '<h4>' . esc_html__('Related News', 'esen-trends-dashboard') . '</h4>';
                    $html .= '<ul class="esen-gt-news-list">';
                    
                    foreach ($trend['news_items'] as $key => $news_item) {
                        if ($key >= 2) break; // Sadece ilk 2 haberi göster
                        
                        $html .= '<li class="esen-gt-news-item">';
                        
                        // Haber resmi
                        if (!empty($news_item['picture'])) {
                            $html .= '<div class="esen-gt-news-image">';
                            $html .= esen_gt_external_image(
                                $news_item['picture'],
                                $news_item['title'],
                                'esen-gt-news-img',
                                150,
                                80
                            );
                            $html .= '</div>';
                        }
                        
                        // Haber içeriği
                        $html .= '<div class="esen-gt-news-content">';
                        $html .= '<h5 class="esen-gt-news-title">';
                        $html .= '<a href="' . esc_url($news_item['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($news_item['title']) . '</a>';
                        $html .= '</h5>';
                        
                        // Haber kaynağı
                        if (!empty($news_item['source'])) {
                            $html .= '<span class="esen-gt-news-source">' . esc_html($news_item['source']) . '</span>';
                        }
                        
                        $html .= '</div>'; // .esen-gt-news-content
                        $html .= '</li>'; // .esen-gt-news-item
                    }
                    
                    $html .= '</ul>'; // .esen-gt-news-list
                    $html .= '</div>'; // .esen-gt-related-news
                }
                
                // Trend ekle butonu
                $html .= '<div class="esen-gt-metabox-actions">';
                $html .= '<button type="button" class="button esen-gt-insert-trend-button" data-title="' . esc_attr($trend['title']) . '">';
                $html .= esc_html__('Insert Title', 'esen-trends-dashboard');
                $html .= '</button>';
                $html .= '</div>';
                
                $html .= '</li>'; // .esen-gt-metabox-trend-item
            }
            
            $html .= '</ul>'; // .esen-gt-metabox-trends-list
        }
        
        wp_send_json_success(array('html' => $html));
    }
}

// Sınıfı başlat
Esen_GT_Metabox::get_instance(); 