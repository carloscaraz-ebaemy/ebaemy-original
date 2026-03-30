{{-- Mejoras de producto: Drift Zoom + GLightbox --}}
{{-- Solo se incluye en páginas de producto --}}
<script>
document.addEventListener('DOMContentLoaded', function(){

    // ══ DRIFT ZOOM: Zoom al pasar mouse sobre imagen principal ══
    if (typeof Drift !== 'undefined') {
        var mainImg = document.querySelector('.product-single-image, .ropa-gallery__img, #ropa-main-img');
        if (mainImg) {
            // Crear contenedor del zoom
            var zoomPane = document.createElement('div');
            zoomPane.id = 'ec-drift-pane';
            zoomPane.style.cssText = 'position:absolute;top:0;right:-320px;width:300px;height:300px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.15);z-index:10;display:none;overflow:hidden;pointer-events:none';
            var parent = mainImg.closest('.product-single-gallery, .ropa-gallery__main, .product-slider-container');
            if (parent) {
                parent.style.position = 'relative';
                parent.appendChild(zoomPane);
            }

            var drift = new Drift(mainImg, {
                paneContainer: zoomPane,
                inlinePane: false,
                zoomFactor: 3,
                hoverBoundingBox: true,
                containInline: true,
                handleTouch: false,
                onShow: function(){ zoomPane.style.display = 'block'; },
                onHide: function(){ zoomPane.style.display = 'none'; },
            });

            // Actualizar Drift cuando cambia la imagen (variantes, thumbnails)
            var observer = new MutationObserver(function(mutations){
                mutations.forEach(function(m){
                    if(m.type === 'attributes' && m.attributeName === 'src'){
                        drift.setZoomImageURL(mainImg.src);
                    }
                });
            });
            observer.observe(mainImg, { attributes: true });

            // Indicador visual de zoom
            mainImg.style.cursor = 'zoom-in';
            mainImg.title = 'Pasa el mouse para hacer zoom';
        }
    }

    // ══ GLIGHTBOX: Galería fullscreen al clic en imagen ══
    if (typeof GLightbox !== 'undefined') {
        // Recoger todas las imágenes del producto
        var images = [];
        document.querySelectorAll('.product-single-image, .ropa-gallery__img, .ropa-thumb img').forEach(function(img){
            var src = img.getAttribute('data-zoom-image') || img.src;
            if (src && !images.includes(src)) images.push(src);
        });

        if (images.length > 0) {
            // Crear elementos ocultos para GLightbox
            var container = document.createElement('div');
            container.style.display = 'none';
            images.forEach(function(src, i){
                var a = document.createElement('a');
                a.href = src;
                a.className = 'ec-product-lightbox';
                a.dataset.gallery = 'product-gallery';
                container.appendChild(a);
            });
            document.body.appendChild(container);

            var lightbox = GLightbox({
                selector: '.ec-product-lightbox',
                touchNavigation: true,
                loop: true,
                closeOnOutsideClick: true,
            });

            // Abrir lightbox al clic en imagen principal
            var mainImgLink = document.querySelector('.product-single-image, .ropa-gallery__img, #ropa-main-img');
            if (mainImgLink) {
                mainImgLink.addEventListener('click', function(e){
                    e.preventDefault();
                    lightbox.openAt(0);
                });
            }

            // Botón de fullscreen si existe
            var fullscreenBtn = document.querySelector('.prod-full-screen');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    lightbox.openAt(0);
                });
            }
        }
    }

    // ══ GSAP: Animación de entrada del producto ══
    if (typeof gsap !== 'undefined') {
        // Animar info del producto al cargar
        var productInfo = document.querySelector('.product-single-details, .ropa-product-info');
        if (productInfo) {
            gsap.from(productInfo, {
                opacity: 0, x: 30, duration: 0.7, ease: 'power2.out', delay: 0.2
            });
        }

        // Animar imagen
        var productGallery = document.querySelector('.product-single-gallery, .ropa-gallery');
        if (productGallery) {
            gsap.from(productGallery, {
                opacity: 0, x: -30, duration: 0.7, ease: 'power2.out', delay: 0.1
            });
        }

        // Animar tabs
        var tabs = document.querySelector('.product-single-tabs');
        if (tabs) {
            gsap.from(tabs, {
                scrollTrigger: { trigger: tabs, start: 'top 90%' },
                opacity: 0, y: 20, duration: 0.5, ease: 'power2.out'
            });
        }
    }

});
</script>
