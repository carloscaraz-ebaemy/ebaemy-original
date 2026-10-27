{{-- Ficha de vista previa para WhatsApp/Facebook/Telegram: la de ebaemy.com (ruta «/») y la de cada producto (/m/p/{id}). --}}
@php
    $mpCfg     = \App\Models\System\Configuration::firstCached();
    $mpOgTitle = $mpCfg->marketplace_og_title ?? 'Marketplace ebaemy — Compra de tiendas verificadas';
    $mpOgDesc  = $mpCfg->marketplace_og_description ?? 'Descubre productos de tiendas peruanas verificadas en un solo lugar. Envío a todo Perú, contacto directo con el vendedor.';
    $mpOgImage = $mpCfg ? $mpCfg->marketplace_og_image_url : asset('logo/logo.jpg');
    $mpOgMeta  = $mpCfg ? $mpCfg->marketplaceOgImageMeta() : null;
    $mpUrl     = route('marketplace.index');
    // Ficha de un producto (MarketplaceController@shareProduct): sus datos
    // sustituyen a los del marketplace.
    if (!empty($card)) {
        $mpOgTitle = $card['title'];
        $mpOgDesc  = $card['description'];
        $mpOgImage = $card['image'] ?: $mpOgImage;
        $mpOgMeta  = $card['image'] ? ['width' => $card['width'], 'height' => $card['height']] : $mpOgMeta;
        $mpUrl     = $card['url'];
    }
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $mpOgTitle }}</title>
    <meta name="description" content="{{ $mpOgDesc }}">
    <link rel="canonical" href="{{ $mpUrl }}">
    <meta property="og:site_name" content="ebaemy Marketplace">
    <meta property="og:locale" content="es_PE">
    <meta property="og:type" content="{{ $card['type'] ?? 'website' }}">
    <meta property="og:title" content="{{ $mpOgTitle }}">
    <meta property="og:description" content="{{ $mpOgDesc }}">
    <meta property="og:image" content="{{ $mpOgImage }}">
    <meta property="og:image:secure_url" content="{{ $mpOgImage }}">
    @if(!empty($mpOgMeta['width']))
    <meta property="og:image:width" content="{{ $mpOgMeta['width'] }}">
    <meta property="og:image:height" content="{{ $mpOgMeta['height'] }}">
    @endif
    <meta property="og:url" content="{{ $mpUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $mpOgTitle }}">
    <meta name="twitter:description" content="{{ $mpOgDesc }}">
    <meta name="twitter:image" content="{{ $mpOgImage }}">
    <meta http-equiv="refresh" content="0;url={{ $mpUrl }}">
</head>
<body><a href="{{ $mpUrl }}">{{ $mpOgTitle }}</a></body>
</html>
