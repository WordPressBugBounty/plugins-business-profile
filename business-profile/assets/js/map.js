/* global google */
/**
 * Front-end JavaScript for Business Profile maps
 *
 * @copyright Copyright (c) 2016, Theme of the Crop
 * @license   GPL-2.0+
 * @since     0.0.1
 */

var bpfwp_map = bpfwp_map || {};

/**
 * Set up a map using the Google Maps API and data attributes added to `.bp-map`
 * elements on a given page.
 *
 * @uses  Google Maps API (https://developers.google.com/maps/web/)
 * @since 1.1.0
 */
function bpInitializeMap() {
	'use strict';

	bpfwp_map.maps = bpfwp_map.maps || {};
	bpfwp_map.info_windows = bpfwp_map.info_windows || {};

	jQuery( '.bp-map' ).each( function() {
		var id = jQuery( this ).attr( 'id' );
		var data = jQuery( this ).data();
		if (data.bpfwpInitialized) { return; }
		if (bpfwp_map.lazy_ready && !data.bpfwpVisible) { return; }
		jQuery(this).data('bpfwpInitialized', true);
		data.address = String(data.address || '');

		data.addressURI = encodeURIComponent( data.address.replace( /(<([^>]+)>)/ig, ', ' ) );

		// Google Maps API v3
		if ( 'undefined' !== typeof google && google.maps && Number.isFinite(Number(data.lat)) && Number.isFinite(Number(data.lon)) ) {
			data.addressURI              = encodeURIComponent( data.address.replace( /(<([^>]+)>)/ig, ', ' ) );
			var options = Object.assign({}, bpfwp_map.map_options || {});
			options.center = new google.maps.LatLng( data.lat, data.lon );
			if ( typeof options.zoom === 'undefined' ) {
				options.zoom = 15;
			}
			bpfwp_map.maps[ id ] = new google.maps.Map( document.getElementById( id ), options );

			var content = document.createElement('div');
			content.className = 'bp-map-info-window';
			[String(data.name || ''), data.address, String(data.phone || '')].forEach(function (text) {
				var paragraph = document.createElement('p');
				paragraph.textContent = text;
				content.appendChild(paragraph);
			});

			var link = document.createElement('a');
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.href = 'https://maps.google.com/maps?saddr=current+location&daddr=' + data.addressURI;
			link.textContent = bpfwp_map.strings.getDirections;
			content.appendChild(link);

			bpfwp_map.info_windows[ id ] = new google.maps.InfoWindow({
				position: options.center,
				content: content
			});
			bpfwp_map.info_windows[ id ].open( bpfwp_map.maps[ id ] );

			// Trigger an intiailized event on this dom element for third-party code
			jQuery( this ).trigger( 'bpfwp.map_initialized', [ id, bpfwp_map.maps[id], bpfwp_map.info_windows[id] ] );

		// Google Maps iframe embed (fallback if no lat/lon data available)
		} else if ( '' !== data.address ) {
			var bpMapIframe = document.createElement( 'iframe' );

			bpMapIframe.frameBorder = 0;
			bpMapIframe.title = String(data.name || data.address);
			bpMapIframe.loading = 'lazy';
			bpMapIframe.style.width = '100%';
			bpMapIframe.style.height = '100%';

			if ( '' !== data.name ) {
				data.address = data.name + ',' + data.address;
			}

			bpMapIframe.src = '//maps.google.com/maps?output=embed&q=' + encodeURIComponent( data.address );
			bpMapIframe.src = '//maps.google.com/maps?output=embed&q=' + data.addressURI;

			jQuery( this ).html( bpMapIframe );

			// Trigger an intiailized event on this dom element for third-party code
			jQuery( this ).trigger( 'bpfwp.map_initialized_in_iframe', [ jQuery( this ) ] );
		}
	});
}

/**
 * Backwards-compatable alias function.
 *
 * @since 1.1.0
 */
function bp_initialize_map() {
	bpInitializeMap();
}

jQuery( document ).ready( function() {
	'use strict';

	// Allow developers to override the maps api loading and initializing.
	if ( ! bpfwp_map.autoload_google_maps ) {
		return;
	}
	if (!document.querySelector('.bp-map')) { return; }
	function loadMaps() {
	// Load Google Maps API and initialize maps.
	if ( 'undefined' === typeof google || 'undefined' === typeof google.maps ) {
		if (bpfwp_map.loading) { return; }
		bpfwp_map.loading = true;
		var bpMapScript = document.createElement( 'script' );
		bpMapScript.type = 'text/javascript';
		bpMapScript.onerror = function () {
			bpfwp_map.loading = false;
			jQuery('.bp-map').each(function () {
				if (!jQuery(this).data('bpfwpInitialized')) {
					jQuery(this).text(bpfwp_map.strings.loadError).attr('role', 'status');
				}
			});
		};
		bpMapScript.src = '//maps.googleapis.com/maps/api/js?v=3.exp&callback=bp_initialize_map&loading=async';

		if ( 'undefined' !== typeof bpfwp_map.google_maps_api_key ) {
			bpMapScript.src += '&key=' + encodeURIComponent(bpfwp_map.google_maps_api_key);
		}

		document.body.appendChild( bpMapScript );
	} else {
		// If the API is already loaded (eg - by a third-party theme or plugin),
		// just initialize the map.
		bp_initialize_map();
	}
	}
	if ('IntersectionObserver' in window) {
		bpfwp_map.lazy_ready = true;
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				jQuery(entry.target).data('bpfwpVisible', true);
				observer.unobserve(entry.target);
				loadMaps();
			});
		}, { rootMargin: '200px' });
		document.querySelectorAll('.bp-map').forEach(function (map) { observer.observe(map); });
	} else { loadMaps(); }
});
