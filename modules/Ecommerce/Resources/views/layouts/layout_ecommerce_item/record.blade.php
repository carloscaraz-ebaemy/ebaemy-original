<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>(function(){var t=localStorage.getItem('ec_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}());</script>

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

    {{-- SEO: Product Schema --}}
    @if(isset($record))
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "{{ $record->description }}",
        "image": "{{ asset('storage/uploads/items/' . $record->image) }}",
        "description": "{{ strip_tags($record->description) }}",
        "sku": "{{ $record->internal_id }}",
        "brand": { "@type": "Brand", "name": "{{ $record->brand->name ?? '' }}" },
        "offers": {
            "@type": "Offer",
            "priceCurrency": "PEN",
            "price": "{{ $record->sale_unit_price }}",
            "availability": "{{ $record->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}"
        }
    }
    </script>
    @endif

    {{-- SEO: BreadcrumbList Schema --}}
    @if(isset($record))
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Inicio",
                "item": "{{ url('/ecommerce') }}"
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "{{ $record->category->name ?? 'Productos' }}",
                "item": "{{ url('/ecommerce') }}"
            },
            {
                "@type": "ListItem",
                "position": 3,
                "name": "{{ $record->description }}"
            }
        ]
    }
    </script>
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
    <link rel="stylesheet" href="{{ asset(file_exists(public_path('porto-light/css/styles_ecommerce.min.css')) ? 'porto-light/css/styles_ecommerce.min.css' : 'porto-light/css/styles_ecommerce.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-light/css/ecommerce-theme-override.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drift-zoom@1/dist/drift-basic.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css">
    @php $__thm = ($seo->theme_template ?? 'generic'); @endphp
    @if($__thm !== 'generic' && file_exists(public_path("porto-light/css/themes/{$__thm}.css")))
        <link rel="stylesheet" href="{{ asset("porto-light/css/themes/{$__thm}.css") }}">
    @endif
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    @php
        $__hex = $seo->color_ecommerce ?? '#ff8000';
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
    <!-- Vue debe cargarse ANTES del header (que usa new Vue) -->
    <script src="{{ asset('porto-ecommerce/assets/js/vue.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/axios.min.js') }}"></script>
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

            {{-- Productos relacionados ahora se muestran en items/partials/related_products.blade.php --}}
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
    <!-- Vue ya cargado en <head> -->
    <script src="{{ asset('porto-ecommerce/assets/js/rating.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/tracker.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/wishlist.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/cart.js') }}?v=20260401"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/stock-notify.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/product-gallery.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/image-zoom.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/recently-viewed.js?v=20260404') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/compare.js') }}"></script>
    {{-- NO cargar @vite app.js: el ecommerce usa su propia instancia Vue (CDN) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/drift-zoom@1/dist/Drift.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js"></script>
    @include('themes._partials.plugins.product-enhancements')
    @include('themes._partials.plugins.init')
    @stack('scripts')
    @include('ecommerce::layouts.partials_ecommerce.auth_modal')
</body>

</html>