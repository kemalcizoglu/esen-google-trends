<?php
/**
 * RSS API sınıfı
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Esen_GT_RSS_API sınıfı
 */
class Esen_GT_RSS_API {
    
    /**
     * Sınıf örneği
     *
     * @var Esen_GT_RSS_API
     */
    private static $instance = null;
    
    /**
     * Sınıf örneğini döndürür
     *
     * @return Esen_GT_RSS_API
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
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * REST API rotalarını kaydet
     */
    public function register_rest_routes() {
        register_rest_route('esen-gt/v1', '/trends', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trends'),
            'permission_callback' => '__return_true',
            'args' => array(
                'geo' => array(
                    'default' => 'TR',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'refresh' => array(
                    'default' => false,
                    'sanitize_callback' => function($param) {
                        return filter_var($param, FILTER_VALIDATE_BOOLEAN);
                    },
                ),
            ),
        ));
    }
    
    /**
     * Trend verilerini API ile döndür
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_trends($request) {
        $geo = $request->get_param('geo');
        $refresh = $request->get_param('refresh');
        
        if ($refresh) {
            $trends = esen_gt_get_trends($geo);
            
            if (!is_wp_error($trends)) {
                $cache_key = 'esen_gt_trends_' . $geo;
                set_transient($cache_key, $trends, HOUR_IN_SECONDS);
            }
        } else {
            $trends = esen_gt_get_cached_trends($geo);
        }
        
        if (is_wp_error($trends)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $trends->get_error_message(),
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $trends,
        ), 200);
    }
}

// Sınıfı başlat
Esen_GT_RSS_API::get_instance(); 