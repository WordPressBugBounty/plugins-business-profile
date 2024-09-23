const { __ } = wp.i18n;
const {	registerBlockType } = wp.blocks;
const { SelectControl, CheckboxControl, PanelBody, Disabled } = wp.components;
const {	ServerSideRender } = wp.serverSideRender;
const {	InspectorControls } = wp.editor;
const {	locationOptions } = bpfwp_blocks;

registerBlockType( 'business-profile/contact-card', {
	title: __( 'Contact Card', 'business-profile' ),
	category: 'bpfwp-blocks',
	icon: 'location',
	attributes: {
		location: {
			type: 'number',
			default: 0
		},
		show_name: {
			type: 'boolean',
			default: true
		},
		show_address: {
			type: 'boolean',
			default: true
		},
		show_get_directions: {
			type: 'boolean',
			default: true
		},
		show_phone: {
			type: 'boolean',
			default: true
		},
		show_cell_phone: {
			type: 'boolean',
			default: true
		},
		show_whatsapp: {
			type: 'boolean',
			default: true
		},
		show_fax: {
			type: 'boolean',
			default: true
		},
		show_contact: {
			type: 'boolean',
			default: true
		},
		show_opening_hours: {
			type: 'boolean',
			default: true
		},
		show_opening_hours_brief: {
			type: 'boolean',
			default: false
		},
		show_map: {
			type: 'boolean',
			default: true
		},
		show_booking_link: {
			type: 'boolean',
			default: false
		}
	},
	supports: {
		html: false,
	},
	edit( { attributes, setAttributes } ) {

		return (
			<div>
				<InspectorControls>
					<PanelBody>
						{locationOptions.length ? (
							<SelectControl
								label={ __( 'Select a Location', 'business-profile' ) }
								value={ attributes.location }
								onChange={ ( location ) => setAttributes( { location: parseInt( location, 10 ) } ) }
								options={ locationOptions }
							/>
						) : ''}
						<CheckboxControl
							label={ __( 'Show Name', 'business-profile') }
							checked={ attributes.show_name }
							onChange={ ( show_name ) => { setAttributes( { show_name } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show Address', 'business-profile') }
							checked={ attributes.show_address }
							onChange={ ( show_address ) => { setAttributes( { show_address } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show link to get directions on Google Maps', 'business-profile') }
							checked={ attributes.show_get_directions }
							onChange={ ( show_get_directions ) => { setAttributes( { show_get_directions } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show Phone number', 'business-profile') }
							checked={ attributes.show_phone }
							onChange={ ( show_phone ) => { setAttributes( { show_phone } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show Cell phone number', 'business-profile') }
							checked={ attributes.show_cell_phone }
							onChange={ ( show_cell_phone ) => { setAttributes( { show_cell_phone } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show Whatsapp', 'business-profile') }
							checked={ attributes.show_whatsapp }
							onChange={ ( show_whatsapp ) => { setAttributes( { show_whatsapp } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show Fax number', 'business-profile') }
							checked={ attributes.show_fax }
							onChange={ ( show_fax ) => { setAttributes( { show_fax } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show contact details', 'business-profile') }
							checked={ attributes.show_contact }
							onChange={ ( show_contact ) => { setAttributes( { show_contact } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show Opening Hours', 'business-profile') }
							checked={ attributes.show_opening_hours }
							onChange={ ( show_opening_hours ) => { setAttributes( { show_opening_hours } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show brief opening hours on one line', 'business-profile') }
							checked={ attributes.show_opening_hours_brief }
							onChange={ ( show_opening_hours_brief ) => { setAttributes( { show_opening_hours_brief } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show Google Map', 'business-profile') }
							checked={ attributes.show_map }
							onChange={ ( show_map ) => { setAttributes( { show_map } ) } }
						/>
						<CheckboxControl
							label={ __( 'Show book a table link', 'business-profile') }
							checked={ attributes.show_booking_link }
							onChange={ ( show_booking_link ) => { setAttributes( { show_booking_link } ) } }
						/>
					</PanelBody>
				</InspectorControls>
				<Disabled>
					<ServerSideRender block="business-profile/contact-card" attributes={ attributes } />
				</Disabled>
			</div>
		);
	},
	save() {
		return null;
	},
} );
