/**
 * Stock Notify — modal de "Avisar cuando haya stock"
 * Escucha clicks en .ec-btn-notify, muestra un mini-modal con email,
 * y envía la suscripción al backend vía fetch.
 */
(function () {
    'use strict';

    var ENDPOINT = '/ecommerce/stock-notify';
    var CSRF     = document.querySelector('meta[name="csrf-token"]')
                    ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    : '';

    // ── Crear modal (una sola vez) ───────────────────────────────────────────
    function buildModal() {
        if (document.getElementById('ec-notify-modal')) return;

        var modal = document.createElement('div');
        modal.id = 'ec-notify-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'ec-notify-title');
        modal.innerHTML = [
            '<div class="ec-notify-backdrop"></div>',
            '<div class="ec-notify-box">',
            '  <button class="ec-notify-close" aria-label="Cerrar">&times;</button>',
            '  <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"',
            '       fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"',
            '       style="color:hsl(var(--primary-h,220),var(--primary-s,70%),var(--primary-l,45%));margin-bottom:8px">',
            '    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>',
            '    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
            '  </svg>',
            '  <h3 id="ec-notify-title" class="ec-notify-title">Avísame cuando haya stock</h3>',
            '  <p class="ec-notify-product-name"></p>',
            '  <form id="ec-notify-form" novalidate>',
            '    <input type="hidden" id="ec-notify-item-id" name="item_id">',
            '    <input type="text"  id="ec-notify-name"  name="name"  placeholder="Tu nombre (opcional)" autocomplete="given-name">',
            '    <input type="email" id="ec-notify-email" name="email" placeholder="Tu email *" required autocomplete="email">',
            '    <p class="ec-notify-error" role="alert" aria-live="polite"></p>',
            '    <button type="submit" class="ec-notify-submit">Notificarme</button>',
            '  </form>',
            '  <div class="ec-notify-success" hidden>',
            '    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"',
            '         stroke="#22c55e" stroke-width="2" aria-hidden="true">',
            '      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            '    </svg>',
            '    <p></p>',
            '  </div>',
            '</div>',
        ].join('\n');

        document.body.appendChild(modal);

        // Cerrar al click en backdrop o botón ×
        modal.querySelector('.ec-notify-backdrop').addEventListener('click', closeModal);
        modal.querySelector('.ec-notify-close').addEventListener('click', closeModal);

        // Cerrar con Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        // Submit
        document.getElementById('ec-notify-form').addEventListener('submit', handleSubmit);
    }

    function openModal(itemId, itemName) {
        buildModal();
        var modal = document.getElementById('ec-notify-modal');
        modal.querySelector('.ec-notify-product-name').textContent = itemName;
        modal.querySelector('#ec-notify-item-id').value = itemId;
        modal.querySelector('#ec-notify-email').value   = '';
        modal.querySelector('#ec-notify-name').value    = '';
        modal.querySelector('.ec-notify-error').textContent = '';
        modal.querySelector('#ec-notify-form').hidden   = false;
        modal.querySelector('.ec-notify-success').hidden = true;
        modal.classList.add('ec-notify-modal--open');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { modal.querySelector('#ec-notify-email').focus(); }, 80);
    }

    function closeModal() {
        var modal = document.getElementById('ec-notify-modal');
        if (!modal) return;
        modal.classList.remove('ec-notify-modal--open');
        document.body.style.overflow = '';
    }

    function handleSubmit(e) {
        e.preventDefault();
        var form    = e.target;
        var itemId  = document.getElementById('ec-notify-item-id').value;
        var email   = document.getElementById('ec-notify-email').value.trim();
        var name    = document.getElementById('ec-notify-name').value.trim();
        var errEl   = form.querySelector('.ec-notify-error');
        var btn     = form.querySelector('.ec-notify-submit');

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errEl.textContent = 'Por favor ingresa un email válido.';
            return;
        }

        errEl.textContent = '';
        btn.disabled      = true;
        btn.textContent   = 'Enviando…';

        fetch(ENDPOINT, {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'X-CSRF-TOKEN':  CSRF,
                'Accept':        'application/json',
            },
            body: JSON.stringify({ item_id: itemId, email: email, name: name }),
        })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
        .then(function (r) {
            if (r.ok && r.data.success) {
                form.hidden = true;
                var success = document.querySelector('.ec-notify-success');
                success.querySelector('p').textContent = r.data.message;
                success.hidden = false;
                setTimeout(closeModal, 3000);
            } else {
                errEl.textContent = (r.data && r.data.message) ? r.data.message : 'Ocurrió un error, intenta de nuevo.';
                btn.disabled   = false;
                btn.textContent = 'Notificarme';
            }
        })
        .catch(function () {
            errEl.textContent = 'Error de conexión, intenta de nuevo.';
            btn.disabled    = false;
            btn.textContent = 'Notificarme';
        });
    }

    // ── Delegación de eventos en .ec-btn-notify ──────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ec-btn-notify');
        if (!btn) return;
        e.preventDefault();
        openModal(
            btn.getAttribute('data-item-id'),
            btn.getAttribute('data-item-name') || 'este producto'
        );
    });
}());
