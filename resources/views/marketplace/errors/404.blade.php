@extends('marketplace.layout')

{{-- 404 del marketplace. Antes cualquier URL equivocada de ebaemy.com
     —incluida la ficha de un producto que la tienda retiro y que Google aun
     tenia— pintaba en pantalla el JSON {"success":false,...}. Sigue
     respondiendo 404 (Google saca la URL del indice), pero quien llega desde
     un enlace viejo tiene por donde seguir. --}}
@section('title', 'Página no encontrada — ebaemy Marketplace')
@section('description', 'La página que buscas ya no está disponible en ebaemy. Busca el producto o explora las tiendas y categorías del marketplace.')
@section('robots', 'noindex, follow')
@section('canonical', route('marketplace.index'))

@include('marketplace.legal._layout')

@section('content')
<article class="mp-legal">
    <h1>No encontramos esta página</h1>
    <p class="mp-legal__lead">
        Puede que el producto ya no esté publicado o que el enlace esté incompleto.
    </p>

    <form action="{{ route('marketplace.index') }}" method="get" role="search" style="display:flex;gap:8px;margin:20px 0;flex-wrap:wrap">
        <label for="mp404q" style="position:absolute;left:-9999px">Buscar productos</label>
        <input id="mp404q" type="search" name="q" placeholder="Busca el producto…"
               style="flex:1 1 220px;min-width:0;padding:12px 14px;border:1px solid var(--mp-line, #d6d6d6);border-radius:10px;font-size:16px">
        <button type="submit" class="mp-cta-primary" style="padding:12px 20px;border:none;border-radius:10px;cursor:pointer">Buscar</button>
    </form>

    <h2>Sigue por aquí</h2>
    <ul>
        <li><a href="{{ route('marketplace.index') }}">Ver todos los productos</a></li>
        <li><a href="{{ route('marketplace.stores') }}">Tiendas del marketplace</a></li>
        <li><a href="{{ route('marketplace.faq') }}">Preguntas frecuentes</a></li>
    </ul>
</article>
@endsection
