<?php
/**
 * Class to handle schema custom post types and general schema settings.
 *
 * @package   BusinessProfile
 * @copyright Copyright (c) 2016, Five Star Plugins
 * @license   GPL-2.0+
 * @since     2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'bpfwpSchemasManager' ) ) :

	/**
	 * Class to handle schema custom post types and general schema settings for Business Profile
	 *
	 * @since 2.0.0
	 */
	class bpfwpSchemasManager {

		/**
		 * Default values for settings
		 *
		 * @since  2.0.0
		 * @access public
		 * @var    array
		 */
		public $schema_cpts = array();

		/**
		 * Initialize the class and register hooks.
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function __construct() {

			add_action( 'init', array( $this, 'create_schema_cpts' ) );

			add_action( 'wp_footer', array( $this, 'output_ld_json_content' ) );
			add_action( 'admin_menu', array( $this, 'source_inventory_menu' ), 20 );
			add_action( 'admin_notices', array( $this, 'save_notice' ) );
		}

		public function save_notice() {
			$error = get_transient( 'bpfwp_schema_error_' . get_current_user_id() );
			if ( $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . ' ' . esc_html__( 'The previous schema values were retained.', 'business-profile' ) . '</p></div>';
				delete_transient( 'bpfwp_schema_error_' . get_current_user_id() );
			}
		}

		public function source_inventory_menu() {
			$hook = add_submenu_page( null, __( 'Schema Source Checks', 'business-profile' ), __( 'Schema Source Checks', 'business-profile' ), 'manage_options', 'bpfwp-source-checks', array( $this, 'source_inventory' ) );
			if ( $hook ) {
				add_action( 'load-' . $hook, array( $this, 'set_source_inventory_title' ) );
			}
		}
		public function set_source_inventory_title() {
			$GLOBALS['title'] = __( 'Schema Source Checks', 'business-profile' );
		}

		/** Bounded, administrator-only inventory; rejected data is never resolved. */
		public function source_inventory() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			global $bpfwp_controller;
			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-source-policy.php';
			$page  = isset( $_GET['bpfwp_page'] ) ? max( 1, absint( $_GET['bpfwp_page'] ) ) : 1;
			$query = new WP_Query(
				array(
					'post_type'      => $bpfwp_controller->cpts->schema_cpt_slug,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 20,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			echo '<div class="wrap bpfwp-diagnostics"><h1>' . esc_html__( 'Schema Source Checks', 'business-profile' ) . '</h1>';
			echo '<p>' . esc_html__( 'Unregistered data sources are blocked before reading data. Stored rules are retained. Edit affected defaults to select a public source or an intentional literal value. This check does not verify public output.', 'business-profile' ) . '</p><section class="bpfwp-check-panel"><h2>' . esc_html__( 'Saved rule source eligibility', 'business-profile' ) . '</h2><p>' . esc_html__( 'This screen checks whether saved defaults use registered sources. It does not read or display source values, or prove that a rule appears on a public page.', 'business-profile' ) . '</p>';
			if ( ! $query->posts ) {
				echo '<p>' . esc_html__( 'No saved schema rules were found on this page. There are no rule sources to check here; this does not verify public output.', 'business-profile' ) . '</p>';
			}
			echo '<dl class="bpfwp-check-list">';
			foreach ( $query->posts as $rule ) {
				$data   = get_post_meta( $rule->ID, 'bpfwp-schema-data', true );
				$fields = bpfwpSchemaSourcePolicy::rejected_fields( isset( $data['field_defaults'] ) ? $data['field_defaults'] : array() );
				/* translators: %d: Rule ID. */
				echo '<div><dt><a href="' . esc_url( get_edit_post_link( $rule->ID ) ) . '">' . esc_html( sprintf( __( 'Rule #%d', 'business-profile' ), $rule->ID ) ) . '</a></dt><dd>';
				/* translators: %d: Number of defaults requiring correction. */
				echo esc_html( $fields ? sprintf( __( '%d stored defaults use an unregistered source. Open the rule to correct the marked fields.', 'business-profile' ), count( $fields ) ) : __( 'No unregistered stored defaults found.', 'business-profile' ) );
				echo '</dd></div>';
			}
			echo '</dl></section><nav aria-label="' . esc_attr__( 'Schema rule pages', 'business-profile' ) . '">';
			if ( $page > 1 ) {
				echo '<a href="' . esc_url( add_query_arg( 'bpfwp_page', $page - 1 ) ) . '">' . esc_html__( 'Previous', 'business-profile' ) . '</a> ';
			}
			if ( $page < $query->max_num_pages ) {
				echo '<a href="' . esc_url( add_query_arg( 'bpfwp_page', $page + 1 ) ) . '">' . esc_html__( 'Next', 'business-profile' ) . '</a>';
			}
			echo '</nav></div>';
		}

		/**
		 * Initialize the class and register hooks.
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function create_schema_cpts() {
			global $bpfwp_controller;

			if ( ! is_a( $bpfwp_controller, 'bpfwpInit' ) ) {
				return;
			}

			require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-cpt.php';
			//require_once BPFWP_PLUGIN_DIR . '/includes/class-schema-cpt-post_type.php';

			$schema_cpt_posts = get_posts(
				array(
					'posts_per_page' => -1,
					'post_type'      => $bpfwp_controller->cpts->schema_cpt_slug,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			foreach ( $schema_cpt_posts as $schema_post ) {
				$schema_data = get_post_meta( $schema_post->ID, 'bpfwp-schema-data', true );

				$schema_target_type  = isset( $schema_data['schema_target_type'] ) ? $schema_data['schema_target_type'] : '';
				$schema_target_value = isset( $schema_data['schema_target_value'] ) ? $schema_data['schema_target_value'] : '';
				$schema_type         = isset( $schema_data['schema_type'] ) ? $schema_data['schema_type'] : '';
				$field_defaults      = isset( $schema_data['field_defaults'] ) ? $schema_data['field_defaults'] : '';
				$default_display     = isset( $schema_data['default_display'] ) ? $schema_data['default_display'] : '';

				$this->schema_cpts[ $schema_post->ID ] = new bpfwpSchemaCPT(
					array(
						'post_id'         => $schema_post->ID,
						'target_type'     => $schema_target_type,
						'target_value'    => $schema_target_value,
						'schema_type'     => $schema_type,
						'field_defaults'  => $field_defaults,
						'default_display' => $default_display,
					)
				);
			}
		}

		/**
		 * Array of organization schema type options
		 *
		 * @since  2.0.0 public
		 * @return array A filtered list of schema types.
		 * @to-do: create schema classes for the other types needed
		 */
		public function get_schema_organization_types() {
			return apply_filters(
				'bp_schema_types',
				array(
					'Organization'                	=> 'Organization',
					'Airline'					  	=> 'Airline',
					'Consortium'                  	=> 'Consortium',
					'Corporation'                 	=> 'Corporation',
					'EducationalOrganization'     	=> 'Educational Organization',
					'CollegeOrUniversity'         	=> '- College or University',
					'ElementarySchool'	         	=> '- Elementary School',
					'HighSchool'         			=> '- High School',
					'MiddleSchool'         			=> '- Middle School',
					'Prechool'         				=> '- Prechool',
					'School'        			 	=> '- School',
					'FundingScheme'				  	=> 'Funding Scheme',
					'GovernmentOrganization'      	=> 'Government Organization',
					'LibrarySystem'               	=> 'Library System',
					'LocalBusiness'               	=> 'Local Business',
					'AnimalShelter'               	=> '- Animal Shelter',
					'AutomotiveBusiness'          	=> '- Automotive Business',
					'AutoBodyShop'          		=> '--- AutoBody Shop',
					'AutoDealer'          			=> '--- Auto Dealer',
					'AutoPartsStore'          		=> '--- Auto Parts Store',
					'AutoRental'          			=> '--- Auto Rental',
					'AutoRepair'          			=> '--- Auto Repair',
					'AutoWash'          			=> '--- Auto Wash',
					'GasStation'          			=> '--- Gas Station',
					'MotorcycleDealer'          	=> '--- Motorcycle Dealer',
					'MotorcycleRepair'          	=> '--- Motorcycle Repair',
					'ChildCare'                   	=> '- Child Care',
					'Dentist'                   	=> '- Dentist',
					'DryCleaningOrLaundry'        	=> '- Dry Cleaning or Laundry',
					'EmergencyService'            	=> '- Emergency Service',
					'FirsStation'          			=> '--- Fire Station',
					'Hospital'          			=> '--- Hospital',
					'PoliceStation'          		=> '--- Police Station',
					'EmploymentAgency'            	=> '- Employment Agency',
					'EntertainmentBusiness'       	=> '- Entertainment Business',
					'AdultEntertainment'          	=> '--- Adult Entertainment',
					'AmusementPark'          		=> '--- Amusement Park',
					'ArtGallery'          			=> '--- Art Gallery',
					'Casino'          				=> '--- Casino',
					'ComedyClub'          			=> '--- Comedy Club',
					'MovieTheater'          		=> '--- Movie Theater',
					'NightClub'          			=> '--- Night Club',
					'FinancialService'            	=> '- Financial Service',
					'AccountingService'          	=> '--- Accounting Service',
					'AutomatedTeller'          		=> '--- Automated Teller',
					'BankOrCreditUnion'          	=> '--- Bank or Credit Union',
					'InsuranceAgency'          		=> '--- Insurance Agency',
					'FoodEstablishment'           	=> '- Food Establishment',
					'Bakery'          				=> '--- Bakery',
					'BarOrPub'          			=> '--- Bar or Pub',
					'Brewery'          				=> '--- Brewery',
					'CafeOrCoffeeShop'          	=> '--- Cafe or Coffee Shop',
					'Distillery'          			=> '--- Distillery',
					'FastFoodRestaurant'        	=> '--- Fast Food Restaurant',
					'IceCreamShop'          		=> '--- Ice Cream Shop',
					'Restaurant'          			=> '--- Restaurant',
					'Winery'          				=> '--- Winery',
					'GovernmentOffice'            	=> '- Government Office',
					'PostOffice'          			=> '--- Post Office',
					'HealthAndBeautyBusiness'     	=> '- Health and Beauty Business',
					'BeautySalon'          			=> '--- Beauty Salon',
					'DaySpa'          				=> '--- Day Spa',
					'HairSalon'          			=> '--- Hair Salon',
					'HealthClub'          			=> '--- Health Club',
					'NailSalon'          			=> '--- Nail Salon',
					'TattooParlor'          		=> '--- Tattoo Parlor',
					'HomeAndConstructionBusiness' 	=> '- Home and Construction Business',
					'Electrician'          			=> '--- Electrician',
					'GeneralContractor'          	=> '--- General Contractor',
					'HVACBusiness'          		=> '--- HVAC Business',
					'HousePainter'          		=> '--- House Painter',
					'Locksmith'          			=> '--- Locksmith',
					'MovingCompany'          		=> '--- Moving Company',
					'Plumber'          				=> '--- Plumber',
					'RoofingContractor'          	=> '--- Roofing Contractor',
					'InternetCafe'                	=> '- Internet Cafe',
					'LegalService'                	=> '- Legal Service',
					'Attorney'          			=> '--- Attorney',
					'Notary'          				=> '--- Notary',
					'Library'                     	=> '- Library',
					'LodgingBusiness'             	=> '- Lodging Business',
					'BedAndBreakfast'          		=> '--- Bed and Breakfast',
					'Campground'          			=> '--- Campground',
					'Hostel'          				=> '--- Hostel',
					'Hotel'          				=> '--- Hotel',
					'Motel'          				=> '--- Motel',
					'Resort'          				=> '--- Resort',
					'MedicalBusiness'         		=> '- Medical Business',
					'CommunityHealth'          		=> '--- Community Health',
					'Dentist'          				=> '--- Dentist',
					'Dermatology'          			=> '--- Dermatology',
					'DietNutrition'          		=> '--- Diet Nutrition',
					'Emergency'          			=> '--- Emergency',
					'Geriatric'          			=> '--- Geriatric',
					'Gynecologic'          			=> '--- Gynecologic',
					'MedicalClinic'          		=> '--- Medical Clinic',
					'Midwifery'          			=> '--- Midwifery',
					'Nursing'          				=> '--- Nursing',
					'Obstetric'          			=> '--- Obstetric',
					'Oncologic'          			=> '--- Oncologic',
					'Optician'          			=> '--- Optician',
					'Optometric'          			=> '--- Optometric',
					'Otolaryngologic'          		=> '--- Otolaryngologic',
					'Pediatric'          			=> '--- Pediatric',
					'Pharmacy'          			=> '--- Pharmacy',
					'Physiotherapy'          		=> '--- Physiotherapy',
					'PlasticSurgery'          		=> '--- Plastic Surgery',
					'Podiatric'          			=> '--- Podiatric',
					'PrimaryCare'          			=> '--- Primary Care',
					'Psychiatric'          			=> '--- Psychiatric',
					'PublicHealth'          		=> '--- Public Health',
					'ProfessionalService'          	=> '- Professional Service',
					'RadioStation'                	=> '- Radio Station',
					'RealEstateAgent'             	=> '- Real Estate Agent',
					'RecyclingCenter'             	=> '- Recycling Center',
					'SelfStorage'                 	=> '- Self Storage',
					'ShoppingCenter'                => '- Shopping Center',
					'SportsActivityLocation'      	=> '- Sports Activity Location',
					'BowlingAlley'          		=> '--- Bowling Alley',
					'ExerciseGym'          			=> '--- Exercise Gym',
					'GolfCourse'          			=> '--- Golf Course',
					'HealthClub'          			=> '--- Health Club',
					'PublicSwimmingPool'          	=> '--- Public Swimming Pool',
					'SkiResort'          			=> '--- Ski Resort',
					'SportsClub'          			=> '--- Sports Club',
					'StadiumOrArena'          		=> '--- Stadium or Arena',
					'TennisComplex'          		=> '--- Tennis Complex',
					'Store'                       	=> '- Store',
					'AutoPartsStore'          		=> '--- Auto Parts Store',
					'BikeStore'          			=> '--- Bike Store',
					'BookStore'          			=> '--- Book Store',
					'ClothingStore'          		=> '--- Clothing Store',
					'ComputerStore'          		=> '--- Computer Store',
					'ConvenienceStore'          	=> '--- Convenience Store',
					'DepartmentStore'          		=> '--- Department Store',
					'ElectronicsStore'          	=> '--- Electronics Store',
					'Florist'          				=> '--- Florist',
					'FurnitureSotre'          		=> '--- Furniture Store',
					'GardenStore'          			=> '--- Garden Store',
					'GroceryStore'          		=> '--- Grocery Store',
					'HardwareStore'          		=> '--- Hardware Store',
					'HobbyShop'          			=> '--- Hobby Shop',
					'HomeGoodsStore'          		=> '--- Home Goods Store',
					'JewelryStore'          		=> '--- Jewelry Store',
					'LiquorStore'          			=> '--- Liquor Store',
					'MensClothingStore'          	=> '--- Mens Clothing Store',
					'MobilePhoneStore'          	=> '--- Mobile Phone Store',
					'MovieRentalStore'          	=> '--- Movie Rental Store',
					'MusicStore'          			=> '--- Music Store',
					'OfficeEquipmentStore'          => '--- Office Equipment Store',
					'OutletStore'          			=> '--- Outlet Store',
					'PawnShop'          			=> '--- Pawn Shop',
					'PetStore'          			=> '--- Pet Store',
					'ShoeStore'          			=> '--- Shoe Store',
					'SportingGoodsStore'          	=> '--- Sporting Goods Store',
					'TireShop'          			=> '--- Tire Shop',
					'ToyStore'          			=> '--- Toy Store',
					'WholesaleStore'          		=> '--- Wholesale Store',
					'TelevisionStation'    			=> '- Television Station',
					'TouristInformationCenter'    	=> '- Tourist Information Center',
					'TravelAgency'                	=> '- Travel Agency',
					'MedicalOrganization'          	=> 'Medical Organization',
					'Dentist'                		=> '- Dentist',
					'DiagnosticLab'                	=> '- Diagnostic Lab',
					'Hospital'                		=> '- Hospital',
					'MedicalClinic'                	=> '- Medical Clinic',
					'Pharmacy'                		=> '- Pharmacy',
					'Physician'                		=> '- Physician',
					'VeterinaryCare'                => '- Veterinary Care',
					'NGO'                         	=> 'NGO',
					'NewsMediaOrganization'        	=> 'News Media Organization',
					'PerformingGroup'             	=> 'PerformingGroup',
					'DanceGroup'                	=> '- Dance Group',
					'MusicGroup'                	=> '- Music Group',
					'TheaterGroup'                	=> '- Theater Group',
					'Project'             			=> 'Project',
					'FundingAgency'                	=> '- Funding Agency',
					'ResearchProject'             	=> '- Research Project',
					'SportsOrganization'           	=> 'Sports Organization',
					'SportsTeam'                	=> '- Sports Team',
					'WorkersUnion'             		=> 'Workers Union',
				)
			);
		}

		/**
		 * Array of schema type options
		 *
		 * @since  2.0.0 public
		 * @return array A filtered list of schema types.
		 * @to-do: create schema classes for the other types needed
		 */
		public function get_schema_rich_results_types() {
			return apply_filters(
				'bp_rich_results_schema_types',
				array(
					'Article' 							=> 'Article',
					'Book' 								=> 'Book',
					'Breadcrumb' 						=> 'Breadcrumb List',
					'Carousel' 							=> 'Carousel', // @type is ItemList
					'Course' 							=> 'Course',
					'CriticReview'						=> 'Critic Review', // @type is Review
					'Dataset' 							=> 'Dataset',
					'EmployerAggregateRating' 			=> 'Employer Aggregate Rating',
					'Event' 							=> 'Event',
					'FactCheck'							=> 'Fact Check',
					'FAQPage' 							=> 'FAQ',
					'HowTo' 							=> 'How-to',
					'JobPosting'						=> 'Job Posting',
					'LocalBusinessListing'				=> 'Local Business Listing',
					'Logo'								=> 'Logo', // @type is Organization
					'Movie'								=> 'Movie',
					'Occupation'						=> 'Occupation',
					'Product'							=> 'Product',
					'QAPage'							=> 'Q & A',
					'Recipe'							=> 'Recipe', // @type is ItemList
					'ReviewSnippet'						=> 'Review Snippet',
					'SitelinksSearchbox'				=> 'Sitelinks Searchbox', // Google modifies this slightly
					'SoftwareApp'						=> 'Software App',
					'SubscriptionAndPaywalledContent'	=> 'Subscription and Paywalled Content',
					'Video'								=> 'Video',
				)
			);
		}

		/**
		 * Array of schema type options
		 *
		 * @since  2.0.0
		 * @access public
		 * @return void
		 */
		public function output_ld_json_content() {
			$ld_json = array();

			$ld_json_ouptut = apply_filters( 'bpfwp_ld_json_output', $ld_json );

			if ( ! empty( $ld_json_ouptut ) ) {
				echo '<script type="application/ld+json" class="bpfwp-ld-json-data">';
				echo wp_json_encode( $ld_json_ouptut, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
				echo '</script>';
			}
		}
	}
endif;
