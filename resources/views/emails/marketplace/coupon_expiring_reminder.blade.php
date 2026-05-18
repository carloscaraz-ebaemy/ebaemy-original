@php
    $appName = config('app.name', 'ebaemy');
    $shopUrl = url('/marketplace');
    $couponsUrl = url('/marketplace/account/coupons');
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Roboto,Arial,sans-serif;background:#f1f5f9;color:#0f172a;line-height:1.5;">
<table cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f1f5f9;padding:32px 16px">
<tr><td align="center">
<table cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 6px 18px rgba(15,23,42,.08)">
    <tr><td style="background:linear-gradient(135deg,#dc2626,#b91c1c);padding:24px 28px;text-align:center;color:#fff">
        <div style="font-size:42px">⏰</div>
        <div style="font-size:18px;font-weight:700;margin-top:8px">Tus cupones expiran pronto</div>
    </td></tr>
    <tr><td style="padding:28px 28px 8px">
        <p style="margin:0 0 18px;color:#0f172a;font-size:15px">
            Hola {{ $user->name ?: 'comprador' }}, no dejes pasar la oportunidad. Estos cupones expiran en menos de 48 horas:
        </p>
        @foreach($coupons as $c)
            @php
                $valueLabel = $c->type === 'percent' ? '-' . (int) $c->value . '%' : '-S/ ' . number_format($c->value, 2);
                $hoursLeft = \Carbon\Carbon::parse($c->expires_at)->diffInHours(now());
            @endphp
            <table cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#fffbeb;border:1.5px dashed #f59e0b;border-radius:12px;margin-bottom:12px">
                <tr><td style="padding:14px 18px">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <div style="font-family:'SF Mono',Menlo,monospace;font-size:18px;font-weight:800;color:#78350f;letter-spacing:.05em">{{ $c->code }}</div>
                            @if($c->name)<div style="font-size:13px;color:#92400e;margin-top:2px">{{ $c->name }}</div>@endif
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:18px;font-weight:800;color:#b45309">{{ $valueLabel }}</div>
                            <div style="font-size:11px;color:#dc2626;font-weight:700;margin-top:2px">Vence en {{ $hoursLeft }}h</div>
                        </div>
                    </div>
                </td></tr>
            </table>
        @endforeach
    </td></tr>
    <tr><td style="padding:16px 28px 28px;text-align:center">
        <a href="{{ $shopUrl }}" style="display:inline-block;background:linear-gradient(135deg,#0f8a82,#0a6f68);color:#fff;text-decoration:none;font-weight:700;padding:14px 32px;border-radius:10px;font-size:15px">
            Comprar ahora
        </a>
        <div style="margin-top:12px">
            <a href="{{ $couponsUrl }}" style="color:#0f8a82;font-size:13px;text-decoration:none">Ver todos mis cupones</a>
        </div>
    </td></tr>
    <tr><td style="background:#f8fafc;padding:18px 28px;text-align:center;color:#94a3b8;font-size:11px;border-top:1px solid #e2e8f0">
        Si ya no quers recibir estos recordatorios, podes desactivar las notificaciones desde tu cuenta.<br>
        <span style="opacity:.7">{{ $appName }} {{ date('Y') }}</span>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
