@php
    use Illuminate\Support\Str;
    $path = explode('/', request()->path());
    $path[1] = (array_key_exists(1, $path)) ? $path[1] : '';
    $path[0] = ($path[0] === '') ? 'menu' : $path[0];
    
    // Debug: ver qué contiene $path[1]
    // {{-- dd($path[1]) --}}
@endphp
<div class="container">
    <div class="row">
        <nav class="main-nav flex-grow-1">
            <ul class="all-category my-0 pb-4">
                <h2 class="title-category">Nuestras Especialidades</h2>
                {{-- <li>
                    <a href="{{ route('tenant.restaurant.menu') }}">Ver todos</a>
                </li> --}}
            </ul>
            <div class="container">
                <ul id="scrollContainer" class="menu restaurante sf-arrows sf-js-enabled" style="touch-action: pan-y;">            
                    <li class="menu-item p-3 {{ $path[1] == '' ? 'selected-category' : '' }}">
                        <a href="{{ route('tenant.restaurant.menu') }}">
                            <img class="category-logo" src="{{ asset('logo/all-category.png') }}" alt="Todos los platos">
                        </a>
                        <span class="mt-auto category-name">
                            Todos los platos
                        </span>
                    </li>
                    @foreach ($categories as $category)
                        @php
                            $categorySlug = Str::slug($category->name, '-');
                            $isSelected = $path[1] == $categorySlug;
                        @endphp                        
                        <li class="menu-item p-3 {{ $isSelected ? 'selected-category' : '' }}"> 
                            <a href="{{ route('tenant.restaurant.menu', ['name' => $categorySlug]) }}">
                                @if($category->image && file_exists(public_path('storage/uploads/categories/'. $category->image)))
                                    <img class="category-logo" src="{{ asset('storage/uploads/categories/'. $category->image) }}" alt="{{$category->name}}" draggable="false">
                                @else
                                    <img class="category-logo" src="{{ asset('logo/Image_not_available.png') }}" alt="{{$category->name}}" draggable="false">
                                @endif                                
                            </a>
                            <span class="mt-auto category-name">
                                {{$category->name}}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>
    </div>
</div>
<!-- Resto del código JavaScript igual -->
<script>
    const container = document.getElementById('scrollContainer');

    let isDragging = false;
    let startX;
    let scrollLeft;

    container.addEventListener('mousedown', (e) => {
        isDragging = true;
        container.classList.add('active');
        startX = e.pageX - container.offsetLeft;
        scrollLeft = container.scrollLeft;
    });

    container.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const x = e.pageX - container.offsetLeft;
        const walk = (x - startX) * 2;
        container.scrollLeft = scrollLeft - walk;
    });

    ['mouseup', 'mouseleave'].forEach(event => {
        container.addEventListener(event, () => {
            isDragging = false;
        });
    });

    // Hacer que todo el li sea clickeable
    document.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!isDragging) {
                const link = this.querySelector('a');
                if (link && e.target.tagName !== 'A') {
                    link.click();
                }
            }
        });
        
        // Agregar cursor pointer al li
        item.style.cursor = 'pointer';
    });
</script>