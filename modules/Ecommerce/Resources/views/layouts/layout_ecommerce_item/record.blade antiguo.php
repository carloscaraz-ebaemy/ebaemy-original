<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $seo      = \App\Models\Tenant\ConfigurationEcommerce::first() ?? new \App\Models\Tenant\ConfigurationEcommerce();
        $company  = \App\Models\Tenant\Company::first();
        $v        = $company && $company->updated_at ? $company->updated_at->timestamp : time();

        if ($company && $company->favicon) {
            $favicon_url = str_contains($company->favicon, 'storage/')
                ? asset($company->favicon)
                : asset('storage/uploads/favicons/' . $company->favicon);
        } else {
            $favicon_url = asset('porto-ecommerce/assets/images/icons/favicon.ico');
        }

        if ($seo && $seo->og_image) {
            $share_image = str_contains($seo->og_image, 'storage/')
                ? asset($seo->og_image)
                : asset('storage/uploads/logos/' . $seo->og_image);
        } else {
            $share_image = $favicon_url;
        }
    @endphp

    {{-- SEO: Título dinámico --}}
    <title>@yield('page_title', 'Producto | ' . ($company->name ?? 'Tienda Online'))</title>

    {{-- SEO: Meta tags --}}
    <meta name="description" content="@yield('meta_description', $seo->seo_description ?? 'Tienda Online')">
    <meta name="keywords"    content="@yield('meta_keywords', $seo->seo_keywords ?? 'ecommerce, tienda')">
    <meta name="author"      content="{{ $company->name ?? 'Tienda Online' }}">

    {{-- SEO: Robots --}}
    @if($seo && $seo->indexable)
        <meta name="robots" content="index, follow">
    @else
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- SEO: Canonical --}}
    <link rel="canonical" href="@yield('canonical_url', url()->current())">

    {{-- SEO: Open Graph --}}
    <meta property="og:locale"            content="es_PE">
    <meta property="og:type"              content="@yield('og_type', 'product')">
    <meta property="og:title"             content="@yield('og_title', $seo->og_title ?? ($company->name ?? 'Tienda Online'))">
    <meta property="og:description"       content="@yield('og_description', $seo->seo_description ?? '')">
    <meta property="og:url"               content="@yield('canonical_url', url()->current())">
    <meta property="og:image"             content="@yield('og_image', $share_image)">
    <meta property="og:image:secure_url"  content="@yield('og_image', $share_image)">
    <meta property="og:image:width"       content="1200">
    <meta property="og:image:height"      content="630">
    <meta property="og:site_name"         content="{{ $company->name ?? 'Tienda Online' }}">

    {{-- SEO: Twitter Cards --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="@yield('og_title', $company->name ?? 'Tienda Online')">
    <meta name="twitter:description" content="@yield('og_description', $seo->seo_description ?? '')">
    <meta name="twitter:image"       content="@yield('og_image', $share_image)">

    {{-- SEO: Google Verification --}}
    @if(!empty($seo->google_site_verification))
        <meta name="google-site-verification" content="{{ $seo->google_site_verification }}">
    @endif

    {{-- SEO: Schema.org JSON-LD producto --}}
    @hasSection('schema_product')
        @yield('schema_product')
    @endif

    {{-- Favicon --}}
    <link rel="icon"             type="image/png" href="{{ $favicon_url }}?v={{ $v }}">
    <link rel="apple-touch-icon"                  href="{{ $favicon_url }}?v={{ $v }}">
    <link rel="shortcut icon"                     href="{{ $favicon_url }}?v={{ $v }}">

    {{-- CSS --}}
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/rating.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-light/css/styles_ecommerce.css') }}">
</head>

<body>
    <div class="page-wrapper">

        @include('ecommerce::layouts.partials_ecommerce.header')
        @include('ecommerce::layouts.partials_ecommerce.header_bottom_sticky')

        <main class="main" role="main">
            <nav aria-label="breadcrumb" class="breadcrumb-nav">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ url('/ecommerce') }}"><i class="icon-home"></i></a>
                        </li>
                        @hasSection('breadcrumb_item')
                            @yield('breadcrumb_item')
                        @endif
                    </ol>
                </div>
            </nav>

            <div class="container">
                <div class="row">
                    <div class="col-lg-9">
                        @yield('content')
                    </div>
                    <div class="col-lg-3 sidebar-right">
                        <div class="sidebar-overlay"></div>
                        <div class="sidebar-toggle"><i class="icon-sliders"></i></div>
                        @include('ecommerce::layouts.partials_ecommerce.sidebar_product_right')
                    </div>
                </div>
            </div>

            <div class="featured-section">
                @include('ecommerce::layouts.partials_ecommerce.featured_products_bottom')
            </div>
        </main>

        <footer class="footer" role="contentinfo">
            @include('ecommerce::layouts.partials_ecommerce.footer')
        </footer>

    </div>

    <div class="mobile-menu-overlay"></div>
    <div class="mobile-menu-container">
        @include('ecommerce::layouts.partials_ecommerce.mobile_menu')
    </div>

    <a id="scroll-top" href="#top" title="Volver arriba" role="button"><i class="icon-angle-up"></i></a>

    {{-- JS --}}
    <script src="{{ asset('porto-ecommerce/assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/plugins.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/main.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/vue.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/rating.js') }}"></script>
    @vite('resources/js/app.js')
    @stack('scripts')

</body>

</html>