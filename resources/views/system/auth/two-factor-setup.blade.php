@extends('system.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center mt-4">
        <div class="col-md-6 col-lg-5">

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            {{-- ── Setup / Activar 2FA ── --}}
            @if (!Auth::guard('admin')->user()->hasTwoFactorEnabled())
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-shield-alt mr-1"></i> Configurar autenticación de dos factores
                </div>
                <div class="card-body text-center">
                    <p class="text-muted">Escanea este código QR con <strong>Google Authenticator</strong>,
                       <strong>Authy</strong> u otra app TOTP compatible.</p>

                    <img src="{{ $qrUrl }}" alt="QR 2FA" class="img-fluid mb-3 border rounded p-1"
                         style="max-width:200px;" onerror="this.style.display='none';document.getElementById('manual-entry').style.display='block'">

                    <div id="manual-entry" style="display:none" class="alert alert-info text-left">
                        <strong>Ingreso manual:</strong><br>
                        Clave secreta: <code class="user-select-all">{{ $secret }}</code>
                    </div>

                    <p class="small text-muted mt-2">
                        ¿No carga el QR?
                        <a href="#" onclick="document.getElementById('manual-entry').style.display='block';return false;">
                            Mostrar clave manual
                        </a>
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger text-left">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('system.2fa.enable') }}" class="mt-3">
                        @csrf
                        <div class="form-group">
                            <label>Código de verificación (6 dígitos)</label>
                            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*"
                                   maxlength="6" autocomplete="one-time-code" autofocus
                                   class="form-control text-center font-weight-bold"
                                   style="letter-spacing:0.4em;font-size:1.3rem;" placeholder="______">
                        </div>
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-check mr-1"></i> Activar 2FA
                        </button>
                    </form>
                </div>
            </div>

            {{-- ── Desactivar 2FA ── --}}
            @else
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-shield-alt mr-1"></i> Autenticación de dos factores — Activa
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-1"></i> Tu cuenta está protegida con 2FA.
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('system.2fa.disable') }}">
                        @csrf
                        <div class="form-group">
                            <label>Contraseña actual</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Código 2FA actual</label>
                            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*"
                                   maxlength="6" class="form-control text-center font-weight-bold"
                                   style="letter-spacing:0.4em;font-size:1.3rem;" placeholder="______">
                        </div>
                        <button type="submit" class="btn btn-danger btn-block"
                                onclick="return confirm('¿Seguro que deseas desactivar el 2FA?')">
                            <i class="fas fa-times mr-1"></i> Desactivar 2FA
                        </button>
                    </form>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
