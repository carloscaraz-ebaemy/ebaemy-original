@extends('marketplace.layout')

@section('title', 'Acceder a mi tienda — ebaemy')
@section('description', 'Ingresa el subdominio de tu tienda y accede a tu panel.')
@section('canonical', route('seller.access'))

@push('styles')
<style>
.sa-card {
    max-width: 480px;
    margin: 60px auto 80px;
    background: #fff;
    border: 1px solid var(--mp-border, #e5e7eb);
    border-radius: 18px;
    padding: 40px 32px;
    text-align: center;
}
.sa-card .sa-icon {
    width: 64px; height: 64px; border-radius: 16px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    color: var(--mp-primary-dark, #0c6b65);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px;
}
.sa-card h1 { margin: 0 0 8px; font-size: 24px; color: var(--mp-ink, #111827); }
.sa-card p { color: #6b7280; margin: 0 0 24px; }

.sa-input-group {
    display: flex; align-items: stretch;
    border: 2px solid var(--mp-border, #e5e7eb);
    border-radius: 12px;
    overflow: hidden;
    transition: border-color .15s;
}
.sa-input-group:focus-within { border-color: var(--mp-primary, #0f8a82); }
.sa-input-group input {
    flex: 1; min-width: 0;
    padding: 14px 16px;
    border: none;
    font-size: 16px;
    text-align: right;
    outline: none;
    font-family: inherit;
}
.sa-input-group .sa-suffix {
    background: #f9fafb;
    padding: 14px 16px;
    color: #6b7280;
    font-size: 15px;
    border-left: 1px solid #e5e7eb;
    white-space: nowrap;
}

.sa-error {
    background: #fee2e2; color: #991b1b;
    padding: 10px 14px; border-radius: 8px;
    font-size: 14px; margin-top: 14px;
}

.sa-btn {
    display: block; width: 100%;
    padding: 14px;
    background: var(--mp-primary, #0f8a82);
    color: #fff; border: none;
    border-radius: 12px;
    font-size: 15px; font-weight: 700;
    cursor: pointer;
    margin-top: 18px;
    transition: background .15s;
}
.sa-btn:hover { background: var(--mp-primary-dark, #0c6b65); }

.sa-help {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #f3f4f6;
    font-size: 13px;
    color: #6b7280;
}
.sa-help a { color: var(--mp-primary-dark, #0c6b65); font-weight: 600; }
</style>
@endpush

@section('content')

<div class="sa-card">
    <div class="sa-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
    </div>

    <h1>Acceder a mi tienda</h1>
    <p>Ingresa el subdominio de tu tienda para ir a tu panel de administración.</p>

    @if($errors->any())
        <div class="sa-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('seller.access.go') }}">
        @csrf
        <div class="sa-input-group">
            <input type="text" name="subdomain" placeholder="mi-tienda" required
                   value="{{ old('subdomain') }}"
                   pattern="[a-z0-9][a-z0-9\-]{1,62}"
                   autocomplete="off"
                   autofocus>
            <span class="sa-suffix">.ebaemy.com</span>
        </div>
        <button type="submit" class="sa-btn">Ir a mi tienda →</button>
    </form>

    <div class="sa-help">
        ¿Aún no tienes tienda? <a href="{{ route('seller.landing') }}">Crea tu tienda gratis</a>
    </div>
</div>

@endsection
