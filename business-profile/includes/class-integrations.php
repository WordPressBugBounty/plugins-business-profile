<?php
/** Create managed automatic rules without modifying independently authored definitions. */
defined( 'ABSPATH' ) || exit;

class bpfwpIntegrations {
	public function __construct() {
		add_action( 'updated_option', array( $this, 'settings_saved' ), 10, 3 );
		add_action( 'added_option', array( $this, 'settings_added' ), 10, 2 );
	}
	public function settings_added( $option, $value ) {

		$this->settings_saved( $option, array(), $value );
	}
	public function settings_saved( $option, $old, $value ) {
		if ( 'bpfwp-settings' !== $option || ! is_array( $value ) ) {
			return;
		}
		foreach ( bpfwpSchemaRuleOwnership::generators() as $origin => $generator ) {
			if ( ! empty( $value[ $generator['setting'] ] ) ) {
				$this->ensure( $origin );
			}
		}
	}
	public function check_for_articles_rich_snippets_change( $value ) {

		if ( ! empty( $value['article-rich-snippets'] ) ) {
			$this->ensure( 'automatic_article' );
		}
		return $value;
	}
	public function check_for_wc_integration_change( $value ) {

		if ( ! empty( $value['woocommerce-integration'] ) ) {
			$this->ensure( 'automatic_woocommerce' );
		}
		return $value;
	}
	private function ensure( $origin ) {
		$generator = bpfwpSchemaRuleOwnership::generators()[ $origin ];
		$settings  = bpfwpSettingsMutation::current();
		if ( empty( $settings[ $generator['setting'] ] ) || ! bpfwpPremiumCapabilities::allowed( $generator['capability'] ) ) {
			return;
		}
		$lock = bpfwpPremiumCapabilities::acquire( $generator['capability'] );
		if ( is_wp_error( $lock ) ) {
			return;
		}
		try {
			// Historical matches must be classified; neither manual nor disabled rules are enabled here.
			if ( get_page_by_path( $generator['slug'], OBJECT, bpfwpSchemaRuleOwnership::post_type() ) ) {
				return;
			}
			if ( ! bpfwpPremiumCapabilities::allowed( $generator['capability'] ) ) {
				return;
			}
			$id = wp_insert_post(
				array(
					'post_title'  => $generator['title'],
					'post_name'   => $generator['slug'],
					'post_type'   => bpfwpSchemaRuleOwnership::post_type(),
					'post_status' => 'draft',
				),
				true
			);
			if ( is_wp_error( $id ) || ! $id ) {
				return;
			}
			update_post_meta( $id, 'bpfwp-schema-data', bpfwpSchemaRuleOwnership::definition( $origin ) );
			if ( bpfwpSchemaRuleOwnership::stamp( $id, $origin ) && bpfwpPremiumCapabilities::allowed( $generator['capability'] ) ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);
			}
		} finally {
			bpfwpPremiumCapabilities::release();
		}
	}
}
