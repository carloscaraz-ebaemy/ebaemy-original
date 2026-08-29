{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index.

     Las garantías eran cuatro bloques escritos a mano: todos los tenants
     prometían "Despacho en 24-48h" aunque no fuera cierto. Ahora salen de
     App\Services\EcommerceHomeContent, cuyos defaults son exactamente esos
     cuatro — un tenant que no las edite ve lo mismo de siempre. --}}
@php
    $benefits = \App\Services\EcommerceHomeContent::benefits();
@endphp

@if(count($benefits))
        <section class="ec-trust-badges" aria-label="Garantías">
            <div class="ec-trust-grid" style="--ec-trust-cols: {{ min(4, count($benefits)) }}">
                @foreach($benefits as $benefit)
                <div class="ec-trust-item">
                    @include('ecommerce::layouts.partials_ecommerce.benefit_icon', ['icon' => $benefit['icon']])
                    <strong>{{ $benefit['title'] }}</strong>
                    @if($benefit['text'] !== '')
                    <span>{{ $benefit['text'] }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </section>
@endif

<style>
/* ═══ TRUST BADGES ═══ */
.ec-trust-badges { padding: 2rem 0; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; margin: 2rem 0; }
/* Las columnas se ajustan a cuántas garantías cargó el tenant: con tres,
   cuatro columnas dejaban un hueco al final. */
.ec-trust-grid { display: grid; grid-template-columns: repeat(var(--ec-trust-cols, 4), 1fr); gap: 1.5rem; max-width: 900px; margin: 0 auto; text-align: center; }
.ec-trust-item { display: flex; flex-direction: column; align-items: center; gap: 6px; color: var(--theme-primary, #4F46E5); }
.ec-trust-item strong { font-size: 14px; color: var(--theme-text-primary, #1e293b); }
.ec-trust-item span { font-size: 12px; color: var(--theme-text-secondary, #94a3b8); }
@media(max-width:768px) { .ec-trust-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; } }
</style>
