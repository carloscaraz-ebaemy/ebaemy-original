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
        <a href="#como-funciona">Cómo funciona</a>
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
                <p>Tus productos visibles en ebaemy.com/marketplace junto a otras tiendas verificadas.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="M9 21V9"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                </div>
                <h3>Tu tienda virtual</h3>
                <p>Subdominio propio (tuempresa.ebaemy.com) con tu logo, colores y productos.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 9 18l-5-5"/></svg>
                </div>
                <h3>Facturación SUNAT</h3>
                <p>Emite boletas, facturas y notas de crédito sin instalar nada — listo para OSE/PSE.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                </div>
                <h3>Stock multi-almacén</h3>
                <p>Control de inventario físico, comprometido y disponible en tiempo real.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 16v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2"/><path d="M5 10V7a7 7 0 0 1 14 0v3"/><circle cx="12" cy="15" r="3"/></svg>
                </div>
                <h3>Pedidos centralizados</h3>
                <p>Gestiona tus órdenes del marketplace y de tu tienda desde un mismo panel.</p>
            </div>
            <div class="sl-benefit">
                <div class="sl-benefit-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M12 18v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m16.24 16.24 2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="m16.24 7.76 2.83-2.83"/></svg>
                </div>
                <h3>Más visibilidad</h3>
                <p>Aparece en búsquedas, categorías y recomendaciones del marketplace ebaemy.</p>
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
