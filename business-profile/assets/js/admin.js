/*NEW DASHBOARD MOBILE MENU AND WIDGET TOGGLING*/
jQuery(document).ready(function($){
	$('#bpfwp-dash-mobile-menu-open').click(function(){
		$('.bpfwp-admin-header-menu .nav-tab:nth-of-type(1n+2)').toggle();
		$('#bpfwp-dash-mobile-menu-up-caret').toggle();
		$('#bpfwp-dash-mobile-menu-down-caret').toggle();
		return false;
	});
	$(function(){
		$(window).resize(function(){
			if($(window).width() > 800){
				$('.bpfwp-admin-header-menu .nav-tab:nth-of-type(1n+2)').show();
			}
			else{
				$('.bpfwp-admin-header-menu .nav-tab:nth-of-type(1n+2)').hide();
				$('#bpfwp-dash-mobile-menu-up-caret').hide();
				$('#bpfwp-dash-mobile-menu-down-caret').show();
			}
		}).resize();
	});	
	$('#bpfwp-dashboard-support-widget-box .bpfwp-dashboard-new-widget-box-top').click(function(){
		$('#bpfwp-dashboard-support-widget-box .bpfwp-dashboard-new-widget-box-bottom').toggle();
		$('#bpfwp-dash-mobile-support-up-caret').toggle();
		$('#bpfwp-dash-mobile-support-down-caret').toggle();
	});
	$('#bpfwp-dashboard-optional-table .bpfwp-dashboard-new-widget-box-top').click(function(){
		$('#bpfwp-dashboard-optional-table .bpfwp-dashboard-new-widget-box-bottom').toggle();
		$('#bpfwp-dash-optional-table-up-caret').toggle();
		$('#bpfwp-dash-optional-table-down-caret').toggle();
	});
});

/*LOCK BOXES*/
jQuery(document).ready(function($){
	$(function(){
		$(window).resize(function(){
			$('.bpfwp-premium-options-table-overlay').each(function(){
				var eachProTableOverlay = $(this);
				var associatedTable = eachProTableOverlay.next();
				var tableWidth = associatedTable.outerWidth(true);
				associatedTable.css('min-height', '240px');
				var tableHeight = associatedTable.outerHeight();
				var tablePosition = associatedTable.position();
				var tableLeft = tablePosition.left; 
				var tableTop = tablePosition.top; 
				eachProTableOverlay.css('width', tableWidth+'px');
				eachProTableOverlay.css('height', tableHeight+'px');
				eachProTableOverlay.css('left', tableLeft+'px');
				eachProTableOverlay.css('top', tableTop+'px');
			});
		}).resize();
	});	
});

//OPTIONS PAGE YES/NO TOGGLE SWITCHES
jQuery(document).ready(function($){
	$('.bpfwp-admin-option-toggle').on('change', function() {
		var Input_Name = $(this).data('inputname'); console.log(Input_Name);
		if ($(this).is(':checked')) {
			$('input[name="' + Input_Name + '"][value="1"]').prop('checked', true).trigger('change');
			$('input[name="' + Input_Name + '"][value=""]').prop('checked', false);
		}
		else {
			$('input[name="' + Input_Name + '"][value="1"]').prop('checked', false).trigger('change');
			$('input[name="' + Input_Name + '"][value=""]').prop('checked', true);
		}
	});
});


// About Us Page
jQuery( document ).ready( function( $ ) {

	jQuery( '.bpfwp-about-us-tab-menu-item' ).on( 'click', function() {

		jQuery( '.bpfwp-about-us-tab-menu-item' ).removeClass( 'bpfwp-tab-selected' );
		jQuery( '.bpfwp-about-us-tab' ).addClass( 'bpfwp-hidden' );

		var tab = jQuery( this ).data( 'tab' );

		jQuery( this ).addClass( 'bpfwp-tab-selected' );
		jQuery( '.bpfwp-about-us-tab[data-tab="' + tab + '"]' ).removeClass( 'bpfwp-hidden' );
	} );

	jQuery( '.bpfwp-about-us-send-feature-suggestion' ).on( 'click', function() {

		var feature_suggestion = jQuery( '.bpfwp-about-us-feature-suggestion textarea' ).val();
		var email_address = jQuery( '.bpfwp-about-us-feature-suggestion input[name="feature_suggestion_email_address"]' ).val();
	
		var params = {};

		params.nonce  				= bpfwp_php_admin_data.nonce;
		params.action 				= 'bpfwp_send_feature_suggestion';
		params.feature_suggestion	= feature_suggestion;
		params.email_address 		= email_address;

		var data = jQuery.param( params );
		jQuery.post( ajaxurl, data, function() {} );

		jQuery( '.bpfwp-about-us-feature-suggestion' ).prepend( '<p>Thank you, your feature suggestion has been submitted.' );
	} );
} );

// Automatically set the ID for each new custom field
jQuery(document).ready(function($){

	$( '.sap-new-admin-add-button' ).on( 'click', function() {

		setTimeout( ewd_ufaq_field_added_handler, 500);
	});
});

function ewd_ufaq_field_added_handler() {

	var highest = 0;
	jQuery( '.sap-infinite-table input[data-name="id"]' ).each( function() {
		highest = Math.max( highest, this.value );
	});

	jQuery( '.sap-infinite-table  tbody tr:last-of-type span.sap-infinite-table-hidden-value' ).html( highest + 1 );
	jQuery( '.sap-infinite-table  tbody tr:last-of-type input[data-name="id"]' ).val( highest + 1 );
}


//SETTINGS PREVIEW SCREENS

jQuery( document ).ready( function() {

	jQuery( '.bpfwp-settings-preview' ).prevAll( 'h2' ).hide();
	jQuery( '.bpfwp-settings-preview' ).prevAll( '.sap-tutorial-toggle' ).hide();
	jQuery( '.bpfwp-settings-preview .sap-tutorial-toggle' ).hide();
});
