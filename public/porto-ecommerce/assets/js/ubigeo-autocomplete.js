/**
 * Ubigeo Autocomplete — reemplaza 3 dropdowns cascading con 1 campo de búsqueda.
 * Carga distritos una vez, filtra client-side.
 */
(function() {
    'use strict';

    window.UbigeoAutocomplete = {
        data: null,

        init: function(inputId, callbacks) {
            var self = this;
            var input = document.getElementById(inputId);
            if (!input) return;

            if (!self.data) {
                fetch('/ecommerce/ubigeo-search')
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        self.data = d;
                        self.setup(input, callbacks);
                    })
                    .catch(function() {});
            } else {
                self.setup(input, callbacks);
            }
        },

        setup: function(input, callbacks) {
            var self = this;
            var dropdown = document.createElement('div');
            dropdown.className = 'ubigeo-ac-dropdown';
            dropdown.style.cssText = 'position:absolute;z-index:9999;background:#fff;border:1px solid #e5e7eb;border-radius:8px;max-height:240px;overflow-y:auto;width:100%;box-shadow:0 4px 12px rgba(0,0,0,.15);display:none;';
            input.parentNode.style.position = 'relative';
            input.parentNode.appendChild(dropdown);

            var debounceTimer;
            input.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                var val = this.value;
                debounceTimer = setTimeout(function() {
                    var query = val.toLowerCase().trim();
                    if (query.length < 2) { dropdown.style.display = 'none'; return; }

                    var results = self.data.filter(function(d) {
                        return d.label.toLowerCase().indexOf(query) !== -1;
                    }).slice(0, 12);

                    dropdown.innerHTML = '';
                    if (!results.length) {
                        dropdown.innerHTML = '<div style="padding:12px;color:#9ca3af;font-size:14px">No encontrado</div>';
                    } else {
                        results.forEach(function(item) {
                            var opt = document.createElement('div');
                            opt.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #f3f4f6;transition:background .1s';
                            opt.textContent = item.label;
                            opt.onmouseenter = function() { this.style.background = '#f3f4f6'; };
                            opt.onmouseleave = function() { this.style.background = ''; };
                            opt.onclick = function() {
                                input.value = item.label;
                                dropdown.style.display = 'none';
                                if (callbacks && callbacks.onSelect) callbacks.onSelect(item);
                            };
                            dropdown.appendChild(opt);
                        });
                    }
                    dropdown.style.display = 'block';
                }, 150);
            });

            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }
    };
})();
