@extends('ecommerce::layouts.master')

@section('page_title', 'Sin conexión | ' . ($company->name ?? 'Tienda Online'))

@section('content')
<div class="ec-offline-wrap">
    <div class="ec-offline-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="1.2">
            <line x1="1" y1="1" x2="23" y2="23"/>
            <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
            <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
            <path d="M10.71 5.05A16 16 0 0 1 22.56 9"/>
            <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
            <line x1="12" y1="20" x2="12.01" y2="20"/>
        </svg>
    </div>
    <h1 class="ec-offline-title">Sin conexión</h1>
    <p class="ec-offline-text">
        Parece que no tienes conexión a internet.<br>
        Verifica tu red y vuelve a intentarlo.
    </p>
    <div class="ec-offline-actions">
        <button type="button" class="ec-offline-retry" onclick="window.location.reload()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <polyline points="1 4 1 10 7 10"/>
                <path d="M3.51 15a9 9 0 1 0 .49-3.96"/>
            </svg>
            Reintentar
        </button>
        <a href="/ecommerce" class="ec-offline-home">Ir al inicio</a>
    </div>
</div>

<style>
.ec-offline-wrap {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    min-height: 55vh; text-align: center;
    padding: 40px 20px;
    gap: 16px;
}
.ec-offline-icon { color: #94a3b8; margin-bottom: 8px; }
.ec-offline-title { font-size: 2.6rem; font-weight: 800; color: var(--title-color); margin: 0; }
.ec-offline-text  { font-size: 1.4rem; color: var(--subtitle-color); margin: 0; line-height: 1.6; }
.ec-offline-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }
.ec-offline-retry {
    display: inline-flex; align-items: center; gap: 8px;
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff; border: none; border-radius: 10px;
    padding: 11px 24px; font-size: 1.4rem; font-weight: 700;
    cursor: pointer; transition: opacity .15s;
}
.ec-offline-retry:hover { opacity: .85; }
.ec-offline-home {
    display: inline-flex; align-items: center;
    border: 1.5px solid #e5e7eb; color: var(--subtitle-color);
    border-radius: 10px; padding: 11px 24px;
    font-size: 1.4rem; font-weight: 600; text-decoration: none;
    transition: border-color .15s;
}
.ec-offline-home:hover { border-color: hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
</style>
@endsection
