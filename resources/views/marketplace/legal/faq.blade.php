@extends('marketplace.layout')

@section('title', 'Preguntas frecuentes — ebaemy Marketplace')
@section('description', 'Resuelve tus dudas sobre comprar y vender en el marketplace ebaemy: envíos, devoluciones, pagos, facturación y atención al cliente.')

@include('marketplace.legal._layout')

@section('content')
<article class="mp-legal">
    <nav class="mp-legal__breadcrumb" aria-label="breadcrumb">
        <a href="{{ route('marketplace.index') }}">Marketplace</a> ›
        <span>Preguntas frecuentes</span>
    </nav>

    <h1>Preguntas frecuentes</h1>
    <p class="mp-legal__lead">Lo más consultado por compradores y vendedores en ebaemy.</p>

    <h2>Para compradores</h2>

    <h3>¿Cómo realizo una compra?</h3>
    <p>Explora el catálogo, agrega productos al carrito y completa el checkout. Recibirás la confirmación por correo y por WhatsApp del vendedor. Cada tienda gestiona el envío y la entrega de forma independiente.</p>

    <h3>¿Qué métodos de pago acepta ebaemy?</h3>
    <p>Aceptamos tarjetas de crédito y débito (Visa, Mastercard, American Express) vía MercadoPago, transferencias Yape, Plin y transferencia bancaria directa cuando el vendedor lo habilita. Todas las pasarelas son procesadas con cifrado SSL.</p>

    <h3>¿Cómo sé que la tienda es confiable?</h3>
    <p>Todas las tiendas tienen <strong>RUC validado contra SUNAT</strong> y emiten <strong>facturación electrónica oficial</strong>. Las tiendas con el sello "Verificado" pasaron además una revisión adicional de identidad. Puedes ver opiniones de otros compradores en cada producto.</p>

    <h3>¿Quién entrega los productos?</h3>
    <p>Cada tienda gestiona su propio envío. El vendedor te contactará por WhatsApp o correo después de tu compra para coordinar entrega o recojo. Algunas tiendas ofrecen envío gratis sobre cierto monto.</p>

    <h3>¿Puedo devolver un producto?</h3>
    <p>Sí. La política de devolución depende de cada tienda y se rige por la Ley de Protección al Consumidor (INDECOPI). Como mínimo, tienes 7 días calendario para devolver productos no usados. Comunícate primero con la tienda; si no hay respuesta, escríbenos.</p>

    <h3>¿Necesito una cuenta para comprar?</h3>
    <p>No es obligatorio. Puedes comprar como invitado dejando solo nombre, correo y teléfono. Si abres cuenta, podrás ver tu historial de pedidos y dejar reseñas.</p>

    <h2>Para vendedores</h2>

    <h3>¿Cuánto cuesta vender en ebaemy?</h3>
    <p>El plan inicial es <strong>gratuito</strong> con hasta 25 productos. Cuando crezcas, puedes pasar a planes con más productos, multiusuario, integraciones logísticas y soporte prioritario.</p>

    <h3>¿Qué necesito para registrar mi tienda?</h3>
    <p>RUC activo en SUNAT, una cuenta bancaria a nombre de la empresa para recibir pagos, y al menos un producto cargado. El proceso de aprobación toma entre 24 y 48 horas hábiles.</p>

    <h3>¿Cómo recibo los pagos?</h3>
    <p>Si tu tienda tiene integración con MercadoPago, el dinero entra a tu cuenta MercadoPago automáticamente al confirmarse el pago. Para Yape, Plin o transferencia directa, el cliente te paga directo y tú gestionas la entrega.</p>

    <h3>¿Me cobran comisión?</h3>
    <p>Por venta marketplace cobramos una comisión transparente sobre cada pedido confirmado (ver <a href="{{ route('seller.landing') }}">página de vendedores</a>). Las ventas en tu propia tienda online ebaemy NO pagan comisión.</p>

    <div class="mp-legal__contact">
        <strong>¿No encontraste tu pregunta?</strong>
        <p style="margin:8px 0 0">Escríbenos a <a href="mailto:soporte@ebaemy.com">soporte@ebaemy.com</a> y respondemos en menos de 24h hábiles.</p>
    </div>
</article>
@endsection
