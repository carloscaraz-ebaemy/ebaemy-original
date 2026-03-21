@extends('ecommerce::layouts.master')

@section('page_title', 'Iniciar sesión')
@section('meta_description', 'Inicia sesión en tu cuenta para gestionar tus pedidos y comprobantes.')

@section('breadcrumbs')
<ol class="ec-breadcrumb">
    <li><a href="{{ route('tenant.ecommerce.index') }}">Inicio</a></li>
    <li class="ec-breadcrumb__sep" aria-hidden="true">/</li>
    <li><span aria-current="page">Iniciar sesión</span></li>
</ol>
@endsection

@section('content')
<div class="container" style="max-width:460px;padding:8rem 15px 4rem;">

    <h1 style="font-size:2.4rem;font-weight:800;color:#222;margin-bottom:8px;text-align:center;">Iniciar sesión</h1>
    <p style="text-align:center;color:#888;font-size:1.4rem;margin-bottom:2.5rem;">Accede a tu cuenta para ver tus pedidos y comprobantes.</p>

    @if(session('error'))
    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px 16px;margin-bottom:1.5rem;color:#856404;font-size:1.35rem;">
        {{ session('error') }}
    </div>
    @endif

    {{-- Google OAuth --}}
    @php $ecConfig = \App\Models\Tenant\ConfigurationEcommerce::first(); @endphp
    @if($ecConfig && $ecConfig->google_login_enabled)
    <a href="{{ route('ecommerce.google.redirect') }}"
       style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px;border:1.5px solid #ddd;border-radius:10px;background:#fff;color:#333;font-size:1.45rem;font-weight:600;text-decoration:none;margin-bottom:1.5rem;transition:box-shadow .15s;"
       onmouseover="this.style.boxShadow='0 2px 10px rgba(0,0,0,.1)'"
       onmouseout="this.style.boxShadow='none'">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continuar con Google
    </a>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.5rem;">
        <hr style="flex:1;border:none;border-top:1px solid #eee;">
        <span style="color:#aaa;font-size:1.25rem;">o con tu correo</span>
        <hr style="flex:1;border:none;border-top:1px solid #eee;">
    </div>
    @endif

    {{-- Form login --}}
    <form method="POST" action="{{ url('ecommerce/login') }}" id="ec-login-form">
        @csrf
        <div style="margin-bottom:1.2rem;">
            <label style="display:block;font-size:1.3rem;font-weight:600;color:#444;margin-bottom:6px;">Correo electrónico</label>
            <input type="email" name="email" required autofocus
                   value="{{ old('email') }}"
                   style="width:100%;padding:12px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:1.4rem;outline:none;transition:border-color .2s;"
                   onfocus="this.style.borderColor='#333'" onblur="this.style.borderColor='#ddd'"
                   placeholder="tu@correo.com">
        </div>
        <div style="margin-bottom:1.8rem;">
            <label style="display:block;font-size:1.3rem;font-weight:600;color:#444;margin-bottom:6px;">Contraseña</label>
            <input type="password" name="password" required
                   style="width:100%;padding:12px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:1.4rem;outline:none;transition:border-color .2s;"
                   onfocus="this.style.borderColor='#333'" onblur="this.style.borderColor='#ddd'"
                   placeholder="••••••••">
        </div>
        <button type="submit"
                style="width:100%;padding:14px;background:#333;color:#fff;border:none;border-radius:8px;font-size:1.5rem;font-weight:700;cursor:pointer;transition:opacity .15s;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            Iniciar sesión
        </button>
    </form>

    <p style="text-align:center;margin-top:1.8rem;font-size:1.35rem;color:#888;">
        ¿No tienes cuenta?
        <a href="#" onclick="window.history.back()" style="color:#333;font-weight:700;text-decoration:none;">Regístrate</a>
    </p>

</div>
@endsection
