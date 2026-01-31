<?php
/**
 * Admin Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PatternsWP_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Add menu and pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'patternswp_clear_cache'));
        add_action('admin_init', array($this, 'patternswp_ensure_hourly_cron'));
        add_action('wp_ajax_patternswp_background_transient_load_ajax', array($this, 'patternswp_background_transient_load_ajaxcc'));
        add_action('wp_ajax_nopriv_patternswp_background_transient_load_ajax', array($this, 'patternswp_background_transient_load_ajaxcc'));
        add_action('patternswp_load_patterns_by_ra', array($this, 'patternswp_load_patterns_by_remote_ajax'));
        register_activation_hook(plugin_dir_path(__DIR__) . 'patternswp.php', array($this, 'patternswp_on_activation'));
        add_action('admin_init', array($this, 'patternswp_redirect_on_activation'));
        
        // Initialize settings
        add_action('admin_init', array($this, 'register_pattern_visibility_settings'));
        
        // Add pattern deregistration hooks
        add_action('init', array($this, 'maybe_deregister_core_patterns'), 999);
        add_action('init', array($this, 'maybe_deregister_theme_patterns'), 1000);
        add_action('init', array($this, 'maybe_deregister_uncategorized_patterns'), 1001);
        
        // Add a second check later to ensure patterns are deregistered
        add_action('init', array($this, 'maybe_deregister_theme_patterns'), 9999);
        add_action('init', array($this, 'maybe_deregister_uncategorized_patterns'), 10000);
        add_action('init', array($this, 'maybe_deregister_core_patterns'), 10001);
        
        error_log('[PatternsWP] Constructor - All pattern deregistration hooks registered');
        
        // Apply filters for different pattern sources
        add_filter('patternswp_patterns', array($this, 'filter_patterns_by_visibility'), 20);
        add_filter('block_editor_settings_all', array($this, 'filter_block_editor_settings'), 10, 2);
        
        // Filter REST API patterns
        add_filter('rest_wp_block_query', array($this, 'filter_rest_patterns'), 10, 2);
        
        // Handle core and theme patterns
        add_filter('should_load_remote_block_patterns', array($this, 'maybe_disable_remote_patterns'), 10, 1);
        add_filter('should_load_remote_patterns_default_override', array($this, 'maybe_disable_remote_patterns'), 10, 1);
        
        // Add action to clear pattern cache when theme changes
        add_action('switch_theme', array($this, 'clear_pattern_cache_on_theme_switch'));
        
        // Also filter the REST API response directly
        add_filter('rest_prepare_wp_block', array($this, 'filter_rest_block_response'), 10, 3);
        
        // Filter the block patterns list in the editor
        add_filter('wp_block_patterns', array($this, 'filter_block_patterns_list'), 10, 1);
        
        // Add a test hook to verify settings are loaded
        add_action('wp_loaded', array($this, 'test_settings'));
    }
    
    /**
     * Test method to verify settings are loaded
     */
    public function test_settings() {
        $hide_theme = get_option('patternswp_hide_theme_patterns', false);
        $hide_uncategorized = get_option('patternswp_hide_uncategorized_patterns', false);
        $hide_core = get_option('patternswp_hide_core_patterns', false);
        
        error_log('[PatternsWP] Settings Check - Theme: ' . ($hide_theme ? 'true' : 'false') . ', Uncategorized: ' . ($hide_uncategorized ? 'true' : 'false') . ', Core: ' . ($hide_core ? 'true' : 'false'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Custom SVG icon (base64 encoded)
        $custom_icon = 'data:image/svg+xml;base64,' . base64_encode('
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                <path d="M9.34 24L4.45 21.16L4.03 20.92V15.3L9.34 12V24Z"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M14.66 9.2L20.97 6L14.66 2.8V9.2Z"/>
                <path d="M14.66 2.8L9.34 6L4.03 9.2V2.8L9.34 0L14.66 2.8Z"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M10.79 11.44V17.84L14.66 15.3L20.97 12V6L14.66 9.2L10.79 11.44Z"/>
            </svg>
        ');

        // Add top-level menu with custom SVG icon
        $menu_slug = 'patternswp-plugin-menu';
        add_menu_page(
            'PatternsWP',
            'PatternsWP',
            'manage_options',
            $menu_slug,
            array($this, 'render_main_page'),
            $custom_icon,
            60
        );

        // Add subpages
        $this->add_subpage($menu_slug, 'Dashboard', 'Dashboard', 'patternswp-plugin-menu', array($this, 'render_main_page'));
        $this->add_subpage($menu_slug, 'Settings', 'Settings', 'patternswp-settings', array($this, 'render_pattern_visibility_page'));
        $this->add_subpage($menu_slug, 'Support', 'Support', 'patternswp-plugin-page-1', array($this, 'patternswp_support'));
    }

    /**
     * Add subpage
     */
    private function add_subpage($parent_slug, $page_title, $menu_title, $menu_slug, $callback) {
        // Add subpage
        add_submenu_page($parent_slug, $page_title, $menu_title, 'manage_options', $menu_slug, $callback);
    }

    /**
     * Render tabs
     */
    public function patterswp_tab( $tab ) {
        $tabs = array(
            'Dashboard' => 'patternswp-plugin-menu',
            'Settings' => 'patternswp-settings',
            'Support' => 'patternswp-plugin-page-1',
            'License' => 'patternswp-license_section'
        );

        // Get the current page from URL parameter
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'patternswp-plugin-menu';
        
        // Map page slugs to tab names
        $page_to_tab = array(
            'patternswp-plugin-menu' => 'Dashboard',
            'patternswp-settings' => 'Settings',
            'patternswp-plugin-page-1' => 'Support',
            'patternswp-license_section' => 'License'
        );
        
        // Determine current tab
        $current_tab = isset($page_to_tab[$current_page]) ? $page_to_tab[$current_page] : 'Dashboard';

        foreach ($tabs as $name => $slug) {
            $class = ( $current_tab === $name ) ? 'nav-tab-active' : '';
            echo '<a href="?page=' . esc_attr($slug) . '" class="nav-tab ' . esc_attr($class) . '">' . esc_html($name) . '</a>';
        }
    }

    /**
     * Render main page
     */
    public function render_main_page() { ?>
        <div class="wrap">
            <h1><?php esc_html_e('PatternsWP', 'patternswp'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php $this->patterswp_tab( 'Dashboard' ); ?>
            </h2>
            <div class="patterns-wp-tabs-content">
                <div id="tab-1" class="patterns-wp-tab-content patterns-wp-tab-active">
                    <div class="wrap">
                        <div class="pw-feature-box big-box" style="max-width: 1280px; padding: 40px; background-color: white; border-radius: 10px; overflow: hidden;">
                            <div class="about__section has-1-columns">
                                <div class="column" style="text-align:center;">
                                    <h4><?php echo esc_html('Hello, ' . wp_get_current_user()->display_name . ' 👋'); ?></h4>
                                    <h1 class="feature-title"><?php esc_html_e('Welcome to PatternsWP', 'patternswp'); ?></h1>
                                    <p><?php esc_html_e('Thanks for choosing PatternsWP! Follow these three simple steps to get started!', 'patternswp'); ?></p>
                                </div>
                                <div class="column" style="padding: 10px; text-align: center;">
                                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>" class="button button-primary">
                                        <?php esc_html_e('Start building with PatternsWP', 'patternswp'); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="about__section has-3-columns">
                                <div class="column" style="padding: 40px;">
                                    <div class="about__image">
                                        <?php
                                            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                                            echo '<img src="' . esc_url(plugin_dir_url(__FILE__) . 'pwp-welcome-01.png') . '" alt="" height="auto" width="100%">';
                                        ?>
                                    </div>
                                    <h4><?php esc_html_e('01. Open the PatternsWP Library', 'patternswp'); ?></h4>
                                    <p><?php esc_html_e('When editing a page or post in the block editor, locate the PatternsWP Library button in the editor’s header. Click it to access a collection of pre-designed patterns and full-page templates.', 'patternswp'); ?></p>
                                </div>
                                <div class="column" style="padding: 40px;">
                                    <div class="about__image">
                                        <?php
                                            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                                            echo '<img src="' . esc_url(plugin_dir_url(__FILE__) . 'pwp-welcome-02.png') . '" alt="" height="auto" width="100%">';
                                        ?>
                                    </div>
                                    <h4><?php esc_html_e('02. Browse Patterns & Templates', 'patternswp'); ?></h4>
                                    <p><?php esc_html_e('Explore a diverse range of block patterns and full-page layouts. Use the search box or filter by category to quickly find the perfect design for your website.', 'patternswp'); ?></p>
                                </div>
                                <div class="column" style="padding: 40px;">
                                    <div class="about__image">
                                        <?php
                                            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                                            echo '<img src="' . esc_url(plugin_dir_url(__FILE__) . 'pwp-welcome-03.png') . '" alt="" height="auto" width="100%">';
                                        ?>
                                    </div>
                                    <h4><?php esc_html_e('03. Add Patterns & Customize', 'patternswp'); ?></h4>
                                    <p><?php esc_html_e('Once you’ve found the right pattern, add it to your page with a single click. Every pattern is fully customizable, allowing you to tweak colors, typography, and content effortlessly.', 'patternswp'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> 
    <?php }

    /**
     * Support page
     */
    public function patternswp_support() { ?>
        <div class="wrap">
            <h1><?php esc_html_e('Support', 'patternswp'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php $this->patterswp_tab( 'Support' ); ?>
            </h2>
            <div class="patterns-wp-tabs-content">
                <div id="tab-1" class="patterns-wp-tab-content patterns-wp-tab-active">
                </div>
                <div id="tab-2" class="patterns-wp-tab-content">
                <div class="wrap">
                        <div class="pw-feature-box big-box" style="max-width: 1280px; padding: 40px; background-color: white; border-radius: 10px; overflow: hidden;">
                            <div class="about__section has-1-columns">
                                <div class="column" style="">
                                    <h1 class="feature-title"><?php esc_html_e('Support', 'patternswp'); ?></h1>
                                    <p><?php esc_html_e('Need help with PatternsWP? Whether you have a question, need technical assistance, or just want to reach out, we’re here for you. Contact us, and we’ll be happy to assist!', 'patternswp'); ?></p>
                                </div>
                                <div style="height: 1px; background-color: #ccc; width: 100%; margin: 20px 0;"></div>
                            </div>
                            <div class="about__section has-2-columns">
                                <div class="column" style="padding-right: 100px;">
                                   
                                    <h4><?php esc_html_e('Frequently asked questions', 'patternswp'); ?></h4>
                                    <p>
                                        <?php 
                                        printf(
                                            esc_attr('Here, you will find answers to commonly asked questions about using PatternsWP. If you need further assistance, feel free to %s.', 'patternswp'), 
                                            '<a href="https://thepatternswp.com/contact/" target="_blank">'.esc_attr('contact us via the support form →', 'patternswp').'</a>'
                                        ); 
                                        ?>
                                    </p>
                                </div>
                                <div class="column" style="padding: 0px;">
                                <h5>
                                    <a href="https://thepatternswp.com/docs/getting-started-with-patternswp/" target="_blank" style="color: #3858e9; text-decoration: none;">
                                    <?php esc_html_e('Getting Started with PatternsWP →', 'patternswp'); ?>
                                    </a>
                                </h5>
                                    <div style="height: 1px; background-color: #ccc; width: 100%; margin: 10px 0;"></div>
                                <h5>
                                    <a href="https://thepatternswp.com/docs/how-to-install-patternswp/" target="_blank" style="color: #3858e9; text-decoration: none;">
                                    <?php esc_html_e('How to install PatternsWP →', 'patternswp'); ?>
                                    </a>
                                </h5>                                    <div style="height: 1px; background-color: #ccc; width: 100%; margin: 10px 0;"></div>
                                <h5>
                                    <a href="https://thepatternswp.com/docs/how-to-upgrade-patternswp-to-pro/" target="_blank" style="color: #3858e9; text-decoration: none;">
                                    <?php esc_html_e('How to Upgrade PatternsWP to Pro →', 'patternswp'); ?>
                                    </a>
                                </h5>                                    <div style="height: 1px; background-color: #ccc; width: 100%; margin: 10px 0;"></div>
                                <h5>
                                    <a href="https://thepatternswp.com/docs/patternswp-support/" target="_blank" style="color: #3858e9; text-decoration: none;">
                                    <?php esc_html_e('PatternsWP Support →', 'patternswp'); ?>
                                    </a>
                                </h5>                                    <div style="height: 1px; background-color: #ccc; width: 100%; margin: 10px 0;"></div>
                                </div>  
                                                              
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php }

    /**
     * Clear Cache page
     */
    public function patternswp_clear_cache_form() { ?>
        <div class="wrap">
            <h1><?php esc_html_e('Clear Cache', 'patternswp'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php $this->patterswp_tab( 'Clear Cache' ); ?>
            </h2>
            <div class="patterns-wp-tabs-content">
                <div id="tab-1" class="patterns-wp-tab-content patterns-wp-tab-active">
                    <div class="wrap">
                        <div class="pw-feature-box big-box" style="max-width: 1280px; padding: 40px; background-color: white; border-radius: 10px; overflow: hidden; margin: 0 auto;">
                            <div class="">
                                <form id="patternswp_clearcache_form" method="post">
                                    <?php wp_nonce_field('patternswp_clear_cache_action', 'patternswp_clear_cache_nonce'); ?>
                                    <input name="patternswp_clear_cache" type="submit" class="button button-primary" value="Clear Cache">
                                    <div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php }

    /**
     * Clear pattern cache when theme is switched
     */
    public function clear_pattern_cache_on_theme_switch() {
        $this->clear_pattern_cache(false, false);
    }

    /**
     * Handle remote pattern loading based on settings
     */
    public function maybe_disable_remote_patterns($should_load) {
        if (get_option('patternswp_hide_core_patterns', false)) {
            return false;
        }
        return $should_load;
    }

    /**
     * Filter block editor settings to handle pattern visibility
     */
    public function filter_block_editor_settings($settings, $context) {
        if (get_option('patternswp_hide_core_patterns', false)) {
            $settings['__experimentalBlockPatterns'] = array();
            $settings['__experimentalBlockPatternCategories'] = array();
            $settings['__experimentalBlockPatterns'] = array();
        }
        return $settings;
    }

    /**
     * Clear Cache
     */
    public function patternswp_clear_cache() {
        global $wpdb;

        if (isset($_POST['patternswp_clear_cache'])) {
            if (!isset($_POST['patternswp_clear_cache_nonce'])) {
                wp_die(esc_attr__('Security check failed. Please try again.', 'patternswp'));
            }
            
            $nonce = sanitize_text_field(wp_unslash($_POST['patternswp_clear_cache_nonce']));
            
            if (!wp_verify_nonce($nonce, 'patternswp_clear_cache_action')) {
                wp_die(esc_attr__('Security check failed. Please try again.', 'patternswp'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(esc_attr('You do not have sufficient permissions to perform this action.', 'patternswp'));
            }

            // Delete category type transient
            delete_transient('patternswp_category_type');

            // Delete all API-related transients
            $pattern = '_transient_patternswp_';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $wpdb->esc_like($pattern) . '%'));

            // Delete all patterns cache
            $patterns = 'patterns_cache_';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                    $wpdb->esc_like($patterns) . '%'
                )
            );

            foreach ($results as $transient) {
                delete_option($transient);
                delete_option(str_replace('_transient_', '_transient_timeout_', $transient));
            }

            // Load patterns in background
            $this->patternswp_load_patterns_by_remote_ajax();
            
            // Set a transient for the success message
            set_transient('patternswp_cache_cleared', 'Cache cleared successfully', 5);
            
            // Redirect to Settings page
            wp_safe_redirect(admin_url('admin.php?page=patternswp-settings'));
            exit;
        }
    }

    /**
     * Ensure hourly cron job is scheduled
     */
    public function patternswp_ensure_hourly_cron() {
        if ( ! wp_next_scheduled( 'patternswp_hourly_transient_load' ) ) {
            wp_schedule_event( time(), 'hourly', 'patternswp_hourly_transient_load' );
        }
    }

    /**
     * AJAX handler to load transient data
     */
    public function patternswp_background_transient_load_ajaxcc() {
        do_action( 'patternswp_hourly_transient_load' );
        wp_die();
    }

    /**
     * Load patterns via AJAX
     */
    public function patternswp_load_patterns_by_remote_ajax() {
        $ajax_url = admin_url('admin-ajax.php');
        wp_remote_post( $ajax_url , array(
            'body'      => array( 'action' => 'patternswp_background_transient_load_ajax' ),
            'timeout'   => 1,
            'blocking'  => false,
            'sslverify' => false,
        ));
    }

    /**
     * Set transient on plugin activation
     */
    public function patternswp_on_activation() {
        set_transient('patternswp_activation_redirect', true, 30);
    }
    
    /**
     * Clear pattern cache when visibility settings change
     */
    public function clear_pattern_cache($old_value, $new_value) {
        // Clear object cache first
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear known transients
        $transients = [
            'patternswp_all_patterns',
            'patternswp_categorized_patterns',
            'patternswp_patterns_data',
            'patternswp_categories'
        ];
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
        
        // Clear any remaining transients with our prefix
        if (!function_exists('delete_expired_transients')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // This is a core function that will clean up expired transients
        delete_expired_transients(true);
        
        // Clear specific pattern caches
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('all_patterns', 'patternswp');
            wp_cache_delete('categorized_patterns', 'patternswp');
        }
    }

    /**
     * Attempt to deregister any patterns from the active or child theme.
     */
    public function maybe_deregister_theme_patterns() {
        $hide_theme_patterns = get_option('patternswp_hide_theme_patterns', false);
        error_log('[PatternsWP] maybe_deregister_theme_patterns called - hide_theme_patterns: ' . ($hide_theme_patterns ? 'true' : 'false'));
        
        if (!$hide_theme_patterns) {
            error_log('[PatternsWP] Theme pattern hiding is disabled');
            return;
        }
        
        // Get the active theme.
        $theme = wp_get_theme();
        
        // Let's get some theme data.
        $paths_to_search = array(
            $theme->stylesheet,
        );
        
        // Check if this is a child theme.
        if ($theme->parent()) {
            $parent_theme = wp_get_theme($theme->parent()->template);
            $paths_to_search[] = $parent_theme->template;
        }
        $paths_to_search = array_unique($paths_to_search);
        
        error_log('[PatternsWP] Theme paths to search: ' . implode(', ', $paths_to_search));
        
        // Get all registered patterns.
        $patterns = \WP_Block_Patterns_Registry::get_instance();
        $all_patterns = $patterns->get_all_registered();
        
        error_log('[PatternsWP] Total registered patterns: ' . count($all_patterns));
        
        // Loop through all patterns and deregister any that are from the active or child theme.
        foreach ($all_patterns as $index => $pattern) {
            $file_path = $pattern['filePath'] ?? '';
            if (empty($file_path)) {
                continue;
            }
            
            // Check if the pattern is from the active or child theme.
            foreach ($paths_to_search as $path) {
                if (false !== strpos($file_path, 'themes/' . $path)) {
                    unregister_block_pattern($pattern['name']);
                    error_log('[PatternsWP] Deregistered theme pattern: ' . $pattern['name'] . ' from path: ' . $file_path);
                }
            }
        }
    }
    
    /**
     * Deregister any uncategorized patterns.
     */
    public function maybe_deregister_uncategorized_patterns() {
        $hide_uncategorized_enabled = get_option('patternswp_hide_uncategorized_patterns', false);
        error_log('[PatternsWP] maybe_deregister_uncategorized_patterns called - hide_uncategorized: ' . ($hide_uncategorized_enabled ? 'true' : 'false'));
        
        // Exit early if not enabled.
        if (!$hide_uncategorized_enabled) {
            error_log('[PatternsWP] Uncategorized pattern hiding is disabled');
            return;
        }
        
        // Retrieve all patterns.
        $patterns = \WP_Block_Patterns_Registry::get_instance();
        $all_patterns = $patterns->get_all_registered();
        
        error_log('[PatternsWP] Checking ' . count($all_patterns) . ' patterns for uncategorized ones');
        
        // Loop through all patterns and deregister any that are uncategorized.
        foreach ($all_patterns as $index => $pattern) {
            if (!isset($pattern['categories']) || empty($pattern['categories'])) {
                unregister_block_pattern($pattern['name']);
                error_log('[PatternsWP] Deregistered uncategorized pattern: ' . $pattern['name']);
            } else {
                $found = false;
                $block_categories = $pattern['categories'] ?? array();
                foreach ($block_categories as $block_category) {
                    $categories = \WP_Block_Pattern_Categories_Registry::get_instance();
                    if ($categories->is_registered($block_category)) {
                        $found = true;
                    }
                }
                if (!$found) {
                    unregister_block_pattern($pattern['name']);
                    error_log('[PatternsWP] Deregistered uncategorized pattern (invalid category): ' . $pattern['name']);
                }
            }
        }
    }
    
    /**
     * Deregister core patterns.
     */
    public function maybe_deregister_core_patterns() {
        $hide_core_patterns = get_option('patternswp_hide_core_patterns', false);
        error_log('[PatternsWP] maybe_deregister_core_patterns called - hide_core_patterns: ' . ($hide_core_patterns ? 'true' : 'false'));
        
        // Exit early if not enabled.
        if (!$hide_core_patterns) {
            error_log('[PatternsWP] Core pattern hiding is disabled');
            return;
        }
        
        // Retrieve all patterns.
        $patterns = \WP_Block_Patterns_Registry::get_instance();
        $all_patterns = $patterns->get_all_registered();
        
        error_log('[PatternsWP] Checking ' . count($all_patterns) . ' patterns for core ones');
        
        // Loop through all patterns and deregister any that are core patterns.
        foreach ($all_patterns as $index => $pattern) {
            // Core patterns typically have names starting with 'core/' or no file path
            $pattern_name = $pattern['name'] ?? '';
            $file_path = $pattern['filePath'] ?? '';
            
            // Check if it's a core pattern
            $is_core_pattern = false;
            $reason = '';
            
            // Method 1: Check if pattern name starts with 'core/'
            if (strpos($pattern_name, 'core/') === 0) {
                $is_core_pattern = true;
                $reason = 'name starts with core/';
            }
            
            // Method 2: Check if file path contains wp-includes
            if (!$is_core_pattern && !empty($file_path) && strpos($file_path, 'wp-includes') !== false) {
                $is_core_pattern = true;
                $reason = 'file path contains wp-includes';
            }
            
            // Method 3: Check if no file path (likely core pattern)
            if (!$is_core_pattern && empty($file_path)) {
                $is_core_pattern = true;
                $reason = 'no file path';
            }
            
            if ($is_core_pattern) {
                unregister_block_pattern($pattern['name']);
                error_log('[PatternsWP] Deregistered core pattern: ' . $pattern['name'] . ' (' . $reason . ')');
            }
        }
    }

    /**
     * Filter the block patterns list in the editor
     */
    public function filter_block_patterns_list($patterns) {
        error_log('[PatternsWP] filter_block_patterns_list called with ' . count($patterns) . ' patterns');
        
        $hide_theme_patterns = get_option('patternswp_hide_theme_patterns', false);
        $hide_uncategorized = get_option('patternswp_hide_uncategorized_patterns', false);
        $hide_core_patterns = get_option('patternswp_hide_core_patterns', false);
        
        error_log('[PatternsWP] Block patterns filter - Theme: ' . ($hide_theme_patterns ? 'true' : 'false') . ', Uncategorized: ' . ($hide_uncategorized ? 'true' : 'false') . ', Core: ' . ($hide_core_patterns ? 'true' : 'false'));
        
        // If no filters are enabled, return original patterns
        if (!$hide_theme_patterns && !$hide_uncategorized && !$hide_core_patterns) {
            return $patterns;
        }
        
        $filtered_patterns = array();
        
        foreach ($patterns as $pattern) {
            $should_include = true;
            
            // Check theme patterns
            if ($hide_theme_patterns && $should_include) {
                // Check if pattern name starts with theme prefix
                if (isset($pattern['name']) && strpos($pattern['name'], 'theme-') === 0) {
                    $should_include = false;
                    error_log('[PatternsWP] Excluding theme pattern by name: ' . $pattern['name']);
                }
                // Check if pattern has theme file path
                if (isset($pattern['filePath']) && strpos($pattern['filePath'], 'themes/') !== false) {
                    $should_include = false;
                    error_log('[PatternsWP] Excluding theme pattern by path: ' . $pattern['name']);
                }
            }
            
            // Check uncategorized patterns
            if ($hide_uncategorized && $should_include) {
                if (!isset($pattern['categories']) || empty($pattern['categories'])) {
                    $should_include = false;
                    error_log('[PatternsWP] Excluding uncategorized pattern: ' . $pattern['name']);
                }
            }
            
            // Check core patterns
            if ($hide_core_patterns && $should_include) {
                if (isset($pattern['name']) && strpos($pattern['name'], 'core/') === 0) {
                    $should_include = false;
                    error_log('[PatternsWP] Excluding core pattern: ' . $pattern['name']);
                }
            }
            
            if ($should_include) {
                $filtered_patterns[] = $pattern;
            }
        }
        
        error_log('[PatternsWP] Filtered from ' . count($patterns) . ' to ' . count($filtered_patterns) . ' patterns');
        
        return $filtered_patterns;
    }

    /**
     * Filter REST API response for individual blocks
     */
    public function filter_rest_block_response($response, $post, $request) {
        // Only filter in the block editor context
        if (!isset($request['context']) || $request['context'] !== 'edit') {
            return $response;
        }
        
        $hide_theme_patterns = get_option('patternswp_hide_theme_patterns', false);
        $hide_uncategorized = get_option('patternswp_hide_uncategorized_patterns', false);
        $hide_core_patterns = get_option('patternswp_hide_core_patterns', false);
        
        // If no filters are enabled, return original response
        if (!$hide_theme_patterns && !$hide_uncategorized && !$hide_core_patterns) {
            return $response;
        }
        
        // Get the pattern data
        $pattern_data = $response->data;
        
        // Check if this should be hidden
        $should_hide = false;
        
        // Check theme patterns
        if ($hide_theme_patterns) {
            // Check if pattern has theme-specific metadata
            if (isset($pattern_data['patternswp_source']) && $pattern_data['patternswp_source'] === 'theme') {
                $should_hide = true;
            }
        }
        
        // Check uncategorized patterns
        if (!$should_hide && $hide_uncategorized) {
            if (empty($pattern_data['pattern_categories'])) {
                $should_hide = true;
            }
        }
        
        // Check core patterns
        if (!$should_hide && $hide_core_patterns) {
            if (isset($pattern_data['name']) && strpos($pattern_data['name'], 'core/') === 0) {
                $should_hide = true;
            }
        }
        
        if ($should_hide) {
            error_log('[PatternsWP] Filtering REST response for pattern: ' . ($pattern_data['title'] ?? 'Unknown'));
            // Return an error response to hide this pattern
            return new WP_Error(
                'rest_pattern_hidden',
                'Pattern is hidden by visibility settings',
                array('status' => 404)
            );
        }
        
        return $response;
    }

    /**
     * Filter REST API patterns
     */
    public function filter_rest_patterns($args, $request) {
        // Only filter if we're in the block editor context
        if (!isset($request['context']) || $request['context'] !== 'edit') {
            return $args;
        }
        
        // Get the current registered patterns
        $patterns = \WP_Block_Patterns_Registry::get_instance();
        $all_patterns = $patterns->get_all_registered();
        
        $hide_theme_patterns = get_option('patternswp_hide_theme_patterns', false);
        $hide_uncategorized = get_option('patternswp_hide_uncategorized_patterns', false);
        $hide_core_patterns = get_option('patternswp_hide_core_patterns', false);
        
        // If no filters are enabled, return original args
        if (!$hide_theme_patterns && !$hide_uncategorized && !$hide_core_patterns) {
            return $args;
        }
        
        // Get theme info for theme pattern detection
        $theme = wp_get_theme();
        $paths_to_search = array($theme->stylesheet);
        if ($theme->parent()) {
            $parent_theme = wp_get_theme($theme->parent()->template);
            $paths_to_search[] = $parent_theme->template;
        }
        $paths_to_search = array_unique($paths_to_search);
        
        // Build list of pattern names to exclude
        $exclude_patterns = array();
        
        foreach ($all_patterns as $pattern) {
            $should_exclude = false;
            
            // Check if it's a theme pattern
            if ($hide_theme_patterns) {
                $file_path = $pattern['filePath'] ?? '';
                if (!empty($file_path)) {
                    foreach ($paths_to_search as $path) {
                        if (false !== strpos($file_path, 'themes/' . $path)) {
                            $should_exclude = true;
                            break;
                        }
                    }
                }
            }
            
            // Check if it's a core pattern
            if (!$should_exclude && $hide_core_patterns) {
                $pattern_name = $pattern['name'] ?? '';
                $file_path = $pattern['filePath'] ?? '';
                
                // Method 1: Check if pattern name starts with 'core/'
                if (strpos($pattern_name, 'core/') === 0) {
                    $should_exclude = true;
                }
                
                // Method 2: Check if file path contains wp-includes
                if (!$should_exclude && !empty($file_path) && strpos($file_path, 'wp-includes') !== false) {
                    $should_exclude = true;
                }
                
                // Method 3: Check if no file path (likely core pattern)
                if (!$should_exclude && empty($file_path)) {
                    $should_exclude = true;
                }
            }
            
            // Check if it's uncategorized
            if (!$should_exclude && $hide_uncategorized) {
                if (!isset($pattern['categories']) || empty($pattern['categories'])) {
                    $should_exclude = true;
                } else {
                    $found = false;
                    $block_categories = $pattern['categories'] ?? array();
                    foreach ($block_categories as $block_category) {
                        $categories = \WP_Block_Pattern_Categories_Registry::get_instance();
                        if ($categories->is_registered($block_category)) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $should_exclude = true;
                    }
                }
            }
            
            if ($should_exclude) {
                $exclude_patterns[] = $pattern['name'];
            }
        }
        
        // Add exclude filter using direct SQL for better performance
        if (!empty($exclude_patterns)) {
            add_filter('posts_where', function($where) use ($exclude_patterns) {
                global $wpdb;
                
                // Prepare placeholders for the IN clause
                $placeholders = array_fill(0, count($exclude_patterns), '%s');
                $placeholders = implode(',', $placeholders);
                
                // Prepare the exclusion subquery
                $exclude_sql = $wpdb->prepare(
                    "SELECT post_id 
                     FROM {$wpdb->postmeta} 
                     WHERE meta_key = 'pattern_id' 
                     AND meta_value IN ($placeholders)",
                    $exclude_patterns
                );
                
                // Add the exclusion to the WHERE clause
                $where .= " AND {$wpdb->posts}.ID NOT IN ($exclude_sql)";
                
                return $where;
            });
            
            // Ensure we don't use post__not_in or meta_query
            unset($args['post__not_in']);
            unset($args['meta_query']);
        }
        
        return $args;
    }

    /**
     * Filter patterns based on visibility settings
     */
    /**
     * Check if a pattern is a theme pattern
     */
    private function is_theme_pattern($pattern) {
        // Check source
        if (!empty($pattern['source']) && $pattern['source'] === 'theme') {
            return true;
        }
        
        // Check name prefix
        if (!empty($pattern['name']) && (strpos($pattern['name'], 'theme-') === 0 || 
                                        strpos($pattern['name'], 'tw/') === 0)) {
            return true;
        }
        
        // Check categories
        if (!empty($pattern['categories'])) {
            $categories = is_array($pattern['categories']) ? 
                         $pattern['categories'] : 
                         array_map('trim', explode(',', $pattern['categories']));
            
            foreach ($categories as $category) {
                if (is_string($category) && 
                   (stripos($category, 'theme') !== false || 
                    stripos($category, 'template') !== false)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if a pattern is a core pattern
     */
    private function is_core_pattern($pattern) {
        // Check source
        if (!empty($pattern['source']) && $pattern['source'] === 'core') {
            return true;
        }
        
        // Check name prefix
        if (!empty($pattern['name']) && strpos($pattern['name'], 'core/') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a pattern is uncategorized
     */
    private function is_uncategorized_pattern($pattern) {
        if (empty($pattern['categories'])) {
            return true;
        }
        
        $categories = is_array($pattern['categories']) ? 
                     $pattern['categories'] : 
                     array_filter(array_map('trim', explode(',', $pattern['categories'])));
        
        return empty($categories);
    }
    
    /**
     * Filter patterns based on visibility settings
     */
    public function filter_patterns_by_visibility($patterns) {
        // Debug logging
        error_log('[PatternsWP] Filter applied with ' . count((array)$patterns) . ' patterns');
        
        // If no patterns or not an array, return early
        if (empty($patterns) || !is_array($patterns)) {
            error_log('[PatternsWP] No patterns to filter');
            return $patterns;
        }

        $filtered_patterns = array();
        $hide_theme_patterns = get_option('patternswp_hide_theme_patterns', false);
        $hide_uncategorized = get_option('patternswp_hide_uncategorized_patterns', false);
        $hide_core_patterns = get_option('patternswp_hide_core_patterns', false);
        
        error_log('[PatternsWP] Settings - Hide Theme: ' . ($hide_theme_patterns ? 'true' : 'false') . 
                 ', Hide Uncategorized: ' . ($hide_uncategorized ? 'true' : 'false') . 
                 ', Hide Core: ' . ($hide_core_patterns ? 'true' : 'false'));

        foreach ($patterns as $pattern) {
            // Skip if pattern is not an array
            if (!is_array($pattern)) {
                $filtered_patterns[] = $pattern;
                continue;
            }

            // Skip theme patterns if enabled
            if ($hide_theme_patterns && $this->is_theme_pattern($pattern)) {
                error_log('[PatternsWP] Skipping theme pattern: ' . ($pattern['title'] ?? 'Unknown'));
                continue;
            }
            
            // Skip core patterns if enabled
            if ($hide_core_patterns && $this->is_core_pattern($pattern)) {
                error_log('[PatternsWP] Skipping core pattern: ' . ($pattern['title'] ?? 'Unknown'));
                continue;
            }

            // Skip uncategorized patterns if enabled
            if ($hide_uncategorized && $this->is_uncategorized_pattern($pattern)) {
                error_log('[PatternsWP] Skipping uncategorized pattern: ' . ($pattern['title'] ?? 'Unknown'));
                continue;
            }

            $filtered_patterns[] = $pattern;
        }

        error_log('[PatternsWP] Filtered to ' . count($filtered_patterns) . ' patterns');
        return $filtered_patterns;
    }
    
    /**
     * Register Pattern Visibility settings
     */
    public function register_pattern_visibility_settings() {
        // Register settings
        register_setting('patternswp_visibility_settings', 'patternswp_hide_theme_patterns', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));

        register_setting('patternswp_visibility_settings', 'patternswp_hide_uncategorized_patterns', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));

        register_setting('patternswp_visibility_settings', 'patternswp_hide_core_patterns', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));

        // Clear cache when settings are updated
        add_action('update_option_patternswp_hide_theme_patterns', array($this, 'clear_pattern_cache'), 10, 2);
        add_action('update_option_patternswp_hide_uncategorized_patterns', array($this, 'clear_pattern_cache'), 10, 2);
        add_action('update_option_patternswp_hide_core_patterns', array($this, 'clear_pattern_cache'), 10, 2);

        // Add settings section
        add_settings_section(
            'patternswp_visibility_section',
            __('Pattern Visibility', 'patternswp'),
            array($this, 'pattern_visibility_section_callback'),
            'patternswp-settings'
        );

        // Add settings fields
        add_settings_field(
            'patternswp_hide_theme_patterns',
            __('Hide Theme Patterns', 'patternswp'),
            array($this, 'render_checkbox_field'),
            'patternswp-settings',
            'patternswp_visibility_section',
            array(
                'label_for' => 'patternswp_hide_theme_patterns',
                'description' => __('Prevent patterns registered by the active theme from displaying in the patterns list.', 'patternswp')
            )
        );

        add_settings_field(
            'patternswp_hide_uncategorized_patterns',
            __('Hide Uncategorized Patterns', 'patternswp'),
            array($this, 'render_checkbox_field'),
            'patternswp-settings',
            'patternswp_visibility_section',
            array(
                'label_for' => 'patternswp_hide_uncategorized_patterns',
                'description' => __('Prevent any patterns not in any registered categories from displaying.', 'patternswp')
            )
        );

        add_settings_field(
            'patternswp_hide_core_patterns',
            __('Hide Core Patterns', 'patternswp'),
            array($this, 'render_checkbox_field'),
            'patternswp-settings',
            'patternswp_visibility_section',
            array(
                'label_for' => 'patternswp_hide_core_patterns',
                'description' => __('Remove all core patterns from the pattern selector by disabling core patterns.', 'patternswp')
            )
        );
    }

    /**
     * Pattern Visibility section callback
     */
    public function pattern_visibility_section_callback() {
        echo '<p>' . esc_html__('Control which patterns are visible in the block editor.', 'patternswp') . '</p>';
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args) {
        $option = get_option($args['label_for']);
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo esc_attr($args['label_for']); ?>" 
                   value="1" 
                   <?php checked(1, $option, true); ?>>
            <span class="description"><?php echo esc_html($args['description']); ?></span>
        </label>
        <?php
    }

    /**
     * Render Pattern Visibility page
     */
    public function render_pattern_visibility_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success/error messages
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'patternswp_messages',
                'patternswp_message',
                __('Settings Saved', 'patternswp'),
                'updated'
            );
        }
        
        // Show cache clear messages from transient
        $cache_message = get_transient('patternswp_cache_cleared');
        if ($cache_message) {
            add_settings_error(
                'patternswp_messages',
                'patternswp_cache_message',
                $cache_message,
                'updated'
            );
            // Delete the transient so it only shows once
            delete_transient('patternswp_cache_cleared');
        } elseif (isset($_GET['pt_msg']) && isset($_GET['status'])) {
            // Keep backward compatibility with URL parameters
            $message = sanitize_text_field(wp_unslash($_GET['pt_msg']));
            $status = sanitize_text_field(wp_unslash($_GET['status']));
            $type = $status === 'success' ? 'updated' : 'error';
            
            add_settings_error(
                'patternswp_messages',
                'patternswp_cache_message',
                $message,
                $type
            );
        }
        
        settings_errors('patternswp_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <?php 
                $this->patterswp_tab(''); 
                ?>
            </h2>
            <div class="patterns-wp-tabs-content">
                <div id="tab-1" class="patterns-wp-tab-content patterns-wp-tab-active">
                    <div class="wrap">
                        <div class="pw-feature-box big-box" style="max-width: 1280px; padding: 40px; background-color: white; border-radius: 10px; overflow: hidden; margin: 0 auto;">
                <form action="options.php" method="post">
                    <?php
                    // Output security fields
                    settings_fields('patternswp_visibility_settings');
                    // Output settings sections and fields
                    do_settings_sections('patternswp-settings');
                    // Output save settings button
                    submit_button(__('Save Settings', 'patternswp'));
                    ?>
                </form>
                
                <hr style="margin: 30px 0;">
                
                <h3><?php esc_html_e('Cache Management', 'patternswp'); ?></h3>
                <p><?php esc_html_e('Clear all cached patterns and data. This may be necessary if patterns are not updating correctly or after changing visibility settings.', 'patternswp'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('patternswp_clear_cache_action', 'patternswp_clear_cache_nonce'); ?>
                    <input type="hidden" name="patternswp_clear_cache" value="1">
                    <?php
                    submit_button(
                        __('Clear All Cache', 'patternswp'),
                        'primary',
                        'patternswp_clear_cache',
                        false
                    );
                    ?>
                </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
 * Redirect to welcome page after activation
 */
public function patternswp_redirect_on_activation() {
    if (get_transient('patternswp_activation_redirect')) {
        delete_transient('patternswp_activation_redirect');
        if (!is_network_admin() && !isset($_GET['activate-multi'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe usage: only checking if 'activate-multi' is set
            wp_safe_redirect(admin_url('admin.php?page=patternswp-plugin-menu'));
            exit;
        }
    }
}
}

new PatternsWP_Admin();