<?php
/** Bounded public object search for editors; no location-count truncation. */
defined( 'ABSPATH' ) || exit;
class bpfwpAdminSelector {
	public static function init() {
		add_action( 'wp_ajax_bpfwp_public_objects', array( __CLASS__, 'search' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'init', array( __CLASS__, 'register' ) );
	}
	public static function register() {
		wp_register_script( 'bpfwp-public-selector', BPFWP_PLUGIN_URL . '/assets/js/public-selector.js', array( 'jquery', 'wp-element', 'wp-components', 'wp-i18n' ), BPFWP_VERSION, true );
		wp_localize_script(
			'bpfwp-public-selector',
			'bpfwp_selector',
			array(
				'nonce' => wp_create_nonce( 'bpfwp-public-selector' ),
				'url'   => admin_url( 'admin-ajax.php' ),
			)
		);
	}
	public static function assets( $hook ) {
		if ( ! current_user_can( 'edit_posts' ) || ! in_array( $hook, array( 'post.php', 'post-new.php', 'widgets.php', 'site-editor.php', 'dashboard_page_bpfwp-getting-started' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'bpfwp-public-selector' );
	}
	public static function search() {
		if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'bpfwp-public-selector', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'business-profile' ) ), 403 );
		}
		$type = isset( $_GET['kind'] ) && is_string( $_GET['kind'] ) ? sanitize_key( $_GET['kind'] ) : '';
		if ( ! in_array( $type, array( 'post', 'page', 'location' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported selection.', 'business-profile' ) ), 400 );
		}
		$args = array(
			'post_type'      => 'location' === $type ? bpfwpLocationPublication::post_type() : $type,
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 50,
			'paged'          => max( 1, min( 100000, absint( $_GET['page'] ?? 1 ) ) ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => bpfwpLocationPublication::public_meta_query(),
		);
		if ( ! empty( $_GET['id'] ) ) {
			$args['p'] = absint( $_GET['id'] );
		}
		if ( isset( $_GET['search'] ) && is_string( $_GET['search'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['search'] ) );
		}
		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'value' => $post->ID,
				/* translators: %d: Post ID. */
				'label' => $post->post_title ? $post->post_title : sprintf( __( 'Untitled #%d', 'business-profile' ), $post->ID ),
			);
		}
		wp_send_json_success(
			array(
				'items' => $items,
				'more'  => $args['paged'] < $query->max_num_pages,
			)
		);
	}
}
