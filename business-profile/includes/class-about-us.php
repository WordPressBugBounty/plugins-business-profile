<?php
/**
 * Class to create the 'About Us' submenu
 */

if ( !defined( 'ABSPATH' ) )
	exit;

if ( !class_exists( 'bpfwpAboutUs' ) ) {
class bpfwpAboutUs {

	public function __construct() {

		add_action( 'wp_ajax_bpfwp_send_feature_suggestion', array( $this, 'send_feature_suggestion' ) );

		add_action( 'admin_menu', array( $this, 'register_menu_screen' ), 11 );
	}

	/**
	 * Adds About Us submenu page
	 * @since 2.3.0
	 */
	public function register_menu_screen() {
		global $bpfwp_controller;

		add_submenu_page(
			'bpfwp-business-profile', 
			esc_html__( 'About Us', 'business-profile' ),
			esc_html__( 'About Us', 'business-profile' ),
			'manage_options', 
			'bpfwp-about-us',
			array( $this, 'display_admin_screen' )
		);
	}

	/**
	 * Displays the About Us page
	 * @since 2.3.0
	 */
	public function display_admin_screen() { ?>

		<div class='bpfwp-about-us-logo'>
			<img src='<?php echo plugins_url( "../assets/img/fsplogo.png", __FILE__ ); ?>'>
		</div>

		<div class='bpfwp-about-us-tabs'>

			<ul id='bpfwp-about-us-tabs-menu'>

				<li class='bpfwp-about-us-tab-menu-item bpfwp-tab-selected' data-tab='who_we_are'>
					<?php _e( 'Who We Are', 'business-profile' ); ?>
				</li>

				<li class='bpfwp-about-us-tab-menu-item' data-tab='lite_vs_premium'>
					<?php _e( 'Lite vs. Premium', 'business-profile' ); ?>
				</li>

				<li class='bpfwp-about-us-tab-menu-item' data-tab='getting_started'>
					<?php _e( 'Getting Started', 'business-profile' ); ?>
				</li>

				<li class='bpfwp-about-us-tab-menu-item' data-tab='suggest_feature'>
					<?php _e( 'Suggest a Feature', 'business-profile' ); ?>
				</li>

			</ul>

			<div class='bpfwp-about-us-tab' data-tab='who_we_are'>

				<p>
					<strong>Five Star Plugins focuses on creating high-quality, easy-to-use WordPress plugins centered around the restaurant, hospitality and business industries.</strong> With over <strong>50,000 active users worldwide</strong>, our plugins bring a great amount of value to many websites and business owners every day, by offering them solutions that are simple to implement and that provide powerful functionality necessary for their operations. Our <a href='https://www.fivestarplugins.com/plugins/five-star-restaurant-reservations/?utm_source=bpfwp_admin_about_us' target='_blank'>WordPress restaurant reservations plugin</a> and <a href='https://www.fivestarplugins.com/plugins/five-star-restaurant-menu/?utm_source=bpfwp_admin_about_us' target='_blank'>WordPress restaurant menu plugin</a> are both rich in features, responsive and highly customizable. Our <a href='https://www.fivestarplugins.com/plugins/five-star-business-profile/?utm_source=bpfwp_admin_about_us' target='_blank'>business profile WordPress plugin</a> and <a href='https://www.fivestarplugins.com/plugins/five-star-restaurant-reviews/?utm_source=bpfwp_admin_about_us' target='_blank'>WordPress restaurant reviews plugin</a> allow you to extend the functionality of your site and offer a full WordPress restaurant solution.
				</p>

				<p>
					<strong>On top of this, we pride ourselves on offering great and timely support and customer service.</strong>
				</p>

				<p>
					Our team is made up of developers, graphic designers, marketing associates and support specialists. Our partnership with <a href='https://www.etoilewebdesign.com/?utm_source=bpfwp_admin_about_us' target='_blank'>Etoile Web Design</a> gives us access to their fantastic support team and allows us to offer unparalleled customer service and technical support via multiple channels.
				</p>

			</div>

			<div class='bpfwp-about-us-tab bpfwp-hidden' data-tab='lite_vs_premium'>

				<p><?php _e( 'The premium version of the plugin includes several advanced features, such as automatic structured data for posts, drag-and-drop re-ordering for contact card elements, access to the Five Star Restaurant Manager mobile app and more!', 'business-profile' ); ?></p>

				<p><?php _e( 'Turn on the included <strong>WooCommerce product integration</strong> to automatically add structured data to your products.', 'business-profile' ); ?></p>

				<p><em><?php _e( 'The following table provides a comparison of the lite and premium versions.', 'business-profile' ); ?></em></p>

				<div class='bpfwp-about-us-premium-table'>
					<div class='bpfwp-about-us-premium-table-head'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Feature', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Lite Version', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Premium Version', 'business-profile' ); ?></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Add a contact card to any page on your site', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Gutenberg block, patterns and shortcodes to choose exactly what to display', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Multiple location support', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Create schema rules to add structured data to any or every page on your site', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Google Maps structured data support', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Add separate phone, mobile and WhatsApp numbers', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Automatic WooCommerce structured data for your products', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Automatically add structured data to posts', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Drag and drop re-ordering for contact card elements', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Easy-to-insert defaults for schema rule parameters', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
					<div class='bpfwp-about-us-premium-table-body'>
						<div class='bpfwp-about-us-premium-table-cell'><?php _e( 'Access to the Five Star Restaurant Manager mobile app to update your business info on the go', 'business-profile' ); ?></div>
						<div class='bpfwp-about-us-premium-table-cell'></div>
						<div class='bpfwp-about-us-premium-table-cell'><img src="<?php echo plugins_url( '../assets/img/dash-asset-checkmark.png', __FILE__ ); ?>"></div>
					</div>
				</div>

				<?php printf( __( '<a href="%s" target="_blank" class="bpfwp-about-us-tab-button bpfwp-about-us-tab-button-purchase">Buy Premium Version</a>', 'business-profile' ), 'https://www.fivestarplugins.com/license-payment/?Selected=BPFWP&Quantity=1&utm_source=admin_about_us' ); ?>
				
			</div>

			<div class='bpfwp-about-us-tab bpfwp-hidden' data-tab='getting_started'>

				<p><?php _e( 'The walk-though that ran when you first activated the plugin offers a quick way to get started with setting it up. If you would like to run through it again, just click the button below', 'business-profile' ); ?></p>

				<?php printf( __( '<a href="%s" class="bpfwp-about-us-tab-button bpfwp-about-us-tab-button-walkthrough">Re-Run Walk-Through</a>', 'business-profile' ), admin_url( '?page=bpfwp-getting-started' ) ); ?>

				<p><?php _e( 'We also have a series of video tutorials that cover the available settings as well as key features of the plugin.', 'business-profile' ); ?></p>

				<?php printf( __( '<a href="%s" target="_blank" class="bpfwp-about-us-tab-button bpfwp-about-us-tab-button-youtube">YouTube Playlist</a>', 'business-profile' ), 'https://www.youtube.com/playlist?list=PLEndQUuhlvSoOidQF7iRvstiKjOT4tX71' ); ?>

				
			</div>

			<div class='bpfwp-about-us-tab bpfwp-hidden' data-tab='suggest_feature'>

				<div class='bpfwp-about-us-feature-suggestion'>

					<p><?php _e( 'You can use the form below to let us know about a feature suggestion you might have.', 'business-profile' ); ?></p>

					<textarea placeholder="<?php _e( 'Please describe your feature idea...', 'business-profile' ); ?>"></textarea>
					
					<br>
					
					<input type="email" name="feature_suggestion_email_address" placeholder="<?php _e( 'Email Address', 'business-profile' ); ?>">
				
				</div>
				
				<div class='bpfwp-about-us-tab-button bpfwp-about-us-send-feature-suggestion'>Send Feature Suggestion</div>
				
			</div>

		</div>

	<?php }

	/**
	 * Sends the feature suggestions submitted via the About Us page
	 * @since 2.3.0
	 */
	public function send_feature_suggestion() {
		global $bpfwp_controller;
		
		if (
			! check_ajax_referer( 'bpfwp-admin-js', 'nonce' ) 
			|| 
			! current_user_can( 'manage_options' )
		) {
			bpfwpHelper::admin_nopriv_ajax();
		}

		$headers = 'Content-type: text/html;charset=utf-8' . "\r\n";  
	    $feedback = sanitize_text_field( $_POST['feature_suggestion'] );
		$feedback .= '<br /><br />Email Address: ';
	  	$feedback .=  sanitize_email( $_POST['email_address'] );
	
	  	wp_mail( 'contact@fivestarplugins.com', 'BPFWP Feature Suggestion', $feedback, $headers );
	
	  	die();
	} 

}
} // endif;