/**
 * image-zoom.js — Zoom de imagen al hover en la galería de producto
 * Muestra un panel lateral con la zona ampliada al mover el ratón sobre la imagen.
 * En móvil/táctil se desactiva automáticamente.
 */
(function () {
    'use strict';

    var zoomPanel  = null;   // Panel lateral con la imagen ampliada
    var zoomFactor = 2.5;    // Factor de ampliación
    var lens       = null;   // Lente cuadrada sobre la imagen
    var activeImg  = null;   // <img> actualmente bajo zoom

    // ── Crear elementos DOM ───────────────────────────────────────────────────
    function buildUI() {
        // Lente
        lens = document.createElement('div');
        lens.id = 'ec-zoom-lens';
        lens.className = 'ec-zoom-lens';

        // Panel de resultado
        zoomPanel = document.createElement('div');
        zoomPanel.id = 'ec-zoom-panel';
        zoomPanel.className = 'ec-zoom-panel';

        document.body.appendChild(lens);
        document.body.appendChild(zoomPanel);
    }

    // ── Activar zoom en una imagen ────────────────────────────────────────────
    function activateZoom(img) {
        var src = img.getAttribute('data-zoom-image') || img.src;
        if (!src) return;

        activeImg = img;

        // Precarga imagen de alta res
        var hi = new Image();
        hi.onload = function () {
            zoomPanel.style.backgroundImage  = 'url(' + src + ')';
            zoomPanel.style.backgroundRepeat = 'no-repeat';
            zoomPanel.style.backgroundSize   = (hi.width * zoomFactor) + 'px ' + (hi.height * zoomFactor) + 'px';
        };
        hi.src = src;

        lens.style.display    = 'block';
        zoomPanel.style.display = 'block';
        positionPanel(img);
    }

    function deactivateZoom() {
        activeImg = null;
        if (lens)      lens.style.display      = 'none';
        if (zoomPanel) zoomPanel.style.display  = 'none';
    }

    // ── Posicionar el panel junto a la imagen ─────────────────────────────────
    function positionPanel(img) {
        var rect   = img.getBoundingClientRect();
        var scroll = window.pageYOffset || document.documentElement.scrollTop;

        var panelW = 340;
        var panelH = Math.min(rect.height, 380);

        // Intentar a la derecha; si no cabe, a la izquierda
        var leftPos = rect.right + window.pageXOffset + 12;
        if (leftPos + panelW > window.innerWidth + window.pageXOffset) {
            leftPos = rect.left + window.pageXOffset - panelW - 12;
        }

        zoomPanel.style.width  = panelW + 'px';
        zoomPanel.style.height = panelH + 'px';
        zoomPanel.style.top    = (rect.top  + scroll) + 'px';
        zoomPanel.style.left   = leftPos + 'px';
    }

    // ── Mover lente y actualizar panel ────────────────────────────────────────
    function onMouseMove(e) {
        if (!activeImg) return;

        var img    = activeImg;
        var rect   = img.getBoundingClientRect();
        var scroll = window.pageYOffset || document.documentElement.scrollTop;

        // Posición del cursor relativa a la imagen
        var cx = e.clientX - rect.left;
        var cy = e.clientY - rect.top;

        // Tamaño de la lente (proporcional al panel)
        var panelW   = zoomPanel.offsetWidth  || 340;
        var panelH   = zoomPanel.offsetHeight || 340;
        var lensW    = Math.round(panelW  / zoomFactor);
        var lensH    = Math.round(panelH  / zoomFactor);

        lens.style.width  = lensW + 'px';
        lens.style.height = lensH + 'px';

        // Clamp lente dentro de la imagen
        var lx = cx - lensW / 2;
        var ly = cy - lensH / 2;
        lx = Math.max(0, Math.min(lx, rect.width  - lensW));
        ly = Math.max(0, Math.min(ly, rect.height - lensH));

        lens.style.left = (rect.left + window.pageXOffset + lx) + 'px';
        lens.style.top  = (rect.top  + scroll + ly) + 'px';

        // Calcular el offset del background en el panel
        var bgX = -lx * zoomFactor;
        var bgY = -ly * zoomFactor;
        zoomPanel.style.backgroundPosition = bgX + 'px ' + bgY + 'px';

        // Re-posicionar el panel verticalmente al hacer scroll
        var newPanelTop = rect.top + scroll;
        zoomPanel.style.top = newPanelTop + 'px';
    }

    // ── Adjuntar eventos a imágenes del carousel ──────────────────────────────
    function attachToImages() {
        document.querySelectorAll('.product-single-carousel .product-single-image').forEach(function (img) {
            img.addEventListener('mouseenter', function () { activateZoom(img); });
            img.addEventListener('mouseleave', deactivateZoom);
            img.addEventListener('mousemove',  onMouseMove);
            img.style.cursor = 'crosshair';
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // No activar en táctil
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) return;

        var carousel = document.querySelector('.product-single-carousel');
        if (!carousel) return;

        buildUI();
        attachToImages();

        // Re-adjuntar cuando Owl Carousel cambie de slide
        if (window.jQuery) {
            jQuery('.product-single-carousel').on('changed.owl.carousel', function () {
                setTimeout(attachToImages, 100);
            });
        }
    });

}());
