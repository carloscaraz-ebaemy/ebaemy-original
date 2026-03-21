/**
 * Lazy Load — IntersectionObserver para imágenes de producto
 * Clase activadora : ec-img-lazy
 * Atributo fuente  : data-src
 * Fallback         : carga directa si IntersectionObserver no está disponible
 */
(function () {
    'use strict';

    var SELECTOR = 'img.ec-img-lazy';
    var LOADED_CLASS = 'ec-img-lazy--loaded';
    var LOADING_CLASS = 'ec-img-lazy--loading';

    function loadImage(img) {
        var src = img.getAttribute('data-src');
        if (!src) return;

        img.classList.add(LOADING_CLASS);

        var tmp = new Image();
        tmp.onload = function () {
            img.src = src;
            img.classList.add(LOADED_CLASS);
            img.classList.remove(LOADING_CLASS, 'ec-img-lazy');
        };
        tmp.onerror = function () {
            // Fallback ya definido en el atributo onerror del <img>
            img.src = src;
            img.classList.add(LOADED_CLASS);
            img.classList.remove(LOADING_CLASS, 'ec-img-lazy');
        };
        tmp.src = src;
    }

    function initLazyLoad() {
        var images = document.querySelectorAll(SELECTOR);
        if (!images.length) return;

        // Fallback: sin IntersectionObserver, carga todo de inmediato
        if (!('IntersectionObserver' in window)) {
            images.forEach(loadImage);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    loadImage(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '200px 0px',   // Precarga 200 px antes de entrar al viewport
            threshold: 0
        });

        images.forEach(function (img) {
            observer.observe(img);
        });
    }

    // Ejecutar al cargar el DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyLoad);
    } else {
        initLazyLoad();
    }

    // Re-scan para imágenes cargadas dinámicamente (paginación Ajax, etc.)
    window.EcLazyLoad = {
        scan: function () { initLazyLoad(); }
    };

}());
