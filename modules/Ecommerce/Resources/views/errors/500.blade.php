@extends('ecommerce::layouts.master')

@section('page_title', 'Error del servidor')
@section('meta_description', 'Ocurrió un error inesperado. Estamos trabajando para solucionarlo.')

@section('content')
<div class="ec-error-page">
    <div class="ec-error-page__inner">

        <div class="ec-error-page__illustration" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 280" fill="none">
                <!-- Engranajes -->
                <circle cx="160" cy="150" r="55" fill="hsl(var(--primary-h),var(--primary-s),94%)" stroke="hsl(var(--primary-h),var(--primary-s),78%)" stroke-width="3"/>
                <circle cx="160" cy="150" r="22" fill="#fff" stroke="hsl(var(--primary-h),var(--primary-s),78%)" stroke-width="3"/>
                <rect x="150" y="88" width="20" height="18" rx="4" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <rect x="150" y="194" width="20" height="18" rx="4" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <rect x="88"  y="140" width="18" height="20" rx="4" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <rect x="194" y="140" width="18" height="20" rx="4" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <rect x="104" y="103" width="15" height="15" rx="3" transform="rotate(45 104 103)" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <rect x="200" y="103" width="15" height="15" rx="3" transform="rotate(45 200 103)" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <rect x="104" y="195" width="15" height="15" rx="3" transform="rotate(45 104 195)" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <rect x="200" y="195" width="15" height="15" rx="3" transform="rotate(45 200 195)" fill="hsl(var(--primary-h),var(--primary-s),78%)"/>
                <!-- Engranaje pequeño -->
                <circle cx="255" cy="120" r="35" fill="hsl(var(--primary-h),var(--primary-s),90%)" stroke="hsl(var(--primary-h),var(--primary-s),75%)" stroke-width="2.5"/>
                <circle cx="255" cy="120" r="14" fill="#fff" stroke="hsl(var(--primary-h),var(--primary-s),75%)" stroke-width="2.5"/>
                <rect x="247" y="79"  width="16" height="14" rx="3" fill="hsl(var(--primary-h),var(--primary-s),75%)"/>
                <rect x="247" y="147" width="16" height="14" rx="3" fill="hsl(var(--primary-h),var(--primary-s),75%)"/>
                <rect x="214" y="112" width="14" height="16" rx="3" fill="hsl(var(--primary-h),var(--primary-s),75%)"/>
                <rect x="282" y="112" width="14" height="16" rx="3" fill="hsl(var(--primary-h),var(--primary-s),75%)"/>
                <!-- Rayo de error -->
                <path d="M280 165 L265 195 L278 195 L263 225 L295 188 L280 188 Z" fill="hsl(42,95%,55%)" stroke="hsl(42,80%,45%)" stroke-width="1.5" stroke-linejoin="round"/>
                <!-- Código 500 -->
                <text x="210" y="268" text-anchor="middle" font-family="Arial,sans-serif" font-size="13" fill="hsl(var(--primary-h),var(--primary-s),60%)" font-weight="700" letter-spacing="2">ERROR 500</text>
            </svg>
        </div>

        <div class="ec-error-page__code">500</div>
        <h1 class="ec-error-page__title">Algo salió mal</h1>
        <p class="ec-error-page__desc">
            Ocurrió un error inesperado en nuestro servidor.<br>
            Nuestro equipo ya fue notificado y estamos trabajando para solucionarlo.
        </p>

        <div class="ec-error-page__actions">
            <a href="{{ url('/ecommerce') }}" class="ec-error-page__btn ec-error-page__btn--primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Ir al inicio
            </a>
            <button onclick="history.back()" class="ec-error-page__btn ec-error-page__btn--secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Volver atrás
            </button>
        </div>

    </div>
</div>
@endsection
