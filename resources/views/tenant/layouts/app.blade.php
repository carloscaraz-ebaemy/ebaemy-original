<!DOCTYPE html>
@php
    $path = explode('/', request()->path());
    $path[1] = (array_key_exists(1, $path) > 0) ? $path[1] : '';
    $path[2] = (array_key_exists(2, $path) > 0) ? $path[2] : '';
    $path[0] = ($path[0] === '') ? 'documents' : $path[0];
    $visual->sidebar_theme = property_exists($visual, 'sidebar_theme') ? $visual->sidebar_theme : '';
    $visual->sidebar_margin = property_exists($visual, 'sidebar_margin') ? (bool)$visual->sidebar_margin : true;
    $sidebar_mode = isset($vc_compact_sidebar) ? ($vc_compact_sidebar->sidebar_mode ?? 'light') : 'light';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fixed no-mobile-device custom-scroll
        sidebar-white sidebar-light {{ $sidebar_mode === 'dark' ? 'sidebarMode-dark' : 'sidebarMode-light' }}
        {{ $visual->sidebar_margin ? 'sidebar-left-floating' : 'sidebar-left-fixed' }}
        {{$vc_compact_sidebar->compact_sidebar == true
    || $path[0] === 'pos'
    || $path[0] === 'pos' && $path[1] === 'fast'
    || $path[0] === 'documents' && $path[1] === 'create' ? 'sidebar-left-collapsed' : ''}}
        {{-- header-{{$visual->navbar ?? 'fixed'}} --}}
        {{-- {{$visual->header == 'dark' ? 'header-dark' : ''}} --}}
        {{-- {{$visual->sidebars == 'dark' ? '' : 'sidebar-light'}} --}}
        {{$visual->bg == 'dark' ? 'dark' : ''}}
        {{ ($path[0] === 'documents' && $path[1] === 'create'
    || $path[0] === 'documents' && $path[1] === 'note'
    || $path[0] === 'quotations' && $path[1] === 'create'
    || $path[0] === 'sale-opportunities' && $path[1] === 'create'
    || $path[0] === 'order-notes' && $path[1] === 'create'
    || $path[0] === 'sale-notes' && $path[1] === 'create'
    || $path[0] === 'purchase-quotations' && $path[1] === 'create'
    || $path[0] === 'purchase-orders' && $path[1] === 'create'
    || $path[0] === 'dispatches' && $path[1] === 'create'
    || $path[0] === 'purchases' && $path[1] === 'create') ? 'newinvoice' : ''}}
        ">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $vc_company->title_web }}</title>
    <meta name="googlebot" content="noindex">
    <meta name="robots" content="noindex">

    @php
        $themeName = $visual->sidebar_theme ?? '';
        $themeVars = '';
        if ($themeName) {
            $themesPath = public_path('json/themes/themes.json');
            if (file_exists($themesPath)) {
                $allThemes = json_decode(file_get_contents($themesPath), true);
                $colors = $allThemes[$themeName] ?? null;
                if ($colors) {
                    $first = reset($colors);
                    if (is_array($first) && !str_starts_with(array_key_first($colors), '--')) {
                        $colors = $colors['default'] ?? $colors['light'] ?? $colors;
                    }
                    $tokenMap = [
                        '--p'      => $colors['--primary-color'] ?? null,
                        '--p-d'    => $colors['--primary-color'] ?? null,
                        '--p-l'    => $colors['--accent-color'] ?? null,
                        '--bg'     => $colors['--light-color'] ?? null,
                        '--t1'     => $colors['--dark-color'] ?? null,
                        '--t2'     => $colors['--muted'] ?? null,
                        '--t3'     => $colors['--muted'] ?? null,
                        '--bd'     => $colors['--accent-color'] ?? null,
                        '--th'     => $colors['--primary-color'] ?? null,
                        '--sb-acc' => $colors['--highlight-color'] ?? $colors['--primary-color'] ?? null,
                        '--sb-act' => $colors['--primary-color'] ?? null,
                        '--font'   => $colors['--font-family'] ?? null,
                    ];
                    $vars = '';
                    foreach ($colors as $k => $v) { $vars .= "{$k}: {$v}; "; }
                    foreach ($tokenMap as $k => $v) { if ($v) $vars .= "{$k}: {$v}; "; }
                    $themeVars = $vars;
                }
            }
        }
    @endphp

    @if($themeVars)
        <style id="theme-styles">:root { {!! $themeVars !!} }</style>
    @endif

    <script>
        window.vc_visual = window.vc_visual || {};
        window.vc_visual.sidebar_theme = @json($visual->sidebar_theme);
    </script>

    @vite(['resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">

    {{-- <link rel="stylesheet" href="{{ asset('porto-light/vendor/bootstrap/css/bootstrap.css') }}" /> --}}
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/animate/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/font-awesome/5.11/css/all.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/meteocons/css/meteocons.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/select2/css/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/select2-bootstrap-theme/select2-bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/datatables/media/css/dataTables.bootstrap4.css') }}" />
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.26.29/sweetalert2.min.css" />
    <link rel="stylesheet" href="{{asset('porto-light/vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css')}}" />

    <link rel="stylesheet" href="{{asset('porto-light/vendor/jquery-ui/jquery-ui.css')}}" />
    <link rel="stylesheet" href="{{asset('porto-light/vendor/jquery-ui/jquery-ui.theme.css')}}" />
    <link rel="stylesheet" href="{{asset('porto-light/vendor/select2/css/select2.css')}}" />
    <link rel="stylesheet" href="{{asset('porto-light/vendor/select2-bootstrap-theme/select2-bootstrap.min.css')}}" />

    <link href="{{ asset('porto-light/vendor/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet">
    <link href="{{ asset('porto-light/vendor/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet">

    <link rel="stylesheet" href="{{asset('porto-light/vendor/bootstrap-timepicker/css/bootstrap-timepicker.css')}}" />

    <link rel="stylesheet" href="{{asset('porto-light/vendor/jquery-loading/dist/jquery.loading.css')}}" />

    <link rel="stylesheet" type="text/css" href="{{ asset('porto-light/master/style-switcher/style-switcher.css')}}">

    <link rel="stylesheet" href="{{ asset('porto-light/css/theme.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/css/custom.css') }}" />

    @if (file_exists(public_path('theme/custom_styles.css')))
        <link rel="stylesheet" href="{{ asset('theme/custom_styles.css') }}" />
    @endif

    @if($vc_compact_sidebar->skin)
        @if (file_exists(storage_path('app/public/skins/' . $vc_compact_sidebar->skin->filename)))
            <link rel="stylesheet" href="{{ asset('storage/skins/' . $vc_compact_sidebar->skin->filename) }}" />
        @endif
    @endif

    {{-- Sidebar styles now handled entirely by erp-redesign.scss via Vite --}}

    @stack('styles')


    <script src="{{ asset('porto-light/vendor/modernizr/modernizr.js') }}"></script>

    <style>
        html.sidebar-left-opened,
        html.options-user-mobile-opened {
            overflow: hidden !important;
        }

        .descarga {
            color: black;
            padding: 5px;
        }

        .el-checkbox__label {
            font-size: 13px;
        }

        .center-el-checkbox {
            display: flex;
            align-items: center;
        }

        .center-el-checkbox .el-checkbox {
            margin-bottom: 0
        }

        .logo-light {
            display: block;
        }

        .logo-dark {
            display: none;
        }

        html.dark .logo-light {
            display: var(--show-light-logo, none);
        }

        html.dark .logo-dark {
            display: var(--show-dark-logo, block);
        }
    </style>

    @if ($vc_company->favicon)
        <link rel="shortcut icon" type="image/png" href="{{ asset($vc_company->favicon) }}" />
    @endif

    <script async src="https://social.buho.la/pixel/y9nonmie9j8dkwha20ct2ua7nwsywi2m"></script>
    <script src="{{ asset('js/dark-mode.js') }}"></script>
</head>

<body class="pr-0"
    data-tenant="true"
    data-company-title="{{ $vc_company->title_web }}">
    <section class="body">
        <!-- start: header -->
        {{-- @include('tenant.layouts.partials.header') --}}
        <!-- end: header -->
        <div class="inner-wrapper">
            <!-- start: sidebar -->
            @include('tenant.layouts.partials.sidebar')
            <!-- end: sidebar -->
            <section role="main" class="content-body" id="main-wrapper">
                @include('tenant.layouts.partials.header')
                @yield('content')
                @include('tenant.layouts.partials.sidebar_styles')
                {{-- @include('tenant.layouts.partials.sidebar_establishment') --}}

                @include('tenant.layouts.partials.check_last_password_update')

                <command-palette></command-palette>
            </section>

            @yield('package-contents')
        </div>
    </section>
    @if($show_ws)
        @if(strlen($phone_whatsapp) > 0)
            <a class='ws-flotante d-flex align-items-center justify-content-center' href='https://wa.me/{{$phone_whatsapp}}'
                target="BLANK"
                style="font-size: 45px; color: #fff !important; background-color: #0074ff; text-decoration: none; border-radius: 30% !important;">
                <i class="fab fa-whatsapp"></i>
            </a>
        @endif
    @endif


    <!-- Vendor -->
    <script src="{{ asset('porto-light/vendor/jquery/jquery.js')}}"></script>
    <script src="{{ asset('porto-light/vendor/jquery-browser-mobile/jquery.browser.mobile.js')}}"></script>
    <script src="{{ asset('porto-light/vendor/jquery-cookie/jquery-cookie.js')}}"></script>
    {{--
    <script src="{{ asset('porto-light/master/style-switcher/style.switcher.js')}}"></script> --}}
    <script src="{{ asset('porto-light/vendor/popper/umd/popper.min.js')}}"></script>
    {{-- <script src="{{ asset('porto-light/vendor/bootstrap/js/bootstrap.js')}}"></script> --}}
    {{--
    <script src="{{ asset('porto-light/vendor/common/common.js')}}"></script> --}}
    <script src="{{ asset('porto-light/vendor/nanoscroller/nanoscroller.js')}}"></script>
    <script src="{{ asset('porto-light/vendor/magnific-popup/jquery.magnific-popup.js')}}"></script>
    <script src="{{ asset('porto-light/vendor/jquery-placeholder/jquery-placeholder.js')}}"></script>
    <script src="{{ asset('porto-light/vendor/select2/js/select2.js') }}"></script>
    <script src="{{ asset('porto-light/vendor/datatables/media/js/jquery.dataTables.min.js')}}"></script>
    <script src="{{ asset('porto-light/vendor/datatables/media/js/dataTables.bootstrap4.min.js')}}"></script>

    {{-- Specific Page Vendor --}}
    <script src="{{asset('porto-light/vendor/jquery-ui/jquery-ui.js')}}"></script>
    <script src="{{asset('porto-light/vendor/jqueryui-touch-punch/jqueryui-touch-punch.js')}}"></script>
    <!--<script src="{{asset('porto-light/vendor/select2/js/select2.js')}}"></script>-->

    <script src="{{asset('porto-light/vendor/jquery-loading/dist/jquery.loading.js')}}"></script>

    <!--<script src="assets/vendor/select2/js/select2.js"></script>-->
    {{--
    <script src="{{asset('porto-light/vendor/bootstrap-multiselect/bootstrap-multiselect.js')}}"></script>--}}

    <!-- Moment -->
    {{--
    <script src="{{ asset('porto-light/vendor/moment/moment.js') }}"></script>--}}

    <!-- DatePicker -->
    {{--
    <script src="{{asset('porto-light/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js')}}"></script>--}}

    <!-- Date range Plugin JavaScript -->
    {{--
    <script src="{{ asset('porto-light/vendor/bootstrap-timepicker/bootstrap-timepicker.js') }}"></script>--}}
    {{--
    <script src="{{ asset('porto-light/vendor/bootstrap-daterangepicker/daterangepicker.js') }}"></script>--}}

    <!-- Theme Initialization Files -->
    {{--
    <script src="{{asset('porto-light/js/theme.init.js')}}"></script> --}}

    {{--
    <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>--}}
    {{--
    <script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js"></script>--}}

    @stack('scripts')

    <script src="{{ asset('js/sign-message.js') }}"></script>
    <script src="{{ asset('js/sha-256.min.js') }}"></script>
    <script src="{{ asset('js/rsvp-3.1.0.min.js') }}"></script>
    <script src="{{ asset('js/qz-tray.js') }}"></script>
    {{-- <script src="{{ asset('js/vendor.js') }}"></script> --}}
    <!-- Theme Base, Components and Settings -->
    <script src="{{asset('porto-light/js/theme.js')}}"></script>

    <!-- Theme Custom -->
    <script src="{{asset('porto-light/js/custom.js')}}"></script>
    <script src="{{asset('porto-light/js/jquery.xml2json.js')}}"></script>

    <script>

        function parseXMLToJSON(source) {
            let transform = $.xml2json(source);
            return transform
        }

        $(document).ready(function () {
            $('#dropdown-notifications').click(function (e) {
                $('#dropdown-notifications').toggleClass('showed');
                $('#dn-toggle').toggleClass('show');
                $('#dn-menu').toggleClass('show');
                e.stopPropagation();
            });
        });

        $(document).click(function () {
            $('#dropdown-notifications').removeClass('showed');
            $('#dn-toggle').removeClass('show');
            $('#dn-menu').removeClass('show');
        });

    </script>
    <!-- <script src="//code.tidio.co/1vliqewz9v7tfosw5wxiktpkgblrws5w.js"></script> -->

    {{-- ── Notificaciones de despacho al vendedor ──────────────────────── --}}
    @php
        $tenantUuid     = app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? 'default';
        $dnKey          = "dispatch_notifications_{$tenantUuid}_" . auth()->id();
        $dispatchNotifs = \Illuminate\Support\Facades\Cache::pull($dnKey, []);
    @endphp
    @if(!empty($dispatchNotifs))
    <div id="dispatch-toast-container"
         style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;max-width:340px;">
    </div>
    <script>
    (function(){
        var notifs = @json($dispatchNotifs);
        var container = document.getElementById('dispatch-toast-container');
        if (!container) return;

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        notifs.forEach(function(n, i){
            var isPickup = n.type === 'RECOGIDO';
            var icon     = isPickup ? '🏪' : '🚚';
            var color    = isPickup ? '#0d6efd' : '#198754';
            var title    = isPickup ? 'Pedido Entregado' : 'Pedido Despachado';

            var div = document.createElement('div');
            div.style.cssText = 'background:white;border-left:4px solid ' + color + ';border-radius:8px;padding:12px 14px;box-shadow:0 4px 15px rgba(0,0,0,.15);animation:slideIn .3s ease;opacity:1;transition:opacity .4s';
            div.innerHTML =
                '<div style="display:flex;justify-content:space-between;align-items:flex-start">' +
                  '<strong style="color:' + color + '">' + icon + ' ' + title + '</strong>' +
                  '<button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#aaa;line-height:1">×</button>' +
                '</div>' +
                '<div style="margin-top:4px;font-size:13px"><strong>' + esc(n.number) + '</strong> — ' + esc(n.customer) + '</div>' +
                (n.courier ? '<div style="font-size:12px;color:#666;margin-top:2px">Courier: ' + esc(n.courier) + (n.tracking ? ' · Guía: ' + esc(n.tracking) : '') + '</div>' : '') +
                (n.pickup_person ? '<div style="font-size:12px;color:#666;margin-top:2px">Recogió: ' + esc(n.pickup_person) + '</div>' : '') +
                '<div style="font-size:11px;color:#aaa;margin-top:4px">' + esc(n.dispatched_at) + '</div>';

            setTimeout(function(){ container.appendChild(div); }, i * 400);

            // Auto-cerrar a los 12 segundos
            setTimeout(function(){
                div.style.opacity = '0';
                setTimeout(function(){ if(div.parentElement) div.remove(); }, 400);
            }, 12000 + i * 400);
        });
    })();
    </script>
    <style>
    @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
    @endif

    @if(in_array(auth()->user()->type ?? '', ['admin', 'superadmin', 'warehouse']) && in_array('logistic', $vc_modules ?? []) && in_array('logistic', $vc_module_levels ?? []))
    <script>
    (function logisticBadgePolling() {
        const INTERVAL = 20000; // cada 20 segundos
        const url      = '{{ route('logistic.sale_notes.queue_count') }}';

        function updateBadges(count, hasUrgent) {
            document.querySelectorAll('.logistic-queue-badge').forEach(function(el) {
                if (count > 0) {
                    el.textContent = count;
                    el.style.display = '';
                    el.className = 'badge ms-1 logistic-queue-badge ' + (hasUrgent ? 'bg-warning' : 'bg-danger');
                } else {
                    el.style.display = 'none';
                }
            });
        }

        function fetchCount() {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.ok ? r.json() : null; })
                .then(function(data) {
                    if (!data) return;
                    updateBadges(data.total_pending, data.has_urgent);
                })
                .catch(function() {});
        }

        // Llamar inmediatamente al cargar la página
        fetchCount();
        // Y luego repetir cada 20 segundos
        setInterval(fetchCount, INTERVAL);
    })();
    </script>
    @endif
</body>

</html>