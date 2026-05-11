<header class="header" style="left:0;">
    <div class="logo-container m-2">
        @php
            use App\Models\System\Configuration;
            $configuration = Configuration::first();
            $logo = $configuration->login->logo ?? null;
        @endphp
        @if ($logo)
            <a href="{{ route('system.dashboard') }}" class="logo pt-2 pt-md-0">
                <img class="uk-logo-inverse" width="100" height="auto" src="{{ $logo }}" alt="Logo" />
            </a>
        @elseif (file_exists(public_path('theme/logo.svg')))
            <a href="{{ route('system.dashboard') }}" class="logo pt-2 pt-md-0">
                <img class="uk-logo-inverse" width="100" height="auto" src="{{ asset('theme/logo.svg') }}" alt="Logo" />
            </a>
        @else
            <a href="{{ route('system.dashboard') }}" class="text-logo pt-md-0">
                PANEL RESELLER
            </a>
        @endif
        <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html"
            data-fire-event="sidebar-left-opened">
            <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
        </div>
    </div>
    <!-- start: search & user box -->
    <div class="header-right d-flex">
        {{-- ═══════════ Campanita de notificaciones SuperAdmin ═══════════ --}}
        <div class="admin-notif-bell" id="adminNotifBell">
            <button type="button" class="admin-notif-btn" aria-label="Notificaciones">
                🔔
                <span class="admin-notif-badge" id="adminNotifBadge" style="display:none">0</span>
            </button>
            <div class="admin-notif-dropdown" id="adminNotifDropdown" style="display:none">
                <div class="admin-notif-head">
                    <strong>Notificaciones</strong>
                    <a href="#" id="adminNotifMarkAll" style="font-size:11px">Marcar todas</a>
                </div>
                <div class="admin-notif-list" id="adminNotifList">
                    <div class="admin-notif-empty">Cargando…</div>
                </div>
                <a href="{{ route('system.admin_notifications.index') }}" class="admin-notif-footer">
                    Ver todas
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-center me-4">
            <a class="btn btn-sm btn-outline-primary me-2" href="https://manual.pro8.uio.la/v8.0" target="_BLANK">🎉 Versión 8</a>
            <a class="btn btn-dark btn-sm d-flex align-items-center justify-content-center" href="https://manual.pro8.uio.la" target="_BLANK">
                <span>Manual</span>
                <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-book ms-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0" /><path d="M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0" /><path d="M3 6l0 13" /><path d="M12 6l0 13" /><path d="M21 6l0 13" /></svg>                
            </a>
        </div>
        <span class="separator"></span>
        <div id="userbox" class="userbox dropdown">
            <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <figure class="profile-picture">
                    {{-- <img src="{{asset('img/%21logged-user.jpg')}}" alt="Joseph Doe" class="rounded-circle"
                        data-lock-picture="img/%21logged-user.jpg" /> --}}
                    <div class="border rounded-circle text-center bg-transparent" style="border: none !important">
                        <svg  xmlns="http://www.w3.org/2000/svg"  width="32"  height="32"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user-circle"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" /></svg>
                    </i>
                    </div>
                </figure>
                <div class="profile-info" data-lock-name="{{ \Auth::getUser()->email }}"
                    data-lock-email="{{ \Auth::getUser()->email }}">
                    <span class="name">{{ \Auth::getUser()->name }}</span>
                    <span class="role">{{ \Auth::getUser()->email }}</span>
                </div>
                <i class="fa custom-caret"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-admin">
                <ul class="list-unstyled mb-0">
                    <li>
                        <a class="dropdown-item" role="menuitem" href="{{ route('system.users.create') }}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
                            Perfil
                        </a>
                        <a class="dropdown-item" role="menuitem" href="{{ route('system.2fa.setup') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield-lock"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3" /><path d="M12 11m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12 12l0 2.5" /></svg>
                            Autenticación 2FA
                            @if(\Auth::guard('admin')->user()->hasTwoFactorEnabled())
                                <span class="badge badge-success ms-1" style="font-size:0.65rem;">Activo</span>
                            @else
                                <span class="badge badge-warning ms-1" style="font-size:0.65rem;">Inactivo</span>
                            @endif
                        </a>
                        <a class="dropdown-item" role="menuitem" href="#" onclick="toggleThemeSidebar()">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-paint"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" /><path d="M19 6h1a2 2 0 0 1 2 2a5 5 0 0 1 -5 5l-5 0v2" /><path d="M10 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" /></svg>
                            Estilos y Temas</a>
                        <a class="dropdown-item" role="menuitem" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-logout"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
                            @lang('app.buttons.logout')
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- end: search & user box -->

    <!-- Theme Sidebar -->
    <div id="theme-sidebar" class="theme-sidebar">
        <div class="theme-sidebar-content">
            <div class="theme-sidebar-header">
                <h4>Estilos y Temas</h4>
                <button type="button" class="close-theme-sidebar" onclick="toggleThemeSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="theme-sidebar-body">
                <div id="theme-vue-component"></div>
            </div>
        </div>
    </div>
    <div id="theme-overlay" class="theme-overlay" onclick="toggleThemeSidebar()"></div>

    <script>
        function toggleThemeSidebar() {
            const sidebar = document.getElementById('theme-sidebar');
            const overlay = document.getElementById('theme-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Cargar el componente si no está cargado
            if (sidebar.classList.contains('active') && !document.getElementById('theme-vue-app')) {
                loadThemeComponent();
            }
        }

        function loadThemeComponent() {
            const container = document.getElementById('theme-vue-component');
            
            container.innerHTML = `
                <div id="theme-vue-app">
                    <div class="theme-color-component">
                        <div class="mt-3 theme-color-selector">
                            <h5>Selecciona un color de tema:</h5>
                            <div class="color-selector">
                                <button
                                    type="button"
                                    class="btn-theme-color"
                                    data-theme="white"
                                    style="background-color: #b3c5ff;"
                                ></button>
                                <button
                                    type="button"
                                    class="btn-theme-color"
                                    data-theme="aqua"
                                    style="background-color: #90dad9;"
                                ></button>
                                <button
                                    type="button"
                                    class="btn-theme-color"
                                    data-theme="acid"
                                    style="background-color: #c1b1f1;"
                                ></button>
                                <button
                                    type="button"
                                    class="btn-theme-color"
                                    data-theme="cupcake"
                                    style="background-color: #e7dad0;"
                                ></button>
                                <button
                                    type="button"
                                    class="btn-theme-color"
                                    data-theme="retro"
                                    style="background-color: #ebddb7;"
                                ></button>
                                <button
                                    type="button"
                                    class="btn-theme-color"
                                    data-theme="lemonade"
                                    style="background-color: #cddfae;"
                                ></button>
                            </div>
                            
                            <div id="loading-indicator" class="text-center mt-3" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Aplicando tema...
                            </div>
                        </div>
                    </div>
                </div>
            `;

            if (!document.getElementById('theme-selector-styles')) {
                const style = document.createElement('style');
                style.id = 'theme-selector-styles';
                style.innerHTML = `

                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }

                    .el-message {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 10000;
                        width: 150px !important;
                        padding: 15px;
                        border-radius: 4px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    }
                `;
                document.head.appendChild(style);
            }

            initializeThemeSelector();
        }

        let currentTheme = 'white';
        let themes = {};
        let isLoading = false;

        async function initializeThemeSelector() {
            try {
                await loadThemes();
                
                await loadCurrentTheme();
                
                setupEventListeners();
                
            } catch (error) {
                console.error('Error initializing theme selector:', error);
            }
        }

        async function loadThemes() {
            try {
                const response = await fetch("/json/themes/themes.json");
                themes = await response.json();
            } catch (error) {
                console.error("Error loading themes:", error);
            }
        }

        async function loadCurrentTheme() {
            try {
                const response = await fetch('/configurations/visual-theme', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                
                const data = await response.json();
                currentTheme = data.theme_color || 'white';
                
                updateThemeSelection();
                
                applyTheme(currentTheme);
                
            } catch (error) {
                console.error('Error loading current theme:', error);
                currentTheme = 'white';
            }
        }

        function setupEventListeners() {
            const buttons = document.querySelectorAll('.btn-theme-color');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const theme = this.getAttribute('data-theme');
                    if (!isLoading && theme !== currentTheme) {
                        onChangeTheme(theme);
                    }
                });
            });
        }

        function updateThemeSelection() {
            const buttons = document.querySelectorAll('.btn-theme-color');
            buttons.forEach(button => {
                const theme = button.getAttribute('data-theme');
                if (theme === currentTheme) {
                    button.classList.add('theme-selected');
                } else {
                    button.classList.remove('theme-selected');
                }
            });
        }

        function showLoading(show) {
            isLoading = show;
            const loadingIndicator = document.getElementById('loading-indicator');
            const buttons = document.querySelectorAll('.btn-theme-color');
            
            if (loadingIndicator) {
                loadingIndicator.style.display = show ? 'block' : 'none';
            }
            
            buttons.forEach(button => {
                button.disabled = show;
            });
        }

        async function saveTheme(theme) {
            try {
                const response = await fetch('/configurations/visual-theme', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        theme_color: theme
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Tema aplicado correctamente', 'success');
                    
                    localStorage.setItem('current_theme', theme);
                    const colors = themes[theme];
                    if (colors) {
                        localStorage.setItem('theme_colors_' + theme, JSON.stringify(colors));
                    }
                    
                    return true;
                } else {
                    showMessage(data.message || 'Error al guardar el tema', 'error');
                    return false;
                }
            } catch (error) {
                console.error('Error saving theme:', error);
                showMessage('Error de conexión al guardar el tema', 'error');
                return false;
            }
        }

        function showMessage(message, type) {
            const notification = document.createElement('div');
            notification.className = `el-message el-message--${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 3000);
        }

        function applyTheme(theme) {
            const colors = themes[theme];
            if (!colors) {
                console.error(`Theme "${theme}" not found.`);
                return;
            }

            let styleTag = document.getElementById("theme-styles");
            if (!styleTag) {
                styleTag = document.createElement("style");
                styleTag.id = "theme-styles";
                document.head.appendChild(styleTag);
            }

            let cssString = ":root {";
            Object.keys(colors).forEach(variable => {
                cssString += `${variable}: ${colors[variable]}; `;
            });
            cssString += "}";

            styleTag.innerHTML = cssString;
            
            localStorage.setItem('current_theme', theme);
            localStorage.setItem('theme_colors_' + theme, JSON.stringify(colors));
            
            // Disparar evento personalizado para notificar a los componentes que el tema cambió
            const themeChangeEvent = new CustomEvent('themeChanged', {
                detail: { theme: theme, colors: colors }
            });
            document.dispatchEvent(themeChangeEvent);
            
            // Segundo disparo después de que el DOM se actualice
            setTimeout(() => {
                const secondEvent = new CustomEvent('themeChanged', {
                    detail: { theme: theme, colors: colors }
                });
                document.dispatchEvent(secondEvent);
                console.log('Segundo evento themeChanged disparado');
            }, 50);
        }

        async function onChangeTheme(theme) {
            if (isLoading || currentTheme === theme) {
                return;
            }

            showLoading(true);

            try {
                // Aplicar el tema inmediatamente
                applyTheme(theme);
                
                // Disparar evento inmediatamente después de aplicar
                setTimeout(() => {
                    const themeChangeEvent = new CustomEvent('themeChanged', {
                        detail: { theme: theme, colors: themes[theme] }
                    });
                    document.dispatchEvent(themeChangeEvent);
                    console.log('Evento themeChanged disparado para:', theme);
                }, 10);
                
                const saved = await saveTheme(theme);
                
                if (saved) {
                    currentTheme = theme;
                    updateThemeSelection();
                    console.log('Tema seleccionado y guardado:', theme);
                } else {
                    applyTheme(currentTheme);
                }
                
            } catch (error) {
                console.error('Error changing theme:', error);
                showMessage('Error al cambiar el tema', 'error');
                
                applyTheme(currentTheme);
            } finally {
                showLoading(false);
            }
        }

        (function() {
            function applyCachedTheme() {
                const cachedTheme = localStorage.getItem('current_theme');
                const cachedColors = localStorage.getItem('theme_colors_' + (cachedTheme || 'white'));
                
                if (cachedTheme && cachedColors) {
                    try {
                        const colors = JSON.parse(cachedColors);
                        let styleTag = document.getElementById("theme-styles");
                        if (!styleTag) {
                            styleTag = document.createElement("style");
                            styleTag.id = "theme-styles";
                            document.head.appendChild(styleTag);
                        }

                        let cssString = ":root {";
                        Object.keys(colors).forEach(variable => {
                            cssString += `${variable}: ${colors[variable]}; `;
                        });
                        cssString += "}";

                        styleTag.innerHTML = cssString;
                        return true;
                    } catch (error) {
                        console.error('Error applying cached theme:', error);
                    }
                }
                return false;
            }

            const themeApplied = applyCachedTheme();
            
            if (!themeApplied) {
                loadInitialTheme();
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    loadInitialTheme(true);
                });
            }
        })();

        async function loadInitialTheme(isBackgroundUpdate = false) {
            try {
                const response = await fetch('/configurations/visual-theme');
                const data = await response.json();
                const theme = data.theme_color || 'white';
                
                const cachedTheme = localStorage.getItem('current_theme');
                if (isBackgroundUpdate && cachedTheme === theme) {
                    return;
                }
                
                const themesResponse = await fetch('/json/themes/themes.json');
                const themesData = await themesResponse.json();
                
                const colors = themesData[theme];
                if (colors) {
                    localStorage.setItem('current_theme', theme);
                    localStorage.setItem('theme_colors_' + theme, JSON.stringify(colors));
                    
                    let styleTag = document.getElementById("theme-styles");
                    if (!styleTag) {
                        styleTag = document.createElement("style");
                        styleTag.id = "theme-styles";
                        document.head.appendChild(styleTag);
                    }

                    let cssString = ":root {";
                    Object.keys(colors).forEach(variable => {
                        cssString += `${variable}: ${colors[variable]}; `;
                    });
                    cssString += "}";

                    styleTag.innerHTML = cssString;
                }
            } catch (error) {
                console.error('Error loading initial theme:', error);
            }
        }
    </script>

    {{-- ═══════════ Estilos + JS campanita de notificaciones ═══════════ --}}
    <style>
        .admin-notif-bell { position: relative; margin-right: 12px; display: flex; align-items: center; }
        .admin-notif-btn {
            background: transparent; border: 0; padding: 6px 10px;
            font-size: 18px; cursor: pointer; position: relative;
            border-radius: 8px; transition: background .12s;
        }
        .admin-notif-btn:hover { background: rgba(0,0,0,.05); }
        .admin-notif-badge {
            position: absolute; top: -2px; right: 0;
            background: #dc2626; color: #fff;
            font-size: 10px; font-weight: 700;
            min-width: 18px; height: 18px;
            border-radius: 999px;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0 5px; line-height: 1;
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,.18);
        }
        .admin-notif-dropdown {
            position: absolute; top: calc(100% + 6px); right: 0;
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 12px 32px -8px rgba(0,0,0,.15);
            width: 340px; max-width: 90vw;
            z-index: 9999;
            overflow: hidden;
        }
        .admin-notif-head {
            padding: 12px 14px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .admin-notif-head a { color: #2563eb; text-decoration: none; }
        .admin-notif-list { max-height: 360px; overflow-y: auto; }
        .admin-notif-item {
            display: flex; gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid #f8fafc;
            text-decoration: none; color: inherit;
            cursor: pointer;
            transition: background .12s;
        }
        .admin-notif-item:hover { background: #f9fafb; }
        .admin-notif-item.is-unread { background: #eff6ff; }
        .admin-notif-item.is-unread:hover { background: #dbeafe; }
        .admin-notif-icon { font-size: 18px; line-height: 1.2; }
        .admin-notif-body { flex: 1; min-width: 0; }
        .admin-notif-title { font-size: 12.5px; font-weight: 600; color: #111827; }
        .admin-notif-desc { font-size: 11.5px; color: #6b7280; margin-top: 2px; }
        .admin-notif-time { font-size: 10.5px; color: #9ca3af; margin-top: 3px; }
        .admin-notif-empty { padding: 20px; text-align: center; color: #9ca3af; font-size: 12.5px; }
        .admin-notif-footer {
            display: block; padding: 10px;
            text-align: center; font-size: 12px; font-weight: 600;
            color: #2563eb; text-decoration: none;
            border-top: 1px solid #f1f5f9;
        }
        .admin-notif-footer:hover { background: #f9fafb; }
    </style>

    <script>
    (function () {
        var bell      = document.getElementById('adminNotifBell');
        var btn       = bell && bell.querySelector('.admin-notif-btn');
        var dropdown  = document.getElementById('adminNotifDropdown');
        var badge     = document.getElementById('adminNotifBadge');
        var list      = document.getElementById('adminNotifList');
        var markAll   = document.getElementById('adminNotifMarkAll');
        if (!bell || !dropdown || !list) return;

        var feedUrl = "{{ url('admin/notifications/feed') }}";
        var markAllUrl = "{{ url('admin/notifications/read-all') }}";
        var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function refresh() {
            fetch(feedUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) {
                        // 401/403/500 → mostramos el código en la lista para diagnosticar
                        return r.text().then(function (t) {
                            list.innerHTML = '<div class="admin-notif-empty">HTTP ' + r.status + '<br><small style="font-size:10px">'
                                + (t.substring(0, 200) || 'sin body') + '</small></div>';
                            throw new Error('HTTP ' + r.status);
                        });
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (!data || !data.success) {
                        list.innerHTML = '<div class="admin-notif-empty">Sin success en respuesta</div>';
                        return;
                    }
                    if (data.unread_count > 0) {
                        badge.style.display = '';
                        badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    } else {
                        badge.style.display = 'none';
                    }
                    if (!data.items || !data.items.length) {
                        list.innerHTML = '<div class="admin-notif-empty">Sin notificaciones</div>';
                        return;
                    }
                    list.innerHTML = data.items.map(function (n) {
                        var cls = n.is_read ? 'admin-notif-item' : 'admin-notif-item is-unread';
                        var html = '<a class="' + cls + '" data-id="' + n.id + '" href="' + (n.link || '#') + '">'
                            + '<div class="admin-notif-icon">' + (n.icon || '🔔') + '</div>'
                            + '<div class="admin-notif-body">'
                            + '<div class="admin-notif-title">' + escapeHtml(n.title) + '</div>'
                            + (n.body ? '<div class="admin-notif-desc">' + escapeHtml(n.body) + '</div>' : '')
                            + '<div class="admin-notif-time">' + escapeHtml(n.created_at) + '</div>'
                            + '</div></a>';
                        return html;
                    }).join('');
                    // Marcar leído al click
                    list.querySelectorAll('.admin-notif-item').forEach(function (a) {
                        a.addEventListener('click', function () {
                            var id = a.dataset.id;
                            fetch("{{ url('admin/notifications') }}/" + id + "/read", {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });
                        });
                    });
                })
                .catch(function (err) {
                    if (list.querySelector('.admin-notif-empty') === null) {
                        list.innerHTML = '<div class="admin-notif-empty">Error: ' + err.message + '</div>';
                    }
                });
        }

        function escapeHtml(s) {
            return String(s || '').replace(/[&<>"']/g, function (c) {
                return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c];
            });
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.style.display = dropdown.style.display === 'none' ? '' : 'none';
            if (dropdown.style.display !== 'none') refresh();
        });
        document.addEventListener('click', function (e) {
            if (!bell.contains(e.target)) dropdown.style.display = 'none';
        });
        if (markAll) {
            markAll.addEventListener('click', function (e) {
                e.preventDefault();
                fetch(markAllUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                }).then(refresh);
            });
        }

        refresh();
        setInterval(refresh, 60000); // polling cada 60s
    })();
    </script>
</header>