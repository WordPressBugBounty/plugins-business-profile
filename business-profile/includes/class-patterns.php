<?php
/**
 * Methods for handling block patterns
 *
 * @package   BusinessProfile
 * @copyright Copyright (c) 2022, Five Star Plugins
 * @license   GPL-2.0+
 * @since     2.2.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'bpfwpPatterns', false ) ) :

	/**
	 * Class to create, edit and display blocks for the Gutenberg editor
	 *
	 * @since 2.2.3
	 */
	class bpfwpPatterns {

		/**
		 * Add hooks
		 * @since 2.2.3
		 */
		public function __construct() {

			add_action( 'init', array( $this, 'bpfwp_add_pattern_category' ) );
			add_action( 'init', array( $this, 'bpfwp_add_patterns' ) );
		}

		/**
		 * Register block patterns
		 * @since 2.2.3
		 */
		public function bpfwp_add_patterns() {

			$block_patterns = array(
				'contact-card',
				'phone',
				'cell-phone',
				'email',
				'phone-email',
				'whatsapp',
				'fax',
				'address',
				'hours',
				'map',
			);
		
			foreach ( $block_patterns as $block_pattern ) {
				$pattern_file = BPFWP_PLUGIN_DIR . '/includes/patterns/' . $block_pattern . '.php';
		
				register_block_pattern(
					'business-profile/' . $block_pattern,
					require $pattern_file
				);
			}
		}

		/**
		 * Create a new category of block patterns to hold our pattern(s)
		 * @since 2.2.3
		 */
		public function bpfwp_add_pattern_category() {
			
			register_block_pattern_category(
				'bpfwp-block-patterns',
				array(
					'label' => __( 'Five Star Business Profile & Schema', 'business-profile' )
				)
			);
		}
	}
endif;
