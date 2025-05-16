<?php
/**
 * Admin sayfası şablonu
 * 
 * @var string $geo Seçili ülke kodu
 * @var array $country_codes Ülke kodları
 * @var array|WP_Error $trends Trend verileri
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap esen-gt-admin-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="esen-gt-admin-header">
        <div class="esen-gt-admin-header-filters">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="esen-trends-dashboard">
                <?php wp_nonce_field('esen_gt_filter_nonce', '_wpnonce', false); ?>
                
                <select name="geo" id="esen-gt-geo-filter">
                    <?php foreach ($country_codes as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($geo, $code); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="button"><?php esc_html_e('Filter', 'esen-trends-dashboard'); ?></button>
            </form>
        </div>
        
        <div class="esen-gt-admin-header-actions">
            <button type="button" class="button esen-gt-refresh-button" data-geo="<?php echo esc_attr($geo); ?>">
                <?php esc_html_e('Refresh Trends', 'esen-trends-dashboard'); ?>
            </button>
        </div>
    </div>
    
    <div class="esen-gt-admin-content">
        <div id="esen-gt-trends-container" class="esen-gt-trends-container">
            <?php if (is_wp_error($trends)) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($trends->get_error_message()); ?></p>
                </div>
            <?php elseif (empty($trends)) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No trends found', 'esen-trends-dashboard'); ?></p>
                </div>
            <?php else : ?>
                <div class="esen-gt-trends-grid">
                    <?php foreach ($trends as $trend) : ?>
                        <?php echo wp_kses_post(esen_gt_get_trend_card_html($trend)); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="esen-gt-admin-footer">
        <div class="esen-gt-admin-footer-info">
            <p>
                <?php
                printf(
                    /* translators: %s: Country name */
                    esc_html__('Google Trends data is shown for %s.', 'esen-trends-dashboard'),
                    '<strong>' . esc_html($country_codes[$geo]) . '</strong>'
                );
                ?>
                <?php esc_html_e('Data is cached for one hour.', 'esen-trends-dashboard'); ?>
            </p>
        </div>
        
        <div class="esen-gt-admin-footer-links">
            <a href="https://trends.google.com/trending?geo=<?php echo esc_attr($geo); ?>&hl=tr" target="_blank" class="button" rel="noopener noreferrer">
                <?php esc_html_e('Visit Google Trends', 'esen-trends-dashboard'); ?>
            </a>
        </div>
    </div>
</div> 