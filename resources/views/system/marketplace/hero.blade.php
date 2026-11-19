@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">🎄 Portada de temporada del marketplace</h3>
        <div class="d-flex gap-2">
            <a href="{{ route('marketplace.index') }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Ver el inicio ↗</a>
            <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Volver al dashboard</a>
        </div>
    </div>

    <div class="alert alert-info py-2 mb-3 small">
        Es el banner de arriba del inicio de <code>ebaemy.com/marketplace</code>: título, subtítulo,
        un botón y una o dos fotos de productos publicados. Apagado o fuera de fechas, no ocupa espacio.
    </div>

    @unless($hasColumn)
        <div class="alert alert-warning py-2 small">
            Falta la migración <code>2026_11_18_000003_add_marketplace_hero_to_configurations</code>: hasta correrla se
            muestra la portada por defecto y no se puede guardar.
        </div>
    @endunless

    @if(session('mp_hero_message'))
        <div class="alert alert-success py-2 small">{{ session('mp_hero_message') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if($current && !$current->is_visible_in_marketplace && old('cta_type', $hero['cta_type']) === 'category')
        <div class="alert alert-warning py-2 small">
            La categoría <strong>{{ $current->name }}</strong> está <strong>oculta</strong> en el marketplace (su página da 404).
            Mientras siga así, el botón lleva al listado filtrado por esa categoría, que sí funciona.
            Para publicarla: <code>php artisan marketplace:publish-category --category={{ $current->parent_id ? $current->parent_id : $current->id }} --first --apply</code>
            o márcala visible en Categorías.
        </div>
    @endif

    <form method="POST" action="{{ route('system.marketplace.hero.update') }}" class="row g-3">
        @csrf
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="heroEnabled" name="enabled" value="1"
                           @checked(old('enabled', $hero['enabled']))>
                    <label class="form-check-label fw-bold" for="heroEnabled">Mostrar la portada en el inicio</label>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="heroTitle">Título</label>
                    <input type="text" id="heroTitle" name="title" class="form-control" maxlength="80"
                           value="{{ old('title', $hero['title']) }}" placeholder="Navidad en casa">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="heroSub">Subtítulo <small class="text-muted fw-normal">(opcional, una línea)</small></label>
                    <input type="text" id="heroSub" name="subtitle" class="form-control" maxlength="160"
                           value="{{ old('subtitle', $hero['subtitle']) }}" placeholder="Adornos, luces y regalos de tiendas peruanas verificadas.">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold" for="heroCta">Texto del botón</label>
                        <input type="text" id="heroCta" name="cta_text" class="form-control" maxlength="40"
                               value="{{ old('cta_text', $hero['cta_text']) }}" placeholder="Ver Navidad">
                        <small class="text-muted">La flecha → se añade sola.</small>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold d-block">El botón lleva a…</label>
                        @php $ctaType = old('cta_type', $hero['cta_type']); @endphp
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="cta_type" id="ctaCat" value="category" @checked($ctaType !== 'url')>
                            <label class="form-check-label" for="ctaCat">una categoría</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="cta_type" id="ctaUrl" value="url" @checked($ctaType === 'url')>
                            <label class="form-check-label" for="ctaUrl">una dirección</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="heroCat">Categoría <small class="text-muted fw-normal">(destino del botón y de dónde salen las fotos)</small></label>
                    <select id="heroCat" name="category" class="form-select">
                        <option value="">— Ninguna —</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->full_slug }}" @selected(old('category', $hero['category']) === $c->full_slug)>
                                {{ $c->parent_id ? '   └ ' : '' }}{{ $c->name }}{{ $c->is_visible_in_marketplace ? '' : ' (oculta)' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="heroUrl">Dirección del botón <small class="text-muted fw-normal">(solo si elegiste «una dirección»)</small></label>
                    <input type="text" id="heroUrl" name="cta_url" class="form-control" maxlength="500"
                           value="{{ old('cta_url', $hero['cta_url']) }}" placeholder="/marketplace?on_offer=1  o  https://…">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="heroIds">Fotos: productos elegidos <small class="text-muted fw-normal">(opcional, hasta 2 IDs del marketplace)</small></label>
                    <input type="text" id="heroIds" name="listing_ids" class="form-control" maxlength="200"
                           value="{{ old('listing_ids', implode(', ', (array) $hero['listing_ids'])) }}" placeholder="ej. 120, 340">
                    <small class="text-muted">Vacío = los dos productos de la categoría con oferta o más vistos. El ID es el de
                        <a href="{{ route('system.marketplace.listings') }}">Listings</a>.</small>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="heroFrom">Desde <small class="text-muted fw-normal">(opcional)</small></label>
                        <input type="date" id="heroFrom" name="starts_at" class="form-control" value="{{ old('starts_at', $hero['starts_at']) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="heroTo">Hasta <small class="text-muted fw-normal">(opcional, incluido)</small></label>
                        <input type="date" id="heroTo" name="ends_at" class="form-control" value="{{ old('ends_at', $hero['ends_at']) }}">
                    </div>
                </div>
            </div></div>

            <button type="submit" class="btn btn-primary mt-3" @disabled(!$hasColumn)>Guardar portada</button>
        </div>

        <div class="col-lg-5">
            <div class="card"><div class="card-body">
                <h5 class="mb-3">Así se ve hoy</h5>
                @if($preview)
                    <div style="border-radius:16px;padding:18px;background:linear-gradient(135deg,#e8f6f5 0%,#fff 70%);border:1px solid #eef2f6;display:flex;gap:14px;align-items:center">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:22px;font-weight:800;line-height:1.1;color:#0f172a">{{ $preview->title }}</div>
                            @if($preview->subtitle)<div style="font-size:13px;color:#475569;margin:6px 0 10px">{{ $preview->subtitle }}</div>@endif
                            <a href="{{ $preview->cta_url }}" target="_blank" rel="noopener" style="display:inline-block;padding:8px 14px;border-radius:999px;background:#0f8a82;color:#fff;font-weight:700;font-size:13px;text-decoration:none">{{ $preview->cta_text }} →</a>
                        </div>
                        @foreach($preview->photos as $p)
                            <img src="{{ $p->image }}" alt="" width="84" height="84" style="width:84px;height:84px;object-fit:cover;border-radius:12px">
                        @endforeach
                    </div>
                    <small class="text-muted d-block mt-2">El botón va a: <code>{{ $preview->cta_url }}</code></small>
                    @if($preview->photos->isEmpty())
                        <small class="text-warning d-block mt-1">Sin fotos: la categoría no tiene productos publicados con imagen. Sale solo el texto.</small>
                    @endif
                @else
                    <p class="text-muted mb-0">Ahora mismo <strong>no se muestra</strong>: está apagada, fuera de fechas o sin destino.</p>
                @endif
            </div></div>
        </div>
    </form>
</div>
@endsection
