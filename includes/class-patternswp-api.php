<?php
class PatternsWP_API_Section {

    public $api_url;

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_patterns_endpoint') );
        add_action('patternswp_hourly_transient_load', array( $this, 'patternswp_save_transient_if_not_ajax' ) );

        // API URL
        $this->api_url = 'https://pwp4.thepatternswp.com/';
    }

    /**
     * Register patterns endpoint
     */
    public function register_patterns_endpoint() {

        $license_key = $licensestatus = '';
        $chunk_size = 50;
        $transient_expiry = DAY_IN_SECONDS;
        $transient_base = 'patterns_cache_';
    
        // Check for existing transients and fetch patterns if they exist
        $cached_patterns = array();
        for ($i = 0; $i < 50; $i++) { // Assuming a maximum of 10 transients (adjust as needed)
            $transient_data = get_transient( $transient_base . $i );
            if ($transient_data !== false) {
                $cached_patterns = array_merge($cached_patterns, $transient_data);
            } else {
                break;
            }
        }
    
        // If cached patterns are found, register patterns and categories
        if (!empty( $cached_patterns ) ) {
            $this->register_patterns_and_categories($cached_patterns);
            return;
        }else{
    
        // Define the API URL for fetching patterns.
            $patterns_api_url = $this->api_url . 'wp-json/patternswps_wp/v1/patterns_wp';
            $get_lc_data = $this->get_license_data();
            if (!empty($get_lc_data)) {
                // Get license data
                $license_key = $get_lc_data['license_key'];
                $licensestatus = $get_lc_data['licensestatus'];
            }
        
            // Get API token
            $api_token = $this->get_api_token();
            // Set the request arguments including pagination parameters.
            $request_args = array(
                'timeout' => 10,
                'method'  => 'GET',
                'body'    => array(
                    'license_key'   => $license_key,
                    'licensestatus' => $licensestatus,
                    'api_token'     => $api_token,
                ),
            );
        
            // Perform the GET request to fetch patterns.
            $response = wp_remote_get($patterns_api_url, $request_args);
        
            // Check for request errors.
            if (is_wp_error($response)) {
                // error_log('Failed to fetch patterns: ' . $response->get_error_message());
                return array();
            }
        
            // Retrieve and decode the response body.
            $response_body = wp_remote_retrieve_body($response);
            $patterns = json_decode($response_body, true);
        
            // Check if the response body was successfully decoded.
            if (json_last_error() !== JSON_ERROR_NONE) {
                // error_log('Failed to decode JSON response: ' . json_last_error_msg());
                return array();
            }
        
            // Return empty array if patterns are not found.
            if (empty($patterns)) {
                // error_log('Received empty response for patterns.');
                return array();
            }
        
            // Cache patterns in transients with chunking
            $chunks = array_chunk($patterns, $chunk_size);
            foreach ($chunks as $index => $chunk) {
                set_transient( $transient_base . $index, $chunk, $transient_expiry );
            }
        
            // Register patterns and categories
            $this->register_patterns_and_categories($patterns);
        }
    }

    /**
     * Get API token
     */
    public function get_api_token() {

        $app_token = 'patternswp_app_token';
        $timestamp = time(); // Get current timestamp

        // Concatenate the value and timestamp
        $unique_key = $app_token . $timestamp;

        // Generate a hash of the concatenated value
        $hashed_key = md5( $timestamp );
  
        $patterns_api_token_url = $this->api_url . 'wp-json/patternswps_token/v1/token';
        $request_args = array(
            'timeout' => '10',
            'method'  => 'GET',
            'body'    => array(
                'timestamp'  => $timestamp,
                'unique_key' => $hashed_key
            )
        );
    
        $response = wp_remote_get( $patterns_api_token_url, $request_args );

        if (is_wp_error($response)) {
            return false;
        }
    
        $token = json_decode( wp_remote_retrieve_body( $response ), true );
        return $token;
    }
    
    /**
     * Get patterns category type
     */
    public function get_patternswp_category_type( $manually = true ) {
        // Define the transient key and timeout.
        $transient_key = 'patternswp_category_type';
        $transient_timeout = HOUR_IN_SECONDS;
    
        // Check if the transient exists.
        $categories = get_transient($transient_key);
    
        // If the transient does not exist, fetch the data.
        if ( $categories === false ) {

            // Fetch pattern categories from the new API.
            $categories_api_url = $this->api_url . 'wp-json/patternswp_pattens_category_types/v1/patterns';
            $categories_response = wp_remote_get($categories_api_url);
            $categories_body = wp_remote_retrieve_body($categories_response);
    
            // Decode JSON response.
            $categories = json_decode($categories_body, true);
    
            // If categories were successfully fetched, add them to the localized data.
            if ( !is_wp_error( $categories_response ) && !empty( $categories ) ) {
                if ( $manually ) {
                    foreach ( $categories as $category ) {
                        if (isset($category['name'])) {
                            register_block_pattern_category($category['name'], array('label' => $category['name']));
                        }
                    }
                }
    
                // Save categories data to transient for 1 hour.
                set_transient($transient_key, $categories, $transient_timeout);
            // } else {
                // error_log('Failed to fetch pattern categories or received an empty response.');
            }
        }
    
        return $categories;
    }

    /**
     * Get patterns from the API
     */
    public function get_patternswp_pattern( $page = 1, $p_per_page = 15, $search  = '', $category = '' ) {
        $license_key       = $licensestatus = '';
        
        // Validate parameters.
        $page              = intval($page) > 0 ? intval($page) : 1;
        $p_per_page        = intval( $p_per_page ) > 0 ? intval( $p_per_page ) : 15;
        
        // Define the API URL for fetching patterns.
        $patterns_api_url  = $this->api_url . 'wp-json/patternswps/v1/patterns';
        $get_lc_data       = $this->get_license_data();
        if( !empty( $get_lc_data ) ){
            //get license data
            $license_key   = $get_lc_data['license_key'];
            $licensestatus = $get_lc_data['licensestatus'];
        }

        //Get site url OR plugin Version OR Get API Token
        $site_url          = get_site_url();
        $plugin_version    = PWP_P_VERSION;
        $api_token         = $this->get_api_token();

        // Set the request arguments including pagination parameters.
        $request_args = array(
            'timeout' => 10,
            'method'  => 'GET',
            'body'    => array(
                'page'            => $page,
                'patternsPerPage' => $p_per_page,
                'search'          => $search,
                'category'        => $category,
                'site_url'        => $site_url,
                'license_key'     => $license_key,
                'licensestatus'   => $licensestatus,
                'api_token'       => $api_token,
                'plugin_version'  => $plugin_version
            ),
        );
    
        // Perform the GET request to fetch patterns.
        $response = wp_remote_get( $patterns_api_url, $request_args );
    
        // Check for request errors.
        if (is_wp_error($response)) {
            // error_log('Failed to fetch patterns: ' . $response->get_error_message());
            return array();
        }
    
        // Retrieve and decode the response body.
        $response_body = wp_remote_retrieve_body( $response );
        $patterns      = json_decode($response_body, true);
    
        // Check if the response body was successfully decoded.
        if (json_last_error() !== JSON_ERROR_NONE) {
            // error_log('Failed to decode JSON response: ' . json_last_error_msg());
            return array();
        }
    
        // Return the patterns if not empty.
        if (!empty($patterns)) {
            return $patterns;
        } else {
            // error_log('Received empty response for patterns.');
            return array();
        }
    }

    /**
     * Get license data
     */
    public function get_license_data(){
        $license_key = $licensestatus = '';
        $get_license_data  = get_option( 'patternswp_plugin_license_data' );   
        if( is_array( $get_license_data ) && isset( $get_license_data['license_key'] ) && !empty( $get_license_data['license_key'] ) && $get_license_data['activated'] === true ){
            $license_key   = $get_license_data['license_key']->key;
            $licensestatus = isset( $get_license_data['activated'] ) ? $get_license_data['activated'] : false ;
        }

        return array( 
            'license_key'   => $license_key,
            'licensestatus' => $licensestatus 
        );
    }

    /**
     * Register patterns and categories
     */
    private function register_patterns_and_categories( $patterns ) {
        $registered_categories = array();
    
        foreach ($patterns as $pattern) {
            $categories      = $pattern['categories'];
            $pattern_title   = $pattern['title'];
            $pattern_content = $pattern['content'];
    
            // Convert comma-separated categories to an array
            if (is_string($categories)) {
                $categories = array_map('trim', explode(',', $categories));
            }
    
            foreach ($categories as $category) {
                // Register block pattern category if not already registered
                if (!in_array($category, $registered_categories)) {
                    $category_label = $this->get_category_label( $category ); // Define this method to get the category label
                    register_block_pattern_category($category, array('label' => $category_label));
                    $registered_categories[] = $category;
                }
            }
    
            // Register block pattern
            register_block_pattern(
                'patternswp-gutenberg-block-patterns/' . sanitize_title( $pattern_title ),
                array(
                    'title'      => $pattern_title,
                    'content'    => $pattern_content,
                    'categories' => $categories, // This is now an array of categories
                )
            );
        }
    }

    /**
     * Get the category label
     */
    private function get_category_label($category) {
        // Define how to get the category label based on your needs
        // This is just a placeholder implementation
        return ucfirst($category); // Example: Capitalize the category name for the label
    }

    /**
     * Save transient if not ajax
     */
    public function patternswp_save_transient_if_not_ajax() {
        $this->patternswp_save_transient_wise_category();
    }

    /**
     * Save transient wise category
     */
    public function patternswp_save_transient_wise_category(){
        $get_patternswps_all_cats = $this->get_patternswp_category_type( false );
        $patterns_per_page = 15;
        $search = '';
        if ( ! empty( $get_patternswps_all_cats ) ) {
            array_unshift( $get_patternswps_all_cats, [ 'name' => '' ] );
            foreach ( $get_patternswps_all_cats as $patternwp_cat ) {
                $cat_name = sanitize_text_field( $patternwp_cat['name'] );
                if ( $cat_name === '' ) {
                    for ( $page = 1; $page <= 7; $page++ ) {
                        $cache_key = 'patternswp_' . md5($page . '-' . $patterns_per_page . '-' . $search . '-' . $cat_name);
                        $check_is_exist = get_transient( $cache_key );
                        
                        if ( ! $check_is_exist ) {
                            $cache_patterns = $this->get_patternswp_pattern( $page, $patterns_per_page, $search, $cat_name );
                            
                            if ( ! empty( $cache_patterns ) ) {
                                set_transient( $cache_key, $cache_patterns, 3600 );
                            }
                        }
                    }
                } else {
                    $page = 1;
                    $cache_key      = 'patternswp_' . md5($page . '-' . $patterns_per_page . '-' . $search . '-' . $cat_name);
                    $check_is_exist = get_transient( $cache_key );
                    if ( ! $check_is_exist ) {
                        $cache_patterns = $this->get_patternswp_pattern( $page, $patterns_per_page, $search, $cat_name );
            
                        if ( ! empty( $cache_patterns ) ) {
                            set_transient( $cache_key, $cache_patterns, 3600 );
                        }
                    }
                }
            }
        }
    }
}

$patternswp_api_section = new PatternsWP_API_Section();