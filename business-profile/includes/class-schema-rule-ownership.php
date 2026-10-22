<?php
/** Provenance is attached to a rule ID, never inferred from Article/Product type. */
defined( 'ABSPATH' ) || exit;

class bpfwpSchemaRuleOwnership {
	const META = 'bpfwp-rule-origin';
	public static function post_type() {
		global $bpfwp_controller;
		return isset( $bpfwp_controller->cpts->schema_cpt_slug ) ? $bpfwp_controller->cpts->schema_cpt_slug : 'schema';
	}
	public static function generators() {
		return array(
			'automatic_article'     => array(
				'slug'       => 'business-profile-article-schema',
				'title'      => 'Business Profile Article Schema',
				'setting'    => 'article-rich-snippets',
				'capability' => 'article_schema',
			),
			'automatic_woocommerce' => array(
				'slug'       => 'business-profile-woocommerce-schema',
				'title'      => 'Business Profile WooCommerce Schema',
				'setting'    => 'woocommerce-integration',
				'capability' => 'woocommerce_schema',
			),
		);
	}
	public static function definition( $origin ) {
		$article = 'automatic_article' === $origin;
		return array(
			'schema_target_type'  => 'post_type',
			'schema_target_value' => $article ? 'post' : 'product',
			'schema_type'         => $article ? 'Article' : 'Product',
			'default_display'     => 'on',
			'field_defaults'      => $article ? array(
				'_author_name'           => 'function display_name get_the_author_meta',
				'_datePublished'         => 'function get_the_date',
				'_dateModified'          => 'function get_the_modified_date',
				'_headline'              => 'function get_the_title',
				'_image'                 => 'function bpfwp_get_post_image_url',
				'_description'           => 'function get_the_excerpt',
				'_publisher_name'        => 'option blogname',
				'_publisher_logo_height' => 'function bpfwp_get_site_logo_height',
				'_publisher_logo_width'  => 'function bpfwp_get_site_logo_width',
				'_publisher_logo_url'    => 'function bpfwp_get_site_logo_url',
			) : array(
				'_image'                           => 'function bpfwp_get_post_image_url',
				'_description'                     => 'function get_the_excerpt',
				'_sku'                             => 'meta _sku',
				'_review_reviewRating_ratingValue' => 'function bpfwp_wc_get_most_recent_review_rating',
				'_review_reviewBody'               => 'function bpfwp_wc_get_most_recent_review_body',
				'_review_author_name'              => 'function bpfwp_wc_get_most_recent_review_author',
				'_aggregateRating_ratingValue'     => 'meta _wc_average_rating',
				'_aggregateRating_reviewCount'     => 'meta _wc_review_count',
				'_offers_priceCurrency'            => 'option woocommerce_currency',
				'_offers_price'                    => 'meta _price',
				'_offers_pricevalidUntil'          => 'meta _sale_price_dates_to',
				'_offers_availability'             => 'meta _stock_status',
			),
		);
	}
	public static function candidate( $id ) {
		$post = get_post( $id );
		if ( ! $post || self::post_type() !== $post->post_type ) {
			return null;
		}
		foreach ( self::generators() as $origin => $generator ) {
			if ( $post->post_name === $generator['slug'] ) {
				return array(
					'origin' => $origin,
					'exact'  => $post->post_title === $generator['title'] && get_post_meta( $id, 'bpfwp-schema-data', true ) === self::definition( $origin ),
				);
			}
		}
		return null;
	}
	public static function stamp( $id, $origin, $source = 'created' ) {
		if ( ! isset( self::generators()[ $origin ] ) && 'manual' !== $origin ) {
			return false;
		}
		$definition = get_post_meta( $id, 'bpfwp-schema-data', true );
		add_post_meta(
			$id,
			self::META,
			array(
				'version'              => 1,
				'origin'               => $origin,
				'creator_version'      => BPFWP_VERSION,
				'rule_id'              => (int) $id,
				'definition_signature' => hash( 'sha256', serialize( $definition ) ),
				'source'               => $source,
				'actor'                => get_current_user_id(),
				'classified_at'        => time(),
				'evidence'             => array(
					'candidate'  => self::candidate( $id ),
					'definition' => $definition,
					'settings'   => array_intersect_key( bpfwpSettingsMutation::current(), array_flip( array( 'article-rich-snippets', 'woocommerce-integration' ) ) ),
				),
			),
			true
		);
		$stored = get_post_meta( $id, self::META, true );
		return is_array( $stored ) && $stored['origin'] === $origin;
	}
	public static function allowed( $id ) {
		$record = get_post_meta( $id, self::META, true );
		if ( ! $record ) {
			// An unresolved historical identity is a release exception, never silently owned.
			return true;
		}
		if ( ! is_array( $record ) || 1 !== ( $record['version'] ?? null ) || ( $record['rule_id'] ?? null ) !== (int) $id ) {
			return false;
		}
		if ( 'manual' === ( $record['origin'] ?? null ) ) {
			return true;
		}
		$generator = self::generators()[ $record['origin'] ?? '' ] ?? null;
		if ( ! $generator ) {
			return false;
		}
		$settings = bpfwpSettingsMutation::current();
		return ! empty( $settings[ $generator['setting'] ] ) && bpfwpPremiumCapabilities::allowed( $generator['capability'] );
	}
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 25 );
		add_action( 'admin_post_bpfwp_classify_schema_rule', array( __CLASS__, 'classify' ) );
	}
	public static function menu() {
		add_submenu_page( 'bpfwp-business-profile', __( 'Automatic Schema Recovery', 'business-profile' ), __( 'Automatic Schema Recovery', 'business-profile' ), 'manage_options', 'bpfwp-schema-ownership', array( __CLASS__, 'screen' ) );
	}
	public static function classify() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'business-profile' ), '', array( 'response' => 403 ) );
		}
		$id = absint( $_POST['rule_id'] ?? 0 );
		check_admin_referer( 'bpfwp_classify_schema_rule_' . $id );
		$candidate = self::candidate( $id );
		$origin    = is_string( $_POST['origin'] ?? null ) ? wp_unslash( $_POST['origin'] ) : '';
		if ( ! $candidate || ! current_user_can( 'edit_post', $id ) || ! in_array( $origin, array( 'manual', $candidate['origin'] ), true ) || get_post_meta( $id, self::META, true ) ) {
			wp_die( esc_html__( 'This rule cannot be classified by this request.', 'business-profile' ), '', array( 'response' => 409 ) );
		}
		if ( ! self::stamp( $id, $origin, 'administrator_recovery' ) ) {
			wp_die( esc_html__( 'The confirmation could not be saved. Review the rule and try again.', 'business-profile' ), '', array( 'response' => 409 ) );
		}
		set_transient( 'bpfwp-schema-review-' . get_current_user_id(), $id, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=bpfwp-schema-ownership' ) );
		exit;
	}
	public static function screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page  = max( 1, absint( $_GET['rules_page'] ?? 1 ) );
		$query = new WP_Query(
			array(
				'post_type'      => self::post_type(),
				'post_status'    => array( 'publish', 'draft', 'disabled', 'private', 'pending', 'future' ),
				'posts_per_page' => 20,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		echo '<div class="wrap bpfwp-recovery-screen"><h1>' . esc_html__( 'Automatic Schema Recovery', 'business-profile' ) . '</h1><p>' . esc_html__( 'Schema is structured information that helps search engines understand your content. Use this screen if you upgraded from an earlier version and need to confirm how a saved schema rule was created.', 'business-profile' ) . '</p><p>' . esc_html__( 'Choose Manual for a rule you set up and manage yourself. Choose Automatic for a rule created and managed by the plugin’s Article or WooCommerce integration. Automatic rules run only while their integration setting is enabled and Premium access is active.', 'business-profile' ) . '</p><p>' . esc_html__( 'Confirmation keeps the rule’s saved settings and any changes made for individual posts or pages. It does not check the rule’s output or guarantee search results. If you are unsure, open the rule to inspect its settings or ask for assistance before choosing.', 'business-profile' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=bpfwp-settings&tab=bpfwp-premium-tab' ) ) . '">' . esc_html__( 'Review automatic Article and WooCommerce settings', 'business-profile' ) . '</a></p>';
		$confirmed = get_transient( 'bpfwp-schema-review-' . get_current_user_id() );
		if ( is_numeric( $confirmed ) && get_post_meta( (int) $confirmed, self::META, true ) && current_user_can( 'edit_post', (int) $confirmed ) ) {
			delete_transient( 'bpfwp-schema-review-' . get_current_user_id() );
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Your confirmation was saved. The rule’s existing settings and content overrides were kept.', 'business-profile' ) . '</p></div>';
		}
		$labels = array(
			'manual'                => __( 'Manual — managed by you', 'business-profile' ),
			'automatic_article'     => __( 'Automatic — Article integration', 'business-profile' ),
			'automatic_woocommerce' => __( 'Automatic — WooCommerce integration', 'business-profile' ),
		);
		echo '<p>' . esc_html__( 'For automatic Article rules, enable Post Rich Snippets in Premium settings. For automatic product rules, enable WooCommerce Integration there.', 'business-profile' ) . '</p>';
		$pending = 0;
		foreach ( $query->posts as $post ) {
			$candidate = self::candidate( $post->ID );
			if ( ! $candidate ) {
				continue;
			}
			$record = get_post_meta( $post->ID, self::META, true );
			echo '<section class="bpfwp-recovery-rule"><h2><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( $post->post_title ) . ' (#' . esc_html( $post->ID ) . ')</a></h2><p>' . esc_html( $record ? ( $labels[ $record['origin'] ?? '' ] ?? __( 'Saved classification needs review. Open the rule to inspect it.', 'business-profile' ) ) : __( 'Needs confirmation: review how this rule was created before choosing below.', 'business-profile' ) ) . '</p><details><summary>' . esc_html__( 'Saved rule details', 'business-profile' ) . '</summary><pre>' . esc_html( wp_json_encode( get_post_meta( $post->ID, 'bpfwp-schema-data', true ), JSON_PRETTY_PRINT ) ) . '</pre></details>';
			if ( ! $record ) {
				++$pending;
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bpfwp_classify_schema_rule"><input type="hidden" name="rule_id" value="' . esc_attr( $post->ID ) . '">';
				wp_nonce_field( 'bpfwp_classify_schema_rule_' . $post->ID );
				echo '<label>' . esc_html__( 'How was this rule created?', 'business-profile' ) . ' <select name="origin" required><option value="">' . esc_html__( 'Choose after reviewing', 'business-profile' ) . '</option><option value="manual">' . esc_html( $labels['manual'] ) . '</option><option value="' . esc_attr( $candidate['origin'] ) . '">' . esc_html( $labels[ $candidate['origin'] ] ) . '</option></select></label> <button class="button">' . esc_html__( 'Confirm rule origin', 'business-profile' ) . '</button></form>';
			}
			echo '</section>';
		}
		if ( 0 === $pending ) {
			echo '<p>' . esc_html__( 'No rules on this page need confirmation. You can continue using your saved settings.', 'business-profile' ) . '</p>';
		}
		echo '<nav aria-label="' . esc_attr__( 'Schema rule pages', 'business-profile' ) . '">';
		if ( $page > 1 ) {
			echo '<a href="' . esc_url( add_query_arg( 'rules_page', $page - 1 ) ) . '">' . esc_html__( 'Previous', 'business-profile' ) . '</a> ';
		}
		if ( $page < $query->max_num_pages ) {
			echo '<a href="' . esc_url( add_query_arg( 'rules_page', $page + 1 ) ) . '">' . esc_html__( 'Next', 'business-profile' ) . '</a>';
		}
		echo '</nav></div>';
	}
}
