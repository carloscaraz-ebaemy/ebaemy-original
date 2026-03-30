/**
 * Ubigeo Filter - adds search capability to department/province/district selects
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Find all ubigeo selects and enhance them
        const selects = document.querySelectorAll('select[data-ubigeo]');
        selects.forEach(enhanceSelect);
    });

    function enhanceSelect(select) {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        const search = document.createElement('input');
        search.type = 'text';
        search.placeholder = 'Buscar...';
        search.style.cssText = 'width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;margin-bottom:4px;font-size:14px;display:none;';
        wrapper.insertBefore(search, select);

        select.addEventListener('focus', () => { search.style.display = 'block'; search.focus(); });

        search.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            Array.from(select.options).forEach(opt => {
                if (opt.value === '') return; // keep placeholder
                opt.style.display = opt.text.toLowerCase().includes(filter) ? '' : 'none';
            });
        });

        search.addEventListener('blur', () => {
            setTimeout(() => { search.style.display = 'none'; }, 200);
        });
    }
})();
