/**
 * Galería de producto mejorada
 * - Lightbox fullscreen al hacer clic en la imagen principal o en el ícono de zoom
 * - Sincroniza miniaturas activas con el carousel Owl
 * - Navegación por teclado (← →, Escape) dentro del lightbox
 */
(function () {
    'use strict';

    // ── Lightbox ──────────────────────────────────────────────────────────────
    var lightbox, lbImg, lbClose, lbPrev, lbNext;
    var images = [];   // URLs de todas las imágenes del producto
    var current = 0;

    function buildLightbox() {
        if (document.getElementById('ec-lightbox')) return;

        var lb = document.createElement('div');
        lb.id = 'ec-lightbox';
        lb.setAttribute('role', 'dialog');
        lb.setAttribute('aria-modal', 'true');
        lb.setAttribute('aria-label', 'Galería de imágenes');
        lb.innerHTML = [
            '<div class="ec-lb-overlay"></div>',
            '<div class="ec-lb-inner">',
            '  <button class="ec-lb-btn ec-lb-close" aria-label="Cerrar">',
            '    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"',
            '         fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/>',
            '         <line x1="6" y1="6" x2="18" y2="18"/></svg>',
            '  </button>',
            '  <button class="ec-lb-btn ec-lb-prev" aria-label="Imagen anterior">',
            '    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"',
            '         fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>',
            '  </button>',
            '  <div class="ec-lb-img-wrap">',
            '    <img class="ec-lb-img" src="" alt="" draggable="false">',
            '  </div>',
            '  <button class="ec-lb-btn ec-lb-next" aria-label="Imagen siguiente">',
            '    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"',
            '         fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>',
            '  </button>',
            '  <p class="ec-lb-counter"></p>',
            '</div>'
        ].join('');

        document.body.appendChild(lb);

        lightbox  = lb;
        lbImg     = lb.querySelector('.ec-lb-img');
        lbClose   = lb.querySelector('.ec-lb-close');
        lbPrev    = lb.querySelector('.ec-lb-prev');
        lbNext    = lb.querySelector('.ec-lb-next');

        lbClose.addEventListener('click', closeLightbox);
        lb.querySelector('.ec-lb-overlay').addEventListener('click', closeLightbox);
        lbPrev.addEventListener('click', function () { navigate(-1); });
        lbNext.addEventListener('click', function () { navigate(1); });
    }

    function openLightbox(index) {
        if (!images.length) return;
        current = Math.max(0, Math.min(index, images.length - 1));
        renderLbImage();
        lightbox.classList.add('ec-lightbox--open');
        document.body.style.overflow = 'hidden';
        lbClose.focus();
    }

    function closeLightbox() {
        lightbox.classList.remove('ec-lightbox--open');
        document.body.style.overflow = '';
    }

    function navigate(dir) {
        current = (current + dir + images.length) % images.length;
        renderLbImage();
    }

    function renderLbImage() {
        lbImg.src = images[current];
        lbImg.alt = 'Imagen ' + (current + 1) + ' de ' + images.length;
        var counter = lightbox.querySelector('.ec-lb-counter');
        counter.textContent = (current + 1) + ' / ' + images.length;
        lbPrev.style.display = images.length > 1 ? '' : 'none';
        lbNext.style.display = images.length > 1 ? '' : 'none';
    }

    // ── Recopilar imágenes del carousel ──────────────────────────────────────
    function collectImages() {
        images = [];
        document.querySelectorAll('.product-single-carousel .product-single-image').forEach(function (img) {
            var zoom = img.getAttribute('data-zoom-image') || img.src;
            if (zoom) images.push(zoom);
        });
    }

    // ── Miniaturas: resaltar activa ───────────────────────────────────────────
    function syncThumbs(activeIndex) {
        document.querySelectorAll('#carousel-custom-dots .owl-dot').forEach(function (dot, i) {
            dot.classList.toggle('ec-thumb-active', i === activeIndex);
        });
    }

    // ── Inicialización ────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var carousel = document.querySelector('.product-single-carousel');
        if (!carousel) return;

        buildLightbox();
        collectImages();

        // Abrir lightbox desde la imagen principal
        document.querySelectorAll('.product-single-carousel .product-item').forEach(function (item, i) {
            item.style.cursor = 'zoom-in';
            item.addEventListener('click', function () { openLightbox(i); });
        });

        // Abrir lightbox desde el ícono de pantalla completa
        var fullScreenBtn = document.querySelector('.prod-full-screen');
        if (fullScreenBtn) {
            fullScreenBtn.style.cursor = 'pointer';
            fullScreenBtn.addEventListener('click', function () { openLightbox(0); });
        }

        // Sincronizar Owl Carousel → activa miniatura
        var $owl = window.jQuery && jQuery('.product-single-carousel');
        if ($owl && $owl.length) {
            $owl.on('changed.owl.carousel', function (e) {
                syncThumbs(e.item.index);
            });
        }
        // Resaltar primera miniatura al iniciar
        syncThumbs(0);

        // Navegación por teclado dentro del lightbox
        document.addEventListener('keydown', function (e) {
            if (!lightbox || !lightbox.classList.contains('ec-lightbox--open')) return;
            if (e.key === 'ArrowLeft')  navigate(-1);
            if (e.key === 'ArrowRight') navigate(1);
            if (e.key === 'Escape')     closeLightbox();
        });

        // Swipe táctil básico en el lightbox
        var touchStartX = null;
        var lbImgWrap = lightbox.querySelector('.ec-lb-img-wrap');
        lbImgWrap.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].clientX;
        });
        lbImgWrap.addEventListener('touchend', function (e) {
            if (touchStartX === null) return;
            var dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 40) navigate(dx < 0 ? 1 : -1);
            touchStartX = null;
        });
    });

}());
