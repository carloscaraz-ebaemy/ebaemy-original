{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index. --}}
            {{-- ── PRODUCTOS ────────────────────────────────────────── --}}
            <section class="ec-home-section" aria-label="{{ $hasCategoryFilter ? 'Productos de ' . $categoryName : 'Catálogo de productos' }}">
                {{-- Sin filtro el H1 de la portada ya dice qué es esto: un
                     segundo título debajo («Explora nuestros productos») era
                     una fila entera repitiendo lo mismo. Solo se pinta cuando
                     aporta algo: el nombre de la categoría o del tag. --}}
                @if($hasCategoryFilter || $tagid)
                <div class="ec-section-header">
                    <h2 class="ec-section-title">
                        {{ $hasCategoryFilter ? $categoryName : 'Productos de la categoría' }}
                    </h2>
                    <a href="{{ $homeUrl }}" class="ec-section-link">← Ver todos</a>
                </div>
                @endif

                {{-- ── Zona de filtros ─────────────────────────────
                     Aquí vivían DOS filas de píldoras más sus etiquetas:
                     las categorías del tenant y los tipos oficiales. Las
                     primeras repetían exactamente el menú del header (mismo
                     árbol, mismos padres de primer nivel) y las segundas
                     eran un segundo sistema de filtros con su propia caja.
                     Entre las dos se comían ~110px antes del primer
                     producto para no decir nada nuevo.

                     Ahora la navegación por categoría vive en un solo sitio
                     —el menú del header— y el tipo de producto es un
                     desplegable más de la barra de filtros. --}}
                <div class="ec-filter-sticky-zone">
                    @include('ecommerce::layouts.partials_ecommerce.filters')
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
