@extends('marketplace.layout')

@section('title', 'Crear cuenta — ebaemy')

@section('content')
<div class="mp-auth-wrap">
    <div class="mp-auth-card">
        <h1 class="mp-auth-title">Crear cuenta gratis</h1>
        <p class="mp-auth-subtitle">Una sola cuenta para comprar en cualquier tienda de la red ebaemy.</p>

        @if($errors->any())
            <div class="mp-auth-error">{{ $errors->first() }}</div>
        @endif

        <a href="{{ route('marketplace.auth.google', ['next' => $next]) }}" class="mp-auth-social mp-auth-social--google">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#4285f4" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#34a853" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#fbbc05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#ea4335" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            <span>Continuar con Google</span>
        </a>

        <div class="mp-auth-divider"><span>o crea cuenta con email</span></div>

        <form method="POST" action="{{ route('marketplace.auth.register') }}" novalidate>
            @csrf
            @if(!empty($next))<input type="hidden" name="next" value="{{ $next }}">@endif

            <label class="mp-auth-label" for="mpRegName">Nombre completo</label>
            <input id="mpRegName" type="text" name="name" required value="{{ old('name') }}"
                   maxlength="120" autocomplete="name" class="mp-auth-input">

            <label class="mp-auth-label" for="mpRegEmail">Email</label>
            <input id="mpRegEmail" type="email" name="email" required value="{{ old('email') }}"
                   placeholder="tu@email.com" autocomplete="email" class="mp-auth-input">

            <label class="mp-auth-label" for="mpRegPhone">WhatsApp (opcional)</label>
            <input id="mpRegPhone" type="tel" name="phone" value="{{ old('phone') }}"
                   placeholder="+51 999 999 999" autocomplete="tel" class="mp-auth-input">

            <label class="mp-auth-label" for="mpRegPwd">Contraseña</label>
            <input id="mpRegPwd" type="password" name="password" required minlength="8" maxlength="200"
                   autocomplete="new-password" class="mp-auth-input">
            <p class="mp-auth-hint">Minimo 8 caracteres.</p>

            <label class="mp-auth-label" for="mpRegPwd2">Confirma tu contraseña</label>
            <input id="mpRegPwd2" type="password" name="password_confirmation" required minlength="8" maxlength="200"
                   autocomplete="new-password" class="mp-auth-input">

            <label class="mp-auth-check">
                <input type="checkbox" name="marketing" value="1" {{ old('marketing') ? 'checked' : '' }}>
                <span>Quiero recibir ofertas y avisos de descuento</span>
            </label>

            <button type="submit" class="mp-auth-submit">Crear mi cuenta</button>

            <p class="mp-auth-helper">
                ¿Ya tienes cuenta? <a href="{{ route('marketplace.login', ['next' => $next]) }}">Iniciar sesion</a>
            </p>
        </form>
    </div>
</div>

<style>
.mp-auth-wrap { max-width: 460px; margin: 32px auto 64px; padding: 0 16px; }
.mp-auth-card { background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; padding: 32px 28px; box-shadow: 0 4px 16px rgba(15,23,42,.04); }
.mp-auth-title { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }
.mp-auth-subtitle { margin: 8px 0 20px; font-size: 14.5px; color: #64748b; line-height: 1.5; }
.mp-auth-error { padding: 12px 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; font-size: 13.5px; margin-bottom: 16px; }
.mp-auth-social { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 12px 16px; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 14.5px; font-weight: 600; color: #1f2937; text-decoration: none; transition: border-color .12s, background .12s; margin-bottom: 14px; }
.mp-auth-social:hover { border-color: #cbd5e1; background: #f9fafb; color: #1f2937; text-decoration: none; }
.mp-auth-social svg { flex-shrink: 0; }
.mp-auth-divider { text-align: center; margin: 14px 0; position: relative; color: #94a3b8; font-size: 12px; }
.mp-auth-divider::before, .mp-auth-divider::after { content: ''; position: absolute; top: 50%; width: calc(50% - 80px); height: 1px; background: #e5e7eb; }
.mp-auth-divider::before { left: 0; }
.mp-auth-divider::after  { right: 0; }
.mp-auth-divider span { background: #fff; padding: 0 10px; }
.mp-auth-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; margin-top: 14px; }
.mp-auth-label:first-of-type { margin-top: 0; }
.mp-auth-input { width: 100%; box-sizing: border-box; padding: 12px 14px; font-size: 15px; border: 1.5px solid #e5e7eb; border-radius: 10px; background: #fff; transition: border-color .12s, box-shadow .12s; }
.mp-auth-input:focus { outline: none; border-color: #0f8a82; box-shadow: 0 0 0 3px rgba(15,138,130,.12); }
.mp-auth-hint { margin: 4px 0 0; font-size: 12px; color: #94a3b8; }
.mp-auth-check { display: flex; gap: 8px; align-items: flex-start; margin: 14px 0 16px; font-size: 13px; color: #475569; cursor: pointer; user-select: none; }
.mp-auth-check input { margin-top: 2px; }
.mp-auth-submit { width: 100%; padding: 13px 20px; background: linear-gradient(135deg, #0f8a82, #0a6f68); color: #fff; border: 0; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: filter .12s; }
.mp-auth-submit:hover { filter: brightness(1.05); }
.mp-auth-helper { margin: 12px 0 0; font-size: 13px; color: #64748b; text-align: center; }
.mp-auth-helper a { color: #0c6b65; font-weight: 600; text-decoration: none; }
.mp-auth-helper a:hover { text-decoration: underline; }
</style>
@endsection
