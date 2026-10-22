(function () {
	'use strict';
	const el = wp.element.createElement;
	const __ = wp.i18n.__;
	const Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || wp.editPost.PluginDocumentSettingPanel;
	let confirmed = false;
	wp.apiFetch.use(function (options, next) {
		if (confirmed && options.data && options.data.meta && Object.prototype.hasOwnProperty.call(options.data.meta, 'bpfwp-location-purpose')) {
			options = Object.assign({}, options, {data: Object.assign({}, options.data, {bpfwp_confirm_public: true})});
		}
		const savingHours = options.body && typeof options.body.get === 'function' && options.body.get('bpfwp-hours-fingerprint');
		if (!savingHours) { return next(options); }
		return next(options).then(async response => {
			const receipt = await response.clone().json();
			if (!receipt.success || !receipt.data || !/^[a-f0-9]{64}$/.test(receipt.data.fingerprint)) {
				throw new Error((receipt.data || {}).message || __('Hours could not be verified. Reload before retrying.', 'business-profile'));
			}
			document.querySelectorAll('[name="bpfwp-hours-fingerprint"]').forEach(input => { input.value = receipt.data.fingerprint; });
			wp.data.dispatch('core/notices').removeNotice('bpfwp-hours-save');
			return response;
		}).catch(error => {
			wp.data.dispatch('core/notices').createErrorNotice(error.message || __('Hours were not saved. Reload before retrying.', 'business-profile'), {id: 'bpfwp-hours-save', isDismissible: false});
			throw error;
		});
	});
	function PurposePanel() {
		const meta = wp.data.useSelect(select => select('core/editor').getEditedPostAttribute('meta') || {}, []);
		const original = wp.data.useSelect(select => (select('core/editor').getCurrentPost().meta || {})['bpfwp-location-purpose'], []);
		const [consent, setConsent] = wp.element.useState(false);
		return el(Panel, {name: 'bpfwp-purpose', title: __('Location purpose', 'business-profile')},
			el(wp.components.SelectControl, {
				label: __('How will this location be used?', 'business-profile'),
				value: meta['bpfwp-location-purpose'] || '',
				options: [
					{value: '', label: __('Choose a purpose', 'business-profile')},
					{value: 'public', label: __('Public business location', 'business-profile')},
					{value: 'internal', label: __('Internal booking resource', 'business-profile')}
				],
				onChange: value => wp.data.dispatch('core/editor').editPost({meta: Object.assign({}, meta, {'bpfwp-location-purpose': value})})
			}),
			el('p', null, __('Internal resources are excluded from public business pages, cards and schema. Reservations controls booking availability and history.', 'business-profile')),
			original === 'internal' && meta['bpfwp-location-purpose'] !== 'internal' && el(wp.components.CheckboxControl, {
				label: __('I confirm that this change may publish the location and its business information.', 'business-profile'),
				checked: consent,
				onChange: value => { confirmed = value; setConsent(value); }
			})
		);
	}
	wp.plugins.registerPlugin('bpfwp-location-purpose', {render: PurposePanel});
}());
