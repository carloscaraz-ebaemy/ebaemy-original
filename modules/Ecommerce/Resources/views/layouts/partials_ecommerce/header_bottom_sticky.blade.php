<style>
.ec-catbar{overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.ec-catbar::-webkit-scrollbar{display:none}
.ec-catbar__list{display:flex;list-style:none;margin:0;padding:8px 0;justify-content:center;gap:0;flex-wrap:nowrap}
.ec-catbar__item{flex-shrink:0}
.ec-catbar__item a{display:block;padding:4px 16px;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#fff;white-space:nowrap;transition:opacity .2s}
.ec-catbar__item a:hover{opacity:.75;text-decoration:none}
.ec-catbar__item+.ec-catbar__item{border-left:1px solid rgba(255,255,255,.3)}
@media(max-width:991px){
    .ec-catbar__list{justify-content:flex-start}
    .ec-catbar__item a{font-size:12px;padding:4px 12px}
}
</style>
<div class="header-bottom sticky-header">
    <div class="container">
        <nav class="ec-catbar">
            <ul class="ec-catbar__list">
                @foreach ($items as $item)
                <li class="ec-catbar__item">
                    <a href="{{ route('tenant.ecommerce.category', ['category' => $item->id]) }}">{{ $item->name }}</a>
                </li>
                @endforeach
            </ul>
        </nav>
    </div>
</div>
