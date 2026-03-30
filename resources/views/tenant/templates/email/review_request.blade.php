<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
body{margin:0;padding:0;font-family:'Segoe UI',Roboto,sans-serif;background:#f3f4f6}
.container{max-width:600px;margin:0 auto;background:#fff}
.header{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;padding:32px;text-align:center}
.header h1{margin:0;font-size:22px}
.body{padding:24px 32px}
.item{display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #e5e7eb}
.item-name{font-weight:600;color:#1f2937}
.stars{color:#f59e0b;font-size:24px;letter-spacing:4px}
.btn{display:inline-block;padding:14px 32px;background:#2563eb;color:#fff!important;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px}
.footer{padding:20px 32px;text-align:center;color:#9ca3af;font-size:13px}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div style="font-size:40px;margin-bottom:8px">&#11088;</div>
        <h1>Como fue tu experiencia?</h1>
        <p style="margin:8px 0 0;opacity:0.9">Tu opinion nos ayuda a mejorar</p>
    </div>
    <div class="body">
        <p>Hola <strong>{{ $firstName }}</strong>,</p>
        <p>Esperamos que estes disfrutando tu compra. Nos encantaria saber tu opinion:</p>
        @if(!empty($items))
        @foreach($items as $item)
        @php $item = (array) $item; @endphp
        <div class="item">
            <div>
                <div class="item-name">{{ $item['item']['description'] ?? $item['description'] ?? 'Producto' }}</div>
                <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
            </div>
        </div>
        @endforeach
        @endif
        <div style="text-align:center;margin:28px 0">
            <a href="{{ $reviewUrl }}" class="btn">Dejar mi resena</a>
        </div>
        <p style="color:#6b7280;font-size:14px">Tu resena ayuda a otros compradores a tomar mejores decisiones. Solo toma 1 minuto!</p>
    </div>
    <div class="footer">
        <p>Si tienes algun problema con tu pedido, contactanos antes de dejar tu resena.</p>
    </div>
</div>
</body>
</html>
