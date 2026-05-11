<script>
// Hover sobre dots (color o variante) → cambia la imagen principal de la
// card y mueve el "is-active" al dot bajo el cursor. Sticky: al salir del
// card, la imagen y el dot activo quedan donde estaban.
document.querySelectorAll('.mp-card').forEach(function (card) {
    var dots = card.querySelectorAll('.mp-card-variant-dot, .mp-card-color-dot[data-img]');
    if (!dots.length) return;
    var primary = card.querySelector('.mp-card-img-primary');
    if (!primary) return;
    var allDots = card.querySelectorAll('.mp-card-color-dot');
    dots.forEach(function (dot) {
        dot.addEventListener('mouseenter', function () {
            var url = dot.getAttribute('data-img');
            if (url) primary.src = url;
            if (dot.classList.contains('mp-card-color-dot')) {
                allDots.forEach(function (d) { d.classList.remove('is-active'); });
                dot.classList.add('is-active');
            }
        });
        dot.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
});

// Galería rotativa al hover sobre la card (estilo AliExpress / TikTok Shop).
// Si la card tiene data-gallery con un array JSON de URLs, al hover ciclamos
// cada 1.2s. Al salir, volvemos a la imagen original. NO interfiere con
// el hover-image específico de los dots (data-img individual prevalece).
document.querySelectorAll('.mp-card[data-gallery]').forEach(function (card) {
    var primary = card.querySelector('.mp-card-img-primary');
    if (!primary) return;
    var gallery;
    try { gallery = JSON.parse(card.getAttribute('data-gallery') || '[]'); } catch (_) { return; }
    if (!Array.isArray(gallery) || gallery.length < 2) return;

    var originalSrc = primary.getAttribute('src');
    var timer = null;
    var idx = 0;
    var hoveringDot = false;  // si está hovering un dot, dejamos que el dot mande

    card.addEventListener('mouseenter', function () {
        if (timer || hoveringDot) return;
        idx = 0;
        timer = setInterval(function () {
            if (hoveringDot) return;
            idx = (idx + 1) % gallery.length;
            primary.src = gallery[idx];
        }, 1200);
    });
    card.addEventListener('mouseleave', function () {
        if (timer) { clearInterval(timer); timer = null; }
        primary.src = originalSrc;
    });

    // Bloquear el slideshow mientras el cursor esté sobre un dot con data-img
    // (la imagen del dot prevalece sobre el slideshow).
    card.querySelectorAll('.mp-card-color-dot[data-img], .mp-card-variant-dot').forEach(function (d) {
        d.addEventListener('mouseenter', function () { hoveringDot = true; });
        d.addEventListener('mouseleave', function () { hoveringDot = false; });
    });
});

// Click en el nombre de la tienda dentro de la card → navega a la página
// pública de esa tienda. Como la card entera es <a>, no podemos anidar
// otro <a>; usamos span con data-href + stopPropagation.
document.querySelectorAll('.js-shop-link').forEach(function (el) {
    el.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var href = el.getAttribute('data-href');
        if (href) window.location.href = href;
    });
});
</script>
