<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crear solicitud de vendedor — ebaemy</title>

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
            background:
                radial-gradient(ellipse at 15% 20%, rgba(31,177,166,0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 85% 80%, rgba(37,99,235,0.10) 0%, transparent 55%),
                #fafbfc;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.01em;
        }
        a { text-decoration: none; color: inherit; }

        /* ── WRAPPER ───────────────────────────────────────── */
        .sr-wrap {
            max-width: 820px;
            margin: 0 auto;
            padding: 40px clamp(16px, 4vw, 32px);
        }
        .sr-header { text-align: center; margin-bottom: 32px; }
        .sr-logo {
            display: inline-block;
            font-weight: 800; font-size: 22px; letter-spacing: -0.02em;
            color: var(--eb-ink, #0f172a);
            margin-bottom: 12px;
        }
        .sr-logo-badge {
            display: inline-block; margin-left: 6px; padding: 2px 10px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; vertical-align: 3px;
        }
        .sr-header h1 {
            font-size: clamp(24px, 3vw, 32px); font-weight: 800; margin: 8px 0 6px;
            letter-spacing: -0.02em;
        }
        .sr-header p { color: var(--eb-ink-soft, #475569); font-size: 15px; margin: 0; }

        /* ── STEPPER ───────────────────────────────────────── */
        .sr-stepper {
            display: flex; align-items: center; justify-content: center;
            gap: 6px; margin: 28px 0 32px;
        }
        .sr-step {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 16px; border-radius: 99px;
            background: #fff; border: 1.5px solid var(--eb-line, #e2e8f0);
            font-size: 13px; font-weight: 600; color: var(--eb-muted, #94a3b8);
            transition: all .25s var(--eb-ease);
        }
        .sr-step.active {
            background: linear-gradient(135deg, var(--eb-brand-light, #1fb1a6), var(--eb-brand-dark, #0a6f68));
            border-color: transparent; color: #fff;
            box-shadow: 0 6px 16px rgba(15,138,130,0.28);
        }
        .sr-step.done { background: var(--eb-brand-soft, #e8f6f5); border-color: var(--eb-brand-light, #1fb1a6); color: var(--eb-brand-dark, #0a6f68); }
        .sr-step-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 50%;
            background: rgba(0,0,0,0.06); font-size: 12px; font-weight: 700;
        }
        .sr-step.active .sr-step-num { background: rgba(255,255,255,0.22); }
        .sr-step-line { width: 20px; height: 2px; background: var(--eb-line, #e2e8f0); }
        @media (max-width: 600px) {
            .sr-step span.sr-step-label { display: none; }
            .sr-step { padding: 10px 12px; }
        }

        /* ── CARD ──────────────────────────────────────────── */
        .sr-card {
            background: #fff; border-radius: 20px;
            border: 1px solid var(--eb-line, #e2e8f0);
            box-shadow: 0 24px 64px -24px rgba(15,23,42,0.15), 0 4px 12px -4px rgba(15,23,42,0.06);
            padding: clamp(24px, 4vw, 40px);
        }
        .sr-card-header { margin-bottom: 20px; }
        .sr-card-header h2 { font-size: 20px; font-weight: 700; margin: 0 0 6px; letter-spacing: -0.01em; }
        .sr-card-header p { color: var(--eb-ink-soft, #475569); font-size: 14px; margin: 0; }

        /* ── FORM ──────────────────────────────────────────── */
        .sr-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 16px; }
        .sr-row.full { grid-template-columns: 1fr; }
        @media (max-width: 600px) { .sr-row { grid-template-columns: 1fr; } }

        .sr-field label {
            display: block; font-size: 13px; font-weight: 600; color: var(--eb-ink, #0f172a);
            margin-bottom: 6px;
        }
        .sr-field label .sr-optional { color: var(--eb-muted, #94a3b8); font-weight: 500; }
        .sr-field input, .sr-field select, .sr-field textarea {
            width: 100%; min-height: 48px;
            padding: 12px 14px;
            border: 1.5px solid var(--eb-line, #e2e8f0); border-radius: 12px;
            background: #fff; color: var(--eb-ink, #0f172a);
            font-size: 14.5px; font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .sr-field textarea { min-height: 90px; resize: vertical; }
        .sr-field input:focus, .sr-field select:focus, .sr-field textarea:focus {
            outline: none;
            border-color: var(--eb-brand, #0f8a82);
            box-shadow: 0 0 0 4px rgba(15,138,130,0.12);
        }
        .sr-field .sr-hint { font-size: 12px; color: var(--eb-muted, #94a3b8); margin-top: 4px; }
        .sr-field .sr-hint.ok { color: #059669; }
        .sr-field .sr-hint.err { color: #dc2626; }
        .sr-field input.is-invalid, .sr-field select.is-invalid, .sr-field textarea.is-invalid {
            border-color: #ef4444; background: #fef2f2;
        }

        .sr-subdomain-wrap {
            display: flex; align-items: stretch;
            border: 1.5px solid var(--eb-line, #e2e8f0); border-radius: 12px;
            overflow: hidden; background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }
        .sr-subdomain-wrap:focus-within {
            border-color: var(--eb-brand, #0f8a82);
            box-shadow: 0 0 0 4px rgba(15,138,130,0.12);
        }
        .sr-subdomain-wrap input {
            flex: 1; border: 0 !important; box-shadow: none !important;
            border-radius: 0; background: transparent;
        }
        .sr-subdomain-wrap input:focus { box-shadow: none !important; }
        .sr-subdomain-suffix {
            padding: 0 14px; background: #f8fafc;
            display: flex; align-items: center;
            font-size: 14px; color: var(--eb-ink-soft, #475569); font-weight: 500;
            border-left: 1px solid var(--eb-line, #e2e8f0);
        }

        .sr-checkbox { display: flex; gap: 10px; align-items: flex-start; padding: 12px; background: #f8fafc; border-radius: 10px; margin-top: 8px; }
        .sr-checkbox input { margin-top: 2px; min-height: auto; min-width: auto; width: 16px; height: 16px; }
        .sr-checkbox label { font-size: 13.5px; color: var(--eb-ink-soft, #475569); line-height: 1.55; margin: 0; font-weight: 500; }

        /* ── BUTTONS ───────────────────────────────────────── */
        .sr-actions {
            display: flex; justify-content: space-between; gap: 12px;
            margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--eb-line, #e2e8f0);
        }
        .sr-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px 28px; border-radius: 12px; font-weight: 600; font-size: 14.5px;
            cursor: pointer; border: 0; transition: transform .2s, box-shadow .2s, filter .2s;
            font-family: inherit;
        }
        .sr-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .sr-btn-primary {
            background: linear-gradient(135deg, var(--eb-brand-light, #1fb1a6) 0%, var(--eb-brand, #0f8a82) 55%, var(--eb-brand-dark, #0a6f68) 100%);
            color: #fff;
            box-shadow: 0 6px 16px rgba(15,138,130,0.28);
        }
        .sr-btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(15,138,130,0.35); }
        .sr-btn-ghost {
            background: #fff; color: var(--eb-ink, #0f172a);
            border: 1.5px solid var(--eb-line, #e2e8f0);
        }
        .sr-btn-ghost:hover:not(:disabled) { border-color: var(--eb-brand, #0f8a82); color: var(--eb-brand-dark, #0a6f68); }

        /* ── STEP TOGGLE ───────────────────────────────────── */
        .sr-step-content { display: none; }
        .sr-step-content.active { display: block; animation: srFade .35s ease; }
        @keyframes srFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* ── SUMMARY (step 4) ──────────────────────────────── */
        .sr-summary { display: grid; gap: 16px; }
        .sr-summary-block {
            border: 1px solid var(--eb-line, #e2e8f0); border-radius: 14px;
            padding: 18px 20px; background: #fafbfc;
        }
        .sr-summary-block h3 {
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--eb-brand-dark, #0a6f68); margin: 0 0 12px;
        }
        .sr-summary-row { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 13.5px; }
        .sr-summary-row dt { color: var(--eb-muted, #94a3b8); }
        .sr-summary-row dd { margin: 0; color: var(--eb-ink, #0f172a); font-weight: 500; text-align: right; word-break: break-word; }

        /* ── SUCCESS ───────────────────────────────────────── */
        .sr-success {
            text-align: center; padding: clamp(28px, 6vw, 60px) 20px;
        }
        .sr-success-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 72px; height: 72px; border-radius: 50%;
            background: linear-gradient(135deg, #1fb1a6, #0a6f68);
            color: #fff; margin-bottom: 18px;
            box-shadow: 0 12px 32px rgba(15,138,130,0.35);
        }
        .sr-success h2 { font-size: 24px; font-weight: 800; margin: 0 0 10px; letter-spacing: -0.02em; }
        .sr-success p { color: var(--eb-ink-soft, #475569); font-size: 15px; max-width: 520px; margin: 0 auto 20px; line-height: 1.6; }
        .sr-success .sr-tracking-link {
            display: inline-block; margin-top: 20px;
            padding: 14px 28px; border-radius: 12px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            font-weight: 600; font-size: 14.5px;
            word-break: break-all;
        }

        /* ── ALERT ─────────────────────────────────────────── */
        .sr-alert {
            padding: 12px 16px; border-radius: 10px; margin-bottom: 16px;
            font-size: 13.5px; line-height: 1.55;
        }
        .sr-alert.err  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .sr-alert.info { background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; }
        .sr-alert.warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .sr-alert a.sr-btn { color: #fff !important; }
        .sr-alert a.sr-btn.sr-btn-ghost { color: var(--eb-ink, #0f172a) !important; }

        .sr-loading { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: srSpin .7s linear infinite; }
        @keyframes srSpin { to { transform: rotate(360deg); } }

        /* ── STRENGTH METER ────────────────────────────────── */
        .sr-strength { margin-top: 6px; }
        .sr-strength-bar {
            height: 4px; border-radius: 4px;
            background: #e2e8f0; overflow: hidden;
            margin-bottom: 4px;
        }
        .sr-strength-fill {
            height: 100%; width: 0%;
            transition: width .25s var(--eb-ease), background-color .25s var(--eb-ease);
            background: #e2e8f0;
        }
        .sr-strength-fill.weak   { background: #dc2626; width: 30%; }
        .sr-strength-fill.medium { background: #f59e0b; width: 65%; }
        .sr-strength-fill.strong { background: #059669; width: 100%; }
        .sr-strength-label { font-size: 12px; color: var(--eb-muted, #94a3b8); font-weight: 500; }
        .sr-strength-label.weak   { color: #dc2626; }
        .sr-strength-label.medium { color: #d97706; }
        .sr-strength-label.strong { color: #059669; }
    </style>
</head>
<body>

<div class="sr-wrap">

    <div class="sr-header">
        <a href="{{ url('/seller') }}" class="sr-logo">
            ebaemy<span class="sr-logo-badge">Sellers</span>
        </a>
        <h1>Crear solicitud de vendedor</h1>
        <p>Completa los datos en 4 pasos. Revisaremos tu solicitud y te notificaremos por correo.</p>
    </div>

    {{-- Stepper --}}
    <div class="sr-stepper" id="srStepper">
        <div class="sr-step active" data-step="1"><span class="sr-step-num">1</span><span class="sr-step-label">Empresa</span></div>
        <div class="sr-step-line"></div>
        <div class="sr-step" data-step="2"><span class="sr-step-num">2</span><span class="sr-step-label">Responsable</span></div>
        <div class="sr-step-line"></div>
        <div class="sr-step" data-step="3"><span class="sr-step-num">3</span><span class="sr-step-label">Tienda</span></div>
        <div class="sr-step-line"></div>
        <div class="sr-step" data-step="4"><span class="sr-step-num">4</span><span class="sr-step-label">Confirmar</span></div>
    </div>

    <div class="sr-card">
        <div id="srErrorBox"></div>

        <form id="srForm" novalidate>

            {{-- ═════════════════ PASO 1 · EMPRESA ═════════════════ --}}
            <div class="sr-step-content active" data-step="1">
                <div class="sr-card-header">
                    <h2>Datos de tu empresa</h2>
                    <p>Al ingresar tu RUC, intentaremos autocompletar razón social y dirección.</p>
                </div>

                <div class="sr-row full">
                    <div class="sr-field">
                        <label>RUC <span style="color:#dc2626">*</span></label>
                        <input type="text" name="ruc" id="srRuc" maxlength="11" inputmode="numeric" autocomplete="off" placeholder="20123456789">
                        <div class="sr-hint" id="srRucHint">11 dígitos. Empieza con 10, 15, 17 o 20.</div>
                    </div>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>Razón social <span style="color:#dc2626">*</span></label>
                        <input type="text" name="business_name" id="srBusinessName" autocomplete="organization" maxlength="255">
                    </div>
                    <div class="sr-field">
                        <label>Nombre comercial <span class="sr-optional">(opcional)</span></label>
                        <input type="text" name="trade_name" maxlength="255">
                    </div>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>Rubro <span class="sr-optional">(opcional)</span></label>
                        <select name="category_id">
                            <option value="">Selecciona…</option>
                            <option value="1">Moda y accesorios</option>
                            <option value="2">Electrónica</option>
                            <option value="3">Hogar y decoración</option>
                            <option value="4">Alimentos y bebidas</option>
                            <option value="5">Salud y belleza</option>
                            <option value="6">Deportes</option>
                            <option value="7">Servicios</option>
                            <option value="99">Otros</option>
                        </select>
                    </div>
                    <div class="sr-field">
                        <label>Dirección fiscal <span class="sr-optional">(opcional)</span></label>
                        <input type="text" name="fiscal_address" id="srFiscalAddress" maxlength="500">
                    </div>
                </div>
            </div>

            {{-- ═════════════════ PASO 2 · RESPONSABLE ═════════════════ --}}
            <div class="sr-step-content" data-step="2">
                <div class="sr-card-header">
                    <h2>Datos del responsable</h2>
                    <p>Persona de contacto principal para la aprobación y gestión de la cuenta.</p>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>Nombre completo <span style="color:#dc2626">*</span></label>
                        <input type="text" name="legal_representative_name" autocomplete="name" maxlength="180">
                    </div>
                    <div class="sr-field">
                        <label>DNI <span style="color:#dc2626">*</span></label>
                        <input type="text" name="legal_representative_dni" maxlength="8" inputmode="numeric" autocomplete="off">
                    </div>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>Cargo <span class="sr-optional">(opcional)</span></label>
                        <input type="text" name="legal_representative_position" maxlength="100" placeholder="Gerente general, Propietario…">
                    </div>
                    <div class="sr-field">
                        <label>Correo electrónico <span style="color:#dc2626">*</span></label>
                        <input type="email" name="email" autocomplete="email" maxlength="180">
                    </div>
                </div>

                <div class="sr-row full">
                    <div class="sr-field">
                        <label>Celular / WhatsApp <span style="color:#dc2626">*</span></label>
                        <input type="tel" name="phone" autocomplete="tel" maxlength="30" placeholder="+51 999 999 999">
                    </div>
                </div>
            </div>

            {{-- ═════════════════ PASO 3 · TIENDA Y ACCESO ═════════════════ --}}
            <div class="sr-step-content" data-step="3">
                <div class="sr-card-header">
                    <h2>Tu tienda y acceso</h2>
                    <p>Elige el subdominio de tu tienda y la contraseña para tu panel.</p>
                </div>

                <div class="sr-row full">
                    <div class="sr-field">
                        <label>Subdominio deseado <span style="color:#dc2626">*</span></label>
                        <div class="sr-subdomain-wrap">
                            <input type="text" name="requested_subdomain" id="srSubdomain" maxlength="60" autocomplete="off" placeholder="mitienda">
                            <div class="sr-subdomain-suffix">.{{ config('tenant.app_url_base') }}</div>
                        </div>
                        <div class="sr-hint" id="srSubdomainHint">Solo letras minúsculas, números y guiones. 3–60 caracteres.</div>
                    </div>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>Nombre de tienda <span class="sr-optional">(opcional)</span></label>
                        <input type="text" name="store_name" maxlength="180">
                    </div>
                    <div class="sr-field">
                        <label>Descripción <span class="sr-optional">(opcional)</span></label>
                        <input type="text" name="store_description" maxlength="2000">
                    </div>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>Contraseña <span style="color:#dc2626">*</span></label>
                        <input type="password" name="password" id="srPassword" autocomplete="new-password" minlength="8">
                        <div class="sr-strength" id="srStrengthWrap" aria-hidden="true">
                            <div class="sr-strength-bar"><div class="sr-strength-fill" id="srStrengthFill"></div></div>
                            <div class="sr-strength-label" id="srStrengthLabel">—</div>
                        </div>
                        <div class="sr-hint">Mínimo 8 caracteres, con mayúscula, minúscula y número.</div>
                    </div>
                    <div class="sr-field">
                        <label>Confirmar contraseña <span style="color:#dc2626">*</span></label>
                        <input type="password" name="password_confirmation" id="srPasswordConfirm" autocomplete="new-password" minlength="8">
                        <div class="sr-hint" id="srMatchHint">—</div>
                    </div>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>Facebook <span class="sr-optional">(opcional)</span></label>
                        <input type="url" name="facebook_url" maxlength="500" placeholder="https://facebook.com/...">
                    </div>
                    <div class="sr-field">
                        <label>Instagram <span class="sr-optional">(opcional)</span></label>
                        <input type="url" name="instagram_url" maxlength="500" placeholder="https://instagram.com/...">
                    </div>
                </div>

                <div class="sr-row">
                    <div class="sr-field">
                        <label>TikTok <span class="sr-optional">(opcional)</span></label>
                        <input type="url" name="tiktok_url" maxlength="500">
                    </div>
                    <div class="sr-field">
                        <label>Página web <span class="sr-optional">(opcional)</span></label>
                        <input type="url" name="website_url" maxlength="500">
                    </div>
                </div>
            </div>

            {{-- ═════════════════ PASO 4 · CONFIRMAR ═════════════════ --}}
            <div class="sr-step-content" data-step="4">
                <div class="sr-card-header">
                    <h2>Revisa y confirma</h2>
                    <p>Verifica que los datos estén correctos antes de enviar tu solicitud.</p>
                </div>

                <div class="sr-summary" id="srSummary"></div>

                <div class="sr-checkbox">
                    <input type="checkbox" name="terms_accepted" id="srTerms" value="1">
                    <label for="srTerms">
                        Acepto los términos y condiciones del marketplace. Entiendo que mi solicitud será revisada
                        manualmente y que <strong>la tienda se creará solo después de la aprobación</strong>.
                    </label>
                </div>
            </div>

            <div class="sr-actions">
                <button type="button" class="sr-btn sr-btn-ghost" id="srBtnBack" style="visibility:hidden;">← Volver</button>
                <button type="button" class="sr-btn sr-btn-primary" id="srBtnNext">
                    Continuar →
                </button>
            </div>
        </form>

        {{-- Éxito (se muestra tras submit OK) --}}
        <div id="srSuccess" class="sr-success" style="display:none;">
            <div class="sr-success-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 9 18l-5-5"/></svg>
            </div>
            <h2>¡Solicitud registrada!</h2>
            <p>
                Nuestro equipo revisará tus datos y te notificaremos por correo o WhatsApp cuando tu tienda sea aprobada.
                Normalmente toma entre 24 y 48 horas hábiles.
            </p>
            <p style="font-size:13.5px; color:var(--eb-muted);">
                Puedes consultar el estado de tu solicitud en cualquier momento en este link
                (te lo enviamos también al correo):
            </p>
            <a href="#" class="sr-tracking-link" id="srTrackingLink" target="_blank">—</a>
        </div>
    </div>
</div>

<script>
const SR_CSRF = document.querySelector('meta[name="csrf-token"]').content;
const SR_STEPS = 4;
let srStep = 1;

// ────────────────────────────────────────────────────────
//  Navegación entre steps
// ────────────────────────────────────────────────────────
function srShowStep(n) {
    srStep = Math.max(1, Math.min(SR_STEPS, n));
    document.querySelectorAll('.sr-step-content').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.step) === srStep);
    });
    document.querySelectorAll('#srStepper .sr-step').forEach(el => {
        const stepNum = parseInt(el.dataset.step);
        el.classList.toggle('active', stepNum === srStep);
        el.classList.toggle('done', stepNum < srStep);
    });
    document.getElementById('srBtnBack').style.visibility = srStep === 1 ? 'hidden' : 'visible';
    const btnNext = document.getElementById('srBtnNext');
    btnNext.textContent = srStep === SR_STEPS ? 'Enviar solicitud' : 'Continuar →';
    window.scrollTo({ top: 0, behavior: 'smooth' });

    if (srStep === SR_STEPS) srRenderSummary();
}

// ────────────────────────────────────────────────────────
//  Validación por paso (client-side mínima)
// ────────────────────────────────────────────────────────
function srValidateStep(n) {
    srClearErrors();
    const form = document.getElementById('srForm');
    const required = {
        1: ['ruc', 'business_name'],
        2: ['legal_representative_name', 'legal_representative_dni', 'email', 'phone'],
        3: ['requested_subdomain', 'password', 'password_confirmation'],
        4: [],
    }[n] || [];

    let valid = true;
    for (const name of required) {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) continue;
        if (!el.value || !el.value.trim()) {
            el.classList.add('is-invalid');
            valid = false;
        }
    }

    // Validaciones específicas
    if (n === 1) {
        const ruc = form.querySelector('[name="ruc"]').value.trim();
        if (!/^(10|15|17|20)\d{9}$/.test(ruc)) {
            form.querySelector('[name="ruc"]').classList.add('is-invalid');
            srShowError('El RUC debe tener 11 dígitos y empezar con 10, 15, 17 o 20.');
            valid = false;
        } else if (srRucBlocked) {
            form.querySelector('[name="ruc"]').classList.add('is-invalid');
            // El mensaje visual ya está en el error box de srSetRucBlocked
            valid = false;
        }
    }
    if (n === 2) {
        const dni = form.querySelector('[name="legal_representative_dni"]').value.trim();
        if (dni && !/^\d{8}$/.test(dni)) {
            form.querySelector('[name="legal_representative_dni"]').classList.add('is-invalid');
            srShowError('El DNI debe tener 8 dígitos.');
            valid = false;
        }
        const email = form.querySelector('[name="email"]').value.trim();
        if (email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
            form.querySelector('[name="email"]').classList.add('is-invalid');
            srShowError('Correo electrónico inválido.');
            valid = false;
        }
    }
    if (n === 3) {
        const sub = form.querySelector('[name="requested_subdomain"]').value.trim();
        if (!/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/.test(sub)) {
            form.querySelector('[name="requested_subdomain"]').classList.add('is-invalid');
            srShowError('Subdominio inválido. Solo letras minúsculas, números y guiones (no al inicio/final).');
            valid = false;
        }
        const pwd  = form.querySelector('[name="password"]').value;
        const pwd2 = form.querySelector('[name="password_confirmation"]').value;
        if (pwd && pwd.length < 8) {
            form.querySelector('[name="password"]').classList.add('is-invalid');
            srShowError('La contraseña debe tener al menos 8 caracteres.');
            valid = false;
        } else if (pwd && !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/.test(pwd)) {
            form.querySelector('[name="password"]').classList.add('is-invalid');
            srShowError('La contraseña debe incluir al menos una minúscula, una mayúscula y un número.');
            valid = false;
        }
        if (pwd !== pwd2) {
            form.querySelector('[name="password_confirmation"]').classList.add('is-invalid');
            srShowError('La confirmación de contraseña no coincide.');
            valid = false;
        }
    }
    if (n === 4) {
        if (!form.querySelector('[name="terms_accepted"]').checked) {
            srShowError('Debes aceptar los términos para enviar tu solicitud.');
            valid = false;
        }
    }
    return valid;
}

function srShowError(msg) {
    const box = document.getElementById('srErrorBox');
    box.innerHTML = `<div class="sr-alert err">${srEscape(msg)}</div>`;
}
function srClearErrors() {
    document.getElementById('srErrorBox').innerHTML = '';
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}
function srEscape(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// ────────────────────────────────────────────────────────
//  RUC auto-validate (debounced)
// ────────────────────────────────────────────────────────
let srRucTimer = null;
document.getElementById('srRuc').addEventListener('input', e => {
    const v = e.target.value.replace(/\D/g, '').slice(0, 11);
    e.target.value = v;
    const hint = document.getElementById('srRucHint');
    hint.className = 'sr-hint';
    hint.textContent = '11 dígitos. Empieza con 10, 15, 17 o 20.';
    clearTimeout(srRucTimer);
    if (v.length !== 11) return;
    hint.textContent = 'Validando RUC…';
    srRucTimer = setTimeout(() => srValidateRuc(v), 400);
});

async function srValidateRuc(ruc) {
    const hint = document.getElementById('srRucHint');
    srSetRucBlocked(false); // reset
    try {
        const res = await fetch(`/seller/register/validate-ruc?ruc=${encodeURIComponent(ruc)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (!data.valid) {
            hint.className = 'sr-hint err';
            hint.textContent = data.error || 'RUC inválido.';
            return;
        }

        // Si el RUC ya está asociado a un tenant o solicitud activa,
        // bloqueamos el flujo y mostramos el mensaje + CTA correspondiente.
        if (data.already_registered) {
            srSetRucBlocked(true, data.already_registered);
            return;
        }

        if (data.business_name) document.getElementById('srBusinessName').value = data.business_name;
        if (data.fiscal_address) document.getElementById('srFiscalAddress').value = data.fiscal_address;
        if (data.requires_manual_review) {
            hint.className = 'sr-hint warn';
            hint.textContent = 'RUC válido, pero nuestra verificación automática no pudo confirmarlo. Continuaremos manualmente.';
        } else {
            hint.className = 'sr-hint ok';
            hint.textContent = `✓ ${data.status || 'OK'} / ${data.condition || '—'} — datos precargados.`;
        }
    } catch (e) {
        hint.className = 'sr-hint warn';
        hint.textContent = 'No pudimos verificar el RUC online, pero puedes continuar.';
    }
}

/**
 * Cuando el RUC ya está registrado, el AJAX devuelve type + subtype:
 *
 *   type='tenant' subtype='active_seller'
 *     → La empresa ya tiene tienda virtual. CTA: iniciar sesión.
 *
 *   type='tenant' subtype='needs_activation'
 *     → Es cliente de ebaemy (facturación/POS) pero SIN tienda virtual.
 *       CTA: contactar soporte para solicitar activación.
 *
 *   type='application' subtype='active_application'
 *     → Ya hay una solicitud de seller en revisión. CTA: consultar correo.
 *
 * Bloqueamos el botón "Continuar" hasta que el usuario cambie el RUC.
 */
const SR_SUPPORT_EMAIL = @json(config('services.support.email'));
const SR_SUPPORT_WHATSAPP = @json(config('services.support.whatsapp'));

let srRucBlocked = false;

function srSetRucBlocked(blocked, info = null) {
    srRucBlocked = blocked;
    const btn = document.getElementById('srBtnNext');
    if (srStep === 1) {
        btn.disabled = blocked;
        btn.style.opacity = blocked ? '0.5' : '';
    }

    const errBox = document.getElementById('srErrorBox');
    const existing = errBox.querySelector('[data-role="already-registered"]');
    if (existing) existing.remove();

    if (!blocked || !info) return;

    const config = srAlreadyRegisteredConfig(info);
    const html = `
        <div class="sr-alert ${config.cls}" data-role="already-registered">
            <div style="font-weight:700; margin-bottom:4px;">${config.icon} ${config.title}</div>
            <div>${srEscape(info.message)}</div>
            ${config.cta ? `<div style="margin-top:10px;">${config.cta}</div>` : ''}
        </div>
    `;
    errBox.insertAdjacentHTML('afterbegin', html);
}

function srAlreadyRegisteredConfig(info) {
    const subtype = info.subtype || info.type;

    // Tenant que es cliente pero NO tiene marketplace activo:
    // ofrecemos contacto con soporte para activar la tienda virtual.
    if (info.type === 'tenant' && info.subtype === 'needs_activation') {
        const ruc = document.getElementById('srRuc')?.value || '';
        const subject = encodeURIComponent(`Solicitud de activación de tienda virtual — RUC ${ruc}`);
        const body = encodeURIComponent(
            `Hola,\n\nSoy cliente de {{ config('app.name', 'ebaemy') }} y quiero activar mi tienda virtual para vender en el marketplace.\n\n`
            + `RUC: ${ruc}\n`
            + `Empresa: (completar)\n`
            + `Responsable: (completar)\n`
            + `Teléfono: (completar)\n\n`
            + `Gracias.`
        );
        const email = SR_SUPPORT_EMAIL || 'soporte@ebaemy.com';
        let ctaHtml = `<a href="mailto:${email}?subject=${subject}&body=${body}" class="sr-btn sr-btn-primary" style="padding:10px 18px; font-size:13px; display:inline-flex; text-decoration:none;">✉️ Solicitar activación por correo</a>`;

        if (SR_SUPPORT_WHATSAPP) {
            const wa = encodeURIComponent(`Hola, soy cliente de ebaemy y quiero activar mi tienda virtual. RUC: ${ruc}`);
            ctaHtml += ` <a href="https://wa.me/${SR_SUPPORT_WHATSAPP}?text=${wa}" target="_blank" class="sr-btn sr-btn-ghost" style="padding:10px 18px; font-size:13px; display:inline-flex; text-decoration:none;">💬 WhatsApp</a>`;
        }

        return {
            icon:  '🛍️',
            title: 'Eres cliente, pero te falta activar tu tienda',
            cls:   'info',
            cta:   ctaHtml,
        };
    }

    // Tenant ya con marketplace activo
    if (info.type === 'tenant') {
        return {
            icon:  '🔒',
            title: 'Esta empresa ya tiene una tienda',
            cls:   'err',
            cta:   null,
        };
    }

    // Application activa en pipeline
    return {
        icon:  '⏳',
        title: 'Ya tienes una solicitud en proceso',
        cls:   'warn',
        cta:   null,
    };
}

// ────────────────────────────────────────────────────────
//  Subdomain check (debounced)
// ────────────────────────────────────────────────────────
let srSubTimer = null;
document.getElementById('srSubdomain').addEventListener('input', e => {
    const v = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
    e.target.value = v;
    const hint = document.getElementById('srSubdomainHint');
    hint.className = 'sr-hint';
    hint.textContent = 'Solo letras minúsculas, números y guiones. 3–60 caracteres.';
    clearTimeout(srSubTimer);
    if (v.length < 3) return;
    hint.textContent = 'Verificando disponibilidad…';
    srSubTimer = setTimeout(() => srCheckSubdomain(v), 400);
});

async function srCheckSubdomain(sub) {
    const hint = document.getElementById('srSubdomainHint');
    try {
        const res = await fetch(`/seller/register/check-subdomain?sub=${encodeURIComponent(sub)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.available) {
            hint.className = 'sr-hint ok';
            hint.textContent = `✓ ${sub}.{{ config('tenant.app_url_base') }} — disponible.`;
        } else {
            hint.className = 'sr-hint err';
            hint.textContent = data.message || 'No disponible.';
        }
    } catch (e) {
        hint.className = 'sr-hint warn';
        hint.textContent = 'No se pudo verificar. Puedes continuar y lo revisaremos al enviar.';
    }
}

// ────────────────────────────────────────────────────────
//  Summary render
// ────────────────────────────────────────────────────────
function srRenderSummary() {
    const form = document.getElementById('srForm');
    const v = name => form.querySelector(`[name="${name}"]`)?.value || '—';
    const html = `
        <div class="sr-summary-block">
            <h3>Empresa</h3>
            <dl>
                <div class="sr-summary-row"><dt>RUC</dt><dd>${srEscape(v('ruc'))}</dd></div>
                <div class="sr-summary-row"><dt>Razón social</dt><dd>${srEscape(v('business_name'))}</dd></div>
                <div class="sr-summary-row"><dt>Nombre comercial</dt><dd>${srEscape(v('trade_name'))}</dd></div>
                <div class="sr-summary-row"><dt>Dirección fiscal</dt><dd>${srEscape(v('fiscal_address'))}</dd></div>
            </dl>
        </div>
        <div class="sr-summary-block">
            <h3>Responsable</h3>
            <dl>
                <div class="sr-summary-row"><dt>Nombre</dt><dd>${srEscape(v('legal_representative_name'))}</dd></div>
                <div class="sr-summary-row"><dt>DNI</dt><dd>${srEscape(v('legal_representative_dni'))}</dd></div>
                <div class="sr-summary-row"><dt>Cargo</dt><dd>${srEscape(v('legal_representative_position'))}</dd></div>
                <div class="sr-summary-row"><dt>Email</dt><dd>${srEscape(v('email'))}</dd></div>
                <div class="sr-summary-row"><dt>Teléfono</dt><dd>${srEscape(v('phone'))}</dd></div>
            </dl>
        </div>
        <div class="sr-summary-block">
            <h3>Tienda</h3>
            <dl>
                <div class="sr-summary-row"><dt>Subdominio</dt><dd><strong>${srEscape(v('requested_subdomain'))}.{{ config('tenant.app_url_base') }}</strong></dd></div>
                <div class="sr-summary-row"><dt>Nombre tienda</dt><dd>${srEscape(v('store_name'))}</dd></div>
                <div class="sr-summary-row"><dt>Descripción</dt><dd>${srEscape(v('store_description'))}</dd></div>
            </dl>
        </div>`;
    document.getElementById('srSummary').innerHTML = html;
}

// ────────────────────────────────────────────────────────
//  Submit final
// ────────────────────────────────────────────────────────
async function srSubmit() {
    if (!srValidateStep(4)) return;

    const btn = document.getElementById('srBtnNext');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="sr-loading"></span> Enviando…';

    const form = document.getElementById('srForm');
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.terms_accepted = form.querySelector('[name="terms_accepted"]').checked ? 1 : 0;

    try {
        const res = await fetch('/seller/register', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': SR_CSRF,
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            let msg = data.message || 'Error al enviar la solicitud.';
            if (data.errors) {
                msg = Object.values(data.errors).flat().join(' ');
            }
            srShowError(msg);
            btn.disabled = false;
            btn.innerHTML = original;
            return;
        }

        // Éxito
        document.getElementById('srForm').style.display = 'none';
        document.getElementById('srStepper').style.display = 'none';
        document.getElementById('srSuccess').style.display = 'block';
        if (data.tracking_url) {
            const link = document.getElementById('srTrackingLink');
            link.href = data.tracking_url;
            link.textContent = data.tracking_url;
        }
    } catch (e) {
        srShowError('Error de red: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// ────────────────────────────────────────────────────────
//  Bindings
// ────────────────────────────────────────────────────────
document.getElementById('srBtnBack').addEventListener('click', () => srShowStep(srStep - 1));
document.getElementById('srBtnNext').addEventListener('click', () => {
    if (!srValidateStep(srStep)) return;
    if (srStep === SR_STEPS) srSubmit();
    else srShowStep(srStep + 1);
});

document.getElementById('srForm').addEventListener('submit', e => e.preventDefault());

// Stepper clicks (solo hacia atrás)
document.querySelectorAll('#srStepper .sr-step').forEach(el => {
    el.addEventListener('click', () => {
        const target = parseInt(el.dataset.step);
        if (target < srStep) srShowStep(target);
    });
});

// ────────────────────────────────────────────────────────
//  Password strength meter + match indicator
// ────────────────────────────────────────────────────────
function srScorePassword(pwd) {
    if (!pwd) return { score: 0, label: '—', cls: '' };
    let score = 0;
    if (pwd.length >= 8)    score++;
    if (/[a-z]/.test(pwd))  score++;
    if (/[A-Z]/.test(pwd))  score++;
    if (/\d/.test(pwd))     score++;
    if (/[^A-Za-z0-9]/.test(pwd) || pwd.length >= 12) score++;

    if (score <= 2) return { score, label: 'Débil',   cls: 'weak'   };
    if (score <= 3) return { score, label: 'Media',   cls: 'medium' };
    return                 { score, label: 'Fuerte',  cls: 'strong' };
}

function srUpdateStrength() {
    const pwd = document.getElementById('srPassword').value;
    const info = srScorePassword(pwd);
    const fill  = document.getElementById('srStrengthFill');
    const label = document.getElementById('srStrengthLabel');
    fill.className  = 'sr-strength-fill '  + info.cls;
    label.className = 'sr-strength-label ' + info.cls;
    label.textContent = pwd ? info.label : '—';
    srUpdateMatch();
}

function srUpdateMatch() {
    const pwd  = document.getElementById('srPassword').value;
    const pwd2 = document.getElementById('srPasswordConfirm').value;
    const hint = document.getElementById('srMatchHint');
    if (!pwd2) {
        hint.className = 'sr-hint'; hint.textContent = '—';
        return;
    }
    if (pwd === pwd2) {
        hint.className = 'sr-hint ok'; hint.textContent = '✓ Las contraseñas coinciden.';
    } else {
        hint.className = 'sr-hint err'; hint.textContent = 'Las contraseñas no coinciden.';
    }
}

document.getElementById('srPassword').addEventListener('input', srUpdateStrength);
document.getElementById('srPasswordConfirm').addEventListener('input', srUpdateMatch);
</script>
</body>
</html>
