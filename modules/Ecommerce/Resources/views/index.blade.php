@extends('ecommerce::layouts.master')

@php
    $tagid           = Request::segment(3);
    $hasCategoryFilter = isset($currentCategory) && $currentCategory;
    $categoryName    = $hasCategoryFilter ? $currentCategory->name : null;
    $categoryUrl     = $hasCategoryFilter ? route('tenant.ecommerce.category', ['category' => $currentCategory->id]) : null;
    $homeUrl         = route('tenant.ecommerce.index');
@endphp

{{-- ── SEO: título y meta para páginas de categoría ─────────────── --}}
@if($hasCategoryFilter)
    @section('page_title', $categoryName . ' — Tienda Online')
    @section('meta_description', 'Explora todos los productos de ' . $categoryName . ' en nuestra tienda.')
    @section('canonical_url', $categoryUrl)
@else
    @section('canonical_url', $homeUrl)
@endif

{{-- ── Schema.org BreadcrumbList ────────────────────────────────── --}}
@if($hasCategoryFilter)
@section('breadcrumb_json')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Inicio",
            "item": "{{ $homeUrl }}"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "{{ $categoryName }}",
            "item": "{{ $categoryUrl }}"
        }
    ]
}
</script>
@endsection

{{-- ── Breadcrumbs visibles ──────────────────────────────────────── --}}
@section('breadcrumbs')
<ol class="ec-breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="{{ $homeUrl }}" itemprop="item">
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
        <span itemprop="name" aria-current="page">{{ $categoryName }}</span>
        <meta itemprop="position" content="2">
        <meta itemprop="item" content="{{ $categoryUrl }}">
    </li>
</ol>
@endsection
@endif

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-12 ecommerce-view" style="padding-top: 0">

            {{-- ── H1 SEO (oculto visualmente si hay slider, visible si no) ── --}}
            @php
                $storeName = $company->trade_name ?? $company->name ?? 'Tienda Online';
                $storeDesc = $seo->seo_description ?? 'Descubre nuestros productos al mejor precio.';
            @endphp
            @if(!$tagid && !$hasCategoryFilter)
                <h1 class="ec-seo-h1">{{ $storeName }}</h1>
                <p class="ec-seo-intro">{{ $storeDesc }}</p>
            @elseif($hasCategoryFilter)
                <h1 class="ec-seo-h1">{{ $categoryName }} — {{ $storeName }}</h1>
            @endif

            {{-- ── SLIDER / BANNER ──────────────────────────────────── --}}
            @if(!$tagid && !$hasCategoryFilter)
                @include('ecommerce::layouts.partials_ecommerce.home_slider')
            @endif

            {{-- Categorías: se muestran como pills de filtro dentro de la sección de productos --}}

            {{-- ── FLASH SALE (primero: urgencia temporal) ──────────── --}}
            @if(!$hasCategoryFilter)
                @include('ecommerce::layouts.partials_ecommerce.flash_sale')
            @endif

            {{-- ── PAQUETES / BUNDLES ───────────────────────────────── --}}
            @if(!$hasCategoryFilter)
                @include('ecommerce::layouts.partials_ecommerce.bundles')
            @endif

            {{-- ── PRODUCTOS ────────────────────────────────────────── --}}
            <section class="ec-home-section" aria-label="{{ $hasCategoryFilter ? 'Productos de ' . $categoryName : 'Catálogo de productos' }}">
                <div class="ec-section-header">
                    <h2 class="ec-section-title">
                        @if($hasCategoryFilter)
                            {{ $categoryName }}
                        @elseif($tagid)
                            Productos de la categoría
                        @else
                            Explora nuestros productos
                        @endif
                    </h2>
                    @if($hasCategoryFilter)
                        <a href="{{ $homeUrl }}" class="ec-section-link">← Ver todos</a>
                    @else
                        <a href="{{ $homeUrl }}" class="ec-section-link">Ver todos</a>
                    @endif
                </div>

                {{-- ── Sticky zone: filtros + pills ───────────── --}}
                <div class="ec-filter-sticky-zone">
                    {{-- Filtros y ordenación --}}
                    @include('ecommerce::layouts.partials_ecommerce.filters')

                    {{-- Category pills --}}
                    @if(!$hasCategoryFilter && isset($categories) && $categories->count())
                    <div class="ec-category-pills" id="ec-category-pills">
                        <button class="ec-cat-pill ec-cat-pill--active" data-category-id="">Todos</button>
                        @foreach($categories as $cat)
                        <button class="ec-cat-pill" data-category-id="{{ $cat->id }}" data-category-name="{{ $cat->name }}">
                            {{ $cat->name }}
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>{{-- /ec-filter-sticky-zone --}}

                {{-- AJAX products wrapper --}}
                <div id="ec-filter-results" class="ec-filter-results">
                    @include('ecommerce::layouts.partials_ecommerce.products_grid')
                </div>
            </section>

            {{-- ── Tracking: ViewCategory / Search ──────────────────── --}}
            @if($hasCategoryFilter)
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.EcommerceTracker) {
                    EcommerceTracker.viewCategory({
                        id:       '{{ $currentCategory->id }}',
                        category: '{{ addslashes($currentCategory->name) }}'
                    });
                }
            });
            </script>
            @endif

            @if(request('q'))
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.EcommerceTracker) {
                    EcommerceTracker.search({ query: '{{ addslashes(request("q")) }}' });
                }
            });
            </script>
            @endif

            {{-- ── OFERTAS / SPOTS ──────────────────────────────────── --}}
            @if(!$hasCategoryFilter)
            @php
                $spotsHasImages = isset($spots) && $spots->whereNotNull('image_url')->where('image_url', '!=', '')->count() > 0;
            @endphp
            @if($spotsHasImages)
            <section class="ec-home-section ec-home-section--offers" aria-label="Ofertas y promociones">
                <div class="ec-section-header">
                    <h2 class="ec-section-title">Ofertas especiales</h2>
                </div>
                @include('ecommerce::layouts.partials_ecommerce.offers')
            </section>
            @endif
            @endif

        </div>

        {{-- ═══ TRUST BADGES ═══ --}}
        <section class="ec-trust-badges" aria-label="Garantías">
            <div class="ec-trust-grid">
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                    <strong>Compra segura</strong>
                    <span>Datos protegidos con SSL</span>
                </div>
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <strong>Envío a todo el Perú</strong>
                    <span>Despacho en 24-48h</span>
                </div>
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="1.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                    <strong>Garantía de calidad</strong>
                    <span>Cambios y devoluciones</span>
                </div>
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="1.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <strong>Atención personalizada</strong>
                    <span>WhatsApp y correo</span>
                </div>
            </div>
        </section>

        {{-- ═══ TESTIMONIOS / RESEÑAS REALES ═══ --}}
        @php
            $latestReviews = \App\Models\Tenant\ProductReview::where('status', 'approved')
                ->where('rating', '>=', 4)
                ->with('item:id,description,slug,image')
                ->latest()
                ->limit(6)
                ->get();
        @endphp
        @if($latestReviews->count() >= 3)
        <section class="ec-home-section ec-testimonials-section" aria-label="Opiniones de clientes">
            <div class="ec-section-header">
                <h2 class="ec-section-title">Lo que dicen nuestros clientes</h2>
            </div>
            <div class="ec-testimonials-grid">
                @foreach($latestReviews as $review)
                <div class="ec-testimonial-card">
                    <div class="ec-testimonial-stars">
                        @for($i = 1; $i <= 5; $i++)
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="{{ $i <= $review->rating ? '#F59E0B' : '#e5e7eb' }}" stroke="none">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        @endfor
                    </div>
                    <p class="ec-testimonial-text">"{{ \Illuminate\Support\Str::limit($review->comment, 150) }}"</p>
                    <div class="ec-testimonial-author">
                        <strong>{{ $review->reviewer_name ?? 'Cliente verificado' }}</strong>
                        @if($review->item)
                        <a href="/ecommerce/item/{{ $review->item->slug ?? $review->item->id }}" class="ec-testimonial-product">
                            {{ \Illuminate\Support\Str::limit($review->item->description, 40) }}
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══ PRODUCTOS VISTOS RECIENTEMENTE ═══ --}}
        <section class="ec-home-section ec-recently-section" id="ec-recently-viewed-section" style="display:none" aria-label="Vistos recientemente">
            <div class="ec-section-header">
                <h2 class="ec-section-title">Vistos recientemente</h2>
            </div>
            <div class="ec-recently-grid" id="ec-recently-viewed-grid"></div>
        </section>

    </div>
</div>

<style>
/* ═══ SEO H1 ═══ */
.ec-seo-h1 { font-size: 1.6rem; font-weight: 700; color: #1e293b; margin: 1.5rem 0 0.3rem; }
.ec-seo-intro { font-size: 14px; color: #64748b; margin: 0 0 1rem; line-height: 1.5; }

/* ═══ TRUST BADGES ═══ */
.ec-trust-badges { padding: 2rem 0; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; margin: 2rem 0; }
.ec-trust-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; max-width: 900px; margin: 0 auto; text-align: center; }
.ec-trust-item { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.ec-trust-item strong { font-size: 14px; color: #1e293b; }
.ec-trust-item span { font-size: 12px; color: #94a3b8; }
@media(max-width:768px) { .ec-trust-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; } }

/* ═══ TESTIMONIOS ═══ */
.ec-testimonials-section { margin-top: 2rem; }
.ec-testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; }
.ec-testimonial-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; transition: box-shadow .2s; }
.ec-testimonial-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.ec-testimonial-stars { display: flex; gap: 2px; margin-bottom: 10px; }
.ec-testimonial-text { font-size: 14px; color: #374151; line-height: 1.5; margin: 0 0 12px; font-style: italic; }
.ec-testimonial-author strong { display: block; font-size: 13px; color: #1e293b; }
.ec-testimonial-product { display: block; font-size: 11px; color: #4F46E5; text-decoration: none; margin-top: 2px; }
@media(max-width:768px) { .ec-testimonials-grid { grid-template-columns: 1fr; } }

/* ═══ RECENTLY VIEWED ═══ */
.ec-recently-section { margin-top: 2rem; }
.ec-recently-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; }
.ec-recently-card { text-align: center; text-decoration: none; color: inherit; }
.ec-recently-card img { width: 100%; aspect-ratio: 1; object-fit: contain; border-radius: 10px; border: 1px solid #f1f5f9; background: #fafafa; }
.ec-recently-card .ec-rc-name { font-size: 12px; color: #374151; margin-top: 6px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ec-recently-card .ec-rc-price { font-size: 14px; font-weight: 700; color: #4F46E5; }
@media(max-width:768px) { .ec-recently-grid { grid-template-columns: repeat(3, 1fr); } }
</style>

<script>
// Recently viewed - render from localStorage
document.addEventListener('DOMContentLoaded', function(){
    try {
        var viewed = JSON.parse(localStorage.getItem('recently_viewed') || '[]');
        if (!viewed.length) return;
        var section = document.getElementById('ec-recently-viewed-section');
        var grid = document.getElementById('ec-recently-viewed-grid');
        if (!section || !grid) return;
        var html = '';
        viewed.slice(0, 6).forEach(function(item){
            var img = item.image || '/logo/imagen-no-disponible.jpg';
            var url = '/ecommerce/item/' + (item.slug || item.id);
            html += '<a href="'+url+'" class="ec-recently-card">'
                + '<img src="'+img+'" alt="'+item.name+'" loading="lazy">'
                + '<span class="ec-rc-name">'+item.name+'</span>'
                + '<span class="ec-rc-price">S/ '+(Number(item.price)||0).toFixed(2)+'</span>'
                + '</a>';
        });
        grid.innerHTML = html;
        section.style.display = 'block';
    } catch(e) {}
});
</script>
@endsection
