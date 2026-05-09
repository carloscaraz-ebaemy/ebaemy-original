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
