<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $raffle->name }} — {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</title>
    <style>
        :root { --brand:#7c3aed; --brand-d:#5b21b6; --brand-weak:#f5f1ff; --ink:#0f172a; --line:#e5e7eb;
                --muted:#6b7280; --ok:#16a34a; --bg:#f5f3fb; --gold:#b45309; --gold-weak:#fef6e7; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               background:var(--bg); color:var(--ink); -webkit-font-smoothing:antialiased; }
        .wrap { max-width:560px; margin:0 auto; padding:18px 14px 48px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:20px; overflow:hidden;
                box-shadow:0 10px 40px -16px rgba(15,23,42,.2); }
        .hero { position:relative; background:linear-gradient(135deg, var(--brand), var(--brand-d)); color:#fff;
                padding:22px 20px; }
        .hero h1 { margin:0; font-size:1.28rem; line-height:1.25; letter-spacing:-.01em; }
        .hero p { margin:.4rem 0 0; font-size:.85rem; opacity:.92; }
        .badge { display:inline-block; font-size:.7rem; font-weight:700; letter-spacing:.05em;
                 text-transform:uppercase; background:rgba(255,255,255,.2); border-radius:999px;
                 padding:.22rem .6rem; margin-bottom:.5rem; }
        .prize-img { width:100%; aspect-ratio:4/3; object-fit:cover; display:block; background:#f1f5f9; }
        .body { padding:20px; }
        .sec { margin-bottom:1.15rem; }
        .sec h2 { font-size:.74rem; text-transform:uppercase; letter-spacing:.06em; color:var(--brand-d);
                  margin:0 0 .45rem; display:flex; align-items:center; gap:.55rem; }
        .sec h2::after { content:''; flex:1; height:1px; background:var(--line); }
        .prize-name { font-size:1.05rem; font-weight:700; }
        .text { font-size:.9rem; line-height:1.55; color:#334155; white-space:pre-line; }
        .dates { list-style:none; margin:0; padding:0; font-size:.86rem; }
        .dates li { display:flex; justify-content:space-between; gap:1rem; padding:.42rem 0;
                    border-bottom:1px solid #f1f5f9; }
        .dates li:last-child { border-bottom:0; }
        .dates span:first-child { color:var(--muted); }
        .dates span:last-child { font-weight:600; text-align:right; }
        .terms { max-height:230px; overflow-y:auto; background:#f8fafc; border:1px solid var(--line);
                 border-radius:12px; padding:.8rem; font-size:.82rem; line-height:1.5; color:#475569;
                 white-space:pre-line; }
        .gallery { display:grid; grid-template-columns:repeat(auto-fill, minmax(88px,1fr)); gap:.45rem; }
        .gallery img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:10px; border:1px solid var(--line); }
        /* ── Opciones de premio ── */
        .opts { display:grid; gap:.5rem; }
        .opt { display:block; cursor:pointer; }
        .opt input { position:absolute; opacity:0; pointer-events:none; }
        .opt__box { display:flex; gap:.6rem; align-items:center; border:2px solid var(--line);
                    border-radius:14px; padding:.6rem; background:#fff; transition:.15s; }
        .opt__box img { width:62px; height:62px; object-fit:cover; border-radius:10px; flex-shrink:0;
                        border:1px solid var(--line); }
        .opt__tx { flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .opt__tx b { font-size:.92rem; }
        .opt__tx span { font-size:.78rem; color:var(--muted); line-height:1.35; }
        .opt__check { flex:0 0 auto; width:24px; height:24px; border-radius:50%; border:2px solid var(--line);
                      color:transparent; display:flex; align-items:center; justify-content:center;
                      font-weight:800; font-size:13px; }
        .opt input:checked + .opt__box { border-color:var(--brand); background:var(--brand-weak); }
        .opt input:checked + .opt__box .opt__check { background:var(--brand); border-color:var(--brand); color:#fff; }
        .opt input:focus-visible + .opt__box { box-shadow:0 0 0 3px var(--brand-weak); }

        .check { display:flex; gap:.55rem; align-items:flex-start; font-size:.86rem; line-height:1.45;
                 background:var(--brand-weak); border:1px solid #ddd2fb; border-radius:12px; padding:.7rem .8rem;
                 margin-bottom:.8rem; }
        .check input { margin-top:.2rem; width:18px; height:18px; flex-shrink:0; }
        .btn { display:block; width:100%; text-align:center; border:0; border-radius:13px; padding:.85rem 1rem;
               font-size:.98rem; font-weight:700; cursor:pointer; text-decoration:none; transition:.15s; }
        .btn-primary { background:var(--brand); color:#fff; }
        .btn-primary:hover { background:var(--brand-d); }
        .btn-primary:disabled { opacity:.45; cursor:not-allowed; }
        .btn-ghost { background:transparent; color:var(--muted); font-weight:600; font-size:.84rem;
                     padding:.6rem; margin-top:.35rem; }
        .alert { border-radius:12px; padding:.7rem .85rem; font-size:.86rem; margin-bottom:.9rem; }
        .alert-ok { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
        .alert-info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
        .state { text-align:center; padding:1.2rem .5rem; }
        .state .icon { font-size:2.6rem; line-height:1; }
        .state .t { font-size:1.05rem; font-weight:700; margin-top:.45rem; }
        .state .s { font-size:.86rem; color:var(--muted); margin-top:.25rem; }
        .winner-box { background:linear-gradient(135deg, var(--gold-weak), #fffdf7); border:1px solid #fde3b0;
                      border-radius:14px; padding:1rem; text-align:center; }
        .winner-box .t { font-size:1.1rem; font-weight:800; color:var(--gold); }
        .foot { text-align:center; font-size:.74rem; color:var(--muted); margin-top:1rem; }
        .person { font-size:.82rem; color:var(--muted); text-align:center; margin-bottom:.7rem; }
    </style>
</head>
<body>
<div class="wrap">

    @if(session('success'))
        <div class="alert alert-ok">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-err">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="hero">
            <span class="badge">
                @if($raffle->phase() === 'proximamente') Próximamente
                @elseif($raffle->phase() === 'en_curso') Sorteo en curso
                @else Finalizado @endif
            </span>
            <h1>{{ $raffle->name }}</h1>
            <p>{{ $company->trade_name ?? $company->name }}</p>
        </div>

        @if($raffle->prize_image)
            <img src="{{ $raffle->prizeImageUrl('main') }}" alt="{{ $raffle->prize_name }}" class="prize-img">
        @endif

        <div class="body">

            <div class="person">Hola, <strong>{{ $participant->full_name }}</strong></div>

            {{-- ── Ganador ───────────────────────────────────────── --}}
            @if($winner)
                <div class="winner-box" style="margin-bottom:1rem">
                    <div style="font-size:2.4rem">🏆</div>
                    <div class="t">¡Ganaste el sorteo!</div>
                    <div class="text" style="margin-top:.4rem">
                        Premio: <strong>{{ $winner->prize_name }}</strong><br>
                        {{ $winner->delivery_label }}
                    </div>
                </div>
            @endif

            {{-- ── Premio ────────────────────────────────────────── --}}
            @if($raffle->prize_name || $raffle->prize_description)
                <div class="sec">
                    <h2>Premio</h2>
                    @if($raffle->prize_name)<div class="prize-name">{{ $raffle->prize_name }}</div>@endif
                    @if($raffle->prize_quantity > 1)
                        <div class="text" style="color:var(--muted);font-size:.82rem">{{ $raffle->prize_quantity }} ganadores</div>
                    @endif
                    @if($raffle->prize_description)
                        <div class="text" style="margin-top:.35rem">{{ $raffle->prize_description }}</div>
                    @endif
                </div>
            @endif

            @if($raffle->description)
                <div class="sec">
                    <h2>Descripción</h2>
                    <div class="text">{{ $raffle->description }}</div>
                </div>
            @endif

            @if($raffle->galleryUrls('medium'))
                <div class="sec">
                    <h2>Galería</h2>
                    <div class="gallery">
                        @foreach($raffle->galleryUrls('medium') as $url)
                            <img src="{{ $url }}" alt="">
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── Fechas ────────────────────────────────────────── --}}
            <div class="sec">
                <h2>Fechas</h2>
                <ul class="dates">
                    @if($raffle->starts_at)
                        <li><span>Inicio</span><span>{{ $raffle->starts_at->format('d/m/Y H:i') }}</span></li>
                    @endif
                    @if($raffle->registration_closes_at)
                        <li><span>Cierre de registro</span><span>{{ $raffle->registration_closes_at->format('d/m/Y H:i') }}</span></li>
                    @endif
                    @if($raffle->draw_at)
                        <li><span>Sorteo</span><span>{{ $raffle->draw_at->format('d/m/Y H:i') }}</span></li>
                    @endif
                    @if($raffle->winner_published_at)
                        <li><span>Publicación del ganador</span><span>{{ $raffle->winner_published_at->format('d/m/Y H:i') }}</span></li>
                    @endif
                </ul>
            </div>

            {{-- ── Bases ─────────────────────────────────────────── --}}
            @if($raffle->terms)
                <div class="sec">
                    <h2>Bases y condiciones</h2>
                    <div class="terms">{{ $raffle->terms }}</div>
                </div>
            @endif

            {{-- ── Acción ────────────────────────────────────────── --}}
            @if($participant->status === \App\Models\Tenant\RaffleParticipant::STATUS_ACCEPTED)
                <div class="state">
                    <div class="icon">✅</div>
                    <div class="t">Ya estás participando</div>
                    <div class="s">
                        Confirmaste el {{ optional($participant->accepted_at)->format('d/m/Y \a \l\a\s H:i') }}.
                        @if($raffle->draw_at) El sorteo se realiza el {{ $raffle->draw_at->format('d/m/Y H:i') }}. @endif
                    </div>
                </div>
            @elseif(!$open)
                <div class="alert alert-info" style="margin-bottom:0">{{ $reason }}</div>
            @else
                <form method="POST" action="{{ route('raffles.public.accept', $participant->token) }}" id="acceptForm">
                    @csrf

                    {{-- Opciones de premio: si el sorteo ofrece varias, el
                         cliente elige la que quiere antes de confirmar. --}}
                    @if($raffle->hasPrizeOptions())
                        <div class="sec">
                            <h2>Elige tu premio</h2>
                            <div class="opts">
                                @foreach($raffle->prizeOptions()->active()->get() as $opt)
                                    <label class="opt">
                                        <input type="radio" name="prize_option_id" value="{{ $opt->id }}" required>
                                        <span class="opt__box">
                                            @if($opt->imageUrl('small'))
                                                <img src="{{ $opt->imageUrl('small') }}" alt="{{ $opt->name }}">
                                            @endif
                                            <span class="opt__tx">
                                                <b>{{ $opt->name }}</b>
                                                @if($opt->description)<span>{{ $opt->description }}</span>@endif
                                            </span>
                                            <span class="opt__check">✓</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <label class="check">
                        <input type="checkbox" name="accept_terms" value="1" id="acceptTerms" required>
                        <span>He leído y acepto las bases y condiciones del sorteo, y autorizo el uso de mis datos para su ejecución.</span>
                    </label>

                    <button type="submit" class="btn btn-primary" id="acceptBtn" disabled>Aceptar participar</button>
                </form>

                <form method="POST" action="{{ route('raffles.public.decline', $participant->token) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">No deseo participar</button>
                </form>
            @endif
        </div>
    </div>

    <div class="foot">
        {{ $company->trade_name ?? $company->name }} · Sorteo {{ $raffle->code }}
    </div>
</div>

<script>
    // El botón solo se habilita al marcar las bases.
    (function () {
        var chk = document.getElementById('acceptTerms');
        var btn = document.getElementById('acceptBtn');
        if (!chk || !btn) return;
        var sync = function () { btn.disabled = !chk.checked; };
        chk.addEventListener('change', sync);
        sync();

        var form = document.getElementById('acceptForm');
        if (form) {
            form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.textContent = 'Registrando…';
            });
        }
    })();
</script>
</body>
</html>
