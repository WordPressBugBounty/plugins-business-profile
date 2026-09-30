<?php
/** Local publication guidance. Configuration checks never claim an external fetch succeeded. */
defined( 'ABSPATH' ) || exit;

class bpfwpPublicationChecks {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'admin_post_bpfwp_verify_publication', array( __CLASS__, 'verify_request' ) );
	}
	public static function styles( $hook ) {
		if ( in_array( $hook, array( 'admin_page_bpfwp-publication-checks', 'admin_page_bpfwp-source-checks' ), true ) ) {
			wp_enqueue_style( 'bpfwp-diagnostics', BPFWP_PLUGIN_URL . '/assets/css/diagnostics.css', array(), BPFWP_VERSION );
		}
	}
	public static function menu() {
		$hook = add_submenu_page( null, __( 'Publication Checks', 'business-profile' ), __( 'Publication Checks', 'business-profile' ), 'manage_options', 'bpfwp-publication-checks', array( __CLASS__, 'render' ) );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'set_page_title' ) );
		}
	}
	public static function set_page_title() {
		$GLOBALS['title'] = __( 'Publication Checks', 'business-profile' );
	}
	public static function checks( $location = 0 ) {
		$facts     = bpfwpBusinessData::facts( $location );
		$hours     = bpfwpBusinessHours::normalize( $facts['opening-hours'], $facts['exceptions'] );
		$page      = get_post( (int) $facts['contact-page'] );
		$visible   = $page && 'publish' === $page->post_status && ! $page->post_password;
		$placement = $page && ( has_shortcode( $page->post_content, 'contact-card' ) || has_block( 'business-profile/contact-card', $page ) );
		return array(
			__( 'Business name saved', 'business-profile' ) => '' !== trim( (string) $facts['name'] ),
			__( 'Hours can be interpreted', 'business-profile' ) => ! $hours['errors'],
			__( 'Contact page is public', 'business-profile' ) => (bool) $visible,
			__( 'Contact card is placed on the selected page', 'business-profile' ) => (bool) $placement,
		);
	}
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap bpfwp-diagnostics"><h1>' . esc_html__( 'Publication Checks', 'business-profile' ) . '</h1><p>' . esc_html__( 'These checks inspect saved configuration. Use the public-page check below to inspect returned markup. Also open the page while signed out and review its visible information.', 'business-profile' ) . '</p><section class="bpfwp-check-panel"><h2>' . esc_html__( 'Saved configuration', 'business-profile' ) . '</h2><dl class="bpfwp-check-list">';
		foreach ( self::checks() as $label => $pass ) {
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd><strong>' . esc_html( $pass ? __( 'Configured', 'business-profile' ) : __( 'Needs attention', 'business-profile' ) ) . '</strong></dd></div>';
		}
		echo '</dl><p><a href="' . esc_url( admin_url( 'index.php?page=bpfwp-getting-started' ) ) . '">' . esc_html__( 'Resume setup', 'business-profile' ) . '</a></p>';
		$facts = bpfwpBusinessData::facts();
		if ( $facts['contact-page'] && current_user_can( 'edit_post', $facts['contact-page'] ) ) {
			echo '<p><a href="' . esc_url( get_edit_post_link( $facts['contact-page'] ) ) . '">' . esc_html__( 'Review and publish the contact page', 'business-profile' ) . '</a></p>';
		}
		echo '</section><section class="bpfwp-check-panel"><h2>' . esc_html__( 'Public-page observation', 'business-profile' ) . '</h2>';
		$result = get_transient( 'bpfwp-publication-observation' );
		if ( is_array( $result ) ) {
			$stale = ( $result['fingerprint'] ?? '' ) !== self::fingerprint();
			echo '<p><strong>' . esc_html( $stale ? __( 'Previous check is stale.', 'business-profile' ) : __( 'Latest public-page check:', 'business-profile' ) ) . '</strong> ' . esc_html( $result['message'] ) . ' ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $result['time'] ) ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Not checked. Saved configuration alone does not verify the public page.', 'business-profile' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bpfwp_verify_publication">';
		wp_nonce_field( 'bpfwp-verify-publication' );
		submit_button( __( 'Check the public contact page', 'business-profile' ), 'secondary', 'submit', false );
		echo '</form><p>' . esc_html__( 'This requests only the selected public page on this site. A blocked request or missing markup is reported without changing your business information. A successful check is not a search-engine eligibility guarantee.', 'business-profile' ) . '</p>';
		echo '</section>';
		$draft = get_transient( 'bpfwp_schedule_draft_' . get_current_user_id() );
		if ( is_array( $draft ) ) {
			echo '<section class="bpfwp-check-panel"><h2>' . esc_html__( 'Schedule recovery', 'business-profile' ) . '</h2><p>' . esc_html__( 'This rejected submission is retained for 24 hours so you can recover its values. Correct the dates and times in the hours editor before saving again.', 'business-profile' ) . '</p><ul>';
			foreach ( $draft as $rows ) {
				foreach ( (array) $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$parts = array( $row['date'] ?? '', $row['date_range']['start'] ?? '', $row['date_range']['end'] ?? '', $row['time']['start'] ?? '', $row['time']['end'] ?? '' );
					foreach ( (array) ( $row['weekdays'] ?? array() ) as $day => $enabled ) {
						if ( $enabled ) {
							$parts[] = $day;
						}
					}
					$parts = array_filter( $parts, 'is_scalar' );
					echo '<li>' . esc_html( implode( ' · ', array_filter( $parts, 'strlen' ) ) ) . '</li>';
				}
			}
			echo '</ul></section>';
		}
		echo '<section class="bpfwp-check-panel"><h2>' . esc_html__( 'Locations and restaurant bookings', 'business-profile' ) . '</h2><p><a href="' . esc_url( admin_url( 'post-new.php?post_type=' . bpfwpLocationPublication::post_type() ) ) . '">' . esc_html__( 'Add a location and choose its publication purpose', 'business-profile' ) . '</a></p>';
		if ( class_exists( 'rtbSettings' ) ) {
			echo '<p>' . esc_html__( 'Reservations is active. Manage booking availability in Reservations settings; public opening hours are managed here.', 'business-profile' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=rtb-settings' ) ) . '">' . esc_html__( 'Open Reservations settings', 'business-profile' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'Reservations is not active. It is optional for public business information and contact cards. Activate Five Star Restaurant Reservations separately if you need booking management.', 'business-profile' ) . '</p>';
		}
		echo '<p>' . esc_html( defined( 'FSPPH_VERSION' ) ? __( 'Premium Helper is active. Available paid features still depend on your existing access.', 'business-profile' ) : __( 'Premium Helper is not active. Public business information, locations and contact cards remain available in Free.', 'business-profile' ) ) . '</p>';
		echo '<p>' . esc_html__( 'Public opening hours are independent of Reservations booking availability. Internal resources must remain excluded from public listings; Reservations receiving-side support must be verified separately.', 'business-profile' ) . '</p></section></div>';
	}

	private static function fingerprint() {
		$facts = bpfwpBusinessData::facts();
		$page  = get_post( (int) $facts['contact-page'] );
		return hash( 'sha256', wp_json_encode( array( get_option( 'bpfwp-settings-revision' ), get_option( 'active_plugins' ), get_option( 'stylesheet' ), wp_get_theme()->get( 'Version' ), BPFWP_VERSION, defined( 'FSPPH_VERSION' ) ? FSPPH_VERSION : '', defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '', defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '', $page ? array( $page->ID, $page->post_status, $page->post_modified_gmt, $page->post_content ) : null ) ) );
	}
	public static function verify_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'business-profile' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bpfwp-verify-publication' );
		$result                = self::observe();
		$result['fingerprint'] = self::fingerprint();
		$result['time']        = time();
		set_transient( 'bpfwp-publication-observation', $result, DAY_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=bpfwp-publication-checks' ) );
		exit;
	}
	public static function observe() {
		$facts = bpfwpBusinessData::facts();
		$page  = get_post( (int) $facts['contact-page'] );
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || $page->post_password ) {
			return array(
				'status'  => 'not_public',
				'message' => __( 'Publish an unprotected contact page before checking public output.', 'business-profile' ),
			);
		}
		$url    = get_permalink( $page );
		$home   = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $url );
		if ( ! $target || ! in_array( $target['scheme'] ?? '', array( 'http', 'https' ), true ) || ( $target['host'] ?? '' ) !== ( $home['host'] ?? '' ) || ( $target['port'] ?? null ) !== ( $home['port'] ?? null ) ) {
			return array(
				'status'  => 'unknown',
				'message' => __( 'The selected page does not have a same-site public URL.', 'business-profile' ),
			);
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => 1048576,
				'cookies'             => array(),
				'headers'             => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'status'  => 'unknown',
				'message' => __( 'The public page could not be retrieved. Check it manually while signed out; no verification is claimed.', 'business-profile' ),
			);
		}
		$body     = wp_remote_retrieve_body( $response );
		$entity   = bpfwpBusinessData::entity();
		$settings = get_option( 'bpfwp-settings', array() );
		$wanted   = array( $entity['@id'] );
		if ( in_array( $settings['business-schema-owner'] ?? 'native', array( 'integrated', 'external' ), true ) && ! empty( $settings['business-schema-provider-id'] ) ) {
			$wanted[] = $settings['business-schema-provider-id'];
		}
		global $bpfwp_controller;
		$business_types = array_keys( $bpfwp_controller->settings->get_schema_types() );
		preg_match_all( '#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script\s*>#is', $body, $matches );
		$found = array();
		foreach ( $matches[1] as $json ) {
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$nodes = $data['@graph'] ?? ( isset( $data['@type'] ) ? array( $data ) : $data );
			foreach ( $nodes as $node ) {
				if ( is_array( $node ) && in_array( $node['@id'] ?? '', $wanted, true ) && array_intersect( (array) ( $node['@type'] ?? array() ), $business_types ) && ! empty( $node['name'] ) ) {
					$found[] = $node;
				}
			}
		}
		if ( count( $found ) > 1 ) {
			return array(
				'status'  => 'duplicate',
				'message' => __( 'Multiple copies of the selected business entity were returned. Review schema ownership and cached markup.', 'business-profile' ),
			);
		}
		if ( $found ) {
			if ( 'external' === ( $settings['business-schema-owner'] ?? 'native' ) ) {
				return array(
					'status'  => 'external',
					'message' => __( 'The selected external entity was found. Its facts belong to the selected provider; compare them there before relying on this page.', 'business-profile' ),
				);
			}
			$owned = array( '@type', 'name', 'url', 'description', 'telephone', 'email', 'address', 'geo', 'image', 'openingHours', 'openingHoursSpecification', 'specialOpeningHoursSpecification' );
			foreach ( $owned as $key ) {
				if ( self::comparable( $entity[ $key ] ?? null ) !== self::comparable( $found[0][ $key ] ?? null ) ) {
					return array(
						'status'  => 'mismatch',
						'message' => __( 'The returned business facts differ from the saved information. Clear page caches and review schema ownership, then check again.', 'business-profile' ),
					);
				}
			}
			return array(
				'status'  => 'observed',
				'message' => __( 'The returned business entity matches the saved structured facts. Review the visible page separately.', 'business-profile' ),
			);
		}
		return array(
			'status'  => 'not_found',
			'message' => __( 'The page responded, but the selected business entity was not found in valid structured data. Review placement, theme overrides and schema ownership.', 'business-profile' ),
		);
	}

	private static function comparable( $value ) {
		if ( ! is_array( $value ) ) {
			return null === $value ? null : (string) $value;
		}
		$list  = array_keys( $value ) === range( 0, count( $value ) - 1 );
		$value = array_map( array( __CLASS__, 'comparable' ), $value );
		if ( $list ) {
			sort( $value );
		} else {
			ksort( $value );
		}
		return $value;
	}

	/** Repeated setup requests select the same draft, never publish an empty page. */
	public static function contact_page( $title, $selected = 0 ) {
		if ( ! current_user_can( 'edit_pages' ) || ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'bpfwp_setup_denied', __( 'You cannot create this contact page.', 'business-profile' ) );
		}
		$lock = bpfwpSettingsMutation::acquire();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			if ( $selected ) {
				$page = get_post( $selected );
				if ( ! $page || 'page' !== $page->post_type || in_array( $page->post_status, array( 'trash', 'auto-draft' ), true ) || ! current_user_can( 'edit_post', $page->ID ) ) {
					return new WP_Error( 'bpfwp_setup_page', __( 'Choose a page you can edit.', 'business-profile' ) );
				}
				$saved = bpfwpSettingsMutation::update( array( 'contact-page' => $page->ID ) );
				return is_wp_error( $saved ) ? $saved : $page->ID;
			}
			$current = bpfwpSettingsMutation::current();
			$page    = isset( $current['contact-page'] ) ? get_post( (int) $current['contact-page'] ) : null;
			if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status && current_user_can( 'edit_post', $page->ID ) ) {
				return $page->ID;
			}
			// Recover a prior created draft even if saving the selected-page setting failed.
			$drafts = get_posts(
				array(
					'post_type'   => 'page',
					'post_status' => 'draft',
					'meta_key'    => '_bpfwp-setup-contact',
					'meta_value'  => '1',
					'numberposts' => 1,
					'orderby'     => 'ID',
					'order'       => 'ASC',
				)
			);
			$id     = $drafts && current_user_can( 'edit_post', $drafts[0]->ID ) ? $drafts[0]->ID : wp_insert_post(
				array(
					'post_title'   => $title ? $title : __( 'Contact', 'business-profile' ),
					'post_content' => '[contact-card]',
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'meta_input'   => array( '_bpfwp-setup-contact' => 1 ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$saved = bpfwpSettingsMutation::update( array( 'contact-page' => $id ) );
			return is_wp_error( $saved ) ? $saved : $id;
		} finally {
			bpfwpSettingsMutation::release();
		}
	}
}
