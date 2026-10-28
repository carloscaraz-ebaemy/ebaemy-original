@extends('system.layouts.app')

@php
    use App\Models\System\MarketplaceExternalSeller as Seller;
@endphp

@push('styles')
<style>
.xs{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--pri:#2563eb;--ok:#16a34a;--bad:#dc2626;color:var(--ink);max-width:1080px}
.xs *{box-sizing:border-box}
.xs-head{margin-bottom:18px}
.xs .xs-head h1{font-size:22px;font-weight:700;margin:0 0 4px}
.xs-head p{color:var(--mut);font-size:13.5px;margin:0;max-width:720px}
.xs-alert{border-radius:10px;padding:10px 14px;font-size:13.5px;margin-bottom:14px}
.xs-alert.ok{background:#dcfce7;color:#166534}.xs-alert.bad{background:#fee2e2;color:#991b1b}
.xs-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.xs-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:13px;text-decoration:none}
.xs-tab:hover{text-decoration:none;border-color:#94a3b8;color:var(--ink)}
.xs-tab.on{background:var(--ink);border-color:var(--ink);color:#fff}
.xs-tab i{font-style:normal;font-size:11px;background:rgba(100,116,139,.15);border-radius:999px;padding:1px 7px}
.xs-tab.on i{background:rgba(255,255,255,.2)}
.xs-tab.hot{border-color:#fcd34d;background:#fffbeb}
.xs-card{background:#fff;border:1px solid var(--line);border-radius:16px;margin-bottom:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.xs-top{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}
.xs-who{display:flex;gap:12px;min-width:0}
.xs-av{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-weight:700;font-size:18px;display:grid;place-items:center;flex:none}
.xs .xs-who h2{font-size:17px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.xs-who small{display:block;color:var(--mut);font-size:12.5px;margin-top:3px}
.xs-who a{color:var(--pri)}
.xs-st{font-size:11.5px;font-weight:600;border-radius:999px;padding:3px 10px}
.st-pending{background:#fef3c7;color:#92400e}.st-approved{background:#dcfce7;color:#166534}.st-rejected{background:#fee2e2;color:#991b1b}.st-suspended{background:#e2e8f0;color:#334155}
.xs-num{text-align:right;font-size:12.5px;color:var(--mut)}
.xs-num b{display:block;font-size:22px;color:var(--ink);line-height:1.1}
.xs-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--line)}
.xs-form input{flex:1;min-width:200px;border:1px solid var(--line);border-radius:9px;padding:8px 11px;font-size:13px}
.xs-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer}
.xs-btn.go{background:var(--ok);border-color:var(--ok);color:#fff}
.xs-btn.bad{color:var(--bad);border-color:#fecaca}
.xs-empty{background:#fff;border:1px dashed var(--line);border-radius:16px;padding:36px;text-align:center;color:var(--mut)}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
<div class="xs">
    <div class="xs-head">
        <h1>🏪 Tiendas PRO 9</h1>
        <p>Tiendas de PRO 9 que pidieron vender en el marketplace con el botón «Publicar en ebaemy.com». Mientras estén por aprobar pueden enviar sus productos, pero no se ven. Al aprobar una tienda, sus productos salen a la venta de inmediato; al rechazarla o suspenderla, se ocultan.</p>
    </div>

    @if(session('ok'))<div class="xs-alert ok">{{ session('ok') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="xs-alert bad">{{ $errors->first() }}</div>@endif

    <nav class="xs-tabs">
        <a href="{{ route('system.marketplace.external_sellers') }}" class="xs-tab {{ $status ? '' : 'on' }}">Todas</a>
        @foreach(Seller::STATUSES as $key => $label)
            <a href="{{ route('system.marketplace.external_sellers', ['status' => $key]) }}"
               class="xs-tab {{ $status === $key ? 'on' : '' }} {{ $key === 'pending' && ($counts[$key] ?? 0) ? 'hot' : '' }}">{{ $label }} <i>{{ $counts[$key] ?? 0 }}</i></a>
        @endforeach
    </nav>

    @forelse($sellers as $s)
        @php
            $p = $products->get($s->hostname_id);
            $phone = preg_replace('/\D/', '', (string) $s->contact_phone);
        @endphp
        <div class="xs-card">
            <div class="xs-top">
                <div class="xs-who">
                    <div class="xs-av">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</div>
                    <div>
                        <h2>{{ $s->name }} <span class="xs-st st-{{ $s->status }}">{{ $s->statusLabel() }}</span></h2>
                        <small>
                            <a href="https://{{ $s->fqdn }}/ecommerce" target="_blank" rel="noopener">{{ $s->fqdn }}</a>
                            @if($s->contact_phone) · <a href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener">WhatsApp {{ $s->contact_phone }}</a>@endif
                            @if($s->contact_email) · {{ $s->contact_email }}@endif
                        </small>
                        <small>Pidió el {{ $s->created_at->format('d/m/Y H:i') }}@if($s->reviewed_at) · revisada el {{ $s->reviewed_at->format('d/m/Y H:i') }}@endif</small>
                    </div>
                </div>
                <div class="xs-num">
                    <b>{{ (int) ($p->activos ?? 0) }}</b>
                    productos enviados
                    @if($s->status === 'approved' && ($p->activos ?? 0))
                        <br><a href="{{ url('marketplace/tienda/' . strtok($s->fqdn, '.')) }}" target="_blank" rel="noopener">Ver en el marketplace →</a>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('system.marketplace.external_sellers.update', $s->id) }}" class="xs-form">
                @csrf
                <input type="text" name="admin_notes" maxlength="500" value="{{ $s->admin_notes }}"
                       placeholder="Nota (la tienda la ve si la rechazas o suspendes)">
                @if($s->status !== 'approved')
                    <button class="xs-btn go" name="status" value="approved">Aprobar</button>
                @endif
                @if($s->status === 'pending')
                    <button class="xs-btn bad" name="status" value="rejected">Rechazar</button>
                @endif
                @if($s->status === 'approved')
                    <button class="xs-btn bad" name="status" value="suspended"
                            onclick="return confirm('Sus productos dejarán de verse en el marketplace. ¿Suspender?')">Suspender</button>
                @endif
            </form>
        </div>
    @empty
        <div class="xs-empty">
            Todavía no hay tiendas {{ $status ? 'en «' . (Seller::STATUSES[$status] ?? $status) . '»' : 'de PRO 9' }}.<br>
            Aparecen aquí cuando un tenant pulsa «Publicar en ebaemy.com» en su PRO 9.
        </div>
    @endforelse

    {{ $sellers->links() }}
</div>
</div>
@endsection
