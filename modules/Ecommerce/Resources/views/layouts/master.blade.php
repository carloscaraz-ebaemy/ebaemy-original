<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    {{-- SEO Dinámico --}}
    @php
        $seo = \App\Models\Tenant\ConfigurationEcommerce::first();
    @endphp

    <title>{{ $seo->seo_title ?? 'eCommerce' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="keywords" content="{{ $seo->seo_keywords ?? 'ecommerce' }}">
    <meta name="description" content="{{ $seo->seo_description ?? 'eCommerce' }}">
    <meta name="author" content="SW-THEMES">

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $seo->og_title ?? $seo->seo_title ?? 'eCommerce' }}">
    <meta property="og:description" content="{{ $seo->og_description ?? $seo->seo_description ?? '' }}">
    <meta property="og:image" content="{{ $seo->og_image ? asset('storage/' . $seo->og_image) : asset('porto-ecommerce/assets/images/logo-black.png') }}">
    <meta property="og:type" content="website">

    {{-- Twitter Card --}}
    <meta name="twitter:title" content="{{ $seo->twitter_title ?? $seo->seo_title ?? 'eCommerce' }}">
    <meta name="twitter:description" content="{{ $seo->twitter_description ?? $seo->seo_description ?? '' }}">
    <meta name="twitter:image" content="{{ $seo->twitter_image ? asset('storage/' . $seo->twitter_image) : asset('porto-ecommerce/assets/images/logo-black.png') }}">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('porto-ecommerce/assets/images/icons/favicon.ico') }}">

    <!-- Plugins CSS File -->
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/bootstrap.min.css') }}">

    <!-- Main CSS File -->
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/rating.css') }}">

    @vite('resources/js/app.js')

    <!-- Fontawesome -->
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css') }}">
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="{{ asset('porto-light/css/styles_ecommerce.css') }}" />
</head>

<body>
    <div class="page-wrapper">

        @include('ecommerce::layouts.partials_ecommerce.header')
        
        <main class="main">
            @yield('content')
        </main><!-- End .main -->

        <footer class="footer">
            @include('ecommerce::layouts.partials_ecommerce.footer')
        </footer><!-- End .footer -->
    </div><!-- End .page-wrapper -->

    <div class="mobile-menu-overlay"></div><!-- End .mobile-menu-overlay -->

    <div class="mobile-menu-container">
        @include('ecommerce::layouts.partials_ecommerce.mobile_menu')
    </div><!-- End .mobile-menu-container -->

    <div class="newsletter-popup mfp-hide" id="newsletter-popup-form">
        <div class="newsletter-popup-content">
            <img src="{{ asset('porto-ecommerce/assets/images/logo-black.png') }}" alt="Logo" class="logo-newsletter">
            <h2>BE THE FIRST TO KNOW</h2>
            <p>Subscribe to the Porto eCommerce newsletter to receive timely updates from your favorite products.</p>
            <form action="#">
                <div class="input-group">
                    <input type="email" class="form-control" id="newsletter-email" name="newsletter-email"
                        placeholder="Email address" required>
                    <input type="submit" class="btn" value="Go!">
                </div>
            </form>
            <div class="newsletter-subscribe">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" value="1">
                        Don't show this popup again
                    </label>
                </div>
            </div>
        </div>
    </div>

    <a id="scroll-top" href="#top" title="Top" role="button"><i class="icon-angle-up"></i></a>

    <!-- Plugins JS File -->
    <script src="{{ asset('porto-ecommerce/assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/plugins.min.js') }}"></script>

    <!-- Main JS File -->
    <script src="{{ asset('porto-ecommerce/assets/js/cart.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/main.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/vue.min.js') }}"></script>

    @stack('scripts')
</body>

</html>
