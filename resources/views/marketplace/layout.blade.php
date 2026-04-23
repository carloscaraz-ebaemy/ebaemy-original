<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Marketplace ebaemy — productos de todas nuestras tiendas')</title>
    <meta name="description" content="@yield('description', 'Descubre productos publicados por miles de tiendas que usan ebaemy. Un solo lugar para comprar, contactar o solicitar envío.')">
    <meta name="keywords"    content="@yield('keywords', 'marketplace peru, ebaemy, tiendas online, compra, productos, cataloogo')">
    <meta name="robots"      content="index, follow">

    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph — WhatsApp / Facebook / LinkedIn --}}
    <meta property="og:site_name"   content="ebaemy Marketplace">
    <meta property="og:locale"      content="es_PE">
    <meta property="og:type"        content="@yield('og_type', 'website')">
    <meta property="og:title"       content="@yield('og_title', 'Marketplace ebaemy')">
    <meta property="og:description" content="@yield('og_description', 'Productos de todas las tiendas ebaemy en un solo lugar.')">
    <meta property="og:image"       content="@yield('og_image', asset('logo/logo.png'))">
    <meta property="og:image:secure_url" content="@yield('og_image', asset('logo/logo.png'))">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url"         content="@yield('canonical', url()->current())">

    {{-- Twitter Card --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="@yield('og_title', 'Marketplace ebaemy')">
    <meta name="twitter:description" content="@yield('og_description', 'Productos de todas las tiendas ebaemy en un solo lugar.')">
    <meta name="twitter:image"       content="@yield('og_image', asset('logo/logo.png'))">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family: 'Inter', -apple-system, Segoe UI, sans-serif; background:#f7f7f9; color:#0f172a; }
        a { color:inherit; text-decoration:none; }
        img { max-width:100%; display:block; }

        .mp-nav { background:#fff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:20; }
        .mp-nav-left { display:flex; align-items:center; gap:20px; }
        .mp-logo { font-weight:700; font-size:20px; color:#111; display:flex; align-items:center; gap:8px; }
        .mp-logo-badge { background:#8b5cf6; color:#fff; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; letter-spacing:.5px; }
        .mp-search { flex:1; max-width:560px; margin:0 20px; display:flex; background:#f3f4f6; border-radius:10px; padding:8px 12px; align-items:center; gap:8px; }
        .mp-search input { flex:1; border:none; background:transparent; outline:none; font-size:14px; }
        .mp-nav-right { display:flex; gap:14px; align-items:center; font-size:14px; color:#4b5563; }
        .mp-nav-right a { color:#4b5563; font-weight:500; }
        .mp-nav-right a:hover { color:#111; }
        .mp-btn-login { background:#111; color:#fff !important; padding:8px 16px; border-radius:8px; font-weight:500; }

        .mp-container { max-width:1200px; margin:0 auto; padding:24px; }

        .mp-hero { background:linear-gradient(135deg,#8b5cf6 0%, #6366f1 100%); color:#fff; padding:48px 24px; border-radius:16px; margin-bottom:32px; }
        .mp-hero h1 { margin:0 0 10px; font-size:32px; font-weight:700; }
        .mp-hero p { margin:0; opacity:.9; font-size:16px; }

        .mp-filters { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center; }
        .mp-chip { background:#fff; border:1px solid #e5e7eb; color:#374151; padding:6px 14px; border-radius:999px; font-size:13px; cursor:pointer; }
        .mp-chip--active { background:#111; color:#fff; border-color:#111; }
        .mp-sort { margin-left:auto; background:#fff; border:1px solid #e5e7eb; padding:6px 10px; border-radius:8px; font-size:13px; }

        .mp-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:18px; }
        .mp-card { background:#fff; border-radius:12px; overflow:hidden; border:1px solid #eef0f3; transition:transform .15s ease, box-shadow .15s ease; }
        .mp-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.08); }
        .mp-card-img { aspect-ratio:1/1; background:#f3f4f6; overflow:hidden; }
        .mp-card-img img { width:100%; height:100%; object-fit:cover; }
        .mp-card-body { padding:14px 16px; }
        .mp-card-title { font-size:14px; font-weight:500; color:#111; line-height:1.35; margin:0 0 8px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .mp-card-price { font-weight:700; font-size:16px; color:#0f172a; }
        .mp-card-shop { font-size:12px; color:#64748b; margin-top:4px; display:flex; justify-content:space-between; align-items:center; }
        .mp-card-buy { background:#059669; color:#fff; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; text-decoration:none; }
        .mp-card-buy:hover { background:#047857; color:#fff; }
        /* Tenant verificado — insignia de confianza */
        .mp-verified-badge { display:inline-flex; align-items:center; gap:3px; background:#eff6ff; color:#1d4ed8; padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700; border:1px solid #bfdbfe; }
        .mp-verified-badge svg { flex-shrink:0; }
        .mp-verified-inline { display:inline-flex; align-items:center; gap:4px; color:#1d4ed8; font-weight:600; font-size:13px; margin-left:6px; vertical-align:middle; }
        /* Rating en card de listing */
        .mp-card-rating { display:flex; align-items:center; gap:4px; font-size:13px; margin-top:3px; }
        .mp-card-rating small { color:#64748b; font-size:11px; }

        .mp-empty { background:#fff; border-radius:12px; padding:60px 24px; text-align:center; color:#64748b; }

        .mp-footer { background:#111; color:#d1d5db; padding:32px 24px; margin-top:60px; text-align:center; font-size:13px; }
        .mp-footer a { color:#a5b4fc; }

        .mp-pag { display:flex; justify-content:center; margin-top:32px; gap:6px; }
        .mp-pag a, .mp-pag span { padding:8px 12px; border-radius:8px; background:#fff; border:1px solid #e5e7eb; color:#374151; font-size:13px; }
        .mp-pag .active { background:#111; color:#fff; border-color:#111; }

        @media (max-width: 640px) {
            .mp-search { margin:0 8px; }
            .mp-nav-right { display:none; }
            .mp-hero h1 { font-size:24px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="mp-nav">
        <div class="mp-nav-left">
            <a href="{{ route('marketplace.index') }}" class="mp-logo">
                ebaemy <span class="mp-logo-badge">MARKETPLACE</span>
            </a>
        </div>
        <form action="{{ route('marketplace.index') }}" method="GET" class="mp-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Busca productos, tiendas, categorías…">
        </form>
        <div class="mp-nav-right">
            <a href="{{ route('seller.landing') }}">Ingresar a mi tienda</a>
            <a href="{{ route('seller.landing') }}" class="mp-btn-login">Vender en ebaemy</a>
        </div>
    </header>

    <main class="mp-container">
        @yield('content')
    </main>

    <footer class="mp-footer">
        <div>© {{ date('Y') }} ebaemy — Todas las tiendas, un solo lugar.</div>
        <div style="margin-top:6px">
            <a href="{{ route('seller.landing') }}">¿Quieres vender?</a>
            ·
            <a href="{{ url('/guest-register') }}">Crear cuenta</a>
        </div>
    </footer>
</body>
</html>
