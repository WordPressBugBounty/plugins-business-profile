<?php
/** Shared Free writer for public location schedules, with revision and lease fencing. */
defined( 'ABSPATH' ) || exit;

class bpfwpLocationHoursMutation {
	public static function keys() {
		return array( 'opening_hours', 'exceptions', 'disable_main_exceptions', 'bpfwp-field-modes' );
	}
	private static function revision_key( $id ) {
		return 'bpfwp-location-hours-revision-' . (int) $id;
	}
	private static function option_raw( $key ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
	}
	/** Always bypass the metadata/option cache before comparing accepted values. */
	public static function snapshot( $id ) {
		global $wpdb;
		$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('opening_hours','exceptions','disable_main_exceptions','bpfwp-field-modes','bpfwp-location-purpose') ORDER BY meta_id", $id ), ARRAY_A );
		$raw       = array();
		$values    = array();
		$duplicate = false;
		foreach ( $rows as $row ) {
			if ( isset( $raw[ $row['meta_key'] ] ) ) {
				$duplicate = true;
			}
			$raw[ $row['meta_key'] ]    = $row;
			$values[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] );
		}
		foreach ( array_merge( self::keys(), array( 'bpfwp-location-purpose' ) ) as $key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				$values[ $key ] = '';
			}
		}
		$settings  = bpfwpSettingsMutation::current();
		$inherited = array(
			'opening-hours' => $settings['opening-hours'] ?? array(),
			'exceptions'    => $settings['exceptions'] ?? array(),
		);
		$editor_raw = $raw;
		unset( $editor_raw['bpfwp-location-purpose'] );
		$revision = self::option_raw( self::revision_key( $id ) );
		return array(
			'id'                 => (int) $id,
			'raw'                => $raw,
			'values'             => $values,
			'revision'           => $revision,
			'inherited'          => $inherited,
			'duplicate'          => $duplicate,
			'editor_fingerprint' => hash( 'sha256', serialize( array( $editor_raw, $revision, $inherited ) ) ),
			'fingerprint'        => hash( 'sha256', serialize( array( $raw, $revision, $inherited ) ) ),
		);
	}
	private static function error( $code, $message, $snapshot = null ) {
		return new WP_Error(
			'bpfwp_hours_' . $code,
			$message,
			array(
				'status'   => 409,
				'accepted' => $snapshot,
			)
		);
	}
	/** Null deletes an absent-before exception on recovery. Other keys cannot be arbitrary. */
	public static function write( $id, $changes, $expected = null, $capability = null, $write_revision = null, $editor = false ) {
		global $wpdb;
		if ( null !== $write_revision && ( ! is_string( $write_revision ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $write_revision ) ) ) {
			return self::error( 'invalid', __( 'Invalid hours revision.', 'business-profile' ) );
		}
		if ( ! is_array( $changes ) || array_diff( array_keys( $changes ), array_merge( self::keys(), array( 'submitted_modes' ) ) ) || bpfwpLocationPublication::post_type() !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return self::error( 'authority', __( 'This location cannot be edited.', 'business-profile' ) );
		}
		$held_settings = bpfwpSettingsMutation::has_lock();
		$lock          = bpfwpSettingsMutation::acquire();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		$lock_key = 'bpfwp-location-hours-lock-' . (int) $id;
		$token    = null;
		try {
			wp_cache_delete( $lock_key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$candidate = wp_generate_uuid4() . ':' . ( time() + 120 );
			if ( ! add_option( $lock_key, $candidate, '', false ) ) {
				$old   = self::option_raw( $lock_key );
				$parts = is_string( $old ) ? explode( ':', $old ) : array();
				if ( 2 !== count( $parts ) || ! ctype_digit( $parts[1] ) || time() < (int) $parts[1]
					|| 1 !== $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $candidate, $lock_key, $old ) ) ) {
					return self::error( 'busy', __( 'This location is being saved. Retry after reloading.', 'business-profile' ) );
				}
			}
			$token  = $candidate;
			$before = self::snapshot( $id );
			if ( $before['duplicate'] || ( null !== $expected && $expected !== $before[ $editor ? 'editor_fingerprint' : 'fingerprint' ] ) ) {
				return self::error( 'conflict', __( 'The location or inherited hours changed. Create a new preview.', 'business-profile' ), $before );
			}
			if ( isset( $changes['submitted_modes'] ) ) {
				$modes = is_array( $before['values']['bpfwp-field-modes'] ) ? $before['values']['bpfwp-field-modes'] : array();
				if ( ! is_array( $changes['submitted_modes'] ) ) {
					return self::error( 'invalid', __( 'Invalid field modes.', 'business-profile' ) );
				}
				foreach ( bpfwpBusinessData::mode_fields() as $field ) {
					$mode = $changes['submitted_modes'][ $field ] ?? null;
					if ( 'legacy' === $mode ) {
						unset( $modes[ $field ] );
					} elseif ( in_array( $mode, array( 'inherit', 'override', 'empty' ), true ) ) {
						$modes[ $field ] = $mode;
					}
				}
				$changes['bpfwp-field-modes'] = $modes;
				unset( $changes['submitted_modes'] );
			}
			$validation = bpfwpSettingsMutation::validate_schedule(
				array(
					'opening-hours' => $changes['opening_hours'] ?? $before['values']['opening_hours'],
					'exceptions'    => $changes['exceptions'] ?? $before['values']['exceptions'],
				)
			);
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
			$parts  = explode( ':', $token );
			$fence  = $wpdb->prepare( "(SELECT lease FROM (SELECT option_value AS lease FROM {$wpdb->options} WHERE option_name = %s LIMIT 1) AS bpfwp_location_lease) = %s AND UNIX_TIMESTAMP() < %d", $lock_key, $token, (int) $parts[1] );
			$fence .= ' AND ' . bpfwpSettingsMutation::fence();
			if ( null !== $capability ) {
				$fence .= ' AND ' . bpfwpPremiumCapabilities::paid_fence( $capability );
			}
			$changed = false;
			foreach ( $changes as $key => $value ) {
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return self::error( 'authority', __( 'Location edit authority changed. Reload before retrying.', 'business-profile' ), self::snapshot( $id ) );
				}
				if ( null !== $capability && ! bpfwpPremiumCapabilities::allowed( $capability ) ) {
					return self::error( 'paused', __( 'Premium access changed. Uncommitted locations were retained.', 'business-profile' ), self::snapshot( $id ) );
				}
				$row        = $before['raw'][ $key ] ?? null;
				$serialized = (string) maybe_serialize( $value );
				if ( ( null === $value && null === $row ) || ( null !== $row && null !== $value && $serialized === $row['meta_value'] ) ) {
					continue;
				}
				if ( null === $value ) {
					$sql = $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_id = %d AND meta_value = %s", $row['meta_id'], $row['meta_value'] );
				} elseif ( null !== $row ) {
					$sql = $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d AND meta_value = %s", $serialized, $row['meta_id'], $row['meta_value'] );
				} else {
					$sql = $wpdb->prepare( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT %d, %s, %s WHERE NOT EXISTS (SELECT found FROM (SELECT meta_id AS found FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1) AS bpfwp_meta_exists)", $id, $key, $serialized, $id, $key );
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared statements plus prepared lease predicates.
				$count = $wpdb->query( $sql . ' AND ' . $fence );
				wp_cache_delete( $id, 'post_meta' );
				if ( 1 !== $count ) {
					return self::error( 'partial', __( 'A location save could not be verified. Reload before retrying; accepted values were retained.', 'business-profile' ), self::snapshot( $id ) );
				}
				$changed = true;
			}
			if ( $changed ) {
				$key      = self::revision_key( $id );
				$revision = null === $write_revision ? wp_generate_uuid4() : $write_revision;
				$sql      = null === $before['revision'] ? $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) SELECT %s,%s,'no' WHERE NOT EXISTS (SELECT found FROM (SELECT option_id AS found FROM {$wpdb->options} WHERE option_name = %s LIMIT 1) AS bpfwp_revision_exists)", $key, $revision, $key )
					: $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $revision, $key, $before['revision'] );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared statement and prepared lease predicates above.
				if ( 1 !== $wpdb->query( $sql . ' AND ' . $fence ) ) {
					return new WP_Error(
						'bpfwp_hours_partial',
						__( 'Hours were saved, but their revision needs review. Reload before retrying.', 'business-profile' ),
						array(
							'status'         => 409,
							'accepted'       => self::snapshot( $id ),
							'written'        => true,
							'write_revision' => $write_revision,
						)
					);
				}
				wp_cache_delete( $key, 'options' );
			}
			$after = self::snapshot( $id );
			foreach ( $changes as $key => $value ) {
				if ( null === $value ? isset( $after['raw'][ $key ] ) : (string) maybe_serialize( $value ) !== ( $after['raw'][ $key ]['meta_value'] ?? null ) ) {
					return self::error( 'partial', __( 'The accepted hours changed during saving. Reload before retrying.', 'business-profile' ), $after );
				}
			}
			do_action( 'bpfwp_location_hours_saved', $id, $after['revision'] );
			return array(
				'changed' => $changed,
				'before'  => $before,
				'after'   => $after,
			);
		} finally {
			if ( null !== $token ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock_key, $token ) );
				wp_cache_delete( $lock_key, 'options' );
			}
			if ( ! $held_settings ) {
				bpfwpSettingsMutation::release();
			}
		}
	}
}
