/**
 * Wishlist — lista de deseos persistida en localStorage
 * API pública: window.Wishlist.toggle(id), .has(id), .getAll(), .clear()
 *
 * Eventos disparados en document:
 *   wishlist:changed  → { detail: { id, action: 'add'|'remove', items } }
 */
(function (window) {
    'use strict';

    var KEY = 'ec_wishlist';

    function load() {
        try { return JSON.parse(localStorage.getItem(KEY)) || []; }
        catch (e) { return []; }
    }

    function save(items) {
        try { localStorage.setItem(KEY, JSON.stringify(items)); }
        catch (e) {}
    }

    var Wishlist = {
        getAll: function () { return load(); },

        has: function (id) {
            return load().indexOf(String(id)) !== -1;
        },

        add: function (id) {
            var items = load();
            var sid   = String(id);
            if (items.indexOf(sid) === -1) {
                items.push(sid);
                save(items);
                dispatch(sid, 'add', items);
            }
        },

        remove: function (id) {
            var sid   = String(id);
            var items = load().filter(function (i) { return i !== sid; });
            save(items);
            dispatch(sid, 'remove', items);
        },

        toggle: function (id) {
            this.has(id) ? this.remove(id) : this.add(id);
        },

        clear: function () {
            save([]);
            dispatch(null, 'clear', []);
        },

        count: function () { return load().length; }
    };

    function dispatch(id, action, items) {
        try {
            document.dispatchEvent(new CustomEvent('wishlist:changed', {
                detail: { id: id, action: action, items: items }
            }));
        } catch (e) {}
        updateAllButtons();
    }

    // ── Actualiza el estado visual de todos los botones ──────────────────────
    function updateAllButtons() {
        document.querySelectorAll('[data-wishlist-id]').forEach(function (btn) {
            var id    = String(btn.getAttribute('data-wishlist-id'));
            var active = Wishlist.has(id);
            btn.classList.toggle('ec-btn-wishlist--active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.setAttribute('title', active ? 'Quitar de favoritos' : 'Guardar en favoritos');
            var svg = btn.querySelector('svg');
            if (svg) {
                svg.setAttribute('fill', active ? '#e53e3e' : 'none');
                svg.setAttribute('stroke', active ? '#e53e3e' : 'currentColor');
            }
        });
        updateHeaderCount();
    }

    // ── Contador en el header ─────────────────────────────────────────────────
    function updateHeaderCount() {
        var countEl = document.getElementById('ec-wishlist-count');
        if (!countEl) return;
        var n = Wishlist.count();
        countEl.textContent = n;
        countEl.style.display = n > 0 ? 'inline-flex' : 'none';
    }

    // ── Delegación de eventos en .ec-btn-wishlist ────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-wishlist-id]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        Wishlist.toggle(btn.getAttribute('data-wishlist-id'));
    });

    // Sincronizar al cargar la página
    document.addEventListener('DOMContentLoaded', updateAllButtons);

    // También al recibir cambios desde otras pestañas
    window.addEventListener('storage', function (e) {
        if (e.key === KEY) updateAllButtons();
    });

    window.Wishlist = Wishlist;

}(window));
