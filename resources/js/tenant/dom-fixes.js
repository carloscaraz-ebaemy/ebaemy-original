// Scripts migrados desde las vistas Blade por conflicto con Vite Upgrade
// Archivo: resources/js/tenant/dom-fixes.js

// 1. Script de tema visual (de app.blade.php)
export function applyThemeAndShowContent(savedTheme) {
    const timeoutDuration = 3000;
    const showContent = () => {
        document.body.classList.add('visible');
    };
    const timeout = setTimeout(() => {
        console.warn('Timeout: Mostrando contenido sin aplicar el tema.');
        showContent();
    }, timeoutDuration);
    if (savedTheme) {
        fetch('/json/themes/themes.json')
            .then(response => response.json())
            .then(themes => {
                if (themes[savedTheme]) {
                    const styleElement = document.createElement('style');
                    let cssVariables = '';
                    Object.keys(themes[savedTheme]).forEach(variable => {
                        cssVariables += `${variable}: ${themes[savedTheme][variable]};\n`;
                    });
                    styleElement.innerHTML = `:root { ${cssVariables} }`;
                    document.head.appendChild(styleElement);
                }
            })
            .catch(error => {
                console.error('Error loading themes:', error);
            })
            .finally(() => {
                clearTimeout(timeout);
                showContent();
            });
    } else {
        clearTimeout(timeout);
        showContent();
    }
}

// 2. Scripts de header (de header.blade.php)
export function setupHeaderDomEvents() {
    document.addEventListener('DOMContentLoaded', function () {
        const optionsUserMobile = document.querySelector('.options-user-mobile');
        const headerRight = document.querySelector('.header-right');
        const closeContainerUser = document.querySelector('.close-container-user');
        const body = document.body;
        if (optionsUserMobile && headerRight) {
            optionsUserMobile.addEventListener('click', function () {
                headerRight.classList.toggle('active');
                document.documentElement.classList.add('options-user-mobile-opened');
            });
        }
        if (closeContainerUser && headerRight) {
            closeContainerUser.addEventListener('click', function () {
                headerRight.classList.remove('active');
                document.documentElement.classList.remove('options-user-mobile-opened');
            });
        }
        if (headerRight && optionsUserMobile) {
            document.addEventListener('click', function (event) {
                if (!headerRight.contains(event.target) && !optionsUserMobile.contains(event.target)) {
                    headerRight.classList.remove('active');
                    document.documentElement.classList.remove('options-user-mobile-opened');
                }
            });
        }
        // script para manejo de dropdown-menu-desktop
        const userProfile = document.querySelector('.check-double');
        const dropdownMenu = document.querySelector('.dropdown-menu-desktop');
        if (userProfile && dropdownMenu) {
            userProfile.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropdownMenu.classList.toggle('active');
            });
            document.addEventListener('click', function (event) {
                if (!userProfile.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    dropdownMenu.classList.remove('active');
                }
            });
        }
    });
}

// 3. Scripts de autenticación del eCommerce (desde footer.blade.php)
export function setupEcommerceAuthHandlers() {
    const init = () => {
        const container = document.getElementById('contenedor-form');
        const loginToggleButton = document.getElementById('iniciar-sesion');
        const registerToggleButton = document.getElementById('registrarse');

        if (container && loginToggleButton && registerToggleButton) {
            loginToggleButton.addEventListener('click', () => {
                container.classList.remove('active');
            });
            registerToggleButton.addEventListener('click', () => {
                container.classList.add('active');
            });
        }

        const passwordInput = document.getElementById('pwd_reg');
        const confirmPasswordInput = document.getElementById('pwd_repeat_reg');
        if (passwordInput && confirmPasswordInput) {
            const validatePassword = () => {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('El Password no coincide.');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            };
            passwordInput.addEventListener('change', validatePassword);
            confirmPasswordInput.addEventListener('keyup', validatePassword);
        }

        const loginForm = document.getElementById('form_login');
        if (loginForm && !loginForm.dataset.bound) {
            loginForm.dataset.bound = 'true';
            const loginMessage = document.getElementById('msg_login');
            const loginUrl = loginForm.dataset.loginUrl;
            loginForm.addEventListener('submit', (event) => {
                event.preventDefault();
                submitEcommerceForm(loginForm, loginUrl, {
                    messageContainer: loginMessage,
                    onFailure: () => {
                        if (loginMessage) {
                            loginMessage.style.display = '';
                        }
                    }
                });
            });
        }

        const registerForm = document.getElementById('form_register');
        if (registerForm && !registerForm.dataset.bound) {
            registerForm.dataset.bound = 'true';
            const registerMessage = document.getElementById('msg_register');
            const registerMessageText = document.getElementById('msg_register_p');
            const registerUrl = registerForm.dataset.registerUrl;
            registerForm.addEventListener('submit', (event) => {
                event.preventDefault();
                submitEcommerceForm(registerForm, registerUrl, {
                    messageContainer: registerMessage,
                    messageTextContainer: registerMessageText
                });
            });
        }

        setupDocumentControls();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}

function submitEcommerceForm(form, url, { messageContainer, messageTextContainer, onFailure } = {}) {
    if (!url) {
        console.warn(`No se definió URL para el formulario ${form.id}`);
        return;
    }

    if (messageContainer) {
        messageContainer.style.display = 'none';
        if (messageTextContainer) {
            messageTextContainer.textContent = '';
        }
    }

    const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
    const isInputSubmit = submitButton && submitButton.tagName === 'INPUT';
    const previousLabel = submitButton ? (isInputSubmit ? submitButton.value : submitButton.innerHTML) : null;
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.dataset.loading = 'true';
        if (isInputSubmit) {
            submitButton.value = 'Procesando...';
        } else {
            submitButton.innerHTML = 'Procesando...';
        }
    }

    const formData = new FormData(form);
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;

    fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
        },
        body: formData
    })
        .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.success) {
                window.location.reload();
            } else {
                if (messageContainer) {
                    messageContainer.style.display = '';
                }
                if (messageTextContainer && data.message) {
                    messageTextContainer.textContent = data.message;
                }
                if (typeof onFailure === 'function') {
                    onFailure();
                }
            }
        })
        .catch((error) => {
            console.error('Error en la petición del formulario eCommerce:', error);
            if (messageContainer) {
                messageContainer.style.display = '';
                if (messageTextContainer) {
                    messageTextContainer.textContent = 'No pudimos procesar tu solicitud. Inténtalo nuevamente.';
                }
            }
        })
        .finally(() => {
            if (submitButton) {
                submitButton.disabled = false;
                if (previousLabel !== null) {
                    if (isInputSubmit) {
                        submitButton.value = previousLabel;
                    } else {
                        submitButton.innerHTML = previousLabel;
                    }
                }
                delete submitButton.dataset.loading;
            }
        });
}

function setupDocumentControls() {
    const select = document.getElementById('selectDocument');
    const input = document.getElementById('ruc_reg');
    const counter = document.getElementById('counter');

    if (!input || !counter) {
        return;
    }

    const getMaxLength = () => {
        if (select) {
            return select.value === 'dni' ? 8 : 11;
        }
        return Number(input.getAttribute('maxlength')) || 11;
    };

    const updateCounter = () => {
        const maxLength = getMaxLength();
        input.setAttribute('maxlength', `${maxLength}`);
        if (input.value.length > maxLength) {
            input.value = input.value.slice(0, maxLength);
        }
        counter.textContent = `${input.value.length}/${maxLength}`;
    };

    if (!input.dataset.counterBound) {
        input.dataset.counterBound = 'true';
        input.addEventListener('input', updateCounter);
    }

    if (select && !select.dataset.counterBound) {
        select.dataset.counterBound = 'true';
        select.addEventListener('change', updateCounter);
    }

    updateCounter();
}

// 4. Actualizar el título del documento con el módulo actual
export function updateTenantPageTitle({
    breadcrumbSelector = '.breadcrumbs .active span',
    fallbackSelector = '.page-header h2',
    disconnectTimeout = 5000
} = {}) {
    const body = document.body;
    if (!body || body.dataset.tenant !== 'true') {
        return;
    }

    const companyName = (body.dataset.companyTitle || '').trim();
    if (!companyName) {
        return;
    }

    const sanitize = (text) => text.replace(/\s+/g, ' ').trim();

    const resolveModuleTitle = () => {
        const directTitleEl = document.querySelector('[data-page-title]');
        let moduleTitle = '';

        if (directTitleEl) {
            moduleTitle = sanitize(
                directTitleEl.dataset.pageTitle || directTitleEl.getAttribute('data-page-title') || directTitleEl.textContent || ''
            );
        }

        if (!moduleTitle) {
            const breadcrumbEl = document.querySelector(breadcrumbSelector);
            moduleTitle = breadcrumbEl ? sanitize(breadcrumbEl.textContent || '') : '';
        }

        if (!moduleTitle || moduleTitle.includes('{{')) {
            const fallbackEl = document.querySelector(fallbackSelector);
            moduleTitle = fallbackEl ? sanitize(fallbackEl.textContent || '') : '';
        }

        return moduleTitle;
    };

    const applyTitle = () => {
        const moduleTitle = resolveModuleTitle();

        if (!moduleTitle) {
            return false;
        }

        document.title = `${moduleTitle} - ${companyName}`;
        return true;
    };

    const init = () => {
        if (applyTitle()) {
            return;
        }

        const target = document.querySelector('.page-header') || document.body;
        if (!target) {
            return;
        }

        const observer = new MutationObserver(() => {
            if (applyTitle()) {
                observer.disconnect();
            }
        });

        observer.observe(target, {
            childList: true,
            subtree: true,
            characterData: true
        });

        if (disconnectTimeout > 0) {
            setTimeout(() => observer.disconnect(), disconnectTimeout);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}
