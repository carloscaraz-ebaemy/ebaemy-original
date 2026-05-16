@extends('marketplace.layout')

@section('title', 'Entra a ebaemy')

@section('content')
<div class="mp-auth-wrap">
    <div class="mp-auth-card">
        <h1 class="mp-auth-title">Entra a ebaemy</h1>
        <p class="mp-auth-subtitle">Ingresa con tu contraseña o pidemos un codigo de un solo uso a tu email.</p>

        @if($errors->any())
            <div class="mp-auth-error">{{ $errors->first() }}</div>
        @endif

        {{-- Toggle entre password y magic link --}}
        <div class="mp-auth-tabs" role="tablist">
            <button type="button" class="mp-auth-tab is-active" data-tab="pwd" role="tab">Con contraseña</button>
            <button type="button" class="mp-auth-tab" data-tab="magic" role="tab">Codigo por email</button>
        </div>

        {{-- Tab 1: email + password --}}
        <form id="mp-auth-pwd-form" method="POST" action="{{ route('marketplace.auth.login_password') }}" novalidate>
            @csrf
            @if(!empty($next))<input type="hidden" name="next" value="{{ $next }}">@endif

            <label class="mp-auth-label" for="mpAuthPwdEmail">Email</label>
            <input id="mpAuthPwdEmail" type="email" name="email" required value="{{ old('email') }}"
                   placeholder="tu@email.com" autocomplete="email" class="mp-auth-input">

            <label class="mp-auth-label" for="mpAuthPwd">Contraseña</label>
            <input id="mpAuthPwd" type="password" name="password" required minlength="1"
                   autocomplete="current-password" class="mp-auth-input">

            <label class="mp-auth-check">
                <input type="checkbox" name="remember" value="1" checked>
                <span>Recordarme en este dispositivo</span>
            </label>

            <button type="submit" class="mp-auth-submit">Entrar</button>

            <p class="mp-auth-helper">
                ¿No tienes cuenta? <a href="{{ route('marketplace.register', ['next' => $next]) }}">Crear cuenta gratis</a>
            </p>
            <p class="mp-auth-helper">
                ¿Olvidaste tu contraseña? <a href="#" data-switch-tab="magic">Recibe un codigo por email</a>
            </p>
        </form>

        {{-- Tab 2: magic link (passwordless) --}}
        <form id="mp-auth-magic-form" method="POST" action="{{ route('marketplace.auth.request') }}" style="display:none" novalidate>
            @csrf
            @if(!empty($next))<input type="hidden" name="next" value="{{ $next }}">@endif

            <label class="mp-auth-label" for="mpAuthMagicEmail">Tu email</label>
            <input id="mpAuthMagicEmail" type="email" name="email" required value="{{ old('email') }}"
                   placeholder="tu@email.com" autocomplete="email" class="mp-auth-input">

            <label class="mp-auth-check">
                <input type="checkbox" name="marketing" value="1" {{ old('marketing') ? 'checked' : '' }}>
                <span>Quiero recibir ofertas y avisos de descuento</span>
            </label>

            <button type="submit" class="mp-auth-submit">Enviarme el codigo</button>

            <p class="mp-auth-helper">
                Tambien creamos tu cuenta automaticamente si es la primera vez.
            </p>
        </form>
    </div>
</div>

<style>
.mp-auth-wrap { max-width: 460px; margin: 32px auto 64px; padding: 0 16px; }
.mp-auth-card {
    background: #fff; border-radius: 16px; border: 1px solid #e5e7eb;
    padding: 32px 28px; box-shadow: 0 4px 16px rgba(15,23,42,.04);
}
.mp-auth-title { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }
.mp-auth-subtitle { margin: 8px 0 20px; font-size: 14.5px; color: #64748b; line-height: 1.5; }
.mp-auth-error { padding: 12px 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; font-size: 13.5px; margin-bottom: 16px; }

.mp-auth-tabs {
    display: flex; gap: 4px; padding: 4px;
    background: #f1f5f9; border-radius: 10px;
    margin-bottom: 18px;
}
.mp-auth-tab {
    flex: 1; padding: 9px 10px;
    background: transparent; border: 0; cursor: pointer;
    border-radius: 7px; font-size: 13.5px; font-weight: 600;
    color: #64748b;
    transition: background .15s, color .15s;
}
.mp-auth-tab:hover { color: #0f172a; }
.mp-auth-tab.is-active {
    background: #fff; color: #0f172a;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}

.mp-auth-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; margin-top: 14px; }
.mp-auth-label:first-of-type { margin-top: 0; }
.mp-auth-input {
    width: 100%; box-sizing: border-box;
    padding: 12px 14px; font-size: 15px;
    border: 1.5px solid #e5e7eb; border-radius: 10px; background: #fff;
    transition: border-color .12s, box-shadow .12s;
}
.mp-auth-input:focus {
    outline: none; border-color: #0f8a82;
    box-shadow: 0 0 0 3px rgba(15,138,130,.12);
}
.mp-auth-check {
    display: flex; gap: 8px; align-items: flex-start;
    margin: 14px 0 16px;
    font-size: 13px; color: #475569;
    cursor: pointer; user-select: none;
}
.mp-auth-check input { margin-top: 2px; }
.mp-auth-submit {
    width: 100%; padding: 13px 20px;
    background: linear-gradient(135deg, #0f8a82, #0a6f68); color: #fff;
    border: 0; border-radius: 10px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: filter .12s;
}
.mp-auth-submit:hover { filter: brightness(1.05); }
.mp-auth-helper { margin: 12px 0 0; font-size: 13px; color: #64748b; text-align: center; line-height: 1.5; }
.mp-auth-helper a { color: #0c6b65; font-weight: 600; text-decoration: none; }
.mp-auth-helper a:hover { text-decoration: underline; }
</style>

<script>
(function () {
    const tabs = document.querySelectorAll('.mp-auth-tab');
    const pwdForm = document.getElementById('mp-auth-pwd-form');
    const magicForm = document.getElementById('mp-auth-magic-form');
    function show(which) {
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.tab === which));
        pwdForm.style.display   = which === 'pwd'   ? '' : 'none';
        magicForm.style.display = which === 'magic' ? '' : 'none';
        // Copiar email entre forms para no perderlo al cambiar tab.
        const fromEmail = (which === 'pwd' ? magicForm : pwdForm).querySelector('input[type=email]').value;
        if (fromEmail) (which === 'pwd' ? pwdForm : magicForm).querySelector('input[type=email]').value = fromEmail;
    }
    tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.tab)));
    document.querySelectorAll('[data-switch-tab]').forEach(a => {
        a.addEventListener('click', (e) => { e.preventDefault(); show(a.dataset.switchTab); });
    });
})();
</script>
@endsection
