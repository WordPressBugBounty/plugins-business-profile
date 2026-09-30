/* Shared editor search; responses contain at most fifty public objects. */
(function () {
    const __ = wp.i18n.__;
    function search(kind, text, page, id) {
        return jQuery.getJSON(bpfwp_selector.url, { action: 'bpfwp_public_objects', nonce: bpfwp_selector.nonce, kind: kind, search: text, page: page, id: id || 0 });
    }
    window.bpfwpLocationSelect = function (props) {
        const el = wp.element.createElement;
        const state = wp.element.useState;
        const [text, setText] = state('');
        const [items, setItems] = state(props.options || []);
        const [page, setPage] = state(1);
        const [more, setMore] = state(false);
        const [resultCount, setResultCount] = state(null);
        const [error, setError] = state('');
        const [busy, setBusy] = state(false);
        function load(number) {
            setBusy(true); setError('');
            search('location', text, number).done(function (response) {
                if (!response.success) { setError(__('Search failed. Please retry.', 'business-profile')); return; }
                const selected = items.filter(item => Number(item.value) === Number(props.value));
                const next = [{value:0,label:__('Use the main Business Profile','business-profile')}].concat(response.data.items);
                selected.forEach(item => { if (!next.some(other => Number(other.value) === Number(item.value))) next.push(item); });
                setResultCount(response.data.items.length);
                setItems(next); setPage(number); setMore(response.data.more);
            }).fail(function () { setError(__('Search failed. Please retry.', 'business-profile')); }).always(function () { setBusy(false); });
        }
        wp.element.useEffect(function () {
            if (props.value && !items.some(item => Number(item.value) === Number(props.value))) {
                search('location', '', 1, props.value).done(function (response) {
                    if (response.success) setItems(current => current.concat(response.data.items));
                });
            }
        }, [props.value]);
        return el('div', null,
            el(wp.components.TextControl, {label:__('Find a public location','business-profile'),help:__('Search public locations to filter the dropdown below, or choose a location directly from the dropdown.','business-profile'),value:text,onChange:setText}),
            el(wp.components.Button, {isSecondary:true,disabled:busy,onClick:()=>load(1)}, __('Search','business-profile')),
            el(wp.components.SelectControl, {label:props.label,value:props.value,onChange:props.onChange,options:items}),
            page > 1 && el(wp.components.Button,{disabled:busy,onClick:()=>load(page-1)},__('Previous','business-profile')),
            more && el(wp.components.Button,{disabled:busy,onClick:()=>load(page+1)},__('Next','business-profile')),
            el('p',{role:'status','aria-live':'polite'},busy ? __('Searching public locations…','business-profile') : error || (resultCount === null ? '' : resultCount === 0 ? __('No matching public locations. Please try another search.','business-profile') : wp.i18n.sprintf(
                /* translators: %d: Number of matching locations on this results page. */
                wp.i18n._n('%d matching public location on this page. Choose a location from the dropdown.','%d matching public locations on this page. Choose a location from the dropdown.',resultCount,'business-profile'),resultCount))));
    };
    // Classic widget and schema target selects use the same bounded endpoint.
    function enhance() {
        document.querySelectorAll('select.bpfwp-public-search').forEach(function (select) {
            if (select.dataset.searchReady) return;
            select.dataset.searchReady = '1';
            let page = 1;
            const box = document.createElement('span');
            const input = document.createElement('input');
            input.type = 'search'; input.setAttribute('aria-label', __('Search public items','business-profile'));
            const button = document.createElement('button'); button.type = 'button'; button.textContent = __('Search','business-profile');
            const next = document.createElement('button'); next.type = 'button'; next.textContent = __('Next','business-profile'); next.hidden = true;
            const status = document.createElement('span'); status.setAttribute('role','status');
            box.append(input,button,next,status); select.after(box);
            function load(number) {
                const kind = select.dataset.kind || document.querySelector('select[name="schema_target_type"]').value;
                if (!['location','post','page'].includes(kind)) return;
                button.disabled = next.disabled = true;
                search(kind,input.value,number).done(function (response) {
                    if (!response.success) { status.textContent = __('Search failed.','business-profile'); return; }
                    const selected = select.selectedOptions[0] && select.selectedOptions[0].cloneNode(true);
                    const primary = Array.from(select.options).filter(option => option.value === '' || option.value === '0').map(option => option.cloneNode(true));
                    select.replaceChildren();
                    primary.forEach(option => select.append(option));
                    if (selected && !primary.some(option => option.value === selected.value)) select.append(selected);
                    response.data.items.forEach(item => { if (!selected || String(item.value) !== selected.value) select.add(new Option(item.label,item.value)); });
                    page = number; next.hidden = !response.data.more;
                    status.textContent = response.data.items.length ? '' : __('No matching public items.','business-profile');
                }).fail(function () { status.textContent = __('Search failed. Please retry.','business-profile'); }).always(function () { button.disabled = next.disabled = false; });
            }
            button.addEventListener('click',()=>load(1)); next.addEventListener('click',()=>load(page+1));
        });
    }
    jQuery(enhance);
    jQuery(document).on('widget-added widget-updated',enhance);
}());
