@extends('marketplace.layout')

@section('title', 'Mis cupones')

@section('content')
<div class="mp-cpn-wrap">
    <header class="mp-cpn-head">
        <a href="{{ route('marketplace.account') }}" class="mp-cpn-back">← Mi cuenta</a>
        <h1 class="mp-cpn-title">Mis cupones</h1>
    </header>

    @if($coupons->isEmpty())
        <div class="mp-cpn-empty">
            <p>Aun no tienes cupones asignados.</p>
            <p class="mp-cpn-empty-sub">Cuando una tienda o ebaemy te envie uno, aparecera aqui.</p>
        </div>
    @else
        <div class="mp-cpn-list">
            @foreach($coupons as $c)
                @php
                    $isUsed = !empty($c->used_at);
                    $isExpired = $c->expires_at && \Carbon\Carbon::parse($c->expires_at)->isPast()
                                  || ($c->valid_until && \Carbon\Carbon::parse($c->valid_until)->isPast());
                    $isActive = !$isUsed && !$isExpired;
                @endphp
                <article class="mp-cpn-card {{ $isUsed ? 'is-used' : ($isExpired ? 'is-expired' : '') }}">
                    <div class="mp-cpn-card__head">
                        <div>
                            <p class="mp-cpn-code">{{ $c->code }}</p>
                            <p class="mp-cpn-name">{{ $c->name }}</p>
                        </div>
                        <div class="mp-cpn-value">
                            @if($c->type === 'percent')
                                <strong>{{ rtrim(rtrim(number_format($c->value, 2), '0'), '.') }}%</strong>
                                <span>off</span>
                            @else
                                <strong>S/ {{ number_format($c->value, 0) }}</strong>
                                <span>fijo</span>
                            @endif
                        </div>
                    </div>
                    @if($c->description)
                        <p class="mp-cpn-desc">{{ $c->description }}</p>
                    @endif
                    <div class="mp-cpn-meta">
                        @if($c->min_subtotal)
                            <span>· Min S/ {{ number_format($c->min_subtotal, 2) }}</span>
                        @endif
                        @if($c->scope === 'tenant')
                            <span>· Solo una tienda</span>
                        @else
                            <span>· Cualquier tienda</span>
                        @endif
                        @if($c->valid_until)
                            <span>· Vence {{ \Carbon\Carbon::parse($c->valid_until)->isoFormat('D MMM YYYY') }}</span>
                        @endif
                    </div>
                    <div class="mp-cpn-status">
                        @if($isUsed)
                            <span class="mp-cpn-pill mp-cpn-pill--used">Usado</span>
                        @elseif($isExpired)
                            <span class="mp-cpn-pill mp-cpn-pill--expired">Expirado</span>
                        @else
                            <span class="mp-cpn-pill mp-cpn-pill--active">Disponible</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

<style>
.mp-cpn-wrap { max-width: 680px; margin: 32px auto 64px; padding: 0 16px; }
.mp-cpn-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
.mp-cpn-back { color: #64748b; text-decoration: none; font-size: 13.5px; }
.mp-cpn-back:hover { color: #0c6b65; }
.mp-cpn-title { margin: 0; font-size: 22px; font-weight: 700; color: #0f172a; }

.mp-cpn-empty { padding: 40px 24px; text-align: center; background: #fff; border: 1px dashed #e5e7eb; border-radius: 12px; }
.mp-cpn-empty p { margin: 0; font-size: 15px; color: #64748b; }
.mp-cpn-empty-sub { margin-top: 8px !important; font-size: 13px; }

.mp-cpn-list { display: flex; flex-direction: column; gap: 12px; }
.mp-cpn-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 18px 20px;
    position: relative;
}
.mp-cpn-card.is-used, .mp-cpn-card.is-expired { opacity: .55; }

.mp-cpn-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 8px; }
.mp-cpn-code { margin: 0; font-size: 18px; font-weight: 800; color: #0c6b65; font-family: 'SF Mono',Menlo,Consolas,monospace; letter-spacing: .05em; }
.mp-cpn-name { margin: 2px 0 0; font-size: 13px; color: #64748b; font-weight: 600; }
.mp-cpn-value { text-align: right; }
.mp-cpn-value strong { display: block; font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1; }
.mp-cpn-value span { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; }

.mp-cpn-desc { margin: 6px 0 8px; font-size: 13.5px; color: #475569; line-height: 1.5; }
.mp-cpn-meta { display: flex; gap: 4px; flex-wrap: wrap; font-size: 12px; color: #64748b; margin-top: 8px; }
.mp-cpn-meta span:first-child { padding-left: 0; }

.mp-cpn-status { margin-top: 10px; }
.mp-cpn-pill {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
}
.mp-cpn-pill--active { background: #d1fae5; color: #065f46; }
.mp-cpn-pill--used { background: #e5e7eb; color: #4b5563; }
.mp-cpn-pill--expired { background: #fee2e2; color: #991b1b; }
</style>
@endsection
