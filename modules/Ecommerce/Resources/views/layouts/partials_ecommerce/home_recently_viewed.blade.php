{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index. --}}
        {{-- ═══ PRODUCTOS VISTOS RECIENTEMENTE ═══ --}}
        <section class="ec-home-section ec-recently-section" id="ec-recently-viewed-section" style="display:none" aria-label="Vistos recientemente">
            <div class="ec-section-header">
                <h2 class="ec-section-title">Vistos recientemente</h2>
            </div>
            <div class="ec-recently-grid" id="ec-recently-viewed-grid"></div>
        </section>

<style>
/* ═══ RECENTLY VIEWED ═══ */
.ec-recently-section { margin-top: 2rem; }
.ec-recently-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; }
.ec-recently-card { text-align: center; text-decoration: none; color: inherit; }
.ec-recently-card img { width: 100%; aspect-ratio: 1; object-fit: contain; border-radius: 10px; border: 1px solid #f1f5f9; background: #fafafa; }
.ec-recently-card .ec-rc-name { font-size: 12px; color: #374151; margin-top: 6px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ec-recently-card .ec-rc-price { font-size: 14px; font-weight: 700; color: #4F46E5; }
@media(max-width:768px) { .ec-recently-grid { grid-template-columns: repeat(3, 1fr); } }
</style>

<script>
// Recently viewed - render from localStorage
document.addEventListener('DOMContentLoaded', function(){
    try {
        var viewed = JSON.parse(localStorage.getItem('recently_viewed') || '[]');
        if (!viewed.length) return;
        var section = document.getElementById('ec-recently-viewed-section');
        var grid = document.getElementById('ec-recently-viewed-grid');
        if (!section || !grid) return;
        var html = '';
        viewed.slice(0, 6).forEach(function(item){
            var img = item.image || '/logo/imagen-no-disponible.jpg';
            var url = '/ecommerce/item/' + (item.slug || item.id);
            html += '<a href="'+url+'" class="ec-recently-card">'
                + '<img src="'+img+'" alt="'+item.name+'" loading="lazy">'
                + '<span class="ec-rc-name">'+item.name+'</span>'
                + '<span class="ec-rc-price">S/ '+(Number(item.price)||0).toFixed(2)+'</span>'
                + '</a>';
        });
        grid.innerHTML = html;
        section.style.display = 'block';
    } catch(e) {}
});
</script>
