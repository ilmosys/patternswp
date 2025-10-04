<?php
/**
 * Plugin Name:       PatternsWP
 * Plugin URI:        https://thepatternswp.com
 * Description:       A growing library of ready-made block patterns can help you build websites faster in no time.
 * Author:            PatternsWP
 * Author URI:        https://thepatternswp.com
 * Version:           1.0.7
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       patternswp
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants.
define('PWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PWP_P_VERSION', '1.0.7');
define('PWP_PLUGIN_FILE', __FILE__);
define('PWP_ABSPATH', dirname(__FILE__) . '/');
define('PWP_VERSION', get_file_data(__FILE__, ['Version'])[0]);

define('PATTERSWP_PLUGIN_API_URL', 'https://api.lemonsqueezy.com/v1/licenses');

// Include necessary files.
require_once PWP_PLUGIN_DIR . 'includes/class-patternswp-admin.php';
require_once PWP_PLUGIN_DIR . 'includes/class-patternswp-api.php';
require_once PWP_PLUGIN_DIR . 'includes/lib/class-patterns-license.php';

/**
 * Enqueue assets for Block Editor.
 */
function patternswp_enqueue_editor_assets() {
    $get_license_data = get_option('patternswp_plugin_license_data');
    $is_active = isset($get_license_data['activated']) ? $get_license_data['activated'] : false;

    $script_asset = patternswp_get_asset_file('build/patternswp-editor');
    wp_enqueue_script(
        'patternswp-editor-scripts', 
        PWP_PLUGIN_URL . 'build/patternswp-editor.js', 
        array_merge($script_asset['dependencies'], ['wp-api']), 
        $script_asset['version'], 
        true
    );

    $patternswp_api_section = new PatternsWP_API_Section();
    $localize_data = [
        'isLicenseActive' => $is_active,
        'externalPatterns' => [],
        'patternCategories' => $patternswp_api_section->get_patternswp_category_type(),
        'patternsNonce' => wp_create_nonce('patternswp_nonce'),
    ];

    wp_localize_script('patternswp-editor-scripts', 'patternsWpData', $localize_data);

    $style_asset = patternswp_get_asset_file('build/block-pattern-inserter-editor-styles');
    wp_enqueue_style('patternswp-editor-styles', PWP_PLUGIN_URL . 'build/style-patternswp-editor-styles.css', [], $style_asset['version']);
}
add_action('enqueue_block_editor_assets', 'patternswp_enqueue_editor_assets');

/**
 * Get asset file data.
 */
function patternswp_get_asset_file($filepath) {
    $asset_path = PWP_ABSPATH . $filepath . '.asset.php';
    return file_exists($asset_path) ? require_once $asset_path : ['dependencies' => [], 'version' => PWP_VERSION];
}

// Plugin activation hook.
register_activation_hook(__FILE__, 'patternswp_schedule_hourly_cron');

function patternswp_schedule_hourly_cron() {
    if ( !wp_next_scheduled( 'patternswp_hourly_transient_load' ) ) {
        wp_schedule_event( time(), 'hourly', 'patternswp_hourly_transient_load' );
    }
}

// Plugin deactivation hook.
register_deactivation_hook(__FILE__, 'patternswp_remove_hourly_cron');

function patternswp_remove_hourly_cron() {
    $timestamp = wp_next_scheduled( 'patternswp_hourly_transient_load' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'patternswp_hourly_transient_load' );
    }
}

/**
 * AJAX handler to fetch patterns.
 */
function fetch_patterns_handler() {

    // Verify nonce
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Missing nonce');
        wp_die();
    }
    
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    
    if (!wp_verify_nonce($nonce, 'patternswp_nonce')) {
        wp_send_json_error('Invalid nonce');
        wp_die();
    }    

    if (!isset($_POST['page']) || !isset($_POST['patternsPerPage'])) {
        wp_send_json_error('Missing parameters');
        wp_die();
    }

    $page = intval($_POST['page']);
    $patterns_per_page = intval($_POST['patternsPerPage']);
    $search = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
    $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));

    $cache_key = 'patternswp_' . md5("$page-$patterns_per_page-$search-$category");
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        wp_send_json_success($cached_data);
        wp_die();
    }

    $patternswp_api_section = new PatternsWP_API_Section();
    $localize_data_ajax = $patternswp_api_section->get_patternswp_pattern($page, $patterns_per_page, $search, $category);

    if (is_array($localize_data_ajax)) {
        set_transient($cache_key, $localize_data_ajax, 3600);
        wp_send_json_success($localize_data_ajax);
    } else {
        wp_send_json_error('Failed to fetch patterns');
    }

    wp_die();
}
add_action('wp_ajax_fetch_patterns', 'fetch_patterns_handler');
add_action('wp_ajax_nopriv_fetch_patterns', 'fetch_patterns_handler');

/**
 * Add plugin action links.
 */
function patternswp_plugin_action_links($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $get_license_data = get_option('patternswp_plugin_license_data');
        $is_active = isset($get_license_data['activated']) ? $get_license_data['activated'] : false;

        $support_link = '<a href="https://thepatternswp.com/contact/" target="_blank">Support</a>';
        array_unshift($links, $support_link);

        if (!$is_active) {
            $upgrade_link = '<a href="https://thepatternswp.com/pricing/" target="_blank">Upgrade to Pro</a>';
            array_unshift($links, $upgrade_link);
        }
    }
    return $links;
}
add_filter('plugin_action_links', 'patternswp_plugin_action_links', 10, 2);

/**
 * Add custom plugin meta links.
 */
function patternswp_add_plugin_meta_links($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $links[] = '<a href="https://thepatternswp.com/suggest-feature" target="_blank">Suggest a Feature</a>';
        $links[] = '<a href="https://wordpress.org/support/plugin/patternswp/reviews/?filter=5" target="_blank">Rate Us</a>';
    }
    return $links;
}
add_filter('plugin_row_meta', 'patternswp_add_plugin_meta_links', 10, 2);