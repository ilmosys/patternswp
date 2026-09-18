<?php
/**
 * Plugin Name:       PatternsWP
 * Plugin URI:        https://thepatternswp.com
 * Description:       A growing library of ready-made block patterns can help you build websites faster in no time.
 * Author:            PatternsWP
 * Author URI:        https://thepatternswp.com
 * Version:           1.1.0
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
define('PWP_P_VERSION', '1.1.0');
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

    $script_path = 'assets/js/patternswp-editor.js';
    $script_deps =     array(
        'lodash',
        'wp-a11y',
        'wp-block-editor',
        'wp-blocks',
        'wp-components',
        'wp-compose',
        'wp-data',
        'wp-dom-ready',
        'wp-element',
        'wp-i18n',
        'wp-notices',
        'wp-plugins',
        'wp-editor',
        'wp-primitives',
    );

    wp_enqueue_script(
        'patternswp-editor-scripts',
        PWP_PLUGIN_URL . $script_path,
        $script_deps,
        patternswp_asset_version( $script_path ),
        true
    );

    $patternswp_api_section = PatternsWP_API_Section::get_instance();
    $localize_data = array(
        'isLicenseActive'   => (bool) $is_active,
        'externalPatterns'  => array(),
        'patternCategories' => $patternswp_api_section->get_patternswp_category_type(),
        'patternsNonce'     => wp_create_nonce( 'patternswp_nonce' ),
        'libraryComplete'   => $patternswp_api_section->is_catalog_ready(),
        'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
    );

    wp_localize_script( 'patternswp-editor-scripts', 'patternsWpData', $localize_data );

	patternswp_register_editor_style();
	wp_enqueue_style( 'patternswp-editor-styles' );
}
add_action( 'enqueue_block_editor_assets', 'patternswp_enqueue_editor_assets' );

/**
 * Register shared editor stylesheet handle.
 */
function patternswp_register_editor_style() {
	$style_path = 'assets/css/patternswp-editor.css';
	wp_register_style(
		'patternswp-editor-styles',
		PWP_PLUGIN_URL . $style_path,
		array( 'wp-components' ),
		patternswp_asset_version( $style_path )
	);
}

/**
 * Load styles into the iframed editor canvas (block content lives there in WP 6.3+).
 * enqueue_block_editor_assets alone only reaches the parent chrome / modal portals.
 */
function patternswp_enqueue_canvas_styles() {
	if ( ! is_admin() ) {
		return;
	}
	patternswp_register_editor_style();
	wp_enqueue_style( 'patternswp-editor-styles' );
}
add_action( 'enqueue_block_assets', 'patternswp_enqueue_canvas_styles' );

/**
 * Register the PatternsWP Pattern Library launcher block.
 *
 * Renders nothing on the front end — it is an editor-only entry point
 * for browsing and inserting patterns (similar to a pattern inserter).
 */
function patternswp_register_blocks() {
	patternswp_register_editor_style();

	register_block_type(
		'patternswp/library',
		array(
			'api_version'     => 3,
			'title'           => __( 'PatternsWP Pattern Library', 'patternswp' ),
			'description'     => __( 'Browse the PatternsWP pattern library and insert patterns into your page.', 'patternswp' ),
			'category'        => 'widgets',
			'icon'            => 'layout',
			'keywords'        => array( 'patternswp', 'patterns', 'library', 'templates', 'blocks' ),
			'supports'        => array(
				'html'     => false,
				'multiple' => true,
				'reusable' => false,
			),
			'editor_style'    => 'patternswp-editor-styles',
			'render_callback' => '__return_empty_string',
		)
	);
}
add_action( 'init', 'patternswp_register_blocks' );

/**
 * Get asset file data (legacy build/*.asset.php support).
 */
function patternswp_get_asset_file( $filepath ) {
    $asset_path = PWP_ABSPATH . $filepath . '.asset.php';
    return file_exists( $asset_path ) ? require $asset_path : array(
        'dependencies' => array(),
        'version'      => PWP_VERSION,
    );
}

/**
 * Cache-busting version for ready-to-use assets.
 *
 * @param string $relative_path Path relative to the plugin root.
 * @return string
 */
function patternswp_asset_version( $relative_path ) {
    $absolute = PWP_PLUGIN_DIR . ltrim( $relative_path, '/' );
    if ( file_exists( $absolute ) ) {
        return (string) filemtime( $absolute );
    }
    return PWP_VERSION;
}

// Plugin activation hook.
register_activation_hook(__FILE__, 'patternswp_schedule_daily_cron');

function patternswp_schedule_daily_cron() {
    wp_clear_scheduled_hook( 'patternswp_hourly_transient_load' );
    if ( ! wp_next_scheduled( 'patternswp_daily_transient_load' ) ) {
        wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'patternswp_daily_transient_load' );
    }
    if ( ! wp_next_scheduled( 'patternswp_library_warm' ) ) {
        wp_schedule_single_event( time() + 3, 'patternswp_library_warm' );
    }
}

// Plugin deactivation hook.
register_deactivation_hook(__FILE__, 'patternswp_remove_daily_cron');

function patternswp_remove_daily_cron() {
    wp_clear_scheduled_hook( 'patternswp_hourly_transient_load' );
    wp_clear_scheduled_hook( 'patternswp_daily_transient_load' );
    wp_clear_scheduled_hook( 'patternswp_library_warm' );
}

/**
 * AJAX handler to fetch patterns.
 */
function patternswp_fetch_patterns_handler() {

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

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Forbidden');
        wp_die();
    }

    patternswp_release_ajax_lock();

    if (!isset($_POST['page']) || !isset($_POST['patternsPerPage'])) {
        wp_send_json_error('Missing parameters');
        wp_die();
    }

    $page = intval($_POST['page']);
    $patterns_per_page = intval($_POST['patternsPerPage']);
    $search = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
    $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));

    $patternswp_api_section = PatternsWP_API_Section::get_instance();

    try {
        $localize_data_ajax = $patternswp_api_section->query_library_page($page, $patterns_per_page, $search, $category);
    } catch ( \Throwable $e ) {
        wp_send_json_error( 'Failed to fetch patterns' );
        wp_die();
    }

    if (is_array($localize_data_ajax)) {
        wp_send_json_success($localize_data_ajax);
    } else {
        wp_send_json_error('Failed to fetch patterns');
    }

    wp_die();
}
add_action('wp_ajax_fetch_patterns', 'patternswp_fetch_patterns_handler');

/**
 * AJAX: continue downloading the catalog in short bursts.
 */
function patternswp_warm_library_handler() {
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Missing nonce');
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    if (!wp_verify_nonce($nonce, 'patternswp_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Forbidden');
    }

    patternswp_release_ajax_lock();

    $api = PatternsWP_API_Section::get_instance();
    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'sync';
    $force = ! empty($_POST['force']);

    try {
        if ( 'check' === $mode ) {
            $status = $api->maybe_pull_remote_updates( $force );
        } else {
            $status = $api->warm_library_for_request();
        }
    } catch ( \Throwable $e ) {
        wp_send_json_error('Failed to refresh patterns');
    }

    wp_send_json_success(is_array($status) ? $status : array());
}
add_action('wp_ajax_patternswp_warm_library', 'patternswp_warm_library_handler');

/**
 * Warm the library after install or plugin update.
 */
function patternswp_maybe_upgrade_library() {
    if (!is_admin()) {
        return;
    }

    $installed = get_option('patternswp_installed_version', '');
    if ($installed === PWP_P_VERSION) {
        return;
    }

    update_option('patternswp_installed_version', PWP_P_VERSION, false);
    PatternsWP_API_Section::get_instance()->maybe_schedule_warm();
}
add_action('admin_init', 'patternswp_maybe_upgrade_library', 5);

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
    }
    return $links;
}
add_filter('plugin_row_meta', 'patternswp_add_plugin_meta_links', 10, 2);

/**
 * Let long catalog AJAX run without blocking other editor requests.
 */
function patternswp_release_ajax_lock() {
    if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
        session_write_close();
        return;
    }
    if ( session_id() ) {
        session_write_close();
    }
}
