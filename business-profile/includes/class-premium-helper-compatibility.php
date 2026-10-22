<?php
/** A local bilateral API agreement, independent of shared Helper release numbering. */
defined( 'ABSPATH' ) || exit;

function bpfwp_get_premium_compatibility_descriptor() {
	return array(
		'product'           => 'BPFWP',
		'descriptor_schema' => 1,
		'package_version'   => BPFWP_VERSION,
		'contract_versions' => array( 1 ),
		'peer_api_versions' => array( 1 ),
		'mutation_protocol' => 2,
	);
}

class bpfwpPremiumHelperCompatibility {
	/** Saved keys and paid installations need recovery UI; keyless Free/trial metadata does not. */
	public static function has_license_history() {
		$key = get_option( 'bpfwp-license-key', '' );
		if ( is_string( $key ) && '' !== trim( $key ) && 'no_license_key_entered' !== trim( $key ) ) {
			return true;
		}
		$level = get_option( 'bpfwp-permission-level', 1 );
		return in_array( $level, array( 2, '2', array( 2 ), array( '2' ) ), true ) && 'Yes' !== get_option( 'BPFWP_Trial_Happening' ) && 'trial' !== ( self::contract()['status'] ?? '' );
	}
	public static function descriptor_valid( $descriptor ) {
		return is_array( $descriptor ) && 'BPFWP' === ( $descriptor['product'] ?? null ) && 1 === ( $descriptor['descriptor_schema'] ?? null )
			&& 2 === ( $descriptor['mutation_protocol'] ?? null )
			&& is_string( $descriptor['package_version'] ?? null ) && '' !== $descriptor['package_version']
			&& is_array( $descriptor['contract_versions'] ?? null ) && in_array( 1, $descriptor['contract_versions'], true )
			&& is_array( $descriptor['peer_api_versions'] ?? null ) && in_array( 1, $descriptor['peer_api_versions'], true );
	}

	public static function compatible() {
		return function_exists( 'fspph_bpfwp_get_compatibility_descriptor' ) && function_exists( 'fspph_bpfwp_get_entitlement_contract' )
			&& self::descriptor_valid( fspph_bpfwp_get_compatibility_descriptor() ) && self::descriptor_valid( bpfwp_get_premium_compatibility_descriptor() );
	}

	public static function contract() {
		if ( ! self::compatible() ) {
			return array();
		}
		$value = fspph_bpfwp_get_entitlement_contract( 1 );
		if ( ! is_array( $value ) || 'BPFWP' !== ( $value['product'] ?? null ) || 1 !== ( $value['contract_version'] ?? null ) || 1 !== ( $value['api_version'] ?? null )
			|| ! in_array( $value['status'] ?? null, array( 'valid', 'valid_cached', 'legacy_transition', 'trial', 'missing', 'invalid', 'revoked', 'unauthorized', 'unverifiable', 'window_exhausted', 'incompatible', 'helper_missing', 'storage_error' ), true )
			|| ! is_int( $value['permission_level'] ?? null ) || ! in_array( $value['permission_level'], array( 1, 2 ), true )
			|| ! is_int( $value['revision'] ?? null ) || $value['revision'] < 0 || ! is_array( $value['capabilities'] ?? null ) ) {
			return array();
		}
		foreach ( $value['capabilities'] as $capability ) {
			if ( ! is_string( $capability ) ) {
				return array();
			}
		}
		if ( array_diff( $value['capabilities'], bpfwpPremiumCapabilities::names() ) ) {
			return array();
		}
		$now = time();
		foreach ( array( 'last_attempt_at', 'last_success_at', 'trial_expiry', 'deadline' ) as $field ) {
			if ( ! is_int( $value[ $field ] ?? null ) || $value[ $field ] < 0 ) {
				return array();
			}
		}
		if ( $value['last_attempt_at'] > $now || $value['last_success_at'] > $now || $value['last_success_at'] > $value['last_attempt_at'] ) {
			return array();
		}
		if ( ! is_array( $value['support_subscription'] ?? null ) || ! in_array( $value['support_subscription']['status'] ?? null, array( 'current', 'expired', 'unknown' ), true ) ) {
			return array();
		}
		if ( in_array( $value['status'], array( 'valid', 'valid_cached', 'legacy_transition', 'trial' ), true ) ) {
			if ( 2 !== $value['permission_level'] || $now >= $value['deadline'] || $value['deadline'] > $now + 604800 ) {
				return array();
			}
			if ( 'legacy_transition' === $value['status'] && $value['deadline'] > $now + 345600 ) {
				return array();
			}
			if ( in_array( $value['status'], array( 'valid', 'valid_cached' ), true ) && ( $value['last_success_at'] <= 0 || $value['deadline'] !== $value['last_success_at'] + 604800 ) ) {
				return array();
			}
			if ( 'trial' === $value['status'] && $value['deadline'] !== $value['trial_expiry'] ) {
				return array();
			}
			if ( in_array( $value['status'], array( 'trial', 'legacy_transition' ), true ) && in_array( 'bulk_holiday_hours', $value['capabilities'], true ) ) {
				return array();
			}
		} elseif ( $value['capabilities'] || 1 !== $value['permission_level'] ) {
			return array();
		}
		return $value;
	}
}
