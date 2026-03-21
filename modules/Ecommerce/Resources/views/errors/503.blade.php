@extends('ecommerce::layouts.master')

@section('page_title', 'Sitio en mantenimiento')
@section('meta_description', 'Estamos realizando mejoras. Volvemos pronto.')

@section('content')
<div class="ec-error-page">
    <div class="ec-error-page__inner">

        <div class="ec-error-page__illustration" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 280" fill="none">
                <!-- Cono de obras -->
                <polygon points="210,70 170,210 250,210" fill="hsl(42,95%,55%)" stroke="hsl(42,80%,42%)" stroke-width="2.5" stroke-linejoin="round"/>
                <rect x="172" y="130" width="76" height="12" rx="3" fill="#fff" opacity=".7"/>
                <rect x="165" y="155" width="80" height="12" rx="3" fill="#fff" opacity=".7"/>
                <rect x="155" y="210" width="110" height="16" rx="4" fill="hsl(var(--primary-h),var(--primary-s),78%)" stroke="hsl(var(--primary-h),var(--primary-s),65%)" stroke-width="1.5"/>
                <!-- Llave inglesa -->
                <path d="M285 155 Q300 130 320 140 L305 155 L315 165 Q330 175 310 195 Q295 210 285 195 L270 180 Z" fill="hsl(var(--primary-h),var(--primary-s),80%)" stroke="hsl(var(--primary-h),var(--primary-s),65%)" stroke-width="2"/>
                <line x1="275" y1="185" x2="248" y2="212" stroke="hsl(var(--primary-h),var(--primary-s),65%)" stroke-width="6" stroke-linecap="round"/>
                <!-- Reloj -->
                <circle cx="115" cy="160" r="38" fill="hsl(var(--primary-h),var(--primary-s),94%)" stroke="hsl(var(--primary-h),var(--primary-s),75%)" stroke-width="2.5"/>
                <line x1="115" y1="160" x2="115" y2="135" stroke="hsl(var(--primary-h),var(--primary-s),55%)" stroke-width="3" stroke-linecap="round"/>
                <line x1="115" y1="160" x2="133" y2="168" stroke="hsl(var(--primary-h),var(--primary-s),55%)" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="115" cy="160" r="4" fill="hsl(var(--primary-h),var(--primary-s),55%)"/>
                <!-- Texto -->
                <text x="210" y="268" text-anchor="middle" font-family="Arial,sans-serif" font-size="13" fill="hsl(var(--primary-h),var(--primary-s),60%)" font-weight="700" letter-spacing="2">MANTENIMIENTO</text>
            </svg>
        </div>

        <div class="ec-error-page__code" style="color:hsl(42,80%,45%);">503</div>
        <h1 class="ec-error-page__title">Estamos en mantenimiento</h1>
        <p class="ec-error-page__desc">
            Estamos realizando mejoras para darte una mejor experiencia.<br>
            Volvemos muy pronto. ¡Gracias por tu paciencia!
        </p>

        @php $config = \App\Models\Tenant\ConfigurationEcommerce::first(); @endphp
        @if(!empty($config->whatsapp))
        <div style="margin-top:16px;">
            <a href="https://wa.me/{{ preg_replace('/\D/','',$config->whatsapp) }}"
               target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:8px;background:#25d366;color:#fff;font-weight:700;padding:12px 24px;border-radius:8px;text-decoration:none;font-size:1.4rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                Contáctanos por WhatsApp
            </a>
        </div>
        @endif

        <div class="ec-error-page__actions" style="margin-top:24px;">
            <button onclick="location.reload()" class="ec-error-page__btn ec-error-page__btn--primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Reintentar
            </button>
        </div>

    </div>
</div>
@endsection
