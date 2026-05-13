@extends('marketplace.layout')

@section('title', 'Mis favoritos — ebaemy Marketplace')
@section('description', 'Productos que guardaste para comprar después en ebaemy.')

@push('styles')
<style>
.mp-fav-page {
    max-width: var(--mp-container-w, 1180px);
    margin: 0 auto;
    padding: 20px clamp(12px, 3vw, 24px) 60px;
}
.mp-fav-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.mp-fav-header h1 {
    font-size: clamp(22px, 4vw, 28px);
    font-weight: 800;
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.mp-fav-header__count {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}
.mp-fav-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, #f0fdfa 0%, #fff 100%);
    border: 1px dashed #a7f3d0;
    border-radius: 14px;
}
.mp-fav-empty__icon {
    width: 72px; height: 72px;
    border-radius: 999px;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    box-shadow: 0 4px 12px rgba(15,23,42,.08);
    font-size: 36px;
}
.mp-fav-empty h2 {
    font-size: 18px; font-weight: 700; margin: 0 0 6px;
}
.mp-fav-empty p {
    color: #6b7280; font-size: 14px;
    margin: 0 0 18px; max-width: 420px;
}
.mp-fav-empty__cta {
    display: inline-block;
    padding: 11px 22px;
    background: var(--mp-primary, #0f8a82);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
}
.mp-fav-empty__cta:hover { background: var(--mp-primary-dark, #0c6b65); }
</style>
@endpush

@section('content')
<div class="mp-fav-page">
    <nav class="mp-breadcrumb" aria-label="breadcrumb">
        <a href="{{ route('marketplace.index') }}">Marketplace</a>
        <span class="sep">›</span>
        <span style="color:var(--mp-ink);font-weight:500">Mis favoritos</span>
    </nav>

    <div class="mp-fav-header">
        <h1>
            <svg width="26" height="26" viewBox="0 0 24 24" fill="#dc2626" stroke="#dc2626" stroke-width="1" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Mis favoritos
        </h1>
        @if($listings->count() > 0)
            <span class="mp-fav-header__count">{{ $listings->count() }} producto{{ $listings->count() === 1 ? '' : 's' }} guardado{{ $listings->count() === 1 ? '' : 's' }}</span>
        @endif
    </div>

    @if($listings->count() === 0)
        <div class="mp-fav-empty">
            <span class="mp-fav-empty__icon">🤍</span>
            <h2>Aún no guardaste productos</h2>
            <p>Pulsa el corazón en cualquier producto del marketplace para guardarlo aquí y comprarlo más adelante.</p>
            <a href="{{ route('marketplace.index') }}" class="mp-fav-empty__cta">Explorar marketplace</a>
        </div>
    @else
        <div class="mp-grid">
            @foreach($listings as $listing)
                @include('marketplace.partials.listing-card', ['listing' => $listing])
            @endforeach
        </div>
    @endif
</div>
@endsection
