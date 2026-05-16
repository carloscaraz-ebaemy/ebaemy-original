@extends('marketplace.layout')

@section('title', 'Ingresa el codigo')

@section('content')
<div class="mp-auth-wrap">
    <div class="mp-auth-card">
        <h1 class="mp-auth-title">Revisa tu correo</h1>
        <p class="mp-auth-subtitle">
            Te enviamos un codigo de 6 digitos a <strong>{{ $email }}</strong>.
            Ingresalo aqui o haz click en el link del correo.
        </p>

        @if($errors->any())
            <div class="mp-auth-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('marketplace.auth.verify_code') }}" novalidate>
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            @if(!empty($next))
                <input type="hidden" name="next" value="{{ $next }}">
            @endif

            <label class="mp-auth-label" for="mpAuthCode">Codigo de 6 digitos</label>
            <input id="mpAuthCode" type="text" name="code" required
                   inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   autocomplete="one-time-code"
                   placeholder="123456"
                   class="mp-auth-input mp-auth-input--code"
                   autofocus>

            <button type="submit" class="mp-auth-submit">Entrar</button>
        </form>

        <p class="mp-auth-footer">
            ¿No te llego el correo? Revisa tu carpeta de spam o
            <a href="{{ route('marketplace.login') }}" style="color:#0c6b65">solicitalo de nuevo</a>.
            El codigo expira en 15 minutos.
        </p>
    </div>
</div>

<style>
.mp-auth-wrap { max-width: 460px; margin: 32px auto 64px; padding: 0 16px; }
.mp-auth-card {
    background: #fff; border-radius: 16px;
    border: 1px solid #e5e7eb;
    padding: 32px 28px;
    box-shadow: 0 4px 16px rgba(15,23,42,.04);
}
.mp-auth-title { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }
.mp-auth-subtitle { margin: 8px 0 24px; font-size: 14.5px; color: #64748b; line-height: 1.5; }
.mp-auth-error { padding: 12px 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; font-size: 13.5px; margin-bottom: 16px; }
.mp-auth-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.mp-auth-input { width: 100%; box-sizing: border-box; padding: 12px 14px; font-size: 15px; border: 1.5px solid #e5e7eb; border-radius: 10px; }
.mp-auth-input:focus { outline: none; border-color: #0f8a82; box-shadow: 0 0 0 3px rgba(15,138,130,.12); }
.mp-auth-input--code { font-family: 'SF Mono',Menlo,Consolas,monospace; font-size: 22px; letter-spacing: .3em; text-align: center; margin-bottom: 18px; }
.mp-auth-submit { width: 100%; padding: 13px 20px; background: linear-gradient(135deg,#0f8a82,#0a6f68); color: #fff; border: 0; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
.mp-auth-submit:hover { filter: brightness(1.05); }
.mp-auth-footer { margin: 18px 0 0; font-size: 13px; color: #64748b; line-height: 1.5; }
</style>
@endsection
