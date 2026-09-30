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
			$stored                 = get_option( 'bpfwp-permission-level' );
			$this->permission_level = self::normalize_level( $stored );
			if ( ! is_array( $stored ) ) {
				update_option( 'bpfwp-permission-level', array( $this->permission_level ) );
			}
		}

		public function get_permission_level() {
			$this->set_permissions();
		}

	public function check_permission($permission_type = '') {
		if ( ! $this->permission_level ) { $this->get_permission_level(); }

		return ( array_key_exists( $permission_type, $this->plugin_permissions ) ? ( $this->permission_level >= $this->plugin_permissions[$permission_type] ? true : false ) : false );
	}

		public function update_permissions() {
			$this->get_permission_level();
		}
}

}