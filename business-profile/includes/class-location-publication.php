<?php
/** Central public-location policy; booking identity remains Reservations-owned. */
defined( 'ABSPATH' ) || exit;

class bpfwpLocationPublication {
	private $rest_publication  = null;
	private $classic_status    = null;
	private $publication_error = null;
	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemap_args' ), 10, 2 );
		add_filter( 'wpseo_sitemap_entry', array( $this, 'sitemap_entry' ), 10, 3 );
		add_filter( 'rank_math/sitemap/entry', array( $this, 'sitemap_entry' ), 10, 3 );
		add_filter( 'rest_pre_dispatch', array( $this, 'rest_item' ), 10, 3 );
		add_filter( 'oembed_response_data', array( $this, 'oembed' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_action( 'save_post', array( $this, 'save' ), 20, 3 );
		add_filter( 'wp_insert_post_data', array( $this, 'require_purpose' ), 10, 2 );
		add_action( 'init', array( $this, 'rest_collection_hook' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
		add_filter( 'rest_request_after_callbacks', array( $this, 'publication_result' ), 10, 3 );
	}

	public function sitemap_entry( $entry, $type, $entry_object ) {
		if ( 'post' === $type && $entry_object instanceof WP_Post && self::post_type() === $entry_object->post_type && ! self::eligible( $entry_object->ID ) ) {
			return false;
		}
		return $entry;
	}

	public function rest_collection_hook() {
		add_filter( 'rest_' . self::post_type() . '_query', array( $this, 'rest_collection' ), 10, 2 );
		add_post_type_support( self::post_type(), 'custom-fields' );
		register_post_meta(
			self::post_type(),
			'bpfwp-location-purpose',
			array(
				'single'        => true,
				'type'          => 'string',
				'show_in_rest'  => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array( '', 'public', 'internal' ),
					),
				),
				'auth_callback' => function ( $allowed, $key, $id ) {
							return current_user_can( 'edit_post', $id );
				},
			)
		);
		add_filter( 'rest_pre_insert_' . self::post_type(), array( $this, 'validate_rest_purpose' ), 10, 2 );
		add_action( 'rest_after_insert_' . self::post_type(), array( $this, 'after_rest_save' ), 10, 2 );
	}
	public function editor_assets() {
		$screen = get_current_screen();
		if ( ! $screen || self::post_type() !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'bpfwp-location-purpose', BPFWP_PLUGIN_URL . '/assets/js/location-purpose.js', array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ), BPFWP_VERSION, true );
	}
	public function validate_rest_purpose( $prepared, $request ) {
		$this->rest_publication  = null;
		$this->publication_error = null;
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$id       = (int) $request['id'];
		$old      = $id ? get_post_meta( $id, 'bpfwp-location-purpose', true ) : '';
		$meta     = $request->get_param( 'meta' );
		$purpose  = is_array( $meta ) && array_key_exists( 'bpfwp-location-purpose', $meta ) ? $meta['bpfwp-location-purpose'] : $old;
		$status   = $prepared->post_status ?? ( $id ? get_post_status( $id ) : 'draft' );
		$required = ! $id || self::requires_purpose( $id );
		if ( $required && in_array( $status, array( 'publish', 'future' ), true ) && ! in_array( $purpose, array( 'public', 'internal' ), true ) ) {
			return new WP_Error( 'bpfwp_purpose_required', __( 'Choose a location purpose in the Location purpose panel before publishing.', 'business-profile' ), array( 'status' => 400 ) );
		}
		if ( 'internal' === $old && 'internal' !== $purpose && true !== $request->get_param( 'bpfwp_confirm_public' ) ) {
			return new WP_Error( 'bpfwp_confirm_public', __( 'Confirm publication in the Location purpose panel before making this internal resource public.', 'business-profile' ), array( 'status' => 400 ) );
		}
		if ( $required && in_array( $status, array( 'publish', 'future' ), true ) ) {
			// The post row must stay private until REST has persisted its purpose.
			$this->rest_publication = array(
				'request' => $request,
				'status'  => $status,
				'purpose' => $purpose,
			);
			$prepared->post_status  = 'draft';
		}
		return $prepared;
	}
	public function after_rest_save( $post, $request ) {
		$pending                = $this->rest_publication;
		$this->rest_publication = null;
		$purpose                = get_post_meta( $post->ID, 'bpfwp-location-purpose', true );
		if ( in_array( $purpose, array( 'public', 'internal' ), true ) ) {
			delete_post_meta( $post->ID, 'bpfwp-purpose-required' );
		}
		if ( $pending && $pending['request'] === $request ) {
			if ( $purpose === $pending['purpose'] && ! get_post_meta( $post->ID, 'bpfwp-purpose-required', true ) ) {
				$result            = wp_update_post(
					array(
						'ID'          => $post->ID,
						'post_status' => $pending['status'],
					),
					true
				);
				$post->post_status = get_post_status( $post->ID );
				if ( ! is_wp_error( $result ) && in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
					return;
				}
			}
			$this->publication_error = new WP_Error( 'bpfwp_publication_failed', __( 'The location was retained as a draft because its publication could not be verified. Reload and retry.', 'business-profile' ), array( 'status' => 500 ) );
			$this->rest_publication  = $pending;
		}
	}

	public function publication_result( $response, $handler, $request ) {
		if ( $this->rest_publication && $this->rest_publication['request'] === $request ) {
			$this->rest_publication = null;
			if ( $this->publication_error ) {
				return $this->publication_error;
			}
		}
		return $response;
	}

	public function rest_collection( $args, $request ) {
		if ( 'edit' === $request->get_param( 'context' ) && current_user_can( 'edit_posts' ) ) {
			$args['bpfwp_authorized_editor'] = true;
		} else {
			$args['meta_query'] = self::public_meta_query( isset( $args['meta_query'] ) ? $args['meta_query'] : array() );
		}
		return $args;
	}

	public static function post_type() {
		global $bpfwp_controller;
		return isset( $bpfwp_controller->cpts ) ? $bpfwp_controller->cpts->location_cpt_slug : 'location';
	}

	private static function requires_purpose( $id ) {
		$status = get_post_status( $id );
		// A failed marker write must not turn a retained draft into a legacy public record.
		return 'auto-draft' === $status || get_post_meta( $id, 'bpfwp-purpose-required', true ) || ( in_array( $status, array( 'draft', 'pending' ), true ) && ! in_array( get_post_meta( $id, 'bpfwp-location-purpose', true ), array( 'public', 'internal' ), true ) );
	}

	public static function eligible( $id ) {
		$post = get_post( $id );
		return $post && self::post_type() === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password && ! get_post_meta( $post->ID, 'bpfwp-purpose-required', true ) && 'internal' !== get_post_meta( $post->ID, 'bpfwp-location-purpose', true );
	}

	public static function public_meta_query( $existing = array() ) {
		$policy = array(
			'relation' => 'OR',
			array(
				'key'     => 'bpfwp-location-purpose',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'bpfwp-location-purpose',
				'value'   => 'internal',
				'compare' => '!=',
			),
		);
		$policy = array(
			'relation' => 'AND',
			$policy,
			array(
				'key'     => 'bpfwp-purpose-required',
				'compare' => 'NOT EXISTS',
			),
		);
		return $existing ? array(
			'relation' => 'AND',
			$existing,
			$policy,
		) : $policy;
	}

	public function filter_query( $query ) {
		if ( is_admin() ) {
			return;
		}
		if ( $query->get( 'bpfwp_authorized_editor' ) && current_user_can( 'edit_posts' ) ) {
			return;
		}
		$id = $query->get( 'p' );
		if ( $query->is_preview() && $id && current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$type = $query->get( 'post_type' );
		if ( ! $type && ! $query->is_search() && ! $query->get( self::post_type() ) && ! $id ) {
			return;
		}
		// Mixed search queries can expose locations too. Never touch term/booking storage.
		if ( $type && 'any' !== $type && ! in_array( self::post_type(), (array) $type, true ) ) {
			return;
		}
		$query->set( 'meta_query', self::public_meta_query( $query->get( 'meta_query' ) ) );
	}

	public function sitemap_args( $args, $type ) {
		if ( self::post_type() === $type ) {
			$args['meta_query'] = self::public_meta_query( isset( $args['meta_query'] ) ? $args['meta_query'] : array() );
		}
		return $args;
	}

	public function rest_item( $result, $server, $request ) {
		$type      = get_post_type_object( self::post_type() );
		$base      = $type && ! empty( $type->rest_base ) ? $type->rest_base : self::post_type();
		$namespace = $type && ! empty( $type->rest_namespace ) ? $type->rest_namespace : 'wp/v2';
		if ( preg_match( '#^/' . preg_quote( $namespace . '/' . $base, '#' ) . '/(\d+)(?:/|$)#', $request->get_route(), $match ) ) {
			$id = (int) $match[1];
			if ( ! self::eligible( $id ) && ! ( 'edit' === $request->get_param( 'context' ) && current_user_can( 'edit_post', $id ) ) && in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) ) {
				return new WP_Error( 'rest_post_invalid_id', __( 'Not found.', 'business-profile' ), array( 'status' => 404 ) );
			}
		}
		return $result;
	}

	public function oembed( $data, $post ) {
		return self::post_type() === $post->post_type && ! self::eligible( $post->ID ) ? false : $data;
	}

	public function metabox() {
		add_meta_box( 'bpfwp-location-purpose', __( 'Location purpose', 'business-profile' ), array( $this, 'render' ), self::post_type(), 'side', 'high', array( '__back_compat_meta_box' => true ) );
	}

	public function render( $post ) {
		$value = get_post_meta( $post->ID, 'bpfwp-location-purpose', true );
		wp_nonce_field( 'bpfwp-purpose', 'bpfwp-purpose-nonce' );
		echo '<p><label for="bpfwp-purpose">' . esc_html__( 'How will this location be used?', 'business-profile' ) . '</label></p><select name="bpfwp-location-purpose" id="bpfwp-purpose" required>';
		$choices = array(
			''         => 'auto-draft' === $post->post_status ? __( 'Choose a purpose', 'business-profile' ) : __( 'Existing publication behavior', 'business-profile' ),
			'public'   => __( 'Public business location', 'business-profile' ),
			'internal' => __( 'Internal booking resource', 'business-profile' ),
		);
		foreach ( $choices as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p>' . esc_html__( 'Internal resources do not have public business pages, cards or schema. Switching to internal removes those public outputs while retaining the location record. Booking availability and history remain controlled by Reservations.', 'business-profile' ) . '</p>';
		if ( 'internal' === $value ) {
			echo '<p><label><input type="checkbox" name="bpfwp-confirm-public" value="1"> ' . esc_html__( 'I confirm that switching to public may publish this location and its business information.', 'business-profile' ) . '</label></p>';
		}
	}

	public function require_purpose( $data, $postarr ) {
		if ( self::post_type() !== $data['post_type'] || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
			return $data;
		}
		$old = ! empty( $postarr['ID'] ) ? get_post( $postarr['ID'] ) : null;
		if ( $old && ! self::requires_purpose( $old->ID ) ) {
			return $data;
		}
		$this->classic_status = $data['post_status'];
		$data['post_status']  = 'draft';
		return $data;
	}

	public function save( $id, $post, $update ) {
		$requested_status = null;
		if ( self::post_type() === $post->post_type ) {
			$requested_status     = $this->classic_status;
			$this->classic_status = null;
		}
		if ( self::post_type() === $post->post_type && ! $update ) {
			update_post_meta( $id, 'bpfwp-purpose-required', 1 );
		}
		if ( self::post_type() !== get_post_type( $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		if ( empty( $_POST['bpfwp-purpose-nonce'] ) || ! is_string( $_POST['bpfwp-purpose-nonce'] ) || ! wp_verify_nonce( $_POST['bpfwp-purpose-nonce'], 'bpfwp-purpose' ) ) {
			return;
		}
		$purpose = isset( $_POST['bpfwp-location-purpose'] ) ? $_POST['bpfwp-location-purpose'] : '';
		if ( ! in_array( $purpose, array( 'public', 'internal' ), true ) ) {
			return;
		}
		$old = get_post_meta( $id, 'bpfwp-location-purpose', true );
		if ( 'internal' === $old && 'public' === $purpose && empty( $_POST['bpfwp-confirm-public'] ) ) {
			return;
		}
		if ( $old !== $purpose ) {
			update_post_meta( $id, 'bpfwp-location-purpose', $purpose );
		}
		if ( get_post_meta( $id, 'bpfwp-location-purpose', true ) === $purpose ) {
			delete_post_meta( $id, 'bpfwp-purpose-required' );
			if ( $old !== $purpose ) {
				do_action( 'bpfwp_location_purpose_changed', $id, $old, $purpose );
			}
			if ( $requested_status && ! get_post_meta( $id, 'bpfwp-purpose-required', true ) ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => $requested_status,
					)
				);
			}
		}
	}
}
