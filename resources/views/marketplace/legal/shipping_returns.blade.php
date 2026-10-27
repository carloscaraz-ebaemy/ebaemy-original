@extends('marketplace.layout')

@section('title', 'Envíos, cambios y devoluciones — ebaemy Marketplace')
@section('description', 'Envíos, cambios y devoluciones en ebaemy: 7 días para cambiar o devolver si tu producto llega dañado, con falla o no es lo que pediste, en todas las tiendas. Consulta las condiciones de cada una.')

@include('marketplace.legal._layout')

@section('content')
<article class="mp-legal">
    <nav class="mp-legal__breadcrumb" aria-label="breadcrumb">
        <a href="{{ route('marketplace.index') }}">Marketplace</a> ›
        <span>Envíos y devoluciones</span>
    </nav>

    <h1>Envíos, cambios y devoluciones</h1>
    <p class="mp-legal__lead">
        En ebaemy compras directamente a cada tienda. La tienda que te vende es quien cobra, envía y
        atiende cambios y devoluciones, siempre con la garantía mínima de 7 días de ebaemy. Aquí tienes las condiciones de cada una.
    </p>

    {{-- Politica minima del marketplace (decidida el 2026-10-27). Google
         Merchant Center la exige para aceptar los productos, y el JSON-LD de
         la ficha (hasMerchantReturnPolicy) declara exactamente esto. --}}
    <section id="garantia-7-dias" class="mp-sr-min">
        <h2>Garantía de 7 días en todas las tiendas</h2>
        <p>Si tu producto <strong>llega dañado, tiene una falla o no es lo que pediste</strong>, tienes
            <strong>7 días calendario</strong> desde que lo recibes para pedir el cambio o la devolución de tu dinero.
            <strong>No pagas el envío de vuelta</strong>: lo asume la tienda.</p>
        <ol>
            <li>Escribe a la tienda que te vendió con tu número de pedido y una foto del problema.</li>
            <li>Conserva el producto con sus accesorios y, si puedes, su empaque.</li>
            <li>La tienda te cambia el producto o te devuelve el dinero por el mismo medio con el que pagaste.</li>
        </ol>
        <p class="mp-sr-min__note">Por otros motivos (no te quedó, cambiaste de opinión) depende de cada tienda: revisa sus condiciones abajo.
            Algunas ofrecen más plazo; ninguna menos. Nada de esto limita tu garantía legal como consumidor.</p>
    </section>

    <h2>Cómo funciona</h2>
    <ul>
        <li><strong>Envío:</strong> cada producto muestra en su ficha cuánto cuesta y cuánto demora, para Lima y para provincias, según lo que indicó la tienda. Si la tienda aún no lo indicó, verás «A coordinar con la tienda» y te lo dirán antes de que pagues.</li>
        <li><strong>Pago:</strong> pagas directamente a la tienda. El costo de envío no se suma en el carrito de ebaemy: lo pagas a la tienda al coordinar la entrega.</li>
        <li><strong>Cambios y devoluciones:</strong> todas cumplen la <a href="#garantia-7-dias">garantía de 7 días</a> por daño, falla o error. Para otros motivos, cada tienda publica sus condiciones (plazos, quién paga el envío del cambio, productos excluidos).</li>
        <li><strong>Reclamos:</strong> cada tienda tiene su Libro de Reclamaciones. Para un problema con un pedido, regístralo en el de la tienda que te vendió.</li>
    </ul>

    <h2>Condiciones de cada tienda</h2>
    @if(count($stores))
        <div class="mp-sr">
            @foreach($stores as $st)
                <section class="mp-sr__store">
                    <h3 class="mp-sr__name">
                        @if($st['sub'])
                            <a href="{{ route('marketplace.tenant', ['subdomain' => $st['sub']]) }}">{{ $st['name'] }}</a>
                        @else
                            {{ $st['name'] }}
                        @endif
                    </h3>
                    <p class="mp-sr__line">🚚 {{ $st['ship'] }}</p>
                    <p class="mp-sr__line">↩️ {{ $st['returns'] ?: 'Cambios y devoluciones: consulta con la tienda' }}</p>
                    @if($st['fqdn'])
                        <p class="mp-sr__links">
                            <a href="https://{{ $st['fqdn'] }}/ecommerce/cambios-devolucion" target="_blank" rel="noopener">Cambios y devoluciones</a>
                            · <a href="https://{{ $st['fqdn'] }}/ecommerce/politica-envio" target="_blank" rel="noopener">Política de envío</a>
                            · <a href="https://{{ $st['fqdn'] }}/ecommerce/libro-reclamaciones" target="_blank" rel="noopener">Libro de Reclamaciones</a>
                        </p>
                    @endif
                </section>
            @endforeach
        </div>
    @else
        <p>Aún no hay tiendas publicadas.</p>
    @endif

    <h2>¿Algo no salió bien?</h2>
    <p>Escribe primero a la tienda que te vendió: es quien puede resolverlo. Si no te responde, puedes registrar tu reclamo en su Libro de Reclamaciones o acudir a INDECOPI.</p>
</article>

@push('styles')
<style>
.mp-sr-min { border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 14px; padding: 14px 18px; margin: 4px 0 22px; }
.mp-sr-min h2 { margin-top: 0; }
.mp-sr-min ol { margin: 8px 0; padding-left: 20px; }
.mp-sr-min__note { font-size: 13px; color: #374151; margin: 8px 0 0; }
.mp-sr { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; margin: 8px 0 20px; }
.mp-sr__store { border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; background: #fff; }
.mp-sr__name { font-size: 15px; margin: 0 0 6px; }
.mp-sr__name a { color: inherit; text-decoration: none; }
.mp-sr__line { font-size: 13px; color: #374151; margin: 0 0 4px; }
.mp-sr__links { font-size: 12.5px; margin: 8px 0 0; }
.mp-sr__links a { color: var(--mp-primary, #0f8a82); }
</style>
@endpush
@endsection
