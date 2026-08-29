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

                    {{-- Category pills (internas del tenant) --}}
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
