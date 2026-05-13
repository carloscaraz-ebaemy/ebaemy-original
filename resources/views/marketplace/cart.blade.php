@extends('marketplace.layout')

@section('title', 'Tu carrito — Marketplace ebaemy')
@section('description', 'Revisa los productos de tu carrito agrupados por tienda. Cada tienda gestiona su propia entrega y comprobante.')
@section('canonical', route('marketplace.cart'))

@push('styles')
<style>
.mp-cart-wrapper {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    margin-bottom: 40px;
}
@media (max-width: 899px) { .mp-cart-wrapper { grid-template-columns: 1fr; } }

.mp-cart-store {
    background: #fff;
    border: 1px solid var(--mp-border, #e5e7eb);
    border-radius: 14px;
    padding: 18px;
    margin-bottom: 16px;
}
.mp-cart-store-header {
    display: flex; align-items: center; gap: 10px;
    padding-bottom: 12px; margin-bottom: 14px;
    border-bottom: 1px solid #f3f4f6;
}
.mp-cart-store-logo {
    width: 36px; height: 36px; border-radius: 8px;
    object-fit: contain; background: #fff; border: 1px solid #e5e7eb;
}
.mp-cart-store-name { font-weight: 600; font-size: 14px; color: var(--mp-ink, #111827); flex: 1; }
.mp-cart-store-name a { color: inherit; text-decoration: none; }
.mp-cart-store-name a:hover { color: var(--mp-primary-dark, #0c6b65); }
.mp-cart-store-subtotal { font-weight: 700; color: var(--mp-primary-dark, #0c6b65); font-size: 14px; }

.mp-cart-line { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px dashed #f3f4f6; }
.mp-cart-line:last-child { border-bottom: none; }
.mp-cart-line-img { width: 72px; height: 72px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #f9fafb; }
.mp-cart-line-img img { width: 100%; height: 100%; object-fit: cover; }
.mp-cart-line-body { flex: 1; min-width: 0; }
.mp-cart-line-title {
    display: block; font-size: 14px; color: var(--mp-ink, #111827);
    text-decoration: none; line-height: 1.35;
    overflow: hidden; text-overflow: ellipsis;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.mp-cart-line-title:hover { color: var(--mp-primary-dark, #0c6b65); }
.mp-cart-line-controls { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.mp-cart-line-price { font-weight: 700; color: var(--mp-ink, #111827); margin-left: auto; }
.mp-cart-qty {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1px solid #e5e7eb; border-radius: 8px; padding: 2px;
}
.mp-cart-qty button {
    width: 28px; height: 28px; border: none; background: transparent;
    cursor: pointer; font-size: 16px; color: #4b5563; border-radius: 6px;
}
.mp-cart-qty button:hover:not(:disabled) { background: #f3f4f6; color: var(--mp-primary-dark, #0c6b65); }
.mp-cart-qty input { width: 36px; text-align: center; border: none; outline: none; font-weight: 600; }
.mp-cart-line-remove {
    background: transparent; border: none; color: #dc2626; cursor: pointer;
    font-size: 18px; padding: 4px;
}

.mp-cart-summary {
    background: #fff; border: 1px solid var(--mp-border, #e5e7eb); border-radius: 14px;
    padding: 22px; position: sticky; top: 16px; height: fit-content;
}
.mp-cart-summary h3 { margin: 0 0 14px; font-size: 16px; color: var(--mp-ink, #111827); }
.mp-cart-summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
.mp-cart-summary-total { padding: 14px 0; border-top: 1px solid #e5e7eb; margin-top: 10px; }
.mp-cart-summary-total .label { font-weight: 600; }
.mp-cart-summary-total .value { font-size: 22px; font-weight: 800; color: var(--mp-primary-dark, #0c6b65); }
.mp-cart-checkout-btn {
    display: block; width: 100%; padding: 14px;
    background: var(--mp-primary, #0f8a82); color: #fff; border: none;
    border-radius: 10px; font-size: 15px; font-weight: 700;
    text-decoration: none; text-align: center; cursor: pointer;
    transition: background .15s; margin-top: 14px;
}
.mp-cart-checkout-btn:hover { background: var(--mp-primary-dark, #0c6b65); color: #fff; }
.mp-cart-checkout-btn:disabled { background: #d1d5db; cursor: not-allowed; }

.mp-cart-info-box {
    background: #fef3c7; border: 1px solid #fde68a; color: #92400e;
    padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-top: 16px;
}

.mp-cart-empty {
    text-align: center; padding: 60px 20px;
    background: #fff; border: 1px dashed #e5e7eb; border-radius: 14px;
}
.mp-cart-empty h3 { color: var(--mp-ink, #111827); }
.mp-cart-empty .btn-cta {
    display: inline-block; margin-top: 16px;
    padding: 12px 28px; background: var(--mp-primary, #0f8a82); color: #fff;
    border-radius: 10px; text-decoration: none; font-weight: 600;
}
</style>
@endpush

@section('content')

<nav class="mp-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('marketplace.index') }}">Marketplace</a>
    <span class="sep">›</span>
    <span style="color:var(--mp-ink);font-weight:500">Mi carrito</span>
</nav>

<h1 style="margin:0 0 20px; font-size: clamp(22px, 3vw, 28px); color: var(--mp-ink, #111827);">
    🛒 Tu carrito ({{ $summary['count'] }} {{ $summary['count'] === 1 ? 'producto' : 'productos' }})
</h1>

@if($stores->isEmpty())
    <div class="mp-cart-empty">
        <div style="font-size: 56px; margin-bottom: 12px">🛒</div>
        <h3>Tu carrito está vacío</h3>
        <p style="color: #6b7280">Explora el marketplace y añade productos de cualquiera de nuestras tiendas verificadas.</p>
        <a href="{{ route('marketplace.index') }}" class="btn-cta">Explorar marketplace →</a>
    </div>
    @include('marketplace.partials.recently-viewed', ['recentlyViewed' => $recentlyViewed ?? collect()])
@else
    <div class="mp-cart-wrapper">
        <div>
            @foreach($stores as $store)
                <section class="mp-cart-store" data-hostname="{{ $store['hostname_id'] }}">
                    <div class="mp-cart-store-header">
                        @if($store['tenant_logo_url'])
                            <img src="{{ $store['tenant_logo_url'] }}" alt="{{ $store['tenant_name'] }}" class="mp-cart-store-logo">
                        @endif
                        <div class="mp-cart-store-name">
                            @if($store['tenant_subdomain'])
                                <a href="{{ route('marketplace.tenant', ['subdomain' => $store['tenant_subdomain']]) }}">
                                    {{ $store['tenant_name'] }}
                                </a>
                            @else
                                {{ $store['tenant_name'] }}
                            @endif
                            <div style="font-weight:400;font-size:12px;color:#6b7280;margin-top:2px">
                                <span data-store-count>{{ $store['item_count'] }}</span> <span data-store-count-word>{{ $store['item_count'] === 1 ? 'unidad' : 'unidades' }}</span> · esta tienda gestiona su entrega
                            </div>
                        </div>
                        <div class="mp-cart-store-subtotal" data-store-subtotal>S/ {{ number_format($store['subtotal'], 2) }}</div>
                    </div>

                    @foreach($store['items'] as $line)
                        <div class="mp-cart-line"
                             data-listing="{{ $line['listing_id'] }}"
                             data-unit-price="{{ $line['price'] }}">
                            <a href="{{ route('marketplace.item', $line['slug']) }}" class="mp-cart-line-img">
                                @if($line['image_url'])
                                    <img src="{{ $line['image_url'] }}" alt="{{ $line['title'] }}" loading="lazy">
                                @endif
                            </a>
                            <div class="mp-cart-line-body">
                                <a href="{{ route('marketplace.item', $line['slug']) }}" class="mp-cart-line-title">
                                    {{ $line['title'] }}
                                    @if(!empty($line['is_pack']))
                                        <span style="display:inline-block;margin-left:6px;background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;font-size:10.5px;font-weight:700;padding:1px 7px;border-radius:999px;vertical-align:middle">
                                            📦 Pack{{ !empty($line['pack_count']) ? ' ×' . $line['pack_count'] : '' }}
                                        </span>
                                    @endif
                                </a>
                                <div class="mp-cart-line-controls">
                                    <div class="mp-cart-qty">
                                        <button type="button" class="mp-cart-qty-dec" aria-label="Disminuir">−</button>
                                        <input type="number" value="{{ $line['quantity'] }}" min="1" max="99" class="mp-cart-qty-input">
                                        <button type="button" class="mp-cart-qty-inc" aria-label="Aumentar">+</button>
                                    </div>
                                    <button type="button" class="mp-cart-line-remove" aria-label="Eliminar">🗑️</button>
                                    <span class="mp-cart-line-price">S/ {{ number_format($line['price'] * $line['quantity'], 2) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>

        <aside class="mp-cart-summary">
            <h3>Resumen</h3>
            <div class="mp-cart-summary-row"><span>Productos</span><span data-summary-count>{{ $summary['count'] }}</span></div>
            <div class="mp-cart-summary-row"><span>Tiendas</span><span>{{ $stores->count() }}</span></div>
            <div class="mp-cart-summary-row"><span>Subtotal</span><span data-summary-subtotal>S/ {{ number_format($summary['subtotal'], 2) }}</span></div>
            <div class="mp-cart-summary-row" style="color:#6b7280"><span>Envío</span><span>Lo coordina cada tienda</span></div>
            <div class="mp-cart-summary-total" style="display:flex;justify-content:space-between;align-items:center">
                <span class="label">Total</span>
                <span class="value" data-summary-total>S/ {{ number_format($summary['total'], 2) }}</span>
            </div>

            <a href="{{ route('marketplace.checkout') }}" class="mp-cart-checkout-btn">
                Continuar al pedido →
            </a>

            <div class="mp-cart-info-box">
                ⚠️ Tu compra incluye productos de {{ $stores->count() }} {{ $stores->count() === 1 ? 'tienda' : 'tiendas distintas' }}.
                Cada vendedor te contactará por separado para coordinar entrega y comprobante.
            </div>
        </aside>
    </div>
@endif

<script>
(function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || @json(csrf_token());

    function patchLine(listingId, quantity) {
        return fetch(@json(url('/marketplace/cart')) + '/' + listingId, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ quantity })
        }).then(r => r.json());
    }

    function deleteLine(listingId) {
        return fetch(@json(url('/marketplace/cart')) + '/' + listingId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        }).then(r => r.json());
    }

    // Formato monetario consistente
    const fmtMoney = n => 'S/ ' + (Number(n) || 0).toFixed(2);

    // Recalcular totales en el DOM sin recargar la página. Se llama después
    // de cada PATCH/DELETE exitoso.
    function recalcTotals() {
        let grandSubtotal = 0;
        let grandCount = 0;

        document.querySelectorAll('.mp-cart-store').forEach(store => {
            let storeSubtotal = 0;
            let storeCount = 0;
            store.querySelectorAll('.mp-cart-line').forEach(line => {
                const qty = parseInt(line.querySelector('.mp-cart-qty-input')?.value, 10) || 0;
                const unit = parseFloat(line.dataset.unitPrice) || 0;
                const lineTotal = qty * unit;
                storeSubtotal += lineTotal;
                storeCount += qty;
                const priceEl = line.querySelector('.mp-cart-line-price');
                if (priceEl) priceEl.textContent = fmtMoney(lineTotal);
            });
            grandSubtotal += storeSubtotal;
            grandCount += storeCount;
            const sub = store.querySelector('[data-store-subtotal]');
            if (sub) sub.textContent = fmtMoney(storeSubtotal);
            const c1 = store.querySelector('[data-store-count]');
            const c2 = store.querySelector('[data-store-count-word]');
            if (c1) c1.textContent = storeCount;
            if (c2) c2.textContent = storeCount === 1 ? 'unidad' : 'unidades';
        });

        document.querySelectorAll('[data-summary-count]').forEach(el => el.textContent = grandCount);
        document.querySelectorAll('[data-summary-subtotal]').forEach(el => el.textContent = fmtMoney(grandSubtotal));
        document.querySelectorAll('[data-summary-total]').forEach(el => el.textContent = fmtMoney(grandSubtotal));

        // Si el cart quedó vacío, recargamos para que el server muestre el
        // empty state ("Tu carrito está vacío") — sería raro mantener el
        // sidebar visible con 0 items.
        if (grandCount === 0) window.location.reload();
    }

    document.querySelectorAll('.mp-cart-line').forEach(function (line) {
        const listingId = line.dataset.listing;
        const input = line.querySelector('.mp-cart-qty-input');
        const dec = line.querySelector('.mp-cart-qty-dec');
        const inc = line.querySelector('.mp-cart-qty-inc');
        const rm  = line.querySelector('.mp-cart-line-remove');

        function commit(newQty) {
            const safe = Math.max(1, Math.min(99, newQty));
            // Optimistic UI: actualizamos el input ya, luego sincronizamos
            // con el server. Si el server falla, hacemos rollback al value
            // anterior.
            const previous = parseInt(input.value, 10) || 1;
            input.value = safe;
            recalcTotals();

            // Solo dispara el PATCH si el valor cambió
            if (safe === previous) return;

            patchLine(listingId, safe).then(function (resp) {
                if (!resp || !resp.success) {
                    // Rollback
                    input.value = previous;
                    recalcTotals();
                    alert(resp?.message || 'No se pudo actualizar la cantidad.');
                }
            }).catch(() => {
                input.value = previous;
                recalcTotals();
            });
        }

        dec?.addEventListener('click', () => commit(parseInt(input.value, 10) - 1));
        inc?.addEventListener('click', () => commit(parseInt(input.value, 10) + 1));
        input?.addEventListener('change', () => commit(parseInt(input.value, 10) || 1));
        rm?.addEventListener('click', function () {
            if (!confirm('¿Quitar este producto del carrito?')) return;
            // Quitamos el line visual antes del PATCH para feedback inmediato
            const lineEl = line;
            lineEl.style.opacity = '0.4';
            deleteLine(listingId).then(function (resp) {
                if (resp && resp.success) {
                    lineEl.remove();
                    // Si la tienda se quedó sin lines, también la quitamos
                    document.querySelectorAll('.mp-cart-store').forEach(store => {
                        if (!store.querySelector('.mp-cart-line')) store.remove();
                    });
                    recalcTotals();
                } else {
                    lineEl.style.opacity = '1';
                    alert('No se pudo eliminar el producto.');
                }
            }).catch(() => { lineEl.style.opacity = '1'; });
        });
    });
})();
</script>
@endsection
