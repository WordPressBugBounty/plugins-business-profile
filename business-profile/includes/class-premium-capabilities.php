<?php
/** Explicit paid setting ownership; saved values are never rewritten on suspension. */
defined( 'ABSPATH' ) || exit;

class bpfwpPremiumCapabilities {
	public static function names() {
		return array( 'card_ordering', 'custom_fields', 'labelling', 'styling', 'article_schema', 'woocommerce_schema', 'schema_default_helpers', 'seo_integration', 'api_usage', 'bulk_holiday_hours' );
	}

	public static function settings() {
		$map = array(
			'contact-card-elements-order' => 'card_ordering',
			'custom-fields'               => 'custom_fields',
			'custom_field_values'         => 'custom_fields',
			'article-rich-snippets'       => 'article_schema',
			'woocommerce-integration'     => 'woocommerce_schema',
			'schema-default-helpers'      => 'schema_default_helpers',
			'seo-provider'                => 'seo_integration',
			'business-schema-owner'       => 'seo_integration',
			'business-schema-provider-id' => 'seo_integration',
			'fsp-api-keys'                => 'api_usage',
		);
		foreach ( array( 'opening-hours', 'place-an-order', 'get-directions', 'contact', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'monday-abbreviation', 'tuesday-abbreviation', 'wednesday-abbreviation', 'thursday-abbreviation', 'friday-abbreviation', 'saturday-abbreviation', 'sunday-abbreviation', 'open', 'open-until', 'open-from', 'closed', 'special-opening-hours', 'holidays' ) as $name ) {
			$map[ 'label-' . $name ] = 'labelling';
		}
		foreach ( array( 'contact-card-layout', 'map-width', 'disable-icons', 'main-font-family', 'main-font-size', 'main-text-color', 'name-font-family', 'name-font-size', 'name-text-color', 'heading-font-family', 'heading-font-size', 'heading-text-color', 'link-color', 'link-hover-color' ) as $name ) {
			$map[ 'styling-' . $name ] = 'styling';
		}
		return $map;
	}

	public static function allowed( $capability ) {
		global $bpfwp_controller;
		return isset( $bpfwp_controller->permissions ) && $bpfwp_controller->permissions->check_permission( $capability );
	}

	/** Paid writers acquire this before any business settings or location lease. */
	public static function acquire( $capability ) {
		if ( ! bpfwpPremiumHelperCompatibility::compatible() || ! class_exists( 'fspphBPFWPEntitlementStore' ) ) {
			return new WP_Error( 'bpfwp_premium_paused', __( 'Premium features are paused; your settings are retained.', 'business-profile' ), array( 'status' => 403 ) );
		}
		$lock = fspphBPFWPEntitlementStore::acquire();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		if ( ! self::allowed( $capability ) ) {
			fspphBPFWPEntitlementStore::release();
			return new WP_Error( 'bpfwp_premium_paused', __( 'Premium features are paused; your settings are retained.', 'business-profile' ), array( 'status' => 403 ) );
		}
		return true;
	}
	public static function release() {
		if ( class_exists( 'fspphBPFWPEntitlementStore' ) ) {
			fspphBPFWPEntitlementStore::release();
		}
	}
	/** A mixed implementation pair must pause paid writes rather than call a missing fence. */
	public static function paid_fence( $capability ) {
		return bpfwpPremiumHelperCompatibility::compatible() && method_exists( 'fspphBPFWPEntitlementStore', 'paid_fence' ) ? fspphBPFWPEntitlementStore::paid_fence( $capability ) : '0 = 1';
	}
	public static function location_custom_fields( $id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'custom_field_values'", $id ), ARRAY_A );
	}
	public static function save_location_custom_fields( $id, $expected, $values ) {
		global $wpdb;
		if ( ! self::allowed( 'custom_fields' ) || ! current_user_can( 'edit_post', $id ) || ! fspphBPFWPEntitlementStore::owns() ) {
			return false;
		}
		$next = (string) maybe_serialize( $values );
		$sql  = null === $expected ? $wpdb->prepare( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) SELECT %d,'custom_field_values',%s WHERE NOT EXISTS (SELECT found FROM (SELECT meta_id AS found FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'custom_field_values' LIMIT 1) AS bpfwp_custom_exists)", $id, $next, $id )
			: $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d AND meta_value = %s", $next, $expected['meta_id'], $expected['meta_value'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared statement and the store's prepared authority predicate.
		$count = $wpdb->query( $sql . ' AND ' . self::paid_fence( 'custom_fields' ) );
		wp_cache_delete( $id, 'post_meta' );
		$rows = self::location_custom_fields( $id );
		return ( 1 === $count || ( 0 === $count && null !== $expected && $expected['meta_value'] === $next && fspphBPFWPEntitlementStore::owns() ) ) && 1 === count( $rows ) && $rows[0]['meta_value'] === $next;
	}

	/** Ordinary Free saves retain inaccessible paid fields rather than defaulting them. */
	public static function preserve( $changes, $current ) {
		foreach ( self::settings() as $key => $capability ) {
			if ( array_key_exists( $key, $changes ) && ! self::allowed( $capability ) ) {
				if ( array_key_exists( $key, $current ) ) {
					$changes[ $key ] = $current[ $key ];
				} else {
					unset( $changes[ $key ] );
				}
			}
		}
		return $changes;
	}
}
