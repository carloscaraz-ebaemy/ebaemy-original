@extends('ecommerce::layouts.master')

@section('page_title', 'Mis favoritos | ' . ($company->trade_name ?? $company->name ?? 'Tienda Online'))
@section('meta_description', 'Tus productos guardados como favoritos.')

@section('content')
<div class="container" style="padding-top: 8rem; padding-bottom: 3rem">

    {{-- Header --}}
    <div class="ec-wishlist-header">
        <div>
            <h1 class="ec-section-title">Mis favoritos</h1>
            <p id="ec-wishlist-count-label" class="ec-wishlist-subtitle" style="display:none"></p>
        </div>
        <div class="ec-wishlist-actions" id="ec-wishlist-actions" style="display:none">
            <button type="button" class="ec-wl-btn ec-wl-btn--share" id="ec-btn-share" title="Compartir lista">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Compartir
            </button>
            <button type="button" class="ec-wl-btn ec-wl-btn--cart" id="ec-btn-add-all">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Agregar todo al carrito
            </button>
        </div>
    </div>

    {{-- Share toast --}}
    <div id="ec-share-toast" class="ec-share-toast" style="display:none" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        ¡Enlace copiado al portapapeles!
    </div>

    {{-- Loading --}}
    <div id="ec-wishlist-loading" style="text-align:center;padding:40px">
        <div class="spinner-border text-secondary" role="status">
            <span class="sr-only">Cargando...</span>
        </div>
    </div>

    {{-- Vacío --}}
    <div id="ec-wishlist-empty" style="display:none;text-align:center;padding:60px 20px">
        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24"
             fill="none" stroke="#ddd" stroke-width="1.5" aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        <p style="font-size:1.6rem;color:#aaa;margin:16px 0 20px">Aún no tienes favoritos guardados.</p>
        <a href="{{ route('tenant.ecommerce.index') }}" class="btn btn-primary">Explorar productos</a>
    </div>

    {{-- Grid --}}
    <div id="ec-wishlist-grid" class="row row-sm" style="display:none"></div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var allItems = null; // cache

    function init() {
        // Esperar a que Wishlist esté disponible (puede tardar si los scripts cargan lento)
        if (!window.Wishlist) {
            setTimeout(init, 200);
            return;
        }
        var ids = window.Wishlist.getAll();

        // ¿Hay IDs compartidos en la URL?
        var params = new URLSearchParams(window.location.search);
        var shared = params.get('share');
        if (shared) {
            try {
                var sharedIds = JSON.parse(atob(shared));
                if (Array.isArray(sharedIds) && sharedIds.length) {
                    ids = sharedIds.map(String);
                }
            } catch (e) {}
        }

        document.getElementById('ec-wishlist-loading').style.display = 'none';

        if (!ids || ids.length === 0) {
            document.getElementById('ec-wishlist-empty').style.display = 'block';
            return;
        }

        fetchAndRender(ids);
    }

    function fetchAndRender(ids) {
        fetch('/ecommerce/items_bar')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                allItems = Array.isArray(data) ? data : (data.data || []);
                var items = allItems.filter(function (i) {
                    return ids.indexOf(String(i.id)) !== -1;
                });

                if (items.length === 0) {
                    document.getElementById('ec-wishlist-empty').style.display = 'block';
                    return;
                }

                renderGrid(items);
            })
            .catch(function () {
                document.getElementById('ec-wishlist-empty').style.display = 'block';
            });
    }

    function renderGrid(items) {
        var grid = document.getElementById('ec-wishlist-grid');
        grid.style.display = '';
        grid.innerHTML = items.map(renderCard).join('');

        // Mostrar header actions
        document.getElementById('ec-wishlist-actions').style.display = '';
        var label = document.getElementById('ec-wishlist-count-label');
        label.style.display = '';
        label.textContent = items.length + ' ' + (items.length === 1 ? 'producto guardado' : 'productos guardados');
    }

    function renderCard(item) {
        var imageUrl = (item.image_url_medium && item.image_url_medium.indexOf('imagen-no-disponible') === -1)
            ? item.image_url_medium
            : '/logo/imagen-no-disponible.jpg';
        var productUrl = '/ecommerce/item/' + (item.slug || item.id);
        var price = parseFloat(item.amount_sale_unit_price) || 0;
        var symbol = item.currency_type_symbol || 'S/';
        var inStock = item.stock > 0;

        return '<div class="col-6 col-md-4 col-lg-3 mb-3">' +
            '<article class="ec-product-card' + (inStock ? '' : ' ec-product-card--oos') + '">' +
            '<div class="ec-badges">' +
                (inStock ? '' : '<span class="ec-badge ec-badge--oos">Agotado</span>') +
            '</div>' +
            '<button type="button" class="ec-btn-wishlist ec-btn-wishlist--active" ' +
                'data-wishlist-id="' + item.id + '" aria-pressed="true" title="Quitar de favoritos">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' +
                    'fill="#e53e3e" stroke="#e53e3e" stroke-width="2" aria-hidden="true">' +
                    '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>' +
                '</svg>' +
            '</button>' +
            '<a href="' + productUrl + '" class="ec-product-card__img-wrap" tabindex="-1">' +
                '<img src="' + imageUrl + '" alt="' + esc(item.description) + '" ' +
                    'loading="lazy" width="300" height="300" class="ec-product-card__img" ' +
                    'onerror="this.src=\'/logo/imagen-no-disponible.jpg\'">' +
            '</a>' +
            '<div class="ec-product-card__body">' +
                (item.category ? '<span class="ec-product-card__cat">' + esc(item.category) + '</span>' : '') +
                '<h2 class="ec-product-card__title"><a href="' + productUrl + '">' + esc(item.description) + '</a></h2>' +
                '<div class="ec-product-card__footer">' +
                    '<div class="ec-product-card__price">' +
                        '<span class="ec-price-current">' + symbol + ' ' + price.toFixed(2) + '</span>' +
                    '</div>' +
                    (inStock
                        ? '<button type="button" class="ec-btn-cart" ' +
                              'data-ec-cart=\'' + JSON.stringify(item).replace(/'/g, '&#39;') + '\' ' +
                              'aria-label="Agregar al carrito">' +
                              '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>' +
                              '<span class="ec-btn-cart__text">Agregar</span>' +
                          '</button>'
                        : '') +
                '</div>' +
            '</div>' +
            '</article></div>';
    }

    // ── Agregar TODO al carrito ───────────────────────────────────────────────
    document.getElementById('ec-btn-add-all').addEventListener('click', function () {
        var btns = document.querySelectorAll('#ec-wishlist-grid .ec-btn-cart[data-ec-cart]');
        if (!btns.length) return;
        var count = 0;
        btns.forEach(function (btn) {
            var data = btn.getAttribute('data-ec-cart');
            if (data) { cart_add(data); count++; }
        });
        showToast('✓ ' + count + ' ' + (count === 1 ? 'producto agregado' : 'productos agregados') + ' al carrito');
    });

    // ── Compartir lista ──────────────────────────────────────────────────────
    document.getElementById('ec-btn-share').addEventListener('click', function () {
        var ids = window.Wishlist ? window.Wishlist.getAll() : [];
        if (!ids.length) return;

        var encoded = btoa(JSON.stringify(ids));
        var url = window.location.origin + window.location.pathname + '?share=' + encoded;

        // Menú contextual
        showShareMenu(url, this);
    });

    function showShareMenu(url, anchor) {
        // Eliminar menú anterior
        var old = document.getElementById('ec-share-menu');
        if (old) { old.remove(); return; }

        var menu = document.createElement('div');
        menu.id = 'ec-share-menu';
        menu.className = 'ec-share-menu';
        menu.innerHTML =
            '<button class="ec-share-option" data-action="copy">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' +
                ' Copiar enlace' +
            '</button>' +
            '<a class="ec-share-option" href="https://wa.me/?text=' + encodeURIComponent('Mira mis favoritos: ' + url) + '" target="_blank">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>' +
                ' Compartir por WhatsApp' +
            '</a>';

        // Posicionar
        var rect = anchor.getBoundingClientRect();
        menu.style.cssText = 'position:fixed;top:' + (rect.bottom + 6) + 'px;left:' + rect.left + 'px;z-index:9999';
        document.body.appendChild(menu);

        menu.querySelector('[data-action="copy"]').addEventListener('click', function () {
            copyToClipboard(url);
            menu.remove();
        });

        // Cerrar al hacer click fuera
        setTimeout(function () {
            document.addEventListener('click', function close(e) {
                if (!menu.contains(e.target) && e.target !== anchor) {
                    menu.remove();
                    document.removeEventListener('click', close);
                }
            });
        }, 50);
    }

    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () { showToast('¡Enlace copiado!'); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
            showToast('¡Enlace copiado!');
        }
    }

    function showToast(msg) {
        var t = document.getElementById('ec-share-toast');
        t.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ' + msg;
        t.style.display = 'flex';
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.style.display = 'none'; }, 3000);
    }

    // ── Quitar favorito → animar salida ──────────────────────────────────────
    document.addEventListener('wishlist:changed', function (e) {
        if (e.detail.action === 'remove') {
            var btn = document.querySelector('[data-wishlist-id="' + e.detail.id + '"]');
            if (btn) {
                var col = btn.closest('.col-6');
                if (col) {
                    col.style.transition = 'opacity .3s, transform .3s';
                    col.style.opacity = '0';
                    col.style.transform = 'scale(.9)';
                    setTimeout(function () {
                        col.remove();
                        checkEmpty();
                        updateCountLabel();
                    }, 300);
                }
            }
        }
    });

    function checkEmpty() {
        var grid = document.getElementById('ec-wishlist-grid');
        if (grid && grid.querySelectorAll('.ec-product-card').length === 0) {
            grid.style.display = 'none';
            document.getElementById('ec-wishlist-actions').style.display = 'none';
            document.getElementById('ec-wishlist-count-label').style.display = 'none';
            document.getElementById('ec-wishlist-empty').style.display = 'block';
        }
    }

    function updateCountLabel() {
        var n = document.querySelectorAll('#ec-wishlist-grid .ec-product-card').length;
        var label = document.getElementById('ec-wishlist-count-label');
        if (label) label.textContent = n + ' ' + (n === 1 ? 'producto guardado' : 'productos guardados');
    }

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.addEventListener('DOMContentLoaded', init);
}());
</script>
@endpush
