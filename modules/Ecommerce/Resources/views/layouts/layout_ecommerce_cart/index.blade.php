<!DOCTYPE html>
<html lang="en">

<!-- Mirrored from portotheme.com/html/porto_ecommerce/demo-6/cart.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 07 Sep 2019 03:40:04 GMT -->
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>eCommerce</title>

    <meta name="keywords" content="HTML5 Template" />
    <meta name="description" content="Porto - Bootstrap eCommerce Template">
    <meta name="author" content="SW-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('porto-ecommerce/assets/images/icons/favicon.ico') }}">

    <!-- Plugins CSS File -->
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/bootstrap.min.css') }}">

    <!-- Main CSS File -->
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/custom.css') }}">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css') }}">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="{{ asset(file_exists(public_path('porto-light/css/styles_ecommerce.min.css')) ? 'porto-light/css/styles_ecommerce.min.css' : 'porto-light/css/styles_ecommerce.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/css/ecommerce-theme-override.css') }}" />
    @php $__thm = (\App\Models\Tenant\ConfigurationEcommerce::first()->theme_template ?? 'generic'); @endphp
    @if($__thm !== 'generic' && file_exists(public_path("porto-light/css/themes/{$__thm}.css")))
        <link rel="stylesheet" href="{{ asset("porto-light/css/themes/{$__thm}.css") }}" />
    @endif
    @php
        $__seo = \App\Models\Tenant\ConfigurationEcommerce::first();
        $__hex = ($__seo->color_ecommerce ?? '#ff8000');
        $__hex = ltrim($__hex, '#');
        if (strlen($__hex) === 3) { $__hex = $__hex[0].$__hex[0].$__hex[1].$__hex[1].$__hex[2].$__hex[2]; }
        $__r = hexdec(substr($__hex,0,2))/255; $__g = hexdec(substr($__hex,2,2))/255; $__b = hexdec(substr($__hex,4,2))/255;
        $__max = max($__r,$__g,$__b); $__min = min($__r,$__g,$__b); $__l = ($__max+$__min)/2;
        if ($__max == $__min) { $__h = $__s = 0; } else {
            $__d = $__max-$__min;
            $__s = $__l > 0.5 ? $__d/(2-$__max-$__min) : $__d/($__max+$__min);
            if ($__max==$__r) $__h = ($__g-$__b)/$__d + ($__g<$__b?6:0);
            elseif ($__max==$__g) $__h = ($__b-$__r)/$__d + 2;
            else $__h = ($__r-$__g)/$__d + 4;
            $__h /= 6;
        }
    @endphp
    <style>:root{--primary-h:{{ round($__h*360) }};--primary-s:{{ round($__s*100) }}%;--primary-l:{{ round($__l*100) }}%;}</style>

    {{-- Rediseño visual 2026 (aditivo). Carga al final para que gane sobre
         Porto legacy. Respeta --primary-h/s/l del tenant inyectado arriba. --}}
    @php
        $__modernCssPath = public_path('porto-light/css/ecommerce-modern.css');
        $__modernCssV = file_exists($__modernCssPath) ? filemtime($__modernCssPath) : time();
    @endphp
    <link rel="stylesheet" href="{{ asset('porto-light/css/ecommerce-modern.css') }}?v={{ $__modernCssV }}" />
    <script>document.documentElement.classList.add('ec-modern');</script>

    <style>
        /* En la página del carrito/checkout el mini-carrito del header no tiene sentido */
        .cart-dropdown .dropdown-toggle { pointer-events: none; cursor: default; }
        .cart-dropdown .ec-minicart-dropdown { display: none !important; }
        /* Ocultar breadcrumb: el stepper cumple esa función */
        .breadcrumb-nav { display: none !important; }
    </style>

    <!-- Element UI CSS -->
    <link rel="stylesheet" href="https://unpkg.com/element-ui/lib/theme-chalk/index.css">

    <!-- Vue + Axios + Element UI deben cargarse ANTES del header (que usa new Vue) -->
    <script src="{{ asset('porto-ecommerce/assets/js/vue.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/axios.min.js') }}"></script>
    <script src="https://unpkg.com/element-ui/lib/index.js"></script>
    <script src="https://unpkg.com/element-ui/lib/umd/locale/es.js"></script>
</head>
<body>
    <div class="page-wrapper">
        @include('ecommerce::layouts.partials_ecommerce.header')
        @include('ecommerce::layouts.partials_ecommerce.header_bottom_sticky')
        <main class="main">
            <nav aria-label="breadcrumb" class="breadcrumb-nav">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{url('ecommerce')}}"><i class="icon-home"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
                    </ol>
                </div><!-- End .container -->
            </nav>

            <div class="container">
                 @yield('content')
            </div><!-- End .container -->

            <div class="mb-6"></div><!-- margin -->
        </main><!-- End .main -->

        <footer class="footer">
            @include('ecommerce::layouts.partials_ecommerce.footer')
        </footer><!-- End .footer -->
    </div><!-- End .page-wrapper -->

    <div class="mobile-menu-overlay"></div><!-- End .mobil-menu-overlay -->

    <div class="mobile-menu-container">
        @include('ecommerce::layouts.partials_ecommerce.mobile_menu')
    </div><!-- End .mobile-menu-container -->



    <a id="scroll-top" href="#top" title="Top" role="button"><i class="icon-angle-up"></i></a>

     <!-- Plugins JS File -->
    <script src="{{ asset('porto-ecommerce/assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/plugins.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/culqi_v3.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/moment.min.js') }}"></script>

    <!-- Main JS File -->
    <script src="{{ asset('porto-ecommerce/assets/js/main.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/cart.js') }}?v=20260401"></script>
    <!-- Vue, Axios, Element UI ya cargados en <head> -->

    {{-- NO cargar @vite app.js aquí: esta página usa su propia instancia Vue (CDN) --}}
    {{-- El bundle Vite conflicta con new Vue({ el: '#app' }) del carrito/checkout --}}

    @stack('scripts')
    @include('ecommerce::layouts.partials_ecommerce.auth_modal')
</body>

<!-- Mirrored from portotheme.com/html/porto_ecommerce/demo-6/cart.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 07 Sep 2019 03:40:04 GMT -->
</html>
