@extends('marketplace.layout')

@section('title', '¡Solicitud enviada! — Marketplace ebaemy')

@section('content')
    <div style="max-width:560px; margin:60px auto; background:#fff; border-radius:16px; padding:40px; text-align:center;">
        <div style="font-size:48px; margin-bottom:12px">✅</div>
        <h1 style="font-size:24px; margin:0 0 8px; color:#059669">¡Solicitud enviada!</h1>
        <p style="color:#374151; font-size:15px; line-height:1.6; margin:0 0 20px">
            Tu solicitud fue recibida y enviada a <strong>{{ $listing->seller_display }}</strong>.<br>
            El vendedor se contactará contigo pronto para coordinar el pago y envío.
        </p>

        <div style="background:#f3f4f6; padding:14px 18px; border-radius:10px; margin:20px 0; text-align:left">
            <div style="font-size:13px; color:#64748b; margin-bottom:4px">Producto solicitado</div>
            <div style="font-weight:600">{{ $listing->title }}</div>
            <div style="font-size:13px; color:#6d28d9; margin-top:4px">S/ {{ number_format($listing->display_price, 2) }}</div>
        </div>

        <a href="{{ route('marketplace.index') }}" style="display:inline-block; background:#111; color:#fff; padding:12px 24px; border-radius:10px; font-weight:500">
            Seguir explorando productos
        </a>
    </div>
@endsection
