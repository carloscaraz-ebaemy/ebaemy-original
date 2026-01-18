@extends('restaurant::layouts.master')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-12 restaurant-view" style="{{ isset($full_width_banner) && $full_width_banner ? 'padding-top: 60px' : 'padding-top: 8rem' }}">
            @php
                $tagid = Request::segment(3);
            @endphp
            @if(!$tagid)
                @include('restaurant::layouts.partials.banner')
            @endif
            <div class="row py-4">
            @include('restaurant::layouts.partials.categories')
            </div>
            <div class="row py-4">
                <div class="container d-flex justify-content-between align-items-center mb-2">
                @php
                    $path = explode('/', request()->path());
                    $categorySlug = (array_key_exists(1, $path)) ? $path[1] : '';
                    $selectedCategoryName = 'Explora nuestro Menú';
                    
                    if ($categorySlug) {
                        // Buscar la categoría por slug
                        $selectedCategory = $categories->first(function ($cat) use ($categorySlug) {
                            return \Illuminate\Support\Str::slug($cat->name, '-') == $categorySlug;
                        });
                        
                        if ($selectedCategory) {
                            $selectedCategoryName = $selectedCategory->name;
                        }
                    } else {
                        $selectedCategoryName = 'Todos los platos';
                    }
                @endphp
                <h2 class="title-category m-0">{{ $selectedCategoryName }}</h2>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn-product-grid-active" id="btn-product-grid-active" title="Ver en formato lista">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-layout-list"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 3a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-2a3 3 0 0 1 3 -3z" /><path d="M18 13a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-2a3 3 0 0 1 3 -3z" /></svg>
                    </button>
                    <button type="button" class="btn-product-grid-active" id="btn-product-grid-outline" title="Ver en formato cuadrícula">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-layout-grid"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 3a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-4a2 2 0 0 1 -2 -2v-4a2 2 0 0 1 2 -2z" /><path d="M19 3a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-4a2 2 0 0 1 -2 -2v-4a2 2 0 0 1 2 -2z" /><path d="M9 13a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-4a2 2 0 0 1 -2 -2v-4a2 2 0 0 1 2 -2z" /><path d="M19 13a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-4a2 2 0 0 1 -2 -2v-4a2 2 0 0 1 2 -2z" /></svg>
                    </button>
                </div>
                </div>
            </div>
            <div class="row row-sm mt-0 products-restaurant list-view" id="productsContainer">
                @include('restaurant::layouts.partials.list_products')
            </div>
            <div class="row page-pagination mt-2">
              <div class="col-md-12 col-lg-12 d-flex justify-content-end mb-4">
                {{ $dataPaginate->onEachSide(1)->links('restaurant::layouts.partials.pagination') }}
              </div>
            </div>
            <div class="row">
                @include('restaurant::layouts.partials.offers')
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
    (function(){
        const btnList = document.getElementById('btn-product-grid-active'); // lista
        const btnGrid = document.getElementById('btn-product-grid-outline'); // cuadricula
        const container = document.getElementById('productsContainer');

        // Restaurar modo desde localStorage (opcional)
        const savedMode = localStorage.getItem('restaurant_products_mode');
        if(savedMode === 'grid') {
            container.classList.remove('list-view');
            container.classList.add('grid-view');
            btnGrid.classList.add('is-selected');
        } else {
            container.classList.add('list-view');
            btnList.classList.add('is-selected');
        }

        function setMode(mode){
            if(mode === 'grid') {
                container.classList.remove('list-view');
                container.classList.add('grid-view');
                btnGrid.classList.add('is-selected');
                btnList.classList.remove('is-selected');
            } else {
                container.classList.remove('grid-view');
                container.classList.add('list-view');
                btnList.classList.add('is-selected');
                btnGrid.classList.remove('is-selected');
            }
            localStorage.setItem('restaurant_products_mode', mode);
        }

        btnList.addEventListener('click', function(){ setMode('list'); });
        btnGrid.addEventListener('click', function(){ setMode('grid'); });
    })();
</script>
@endpush
@endsection
