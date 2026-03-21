/**
 * newsletter-popup.js
 * Muestra el pop-up de suscripción:
 *   - A los 8 segundos de estar en la página, O
 *   - Al detectar intento de salida (mouse hacia la barra del navegador)
 * Solo una vez cada 7 días (localStorage).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'ec_nl_dismissed';
    var COOLDOWN_MS = 7 * 24 * 60 * 60 * 1000; // 7 días

    var overlay = document.getElementById('ec-nl-overlay');
    if (!overlay) return;

    // ── ¿Ya fue visto recientemente? ─────────────────────────────────────────
    function wasDismissed() {
        try {
            var ts = parseInt(localStorage.getItem(STORAGE_KEY), 10);
            return ts && (Date.now() - ts) < COOLDOWN_MS;
        } catch (e) { return false; }
    }

    function markDismissed() {
        try { localStorage.setItem(STORAGE_KEY, Date.now()); } catch (e) {}
    }

    // ── Abrir / cerrar ────────────────────────────────────────────────────────
    function open() {
        overlay.style.display = 'flex';
        overlay.classList.add('ec-nl-overlay--in');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        markDismissed();
        overlay.classList.remove('ec-nl-overlay--in');
        overlay.classList.add('ec-nl-overlay--out');
        setTimeout(function () {
            overlay.style.display = 'none';
            overlay.classList.remove('ec-nl-overlay--out');
            document.body.style.overflow = '';
        }, 300);
        clearTimers();
    }

    var timers = [];
    function clearTimers() { timers.forEach(clearTimeout); timers = []; }

    // ── Disparadores ─────────────────────────────────────────────────────────
    function init() {
        if (wasDismissed()) return;

        // 1. Timer de 8 segundos
        timers.push(setTimeout(open, 8000));

        // 2. Exit intent (mouse sale hacia arriba en desktop)
        function exitIntent(e) {
            if (e.clientY <= 5 && !wasDismissed()) {
                open();
                document.removeEventListener('mouseleave', exitIntent);
                clearTimers();
            }
        }
        document.addEventListener('mouseleave', exitIntent);
    }

    // ── Eventos UI ────────────────────────────────────────────────────────────
    document.getElementById('ec-nl-close').addEventListener('click', close);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.style.display !== 'none') close();
    });

    // ── Formulario ───────────────────────────────────────────────────────────
    var form = document.getElementById('ec-nl-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var emailInput = document.getElementById('ec-nl-email');
            var email = emailInput ? emailInput.value.trim() : '';
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                emailInput && emailInput.classList.add('ec-nl-input--error');
                return;
            }
            emailInput.classList.remove('ec-nl-input--error');

            // Guardar en localStorage (igual que el newsletter bar del footer)
            try {
                var list = JSON.parse(localStorage.getItem('ec_newsletter') || '[]');
                if (list.indexOf(email) === -1) {
                    list.push(email);
                    localStorage.setItem('ec_newsletter', JSON.stringify(list));
                }
            } catch (err) {}

            // Enviar al backend
            fetch('/ecommerce/newsletter-subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                },
                body: JSON.stringify({ email: email })
            }).catch(function () {});

            // Mostrar éxito
            var formWrap = document.getElementById('ec-nl-form-wrap');
            var success  = document.getElementById('ec-nl-success');
            if (formWrap) formWrap.style.display = 'none';
            if (success)  success.style.display  = 'flex';

            markDismissed();

            // Auto-cerrar después de 5s
            setTimeout(close, 5000);
        });
    }

    // ── Copiar código de descuento ────────────────────────────────────────────
    var copyBtn = document.getElementById('ec-nl-copy');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var code = document.querySelector('.ec-nl-coupon-code');
            if (!code) return;
            var text = code.textContent.trim();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    copyBtn.textContent = '¡Copiado!';
                    setTimeout(function () { copyBtn.textContent = 'Copiar'; }, 2000);
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
                copyBtn.textContent = '¡Copiado!';
                setTimeout(function () { copyBtn.textContent = 'Copiar'; }, 2000);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);

}());
