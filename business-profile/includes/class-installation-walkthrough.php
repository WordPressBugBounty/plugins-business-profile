<?php

/**
 * Class to handle everything related to the walk-through that runs on plugin activation
 */

if ( !defined( 'ABSPATH' ) )
	exit;

class bpfwpInstallationWalkthrough {

	// The scheduler control so that a business can sets its opening hours
	public $scheduler;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_install_screen' ) );
		add_action( 'admin_head', array( $this, 'hide_install_screen_menu_item' ) );
		add_action( 'admin_init', array( $this, 'redirect' ), 9999 );

		add_action( 'admin_head', array( $this, 'admin_enqueue' ) );

		add_action( 'init', array( $this, 'initialize_scheduler' ) );

		add_action('wp_ajax_bpfwp_welcome_add_contact_page', array($this, 'add_contact_page'));
		add_action('wp_ajax_bpfwp_welcome_set_contact_information', array($this, 'set_contact_information'));
		add_action('wp_ajax_bpfwp_welcome_set_opening_hours', array($this, 'set_opening_hours'));
	}

	public function initialize_scheduler() {

		if ( ! class_exists( 'sapAdminPageSetting_2_6_19' ) ) {
			require_once BPFWP_PLUGIN_DIR . '/lib/simple-admin-pages/classes/AdminPageSetting.class.php';
		}

		if ( ! class_exists( 'sapAdminPageSettingScheduler_2_6_19' ) ) {
			require_once BPFWP_PLUGIN_DIR . '/lib/simple-admin-pages/classes/AdminPageSetting.Scheduler.class.php';
		}

		$args = array(
			'id'                 => 'opening-hours',
			'page'               => 'walkthrough',
			'title'              => __( 'Opening Hours', 'business-profile' ),
			'description'        => __( 'Define your weekly opening hours by adding scheduling rules.', 'business-profile' ),
			'weekdays'           => array(
				'monday'    => _x( 'Mo', 'Monday abbreviation', 'business-profile' ),
				'tuesday'   => _x( 'Tu', 'Tuesday abbreviation', 'business-profile' ),
				'wednesday' => _x( 'We', 'Wednesday abbreviation', 'business-profile' ),
				'thursday'  => _x( 'Th', 'Thursday abbreviation', 'business-profile' ),
				'friday'    => _x( 'Fr', 'Friday abbreviation', 'business-profile' ),
				'saturday'  => _x( 'Sa', 'Saturday abbreviation', 'business-profile' ),
				'sunday'    => _x( 'Su', 'Sunday abbreviation', 'business-profile' ),
			),
			'time_format'        => _x( 'h:i A', 'Time format displayed in the opening hours setting panel in your admin area. Must match formatting rules at https://amsul.ca/pickadate.js/time/#formatting-rules', 'business-profile' ),
			'date_format'        => _x( 'mmmm d, yyyy', 'Date format displayed in the opening hours setting panel in your admin area. Must match formatting rules at https://amsul.ca/pickadate.js/date/#formatting-rules', 'business-profile' ),
			'disable_weeks'      => true,
			'disable_date'       => true,
			'disable_date_range' => true,
			'strings'            => array(
				'add_rule'         => __( 'Add another opening time', 'business-profile' ),
				'weekly'           => _x( 'Weekly', 'Format of a scheduling rule', 'business-profile' ),
				'monthly'          => _x( 'Monthly', 'Format of a scheduling rule', 'business-profile' ),
				'date'             => _x( 'Date', 'Format of a scheduling rule', 'business-profile' ),
				'weekdays'         => _x( 'Days of the week', 'Label for selecting days of the week in a scheduling rule', 'business-profile' ),
				'month_weeks'      => _x( 'Weeks of the month', 'Label for selecting weeks of the month in a scheduling rule', 'business-profile' ),
				'date_label'       => _x( 'Date', 'Label to select a date for a scheduling rule', 'business-profile' ),
				'time_label'       => _x( 'Time', 'Label to select a time slot for a scheduling rule', 'business-profile' ),
				'allday'           => _x( 'All day', 'Label to set a scheduling rule to last all day', 'business-profile' ),
				'start'            => _x( 'Start', 'Label for the starting time of a scheduling rule', 'business-profile' ),
				'end'              => _x( 'End', 'Label for the ending time of a scheduling rule', 'business-profile' ),
				/* translators: 1: Opening link tag, 2: Closing link tag. */
				'set_time_prompt'  => _x( 'All day long. Want to %1$sset a time slot%2$s?', 'Prompt displayed when a scheduling rule is set without any time restrictions', 'business-profile' ),
				'toggle'           => _x( 'Open and close this rule', 'Toggle a scheduling rule open and closed', 'business-profile' ),
				'delete'           => _x( 'Delete rule', 'Delete a scheduling rule', 'business-profile' ),
				'delete_schedule'  => __( 'Delete scheduling rule', 'business-profile' ),
				'never'            => _x( 'Never', 'Brief default description of a scheduling rule when no weekdays or weeks are included in the rule', 'business-profile' ),
				'weekly_always'    => _x( 'Every day', 'Brief default description of a scheduling rule when all the weekdays/weeks are included in the rule', 'business-profile' ),
				/* translators: 1: Weekday list, 2: Week-of-month list. */
				'monthly_weekdays' => _x( '%1$s on the %2$s week of the month', 'Brief default description of a scheduling rule when some weekdays are included on only some weeks of the month. %s should be left alone and will be replaced by a comma-separated list of days and weeks in the following format: M, T, W on the first, second week of the month', 'business-profile' ),
				'monthly_weeks'    => _x( '%s week of the month', 'Brief default description of a scheduling rule when some weeks of the month are included but all or no weekdays are selected. %s should be left alone and will be replaced by a comma-separated list of weeks in the following format: First, second week of the month', 'business-profile' ),
				'all_day'          => _x( 'All day', 'Brief default description of a scheduling rule when no times are set', 'business-profile' ),
				'before'           => _x( 'Ends at', 'Brief default description of a scheduling rule when an end time is set but no start time. If the end time is 6pm, it will read: Ends at 6pm', 'business-profile' ),
				'after'            => _x( 'Starts at', 'Brief default description of a scheduling rule when a start time is set but no end time. If the start time is 6pm, it will read: Starts at 6pm', 'business-profile' ),
				'separator'        => _x( '&mdash;', 'Separator between times of a scheduling rule', 'business-profile' ),
			),
			'args'               => array(
				'class' => 'bpfwp-opening-hours',
			),
		);

		// This is required otherwise SAP_VERSION will throw error.
		require_once BPFWP_PLUGIN_DIR . '/lib/simple-admin-pages/simple-admin-pages.php';
		$sap = sap_initialize_library(
			array(
				'version' => '2.6.19',
				'lib_url' => BPFWP_PLUGIN_URL . '/lib/simple-admin-pages/',
			)
		);

		$this->scheduler = new sapAdminPageSettingScheduler_2_6_19( $args );
		global $bpfwp_controller;
		$hours = $bpfwp_controller->settings->get_setting( 'opening-hours' );
		if ( is_array( $hours ) ) {
			$this->scheduler->set_value( $hours );
		}
	}

	public function redirect() {
		global $bpfwp_controller;

		if ( ! get_transient( 'bpfwp-getting-started' ) ) 
			return;

		delete_transient( 'bpfwp-getting-started' );

		if ( is_network_admin() || isset( $_GET['activate-multi'] ) )
			return;

		$plugin_items = get_posts(array('post_type' => array( $bpfwp_controller->cpts->schema_cpt_slug, $bpfwp_controller->cpts->location_cpt_slug)));
		if (!empty($plugin_items)) {
			set_transient('bpfwp-admin-install-notice', true, 5);
			return;
		}
		
		wp_safe_redirect( admin_url( 'index.php?page=bpfwp-getting-started' ) ); 
		exit;
	}

	public function register_install_screen() {
		add_dashboard_page(
			esc_html__( 'Five Star Business Profile and Schema - Welcome!', 'business-profile' ),
			esc_html__( 'Five Star Business Profile and Schema - Welcome!', 'business-profile' ),
			'manage_options',
			'bpfwp-getting-started',
			array($this, 'display_install_screen')
		);
	}

	public function hide_install_screen_menu_item() {
		remove_submenu_page( 'index.php', 'bpfwp-getting-started' );
	}

	public function add_contact_page() {
		global $bpfwp_controller;

		// Authenticate request
		if ( ! check_ajax_referer( 'bpfwp-getting-started', 'nonce' ) or ! current_user_can( 'manage_options' ) ) {

			bpfwpHelper::admin_nopriv_ajax();
		}

		$title        = isset( $_POST['contact_page_title'] ) && is_string( $_POST['contact_page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_page_title'] ) ) : '';
		$contact_page = bpfwpPublicationChecks::contact_page( $title, absint( $_POST['contact_page_id'] ?? 0 ) );
		if ( is_wp_error( $contact_page ) ) {
			wp_send_json_error( array( 'message' => $contact_page->get_error_message() ), 400 );
		}
		wp_send_json_success(
			array(
				'message' => __( 'Contact page selected. Review it in the page editor before publishing.', 'business-profile' ),
				'page'    => $contact_page,
				'edit'    => get_edit_post_link( $contact_page, 'raw' ),
			)
		);
	}

	public function set_contact_information() {
		global $bpfwp_controller;

		// Authenticate request
		if ( ! check_ajax_referer( 'bpfwp-getting-started', 'nonce' ) or ! current_user_can( 'manage_options' ) ) {

			bpfwpHelper::admin_nopriv_ajax();
		}

		$raw = wp_unslash( $_POST );
		foreach ( array( 'schema_type', 'name', 'address', 'phone', 'email' ) as $field ) {
			if ( ! isset( $raw[ $field ] ) || ! is_string( $raw[ $field ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Complete the contact fields before saving.', 'business-profile' ) ), 400 );
			}
		}
		if ( ! array_key_exists( $raw['schema_type'], $bpfwp_controller->settings->get_schema_types() ) || ( '' !== $raw['email'] && ! is_email( $raw['email'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Check the schema type and email address.', 'business-profile' ) ), 400 );
		}
		$lock = bpfwpSettingsMutation::acquire();
		if ( is_wp_error( $lock ) ) {
			wp_send_json_error( array( 'message' => $lock->get_error_message() ), 409 );
		}
		$current = bpfwpSettingsMutation::current();
		$address = isset( $current['address'] ) && is_array( $current['address'] ) ? $current['address'] : array();
		$text    = sanitize_textarea_field( $raw['address'] );
		if ( ( $address['text'] ?? '' ) !== $text ) {
			$address['lat'] = '';
			$address['lon'] = '';
		}
		$address['text'] = $text;
		$result          = bpfwpSettingsMutation::update(
			array(
				'schema_type'   => $raw['schema_type'],
				'name'          => sanitize_text_field( $raw['name'] ),
				'address'       => $address,
				'phone'         => sanitize_text_field( $raw['phone'] ),
				'contact-email' => sanitize_email( $raw['email'] ),
			)
		);
		bpfwpSettingsMutation::release();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'message' => __( 'Contact information saved.', 'business-profile' ) ) );
	}

	public function set_opening_hours() {
		global $bpfwp_controller;

		// Authenticate request
		if ( ! check_ajax_referer( 'bpfwp-getting-started', 'nonce' ) or ! current_user_can( 'manage_options' ) ) {

			bpfwpHelper::admin_nopriv_ajax();
		}

		$raw   = isset( $_POST['walkthrough']['opening-hours'] ) ? wp_unslash( $_POST['walkthrough']['opening-hours'] ) : array();
		$check = bpfwpSettingsMutation::validate_schedule( array( 'opening-hours' => $raw ) );
		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ), 400 );
		}
		$result = bpfwpSettingsMutation::update( array( 'opening-hours' => $this->scheduler->sanitize_callback_wrapper( $raw ) ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'message' => __( 'Public opening hours saved. Booking availability is managed separately.', 'business-profile' ) ) );
	}

	public function admin_enqueue() {

		if ( ! isset( $_GET['page'] ) or 'bpfwp-getting-started' !== $_GET['page'] ) {
			return;
		}

		wp_enqueue_style( 'bpfwp-welcome-screen', BPFWP_PLUGIN_URL . '/assets/css/admin-bpfwp-welcome-screen.css', array(), BPFWP_VERSION );
		wp_enqueue_style( 'bpfwp-sap-admin-css', BPFWP_PLUGIN_URL . '/lib/simple-admin-pages/css/admin.css', array(), BPFWP_VERSION );

		foreach ( $this->scheduler->styles as $slug => $style ) {
			wp_enqueue_style( $slug, BPFWP_PLUGIN_URL . '/lib/simple-admin-pages/' . $style['path'], $style['dependencies'], $style['version'], $style['media'] );
		}

		wp_enqueue_script( 'bpfwp-getting-started', BPFWP_PLUGIN_URL . '/assets/js/admin-bpfwp-welcome-screen.js', array( 'jquery' ), BPFWP_VERSION );

		wp_localize_script(
			'bpfwp-getting-started',
			'bpfwp_getting_started',
			array(
				'nonce'      => wp_create_nonce( 'bpfwp-getting-started' ),
				'save_error' => __( 'The changes could not be saved. Your entries are still here; please retry.', 'business-profile' ),
			)
		);

		foreach ( $this->scheduler->scripts as $slug => $script ) {
			wp_enqueue_script( $slug, BPFWP_PLUGIN_URL . '/lib/simple-admin-pages/' . $script['path'], $script['dependencies'], $script['version'], $script['footer'] );
		}
	}

	public function display_install_screen() {
		?>
		<?php global $bpfwp_controller; ?>
		<?php $schema_types = $bpfwp_controller->settings->get_schema_types(); ?>

		<div class='bpfwp-welcome-screen'>
			<p id="bpfwp-setup-status" role="status" aria-live="polite"></p>
			<form class='bpfwp-welcome-screen-form'>
				<?php if ( ! isset( $_GET['exclude'] ) ) { ?>
				<div class='bpfwp-welcome-screen-header'>
					<h1><?php _e( 'Welcome to the Five Star Business Profile and Schema', 'business-profile' ); ?></h1>
						<p><?php esc_html_e( 'Set your public business information, prepare a contact page, then review it before publishing. You can return to setup at any time.', 'business-profile' ); ?></p>
				</div>
				<?php } ?>

				<div class='bpfwp-welcome-screen-box bpfwp-welcome-screen-set_contact_info bpfwp-welcome-screen-open' data-screen='set_contact_info'>
					<h2><button type="button" class="bpfwp-setup-section"><?php _e( '1. Set Contact Information', 'business-profile' ); ?></button></h2>
					<div class='bpfwp-welcome-screen-box-content'>
						<p><?php _e( 'Set the information that will be displayed on your contact page and in the contact schema for your business', 'business-profile' ); ?></p>
						<div class='bpfwp-welcome-screen-key-options'>
							<div class='rtb-welcome-screen-option'>
								<label class='bpfwp-option-name' for='bpfwp-schema-type'><?php esc_html_e( 'Schema Type:', 'business-profile' ); ?></label>
								<select id='bpfwp-schema-type' name='bpfwp-schema-type'>
									<?php foreach ( $schema_types as $schema_type => $schema_name ) { ?>
										<option value='<?php echo esc_attr( $schema_type ); ?>' <?php selected( $bpfwp_controller->settings->get_setting( 'schema_type' ), $schema_type ); ?>><?php echo esc_html( $schema_name ); ?></option>
									<?php } ?>
								</select>
							</div>
							<div class='rtb-welcome-screen-option'>
								<label class='bpfwp-option-name' for='bpfwp-contact-name'><?php esc_html_e( 'Name:', 'business-profile' ); ?></label>
								<input id="bpfwp-contact-name" type='text' name='bpfwp-contact-name' value="<?php echo esc_attr( bpfwp_setting( 'name' ) ); ?>" />
							</div>
							<div class='rtb-welcome-screen-option'>
								<label class='bpfwp-option-name' for='bpfwp-contact-address'><?php esc_html_e( 'Address:', 'business-profile' ); ?></label>
								<textarea id="bpfwp-contact-address" name='bpfwp-contact-address'>
								<?php
								$address = bpfwp_setting( 'address' );
								echo esc_textarea( $address['text'] ?? '' );
								?>
								</textarea>
							</div>
							<div class='rtb-welcome-screen-option'>
								<label class='bpfwp-option-name' for='bpfwp-contact-phone'><?php esc_html_e( 'Phone:', 'business-profile' ); ?></label>
								<input id="bpfwp-contact-phone" type='text' name='bpfwp-contact-phone' value="<?php echo esc_attr( bpfwp_setting( 'phone' ) ); ?>" />
							</div>
							<div class='rtb-welcome-screen-option'>
								<label class='bpfwp-option-name' for='bpfwp-contact-email'><?php esc_html_e( 'Email:', 'business-profile' ); ?></label>
								<input id="bpfwp-contact-email" type='email' name='bpfwp-contact-email' value='<?php echo esc_attr( bpfwp_setting( 'contact-email' ) ); ?>' />
							</div>
							<button type="button" class='bpfwp-welcome-screen-set-contact-information-button'><?php esc_html_e( 'Save Contact Information', 'business-profile' ); ?></button>
						</div>
						<div class='clear'></div>
						<button type="button" class='bpfwp-welcome-screen-next-button bpfwp-welcome-screen-next-button-not-top-margin' data-nextaction='set_hours'><?php _e( 'Next Step', 'business-profile' ); ?></button>
						<div class='clear'></div>
					</div>
				</div>
			
				<div class='bpfwp-welcome-screen-box bpfwp-welcome-screen-set_hours' data-screen='set_hours'>
					<h2><button type="button" class="bpfwp-setup-section"><?php _e( '2. Set Public Opening Hours', 'business-profile' ); ?></button></h2>
					<div class='bpfwp-welcome-screen-box-content'>
						<div class='bpfwp-welcome-screen-set-hours-div'>
						
							<?php $this->scheduler->display_setting(); ?>

							<button type="button" class='bpfwp-welcome-screen-set-hours-button'><?php esc_html_e( 'Save Hours', 'business-profile' ); ?></button>
						</div>
						<div class="bpfwp-welcome-clear"></div>
						<button type="button" class='bpfwp-welcome-screen-next-button' data-nextaction='create_contact_page'><?php _e( 'Next Step', 'business-profile' ); ?></button>
						<button type="button" class='bpfwp-welcome-screen-previous-button' data-previousaction='set_contact_info'><?php _e( 'Previous Step', 'business-profile' ); ?></button>
						<div class='clear'></div>
					</div>
				</div>

				<div class='bpfwp-welcome-screen-box bpfwp-welcome-screen-create_contact_page' data-screen='create_contact_page'>
					<h2><button type="button" class="bpfwp-setup-section"><?php _e( '3. Prepare a Contact Page', 'business-profile' ); ?></button></h2>
					<div class='bpfwp-welcome-screen-box-content'>
						<p><?php _e( 'You can create a dedicated contact page below, or skip this step and add your contact schema to a page you\'ve already created manually.', 'business-profile' ); ?></p>
						<div class='bpfwp-welcome-screen-menu-page'>
							<p><label for="bpfwp-setup-existing-page"><?php esc_html_e( 'Select an existing public page, or prepare a draft', 'business-profile' ); ?></label><br><select id="bpfwp-setup-existing-page" class="bpfwp-public-search" data-kind="page"><option value="0"><?php esc_html_e( 'Reuse my selected page or prepare a draft', 'business-profile' ); ?></option></select></p>
							<div class='bpfwp-welcome-screen-add-contact-page-name bpfwp-welcome-screen-box-content-divs'><label for='bpfwp-setup-page-title'><?php _e( 'Page Title:', 'business-profile' ); ?></label><input id='bpfwp-setup-page-title' type='text' value='<?php esc_attr_e( 'Contact', 'business-profile' ); ?>' /></div>
							<button type="button" class='bpfwp-welcome-screen-add-contact-page-button'><?php esc_html_e( 'Prepare Contact Page', 'business-profile' ); ?></button>
						</div>
						<div class="bpfwp-welcome-clear"></div>
						<button type="button" class='bpfwp-welcome-screen-next-button' data-nextaction='create_schema'><?php _e( 'Next Step', 'business-profile' ); ?></button>
						<div class='clear'></div>
					</div>
				</div>

				<div class='bpfwp-welcome-screen-box bpfwp-welcome-screen-create_schema' data-screen='create_schema'>
					<h2><button type="button" class="bpfwp-setup-section"><?php _e( '4. Review and Publish', 'business-profile' ); ?></button></h2>
					<div class='bpfwp-welcome-screen-box-content'>
						<p><?php esc_html_e( 'Review the saved facts and selected page, then publish through the page editor. Additional schema rules are optional.', 'business-profile' ); ?></p>
						<div class='bpfwp-welcome-screen-create-schema-link-div'>
							<a href='post-new.php?post_type=<?php echo esc_attr( $bpfwp_controller->cpts->schema_cpt_slug ); ?>'><?php _e( 'Create a schema now', 'business-profile' ); ?></a>
						</div>
						<div class="bpfwp-welcome-clear"></div>
						<button type="button" class='bpfwp-welcome-screen-previous-button' data-previousaction='create_contact_page'><?php _e( 'Previous Step', 'business-profile' ); ?></button>
							<div class='bpfwp-welcome-screen-finish-button'><a href='admin.php?page=bpfwp-settings'><?php esc_html_e( 'Review Business Profile Settings', 'business-profile' ); ?></a></div>
						<div class='clear'></div>
					</div>
				</div>
		
				<div class='bpfwp-welcome-screen-skip-container'>
						<a href='admin.php?page=bpfwp-dashboard'><div class='bpfwp-welcome-screen-skip-button'><?php esc_html_e( 'Skip Setup', 'business-profile' ); ?></div></a>
				</div>
			</form>
		</div>

	<?php }
}


?>
