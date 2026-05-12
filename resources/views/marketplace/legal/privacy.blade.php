@extends('marketplace.layout')

@section('title', 'Política de privacidad — ebaemy Marketplace')
@section('description', 'Política de privacidad y tratamiento de datos personales del marketplace ebaemy. Cumplimos con la Ley 29733 y su reglamento de protección de datos.')

@include('marketplace.legal._layout')

@section('content')
<article class="mp-legal">
    <nav class="mp-legal__breadcrumb" aria-label="breadcrumb">
        <a href="{{ route('marketplace.index') }}">Marketplace</a> ›
        <span>Política de privacidad</span>
    </nav>

    <h1>Política de privacidad</h1>
    <span class="mp-legal__updated">Última actualización: {{ \Carbon\Carbon::create(2026,5,12)->translatedFormat('d \d\e F \d\e Y') }}</span>
    <p class="mp-legal__lead">Tu privacidad es una prioridad. Esta política explica qué datos recolectamos, para qué los usamos, con quién los compartimos y los derechos que tienes sobre ellos. Cumplimos con la <strong>Ley 29733</strong> y su reglamento.</p>

    <h2>1. Identidad del responsable</h2>
    <p>El responsable del tratamiento de los datos es <strong>ebaemy</strong>, con domicilio en Perú. Para cualquier consulta sobre privacidad, escríbenos a <a href="mailto:soporte@ebaemy.com">soporte@ebaemy.com</a>.</p>

    <h2>2. Qué datos recolectamos</h2>
    <ul>
        <li><strong>Datos de cuenta:</strong> nombre, correo electrónico, teléfono, contraseña cifrada.</li>
        <li><strong>Datos de compra:</strong> productos pedidos, dirección de entrega, método de pago elegido.</li>
        <li><strong>Datos del vendedor:</strong> razón social, RUC, datos del representante legal, cuenta bancaria.</li>
        <li><strong>Datos técnicos:</strong> dirección IP, navegador, sistema operativo, páginas visitadas, cookies.</li>
        <li><strong>Comunicaciones:</strong> mensajes que envíes a través de la plataforma o por correo.</li>
    </ul>

    <h2>3. Cómo los obtenemos</h2>
    <p>Los datos los recibimos directamente cuando: te registras, compras, vendes, contactas a soporte, te suscribes al newsletter o navegas en la plataforma (cookies).</p>

    <h2>4. Para qué los usamos</h2>
    <ul>
        <li>Procesar y entregar tus pedidos.</li>
        <li>Comunicarte el estado de tu compra (correo y WhatsApp).</li>
        <li>Emitir factura o boleta electrónica vía SUNAT.</li>
        <li>Prevenir fraude y proteger la cuenta.</li>
        <li>Mejorar la experiencia de uso y recomendarte productos relevantes.</li>
        <li>Enviarte newsletter SOLO si te suscribiste explícitamente (puedes cancelar en cualquier momento).</li>
    </ul>

    <h2>5. Con quién los compartimos</h2>
    <p>Compartimos datos estrictamente necesarios con:</p>
    <ul>
        <li><strong>Vendedores:</strong> nombre, teléfono, dirección de entrega para que cumplan tu pedido.</li>
        <li><strong>Pasarelas de pago</strong> (MercadoPago, Yape, Plin): para procesar la transacción.</li>
        <li><strong>Operadores logísticos</strong> contratados por el Vendedor.</li>
        <li><strong>SUNAT:</strong> para emitir comprobantes electrónicos.</li>
        <li><strong>Proveedores tecnológicos</strong> (hosting, correo transaccional, antifraude).</li>
    </ul>
    <p>Nunca vendemos tu información a terceros con fines publicitarios.</p>

    <h2>6. Cookies</h2>
    <p>Usamos cookies técnicas (esenciales para que el sitio funcione: carrito, sesión, CSRF) y, opcionalmente, cookies analíticas anónimas. Puedes desactivar cookies desde tu navegador, aunque podrían dejar de funcionar algunas funciones (login, carrito).</p>

    <h2>7. Tiempo de conservación</h2>
    <p>Conservamos tus datos mientras tu cuenta esté activa. Si la eliminas, retenemos los datos mínimos exigidos por ley (comprobantes fiscales: 5 años, según Código Tributario). Lo demás se borra.</p>

    <h2>8. Tus derechos</h2>
    <p>Tienes derecho a:</p>
    <ul>
        <li><strong>Acceder</strong> a los datos que tenemos sobre ti.</li>
        <li><strong>Rectificar</strong> datos incorrectos o desactualizados.</li>
        <li><strong>Cancelar</strong> el tratamiento (cierre de cuenta).</li>
        <li><strong>Oponerte</strong> al uso para fines no esenciales (marketing).</li>
        <li><strong>Portar</strong> tus datos a otra plataforma.</li>
    </ul>
    <p>Para ejercer cualquier derecho, escríbenos a <a href="mailto:soporte@ebaemy.com">soporte@ebaemy.com</a> y responderemos en máximo 20 días hábiles. Si no quedas conforme, puedes acudir a la <strong>Autoridad Nacional de Protección de Datos Personales (ANPDP)</strong>.</p>

    <h2>9. Seguridad</h2>
    <p>Aplicamos medidas técnicas y organizativas razonables: cifrado SSL/TLS en todo el sitio, contraseñas hasheadas (bcrypt), separación de bases de datos por tienda, copias de seguridad periódicas, control de accesos por rol. Aun así, ninguna medida es 100% infalible — si detectamos un incidente que afecte tus datos, te lo notificaremos.</p>

    <h2>10. Menores de edad</h2>
    <p>El servicio está dirigido a mayores de 18 años. No recolectamos conscientemente datos de menores. Si un menor de edad creó cuenta, escríbenos para eliminarla.</p>

    <h2>11. Cambios</h2>
    <p>Si modificamos esta política, actualizaremos la fecha al inicio. Para cambios significativos, te notificaremos por correo.</p>

    <div class="mp-legal__contact">
        <strong>Contacto de privacidad</strong>
        <p style="margin:8px 0 0">Para cualquier consulta sobre tus datos personales, escríbenos a <a href="mailto:soporte@ebaemy.com">soporte@ebaemy.com</a>.</p>
    </div>
</article>
@endsection
