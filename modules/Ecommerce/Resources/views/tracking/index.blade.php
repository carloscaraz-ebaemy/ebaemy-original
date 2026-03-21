@extends('ecommerce::layouts.master')

@section('page_title', 'Seguimiento de pedido')
@section('meta_description', 'Consulta el estado de tu pedido ingresando tu número de nota de venta o guía de courier.')

@section('breadcrumbs')
<ol class="ec-breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="{{ route('tenant.ecommerce.index') }}" itemprop="item">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span itemprop="name">Inicio</span>
        </a>
        <meta itemprop="position" content="1">
    </li>
    <li class="ec-breadcrumb__sep" aria-hidden="true">/</li>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <span itemprop="name" aria-current="page">Seguimiento de pedido</span>
        <meta itemprop="position" content="2">
    </li>
</ol>
@endsection

@section('content')
<div class="container ec-tracking-wrap">

    {{-- ── Hero ──────────────────────────────────────────── --}}
    <div class="ec-tracking-hero">
        <div class="ec-tracking-hero__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="3" width="15" height="13"/>
                <path d="M16 8h4l3 3v5h-7V8z"/>
                <circle cx="5.5" cy="18.5" r="2.5"/>
                <circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
        </div>
        <h1 class="ec-tracking-hero__title">Seguimiento de pedido</h1>
        <p class="ec-tracking-hero__sub">
            Ingresa tu número de nota de venta (ej: <code>NV-00001</code>) o el código de guía del courier.
        </p>
    </div>

    {{-- ── Formulario ───────────────────────────────────── --}}
    <div class="ec-tracking-search-wrap">
        <form method="GET" action="{{ route('ecommerce.tracking') }}" class="ec-tracking-form">
            <div class="ec-tracking-input-group">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" class="ec-tracking-search-icon" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text"
                       name="q"
                       value="{{ $query ?? '' }}"
                       class="ec-tracking-input"
                       placeholder="NV-00001 o código de guía..."
                       aria-label="Número de pedido o guía"
                       required
                       autofocus>
                <button type="submit" class="ec-tracking-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    Buscar
                </button>
            </div>
        </form>
    </div>

    {{-- ── Error ────────────────────────────────────────── --}}
    @if($error)
    <div class="ec-tracking-alert" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        {{ $error }}
    </div>
    @endif

    {{-- ── Resultado ────────────────────────────────────── --}}
    @if($saleNote)
    <div class="ec-tracking-result">

        {{-- Cabecera del pedido --}}
        <div class="ec-tracking-result__header">
            <div class="ec-tracking-result__info">
                <span class="ec-tracking-result__number">{{ $saleNote->number_full }}</span>
                <span class="ec-tracking-result__date">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    {{ $saleNote->date_of_issue ? \Carbon\Carbon::parse($saleNote->date_of_issue)->format('d/m/Y') : '' }}
                </span>
            </div>
            @if($saleNote->logistic_status)
            <span class="ec-tracking-badge ec-tracking-badge--{{ strtolower($saleNote->logistic_status->value) }}">
                {{ $saleNote->logistic_status->label() }}
            </span>
            @endif
        </div>

        {{-- Timeline --}}
        <div class="ec-tracking-timeline" aria-label="Estado del pedido">
            @foreach($timeline as $step)
            <div class="ec-tracking-step {{ $step['active'] ? 'ec-tracking-step--active' : '' }} {{ $step['completed'] ? 'ec-tracking-step--done' : '' }}">
                <div class="ec-tracking-step__icon" aria-hidden="true">
                    @if($step['completed'] && !$step['active'])
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    @elseif($step['active'])
                        <span class="ec-tracking-step__pulse"></span>
                    @endif
                </div>
                @if(!$loop->last)
                <div class="ec-tracking-step__line {{ $step['completed'] ? 'ec-tracking-step__line--done' : '' }}"></div>
                @endif
                <div class="ec-tracking-step__body">
                    <p class="ec-tracking-step__label">
                        {{ $step['label'] }}
                        @if($step['active'])
                        <span class="ec-tracking-step__badge">Actual</span>
                        @endif
                    </p>
                    <p class="ec-tracking-step__desc">{{ $step['description'] }}</p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Datos del courier --}}
        @if($saleNote->courier_name || $saleNote->tracking_number)
        <div class="ec-tracking-courier">
            <h3 class="ec-tracking-section-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="1" y="3" width="15" height="13"/>
                    <path d="M16 8h4l3 3v5h-7V8z"/>
                    <circle cx="5.5" cy="18.5" r="2.5"/>
                    <circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
                Datos de envío
            </h3>
            <div class="ec-tracking-courier__grid">
                @if($saleNote->courier_name)
                <div class="ec-tracking-courier__item">
                    <span class="ec-tracking-courier__label">Courier</span>
                    <span class="ec-tracking-courier__value">{{ $saleNote->courier_name }}</span>
                </div>
                @endif
                @if($saleNote->tracking_number)
                <div class="ec-tracking-courier__item">
                    <span class="ec-tracking-courier__label">N° de guía</span>
                    <span class="ec-tracking-courier__value ec-tracking-guide">{{ $saleNote->tracking_number }}</span>
                </div>
                @endif
                @if($saleNote->dispatch_date)
                <div class="ec-tracking-courier__item">
                    <span class="ec-tracking-courier__label">Fecha de despacho</span>
                    <span class="ec-tracking-courier__value">{{ $saleNote->dispatch_date->format('d/m/Y H:i') }}</span>
                </div>
                @endif
                @if($saleNote->shipping_address)
                <div class="ec-tracking-courier__item ec-tracking-courier__item--full">
                    <span class="ec-tracking-courier__label">Dirección de entrega</span>
                    <span class="ec-tracking-courier__value">{{ $saleNote->shipping_address }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Productos --}}
        @if($saleNote->items && $saleNote->items->count())
        <div class="ec-tracking-products">
            <h3 class="ec-tracking-section-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                Productos del pedido
            </h3>
            <div class="ec-tracking-items">
                @foreach($saleNote->items->take(5) as $item)
                @php
                    $itm = $item->relation_item ?? null;
                    $img = ($itm && $itm->image && $itm->image !== 'imagen-no-disponible.jpg')
                           ? asset('storage/uploads/items/' . $itm->image)
                           : asset('logo/imagen-no-disponible.jpg');
                    $name = optional($itm)->description ?? $item->description ?? 'Producto';
                @endphp
                <div class="ec-tracking-item">
                    <img src="{{ $img }}" alt="{{ $name }}" class="ec-tracking-item__img"
                         onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                    <div class="ec-tracking-item__info">
                        <p class="ec-tracking-item__name">{{ $name }}</p>
                        <p class="ec-tracking-item__qty">Cant: {{ $item->quantity }}</p>
                    </div>
                    <p class="ec-tracking-item__price">S/ {{ number_format($item->total, 2) }}</p>
                </div>
                @endforeach
                @if($saleNote->items->count() > 5)
                <p class="ec-tracking-more">+ {{ $saleNote->items->count() - 5 }} producto(s) más</p>
                @endif
            </div>
            <div class="ec-tracking-total">
                <span>Total del pedido</span>
                <strong>S/ {{ number_format($saleNote->total, 2) }}</strong>
            </div>
        </div>
        @endif

    </div>
    @endif

    {{-- Estado vacío (sin búsqueda todavía) --}}
    @if(!$query && !$saleNote)
    <div class="ec-tracking-empty">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="1" aria-hidden="true"
             style="color:#ddd; margin-bottom:16px;">
            <rect x="1" y="3" width="15" height="13"/>
            <path d="M16 8h4l3 3v5h-7V8z"/>
            <circle cx="5.5" cy="18.5" r="2.5"/>
            <circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
        <p>Ingresa tu número de pedido para ver el estado de tu entrega.</p>
    </div>
    @endif

</div>

<style>
.ec-tracking-wrap {
    max-width: 760px;
    padding: 8rem 15px 4rem;
}
/* Hero */
.ec-tracking-hero { text-align: center; padding: 2rem 0 1.5rem; }
.ec-tracking-hero__icon {
    width: 72px; height: 72px;
    background: hsl(var(--primary-h),var(--primary-s),92%);
    color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}
.ec-tracking-hero__title { font-size: 2.6rem; font-weight: 800; color: #222; margin: 0 0 8px; }
.ec-tracking-hero__sub { font-size: 1.4rem; color: #777; margin: 0; }
.ec-tracking-hero__sub code {
    background: #f1f3f5; border-radius: 4px;
    padding: 1px 6px; font-size: 1.3rem; color: #555;
}
/* Search */
.ec-tracking-search-wrap { margin: 2rem 0; }
.ec-tracking-form {}
.ec-tracking-input-group {
    display: flex;
    align-items: center;
    border: 2px solid #ddd;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    transition: border-color .2s;
}
.ec-tracking-input-group:focus-within {
    border-color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
}
.ec-tracking-search-icon {
    flex-shrink: 0;
    margin: 0 12px;
    color: #bbb;
}
.ec-tracking-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 1.5rem;
    padding: 13px 0;
    background: transparent;
    color: #333;
}
.ec-tracking-btn {
    display: flex;
    align-items: center;
    gap: 7px;
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
    border: none;
    padding: 13px 22px;
    font-size: 1.45rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity .15s;
}
.ec-tracking-btn:hover { opacity: .88; }
/* Alert */
.ec-tracking-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 10px;
    padding: 14px 18px;
    font-size: 1.4rem;
    color: #7a6000;
    margin-bottom: 1.5rem;
}
/* Result card */
.ec-tracking-result {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    overflow: hidden;
    margin-top: 1.5rem;
}
.ec-tracking-result__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: hsl(var(--primary-h),var(--primary-s),96%);
    border-bottom: 1px solid hsl(var(--primary-h),var(--primary-s),88%);
    padding: 16px 24px;
}
.ec-tracking-result__info { display: flex; flex-direction: column; gap: 4px; }
.ec-tracking-result__number { font-size: 1.8rem; font-weight: 800; color: #222; }
.ec-tracking-result__date {
    display: flex; align-items: center; gap: 5px;
    font-size: 1.2rem; color: #888;
}
/* Badge estado */
.ec-tracking-badge {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 1.25rem;
    font-weight: 700;
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
}
.ec-tracking-badge--entregado,
.ec-tracking-badge--recogido,
.ec-tracking-badge--entrega_inmediata { background: #22c55e; }
.ec-tracking-badge--anulado { background: #ef4444; }
/* Timeline */
.ec-tracking-timeline {
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 0;
}
.ec-tracking-step {
    display: grid;
    grid-template-columns: 36px 2px 1fr;
    gap: 0 12px;
    align-items: start;
}
.ec-tracking-step__icon {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #f1f3f5;
    border: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #adb5bd;
    position: relative;
    z-index: 1;
}
.ec-tracking-step--done .ec-tracking-step__icon {
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    border-color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
}
.ec-tracking-step--active .ec-tracking-step__icon {
    background: #fff;
    border-color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    box-shadow: 0 0 0 4px hsl(var(--primary-h),var(--primary-s),90%);
}
.ec-tracking-step__pulse {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    animation: ec-pulse 1.4s infinite;
}
@keyframes ec-pulse {
    0%,100% { transform: scale(1); opacity: 1; }
    50%      { transform: scale(1.5); opacity: .6; }
}
.ec-tracking-step__line {
    width: 2px;
    min-height: 32px;
    background: #dee2e6;
    margin: 0 auto;
    grid-row: 2;
}
.ec-tracking-step__line--done { background: hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
.ec-tracking-step__body {
    padding: 6px 0 24px;
    grid-column: 3;
    grid-row: 1 / 3;
}
.ec-tracking-step__label {
    font-size: 1.45rem;
    font-weight: 700;
    color: #333;
    margin: 0 0 3px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ec-tracking-step--active .ec-tracking-step__label { color: hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
.ec-tracking-step__badge {
    font-size: 1.05rem;
    font-weight: 700;
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
    padding: 2px 8px;
    border-radius: 20px;
}
.ec-tracking-step__desc { font-size: 1.25rem; color: #888; margin: 0; }
/* Secciones internas */
.ec-tracking-courier,
.ec-tracking-products {
    padding: 20px 24px;
    border-top: 1px solid #f0f0f0;
}
.ec-tracking-section-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #444;
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0 0 14px;
}
.ec-tracking-courier__grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.ec-tracking-courier__item { display: flex; flex-direction: column; gap: 3px; }
.ec-tracking-courier__item--full { grid-column: 1 / -1; }
.ec-tracking-courier__label { font-size: 1.1rem; color: #aaa; }
.ec-tracking-courier__value { font-size: 1.35rem; font-weight: 600; color: #333; }
.ec-tracking-guide {
    font-family: monospace;
    background: #f8f9fa;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 1.25rem;
}
/* Items */
.ec-tracking-items { display: flex; flex-direction: column; gap: 12px; }
.ec-tracking-item { display: flex; align-items: center; gap: 12px; }
.ec-tracking-item__img {
    width: 50px; height: 50px;
    object-fit: contain;
    border-radius: 6px;
    border: 1px solid #eee;
    background: #fafafa;
    flex-shrink: 0;
}
.ec-tracking-item__info { flex: 1; min-width: 0; }
.ec-tracking-item__name {
    font-size: 1.3rem; font-weight: 600; color: #333;
    margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ec-tracking-item__qty { font-size: 1.2rem; color: #999; margin: 0; }
.ec-tracking-item__price { font-size: 1.35rem; font-weight: 700; color: #333; white-space: nowrap; }
.ec-tracking-more { font-size: 1.2rem; color: #aaa; text-align: center; margin: 6px 0 0; }
.ec-tracking-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #eee;
    margin-top: 14px;
    padding-top: 12px;
    font-size: 1.5rem;
    font-weight: 800;
    color: #222;
}
.ec-tracking-total span { font-weight: 400; color: #777; }
/* Empty */
.ec-tracking-empty {
    text-align: center;
    padding: 3rem 0;
    color: #bbb;
    font-size: 1.4rem;
}
@media (max-width: 575px) {
    .ec-tracking-result__header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .ec-tracking-courier__grid { grid-template-columns: 1fr; }
    .ec-tracking-timeline { padding: 20px 16px; }
}
</style>
@endsection
