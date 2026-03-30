{{-- THEME FERRETERÍA — Header industrial, robusto --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(8)->get();
@endphp
<style>
.hw-header{background:#1c1917;position:sticky;top:0;z-index:100}
.hw-header__top{display:flex;align-items:center;justify-content:space-between;padding:10px 24px;max-width:1400px;margin:0 auto}
.hw-header__logo img{height:38px;filter:brightness(0) invert(1)}
.hw-header__search{flex:1;max-width:500px;margin:0 2rem;position:relative}
.hw-header__search input{width:100%;padding:9px 16px 9px 38px;border:2px solid hsl(var(--primary-h),var(--primary-s),var(--primary-l));border-radius:4px;background:#292524;color:#e7e5e4;font-size:13px;outline:none}
.hw-header__search input::placeholder{color:#78716c}
.hw-header__search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:hsl(var(--primary-h),var(--primary-s),var(--primary-l))}
.hw-header__actions{display:flex;align-items:center;gap:1rem}
.hw-header__action{color:#d6d3d1;text-decoration:none;display:flex;align-items:center;gap:.3rem;font-size:12px;font-weight:600;position:relative;background:none;border:none;cursor:pointer}
.hw-header__action:hover{color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));text-decoration:none}
.hw-header__badge{position:absolute;top:-6px;right:-8px;background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));color:#fff;font-size:9px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center}
.hw-header__nav{background:#292524}
.hw-header__nav-inner{display:flex;gap:.25rem;padding:6px 24px;max-width:1400px;margin:0 auto;overflow-x:auto}
.hw-header__nav-link{font-size:12px;font-weight:600;color:#a8a29e;padding:5px 12px;border-radius:3px;text-decoration:none;white-space:nowrap;transition:all .15s;text-transform:uppercase;letter-spacing:.03em}
.hw-header__nav-link:hover{background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));color:#fff;text-decoration:none}
@media(max-width:767px){.hw-header__search{display:none}.hw-header__action span{display:none}}
</style>
<header class="hw-header">
    <div class="hw-header__top">
        <a href="{{ route('tenant.ecommerce.index') }}" class="hw-header__logo"><img src="{{ $logo }}" alt="{{ $company->name ?? '' }}"></a>
        <div class="hw-header__search">
            <svg class="hw-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="ec-search-input" placeholder="Buscar herramientas, materiales...">
        </div>
        <div class="hw-header__actions">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="hw-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="ec-wishlist-counter hw-header__badge" style="display:none"></span></a>
            <a href="{{ route('tenant_detail_cart') }}" class="hw-header__action" id="ec-cart-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg><span class="badge-custom cart-count hw-header__badge">0</span></a>
            @guest('ecommerce')<a href="{{ route('tenant_ecommerce_login') }}" class="hw-header__action"><span>Ingresar</span></a>@else<a href="{{ route('tenant.ecommerce.profile') }}" class="hw-header__action"><span>{{ \Illuminate\Support\Str::limit(auth('ecommerce')->user()->name, 10) }}</span></a>@endguest
        </div>
    </div>
    @if($categories->count())
    <nav class="hw-header__nav"><div class="hw-header__nav-inner">
        <a href="{{ route('tenant.ecommerce.index') }}" class="hw-header__nav-link">Inicio</a>
        @foreach($categories as $cat)<a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="hw-header__nav-link">{{ $cat->name }}</a>@endforeach
    </div></nav>
    @endif
</header>
