<?php
/**
 * Template functions for rendering contact cards.
 *
 * @package   BusinessProfile
 * @copyright Copyright (c) 2016, Theme of the Crop
 * @license   GPL-2.0+
 * @since     0.0.1
 */

if ( ! function_exists( 'bpfwp_setting' ) ) {
	/**
	 * Retrieve the value of any stored setting
	 *
	 * A wrapper for $bpfw_controller->settings->get_setting() that should be
	 * used to access any data for the global location or one of the location
	 * custom posts.
	 *
	 * @since  1.1
	 * @access public
	 * @param  string $setting The setting to retrieve.
	 * @param  string $location The location associated with the setting.
	 * @return mixed A setting based on the key provided.
	 */
	function bpfwp_setting( $setting, $location = false ) {
		global $bpfwp_controller;
		return $bpfwp_controller->settings->get_setting( $setting, $location );
	}
}

if ( ! function_exists( 'bpfwp_get_display' ) ) {
	/**
	 * A helper function to check if a setting should be displayed visually or
	 * added as metadata
	 *
	 * @since 1.1
	 * @access public
	 * @param  string $setting The setting to retrieve.
	 * @return mixed A setting based on the key provided.
	 */
	function bpfwp_get_display( $setting ) {

		global $bpfwp_controller;

		if ( empty( $bpfwp_controller->display_settings ) ) {
			$bpfwp_controller->display_settings = $bpfwp_controller->settings->get_default_display_settings();
		}

		return isset( $bpfwp_controller->display_settings[ $setting ] ) ? $bpfwp_controller->display_settings[ $setting ] : false;
	}
}

if ( ! function_exists( 'bpfwp_set_display' ) ) {
	/**
	 * A helper function to set a setting's visibility on the fly
	 *
	 * These visibility flags are usually set when the shortcode or widget is
	 * loaded, or bpwfwp_print_contact_card() is called. This helper function
	 * makes it easy to set a flag if you're building your own template.
	 *
	 * @since  1.1
	 * @access public
	 * @param  string $setting The setting to be changed.
	 * @param  string $value The setting value to be used.
	 * @return void
	 */
	function bpfwp_set_display( $setting, $value ) {

		global $bpfwp_controller;

		if ( empty( $bpfwp_controller->display_settings ) ) {
			$bpfwp_controller->display_settings = $bpfwp_controller->settings->get_default_display_settings();
		}

		$bpfwp_controller->display_settings[ $setting ] = $value;
	}
}

if ( ! function_exists( 'bpwfwp_print_contact_card' ) ) {
	/**
	 * Print a contact card and add a shortcode.
	 *
	 * @since  0.0.1
	 * @access public
	 * @param  array $args Options for outputting the contact card.
	 * @return string Markup for displaying a contact card.
	 */
	function bpwfwp_print_contact_card( $args = array() ) {

		global $bpfwp_controller;

		// Define shortcode attributes.
		$bpfwp_controller->display_settings = shortcode_atts(
			$bpfwp_controller->settings->get_default_display_settings(),
			$args,
			'contact-card'
		);

		// Check if location is allowed to be viewed
		$location_id = bpfwp_get_display( 'location' );
		if ( $location_id && !current_user_can( 'edit_location', $location_id ) && get_post_status( $location_id ) !== 'publish' ) {
			return apply_filters( 'bpwfwp_protected_contact_card_output', '' );
		}

		// Setup components and callback functions to render them.
		$data = apply_filters(
			'bpwfwp_component_callbacks',
			bpfwp_get_contact_card_fields()
		);

		if ( ! $bpfwp_controller->get_theme_support( 'disable_styles' ) ) {
			/**
			 * Filter to override whether the frontend stylesheets are loaded.
			 *
			 * This is deprecated in favor of add_theme_support(). To prevent
			 * styles from being loaded, add the following to your theme:
			 *
			 * add_theme_support( 'business-profile', array( 'disable_styles' => true ) );
			 */
			if ( apply_filters( 'bpfwp-load-frontend-assets', true ) ) {
				wp_enqueue_style( 'dashicons' );
				wp_enqueue_style( 'bpfwp-default' );
			}
		}

		ob_start();
		$template = new bpfwpTemplateLoader;
		$template->set_template_data( $data );

		// Custom styling
		$styling = bpfwp_add_custom_styling();
		echo $styling;

		if ( bpfwp_get_display( 'location' ) ) {
			$template->get_template_part( 'contact-card', bpfwp_get_display( 'location' ) );
		} else {
			$template->get_template_part( 'contact-card' );
		}

		$output = ob_get_clean();

		// Reset display settings.
		$bpfwp_controller->display_settings = $bpfwp_controller->settings->get_default_display_settings();

		return apply_filters( 'bpwfwp_contact_card_output', $output );
	}

	if ( ! shortcode_exists( 'contact-card' ) ) {
		add_shortcode( 'contact-card', 'bpwfwp_print_contact_card' );
	}
}

if ( ! function_exists( 'bpfwp_print_ordering_link' ) ) {
	/**
	 * Print the menu link
	 *
	 * @since  2.1.5
	 * @access public
	 * @return array
	 */
	function bpfwp_print_ordering_link( $location = false ) {
		global $bpfwp_controller;

		$return_data = array();

		$link = bpfwp_setting( 'menu', $location );

		$ordering_link = bpfwp_setting( 'ordering-link', $location );

		if( ! $link ) {
			$return_data['hasMenu'] = $ordering_link;
		}
		else {
			$return_data['hasMenu'] = get_permalink( $link );
		}

		if ( bpfwp_get_display( 'show_ordering_link' ) && ! empty( $ordering_link ) ) :
		?>
			<div class="bp-ordering-link">
				<a href="<?php echo esc_url( $ordering_link ); ?>" target="blank">
					<?php echo esc_html( $bpfwp_controller->settings->get_setting( 'label-place-an-order' ) ); ?>
				</a>
			</div>
			<?php
		endif;

		return $return_data;
	}
}

if ( ! function_exists( 'bpfwp_print_custom_fields' ) ) {
	/**
	 * Print any admin-defined custom fields
	 *
	 * @since  2.1.5
	 * @access public
	 * @return array
	 */
	function bpfwp_print_custom_fields( $location = false ) {
		global $bpfwp_controller;

		$return_data = array();

		$custom_fields = bpfwp_decode_infinite_table_setting( $bpfwp_controller->settings->get_setting( 'custom-fields' ) );

		$custom_field_values = bpfwp_setting( 'custom_field_values', $location );

		if ( bpfwp_get_display( 'show_custom_fields' ) && ! empty( $custom_fields ) ) :
		?>
			<div class="bp-custom-fields">
				
				<?php foreach ( $custom_fields as $custom_field ) { ?>

					<div class="bp-custom-field">

						<label for='bp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>'>
							<?php echo esc_html( $custom_field->name ); ?>
						</label>

						<?php $field_value = ! empty( $custom_field_values[ $custom_field->id ] ) ? $custom_field_values[ $custom_field->id ] : ''; ?>

						<?php $field_value = is_array( $field_value ) ? implode( ', ', $field_value ) : $field_value; ?>

						<div class='bp-custom-field-value' id='bp-custom-field-<?php echo esc_attr( $custom_field->id ); ?>'>

							<?php if ( $custom_field->type == 'file' ) { ?>
							
								<a href='<?php echo esc_attr( $field_value ); ?>' download> 
									<?php echo ! empty( $field_value ) ? esc_html( basename( $field_value ) ) : ''; ?>
								</a>

							<?php } elseif ( $custom_field->type == 'link' ) { ?>

								<?php if ( $field_value != '' ) { ?>
							
									<a href='<?php echo esc_attr( $field_value ); ?>' > 
										<?php echo esc_html( $field_value ); ?>
									</a>

								<?php } ?>
		
							<?php } else { ?>
								
								<?php echo esc_html( $field_value ); ?>

							<?php } ?>

						</div>

					</div>

				<?php } ?>

			</div>
			<?php
		endif;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_name' ) ) {
	/**
	 * Print the name.
	 *
	 * @since  0.0.1
	 * @access public
	 * @param  string $location The location associated with the name.
	 * @return array
	 */
	function bpwfwp_print_name( $location = false ) {

		$return_data = array();

		if ( bpfwp_get_display( 'show_name' ) ) :
		?>
		<div class="bp-name">
			<?php echo esc_attr( bpfwp_setting( 'name', $location ) ); ?>
		</div>
		<?php endif; ?>

		<?php $return_data['name'] = bpfwp_setting( 'name', $location ); ?>

		<?php if ( empty( $location ) ) : ?>
			<?php $return_data['description'] = get_bloginfo( 'description' ); ?>
			<?php $return_data['url'] = get_bloginfo( 'url' ); ?>

		<?php else : ?>
			<?php $return_data['url'] = get_permalink( $location ); ?>

		<?php endif;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_address' ) ) {
	/**
	 * Print the address with a get directions link to Google Maps.
	 *
	 * @since  0.0.1
	 * @access public
	 * @param  string $location The location associated with the address.
	 * @return array
	 */
	function bpwfwp_print_address( $location = false ) {

		global $bpfwp_controller;

		$return_data = array();

		$address = bpfwp_setting( 'address', $location );

		if ( empty( $address['text'] ) ) {
			return $return_data;
		}
		?>

		<?php 
		$return_data['address'] = array(
			'type' => 'PostalAddress',
			'name' => $address['text'],
		);
		?>

		<?php if ( bpfwp_get_display( 'show_address' ) ) : ?>
		<div class="bp-address">
			<?php echo nl2br( esc_html( $address['text'] ) ); ?>
		</div>
		<?php endif; ?>

		<?php if ( bpfwp_get_display( 'show_get_directions' ) ) : ?>
		<div class="bp-directions">
			<a href="//maps.google.com/maps?saddr=current+location&daddr=<?php echo urlencode( esc_attr( $address['text'] ) ); ?>" target="_blank"><?php echo esc_html( $bpfwp_controller->settings->get_setting( 'label-get-directions' ) ); ?></a>
		</div>
		<?php endif;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_phone' ) ) {
	/**
	 * Print the phone number.
	 *
	 * @since  0.0.1
	 * @access public
	 * @param  string $location The location associated with the phone.
	 * @return array
	 */
	function bpwfwp_print_phone( $location = false ) {

		$return_data = array(
			'contactPoint' => array(
				array(
					'@type' => 'ContactPoint',
					'contactType' => 'Telephone'
				)
			)
		);

		$phone = bpfwp_setting( 'phone', $location );
		$click_to_call_phone = bpfwp_setting( 'clickphone', $location );

		if ( $click_to_call_phone == '' ) {
			$click_to_call_phone = $phone;
		}

		if ( empty( $phone ) ) {
			return '';
		}

		if ( bpfwp_get_display( 'show_phone' ) ) : ?>

		<div class="bp-phone">
			<a href="tel:<?php echo esc_attr( $click_to_call_phone ); ?>"><?php echo esc_html( $phone ); ?></a>
		</div>

		<?php endif;

		$return_data['contactPoint'][0]['telephone'] = $phone;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_cell_phone' ) ) {
	/**
	 * Print the phone number.
	 *
	 * @since  2.2.3
	 * @access public
	 * @param  string $location The location associated with the phone.
	 * @return array
	 */
	function bpwfwp_print_cell_phone( $location = false ) {

		$return_data = array(
			'contactPoint' => array(
				array(
					'@type' => 'ContactPoint',
					'contactType' => 'Cell Phone'
				)
			)
		);

		$cell_phone = bpfwp_setting( 'cell-phone', $location );
		$click_to_call_phone = bpfwp_setting( 'clickcellphone', $location );

		if ( $click_to_call_phone == '' ) {
			$click_to_call_phone = $cell_phone;
		}

		if ( empty( $cell_phone ) ) {
			return '';
		}

		if ( bpfwp_get_display( 'show_cell_phone' ) ) : ?>

		<div class="bp-cell-phone">
			<a href="tel:<?php echo esc_attr( $click_to_call_phone ); ?>"><?php echo esc_html( $cell_phone ); ?></a>
		</div>

		<?php endif;

		$return_data['contactPoint'][0]['telephone'] = $cell_phone;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_whatsapp_phone' ) ) {
	/**
	 * Print the phone number.
	 *
	 * @since  2.2.3
	 * @access public
	 * @param  string $location The location associated with the whatsapp.
	 * @return array
	 */
	function bpwfwp_print_whatsapp_phone( $location = false ) {

		$return_data = array(
			'contactPoint' => array(
				array(
					'@type' => 'ContactPoint',
					'contactType' => 'Whatsapp'
				)
			)
		);

		$whatsapp = bpfwp_setting( 'whatsapp', $location );
		$whatsapp_txt = bpfwp_setting( 'whatsapptext', $location );
		$whatsapp_display = bpfwp_setting( 'whatsappdisplay', $location );

		if ( empty( $whatsapp ) ) {
			return '';
		}

		if ( empty( $whatsapp_display ) ) {
			return '';
		}

		if( !empty( $whatsapp_txt ) ) {
			$whatsapp_txt = '?text='.urlencode( $whatsapp_txt );
		}

		if ( bpfwp_get_display( 'show_whatsapp' ) ) : ?>

		<div class="bp-whatsapp">
			<a href="https://wa.me/<?php echo esc_attr( $whatsapp . $whatsapp_txt ); ?>"><?php echo esc_html( $whatsapp_display ); ?></a>
		</div>

		<?php endif;

		$return_data['contactPoint'][0]['telephone'] = $whatsapp;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_fax' ) ) {
	/**
	 * Print the phone number.
	 *
	 * @since  2.2.3
	 * @access public
	 * @param  string $location The location associated with the fax number.
	 * @return array
	 */
	function bpwfwp_print_fax( $location = false ) {

		$return_data = array();

		$fax = bpfwp_setting( 'fax', $location );

		if ( empty( $fax ) ) {
			return '';
		}

		if ( bpfwp_get_display( 'show_fax' ) ) : ?>

		<div class="bp-fax">
			<a href="fax:<?php echo esc_attr( $fax ); ?>"><?php echo esc_html( $fax ); ?></a>
		</div>

		<?php endif;

		$return_data['faxNumber'] = $fax;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_contact' ) ) {
	/**
	 * Print the contact link.
	 *
	 * @since  0.0.1
	 * @access public
	 * @param  string $location The location associated with the contact.
	 * @return array
	 */
	function bpwfwp_print_contact( $location = false ) {
		global $bpfwp_controller;

		$return_data = array();

		$email = bpfwp_setting( 'contact-email', $location );

		if ( ! empty( $email ) ) :
			$antispam_email = antispambot( $email );

			if ( bpfwp_get_display( 'show_contact' ) ) : ?>

				<div class="bp-contact bp-contact-email">
					<a href="mailto:<?php echo esc_attr( $antispam_email ); ?>"><?php echo esc_html( $antispam_email ); ?></a>
				</div>

			<?php endif; ?>

			<?php $return_data['email'] = $antispam_email; ?>

		<?php
			return $return_data;
		endif;

		$contact = bpfwp_setting( 'contact-page', $location );
		if ( ! empty( $contact ) && bpfwp_get_display( 'show_contact' ) ) :
		?>

		<div class="bp-contact bp-contact-page">
			<a href="<?php echo esc_url( get_permalink( $contact ) ); ?>">
				<?php esc_html( $bpfwp_controller->settings->get_setting( 'label-contact' ) ); ?>
			</a>
		</div>

		<?php
		$return_data['ContactPoint'] = array(
			array(
				'contactType' => 'customer support',
				'url' => get_permalink( $contact ),
			)
		);

		endif;

		return $return_data;
	}
}

if ( ! function_exists( 'bpwfwp_print_opening_hours' ) ) {
	/**
	 * Print the opening hours.
	 *
	 * @since  0.0.1
	 * @access public
	 * @param  string $location The location associated with the hours.
	 * @return string|void Returns an empty string if no hours exist.
	 */
	function bpwfwp_print_opening_hours( $location = false ) {
		global $bpfwp_controller;

		$hours = bpfwp_setting( 'opening-hours', $location );

		if ( empty( $hours ) ) {
			return '';
		}

		// Get the opening hours in a returnable format
		$return_data = bpfwp_get_opening_hours_array( $hours );

		if ( ! bpfwp_get_display( 'show_opening_hours' ) ) {
			return;
		}

		$tz = new DateTimeZone( wp_timezone_string() );

		// Output display format.
		if ( bpfwp_get_display( 'show_opening_hours_brief' ) ) :
		?>

		<div class="bp-opening-hours-brief">

			<?php
			$slots = array();
			foreach ( $hours as $slot ) {

				// Skip this entry if no weekdays are set.
				if ( empty( $slot['weekdays'] ) ) {
					continue;
				}

				$days = array();
				$weekdays_i18n = array(
					'monday'	=> esc_html( $bpfwp_controller->settings->get_setting( 'label-monday-abbreviation' ) ),
					'tuesday'	=> esc_html( $bpfwp_controller->settings->get_setting( 'label-tuesday-abbreviation' ) ),
					'wednesday'	=> esc_html( $bpfwp_controller->settings->get_setting( 'label-wednesday-abbreviation' ) ),
					'thursday'	=> esc_html( $bpfwp_controller->settings->get_setting( 'label-thursday-abbreviation' ) ),
					'friday'	=> esc_html( $bpfwp_controller->settings->get_setting( 'label-friday-abbreviation' ) ),
					'saturday'	=> esc_html( $bpfwp_controller->settings->get_setting( 'label-saturday-abbreviation' ) ),
					'sunday'	=> esc_html( $bpfwp_controller->settings->get_setting( 'label-sunday-abbreviation' ) ),
				);
				foreach ( $slot['weekdays'] as $day => $val ) {
					$days[] = $weekdays_i18n[ $day ];
				}
				$days_string = ! empty( $days ) ? join( _x( ',', 'Separator between days of the week when displaying opening hours in brief. Example: Mo,Tu,We', 'business-profile' ), $days ) : '';

				if ( empty( $slot['time'] ) ) {
					$string = sprintf( _x( '%s all day', 'Brief opening hours description which lists days_strings when open all day. Example: Mo,Tu,We all day', 'business-profile' ), $days_string );
				} else {
					unset( $start );
					unset( $end );
					if ( ! empty( $slot['time']['start'] ) ) {
						$start = new DateTime( $slot['time']['start'], $tz );
					}
					if ( ! empty( $slot['time']['end'] ) ) {
						$end = new DateTime( $slot['time']['end'], $tz );
					}

					if ( empty( $start ) ) {
						$string = sprintf( _x( '%s open until %s', 'Brief opening hours description which lists the days followed by the closing time. Example: Mo,Tu,We open until 9:00pm', 'business-profile' ), $days_string, $end->format( get_option( 'time_format' ) ) );
					} elseif ( empty( $end ) ) {
						$string = sprintf( _x( '%s open from %s', 'Brief opening hours description which lists the days followed by the opening time. Example: Mo,Tu,We open from 9:00am', 'business-profile' ), $days_string, $start->format( get_option( 'time_format' ) ) );
					} else {
						$string = sprintf( _x( '%s %s&thinsp;&ndash;&thinsp;%s', 'Brief opening hours description which lists the days followed by the opening and closing times. Example: Mo,Tu,We 9:00am&thinsp;&ndash;&thinsp;5:00pm', 'business-profile' ), $days_string, $start->format( get_option( 'time_format' ) ),  $end->format( get_option( 'time_format' ) ) );
					}
				}

				$slots[] = $string;
			}

			echo join( _x( '; ', 'Separator between multiple opening times in the brief opening hours. Example: Mo,We 9:00 AM&thinsp;&ndash;&thinsp;5:00 PM; Tu,Th 10:00 AM&thinsp;&ndash;&thinsp;5:00 PM', 'business-profile' ), $slots );
			?>

		</div>

		<?php
			return $return_data;
		endif; // Brief opening hours.

		$weekdays_display = array(
			'monday'	=> $bpfwp_controller->settings->get_setting( 'label-monday'),
			'tuesday'	=> $bpfwp_controller->settings->get_setting( 'label-tuesday'),
			'wednesday'	=> $bpfwp_controller->settings->get_setting( 'label-wednesday'),
			'thursday'	=> $bpfwp_controller->settings->get_setting( 'label-thursday'),
			'friday'	=> $bpfwp_controller->settings->get_setting( 'label-friday'),
			'saturday'	=> $bpfwp_controller->settings->get_setting( 'label-saturday'),
			'sunday'	=> $bpfwp_controller->settings->get_setting( 'label-sunday'),
		);

		$weekdays = array();
		foreach ( $hours as $rule ) {

			// Skip this entry if no weekdays are set.
			if ( empty( $rule['weekdays'] ) ) {
				continue;
			}

			if ( empty( $rule['time'] ) ) {
				$time = __( 'Open', 'business-profile' );

			} else {

				if ( ! empty( $rule['time']['start'] ) ) {
					$start = new DateTime( $rule['time']['start'], $tz );
				}
				if ( ! empty( $rule['time']['end'] ) ) {
					$end = new DateTime( $rule['time']['end'], $tz );
				}

				if ( empty( $start ) ) {
					$time = __( 'Open until ', 'business-profile' ) . $end->format( get_option( 'time_format' ) );
				} elseif ( empty( $end ) ) {
					$time = __( 'Open from ', 'business-profile' ) . $start->format( get_option( 'time_format' ) );
				} else {
					$time = $start->format( get_option( 'time_format' ) ) . _x( '&thinsp;&ndash;&thinsp;', 'Separator between opening and closing times. Example: 9:00am&thinsp;&ndash;&thinsp;5:00pm', 'business-profile' ) . $end->format( get_option( 'time_format' ) );
				}
			}

			foreach ( $rule['weekdays'] as $day => $val ) {

				if ( ! array_key_exists( $day, $weekdays ) ) {
					$weekdays[ $day ] = array();
				}

				$weekdays[ $day ][] = $time;
			}
		}

		if ( count( $weekdays ) ) {

			// Order the weekdays and add any missing days as "closed".
			$weekdays_ordered = array();
			foreach ( $weekdays_display as $slug => $name ) {
				if ( ! array_key_exists( $slug, $weekdays ) ) {
					$weekdays_ordered[ $slug ] = array( __( 'Closed', 'business-profile' ) );
				} else {
					$weekdays_ordered[ $slug ] = $weekdays[ $slug ];
				}
			}

			$data = array(
				'weekday_hours' => $weekdays_ordered,
				'weekday_names' => $weekdays_display,
			);

			$template = new bpfwpTemplateLoader;
			$template->set_template_data( $data );

			if ( bpfwp_get_display( 'location' ) ) {
				$template->get_template_part( 'opening-hours', bpfwp_get_display( 'location' ) );
			} else {
				$template->get_template_part( 'opening-hours' );
			}
		}

		return $return_data;
	}
}

if ( ! function_exists( 'bpfwp_get_opening_hours_array' ) ) {
	/**
	 * Returns an array of opening hours, outputable for json+ld
	 *
	 * @since  2.1.0
	 * @access public
	 * @param  array $hours A list of opening hours.
	 * @return array
	 */
	function bpfwp_get_opening_hours_array( $hours ) {

		$opening_hours = array();

		$weekdays_schema = array(
			'monday'	=> 'Mo',
			'tuesday'	=> 'Tu',
			'wednesday'	=> 'We',
			'thursday'	=> 'Th',
			'friday'	=> 'Fr',
			'saturday'	=> 'Sa',
			'sunday'	=> 'Su',
		);

		// Output proper schema.org format.
		foreach ( $hours as $slot ) {

			// Skip this entry if no weekdays are set.
			if ( empty( $slot['weekdays'] ) ) {
				continue;
			}

			$days = array();
			foreach ( $slot['weekdays'] as $day => $val ) {
				$days[] = $weekdays_schema[ $day ];
			}
			$string = ! empty( $days ) ? join( ',', $days ) : '';

			if ( ! empty( $string ) && ! empty( $slot['time'] ) ) {

				if ( empty( $slot['time']['start'] ) ) {
					$start = '00:00';
				} else {
					$start = trim( substr( $slot['time']['start'], 0, -2 ) );
					if ( 'PM' === substr( $slot['time']['start'], -2 ) ) {
						$split = explode( ':', $start );
						$split[0] += intval($split[0]) == 12 ? 0 : 12;
						$start = join( ':', $split );
					}
					if ( 'AM' === substr( $slot['time']['start'], -2 ) && '12:00' === $start ) {
						$start = '00:00';
					}
				}

				if ( empty( $slot['time']['end'] ) ) {
					$end = '24:00';
				} else {
					$end = trim( substr( $slot['time']['end'], 0, -2 ) );
					if ( 'PM' === substr( $slot['time']['end'], -2 ) ) {
						$split = explode( ':', $end );
						$split[0] += intval($split[0]) == 12 ? 0 : 12;
						$end = join( ':', $split );
					}
					if ( ! empty( $slot['time']['end'] ) && 'AM' === substr( $slot['time']['end'], -2 ) && '12:00' === $end ) {
						$end = '24:00';
					}
				}

				$string .= ' ' . $start . '-' . $end;
			}
			
			$opening_hours[] = '"' . $string . '"';
		}

		return array( 'openingHours' => '[' . implode( ',', $opening_hours ) . ']' );
	}
}

if ( ! function_exists( 'bpwfwp_print_exceptions' ) ) {
	/**
	 * Print the exceptions, special opening hours or holidays.
	 *
	 * @since  2.1.0
	 * @access public
	 * @param  string $location The location associated with the exceptions.
	 * @return string|void Returns an empty string if no exceptions exist.
	 */
	function bpwfwp_print_exceptions( $location = false ) {
		global $bpfwp_controller;

		$disable_main_exceptions = get_post_meta( $location, 'disable_main_exceptions', true );

		$exceptions = ( $disable_main_exceptions and $location ) ? get_post_meta( $location, 'exceptions', true ) : bpfwp_setting( 'exceptions', $location );
		
		if ( empty( $exceptions ) || ! bpfwp_get_display( 'show_opening_hours' ) || ! function_exists( 'wp_date' ) ) {
			return '';
		}

		// Print the metatags with proper schema formatting.
		$return_data = bpfwp_get_exceptions_array( $exceptions );

		$date_format = get_option('date_format');
		$time_format = get_option('time_format');

		$tz = new DateTimeZone( wp_timezone_string() );

		$data = array(
			'special_hours' => array(),
			'holiday'       => array()
		);

		foreach ( $exceptions as $exception ) {

			if ( empty( $exception['date'] ) ) { continue; }

			if ( time() > strtotime( $exception['date'] ) + 24*3600 ) { continue; }
			
			if ( array_key_exists( 'time', $exception ) ) {
				// special opening-hours
				$data['special_hours'][] = $exception;
			}
			else {
				// holiday
				$data['holiday'][] = $exception;
			}
		}

		usort( $data['special_hours'], function( $a, $b ) {
			return strcasecmp( $a['date'], $b['date'] );
		});
		usort( $data['holiday'], function( $a, $b ) {
			return strcasecmp( $a['date'], $b['date'] );
		});

		if ( 0 < count( $data['special_hours'] ) ) { ?>
			
			<div class="bp-opening-hours special">
				<span class="bp-title"><?php echo esc_html( $bpfwp_controller->settings->get_setting( 'label-special-opening-hours' ) ); ?></span>
			
				<?php foreach ( $data['special_hours'] as $exception ) { ?>
				
					<?php 
						$date  = new DateTime( $exception['date'], $tz );
						$start = new DateTime( $exception['time']['start'], $tz );
						$end   = new DateTime( $exception['time']['end'], $tz );
					?>

					<div class="bp-date">
						<span class="label"><?php echo wp_date( $date_format, $date->format( 'U' ) ); ?></span>
						<span class="bp-times">
							<span class="bp-time">
								<?php 
									echo wp_date( $time_format, $start->format( 'U' ) ) 
										. ' – ' 
										. wp_date( $time_format, $end->format( 'U' ) );
								?>
							</span>
						</span>
					</div>
				<?php } ?>

			</div>
		<?php }

		if ( 0 < count( $data['holiday'] ) ) { ?>

			<div class="bp-opening-hours holiday">
				<span class="bp-title"><?php echo esc_html( $bpfwp_controller->settings->get_setting( 'label-holidays' ) ); ?></span>

				<?php foreach ( $data['holiday'] as $exception ) { ?>
				
					<?php $date = new DateTime( $exception['date'], $tz ); ?>
				
					<div class="bp-date">
						<span class="label">
							<?php echo wp_date( $date_format, $date->format( 'U' ) ); ?>
							</span>
						<span class="bp-times">
							<span class="bp-time">
								<?php echo esc_html( $bpfwp_controller->settings->get_setting( 'label-closed' ) ); ?>
							</span>
						</span>
					</div>
				<?php } ?>

			</div>
		<?php }

		return $return_data;
	}
}

if ( ! function_exists( 'bpfwp_get_exceptions_array' ) ) {
	/**
	 * Returns an array of exception rules, outputable for json+ld
	 *
	 * @since  2.1.0
	 * @access public
	 * @param  array $exceptions A list of opening hours.
	 * @return array
	 */
	function bpfwp_get_exceptions_array( $exceptions ) {
		
		$result = array();

		if ( is_array( $exceptions ) ) {
			
			foreach ( $exceptions as $exception ) {
				// Special opening-hours
				// @type: specialOpeningHoursSpecification
				$special_hours = array(
					'type'         => 'openingHoursSpecification',
					'validFrom'    => $exception['date'],
					'validThrough' => $exception['date']
				);
				
				if ( array_key_exists( 'time', $exception ) ) {
					$special_hours['opens']  = $exception['time']['start'];
					$special_hours['closes'] = $exception['time']['end'];
				}
				else {
					// without opens it is considered as close - holiday
				}

				$result['specialOpeningHoursSpecification'][] = $special_hours;
			}
		}

		return $result;
	}
}



if ( ! function_exists( 'bpwfwp_print_map' ) ) {
	/**
	 * Print a map to the address
	 *
	 * @since  0.0.1
	 * @access public
	 * @param  string $location The location associated with the map.
	 * @return array
	 */
	function bpwfwp_print_map( $location = false ) {

		$return_data = array();

		$address = bpfwp_setting( 'address', $location );

		if ( empty( $address['text'] ) || ! bpfwp_get_display( 'show_map' ) ) {
			return '';
		}

		global $bpfwp_controller;

		if ( ! $bpfwp_controller->get_theme_support( 'disable_scripts' ) ) {
			wp_enqueue_script( 'bpfwp-map' );
			wp_localize_script(
				'bpfwp-map',
				'bpfwp_map',
				array(
					// Override loading and intialization of Google Maps api.
					'google_maps_api_key' => bpfwp_setting( 'google-maps-api-key' ),
					'autoload_google_maps' => apply_filters( 'bpfwp_autoload_google_maps', true ),
					'map_options' => apply_filters( 'bpfwp_google_map_options', array() ),
					'strings' => array(
						'getDirections' => $bpfwp_controller->settings->get_setting( 'label-get-directions' ),
					),
				)
			);
		}

		global $bpfwp_map_ids;
		if ( empty( $bpfwp_map_ids ) ) {
			$bpfwp_map_ids = array();
		}

		$id = count( $bpfwp_map_ids );
		$bpfwp_map_ids[] = $id;

		$attr = '';

		$phone = bpfwp_setting( 'phone', $location );
		if ( ! empty( $phone ) ) {
			$attr .= ' data-phone="' . esc_attr( $phone ) . '"';
		}

		if ( ! empty( $address['lat'] ) && ! empty( $address['lon'] ) ) {
			$attr .= ' data-lat="' . esc_attr( $address['lat'] ) . '" data-lon="' . esc_attr( $address['lon'] ) . '"';
		}
		?>

		<div id="bp-map-<?php echo esc_attr( $id ); ?>" class="bp-map" data-name="<?php echo esc_attr( bpfwp_setting( 'name', $location ) ); ?>" data-address="<?php echo esc_attr( $address['text'] ); ?>" <?php echo $attr; ?>></div>

		<?php

		return $return_data;
	}
}

if ( ! function_exists( 'bpfwp_print_parent_organization' ) ) {
	/**
	 * Print a meta tag which connects a location to a `parentOrganization`
	 *
	 * @since  1.1
	 * @access public
	 * @return array
	 */
	function bpfwp_print_parent_organization() {

		$return_data = array();

		$location = bpfwp_get_display( 'location' );

		if ( empty( $location ) ) {
			return '';
		}

		$return_data['parentOrganization'] = bpfwp_setting( 'name' );

		return $return_data;
	}
}

function bpfwp_get_contact_card_fields() {
	global $bpfwp_controller;
	
	return array_replace( 
		(array) json_decode( $bpfwp_controller->settings->get_setting( 'contact-card-elements-order' ) ), 
		array(
			'name'                => 'bpwfwp_print_name',
			'address'             => 'bpwfwp_print_address',
			'phone'               => 'bpwfwp_print_phone',
			'cell_phone'          => 'bpwfwp_print_cell_phone',
			'whatsapp'            => 'bpwfwp_print_whatsapp_phone',
			'fax_phone'           => 'bpwfwp_print_fax',
			'ordering-link'       => 'bpfwp_print_ordering_link',
			'custom_fields'       => 'bpfwp_print_custom_fields',
			'contact'             => 'bpwfwp_print_contact',
			'exceptions'          => 'bpwfwp_print_exceptions',
			'opening_hours'       => 'bpwfwp_print_opening_hours', // opening-hours
			'map'                 => 'bpwfwp_print_map',
			'parent_organization' => 'bpfwp_print_parent_organization'
		)
	);
}

function bpfwp_get_time_label( $time ) {
	global $bpfwp_controller;

	switch ( $time ) {

		case 'Open':
			
			$time_label = $bpfwp_controller->settings->get_setting( 'label-open' );
			break;

		case 'Open until':
			
			$time_label = $bpfwp_controller->settings->get_setting( 'label-open-until' );
			break;

		case 'Open from':
			
			$time_label = $bpfwp_controller->settings->get_setting( 'label-open-from' );
			break;

		case 'Closed':
			
			$time_label = $bpfwp_controller->settings->get_setting( 'label-closed' );
			break;
		
		default:
			
			$time_label = $time;
			break;
	}

	return $time_label; 
}

if ( ! function_exists( 'bpfwp_json_ld_contact_print' ) ) {
	/**
	 * Recursively print out an array of $key => $value pairs of json+ld data 
	 *
	 * @since  2.1.0
	 * @access public
	 * @param mixed $json_key, an int for array data or the parameter type for json data
	 * @param mixed $json_data, print or recurse through it if array
	 * @return void
	 */
	function bpfwp_json_ld_contact_print( $json_key, $json_data ) {

		$return_string = '';

		if ( is_array( $json_data ) )
		{
			$closing = '';
			if ( bpfwp_array_any( array_keys( $json_data ), 'is_int' ) ) {
				$return_string .= '"' . $json_key . '": [';
				$closing = '],';
			}
			else {
				$return_string .= empty( $json_key ) || is_int( $json_key ) ? '' : '"' . $json_key . '" : ';
				
				$return_string .= '{';
				$closing = '},';
			}

			
			foreach ( $json_data as $key => $data ) {
				// recurse
				$return_string .= bpfwp_json_ld_contact_print( $key, $data );
			}

			$return_string = trim( $return_string, ',' ) . $closing;
		}
		elseif ( $json_key == 'openingHours' ) {
			$return_string .= '"' . $json_key . '": ' . $json_data . ',';
		}
		else {
			$return_string .= '"' . ( $json_key == 'type' ? '@' : '' ) . $json_key . '": "' . $json_data . '",';
		}

		return $return_string;
	}

	function bpfwp_array_any(array $array, callable $fn) {
		foreach ( $array as $value ) {
		    if( $fn( $value ) ) {
		        return true;
		    }
		}
		return false;
	}
}

if ( ! function_exists( 'bpfwp_add_custom_styling' ) ) {
	/**
	 * Add styling options`
	 *
	 * @since  2.3.4
	 */
	function bpfwp_add_custom_styling() {
		global $bpfwp_controller;
		$styling = '<style>';
			if ( $bpfwp_controller->settings->get_setting( 'styling-map-width') != '' ) { $styling .=  '.bp-map { width: ' . $bpfwp_controller->settings->get_setting( 'styling-map-width' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-disable-icons') ) { $styling .=  '.bp-contact-card > div:before, .bp-title:before { display: none !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-main-font-family') != '' ) { $styling .=  '.bp-contact-card, .bp-contact-card a { font-family: \'' . $bpfwp_controller->settings->get_setting( 'styling-main-font-family' ) . '\' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-main-font-size') != '' ) { $styling .=  '.bp-contact-card, .bp-contact-card a { font-size: ' . $bpfwp_controller->settings->get_setting( 'styling-main-font-size' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-main-text-color') != '' ) { $styling .=  '.bp-contact-card { color: ' . $bpfwp_controller->settings->get_setting( 'styling-main-text-color' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-name-font-family') != '' ) { $styling .=  '.bp-name { font-family: \'' . $bpfwp_controller->settings->get_setting( 'styling-name-font-family' ) . '\' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-name-font-size') != '' ) { $styling .=  '.bp-name { font-size: ' . $bpfwp_controller->settings->get_setting( 'styling-name-font-size' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-name-text-color') != '' ) { $styling .=  '.bp-name { color: ' . $bpfwp_controller->settings->get_setting( 'styling-name-text-color' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-heading-font-family') != '' ) { $styling .=  '.bp-title { font-family: \'' . $bpfwp_controller->settings->get_setting( 'styling-heading-font-family' ) . '\' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-heading-font-size') != '' ) { $styling .=  '.bp-title { font-size: ' . $bpfwp_controller->settings->get_setting( 'styling-heading-font-size' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-heading-text-color') != '' ) { $styling .=  '.bp-title { color: ' . $bpfwp_controller->settings->get_setting( 'styling-heading-text-color' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-link-color') != '' ) { $styling .=  '.bp-contact-card a { color: ' . $bpfwp_controller->settings->get_setting( 'styling-link-color' ) . ' !important; }'; }
			if ( $bpfwp_controller->settings->get_setting( 'styling-link-hover-color') != '' ) { $styling .=  '.bp-contact-card a:hover { color: ' . $bpfwp_controller->settings->get_setting( 'styling-link-hover-color' ) . ' !important; }'; }
		$styling .=   '</style>';
		return $styling;
	}
}

if ( ! function_exists( 'bpfwp_decode_infinite_table_setting' ) ) {
function bpfwp_decode_infinite_table_setting( $values ) {

	$values = $values ?? '';
	
	return is_array( json_decode( html_entity_decode( $values ) ) ) ? json_decode( html_entity_decode( $values ) ) : array();
}
}
