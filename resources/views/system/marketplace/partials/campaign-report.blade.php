{{--
    Informe por campaña del marketplace: de dónde vino cada pedido.

    En un partial propio, y no embebido en dashboard.blade.php, para poder
    renderizarlo aislado en una prueba. El dashboard completo no se puede
    renderizar fuera de un request real (el header del layout resuelve el
    usuario por el guard por defecto, que apunta a la conexión de tenant).

    Recibe `byCampaign` y `campaignStats` de MarketplaceAdminController@dashboard.
--}}
{{-- ════════════ De qué campaña vienen los pedidos ════════════ --}}
<div class="mpd-panel mpd-panel--table">
    <div class="mpd-panel__head">
        <h5 class="mpd-panel__title">De qué campaña vienen los pedidos</h5>
        <span class="mpd-panel__count">
            {{ $campaignStats['campaigns'] }} {{ $campaignStats['campaigns'] === 1 ? 'campaña' : 'campañas' }} con actividad
        </span>
    </div>

    {{-- Cobertura: el primer número a mirar. Con cobertura baja el resto
         de la tabla no significa nada todavía. --}}
    <div class="mpd-cov">
        <div class="mpd-cov__bar">
            <span class="mpd-cov__fill" style="width:{{ max(1, (float) $campaignStats['coverage']) }}%"></span>
        </div>
        <div class="mpd-cov__txt">
            <strong>{{ number_format($campaignStats['orders_attributed']) }}</strong>
            de {{ number_format($campaignStats['orders_total']) }} pedidos traen origen conocido
            ({{ $campaignStats['coverage'] }}%) ·
            <strong>S/ {{ number_format($campaignStats['revenue_attributed'], 2) }}</strong> atribuidos
        </div>
    </div>

    @if($campaignStats['orders_total'] > 0 && $campaignStats['orders_attributed'] === 0)
        <div class="mpd-cov__hint">
            Ningún pedido de este rango trae campaña. Lo normal es que las URLs de
            destino de los anuncios no lleven UTM: añadí
            <code>?utm_source=tiktok&amp;utm_medium=cpc&amp;utm_campaign=&lt;nombre&gt;</code>
            a la URL del anuncio y los pedidos siguientes se podrán separar aquí.
        </div>
    @endif

    <div class="table-responsive">
        <table class="mpd-table">
            <thead>
                <tr>
                    <th>Origen</th>
                    <th>Campaña</th>
                    <th>Medio</th>
                    <th class="mpd-th-r">Pedidos</th>
                    <th class="mpd-th-r">Ingresos</th>
                    <th class="mpd-th-r">Ticket medio</th>
                    <th class="mpd-th-r">Leads</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byCampaign as $c)
                    @php $sinOrigen = !$c->source && !$c->campaign; @endphp
                    <tr class="{{ $sinOrigen ? 'mpd-tr--muted' : '' }}">
                        <td>
                            @if($sinOrigen)
                                <span class="mpd-td-tenant">Sin campaña — directo u orgánico</span>
                            @else
                                <strong>{{ $c->source ?: '—' }}</strong>
                            @endif
                        </td>
                        <td class="mpd-td-tenant">{{ $c->campaign ?: ($sinOrigen ? '' : '—') }}</td>
                        <td class="mpd-td-tenant">{{ $c->medium ?: '' }}</td>
                        <td class="mpd-th-r">{{ number_format($c->orders) }}</td>
                        <td class="mpd-th-r">S/ {{ number_format($c->revenue, 2) }}</td>
                        <td class="mpd-th-r mpd-td-tenant">
                            {{ $c->orders > 0 ? 'S/ ' . number_format($c->ticket, 2) : '' }}
                        </td>
                        <td class="mpd-th-r mpd-td-leads">{{ number_format($c->leads) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mpd-empty">
                        Sin pedidos ni leads en este rango.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mpd-panel__foot">
        Cuenta pedidos del marketplace, no sub-pedidos por tienda: un pedido con
        productos de tres tiendas es <strong>uno</strong> aquí y tres en el KPI de
        arriba. Y no aplica el filtro de tienda — una campaña es del marketplace y
        su pedido puede repartirse entre varias.
    </div>
</div>
