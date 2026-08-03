@extends('tenant.layouts.app')

@push('styles')
    @include('tenant.raffles._tokens')
@endpush

@php
    $isNew   = !$raffle->exists;
    $action  = $isNew ? route('raffles.store') : route('raffles.update', $raffle);
    $dt      = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
    // Chips de productos: JSON pre-calculado (ver feedback_blade_json_parser_trap).
    $itemsJson = json_encode($selectedItems->all(), JSON_UNESCAPED_UNICODE);
    $searchUrl = route('raffles.search_items');
@endphp

@section('content')
<div id="rfApp" class="container-fluid py-3">

    <div class="rf-head">
        <div>
            <h1 class="rf-title">{{ $isNew ? '🎁 Nuevo sorteo' : '✏️ Editar sorteo' }}</h1>
            <p class="rf-sub">{{ $isNew ? 'Define el premio, la vigencia y qué pedidos participan.' : $raffle->code . ' · ' . $raffle->name }}</p>
        </div>
        <div class="rf-actions">
            <a href="{{ $isNew ? route('raffles.index') : route('raffles.show', $raffle) }}" class="rf-btn">Cancelar</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="rfForm">
        @csrf

        <div class="row g-3">
            {{-- ───────────── Columna izquierda ───────────── --}}
            <div class="col-lg-7">

                <div class="rf-card">
                    <div class="rf-card__body">
                        <div class="rf-section">
                            <div class="rf-section__label">Información general</div>

                            <div class="rf-field">
                                <label for="rf_name">Nombre del sorteo *</label>
                                <input id="rf_name" name="name" class="rf-input" maxlength="160" required
                                       value="{{ old('name', $raffle->name) }}" placeholder="Ej. Aniversario 2026">
                            </div>

                            <div class="rf-field">
                                <label for="rf_description">Descripción</label>
                                <textarea id="rf_description" name="description" class="rf-input" rows="3"
                                          placeholder="Qué celebra el sorteo, a quién está dirigido…">{{ old('description', $raffle->description) }}</textarea>
                            </div>

                            <div class="rf-field">
                                <label for="rf_terms">Términos y condiciones (bases)</label>
                                <textarea id="rf_terms" name="terms" class="rf-input" rows="6"
                                          placeholder="Bases del sorteo que verá el cliente antes de aceptar.">{{ old('terms', $raffle->terms) }}</textarea>
                                <div class="rf-note">Se muestran íntegras en el enlace de invitación. El cliente debe marcarlas para poder participar.</div>
                            </div>

                            <div class="rf-field">
                                <label for="rf_status">Estado</label>
                                <select id="rf_status" name="status" class="rf-input">
                                    @foreach(\App\Models\Tenant\Raffle::STATUSES as $key => $label)
                                        <option value="{{ $key }}" @selected(old('status', $raffle->status) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="rf-note">En <strong>Borrador</strong> los enlaces no aceptan participaciones. Ponlo en <strong>Activo</strong> para abrir el sorteo.</div>
                            </div>
                        </div>

                        <div class="rf-section">
                            <div class="rf-section__label">Premio</div>

                            <div class="row g-2">
                                <div class="col-md-7 rf-field">
                                    <label for="rf_prize_name">Nombre del premio</label>
                                    <input id="rf_prize_name" name="prize_name" class="rf-input" maxlength="160"
                                           value="{{ old('prize_name', $raffle->prize_name) }}" placeholder="Ej. Televisor 50&quot;">
                                </div>
                                <div class="col-md-2 rf-field">
                                    <label for="rf_prize_quantity">Cantidad</label>
                                    <input id="rf_prize_quantity" type="number" min="1" max="999" name="prize_quantity" class="rf-input"
                                           value="{{ old('prize_quantity', $raffle->prize_quantity ?: 1) }}" required>
                                </div>
                                <div class="col-md-3 rf-field">
                                    <label for="rf_prize_value">Valor referencial</label>
                                    <input id="rf_prize_value" type="number" step="0.01" min="0" name="prize_value" class="rf-input"
                                           value="{{ old('prize_value', $raffle->prize_value) }}" placeholder="Opcional">
                                </div>
                            </div>

                            <div class="rf-note mb-2">La cantidad define cuántos ganadores se pueden sortear en total.</div>

                            <div class="rf-field">
                                <label for="rf_prize_description">Descripción del premio</label>
                                <textarea id="rf_prize_description" name="prize_description" class="rf-input" rows="2">{{ old('prize_description', $raffle->prize_description) }}</textarea>
                            </div>

                            <div class="rf-field">
                                <label for="rf_prize_image">Imagen principal del premio</label>
                                {{-- `accept` explícito: sin él, el iPhone manda HEIC sin convertir
                                     (ver feedback_upload_accept_heic). --}}
                                <input id="rf_prize_image" type="file" name="prize_image_file" class="rf-input js-img-preview"
                                       data-preview="rf_prize_preview"
                                       accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/bmp,image/heic,image/heif">
                                <div class="rf-note">
                                    Se muestra en el enlace del cliente, en el historial y en la ficha del ganador.
                                    Máximo 15 MB; se recorta y comprime sola.
                                </div>
                                <div id="rf_prize_preview" class="rf-preview">
                                    @if($raffle->prize_image)
                                        <img src="{{ $raffle->prizeImageUrl('medium') }}" alt="Premio">
                                        <span class="rf-preview__tag">Imagen actual</span>
                                    @endif
                                </div>
                            </div>

                            <div class="rf-field">
                                <label for="rf_gallery">Galería de imágenes (opcional)</label>
                                <input id="rf_gallery" type="file" name="gallery_files[]" class="rf-input" multiple
                                       accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/bmp,image/heic,image/heif">
                                @if(!empty($raffle->prize_gallery))
                                    <div class="rf-thumbs">
                                        @foreach($raffle->prize_gallery as $file)
                                            <div class="rf-thumb">
                                                <img src="{{ \App\Services\Tenant\ImageProcessingService::getUrl($file, 'small') }}" alt="">
                                                <label>
                                                    <input type="checkbox" name="remove_gallery[]" value="{{ $file }}"> quitar
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- ── Opciones de premio ────────────────────────────
                             Si cargas dos o más, el cliente elige cuál quiere
                             al aceptar participar. Con una o ninguna, el sorteo
                             usa el premio único de arriba. --}}
                        <div class="rf-section">
                            <div class="rf-section__label">Opciones de premio (el cliente elige)</div>
                            <div class="rf-note mb-2">
                                Carga <strong>dos o más</strong> alternativas y el cliente escogerá la
                                que prefiere al confirmar su participación. Verás en el panel cuántos
                                eligieron cada una. Si dejas esto vacío, se sortea el premio único.
                            </div>

                            <div id="rf_options">
                                @php $opts = $raffle->exists ? $raffle->prizeOptions()->get() : collect(); @endphp
                                @foreach($opts as $opt)
                                    <div class="rf-opt">
                                        <input type="hidden" name="options[id][]" value="{{ $opt->id }}">
                                        <div class="rf-opt__img">
                                            <div class="rf-preview rf-preview--sm" id="rf_opt_prev_{{ $opt->id }}">
                                                @if($opt->image)
                                                    <img src="{{ $opt->imageUrl('small') }}" alt="{{ $opt->name }}">
                                                @endif
                                            </div>
                                            <input type="file" name="options[image][]" class="rf-input js-img-preview"
                                                   data-preview="rf_opt_prev_{{ $opt->id }}"
                                                   accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/bmp,image/heic,image/heif">
                                        </div>
                                        <div class="rf-opt__tx">
                                            <input type="text" name="options[name][]" class="rf-input" maxlength="160"
                                                   value="{{ $opt->name }}" placeholder="Nombre de la opción">
                                            <input type="text" name="options[description][]" class="rf-input mt-1" maxlength="500"
                                                   value="{{ $opt->description }}" placeholder="Detalle (opcional)">
                                            @if($opt->chosen_count)
                                                <div class="rf-note">{{ $opt->chosen_count }} cliente(s) la eligieron</div>
                                            @endif
                                        </div>
                                        <button type="button" class="rf-btn rf-btn--danger js-opt-del" title="Quitar">✕</button>
                                    </div>
                                @endforeach
                            </div>

                            <button type="button" class="rf-btn" id="rf_opt_add">+ Agregar opción de premio</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ───────────── Columna derecha ───────────── --}}
            <div class="col-lg-5">

                <div class="rf-card">
                    <div class="rf-card__body">
                        <div class="rf-section">
                            <div class="rf-section__label">Vigencia</div>

                            {{-- Atajos: llenar 4 fechas a mano es tedioso y es donde
                                 se cuelan los errores (dejar el cierre en el pasado
                                 hace que nadie pueda participar). --}}
                            <div class="rf-quick">
                                <span class="rf-quick__lbl">Duración</span>
                                @foreach([7 => '7 días', 15 => '15 días', 30 => '30 días'] as $d => $l)
                                    <button type="button" class="rf-btn js-rf-dur" data-days="{{ $d }}">{{ $l }}</button>
                                @endforeach
                                <span class="rf-note">Empieza hoy · sorteo al día siguiente del cierre</span>
                            </div>

                            {{-- Diagnóstico en vivo del orden y de las fechas vencidas. --}}
                            <div id="rf_dates_msg" class="rf-dates-msg" hidden></div>

                            <div class="rf-field">
                                <label for="rf_starts_at">Fecha de inicio del sorteo</label>
                                <input id="rf_starts_at" type="datetime-local" name="starts_at" class="rf-input"
                                       value="{{ old('starts_at', $dt($raffle->starts_at)) }}">
                                <div class="rf-note">Antes de esta fecha el enlace no acepta participaciones.</div>
                            </div>

                            <div class="rf-field">
                                <label for="rf_reg_close">Cierre del registro de participantes</label>
                                <input id="rf_reg_close" type="datetime-local" name="registration_closes_at" class="rf-input"
                                       value="{{ old('registration_closes_at', $dt($raffle->registration_closes_at)) }}">
                                <div class="rf-note">Después de esta fecha ya no se aceptan nuevas participaciones.</div>
                            </div>

                            <div class="rf-field">
                                <label for="rf_draw_at">Fecha y hora del sorteo</label>
                                <input id="rf_draw_at" type="datetime-local" name="draw_at" class="rf-input"
                                       value="{{ old('draw_at', $dt($raffle->draw_at)) }}">
                            </div>

                            <div class="rf-field">
                                <label for="rf_publish">Publicación del ganador (opcional)</label>
                                <input id="rf_publish" type="datetime-local" name="winner_published_at" class="rf-input"
                                       value="{{ old('winner_published_at', $dt($raffle->winner_published_at)) }}">
                            </div>
                        </div>

                        @include('tenant.raffles.partials.source-picker')

                        <button type="submit" class="rf-btn rf-btn--primary w-100">
                            {{ $isNew ? 'Crear sorteo' : 'Guardar cambios' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection


@push('scripts')
<script>
/*
 * Formulario de sorteo: alterna el panel de filtros según el origen elegido
 * y gestiona los chips de productos de cada panel.
 *
 * Delegación en `document` + re-consulta fresca de nodos: Vue monta en
 * #main-wrapper y re-renderiza el DOM, así que nunca guardamos referencias.
 * Ver feedback_vue_mainwrapper_rerender.
 *
 * Los paneles ocultos llevan sus inputs `disabled` para que el POST solo
 * cargue los filtros del origen activo.
 */
(function () {
    var SEARCH_URL   = {!! json_encode($searchUrl) !!};
    var INITIAL_ITEMS = {!! $itemsJson !!} || [];
    var chosen       = {};   // panel (origen) → productos elegidos
    var timer        = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function currentSource() {
        var r = document.querySelector('input[name="source"]:checked');
        return r ? r.value : null;
    }

    function panelOf(node) {
        return node.closest ? node.closest('[data-rf-filters]') : null;
    }

    // ── Paneles de filtros ────────────────────────────────────────────
    function syncPanels() {
        var active = currentSource();

        document.querySelectorAll('[data-rf-filters]').forEach(function (panel) {
            var on = panel.getAttribute('data-rf-filters') === active;
            panel.style.display = on ? '' : 'none';
            panel.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.disabled = !on;
            });
        });

        renderChips();
    }

    // ── Chips de productos ────────────────────────────────────────────
    function renderChips() {
        document.querySelectorAll('.js-rf-item-chips').forEach(function (box) {
            var panel = panelOf(box);
            var key   = panel ? panel.getAttribute('data-rf-filters') : '_';
            var name  = box.getAttribute('data-name') || 'filters[items][]';
            var list  = chosen[key] || [];
            var off   = panel && panel.style.display === 'none';

            box.innerHTML = list.map(function (it) {
                return '<span class="rf-pill rf-pill--finished" style="cursor:pointer" data-rf-remove="' + esc(it.id) + '">'
                     + esc(it.label) + ' ✕'
                     + '<input type="hidden" name="' + esc(name) + '" value="' + esc(it.id) + '"'
                     + (off ? ' disabled' : '') + '></span>';
            }).join(' ');
        });
    }

    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.name === 'source') syncPanels();
    });

    document.addEventListener('input', function (ev) {
        var input = ev.target;
        if (!input || !input.classList || !input.classList.contains('js-rf-item-search')) return;

        var panel   = panelOf(input);
        var results = panel ? panel.querySelector('.js-rf-item-results') : null;
        var term    = input.value.trim();

        clearTimeout(timer);
        if (term.length < 2) { if (results) results.innerHTML = ''; return; }

        timer = setTimeout(function () {
            fetch(SEARCH_URL + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    if (!results) return;
                    if (!rows.length) { results.innerHTML = 'Sin resultados.'; return; }
                    results.innerHTML = rows.map(function (r) {
                        return '<a href="#" class="d-block py-1" data-rf-add="' + esc(r.id)
                             + '" data-rf-label="' + esc(r.label) + '">' + esc(r.label) + '</a>';
                    }).join('');
                })
                .catch(function () {});
        }, 250);
    });

    document.addEventListener('click', function (ev) {
        var add = ev.target.closest ? ev.target.closest('[data-rf-add]') : null;
        if (add) {
            ev.preventDefault();
            var panel = panelOf(add);
            var key   = panel ? panel.getAttribute('data-rf-filters') : '_';
            var id    = parseInt(add.getAttribute('data-rf-add'), 10);

            chosen[key] = chosen[key] || [];
            if (!chosen[key].some(function (s) { return parseInt(s.id, 10) === id; })) {
                chosen[key].push({ id: id, label: add.getAttribute('data-rf-label') });
            }

            if (panel) {
                var search = panel.querySelector('.js-rf-item-search');
                var res    = panel.querySelector('.js-rf-item-results');
                if (search) search.value = '';
                if (res) res.innerHTML = '';
            }
            renderChips();
            return;
        }

        var rm = ev.target.closest ? ev.target.closest('[data-rf-remove]') : null;
        if (rm) {
            ev.preventDefault();
            var p   = panelOf(rm);
            var k   = p ? p.getAttribute('data-rf-filters') : '_';
            var rid = parseInt(rm.getAttribute('data-rf-remove'), 10);
            chosen[k] = (chosen[k] || []).filter(function (s) { return parseInt(s.id, 10) !== rid; });
            renderChips();
        }
    });

    /* ── Vista previa de la imagen ANTES de guardar ───────────────────
       Antes se subía a ciegas: la foto solo se veía después de guardar y, si
       el archivo no servía, el sorteo quedaba sin imagen sin explicación.
       Aquí se valida tipo y tamaño en el navegador y se ve la miniatura al
       instante. */
    var MAX_MB = 15;

    document.addEventListener('change', function (ev) {
        var input = ev.target;
        if (!input.classList || !input.classList.contains('js-img-preview')) return;

        var box = document.getElementById(input.getAttribute('data-preview'));
        if (!box) return;

        var file = input.files && input.files[0];
        if (!file) return;

        var tooBig   = file.size > MAX_MB * 1024 * 1024;
        // El iPhone a veces manda HEIC con type vacío: eso se acepta y lo
        // convierte el servidor. Solo se rechaza lo que claramente no es imagen.
        var notImage = file.type && file.type.indexOf('image/') !== 0;

        if (tooBig || notImage) {
            box.innerHTML = '<span class="rf-preview__err">'
                + (tooBig ? 'Pesa ' + (file.size / 1048576).toFixed(1) + ' MB; el máximo es ' + MAX_MB + ' MB.'
                          : 'Ese archivo no es una imagen.')
                + '</span>';
            input.value = '';
            return;
        }

        // HEIC no se puede previsualizar en el navegador, pero sí subir.
        if (/\.(heic|heif)$/i.test(file.name)) {
            box.innerHTML = '<span class="rf-preview__tag">📷 ' + file.name + ' — se convertirá al guardar</span>';
            return;
        }

        var url = URL.createObjectURL(file);
        box.innerHTML = '<img src="' + url + '" alt=""><span class="rf-preview__new">Nueva imagen</span>';
        var img = box.querySelector('img');
        if (img) img.onload = function () { URL.revokeObjectURL(url); };
    });

    /* ── Opciones de premio: agregar y quitar filas ───────────────── */
    document.addEventListener('click', function (ev) {
        if (ev.target.closest && ev.target.closest('#rf_opt_add')) {
            ev.preventDefault();
            var box = document.getElementById('rf_options');
            if (!box) return;

            var uid = 'new' + Date.now();
            var row = document.createElement('div');
            row.className = 'rf-opt';
            row.innerHTML =
                '<input type="hidden" name="options[id][]" value="">' +
                '<div class="rf-opt__img">' +
                    '<div class="rf-preview rf-preview--sm" id="rf_opt_prev_' + uid + '"></div>' +
                    '<input type="file" name="options[image][]" class="rf-input js-img-preview" ' +
                        'data-preview="rf_opt_prev_' + uid + '" ' +
                        'accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/bmp,image/heic,image/heif">' +
                '</div>' +
                '<div class="rf-opt__tx">' +
                    '<input type="text" name="options[name][]" class="rf-input" maxlength="160" placeholder="Nombre de la opción">' +
                    '<input type="text" name="options[description][]" class="rf-input mt-1" maxlength="500" placeholder="Detalle (opcional)">' +
                '</div>' +
                '<button type="button" class="rf-btn rf-btn--danger js-opt-del" title="Quitar">✕</button>';
            box.appendChild(row);
            var first = row.querySelector('input[type="text"]');
            if (first) first.focus();
            return;
        }

        var del = ev.target.closest && ev.target.closest('.js-opt-del');
        if (del) {
            ev.preventDefault();
            var row2 = del.closest('.rf-opt');
            // Basta con quitar la fila: el servidor borra las opciones que ya
            // no llegan y solo desactiva las que algún cliente ya eligió.
            if (row2) row2.remove();
        }
    });

    /* ── Fechas: atajos de duración + diagnóstico en vivo ─────────────
       Llenar cuatro campos datetime a mano es tedioso y ahí se cuelan los
       errores: basta dejar el cierre de registro en el pasado para que nadie
       pueda participar y el sorteo quede bloqueado sin explicación. */
    var D = { start: 'rf_starts_at', close: 'rf_reg_close', draw: 'rf_draw_at' };

    function toLocalValue(d) {
        var p = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
             + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
    }
    function val(id) {
        var el = document.getElementById(id);
        return el && el.value ? new Date(el.value) : null;
    }
    function setVal(id, d) {
        var el = document.getElementById(id);
        if (el) el.value = toLocalValue(d);
    }

    function checkDates() {
        var box = document.getElementById('rf_dates_msg');
        if (!box) return;

        var s = val(D.start), c = val(D.close), w = val(D.draw), now = new Date();
        var errores = [], avisos = [];

        if (c && s && c < s) errores.push('El <b>cierre de registro</b> es anterior al inicio.');
        if (w && c && w < c) errores.push('La <b>fecha del sorteo</b> es anterior al cierre de registro.');
        if (c && c < now)    errores.push('El <b>cierre de registro ya pasó</b>: nadie podrá participar.');
        if (w && w < now)    avisos.push('La fecha del sorteo ya pasó.');
        if (s && s > now)    avisos.push('El sorteo aún no empieza: el enlace no aceptará participaciones hasta esa fecha.');

        if (errores.length) {
            box.className = 'rf-dates-msg rf-dates-msg--bad';
            box.innerHTML = '⚠️ ' + errores.join('<br>⚠️ ');
            box.hidden = false;
            return;
        }
        if (avisos.length) {
            box.className = 'rf-dates-msg rf-dates-msg--warn';
            box.innerHTML = 'ℹ️ ' + avisos.join('<br>ℹ️ ');
            box.hidden = false;
            return;
        }
        if (s && c && w) {
            var dias = Math.max(1, Math.round((c - s) / 86400000));
            box.className = 'rf-dates-msg rf-dates-msg--ok';
            box.innerHTML = '✓ Se puede participar durante <b>' + dias + ' día(s)</b>, y el sorteo se realiza el <b>'
                          + w.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' }) + '</b>.';
            box.hidden = false;
            return;
        }
        box.hidden = true;
    }

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest && ev.target.closest('.js-rf-dur');
        if (!b) return;
        ev.preventDefault();

        var dias  = parseInt(b.getAttribute('data-days'), 10) || 7;
        var ahora = new Date();
        var cierre = new Date(ahora.getTime() + dias * 86400000);
        // El sorteo, al día siguiente del cierre, a las 12:00.
        var sorteo = new Date(cierre.getTime() + 86400000);
        sorteo.setHours(12, 0, 0, 0);

        setVal(D.start, ahora);
        setVal(D.close, cierre);
        setVal(D.draw, sorteo);
        checkDates();
    });

    document.addEventListener('change', function (ev) {
        if (ev.target && ['starts_at', 'registration_closes_at', 'draw_at'].indexOf(ev.target.name) !== -1) {
            checkDates();
        }
    });

    checkDates();

    // Hidratar los productos guardados en el origen actualmente elegido.
    var initial = currentSource();
    if (initial && INITIAL_ITEMS.length) chosen[initial] = INITIAL_ITEMS.slice();

    syncPanels();

    // Si Vue re-renderiza #main-wrapper los paneles vuelven al estado del
    // servidor: se reaplica. Barato porque solo actúa cuando algo desencaja.
    new MutationObserver(function () {
        var active = currentSource();
        var panel  = active ? document.querySelector('[data-rf-filters="' + active + '"]') : null;
        if (panel && panel.style.display === 'none') syncPanels();
    }).observe(document.body, { childList: true, subtree: true });
})();
</script>
@endpush
