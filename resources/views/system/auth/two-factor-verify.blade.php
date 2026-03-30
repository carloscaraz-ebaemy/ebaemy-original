@extends('system.layouts.auth')

@section('content')
<section class="body-sign">
    <div class="center-sign">
        <div class="card card-sign">
            <div class="card-title-sign mt-3 text-right">
                <h2 class="title text-uppercase font-weight-bold m-0">
                    <i class="fas fa-shield-alt mr-1 text-primary"></i> Verificación 2FA
                </h2>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Ingresa el código de 6 dígitos de tu aplicación de autenticación.</p>

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('system.2fa.verify') }}">
                    @csrf
                    <div class="form-group mb-3">
                        <label>Código de autenticación</label>
                        <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*"
                               maxlength="7" autocomplete="one-time-code" autofocus
                               class="form-control form-control-lg text-center font-weight-bold tracking-widest"
                               placeholder="_ _ _ _ _ _"
                               style="letter-spacing: 0.4em; font-size: 1.4rem;">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <a href="{{ route('login') }}" class="btn btn-link text-muted px-0">← Volver</a>
                        </div>
                        <div class="col-6 text-right">
                            <button type="submit" class="btn btn-primary">Verificar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <p class="text-center text-muted mt-3 mb-3">{{ config('app.name') }} &copy; {{ date('Y') }}</p>
    </div>
</section>
@endsection
