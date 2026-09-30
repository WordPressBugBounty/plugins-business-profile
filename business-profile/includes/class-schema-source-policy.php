<?php
/** Public schema sources. Stored expressions never confer execution authority. */
defined( 'ABSPATH' ) || exit;

class bpfwpSchemaSourcePolicy {
	/** Descriptors are registered by trusted PHP, not by UI choice filters. */
	public static function registry() {
		$registry = array();
		foreach ( array( 'blogname', 'blogdescription', 'siteurl', 'home' ) as $option ) {
			$registry[ 'option ' . $option ] = self::descriptor( 'site', 'option', $option );
		}
		foreach ( array( 'get_the_title', 'get_the_excerpt', 'get_the_content', 'get_permalink', 'get_the_permalink', 'get_the_date', 'get_the_modified_date', 'get_post_datetime', 'get_the_author', 'bpfwp_get_post_image_url' ) as $function ) {
			$registry[ 'function ' . $function ] = self::descriptor( 'post', 'content', $function );
		}
		$registry['function display_name get_the_author_meta'] = self::descriptor( 'post', 'author', 'display_name' );
		$registry['function url get_the_author_meta']          = self::descriptor( 'post', 'author', 'profile_url' );
		foreach ( array( 'bpfwp_get_site_logo_url', 'bpfwp_get_site_logo_width', 'bpfwp_get_site_logo_height' ) as $function ) {
			$registry[ 'function ' . $function ] = self::descriptor( 'site', 'logo', $function );
		}
		foreach ( array( '_sku', '_price', '_wc_average_rating', '_wc_review_count', '_sale_price_dates_to', '_stock_status' ) as $key ) {
			$registry[ 'meta ' . $key ] = self::descriptor( 'product', 'product', $key );
		}
		$registry['option woocommerce_currency'] = self::descriptor( 'product', 'currency', '' );
		foreach ( array( 'rating', 'body', 'author' ) as $part ) {
			$registry[ 'function bpfwp_wc_get_most_recent_review_' . $part ] = self::descriptor( 'product', 'review', $part );
		}
		return apply_filters( 'bpfwp_schema_source_registry', $registry );
	}

	private static function descriptor( $context, $kind, $source ) {
		return array(
			'context' => $context,
			'kind'    => $kind,
			'source'  => $source,
			'type'    => 'scalar',
		);
	}

	/** Unknown executable expressions fail closed; ordinary literal text is unchanged. */
	public static function is_expression( $value ) {
		return is_string( $value ) && preg_match( '/^(?:function|option|meta)(?:\s|$)/', ltrim( $value ) );
	}

	public static function is_registered( $value ) {
		$registry = self::registry();
		return is_string( $value ) && isset( $registry[ $value ] );
	}

	/** Only catalogue members may select a bundled PHP definition. */
	public static function schema_file( $type ) {
		global $bpfwp_controller;
		if ( ! is_string( $type ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9]*$/D', $type ) || ! isset( $bpfwp_controller->schemas ) ) {
			return false;
		}
		$types = array_merge( $bpfwp_controller->schemas->get_schema_organization_types(), $bpfwp_controller->schemas->get_schema_rich_results_types() );
		if ( ! array_key_exists( $type, $types ) ) {
			return false;
		}
		$file = BPFWP_PLUGIN_DIR . '/includes/schemas/class-schema-' . strtolower( $type ) . '.php';
		return is_file( $file ) ? $file : false;
	}

	/** Inspect expressions without resolving them or exposing their operands. */
	public static function rejected_fields( $defaults ) {
		$rejected = array();
		foreach ( (array) $defaults as $field => $expression ) {
			if ( self::is_expression( $expression ) && ! self::is_registered( $expression ) ) {
				$rejected[] = $field;
			}
		}
		return $rejected;
	}

	public static function resolve( $expression, $object_id, $object_type = 'post' ) {
		if ( ! self::is_expression( $expression ) ) {
			return is_scalar( $expression ) || null === $expression ? $expression : false;
		}
		$registry = self::registry();
		if ( ! isset( $registry[ $expression ] ) || ! is_array( $registry[ $expression ] ) ) {
			return false;
		}
		$descriptor = $registry[ $expression ];
		if ( ! isset( $descriptor['context'], $descriptor['type'] ) || ! in_array( $descriptor['context'], array( 'site', 'post', 'product' ), true ) || ! in_array( $descriptor['type'], array( 'scalar', 'text', 'number', 'url' ), true ) ) {
			return false;
		}
		$post = null;
		if ( 'site' !== $descriptor['context'] ) {
			$post = 'post' === $object_type ? get_post( $object_id ) : null;
			if ( ! $post || 'publish' !== $post->post_status || ! empty( $post->post_password ) || ! is_post_type_viewable( $post->post_type ) ) {
				return false;
			}
			if ( 'internal' === get_post_meta( $post->ID, 'bpfwp-location-purpose', true ) ) {
				return false;
			}
			if ( 'product' === $descriptor['context'] && ( 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) ) {
				return false;
			}
		}
		if ( isset( $descriptor['resolve'] ) && is_callable( $descriptor['resolve'] ) ) {
			// Exact registered expressions have no user-selected callable or argument list.
			$value = call_user_func( $descriptor['resolve'], $post ? $post->ID : 0 );
		} elseif ( isset( $descriptor['kind'], $descriptor['source'] ) ) {
			$value = self::resolve_builtin( $descriptor, $post );
		} else {
			return false;
		}
		if ( ! is_scalar( $value ) && null !== $value ) {
			return false;
		}
		switch ( $descriptor['type'] ) {
			case 'scalar':
				return $value;
			case 'text':
				return is_string( $value ) ? $value : false;
			case 'number':
				return is_int( $value ) || is_float( $value ) ? $value : false;
			case 'url':
				return is_string( $value ) && preg_match( '#^https?://#i', $value ) ? esc_url_raw( $value ) : false;
			default:
				return false;
		}
	}

	private static function resolve_builtin( $descriptor, $post ) {
		$source = $descriptor['source'];
		switch ( $descriptor['kind'] ) {
			case 'option':
				return get_option( $source );
			case 'author':
				return 'profile_url' === $source ? get_author_posts_url( $post->post_author ) : get_the_author_meta( 'display_name', $post->post_author );
			case 'logo':
				return function_exists( $source ) ? call_user_func( $source ) : false;
			case 'currency':
				return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : false;
			case 'content':
				switch ( $source ) {
					case 'get_the_title':
						return get_the_title( $post->ID );
					case 'get_the_excerpt':
						return get_the_excerpt( $post );
					case 'get_the_content':
						return get_the_content( null, false, $post );
					case 'get_permalink':
					case 'get_the_permalink':
						return get_permalink( $post->ID );
					case 'get_the_date':
					case 'get_post_datetime':
						return get_the_date( 'c', $post->ID );
					case 'get_the_modified_date':
						return get_the_modified_date( 'c', $post->ID );
					case 'get_the_author':
						return get_the_author_meta( 'display_name', $post->post_author );
					case 'bpfwp_get_post_image_url':
						return get_the_post_thumbnail_url( $post->ID, 'single-post-thumbnail' );
				}
				return false;
			case 'product':
				$product = wc_get_product( $post->ID );
				if ( ! $product ) {
					return false;
				}
				if ( in_array( $source, array( '_wc_average_rating', '_wc_review_count' ), true ) && method_exists( $product, 'get_rating_count' ) && ! $product->get_rating_count() ) {
					return false;
				}
				$methods = array(
					'_sku'               => 'get_sku',
					'_price'             => 'get_price',
					'_wc_average_rating' => 'get_average_rating',
					'_wc_review_count'   => 'get_review_count',
				);
				if ( isset( $methods[ $source ] ) ) {
					return call_user_func( array( $product, $methods[ $source ] ) );
				}
				if ( '_sale_price_dates_to' === $source ) {
					$date = $product->get_date_on_sale_to();
					return $date ? $date->date( 'c' ) : false;
				}
				if ( '_stock_status' === $source ) {
					$status   = $product->get_stock_status();
					$statuses = array(
						'instock'     => 'InStock',
						'outofstock'  => 'OutOfStock',
						'onbackorder' => 'BackOrder',
					);
					return isset( $statuses[ $status ] ) ? 'https://schema.org/' . $statuses[ $status ] : false;
				}
				return false;
			case 'review':
				$reviews = get_comments(
					array(
						'post_id' => $post->ID,
						'status'  => 'approve',
						'type'    => 'review',
						'number'  => 1,
						'orderby' => 'comment_ID',
						'order'   => 'DESC',
					)
				);
				if ( ! $reviews ) {
					return false;
				}
				$review = reset( $reviews );
				if ( 'rating' === $source ) {
					return get_comment_meta( $review->comment_ID, 'rating', true );
				}
				return 'body' === $source ? $review->comment_content : $review->comment_author;
		}
		return false;
	}
}
