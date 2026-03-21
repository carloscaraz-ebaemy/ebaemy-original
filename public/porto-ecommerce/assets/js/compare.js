/**
 * ec-compare.js — Product comparator (max 3)
 * localStorage key: ec_compare
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'ec_compare';
    var MAX = 3;

    // ── HTML escape helper (previene XSS en innerHTML) ───────────────────────
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Storage helpers ─────────────────────────────────────────────────────
    function getAll() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
        catch (e) { return []; }
    }
    function save(list) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); }
        catch (e) {}
    }
    function add(product) {
        var list = getAll();
        if (list.find(function (p) { return p.id === product.id; })) return 'already';
        if (list.length >= MAX) return 'full';
        list.push(product);
        save(list);
        return 'added';
    }
    function remove(id) {
        var list = getAll().filter(function (p) { return p.id !== id; });
        save(list);
    }
    function clear() { localStorage.removeItem(STORAGE_KEY); }
    function has(id) { return !!getAll().find(function (p) { return p.id === id; }); }

    // ── Build product object from data-product JSON ──────────────────────────
    function fromDataProduct(raw) {
        try {
            var d = typeof raw === 'string' ? JSON.parse(raw) : raw;
            return {
                id:         d.id,
                name:       d.description || d.name || '',
                price:      d.sale_unit_price || 0,
                currency:   (d.currency_type && d.currency_type.symbol) || 'S/',
                image:      d.image ? '/storage/uploads/items/' + d.image : '',
                url:        d.slug ? '/ecommerce/item/' + d.slug : '/ecommerce/item/' + d.id,
                category:   (d.category && d.category.name) || '',
                stock:      d.stock || 0,
                attributes: d.attributes || []
            };
        } catch (e) { return null; }
    }

    // ── Floating bar ─────────────────────────────────────────────────────────
    function renderBar() {
        var list = getAll();
        var bar  = document.getElementById('ec-compare-bar');
        if (!bar) return;

        var slots = bar.querySelector('.ec-compare-bar__slots');
        var btn   = bar.querySelector('.ec-compare-bar__open');
        var clr   = bar.querySelector('.ec-compare-bar__clear');

        // Render slots
        if (slots) {
            var html = '';
            for (var i = 0; i < MAX; i++) {
                var p = list[i];
                if (p) {
                    html += '<div class="ec-cslot ec-cslot--filled" data-id="' + p.id + '">' +
                        (p.image ? '<img src="' + p.image + '" alt="" onerror="this.style.display=\'none\'">' : '<span class="ec-cslot__placeholder"></span>') +
                        '<span class="ec-cslot__name">' + (p.name.length > 22 ? esc(p.name.slice(0, 22)) + '…' : esc(p.name)) + '</span>' +
                        '<button type="button" class="ec-cslot__remove" data-remove="' + p.id + '" aria-label="Quitar">✕</button>' +
                    '</div>';
                } else {
                    html += '<div class="ec-cslot ec-cslot--empty"><span class="ec-cslot__placeholder"></span><span class="ec-cslot__label">Agregar producto</span></div>';
                }
            }
            slots.innerHTML = html;
        }

        // Show/hide bar
        bar.classList.toggle('ec-compare-bar--visible', list.length > 0);
        if (btn)  btn.disabled = list.length < 2;
        if (clr)  clr.style.display = list.length > 0 ? '' : 'none';
    }

    // ── Comparison modal ─────────────────────────────────────────────────────
    function buildModal() {
        if (document.getElementById('ec-compare-modal')) return;
        var el = document.createElement('div');
        el.id = 'ec-compare-modal';
        el.className = 'ec-compare-modal';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Comparar productos');
        el.innerHTML =
            '<div class="ec-compare-modal__inner">' +
                '<div class="ec-compare-modal__head">' +
                    '<h2 class="ec-compare-modal__title">Comparar productos</h2>' +
                    '<button type="button" class="ec-compare-modal__close" aria-label="Cerrar">✕</button>' +
                '</div>' +
                '<div class="ec-compare-modal__body" id="ec-compare-modal-body"></div>' +
            '</div>';
        document.body.appendChild(el);

        el.querySelector('.ec-compare-modal__close').addEventListener('click', closeModal);
        el.addEventListener('click', function (e) { if (e.target === el) closeModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    }

    function openModal() {
        buildModal();
        var list = getAll();
        if (list.length < 2) return;

        // Collect all attribute keys
        var attrKeys = [];
        list.forEach(function (p) {
            (p.attributes || []).forEach(function (a) {
                if (a.description && attrKeys.indexOf(a.description) === -1) attrKeys.push(a.description);
            });
        });

        var padded = list.slice(); // already up to 3
        while (padded.length < MAX) padded.push(null);

        // Build table
        function cell(content, tag) {
            tag = tag || 'td';
            return '<' + tag + '>' + (content || '<span class="ec-compare-na">—</span>') + '</' + tag + '>';
        }
        function headerCell(content) { return cell(content, 'th'); }

        var rows = '';

        // Image row
        rows += '<tr class="ec-compare-row ec-compare-row--img">' +
            headerCell('') +
            padded.map(function (p) {
                if (!p) return '<td class="ec-compare-col ec-compare-col--empty"></td>';
                return '<td class="ec-compare-col">' +
                    '<a href="' + p.url + '">' +
                    (p.image ? '<img src="' + esc(p.image) + '" alt="' + esc(p.name) + '" onerror="this.style.display=\'none\'">' : '') +
                    '</a></td>';
            }).join('') +
        '</tr>';

        // Name row
        rows += '<tr class="ec-compare-row">' +
            headerCell('Producto') +
            padded.map(function (p) {
                if (!p) return '<td class="ec-compare-col ec-compare-col--empty"></td>';
                return '<td class="ec-compare-col"><a href="' + esc(p.url) + '" class="ec-compare-name">' + esc(p.name) + '</a></td>';
            }).join('') +
        '</tr>';

        // Price row
        rows += '<tr class="ec-compare-row ec-compare-row--highlight">' +
            headerCell('Precio') +
            padded.map(function (p) {
                if (!p) return '<td class="ec-compare-col ec-compare-col--empty"></td>';
                return '<td class="ec-compare-col ec-compare-price">' + p.currency + ' ' + parseFloat(p.price).toFixed(2) + '</td>';
            }).join('') +
        '</tr>';

        // Category row
        rows += '<tr class="ec-compare-row">' +
            headerCell('Categoría') +
            padded.map(function (p) {
                if (!p) return '<td class="ec-compare-col ec-compare-col--empty"></td>';
                return '<td class="ec-compare-col">' + (p.category ? esc(p.category) : '<span class="ec-compare-na">—</span>') + '</td>';
            }).join('') +
        '</tr>';

        // Stock row
        rows += '<tr class="ec-compare-row">' +
            headerCell('Disponibilidad') +
            padded.map(function (p) {
                if (!p) return '<td class="ec-compare-col ec-compare-col--empty"></td>';
                var inStock = p.stock > 0;
                return '<td class="ec-compare-col"><span class="ec-compare-stock ec-compare-stock--' + (inStock ? 'in' : 'out') + '">' +
                    (inStock ? 'En stock' : 'Sin stock') + '</span></td>';
            }).join('') +
        '</tr>';

        // Attributes rows
        attrKeys.forEach(function (key) {
            rows += '<tr class="ec-compare-row">' +
                headerCell(key) +
                padded.map(function (p) {
                    if (!p) return '<td class="ec-compare-col ec-compare-col--empty"></td>';
                    var attr = (p.attributes || []).find(function (a) { return a.description === key; });
                    return '<td class="ec-compare-col">' + (attr ? esc(attr.value) : '<span class="ec-compare-na">—</span>') + '</td>';
                }).join('') +
            '</tr>';
        });

        // CTA row
        rows += '<tr class="ec-compare-row ec-compare-row--cta">' +
            headerCell('') +
            padded.map(function (p) {
                if (!p) return '<td class="ec-compare-col ec-compare-col--empty"></td>';
                return '<td class="ec-compare-col">' +
                    '<a href="' + p.url + '" class="ec-compare-cta-btn">Ver producto</a>' +
                    '<button type="button" class="ec-compare-remove-btn" data-remove="' + p.id + '">Quitar</button>' +
                '</td>';
            }).join('') +
        '</tr>';

        var body = document.getElementById('ec-compare-modal-body');
        if (body) {
            body.innerHTML = '<div class="ec-compare-table-wrap"><table class="ec-compare-table"><tbody>' + rows + '</tbody></table></div>';
            // Remove from modal
            body.querySelectorAll('[data-remove]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    remove(parseInt(this.getAttribute('data-remove')));
                    renderBar();
                    syncAllButtons();
                    if (getAll().length < 2) { closeModal(); }
                    else { openModal(); }
                });
            });
        }

        var modal = document.getElementById('ec-compare-modal');
        if (modal) {
            modal.classList.add('ec-compare-modal--open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal() {
        var modal = document.getElementById('ec-compare-modal');
        if (modal) modal.classList.remove('ec-compare-modal--open');
        document.body.style.overflow = '';
    }

    // ── Sync compare-button states across the page ───────────────────────────
    function syncAllButtons() {
        document.querySelectorAll('[data-compare-id]').forEach(function (btn) {
            var id = parseInt(btn.getAttribute('data-compare-id'));
            var active = has(id);
            btn.classList.toggle('ec-btn-compare--active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.setAttribute('title', active ? 'Quitar de comparación' : 'Comparar');
        });
    }

    // ── Handle compare-button click ──────────────────────────────────────────
    function handleCompareClick(btn) {
        var id = parseInt(btn.getAttribute('data-compare-id'));
        if (!id) return;

        if (has(id)) {
            remove(id);
            renderBar();
            syncAllButtons();
            return;
        }

        var rawProduct = btn.getAttribute('data-product');
        var product = rawProduct ? fromDataProduct(rawProduct) : null;

        // Fallback: build minimal product from card DOM
        if (!product) {
            var card = btn.closest('[data-item-id]') || btn.closest('article');
            product = {
                id:       id,
                name:     (card && card.querySelector('.ec-product-card__title, h1.product-title')) ?
                              (card.querySelector('.ec-product-card__title, h1.product-title').textContent.trim()) : 'Producto',
                price:    0,
                currency: 'S/',
                image:    '',
                url:      '/ecommerce/item/' + id,
                category: '',
                stock:    0,
                attributes: []
            };
        }

        var result = add(product);
        if (result === 'full') {
            showToast('Máximo ' + MAX + ' productos para comparar.', 'warn');
            return;
        }
        renderBar();
        syncAllButtons();
        if (result === 'added') {
            showToast('Agregado a comparación', 'ok');
        }
    }

    // ── Toast notification ───────────────────────────────────────────────────
    function showToast(msg, type) {
        var t = document.createElement('div');
        t.className = 'ec-compare-toast ec-compare-toast--' + (type || 'ok');
        t.textContent = msg;
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('ec-compare-toast--in'); });
        setTimeout(function () {
            t.classList.remove('ec-compare-toast--in');
            setTimeout(function () { t.parentNode && t.parentNode.removeChild(t); }, 300);
        }, 2200);
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Inject floating bar
        var bar = document.createElement('div');
        bar.id        = 'ec-compare-bar';
        bar.className = 'ec-compare-bar';
        bar.setAttribute('aria-label', 'Barra de comparación');
        bar.innerHTML =
            '<div class="ec-compare-bar__inner">' +
                '<span class="ec-compare-bar__label">Comparar:</span>' +
                '<div class="ec-compare-bar__slots"></div>' +
                '<div class="ec-compare-bar__actions">' +
                    '<button type="button" class="ec-compare-bar__open" disabled>Comparar ahora</button>' +
                    '<button type="button" class="ec-compare-bar__clear">Limpiar</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(bar);

        bar.querySelector('.ec-compare-bar__open').addEventListener('click', openModal);
        bar.querySelector('.ec-compare-bar__clear').addEventListener('click', function () {
            clear(); renderBar(); syncAllButtons();
        });
        bar.addEventListener('click', function (e) {
            var removeBtn = e.target.closest('[data-remove]');
            if (removeBtn) {
                remove(parseInt(removeBtn.getAttribute('data-remove')));
                renderBar(); syncAllButtons();
            }
        });

        renderBar();
        syncAllButtons();

        // ── Event delegation for compare buttons ─────────────────────────────
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-compare-id]');
            if (btn) handleCompareClick(btn);
        });

        // ── Re-scan after dynamic content (items_bar, recently viewed) ────────
        document.addEventListener('ec:content-rendered', function () {
            syncAllButtons();
        });
    });

    // Expose
    window.EcCompare = { add: add, remove: remove, clear: clear, has: has, open: openModal };
}());
