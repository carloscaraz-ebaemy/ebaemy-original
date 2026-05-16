<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu resumen semanal de ofertas</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0f172a">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;padding:32px 16px">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,.08)">
                <tr><td style="padding:32px 28px 8px">
                    <div style="display:inline-block;width:40px;height:40px;background:linear-gradient(135deg,#0f8a82,#0a6f68);border-radius:10px;color:#fff;font-size:20px;font-weight:800;text-align:center;line-height:40px">e</div>
                    <span style="font-size:18px;font-weight:700;margin-left:10px;vertical-align:6px">ebaemy</span>
                </td></tr>
                <tr><td style="padding:8px 28px">
                    <h1 style="margin:0;font-size:22px;font-weight:700;color:#0f172a">🔥 Ofertas de esta semana</h1>
                    <p style="margin:10px 0 0;font-size:14.5px;color:#475569;line-height:1.55">
                        Hola {{ $user->name }}, estas son las mejores ofertas en tus categorias
                        @if(!empty($category_names))
                            <strong>{{ implode(', ', array_slice($category_names, 0, 3)) }}</strong>
                        @endif.
                    </p>
                </td></tr>
                @if(count($offers) === 0)
                    <tr><td style="padding:24px 28px">
                        <p style="margin:0;padding:18px;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:10px;text-align:center;color:#64748b;font-size:14px">
                            Esta semana no hay ofertas nuevas en tus categorias. Vuelve la proxima!
                        </p>
                    </td></tr>
                @else
                    @foreach(array_chunk($offers, 2) as $row)
                        <tr><td style="padding:16px 22px 0">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
                            @foreach($row as $o)
                                <td valign="top" width="50%" style="padding:0 6px">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px">
                                        <tr><td>
                                            @if(!empty($o['image_url']))
                                                <img src="{{ $o['image_url'] }}" width="100%" alt="" style="display:block;width:100%;height:140px;object-fit:cover;border-radius:12px 12px 0 0;background:#fff">
                                            @endif
                                        </td></tr>
                                        <tr><td style="padding:10px 12px 12px">
                                            <p style="margin:0;font-size:13px;font-weight:600;color:#0f172a;line-height:1.35;min-height:34px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $o['title'] }}</p>
                                            <p style="margin:6px 0 0">
                                                <span style="font-size:15px;font-weight:700;color:#0c6b65">S/ {{ number_format($o['price'], 2) }}</span>
                                                @if(!empty($o['original_price']) && $o['original_price'] > $o['price'])
                                                    <span style="font-size:11.5px;color:#9ca3af;text-decoration:line-through;margin-left:4px">S/ {{ number_format($o['original_price'], 2) }}</span>
                                                @endif
                                                @if(!empty($o['discount_pct']))
                                                    <span style="display:inline-block;margin-left:4px;padding:1px 6px;background:#fee2e2;color:#991b1b;border-radius:999px;font-size:10px;font-weight:700">-{{ $o['discount_pct'] }}%</span>
                                                @endif
                                            </p>
                                            <p style="margin:8px 0 0">
                                                <a href="{{ url('/marketplace/item/' . $o['slug']) }}" style="display:inline-block;padding:6px 12px;background:#0f8a82;color:#fff;text-decoration:none;border-radius:6px;font-size:11.5px;font-weight:600">Ver</a>
                                            </p>
                                        </td></tr>
                                    </table>
                                </td>
                            @endforeach
                            @if(count($row) === 1)<td width="50%"></td>@endif
                            </tr></table>
                        </td></tr>
                    @endforeach
                @endif
                <tr><td style="padding:24px 28px 8px">
                    <a href="{{ route('marketplace.index') }}" style="display:inline-block;padding:12px 22px;background:linear-gradient(135deg,#0f8a82,#0a6f68);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px">Explorar todas las ofertas</a>
                </td></tr>
                <tr><td style="padding:24px 28px 28px;border-top:1px solid #e5e7eb;margin-top:18px">
                    <p style="margin:18px 0 0;font-size:11.5px;color:#94a3b8;line-height:1.5">
                        Recibes este resumen porque suscribiste a digest semanal en ebaemy.
                        <a href="{{ url('/marketplace/account') }}" style="color:#0c6b65">Cambiar frecuencia</a>.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
