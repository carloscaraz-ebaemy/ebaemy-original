@extends('marketplace.layout')

@section('title', 'Términos y condiciones — ebaemy Marketplace')
@section('description', 'Términos y condiciones de uso del marketplace ebaemy. Reglas para compradores y vendedores, responsabilidades, devoluciones y resolución de disputas.')

@include('marketplace.legal._layout')

@section('content')
<article class="mp-legal">
    <nav class="mp-legal__breadcrumb" aria-label="breadcrumb">
        <a href="{{ route('marketplace.index') }}">Marketplace</a> ›
        <span>Términos y condiciones</span>
    </nav>

    <h1>Términos y condiciones</h1>
    <span class="mp-legal__updated">Última actualización: {{ \Carbon\Carbon::create(2026,5,12)->translatedFormat('d \d\e F \d\e Y') }}</span>
    <p class="mp-legal__lead">Al usar ebaemy aceptas estos términos. Léelos con atención: regulan tu relación con la plataforma y con los vendedores que operan en ella.</p>

    <h2>1. Sobre ebaemy</h2>
    <p>ebaemy (en adelante "la Plataforma") es un marketplace online operado en la República del Perú que conecta a compradores con tiendas independientes ("Vendedores"). La Plataforma actúa como intermediario tecnológico y no es propietaria, vendedora ni titular de los productos publicados.</p>

    <h2>2. Quién puede usar la Plataforma</h2>
    <ul>
        <li><strong>Compradores:</strong> personas naturales mayores de 18 años con capacidad legal de contratar.</li>
        <li><strong>Vendedores:</strong> personas naturales con RUC o personas jurídicas formalmente constituidas, con RUC activo y habido en SUNAT.</li>
    </ul>

    <h2>3. Relación entre las partes</h2>
    <p>El contrato de compraventa se celebra <strong>directamente entre el Comprador y el Vendedor</strong>. ebaemy facilita la transacción, el procesamiento de pagos y la comunicación, pero no es parte del contrato de compraventa.</p>

    <h2>4. Productos y precios</h2>
    <p>Los Vendedores son responsables de la veracidad, calidad, descripción, stock y precio de los productos publicados. ebaemy se reserva el derecho de retirar publicaciones que infrinjan la ley peruana, los derechos de terceros o estos términos.</p>

    <h2>5. Pagos</h2>
    <p>Los pagos se procesan a través de pasarelas autorizadas (MercadoPago, Yape, Plin, transferencia bancaria) bajo los términos de cada operador. ebaemy no almacena datos de tarjetas. Los reembolsos siguen la política de la pasarela y del Vendedor.</p>

    <h2>6. Envíos y entregas</h2>
    <p>El envío, embalaje y entrega es responsabilidad del Vendedor. Los plazos y condiciones varían por tienda y se muestran en cada producto antes de comprar. Si tu pedido se retrasa o no llega, contacta primero al Vendedor; si no obtienes respuesta en 48h hábiles, escríbenos a <a href="mailto:soporte@ebaemy.com">soporte@ebaemy.com</a>.</p>

    <h2>7. Devoluciones y garantías</h2>
    <p>Aplican la <strong>Ley 29571 — Código de Protección y Defensa del Consumidor</strong> y las garantías legales del Perú. Mínimo 7 días calendario para arrepentimiento en ventas a distancia, contados desde la recepción del producto, salvo excepciones legales (productos perecibles, personalizados, abiertos).</p>

    <h2>8. Reseñas y contenido del usuario</h2>
    <p>Al publicar una reseña aceptas que sea revisable por ebaemy. Está prohibido publicar contenido falso, ofensivo, difamatorio o que viole derechos de terceros. ebaemy puede eliminar reseñas que incumplan estos criterios.</p>

    <h2>9. Propiedad intelectual</h2>
    <p>La marca "ebaemy", su logo, diseño y código fuente son propiedad de ebaemy. Las imágenes y descripciones de productos son propiedad del Vendedor correspondiente. No se permite el uso comercial sin autorización escrita.</p>

    <h2>10. Limitación de responsabilidad</h2>
    <p>ebaemy no responde por incumplimientos contractuales entre Comprador y Vendedor, salvo aquellos derivados directamente de fallas técnicas de la Plataforma. En caso de disputa, ebaemy puede mediar pero la decisión final corresponde a la vía administrativa (INDECOPI) o judicial.</p>

    <h2>11. Resolución de disputas</h2>
    <p>Estos términos se rigen por la legislación peruana. Las controversias se someten a INDECOPI y, subsidiariamente, a los jueces de Lima Metropolitana.</p>

    <h2>12. Modificaciones</h2>
    <p>Podemos actualizar estos términos en cualquier momento. La fecha de última actualización aparece al inicio. Si la modificación afecta tus derechos, te avisaremos por correo con 15 días de anticipación.</p>

    <div class="mp-legal__contact">
        <strong>Atención al consumidor</strong>
        <p style="margin:8px 0 0">Para reclamos, escríbenos a <a href="mailto:soporte@ebaemy.com">soporte@ebaemy.com</a>. Si no obtienes respuesta en 30 días calendario, puedes acudir a <a href="https://www.consumidor.gob.pe" target="_blank" rel="noopener">INDECOPI — Libro de Reclamaciones</a>.</p>
    </div>
</article>
@endsection
