{{-- THEME ROPA — Wishlist estilo moda --}}
@extends('ecommerce::layouts.master')

@section('page_title', 'Mis Favoritos')
@section('meta_description', 'Tu lista de productos favoritos')

@section('content')
<div class="container" style="padding-top:2rem;padding-bottom:3rem;min-height:50vh">
    <h1 style="font-family:'Cormorant Garamond',Georgia,serif;font-size:28px;font-weight:500;text-align:center;margin-bottom:2rem;color:hsl(var(--primary-h),var(--primary-s),15%)">Mis Favoritos</h1>

    <div class="row" id="ec-wishlist-grid">
        <div class="col-12 text-center text-muted py-5" id="ec-wishlist-empty">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <p class="mt-3">Aún no tienes productos favoritos</p>
            <a href="{{ route('tenant.ecommerce.index') }}" style="color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));text-decoration:underline;font-size:14px">Explorar productos</a>
        </div>
    </div>
</div>
@endsection
