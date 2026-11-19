{{--
    Reproductor «lite» de los videos de producto (2026-11-19).

    La página solo pinta una miniatura con ▶; el iframe del proveedor (que
    pesa cientos de KB y abre conexiones a terceros) se crea al TOCAR. Así el
    video nunca es el LCP ni resta PageSpeed (project_pagespeed_lcp_marketplace).

    Seguridad: el src viene del servidor ya armado desde el id validado
    (App\Services\Marketplace\VideoLink::embedUrl) y aquí se vuelve a
    comprobar contra la lista de prefijos permitidos antes de crear el iframe.

    window.mpVideoPlayer(src, opts) → el nodo (iframe o <video>) o null.
    Lo usan [data-mp-video] (ficha y bottom sheet) y el modal del inicio
    (partials/video-reels).
--}}
<script>
(function () {
    if (window.mpVideoPlayer) return;

    var EMBEDS = [
        /^https:\/\/www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]{11}$/,
        /^https:\/\/www\.tiktok\.com\/embed\/v2\/\d{8,25}$/,
        /^https:\/\/www\.instagram\.com\/reel\/[A-Za-z0-9_-]{5,40}\/embed$/
    ];

    function allowedEmbed(src) {
        for (var i = 0; i < EMBEDS.length; i++) if (EMBEDS[i].test(src)) return true;
        return false;
    }

    window.mpVideoPlayer = function (src, opts) {
        opts = opts || {};
        src = String(src || '');
        if (opts.kind === 'file') {
            if (!/^https:\/\//.test(src)) return null;
            var v = document.createElement('video');
            v.src = src;
            v.controls = true;
            v.autoplay = true;
            v.setAttribute('playsinline', '');
            v.className = 'mp-vplayer';
            return v;
        }
        if (!allowedEmbed(src)) return null;
        // Autoplay solo donde el proveedor lo admite (YouTube); en TikTok e
        // Instagram el comprador toca play dentro del reproductor.
        if (src.indexOf('youtube-nocookie.com') !== -1) src += '?autoplay=1&playsinline=1&rel=0';
        var f = document.createElement('iframe');
        f.src = src;
        f.className = 'mp-vplayer';
        f.title = opts.title ? ('Video: ' + opts.title) : 'Video del producto';
        f.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
        f.setAttribute('allowfullscreen', '');
        f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        f.setAttribute('loading', 'eager');
        return f;
    };

    // Ficha (y ficha dentro del bottom sheet): la miniatura se cambia por el
    // reproductor en su sitio.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-mp-video]');
        if (!btn) return;
        e.preventDefault();
        var box = btn.closest('.mp-pvideo');
        var player = window.mpVideoPlayer(btn.getAttribute('data-mp-video'), {
            kind: btn.getAttribute('data-mp-video-kind'),
            title: btn.getAttribute('data-mp-video-title')
        });
        if (!player || !box) return;
        var stage = document.createElement('div');
        stage.className = 'mp-pvideo__stage mp-pvideo__stage--' + (btn.getAttribute('data-mp-video-orientation') === 'horizontal' ? 'horizontal' : 'vertical')
            + ' mp-pvideo__stage--' + (btn.getAttribute('data-mp-video-provider') || 'file');
        stage.appendChild(player);
        box.innerHTML = '';
        box.appendChild(stage);
        box.classList.add('is-playing');
    });
})();
</script>
