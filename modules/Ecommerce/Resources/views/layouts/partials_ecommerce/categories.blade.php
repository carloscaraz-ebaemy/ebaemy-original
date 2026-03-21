
@php
    use Illuminate\Support\Str;
    $path = explode('/', request()->path());
    $path[1] = (array_key_exists(1, $path)> 0)?$path[1]:'';
    $path[0] = ($path[0] === '')?'ecommerce':$path[0];
@endphp
            <div class="container">
                <div class="row">
                    <nav class="main-nav flex-grow-1">
                                <ul class="all-category my-0 pb-4">
                        <div class="container">
                            <ul id="scrollContainer" class="menu restaurante sf-arrows sf-js-enabled" style="touch-action: pan-y;">
                            @foreach ($categories as $category)
                <li class="menu-item ecommerce">
                    <a href="{{ route('tenant.ecommerce.index', Str::slug($category->name, '-')) }}" 
                    class="{{ $path[1] == $category->name ? 'bg-success text-light' : '' }}"><img src="{{ asset('storage/uploads/categories/'. $category->image) }}" alt="{{$category->name}}" draggable="false">
                    {{ $category->name }}
                    </a>
                </li>
            @endforeach
                </ul>
            </div>
        </nav>
    </div>
</div>
<!-- codigo para el scroll de las categorias -->
<script>
(function () {
    var container = document.getElementById('scrollContainer');
    if (!container) return;

    var isDragging = false;
    var startX, scrollLeft, startTime;
    var velocity = 0, lastX, momentumId;

    // ── Helpers ──────────────────────────────────────────────────
    function startDrag(x) {
        isDragging  = true;
        startX      = x - container.offsetLeft;
        scrollLeft  = container.scrollLeft;
        lastX       = x;
        velocity    = 0;
        startTime   = Date.now();
        cancelAnimationFrame(momentumId);
        container.classList.add('active');
        container.style.scrollBehavior = 'auto';
    }

    function moveDrag(x) {
        if (!isDragging) return;
        var walk = (x - container.offsetLeft - startX) * 1.5;
        velocity = x - lastX;
        lastX    = x;
        container.scrollLeft = scrollLeft - walk;
    }

    function endDrag() {
        if (!isDragging) return;
        isDragging = false;
        container.classList.remove('active');
        // Momentum scroll
        (function momentum() {
            if (Math.abs(velocity) < 0.5) return;
            velocity *= 0.92;
            container.scrollLeft -= velocity;
            momentumId = requestAnimationFrame(momentum);
        })();
    }

    // ── Mouse events (desktop) ───────────────────────────────────
    container.addEventListener('mousedown',  function(e) { startDrag(e.pageX); });
    container.addEventListener('mousemove',  function(e) { if (isDragging) { e.preventDefault(); moveDrag(e.pageX); } });
    container.addEventListener('mouseup',    endDrag);
    container.addEventListener('mouseleave', endDrag);

    // ── Touch events (mobile / tablet) ───────────────────────────
    container.addEventListener('touchstart', function(e) {
        startDrag(e.touches[0].pageX);
    }, { passive: true });

    container.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        moveDrag(e.touches[0].pageX);
        // Solo prevenir scroll vertical si hay movimiento horizontal significativo
        if (Math.abs(velocity) > 3) e.preventDefault();
    }, { passive: false });

    container.addEventListener('touchend',    endDrag, { passive: true });
    container.addEventListener('touchcancel', endDrag, { passive: true });

    // Evitar que los clicks disparen después de un drag
    container.addEventListener('click', function(e) {
        if (Math.abs(Date.now() - startTime) > 200 && Math.abs(container.scrollLeft - scrollLeft) > 5) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
}());
</script>