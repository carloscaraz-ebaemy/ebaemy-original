@extends('marketplace.layout')

@section('title', 'Ajustes — Mi cuenta')

@section('content')
<div class="mp-settings-wrap">
    <header class="mp-settings-head">
        <a href="{{ route('marketplace.account') }}" class="mp-settings-back">← Mi cuenta</a>
        <h1 class="mp-settings-title">Ajustes</h1>
    </header>

    @if(session('mkt_settings_ok'))
        <div class="mp-settings-note">{{ session('mkt_settings_ok') }}</div>
    @endif
    @if($errors->any())
        <div class="mp-settings-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('marketplace.account.settings_save') }}" novalidate>
        @csrf

        {{-- Perfil --}}
        <section class="mp-settings-card">
            <h2>Perfil</h2>
            <p class="mp-settings-help">Datos visibles a las tiendas donde compras.</p>
            <label class="mp-settings-label">Nombre</label>
            <input type="text" name="name" required maxlength="120"
                   value="{{ old('name', $user->name) }}" class="mp-settings-input">

            <label class="mp-settings-label">Email</label>
            <input type="email" disabled value="{{ $user->email }}" class="mp-settings-input mp-settings-input--readonly">
            <p class="mp-settings-help">Para cambiar tu email, escribenos a soporte@ebaemy.com.</p>

            <label class="mp-settings-label">WhatsApp (opcional)</label>
            <input type="tel" name="phone" maxlength="20"
                   placeholder="+51 999 999 999"
                   value="{{ old('phone', $user->phone) }}" class="mp-settings-input">
        </section>

        {{-- Seguridad / Password --}}
        <section class="mp-settings-card">
            <h2>Contraseña</h2>
            @if($user->password_hash)
                <p class="mp-settings-help">Cambia tu contraseña si lo deseas. Dejala en blanco para mantener la actual.</p>
                <label class="mp-settings-label">Contraseña actual</label>
                <input type="password" name="current_password" autocomplete="current-password" class="mp-settings-input">
            @else
                <p class="mp-settings-help">No tienes contraseña aun (usas magic link o Google). Si quieres crear una para login mas rapido:</p>
            @endif

            <label class="mp-settings-label">{{ $user->password_hash ? 'Nueva contraseña' : 'Contraseña' }}</label>
            <input type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" class="mp-settings-input">

            <label class="mp-settings-label">Confirmar</label>
            <input type="password" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" class="mp-settings-input">
        </section>

        {{-- Preferencias de notificaciones --}}
        <section class="mp-settings-card">
            <h2>Notificaciones</h2>
            <p class="mp-settings-help">Controla cuanto y como te contactamos.</p>

            <label class="mp-settings-label">Frecuencia de email</label>
            <select name="email_frequency" class="mp-settings-input">
                @foreach(['off' => 'No me envies emails de marketing', 'daily' => 'Diario', 'weekly' => 'Semanal (recomendado)', 'monthly' => 'Mensual'] as $k => $v)
                    <option value="{{ $k }}" {{ old('email_frequency', $pref->email_frequency) === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>

            <label class="mp-settings-label">Frecuencia de WhatsApp</label>
            <select name="whatsapp_frequency" class="mp-settings-input">
                @foreach(['off' => 'No usar WhatsApp', 'critical_only' => 'Solo cosas criticas (pedidos)', 'weekly' => 'Semanal'] as $k => $v)
                    <option value="{{ $k }}" {{ old('whatsapp_frequency', $pref->whatsapp_frequency) === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>

            <div class="mp-settings-opts">
                <label class="mp-settings-check">
                    <input type="checkbox" name="opt_email_marketing" value="1" {{ old('opt_email_marketing', $hasMarketingConsent ? '1' : '') ? 'checked' : '' }}>
                    <span>Recibir email con ofertas y novedades</span>
                </label>
                <label class="mp-settings-check">
                    <input type="checkbox" name="opt_email_price_alerts" value="1" {{ old('opt_email_price_alerts', $hasPriceAlertConsent ? '1' : '') ? 'checked' : '' }}>
                    <span>Avisarme por email cuando bajen de precio mis favoritos</span>
                </label>
                <label class="mp-settings-check">
                    <input type="checkbox" name="opt_wa_marketing" value="1" {{ old('opt_wa_marketing', $hasWaMarketingConsent ? '1' : '') ? 'checked' : '' }}>
                    <span>Recibir promociones por WhatsApp</span>
                </label>
            </div>
        </section>

        <button type="submit" class="mp-settings-submit">Guardar cambios</button>
    </form>
</div>

<style>
.mp-settings-wrap { max-width: 640px; margin: 32px auto 64px; padding: 0 16px; }
.mp-settings-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
.mp-settings-back { color: #64748b; text-decoration: none; font-size: 13.5px; }
.mp-settings-back:hover { color: #0c6b65; }
.mp-settings-title { margin: 0; font-size: 22px; font-weight: 700; color: #0f172a; }

.mp-settings-note { padding: 12px 16px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
.mp-settings-error { padding: 12px 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; font-size: 13.5px; margin-bottom: 16px; }

.mp-settings-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 22px 22px 24px; margin-bottom: 16px;
}
.mp-settings-card h2 { margin: 0 0 4px; font-size: 16px; font-weight: 700; color: #0f172a; }
.mp-settings-help { margin: 0 0 14px; font-size: 13px; color: #64748b; line-height: 1.5; }
.mp-settings-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin: 12px 0 6px; }
.mp-settings-input {
    width: 100%; box-sizing: border-box;
    padding: 11px 13px; font-size: 14.5px;
    border: 1.5px solid #e5e7eb; border-radius: 8px; background: #fff;
    transition: border-color .12s, box-shadow .12s;
}
.mp-settings-input:focus { outline: none; border-color: #0f8a82; box-shadow: 0 0 0 3px rgba(15,138,130,.12); }
.mp-settings-input--readonly { background: #f9fafb; color: #64748b; }
.mp-settings-opts { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
.mp-settings-check { display: flex; gap: 10px; align-items: flex-start; font-size: 13.5px; color: #475569; cursor: pointer; user-select: none; }
.mp-settings-check input { margin-top: 3px; }
.mp-settings-submit {
    width: 100%; padding: 14px 20px;
    background: linear-gradient(135deg,#0f8a82,#0a6f68); color: #fff;
    border: 0; border-radius: 10px;
    font-size: 15px; font-weight: 700; cursor: pointer;
}
.mp-settings-submit:hover { filter: brightness(1.05); }
</style>
@endsection
