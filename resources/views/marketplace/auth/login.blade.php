@extends('marketplace.layout')

@section('title', 'Entra a ebaemy')

@section('content')
<div class="mp-auth-wrap">
    <div class="mp-auth-card">
        <h1 class="mp-auth-title">Entra a ebaemy</h1>
        <p class="mp-auth-subtitle">Sin password. Te enviamos un codigo al email para entrar.</p>

        @if($errors->any())
            <div class="mp-auth-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('marketplace.auth.request') }}" novalidate>
            @csrf
            @if(!empty($next))
                <input type="hidden" name="next" value="{{ $next }}">
            @endif

            <label class="mp-auth-label" for="mpAuthEmail">Tu email</label>
            <input id="mpAuthEmail" type="email" name="email" required
                   value="{{ old('email') }}"
                   placeholder="tu@email.com"
                   autocomplete="email"
                   class="mp-auth-input">

            <label class="mp-auth-check">
                <input type="checkbox" name="marketing" value="1" {{ old('marketing') ? 'checked' : '' }}>
                <span>Quiero recibir ofertas y avisos de descuento (puedes desactivarlo cuando quieras)</span>
            </label>

            <button type="submit" class="mp-auth-submit">Enviarme el codigo</button>
        </form>

        <p class="mp-auth-footer">
            ¿Ya estabas explorando? Tus favoritos y carrito se asocian a tu cuenta al entrar.
        </p>
    </div>
</div>

<style>
.mp-auth-wrap { max-width: 460px; margin: 32px auto 64px; padding: 0 16px; }
.mp-auth-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    padding: 32px 28px;
    box-shadow: 0 4px 16px rgba(15,23,42,.04);
}
.mp-auth-title { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }
.mp-auth-subtitle { margin: 8px 0 24px; font-size: 14.5px; color: #64748b; line-height: 1.5; }
.mp-auth-error {
    padding: 12px 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 10px;
    font-size: 13.5px;
    margin-bottom: 16px;
}
.mp-auth-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}
.mp-auth-input {
    width: 100%; box-sizing: border-box;
    padding: 12px 14px;
    font-size: 15px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    transition: border-color .12s, box-shadow .12s;
}
.mp-auth-input:focus {
    outline: none;
    border-color: #0f8a82;
    box-shadow: 0 0 0 3px rgba(15,138,130,.12);
}
.mp-auth-check {
    display: flex; gap: 8px; align-items: flex-start;
    margin: 14px 0 20px;
    font-size: 13px; color: #475569;
    cursor: pointer; user-select: none;
}
.mp-auth-check input { margin-top: 2px; }
.mp-auth-submit {
    width: 100%;
    padding: 13px 20px;
    background: linear-gradient(135deg, #0f8a82, #0a6f68);
    color: #fff;
    border: 0;
    border-radius: 10px;
    font-size: 15px; font-weight: 600;
    cursor: pointer;
    transition: filter .12s;
}
.mp-auth-submit:hover { filter: brightness(1.05); }
.mp-auth-footer { margin: 18px 0 0; font-size: 13px; color: #64748b; line-height: 1.5; }
</style>
@endsection
