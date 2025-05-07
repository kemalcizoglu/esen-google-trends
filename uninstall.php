<?php
/**
 * Uninstall Esen Google Trends
 *
 * @package     Esen_Google_Trends
 * @author      Kemal CIZOĞLU
 * @copyright   2023
 * @license     GPL-2.0+
 * @since       1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options created by plugin
delete_option('esen_gt_default_country');
delete_option('esen_gt_show_in_dashboard');
delete_option('esen_gt_show_in_posts');
delete_option('esen_gt_dashboard_widget_options');

// Clear any cached data (transients)
// Desteklenen ülke kodları - helper-functions.php içinde tanımlanan ülkeler
$country_codes = array('TR', 'US', 'DE', 'FR', 'GB', 'JP', 'IN', 'BR', 'CA', 'AU');

// Her bir ülke için transient'i temizle
foreach ($country_codes as $code) {
    delete_transient('esen_gt_trends_' . $code);
}

// Clear any post meta stored by the plugin
delete_post_meta_by_key('_esen_gt_country_code'); 