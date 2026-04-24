@extends('marketplace.layout')

@section('title', '¡Solicitud enviada! — Marketplace ebaemy')

@section('content')
    @php
        // leadStatus viene en session flash desde MarketplaceController::lead()
        // → 'converted' = llegó al tenant, 'failed' = reintento pendiente, null = sin sesión (acceso directo)
        $isFailed = ($leadStatus ?? null) === 'failed';
    @endphp

    <div style="max-width: 600px; margin: 40px auto;">
        <div style="background: #fff; border-radius: var(--mp-radius-lg); padding: clamp(28px, 5vw, 48px); text-align: center; border: 1px solid var(--mp-line-soft); box-shadow: var(--mp-shadow-md);">
            @if($isFailed)
                <div style="display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;border-radius:50%;background:#fef3c7;color:#d97706;margin-bottom:18px;box-shadow:0 8px 20px rgba(245,158,11,0.22)">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h1 style="font-size:26px; margin:0 0 10px; color:#92400e; font-weight:800; letter-spacing:-0.02em">¡Solicitud registrada!</h1>
                <p style="color:var(--mp-ink-soft); font-size:15px; line-height:1.6; margin:0 auto 24px; max-width:420px">
                    Guardamos tu pedido para <strong style="color:var(--mp-ink)">{{ $listing->seller_display }}</strong>.
                    Hubo un retraso enviándolo automáticamente — nuestro equipo lo está procesando
                    y el vendedor te contactará en las próximas horas.
                </p>
            @else
                <div style="display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1fb1a6,#0a6f68);color:#fff;margin-bottom:18px;box-shadow:0 10px 28px rgba(15,138,130,0.35)">
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 9 18l-5-5"/></svg>
                </div>
                <h1 style="font-size:26px; margin:0 0 10px; color:var(--mp-ink); font-weight:800; letter-spacing:-0.02em">¡Solicitud enviada!</h1>
                <p style="color:var(--mp-ink-soft); font-size:15px; line-height:1.6; margin:0 auto 24px; max-width:420px">
                    Tu solicitud fue recibida y enviada a <strong style="color:var(--mp-ink)">{{ $listing->seller_display }}</strong>.
                    El vendedor se contactará contigo pronto para coordinar el pago y envío.
                </p>
            @endif

            <div style="background: var(--mp-line-soft); padding: 18px 22px; border-radius: 12px; margin: 24px 0; text-align: left; display:flex;gap:14px;align-items:center">
                @if($listing->image_url)
                    <img src="{{ $listing->image_url }}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:10px;flex-shrink:0">
                @else
                    <div style="width:72px;height:72px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;color:var(--mp-muted);font-size:11px;flex-shrink:0">Sin img</div>
                @endif
                <div style="flex:1;min-width:0">
                    <div style="font-size:11px; color:var(--mp-muted); margin-bottom:4px; text-transform:uppercase; letter-spacing:0.06em; font-weight:700">Producto solicitado</div>
                    <div style="font-weight:600;color:var(--mp-ink);font-size:14px;line-height:1.35">{{ $listing->title }}</div>
                    <div style="font-size:15px; color:var(--mp-primary-dark); margin-top:4px; font-weight:700">S/ {{ number_format($listing->display_price, 2) }}</div>
                </div>
            </div>

            <a href="{{ route('marketplace.index') }}" class="mp-cta-primary" style="width:auto;display:inline-flex;margin:0">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                Seguir explorando productos
            </a>
        </div>
    </div>
@endsection
