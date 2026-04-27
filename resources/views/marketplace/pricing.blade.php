@extends('marketplace.layout')

@section('title', 'Planes y precios — ebaemy')
@section('description', 'Compara nuestros planes. Empieza gratis y publica hasta 25 productos en el marketplace, o escala con planes Pro y Enterprise.')
@section('keywords', 'planes, precios, ebaemy, marketplace, vender, tienda virtual, suscripción')
@section('og_title', 'Planes y precios de ebaemy')
@section('og_description', 'Crea tu tienda gratis. Publica hasta 25 productos en el marketplace sin pagar nada.')
@section('canonical', url('/precios'))

@push('styles')
<style>
.pp-hero { text-align: center; padding: 56px 20px 12px; }
.pp-hero h1 { font-size: clamp(28px, 5vw, 44px); margin: 0 0 12px; color: var(--mp-ink, #111827); letter-spacing: -0.02em; }
.pp-hero p { color: #4b5563; font-size: 17px; max-width: 580px; margin: 0 auto; }

/* Toggle mensual / anual */
.pp-toggle-wrap {
    display: flex; justify-content: center; gap: 12px;
    margin: 24px 0 8px; align-items: center;
}
.pp-toggle {
    background: #f3f4f6; border-radius: 999px; padding: 4px;
    display: inline-flex; gap: 2px; position: relative;
}
.pp-toggle button {
    background: transparent; border: 0; cursor: pointer;
    padding: 8px 18px; border-radius: 999px;
    font-size: 13.5px; font-weight: 600; color: #4b5563;
    transition: background-color .15s, color .15s;
}
.pp-toggle button.is-active {
    background: #fff; color: var(--mp-ink, #111827);
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.pp-savings-badge {
    display: inline-block; background: #dcfce7; color: #15803d;
    border-radius: 999px; padding: 3px 10px; font-size: 11px; font-weight: 700;
    margin-left: 6px;
}

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
    content: 'Más popular';
    position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
    background: var(--mp-primary, #0f8a82); color: #fff;
    padding: 4px 14px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
}
.pp-card h3 { margin: 0 0 6px; font-size: 20px; color: var(--mp-ink, #111827); }
.pp-price-row { display: flex; align-items: baseline; gap: 6px; margin: 12px 0 4px; }
.pp-price { font-size: 36px; font-weight: 800; color: var(--mp-ink, #111827); }
.pp-price-old { font-size: 15px; color: #9ca3af; text-decoration: line-through; margin-left: 6px; }
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

.pp-trust {
    display: flex; gap: 28px; flex-wrap: wrap; justify-content: center;
    padding: 20px 12px 40px; color: #6b7280; font-size: 13.5px;
}
.pp-trust span { display: inline-flex; align-items: center; gap: 6px; }
.pp-trust svg { color: var(--mp-primary, #0f8a82); }

/* Tabla comparativa */
.pp-compare { padding: 30px 0 60px; }
.pp-compare h2 { font-size: 24px; color: var(--mp-ink, #111827); margin: 0 0 18px; text-align: center; }
.pp-compare-wrap {
    overflow-x: auto;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
}
.pp-compare table {
    width: 100%; border-collapse: collapse; min-width: 720px;
}
.pp-compare th, .pp-compare td {
    padding: 12px 14px; font-size: 13.5px; text-align: center;
    border-bottom: 1px solid #f3f4f6;
}
.pp-compare th { background: #f9fafb; font-weight: 700; color: var(--mp-ink, #111827); }
.pp-compare td:first-child, .pp-compare th:first-child {
    text-align: left; min-width: 220px; font-weight: 500;
}
.pp-compare .yes { color: #16a34a; font-weight: 700; }
.pp-compare .no  { color: #cbd5e1; }
.pp-compare .lim { color: #6b7280; font-size: 12.5px; }
.pp-compare tr.row-section td {
    background: #f0fdfa; font-weight: 700; color: var(--mp-primary-dark, #0c6b65);
    text-transform: uppercase; font-size: 11.5px; letter-spacing: .04em;
}

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

@php
    /**
     * Para planes que no tienen features asignados en la BD pero existen
     * en el sistema (ej: Caserito = facturación + POS), listamos features
     * explícitas aquí para que el card no quede vacío.
     *
     * Match por nombre de plan en lowercase.
     */
    /**
     * Fallbacks por nombre de plan cuando la BD no tiene features
     * asignados. Solo se usa si $plan->features está vacío.
     */
    $defaultFeatures = [
        'caserito' => [
            ['label' => '🧾 Facturación electrónica SUNAT'],
            ['label' => '🛒 POS punto de venta'],
            ['label' => '📋 Boletas, facturas y notas de crédito'],
            ['label' => '📦 1 establecimiento'],
        ],
    ];
@endphp

@section('content')

<div class="pp-hero">
    <h1>Planes hechos para crecer</h1>
    <p>Empieza gratis con 25 productos en el marketplace. Cuando tu tienda crezca, escala al plan que necesites — sin penalizaciones.</p>
</div>

<div class="pp-toggle-wrap" id="ppToggle" role="tablist" aria-label="Periodicidad">
    <div class="pp-toggle">
        <button type="button" class="is-active" data-cycle="monthly" role="tab">Mensual</button>
        <button type="button" data-cycle="yearly" role="tab">
            Anual <span class="pp-savings-badge">-17%</span>
        </button>
    </div>
</div>

<div class="pp-grid">
    @foreach($plans as $plan)
        @php
            $isFeatured = strtolower($plan->name) === 'pro';
            $defaults   = $defaultFeatures[strtolower($plan->name)] ?? [];
        @endphp
        <article class="pp-card {{ $isFeatured ? 'pp-card--featured' : '' }}">
            <h3>{{ $plan->name }}</h3>
            <p class="pp-tagline">
                @switch(strtolower($plan->name))
                    @case('gratis')        Para empezar a vender online sin compromiso. @break
                    @case('tienda web')    Tu tienda virtual lista, sin facturación electrónica. @break
                    @case('caserito')      Facturación electrónica + POS para tu local. @break
                    @case('negocio')       Ecommerce + variantes + cupones. @break
                    @case('pro')           Todo del Negocio + módulo logístico, smart stock y reportes. @break
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
                    <span class="pp-price"
                          data-monthly="{{ number_format($plan->price, 0) }}"
                          data-yearly="{{ number_format(round($plan->price * 0.83), 0) }}">{{ number_format($plan->price, 0) }}</span>
                @endif
            </div>
            <div class="pp-cycle">
                @if(!$plan->is_free)
                    <span data-cycle-text="monthly">por mes</span><span data-cycle-text="yearly" hidden>por mes (facturado anual)</span> ·
                @endif
                {{ $plan->limit_users == 0 ? 'usuarios ilimitados' : $plan->limit_users . ' ' . ($plan->limit_users === 1 ? 'usuario' : 'usuarios') }} · {{ $plan->establishments }} establecimiento(s)
            </div>

            <ul class="pp-features">
                @if($plan->features->isEmpty() && !empty($defaults))
                    @foreach($defaults as $f)
                        <li>{{ $f['label'] }}</li>
                    @endforeach
                @else
                    @foreach($plan->features as $f)
                        <li>{{ $f['label'] }}</li>
                    @endforeach
                @endif
            </ul>

            @if($plan->is_free)
                <a href="{{ route('seller.register') }}" class="pp-cta pp-cta--primary">Empezar gratis →</a>
            @else
                <a href="{{ route('seller.register') }}?plan={{ urlencode($plan->name) }}"
                   class="pp-cta {{ $isFeatured ? 'pp-cta--primary' : 'pp-cta--ghost' }}">
                    Elegir {{ $plan->name }} →
                </a>
            @endif
        </article>
    @endforeach
</div>

<div class="pp-trust" aria-label="Garantías">
    <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg> Sin comisiones por venta</span>
    <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg> Cancela cuando quieras</span>
    <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg> Soporte en español</span>
    <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg> RUC validado en SUNAT</span>
</div>

<section class="pp-compare" id="comparativa">
    <h2>Comparativa completa</h2>
    <div class="pp-compare-wrap">
        <table>
            <thead>
                <tr>
                    <th>Característica</th>
                    @foreach($plans as $plan)
                        <th>{{ $plan->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr class="row-section"><td colspan="{{ $plans->count() + 1 }}">Catálogo & marketplace</td></tr>
                @php
                    $rows = [
                        ['label' => 'Tienda virtual con subdominio',         'key' => 'ecommerce'],
                        ['label' => 'Productos en marketplace',              'key' => 'marketplace_products_limit'],
                        ['label' => 'Variantes (talla, color, etc.)',        'key' => 'variants'],
                        ['__section__' => 'Promociones'],
                        ['label' => 'Cupones y promociones',                 'key' => 'promotions'],
                        ['label' => 'Flash sales',                           'key' => 'flash_sales'],
                        ['__section__' => 'Operaciones'],
                        ['label' => 'Múltiples establecimientos',            'key' => 'multi_establishment'],
                        ['label' => 'Smart Stock (físico/comprometido)',     'key' => 'smart_stock'],
                        ['label' => 'Módulo logístico',                      'key' => 'logistic_module'],
                        ['label' => 'Integración carrier API',               'key' => 'carrier_api'],
                        ['__section__' => 'Pagos & integraciones'],
                        ['label' => 'Pago Culqi pre-autorización',           'key' => 'culqi_preauth'],
                        ['label' => 'WhatsApp API (Meta Cloud)',             'key' => 'whatsapp_api'],
                        ['label' => 'Login con Google',                      'key' => 'google_login'],
                        ['__section__' => 'Avanzado'],
                        ['label' => 'Reportes avanzados',                    'key' => 'advanced_reports'],
                        ['label' => 'Réplica de lectura (escalado)',         'key' => 'read_replica'],
                    ];

                    /**
                     * Crea un mapa plan → set de feature keys (con su límite si aplica)
                     * para resolver rápido en la tabla comparativa.
                     */
                    $planFeatureMap = $plans->mapWithKeys(function ($p) {
                        $set = [];
                        foreach ($p->features as $f) {
                            $set[$f['key']] = $f['limit'];
                        }
                        return [$p->id => $set];
                    });
                @endphp
                @foreach($rows as $row)
                    @if(isset($row['__section__']))
                        <tr class="row-section"><td colspan="{{ $plans->count() + 1 }}">{{ $row['__section__'] }}</td></tr>
                    @else
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            @foreach($plans as $plan)
                                @php
                                    // array_key_exists distingue 'no asignado' de 'asignado con null':
                                    //   missing  → feature NO incluida en este plan       → —
                                    //   null     → incluida sin límite                    → ✓
                                    //   int > 0  → incluida con cuota                     → hasta N
                                    $set     = $planFeatureMap[$plan->id] ?? [];
                                    $hasKey  = array_key_exists($row['key'], $set);
                                    $val     = $hasKey ? $set[$row['key']] : null;
                                @endphp
                                <td>
                                    @if(!$hasKey)
                                        <span class="no">—</span>
                                    @elseif($val === null)
                                        <span class="yes">✓</span>
                                    @else
                                        <span class="lim">hasta {{ $val }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</section>

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
        <summary>¿Cobran comisión por cada venta?</summary>
        <p>No cobramos comisión por venta — solo el plan mensual fijo. Las pasarelas de pago (Culqi, Yape, etc.) tienen sus propias tarifas por transacción que ebaemy no controla.</p>
    </details>

    <details>
        <summary>¿Cómo funciona la facturación electrónica?</summary>
        <p>Los planes con facturación (Caserito, Negocio, Pro, Enterprise) incluyen integración SUNAT lista para emitir boletas, facturas y notas de crédito/débito. El plan gratis y "Tienda Web" no incluyen facturación.</p>
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
        <summary>¿El precio anual incluye descuento?</summary>
        <p>Sí. Pagar el plan anual cuesta lo equivalente a 10 meses (~17% de ahorro). Si cancelas antes del año recibes prorrateo del tiempo no usado.</p>
    </details>

    <details>
        <summary>¿Mis productos del marketplace se ven en redes sociales?</summary>
        <p>Sí. ebaemy genera feeds compatibles con Meta Catalog y Google Merchant Center que se actualizan automáticamente. Tus productos pueden mostrarse en Facebook, Instagram y Google Shopping desde tu plan gratis.</p>
    </details>
</section>

<script>
(function () {
    const toggle = document.getElementById('ppToggle');
    if (!toggle) return;
    const buttons = toggle.querySelectorAll('button[data-cycle]');
    const prices  = document.querySelectorAll('.pp-price[data-monthly]');
    const cycleTexts = document.querySelectorAll('[data-cycle-text]');

    function setCycle(cycle) {
        buttons.forEach(b => b.classList.toggle('is-active', b.dataset.cycle === cycle));
        prices.forEach(p => {
            p.textContent = cycle === 'yearly' ? p.dataset.yearly : p.dataset.monthly;
        });
        cycleTexts.forEach(el => {
            el.hidden = el.dataset.cycleText !== cycle;
        });
    }

    buttons.forEach(b => b.addEventListener('click', () => setCycle(b.dataset.cycle)));
})();
</script>

@endsection
