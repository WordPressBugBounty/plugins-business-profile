<?php
/** Definition-bound, rule-local schema values. No saved value selects executable code. */
defined( 'ABSPATH' ) || exit;

class bpfwpSchemaValues {
	const META     = 'bpfwp-rule-overrides-v1';
	const MAX_ROWS = 50;
	/** Give editors the retained values whose historical paths cannot identify a repeated parent. */
	public static function recovery( $fields, $raw, $prefix = '', $label = '', $repeated = false, $ambiguous = false ) {
		$items = array();
		foreach ( $fields as $field ) {
			$key  = $prefix . '_' . $field->slug;
			$name = $label ? $label . ' / ' . $field->name : $field->name;
			if ( 'SchemaField' === $field->input ) {
				$items = array_merge( $items, self::recovery( $field->children, $raw, $key, $name, $repeated || $field->repeatable, $ambiguous || ( $repeated && $field->repeatable ) ) );
			} elseif ( $ambiguous && isset( $raw[ $key ] ) ) {
				$values = array_filter( (array) $raw[ $key ], 'is_scalar' );
				if ( $values ) {
					$items[ $name ] = array_slice( $values, 0, self::MAX_ROWS );
				}
			}
		}
		return $items;
	}

	public static function entry( $rule, $post_id ) {
		$entries = get_post_meta( $post_id, self::META, true );
		if ( is_array( $entries ) && isset( $entries[ $rule->post_id ] ) && is_array( $entries[ $rule->post_id ] ) ) {
			return $entries[ $rule->post_id ];
		}
		$legacy = get_post_meta( $post_id, 'bpfwp_values_' . $rule->schema_type, true );
		return array(
			'output' => 'inherit',
			'values' => self::legacy( $rule->schema_class->fields, is_array( $legacy ) ? $legacy : array() ),
		);
	}

	/** Flat historical paths are recovered only when they identify a single parent. */
	public static function legacy( $fields, $raw, $prefix = '', $index = 1, $repeated = false ) {
		$values = array();
		foreach ( $fields as $field ) {
			$key = $prefix . '_' . $field->slug;
			if ( 'SchemaField' === $field->input ) {
				if ( $field->repeatable && $repeated ) {
					continue;
				}
				$count = 1;
				if ( $field->repeatable ) {
					foreach ( $raw as $path => $rows ) {
						if ( 0 === strpos( $path, $key . '_' ) && is_array( $rows ) ) {
							$count = max( $count, min( self::MAX_ROWS, count( $rows ) ) );
						}
					}
				}
				$children = array();
				for ( $i = 1; $i <= $count; $i++ ) {
					$children[] = self::legacy( $field->children, $raw, $key, $field->repeatable ? $i : $index, $repeated || $field->repeatable );
				}
				$values[ $field->slug ] = $field->repeatable ? $children : $children[0];
			} elseif ( isset( $raw[ $key ] ) ) {
				$value = is_array( $raw[ $key ] ) ? ( $raw[ $key ][ $index ] ?? null ) : $raw[ $key ];
				// Legacy blanks meant default. New entries represent explicit blanks separately.
				if ( is_scalar( $value ) && '' !== $value ) {
					$values[ $field->slug ] = array(
						'mode'  => 'value',
						'value' => $value,
					);
				}
			}
		}
		return $values;
	}

	public static function sanitize( $fields, $raw, $depth = 0 ) {
		if ( ! is_array( $raw ) || $depth > 12 ) {
			return new WP_Error( 'bpfwp_schema_shape', __( 'Invalid schema field structure.', 'business-profile' ) );
		}
		$result = array();
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field->slug, $raw ) ) {
				continue;
			}
			$value = $raw[ $field->slug ];
			if ( 'SchemaField' === $field->input ) {
				if ( ! is_array( $value ) || ( $field->repeatable && count( $value ) > self::MAX_ROWS ) ) {
					return new WP_Error( 'bpfwp_schema_rows', __( 'Invalid schema rows or too many repeated items.', 'business-profile' ) );
				}
				$rows  = $field->repeatable ? array_values( $value ) : array( $value );
				$clean = array();
				foreach ( $rows as $row ) {
					$child = self::sanitize( $field->children, $row, $depth + 1 );
					if ( is_wp_error( $child ) ) {
						return $child;
					}
					$clean[] = $child;
				}
				$result[ $field->slug ] = $field->repeatable ? $clean : $clean[0];
			} else {
				if ( ! is_array( $value ) || ! isset( $value['mode'] ) || ! in_array( $value['mode'], array( 'inherit', 'value', 'empty' ), true ) ) {
					return new WP_Error( 'bpfwp_schema_mode', __( 'Invalid schema field choice.', 'business-profile' ) );
				}
				if ( 'inherit' === $value['mode'] ) {
					continue;
				}
				if ( 'empty' === $value['mode'] ) {
					$result[ $field->slug ] = array( 'mode' => 'empty' );
					continue;
				}
				if ( ! isset( $value['value'] ) || ! is_scalar( $value['value'] ) ) {
					return new WP_Error( 'bpfwp_schema_value', __( 'Invalid schema field value.', 'business-profile' ) );
				}
				// Literal schema text is data, not HTML. Preserve it and escape at each output boundary.
				$clean = str_replace( "\0", '', wp_check_invalid_utf8( (string) $value['value'] ) );
				if ( 'number' === $field->input || in_array( $field->type, array( 'Number', 'Integer', 'Float' ), true ) ) {
					if ( ! is_numeric( $clean ) ) {
						return new WP_Error( 'bpfwp_schema_number', __( 'Enter a valid schema number.', 'business-profile' ) );
					}
					$clean = 'Integer' === $field->type ? (int) $clean : 0 + $clean;
				}
				$result[ $field->slug ] = array(
					'mode'  => 'value',
					'value' => $clean,
				);
			}
		}
		return $result;
	}

	public static function output( $fields, $values, $defaults, $post_id, $prefix = '' ) {
		$result = array();
		foreach ( $fields as $field ) {
			$path  = $prefix . '_' . $field->slug;
			$entry = isset( $values[ $field->slug ] ) && is_array( $values[ $field->slug ] ) ? $values[ $field->slug ] : array();
			if ( 'SchemaField' === $field->input ) {
				$rows  = $field->repeatable ? ( $entry ? $entry : array( array() ) ) : array( $entry );
				$value = array();
				foreach ( array_slice( $rows, 0, self::MAX_ROWS ) as $row ) {
					$child = self::output( $field->children, is_array( $row ) ? $row : array(), $defaults, $post_id, $path );
					if ( ! $child ) {
						continue;
					}
					$type = array(
						'Publisher' => 'Organization',
						'Logo'      => 'ImageObject',
					);
					if ( $field->type ) {
						$child = array_merge( array( '@type' => $type[ $field->type ] ?? $field->type ), $child );
					}
					$value[] = $child;
				}
				if ( ! $value ) {
					continue;
				}
				if ( ! $field->repeatable ) {
					$value = $value[0];
				}
			} else {
				if ( isset( $entry['mode'] ) && 'empty' === $entry['mode'] ) {
					continue;
				}
				if ( isset( $entry['mode'] ) && 'value' === $entry['mode'] && array_key_exists( 'value', $entry ) ) {
					$value = $entry['value'];
				} else {
					$source = isset( $defaults[ $path ] ) && '' !== $defaults[ $path ] ? $defaults[ $path ] : $field->callback;
					$value  = bpfwpSchemaSourcePolicy::resolve( $source, $post_id, $post_id ? 'post' : 'site' );
				}
				if ( null === $value || false === $value || '' === $value || ! is_scalar( $value ) ) {
					continue;
				}
			}
			$result[ 'pricevalidUntil' === $field->slug ? 'priceValidUntil' : $field->slug ] = $value;
		}
		return $result;
	}

	public static function render( $fields, $values, $name, $depth = 0 ) {
		if ( $depth > 12 ) {
			return;
		}
		foreach ( $fields as $field ) {
			$path  = $name . '[' . $field->slug . ']';
			$entry = isset( $values[ $field->slug ] ) && is_array( $values[ $field->slug ] ) ? $values[ $field->slug ] : array();
			if ( 'SchemaField' === $field->input ) {
				echo '<fieldset class="bpfwp-schema-group"><legend>' . esc_html( $field->name ) . '</legend>';
				if ( $field->repeatable ) {
					echo '<div class="bpfwp-schema-rows" data-path="' . esc_attr( $path ) . '">';
					foreach ( ( $entry ? $entry : array( array() ) ) as $i => $row ) {
						echo '<div class="bpfwp-schema-row">';
						self::render( $field->children, $row, $path . '[' . $i . ']', $depth + 1 );
						echo '<button type="button" class="button bpfwp-schema-remove">' . esc_html__( 'Remove item', 'business-profile' ) . '</button></div>';
					}
					echo '</div><button type="button" class="button bpfwp-schema-add">' . esc_html__( 'Add item', 'business-profile' ) . '</button>';
				} else {
					self::render( $field->children, $entry, $path, $depth + 1 );
				}
				echo '</fieldset>';
			} else {
				$id = 'bpfwp-' . md5( $path );
				/* translators: %s: Schema field label. */
				echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $field->name ) . '</label> <select aria-label="' . esc_attr( sprintf( __( '%s value source', 'business-profile' ), $field->name ) ) . '" name="' . esc_attr( $path . '[mode]' ) . '">';
				foreach ( array(
					'inherit' => __( 'Use default', 'business-profile' ),
					'value'   => __( 'Use this value', 'business-profile' ),
					'empty'   => __( 'Leave empty', 'business-profile' ),
				) as $mode => $label ) {
					echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $entry['mode'] ?? 'inherit', $mode, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select> ';
				$value = $entry['value'] ?? '';
				if ( 'textarea' === $field->input ) {
					echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $path . '[value]' ) . '">' . esc_textarea( $value ) . '</textarea>';
				} else {
					echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $path . '[value]' ) . '" value="' . esc_attr( $value ) . '">';
				}
					echo '</p>';
			}
		}
	}
}
