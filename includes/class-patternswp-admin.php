<?php
/**
 * Admin Page
 */

class PatternsWP_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Add menu and pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'patternswp_clear_cache'));
        add_action( 'admin_init', array( $this, 'patternswp_ensure_hourly_cron' ) );
        add_action( 'wp_ajax_patternswp_background_transient_load_ajax', array( $this, 'patternswp_background_transient_load_ajaxcc' ) );
        add_action( 'wp_ajax_nopriv_patternswp_background_transient_load_ajax', array( $this, 'patternswp_background_transient_load_ajaxcc' ) );
        add_action( 'patternswp_load_patterns_by_ra', array( $this, 'patternswp_load_patterns_by_remote_ajax' ) );
        register_activation_hook(plugin_dir_path(__DIR__) . 'patternswp.php', array($this, 'patternswp_on_activation'));
        add_action('admin_init', array($this, 'patternswp_redirect_on_activation'));
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
        $this->add_subpage($menu_slug, 'Support', 'Support', 'patternswp-plugin-page-1', array($this, 'patternswp_support'));
        $this->add_subpage($menu_slug, 'Clear Cache', 'Clear Cache', 'patternswp-clear-cache', array($this, 'patternswp_clear_cache_form'));
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
            'Support' => 'patternswp-plugin-page-1',
            'Clear Cache' => 'patternswp-clear-cache',
            'License' => 'patternswp-license_section'
        );

        foreach ($tabs as $name => $slug) {
            $class = ( $tab === $name ) ? 'nav-tab-active' : '';
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
                        <div class="container" style="padding: 20px; background-color: white; border-radius: 5px; overflow: hidden; width: 70%;">
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

            //load patterns in background
            $this->patternswp_load_patterns_by_remote_ajax();
            
            wp_redirect(add_query_arg(array(
                'page'   => 'patternswp-clear-cache',
                'pt_msg' => urlencode('Clear Cache Successfully'),
                'status' => 'success'
            ), admin_url('admin.php')));
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