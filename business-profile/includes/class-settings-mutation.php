<?php
/** Serialize participating settings writers and merge only their submitted changes. */
defined( 'ABSPATH' ) || exit;

class bpfwpSettingsMutation {
	private static $token    = null;
	private static $writing  = false;
	private static $expected = null;
	private static $error    = null;
	const LOCK               = 'bpfwp-settings-mutation-lock';
	public static function has_lock() {
		return null !== self::$token;
	}

	public static function init() {
		add_filter( 'pre_update_option_bpfwp-settings', array( __CLASS__, 'before_update' ), 100, 2 );
		add_filter( 'pre_update_option', array( __CLASS__, 'commit' ), PHP_INT_MAX, 3 );
		add_action( 'updated_option', array( __CLASS__, 'after_update' ), 100, 3 );
		add_action( 'added_option', array( __CLASS__, 'after_add' ), 100, 2 );
		add_action( 'shutdown', array( __CLASS__, 'release' ) );
	}

	public static function acquire() {
		global $wpdb;
		if ( null !== self::$token ) {
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
			$parts  = explode( ':', self::$token );
			if ( $stored === self::$token && (int) $parts[1] > time() ) {
				return true;
			}
			self::$token = null;
			return new WP_Error( 'bpfwp_settings_busy', __( 'The save window expired. Reload the business information before retrying.', 'business-profile' ), array( 'status' => 409 ) );
		}
		// Another PHP process may have released a previously observed lock.
		wp_cache_delete( self::LOCK, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$token = wp_generate_uuid4() . ':' . ( time() + 120 );
		if ( ! add_option( self::LOCK, $token, '', false ) ) {
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
			$parts  = is_string( $stored ) ? explode( ':', $stored ) : array();
			if ( 2 !== count( $parts ) || ! ctype_digit( $parts[1] ) || (int) $parts[1] >= time() ) {
				return new WP_Error( 'bpfwp_settings_busy', __( 'Business information is being saved. Please try again.', 'business-profile' ), array( 'status' => 409 ) );
			}
			// Compare-and-swap an expired lease; never delete a successor's lock.
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, self::LOCK, $stored ) );
			wp_cache_delete( self::LOCK, 'options' );
			if ( 1 !== $changed ) {
				return new WP_Error( 'bpfwp_settings_busy', __( 'Business information is being saved. Please try again.', 'business-profile' ), array( 'status' => 409 ) );
			}
		}
		self::$token = $token;
		return true;
	}

	public static function release() {
		global $wpdb;
		if ( null === self::$token ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, self::$token ) );
		wp_cache_delete( self::LOCK, 'options' );
		self::$token = null;
	}

	public static function current() {
		wp_cache_delete( 'bpfwp-settings', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$value = get_option( 'bpfwp-settings', array() );
		return is_array( $value ) ? $value : array();
	}

	public static function update( $changes ) {
		if ( ! is_array( $changes ) ) {
			return new WP_Error( 'bpfwp_invalid_settings', __( 'Invalid business information.', 'business-profile' ), array( 'status' => 400 ) );
		}
		$validation = self::validate_schedule( $changes );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$had_lock    = null !== self::$token;
		$was_writing = self::$writing;
		$lock        = self::acquire();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			self::$writing = true;
			self::$error   = null;
			$current       = self::current();
			$next          = array_replace( $current, $changes );
			update_option( 'bpfwp-settings', $next );
			if ( is_wp_error( self::$error ) ) {
				return self::$error;
			}
			$stored = self::current();
			foreach ( $changes as $key => $value ) {
				if ( ! array_key_exists( $key, $stored ) || $stored[ $key ] !== $value ) {
					return new WP_Error( 'bpfwp_save_unverified', __( 'The requested values could not all be verified. Reload the business information before retrying.', 'business-profile' ), array( 'status' => 500 ) );
				}
			}
			return $stored;
		} finally {
			self::$writing = $was_writing;
			if ( ! $had_lock ) {
				self::release();
			}
		}
	}

	public static function before_update( $value, $old ) {
		global $wpdb;
		self::$error    = null;
		self::$expected = null;
		$lock           = self::acquire();
		if ( is_wp_error( $lock ) ) {
			self::$error = $lock;
			add_settings_error( 'bpfwp-settings', $lock->get_error_code(), $lock->get_error_message() );
			return $old;
		}
		self::$expected = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'bpfwp-settings' ) );
		if ( self::$writing ) {
			return $value;
		}
		if ( ! is_array( $value ) ) {
			return $old;
		}
		$raw   = isset( $_POST['bpfwp-settings'] ) && is_array( $_POST['bpfwp-settings'] ) ? wp_unslash( $_POST['bpfwp-settings'] ) : $value;
		$error = self::validate_schedule( $raw );
		if ( is_wp_error( $error ) ) {
			self::$error = $error;
			add_settings_error( 'bpfwp-settings', $error->get_error_code(), $error->get_error_message() );
			self::release();
			return $old;
		}
		// The Settings API has already sanitized values. Missing tabs/groups must survive.
		if ( isset( $_POST['option_page'], $_POST['bpfwp-settings'] ) && 'bpfwp-settings' === $_POST['option_page'] && is_array( $_POST['bpfwp-settings'] ) ) {
			$value = array_intersect_key( $value, $_POST['bpfwp-settings'] );
		}
		return array_replace( self::current(), $value );
	}

	/** Commit after option filters, fencing the mutation in the database statement itself. */
	public static function commit( $value, $option, $old ) {
		global $wpdb;
		if ( 'bpfwp-settings' !== $option ) {
			return $value;
		}
		$expected       = self::$expected;
		self::$expected = null;
		$lock           = self::acquire();
		if ( is_wp_error( $lock ) || is_wp_error( self::$error ) ) {
			self::$error = is_wp_error( $lock ) ? $lock : self::$error;
			return $old;
		}
		$serialized = maybe_serialize( $value );
		// The derived table with LIMIT prevents MySQL target-table merging; SQLite supports it too.
		$parts = explode( ':', self::$token );
		if ( null === $expected ) {
			$sql = $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) SELECT %s, %s, 'no' WHERE (SELECT lease FROM (SELECT option_value AS lease FROM {$wpdb->options} WHERE option_name = %s LIMIT 1) AS bpfwp_lease) = %s AND UNIX_TIMESTAMP() < %d", 'bpfwp-settings', $serialized, self::LOCK, self::$token, (int) $parts[1] );
		} else {
			$sql = $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s AND (SELECT lease FROM (SELECT option_value AS lease FROM {$wpdb->options} WHERE option_name = %s LIMIT 1) AS bpfwp_lease) = %s AND UNIX_TIMESTAMP() < %d", $serialized, 'bpfwp-settings', $expected, self::LOCK, self::$token, (int) $parts[1] );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Both complete statements are prepared immediately above.
		$changed = $wpdb->query( $sql );
		$noop    = 0 === $changed && $serialized === $expected && ! is_wp_error( self::acquire() );
		wp_cache_delete( 'bpfwp-settings', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( 1 !== $changed && ! $noop ) {
			self::$error = new WP_Error( 'bpfwp_settings_conflict', __( 'Business information changed during this save. Reload and retry; the newer save was retained.', 'business-profile' ), array( 'status' => 409 ) );
			add_settings_error( 'bpfwp-settings', self::$error->get_error_code(), self::$error->get_error_message() );
		} elseif ( $serialized !== $expected ) {
			do_action( 'update_option_bpfwp-settings', $old, $value, $option );
			do_action( 'updated_option', $option, $old, $value );
		}
		// Prevent core from following the fenced mutation with an unconditional UPDATE.
		return $old;
	}

	public static function validate_schedule( $changes ) {
		if ( ! array_key_exists( 'opening-hours', $changes ) && ! array_key_exists( 'exceptions', $changes ) ) {
			return true;
		}
		$schedule = bpfwpBusinessHours::normalize( $changes['opening-hours'] ?? array(), $changes['exceptions'] ?? array() );
		if ( ! $schedule['errors'] ) {
			return true;
		}
		if ( get_current_user_id() ) {
			set_transient( 'bpfwp_schedule_draft_' . get_current_user_id(), array_intersect_key( $changes, array_flip( array( 'opening-hours', 'exceptions' ) ) ), DAY_IN_SECONDS );
		}
		return new WP_Error( 'bpfwp_invalid_hours', __( 'The hours contain an invalid date, time, or weekday. Previous hours were retained; your submitted schedule is available in Publication Checks for recovery.', 'business-profile' ), array( 'status' => 400 ) );
	}

	public static function after_update( $option, $old, $value ) {
		if ( 'bpfwp-settings' !== $option ) {
			return;
		}
		update_option( 'bpfwp-settings-revision', wp_generate_uuid4(), false );
		if ( ! self::$writing ) {
			self::release();
		}
	}

	public static function after_add( $option, $value ) {
		self::after_update( $option, false, $value );
	}
}
