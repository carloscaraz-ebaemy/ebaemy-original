{{--
    THEME ROPA — Layout del carrito/checkout
    Hereda el layout original pero inyecta estilos del theme
--}}
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>(function(){var t=localStorage.getItem('ec_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}());</script>

    @php
        $company = \App\Models\Tenant\Company::first();
        $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    @endphp
    <title>{{ $company->trade_name ?? $company->name ?? 'Tienda' }} — Carrito</title>

    @if($company && $company->favicon)
    <link rel="icon" href="{{ str_contains($company->favicon, 'storage/') ? asset($company->favicon) : asset('storage/uploads/favicons/' . $company->favicon) }}">
    @endif

    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset(file_exists(public_path('porto-light/css/styles_ecommerce.min.css')) ? 'porto-light/css/styles_ecommerce.min.css' : 'porto-light/css/styles_ecommerce.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-light/css/ecommerce-theme-override.css') }}">

    {{-- Theme CSS --}}
    @php
        $__thm = $econfig->theme_template ?? 'generic';
    @endphp
    @if($__thm !== 'generic' && file_exists(public_path("porto-light/css/themes/{$__thm}.css")))
        <link rel="stylesheet" href="{{ asset("porto-light/css/themes/{$__thm}.css") }}">
    @endif

    {{-- Color primario --}}
    @php
        $__hex = ($econfig->color_ecommerce ?? '#ff8000');
        $__hex = ltrim($__hex, '#');
        if (strlen($__hex) === 3) $__hex = $__hex[0].$__hex[0].$__hex[1].$__hex[1].$__hex[2].$__hex[2];
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

    <style>
        .cart-dropdown .dropdown-toggle { pointer-events: none; cursor: default; }
        .cart-dropdown .ec-minicart-dropdown { display: none !important; }
        .breadcrumb-nav { display: none !important; }

        /* ═══ THEME ROPA — CART/CHECKOUT OVERRIDES ═══ */
        @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&display=swap');

        /* Títulos serif */
        .ec-checkout-card__header span,
        .ec-section-title,
        h1, h2, h3 {
            font-family: 'Cormorant Garamond', Georgia, serif !important;
        }

        /* Cards más elegantes */
        .ec-checkout-card {
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            border-bottom: 1px solid #f3f4f6 !important;
        }
        .ec-checkout-card__header {
            background: transparent !important;
            border-bottom: 1px solid #f3f4f6 !important;
            padding: 20px 0 12px !important;
            font-size: 20px !important;
            letter-spacing: .03em;
        }

        /* Stepper minimalista */
        .ec-step__num {
            background: hsl(var(--primary-h), var(--primary-s), var(--primary-l)) !important;
        }
        .ec-step--done .ec-step__num {
            background: #111827 !important;
            box-shadow: none !important;
        }
        .ec-step__line--done { background: #111827 !important; }
        .ec-step__label {
            font-family: 'Cormorant Garamond', Georgia, serif !important;
            font-size: 14px !important;
            letter-spacing: .02em;
        }

        /* Items del carrito más limpios */
        .ec-cart-item {
            padding: 16px 0 !important;
            border-bottom: 1px solid #f3f4f6 !important;
        }
        .ec-cart-item__img {
            border-radius: 4px !important;
            border: none !important;
        }
        .ec-cart-item__name {
            font-family: 'Cormorant Garamond', Georgia, serif !important;
            font-size: 16px !important;
            font-weight: 500 !important;
        }

        /* Botón continuar al pago — negro elegante */
        .ec-pay-btn--continue {
            background: #111827 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            text-transform: uppercase !important;
            letter-spacing: .12em !important;
            font-family: 'Cormorant Garamond', Georgia, serif !important;
            font-size: 15px !important;
            height: 52px;
        }
        .ec-pay-btn--continue:hover {
            background: #374151 !important;
        }

        /* Total elegante */
        .ec-order-total {
            background: transparent !important;
            border-top: 2px solid #111827 !important;
            padding: 16px 0 !important;
        }
        .ec-order-total strong {
            color: #111827 !important;
            font-family: 'Cormorant Garamond', Georgia, serif !important;
            font-size: 26px !important;
        }

        /* Cupón sin bordes redondeados */
        .ec-coupon-input { border-radius: 0 !important; }
        .ec-coupon-btn { border-radius: 0 !important; background: #111827 !important; }

        /* Qty selector minimal */
        .ec-qty-selector { border-radius: 0 !important; }
        .ec-qty-btn { border-radius: 0 !important; }

        /* Progress bar del checkout */
        .ec-progress-step--active .ec-progress-num,
        .ec-progress-step--done .ec-progress-num {
            background: #111827 !important;
        }
        .ec-progress-line--done { background: #111827 !important; }

        /* Trust badges elegantes */
        .ec-secure-note {
            background: transparent !important;
            border: none !important;
            font-family: 'Cormorant Garamond', Georgia, serif !important;
            font-size: 12px !important;
            letter-spacing: .05em;
        }

        /* Formularios del checkout */
        .ec-field__input-wrap {
            border-radius: 0 !important;
        }
        .ec-field__input-wrap:focus-within {
            border-color: #111827 !important;
            box-shadow: 0 0 0 1px #111827 !important;
        }

        /* Cash/Visa buttons */
        .ec-pay-btn--visa {
            background: #111827 !important;
            border-radius: 0 !important;
        }
        .ec-pay-btn--cash {
            border-radius: 0 !important;
            border-color: #111827 !important;
        }
    </style>

    <link rel="stylesheet" href="https://unpkg.com/element-ui/lib/theme-chalk/index.css">
    <script src="{{ asset('porto-ecommerce/assets/js/vue.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/axios.min.js') }}"></script>
    <script src="https://unpkg.com/element-ui/lib/index.js"></script>
    <script src="https://unpkg.com/element-ui/lib/umd/locale/es.js"></script>
</head>
<body>
    <div class="page-wrapper">
        @include('ecommerce::layouts.partials_ecommerce.header')
        <main class="main" style="min-height:60vh">
            <div class="container" style="max-width:1100px">
                @yield('content')
            </div>
            <div class="mb-6"></div>
        </main>
        <footer class="footer">
            @include('ecommerce::layouts.partials_ecommerce.footer')
        </footer>
    </div>

    <a id="scroll-top" href="#top" title="Top" role="button"><i class="icon-angle-up"></i></a>

    <script src="{{ asset('porto-ecommerce/assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/plugins.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/culqi_v3.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/moment.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/main.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/cart.js') }}"></script>

    @stack('scripts')
    @include('ecommerce::layouts.partials_ecommerce.auth_modal')
</body>
</html>
