@extends('ecommerce::layouts.master')

@section('page_title', 'Página no encontrada')
@section('meta_description', 'La página que buscas no existe.')

@section('content')
@php $company = \App\Models\Tenant\Company::first(); @endphp

<div class="ec-error-page">
    <div class="ec-error-page__inner">

        {{-- Ilustración SVG --}}
        <div class="ec-error-page__illustration" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 280" fill="none">
                <!-- Caja abierta -->
                <rect x="110" y="140" width="200" height="120" rx="8" fill="hsl(var(--primary-h),var(--primary-s),95%)" stroke="hsl(var(--primary-h),var(--primary-s),80%)" stroke-width="2"/>
                <path d="M110 155 L210 175 L310 155" stroke="hsl(var(--primary-h),var(--primary-s),75%)" stroke-width="2" fill="none"/>
                <path d="M110 140 L160 110 L210 140" fill="hsl(var(--primary-h),var(--primary-s),88%)" stroke="hsl(var(--primary-h),var(--primary-s),75%)" stroke-width="2"/>
                <path d="M310 140 L260 110 L210 140" fill="hsl(var(--primary-h),var(--primary-s),88%)" stroke="hsl(var(--primary-h),var(--primary-s),75%)" stroke-width="2"/>
                <!-- Signo ? -->
                <text x="190" y="215" font-size="52" font-weight="800"
                      fill="hsl(var(--primary-h),var(--primary-s),var(--primary-l))"
                      font-family="sans-serif">?</text>
                <!-- Lupa -->
                <circle cx="310" cy="80" r="32" stroke="hsl(var(--primary-h),var(--primary-s),70%)" stroke-width="3" fill="hsl(var(--primary-h),var(--primary-s),96%)"/>
                <line x1="333" y1="103" x2="355" y2="125" stroke="hsl(var(--primary-h),var(--primary-s),65%)" stroke-width="3" stroke-linecap="round"/>
                <!-- Estrellas -->
                <circle cx="80" cy="90" r="5" fill="hsl(var(--primary-h),var(--primary-s),80%)"/>
                <circle cx="355" cy="155" r="4" fill="hsl(var(--primary-h),var(--primary-s),85%)"/>
                <circle cx="100" cy="210" r="3" fill="hsl(var(--primary-h),var(--primary-s),88%)"/>
            </svg>
        </div>

        <p class="ec-error-page__code">404</p>
        <h1 class="ec-error-page__title">Página no encontrada</h1>
        <p class="ec-error-page__desc">
            Lo sentimos, el producto o página que buscas no existe o fue movido.
        </p>

        <div class="ec-error-page__actions">
            <a href="{{ route('tenant.ecommerce.index') }}" class="ec-error-page__btn ec-error-page__btn--primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Ir al inicio
            </a>
            <button onclick="history.back()" class="ec-error-page__btn ec-error-page__btn--secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Volver atrás
            </button>
        </div>

        {{-- Búsqueda rápida --}}
        <div class="ec-error-page__search">
            <p>¿Buscas un producto específico?</p>
            <form method="GET" action="{{ route('tenant.ecommerce.index') }}" class="ec-error-search-form">
                <input type="text" name="q" placeholder="Buscar productos..."
                       class="ec-error-search-input"
                       aria-label="Buscar en la tienda">
                <button type="submit" class="ec-error-search-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                </button>
            </form>
        </div>

    </div>
</div>

<style>
.ec-error-page {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8rem 20px 4rem;
}
.ec-error-page__inner {
    max-width: 500px;
    width: 100%;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}
.ec-error-page__illustration svg {
    width: 100%;
    max-width: 320px;
    height: auto;
    margin-bottom: 8px;
}
.ec-error-page__code {
    font-size: 6rem;
    font-weight: 900;
    color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    line-height: 1;
    margin: 0;
    letter-spacing: -2px;
}
.ec-error-page__title {
    font-size: 2.4rem;
    font-weight: 700;
    color: var(--title-color,#333);
    margin: 0;
}
.ec-error-page__desc {
    font-size: 1.5rem;
    color: #777;
    margin: 0;
    max-width: 380px;
}
.ec-error-page__actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 8px;
}
.ec-error-page__btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 22px;
    border-radius: 8px;
    font-size: 1.4rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: opacity .18s, transform .18s;
}
.ec-error-page__btn:hover { opacity: .85; transform: translateY(-2px); }
.ec-error-page__btn--primary {
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
}
.ec-error-page__btn--secondary {
    background: #f1f3f5;
    color: #555;
}
.ec-error-page__search {
    margin-top: 16px;
    width: 100%;
}
.ec-error-page__search p {
    font-size: 1.3rem;
    color: #999;
    margin-bottom: 10px;
}
.ec-error-search-form {
    display: flex;
    gap: 0;
    max-width: 340px;
    margin: 0 auto;
    border: 1.5px solid #ddd;
    border-radius: 10px;
    overflow: hidden;
    transition: border-color .18s;
}
.ec-error-search-form:focus-within {
    border-color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
}
.ec-error-search-input {
    flex: 1;
    border: none;
    padding: 10px 14px;
    font-size: 1.4rem;
    outline: none;
    background: #fff;
}
.ec-error-search-btn {
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
    border: none;
    padding: 10px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity .15s;
}
.ec-error-search-btn:hover { opacity: .85; }
</style>
@endsection
