<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('porto-ecommerce/assets/js/jquery.min.js') }}"></script>

@php
    $seo = \App\Models\Tenant\ConfigurationEcommerce::first() ?? new \App\Models\Tenant\ConfigurationEcommerce();
    $company = \App\Models\Tenant\Company::first();
    $social_scripts = \App\Models\Tenant\ConfigurationScript::where('active', true)->get();
    $path_logos = asset('storage/uploads/logos/');
    $logo_default = asset('porto-ecommerce/assets/images/logo-black.png');
    $v = $company->updated_at ? $company->updated_at->timestamp : time();
    
    if ($company && $company->favicon) {
        $favicon_url = str_contains($company->favicon, 'storage/') 
                       ? asset($company->favicon) 
                       : asset('storage/uploads/favicons/' . $company->favicon);
    } else {
        $favicon_url = asset('porto-ecommerce/assets/images/icons/favicon.ico');
    }
    
    if ($seo->og_image) {
        $share_image = str_contains($seo->og_image, 'storage/') 
                       ? asset($seo->og_image) 
                       : asset('storage/uploads/logos/' . $seo->og_image);
    } else {
        $share_image = $favicon_url;
    }
@endphp

    {{-- SEO: Título dinámico por página --}}
    <title>@yield('page_title', $seo->seo_title ?? $company->name ?? 'Tienda Online')</title>
    <meta name="description" content="@yield('meta_description', $seo->seo_description ?? 'Bienvenido a nuestra tienda.')">
    <meta name="keywords" content="@yield('meta_keywords', $seo->seo_keywords ?? 'ecommerce, tienda, decoración, hogar')">
    <meta name="author" content="{{ $seo->seo_author ?? $company->name }}">

    {{-- SEO: Robots --}}
    @if($seo->indexable)
        <meta name="robots" content="{{ $seo->seo_robots ?? 'index, follow' }}">
    @else
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- SEO: Canonical dinámico por página --}}
    <link rel="canonical" href="@yield('canonical_url', url()->current())">

    {{-- SEO: Paginación rel prev/next --}}
    @hasSection('prev_page')
        <link rel="prev" href="@yield('prev_page')">
    @endif
    @hasSection('next_page')
        <link rel="next" href="@yield('next_page')">
    @endif

    {{-- SEO: Open Graph --}}
    <meta property="og:locale" content="es_PE">
    <meta property="og:title" content="@yield('og_title', $seo->og_title ?? $seo->seo_title ?? $company->name)">
    <meta property="og:description" content="@yield('og_description', $seo->og_description ?? $seo->seo_description)">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('canonical_url', url()->current())">
    <meta property="og:image" content="@yield('og_image', $share_image . '?v=' . $v)">
    <meta property="og:image:secure_url" content="@yield('og_image', $share_image . '?v=' . $v)">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="{{ $company->name ?? 'Tienda Online' }}">

    {{-- SEO: Google Search Console Verification --}}
    @if(!empty($seo->google_site_verification))
        <meta name="google-site-verification" content="{{ $seo->google_site_verification }}">
    @endif

    {{-- SEO: Twitter Cards --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $seo->twitter_title ?? $seo->seo_title ?? $company->name)">
    <meta name="twitter:description" content="@yield('og_description', $seo->twitter_description ?? $seo->seo_description)">
    <meta name="twitter:image" content="@yield('og_image', $share_image . '?v=' . $v)">

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ $favicon_url }}?v={{ $v }}">
    <link rel="apple-touch-icon" href="{{ $favicon_url }}?v={{ $v }}">
    <link rel="shortcut icon" href="{{ $favicon_url }}?v={{ $v }}">

    {{-- SEO: Schema.org JSON-LD para el sitio --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Store",
        "name": "{{ $company->name ?? 'Tienda Online' }}",
        "description": "{{ $seo->seo_description ?? 'Tienda de decoración y hogar' }}",
        "url": "{{ url('/ecommerce') }}",
        "logo": "{{ $share_image }}",
        "image": "{{ $share_image }}",
        @if($company->telephone ?? false)
        "telephone": "{{ $company->telephone }}",
        @endif
        "address": {
            "@type": "PostalAddress",
            "addressCountry": "PE"
            @if($company->address ?? false)
            ,"streetAddress": "{{ $company->address }}"
            @endif
        }
        @if($company->trade_name ?? false)
        ,"alternateName": "{{ $company->trade_name }}"
        @endif
    }
    </script>

    {{-- SEO: BreadcrumbList --}}
    @hasSection('breadcrumb_json')
        @yield('breadcrumb_json')
    @endif

    {{-- Scripts personalizados (head) --}}
    @foreach($social_scripts->where('position', 'head') as $item)
        {!! $item->script !!}
    @endforeach

    {{-- CSS --}}
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/rating.css') }}">
    @vite('resources/js/app.js')
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-light/css/styles_ecommerce.css') }}">
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
        <main class="main" role="main">
            {{-- SEO: Breadcrumbs visibles --}}
            @hasSection('breadcrumbs')
                <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                    <div class="container">
                        @yield('breadcrumbs')
                    </div>
                </nav>
            @endif

            @yield('content')
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
    <script src="{{ asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/plugins.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/cart.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/main.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/vue.min.js') }}"></script>
    @stack('scripts')
</body>

</html>