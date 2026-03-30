{{-- THEME ROPA — Login page estilo moda --}}
@extends('ecommerce::layouts.master')

@section('page_title', 'Iniciar sesión')
@section('meta_description', 'Accede a tu cuenta')

@section('content')
<div class="ropa-login-page">
    <div class="ropa-login-card">
        <h1 class="ropa-login-title">Iniciar Sesión</h1>
        <p class="ropa-login-subtitle">Accede a tu cuenta para ver tus pedidos y favoritos</p>

        @if(session('message'))
        <div class="alert alert-danger" style="border-radius:0;font-size:13px">{{ session('message') }}</div>
        @endif

        {{-- Google OAuth --}}
        @php $econf = \App\Models\Tenant\ConfigurationEcommerce::firstCached(); @endphp
        @if($econf->google_login_enabled ?? false)
        <a href="{{ route('ecommerce.google.redirect') }}" class="ropa-login-google">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            Continuar con Google
        </a>
        <div class="ropa-login-divider"><span>o con tu correo</span></div>
        @endif

        <form action="{{ route('tenant_ecommerce_login') }}" method="POST" autocomplete="off">
            @csrf
            <div class="ropa-form-group">
                <label class="ropa-form-label">Correo electrónico</label>
                <input type="email" name="email" class="ropa-form-input" required autocomplete="email">
            </div>
            <div class="ropa-form-group">
                <label class="ropa-form-label">Contraseña</label>
                <input type="password" name="password" class="ropa-form-input" required>
            </div>
            <button type="submit" class="ropa-login-btn">Ingresar</button>
        </form>

        <div class="ropa-login-footer">
            <p>¿No tienes cuenta? <a href="#" onclick="document.getElementById('ropa-register').style.display='block';this.closest('.ropa-login-card').querySelector('form').style.display='none';return false;">Crear cuenta</a></p>
        </div>

        {{-- Registro --}}
        <div id="ropa-register" style="display:none">
            <h2 class="ropa-login-title" style="font-size:20px;margin-top:1rem">Crear Cuenta</h2>
            <form action="{{ route('tenant_ecommerce_store_user') }}" method="POST" autocomplete="off">
                @csrf
                <div class="ropa-form-group">
                    <label class="ropa-form-label">Nombre completo</label>
                    <input type="text" name="name" class="ropa-form-input" required>
                </div>
                <div class="ropa-form-group">
                    <label class="ropa-form-label">Correo electrónico</label>
                    <input type="email" name="email" class="ropa-form-input" required>
                </div>
                <div class="ropa-form-group">
                    <label class="ropa-form-label">Contraseña</label>
                    <input type="password" name="password" class="ropa-form-input" required minlength="6">
                </div>
                <div class="ropa-form-group">
                    <label class="ropa-form-label">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" class="ropa-form-input" required>
                </div>
                <button type="submit" class="ropa-login-btn">Crear Cuenta</button>
            </form>
            <p class="ropa-login-footer"><a href="#" onclick="document.getElementById('ropa-register').style.display='none';this.closest('.ropa-login-card').querySelector('form').style.display='block';return false;">Volver a iniciar sesión</a></p>
        </div>
    </div>
</div>

<style>
.ropa-login-page { display:flex; justify-content:center; padding:3rem 1rem; min-height:60vh; }
.ropa-login-card { width:100%; max-width:420px; }
.ropa-login-title { font-family:'Cormorant Garamond',Georgia,serif; font-size:28px; font-weight:500; text-align:center; margin-bottom:.25rem; color:hsl(var(--primary-h),var(--primary-s),15%); }
.ropa-login-subtitle { text-align:center; font-size:13px; color:#6b7280; margin-bottom:1.5rem; }
.ropa-login-google { display:flex; align-items:center; justify-content:center; gap:.5rem; width:100%; padding:12px; border:1.5px solid #d1d5db; background:#fff; border-radius:0; font-size:13px; font-weight:600; color:#374151; text-decoration:none; transition:border-color .18s; }
.ropa-login-google:hover { border-color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#374151; text-decoration:none; }
.ropa-login-divider { display:flex; align-items:center; gap:.75rem; margin:1.25rem 0; color:#9ca3af; font-size:12px; }
.ropa-login-divider::before,.ropa-login-divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
.ropa-form-group { margin-bottom:1rem; }
.ropa-form-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#374151; margin-bottom:.3rem; }
.ropa-form-input { width:100%; padding:10px 12px; border:1.5px solid #d1d5db; border-radius:0; font-size:14px; transition:border-color .18s; outline:none; }
.ropa-form-input:focus { border-color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); }
.ropa-login-btn { width:100%; padding:13px; background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; border:none; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; cursor:pointer; transition:filter .18s; margin-top:.5rem; }
.ropa-login-btn:hover { filter:brightness(.9); }
.ropa-login-footer { text-align:center; margin-top:1rem; font-size:13px; color:#6b7280; }
.ropa-login-footer a { color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); text-decoration:underline; }
</style>
@endsection
