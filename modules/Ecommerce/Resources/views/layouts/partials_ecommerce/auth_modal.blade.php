{{-- ══════════════════════════════════════════════════════
     MODAL LOGIN GLOBAL — incluir en cada layout del ecommerce
     ══════════════════════════════════════════════════════ --}}
@php $ecCfgModal = \App\Models\Tenant\ConfigurationEcommerce::first(); @endphp

<div id="ec-auth-modal" role="dialog" aria-modal="true" aria-label="Iniciar sesión"
     style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;">
    {{-- Backdrop --}}
    <div id="ec-auth-backdrop"
         style="position:absolute;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);"
         onclick="ecAuthModal.close()"></div>

    {{-- Panel --}}
    <div style="position:relative;z-index:1;display:flex;width:min(900px,95vw);min-height:520px;border-radius:18px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.35);">

        {{-- ── LEFT: formulario ── --}}
        <div style="flex:1;background:#fff;padding:3rem 3.2rem;display:flex;flex-direction:column;justify-content:center;">
            <h2 style="font-size:2rem;font-weight:900;letter-spacing:.08em;color:#1a1a1a;margin:0 0 2rem;text-transform:uppercase;">Iniciar sesión</h2>

            <div id="ec-am-error" style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:1rem;color:#b91c1c;font-size:1.3rem;"></div>

            <form id="ec-am-form" onsubmit="ecAuthModal.submit(event)">
                @csrf
                <label style="font-size:1.1rem;font-weight:700;letter-spacing:.06em;color:#7c6ea0;text-transform:uppercase;display:block;margin-bottom:5px;">Correo electrónico:</label>
                <input id="ec-am-email" type="email" name="email" required placeholder="Enter email"
                       style="width:100%;padding:12px 15px;border:none;border-radius:8px;background:#f3eeff;font-size:1.4rem;margin-bottom:1.4rem;outline:none;color:#333;box-sizing:border-box;">

                <label style="font-size:1.1rem;font-weight:700;letter-spacing:.06em;color:#7c6ea0;text-transform:uppercase;display:block;margin-bottom:5px;">Contraseña:</label>
                <input id="ec-am-pass" type="password" name="password" required placeholder="Enter password"
                       style="width:100%;padding:12px 15px;border:none;border-radius:8px;background:#f3eeff;font-size:1.4rem;margin-bottom:2rem;outline:none;color:#333;box-sizing:border-box;">

                <button id="ec-am-btn" type="submit"
                        style="width:100%;padding:14px;background:linear-gradient(135deg,#6c47d6,#4f8ef7);color:#fff;border:none;border-radius:30px;font-size:1.5rem;font-weight:800;letter-spacing:.08em;cursor:pointer;text-transform:uppercase;transition:opacity .15s;">
                    Ingresar
                </button>
            </form>

            @if($ecCfgModal && $ecCfgModal->google_login_enabled)
            <div style="display:flex;align-items:center;gap:10px;margin:1.4rem 0 1rem;">
                <hr style="flex:1;border:none;border-top:1px solid #e5e7eb;">
                <span style="font-size:1.2rem;color:#aaa;">o continúa con</span>
                <hr style="flex:1;border:none;border-top:1px solid #e5e7eb;">
            </div>
            <a href="{{ route('ecommerce.google.redirect') }}"
               style="display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;border:1.5px solid #e5e7eb;border-radius:8px;background:#fff;color:#333;font-size:1.4rem;font-weight:600;text-decoration:none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                Continuar con Google
            </a>
            @endif

            <div style="margin-top:1.4rem;background:#f3eeff;border-radius:10px;padding:1.4rem;text-align:center;">
                <p style="font-size:1.3rem;font-weight:800;color:#1a1a1a;margin:0 0 .5rem;text-transform:uppercase;letter-spacing:.04em;">¿Olvidaste tu contraseña?</p>
                <p style="font-size:1.25rem;color:#7c6ea0;margin:0;">Ponte en contacto con tu administrador o proveedor para que te genere una nueva clave de acceso.</p>
            </div>
        </div>

        {{-- ── RIGHT: bienvenida ── --}}
        <div style="width:340px;background:linear-gradient(160deg,#5b2d8e,#3a1a6e);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3rem 2rem;text-align:center;position:relative;overflow:hidden;">
            <div style="position:absolute;bottom:-80px;right:-80px;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.07);"></div>
            <div style="position:absolute;top:-60px;left:-60px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.05);"></div>
            <h3 style="font-size:2.6rem;font-weight:900;color:#f0e6ff;margin:0 0 1.2rem;position:relative;">¡Bienvenido!</h3>
            <p style="font-size:1.4rem;color:#d4b8ff;line-height:1.6;margin:0 0 2.4rem;position:relative;">Por favor ingrese sus credenciales para iniciar sesión</p>
            <a href="{{ url('ecommerce/register') }}"
               style="display:inline-block;padding:13px 30px;border:2.5px solid #fff;border-radius:30px;color:#fff;font-size:1.4rem;font-weight:700;text-decoration:none;position:relative;background:transparent;transition:background .2s;"
               onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='transparent'">
                ¡Registrarse!
            </a>
        </div>

        {{-- Close --}}
        <button onclick="ecAuthModal.close()" aria-label="Cerrar"
                style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:2.2rem;color:#888;cursor:pointer;line-height:1;z-index:10;">&times;</button>
    </div>
</div>

<script>
(function () {
    if (window.ecAuthModal) return; // ya cargado

    var modal  = document.getElementById('ec-auth-modal');
    var errBox = document.getElementById('ec-am-error');
    var btn    = document.getElementById('ec-am-btn');
    var _redirect = null;

    function open(redirectUrl) {
        _redirect = redirectUrl || null;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(function () {
            var el = document.getElementById('ec-am-email');
            if (el) el.focus();
        }, 80);
    }

    function close() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        errBox.style.display = 'none';
    }

    function submit(e) {
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Verificando…';
        errBox.style.display = 'none';

        var fd = new FormData();
        fd.append('email',    document.getElementById('ec-am-email').value);
        fd.append('password', document.getElementById('ec-am-pass').value);
        fd.append('_token',   document.querySelector('#ec-am-form [name=_token]').value);

        fetch('{{ url("ecommerce/login") }}', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.assign(_redirect || '{{ route("tenant.ecommerce.index") }}');
                } else {
                    errBox.textContent = data.message || 'Usuario o contraseña incorrectos.';
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Ingresar';
                }
            })
            .catch(function () {
                errBox.textContent = 'Error de conexión. Intenta de nuevo.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Ingresar';
            });
    }

    // ── Intercept <a href="...login..."> clicks ──────────────────
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.getAttribute('href') || '';
        if (!href.includes('ecommerce/login') && !href.includes('ecommerce_login')) return;
        if (href.includes('#registro')) return; // registro no interceptar
        e.preventDefault();
        e.stopPropagation();
        open(window.location.href);
    }, true);

    // ── Intercept window.location.href = '...login...' ──────────
    try {
        var proto = window.location.__proto__;
        var desc  = Object.getOwnPropertyDescriptor(proto, 'href');
        if (desc && desc.set) {
            var origSet = desc.set;
            Object.defineProperty(proto, 'href', {
                get: desc.get,
                set: function (url) {
                    if (typeof url === 'string' && url.includes('ecommerce/login')) {
                        open(null);
                    } else {
                        origSet.call(this, url);
                    }
                },
                configurable: true
            });
        }
    } catch (ex) { /* not supported everywhere */ }

    // ── Escape to close ──────────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') close();
    });

    window.ecAuthModal = { open: open, close: close, submit: submit };
})();
</script>
