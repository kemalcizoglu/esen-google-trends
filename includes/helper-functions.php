<?php
/**
 * Esen Google Trends - Yardımcı Fonksiyonlar
 *
 * @phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Trends RSS verilerini çeker
 *
 * @param string $geo Ülke kodu (TR, US vb.)
 * @return array|WP_Error Trend verileri veya hata
 */
function esen_gt_get_trends($geo = 'TR') {
    $rss_url = "https://trends.google.com/trending/rss?geo={$geo}";
    $response = wp_remote_get($rss_url);
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return new WP_Error('empty_rss', __('RSS içeriği boş', 'esen-google-trends'));
    }
    
    // SimpleXML ile RSS'i işle
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return new WP_Error('xml_parse_error', __('XML ayrıştırma hatası', 'esen-google-trends'), $errors);
    }
    
    $trends = array();
    
    // RSS öğelerini işle
    if (isset($xml->channel) && isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $title = (string) $item->title;
            $link = (string) $item->link;
            $pubDate = (string) $item->pubDate;
            $description = (string) $item->description;
            
            /**
             * Bu kısım HTML/XML içeriğinden görsel URL'lerini çıkarmak için kullanılır,
             * görüntü yükleme veya görüntüleme işlemi yapmaz. Sadece veri ayıklama işlemidir.
             */
            $image_url = '';
            if (preg_match('/<img.+?src=[\"\'](.+?)[\"\'].*?>/i', $description, $match)) {
                $image_url = $match[1];
            }
            
            // XML namespace'leri kontrol et
            $namespaces = $item->getNamespaces(true);
            
            // Arama hacmi bilgisi (ht:approx_traffic)
            $search_volume = '';
            if (isset($namespaces['ht'])) {
                $ht = $item->children($namespaces['ht']);
                if (isset($ht->approx_traffic)) {
                    $search_volume = (string) $ht->approx_traffic;
                }
            }
            
            // XML'den haber öğelerini çıkar
            $news_items = array();
            
            if (isset($namespaces['ht'])) {
                $ht = $item->children($namespaces['ht']);
                
                // Görsel ve kaynak bilgisi
                $picture = '';
                $picture_source = '';
                if (isset($ht->picture)) {
                    $picture = (string) $ht->picture;
                }
                if (isset($ht->picture_source)) {
                    $picture_source = (string) $ht->picture_source;
                }
                
                if (!$image_url && $picture) {
                    $image_url = $picture;
                }
                
                // İlgili haber öğeleri
                if (isset($ht->news_item)) {
                    foreach ($ht->news_item as $news_item) {
                        if (isset($news_item->news_item_title) && isset($news_item->news_item_url)) {
                            $news_items[] = array(
                                'title' => (string) $news_item->news_item_title,
                                'url' => (string) $news_item->news_item_url,
                                'picture' => (string) $news_item->news_item_picture,
                                'source' => (string) $news_item->news_item_source,
                                'snippet' => (string) $news_item->news_item_snippet
                            );
                        }
                    }
                }
            }
            
            // Haber öğeleri XML namespace'de yoksa, HTML içeriğinden çıkarmayı dene
            if (empty($news_items)) {
                if (preg_match_all('/<a\s+href=[\"\']([^\"\']+?)[\"\'][^>]*>(.*?)<\/a>/is', $description, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        if (strpos($match[1], 'http') === 0) { // URL kontrolü
                            $news_title = wp_strip_all_tags($match[2]);
                            
                            /**
                             * Bu kısım HTML/XML içeriğinden görsel URL'lerini çıkarmak için kullanılır,
                             * görüntü yükleme veya görüntüleme işlemi yapmaz. Sadece veri ayıklama işlemidir.
                             */
                            $news_image = '';
                            if (preg_match('/<img.+?src=[\"\'](.+?)[\"\'].*?>/i', $match[2], $img_match)) {
                                $news_image = $img_match[1];
                            }
                            
                            // Kaynak adını bul
                            $news_source = '';
                            $domain = wp_parse_url($match[1], PHP_URL_HOST);
                            if ($domain) {
                                $domain_parts = explode('.', $domain);
                                if (count($domain_parts) >= 2) {
                                    $news_source = ucfirst($domain_parts[count($domain_parts) - 2]);
                                }
                            }
                            
                            $news_items[] = array(
                                'title' => $news_title,
                                'url' => $match[1],
                                'picture' => $news_image,
                                'source' => $news_source,
                                'snippet' => ''
                            );
                        }
                    }
                }
            }
            
            // Tarihi formatla
            $date = new DateTime($pubDate);
            $formatted_date = $date->format('Y-m-d H:i:s');
            
            $trends[] = array(
                'title' => wp_strip_all_tags($title),
                'link' => $link,
                'image_url' => $image_url,
                'search_volume' => $search_volume,
                'pub_date' => $formatted_date,
                'raw_description' => $description,
                'news_items' => $news_items
            );
        }
    }
    
    return $trends;
}

/**
 * Trend verilerini önbelleğe alma
 *
 * @param string $geo Ülke kodu
 * @return array|WP_Error Trend verileri veya hata
 */
function esen_gt_get_cached_trends($geo = 'TR') {
    $cache_key = 'esen_gt_trends_' . $geo;
    $cached_data = get_transient($cache_key);
    
    if (false !== $cached_data) {
        return $cached_data;
    }
    
    $trends = esen_gt_get_trends($geo);
    
    if (!is_wp_error($trends)) {
        // 1 saat süreyle önbelleğe al
        set_transient($cache_key, $trends, HOUR_IN_SECONDS);
    }
    
    return $trends;
}

/**
 * Ülke kodlarını döndürür
 *
 * @return array Ülke kodları
 */
function esen_gt_get_country_codes() {
    return array(
        'TR' => __('Turkey', 'esen-google-trends'),
        'US' => __('United States', 'esen-google-trends'),
        'DE' => __('Germany', 'esen-google-trends'),
        'FR' => __('France', 'esen-google-trends'),
        'GB' => __('United Kingdom', 'esen-google-trends'),
        'JP' => __('Japan', 'esen-google-trends'),
        'IN' => __('India', 'esen-google-trends'),
        'BR' => __('Brazil', 'esen-google-trends'),
        'CA' => __('Canada', 'esen-google-trends'),
        'AU' => __('Australia', 'esen-google-trends'),
    );
}

/**
 * Admin sayfası için URL oluşturur
 *
 * @param array $args Ek parametreler
 * @return string Admin sayfası URL'si
 */
function esen_gt_admin_url($args = array()) {
    $defaults = array(
        'page' => 'esen-google-trends',
    );
    
    $args = wp_parse_args($args, $defaults);
    
    return add_query_arg($args, admin_url('admin.php'));
}

/**
 * Trend kartı HTML'ini oluşturur
 *
 * @param array $trend Trend verileri
 * @return string HTML içeriği
 */
function esen_gt_get_trend_card_html($trend) {
    $html = '<div class="esen-gt-trend-card">';
    
    // Resim
    if (!empty($trend['image_url'])) {
        $html .= '<div class="esen-gt-trend-image">';
        $html .= esen_gt_external_image(
            $trend['image_url'],
            $trend['title'],
            'esen-gt-trend-img',
            300,
            150
        );
        $html .= '</div>';
    }
    
    // İçerik
    $html .= '<div class="esen-gt-trend-content">';
    $html .= '<h3 class="esen-gt-trend-title">';
    
    // Başlığı link olmadan göster
    $html .= esc_html($trend['title']);
    
    // Arama hacmi
    if (!empty($trend['search_volume'])) {
        $html .= ' <span class="esen-gt-search-volume">' . esc_html($trend['search_volume']) . '</span>';
    }
    
    $html .= '</h3>';
    
    // Tarih
    $html .= '<div class="esen-gt-trend-meta">';
    $html .= '<span class="esen-gt-trend-date">' . esc_html($trend['pub_date']) . '</span>';
    $html .= '</div>';
    
    // İlgili haberler varsa göster
    if (!empty($trend['news_items'])) {
        $html .= '<div class="esen-gt-related-news">';
        $html .= '<h4>' . esc_html__('Related News', 'esen-google-trends') . '</h4>';
        $html .= '<ul class="esen-gt-news-list">';
        
        foreach ($trend['news_items'] as $news_item) {
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
    
    $html .= '</div>'; // .esen-gt-trend-content
    $html .= '</div>'; // .esen-gt-trend-card
    
    return $html;
}

/**
 * Manuel olarak trend öğesine ilgili haber öğeleri ekler
 *
 * @param array $trend Güncellenecek trend verisi
 * @param array $news_items Eklenecek haber öğeleri
 * @return array Güncellenen trend verisi
 */
function esen_gt_add_news_items_manually($trend, $news_items) {
    if (!isset($trend['news_items'])) {
        $trend['news_items'] = array();
    }
    
    // Mevcut haber öğelerini temizle (isteğe bağlı)
    // $trend['news_items'] = array();
    
    // Manuel olarak eklenen haber öğelerini ekle
    foreach ($news_items as $news_item) {
        if (isset($news_item['title']) && isset($news_item['url'])) {
            $trend['news_items'][] = array(
                'title' => sanitize_text_field($news_item['title']),
                'url' => esc_url_raw($news_item['url']),
                'picture' => isset($news_item['picture']) ? esc_url_raw($news_item['picture']) : '',
                'source' => isset($news_item['source']) ? sanitize_text_field($news_item['source']) : '',
                'snippet' => isset($news_item['snippet']) ? sanitize_textarea_field($news_item['snippet']) : ''
            );
        }
    }
    
    return $trend;
}

/**
 * Harici URL'den gelen görseller için güvenli bir şekilde img etiketi oluşturur.
 * Google Trends gibi dış kaynaklardan gelen görseller WordPress medya kütüphanesinde 
 * olmadığı için wp_get_attachment_image() kullanılamaz. Bu fonksiyon, harici görselleri
 * güvenli bir şekilde göstermek için alternatif bir çözüm sunar.
 *
 * @param string $url Görsel URL'si
 * @param string $alt Alt metni
 * @param string $class CSS sınıfı
 * @param int    $width Görsel genişliği
 * @param int    $height Görsel yüksekliği
 * @return string HTML img etiketi
 */
function esen_gt_external_image($url, $alt = '', $class = '', $width = 150, $height = 80) {
    if (empty($url)) {
        return '';
    }
    
    // Harici görsel URL'sini esc_url ile güvenli hale getir
    $safe_url = esc_url($url);
    $safe_alt = esc_attr($alt);
    $safe_class = esc_attr($class);
    
    // WordPress'in önerdiği genişlik ve yükseklik özelliklerini ekle
    $img = sprintf(
        '<img src="%s" alt="%s" class="%s" loading="lazy" width="%d" height="%d">',
        $safe_url,
        $safe_alt,
        $safe_class,
        absint($width),
        absint($height)
    );
    
    return $img;
} 