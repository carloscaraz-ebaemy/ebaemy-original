@extends('ecommerce::layouts.master')

@section('page_title', $bundle->description . ' — Pack especial')
@section('meta_description', 'Obtén el pack ' . $bundle->description . ' con ' . $savingsPct . '% de descuento. ' . ($bundle->additional_information ?? ''))
@section('og_type', 'product')
@section('og_title', $bundle->description . ' — Pack especial')
@section('og_image', $mainImage)

@section('content')

{{-- SEO: Schema.org Product para el pack --}}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "{{ $bundle->description }}",
    "description": "Pack especial: {{ $bundle->description }}. Ahorra {{ $savingsPct }}%.",
    "image": "{{ $mainImage }}",
    "sku": "{{ $bundle->internal_id ?? 'PACK-' . $bundle->id }}",
    "offers": {
        "@type": "Offer",
        "priceCurrency": "PEN",
        "price": "{{ number_format($packPrice, 2, '.', '') }}",
        "availability": "{{ $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}",
        "url": "{{ url()->current() }}"
    }
}
</script>

<div class="ec-bundle-landing">

    {{-- ── HERO ────────────────────────────────────────────────────── --}}
    <div class="ec-bl-hero">
        <div class="ec-bl-hero__bg" aria-hidden="true"></div>

        <div class="container">
            <div class="ec-bl-hero__inner">

                {{-- Galería --}}
                <div class="ec-bl-gallery">
                    <div class="ec-bl-gallery__main">
                        <img id="ec-bl-main-img"
                             src="{{ $mainImage }}"
                             alt="{{ $bundle->description }}"
                             class="ec-bl-gallery__img">
                        @if($savingsPct > 0)
                        <span class="ec-bl-save-badge">-{{ $savingsPct }}%</span>
                        @endif
                    </div>
                    @if($galleryImages->count() > 1)
                    <div class="ec-bl-gallery__thumbs">
                        @foreach($galleryImages as $idx => $img)
                        <button type="button"
                                class="ec-bl-thumb {{ $idx === 0 ? 'ec-bl-thumb--active' : '' }}"
                                data-img="{{ $img }}"
                                aria-label="Ver imagen {{ $idx + 1 }}">
                            <img src="{{ $img }}" alt="">
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- Info principal --}}
                <div class="ec-bl-info">
                    <span class="ec-bl-info__tag">Pack especial</span>
                    <h1 class="ec-bl-info__title">{{ $bundle->description }}</h1>

                    @if($bundle->additional_information)
                    <p class="ec-bl-info__desc">{{ $bundle->additional_information }}</p>
                    @endif

                    {{-- Precio --}}
                    <div class="ec-bl-price-block">
                        @if($savings > 0)
                        <span class="ec-bl-price-old">
                            {{ $symbol }} {{ number_format($normalTotal, 2) }}
                        </span>
                        @endif
                        <span class="ec-bl-price-now">
                            {{ $symbol }} {{ number_format($packPrice, 2) }}
                        </span>
                        @if($savings > 0)
                        <span class="ec-bl-price-save">
                            Ahorras {{ $symbol }} {{ number_format($savings, 2) }}
                        </span>
                        @endif
                    </div>

                    {{-- Countdown: SOLO si hay Flash Sale activa --}}
                    @if($flashEndsAt)
                    <div class="ec-bl-countdown" id="ec-bl-countdown"
                         data-ends="{{ $flashEndsAt->timestamp * 1000 }}"
                         aria-label="Tiempo restante de la oferta">
                        <span class="ec-bl-countdown__label">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Oferta flash termina en:
                        </span>
                        <div class="ec-bl-countdown__boxes">
                            <div class="ec-bl-cd-box"><span id="ec-cd-h">00</span><small>h</small></div>
                            <div class="ec-bl-cd-sep">:</div>
                            <div class="ec-bl-cd-box"><span id="ec-cd-m">00</span><small>m</small></div>
                            <div class="ec-bl-cd-sep">:</div>
                            <div class="ec-bl-cd-box"><span id="ec-cd-s">00</span><small>s</small></div>
                        </div>
                        <div class="ec-bl-expired" id="ec-bl-expired" style="display:none">
                            <span class="ec-bl-expired__text">Esta oferta ha expirado</span>
                        </div>
                    </div>
                    @endif

                    {{-- Stock --}}
                    @if($stock > 0 && $stock <= 10)
                    <p class="ec-bl-low-stock">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="#ef4444" stroke-width="2.5" aria-hidden="true">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        ¡Solo quedan <strong>{{ $stock }}</strong> unidades!
                    </p>
                    @endif

                    {{-- CTA --}}
                    @if($stock > 0)
                    @php
                        $cartData = json_encode([
                            'id'              => $bundle->id,
                            'description'     => $bundle->description,
                            'sale_unit_price' => $packPrice,
                            'image'           => $bundle->image,
                            'currency_type_id'=> $bundle->currency_type_id,
                            'is_set'          => true,
                            'stock'           => $stock,
                        ]);
                    @endphp
                    <button type="button"
                            class="ec-bl-cta"
                            data-ec-cart="{{ $cartData }}"
                            id="ec-bl-add-cart">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        Agregar pack al carrito
                    </button>
                    @else
                    <button type="button" class="ec-bl-cta ec-bl-cta--oos" disabled>Agotado</button>
                    @endif

                    {{-- Beneficios --}}
                    <div class="ec-bl-perks">
                        <div class="ec-bl-perk">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Precio especial de pack
                        </div>
                        <div class="ec-bl-perk">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Todo en un solo pedido
                        </div>
                        @if($savings > 0)
                        <div class="ec-bl-perk">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Ahorras {{ $symbol }} {{ number_format($savings, 2) }} vs comprar por separado
                        </div>
                        @endif
                    </div>
                </div>{{-- /ec-bl-info --}}

            </div>{{-- /ec-bl-hero__inner --}}
        </div>
    </div>{{-- /ec-bl-hero --}}

    {{-- ── QUÉ INCLUYE ────────────────────────────────────────────── --}}
    @if($bundle->sets && $bundle->sets->count() > 0)
    <section class="ec-bl-includes">
        <div class="container">
            <h2 class="ec-bl-section-title">¿Qué incluye este pack?</h2>

            <div class="ec-bl-includes__grid">
                @php $sumIndividual = 0; @endphp
                @foreach($bundle->sets as $si)
                @if($si->individual_item)
                @php
                    $itm      = $si->individual_item;
                    $qty      = $si->quantity;
                    $unitPrice= (float) $itm->sale_unit_price;
                    $lineTotal= $unitPrice * $qty;
                    $sumIndividual += $lineTotal;
                    $imgSrc   = ($itm->image && $itm->image !== 'imagen-no-disponible.jpg')
                                ? asset('storage/uploads/items/' . $itm->image)
                                : asset('logo/imagen-no-disponible.jpg');
                    $itmSlug  = $itm->slug ?: $itm->id;
                @endphp
                <div class="ec-bl-inc-item">
                    <a href="{{ route('tenant.ecommerce.item', ['slug' => $itmSlug]) }}"
                       class="ec-bl-inc-img-wrap" tabindex="-1">
                        <img src="{{ $imgSrc }}" alt="{{ $itm->description }}"
                             loading="lazy"
                             onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                        @if($qty > 1)
                        <span class="ec-bl-inc-qty">×{{ number_format($qty, 0) }}</span>
                        @endif
                    </a>
                    <div class="ec-bl-inc-body">
                        <a href="{{ route('tenant.ecommerce.item', ['slug' => $itmSlug]) }}"
                           class="ec-bl-inc-name">{{ $itm->description }}</a>
                        @if($itm->additional_information)
                        <p class="ec-bl-inc-desc">{{ Str::limit($itm->additional_information, 60) }}</p>
                        @endif
                        <div class="ec-bl-inc-price">
                            @if($qty > 1)
                            <span class="ec-bl-inc-unit">{{ $symbol }} {{ number_format($unitPrice, 2) }} c/u</span>
                            @endif
                            <span class="ec-bl-inc-total">{{ $symbol }} {{ number_format($lineTotal, 2) }}</span>
                        </div>
                    </div>
                </div>
                @endif
                @endforeach
            </div>

            {{-- Tabla resumen precio --}}
            <div class="ec-bl-price-summary">
                <div class="ec-bl-ps-row">
                    <span>Precio individual total</span>
                    <span>{{ $symbol }} {{ number_format($normalTotal, 2) }}</span>
                </div>
                @if($savings > 0)
                <div class="ec-bl-ps-row ec-bl-ps-row--save">
                    <span>Descuento del pack</span>
                    <span>-{{ $symbol }} {{ number_format($savings, 2) }}</span>
                </div>
                @endif
                <div class="ec-bl-ps-row ec-bl-ps-row--total">
                    <span>Precio del pack</span>
                    <span>{{ $symbol }} {{ number_format($packPrice, 2) }}</span>
                </div>
            </div>

            {{-- CTA secundario --}}
            @if($stock > 0)
            <div class="text-center mt-4">
                <button type="button"
                        class="ec-bl-cta ec-bl-cta--outline"
                        data-ec-cart="{{ $cartData ?? json_encode(['id' => $bundle->id, 'description' => $bundle->description, 'sale_unit_price' => $packPrice, 'image' => $bundle->image, 'currency_type_id' => $bundle->currency_type_id]) }}"
                        onclick="window.scrollTo({top:0,behavior:'smooth'})">
                    Quiero este pack ahora
                </button>
            </div>
            @endif

        </div>
    </section>
    @endif

</div>{{-- /ec-bundle-landing --}}
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Galería de miniaturas ─────────────────────────────────────
    document.querySelectorAll('.ec-bl-thumb').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var img = document.getElementById('ec-bl-main-img');
            if (img) img.src = this.getAttribute('data-img');
            document.querySelectorAll('.ec-bl-thumb').forEach(function (b) {
                b.classList.remove('ec-bl-thumb--active');
            });
            this.classList.add('ec-bl-thumb--active');
        });
    });

    // ── Feedback al agregar al carrito ───────────────────────────
    var addBtn = document.getElementById('ec-bl-add-cart');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var orig = this.innerHTML;
            this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> ¡Agregado!';
            this.style.background = '#16a34a';
            var self = this;
            setTimeout(function () {
                self.innerHTML = orig;
                self.style.background = '';
            }, 2000);
        });
    }

    // ── Countdown (solo Flash Sale real) ─────────────────────────
    var countdownEl = document.getElementById('ec-bl-countdown');
    if (!countdownEl) return;

    var endsAt = parseInt(countdownEl.getAttribute('data-ends'), 10);
    if (!endsAt) return;

    var expired = false;

    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function onExpired() {
        if (expired) return;
        expired = true;
        // Ocultar countdown boxes y mostrar mensaje de expirado
        var boxes = countdownEl.querySelector('.ec-bl-countdown__boxes');
        var label = countdownEl.querySelector('.ec-bl-countdown__label');
        var expiredEl = document.getElementById('ec-bl-expired');
        if (boxes) boxes.style.display = 'none';
        if (label) label.style.display = 'none';
        if (expiredEl) expiredEl.style.display = 'block';

        // Deshabilitar boton de agregar al carrito
        var addBtn = document.getElementById('ec-bl-add-cart');
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.classList.add('ec-bl-cta--oos');
            addBtn.innerHTML = 'Oferta expirada';
        }

        // Ocultar precio de descuento y mostrar precio normal
        var priceOld = document.querySelector('.ec-bl-price-old');
        var priceSave = document.querySelector('.ec-bl-price-save');
        if (priceOld) priceOld.style.display = 'none';
        if (priceSave) priceSave.style.display = 'none';
    }

    function tick() {
        var diff = endsAt - Date.now();
        if (diff <= 0) {
            document.getElementById('ec-cd-h').textContent = '00';
            document.getElementById('ec-cd-m').textContent = '00';
            document.getElementById('ec-cd-s').textContent = '00';
            onExpired();
            return;
        }
        var h = Math.floor(diff / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        document.getElementById('ec-cd-h').textContent = pad(h);
        document.getElementById('ec-cd-m').textContent = pad(m);
        document.getElementById('ec-cd-s').textContent = pad(s);
    }

    tick();
    setInterval(tick, 1000);
}());
</script>
@endpush
