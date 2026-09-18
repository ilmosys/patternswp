<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PatternsWP_LicenseSection {
	private $patternswp_license_key_option;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'patternswp_add_license_page' ) );
		add_action( 'admin_init', array( $this, 'patternswp_save_license_key' ) );
		add_action( 'admin_notices', array( $this, 'patternswp_display_admin_notice' ) );
		add_action( 'wp_ajax_patternswp_activate_license', array( $this, 'ajax_activate_license' ) );
	}

	/**
	 * Add License Page
	 */
	public function patternswp_add_license_page() {
		add_submenu_page( 'patternswp-plugin-menu', 'License', 'License', 'manage_options', 'patternswp-license_section', array( $this, 'patternswp_plugin_license_section' ) );
	}

	/**
	 * License Section — React admin mount.
	 */
	public function patternswp_plugin_license_section() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap patternswp-admin-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'License', 'patternswp' ); ?></h1>
			<div id="patternswp-admin-root">
				<div class="patternswp-admin__loading">
					<span class="spinner is-active" style="float:none;margin:0;"></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Mask a license key for display.
	 *
	 * @param string $license_key Raw license key.
	 * @return string
	 */
	private function mask_license_key( $license_key ) {
		if ( ! empty( $license_key ) && strlen( $license_key ) > 8 ) {
			return substr( $license_key, 0, 8 ) . str_repeat( 'X', strlen( $license_key ) - 8 );
		}
		return $license_key;
	}

	/**
	 * AJAX: activate license key.
	 */
	public function ajax_activate_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the license.', 'patternswp' ) ), 403 );
		}

		check_ajax_referer( 'patternswp_admin_nonce', 'nonce' );

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		// Ignore masked keys (contain X padding from previous activation display).
		if ( false !== strpos( $license_key, 'X' ) && preg_match( '/X{4,}/', $license_key ) ) {
			$stored = get_option( 'patternswp_license_key', array() );
			$stored_key = isset( $stored['patternswp_pro_license_key'] ) ? $stored['patternswp_pro_license_key'] : '';
			if ( ! empty( $stored_key ) && $this->mask_license_key( $stored_key ) === $license_key ) {
				$license_data = get_option( 'patternswp_plugin_license_data', array() );
				if ( ! empty( $license_data['activated'] ) ) {
					wp_send_json_success(
						array(
							'message'   => __( 'License Key is already activated.', 'patternswp' ),
							'status'    => 'info',
							'activated' => true,
							'maskedKey' => $this->mask_license_key( $stored_key ),
						)
					);
				}
				$license_key = $stored_key;
			}
		}

		if ( empty( $license_key ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'License activation failed: License Key cannot be empty. Please enter a valid license key.', 'patternswp' ),
				)
			);
		}

		$result = $this->activate_license_ajax( $license_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Activate license and return structured result (no redirects).
	 *
	 * @param string $license_key License key.
	 * @return array|WP_Error
	 */
	public function activate_license_ajax( $license_key ) {
		$stored = get_option( 'patternswp_license_key', array() );
		$old_key = isset( $stored['patternswp_pro_license_key'] ) ? $stored['patternswp_pro_license_key'] : '';
		$license_data = get_option( 'patternswp_plugin_license_data', array() );

		if ( $old_key === $license_key && ! empty( $license_data['activated'] ) ) {
			return array(
				'message'   => __( 'License Key is already activated.', 'patternswp' ),
				'status'    => 'info',
				'activated' => true,
				'maskedKey' => $this->mask_license_key( $license_key ),
			);
		}

		$activation_url = PATTERSWP_PLUGIN_API_URL . '/activate';
		$data           = array(
			'license_key'   => $license_key,
			'instance_name' => home_url(),
		);
		$response       = wp_remote_post(
			$activation_url,
			array(
				'body'    => $data,
				'headers' => array( 'Accept' => 'application/json' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'patternswp_license_request_failed',
				$response->get_error_message()
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( isset( $body->error ) && ! empty( $body->error ) ) {
			$error = $body->error;
			if ( 'license_key not found.' === $error ) {
				$error = __( 'License activation failed: Please enter a valid license key.', 'patternswp' );
			}
			return new WP_Error( 'patternswp_license_invalid', $error );
		}

		if ( isset( $body->activated ) && true === $body->activated ) {
			update_option( 'patternswp_plugin_license_data', (array) $body );
			$this->clear_transients();
			update_option(
				'patternswp_license_key',
				array( 'patternswp_pro_license_key' => $license_key )
			);
			do_action( 'patternswp_load_patterns_by_ra' );

			return array(
				'message'   => __( 'License activated successfully.', 'patternswp' ),
				'status'    => 'success',
				'activated' => true,
				'maskedKey' => $this->mask_license_key( $license_key ),
			);
		}

		return new WP_Error(
			'patternswp_license_failed',
			__( 'License activation failed. Please try again.', 'patternswp' )
		);
	}

	/**
	 * Save License Key (legacy form POST — kept for compatibility).
	 */
	public function patternswp_save_license_key() {
		$this->patternswp_license_key_option = get_option( 'patternswp_license_key' );
		
		if ( isset($_POST['patternswp_save_license'] ) ) {
			if (!isset( $_POST['patternswp_license_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['patternswp_license_nonce'] ) ), 'patternswp_save_license_action') ) {
				wp_die(esc_html__('Security check failed. Please try again.', 'patternswp'));
			}
			if ( !current_user_can('manage_options' ) ) {
				wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'patternswp'));
			}

			if ( isset( $_POST['patternswp_license_key']['patternswp_pro_license_key'] ) && !empty( $_POST['patternswp_license_key']['patternswp_pro_license_key'] ) ) {
				$license_k = array(
					'patternswp_pro_license_key' => sanitize_text_field( wp_unslash( $_POST['patternswp_license_key']['patternswp_pro_license_key'] ) ),
				);
				$empty_option = array();
				if( !empty( $license_k ) ){
					$empty_option['patternswp_pro_license_key'] = $this->patternswp_license_key_option['patternswp_pro_license_key'];
					$this->handle_license_activation( $empty_option, $license_k  );
					
				}
				$patterns_tkey    = 'patterns_data';
				delete_transient( $patterns_tkey );
			}else{
				$redirect_url = add_query_arg( array( 
					'page'    => 'patternswp-license_section', 
					'pt_msg'  => urlencode( 'License activation failed: License Key cannot be empty. Please enter a valid license key.' ), 
					'status'  => 'error' 
				), admin_url( 'admin.php' ) );
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$redirect_url = add_query_arg( 'updated', 'true', wp_get_referer() );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Handle License Activation
	 *
	 * @param array $old_value Old license key.
	 * @param array $new_value New license key.
	 */
	public function handle_license_activation( $old_value, $new_value ) {
		if ( isset( $new_value['patternswp_pro_license_key'] ) && ! empty( $new_value['patternswp_pro_license_key'] ) ) {
			if ( $old_value['patternswp_pro_license_key'] === $new_value['patternswp_pro_license_key'] ) {
				$redirect_url = add_query_arg( array( 
					'page'    => 'patternswp-license_section', 
					'pt_msg'  => urlencode( 'License Key is already activated.' ), 
					'status'  => 'info' 
				), admin_url( 'admin.php' ) );
				wp_safe_redirect( $redirect_url );
				exit;
			} else {
				$this->activate_license( $new_value['patternswp_pro_license_key'] );
			}
		} else {
			$redirect_url = add_query_arg( array( 
				'page'    => 'patternswp-license_section', 
				'pt_msg'  => urlencode( 'License activation failed: License Key cannot be empty. Please enter a valid license key.' ), 
				'status'  => 'error' 
			), admin_url( 'admin.php' ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Activate License
	 *
	 * @param string $license_key License key.
	 */
	public function activate_license( $license_key ) {
		$result = $this->activate_license_ajax( $license_key );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( array(
				'page'   => 'patternswp-license_section',
				'pt_msg' => urlencode( $result->get_error_message() ),
				'status' => 'error',
			), admin_url( 'admin.php' ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$redirect_url = add_query_arg( array(
			'page'   => 'patternswp-license_section',
			'pt_msg' => urlencode( $result['message'] ),
			'status' => isset( $result['status'] ) ? $result['status'] : 'success',
		), admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}
	
	/**
	 * Clear transients
	 */
	private function clear_transients() {
		global $wpdb;
	
		// Delete category type transient
		delete_transient( 'patternswp_category_type' );
	
		// Delete all API-related transients
		$pattern = '_transient_patternswp_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $wpdb->esc_like( $pattern ) . '%' ) );

		// Delete all patterns cache
		$patterns = 'patterns_cache_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s", 
				$wpdb->esc_like( $patterns ) . '%' 
			) 
		);
	
		foreach ( $results as $transient ) {
			delete_option( $transient );
			delete_option(str_replace('_transient_', '_transient_timeout_', $transient));
		}
	}

	/**
	 * Display admin notice (legacy URL flash messages — React also reads these via localize).
	 */
	public function patternswp_display_admin_notice() {
		// Notices are shown inside the React admin app when on PatternsWP pages.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && false !== strpos( $screen->id, 'patternswp' ) ) {
			return;
		}

		if ( isset( $_GET['pt_msg'] ) && ! empty( sanitize_text_field( wp_unslash( $_GET['pt_msg'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = isset($_GET['pt_msg']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['pt_msg'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status  = isset( $_GET['status'] ) && sanitize_text_field( wp_unslash( $_GET['status'] ) ) === 'success' ? 'updated' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="%s notice is-dismissible"><p>%s</p></div>', esc_attr( $status ), esc_attr( $message ) );
		}
	}	
}

new PatternsWP_LicenseSection();
