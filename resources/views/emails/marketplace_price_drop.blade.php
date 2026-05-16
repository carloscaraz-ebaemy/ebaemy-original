<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bajaron de precio productos que guardaste</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0f172a">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;padding:32px 16px">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,.08)">
                <tr><td style="padding:32px 28px 8px">
                    <div style="display:inline-block;width:40px;height:40px;background:linear-gradient(135deg,#0f8a82,#0a6f68);border-radius:10px;color:#fff;font-size:20px;font-weight:800;text-align:center;line-height:40px">e</div>
                    <span style="font-size:18px;font-weight:700;margin-left:10px;vertical-align:6px">ebaemy</span>
                </td></tr>
                <tr><td style="padding:8px 28px">
                    <h1 style="margin:0;font-size:22px;font-weight:700;color:#0f172a">📉 {{ $count === 1 ? 'Bajo' : 'Bajaron' }} de precio</h1>
                    <p style="margin:10px 0 0;font-size:14.5px;color:#475569;line-height:1.55">
                        Hola {{ $user->name }}, {{ $count === 1 ? 'un producto' : "$count productos" }} que guardaste en tu wishlist {{ $count === 1 ? 'bajo' : 'bajaron' }} de precio. Aprovecha mientras dure el descuento.
                    </p>
                </td></tr>
                @foreach($drops as $d)
                    <tr><td style="padding:16px 28px 0">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:12px">
                            <tr>
                                <td width="80" valign="top">
                                    @if(!empty($d['image_url']))
                                        <img src="{{ $d['image_url'] }}" width="72" height="72" alt="" style="display:block;width:72px;height:72px;object-fit:cover;border-radius:8px;background:#fff">
                                    @endif
                                </td>
                                <td valign="top" style="padding-left:12px">
                                    <p style="margin:0;font-size:14px;font-weight:600;color:#0f172a;line-height:1.35">{{ $d['title'] }}</p>
                                    <p style="margin:6px 0 0">
                                        <span style="font-size:16px;font-weight:700;color:#0c6b65">S/ {{ number_format($d['new_price'], 2) }}</span>
                                        <span style="font-size:13px;color:#9ca3af;text-decoration:line-through;margin-left:6px">S/ {{ number_format($d['old_price'], 2) }}</span>
                                        <span style="display:inline-block;margin-left:6px;padding:2px 8px;background:#ecfdf5;color:#047857;border-radius:999px;font-size:11.5px;font-weight:700">-{{ $d['saving_pct'] }}%</span>
                                    </p>
                                    <p style="margin:8px 0 0">
                                        <a href="{{ url('/marketplace/item/' . $d['slug']) }}" style="display:inline-block;padding:8px 14px;background:#0f8a82;color:#fff;text-decoration:none;border-radius:6px;font-size:12.5px;font-weight:600">Ver producto</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td></tr>
                @endforeach
                <tr><td style="padding:24px 28px 8px">
                    <a href="{{ route('marketplace.favorites') }}" style="display:inline-block;padding:12px 22px;background:#fff;color:#0c6b65;border:1.5px solid #0f8a82;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px">Ver todos mis favoritos</a>
                </td></tr>
                <tr><td style="padding:24px 28px 28px;border-top:1px solid #e5e7eb;margin-top:18px">
                    <p style="margin:18px 0 0;font-size:11.5px;color:#94a3b8;line-height:1.5">
                        Recibes este email porque marcaste estos productos como favoritos en ebaemy y aceptaste alertas de precio.
                        <a href="{{ url('/marketplace/account') }}" style="color:#0c6b65">Ajustar preferencias</a>.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
