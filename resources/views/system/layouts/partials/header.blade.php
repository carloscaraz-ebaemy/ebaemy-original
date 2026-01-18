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
</header>