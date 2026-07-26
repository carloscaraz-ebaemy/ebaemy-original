@extends('tenant.layouts.app')

@push('styles')
    @include('tenant.raffles._tokens')
@endpush

@php
    $isNew   = !$raffle->exists;
    $action  = $isNew ? route('raffles.store') : route('raffles.update', $raffle);
    $dt      = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
    $d       = fn ($v) => $v ? $v->format('Y-m-d') : '';
    $sources = old('sources', $raffle->sources ?: [\App\Models\Tenant\Raffle::SOURCE_DOCUMENTS, \App\Models\Tenant\Raffle::SOURCE_SALE_NOTES]);
    $cats    = old('category_ids', $raffle->category_ids ?? []);
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
                                <input id="rf_prize_image" type="file" name="prize_image_file" class="rf-input"
                                       accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/bmp,image/heic,image/heif">
                                <div class="rf-note">Se muestra en el formulario de participación, en el enlace del cliente, en el historial y en la ficha del ganador.</div>
                                @if($raffle->prize_image)
                                    <img src="{{ $raffle->prizeImageUrl('medium') }}" alt="Premio" class="rf-prize__img mt-2">
                                @endif
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
                    </div>
                </div>
            </div>

            {{-- ───────────── Columna derecha ───────────── --}}
            <div class="col-lg-5">

                <div class="rf-card">
                    <div class="rf-card__body">
                        <div class="rf-section">
                            <div class="rf-section__label">Vigencia</div>

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

                        <div class="rf-section">
                            <div class="rf-section__label">Criterios de elegibilidad</div>
                            <div class="rf-note mb-2">Definen qué pedidos hacen elegible a un cliente. Un cliente aparece una sola vez aunque tenga varios pedidos.</div>

                            <div class="rf-field">
                                <label>Origen de los pedidos</label>
                                @foreach(\App\Models\Tenant\Raffle::SOURCES as $key => $label)
                                    <label class="rf-check">
                                        <input type="checkbox" name="sources[]" value="{{ $key }}" @checked(in_array($key, (array) $sources, true))>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <label class="rf-check">
                                <input type="checkbox" name="require_paid" value="1" @checked(old('require_paid', $raffle->require_paid ?? true))>
                                <span>Solo pedidos con <strong>pago confirmado</strong></span>
                            </label>

                            <div class="row g-2 mt-1">
                                <div class="col-6 rf-field">
                                    <label for="rf_pfrom">Compras desde</label>
                                    <input id="rf_pfrom" type="date" name="purchase_from" class="rf-input"
                                           value="{{ old('purchase_from', $d($raffle->purchase_from)) }}">
                                </div>
                                <div class="col-6 rf-field">
                                    <label for="rf_pto">Compras hasta</label>
                                    <input id="rf_pto" type="date" name="purchase_to" class="rf-input"
                                           value="{{ old('purchase_to', $d($raffle->purchase_to)) }}">
                                </div>
                            </div>

                            <div class="rf-field">
                                <label for="rf_min">Monto mínimo de compra (opcional)</label>
                                <input id="rf_min" type="number" step="0.01" min="0" name="min_amount" class="rf-input"
                                       value="{{ old('min_amount', $raffle->min_amount) }}" placeholder="Ej. 200.00">
                                <div class="rf-note">Se evalúa sobre el <strong>acumulado</strong> del cliente en el periodo, no por pedido.</div>
                            </div>

                            <div class="rf-field">
                                <label for="rf_estab">Sucursal (opcional)</label>
                                <select id="rf_estab" name="establishment_id" class="rf-input">
                                    <option value="">Todas</option>
                                    @foreach($establishments as $e)
                                        <option value="{{ $e->id }}" @selected((int) old('establishment_id', $raffle->establishment_id) === (int) $e->id)>{{ $e->description }}</option>
                                    @endforeach
                                </select>
                                <div class="rf-note">Aplica a comprobantes y notas de venta.</div>
                            </div>

                            @if($channels->count())
                                <div class="rf-field">
                                    <label for="rf_channel">Canal de venta (opcional)</label>
                                    <select id="rf_channel" name="channel_id" class="rf-input">
                                        <option value="">Todos</option>
                                        @foreach($channels as $c)
                                            <option value="{{ $c->id }}" @selected((int) old('channel_id', $raffle->channel_id) === (int) $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="rf-note">Aplica a los pedidos de tienda virtual.</div>
                                </div>
                            @endif

                            @if($categories->count())
                                <div class="rf-field">
                                    <label for="rf_cats">Categorías de producto (opcional)</label>
                                    <select id="rf_cats" name="category_ids[]" class="rf-input" multiple size="5">
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" @selected(in_array($cat->id, (array) $cats))>{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="rf-note">Ctrl/Cmd + clic para elegir varias.</div>
                                </div>
                            @endif

                            <div class="rf-field">
                                <label for="rf_item_search">Productos específicos (opcional)</label>
                                <input id="rf_item_search" class="rf-input" placeholder="Escribe para buscar un producto…" autocomplete="off">
                                <div id="rf_item_results" class="rf-note"></div>
                                <div id="rf_item_chips" class="rf-thumbs"></div>
                                <div class="rf-note">Con filtro por categoría o producto, los pedidos de tienda virtual quedan fuera (sus ítems no son consultables).</div>
                            </div>
                        </div>

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
 * Chips de productos del criterio de elegibilidad.
 * Delegación en `document` + re-consulta fresca de nodos: Vue monta en
 * #main-wrapper y re-renderiza el DOM, así que nunca guardamos referencias.
 * Ver feedback_vue_mainwrapper_rerender.
 */
(function () {
    var SEARCH_URL = {!! json_encode($searchUrl) !!};
    var selected   = {!! $itemsJson !!} || [];
    var timer      = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function renderChips() {
        var box = document.getElementById('rf_item_chips');
        if (!box) return;
        box.innerHTML = selected.map(function (it) {
            return '<span class="rf-pill rf-pill--finished" style="cursor:pointer" data-rf-remove="' + it.id + '">'
                 + esc(it.label) + ' ✕'
                 + '<input type="hidden" name="item_ids[]" value="' + esc(it.id) + '"></span>';
        }).join(' ');
    }

    function renderResults(rows) {
        var box = document.getElementById('rf_item_results');
        if (!box) return;
        if (!rows.length) { box.innerHTML = 'Sin resultados.'; return; }
        box.innerHTML = rows.map(function (r) {
            return '<a href="#" class="d-block py-1" data-rf-add="' + esc(r.id) + '" data-rf-label="' + esc(r.label) + '">'
                 + esc(r.label) + '</a>';
        }).join('');
    }

    document.addEventListener('input', function (ev) {
        if (!ev.target || ev.target.id !== 'rf_item_search') return;
        var term = ev.target.value.trim();
        clearTimeout(timer);
        if (term.length < 2) {
            var box = document.getElementById('rf_item_results');
            if (box) box.innerHTML = '';
            return;
        }
        timer = setTimeout(function () {
            fetch(SEARCH_URL + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(renderResults)
                .catch(function () {});
        }, 250);
    });

    document.addEventListener('click', function (ev) {
        var add = ev.target.closest ? ev.target.closest('[data-rf-add]') : null;
        if (add) {
            ev.preventDefault();
            var id = parseInt(add.getAttribute('data-rf-add'), 10);
            if (!selected.some(function (s) { return parseInt(s.id, 10) === id; })) {
                selected.push({ id: id, label: add.getAttribute('data-rf-label') });
                renderChips();
            }
            var input = document.getElementById('rf_item_search');
            var res   = document.getElementById('rf_item_results');
            if (input) input.value = '';
            if (res) res.innerHTML = '';
            return;
        }

        var rm = ev.target.closest ? ev.target.closest('[data-rf-remove]') : null;
        if (rm) {
            ev.preventDefault();
            var rid = parseInt(rm.getAttribute('data-rf-remove'), 10);
            selected = selected.filter(function (s) { return parseInt(s.id, 10) !== rid; });
            renderChips();
        }
    });

    renderChips();

    // Si Vue re-renderiza #main-wrapper, el contenedor de chips vuelve vacío:
    // lo repintamos. Barato porque solo actúa cuando falta contenido.
    new MutationObserver(function () {
        var box = document.getElementById('rf_item_chips');
        if (box && selected.length && !box.querySelector('[data-rf-remove]')) {
            renderChips();
        }
    }).observe(document.body, { childList: true, subtree: true });
})();
</script>
@endpush
