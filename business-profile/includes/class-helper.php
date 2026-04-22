<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'bpfwpHelper' ) ) {
/**
 * Class to provide helper functions.
 *
 * @since 5.1.0
 */
class bpfwpHelper {

  // Hold the class instance.
  private static $instance = null;

  // Links for the help button.
  private static $documentation_link = 'https://doc.fivestarplugins.com/plugins/business-profile/';
  private static $tutorials_link = 'https://www.youtube.com/playlist?list=PLEndQUuhlvSoOidQF7iRvstiKjOT4tX71';
  private static $faq_link = 'https://doc.fivestarplugins.com/plugins/business-profile/user/faq';
  private static $wp_forum_support_link = 'https://wordpress.org/support/plugin/business-profile/';
  private static $support_center_link = 'https://www.fivestarplugins.com/support-center/';

  // Values for when to trigger the help button to display.
  private static $post_types = array( 'location', 'schema' );
  private static $taxonomies = array();
  private static $additional_pages = array(
    'bpfwp-dashboard',
    'bpfwp-settings',
  );

  /**
   * The constructor is private to prevent initiation with outer code.
   */
  private function __construct() {}

  /**
   * The object is created from within the class itself only if the class has no instance.
   */
  public static function getInstance() {

    if ( self::$instance == null ) {
      self::$instance = new bpfwpHelper();
    }

    return self::$instance;
  }

  /**
   * Register Business Profile help with AIAA when available.
   *
   * @since 5.1.0
   */
  public static function aiaa_add_filter() {

    add_filter( 'ait_aiaa_third_party_information', array( __CLASS__, 'add_help_to_aiaa' ), 20, 2 );
  }

  /**
   * Add Business Profile help content to AIAA's third-party Help tab.
   *
   * @param array $items
   * @param array $context
   * @return array
   *
   * @since 5.1.0
   */
  public static function add_help_to_aiaa( $items, $context ) {

    $items   = is_array( $items ) ? $items : array();
    $context = is_array( $context ) ? $context : array();

    $screen_id = isset( $context['screen_id'] ) ? (string) $context['screen_id'] : '';
    $post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';

    if ( ! self::aiaa_matches_context( $screen_id, $post_type, $taxonomy ) ) { return $items; }

    $page_details = self::get_page_details_for_context( $context );

    $tutorial_links = array();
    if ( ! empty( $page_details['tutorials'] ) && is_array( $page_details['tutorials'] ) ) {

      foreach ( $page_details['tutorials'] as $tutorial ) {

        if ( empty( $tutorial['url'] ) || empty( $tutorial['title'] ) ) { continue; }

        $tutorial_links[] = array(
          'title' => (string) $tutorial['title'],
          'url'   => (string) $tutorial['url'],
        );
      }
    }

    $general_links = array();
    if ( ! empty( self::$documentation_link ) ) {
      $general_links[] = array(
        'title' => __( 'Documentation', 'business-profile' ),
        'url'   => self::$documentation_link,
      );
    }
    if ( ! empty( self::$tutorials_link ) ) {
      $general_links[] = array(
        'title' => __( 'YouTube Tutorials', 'business-profile' ),
        'url'   => self::$tutorials_link,
      );
    }
    if ( ! empty( self::$faq_link ) ) {
      $general_links[] = array(
        'title' => __( 'FAQ', 'business-profile' ),
        'url'   => self::$faq_link,
      );
    }
    if ( ! empty( self::$wp_forum_support_link ) ) {
      $general_links[] = array(
        'title' => __( 'WP Forum Support', 'business-profile' ),
        'url'   => self::$wp_forum_support_link,
      );
    }
    if ( ! empty( self::$support_center_link ) ) {
      $general_links[] = array(
        'title' => __( 'Support Center', 'business-profile' ),
        'url'   => self::$support_center_link,
      );
    }

    $help_links = array();
    if ( ! empty( $tutorial_links ) ) { $help_links[ __( 'Tutorials', 'business-profile' ) ] = $tutorial_links; }
    if ( ! empty( $general_links ) ) { $help_links[ __( 'General', 'business-profile' ) ] = $general_links; }

    $items[] = array(
      'id'              => 'bpfwp_help',
      'title'           => __( 'Business Profile Help', 'business-profile' ),
      'description'     => ! empty( $page_details['description'] ) ? '<p>' . esc_html( $page_details['description'] ) . '</p>' : '',
      'help_links'      => $help_links,
      'source'          => array(
        'type' => 'plugin',
        'name' => 'Business Profile',
        'slug' => 'business-profile',
      ),
      'target_callback' => array( __CLASS__, 'aiaa_target_callback' ),
      'priority'        => 20,
      'capability'      => 'manage_options',
      'icon'            => 'dashicons-editor-help',
    );

    return $items;
  }

  /**
   * AIAA advanced targeting callback.
   *
   * @param array $context
   * @param array $item
   * @return bool
   *
   * @since 5.1.0
   */
  public static function aiaa_target_callback( $context, $item ) {

    $context = is_array( $context ) ? $context : array();

    $screen_id = isset( $context['screen_id'] ) ? (string) $context['screen_id'] : '';
    $post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';

    return self::aiaa_matches_context( $screen_id, $post_type, $taxonomy );
  }

  /**
   * Shared matcher for AIAA context.
   *
   * @param string $screen_id
   * @param string $post_type
   * @param string $taxonomy
   * @return bool
   *
   * @since 5.1.0
   */
  private static function aiaa_matches_context( $screen_id, $post_type, $taxonomy ) {

    if ( ! empty( $post_type ) && in_array( $post_type, self::$post_types, true ) ) { return true; }

    if ( ! empty( $taxonomy ) && in_array( $taxonomy, self::$taxonomies, true ) ) { return true; }

    if ( ! empty( $screen_id ) && ! empty( self::$additional_pages ) ) {

      foreach ( self::$additional_pages as $slug ) {

        if ( empty( $slug ) ) { continue; }

        if ( strpos( $screen_id, $slug ) !== false ) { return true; }
      }
    }

    return false;
  }

  /**
   * Handle ajax requests in admin area for logged out users.
   *
   * @since 5.1.0
   */
  public static function admin_nopriv_ajax() {

    wp_send_json_error(
      array(
        'error' => 'loggedout',
        'msg'   => sprintf( __( 'You have been logged out. Please %slogin again%s.', 'business-profile' ), '<a href="' . wp_login_url( admin_url( 'admin.php?page=bpfwp-dashboard' ) ) . '">', '</a>' ),
      )
    );
  }

  /**
   * Handle ajax requests where an invalid nonce is passed with the request.
   *
   * @since 5.1.0
   */
  public static function bad_nonce_ajax() {

    wp_send_json_error(
      array(
        'error' => 'badnonce',
        'msg'   => __( 'The request has been rejected because it does not appear to have come from this site.', 'business-profile' ),
      )
    );
  }

  /**
   * Escapes PHP data being passed to JS, recursively.
   *
   * @since 5.1.0
   */
  public static function escape_js_recursive( $values ) {

    $return_values = array();

    foreach ( (array) $values as $key => $value ) {

      if ( is_array( $value ) ) {
        $value = bpfwpHelper::escape_js_recursive( $value );
      }
      elseif ( ! is_scalar( $value ) ) {
        continue;
      }
      else {
        $value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
      }

      $return_values[ $key ] = $value;
    }

    return $return_values;
  }

  /**
   * Get page details for the current AIAA context.
   *
   * @param array $context
   * @return array
   */
  public static function get_page_details_for_context( $context ) {
    $context   = is_array( $context ) ? $context : array();
    $request   = isset( $context['request'] ) && is_array( $context['request'] ) ? $context['request'] : array();
    $page      = isset( $request['page'] ) ? sanitize_text_field( $request['page'] ) : '';
    $tab       = isset( $request['tab'] ) ? sanitize_text_field( $request['tab'] ) : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? sanitize_text_field( $context['taxonomy'] ) : '';
    $post_type = isset( $context['post_type'] ) ? sanitize_text_field( $context['post_type'] ) : '';
    $screen_id = isset( $context['screen_id'] ) ? sanitize_text_field( $context['screen_id'] ) : '';

    if ( ! $page && ! empty( $screen_id ) ) {
      foreach ( self::$additional_pages as $slug ) {
        if ( strpos( (string) $screen_id, $slug ) !== false ) {
          $page = $slug;
          break;
        }
      }
    }

    return self::get_page_details_by_values( $page, $tab, $taxonomy, $post_type, $screen_id );
  }

  /**
   * Resolve page details from the available context values.
   *
   * @param string $page
   * @param string $tab
   * @param string $taxonomy
   * @param string $post_type
   * @param string $screen_id
   * @return array
   */
  private static function get_page_details_by_values( $page, $tab, $taxonomy, $post_type, $screen_id ) {
    $page_details = array(
      'bpfwp-dashboard' => array(
        'description' => __( 'A quick overview of your locations at a glance. Also provides access to upgrade options and helpful links to documentation, support, and tutorial videos to help you get the most out of the plugin.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/contact-card/',
            'title' => 'Contact Card'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/multiple-locations/',
            'title' => 'Multiple Locations'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/blocks-shortcodes/',
            'title' => 'Block and Shortcode'
          ),
        )
      ),
      'edit-location' => array(
        'description' => __( 'View and manage all your locations. Filter by status or date, and search by name. The overview table displays each location’s title and published date.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/multiple-locations/',
            'title' => 'Multiple Locations'
          ),
        )
      ),
      'location' => array(
        'description' => __( 'Create or edit a location. Add a title and description, then configure opening hours, exceptions, schema type, and contact details specific to this location.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/multiple-locations/',
            'title' => 'Multiple Locations'
          ),
        )
      ),
      'edit-schema' => array(
        'description' => __( 'View and manage all your schemas. Filter by status or date, and search by name. The overview table displays each schema’s title and published date.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/schema/create',
            'title' => 'Create Schema Rules'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/schema/woocommerce',
            'title' => 'WooCommerce Schema'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/schema/posts',
            'title' => 'Structured Data for Posts'
          ),
        )
      ),
      'schema' => array(
        'description' => __( 'Create or edit a schema rule. Add a title, then select the schema type and where it should be applied (specific content, post types, or site-wide). After publishing, configure the available fields for the selected schema.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/schema/create',
            'title' => 'Create Schema Rules'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/schema/woocommerce',
            'title' => 'WooCommerce Schema'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/schema/posts',
            'title' => 'Structured Data for Posts'
          ),
        )
      ),
      'bpfwp-settings' => array(
        'description' => __( 'Configure your core business information, including schema type, contact details, and opening hours. These settings define your default structured data and contact card across your site.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/settings/google-maps-api-key',
            'title' => 'Set up your Google Maps API Key'
          ),
        )
      ),
      'bpfwp-settings-bpfwp-basic' => array(
        'description' => __( 'Configure your core business information, including schema type, contact details, and opening hours. These settings define your default structured data and contact card across your site.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/settings/google-maps-api-key',
            'title' => 'Set up your Google Maps API Key'
          ),
        )
      ),
      'bpfwp-settings-bpfwp-premium-tab' => array(
        'description' => __( 'Configure advanced features for your contact card and structured data, including element ordering, custom fields, schema enhancements, and supported plugin integrations.', 'business-profile' ),
        'tutorials'   => array()
      ),
      'bpfwp-settings-bpfwp-labelling-tab' => array(
        'description' => __( 'Customize the text labels used throughout your contact card and opening hours display. These labels control how information such as opening hours, navigation actions, and day names appear to visitors on the front end.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/labelling/',
            'title' => 'Labelling and Translation'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/labelling/translating',
            'title' => 'Translating'
          ),
        )
      ),
      'bpfwp-settings-bpfwp-styling-tab' => array(
        'description' => __( 'Customize the layout and visual appearance of your contact card. Control how elements are arranged, as well as typography, colors, icons, and other display settings.', 'business-profile' ),
        'tutorials'   => array()
      ),
      'bpfwp-settings-bpfwp-api-tab' => array(
        'description' => __( 'Manage API access for the Five Star Restaurant Manager Mobile App and view error logs.', 'business-profile' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/business-profile/user/fsrm/',
            'title' => 'Five Star Restaurant Manager Mobile App'
          ),
        )
      ),
    );

    $page = $page ? sanitize_text_field( $page ) : '';
    $tab = $tab ? sanitize_text_field( $tab ) : '';
    $taxonomy = $taxonomy ? sanitize_text_field( $taxonomy ) : '';
    $post_type = $post_type ? sanitize_text_field( $post_type ) : '';
    $screen_id = $screen_id ? sanitize_text_field( $screen_id ) : '';
    
    if ( $page && $tab && isset( $page_details[ $page . '-' . $tab ] ) ) { return $page_details[ $page . '-' . $tab ]; }

    if ( $page && isset( $page_details[ $page ] ) ) { return $page_details[ $page ]; }

    if ( $screen_id && isset( $page_details[ $screen_id ] ) ) { return $page_details[ $screen_id ]; }

    if ( $taxonomy && isset( $page_details[ $taxonomy ] ) ) { return $page_details[ $taxonomy ]; }

    if ( $post_type && $screen_id && strpos( $screen_id, 'edit-' ) === 0 && isset( $page_details[ 'edit-' . $post_type ] ) ) {
      return $page_details[ 'edit-' . $post_type ];
    }

    if ( $post_type && isset( $page_details[ $post_type ] ) ) { return $page_details[ $post_type ]; }

    return array( 'description' => '', 'tutorials' => array() );
  }
}

add_action( 'plugins_loaded', array( 'bpfwpHelper', 'aiaa_add_filter' ), 20 );

}
