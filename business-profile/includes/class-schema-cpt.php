<?php
/**
 * Creates a class based on a schema custom post type's data.
 *
 * @package   BusinessProfile
 * @copyright Copyright (c) 2019, Five Star Plugins
 * @license   GPL-2.0+
 * @since     2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'bpfwpSchemaCPT' ) ) :

	/**
	 * Schema CPT-based class for Business Profile
	 *
	 * @since 2.0.0
	 */
	class bpfwpSchemaCPT {

		/**
		 * The the WP $post_id, if any, for this Schema CPT
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    int
		 */
		public $post_id = '';
		/**
		 * The targeted WP-object type
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $target_type = '';

		/**
		 * The value for the target
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $target_value = '';

		/**
		 * The schema type that should be added
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $schema_type = '';

		/**
		 * The schema class that defines the fields, etc. used in the selected schema
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    bpfwpSchema child class
		 */
		public $schema_class;

		/**
		 * The default values that should be applied to each schema field
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    array
		 */
		public $field_defaults = array();

		/**
		 * Whether this schema should be applied to all matching posts by default
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    boolean
		 */
		public $default_display = false;

		/**
		 * Initialize the class and recursively initialize child classes.
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function __construct( $args ) {

			$this->set_properties( $args );

			if ( isset($this->schema_class) ){
				add_action( 'admin_init', array( $this, 'set_admin_hooks' ) );
				add_action( 'wp_footer', array( $this, 'set_display_hooks' ), 1 );
			}
		}

		/**
		 * Load the schema's default fields
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function set_properties( $args ) {

			if ( isset( $args['post_id'] ) ) {
				$this->post_id = $args['post_id'];
			}
			if ( isset( $args['target_type'] ) ) {
				$this->target_type = $args['target_type'];
			}
			if ( isset( $args['target_value'] ) ) {
				$this->target_value = $args['target_value'];
			}
			if ( isset( $args['schema_type'] ) ) {
				$this->schema_type = $args['schema_type'];
			}
			if ( isset( $args['field_defaults'] ) ) {
				$this->field_defaults = $args['field_defaults'];
			}
			if ( isset( $args['default_display'] ) ) {
				$this->default_display = $args['default_display'];
			}

			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-source-policy.php';
			$schema_file = bpfwpSchemaSourcePolicy::schema_file( $this->schema_type );
			if ( $schema_file ) {
				include_once $schema_file;

				$class_name         = 'bpfwpSchema' . $this->schema_type;
				$this->schema_class = new $class_name( array( 'depth' => 0 ) );
			}
		}
 

		/**
		 * Set admin hooks to display and save the schema meta boxes based on the target type and value
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function set_admin_hooks() {

			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
			add_action( 'save_post', array( $this, 'save_meta' ) );
			wp_enqueue_script( 'schema-cpt', BPFWP_PLUGIN_URL . '/assets/js/schema-cpt.js', array( 'jquery' ), BPFWP_VERSION );
			wp_enqueue_script( 'bpfwp-schema-values', BPFWP_PLUGIN_URL . '/assets/js/schema-values.js', array(), BPFWP_VERSION, true );
		}

		public function set_display_hooks() {

			if ( $this->public_context() && $this->validate_target( is_singular() ? get_queried_object() : null ) ) {
				add_filter( 'bpfwp_ld_json_output', array( $this, 'output_ld_json_data' ) );
			}
		}

		private function public_context() {

			if ( is_admin() || is_feed() || is_404() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return false;
			}
			if ( is_preview() ) {
				return false;
			}
			if ( is_singular() ) {
				$post = get_queried_object();
				if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || $post->post_password || ! is_post_type_viewable( $post->post_type ) || 'internal' === get_post_meta( $post->ID, 'bpfwp-location-purpose', true ) ) {
					return false;
				}
			}
			return true;
		}

		public function add_meta_nonce() {
			wp_nonce_field( 'bpfwp_rule_' . $this->post_id, 'bpfwp_rule_nonce_' . $this->post_id );
		}

		public function form_signature() {

			return hash( 'sha256', wp_json_encode( array( $this->schema_type, $this->target_type, $this->target_value, $this->field_defaults, $this->default_display, get_post_meta( $this->post_id, 'bpfwp-rule-contract-version', true ) ) ) );
		}

		public function validate_target( $target = null ) {

			if ( 'global' === $this->target_type ) {
				return true;
			}
			if ( null === $target && is_admin() ) {
				global $post;
				$target = $post;
			}
			if ( ! $target instanceof WP_Post ) {
				return false;
			}
			if ( 'post_type' === $this->target_type ) {
				return $target->post_type === $this->target_value;
			}
			return in_array( $this->target_type, array( 'post', 'page' ), true ) && $target->post_type === $this->target_type && (int) $this->target_value === (int) $target->ID;
		}

		public function add_meta_box( $post_type, $post = null ) {

			global $bpfwp_controller;
			if ( ! $post instanceof WP_Post || $post_type === $bpfwp_controller->cpts->schema_cpt_slug || ! is_post_type_viewable( $post_type ) || ! $this->validate_target( $post ) ) {
				return;
			}
			$box = apply_filters(
				'bpfwp_schema_cpt_meta_box',
				array(
					'id'        => 'bpfwp_schema_rule_' . $this->post_id,
					/* translators: 1: Schema type, 2: Rule ID. */
					'title'     => sprintf( __( '%1$s Details — Rule #%2$d', 'business-profile' ), $this->schema_type, $this->post_id ),
					'callback'  => array( $this, 'print_schema_metabox' ),
					'post_type' => $post_type,
					'context'   => 'normal',
					'priority'  => 'default',
				)
			);
			add_meta_box( $box['id'], $box['title'], $box['callback'], $box['post_type'], $box['context'], $box['priority'] );
		}

		public function print_schema_metabox( $post ) {

			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-values.php';
				$entry = bpfwpSchemaValues::entry( $this, $post->ID );
			$name      = 'bpfwp_rules[' . $this->post_id . ']';
			$this->add_meta_nonce();
			$draft = get_transient( 'bpfwp_schema_draft_' . get_current_user_id() . '_' . $post->ID . '_' . $this->post_id );
			if ( is_array( $draft ) ) {
				echo '<details><summary>' . esc_html__( 'Recover an incomplete submission', 'business-profile' ) . '</summary><p>' . esc_html__( 'These are the submitted values the server received, retained for 24 hours. Later fields may be missing because of the server input limit. Your saved values below were preserved.', 'business-profile' ) . '</p><textarea class="large-text" rows="8" readonly>' . esc_textarea( wp_json_encode( $draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</textarea></details>';
			}
				echo '<input type="hidden" name="' . esc_attr( $name . '[present]' ) . '" value="1">';
			echo '<input type="hidden" name="' . esc_attr( $name . '[signature]' ) . '" value="' . esc_attr( $this->form_signature() ) . '">';
			echo '<p><label>' . esc_html__( 'Output on this page', 'business-profile' ) . ' <select name="' . esc_attr( $name . '[output]' ) . '">';
			foreach ( array(
				'inherit' => __( 'Follow rule', 'business-profile' ),
				'include' => __( 'Include', 'business-profile' ),
				'exclude' => __( 'Exclude', 'business-profile' ),
			) as $mode => $label ) {
				echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $entry['output'] ?? 'inherit', $mode, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label></p>';
			echo '<p>' . esc_html__( 'Historical values are retained. Deeply repeated legacy fields may need recovery because their original format did not identify each parent. Saving affects only this rule.', 'business-profile' ) . '</p>';
			$legacy   = get_post_meta( $post->ID, 'bpfwp_values_' . $this->schema_type, true );
			$recovery = bpfwpSchemaValues::recovery( $this->schema_class->fields, is_array( $legacy ) ? $legacy : array() );
			if ( $recovery ) {
				echo '<details><summary>' . esc_html__( 'Recover ambiguous historical values', 'business-profile' ) . '</summary><p>' . esc_html__( 'These values were retained but cannot be assigned to a parent automatically. Copy each value into its intended item below. This recovery copy remains available after saving.', 'business-profile' ) . '</p><dl>';
				foreach ( $recovery as $label => $values ) {
					echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( implode( ' · ', $values ) ) . '</dd>';
				}
				echo '</dl></details>';
			}
			bpfwpSchemaValues::render( $this->schema_class->fields, $entry['values'] ?? array(), $name . '[values]' );
			echo '<input type="hidden" name="' . esc_attr( $name . '[complete]' ) . '" value="' . esc_attr( $this->form_signature() ) . '">';
		}

		/** Compatibility entry point; new forms use structured paths. */
		public function display_field( $field, $post, $specified_values, $field_prefix = '', $count = 1 ) {

			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-values.php';
			$values = bpfwpSchemaValues::legacy( array( $field ), (array) $specified_values, $field_prefix, $count );
			bpfwpSchemaValues::render( array( $field ), $values, 'bpfwp_rules[' . $this->post_id . '][values]' );
			return 1;
		}

		public function save_meta( $post_id ) {
			global $bpfwp_controller;
			$nonce = 'bpfwp_rule_nonce_' . $this->post_id;
			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST[ $nonce ] ) || ! is_string( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce ] ) ), 'bpfwp_rule_' . $this->post_id ) ) {
				return $post_id;
			}
			$post = get_post( $post_id );
			if ( ! $post || $post->post_type === $bpfwp_controller->cpts->schema_cpt_slug || ! is_post_type_viewable( $post->post_type ) || ! $this->validate_target( $post ) || ! isset( $_POST['bpfwp_rules'][ $this->post_id ] ) ) {
				return $post_id;
			}
			$raw = wp_unslash( $_POST['bpfwp_rules'][ $this->post_id ] );
			if ( ! is_array( $raw ) || ! isset( $raw['complete'] ) || ! is_string( $raw['complete'] ) || ! hash_equals( $this->form_signature(), $raw['complete'] ) ) {
				if ( is_array( $raw ) && isset( $raw['values'] ) && is_array( $raw['values'] ) ) {
					set_transient( 'bpfwp_schema_draft_' . get_current_user_id() . '_' . $post_id . '_' . $this->post_id, $raw['values'], DAY_IN_SECONDS );
				}
					set_transient( 'bpfwp_schema_error_' . get_current_user_id(), __( 'The schema form was incomplete, possibly because it exceeded the server input limit. No values for this rule were changed. Keep this editor open to recover your entries, reduce repeated items or ask your host to increase max_input_vars, then retry.', 'business-profile' ), 300 );
				return $post_id;
			}
			if ( ! is_array( $raw ) || ! isset( $raw['signature'] ) || ! is_string( $raw['signature'] ) || ! hash_equals( $this->form_signature(), $raw['signature'] ) ) {
				set_transient( 'bpfwp_schema_error_' . get_current_user_id(), __( 'The schema rule changed while this page was open. Reload before editing its values.', 'business-profile' ), 60 );
				return $post_id;
			}
			if ( ! is_array( $raw ) || empty( $raw['present'] ) || ! isset( $raw['output'], $raw['values'] ) || ! in_array( $raw['output'], array( 'inherit', 'include', 'exclude' ), true ) ) {
				return $post_id;
			}
			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-values.php';
			$values = bpfwpSchemaValues::sanitize( $this->schema_class->fields, $raw['values'] );
			if ( is_wp_error( $values ) ) {
				set_transient( 'bpfwp_schema_error_' . get_current_user_id(), $values->get_error_message(), 60 );
				return $post_id;
			}
			$entries                   = get_post_meta( $post_id, bpfwpSchemaValues::META, true );
			$entries                   = is_array( $entries ) ? $entries : array();
			$entries[ $this->post_id ] = array(
				'output' => $raw['output'],
				'values' => $values,
			);
			update_post_meta( $post_id, bpfwpSchemaValues::META, wp_slash( $entries ) );
			return $post_id;
		}

		public function output_ld_json_data( $ld_json ) {

			if ( ! $this->public_context() || 'publish' !== get_post_status( $this->post_id ) ) {
				return $ld_json;
			}
			$post = is_singular() ? get_queried_object() : null;
			if ( ! $this->validate_target( $post ) ) {
				return $ld_json;
			}
			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-values.php';
			$id    = $post ? $post->ID : 0;
			$entry = $id ? bpfwpSchemaValues::entry( $this, $id ) : array();
			$state = $entry['output'] ?? 'inherit';
			if ( 'exclude' === $state || ( 'inherit' === $state && get_post_meta( $this->post_id, 'bpfwp-rule-contract-version', true ) && ! $this->default_display ) ) {
				return $ld_json;
			}
			// Content-specific catalogue types cannot describe an incidental archive-loop post.
			if ( ! $id && in_array( $this->schema_type, array( 'Article', 'NewsArticle', 'BlogPosting', 'Product', 'Review' ), true ) ) {
				return $ld_json;
			}
			$values = bpfwpSchemaValues::output( $this->schema_class->fields, $entry['values'] ?? array(), (array) $this->field_defaults, $id );
			if ( ! $values ) {
				return $ld_json;
			}
			$url       = $id ? get_permalink( $id ) : get_pagenum_link( max( 1, get_query_var( 'paged' ) ), false );
			$output    = array_merge(
				array(
					'@context' => 'https://schema.org',
					'@type'    => $this->schema_type,
					'@id'      => $url . '#bpfwp-rule-' . $this->post_id,
				),
				$values
			);
			$ld_json[] = $output;
			return $ld_json;
		}

		public function get_field_output_value( $values, $field, $count = 1, $field_prefix = '' ) {

			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-values.php';
				$post = is_singular() ? get_queried_object() : null;
				$tree = bpfwpSchemaValues::legacy( array( $field ), (array) $values, $field_prefix, $count );
			$output   = bpfwpSchemaValues::output( array( $field ), $tree, (array) $this->field_defaults, $post ? $post->ID : 0, $field_prefix );
			return $output[ $field->slug ] ?? '';
		}
	}
endif;
