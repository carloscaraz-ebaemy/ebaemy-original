@extends('ecommerce::layouts.master')

@section('content')
<div class="lr-wrap">
    <div class="lr-container lr-container--narrow">

        <header class="lr-head">
            <span class="lr-head__eyebrow">Libro de Reclamaciones</span>
            <h1 class="lr-head__title">Consulta tu registro</h1>
            <p class="lr-head__provider">{{ $provider['name'] }} · RUC {{ $provider['ruc'] }}</p>
        </header>

        @if(session('error'))
            <div class="lr-alert lr-alert--error" role="alert">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="lr-alert lr-alert--error" role="alert">
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="lr-card">
            {{-- El código es correlativo y por tanto adivinable: por sí solo no
                 abre nada. Hace falta también el correo del registro. --}}
            <form method="POST" action="{{ route('tenant.libro_reclamaciones.track_search') }}">
                @csrf
                <div class="lr-grid">
                    <div class="lr-field lr-field--full">
                        <label for="code">Código de registro <span class="lr-req">*</span></label>
                        <input type="text" id="code" name="code" placeholder="LR-{{ date('Y') }}-000001"
                               value="{{ old('code') }}" maxlength="20" required>
                    </div>
                    <div class="lr-field lr-field--full">
                        <label for="email">Correo electrónico <span class="lr-req">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="120" required>
                        <p class="lr-hint">El mismo con el que registraste tu reclamo o queja.</p>
                    </div>
                </div>
                <button type="submit" class="lr-btn lr-btn--primary lr-btn--block">Consultar</button>
            </form>
        </section>

        @isset($claim)
            @if($claim)
                <section class="lr-card">
                    <div class="lr-result__head">
                        <div>
                            <div class="lr-result__code">{{ $claim->code }}</div>
                            <div class="lr-result__meta">{{ $claim->typeLabel() }} · registrado el {{ $claim->created_at->format('d/m/Y') }}</div>
                        </div>
                        <span class="lr-badge lr-badge--{{ $claim->status }}">{{ $claim->statusLabel() }}</span>
                    </div>

                    <dl class="lr-dl">
                        <dt>{{ ucfirst($claim->item_type) }} contratado</dt>
                        <dd>{{ $claim->product_description }}</dd>

                        @if($claim->order_reference)
                            <dt>Pedido</dt><dd>N° {{ $claim->order_reference }}</dd>
                        @endif

                        <dt>Tu detalle</dt>
                        <dd class="lr-pre">{{ $claim->detail }}</dd>

                        <dt>Tu pedido concreto</dt>
                        <dd class="lr-pre">{{ $claim->consumer_request }}</dd>

                        @if($claim->answered_at)
                            <dt>Respuesta del proveedor <span class="lr-dl__date">{{ $claim->answered_at->format('d/m/Y') }}</span></dt>
                            <dd class="lr-pre">{{ $claim->answer }}</dd>

                            @if($claim->actions_taken)
                                <dt>Acciones adoptadas</dt>
                                <dd class="lr-pre">{{ $claim->actions_taken }}</dd>
                            @endif

                            @if($claim->request_accepted === false && $claim->rejection_grounds)
                                <dt>Fundamento</dt>
                                <dd class="lr-pre">{{ $claim->rejection_grounds }}</dd>
                            @endif
                        @else
                            <dt>Respuesta del proveedor</dt>
                            <dd>Pendiente. El plazo máximo vence el <strong>{{ $claim->due_date->format('d/m/Y') }}</strong>.</dd>
                        @endif
                    </dl>

                    <a class="lr-btn lr-btn--ghost lr-btn--block"
                       href="{{ route('tenant.libro_reclamaciones.pdf', $claim->code) }}?t={{ $token }}"
                       target="_blank" rel="noopener">Descargar copia en PDF</a>
                </section>
            @endif
        @endisset

        <p class="lr-track">
            <a href="{{ route('tenant.libro_reclamaciones') }}">Registrar un nuevo reclamo o queja</a>
        </p>
    </div>
</div>

<style>
    .lr-wrap { background:#f5f6f8; padding:96px 0 48px; }
    .lr-container { max-width:840px; margin:0 auto; padding:0 16px; }
    .lr-container--narrow { max-width:620px; }

    .lr-head { margin-bottom:18px; }
    .lr-head__eyebrow { display:inline-block; font-size:12px; letter-spacing:.09em; text-transform:uppercase; color:#5b6472; font-weight:700; }
    .lr-head__title { font-size:clamp(24px, 5vw, 30px); margin:6px 0 8px; color:#141821; font-weight:700; }
    .lr-head__provider { font-size:14px; color:#5b6472; margin:0; }

    .lr-alert { border-radius:10px; padding:14px 16px; font-size:14px; line-height:1.6; margin-bottom:18px; }
    .lr-alert--error { background:#fdecec; border:1px solid #f3b7b7; color:#8e1b1b; }
    .lr-alert ul { margin:0; padding-left:18px; }

    .lr-card { background:#fff; border:1px solid #e6e8ec; border-radius:14px; padding:20px; margin-bottom:16px; }
    .lr-grid { display:grid; grid-template-columns:1fr; gap:14px; margin-bottom:16px; }
    .lr-field label { font-size:13.5px; font-weight:600; color:#39414f; margin-bottom:6px; display:block; }
    .lr-req { color:#c0392b; }
    .lr-field input { width:100%; border:1px solid #d3d7de; border-radius:9px; padding:11px 12px; font-size:15px; min-height:44px; font-family:inherit; }
    .lr-field input:focus { outline:none; border-color:#1f5eff; box-shadow:0 0 0 3px rgba(31,94,255,.14); }
    .lr-hint { font-size:12.5px; color:#7a8393; margin:6px 0 0; }

    .lr-btn { display:inline-flex; align-items:center; justify-content:center; border-radius:10px; padding:13px 22px; font-size:15.5px; font-weight:600; text-decoration:none; border:1px solid transparent; cursor:pointer; min-height:48px; }
    .lr-btn--primary { background:#1f5eff; color:#fff; }
    .lr-btn--primary:hover { background:#1a4fd8; color:#fff; }
    .lr-btn--ghost { background:#fff; color:#39414f; border-color:#d3d7de; }
    .lr-btn--block { width:100%; }

    .lr-result__head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid #eceef2; padding-bottom:14px; margin-bottom:14px; flex-wrap:wrap; }
    .lr-result__code { font-size:19px; font-weight:700; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; color:#141821; }
    .lr-result__meta { font-size:13px; color:#6b7382; margin-top:3px; }

    .lr-badge { font-size:12.5px; font-weight:700; padding:6px 12px; border-radius:999px; white-space:nowrap; }
    .lr-badge--registered { background:#eef2ff; color:#3949ab; }
    .lr-badge--in_review { background:#fff4e0; color:#a55a00; }
    .lr-badge--answered { background:#e7f6ee; color:#0f7a54; }
    .lr-badge--closed { background:#eef0f3; color:#5b6472; }

    .lr-dl { margin:0 0 16px; }
    .lr-dl dt { font-size:12.5px; text-transform:uppercase; letter-spacing:.05em; color:#7a8393; font-weight:700; margin-top:14px; }
    .lr-dl dd { margin:5px 0 0; font-size:14.5px; color:#2b3240; line-height:1.6; }
    .lr-dl__date { text-transform:none; letter-spacing:0; color:#9aa2b1; font-weight:500; }
    .lr-pre { white-space:pre-line; }

    .lr-track { font-size:14px; text-align:center; color:#6b7382; }

    @media (max-width: 680px) {
        .lr-wrap { padding:80px 0 32px; }
        .lr-card { padding:16px; }
    }
</style>
@endsection
