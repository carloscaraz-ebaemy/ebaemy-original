<div class="header-bottom sticky-header">
    <div class="container d-flex">
        <nav class="main-nav flex-grow-1">
            <ul class="menu sf-arrows" style="display:flex;flex-wrap:wrap;list-style:none;margin:0;padding:0;justify-content:center;gap:0;">
                @foreach ($items as $item)
                <li style="padding:0 12px;"><a href="{{ route("tenant.ecommerce.category", ['category' => $item->id]) }}" style="white-space:nowrap;">{{ $item->name }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>
</div>
