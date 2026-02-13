<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $seo = \App\Models\Tenant\ConfigurationEcommerce::first() ?? new \App\Models\Tenant\ConfigurationEcommerce();
    $social_scripts = \App\Models\Tenant\ConfigurationScript::where('active', true)->get();
    
    $path_logos = asset('storage/uploads/logos/');
    $logo_default = asset('porto-ecommerce/assets/images/logo-black.png');
@endphp

<title>{{ $seo->seo_title ?? $company->name ?? 'Tienda Online' }}</title>
<meta name="description" content="{{ $seo->seo_description ?? 'Bienvenido a nuestra tienda. Encuentra los mejores productos aquí.' }}">
<meta name="keywords" content="{{ $seo->seo_keywords ?? 'ecommerce, tienda, online' }}">
<meta name="author" content="{{ $seo->seo_author ?? $company->name }}">

@if($seo->indexable)
    <meta name="robots" content="{{ $seo->seo_robots ?? 'index, follow' }}">
@else
    <meta name="robots" content="noindex, nofollow">
@endif

<link rel="canonical" href="{{ $seo->canonical_url ?? url()->current() }}">

<meta property="og:title" content="{{ $seo->og_title ?? $seo->seo_title ?? $company->name }}">
<meta property="og:description" content="{{ $seo->og_description ?? $seo->seo_description }}">
<meta property="og:type" content="{{ $seo->og_type ?? 'website' }}">
<meta property="og:url" content="{{ url()->current() }}">

<meta property="og:image" content="{{ $seo->og_image ? $path_logos . '/' . $seo->og_image : $logo_default }}">

<meta name="twitter:card" content="{{ $seo->twitter_card ?? 'summary_large_image' }}">
<meta name="twitter:title" content="{{ $seo->twitter_title ?? $seo->seo_title ?? $company->name }}">
<meta name="twitter:description" content="{{ $seo->twitter_description ?? $seo->seo_description }}">
<meta name="twitter:image" content="{{ $seo->twitter_image ? $path_logos . '/' . $seo->twitter_image : ($seo->og_image ? $path_logos . '/' . $seo->og_image : $logo_default) }}">


@foreach($social_scripts->where('position', 'head') as $item)
    {!! $item->script !!}
@endforeach

   @if($social_scripts->where('position', 'head')->isNotEmpty())
        @foreach($social_scripts->where('position', 'head') as $item)
            @if(!empty($item->script))
                {!! $item->script !!}
            @endif
        @endforeach
    @endif

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
   @if($social_scripts->where('position', 'body')->isNotEmpty())
        @foreach($social_scripts->where('position', 'body') as $item)
            @if(!empty($item->script))
                {!! $item->script !!}
            @endif
        @endforeach
    @endif


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
