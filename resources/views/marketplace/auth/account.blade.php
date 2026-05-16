@extends('marketplace.layout')

@section('title', 'Mi cuenta')

@section('content')
<div class="mp-acc-wrap">
    <header class="mp-acc-head">
        <div>
            <h1 class="mp-acc-title">Hola, {{ $user->name }}</h1>
            <p class="mp-acc-email">{{ $user->email }}</p>
        </div>
        <form method="POST" action="{{ route('marketplace.auth.logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="mp-acc-logout">Cerrar sesion</button>
        </form>
    </header>

    @if(session('mkt_login_ok'))
        <div class="mp-acc-note">{{ session('mkt_login_ok') }}</div>
    @endif

    {{-- Counters rapidos --}}
    <section class="mp-acc-stats">
        <div class="mp-acc-stat">
            <span class="mp-acc-stat__num">{{ $favCount }}</span>
            <span class="mp-acc-stat__lbl">Favoritos</span>
        </div>
        <div class="mp-acc-stat">
            <span class="mp-acc-stat__num">{{ $ordersCount }}</span>
            <span class="mp-acc-stat__lbl">Pedidos</span>
        </div>
        <div class="mp-acc-stat">
            <span class="mp-acc-stat__num">{{ count($interests) }}</span>
            <span class="mp-acc-stat__lbl">Categorias seguidas</span>
        </div>
    </section>

    {{-- Sigue donde lo dejaste --}}
    @if($recentViews->count() > 0)
        <section class="mp-acc-section">
            <h2 class="mp-acc-h2">Sigue donde lo dejaste</h2>
            <div class="mp-acc-recent">
                @foreach($recentViews as $rv)
                    @php
                        $effPrice = $rv->mp_price ?: $rv->price;
                    @endphp
                    <a href="{{ route('marketplace.item', $rv->slug) }}" class="mp-acc-recent__card">
                        @if($rv->image_url)
                            <img src="{{ $rv->image_url }}" alt="{{ $rv->title }}" loading="lazy">
                        @else
                            <div class="mp-acc-recent__noimg">—</div>
                        @endif
                        <div class="mp-acc-recent__body">
                            <p class="mp-acc-recent__title">{{ \Illuminate\Support\Str::limit($rv->title, 48) }}</p>
                            @if($effPrice > 0)
                                <p class="mp-acc-recent__price">S/ {{ number_format($effPrice, 2) }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Categorias seguidas (solo si el job ya corrio al menos una vez) --}}
    @if($interests->count() > 0)
        <section class="mp-acc-section">
            <h2 class="mp-acc-h2">Tus categorias</h2>
            <div class="mp-acc-cats">
                @foreach($interests as $i)
                    <a href="{{ $i->full_slug ? route('marketplace.category_official', ['fullSlug' => $i->full_slug]) : route('marketplace.index') }}"
                       class="mp-acc-cat-chip">
                        {{ $i->name ?: 'Categoria' }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Accesos --}}
    <section class="mp-acc-grid">
        <a href="{{ route('marketplace.favorites') }}" class="mp-acc-tile">
            <h3>Favoritos</h3>
            <p>{{ $favCount }} producto{{ $favCount === 1 ? '' : 's' }} guardado{{ $favCount === 1 ? '' : 's' }}.</p>
        </a>
        <a href="{{ route('marketplace.cart') }}" class="mp-acc-tile">
            <h3>Mi carrito</h3>
            <p>Lo que estas por comprar ahora mismo.</p>
        </a>
        <a href="{{ route('marketplace.account.orders') }}" class="mp-acc-tile">
            <h3>Mis pedidos</h3>
            <p>{{ $ordersCount === 0 ? 'Aun no tienes compras confirmadas.' : $ordersCount . ' pedido' . ($ordersCount === 1 ? '' : 's') . ' realizados.' }}</p>
        </a>
        <a href="{{ route('marketplace.account.coupons') }}" class="mp-acc-tile">
            <h3>Mis cupones</h3>
            <p>Codigos de descuento asignados a tu cuenta.</p>
        </a>
        <a href="{{ route('marketplace.account.settings') }}" class="mp-acc-tile">
            <h3>Ajustes</h3>
            <p>Perfil, contraseña y preferencias de notificaciones.</p>
        </a>
    </section>
</div>

<style>
.mp-acc-wrap { max-width: 880px; margin: 32px auto 64px; padding: 0 16px; }
.mp-acc-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.mp-acc-title { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }
.mp-acc-email { margin: 4px 0 0; font-size: 14px; color: #64748b; }
.mp-acc-logout { background: #fff; color: #475569; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 14px; font-size: 13.5px; font-weight: 600; cursor: pointer; }
.mp-acc-logout:hover { border-color: #cbd5e1; background: #f9fafb; }
.mp-acc-note { padding: 12px 16px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; border-radius: 10px; font-size: 14px; margin-bottom: 18px; }

.mp-acc-stats { display: grid; gap: 10px; grid-template-columns: repeat(3, 1fr); margin-bottom: 24px; }
.mp-acc-stat { padding: 14px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; text-align: center; }
.mp-acc-stat__num { display: block; font-size: 22px; font-weight: 700; color: #0f172a; }
.mp-acc-stat__lbl { display: block; margin-top: 2px; font-size: 12px; color: #64748b; }

.mp-acc-section { margin-bottom: 28px; }
.mp-acc-h2 { margin: 0 0 12px; font-size: 16px; font-weight: 700; color: #0f172a; }

.mp-acc-recent { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
.mp-acc-recent__card { display: block; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; text-decoration: none; color: inherit; transition: border-color .12s, transform .12s; }
.mp-acc-recent__card:hover { border-color: #0f8a82; transform: translateY(-2px); }
.mp-acc-recent__card img { display: block; width: 100%; height: 120px; object-fit: cover; background: #f3f4f6; }
.mp-acc-recent__noimg { width: 100%; height: 120px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
.mp-acc-recent__body { padding: 10px 12px; }
.mp-acc-recent__title { margin: 0; font-size: 13px; font-weight: 500; color: #0f172a; line-height: 1.35; }
.mp-acc-recent__price { margin: 4px 0 0; font-size: 14px; font-weight: 700; color: #0c6b65; }

.mp-acc-cats { display: flex; flex-wrap: wrap; gap: 8px; }
.mp-acc-cat-chip { padding: 7px 14px; background: #f0fdfa; border: 1px solid #99f6e4; color: #0c6b65; border-radius: 999px; font-size: 13px; font-weight: 600; text-decoration: none; }
.mp-acc-cat-chip:hover { background: #ccfbf1; }

.mp-acc-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.mp-acc-tile { display: block; padding: 18px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; text-decoration: none; color: inherit; transition: border-color .12s, transform .12s; }
.mp-acc-tile:hover { border-color: #0f8a82; transform: translateY(-2px); }
.mp-acc-tile h3 { margin: 0 0 6px; font-size: 16px; font-weight: 700; color: #0f172a; }
.mp-acc-tile p { margin: 0; font-size: 13.5px; color: #64748b; line-height: 1.45; }
.mp-acc-tile--disabled { opacity: .55; pointer-events: none; }
@media (max-width: 640px) {
    .mp-acc-title { font-size: 20px; }
    .mp-acc-stats { grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .mp-acc-stat__num { font-size: 18px; }
    .mp-acc-stat { padding: 10px 6px; }
}
</style>
@endsection
