<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PatternsWP_API_Section {

    public $api_url;

    /** @var PatternsWP_API_Section|null */
    private static $instance = null;

    /** In-request library memo, keyed by license scope. */
    private $library_runtime = array();

    /**
     * Get singleton instance.
     *
     * @return PatternsWP_API_Section
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'admin_init', array( $this, 'register_patterns_endpoint') );
        add_action( 'patternswp_daily_transient_load', array( $this, 'patternswp_save_transient_if_not_ajax' ) );

        $this->api_url = 'https://pwp4.thepatternswp.com/';
    }

    /**
     * Register patterns from the site-wide library cache.
     */
    public function register_patterns_endpoint() {
        $library = $this->get_library( false );

        if ( empty( $library ) ) {
            if ( ! wp_doing_cron() && ! wp_next_scheduled( 'patternswp_daily_transient_load' ) ) {
                wp_schedule_single_event( time() + 5, 'patternswp_daily_transient_load' );
            }
            return;
        }

        try {
            $this->register_patterns_and_categories( $library );
        } catch ( \Throwable $e ) {
            return;
        }
    }

    /**
     * Get patterns for the library modal.
     *
     * Serves from a site-wide cache and filters locally so category clicks,
     * search, and pagination never hit the remote API.
     *
     * @param int    $page       Page number.
     * @param int    $p_per_page Patterns per page.
     * @param string $search     Search query.
     * @param string $category   Category slug, or empty for all.
     * @return array
     */
    public function get_patternswp_pattern( $page = 1, $p_per_page = 15, $search = '', $category = '' ) {
        $page       = max( 1, intval( $page ) );
        $p_per_page = max( 1, intval( $p_per_page ) );
        $search     = is_string( $search ) ? $search : '';
        $category   = is_string( $category ) ? $category : '';

        $patterns = $this->get_library( true );
        if ( empty( $patterns ) ) {
            return array();
        }

        $patterns = $this->filter_patterns_by_category( $patterns, $category );
        $patterns = $this->filter_patterns_by_search( $patterns, $search );
        $patterns = apply_filters( 'patternswp_patterns', $patterns );
        $patterns = $this->normalize_patterns_list( $patterns );

        $offset = ( $page - 1 ) * $p_per_page;
        return array_values( array_slice( $patterns, $offset, $p_per_page ) );
    }

    /**
     * Get patterns category type
     */
    public function get_patternswp_category_type( $manually = true ) {
        $transient_key = 'patternswp_category_type';
        $categories    = get_transient( $transient_key );

        if ( false === $categories ) {
            $categories_response = wp_remote_get(
                $this->api_url . 'wp-json/patternswp_pattens_category_types/v1/patterns',
                array( 'timeout' => 8 )
            );

            if ( is_wp_error( $categories_response ) ) {
                return array();
            }

            $categories = json_decode( wp_remote_retrieve_body( $categories_response ), true );
            if ( ! is_array( $categories ) || empty( $categories ) ) {
                return array();
            }

            set_transient( $transient_key, $categories, DAY_IN_SECONDS );
        }

        if ( $manually && is_array( $categories ) ) {
            foreach ( $categories as $category ) {
                if ( ! is_array( $category ) || empty( $category['name'] ) || ! is_string( $category['name'] ) ) {
                    continue;
                }
                register_block_pattern_category(
                    $category['name'],
                    array( 'label' => $this->get_category_label( $category['name'] ) )
                );
            }
        }

        return is_array( $categories ) ? $categories : array();
    }

    /**
     * Get license data
     */
    public function get_license_data() {
        $license_key      = '';
        $licensestatus    = false;
        $get_license_data = get_option( 'patternswp_plugin_license_data' );
        $stored_option    = get_option( 'patternswp_license_key', array() );

        if ( is_array( $stored_option ) && ! empty( $stored_option['patternswp_pro_license_key'] ) ) {
            $license_key = (string) $stored_option['patternswp_pro_license_key'];
        } elseif ( is_string( $stored_option ) ) {
            $license_key = $stored_option;
        }

        if ( is_array( $get_license_data ) && ! empty( $get_license_data['activated'] ) ) {
            $licensestatus = true;

            if ( '' === $license_key && ! empty( $get_license_data['license_key'] ) ) {
                $stored = $get_license_data['license_key'];
                if ( is_object( $stored ) && isset( $stored->key ) ) {
                    $license_key = (string) $stored->key;
                } elseif ( is_array( $stored ) && isset( $stored['key'] ) ) {
                    $license_key = (string) $stored['key'];
                } elseif ( is_string( $stored ) ) {
                    $license_key = $stored;
                }
            }
        }

        return array(
            'license_key'   => $license_key,
            'licensestatus' => $licensestatus,
        );
    }

    /**
     * Daily / manual cache warm.
     */
    public function patternswp_save_transient_if_not_ajax() {
        $this->patternswp_save_transient_wise_category();
    }

    /**
     * Refresh the shared library once. Category pages are served locally.
     */
    public function patternswp_save_transient_wise_category() {
        $this->refresh_library( true );
        $this->get_patternswp_category_type( false );
    }

    /**
     * Return the full library for the current license, from cache when possible.
     *
     * @param bool $refresh_if_empty Fetch remotely when the cache is cold.
     * @return array
     */
    private function get_library( $refresh_if_empty = false ) {
        $scope = $this->get_library_scope();

        if ( isset( $this->library_runtime[ $scope ] ) && is_array( $this->library_runtime[ $scope ] ) ) {
            return $this->library_runtime[ $scope ];
        }

        $cached = $this->read_library_cache( $scope );
        if ( ! empty( $cached ) ) {
            $this->library_runtime[ $scope ] = $cached;
            return $cached;
        }

        if ( ! $refresh_if_empty && ! wp_doing_cron() ) {
            return array();
        }

        $refreshed = $this->refresh_library( false );
        $this->library_runtime[ $scope ] = $refreshed;
        return $refreshed;
    }

    /**
     * Fetch and store the full pattern catalog once for this site.
     *
     * @param bool $force Bypass the in-flight lock wait when cron already owns the job.
     * @return array
     */
    private function refresh_library( $force = false ) {
        $scope = $this->get_library_scope();
        $lock  = 'patternswp_library_lock';

        if ( ! $force ) {
            $cached = $this->read_library_cache( $scope );
            if ( ! empty( $cached ) ) {
                return $cached;
            }
        }

        if ( ! $this->acquire_refresh_lock( $lock ) ) {
            $waited = $this->wait_for_library_cache( $scope, 8 );
            if ( ! empty( $waited ) && ! $force ) {
                return $waited;
            }
            if ( ! $force ) {
                return array();
            }
            set_transient( $lock, 1, MINUTE_IN_SECONDS );
        }

        $existing = $this->read_library_cache( $scope );
        $existing = $this->backfill_missing_categories( $existing );
        $fetched  = $this->fetch_full_catalog();
        $patterns = $this->merge_pattern_libraries( $fetched, $existing );
        $patterns = $this->backfill_missing_categories( $patterns );

        if ( ! empty( $patterns ) ) {
            $this->store_library_cache( $scope, $patterns );
            $this->library_runtime[ $scope ] = $patterns;
        } else {
            $patterns = $existing;
        }

        delete_transient( $lock );

        return is_array( $patterns ) ? $patterns : array();
    }

    /**
     * Download every pattern page and merge typed + bulk sources.
     *
     * Categories that the unfiltered stream missed (Page Templates live later
     * in the catalog and often ride on flaky/large responses) are backfilled
     * with a direct category query during this one refresh.
     *
     * @return array
     */
    private function fetch_full_catalog() {
        $typed = $this->fetch_all_paginated_patterns( '' );
        $typed = $this->backfill_missing_categories( $typed );
        $bulk  = $this->fetch_bulk_patterns();

        return $this->merge_pattern_libraries( $typed, $bulk );
    }

    /**
     * Fetch categories that did not appear in the unfiltered catalog.
     *
     * @param array $patterns Patterns already downloaded.
     * @return array
     */
    private function backfill_missing_categories( array $patterns ) {
        $categories = $this->get_patternswp_category_type( false );
        if ( empty( $categories ) || ! is_array( $categories ) ) {
            return $patterns;
        }

        foreach ( $categories as $category ) {
            $name = ( is_array( $category ) && isset( $category['name'] ) ) ? (string) $category['name'] : '';
            if ( '' === $name ) {
                continue;
            }

            $existing = $this->filter_patterns_by_category( $patterns, $name );
            if ( ! empty( $existing ) ) {
                continue;
            }

            $extra    = $this->fetch_all_paginated_patterns( $name );
            $patterns = $this->merge_pattern_libraries( $patterns, $extra );
            usleep( 400000 );
        }

        return $patterns;
    }

    /**
     * Fetch every page from the modal API (includes free/pro type).
     *
     * The remote API rejects large page sizes and consumes tokens per request,
     * so we page at 15–20 with a fresh token each time.
     *
     * @param string $category Optional category slug.
     * @return array
     */
    private function fetch_all_paginated_patterns( $category = '' ) {
        $per_page = 0;
        $first    = array();

        foreach ( array( 20, 15 ) as $size ) {
            $first = $this->fetch_paginated_page( 1, $size, $category );
            if ( ! empty( $first ) ) {
                $per_page = $size;
                break;
            }
        }

        if ( empty( $first ) || $per_page < 1 ) {
            return array();
        }

        $all          = $first;
        $page         = 2;
        $empty_streak = 0;

        while ( $page <= 50 ) {
            $batch = $this->fetch_paginated_page( $page, $per_page, $category );
            if ( empty( $batch ) ) {
                $empty_streak++;
                if ( $empty_streak >= 3 ) {
                    break;
                }
                $page++;
                continue;
            }

            $empty_streak = 0;
            $all          = array_merge( $all, $batch );

            if ( count( $batch ) < $per_page ) {
                break;
            }

            $page++;
        }

        return $this->normalize_patterns_list( $all );
    }

    /**
     * Fetch one paginated catalog page with a fresh token and retries.
     *
     * @param int    $page     Page number.
     * @param int    $per_page Page size the remote API actually honours.
     * @param string $category Optional category slug.
     * @return array
     */
    private function fetch_paginated_page( $page, $per_page, $category = '' ) {
        $license = $this->get_license_data();

        for ( $try = 0; $try < 4; $try++ ) {
            $token = $this->get_api_token();
            if ( empty( $token ) ) {
                usleep( 250000 );
                continue;
            }

            $url = add_query_arg(
                array(
                    'page'            => $page,
                    'patternsPerPage' => $per_page,
                    'search'          => '',
                    'category'        => $category,
                    'site_url'        => get_site_url(),
                    'license_key'     => ! empty( $license['licensestatus'] ) ? $license['license_key'] : '',
                    'licensestatus'   => ! empty( $license['licensestatus'] ) ? '1' : '',
                    'api_token'       => $token,
                    'plugin_version'  => PWP_P_VERSION,
                ),
                $this->api_url . 'wp-json/patternswps/v1/patterns'
            );

            $batch = $this->normalize_patterns_list( $this->remote_get_json( $url, 60 ) );
            if ( ! empty( $batch ) ) {
                return $batch;
            }

            usleep( 350000 );
        }

        return array();
    }

    /**
     * Fetch the bulk download used for Gutenberg registration.
     *
     * @return array
     */
    private function fetch_bulk_patterns() {
        $license = $this->get_license_data();

        for ( $try = 0; $try < 3; $try++ ) {
            $token = $this->get_api_token();
            if ( empty( $token ) ) {
                usleep( 250000 );
                continue;
            }

            $url = add_query_arg(
                array(
                    'license_key'   => ! empty( $license['licensestatus'] ) ? $license['license_key'] : '',
                    'licensestatus' => ! empty( $license['licensestatus'] ) ? '1' : '',
                    'api_token'     => $token,
                ),
                $this->api_url . 'wp-json/patternswps_wp/v1/patterns_wp'
            );

            $batch = $this->normalize_patterns_list( $this->remote_get_json( $url, 45 ) );
            if ( ! empty( $batch ) ) {
                return $batch;
            }

            usleep( 350000 );
        }

        return array();
    }

    /**
     * @param string $url     Request URL.
     * @param int    $timeout Timeout in seconds.
     * @return mixed
     */
    private function remote_get_json( $url, $timeout = 20 ) {
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => $timeout,
                'method'  => 'GET',
            )
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return null;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : null;
    }

    /**
     * Prefer typed (paginated) records so Pro badges survive a bulk merge.
     *
     * @param array $typed_patterns Patterns from the paginated API.
     * @param array $bulk_patterns  Patterns from the bulk API.
     * @return array
     */
    private function merge_pattern_libraries( array $typed_patterns, array $bulk_patterns ) {
        $merged = array();

        foreach ( $typed_patterns as $pattern ) {
            if ( ! is_array( $pattern ) ) {
                continue;
            }
            $key = $this->get_pattern_library_key( $pattern );
            if ( '' === $key ) {
                continue;
            }
            $merged[ $key ] = $pattern;
        }

        foreach ( $bulk_patterns as $pattern ) {
            if ( ! is_array( $pattern ) ) {
                continue;
            }
            $key = $this->get_pattern_library_key( $pattern );
            if ( '' === $key ) {
                continue;
            }
            if ( isset( $merged[ $key ] ) ) {
                if ( empty( $merged[ $key ]['categories'] ) && ! empty( $pattern['categories'] ) ) {
                    $merged[ $key ]['categories'] = $pattern['categories'];
                }
                continue;
            }
            $pattern['type'] = 'free';
            $merged[ $key ]  = $pattern;
        }

        return array_values( $merged );
    }

    /**
     * @param array $pattern Pattern payload.
     * @return string
     */
    private function get_pattern_library_key( array $pattern ) {
        return strtolower( trim( (string) ( $pattern['title'] ?? '' ) ) );
    }

    /**
     * @return string
     */
    private function get_library_scope() {
        $license = $this->get_license_data();
        return ! empty( $license['licensestatus'] ) ? 'pro' : 'free';
    }

    /**
     * @param string $scope License scope.
     * @return string
     */
    private function get_library_prefix( $scope ) {
        return 'patternswp_lib_' . sanitize_key( $scope ) . '_';
    }

    /**
     * @param string $scope License scope.
     * @return array
     */
    private function read_library_cache( $scope ) {
        $prefix      = $this->get_library_prefix( $scope );
        $chunk_count = (int) get_transient( $prefix . 'count' );
        $patterns    = array();

        if ( $chunk_count > 0 ) {
            for ( $i = 0; $i < $chunk_count && $i < 50; $i++ ) {
                $chunk = get_transient( $prefix . $i );
                if ( false === $chunk ) {
                    return array();
                }
                if ( is_array( $chunk ) ) {
                    $patterns = array_merge( $patterns, $chunk );
                }
            }

            return $this->normalize_patterns_list( $patterns );
        }

        return $this->get_legacy_cached_patterns();
    }

    /**
     * Legacy chunked cache from earlier plugin versions.
     *
     * @return array
     */
    private function get_legacy_cached_patterns() {
        $cached_patterns = array();

        for ( $i = 0; $i < 50; $i++ ) {
            $transient_data = get_transient( 'patterns_cache_' . $i );
            if ( false === $transient_data ) {
                break;
            }
            if ( is_array( $transient_data ) ) {
                $cached_patterns = array_merge( $cached_patterns, $transient_data );
            }
        }

        return $this->normalize_patterns_list( $cached_patterns );
    }

    /**
     * @param string $scope    License scope.
     * @param array  $patterns Pattern list.
     */
    private function store_library_cache( $scope, array $patterns ) {
        $prefix     = $this->get_library_prefix( $scope );
        $chunk_size = 10;
        $chunks     = array_chunk( $patterns, $chunk_size );

        $this->delete_library_cache( $scope );

        foreach ( $chunks as $index => $chunk ) {
            set_transient( $prefix . $index, $chunk, DAY_IN_SECONDS );
        }

        set_transient( $prefix . 'count', count( $chunks ), DAY_IN_SECONDS );

        // Keep the legacy Gutenberg cache in sync for older readers.
        $this->delete_legacy_pattern_cache();
        foreach ( $chunks as $index => $chunk ) {
            set_transient( 'patterns_cache_' . $index, $chunk, DAY_IN_SECONDS );
        }
    }

    /**
     * @param string $scope License scope.
     */
    private function delete_library_cache( $scope ) {
        $prefix = $this->get_library_prefix( $scope );
        $count  = (int) get_transient( $prefix . 'count' );

        for ( $i = 0; $i < max( $count, 80 ); $i++ ) {
            delete_transient( $prefix . $i );
        }

        delete_transient( $prefix . 'count' );
    }

    /**
     * Remove legacy bulk chunks.
     */
    private function delete_legacy_pattern_cache() {
        for ( $i = 0; $i < 50; $i++ ) {
            delete_transient( 'patterns_cache_' . $i );
        }
    }

    /**
     * @param string $lock_key Lock transient.
     * @return bool
     */
    private function acquire_refresh_lock( $lock_key ) {
        if ( false !== get_transient( $lock_key ) ) {
            return false;
        }

        set_transient( $lock_key, 1, MINUTE_IN_SECONDS );
        return true;
    }

    /**
     * @param string $scope License scope.
     * @param int    $tries Poll attempts.
     * @return array
     */
    private function wait_for_library_cache( $scope, $tries = 8 ) {
        for ( $i = 0; $i < $tries; $i++ ) {
            usleep( 250000 );
            $cached = $this->read_library_cache( $scope );
            if ( ! empty( $cached ) ) {
                return $cached;
            }
        }

        return array();
    }

    /**
     * Normalize API/cache payloads into a list of pattern arrays.
     *
     * @param mixed $patterns Raw patterns payload.
     * @return array
     */
    private function normalize_patterns_list( $patterns ) {
        if ( is_string( $patterns ) ) {
            $decoded  = json_decode( $patterns, true );
            $patterns = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : array();
        }

        if ( ! is_array( $patterns ) ) {
            return array();
        }

        foreach ( array( 'patterns', 'data', 'items', 'results' ) as $key ) {
            if ( isset( $patterns[ $key ] ) && is_array( $patterns[ $key ] ) ) {
                $patterns = $patterns[ $key ];
                break;
            }
        }

        $normalized = array();
        foreach ( $patterns as $pattern ) {
            if ( ! is_array( $pattern ) ) {
                continue;
            }

            $title   = isset( $pattern['title'] ) ? (string) $pattern['title'] : '';
            $content = isset( $pattern['content'] ) ? (string) $pattern['content'] : '';
            if ( '' === $title || '' === $content ) {
                continue;
            }

            $categories = isset( $pattern['categories'] ) ? $pattern['categories'] : array();
            if ( is_string( $categories ) ) {
                $categories = array_filter( array_map( 'trim', explode( ',', $categories ) ) );
            }
            if ( ! is_array( $categories ) ) {
                $categories = array();
            }

            $type = isset( $pattern['type'] ) ? strtolower( (string) $pattern['type'] ) : 'free';
            if ( ! in_array( $type, array( 'free', 'pro' ), true ) ) {
                $type = 'free';
            }

            $pattern['title']      = $title;
            $pattern['content']    = $content;
            $pattern['categories'] = array_values( array_filter( $categories, 'is_string' ) );
            $pattern['type']       = $type;
            $normalized[]          = $pattern;
        }

        return $normalized;
    }

    /**
     * Request a one-time API token. The remote API invalidates reused tokens.
     *
     * @return string|false
     */
    public function get_api_token() {
        $timestamp = time();
        $url       = add_query_arg(
            array(
                'timestamp'  => $timestamp,
                'unique_key' => md5( (string) $timestamp ),
            ),
            $this->api_url . 'wp-json/patternswps_token/v1/token'
        );

        $token = $this->remote_get_json( $url, 10 );
        if ( is_array( $token ) ) {
            $token = isset( $token['token'] ) ? $token['token'] : reset( $token );
        }
        if ( is_string( $token ) ) {
            $token = trim( $token, "\" \t\n\r\0\x0B" );
        }

        return ( is_string( $token ) && '' !== $token ) ? $token : false;
    }

    /**
     * @param array  $patterns Pattern list.
     * @param string $search   Search query.
     * @return array
     */
    private function filter_patterns_by_search( $patterns, $search ) {
        $search = trim( (string) $search );
        if ( '' === $search || empty( $patterns ) ) {
            return is_array( $patterns ) ? $patterns : array();
        }

        $needle = strtolower( $search );
        return array_values(
            array_filter(
                $patterns,
                function ( $pattern ) use ( $needle ) {
                    if ( ! is_array( $pattern ) ) {
                        return false;
                    }
                    $title = isset( $pattern['title'] ) ? strtolower( (string) $pattern['title'] ) : '';
                    return false !== strpos( $title, $needle );
                }
            )
        );
    }

    /**
     * @param array  $patterns Pattern list.
     * @param string $category Category slug.
     * @return array
     */
    private function filter_patterns_by_category( $patterns, $category ) {
        $category = trim( (string) $category );
        if ( '' === $category || empty( $patterns ) ) {
            return is_array( $patterns ) ? $patterns : array();
        }

        $wanted = array(
            $category,
            sanitize_title( $category ),
        );

        return array_values(
            array_filter(
                $patterns,
                function ( $pattern ) use ( $wanted ) {
                    if ( ! is_array( $pattern ) ) {
                        return false;
                    }

                    $categories = isset( $pattern['categories'] ) && is_array( $pattern['categories'] )
                        ? $pattern['categories']
                        : array();

                    foreach ( $categories as $assigned ) {
                        if ( ! is_string( $assigned ) ) {
                            continue;
                        }
                        if ( in_array( $assigned, $wanted, true ) ) {
                            return true;
                        }
                        if ( in_array( sanitize_title( $assigned ), $wanted, true ) ) {
                            return true;
                        }
                    }

                    return false;
                }
            )
        );
    }

    /**
     * Register patterns and categories for the native Gutenberg inserter.
     * Free sites only register free patterns so Pro content stays gated.
     */
    private function register_patterns_and_categories( $patterns ) {
        $patterns = $this->normalize_patterns_list( $patterns );
        $patterns = apply_filters( 'patternswp_patterns', $patterns );
        $patterns = $this->normalize_patterns_list( $patterns );

        if ( empty( $patterns ) ) {
            return;
        }

        $licensed              = ! empty( $this->get_license_data()['licensestatus'] );
        $registered_categories = array();
        $registry              = \WP_Block_Patterns_Registry::get_instance();

        foreach ( $patterns as $pattern ) {
            if ( ! is_array( $pattern ) ) {
                continue;
            }

            if ( ! $licensed && isset( $pattern['type'] ) && 'pro' === $pattern['type'] ) {
                continue;
            }

            $categories      = isset( $pattern['categories'] ) && is_array( $pattern['categories'] ) ? $pattern['categories'] : array();
            $pattern_title   = isset( $pattern['title'] ) ? (string) $pattern['title'] : '';
            $pattern_content = isset( $pattern['content'] ) ? (string) $pattern['content'] : '';

            if ( '' === $pattern_title || '' === $pattern_content ) {
                continue;
            }

            $category_slugs = array();
            foreach ( $categories as $category ) {
                if ( ! is_string( $category ) || '' === trim( $category ) ) {
                    continue;
                }

                $category_slug = sanitize_title( $category );
                if ( '' === $category_slug ) {
                    continue;
                }

                $category_slugs[] = $category_slug;

                if ( ! in_array( $category_slug, $registered_categories, true ) ) {
                    register_block_pattern_category(
                        $category_slug,
                        array( 'label' => $this->get_category_label( $category ) )
                    );
                    $registered_categories[] = $category_slug;
                }
            }

            $pattern_name = 'patternswp-gutenberg-block-patterns/' . sanitize_title( $pattern_title );
            if ( $registry->is_registered( $pattern_name ) ) {
                continue;
            }

            register_block_pattern(
                $pattern_name,
                array(
                    'title'      => $pattern_title,
                    'content'    => $pattern_content,
                    'categories' => $category_slugs,
                )
            );
        }
    }

    /**
     * @param string $category Category slug or label.
     * @return string
     */
    private function get_category_label( $category ) {
        $label = (string) $category;
        $label = preg_replace( '/^patternswp[-_]/i', '', $label );
        $label = str_replace( array( '-', '_' ), ' ', $label );
        return ucwords( $label );
    }
}

PatternsWP_API_Section::get_instance();
