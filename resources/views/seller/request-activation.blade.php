<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Solicitar activación de tienda virtual — {{ config('app.name', 'ebaemy') }}</title>

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
                #fafbfc;
            min-height: 100vh;
            letter-spacing: -0.01em;
        }
        a { text-decoration: none; color: inherit; }
        .ra-wrap { max-width: 680px; margin: 0 auto; padding: 40px clamp(16px, 4vw, 32px); }
        .ra-header { text-align: center; margin-bottom: 28px; }
        .ra-logo {
            font-weight: 800; font-size: 20px; letter-spacing: -0.02em;
            display: inline-block;
        }
        .ra-logo-badge {
            display: inline-block; margin-left: 6px; padding: 2px 10px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; vertical-align: 3px;
        }
        .ra-header h1 {
            font-size: clamp(24px, 3vw, 30px); font-weight: 800; margin: 16px 0 6px;
            letter-spacing: -0.02em;
        }
        .ra-header p { color: var(--eb-ink-soft, #475569); font-size: 14.5px; margin: 0; }
        .ra-card {
            background: #fff; border-radius: 18px;
            border: 1px solid var(--eb-line, #e2e8f0);
            box-shadow: 0 20px 56px -20px rgba(15,23,42,0.12);
            padding: clamp(24px, 4vw, 36px);
        }
        .ra-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 16px; }
        .ra-row.full { grid-template-columns: 1fr; }
        @media (max-width: 600px) { .ra-row { grid-template-columns: 1fr; } }
        .ra-field label { display:block; font-size:13px; font-weight:600; color:var(--eb-ink); margin-bottom:6px; }
        .ra-field label .opt { color: var(--eb-muted, #94a3b8); font-weight: 500; }
        .ra-field input, .ra-field textarea {
            width: 100%; min-height: 48px; padding: 12px 14px;
            border: 1.5px solid var(--eb-line, #e2e8f0); border-radius: 12px;
            background: #fff; font-size: 14.5px; font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .ra-field textarea { min-height: 90px; resize: vertical; }
        .ra-field input:focus, .ra-field textarea:focus {
            outline: none; border-color: var(--eb-brand, #0f8a82);
            box-shadow: 0 0 0 4px rgba(15,138,130,0.12);
        }
        .ra-field input.is-invalid, .ra-field textarea.is-invalid {
            border-color: #ef4444; background: #fef2f2;
        }
        .ra-field .hint { font-size: 12px; color: var(--eb-muted); margin-top: 4px; }
        .ra-field input[readonly] { background: #f8fafc; color: var(--eb-ink-soft); }
        .ra-info-box {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px;
            padding: 14px 16px; font-size: 13.5px; color: #1e3a8a; line-height: 1.55;
            margin-bottom: 20px;
        }
        .ra-checkbox {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 12px; background: #f8fafc; border-radius: 10px; margin-top: 8px;
        }
        .ra-checkbox input { margin-top: 2px; width: 16px; height: 16px; }
        .ra-checkbox label { font-size: 13.5px; color: var(--eb-ink-soft); line-height: 1.55; font-weight: 500; }
        .ra-actions {
            display: flex; justify-content: space-between; gap: 12px;
            margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--eb-line);
        }
        .ra-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px 28px; border-radius: 12px; font-weight: 600; font-size: 14.5px;
            cursor: pointer; border: 0; font-family: inherit;
            transition: transform .2s, box-shadow .2s;
        }
        .ra-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .ra-btn-primary {
            background: linear-gradient(135deg, var(--eb-brand-light, #1fb1a6) 0%, var(--eb-brand, #0f8a82) 55%, var(--eb-brand-dark, #0a6f68) 100%);
            color: #fff;
            box-shadow: 0 6px 16px rgba(15,138,130,0.28);
        }
        .ra-btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(15,138,130,0.35); }
        .ra-btn-ghost {
            background: #fff; color: var(--eb-ink);
            border: 1.5px solid var(--eb-line);
        }
        .ra-alert {
            padding: 12px 16px; border-radius: 10px; margin-bottom: 16px;
            font-size: 13.5px; line-height: 1.55;
        }
        .ra-alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .ra-success {
            text-align: center; padding: clamp(28px, 6vw, 48px) 20px;
        }
        .ra-success-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 72px; height: 72px; border-radius: 50%;
            background: linear-gradient(135deg, #1fb1a6, #0a6f68);
            color: #fff; margin-bottom: 18px;
            box-shadow: 0 12px 32px rgba(15,138,130,0.35);
        }
        .ra-success h2 { font-size: 22px; font-weight: 800; margin: 0 0 10px; letter-spacing: -0.02em; }
        .ra-success p { color: var(--eb-ink-soft); font-size: 14.5px; max-width: 480px; margin: 0 auto 16px; line-height: 1.6; }
        .ra-tracking-link {
            display: inline-block; margin-top: 16px;
            padding: 12px 20px; border-radius: 10px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark);
            font-weight: 600; font-size: 13.5px; word-break: break-all;
        }
        .loading { display:inline-block; width:14px; height:14px; border:2px solid rgba(255,255,255,0.4); border-top-color:#fff; border-radius:50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="ra-wrap">
    <div class="ra-header">
        <a href="{{ url('/seller') }}" class="ra-logo">
            {{ config('app.name', 'ebaemy') }}<span class="ra-logo-badge">Sellers</span>
        </a>
        <h1>Activar mi tienda virtual</h1>
        <p>Como ya eres cliente de {{ config('app.name', 'ebaemy') }}, solicita la activación de tu tienda virtual para vender en el marketplace.</p>
    </div>

    <div class="ra-card">
        <div id="raErrorBox"></div>

        <div class="ra-info-box">
            <strong>¿Cómo funciona?</strong><br>
            Completa este formulario con tus datos de contacto. Nuestro equipo verificará que eres el dueño del tenant y activará tu tienda. Te notificaremos por correo cuando esté lista — iniciarás sesión con <strong>las mismas credenciales que ya usas</strong>.
        </div>

        <form id="raForm" novalidate>
            <div class="ra-row">
                <div class="ra-field">
                    <label>RUC <span style="color:#dc2626">*</span></label>
                    <input type="text" name="ruc" id="raRuc"
                           maxlength="11" inputmode="numeric" autocomplete="off"
                           value="{{ $prefill['ruc'] ?? '' }}"
                           {{ !empty($prefill['ruc']) ? 'readonly' : '' }}>
                    @if(!empty($prefill['business_name']))
                        <div class="hint">{{ $prefill['business_name'] }}</div>
                    @endif
                </div>
                <div class="ra-field">
                    <label>Nombre del responsable <span style="color:#dc2626">*</span></label>
                    <input type="text" name="legal_representative_name" autocomplete="name" maxlength="180">
                </div>
            </div>

            <div class="ra-row">
                <div class="ra-field">
                    <label>DNI del responsable <span class="opt">(opcional)</span></label>
                    <input type="text" name="legal_representative_dni" maxlength="8" inputmode="numeric">
                </div>
                <div class="ra-field">
                    <label>Cargo <span class="opt">(opcional)</span></label>
                    <input type="text" name="legal_representative_position" maxlength="100" placeholder="Gerente, Propietario…">
                </div>
            </div>

            <div class="ra-row">
                <div class="ra-field">
                    <label>Correo electrónico <span style="color:#dc2626">*</span></label>
                    <input type="email" name="email" autocomplete="email" maxlength="180">
                    @if(!empty($prefill['email_hint']))
                        <div class="hint">Registrado en tu cuenta: <code>{{ $prefill['email_hint'] }}</code></div>
                    @endif
                </div>
                <div class="ra-field">
                    <label>Celular / WhatsApp <span style="color:#dc2626">*</span></label>
                    <input type="tel" name="phone" autocomplete="tel" maxlength="30" placeholder="+51 999 999 999">
                </div>
            </div>

            <div class="ra-row full">
                <div class="ra-field">
                    <label>Motivo o comentario <span class="opt">(opcional)</span></label>
                    <textarea name="activation_reason" maxlength="2000" placeholder="Cuéntanos brevemente por qué quieres activar tu tienda virtual…"></textarea>
                </div>
            </div>

            <div class="ra-checkbox">
                <input type="checkbox" name="terms_accepted" id="raTerms" value="1">
                <label for="raTerms">
                    Acepto que mi solicitud será revisada manualmente y que
                    <strong>mi tienda virtual se activará solo después de la verificación</strong>.
                    Entiendo que al activarse usaré las mismas credenciales que ya tengo.
                </label>
            </div>

            <div class="ra-actions">
                <a href="{{ url('/seller/register') }}" class="ra-btn ra-btn-ghost">← Volver</a>
                <button type="button" class="ra-btn ra-btn-primary" id="raBtnSubmit">
                    Enviar solicitud
                </button>
            </div>
        </form>

        <div id="raSuccess" class="ra-success" style="display:none;">
            <div class="ra-success-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 9 18l-5-5"/></svg>
            </div>
            <h2>¡Solicitud enviada!</h2>
            <p>
                Nuestro equipo revisará tu solicitud y te notificará por correo cuando tu tienda virtual esté activa.
                Normalmente toma entre 24 y 48 horas hábiles.
            </p>
            <p style="font-size:13px; color:var(--eb-muted);">
                Puedes consultar el estado en este link (también te lo enviamos por correo):
            </p>
            <a href="#" class="ra-tracking-link" id="raTrackingLink" target="_blank">—</a>
        </div>
    </div>
</div>

<script>
const RA_CSRF = document.querySelector('meta[name="csrf-token"]').content;

function raShowError(msg) {
    document.getElementById('raErrorBox').innerHTML = `<div class="ra-alert err">${msg.replace(/[<>]/g, c => ({'<':'&lt;','>':'&gt;'}[c]))}</div>`;
}
function raClearError() { document.getElementById('raErrorBox').innerHTML = ''; }

document.getElementById('raBtnSubmit').addEventListener('click', async () => {
    raClearError();
    const form = document.getElementById('raForm');
    const required = ['ruc','legal_representative_name','email','phone'];
    let valid = true;
    for (const n of required) {
        const el = form.querySelector(`[name="${n}"]`);
        if (!el.value || !el.value.trim()) { el.classList.add('is-invalid'); valid = false; }
        else el.classList.remove('is-invalid');
    }
    if (!valid) return raShowError('Completa los campos obligatorios.');
    if (!form.querySelector('[name="terms_accepted"]').checked) return raShowError('Debes aceptar los términos para enviar.');

    const payload = Object.fromEntries(new FormData(form).entries());
    payload.terms_accepted = 1;

    const btn = document.getElementById('raBtnSubmit');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading"></span> Enviando…';

    try {
        const res = await fetch('/seller/request-activation', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': RA_CSRF,
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            raShowError(data.message || 'Error al enviar.');
            btn.disabled = false; btn.innerHTML = original;
            return;
        }
        document.getElementById('raForm').style.display = 'none';
        document.getElementById('raSuccess').style.display = 'block';
        if (data.tracking_url) {
            const link = document.getElementById('raTrackingLink');
            link.href = data.tracking_url;
            link.textContent = data.tracking_url;
        }
    } catch (e) {
        raShowError('Error de red: ' + e.message);
        btn.disabled = false; btn.innerHTML = original;
    }
});
</script>
</body>
</html>
