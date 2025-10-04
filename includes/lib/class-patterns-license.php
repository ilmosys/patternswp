<?php

class PatternsWP_LicenseSection {
	private $patternswp_license_key_option;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'patternswp_add_license_page' ) );
		add_action( 'admin_init', array( $this, 'patternswp_save_license_key' ) );
		add_action( 'admin_notices', array( $this, 'patternswp_display_admin_notice' ) );
	}

	/**
	 * Add License Page
	 */
	public function patternswp_add_license_page() {
		add_submenu_page( 'patternswp-plugin-menu', 'License', 'License', 'manage_options', 'patternswp-license_section', array( $this, 'patternswp_plugin_license_section' ) );
	}

	/**
	 * License Section
	 */
	public function patternswp_plugin_license_section() {
		$license_key = $this->patternswp_license_key_option = get_option( 'patternswp_license_key' );
		$license_key = isset( $license_key['patternswp_pro_license_key'] ) ? $license_key['patternswp_pro_license_key'] : '';
		if (!empty( $license_key ) && strlen( $license_key ) > 8) {
			$masked_key = substr( $license_key, 0, 8 ) . str_repeat( 'X', strlen( $license_key ) - 8 );
		} else {
			$masked_key = $license_key;
		}
		$get_license_status = get_option( 'patternswp_plugin_license_data' );
		$license_status     = isset( $get_license_status['activated'] ) ? $get_license_status['activated'] : false;
		?>
		<div class="wrap">
			<h1><?php esc_attr_e( 'License', 'patternswp' ); ?></h1>
			<?php settings_errors(); ?>
			<h2 class="nav-tab-wrapper">
                <?php 
					$patternswp_admin = new PatternsWP_Admin();
					$patternswp_admin->patterswp_tab( 'License' ); 
				?>
            </h2>
			<div class="patterns-wp-tabs-content">
				<div id="tab-1" class="patterns-wp-tab-content patterns-wp-tab-active">
					<div class="wrap">
						
						<div class="container" style="padding: 40px;background-color: white;border-radius: 5px;overflow: hidden;width: 70%;">
						<div class="about__section has-1-columns">
                                <div class="column" style="">
                                    <h1 class="feature-title"><?php esc_html_e('License', 'patternswp'); ?></h1>
                                    <p><?php esc_html_e('Already purchased? Simply enter your license key below to connect with PatternsWP Pro!', 'patternswp'); ?></p>
                                </div>
                                <div style="height: 1px; background-color: #ccc; width: 100%; margin: 20px 0;"></div>
                            </div>
							<div class="">
								<form id="patternswp_license_form" method="post" >
									<input class="regular-text" type="text" placeholder="Enter your license key here..." name="patternswp_license_key[patternswp_pro_license_key]" id="patternswp_pro_license_key" value="<?php echo esc_attr( $masked_key )  ?>">
									<?php wp_nonce_field('patternswp_save_license_action', 'patternswp_license_nonce'); ?>
									<input name="patternswp_save_license" type="submit" id="custom_submit_btn" class="button button-primary" value="Save & Activate" style="margin-left: 5px;">
									<?php
										if ( $license_status == true ) {
											echo '<div style="margin-top: 10px;"><span style="color: green;font-weight: bold;">Your license is active and verified. Enjoy all premium features!</span></div>';
										}										
									?>
									<div>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save License Key
	 */
	public function patternswp_save_license_key() {
		$this->patternswp_license_key_option = get_option( 'patternswp_license_key' );
		
		if ( isset($_POST['patternswp_save_license'] ) ) {
			if (!isset( $_POST['patternswp_license_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['patternswp_license_nonce'] ) ), 'patternswp_save_license_action') ) {
				wp_die(esc_attr('Security check failed. Please try again.', 'patternswp'));
			}
			if ( !current_user_can('manage_options' ) ) {
				wp_die(esc_attr('You do not have sufficient permissions to perform this action.', 'patternswp'));
			}

			if ( isset( $_POST['patternswp_license_key']['patternswp_pro_license_key'] ) && !empty( $_POST['patternswp_license_key']['patternswp_pro_license_key'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$license_k   = isset( $_POST['patternswp_license_key'] ) ? $_POST['patternswp_license_key'] : '';
				$empty_option = array();
				if( !empty( $license_k ) ){
					$empty_option['patternswp_pro_license_key'] = $this->patternswp_license_key_option['patternswp_pro_license_key'];
					$this->handle_license_activation( $empty_option, $license_k  );
					
				}
				$patterns_tkey    = 'patterns_data';
				delete_transient( $patterns_tkey );
			}else{
				wp_redirect( add_query_arg( array( 
					'page'    => 'patternswp-license_section', 
					'pt_msg'  => urlencode( 'License activation failed: License Key cannot be empty. Please enter a valid license key.' ), 
					'status'  => 'error' 
				), admin_url( 'admin.php' ) ) );
				exit;
			}

			wp_redirect( add_query_arg( 'updated', 'true', wp_get_referer() ) );
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
				wp_redirect( add_query_arg( array( 
					'page'    => 'patternswp-license_section', 
					'pt_msg'  => urlencode( 'License Key is already activated.' ), 
					'status'  => 'info' 
				), admin_url( 'admin.php' ) ) );
				exit;
			} else {
				$this->activate_license( $new_value['patternswp_pro_license_key'] );
			}
		} else {
			wp_redirect( add_query_arg( array( 
				'page'    => 'patternswp-license_section', 
				'pt_msg'  => urlencode( 'License activation failed: License Key cannot be empty. Please enter a valid license key.' ), 
				'status'  => 'error' 
			), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Activate License
	 *
	 * @param string $license_key License key.
	 */
	public function activate_license( $license_key ) {
		global $wpdb;
	
		$activation_url = PATTERSWP_PLUGIN_API_URL . '/activate';
		$data           = array( 'license_key'   => $license_key, 'instance_name' => home_url() );
		$response       = wp_remote_post( $activation_url, array( 'body' => $data, 'headers' => array( 'Accept' => 'application/json' ), 'sslverify' => false, 'timeout'   => 10 ) );
		$response       = json_decode( wp_remote_retrieve_body( $response ) );
	
		if ( isset( $response->error ) && !empty( $response->error ) ) {
			if( $response->error == 'license_key not found.' ){
				$response->error = 'License activation failed: Please enter a valid license key.';
			}

			wp_redirect( add_query_arg( array( 
				'page'    => 'patternswp-license_section', 
				'pt_msg' => urlencode( $response->error ), 
				'status'  => 'error' 
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		if( isset( $response->activated ) && $response->activated == true ) {
			// Store license data in options
			$license_data = (array) $response;
			update_option( 'patternswp_plugin_license_data', $license_data );
			$this->clear_transients();

			$license_key_data = array( 'patternswp_pro_license_key' => $license_key );
			$license_key = update_option( 'patternswp_license_key', $license_key_data );

			do_action( 'patternswp_load_patterns_by_ra' );

			wp_redirect( add_query_arg( array( 
				'page'    => 'patternswp-license_section', 
				'pt_msg'  => urlencode( 'License activated successfully.' ), 
				'status'  => 'success' 
			), admin_url( 'admin.php' ) ) );
			exit;

		}
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
	 * Display admin notice
	 */
	public function patternswp_display_admin_notice() {
		if ( isset( $_GET['pt_msg'] ) && ! empty( sanitize_text_field( wp_unslash( $_GET['pt_msg'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = isset($_GET['pt_msg']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['pt_msg'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status  = isset( $_GET['status'] ) && sanitize_text_field( wp_unslash( $_GET['status'] ) ) === 'success' ? 'updated' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="%s notice is-dismissible"><p>%s</p></div>', esc_attr( $status ), esc_attr( $message ) );
		}
	}	
}

new PatternsWP_LicenseSection();