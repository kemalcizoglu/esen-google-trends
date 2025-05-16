<?php
/**
 * Settings page template
 */

// Güvenlik için doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap esen-gt-settings-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('esen_gt_settings'); ?>
        <?php do_settings_sections('esen_gt_settings'); ?>
        
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Default Country', 'esen-trends-dashboard'); ?></th>
                <td>
                    <select name="esen_gt_default_country">
                        <?php 
                        $country_codes = esen_gt_get_country_codes();
                        $selected = get_option('esen_gt_default_country', 'TR');
                        
                        foreach ($country_codes as $code => $name) : 
                        ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($selected, $code); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select the default country for Google Trends data.', 'esen-trends-dashboard'); ?></p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Dashboard Widget', 'esen-trends-dashboard'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="esen_gt_show_in_dashboard" value="1" <?php checked(get_option('esen_gt_show_in_dashboard'), '1'); ?> />
                        <?php esc_html_e('Show Google Trends in Dashboard', 'esen-trends-dashboard'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Enable or disable the Google Trends widget on the WordPress dashboard.', 'esen-trends-dashboard'); ?></p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Post Editor', 'esen-trends-dashboard'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="esen_gt_show_in_posts" value="1" <?php checked(get_option('esen_gt_show_in_posts'), '1'); ?> />
                        <?php esc_html_e('Show Google Trends in Post Editor', 'esen-trends-dashboard'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Enable or disable the Google Trends metabox in the post editor.', 'esen-trends-dashboard'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Settings', 'esen-trends-dashboard')); ?>
    </form>
</div> 