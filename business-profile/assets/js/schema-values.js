/* Structured repeat paths remain local to each rule and each parent item. */
document.addEventListener('click', function (event) {
    const add = event.target.closest('.bpfwp-schema-add');
    const remove = event.target.closest('.bpfwp-schema-remove');
    if (!add && !remove) return;
    const rows = add ? add.previousElementSibling : remove.parentElement.parentElement;
    const items = Array.from(rows.children);
    if (add) {
        if (items.length >= 50) return;
        const copy = items[0].cloneNode(true);
        copy.querySelectorAll('input, textarea').forEach(function (input) { input.value = ''; });
        copy.querySelectorAll('select').forEach(function (select) { select.value = 'inherit'; });
        rows.appendChild(copy);
    } else if (items.length > 1) {
        remove.parentElement.remove();
    } else {
        items[0].querySelectorAll('input, textarea').forEach(function (input) { input.value = ''; });
        items[0].querySelectorAll('select').forEach(function (select) { select.value = 'empty'; });
    }
    const prefix = rows.dataset.path;
    Array.from(rows.children).forEach(function (row, index) {
        row.querySelectorAll('[name], [data-path]').forEach(function (node) {
            ['name', 'data-path'].forEach(function (attribute) {
                const value = node.getAttribute(attribute);
                if (!value || !value.startsWith(prefix + '[')) return;
                const end = value.indexOf(']', prefix.length);
                node.setAttribute(attribute, prefix + '[' + index + ']' + value.substring(end + 1));
            });
        });
    });
    // Rebuild label associations after a clone, including nested groups.
    let serial = 0;
    document.querySelectorAll('.bpfwp-schema-group input, .bpfwp-schema-group textarea').forEach(function (input) {
        const label = input.closest('p').querySelector('label');
        input.id = 'bpfwp-schema-input-' + (++serial);
        if (label) label.htmlFor = input.id;
    });
    const focus = rows.lastElementChild.querySelector('select, input, textarea');
    if (focus) focus.focus();
});
