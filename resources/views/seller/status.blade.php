<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de mi solicitud — ebaemy</title>
    <meta name="robots" content="noindex,nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/design-tokens.css') }}">

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: var(--eb-font, 'Inter', system-ui, sans-serif);
            color: var(--eb-ink, #0f172a);
            background:
                radial-gradient(ellipse at 15% 20%, rgba(31,177,166,0.12) 0%, transparent 60%),
                #fafbfc;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.01em;
        }
        a { text-decoration: none; color: inherit; }

        .ss-wrap { max-width: 720px; margin: 0 auto; padding: 40px clamp(16px, 4vw, 32px); }
        .ss-header { text-align: center; margin-bottom: 28px; }
        .ss-logo { font-weight: 800; font-size: 20px; letter-spacing: -0.02em; }
        .ss-logo-badge {
            display: inline-block; margin-left: 6px; padding: 2px 10px;
            background: var(--eb-brand-soft, #e8f6f5); color: var(--eb-brand-dark, #0a6f68);
            border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; vertical-align: 3px;
        }
        .ss-header h1 { font-size: clamp(22px, 3vw, 28px); font-weight: 800; margin: 20px 0 6px; letter-spacing: -0.02em; }
        .ss-header p { color: var(--eb-ink-soft, #475569); font-size: 14.5px; margin: 0; }

        .ss-card {
            background: #fff; border-radius: 18px;
            border: 1px solid var(--eb-line, #e2e8f0);
            box-shadow: 0 16px 48px -16px rgba(15,23,42,0.12);
            padding: clamp(24px, 4vw, 36px);
            margin-bottom: 20px;
        }

        /* ── STATUS HERO ───────────────────────────────────── */
        .ss-status-hero { text-align: center; margin-bottom: 8px; }
        .ss-status-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 68px; height: 68px; border-radius: 50%;
            margin-bottom: 14px; color: #fff;
            box-shadow: 0 10px 24px rgba(15,138,130,0.28);
        }
        .ss-status-icon.pending      { background: linear-gradient(135deg, #94a3b8, #64748b); box-shadow: 0 10px 24px rgba(100,116,139,0.28); }
        .ss-status-icon.reviewing    { background: linear-gradient(135deg, #60a5fa, #2563eb); box-shadow: 0 10px 24px rgba(37,99,235,0.28); }
        .ss-status-icon.docs         { background: linear-gradient(135deg, #fbbf24, #d97706); box-shadow: 0 10px 24px rgba(217,119,6,0.28); }
        .ss-status-icon.approved     { background: linear-gradient(135deg, #1fb1a6, #0a6f68); box-shadow: 0 10px 24px rgba(15,138,130,0.35); }
        .ss-status-icon.rejected     { background: linear-gradient(135deg, #f87171, #dc2626); box-shadow: 0 10px 24px rgba(220,38,38,0.28); }

        .ss-status-title { font-size: 22px; font-weight: 800; margin: 0 0 6px; letter-spacing: -0.02em; }
        .ss-status-desc { color: var(--eb-ink-soft, #475569); font-size: 14.5px; margin: 0 auto; max-width: 460px; line-height: 1.6; }

        .ss-meta {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;
            margin-top: 28px; padding-top: 24px;
            border-top: 1px solid var(--eb-line, #e2e8f0);
        }
        @media (max-width: 560px) { .ss-meta { grid-template-columns: 1fr; } }
        .ss-meta div { font-size: 13px; }
        .ss-meta dt { color: var(--eb-muted, #94a3b8); font-weight: 500; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; margin-bottom: 4px; }
        .ss-meta dd { margin: 0; font-weight: 600; color: var(--eb-ink, #0f172a); }

        /* ── ALERT ─────────────────────────────────────────── */
        .ss-alert { padding: 14px 18px; border-radius: 12px; font-size: 14px; line-height: 1.55; }
        .ss-alert.warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .ss-alert.err  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .ss-alert.ok   { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .ss-alert strong { display: block; margin-bottom: 4px; }

        /* ── TIMELINE ──────────────────────────────────────── */
        .ss-timeline { position: relative; padding-left: 28px; }
        .ss-timeline::before {
            content: ''; position: absolute; left: 11px; top: 6px; bottom: 6px;
            width: 2px; background: var(--eb-line, #e2e8f0);
        }
        .ss-tl-item { position: relative; padding-bottom: 18px; }
        .ss-tl-item:last-child { padding-bottom: 0; }
        .ss-tl-dot {
            position: absolute; left: -23px; top: 2px;
            width: 14px; height: 14px; border-radius: 50%;
            background: var(--eb-brand, #0f8a82); border: 3px solid #fff;
            box-shadow: 0 0 0 2px var(--eb-brand, #0f8a82);
        }
        .ss-tl-date { font-size: 12px; color: var(--eb-muted, #94a3b8); margin-bottom: 3px; }
        .ss-tl-action { font-size: 14px; font-weight: 600; color: var(--eb-ink, #0f172a); }
        .ss-tl-notes { font-size: 13px; color: var(--eb-ink-soft, #475569); margin-top: 4px; white-space: pre-line; }

        .ss-footer { text-align: center; margin-top: 24px; font-size: 13px; color: var(--eb-muted, #94a3b8); }
        .ss-footer a { color: var(--eb-brand-dark, #0a6f68); font-weight: 500; }
    </style>
</head>
<body>

@php
    $statusConfig = [
        'pending' => [
            'icon_class' => 'pending',
            'icon_svg'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'title'      => 'Solicitud recibida',
            'desc'       => 'Hemos registrado tu solicitud y la tenemos en cola para revisión. Te notificaremos por correo cuando haya novedades.',
        ],
        'under_review' => [
            'icon_class' => 'reviewing',
            'icon_svg'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>',
            'title'      => 'En revisión',
            'desc'       => 'Nuestro equipo está revisando tus datos. Normalmente toma entre 24 y 48 horas hábiles.',
        ],
        'requires_documents' => [
            'icon_class' => 'docs',
            'icon_svg'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            'title'      => 'Faltan documentos',
            'desc'       => 'Necesitamos información adicional para continuar. Revisa la solicitud a continuación.',
        ],
        'requires_review' => [
            'icon_class' => 'reviewing',
            'icon_svg'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>',
            'title'      => 'En revisión manual',
            'desc'       => 'No pudimos verificar automáticamente algunos datos. Un revisor humano los validará manualmente.',
        ],
        'approved' => [
            'icon_class' => 'approved',
            'icon_svg'   => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 9 18l-5-5"/></svg>',
            'title'      => '¡Solicitud aprobada!',
            'desc'       => 'Tu tienda ya está activa. Revisa el correo que te enviamos con tus credenciales de acceso.',
        ],
        'rejected' => [
            'icon_class' => 'rejected',
            'icon_svg'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            'title'      => 'Solicitud rechazada',
            'desc'       => 'Tu solicitud no pudo ser aprobada. Revisa el motivo a continuación.',
        ],
        'cancelled' => [
            'icon_class' => 'pending',
            'icon_svg'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            'title'      => 'Solicitud cancelada',
            'desc'       => 'Esta solicitud fue cancelada.',
        ],
    ];
    $current = $statusConfig[$application->status] ?? $statusConfig['pending'];
    $actionLabels = [
        'created'        => 'Solicitud registrada',
        'status_changed' => 'Estado actualizado',
        'docs_requested' => 'Se solicitaron documentos',
        'approved'       => 'Solicitud aprobada',
        'rejected'       => 'Solicitud rechazada',
    ];
@endphp

<div class="ss-wrap">

    <div class="ss-header">
        <a href="{{ url('/seller') }}" class="ss-logo">
            ebaemy<span class="ss-logo-badge">Sellers</span>
        </a>
    </div>

    <div class="ss-card">
        <div class="ss-status-hero">
            <div class="ss-status-icon {{ $current['icon_class'] }}">
                {!! $current['icon_svg'] !!}
            </div>
            <h1 class="ss-status-title">{{ $current['title'] }}</h1>
            <p class="ss-status-desc">{{ $current['desc'] }}</p>
        </div>

        <dl class="ss-meta">
            <div>
                <dt>RUC</dt>
                <dd>{{ $application->ruc }}</dd>
            </div>
            <div>
                <dt>Razón social</dt>
                <dd>{{ $application->business_name }}</dd>
            </div>
            <div>
                <dt>Subdominio</dt>
                <dd><code>{{ $application->requested_subdomain }}.{{ config('tenant.app_url_base') }}</code></dd>
            </div>
            <div>
                <dt>Solicitud creada</dt>
                <dd>{{ $application->created_at->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>
    </div>

    @if($application->status === 'requires_documents' && $application->review_notes)
        <div class="ss-card" style="padding: 0; overflow: hidden;">
            <div class="ss-alert warn" style="border-radius: 0; border-top: 0; border-left: 0; border-right: 0;">
                <strong>Qué necesitamos de ti:</strong>
                <div>{{ $application->review_notes }}</div>
                <div style="margin-top: 12px; font-size: 12.5px;">Respóndenos por correo con la información solicitada.</div>
            </div>
        </div>
    @endif

    @if($application->status === 'rejected' && $application->rejection_reason)
        <div class="ss-card" style="padding: 0; overflow: hidden;">
            <div class="ss-alert err" style="border-radius: 0; border-top: 0; border-left: 0; border-right: 0;">
                <strong>Motivo del rechazo:</strong>
                <div>{{ $application->rejection_reason }}</div>
            </div>
        </div>
    @endif

    @if($application->status === 'approved')
        <div class="ss-card" style="padding: 0; overflow: hidden;">
            <div class="ss-alert ok" style="border-radius: 0; border-top: 0; border-left: 0; border-right: 0;">
                <strong>Tu tienda ya está activa</strong>
                <div>
                    Accede a tu panel en:
                    <a href="{{ (config('tenant.force_https') === true ? 'https://' : 'http://') . $application->requested_subdomain . '.' . config('tenant.app_url_base') }}/login"
                       target="_blank"
                       style="color: #065f46; font-weight: 700;">
                        {{ $application->requested_subdomain }}.{{ config('tenant.app_url_base') }}
                    </a>
                </div>
                <div style="margin-top: 8px; font-size: 12.5px;">Las credenciales se enviaron al correo <strong>{{ $application->email }}</strong>.</div>
            </div>
        </div>
    @endif

    <div class="ss-card">
        <h2 style="font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--eb-brand-dark); margin: 0 0 20px;">
            Historial
        </h2>
        <div class="ss-timeline">
            @forelse($publicLogs as $log)
                <div class="ss-tl-item">
                    <div class="ss-tl-dot"></div>
                    <div class="ss-tl-date">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i') }}</div>
                    <div class="ss-tl-action">
                        {{ $actionLabels[$log->action] ?? $log->action }}
                        @if($log->new_status)
                            — <span style="color: var(--eb-brand-dark);">{{ $log->new_status }}</span>
                        @endif
                    </div>
                    @if($log->notes && $application->status !== 'approved')
                        <div class="ss-tl-notes">{{ $log->notes }}</div>
                    @endif
                </div>
            @empty
                <div style="color: var(--eb-muted); font-size: 14px;">Sin actividad registrada aún.</div>
            @endforelse
        </div>
    </div>

    <div class="ss-footer">
        ¿Tienes preguntas? Responde al correo que te enviamos o contáctanos desde
        <a href="{{ url('/seller') }}">ebaemy.com/seller</a>
    </div>

</div>

</body>
</html>
