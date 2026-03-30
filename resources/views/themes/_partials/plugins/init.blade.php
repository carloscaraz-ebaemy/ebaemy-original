{{-- Inicialización automática de plugins cargados --}}
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ══ GSAP: Animaciones al scroll ══
    if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);

        // Fade in al aparecer en viewport
        document.querySelectorAll('.gsap-fade-in').forEach(function(el) {
            gsap.from(el, {
                scrollTrigger: { trigger: el, start: 'top 85%', once: true },
                opacity: 0, y: 30, duration: 0.7, ease: 'power2.out'
            });
        });

        // Stagger de cards
        document.querySelectorAll('.gsap-stagger').forEach(function(container) {
            gsap.from(container.children, {
                scrollTrigger: { trigger: container, start: 'top 85%', once: true },
                opacity: 0, y: 20, stagger: 0.08, duration: 0.5, ease: 'power2.out'
            });
        });
    }

    // ══ GLightbox: Galería de imágenes ══
    if (typeof GLightbox !== 'undefined') {
        GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true });
    }

    // ══ Swiper: Carruseles automáticos ══
    if (typeof Swiper !== 'undefined') {
        // Carrusel de productos destacados
        document.querySelectorAll('.ec-product-swiper').forEach(function(el) {
            new Swiper(el, {
                slidesPerView: 2,
                spaceBetween: 16,
                navigation: { nextEl: el.querySelector('.swiper-button-next'), prevEl: el.querySelector('.swiper-button-prev') },
                pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
                breakpoints: { 640: { slidesPerView: 3 }, 1024: { slidesPerView: 4 }, 1280: { slidesPerView: 5 } },
                autoplay: { delay: 5000, disableOnInteraction: false },
            });
        });
    }

    // ══ Drift Zoom: Zoom de imagen al hover ══
    if (typeof Drift !== 'undefined') {
        document.querySelectorAll('[data-drift-zoom]').forEach(function(img) {
            new Drift(img, {
                paneContainer: img.closest('.ec-drift-container') || document.body,
                inlinePane: 900,
                containInline: true,
                hoverBoundingBox: true,
                zoomFactor: 2,
            });
        });
    }

});
</script>
