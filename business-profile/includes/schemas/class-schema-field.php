<?php
/**
 * Create a schema field to be used for schema classes.
 *
 * @package   BusinessProfile
 * @copyright Copyright (c) 2019, Five Star Plugins
 * @license   GPL-2.0+
 * @since     2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'bpfwpSchemaField' ) ) :

	/**
	 * Schema field for Business Profile
	 *
	 * @since 2.0.0
	 */
	class bpfwpSchemaField {

		/**
		 * The type used by Schema.org
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $type = '';

		/**
		 * The name used by Schema.org
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $slug = '';

		/**
		 * The display name for this field
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $name = '';

		/**
		 * The input type for this field
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $input = '';

		/**
		 * Whether this field is should be recommended to be filled in (bolded)
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    boolean
		 */
		public $recommended = false;

		/**
		 * Whether there can be multiple instances of this field
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    boolean
		 */
		public $repeatable = false;

		/**
		 * What default value, if any, should exist for this field. 
		 * Can include 'function', 'option' or 'meta' to return, respectively,
		 * the value of a function, get_option or appropriate get_meta call
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    string
		 */
		public $callback = '';
		
		/**
		 * Child fields for this class
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    array
		 */
		public $children = array();

		/**
		 * Initialize the class and recursively initialize child classes.
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function __construct( $args ) {

			$this->set_properties( $args );
		}

		/**
		 * Load the schema's default fields
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function set_properties( $args ) {
			
			if ( isset($args['type']) ) { $this->type = $args['type']; }
			if ( isset($args['slug']) ) { $this->slug = $args['slug']; }
			if ( isset($args['name']) ) { $this->name = $args['name']; }
			if ( isset($args['recommended']) ) { $this->recommended = $args['recommended']; }
			if ( isset($args['repeatable']) ) { $this->repeatable = $args['repeatable']; }
			if ( isset($args['input']) ) { $this->input = $args['input']; }
			if ( isset($args['callback']) ) { $this->callback = $args['callback']; }
			if ( isset($args['children']) ) { $this->children = $args['children']; }
		}


		/**
		 * Get the default value for this field based on the object this field is called for
		 *
		 * @since  2.0.0
		 * @access public
		 * @param  int $object_id ID of the object we're fetching a default value for.
		 * @param  string $object_type description of the type of object (post, taxnomy, etc.)
		 * @return mixed $value 
		 */
		public function get_default_value( $object_id, $object_type = 'post' ) {
			require_once dirname( __DIR__ ) . '/class-schema-source-policy.php';
			return bpfwpSchemaSourcePolicy::resolve( $this->callback, $object_id, $object_type );
		}

		/**
		 * Get the functions that schema field callbacks are allowed to invoke.
		 *
		 * @return array $allowed_functions The allowed callback function names.
		 */
		protected function get_allowed_callback_functions() {
		
			global $bpfwp_controller;
		
			$allowed_functions = array(
				'get_permalink',
				'get_the_author',
				'get_the_author_meta',
				'get_the_permalink',
			);
		
			if ( isset( $bpfwp_controller->cpts ) ) {
				foreach ( $bpfwp_controller->cpts->get_helper_function_options() as $helper_function ) {
					if ( isset( $helper_function['value'] ) && is_string( $helper_function['value'] ) ) {
						$allowed_functions[] = $helper_function['value'];
					}
				}
			}
		
			$allowed_functions = apply_filters(
				'bpfwp_schema_field_allowed_functions',
				$allowed_functions
			);
		
			return array_values(
				array_unique(
					array_filter(
						(array) $allowed_functions,
						'is_string'
					)
				)
			);
		}
	}
endif;
