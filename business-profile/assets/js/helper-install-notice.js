jQuery( document ).ready( function( $ ) {

  jQuery(document).on( 'click', '.bpfwp-helper-install-notice .notice-dismiss', function( event ) {
    var data = jQuery.param({
      action: 'bpfwp_hide_helper_notice',
      nonce: bpfwp_helper_notice.nonce
    });

    jQuery.post( ajaxurl, data, function() {} );
  });
});

/* NEW PLUGIN NOTICE */

jQuery( document ).ready( function( $ ) {

  jQuery(document).on( 'click', '.ait-aiaa-new-plugin-notice .notice-dismiss', function( event ) {
    var data = jQuery.param({
      action: 'bpfwp_hide_new_plugin_notice',
      plugin: 'ait_aiaa',
      nonce: bpfwp_helper_notice.nonce
    });

    jQuery.post( ajaxurl, data, function() {} );
  });
});