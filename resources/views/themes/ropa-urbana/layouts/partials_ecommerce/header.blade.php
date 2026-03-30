{{-- THEME MODA URBANA — Header estilo Shein/H&M: colorido, bold, barra de envío gratis --}}
@php
    $company = \App\Models\Tenant\Company::first();
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    $logo = ($company && $company->logo) ? asset('storage/uploads/logos/'.$company->logo) : asset('porto-ecommerce/assets/images/logo-black.png');
    $categories = \Modules\Item\Models\Category::whereHas('items', fn($q) => $q->where('apply_store', 1))->orderBy('name')->take(8)->get();
@endphp
<style>
.urban-header{position:sticky;top:0;z-index:100}
.urban-header__promo{background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));text-align:center;padding:6px;font-size:12px;font-weight:800;color:#fff;letter-spacing:.04em}
.urban-header__main{background:#fff;display:flex;align-items:center;justify-content:space-between;padding:8px 20px;border-bottom:1px solid #f3f4f6}
.urban-header__logo img{height:36px}
.urban-header__search{flex:1;max-width:500px;margin:0 1.5rem;position:relative}
.urban-header__search input{width:100%;padding:10px 16px 10px 40px;border:2px solid #f3f4f6;border-radius:24px;font-size:13px;outline:none;background:#f9fafb;font-weight:500}
.urban-header__search input:focus{border-color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));background:#fff}
.urban-header__search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af}
.urban-header__actions{display:flex;align-items:center;gap:.75rem}
.urban-header__action{color:#374151;text-decoration:none;display:flex;align-items:center;gap:.25rem;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;position:relative;background:none;border:none;cursor:pointer}
.urban-header__action:hover{color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));text-decoration:none}
.urban-header__badge{position:absolute;top:-6px;right:-8px;background:#ef4444;color:#fff;font-size:9px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center}
.urban-header__cats{background:#fff;border-bottom:1px solid #f3f4f6;overflow-x:auto}
.urban-header__cats-inner{display:flex;gap:0;padding:0 20px;max-width:1400px;margin:0 auto}
.urban-header__cat{font-size:12px;font-weight:700;color:#374151;padding:10px 16px;text-decoration:none;white-space:nowrap;border-bottom:2px solid transparent;transition:all .15s;text-transform:uppercase;letter-spacing:.03em}
.urban-header__cat:hover,.urban-header__cat--active{color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));border-bottom-color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));text-decoration:none}
@media(max-width:767px){.urban-header__search{display:none}.urban-header__action span{display:none}}
</style>
<header class="urban-header">
    <div class="urban-header__promo">ENVÍO GRATIS en pedidos mayores a S/ 99 | Usa código: URBAN20</div>
    <div class="urban-header__main">
        <a href="{{ route('tenant.ecommerce.index') }}" class="urban-header__logo"><img src="{{ $logo }}" alt="{{ $company->name ?? '' }}"></a>
        <div class="urban-header__search">
            <svg class="urban-header__search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="ec-search-input" placeholder="¿Qué estás buscando hoy?">
        </div>
        <div class="urban-header__actions">
            <a href="{{ route('tenant.ecommerce.wishlist') }}" class="urban-header__action"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="ec-wishlist-counter urban-header__badge" style="display:none"></span></a>
            <a href="{{ route('tenant_detail_cart') }}" class="urban-header__action" id="ec-cart-link"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg><span class="badge-custom cart-count urban-header__badge">0</span></a>
            @guest('ecommerce')<a href="{{ route('tenant_ecommerce_login') }}" class="urban-header__action"><span>Ingresar</span></a>@else<a href="{{ route('tenant.ecommerce.profile') }}" class="urban-header__action"><span>{{ \Illuminate\Support\Str::limit(auth('ecommerce')->user()->name, 10) }}</span></a>@endguest
        </div>
    </div>
    @if($categories->count())
    <nav class="urban-header__cats"><div class="urban-header__cats-inner">
        <a href="{{ route('tenant.ecommerce.index') }}" class="urban-header__cat urban-header__cat--active">Todos</a>
        @foreach($categories as $cat)<a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}" class="urban-header__cat">{{ $cat->name }}</a>@endforeach
    </div></nav>
    @endif
</header>
