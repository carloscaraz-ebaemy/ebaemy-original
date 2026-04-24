@php
    $path = explode('/', request()->path());
    $path[1] = (array_key_exists(1, $path)> 0)?$path[1]:'';
    $path[2] = (array_key_exists(2, $path)> 0)?$path[2]:'';
    $path[0] = ($path[0] === '')?'documents':$path[0];
@endphp
<aside id="sidebar-left" class="sidebar-left mt-0" style="z-index: 900">
    <div class="nano px-2">
        <div class="nano-content">
            <nav id="menu" class="nav-main pt-1" role="navigation">
                <ul class="nav nav-main">

                    {{-- ── PRINCIPAL ───────────────────────────────────── --}}
                    <li class="nav-separator">Principal</li>

                    <li class="{{ (in_array($path[0], ['clients', 'dashboard']))?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.dashboard')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /><path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /></svg>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <li class="{{ ($path[0] === 'analytics')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.analytics')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 3v18h18" /><path d="M7 16l4 -7l4 4l4 -5" /></svg>
                            <span>Analytics</span>
                        </a>
                    </li>

                    {{-- ── CLIENTES ─────────────────────────────────────── --}}
                    <li class="nav-separator">Clientes</li>

                    <li class="{{ ($path[0] === 'plans')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.plans.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" /><path d="M9 7l6 0" /><path d="M9 11l6 0" /><path d="M9 15l4 0" /></svg>
                            <span>Planes</span>
                        </a>
                    </li>

                    <li class="{{ ($path[0] === 'themes')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.themes.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18z"/><path d="M3.6 9h16.8"/><path d="M3.6 15h16.8"/><path d="M12 3a15 15 0 0 1 4 9 15 15 0 0 1-4 9 15 15 0 0 1-4-9 15 15 0 0 1 4-9z"/></svg>
                            <span>Themes</span>
                        </a>
                    </li>

                    @if(config('configuration.multi_user_enabled'))
                    <li class="{{ ($path[0] === 'multi-users')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.multi-users.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 13a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" /><path d="M15 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 10h2a2 2 0 0 1 2 2v1" /><path d="M5 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M3 13v-1a2 2 0 0 1 2 -2h2" /></svg>
                            <span>Multi Usuarios</span>
                        </a>
                    </li>
                    @endif

                    <li class="{{ ($path[0] === 'payment-orders')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.payments.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 10l18 0" /><path d="M7 15l.01 0" /><path d="M11 15l2 0" /></svg>
                            <span>Pagos</span>
                        </a>
                    </li>

                    {{-- ── FACTURACIÓN ──────────────────────────────────── --}}
                    <li class="nav-separator">Facturación</li>

                    <li class="{{ ($path[0] === 'massive-invoice')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.massive-invoice.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 3v4a1 1 0 0 0 1 1h4" /><path d="M18 17h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h4l5 5v7a2 2 0 0 1 -2 2z" /><path d="M16 17v2a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h2" /></svg>
                            <span>Facturación Masiva</span>
                        </a>
                    </li>

                    <li class="{{ ($path[0] === 'accounting')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.accounting.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 3m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M8 7m0 1a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v1a1 1 0 0 1 -1 1h-6a1 1 0 0 1 -1 -1z" /><path d="M8 14l0 .01" /><path d="M12 14l0 .01" /><path d="M16 14l0 .01" /><path d="M8 17l0 .01" /><path d="M12 17l0 .01" /><path d="M16 17l0 .01" /></svg>
                            <span>Contabilidad</span>
                        </a>
                    </li>

                    <li class="{{ ($path[0] === 'reports')?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.list-reports') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 17v-5" /><path d="M12 17v-1" /><path d="M15 17v-3" /></svg>
                            <span>Reportes</span>
                        </a>
                    </li>

                    {{-- ── MARKETPLACE (agregador central) ──────────────── --}}
                    <li class="nav-separator">Marketplace</li>

                    <li class="{{ (isset($path[1]) && $path[1] === 'marketplace' && empty($path[2]))?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.marketplace.dashboard') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4 -7l4 4l4 -5"/></svg>
                            <span>Dashboard Marketplace</span>
                        </a>
                    </li>

                    <li class="{{ (isset($path[1]) && $path[1] === 'marketplace' && $path[2] === 'listings')?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.marketplace.listings') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l3 -3h12l3 3"/><path d="M3 7v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2 -2v-12"/><path d="M9 7v4a3 3 0 0 1 -6 0v-4"/><path d="M15 7v4a3 3 0 0 0 6 0v-4"/><path d="M12 7v4a3 3 0 0 1 -6 0"/><path d="M12 7v4a3 3 0 0 0 6 0"/></svg>
                            <span>Productos publicados</span>
                        </a>
                    </li>

                    <li class="{{ (isset($path[1]) && $path[1] === 'marketplace' && $path[2] === 'leads')?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.marketplace.leads') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9 6l9 -6"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/></svg>
                            <span>Solicitudes / Leads</span>
                        </a>
                    </li>

                    <li class="{{ (isset($path[1]) && $path[1] === 'marketplace' && $path[2] === 'reviews')?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.marketplace.reviews') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <span>Reviews</span>
                        </a>
                    </li>

                    <li class="{{ (isset($path[1]) && $path[1] === 'admin' && isset($path[2]) && $path[2] === 'seller-applications')?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.seller_applications.index') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0 -4 -4h-5a4 4 0 0 0 -4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11l-3 3l-2 -2"/></svg>
                            <span>Solicitudes de vendedores</span>
                        </a>
                    </li>

                    {{-- ── SISTEMA ──────────────────────────────────────── --}}
                    <li class="nav-separator">Sistema</li>

                    <li class="{{ ($path[0] === 'auto-update')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.update')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.933 13.041a8 8 0 1 1 -9.925 -8.788c3.899 -1 7.935 1.007 9.425 4.747" /><path d="M20 4v5h-5" /></svg>
                            <span>Actualización</span>
                        </a>
                    </li>

                    <li class="{{ ($path[0] === 'backup')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.backup')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 18.004h-5.343c-2.572 -.004 -4.657 -2.011 -4.657 -4.487c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.99c1.38 0 2.573 .813 3.13 1.99" /><path d="M19 16v6" /><path d="M22 19l-3 3l-3 -3" /></svg>
                            <span>Backup</span>
                        </a>
                    </li>

                    <li class="">
                        <a class="nav-link" href="{{url('logs')}}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 9v-1a3 3 0 0 1 6 0v1" /><path d="M8 9h8a6 6 0 0 1 1 3v3a5 5 0 0 1 -10 0v-3a6 6 0 0 1 1 -3" /><path d="M3 13l4 0" /><path d="M17 13l4 0" /><path d="M12 20l0 -6" /><path d="M4 19l3.35 -2" /><path d="M20 19l-3.35 -2" /><path d="M4 7l3.75 2.4" /><path d="M20 7l-3.75 2.4" /></svg>
                            <span>Logs</span>
                        </a>
                    </li>

                    <li class="{{ ($path[0] === 'information')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.information')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9h.01" /><path d="M11 12h1v4h1" /><path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" /></svg>
                            <span>Información</span>
                        </a>
                    </li>

                </ul>
            </nav>
        </div>

        <script>
            if (typeof localStorage !== 'undefined') {
                if (localStorage.getItem('sidebar-left-position') !== null) {
                    var initialPosition = localStorage.getItem('sidebar-left-position'),
                        sidebarLeft = document.querySelector('#sidebar-left .nano-content');
                    sidebarLeft.scrollTop = initialPosition;
                }
            }
        </script>
    </div>

    <nav class="nav-main configuration-nav pt-0 px-2" role="navigation">
        <ul class="nav nav-main">
            <li class="{{ ($path[0] === 'configurations')?'nav-active':'' }}">
                <a class="nav-link" href="{{route('system.configuration.index')}}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                    <span>Configuración</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
