/**
 * filter-ajax.js — AJAX product filtering with price slider
 */
(function () {
    'use strict';

    // Limpiar ?_ajax=1 de la barra URL ANTES de verificar si filter-form existe.
    // Debe correr en cualquier página del ecommerce (home, detalle, cart) donde
    // el browser haya llegado con ese marker por error. El parámetro es interno.
    (function cleanAjaxMarker() {
        if (!window.history || !window.history.replaceState) return;
        var currentSearch = window.location.search;
        if (currentSearch.indexOf('_ajax=1') === -1) return;
        var cleanSearch = currentSearch
            .replace(/([?&])_ajax=1(&|$)/, function (_m, pre, post) {
                return post === '&' ? pre : '';
            })
            .replace(/^&/, '?')
            .replace(/\?$/, '');
        var cleanUrl = window.location.pathname + cleanSearch + window.location.hash;
        try { window.history.replaceState(null, '', cleanUrl); } catch (e) {}
    })();

    var form        = document.getElementById('ec-filter-form');
    var resultsWrap = document.getElementById('ec-filter-results');
    if (!form || !resultsWrap) return;

    var ajaxUrl     = form.getAttribute('data-ajax-url') || window.location.pathname;
    var debounceTimer = null;
    var currentXhr    = null;
    var activeCatId   = '';

    // ── Price Range Slider ────────────────────────────────────────────────────
    var slider   = document.getElementById('ec-range-slider');
    var inputMin = document.getElementById('ec-range-min');
    var inputMax = document.getElementById('ec-range-max');
    var fill     = document.getElementById('ec-range-fill');
    var display  = document.getElementById('ec-price-display');

    function updateSliderUI() {
        if (!slider || !inputMin || !inputMax) return;
        var min    = parseInt(inputMin.min);
        var max    = parseInt(inputMax.max);
        var valMin = parseInt(inputMin.value);
        var valMax = parseInt(inputMax.value);

        var pctMin = ((valMin - min) / (max - min)) * 100;
        var pctMax = ((valMax - min) / (max - min)) * 100;

        if (fill) {
            fill.style.left  = pctMin + '%';
            fill.style.width = (pctMax - pctMin) + '%';
        }
        if (display) display.textContent = 'S/ ' + valMin + ' – S/ ' + valMax;
    }

    if (inputMin && inputMax) {
        // Init
        updateSliderUI();

        inputMin.addEventListener('input', function () {
            var valMin = parseInt(inputMin.value);
            var valMax = parseInt(inputMax.value);
            if (valMin >= valMax) inputMin.value = valMax - 1;
            updateSliderUI();
            scheduleFilter(400);
        });

        inputMax.addEventListener('input', function () {
            var valMin = parseInt(inputMin.value);
            var valMax = parseInt(inputMax.value);
            if (valMax <= valMin) inputMax.value = valMin + 1;
            updateSliderUI();
            scheduleFilter(400);
        });
    }

    // ── Sort & available ──────────────────────────────────────────────────────
    var sortSel   = document.getElementById('ec-sort');
    var availChk  = document.getElementById('ec-only-avail');

    if (sortSel)  sortSel.addEventListener('change',  function () { scheduleFilter(0); });
    if (availChk) availChk.addEventListener('change', function () { scheduleFilter(0); });

    // ── Category pills ────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var pill = e.target.closest('.ec-cat-pill');
        if (!pill) return;

        document.querySelectorAll('.ec-cat-pill').forEach(function (p) {
            p.classList.remove('ec-cat-pill--active');
        });
        pill.classList.add('ec-cat-pill--active');

        activeCatId = pill.getAttribute('data-category-id') || '';
        var catInput = document.getElementById('ec-filter-category');
        if (catInput) catInput.value = activeCatId;

        // Al clickear cualquier categoría, limpiar búsqueda
        var cleanUrl = new URL(window.location);
        cleanUrl.searchParams.delete('q');
        if (!activeCatId) {
            cleanUrl.searchParams.delete('category_id');
            window.history.replaceState({}, '', cleanUrl.pathname);
        } else {
            cleanUrl.searchParams.set('category_id', activeCatId);
            window.history.replaceState({}, '', cleanUrl.pathname + '?' + cleanUrl.searchParams.toString());
        }
        // Ocultar badge de búsqueda
        var clearWrap = document.getElementById('ec-clear-wrap');
        if (clearWrap) clearWrap.style.display = 'none';

        scheduleFilter(0);
    });

    // ── Pagination: intercept AJAX pagination links ───────────────────────────
    resultsWrap.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        // Only intercept pagination links (they contain page=)
        if (!href || href.indexOf('page=') === -1) return;

        // Si la página actual está en HTTPS pero el link generado por el
        // servidor es HTTP (caso típico detrás de proxy reverso sin
        // X-Forwarded-Proto configurado), normalizar para evitar CSP
        // connect-src violation y SecurityError en pushState.
        if (window.location.protocol === 'https:' && /^http:\/\//i.test(href)) {
            href = href.replace(/^http:\/\//i, 'https://');
        }

        // Limpiar `_ajax=1` si el servidor lo dejó en el link (no debería,
        // pero Laravel preserva algunos querystrings en appends del paginator).
        href = href.replace(/([?&])_ajax=1(&|$)/, function (_m, pre, post) {
            return post === '&' ? pre : '';
        }).replace(/\?$/, '');

        e.preventDefault();
        fetchResults(href, true); // true = update URL
    });

    // ── Clear filters ─────────────────────────────────────────────────────────
    var clearBtn = document.getElementById('ec-filter-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (sortSel)  sortSel.value  = 'newest';
            if (availChk) availChk.checked = false;
            if (inputMin) {
                inputMin.value = inputMin.min;
                inputMax.value = inputMax.max;
                updateSliderUI();
            }
            activeCatId = '';
            var catInput = document.getElementById('ec-filter-category');
            if (catInput) catInput.value = '';

            // Deactivate category pills
            document.querySelectorAll('.ec-cat-pill').forEach(function (p) {
                p.classList.toggle('ec-cat-pill--active', !p.getAttribute('data-category-id'));
            });

            var clearWrap = document.getElementById('ec-clear-wrap');
            if (clearWrap) clearWrap.style.display = 'none';

            scheduleFilter(0);
        });
    }

    // ── Prevent full form submit ──────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        scheduleFilter(0);
    });

    // ── Core: debounce + fetch ────────────────────────────────────────────────
    function scheduleFilter(delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { fetchResults(null, true); }, delay);
    }

    function buildUrl(baseUrl) {
        var params = new URLSearchParams();

        // Preserve ?q= search term if present
        var currentQ = new URLSearchParams(window.location.search).get('q');
        if (currentQ) params.set('q', currentQ);

        if (sortSel  && sortSel.value !== 'newest') params.set('sort', sortSel.value);
        if (availChk && availChk.checked)           params.set('available', '1');
        if (inputMin) {
            var pMin = parseInt(inputMin.value);
            var pMax = parseInt(inputMax.value);
            var absMin = parseInt(inputMin.min);
            var absMax = parseInt(inputMax.max);
            if (pMin > absMin) params.set('min_price', pMin);
            if (pMax < absMax) params.set('max_price', pMax);
        }
        if (activeCatId) params.set('category_id', activeCatId);

        var qs = params.toString();
        return baseUrl + (qs ? '?' + qs : '');
    }

    function fetchResults(explicitUrl, updateHistory) {
        var url = explicitUrl || buildUrl(ajaxUrl);

        // Show loading overlay
        setLoading(true);

        // Show/hide clear button
        var clearWrap = document.getElementById('ec-clear-wrap');
        if (clearWrap) {
            var hasFilters = (sortSel && sortSel.value !== 'newest') ||
                             (availChk && availChk.checked) ||
                             activeCatId ||
                             (inputMin && parseInt(inputMin.value) > parseInt(inputMin.min)) ||
                             (inputMax && parseInt(inputMax.value) < parseInt(inputMax.max));
            clearWrap.style.display = hasFilters ? '' : 'none';
        }

        // Abort previous
        if (currentXhr) currentXhr.abort();

        var xhr = new XMLHttpRequest();
        currentXhr = xhr;

        // Add _ajax marker
        var fetchUrl = url + (url.indexOf('?') === -1 ? '?' : '&') + '_ajax=1';

        xhr.open('GET', fetchUrl);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                resultsWrap.innerHTML = xhr.responseText;

                // Re-init lazy load
                if (window.EcLazyLoad) window.EcLazyLoad.scan();
                // Re-sync wishlist / compare buttons
                if (window.EcCompare) window.EcCompare.open && syncButtons();
                document.dispatchEvent(new CustomEvent('ec:content-rendered'));

                // Scroll to results
                var top = resultsWrap.getBoundingClientRect().top + window.pageYOffset - 80;
                window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            } else if (explicitUrl) {
                // Fallback: si la respuesta AJAX falló (500, 404, etc.) y
                // teníamos una URL explícita (ej. paginación), navega nativo.
                // Sin esto el paginador quedaba "sin responder".
                console.warn('filter-ajax: AJAX falló (' + xhr.status + '), navegando directo');
                window.location.href = explicitUrl;
                return;
            }
            setLoading(false);
            currentXhr = null;
        };
        xhr.onerror = function () {
            setLoading(false);
            currentXhr = null;
            if (explicitUrl) {
                console.warn('filter-ajax: network error, navegando directo');
                window.location.href = explicitUrl;
            }
        };
        xhr.onabort = function () {
            setLoading(false);
            currentXhr = null;
        };
        xhr.send();

        // Update URL without reload
        if (updateHistory && window.history && window.history.pushState) {
            var displayUrl = explicitUrl || buildUrl(window.location.pathname);
            // Protege contra SecurityError si el explicitUrl viene con http
            // mientras la página está en https (cross-origin para el browser).
            if (window.location.protocol === 'https:' && /^http:\/\//i.test(displayUrl)) {
                displayUrl = displayUrl.replace(/^http:\/\//i, 'https://');
            }
            try {
                window.history.pushState({ ec_filter: true }, '', displayUrl);
            } catch (err) {
                // Silencioso: si aún falla, no importa — el contenido ya se
                // actualizó via AJAX; solo la barra URL queda como estaba.
                console.warn('filter-ajax: pushState skipped', err.message);
            }
        }
    }

    function syncButtons() {
        if (window.EcCompare) {
            document.querySelectorAll('[data-compare-id]').forEach(function (btn) {
                var id = parseInt(btn.getAttribute('data-compare-id'));
                var active = window.EcCompare.has(id);
                btn.classList.toggle('ec-btn-compare--active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }
    }

    function setLoading(on) {
        resultsWrap.classList.toggle('ec-filter-loading', on);
        if (on) {
            var grid = document.getElementById('ec-products-grid');
            if (grid) grid.classList.add('ec-grid-loading');
        } else {
            var grid = document.getElementById('ec-products-grid');
            if (grid) grid.classList.remove('ec-grid-loading');
        }
    }

    // Handle browser back/forward
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.ec_filter !== undefined) {
            fetchResults(window.location.href, false);
        }
    });

}());

// ── View Mode Toggle ──────────────────────────────────────────────────────
(function () {
    var STORAGE_KEY = 'ec_view_mode';
    var currentMode = localStorage.getItem(STORAGE_KEY) || 'grid';

    function applyViewMode(mode, save) {
        var grid = document.getElementById('ec-products-grid');
        if (grid) grid.classList.toggle('ec-products-grid--list', mode === 'list');
        if (save !== false) {
            currentMode = mode;
            localStorage.setItem(STORAGE_KEY, mode);
        }
        document.querySelectorAll('.ec-view-btn').forEach(function (btn) {
            var active = btn.getAttribute('data-view') === mode;
            btn.classList.toggle('ec-view-btn--active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    applyViewMode(currentMode, false);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ec-view-btn');
        if (!btn) return;
        applyViewMode(btn.getAttribute('data-view'));
    });

    document.addEventListener('ec:content-rendered', function () {
        applyViewMode(currentMode, false);
    });

    window.EcViewMode = { apply: applyViewMode };
}());

// ── Active Filter Chips ───────────────────────────────────────────────────
(function () {
    var sortLabels = {
        'price_asc': 'Menor precio',
        'price_desc': 'Mayor precio',
        'name_asc': 'A → Z'
    };

    function renderChips() {
        var chipsDiv = document.getElementById('ec-active-chips');
        if (!chipsDiv) return;

        var sortSel  = document.getElementById('ec-sort');
        var availChk = document.getElementById('ec-only-avail');
        var inputMin = document.getElementById('ec-range-min');
        var inputMax = document.getElementById('ec-range-max');

        var chips = [];

        if (sortSel && sortSel.value !== 'newest') {
            var lbl = sortLabels[sortSel.value] || sortSel.value;
            chips.push({
                label: lbl,
                remove: function () {
                    sortSel.value = 'newest';
                    sortSel.dispatchEvent(new Event('change'));
                }
            });
        }

        if (inputMin && inputMax) {
            var pMin = parseInt(inputMin.value);
            var pMax = parseInt(inputMax.value);
            if (pMin > parseInt(inputMin.min) || pMax < parseInt(inputMax.max)) {
                chips.push({
                    label: 'S/' + pMin + '–S/' + pMax,
                    remove: function () {
                        inputMin.value = inputMin.min;
                        inputMax.value = inputMax.max;
                        inputMin.dispatchEvent(new Event('input'));
                    }
                });
            }
        }

        if (availChk && availChk.checked) {
            chips.push({
                label: 'Solo disponibles',
                remove: function () {
                    availChk.checked = false;
                    availChk.dispatchEvent(new Event('change'));
                }
            });
        }

        var activePill = document.querySelector('.ec-cat-pill--active[data-category-id]:not([data-category-id=""])');
        if (activePill) {
            var catName = activePill.getAttribute('data-category-name') || 'Categoría';
            chips.push({
                label: catName,
                remove: function () {
                    var todoPill = document.querySelector('.ec-cat-pill[data-category-id=""]');
                    if (todoPill) todoPill.click();
                }
            });
        }

        chipsDiv.innerHTML = '';
        chips.forEach(function (chip) {
            var span = document.createElement('span');
            span.className = 'ec-active-chip';
            span.innerHTML =
                '<span class="ec-active-chip__label">' + chip.label + '</span>' +
                '<button class="ec-active-chip__remove" type="button" aria-label="Quitar filtro">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button>';
            (function (fn) {
                span.querySelector('.ec-active-chip__remove').addEventListener('click', fn);
            }(chip.remove));
            chipsDiv.appendChild(span);
        });

        chipsDiv.style.display = chips.length ? 'flex' : 'none';

        var badge = document.getElementById('ec-filter-badge');
        if (badge) {
            badge.textContent = chips.length;
            badge.style.display = chips.length ? 'inline-flex' : 'none';
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target && (e.target.id === 'ec-sort' || e.target.id === 'ec-only-avail')) {
            setTimeout(renderChips, 30);
        }
    });
    document.addEventListener('input', function (e) {
        if (e.target && (e.target.id === 'ec-range-min' || e.target.id === 'ec-range-max')) {
            setTimeout(renderChips, 30);
        }
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('.ec-cat-pill') || e.target.closest('#ec-filter-clear')) {
            setTimeout(renderChips, 50);
        }
    });

    setTimeout(renderChips, 150);
    window.EcFilterChips = { render: renderChips };
}());

// ── Mobile Filter Toggle ──────────────────────────────────────────────────
(function () {
    var toggle = document.getElementById('ec-filter-mob-toggle');
    var wrap   = document.getElementById('ec-filter-form-wrap');
    if (!toggle || !wrap) return;

    toggle.addEventListener('click', function () {
        var isOpen = wrap.classList.toggle('ec-filter-form-wrap--open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.classList.toggle('ec-filter-mob-toggle--open', isOpen);
    });
}());

// ── Sticky zone: add shadow when stuck ───────────────────────────────────
(function () {
    var zone = document.querySelector('.ec-filter-sticky-zone');
    if (!zone || !window.IntersectionObserver) return;
    // Sentinel placed just above the sticky zone
    var sentinel = document.createElement('div');
    sentinel.style.cssText = 'position:absolute;top:0;height:1px;pointer-events:none;';
    zone.parentNode.insertBefore(sentinel, zone);
    new IntersectionObserver(function (entries) {
        zone.classList.toggle('is-stuck', !entries[0].isIntersecting);
    }, { threshold: [1] }).observe(sentinel);
}());
