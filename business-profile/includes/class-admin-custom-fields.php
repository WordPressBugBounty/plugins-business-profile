<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'bpfwpAdminCustomFields' ) ) {
/**
 * Class to handle the admin custom fields page for Business Profile
 *
 * @since 2.3.4
 */
class bpfwpAdminCustomFields {

	public function __construct() {

		// Add the admin menu
		add_action( 'admin_menu', array( $this, 'maybe_add_menu_page' ), 11 );
	}

	/**
	 * Add the top-level admin menu page
	 * @since 2.3.4
	 */
	public function maybe_add_menu_page() {
		global $bpfwp_controller;

		if ( empty( $bpfwp_controller->settings->get_setting( 'custom-fields' ) ) ) { return; }

		add_submenu_page( 
			'bpfwp-business-profile', 
			_x( 'Custom Fields', 'Title of admin page that lets you view and edit all custom field valuess', 'business-profile' ),
			_x( 'Custom Fields', 'Title of the custom fields admin menu item', 'business-profile' ), 
			'manage_options', 
			'bpfwp-custom-fields', 
			array( $this, 'show_admin_custom_fields_page' )
		);

	}

	/**
	 * Display the admin custom fields page
	 * @since 2.3.4
	 */
	public function show_admin_custom_fields_page() {
		global $bpfwp_controller;

		if ( ! empty( $_POST['bpfwp-custom-fields-submit'] ) ) {

			$this->save_custom_field_values();
		}

		$custom_fields = bpfwp_decode_infinite_table_setting( $bpfwp_controller->settings->get_setting( 'custom-fields' ) );

		$custom_field_values = $bpfwp_controller->settings->get_setting( 'custom_field_values' );

		?>

		<div class="wrap">
			<h1>
				<?php _e( 'Custom Fields', 'business-profile' ); ?>
			</h1>

			<?php do_action( 'bpfwp_custom_fields_table_top' ); ?>
	
			<form id="bpfwp-custom-fields-table" method="POST" action="" enctype="multipart/form-data">

				<?php wp_nonce_field( 'bpfwp_custom_fields', 'bpfwp_custom_fields_nonce' ); ?>
	
				<div id='bpfwp-custom-fields-table-div'>
	
					<div class='bpfwp-custom-fields-explanation'>
						<?php _e( 'These global custom field values are used for the main contact card, or if a custom field value isn\'t set for a particular location.', 'business-profile' ); ?>
					</div>
	
					<?php foreach ( $custom_fields as $custom_field ) { ?>
	
						<div class="bpfwp-custom-field">

							<label for='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>'>
								<?php echo esc_html( $custom_field->name ); ?>
							</label>

							<div class='bpfwp-custom-field-value'>
	
								<?php $options = explode( ',', $custom_field->options ); ?>
		
								<?php $field_value = ! empty( $custom_field_values[ $custom_field->id ] ) ? $custom_field_values[ $custom_field->id ] : ''; ?>
		
								<?php if ( $custom_field->type == 'textarea' ) { ?>
				
										<textarea name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>'>
											<?php echo esc_html( $field_value ); ?>
										</textarea>
				
								<?php } elseif ( $custom_field->type == 'select' ) { ?>
									<?php if ( ! empty( $options ) ) { ?>
				
										<select name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>'>
											<?php foreach ( $options as $option ) { ?>
				
												<option value='<?php echo esc_attr( $option ); ?>' <?php echo ( $option == $field_value ? 'selected' : '' ); ?> >
													<?php echo esc_html( $option ); ?>
												</option>
											<?php } ?>
										</select>
				
									<?php } ?>
								<?php } elseif ( $custom_field->type == 'checkbox' ) { ?>
									<?php $field_value = is_array( $field_value ) ? $field_value : array(); ?>
									<?php if ( ! empty( $options ) ) { ?>
				
										<div class='bpfwp-fields-page-radio-checkbox-container'>
											<?php foreach ( $options as $option ) { ?>
				
												<div class='bpfwp-fields-page-radio-checkbox-each'>
													<input type='checkbox' name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>[]' value='<?php echo esc_attr( $option ); ?>' <?php echo ( in_array( $option, $field_value ) ? 'checked' : '' ); ?> />
													<?php echo esc_html( $option ); ?>
												</div>
											<?php } ?>
										</div>
				
									<?php } ?>
								<?php } elseif ( $custom_field->type == 'radio' ) { ?>
									<?php if ( ! empty( $options ) ) { ?>
				
										<div class='bpfwp-fields-page-radio-checkbox-container'>
											<?php foreach ( $options as $option ) { ?>
				
												<div class='bpfwp-fields-page-radio-checkbox-each'>
													<input type='radio' name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>' value='<?php echo esc_attr( $option ); ?>' <?php echo ( $option == $field_value ? 'checked' : '' ); ?> />
													<?php echo esc_html( $option ); ?>
												</div>
											<?php } ?>
										</div>
				
									<?php } ?>
								<?php } elseif ( $custom_field->type == 'date' ) { ?>
				
									<input type='date' class='bpfwp-jquery-datepicker' name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>' value='<?php echo esc_attr( $field_value ); ?>' />
				
								<?php } elseif ( $custom_field->type == 'datetime' ) { ?>
				
									<input type='datetime-local' name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>' value='<?php echo esc_attr( $field_value ); ?>' />
								
								<?php } elseif ( $custom_field->type == 'file' ) { ?>
			
									<div class='bpfwp-fields-page-file-preview'>
			
										<span>
											<?php _e( 'Current File:',  'business-profile' ); ?> <?php echo ! empty( $field_value ) ? esc_html( basename( $field_value ) ) : ''; ?>
										</span>
			
									</div>
									
									<input type='hidden' name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>' value='<?php echo esc_attr( $field_value ); ?>' />

									<input type='file' id='bpfwp-<?php echo esc_attr( $custom_field->name ); ?>' name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>' />
			
								<?php } else { ?>
				
									<input type='text' id='bpfwp-<?php echo esc_attr( $custom_field->name ); ?>' name='bpfwp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>' value='<?php echo esc_attr( $field_value ); ?>' size='25' />
				
								<?php } ?>

							</div>

						</div>
	
					<?php } ?>
	
				</div>
	
				<input type='submit' class='button button-primary' name='bpfwp-custom-fields-submit' value='<?php _e( 'Save Fields', 'business-profile' ); ?>' />
					
			</form>
				
			<?php do_action( 'bpfwp_custom_fields_table_bottom' ); ?>

		</div>

		<?php
	}

	/**
	 * Save the custom fields when the form is submitted
	 * @since 2.3.4
	 */
	public function save_custom_field_values() {
		global $bpfwp_controller;

		if ( ! isset( $_POST['bpfwp_custom_fields_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bpfwp_custom_fields_nonce'] ), 'bpfwp_custom_fields' ) ) { return false; } 

		$custom_fields = bpfwp_decode_infinite_table_setting( $bpfwp_controller->settings->get_setting( 'custom-fields' ) );

		$custom_field_values = array();

		foreach ( $custom_fields as $custom_field ) { 
				
			$input_name = 'bpfwp-custom-field-' . $custom_field->id;
	
			if ( $custom_field->type == 'file' ) {
	
				if ( empty( $_FILES[ $input_name ]['name'] ) ) {

					$field_value = sanitize_text_field( $_POST[ $input_name ] ); 
				}
				else {
				
					$uploaded_file = wp_handle_upload( $_FILES[ $input_name ], array( 'test_form' => false ) );
					$field_value = $uploaded_file['url'];
				}
			}
			elseif ( $custom_field->type == 'checkbox' ) {
	
				$field_value = ( isset( $_POST[ $input_name ] ) and is_array( $_POST[ $input_name ] ) ) ? array_map( 'sanitize_text_field', $_POST[ $input_name ] ) : array();
			}
			else {
					
				$field_value = sanitize_text_field( $_POST[ $input_name ] );
			}

			$custom_field_values[ $custom_field->id ] = $field_value;
		}
			
		update_option( 'bpfwp_custom_field_values', $custom_field_values );
	}
}
} // endif;
