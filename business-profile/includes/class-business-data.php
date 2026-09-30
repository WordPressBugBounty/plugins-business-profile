<?php
/** Public business fields and explicit location inheritance. */
defined( 'ABSPATH' ) || exit;

class bpfwpBusinessData {
	private static $pending_entities = array();
	public static function queue_entity( $entity, $location ) {
		if ( ! is_array( $entity ) || empty( $entity['@id'] ) ) {
			return;
		}
		self::$pending_entities[ $entity['@id'] ] = array(
			'entity'   => $entity,
			'location' => (int) $location,
		);
	}
	/** Block themes can render content before wp_head; decide ownership only at output time. */
	public static function output_entities() {
		foreach ( self::$pending_entities as $pending ) {
			$entity = apply_filters( 'bpfwp_business_schema_output', $pending['entity'], $pending['location'] );
			if ( ! $entity ) {
				continue;
			}
			echo '<script type="application/ld+json" class="bpfwp-business-schema">' . wp_json_encode( bpfwp_normalize_contact_json( $entity ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n";
		}
		self::$pending_entities = array();
	}
	public static function fields() {
		return array( 'schema_type', 'name', 'description', 'address', 'phone', 'clickphone', 'cell-phone', 'clickcellphone', 'whatsapp', 'whatsappdisplay', 'whatsapptext', 'fax', 'contact-email', 'contact-page', 'ordering-link', 'image', 'opening-hours', 'exceptions' );
	}

	public static function mode_fields() {
		return array_diff( self::fields(), array( 'name', 'description', 'schema_type' ) );
	}

	/** Null means legacy resolution must continue without reclassifying stored values. */
	public static function explicit_value( $settings, $field, $location ) {
		if ( ! $location || ! in_array( $field, self::mode_fields(), true ) ) {
			return null;
		}
		$modes = get_post_meta( $location, 'bpfwp-field-modes', true );
		$mode  = is_array( $modes ) && isset( $modes[ $field ] ) ? $modes[ $field ] : 'legacy';
		if ( ! in_array( $mode, array( 'inherit', 'override', 'empty' ), true ) ) {
			return null;
		}
		if ( 'inherit' === $mode ) {
			$value = $settings->get_setting( $field );
		} elseif ( 'empty' === $mode ) {
			$value = in_array( $field, array( 'opening-hours', 'exceptions' ), true ) ? array() : '';
			if ( 'address' === $field ) {
				$value = array(
					'text' => '',
					'lat'  => '',
					'lon'  => '',
				);
			}
			if ( in_array( $field, array( 'image', 'contact-page' ), true ) ) {
				$value = 0;
			}
		} elseif ( 'address' === $field ) {
			$value = array(
				'text' => get_post_meta( $location, 'geo_address', true ),
				'lat'  => get_post_meta( $location, 'geo_latitude', true ),
				'lon'  => get_post_meta( $location, 'geo_longitude', true ),
			);
		} elseif ( 'image' === $field ) {
			$value = get_post_thumbnail_id( $location );
		} else {
			$keys  = array(
				'opening-hours' => 'opening_hours',
				'contact-email' => 'contact_email',
				'contact-page'  => 'contact_post',
			);
			$value = get_post_meta( $location, isset( $keys[ $field ] ) ? $keys[ $field ] : $field, true );
			if ( in_array( $field, array( 'opening-hours', 'exceptions' ), true ) && ! is_array( $value ) ) {
				$value = array();
			}
		}
		return array(
			'value'    => $value,
			'mode'     => $mode,
			'location' => (int) $location,
		);
	}

	/** Deliberately excludes license/API keys and arbitrary settings. */
	public static function facts( $location = 0 ) {
		global $bpfwp_controller;
		$facts = array();
		foreach ( self::fields() as $field ) {
			$facts[ $field ] = $bpfwp_controller->settings->get_setting( $field, $location );
		}
		return $facts;
	}

	public static function register_sources( $sources ) {
		foreach ( array( 'name', 'phone', 'contact-email', 'ordering-link' ) as $field ) {
			$sources[ 'function bpfwp_public_' . str_replace( '-', '_', $field ) ] = array(
				'context' => 'site',
				'type'    => 'scalar',
				'resolve' => function () use ( $field ) {
					global $bpfwp_controller;
					return $bpfwp_controller->settings->get_setting( $field );
				},
			);
		}
		return $sources;
	}

	/** Shared typed public entity for native output and trusted graph adapters. */
	public static function entity( $location = 0 ) {
		if ( $location && ! bpfwpLocationPublication::eligible( $location ) ) {
			return array();
		}
		$facts = self::facts( $location );
		global $bpfwp_controller;
		$type = $facts['schema_type'];
		if ( ! is_string( $type ) || ! array_key_exists( $type, $bpfwp_controller->settings->get_schema_types() ) ) {
			$type = 'Organization';
		}
		$url    = $location ? get_permalink( $location ) : home_url( '/' );
		$entity = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'@id'      => $url . '#bpfwp-business',
			'url'      => $url,
		);
		foreach ( array(
			'name'          => 'name',
			'description'   => 'description',
			'phone'         => 'telephone',
			'contact-email' => 'email',
		) as $field => $property ) {
			if ( is_string( $facts[ $field ] ) && '' !== $facts[ $field ] ) {
				$entity[ $property ] = wp_strip_all_tags( $facts[ $field ] );
			}
		}
		$address = is_array( $facts['address'] ) ? $facts['address'] : array();
		if ( ! empty( $address['text'] ) ) {
			$entity['address'] = array(
				'@type'         => 'PostalAddress',
				'streetAddress' => wp_strip_all_tags( $address['text'] ),
			);
		}
		if ( isset( $address['lat'], $address['lon'] ) && is_numeric( $address['lat'] ) && is_numeric( $address['lon'] ) && abs( (float) $address['lat'] ) <= 90 && abs( (float) $address['lon'] ) <= 180 ) {
			$entity['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $address['lat'],
				'longitude' => (float) $address['lon'],
			);
		}
		$image = $facts['image'] ? wp_get_attachment_url( $facts['image'] ) : false;
		if ( $image ) {
			$entity['image'] = $image;
		}
		$hours = bpfwpBusinessHours::normalize( $facts['opening-hours'], $facts['exceptions'] );
		foreach ( array(
			'openingHoursSpecification'        => bpfwpBusinessHours::weekly_schema( $hours ),
			'specialOpeningHoursSpecification' => bpfwpBusinessHours::exception_schema( $hours ),
		) as $key => $value ) {
			if ( $value ) {
				$entity[ $key ] = $value;
			}
		}
		return $entity;
	}

	public static function render_modes( $post ) {
		$labels  = array(
			'address'         => __( 'Address and coordinates', 'business-profile' ),
			'phone'           => __( 'Phone', 'business-profile' ),
			'clickphone'      => __( 'Phone link', 'business-profile' ),
			'cell-phone'      => __( 'Mobile phone', 'business-profile' ),
			'clickcellphone'  => __( 'Mobile phone link', 'business-profile' ),
			'whatsapp'        => __( 'WhatsApp number', 'business-profile' ),
			'whatsappdisplay' => __( 'WhatsApp display', 'business-profile' ),
			'whatsapptext'    => __( 'WhatsApp message', 'business-profile' ),
			'fax'             => __( 'Fax', 'business-profile' ),
			'contact-email'   => __( 'Contact email', 'business-profile' ),
			'contact-page'    => __( 'Contact page', 'business-profile' ),
			'ordering-link'   => __( 'Ordering link', 'business-profile' ),
			'image'           => __( 'Image', 'business-profile' ),
			'opening-hours'   => __( 'Opening hours', 'business-profile' ),
			'exceptions'      => __( 'Exceptions', 'business-profile' ),
		);
		$modes   = get_post_meta( $post->ID, 'bpfwp-field-modes', true );
		$choices = array(
			'legacy'   => __( 'Existing behavior', 'business-profile' ),
			'inherit'  => __( 'Use main business value', 'business-profile' ),
			'override' => __( 'Use this location value', 'business-profile' ),
			'empty'    => __( 'Leave intentionally empty', 'business-profile' ),
		);
		echo '<p>' . esc_html__( 'Choose how each field is published. Local values are retained when inheriting or leaving a field empty. Public hours do not change booking availability.', 'business-profile' ) . '</p>';
		foreach ( self::mode_fields() as $field ) {
			$id   = 'bpfwp-mode-' . $field;
			$mode = is_array( $modes ) && isset( $modes[ $field ] ) ? $modes[ $field ] : 'legacy';
			echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $labels[ $field ] ) . '</label><br><select id="' . esc_attr( $id ) . '" name="bpfwp-field-modes[' . esc_attr( $field ) . ']">';
			foreach ( $choices as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $mode, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></p>';
		}
	}

	/** Called only after the location editor's capability and nonce checks. */
	public static function save_modes( $post_id, $submitted ) {
		if ( ! is_array( $submitted ) ) {
			return;
		}
		$modes = get_post_meta( $post_id, 'bpfwp-field-modes', true );
		$modes = is_array( $modes ) ? $modes : array();
		foreach ( self::mode_fields() as $field ) {
			if ( ! isset( $submitted[ $field ] ) ) {
				continue;
			}
			if ( 'legacy' === $submitted[ $field ] ) {
				unset( $modes[ $field ] );
			} elseif ( in_array( $submitted[ $field ], array( 'inherit', 'override', 'empty' ), true ) ) {
				$modes[ $field ] = $submitted[ $field ];
			}
		}
		update_post_meta( $post_id, 'bpfwp-field-modes', $modes );
	}
}
