<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vende en ebaemy — Publica tu tienda en el marketplace</title>
    <meta name="description" content="Publica tus productos en el marketplace ebaemy, ten tu propia tienda virtual, gestiona pedidos y emite comprobantes electrónicos.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/design-tokens.css') }}">

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: var(--eb-font, 'Inter', system-ui, sans-serif);
            color: var(--eb-ink, #0f172a);
            background: #ffffff;
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
            letter-spacing: -0.01em;
        }
        a { text-decoration: none; color: inherit; }

        /* ── NAV ───────────────────────────────────────────── */
        .sl-nav {
            position: sticky; top: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px clamp(20px, 5vw, 56px);
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--eb-line, #e2e8f0);
        }
        .sl-logo {
            font-weight: 800; font-size: 20px; letter-spacing: -0.02em;
            color: var(--eb-ink, #0f172a);
        }
        .sl-logo-badge {
            display: inline-block; margin-left: 8px; padding: 2px 8px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em;
            text-transform: uppercase; vertical-align: 2px;
        }
        .sl-nav-links { display: flex; gap: 24px; align-items: center; font-size: 14px; font-weight: 500; }
        .sl-nav-links a { color: var(--eb-ink-soft, #475569); }
        .sl-nav-links a:hover { color: var(--eb-brand, #0f8a82); }
        .sl-nav-cta {
            padding: 10px 18px; border-radius: 10px;
            background: linear-gradient(135deg, var(--eb-brand-light, #1fb1a6), var(--eb-brand, #0f8a82));
            color: #fff !important; font-weight: 600; font-size: 13.5px;
            box-shadow: 0 4px 12px rgba(15,138,130,0.28);
            transition: transform .2s, box-shadow .2s;
        }
        .sl-nav-cta:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(15,138,130,0.35); }

        /* ── HERO ──────────────────────────────────────────── */
        .sl-hero {
            position: relative; overflow: hidden;
            padding: clamp(60px, 10vw, 120px) clamp(20px, 5vw, 56px) clamp(60px, 10vw, 100px);
            background:
                radial-gradient(ellipse at 15% 20%, rgba(31,177,166,0.20) 0%, transparent 60%),
                radial-gradient(ellipse at 85% 30%, rgba(37,99,235,0.12) 0%, transparent 55%),
                linear-gradient(180deg, #f6fbfc 0%, #ffffff 100%);
        }
        .sl-hero-container {
            max-width: 1100px; margin: 0 auto;
            display: grid; grid-template-columns: 1.1fr 0.9fr; gap: clamp(32px, 5vw, 64px);
            align-items: center;
        }
        .sl-hero h1 {
            font-size: clamp(34px, 5vw, 56px); line-height: 1.08; font-weight: 800;
            letter-spacing: -0.03em; margin: 0 0 20px;
            color: var(--eb-ink, #0f172a);
        }
        .sl-hero h1 .gradient {
            background: linear-gradient(135deg, var(--eb-brand, #0f8a82), var(--eb-brand-light, #1fb1a6));
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .sl-hero p {
            font-size: clamp(15px, 1.4vw, 18px); color: var(--eb-ink-soft, #475569);
            max-width: 520px; margin: 0 0 32px;
        }
        .sl-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .sl-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 24px; border-radius: 12px; font-weight: 600; font-size: 15px;
            cursor: pointer; border: 0; transition: transform .2s, box-shadow .2s;
        }
        .sl-btn-primary {
            background: linear-gradient(135deg, var(--eb-brand-light, #1fb1a6) 0%, var(--eb-brand, #0f8a82) 55%, var(--eb-brand-dark, #0a6f68) 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(15,138,130,0.35);
        }
        .sl-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15,138,130,0.45); }
        .sl-btn-ghost {
            background: #fff; color: var(--eb-ink, #0f172a);
            border: 1.5px solid var(--eb-line, #e2e8f0);
        }
        .sl-btn-ghost:hover { border-color: var(--eb-brand, #0f8a82); color: var(--eb-brand-dark, #0a6f68); }

        /* Card mockup decorativa */
        .sl-hero-visual {
            position: relative; aspect-ratio: 4 / 3.2;
            background: linear-gradient(135deg, #0a6f68 0%, #0f8a82 55%, #1fb1a6 100%);
            border-radius: 24px;
            box-shadow: 0 30px 80px -20px rgba(15,138,130,0.45), 0 12px 30px -10px rgba(15,23,42,0.2);
            padding: 28px;
            display: flex; flex-direction: column; justify-content: space-between;
            color: #fff;
            overflow: hidden;
        }
        .sl-hero-visual::before {
            content: ''; position: absolute; top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 70%);
            border-radius: 50%;
        }
        .sl-hv-title { font-size: 13px; opacity: 0.85; font-weight: 500; letter-spacing: 0.05em; text-transform: uppercase; }
        .sl-hv-big { font-size: 42px; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; }
        .sl-hv-stats { display: flex; gap: 20px; }
        .sl-hv-stat { flex: 1; background: rgba(255,255,255,0.12); backdrop-filter: blur(10px); border-radius: 14px; padding: 14px; }
        .sl-hv-stat-label { font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
        .sl-hv-stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }

        /* ── BENEFICIOS ────────────────────────────────────── */
        .sl-section { padding: clamp(60px, 8vw, 100px) clamp(20px, 5vw, 56px); }
        .sl-section-container { max-width: 1100px; margin: 0 auto; }
        .sl-section-header { text-align: center; max-width: 680px; margin: 0 auto 56px; }
        .sl-eyebrow {
            display: inline-block; padding: 6px 14px; border-radius: 99px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            margin-bottom: 16px;
        }
        .sl-section h2 {
            font-size: clamp(26px, 3.5vw, 38px); line-height: 1.15; font-weight: 800;
            letter-spacing: -0.02em; margin: 0 0 14px; color: var(--eb-ink, #0f172a);
        }
        .sl-section p.sl-lead {
            font-size: 16px; color: var(--eb-ink-soft, #475569); margin: 0;
        }

        .sl-benefits-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;
        }
        .sl-benefit {
            background: #fff; border: 1px solid var(--eb-line, #e2e8f0);
            border-radius: 18px; padding: 28px; transition: border-color .2s, transform .2s;
        }
        .sl-benefit:hover { border-color: var(--eb-brand-light, #1fb1a6); transform: translateY(-3px); }
        .sl-benefit-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 44px; height: 44px; border-radius: 12px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            margin-bottom: 16px;
        }
        .sl-benefit h3 { font-size: 17px; font-weight: 700; margin: 0 0 8px; letter-spacing: -0.01em; color: var(--eb-ink, #0f172a); }
        .sl-benefit p { font-size: 14px; color: var(--eb-ink-soft, #475569); margin: 0; line-height: 1.55; }

        /* ── CÓMO FUNCIONA ────────────────────────────────── */
        .sl-how { background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%); }
        .sl-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; }
        .sl-step {
            position: relative; padding: 28px 24px; background: #fff;
            border: 1px solid var(--eb-line, #e2e8f0); border-radius: 18px;
        }
        .sl-step-num {
            position: absolute; top: -18px; left: 24px;
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--eb-brand-light, #1fb1a6), var(--eb-brand-dark, #0a6f68));
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 16px;
            box-shadow: 0 6px 14px rgba(15,138,130,0.3);
        }
        .sl-step h4 { font-size: 16px; font-weight: 700; margin: 12px 0 8px; color: var(--eb-ink, #0f172a); }
        .sl-step p { font-size: 13.5px; color: var(--eb-ink-soft, #475569); margin: 0; line-height: 1.55; }

        /* ── LOGÍSTICA DESTACADA ───────────────────────────── */
        .sl-logistic { background: #fafbfc; }
        .sl-logistic-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: clamp(28px, 4vw, 56px); align-items: center;
        }
        .sl-logistic-list { list-style: none; padding: 0; margin: 24px 0 0; display: grid; gap: 14px; }
        .sl-logistic-list li {
            display: flex; align-items: flex-start; gap: 12px;
            font-size: 14.5px; color: var(--eb-ink-soft, #475569);
        }
        .sl-logistic-list .check {
            flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            display: inline-flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }
        .sl-logistic-visual {
            background: #fff; border: 1px solid var(--eb-line, #e2e8f0);
            border-radius: 18px; padding: 22px;
            box-shadow: 0 12px 30px -10px rgba(15,23,42,0.10);
        }
        .sl-kanban { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .sl-kanban-col {
            background: #f8fafc; border-radius: 12px; padding: 12px 10px; min-height: 180px;
        }
        .sl-kanban-col h5 {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; margin: 0 0 10px; color: var(--eb-ink-soft, #475569);
        }
        .sl-kanban-card {
            background: #fff; border: 1px solid var(--eb-line, #e2e8f0);
            border-radius: 8px; padding: 9px 10px; margin-bottom: 7px;
            font-size: 12px; color: var(--eb-ink, #0f172a);
        }
        .sl-kanban-card .num { font-weight: 700; color: var(--eb-brand-dark, #0a6f68); font-size: 11px; }
        .sl-kanban-card .meta { color: var(--eb-muted, #94a3b8); font-size: 11px; margin-top: 3px; }
        .sl-kanban-pill {
            display: inline-block; padding: 2px 8px; border-radius: 99px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            font-size: 10px; font-weight: 700; margin-top: 4px;
        }

        /* ── COMPARATIVA ───────────────────────────────────── */
        .sl-compare-table {
            background: #fff; border: 1px solid var(--eb-line, #e2e8f0);
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 12px 30px -10px rgba(15,23,42,0.08);
        }
        .sl-compare-row {
            display: grid; grid-template-columns: 1.3fr 1fr 1fr;
            border-bottom: 1px solid var(--eb-line-soft, #f1f5f9);
        }
        .sl-compare-row:last-child { border-bottom: 0; }
        .sl-compare-row > div { padding: 16px 18px; font-size: 14px; }
        .sl-compare-row.head > div {
            background: var(--eb-brand-soft, #e8f6f5);
            font-weight: 700; color: var(--eb-brand-dark, #0a6f68);
            font-size: 13px; letter-spacing: 0.02em;
        }
        .sl-compare-row > div:first-child { font-weight: 600; color: var(--eb-ink, #0f172a); }
        .sl-compare-row .yes { color: #059669; font-weight: 600; }
        .sl-compare-row .no { color: #94a3b8; }
        .sl-compare-row .ebaemy { background: rgba(31,177,166,0.06); }

        @media (max-width: 760px) {
            .sl-logistic-grid { grid-template-columns: 1fr; }
            .sl-compare-row { grid-template-columns: 1.5fr 1fr 1fr; }
            .sl-compare-row > div { padding: 12px 10px; font-size: 12.5px; }
        }

        /* ── CTA FINAL ────────────────────────────────────── */
        .sl-cta {
            background: linear-gradient(135deg, #0a6f68 0%, #0f8a82 50%, #1fb1a6 100%);
            color: #fff; text-align: center;
            padding: clamp(60px, 8vw, 100px) 24px;
        }
        .sl-cta h2 { color: #fff; font-size: clamp(28px, 3.5vw, 40px); margin: 0 0 16px; }
        .sl-cta p { color: rgba(255,255,255,0.9); font-size: 16px; max-width: 560px; margin: 0 auto 32px; }
        .sl-cta .sl-btn-primary {
            background: #fff; color: var(--eb-brand-dark, #0a6f68);
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
        }
        .sl-cta .sl-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(0,0,0,0.28); }

        /* ── NOTA FASE ────────────────────────────────────── */
        .sl-phase-note {
            max-width: 680px; margin: 32px auto 0;
            padding: 16px 20px; border-radius: 14px;
            background: rgba(255,255,255,0.12); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.22);
            color: rgba(255,255,255,0.92); font-size: 13.5px; line-height: 1.55;
        }

        /* ── FOOTER ───────────────────────────────────────── */
        .sl-footer {
            padding: 32px 24px; text-align: center;
            font-size: 13px; color: var(--eb-ink-soft, #475569);
            border-top: 1px solid var(--eb-line, #e2e8f0);
        }
        .sl-footer a { color: var(--eb-brand-dark, #0a6f68); font-weight: 500; }

        /* ── RESPONSIVE ────────────────────────────────────── */
        @media (max-width: 860px) {
            .sl-hero-container { grid-template-columns: 1fr; }
            .sl-hero-visual { aspect-ratio: 16 / 10; order: -1; }
            .sl-nav-links { display: none; }
        }
        @media (max-width: 500px) {
            .sl-hero-actions { flex-direction: column; align-items: stretch; }
            .sl-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<nav class="sl-nav">
    <a href="{{ url('/') }}" class="sl-logo">
        ebaemy<span class="sl-logo-badge">Sellers</span>
    </a>
    <div class="sl-nav-links">
        <a href="#beneficios">Beneficios</a>
        <a href="#logistica">Logística</a>
        <a href="#comparativa">Comparativa</a>
        <a href="{{ url('/marketplace') }}">Ir al marketplace</a>
    </div>
    <a href="{{ route('seller.register') }}" class="sl-nav-cta">Empezar ahora</a>
</nav>

<section class="sl-hero">
    <div class="sl-hero-container">
        <div>
            <h1>Vende en <span class="gradient">ebaemy</span><br>y haz crecer tu negocio.</h1>
            <p>Publica tus productos en nuestro marketplace, ten tu propia tienda virtual, controla stock e inventario, y emite comprobantes electrónicos — todo desde un solo panel.</p>
            <div class="sl-hero-actions">
                <a href="{{ route('seller.register') }}" class="sl-btn sl-btn-primary">
                    Crear solicitud de vendedor
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <a href="{{ url('/marketplace') }}" class="sl-btn sl-btn-ghost">Ver el marketplace</a>
            </div>
        </div>
        <div class="sl-hero-visual">
            <div>
                <div class="sl-hv-title">Panel del vendedor</div>
                <div class="sl-hv-big">+ S/ 12,450</div>
                <div style="font-size:13px; opacity:.85; margin-top:4px">ventas del mes</div>
            </div>
            <div class="sl-hv-stats">
                <div class="sl-hv-stat">
                    <div class="sl-hv-stat-label">Pedidos</div>
                    <div class="sl-hv-stat-value">128</div>
                </div>
                <div class="sl-hv-stat">
                    <div class="sl-hv-stat-label">Productos</div>
                    <div class="sl-hv-stat-value">47</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="sl-section" id="beneficios">
    <div class="sl-section-container">
        <div class="sl-section-header">
            <span class="sl-eyebrow">Por qué ebaemy</span>
            <h2>Todo lo que necesitas para vender en línea</h2>
            <p class="sl-lead">Desde la publicación de productos hasta la emisión de comprobantes electrónicos — sin integraciones adicionales.</p>
        </div>
        <div class="sl-benefits-grid">
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                </div>
                <h3>Marketplace central</h3>
                <p>Tus productos visibles en ebaemy.com/marketplace junto a otras tiendas verificadas con RUC validado.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="M9 21V9"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                </div>
                <h3>Tu tienda virtual propia</h3>
                <p>Subdominio (tuempresa.ebaemy.com) con tu logo, colores, banners y productos. Tema responsive listo para móvil.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                </div>
                <h3>Facturación SUNAT</h3>
                <p>Boletas, facturas, notas de crédito/débito y guías de remisión electrónicas. Compatible con OSE/PSE.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                </div>
                <h3>Smart Stock multi-almacén</h3>
                <p>Stock físico, comprometido y disponible en tiempo real. Sin sobreventa: las reservas se liberan automáticamente.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                </div>
                <h3>POS punto de venta</h3>
                <p>Vende en tu local físico con la misma cuenta. Un solo stock entre POS, marketplace y tienda virtual.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="3"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                </div>
                <h3>Cobros online con Culqi</h3>
                <p>Acepta tarjetas Visa, Mastercard y Yape. Pre-autorización + captura async para no perder ventas por timeouts.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m13 2-2 2.5h3L12 7"/><path d="M19 9A7 7 0 1 1 5 9c0-2 1-3.9 3-5.4l3 4"/><path d="M11 12 9 22l4-3 4 3-2-10"/></svg>
                </div>
                <h3>Promos & Flash Sales</h3>
                <p>Cupones (% / monto / envío gratis), ofertas relámpago con countdown y precios distintos para marketplace y tienda.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </div>
                <h3>WhatsApp automático</h3>
                <p>Notifica al cliente cuando su pedido se confirma, prepara, despacha o entrega — sin escribir nada manual.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M3 16v5h5"/><path d="M16 21h5v-5"/></svg>
                </div>
                <h3>Logística provincia</h3>
                <p>Cola de almacén kanban, guías de remisión SUNAT, integración con Chazki / 99Minutos y tracking público para tu cliente.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </div>
                <h3>Recupera ventas perdidas</h3>
                <p>Carritos abandonados, alertas de stock para clientes esperando reposición, wishlist y comparador de productos.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h3>Multi-usuario con roles</h3>
                <p>Crea usuarios para vendedores, almaceneros (acceso solo al kanban) y administradores. Cada uno con su almacén.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 14 4-4 4 4 6-6"/></svg>
                </div>
                <h3>Reportes & Customer 360°</h3>
                <p>Dashboard de ventas, kardex de inventario, historial completo del cliente (compras, reclamos, contactos).</p>
            </div>
        </div>
    </div>
</section>

<section class="sl-section sl-how" id="como-funciona">
    <div class="sl-section-container">
        <div class="sl-section-header">
            <span class="sl-eyebrow">Simple y rápido</span>
            <h2>Cómo funciona</h2>
            <p class="sl-lead">Un proceso claro desde la solicitud hasta tu primera venta.</p>
        </div>
        <div class="sl-steps">
            <div class="sl-step">
                <div class="sl-step-num">1</div>
                <h4>Registras tu empresa</h4>
                <p>Completas el formulario con tu RUC, datos del responsable y subdominio deseado.</p>
            </div>
            <div class="sl-step">
                <div class="sl-step-num">2</div>
                <h4>Validamos tu RUC</h4>
                <p>Verificamos contra SUNAT que tu empresa esté activa y habida.</p>
            </div>
            <div class="sl-step">
                <div class="sl-step-num">3</div>
                <h4>Aprobamos tu tienda</h4>
                <p>Nuestro equipo revisa tu solicitud. Te notificamos por correo y WhatsApp.</p>
            </div>
            <div class="sl-step">
                <div class="sl-step-num">4</div>
                <h4>Empiezas a vender</h4>
                <p>Configuras productos, almacén y stock — y publicas en el marketplace.</p>
            </div>
        </div>
    </div>
</section>

<section class="sl-section sl-logistic" id="logistica">
    <div class="sl-section-container">
        <div class="sl-logistic-grid">
            <div>
                <span class="sl-eyebrow">Despacho a todo el Perú</span>
                <h2>Logística que se mueve sola</h2>
                <p class="sl-lead" style="margin-top:14px">Tu equipo de almacén ve los pedidos en un kanban en tiempo real. Las guías de remisión SUNAT se generan solas. El courier las recoge. El cliente recibe tracking automático.</p>
                <ul class="sl-logistic-list">
                    <li>
                        <span class="check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        <span><strong>Kanban de almacén</strong> — pedidos pasan de pendiente → preparando → listo → despachado en tiempo real.</span>
                    </li>
                    <li>
                        <span class="check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        <span><strong>Guía de remisión electrónica</strong> SUNAT generada automáticamente al despachar.</span>
                    </li>
                    <li>
                        <span class="check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        <span><strong>Integración con couriers</strong> — Chazki, 99Minutos y agencias manuales (Olva, Shalom, Marvisur).</span>
                    </li>
                    <li>
                        <span class="check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        <span><strong>Tracking público</strong> — tu cliente sigue su pedido sin llamarte.</span>
                    </li>
                    <li>
                        <span class="check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        <span><strong>Devoluciones controladas</strong> — el stock vuelve al almacén correcto sin desfases.</span>
                    </li>
                </ul>
            </div>
            <div class="sl-logistic-visual" aria-hidden="true">
                <div class="sl-kanban">
                    <div class="sl-kanban-col">
                        <h5>Pendientes</h5>
                        <div class="sl-kanban-card">
                            <span class="num">NV-1042</span>
                            <div class="meta">Lima · Olva</div>
                            <span class="sl-kanban-pill">3 ítems</span>
                        </div>
                        <div class="sl-kanban-card">
                            <span class="num">NV-1043</span>
                            <div class="meta">Trujillo · Chazki</div>
                            <span class="sl-kanban-pill">1 ítem</span>
                        </div>
                    </div>
                    <div class="sl-kanban-col">
                        <h5>Preparando</h5>
                        <div class="sl-kanban-card">
                            <span class="num">NV-1041</span>
                            <div class="meta">Cuzco · Shalom</div>
                            <span class="sl-kanban-pill">5 ítems</span>
                        </div>
                    </div>
                    <div class="sl-kanban-col">
                        <h5>Despachado</h5>
                        <div class="sl-kanban-card">
                            <span class="num">NV-1038</span>
                            <div class="meta">Arequipa</div>
                            <span class="sl-kanban-pill" style="background:#dcfce7;color:#16a34a">✓ Entregado</span>
                        </div>
                        <div class="sl-kanban-card">
                            <span class="num">NV-1039</span>
                            <div class="meta">Piura</div>
                            <span class="sl-kanban-pill" style="background:#dcfce7;color:#16a34a">✓ Entregado</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="sl-section" id="comparativa">
    <div class="sl-section-container">
        <div class="sl-section-header">
            <span class="sl-eyebrow">Por qué ebaemy y no otra</span>
            <h2>Comparativa rápida</h2>
            <p class="sl-lead">Lo que sí incluye ebaemy y lo que tendrías que pagar aparte en otras plataformas.</p>
        </div>
        <div class="sl-compare-table">
            <div class="sl-compare-row head">
                <div>Característica</div>
                <div class="ebaemy">ebaemy</div>
                <div>Otras plataformas</div>
            </div>
            <div class="sl-compare-row">
                <div>Facturación SUNAT (boletas / facturas / NC)</div>
                <div class="ebaemy yes">✓ Incluido</div>
                <div class="no">App externa de pago</div>
            </div>
            <div class="sl-compare-row">
                <div>Comisión por venta del marketplace</div>
                <div class="ebaemy yes">0%</div>
                <div class="no">5% – 15%</div>
            </div>
            <div class="sl-compare-row">
                <div>Subdominio propio + tienda virtual</div>
                <div class="ebaemy yes">✓ Incluido</div>
                <div class="no">Plan superior</div>
            </div>
            <div class="sl-compare-row">
                <div>Stock multi-almacén con reserva real</div>
                <div class="ebaemy yes">✓ Incluido</div>
                <div class="no">App externa</div>
            </div>
            <div class="sl-compare-row">
                <div>Logística provincia + guías SUNAT</div>
                <div class="ebaemy yes">✓ Incluido</div>
                <div class="no">No disponible</div>
            </div>
            <div class="sl-compare-row">
                <div>WhatsApp automático al cliente</div>
                <div class="ebaemy yes">✓ Incluido</div>
                <div class="no">Plug-in pagado</div>
            </div>
            <div class="sl-compare-row">
                <div>POS para tienda física</div>
                <div class="ebaemy yes">✓ Incluido</div>
                <div class="no">Producto separado</div>
            </div>
        </div>
    </div>
</section>

<section class="sl-cta" id="cta">
    <h2>¿Listo para vender en ebaemy?</h2>
    <p>Crea tu solicitud de vendedor en minutos. Nuestro equipo revisará tus datos y activaremos tu tienda.</p>
    <a href="{{ route('seller.register') }}" class="sl-btn sl-btn-primary">
        Crear solicitud de vendedor
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
    </a>
</section>

<footer class="sl-footer">
    © {{ date('Y') }} ebaemy — <a href="{{ url('/marketplace') }}">Ver marketplace</a>
</footer>

</body>
</html>
