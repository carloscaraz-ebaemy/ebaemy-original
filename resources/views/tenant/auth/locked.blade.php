@extends('tenant.layouts.auth')

@section('content')
<section class="auth auth__form-{{ $login->position_form ?? 'right' }}">
    @include('tenant.auth.partials.side_left')
    <article class="auth__form">
        <div class="d-flex justify-content-center">
            <div class="row form-logo-container">
                @include('tenant.auth.partials.form_logo')
            </div>
        </div>

        <div class="text-center title-login-container">
            <div style="font-size: 56px; line-height: 1; margin-bottom: 8px;">🔒</div>
            <h1 class="auth__title">
                <b>Cuenta inactiva</b>
            </h1>
            <p class="auth__subtitle" style="margin-top: 12px;">
                El acceso a <strong>{{ $company->trade_name ?? 'esta tienda' }}</strong> est temporalmente suspendido.
            </p>
        </div>

        <div class="alert" style="
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 16px 18px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.5;
        ">
            Para reactivar tu cuenta, contacta al equipo de soporte de
            <strong>{{ config('app.name', 'ebaemy') }}</strong>.
        </div>

        <div class="text-center" style="margin-top: 24px;">
            <a href="https://ebaemy.com" class="btn btn-signin btn-block" style="text-decoration: none;">
                Volver al sitio
            </a>
        </div>
    </article>
</section>
@endsection
