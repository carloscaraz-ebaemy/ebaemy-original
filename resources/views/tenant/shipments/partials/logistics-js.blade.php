<script>
/*
 * Acciones de la capa logística en el panel: cambio de modalidad, anulación
 * con motivo, restauración y bitácora.
 *
 * Todo por delegación en `document` y re-consultando los nodos frescos: Vue
 * monta en #main-wrapper y re-renderiza el DOM, así que no se guardan
 * referencias ni se enganchan listeners directos.
 * Ver feedback_vue_mainwrapper_rerender.
 */
(function () {
    var ROUTES = {!! json_encode([
        'modality' => route('shipments.modality', ['shipment' => '__ID__']),
        'cancel'   => route('shipments.cancel',   ['shipment' => '__ID__']),
        'restore'  => route('shipments.restore',  ['shipment' => '__ID__']),
    ], JSON_UNESCAPED_SLASHES) !!};

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function urlFor(key, id) {
        return ROUTES[key].replace('__ID__', id);
    }

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text || '';
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) return;

        // ── Cambiar modalidad ────────────────────────────────────────
        var mod = t.closest('.js-change-modality');
        if (mod) {
            var form = document.getElementById('formModalidad');
            if (form) form.setAttribute('action', urlFor('modality', mod.getAttribute('data-id')));

            setText('modCode', mod.getAttribute('data-code'));
            setText('modBatch', mod.getAttribute('data-batch'));

            // Aviso de bloqueo solo si el lote ya se imprimió.
            var locked = mod.getAttribute('data-locked') === '1';
            var box = document.getElementById('modLocked');
            if (box) box.classList.toggle('d-none', !locked);

            // Marcar la modalidad actual y deshabilitarla (no se "cambia" a lo mismo).
            var current = mod.getAttribute('data-current');
            document.querySelectorAll('#modalModalidad input[name="delivery_type"]').forEach(function (r) {
                r.checked = false;
                r.disabled = (r.value === current);
                var label = r.closest('.js-mod-option');
                if (label) label.style.opacity = r.disabled ? '.45' : '1';
            });

            var reason = document.getElementById('modReason');
            if (reason) reason.value = '';
            return;
        }

        // ── Anular ───────────────────────────────────────────────────
        var cancel = t.closest('.js-cancel-shipment');
        if (cancel) {
            var fc = document.getElementById('formAnular');
            if (fc) fc.setAttribute('action', urlFor('cancel', cancel.getAttribute('data-id')));
            setText('anuCode', cancel.getAttribute('data-code'));
            var ar = document.getElementById('anuReason');
            if (ar) ar.value = '';
            return;
        }

        // ── Restaurar ────────────────────────────────────────────────
        var restore = t.closest('.js-restore-shipment');
        if (restore) {
            var fr = document.getElementById('formRestaurar');
            if (fr) fr.setAttribute('action', urlFor('restore', restore.getAttribute('data-id')));
            setText('resCode', restore.getAttribute('data-code'));

            var info = document.getElementById('resInfo');
            if (info) {
                var by = restore.getAttribute('data-by');
                var at = restore.getAttribute('data-at');
                var wh = restore.getAttribute('data-reason');
                var parts = [];
                if (at) parts.push('Anulado el <strong>' + esc(at) + '</strong>');
                if (by) parts.push('por <strong>' + esc(by) + '</strong>');
                if (wh) parts.push('· Motivo: ' + esc(wh));
                info.innerHTML = parts.length ? parts.join(' ') : 'Sin datos de la anulación.';
            }

            var rr = document.getElementById('resReason');
            if (rr) rr.value = '';
            return;
        }

        // ── Reimprimir rótulo individual ─────────────────────────────
        // El botón abre el modal por data-api de Bootstrap; aquí solo se
        // rellenan sus campos. NO se usa `window.bootstrap`: el bundle de Vite
        // no expone ese global, y con él se abría (o mejor dicho, NO se abría)
        // el modal en silencio.
        var pr = t.closest('.js-reprint');
        if (pr) {
            var modalEl = document.getElementById('modalReimprimir');
            if (!modalEl) return;

            modalEl.setAttribute('data-url', pr.getAttribute('data-url') || '');
            setText('rpCode', pr.getAttribute('data-code') || '');
            setText('rpCount', pr.getAttribute('data-count') || '1');

            var rr = document.getElementById('rpReason');
            if (rr) { rr.value = ''; rr.classList.remove('is-invalid'); }
            return;
        }

        // Botón "Reimprimir" de la ficha "ojo": cierra la ficha y delega en el
        // botón de la fila, que ya lleva el data-api del modal. Mismo recurso
        // que usa "Editar" para no apilar dos modales.
        var prView = t.closest('.js-reprint-from-view');
        if (prView) {
            ev.preventDefault();
            var id   = prView.getAttribute('data-id');
            var view = document.getElementById('modalVerEnvio');
            var row  = id ? document.querySelector('.js-reprint[data-id="' + id + '"]') : null;

            if (view) {
                var closer = view.querySelector('[data-bs-dismiss="modal"]');
                if (closer) closer.click();
            }
            if (row) setTimeout(function () { row.click(); }, 200);
            return;
        }

        // Motivo frecuente: rellena el campo en vez de obligar a teclearlo.
        var quick = t.closest('.js-rp-quick');
        if (quick) {
            ev.preventDefault();
            var input = document.getElementById('rpReason');
            if (input) { input.value = quick.textContent.trim(); input.focus(); }
            return;
        }

        // Confirmar la reimpresión: abre el rótulo con el motivo en la URL.
        var go = t.closest('#rpGo');
        if (go) {
            ev.preventDefault();
            var mEl    = document.getElementById('modalReimprimir');
            var reason = (document.getElementById('rpReason') || {}).value || '';
            var fmt    = (document.getElementById('rpFormat') || {}).value || 'a5';

            reason = reason.trim();
            if (!reason) {
                var f = document.getElementById('rpReason');
                if (f) { f.focus(); f.classList.add('is-invalid'); }
                return;
            }

            var base = mEl ? (mEl.getAttribute('data-url') || '') : '';
            if (!base) return;

            var url = base + (base.indexOf('?') === -1 ? '?' : '&')
                    + 'motivo=' + encodeURIComponent(reason)
                    + '&format=' + encodeURIComponent(fmt);

            window.open(url, '_blank');

            // Cerrar disparando el botón de cierre del propio modal.
            var closeBtn = mEl.querySelector('[data-bs-dismiss="modal"]');
            if (closeBtn) closeBtn.click();
            return;
        }

        // ── Bitácora ─────────────────────────────────────────────────
        var audit = t.closest('.js-audit-trail');
        if (audit) {
            setText('bitCode', audit.getAttribute('data-code'));
            var body = document.getElementById('bitBody');
            if (body) body.innerHTML = '<div class="text-muted">Cargando…</div>';

            fetch(audit.getAttribute('data-url'), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!body) return;
                    if (!data.logs || !data.logs.length) {
                        body.innerHTML = '<div class="text-muted">Sin movimientos registrados.</div>';
                        return;
                    }
                    body.innerHTML = '<ul class="lg-log" style="list-style:none;margin:0;padding:0">'
                        + data.logs.map(function (l) {
                            return '<li style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:.83rem">'
                                 + '<span style="color:#9ca3af;white-space:nowrap">' + esc(l.at) + '</span>'
                                 + '<span style="flex:1;min-width:0">' + esc(l.summary)
                                 + (l.exception ? ' <span style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:999px;font-size:.66rem;font-weight:700;padding:1px 6px">excepción</span>' : '')
                                 + '<div style="color:#6b7280;font-size:.75rem">' + esc(l.user)
                                 + (l.notes ? ' · ' + esc(l.notes) : '') + '</div>'
                                 + '</span></li>';
                        }).join('')
                        + '</ul>';
                })
                .catch(function () {
                    if (body) body.innerHTML = '<div class="text-danger">No se pudo cargar la bitácora.</div>';
                });
        }
    });
})();
</script>
