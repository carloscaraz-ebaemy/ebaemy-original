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

    <section class="mp-acc-grid">
        <a href="{{ route('marketplace.favorites') }}" class="mp-acc-tile">
            <h3>Favoritos</h3>
            <p>Productos que guardaste para comprar despues.</p>
        </a>
        <a href="{{ route('marketplace.cart') }}" class="mp-acc-tile">
            <h3>Mi carrito</h3>
            <p>Lo que estas por comprar ahora mismo.</p>
        </a>
        <div class="mp-acc-tile mp-acc-tile--disabled">
            <h3>Mis pedidos</h3>
            <p>Disponible cuando confirmes tu primera compra.</p>
        </div>
        <div class="mp-acc-tile mp-acc-tile--disabled">
            <h3>Preferencias</h3>
            <p>Frecuencia de avisos y categorias de interes (pronto).</p>
        </div>
    </section>
</div>

<style>
.mp-acc-wrap { max-width: 880px; margin: 32px auto 64px; padding: 0 16px; }
.mp-acc-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; margin-bottom: 24px; flex-wrap: wrap;
}
.mp-acc-title { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }
.mp-acc-email { margin: 4px 0 0; font-size: 14px; color: #64748b; }
.mp-acc-logout {
    background: #fff; color: #475569;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 14px; font-size: 13.5px; font-weight: 600;
    cursor: pointer;
}
.mp-acc-logout:hover { border-color: #cbd5e1; background: #f9fafb; }
.mp-acc-note {
    padding: 12px 16px;
    background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857;
    border-radius: 10px; font-size: 14px; margin-bottom: 18px;
}
.mp-acc-grid {
    display: grid; gap: 14px;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}
.mp-acc-tile {
    display: block; padding: 18px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    text-decoration: none; color: inherit;
    transition: border-color .12s, transform .12s;
}
.mp-acc-tile:hover { border-color: #0f8a82; transform: translateY(-2px); }
.mp-acc-tile h3 { margin: 0 0 6px; font-size: 16px; font-weight: 700; color: #0f172a; }
.mp-acc-tile p { margin: 0; font-size: 13.5px; color: #64748b; line-height: 1.45; }
.mp-acc-tile--disabled { opacity: .55; pointer-events: none; }
@media (max-width: 640px) {
    .mp-acc-title { font-size: 20px; }
}
</style>
@endsection
