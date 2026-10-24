@extends('marketplace.layout')

@section('title', 'Envíos, cambios y devoluciones — ebaemy Marketplace')
@section('description', 'Cómo funcionan los envíos, cambios y devoluciones en ebaemy: cada tienda vende, cobra, despacha y atiende sus cambios según sus condiciones. Consulta las de cada tienda.')

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
        atiende cambios y devoluciones, según sus propias condiciones. Aquí tienes las de cada una.
    </p>

    <h2>Cómo funciona</h2>
    <ul>
        <li><strong>Envío:</strong> cada producto muestra en su ficha cuánto cuesta y cuánto demora, para Lima y para provincias, según lo que indicó la tienda. Si la tienda aún no lo indicó, verás «A coordinar con la tienda» y te lo dirán antes de que pagues.</li>
        <li><strong>Pago:</strong> pagas directamente a la tienda. El costo de envío no se suma en el carrito de ebaemy: lo pagas a la tienda al coordinar la entrega.</li>
        <li><strong>Cambios y devoluciones:</strong> cada tienda publica sus condiciones (plazos, quién paga el envío del cambio, productos excluidos). Lo que diga la tienda no limita tus derechos de garantía legal como consumidor.</li>
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
