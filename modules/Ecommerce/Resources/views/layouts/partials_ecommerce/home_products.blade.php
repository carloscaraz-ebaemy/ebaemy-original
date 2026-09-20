{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index. --}}
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

                    {{-- Píldoras de categoría (internas del tenant).

                         Antes se pintaban TODAS: en una tienda con 63
                         categorías el listado empezaba con seis filas de
                         botones antes del primer producto. Ahora sólo salen
                         los grupos de primer nivel, y a partir del séptimo se
                         esconden tras «Ver más». --}}
                    @php
                        $__pills      = isset($categoryTree) && $categoryTree->count()
                                        ? $categoryTree
                                        : ($categories ?? collect());
                        $__pillLimit  = 6;
                    @endphp
                    @if(!$hasCategoryFilter && $__pills->count())
                    <div class="ec-category-pills" id="ec-category-pills">
                        <button class="ec-cat-pill ec-cat-pill--active" data-category-id="">Todos</button>
                        @foreach($__pills as $i => $cat)
                        <button class="ec-cat-pill {{ $i >= $__pillLimit ? 'ec-cat-pill--extra' : '' }}"
                                data-category-id="{{ $cat->id }}"
                                data-category-name="{{ $cat->name }}"
                                @if($i >= $__pillLimit) hidden @endif>
                            {{ $cat->name }}
                            @isset($cat->items_count)<span class="ec-cat-pill__n">{{ $cat->items_count }}</span>@endisset
                        </button>
                        @endforeach

                        @if($__pills->count() > $__pillLimit)
                        <button type="button" class="ec-cat-pill ec-cat-pill--toggle" id="ec-cat-pill-more"
                                aria-expanded="false">
                            Ver más categorías
                            <span class="ec-cat-pill__n">+{{ $__pills->count() - $__pillLimit }}</span>
                        </button>
                        @endif
                    </div>

                    {{-- Estilos aquí y no en styles_ecommerce.css: ese archivo
                         tiene una versión .min al lado y tocar los dos a mano
                         es la forma conocida de que se desincronicen. --}}
                    <style>
                        .ec-cat-pill__n {
                            display: inline-block; margin-left: 6px;
                            font-size: .74em; font-weight: 700; opacity: .55;
                            font-variant-numeric: tabular-nums;
                        }
                        .ec-cat-pill--toggle {
                            border-style: dashed; color: #6b7280;
                        }
                        .ec-cat-pill--toggle:hover { border-style: solid; }
                    </style>

                    @if($__pills->count() > $__pillLimit)
                    <script>
                    (function () {
                        var btn = document.getElementById('ec-cat-pill-more');
                        if (!btn || btn.dataset.ready) { return; }
                        btn.dataset.ready = '1';

                        btn.addEventListener('click', function () {
                            var open = btn.getAttribute('aria-expanded') === 'true';
                            document.querySelectorAll('.ec-cat-pill--extra').forEach(function (p) {
                                p.hidden = open;
                            });
                            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
                            btn.firstChild.nodeValue = open ? 'Ver más categorías ' : 'Ver menos ';
                        });
                    })();
                    </script>
                    @endif
                    @endif

                    {{-- Pills de categorías oficiales del marketplace (Hogar, Moda,
                         Mascotas, etc.). Solo aparecen las raíces con items en
                         este tenant. Filtran via ?mp_category={id} y matchean
                         también descendientes en el controller. --}}
                    @if(isset($marketplaceCategories) && $marketplaceCategories->count())
                        @php
                            $currentMpId = $currentMpCategory ? $currentMpCategory->id : null;
                            // Mantener filtros actuales al cambiar mp_category
                            $baseQs = array_filter([
                                'q'          => request('q'),
                                'sort'       => request('sort'),
                                'min_price'  => request('min_price'),
                                'max_price'  => request('max_price'),
                                'available'  => request('available'),
                                'category_id' => request('category_id'),
                            ], fn($v) => $v !== null && $v !== '');
                        @endphp
                        <div class="ec-mp-category-pills">
                            <div class="ec-mp-category-pills__label">🛒 Tipo de producto:</div>
                            <a href="{{ url('/ecommerce?' . http_build_query($baseQs)) }}"
                               class="ec-cat-pill ec-mp-pill {{ !$currentMpId ? 'ec-cat-pill--active' : '' }}">
                                Todos
                            </a>
                            @foreach($marketplaceCategories as $mpCat)
                                <a href="{{ url('/ecommerce?' . http_build_query(array_merge($baseQs, ['mp_category' => $mpCat->id]))) }}"
                                   class="ec-cat-pill ec-mp-pill {{ $currentMpId == $mpCat->id ? 'ec-cat-pill--active' : '' }}"
                                   title="Filtrar por categoría oficial: {{ $mpCat->name }}">
                                    @if($mpCat->icon){{ $mpCat->icon }} @endif{{ $mpCat->name }}
                                </a>
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
