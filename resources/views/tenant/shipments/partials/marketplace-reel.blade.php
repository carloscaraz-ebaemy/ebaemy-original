{{-- ══════════════════════════════════════════════════════════════════════
     CARRUSEL DE PRODUCTOS DEL MARKETPLACE (venta cruzada)
     Se muestra en el formulario público de envíos: el cliente ya está en la
     tienda, así que se le enseñan los productos que ESTA tienda publicó en el
     marketplace, con los que tienen descuento real primero.

     Al tocar una tarjeta se abre la ficha del producto en el marketplace
     (dominio central), en una pestaña nueva para no perder el formulario a
     medio llenar.

     Sin librerías: scroll horizontal con scroll-snap. Las flechas son un
     extra para escritorio; en móvil se arrastra con el dedo.

     Espera: $mpReel (colección) y $company.
     ══════════════════════════════════════════════════════════════════════ --}}
@if(!empty($mpReel) && count($mpReel))
<div class="mpreel">
    <div class="mpreel__head">
        <div>
            <div class="mpreel__t">🛍️ También en nuestra tienda</div>
            <div class="mpreel__s">Míralos mientras registras tu envío</div>
        </div>
        <div class="mpreel__nav">
            <button type="button" class="mpreel__arrow" data-reel="prev" aria-label="Anterior">‹</button>
            <button type="button" class="mpreel__arrow" data-reel="next" aria-label="Siguiente">›</button>
        </div>
    </div>

    <div class="mpreel__track" id="mpreelTrack">
        @foreach($mpReel as $p)
            <a class="mpcard" href="{{ $p['url'] }}" target="_blank" rel="noopener">
                <div class="mpcard__img">
                    @if($p['image'])
                        <img src="{{ $p['image'] }}" alt="{{ $p['title'] }}" loading="lazy">
                    @else
                        <span class="mpcard__noimg">Sin foto</span>
                    @endif
                    @if($p['discount'])
                        <span class="mpcard__off">-{{ $p['discount'] }}%</span>
                    @endif
                </div>
                <div class="mpcard__body">
                    <div class="mpcard__title">{{ $p['title'] }}</div>
                    <div class="mpcard__prices">
                        <span class="mpcard__price">S/ {{ number_format($p['price'], 2) }}</span>
                        @if($p['before'])
                            <span class="mpcard__before">S/ {{ number_format($p['before'], 2) }}</span>
                        @endif
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>

<style>
    .mpreel { max-width:560px; margin:18px auto 0; }
    .mpreel__head { display:flex; align-items:flex-end; justify-content:space-between;
                    gap:10px; margin-bottom:10px; padding:0 2px; }
    .mpreel__t { font-size:.95rem; font-weight:700; color:var(--ink, #0f172a); }
    .mpreel__s { font-size:.78rem; color:var(--muted, #6b7280); margin-top:1px; }
    .mpreel__nav { display:none; gap:6px; }
    .mpreel__arrow { width:30px; height:30px; border-radius:50%; border:1px solid var(--line, #e5e7eb);
                     background:#fff; color:var(--ink, #0f172a); font-size:17px; line-height:1;
                     cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .mpreel__arrow:hover { background:var(--brand, #2563eb); border-color:var(--brand, #2563eb); color:#fff; }

    /* Carrusel: scroll con snap. Sin JS obligatorio — en móvil se arrastra. */
    .mpreel__track { display:flex; gap:10px; overflow-x:auto; scroll-snap-type:x mandatory;
                     -webkit-overflow-scrolling:touch; scroll-behavior:smooth;
                     padding:2px 2px 10px; scrollbar-width:thin; }
    .mpreel__track::-webkit-scrollbar { height:5px; }
    .mpreel__track::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:99px; }

    .mpcard { flex:0 0 148px; scroll-snap-align:start; text-decoration:none; color:inherit;
              background:#fff; border:1px solid var(--line, #e5e7eb); border-radius:14px;
              overflow:hidden; transition:transform .15s, box-shadow .15s, border-color .15s; }
    .mpcard:hover { transform:translateY(-2px); border-color:var(--brand, #2563eb);
                    box-shadow:0 10px 24px -12px rgba(15,23,42,.35); }
    .mpcard__img { position:relative; aspect-ratio:1; background:#f1f5f9; }
    .mpcard__img img { width:100%; height:100%; object-fit:cover; display:block; }
    .mpcard__noimg { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                     font-size:.72rem; color:#94a3b8; }
    .mpcard__off { position:absolute; top:6px; left:6px; background:#dc2626; color:#fff;
                   font-size:.68rem; font-weight:800; padding:2px 7px; border-radius:99px;
                   box-shadow:0 2px 6px rgba(0,0,0,.2); }
    .mpcard__body { padding:8px 9px 10px; }
    .mpcard__title { font-size:.76rem; line-height:1.3; font-weight:600; min-height:2.6em;
                     display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
                     overflow:hidden; }
    .mpcard__prices { display:flex; align-items:baseline; gap:6px; margin-top:5px; flex-wrap:wrap; }
    .mpcard__price { font-size:.9rem; font-weight:800; color:var(--ink, #0f172a); }
    .mpcard__before { font-size:.72rem; color:var(--muted, #6b7280); text-decoration:line-through; }

    @media (min-width: 640px) { .mpreel__nav { display:flex; } }
</style>

<script>
/* Flechas del carrusel (solo escritorio). En móvil se arrastra con el dedo. */
(function () {
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.mpreel__arrow') : null;
        if (!btn) return;

        var track = document.getElementById('mpreelTrack');
        if (!track) return;

        var card = track.querySelector('.mpcard');
        var step = card ? (card.offsetWidth + 10) * 2 : 300;

        track.scrollBy({
            left: btn.getAttribute('data-reel') === 'next' ? step : -step,
            behavior: 'smooth'
        });
    });
})();
</script>
@endif
