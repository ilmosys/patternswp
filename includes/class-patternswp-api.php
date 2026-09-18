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
        add_action( 'patternswp_library_warm', array( $this, 'warm_library' ) );

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

        $patterns = $this->get_library( false );
        if ( empty( $patterns ) ) {
            $this->schedule_library_refresh();
            $seeded = $this->seed_library_for_request( $page, $p_per_page, $search, $category );
            $seeded = $this->filter_patterns_by_category( $seeded, $category );
            $seeded = $this->filter_patterns_by_search( $seeded, $search );
            $seeded = apply_filters( 'patternswp_patterns', $seeded );
            $seeded = $this->normalize_patterns_list( $seeded );

            if ( '' === $search ) {
                return array_values( array_slice( $seeded, 0, $p_per_page ) );
            }

            $offset = ( $page - 1 ) * $p_per_page;
            return array_values( array_slice( $seeded, $offset, $p_per_page ) );
        } elseif ( '' !== $category && ! $this->is_library_complete() ) {
            $in_category = $this->filter_patterns_by_category( $patterns, $category );
            if ( empty( $in_category ) ) {
                $this->schedule_library_refresh();
                $extra = $this->fetch_paginated_page( 1, 20, $category );
                if ( ! empty( $extra ) ) {
                    $patterns = $this->merge_pattern_libraries( $extra, $patterns );
                    $this->store_library_cache( $this->get_library_scope(), $patterns );
                    $this->library_runtime[ $this->get_library_scope() ] = $patterns;
                }
            }
        }

        if ( empty( $patterns ) ) {
            return array();
        }

        $filtered = $this->filter_patterns_by_category( $patterns, $category );
        $filtered = $this->filter_patterns_by_search( $filtered, $search );
        $filtered = apply_filters( 'patternswp_patterns', $filtered );
        $filtered = $this->normalize_patterns_list( $filtered );

        $offset = ( $page - 1 ) * $p_per_page;
        $slice  = array_values( array_slice( $filtered, $offset, $p_per_page ) );

        if ( empty( $slice ) && ! $this->is_library_complete() && '' === $search ) {
            $this->schedule_library_refresh();
            $remote = $this->fetch_paginated_page( $page, min( 20, max( 15, $p_per_page ) ), $category );
            if ( ! empty( $remote ) ) {
                $scope    = $this->get_library_scope();
                $patterns = $this->merge_pattern_libraries( $remote, $patterns );
                $this->store_library_cache( $scope, $patterns );
                $this->library_runtime[ $scope ] = $patterns;

                $filtered = $this->filter_patterns_by_category( $patterns, $category );
                $filtered = apply_filters( 'patternswp_patterns', $filtered );
                $filtered = $this->normalize_patterns_list( $filtered );
                $slice    = array_values( array_slice( $filtered, $offset, $p_per_page ) );

                if ( empty( $slice ) ) {
                    return array_values( array_slice( $remote, 0, $p_per_page ) );
                }
            }
        }

        return $slice;
    }

    /**
     * Get patterns category type
     */
    public function get_patternswp_category_type( $manually = true ) {
        $transient_key = 'patternswp_category_type';
        $categories    = get_transient( $transient_key );

        if ( false === $categories ) {
            if ( ! wp_doing_ajax() && ! wp_doing_cron() ) {
                $this->schedule_library_refresh();
                $categories = $this->get_fallback_categories();
            } else {
                $categories = $this->remote_get_json(
                    $this->api_url . 'wp-json/patternswp_pattens_category_types/v1/patterns',
                    8
                );

                if ( ! is_array( $categories ) || empty( $categories ) ) {
                    $this->schedule_library_refresh();
                    return $this->get_fallback_categories();
                }

                set_transient( $transient_key, $categories, DAY_IN_SECONDS );
            }
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
        $get_license_data = get_option( 'patternswp_plugin_license_data' );
        $stored_option    = get_option( 'patternswp_license_key', array() );

        if ( is_array( $stored_option ) && ! empty( $stored_option['patternswp_pro_license_key'] ) ) {
            $license_key = (string) $stored_option['patternswp_pro_license_key'];
        } elseif ( is_string( $stored_option ) ) {
            $license_key = $stored_option;
        }

        if ( is_array( $get_license_data ) && ! empty( $get_license_data['license_key'] ) && '' === $license_key ) {
            $stored = $get_license_data['license_key'];
            if ( is_object( $stored ) && isset( $stored->key ) ) {
                $license_key = (string) $stored->key;
            } elseif ( is_array( $stored ) && isset( $stored['key'] ) ) {
                $license_key = (string) $stored['key'];
            } elseif ( is_string( $stored ) ) {
                $license_key = $stored;
            }
        }

        $activated = is_array( $get_license_data ) && isset( $get_license_data['activated'] )
            ? $get_license_data['activated']
            : false;

        return array(
            'license_key'   => $license_key,
            'licensestatus' => $this->is_activated_flag( $activated ) && '' !== trim( $license_key ),
        );
    }

    /**
     * Whether Pro is unlocked for this site.
     *
     * Requires a stored license key AND a verified activation flag.
     *
     * @return bool
     */
    public function is_license_active() {
        $license = $this->get_license_data();
        return ! empty( $license['licensestatus'] );
    }

    /**
     * @param mixed $value Raw `activated` flag from license storage.
     * @return bool
     */
    private function is_activated_flag( $value ) {
        if ( true === $value || 1 === $value || '1' === $value ) {
            return true;
        }

        return is_string( $value ) && 'true' === strtolower( $value );
    }

    /**
     * Daily cache warm (forced).
     */
    public function patternswp_save_transient_if_not_ajax() {
        $this->refresh_library( true );
        $this->get_patternswp_category_type( false );
        update_option( 'patternswp_lib_checked_at', time(), false );
    }

    /**
     * Resume a time-budgeted library download.
     */
    public function warm_library() {
        $this->refresh_library( false, wp_doing_ajax() ? 10 : 18 );
        $this->get_patternswp_category_type( false );
    }

    /**
     * Refresh the shared library once. Category pages are served locally.
     */
    public function patternswp_save_transient_wise_category() {
        $this->refresh_library( true );
        $this->get_patternswp_category_type( false );
    }

    /**
     * Drop cached catalogs and queue a background refill.
     */
    public function purge_library_caches() {
        $this->delete_library_cache( 'free' );
        $this->delete_library_cache( 'pro' );
        $this->delete_legacy_pattern_cache();
        $this->delete_library_file( 'free' );
        $this->delete_library_file( 'pro' );
        delete_transient( 'patternswp_category_type' );
        delete_transient( 'patternswp_library_lock' );
        delete_option( 'patternswp_lib_state' );
        delete_option( 'patternswp_lib_checked_at' );
        $this->library_runtime = array();
    }

    /**
     * Queue WP-Cron to finish downloading the catalog without blocking admin AJAX.
     *
     * @param int $delay Seconds to wait before the first warm run.
     */
    public function schedule_library_refresh( $delay = 1 ) {
        $hook = 'patternswp_library_warm';
        if ( ! wp_next_scheduled( $hook ) ) {
            wp_schedule_single_event( time() + max( 0, (int) $delay ), $hook );
            if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
                spawn_cron();
            }
        }

        if ( ! wp_next_scheduled( 'patternswp_daily_transient_load' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'patternswp_daily_transient_load' );
        }
    }

    /**
     * Start a background download when this site has no catalog yet.
     */
    public function maybe_schedule_warm() {
        if ( empty( $this->get_library( false ) ) || ! $this->is_library_complete() ) {
            $this->schedule_library_refresh( 1 );
        }
    }

    /**
     * Whether the cached catalog is complete enough to serve locally.
     *
     * @return bool
     */
    public function is_catalog_ready() {
        return $this->is_library_complete();
    }

    /**
     * Progress payload for the editor warmer.
     *
     * @param bool $has_updates Whether new remote patterns were merged.
     * @return array
     */
    public function get_library_status( $has_updates = false ) {
        $patterns = $this->get_library( false );

        return array(
            'total'      => count( $patterns ),
            'complete'   => $this->is_library_complete(),
            'categories' => $this->get_patternswp_category_type( false ),
            'has_updates' => (bool) $has_updates,
        );
    }

    /**
     * Cheap check for newly published remote patterns.
     *
     * @param bool $force Ignore the 6-hour throttle.
     * @return array
     */
    public function maybe_pull_remote_updates( $force = false ) {
        $last     = (int) get_option( 'patternswp_lib_checked_at', 0 );
        $complete = $this->is_library_complete();

        if ( ! $force && $complete && $last && ( time() - $last ) < ( 6 * HOUR_IN_SECONDS ) ) {
            return $this->get_library_status();
        }

        $before = count( $this->get_library( false ) );
        $batch  = $this->fetch_paginated_page( 1, 20, '' );
        $pro    = $this->fetch_paginated_page( 1, 20, '', 'pro' );
        update_option( 'patternswp_lib_checked_at', time(), false );

        $has_updates = false;
        $scope       = $this->get_library_scope();
        $merged      = $this->get_library( false );

        if ( ! empty( $batch ) ) {
            $merged = $this->merge_pattern_libraries( $batch, $merged );
        }
        if ( ! empty( $pro ) ) {
            $merged = $this->merge_pattern_libraries( $pro, $merged );
        }

        if ( ! empty( $batch ) || ! empty( $pro ) ) {
            $this->store_library_cache( $scope, $merged );
            $this->library_runtime[ $scope ] = $merged;

            if ( count( $merged ) > $before ) {
                $has_updates = true;
                $state                 = $this->get_refresh_state();
                $state['complete']     = false;
                $state['phase']        = 'paginated';
                $state['empty_streak'] = 0;
                $per                   = max( 1, (int) $state['per_page'] );
                $state['page']         = max( 2, (int) ceil( count( $merged ) / $per ) );
                $this->set_refresh_state( $state );
            }
        }

        if ( $force || false === get_transient( 'patternswp_category_type' ) ) {
            delete_transient( 'patternswp_category_type' );
            $this->get_patternswp_category_type( false );
        }

        return $this->get_library_status( $has_updates );
    }

    /**
     * One short catalog burst for editor AJAX. Safe on hosts with 30s limits.
     *
     * @return array
     */
    public function warm_library_for_request() {
        $this->maybe_classify_pro_patterns();
        $this->refresh_library( false, 8 );
        $this->get_patternswp_category_type( false );
        return $this->get_library_status();
    }

    /**
     * Paged library payload for the modal.
     *
     * @param int    $page       Page number.
     * @param int    $p_per_page Patterns per page.
     * @param string $search     Search query.
     * @param string $category   Category slug.
     * @return array
     */
    public function query_library_page( $page = 1, $p_per_page = 15, $search = '', $category = '' ) {
        $this->maybe_classify_pro_patterns();

        $items = $this->get_patternswp_pattern( $page, $p_per_page, $search, $category );

        $all      = $this->get_library( false );
        $filtered = $this->filter_patterns_by_category( $all, $category );
        $filtered = $this->filter_patterns_by_search( $filtered, $search );
        $filtered = apply_filters( 'patternswp_patterns', $filtered );
        $filtered = $this->normalize_patterns_list( $filtered );

        return array(
            'patterns'      => $this->prepare_patterns_for_client( is_array( $items ) ? $items : array() ),
            'total'         => count( $filtered ),
            'complete'      => $this->is_library_complete(),
            'categories'    => $this->get_cached_categories(),
            'licenseActive' => $this->is_license_active(),
        );
    }

    /**
     * Categories for the modal without a blocking remote request.
     *
     * @return array
     */
    private function get_cached_categories() {
        $categories = get_transient( 'patternswp_category_type' );
        if ( is_array( $categories ) && ! empty( $categories ) ) {
            return $categories;
        }

        return $this->get_fallback_categories();
    }

    /**
     * Mark Pro patterns as locked for unlicensed sites.
     * Markup stays in the payload so free users can see the design and convert.
     *
     * @param array $patterns Pattern list.
     * @return array
     */
    private function prepare_patterns_for_client( array $patterns ) {
        $licensed = $this->is_license_active();
        $prepared = array();

        foreach ( $patterns as $pattern ) {
            if ( ! is_array( $pattern ) ) {
                continue;
            }

            $is_pro            = $this->is_pro_pattern( $pattern );
            $pattern['type']   = $is_pro ? 'pro' : 'free';
            $pattern['locked'] = ( $is_pro && ! $licensed );
            $prepared[]        = $pattern;
        }

        return array_values( $prepared );
    }

    /**
     * Stamp Pro types onto a cache that was stored as all-free.
     */
    private function maybe_classify_pro_patterns() {
        if ( $this->library_has_pro_patterns() ) {
            return;
        }

        if ( get_transient( 'patternswp_pro_classify_empty' ) ) {
            return;
        }

        if ( get_transient( 'patternswp_pro_classify_lock' ) ) {
            return;
        }

        set_transient( 'patternswp_pro_classify_lock', 1, MINUTE_IN_SECONDS );
        $this->classify_pro_patterns( 6 );
    }

    /**
     * @return bool
     */
    private function library_has_pro_patterns() {
        foreach ( $this->get_library( false ) as $pattern ) {
            if ( $this->is_pro_pattern( $pattern ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Download the Pro catalog stream and mark matching local patterns as Pro.
     *
     * @param int $budget Seconds to spend.
     */
    private function classify_pro_patterns( $budget = 6 ) {
        $deadline = time() + max( 1, (int) $budget );
        $scope    = $this->get_library_scope();
        $patterns = $this->get_library( false );
        $page     = 1;
        $found    = 0;

        $pro_first  = $this->fetch_paginated_page( 1, 20, '', 'pro' );
        $free_first = $this->fetch_paginated_page( 1, 20, '', 'free' );
        if ( empty( $pro_first ) ) {
            set_transient( 'patternswp_pro_classify_empty', 1, 6 * HOUR_IN_SECONDS );
            return;
        }

        if ( ! empty( $free_first ) && $this->pattern_key_set( $pro_first ) === $this->pattern_key_set( $free_first ) ) {
            set_transient( 'patternswp_pro_classify_empty', 1, 6 * HOUR_IN_SECONDS );
            return;
        }

        $found   += count( $pro_first );
        $patterns = $this->merge_pattern_libraries( $pro_first, $patterns );
        $page     = 2;

        while ( time() < $deadline && $page <= 50 ) {
            $batch = $this->fetch_paginated_page( $page, 20, '', 'pro' );
            if ( empty( $batch ) ) {
                break;
            }

            $found   += count( $batch );
            $patterns = $this->merge_pattern_libraries( $batch, $patterns );

            if ( count( $batch ) < 20 ) {
                break;
            }

            $page++;
        }

        if ( $found > 0 ) {
            $this->store_library_cache( $scope, $patterns );
            $this->library_runtime[ $scope ] = $patterns;
            delete_transient( 'patternswp_pro_classify_empty' );
        } else {
            set_transient( 'patternswp_pro_classify_empty', 1, 6 * HOUR_IN_SECONDS );
        }
    }

    /**
     * @param array $patterns Pattern list.
     * @return string
     */
    private function pattern_key_set( array $patterns ) {
        $keys = array();
        foreach ( $patterns as $pattern ) {
            if ( ! is_array( $pattern ) ) {
                continue;
            }
            $key = $this->get_pattern_library_key( $pattern );
            if ( '' !== $key ) {
                $keys[] = $key;
            }
        }
        sort( $keys );
        return implode( '|', $keys );
    }

    /**
     * @param array $pattern Pattern payload.
     * @return bool
     */
    private function is_pro_pattern( array $pattern ) {
        return 'pro' === $this->resolve_pattern_type( $pattern );
    }

    /**
     * @param array $pattern Pattern payload.
     * @return string `pro` or `free`.
     */
    private function resolve_pattern_type( array $pattern ) {
        foreach ( array( 'is_pro', 'isPro', 'pro', 'premium', 'is_premium' ) as $flag ) {
            if ( ! array_key_exists( $flag, $pattern ) ) {
                continue;
            }
            $value = $pattern[ $flag ];
            if ( true === $value || 1 === $value || '1' === $value ) {
                return 'pro';
            }
            if ( is_string( $value ) && in_array( strtolower( $value ), array( 'pro', 'premium', 'paid', 'true' ), true ) ) {
                return 'pro';
            }
        }

        foreach ( array( 'type', 'Type', 'pattern_type', 'patternType', 'plan', 'tier' ) as $key ) {
            if ( ! isset( $pattern[ $key ] ) ) {
                continue;
            }

            $raw = $pattern[ $key ];
            if ( true === $raw || 1 === $raw ) {
                return 'pro';
            }

            $value = strtolower( trim( (string) $raw ) );
            if ( in_array( $value, array( 'pro', 'premium', 'paid', 'paid-pro', '1', 'true' ), true ) ) {
                return 'pro';
            }
            if ( in_array( $value, array( 'free', '0', 'false' ), true ) ) {
                return 'free';
            }
        }

        return 'free';
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
        if ( empty( $cached ) ) {
            $other  = ( 'pro' === $scope ) ? 'free' : 'pro';
            $cached = $this->read_library_cache( $other );
        }
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
     * Fetch one remote page so the modal can render on a cold cache.
     * Full catalog download continues in WP-Cron so hosts do not kill AJAX.
     *
     * @param int    $page       Requested page.
     * @param int    $p_per_page Page size.
     * @param string $search     Search query.
     * @param string $category   Category slug.
     * @return array
     */
    private function seed_library_for_request( $page, $p_per_page, $search, $category ) {
        $scope    = $this->get_library_scope();
        $per_page = min( 20, max( 15, (int) $p_per_page ) );
        $remote_page = ( '' === $search ) ? max( 1, (int) $page ) : 1;

        $batch = $this->fetch_paginated_page( $remote_page, $per_page, $category );
        if ( empty( $batch ) && $per_page !== 15 ) {
            $batch = $this->fetch_paginated_page( $remote_page, 15, $category );
        }

        if ( ! empty( $batch ) ) {
            $existing = $this->read_library_cache( $scope );
            $merged   = $this->merge_pattern_libraries( $batch, is_array( $existing ) ? $existing : array() );
            $this->store_library_cache( $scope, $merged );
            $this->library_runtime[ $scope ] = $merged;
            return $merged;
        }

        return array();
    }

    /**
     * Fetch and store the catalog in short, resumable bursts.
     *
     * Hosts commonly kill 30–60s AJAX with an HTML error page. Saving after
     * each page keeps the library usable if PHP is stopped mid-run.
     *
     * @param bool $force  Restart from page 1.
     * @param int  $budget Seconds to spend, or 0 for the default.
     * @return array
     */
    private function refresh_library( $force = false, $budget = 0 ) {
        $scope = $this->get_library_scope();
        $lock  = 'patternswp_library_lock';

        if ( ! $force && $this->is_library_complete() ) {
            $cached = $this->read_library_cache( $scope );
            if ( ! empty( $cached ) ) {
                return $cached;
            }
        }

        if ( ! $this->acquire_refresh_lock( $lock ) ) {
            if ( wp_doing_ajax() && ! $force ) {
                $this->schedule_library_refresh( 2 );
                return $this->read_library_cache( $scope );
            }

            $waited = $this->wait_for_library_cache( $scope, wp_doing_ajax() ? 2 : 6 );
            if ( ! empty( $waited ) && ! $force ) {
                return $waited;
            }
            if ( ! $force ) {
                $this->schedule_library_refresh( 3 );
                return $this->read_library_cache( $scope );
            }
            set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );
        }

        if ( function_exists( 'ignore_user_abort' ) ) {
            ignore_user_abort( true );
        }

        $deadline = time() + ( $budget > 0 ? (int) $budget : ( wp_doing_ajax() ? 10 : 18 ) );
        $state    = $force ? $this->default_refresh_state() : $this->get_refresh_state();
        $patterns = $this->read_library_cache( $scope );

        if ( count( $patterns ) < 200 ) {
            $state['complete'] = false;
            if ( in_array( $state['phase'], array( 'done', 'bulk' ), true ) ) {
                $state['phase'] = 'paginated';
            }
            $per = (int) $state['per_page'];
            if ( $per > 0 && count( $patterns ) > 0 ) {
                $state['page'] = max( (int) $state['page'], (int) ceil( count( $patterns ) / $per ) + 1 );
            }
        }

        if ( empty( $state['per_page'] ) ) {
            foreach ( array( 20, 15 ) as $size ) {
                $first = $this->fetch_paginated_page( 1, $size, '' );
                if ( ! empty( $first ) ) {
                    $state['per_page'] = $size;
                    $state['page']     = 2;
                    $patterns          = $this->merge_pattern_libraries( $first, $patterns );
                    $this->store_library_cache( $scope, $patterns );
                    break;
                }
            }
            if ( empty( $state['per_page'] ) ) {
                delete_transient( $lock );
                $this->schedule_library_refresh( 30 );
                return $patterns;
            }
        }

        while ( 'paginated' === $state['phase'] && time() < $deadline && $state['page'] <= 50 ) {
            $batch = $this->fetch_paginated_page( (int) $state['page'], (int) $state['per_page'], '' );
            if ( empty( $batch ) ) {
                $state['empty_streak'] = (int) $state['empty_streak'] + 1;
                /*
                 * Empty responses are usually timeouts/token misses, not EOF.
                 * Only leave pagination after several failures AND a sizable catalog.
                 */
                if ( $state['empty_streak'] >= 5 && count( $patterns ) >= 200 ) {
                    $state['phase'] = 'backfill';
                    $this->set_refresh_state( $state );
                    break;
                }
                $this->set_refresh_state( $state );
                break;
            }

            $state['empty_streak'] = 0;
            $patterns              = $this->merge_pattern_libraries( $batch, $patterns );
            $this->store_library_cache( $scope, $patterns );

            if ( count( $batch ) < (int) $state['per_page'] ) {
                $state['phase'] = 'backfill';
                break;
            }

            $state['page']++;
            $this->set_refresh_state( $state );
        }

        if ( 'paginated' === $state['phase'] && (int) $state['page'] > 50 ) {
            $state['phase'] = 'backfill';
        }

        if ( 'backfill' === $state['phase'] && time() < $deadline ) {
            $before    = count( $patterns );
            $patterns  = $this->backfill_missing_categories( $patterns, $deadline );
            if ( count( $patterns ) !== $before ) {
                $this->store_library_cache( $scope, $patterns );
            }
            if ( time() < $deadline ) {
                $state['phase'] = 'bulk';
            }
        }

        if ( 'bulk' === $state['phase'] && time() < $deadline ) {
            $bulk     = $this->fetch_bulk_patterns();
            $patterns = $this->merge_pattern_libraries( $patterns, $bulk );
            $this->store_library_cache( $scope, $patterns );
            $state['phase'] = 'classify';
        } elseif ( 'bulk' === $state['phase'] && count( $patterns ) >= 200 ) {
            $state['phase'] = 'classify';
        }

        if ( 'classify' === $state['phase'] && time() < $deadline ) {
            $remaining = max( 1, $deadline - time() );
            $this->library_runtime[ $scope ] = $patterns;
            $this->classify_pro_patterns( $remaining );
            $patterns = $this->get_library( false );
            $state['phase']    = 'done';
            $state['complete'] = count( $patterns ) >= 200;
        }

        $this->library_runtime[ $scope ] = $patterns;
        $this->set_refresh_state( $state );
        delete_transient( $lock );

        if ( empty( $state['complete'] ) ) {
            $this->schedule_library_refresh( 2 );
        }

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
     * @param int   $deadline Unix timestamp to stop, or 0 for no limit.
     * @return array
     */
    private function backfill_missing_categories( array $patterns, $deadline = 0 ) {
        $categories = $this->get_patternswp_category_type( false );
        if ( empty( $categories ) || ! is_array( $categories ) ) {
            return $patterns;
        }

        foreach ( $categories as $category ) {
            if ( $deadline && time() >= $deadline ) {
                return $patterns;
            }

            $name = ( is_array( $category ) && isset( $category['name'] ) ) ? (string) $category['name'] : '';
            if ( '' === $name ) {
                continue;
            }

            $existing = $this->filter_patterns_by_category( $patterns, $name );
            if ( ! empty( $existing ) ) {
                continue;
            }

            $extra    = $this->fetch_all_paginated_patterns( $name, $deadline );
            $patterns = $this->merge_pattern_libraries( $patterns, $extra );
            usleep( 150000 );
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
     * @param int    $deadline Unix timestamp to stop, or 0 for no limit.
     * @return array
     */
    private function fetch_all_paginated_patterns( $category = '', $deadline = 0 ) {
        $per_page = 0;
        $first    = array();

        foreach ( array( 20, 15 ) as $size ) {
            if ( $deadline && time() >= $deadline ) {
                return array();
            }
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
            if ( $deadline && time() >= $deadline ) {
                break;
            }

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
    private function fetch_paginated_page( $page, $per_page, $category = '', $type = '' ) {
        $license = $this->get_license_data();

        for ( $try = 0; $try < 2; $try++ ) {
            $token = $this->get_api_token();
            if ( empty( $token ) ) {
                usleep( 200000 );
                continue;
            }

            $query = array(
                'page'            => $page,
                'patternsPerPage' => $per_page,
                'search'          => '',
                'category'        => $category,
                'site_url'        => get_site_url(),
                'license_key'     => ! empty( $license['licensestatus'] ) ? $license['license_key'] : '',
                'licensestatus'   => ! empty( $license['licensestatus'] ) ? '1' : '',
                'api_token'       => $token,
                'plugin_version'  => PWP_P_VERSION,
            );
            if ( '' !== $type ) {
                $query['type'] = $type;
            }

            $url = add_query_arg(
                $query,
                $this->api_url . 'wp-json/patternswps/v1/patterns'
            );

            $batch = $this->normalize_patterns_list( $this->remote_get_json( $url, 20 ) );
            if ( ! empty( $batch ) ) {
                if ( 'pro' === $type ) {
                    foreach ( $batch as &$pattern ) {
                        $pattern['type'] = 'pro';
                    }
                    unset( $pattern );
                }
                return $batch;
            }

            usleep( 250000 );
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

            $batch = $this->normalize_patterns_list( $this->remote_get_json( $url, 25 ) );
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
        $args = array(
            'timeout'    => $timeout,
            'method'     => 'GET',
            'sslverify'  => true,
            'user-agent' => 'PatternsWP/' . PWP_P_VERSION . '; ' . home_url( '/' ),
        );

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            $args['sslverify'] = false;
            $response          = wp_remote_get( $url, $args );
        }

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

        $add = function ( $pattern ) use ( &$merged ) {
            if ( ! is_array( $pattern ) ) {
                return;
            }
            $key = $this->get_pattern_library_key( $pattern );
            if ( '' === $key ) {
                return;
            }

            $incoming_type = $this->resolve_pattern_type( $pattern );
            if ( isset( $merged[ $key ] ) ) {
                if ( 'pro' === $incoming_type ) {
                    $merged[ $key ]['type'] = 'pro';
                }
                if ( empty( $merged[ $key ]['categories'] ) && ! empty( $pattern['categories'] ) ) {
                    $merged[ $key ]['categories'] = $pattern['categories'];
                }
                return;
            }

            $pattern['type'] = $incoming_type;
            $merged[ $key ]  = $pattern;
        };

        foreach ( $typed_patterns as $pattern ) {
            $add( $pattern );
        }
        foreach ( $bulk_patterns as $pattern ) {
            $add( $pattern );
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
        $file = $this->read_library_file( $scope );
        if ( ! empty( $file ) ) {
            return $file;
        }

        $prefix      = $this->get_library_prefix( $scope );
        $chunk_count = (int) get_transient( $prefix . 'count' );
        $patterns    = array();

        if ( $chunk_count > 0 ) {
            for ( $i = 0; $i < $chunk_count && $i < 200; $i++ ) {
                $chunk = get_transient( $prefix . $i );
                if ( false === $chunk ) {
                    continue;
                }
                $chunk = $this->unpack_cache_payload( $chunk );
                if ( is_array( $chunk ) ) {
                    $patterns = array_merge( $patterns, $chunk );
                }
            }

            $patterns = $this->normalize_patterns_list( $patterns );
            if ( ! empty( $patterns ) ) {
                return $patterns;
            }
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
        $misses          = 0;

        for ( $i = 0; $i < 200; $i++ ) {
            $transient_data = get_transient( 'patterns_cache_' . $i );
            if ( false === $transient_data ) {
                $misses++;
                if ( $misses >= 3 ) {
                    break;
                }
                continue;
            }

            $misses         = 0;
            $transient_data = $this->unpack_cache_payload( $transient_data );
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
        $patterns   = $this->normalize_patterns_list( $patterns );
        $prefix     = $this->get_library_prefix( $scope );
        $chunk_size = 4;
        $chunks     = array_chunk( $patterns, $chunk_size );

        $this->write_library_file( $scope, $patterns );
        $this->delete_library_cache( $scope );

        foreach ( $chunks as $index => $chunk ) {
            set_transient( $prefix . $index, $this->pack_cache_payload( $chunk ), DAY_IN_SECONDS );
        }

        set_transient( $prefix . 'count', count( $chunks ), DAY_IN_SECONDS );

        $this->delete_legacy_pattern_cache();
        foreach ( $chunks as $index => $chunk ) {
            set_transient( 'patterns_cache_' . $index, $this->pack_cache_payload( $chunk ), DAY_IN_SECONDS );
        }
    }

    /**
     * @param string $scope License scope.
     */
    private function delete_library_cache( $scope ) {
        $prefix = $this->get_library_prefix( $scope );
        $count  = (int) get_transient( $prefix . 'count' );

        for ( $i = 0; $i < max( $count, 200 ); $i++ ) {
            delete_transient( $prefix . $i );
        }

        delete_transient( $prefix . 'count' );
    }

    /**
     * Remove legacy bulk chunks.
     */
    private function delete_legacy_pattern_cache() {
        for ( $i = 0; $i < 200; $i++ ) {
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

        set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );
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

            $type = $this->resolve_pattern_type( $pattern );

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

        $licensed              = $this->is_license_active();
        $registered_categories = array();
        $registry              = \WP_Block_Patterns_Registry::get_instance();

        foreach ( $patterns as $pattern ) {
            if ( ! is_array( $pattern ) ) {
                continue;
            }

            if ( ! $licensed && $this->is_pro_pattern( $pattern ) ) {
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
     * @param mixed $data Cache payload.
     * @return mixed
     */
    private function pack_cache_payload( $data ) {
        if ( ! function_exists( 'gzcompress' ) ) {
            return $data;
        }

        $json = wp_json_encode( $data );
        if ( ! is_string( $json ) || '' === $json ) {
            return $data;
        }

        return array(
            '_pwp' => 1,
            'z'    => base64_encode( gzcompress( $json, 6 ) ),
        );
    }

    /**
     * @param mixed $payload Stored cache payload.
     * @return array
     */
    private function unpack_cache_payload( $payload ) {
        if ( is_array( $payload ) && ! empty( $payload['_pwp'] ) && ! empty( $payload['z'] ) && is_string( $payload['z'] ) ) {
            if ( ! function_exists( 'gzuncompress' ) ) {
                return array();
            }
            $raw = base64_decode( $payload['z'], true );
            if ( false === $raw ) {
                return array();
            }
            $json = @gzuncompress( $raw );
            $data = is_string( $json ) ? json_decode( $json, true ) : null;
            return is_array( $data ) ? $data : array();
        }

        return is_array( $payload ) ? $payload : array();
    }

    /**
     * @param string $scope License scope.
     * @return string
     */
    private function get_library_file_path( $scope ) {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return '';
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'patternswp';
        return $dir . '/library-' . sanitize_key( $scope ) . '.json.gz';
    }

    /**
     * @param string $scope    License scope.
     * @param array  $patterns Pattern list.
     */
    private function write_library_file( $scope, array $patterns ) {
        $path = $this->get_library_file_path( $scope );
        if ( '' === $path ) {
            return;
        }

        $dir = dirname( $path );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        if ( is_dir( $dir ) && ! file_exists( $dir . '/index.php' ) ) {
            file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
        }

        $json = wp_json_encode( array_values( $patterns ) );
        if ( ! is_string( $json ) ) {
            return;
        }

        if ( function_exists( 'gzencode' ) ) {
            file_put_contents( $path, gzencode( $json, 6 ) );
            return;
        }

        file_put_contents( $path, $json );
    }

    /**
     * @param string $scope License scope.
     * @return array
     */
    private function read_library_file( $scope ) {
        $path = $this->get_library_file_path( $scope );
        if ( '' === $path || ! is_readable( $path ) ) {
            return array();
        }

        $raw = file_get_contents( $path );
        if ( ! is_string( $raw ) || '' === $raw ) {
            return array();
        }

        if ( function_exists( 'gzdecode' ) ) {
            $decoded = @gzdecode( $raw );
            if ( is_string( $decoded ) && '' !== $decoded ) {
                $raw = $decoded;
            }
        }

        $data = json_decode( $raw, true );
        return $this->normalize_patterns_list( $data );
    }

    /**
     * @param string $scope License scope.
     */
    private function delete_library_file( $scope ) {
        $path = $this->get_library_file_path( $scope );
        if ( '' !== $path && file_exists( $path ) ) {
            wp_delete_file( $path );
        }
    }

    /**
     * @return array
     */
    private function default_refresh_state() {
        return array(
            'phase'        => 'paginated',
            'page'         => 1,
            'per_page'     => 0,
            'empty_streak' => 0,
            'complete'     => false,
        );
    }

    /**
     * @return array
     */
    private function get_refresh_state() {
        $state = get_option( 'patternswp_lib_state', array() );
        if ( ! is_array( $state ) ) {
            $state = array();
        }

        return array_merge( $this->default_refresh_state(), $state );
    }

    /**
     * @param array $state Refresh progress.
     */
    private function set_refresh_state( array $state ) {
        update_option( 'patternswp_lib_state', $state, false );
    }

    /**
     * @return bool
     */
    private function is_library_complete() {
        $state = $this->get_refresh_state();
        if ( empty( $state['complete'] ) ) {
            return false;
        }

        $cached = $this->read_library_cache( $this->get_library_scope() );
        return count( $cached ) >= 200;
    }

    /**
     * Sidebar categories when the remote taxonomy request is blocked or slow.
     *
     * @return array
     */
    private function get_fallback_categories() {
        $names = array(
            'patternswp-blog',
            'patternswp-contact',
            'patternswp-cta',
            'patternswp-customer',
            'patternswp-faq',
            'patternswp-features',
            'patternswp-footer',
            'patternswp-gallery',
            'patternswp-header',
            'patternswp-hero',
            'patternswp-link-in-bio',
            'patternswp-page-templates',
            'patternswp-pricing',
            'patternswp-statistics',
            'patternswp-team',
            'patternswp-testimonials',
            'patternswp-utility',
        );

        $categories = array();
        foreach ( $names as $name ) {
            $categories[] = array( 'name' => $name );
        }

        return $categories;
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
