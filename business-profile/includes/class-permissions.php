<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'bpfwpPermissions' ) ) {
/**
 * Class to handle plugin permissions for Business Profile
 *
 * @since 2.0.0
 */
class bpfwpPermissions {

	private $plugin_permissions;
	private $permission_level;

	public function __construct() {
		$this->plugin_permissions = array(
			"premium" => 2,
			"labelling" => 2,
			"styling" => 2,
			"locations" => 2,
			"integrations" => 2,
			"api_usage"	=> 2
		);
	}

		/** Normalize only the established Free/Premium levels; malformed data grants nothing. */
		public static function normalize_level( $value ) {
			if ( is_array( $value ) ) {
				$value = count( $value ) === 1 ? reset( $value ) : null;
			}
			return in_array( $value, array( 2, '2' ), true ) ? 2 : 1;
		}

		public function set_permissions() {
			$contract               = bpfwpPremiumHelperCompatibility::contract();
			$this->permission_level = $contract['permission_level'] ?? 1;
		}

		public function get_permission_level() {
			$this->set_permissions();
		}

		public function check_permission( $permission_type = '' ) {
			if ( ! is_string( $permission_type ) || ( ! array_key_exists( $permission_type, $this->plugin_permissions ) && ! in_array( $permission_type, bpfwpPremiumCapabilities::names(), true ) ) ) {
				return false;
			}
			$contract = bpfwpPremiumHelperCompatibility::contract();
			if ( 2 !== ( $contract['permission_level'] ?? 1 ) ) {
				return false;
			}
			$caps = $contract['capabilities'];
			if ( in_array( $permission_type, array( 'premium', 'locations' ), true ) ) {
				return ! empty( $caps );
			}
			if ( 'integrations' === $permission_type ) {
				return in_array( 'article_schema', $caps, true ) && in_array( 'woocommerce_schema', $caps, true );
			}
			return in_array( $permission_type, $caps, true );
		}

		public function update_permissions() {
			$this->get_permission_level();
		}
}

}
