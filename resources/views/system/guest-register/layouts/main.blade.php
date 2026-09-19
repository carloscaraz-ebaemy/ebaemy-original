<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="googlebot" content="noindex">
        <meta name="robots" content="noindex">
        <title>Regístrate gratis</title>
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">
        <link rel="preconnect" href="https://fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('porto-light/vendor/bootstrap/css/bootstrap.css') }}" />
        <link rel="stylesheet" href="{{ asset('porto-light/vendor/animate/animate.css') }}" />
        <link rel="stylesheet" href="{{ asset('porto-light/vendor/font-awesome/css/fontawesome-all.min.css') }}" />
        <link rel="stylesheet" href="{{ asset('porto-light/css/theme.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/auth.css') }}" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.26.29/sweetalert2.min.css" />
        {{-- `asset()` y no `mix()`: `public/mix-manifest.json` lleva commiteado
             con marcadores de conflicto de merge sin resolver, asi que el JSON
             no parsea. Con APP_DEBUG=false `mix()` no tumba la pagina — reporta
             al log y devuelve la ruta cruda — de ahi los «Unable to locate Mix
             file» que ensuciaban `laravel.log` en CADA visita. La ruta servida
             es la misma que ya salia, asi que aqui no cambia nada mas.

             OJO, lo que esto NO arregla: `js/app.js` no existe (404), asi que
             esta pagina lleva tiempo sin Vue y su formulario —el componente
             <system-guest-register-register>— no se pinta. Arreglarlo es pasar
             a la directiva de Vite con resources/js/system.js (monta en
             #main-wrapper, que este layout ya tiene) y reconstruir el
             bundle: el componente ya
             quedo registrado en resources/js/system.js. --}}
        <link href="{{ asset('css/app.css') }}" id="app-style" rel="stylesheet" type="text/css" />
        <script src="{{ asset('js/manifest.js') }}"></script>
        <script src="{{ asset('js/vendor.js') }}"></script>

    </head>
    <body>
        <div class="app" id="main-wrapper">
            @yield('content')
        </div>

    </body>
</html>