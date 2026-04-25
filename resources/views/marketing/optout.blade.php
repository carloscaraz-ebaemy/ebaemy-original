<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cancelar suscripción — ebaemy</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; margin: 0; padding: 24px; background: #f9fafb; color: #111; }
        .card { max-width: 520px; margin: 60px auto; background: #fff; padding: 40px 32px; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,.05); }
        h1 { margin: 0 0 8px; font-size: 22px; }
        p { color: #4b5563; line-height: 1.5; }
        .btn { display: inline-block; padding: 12px 22px; background: #dc2626; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .btn-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .ok { background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 14px; }
        textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; resize: vertical; font-family: inherit; }
        label { display: block; font-size: 13px; color: #4b5563; margin: 16px 0 6px; }
    </style>
</head>
<body>
    <div class="card">
        @if($already)
            <h1>✓ Suscripción cancelada</h1>
            <div class="ok">No volverás a recibir promociones de ebaemy en este contacto.</div>
            <p style="margin-top:20px;font-size:13px">Si fue un error, escríbenos a <a href="mailto:soporte@ebaemy.com">soporte@ebaemy.com</a> para reactivar.</p>
        @else
            <h1>¿Cancelar suscripción?</h1>
            <p>Estás a punto de dejar de recibir promociones de <strong>ebaemy Marketplace</strong>.</p>
            <p style="font-size:13px;color:#6b7280">Contacto: {{ $contact->name ?: ($contact->phone ?: $contact->email) }}</p>

            <form method="POST" action="{{ url('/unsubscribe/' . $contact->opt_out_token) }}">
                @csrf
                <label>Motivo (opcional)</label>
                <textarea name="reason" rows="3" maxlength="200" placeholder="Ej: ya no me interesa, demasiados mensajes, etc."></textarea>
                <div style="margin-top:20px;display:flex;gap:10px">
                    <button type="submit" class="btn">Confirmar cancelación</button>
                    <a href="{{ url('/marketplace') }}" class="btn btn-ghost">Volver al marketplace</a>
                </div>
            </form>
        @endif
    </div>
</body>
</html>
