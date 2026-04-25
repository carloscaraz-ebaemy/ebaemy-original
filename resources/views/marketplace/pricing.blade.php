@extends('marketplace.layout')

@section('title', 'Planes y precios — ebaemy')
@section('description', 'Compara nuestros planes. Empieza gratis y publica hasta 25 productos en el marketplace, o escala con planes Pro y Enterprise.')
@section('keywords', 'planes, precios, ebaemy, marketplace, vender, tienda virtual, suscripción')
@section('og_title', 'Planes y precios de ebaemy')
@section('og_description', 'Crea tu tienda gratis. Publica hasta 25 productos en el marketplace sin pagar nada.')
@section('canonical', url('/precios'))

@push('styles')
<style>
.pp-hero { text-align: center; padding: 56px 20px 24px; }
.pp-hero h1 { font-size: clamp(28px, 5vw, 44px); margin: 0 0 12px; color: var(--mp-ink, #111827); letter-spacing: -0.02em; }
.pp-hero p { color: #4b5563; font-size: 17px; max-width: 580px; margin: 0 auto; }

.pp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    padding: 24px 0 60px;
    align-items: stretch;
}
.pp-card {
    background: #fff;
    border: 1.5px solid var(--mp-border, #e5e7eb);
    border-radius: 18px;
    padding: 28px 22px;
    display: flex; flex-direction: column;
    position: relative;
    transition: border-color .2s, transform .2s;
}
.pp-card:hover { border-color: var(--mp-primary, #0f8a82); transform: translateY(-3px); }
.pp-card--featured {
    border-color: var(--mp-primary, #0f8a82);
    box-shadow: 0 12px 32px rgba(15,138,130,.15);
    transform: scale(1.02);
}
.pp-card--featured::before {
    content: 'Recomendado';
    position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
    background: var(--mp-primary, #0f8a82); color: #fff;
    padding: 4px 14px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
}
.pp-card h3 { margin: 0 0 6px; font-size: 20px; color: var(--mp-ink, #111827); }
.pp-price-row { display: flex; align-items: baseline; gap: 6px; margin: 12px 0 4px; }
.pp-price { font-size: 36px; font-weight: 800; color: var(--mp-ink, #111827); }
.pp-currency { color: #6b7280; font-size: 14px; }
.pp-cycle { color: #6b7280; font-size: 13px; margin-top: 2px; }
.pp-tagline {
    color: #6b7280; font-size: 13.5px; min-height: 40px; padding-bottom: 14px;
    border-bottom: 1px dashed #e5e7eb;
}

.pp-features { list-style: none; padding: 14px 0 0; margin: 0; flex: 1; }
.pp-features li {
    padding: 6px 0; font-size: 13.5px; color: #374151;
    display: flex; align-items: flex-start; gap: 8px;
}
.pp-features li::before {
    content: '✓'; color: var(--mp-primary-dark, #0c6b65); font-weight: 700; flex-shrink: 0;
}
.pp-features-empty { color: #9ca3af; font-size: 13px; padding: 14px 0 0; font-style: italic; }

.pp-cta {
    display: block; margin-top: 18px; padding: 12px;
    border-radius: 10px; font-size: 14px; font-weight: 700;
    text-align: center; text-decoration: none; transition: all .15s;
    border: none; cursor: pointer;
}
.pp-cta--primary { background: var(--mp-primary, #0f8a82); color: #fff; }
.pp-cta--primary:hover { background: var(--mp-primary-dark, #0c6b65); color: #fff; }
.pp-cta--ghost {
    background: #fff; color: var(--mp-ink, #111827);
    border: 1.5px solid #e5e7eb;
}
.pp-cta--ghost:hover { border-color: var(--mp-primary, #0f8a82); color: var(--mp-primary-dark, #0c6b65); }

.pp-extra {
    background: linear-gradient(135deg, #f0fdfa 0%, #ecfeff 100%);
    border-radius: 14px; padding: 24px;
    margin: 40px 0 60px; text-align: center;
}
.pp-extra h2 { margin: 0 0 6px; font-size: 22px; color: var(--mp-ink, #111827); }
.pp-extra p { color: #4b5563; max-width: 520px; margin: 0 auto; }

.pp-faq { padding: 30px 0 60px; }
.pp-faq h2 { font-size: 24px; color: var(--mp-ink, #111827); margin: 0 0 18px; }
.pp-faq details {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 14px 18px; margin-bottom: 10px;
}
.pp-faq summary { cursor: pointer; font-weight: 600; color: var(--mp-ink, #111827); }
.pp-faq details p { color: #4b5563; margin: 10px 0 0; line-height: 1.55; }
</style>
@endpush

@section('content')

<div class="pp-hero">
    <h1>Planes hechos para crecer</h1>
    <p>Empieza gratis con 25 productos en el marketplace. Cuando tu tienda crezca, escala al plan que necesites — sin penalizaciones.</p>
</div>

<div class="pp-grid">
    @foreach($plans as $plan)
        @php
            $isFeatured = strtolower($plan->name) === 'pro';
        @endphp
        <article class="pp-card {{ $isFeatured ? 'pp-card--featured' : '' }}">
            <h3>{{ $plan->name }}</h3>
            <p class="pp-tagline">
                @switch(strtolower($plan->name))
                    @case('gratis')        Para empezar a vender online sin compromiso. @break
                    @case('tienda web')    Tu tienda lista, sin facturación electrónica. @break
                    @case('caserito')      Facturación electrónica básica + POS. @break
                    @case('negocio')       Ecommerce + variantes + cupones. @break
                    @case('pro')           Todo lo del Negocio + módulo logístico, smart stock y reportes. @break
                    @case('enterprise')    Todo ilimitado + WhatsApp API y carrier integrations. @break
                    @default               Plan corporativo con beneficios extendidos.
                @endswitch
            </p>

            <div class="pp-price-row">
                @if($plan->is_free)
                    <span class="pp-price">S/ 0</span>
                    <span class="pp-currency">para siempre</span>
                @else
                    <span class="pp-currency">S/</span>
                    <span class="pp-price">{{ number_format($plan->price, 0) }}</span>
                @endif
            </div>
            <div class="pp-cycle">@if(!$plan->is_free) por mes @endif · {{ $plan->limit_users == 0 ? 'usuarios ilimitados' : $plan->limit_users . ' ' . ($plan->limit_users === 1 ? 'usuario' : 'usuarios') }} · {{ $plan->establishments }} establecimiento(s)</div>

            @if($plan->features->isEmpty())
                <div class="pp-features-empty">Solo facturación y POS</div>
            @else
                <ul class="pp-features">
                    @foreach($plan->features as $f)
                        <li>{{ $f['label'] }}</li>
                    @endforeach
                </ul>
            @endif

            @if($plan->is_free)
                <a href="{{ route('seller.register') }}" class="pp-cta pp-cta--primary">Crear mi tienda gratis →</a>
            @else
                <a href="{{ route('seller.register') }}?plan={{ urlencode($plan->name) }}"
                   class="pp-cta {{ $isFeatured ? 'pp-cta--primary' : 'pp-cta--ghost' }}">
                    {{ $isFeatured ? 'Empezar con ' . $plan->name : 'Elegir ' . $plan->name }} →
                </a>
            @endif
        </article>
    @endforeach
</div>

<div class="pp-extra">
    <h2>¿Necesitas algo distinto?</h2>
    <p>Multi-empresa, integraciones a medida, white-label o volúmenes especiales — escríbenos y armamos un plan a la medida.</p>
    <p style="margin-top: 16px"><a href="https://wa.me/51999999999?text={{ urlencode('Hola, quiero un plan corporativo de ebaemy') }}" class="pp-cta pp-cta--ghost" style="display: inline-block; max-width: 280px">💬 Hablar con ventas</a></p>
</div>

<section class="pp-faq">
    <h2>Preguntas frecuentes</h2>

    <details>
        <summary>¿El plan gratis tiene letra chica?</summary>
        <p>No. El plan gratis incluye tu propia tienda con subdominio, hasta 25 productos publicados en el marketplace y todas las funciones esenciales. Solo aplica el límite de productos en marketplace; tu tienda virtual no tiene límite de catálogo.</p>
    </details>

    <details>
        <summary>¿Cómo funciona la facturación electrónica?</summary>
        <p>Los planes con facturación (Caserito, Negocio, Pro, Enterprise) incluyen integración SUNAT lista para emitir boletas y facturas. El plan gratis y "Tienda Web" no incluyen facturación; si tu negocio lo requiere, escala al plan correspondiente.</p>
    </details>

    <details>
        <summary>¿Puedo cambiar de plan después?</summary>
        <p>Sí, en cualquier momento. Si subes de plan, los nuevos límites se aplican al instante. Si bajas, mantenemos tu data y solo aplicamos los límites del nuevo plan a partir del siguiente ciclo.</p>
    </details>

    <details>
        <summary>¿Qué pasa con mis productos en marketplace si cambio de plan?</summary>
        <p>Tus productos publicados se mantienen visibles. Si tu nuevo plan tiene un límite menor, no podrás publicar nuevos productos hasta despublicar algunos, pero los existentes siguen activos.</p>
    </details>

    <details>
        <summary>¿Cómo me cobran?</summary>
        <p>Los planes pagados se facturan mensualmente. Para activarlos contacta con ventas — actualmente las altas pasan por aprobación del equipo ebaemy para asegurar el correcto onboarding técnico.</p>
    </details>

    <details>
        <summary>¿Mis productos del marketplace se ven en redes sociales?</summary>
        <p>Sí. ebaemy genera feeds compatibles con Meta Catalog y Google Merchant Center que se actualizan automáticamente. Tus productos pueden mostrarse en Facebook, Instagram y Google Shopping desde tu plan gratis.</p>
    </details>
</section>

@endsection
