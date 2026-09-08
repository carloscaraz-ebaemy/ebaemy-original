<template>
    <div class="orders" v-loading="loading_submit">
        <div class="page-header pe-0">
            <h2>
                <a href="/orders">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 17h-11v-14h-2" />
                        <path d="M6 5l14 1l-1 7h-13" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Pedidos</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right"></div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <div class="ord-kpis">
                    <div class="ord-kpi">
                        <div class="ord-kpi-label">Por despachar</div>
                        <div class="ord-kpi-val">{{ chipCounts.todispatch || 0 }}</div>
                    </div>
                    <div class="ord-kpi ord-kpi-warn">
                        <div class="ord-kpi-label">Falta emitir</div>
                        <div class="ord-kpi-val">{{ chipCounts.no_invoice || 0 }}</div>
                    </div>
                    <div class="ord-kpi ord-kpi-ok">
                        <div class="ord-kpi-label">Entregados</div>
                        <div class="ord-kpi-val">{{ chipCounts.delivered || 0 }}</div>
                    </div>
                    <div class="ord-kpi ord-kpi-rev">
                        <div class="ord-kpi-label">Vendido del mes</div>
                        <div class="ord-kpi-val">
                            S/ {{ formatMoney(stats.revenueMonth) }}
                        </div>
                    </div>
                </div>
                <div v-if="countsError" class="ord-counts-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    No se pudieron cargar los contadores: {{ countsError }}
                    <button class="ord-counts-retry" @click="loadChipCounts">Reintentar</button>
                </div>
                <div class="ord-chips">
                    <button
                        v-for="chip in orderChips"
                        :key="chip.key"
                        class="ord-chip"
                        :class="{ active: mpFilter === chip.key }"
                        @click="applyMpFilter(chip.key)"
                    >
                        {{ chip.label }}
                        <span
                            v-if="chipCounts[chip.key] !== undefined"
                            class="ord-chip-n"
                            >{{ chipCounts[chip.key] }}</span
                        >
                    </button>
                </div>
                <!-- Barra de filtros. Antes era una caja titulada "Gestión de
                     pedidos para facturar" (de Saga) con los controles
                     logísticos metidos dentro: se leían como si filtraran la
                     facturación. Ahora cada control lleva su etiqueta y van en
                     una rejilla que se apila sola en móvil. -->
                <!-- Barra de filtros.
                     Antes eran seis controles SIEMPRE visibles —periodo, fecha
                     a considerar, desde/hasta, modalidad, antiguedad y origen—
                     mas los chips y los KPI, todo antes de la primera fila.
                     Ahora quedan arriba la busqueda y los dos filtros que se
                     usan a diario; el resto entra en «Mas filtros», que se abre
                     solo si hace falta y avisa cuantos hay puestos. -->
                <div class="ord-bar">
                    <!-- Una sola busqueda inteligente. NO es nueva: el backend
                         ya buscaba por codigo, cliente, DNI, telefono, direccion,
                         comprobante, envio y tracking en un solo campo. Estaba
                         detras de «Mostrar filtros» y de un desplegable donde
                         habia que elegir «Buscar en todo» primero. -->
                    <div class="ord-search">
                        <i class="el-icon-search"></i>
                        <input
                            v-model="q"
                            type="search"
                            placeholder="Buscar por código, cliente, DNI, RUC, teléfono, comprobante o tracking…"
                            @keyup.enter="applySearch"
                            @search="applySearch"
                        />
                        <button v-if="q" class="ord-search-x" title="Limpiar" @click="q = ''; applySearch()">
                            <i class="el-icon-close"></i>
                        </button>
                        <button class="ord-search-go" @click="applySearch">Buscar</button>
                    </div>

                    <el-select
                        v-model="dateRange"
                        class="ord-bar-sel"
                        size="small"
                        placeholder="Periodo"
                        @change="applyDateFilters"
                    >
                        <el-option
                            v-for="opt in rangeOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        ></el-option>
                    </el-select>

                    <!-- Estado economico. Es el filtro que no existia: hasta
                         ahora lo unico que se podia filtrar del dinero era la
                         etiqueta «Pago verificado», que se pone a mano y no lo
                         mira. -->
                    <el-select
                        v-model="estadoPago"
                        class="ord-bar-sel"
                        size="small"
                        @change="pushFilters"
                    >
                        <el-option label="Todo el cobro" value=""></el-option>
                        <el-option label="Pago pendiente" value="pendiente"></el-option>
                        <el-option label="Pago parcial" value="parcial"></el-option>
                        <el-option label="Pagado" value="pagado"></el-option>
                        <el-option label="Cobrado por el canal" value="canal"></el-option>
                    </el-select>

                    <el-select
                        v-model="orderSource"
                        class="ord-bar-sel"
                        size="small"
                        @change="applyOrderSource"
                    >
                        <el-option label="Todos los pedidos" value="all"></el-option>
                        <el-option label="Solo Saga Falabella" value="saga"></el-option>
                        <el-option label="Otros pedidos" value="other"></el-option>
                    </el-select>

                    <!-- Las fechas concretas van AQUI, pegadas al periodo que
                         las activa. Estaban dentro de «Mas filtros», que se
                         abre aparte: elegir «Personalizado» en la barra no
                         mostraba ningun campo y el filtro por fechas quedaba
                         sin manera de usarse. Regresion del paso 3 del
                         rediseno; se ven solo con «Personalizado» porque si no
                         compiten con el rango rapido y no se sabe cual manda. -->
                    <el-date-picker
                        v-if="dateRange === 'custom'"
                        v-model="invoiceDateRange"
                        class="ord-bar-fechas"
                        type="daterange"
                        size="small"
                        range-separator="a"
                        start-placeholder="Desde"
                        end-placeholder="Hasta"
                        value-format="yyyy-MM-dd"
                        :clearable="true"
                        @change="applyDateFilters"
                    ></el-date-picker>

                    <!-- Orden. Hasta ahora la consulta era `latest()` fijo: no
                         habia forma de ordenar por importe ni por cliente. -->
                    <div class="ord-sort">
                        <el-select
                            v-model="orden"
                            class="ord-sort-sel"
                            size="small"
                            @change="pushFilters"
                        >
                            <el-option
                                v-for="opt in ordenOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                        <button
                            class="ord-sort-dir"
                            :title="ordenDir === 'desc' ? 'De mayor a menor' : 'De menor a mayor'"
                            @click="ordenDir = ordenDir === 'desc' ? 'asc' : 'desc'; pushFilters()"
                        >
                            <i :class="ordenDir === 'desc' ? 'el-icon-bottom' : 'el-icon-top'"></i>
                        </button>
                    </div>

                    <button
                        class="ord-bar-more"
                        :class="{ 'is-on': showMoreFilters || extraFilterCount }"
                        @click="showMoreFilters = !showMoreFilters"
                    >
                        <i class="fas fa-sliders-h"></i> Más filtros
                        <span v-if="extraFilterCount" class="ord-bar-badge">{{
                            extraFilterCount
                        }}</span>
                    </button>

                    <!-- Configuracion. Las dos pantallas YA existen en el
                         modulo de Envios y `shipping_settings` es una unica
                         fila por tenant que Pedidos ya lee en cada listado. Aqui
                         se enlazan, no se duplican: copiarlas daria dos sitios
                         donde cambiar la misma tarifa. -->
                    <el-dropdown v-if="shipping" trigger="click" @command="irA">
                        <button class="ord-bar-more">
                            <i class="fas fa-cog"></i> Configuración
                        </button>
                        <el-dropdown-menu slot="dropdown">
                            <el-dropdown-item command="tienda">
                                <i class="el-icon-office-building"></i>
                                Configuración de tienda
                            </el-dropdown-item>
                            <!-- OJO: esto NO es una configuracion de
                                 motorizados —no existe tal catalogo— sino el
                                 tablero de reparto a domicilio. Se le llama por
                                 lo que es. -->
                            <el-dropdown-item command="motorizado">
                                <i class="el-icon-bicycle"></i>
                                Tablero de reparto a domicilio
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </el-dropdown>

                    <button class="ord-new-btn" @click="manualOrderId = null; showManualDialog = true">
                        <i class="fas fa-plus"></i> Nuevo pedido
                    </button>
                </div>

                <!-- Los que no se tocan a diario. Se abren, se usan y se
                     cierran; mientras esten puestos, el boton lo dice. -->
                <div v-if="showMoreFilters" class="ord-filters">
                    <div class="ord-filter">
                        <label>Fecha a considerar</label>
                        <el-select v-model="dateType" size="small" @change="applyDateFilters">
                            <el-option
                                v-for="opt in dateTypeOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                    </div>

                    <div class="ord-filter">
                        <label>Modalidad de entrega</label>
                        <el-select v-model="deliveryTypeFilter" size="small" @change="applyLogisticFilters">
                            <el-option
                                v-for="opt in deliveryTypeOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                    </div>

                    <div class="ord-filter">
                        <label>Antigüedad</label>
                        <el-select v-model="agingFilter" size="small" @change="applyLogisticFilters">
                            <el-option
                                v-for="opt in agingOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                    </div>

                    <div class="ord-filter ord-filter-reset">
                        <button
                            v-if="hasActiveFilters"
                            class="ord-filter-clear"
                            @click="clearFilters"
                        >
                            Limpiar filtros
                        </button>
                    </div>
                </div>

                <div v-if="selectedIds.length" class="ord-bulkbar">
                    <span class="ord-bulk-count"
                        >{{ selectedIds.length }} seleccionado(s)</span
                    >
                    <button class="ord-bulk-btn" @click="bulkMarkInvoiced">
                        <i class="fas fa-check"></i> Marcar boleta (externa)
                    </button>
                    <button class="ord-bulk-btn" @click="bulkDownloadLabels">
                        <i class="fas fa-printer"></i> Descargar rótulos
                    </button>
                    <!-- Lote de impresión desde los pedidos seleccionados: es
                         la operación que antes obligaba a saltar al módulo de
                         Registro de Envíos. -->
                    <button class="ord-bulk-btn" @click="bulkCreatePrintBatch">
                        <i class="fas fa-layer-group"></i> Crear lote de impresión
                    </button>
                    <button class="ord-bulk-btn ghost" @click="selectedIds = []">
                        Limpiar
                    </button>
                </div>
                <data-table
                    ref="ordersTable"
                    :resource="resource"
                    @records-changed="onRecordsChanged"
                >
                    <tr slot="heading" width="100%">
                        <th class="text-center" style="width: 36px">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="toggleAll($event)"
                            />
                        </th>
                        <!-- Seis columnas, una por pregunta que se hace el
                             operador. Antes eran diez y la fila no entraba en
                             una laptop: «Codigo», «Cliente» y «Fecha»
                             respondian todas a «que pedido es y de quien», y
                             «Total» y «Medio Pago» hablaban las dos del cobro.
                             Nada se pierde: lo que sale de la fila esta en el
                             detalle del producto, en el tooltip o en el menu. -->
                        <th class="ord-c-order">Pedido</th>
                        <th class="ord-c-items">Productos</th>
                        <th class="text-end ord-c-pay">Cobro</th>
                        <th class="ord-c-state">Estado</th>
                        <th class="text-center ord-c-docs">Docs</th>
                        <th class="text-end ord-c-act">Acciones</th>
                    </tr>
                    <tr></tr>
                    <tr slot-scope="{ index, row }">
                        <td class="text-center">
                            <input
                                type="checkbox"
                                :value="row.id"
                                v-model="selectedIds"
                            />
                        </td>
                        <!-- Pedido: quien, que numero y cuando, en tres
                             renglones. La fecha estaba en una columna propia
                             para un dato de una linea, y el telefono y la
                             direccion del cliente se pintaban SIEMPRE aunque
                             solo importan al despachar: ahora viajan en el
                             tooltip y en el detalle, que es donde se consultan. -->
                        <td data-label="Pedido">
                            <div class="ord-o-top">
                                <button
                                    class="ord-o-id"
                                    title="Ver el detalle del pedido"
                                    @click="verPedido(row)"
                                >
                                    #{{ row.order_id }}
                                </button>
                                <span
                                    class="ord-o-canal"
                                    :style="{ background: canalColor(row) }"
                                    :title="canalTitulo(row)"
                                ></span>
                                <!-- El numero del pedido en el canal, que es
                                     el que el operador reconoce al hablar con
                                     Saga. `mp_external_order_id` y no
                                     `external_order_ref`: el segundo existe en
                                     la tabla pero NO viaja en el payload de la
                                     fila, y pintarlo daria siempre vacio. -->
                                <span
                                    v-if="row.mp_external_order_id"
                                    class="ord-o-ext"
                                    :title="'Nº en el canal: ' + row.mp_external_order_id"
                                    >{{ row.mp_external_order_id }}</span
                                >
                            </div>
                            <div class="ord-o-cli" :title="clienteTitulo(row)">
                                {{ row.customer }}
                            </div>
                            <!-- Documento y telefono, debajo del nombre. En el
                                 rediseno se habian ido al tooltip por ancho,
                                 pero son los dos datos con los que el operador
                                 identifica al cliente que llama y decide a
                                 nombre de quien sale la boleta. -->
                            <div class="ord-o-meta">
                                <span v-if="row.customer_doc">{{ row.customer_doc }}</span>
                                <!-- Sin documento la boleta saldria como
                                     «Cliente Final 00000000»: hay que verlo. -->
                                <span
                                    v-else-if="row.mp_order_id"
                                    class="ord-o-nodoc"
                                    title="La boleta saldria como Cliente Final 00000000"
                                    >sin documento</span
                                >
                                <span
                                    v-if="row.customer_telefono"
                                    class="ord-o-tel"
                                    :title="'Teléfono del cliente'"
                                    >{{ row.customer_telefono }}</span
                                >
                            </div>
                            <div class="ord-o-fecha" :title="row.created_at">
                                {{ fechaCorta(row.created_at) }}
                            </div>
                        </td>
                        <!-- Productos: el contador y el primero. El popover
                             de detalle se conserva tal cual —ya trae la tabla
                             completa, el telefono y la direccion—; lo que
                             cambia es el disparador, que era una lupa sin
                             contexto y ahora dice cuantos hay. -->
                        <td data-label="Productos">
                            <template>
                                <el-popover
                                    placement="right"
                                    width="540"
                                    trigger="click"
                                    popper-class="ord-items-pop"
                                >
                                    <el-table
                                        style="width: 100%"
                                        :data="row.items"
                                    >
                                        <el-table-column
                                            width="150"
                                            property="description"
                                            label="Nombre"
                                        ></el-table-column>
                                        <el-table-column
                                            width="90"
                                            property="cantidad"
                                            label="Cant."
                                        ></el-table-column>
                                        <el-table-column
                                            width="90"
                                            label="Precio"
                                        >
                                            <template slot-scope="scope">
                                                <span
                                                    >{{
                                                        scope.row
                                                            .currency_type_id ===
                                                        "USD"
                                                            ? "$"
                                                            : "S/"
                                                    }}
                                                    {{
                                                        Number(
                                                            scope.row
                                                                .sale_unit_price
                                                        ).toFixed(2)
                                                    }}</span
                                                >
                                            </template>
                                        </el-table-column>
                                        <el-table-column
                                            width="90"
                                            property="exchange_rate_sale"
                                            label="T/C"
                                        ></el-table-column>
                                        <el-table-column
                                            width="90"
                                            label="Subtotal"
                                        >
                                            <template slot-scope="scope">
                                                <span
                                                    >S/
                                                    {{
                                                        subtotal(scope.row)
                                                    }}</span
                                                >
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                    <table
                                        class="el-table--small el-table--fit el-table"
                                    >
                                        <thead class="has-gutter">
                                            <th colspan="2" class="text-center">
                                                Contacto
                                            </th>
                                        </thead>
                                        <tbody>
                                            <tr class="el-table tr">
                                                <td class="el-table--small td">
                                                    TELÉFONO:
                                                    {{ row.customer_telefono }}
                                                </td>
                                            </tr>
                                            <tr class="el-table tr">
                                                <td class="el-table--small td">
                                                    DIRECCIÓN:
                                                    {{ row.customer_direccion }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div slot="reference" class="ord-i-ref">
                                        <span class="ord-i-n"
                                            >{{ row.item_count }}
                                            {{ row.item_count === 1 ? "producto" : "productos" }}</span
                                        >
                                        <span
                                            v-if="primerProducto(row)"
                                            class="ord-i-first"
                                            >{{ primerProducto(row) }}</span
                                        >
                                    </div>
                                </el-popover>
                            </template>
                        </td>
                        <!-- Cobro: importe, saldo y medio, juntos.
                             Estaban repartidos en «Total» y «Medio Pago», dos
                             columnas hablando del mismo hecho con vocabularios
                             distintos; el operador tenia que reconciliarlas en
                             cada fila. La regla de donde vive el dinero NO
                             cambia: en un encargo logistico sigue leyendose del
                             envio, derivado y no copiado. -->
                        <!-- Cobro: importe, saldo, estado economico y medio.
                             El ESTADO lo decide el servidor y no esta celda: es
                             la misma regla que filtra el listado en SQL, y con
                             dos copias el operador filtraria por «parcial» y le
                             saldrian pedidos que la fila pinta «pagado».
                             La regla de donde vive el dinero tampoco cambia: en
                             un encargo se lee del envio, derivado y no copiado. -->
                        <td class="text-end" data-label="Cobro">
                            <div class="ord-p-total">{{ importeCobro(row) }}</div>
                            <div
                                v-if="row.payment_state"
                                class="ord-p-chip"
                                :class="'is-' + row.payment_state"
                                :title="tituloCobro(row)"
                            >
                                {{ row.payment_state_label }}
                            </div>
                            <div v-if="saldoCobro(row)" class="ord-p-saldo">
                                {{ saldoCobro(row) }}
                            </div>
                            <div
                                v-if="isMarketplace(row)"
                                class="ord-p-medio is-mp"
                                title="Cobrado por el canal, fuera de EBAEMY"
                            >
                                {{ medioPago(row) }}
                            </div>
                            <div v-else class="ord-p-medio">{{ medioPago(row) }}</div>
                        </td>
                        <!-- Estado: dos planos distintos del mismo pedido,
                             uno debajo del otro. El comercial era un stepper de
                             cinco pasos —el elemento mas ancho de la fila— y
                             ahora es un chip; el logistico ocupaba una columna
                             entera con siete datos apilados y ahora es otro
                             chip con su tooltip. Siguen SEPARADOS a proposito:
                             son dimensiones distintas y mezclarlas fue lo que
                             hizo ilegible la tabla anterior. -->
                        <td data-label="Estado">
                            <div class="ord-st">
                                <span
                                    v-if="row.status_order_id == 5"
                                    class="ord-st-chip is-cancel"
                                    >Cancelado</span
                                >
                                <span
                                    v-else
                                    class="ord-st-chip"
                                    :class="'is-' + estadoTono(row.status_order_id)"
                                    >{{ etiquetaOperativa(row.status_order_id) }}</span
                                >

                                <!-- Envio. El punto es el semaforo de
                                     antiguedad, con el mismo tooltip de antes. -->
                                <div v-if="row.shipment" class="ord-st-ship">
                                    <span
                                        class="ord-st-chip is-ship"
                                        :style="{
                                            color: row.shipment.delivery_meta.color,
                                            background: row.shipment.delivery_meta.bg,
                                            borderColor: row.shipment.delivery_meta.line
                                        }"
                                        :title="envioTitulo(row)"
                                        >{{ row.shipment.delivery_short }} ·
                                        {{ row.shipment.status_label }}</span
                                    >
                                    <span
                                        v-if="row.shipment.aging_meta"
                                        class="ord-st-dot"
                                        :style="{ background: row.shipment.aging_meta.color }"
                                        :title="
                                            row.shipment.aging_meta.label +
                                            ' · ' +
                                            row.shipment.aging_days +
                                            ' día(s) hábil(es)'
                                        "
                                    ></span>
                                    <!-- Sin este aviso, el operador descubre que
                                         faltan datos recien al intentar rotular. -->
                                    <i
                                        v-if="row.shipment.missing_data && row.shipment.missing_data.length"
                                        class="fas fa-exclamation-triangle ord-st-warn"
                                        :title="'Faltan datos para rotular: ' + row.shipment.missing_data.join(', ')"
                                    ></i>
                                </div>
                                <!-- A donde va: agencia y ciudad. En agencia,
                                     «Shalom» a secas no dice el destino, y la
                                     ciudad sola no dice por donde viaja. -->
                                <div
                                    v-if="destinoEnvio(row)"
                                    class="ord-st-dest"
                                    :title="destinoEnvio(row)"
                                >
                                    {{ destinoEnvio(row) }}
                                </div>

                                <!-- Un envio anulado NO es lo mismo que no tener
                                     envio, y decir «sin envio» seria falso. -->
                                <span
                                    v-else-if="row.shipment_cancelled"
                                    class="ord-st-chip is-void"
                                    :title="'El envío ' + row.shipment_cancelled.code + ' fue anulado. Puedes restaurarlo desde el menú.'"
                                    >Envío anulado</span
                                >
                                <span v-else class="ord-st-chip is-none">Sin envío</span>

                                <!-- Cambiar el estado sigue exactamente igual:
                                     candado, desplegable y `updateStatus`, que
                                     es quien decide si hay que emitir nota de
                                     venta o pedir el almacen. -->
                                <div v-if="!isMarketplace(row)" class="ord-status-editbar">
                                    <template v-if="editingStatusId === row.id">
                                        <el-select
                                            v-model="row.status_order_id"
                                            size="mini"
                                            class="ord-status-edit"
                                            placeholder="Cambiar estado"
                                            @change="updateStatus(row)"
                                        >
                                            <el-option
                                                v-for="item in options"
                                                :key="item.id"
                                                :label="etiquetaOperativa(item.id)"
                                                :value="item.id"
                                            ></el-option>
                                        </el-select>
                                        <button
                                            class="ord-lock-btn cancel"
                                            title="Cancelar"
                                            @click="editingStatusId = null"
                                        >
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </template>
                                    <button
                                        v-else
                                        class="ord-lock-btn"
                                        title="Desbloquear para cambiar el estado"
                                        @click="editingStatusId = row.id"
                                    >
                                        <i class="fas fa-lock"></i> Cambiar
                                    </button>
                                </div>
                                <small v-else class="ord-saga-status"
                                    >Sincronizado desde Saga</small
                                >
                            </div>
                        </td>
                        <!-- Documentos del pedido.
                             Antes esta celda solo sabia hablar de Saga: para un
                             pedido de ecommerce o manual no decia casi nada.
                             Ahora son cuatro siglas (NV, B, F, GR) que se leen de
                             un vistazo, y el clic abre el panel completo.
                             Los chips y sus estados los resuelve `OrderDocuments`
                             en PHP: aqui no se decide nada, solo se pinta. Un tipo
                             que NO corresponde a este pedido no viene en el
                             payload y por eso no se dibuja — pintarlo en gris
                             invitaria a intentar algo que el sistema rechaza. -->
                        <td class="text-center" data-label="Docs">
                            <div
                                class="ord-doc-chips"
                                role="button"
                                tabindex="0"
                                title="Ver los documentos del pedido"
                                @click="abrirDocumentos(row)"
                                @keyup.enter="abrirDocumentos(row)"
                            >
                                <span
                                    v-for="s in documentSlots(row)"
                                    :key="s.tipo"
                                    class="ord-doc-chip"
                                    :class="[
                                        'is-' + docTone(s),
                                        { 'is-sugerido': s.sugerido },
                                    ]"
                                    :title="docTitle(s)"
                                    >{{ s.chip }}</span
                                >
                                <span
                                    v-if="!documentSlots(row).length"
                                    class="text-muted"
                                    title="Un encargo de envío no genera documentos comerciales."
                                    >—</span
                                >
                                <i
                                    v-if="row.mp_invoice_state === 'alert'"
                                    class="fas fa-exclamation-triangle ord-doc-flag"
                                    title="Saga devolvió el pedido con la boleta emitida."
                                ></i>
                            </div>
                        </td>
                        <td class="text-end" data-label="Acciones">
                            <!-- Todas las acciones en un menu: sueltas no
                                 caben, y con el tiempo se fueron sumando
                                 (boleta, rotulo, subir a Saga, PDF...). -->
                            <button class="ord-ver-btn" @click="verPedido(row)">
                                Ver
                            </button>
                            <el-dropdown
                                trigger="click"
                                @command="runAction($event, row)"
                            >
                                <el-button size="mini" class="ord-actions-btn">
                                    <i class="fas fa-ellipsis-v"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown">
                                    <el-dropdown-item
                                        v-if="canGenerateInvoice(row)"
                                        command="invoice"
                                    >
                                        <i class="el-icon-document"></i>
                                        {{
                                            invoiceIsRisky(row)
                                                ? "Generar boleta ⚠ (sin entrega confirmada)"
                                                : "Generar boleta"
                                        }}
                                    </el-dropdown-item>

                                    <!-- Emitida aqui pero todavia no esta en
                                         Saga: es el paso que falta y antes no
                                         se veia por ningun lado. -->
                                    <!-- Editar: solo antes de despachar. El
                                         servidor lo vuelve a comprobar. -->
                                    <el-dropdown-item
                                        v-if="[1, 2, 3].indexOf(Number(row.status_order_id)) !== -1"
                                        command="edit"
                                    >
                                        <i class="el-icon-edit"></i>
                                        Editar pedido
                                    </el-dropdown-item>

                                    <!-- Acciones del ENVIO del pedido. Solo se
                                         ofrecen si el pedido tiene envio: el
                                         rotulo, la modalidad y la anulacion son
                                         del envio, no del pedido. -->
                                    <!-- La modalidad NO se cambia desde aqui:
                                         ya se elige en «Configurar envio», que
                                         ahora enruta el cambio por el camino
                                         que aplica el bloqueo por lote y la
                                         cascada. Un segundo sitio para lo mismo
                                         solo daria dos comportamientos. -->
                                    <el-dropdown-item
                                        v-if="row.shipment && !row.shipment.is_pickup && !row.shipment.has_guide"
                                        command="uploadGuide"
                                    >
                                        <i class="el-icon-upload"></i>
                                        Subir guía de la agencia
                                    </el-dropdown-item>
                                    <!-- El envio ya no tiene columna propia:
                                         estas tres eran botones sueltos en la
                                         celda «Entrega» y ahora viven aqui. La
                                         informacion (destino, agencia, tracking)
                                         esta en el tooltip del chip de estado. -->
                                    <el-dropdown-item
                                        v-if="row.shipment"
                                        command="shipment"
                                    >
                                        <i class="el-icon-truck"></i>
                                        Ver / editar envío
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-else-if="row.shipment_cancelled"
                                        command="restoreShipment"
                                    >
                                        <i class="el-icon-refresh-left"></i>
                                        Restaurar envío anulado
                                    </el-dropdown-item>
                                    <el-dropdown-item v-else command="shipment">
                                        <i class="el-icon-truck"></i>
                                        Configurar envío
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="row.shipment"
                                        command="cancelShipment"
                                    >
                                        <i class="el-icon-close"></i>
                                        Anular envío
                                    </el-dropdown-item>

                                    <el-dropdown-item command="payments">
                                        <i class="el-icon-wallet"></i>
                                        Pagos del pedido
                                    </el-dropdown-item>

                                    <!-- Verificar es OTRA cosa que registrar, y
                                         por eso es otra accion. Solo si el
                                         tenant lo exige. -->
                                    <el-dropdown-item
                                        v-if="verification"
                                        command="verifyPayments"
                                    >
                                        <i class="el-icon-circle-check"></i>
                                        Verificar cobros
                                    </el-dropdown-item>

                                    <!-- Documentos. Se ofrecen SOLO cuando
                                         `OrderDocuments` dice que se pueden
                                         emitir: la fila no repite las reglas de
                                         SUNAT, las consulta. -->
                                    <el-dropdown-item
                                        v-if="puedeEmitir(row, 'nota_venta')"
                                        command="emitSaleNote"
                                        divided
                                    >
                                        <i class="el-icon-tickets"></i>
                                        Emitir nota de venta
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="puedeEmitirComprobante(row)"
                                        command="emitDocument"
                                    >
                                        <i class="el-icon-document-checked"></i>
                                        Emitir {{ nombreComprobante(row) }}
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="puedeEmitir(row, 'guia')"
                                        command="dispatchGuide"
                                    >
                                        <i class="el-icon-truck"></i>
                                        Generar guía de remisión
                                    </el-dropdown-item>

                                    <!-- Rotulado. Se ofrece solo si el pedido
                                         tiene envio: el rotulo es del envio, no
                                         del pedido. -->
                                    <el-dropdown-item
                                        v-if="row.shipment"
                                        command="label"
                                        :disabled="!!row.shipment.print_block"
                                    >
                                        <i class="el-icon-printer"></i>
                                        {{ labelActionText(row) }}
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="canUploadInvoice(row)"
                                        command="upload"
                                    >
                                        <i class="el-icon-upload2"></i>
                                        Subir boleta a Saga
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="isSagaOrder(row) && row.mp_invoice_state === 'pending'"
                                        command="markExternal"
                                    >
                                        <i class="el-icon-check"></i>
                                        Ya la emití en Saga
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="row.document_type_id == '80' && row.sale_note_id"
                                        command="saleNote"
                                        divided
                                    >
                                        <i class="el-icon-tickets"></i>
                                        Nota de venta / convertir
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="row.document_external_id"
                                        command="document"
                                        divided
                                    >
                                        <i class="el-icon-tickets"></i>
                                        Ver comprobante
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="canDownloadLabel(row)"
                                        command="sagaLabel"
                                    >
                                        <i class="el-icon-printer"></i>
                                        Rótulo de Saga
                                    </el-dropdown-item>

                                    <!-- Enlace para que el CLIENTE complete sus
                                         datos de entrega. Reemplaza al
                                         formulario público suelto: llega al
                                         pedido, no crea uno nuevo. -->
                                    <el-dropdown-item command="shippingLink" divided>
                                        <i class="el-icon-link"></i>
                                        Copiar enlace de datos de envío
                                    </el-dropdown-item>

                                    <!-- La guia que dio la agencia. Ya estaba
                                         cargada, pero solo se podia abrir desde
                                         el panel de Envios. -->
                                    <el-dropdown-item
                                        v-if="row.shipment && row.shipment.guide_url"
                                        command="viewGuide"
                                    >
                                        <i class="el-icon-document"></i>
                                        Ver guía de la agencia
                                    </el-dropdown-item>

                                    <el-dropdown-item command="timeline">
                                        <i class="el-icon-time"></i>
                                        Ver historial
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>

        <!-- Envío del pedido: el detalle logístico vive DENTRO del pedido. -->
        <shipment-form
            :visible.sync="showShipmentDialog"
            :order-id="shipmentOrderId"
            @saved="onShipmentSaved"
        ></shipment-form>

        <!-- Pagos: el MISMO panel que usa Nota de Venta, apuntando al pedido
             o al envío segun donde viva el dinero. El componente ya es
             generico (se parametriza con `resource`), asi que no hay una
             segunda pantalla de pagos: hay una, con dos destinos. -->
        <record-payments
            v-if="showPaymentsDialog"
            :showDialog.sync="showPaymentsDialog"
            :recordId="paymentsRecordId"
            :resource="paymentsResource"
            :foreignKey="paymentsForeignKey"
            :fileType="paymentsFileType"
            :title="paymentsTitle"
            @updated="onPaymentsUpdated"
        ></record-payments>

        <!-- Alta manual: el pedido que llega por WhatsApp, telefono o mostrador
             y no lo crea ninguna integracion. -->
        <manual-order
            :key="manualOrderId || 'nuevo'"
            :showDialog.sync="showManualDialog"
            :orderId="manualOrderId"
            @created="onManualCreated"
        ></manual-order>

        <!-- Guía de la agencia: el comprobante de que el paquete salió. -->
        <shipment-guide
            :showDialog.sync="showGuideDialog"
            :orderId="guideOrderId"
            :code="guideCode"
            @saved="refrescarTrasEnvio"
        ></shipment-guide>

        <!-- Con que se factura el pedido. Fase C: antes lo decidia solo el
             comprador en el checkout. -->
        <billing-type
            :showDialog.sync="showBillingDialog"
            :orderId="billingOrderId"
            :billing="billingData"
            @saved="refrescarTrasEnvio"
        ></billing-type>

        <!-- Emitir boleta o factura. Es EL MISMO componente de Notas de Venta,
             reutilizado tal cual: lee todo de `/sale-notes/...` y postea a
             `/documents`. Por eso el pedido necesita su nota de venta primero. -->
        <sale-note-generate
            :show.sync="showCpeDialog"
            :recordId="cpeSaleNoteId"
            :showGenerate="true"
            :showClose="false"
            @hasGeneratedDocument="refrescarTrasEnvio"
        ></sale-note-generate>

        <!-- Panel de documentos del pedido (Fase F). Los chips de la columna son
             el vistazo; esto es el detalle, con las acciones y los motivos. -->
        <documents-panel
            :showDialog.sync="showDocsDialog"
            :row="docsRow"
            @emit-sale-note="emitirNotaVenta"
            @emit-document="emitirComprobante"
            @dispatch-guide="generarGuiaRemision"
            @print-label="printLabel"
            @fix-billing="corregirComprobante"
        ></documents-panel>

        <!-- Detalle del pedido (paso 4). Aloja lo que la fila dejo de mostrar
             al pasar de diez columnas a seis, y delega cada accion en la
             pantalla que ya la hacia. -->
        <order-drawer
            :visible.sync="showDrawer"
            :row="drawerRow"
            @payments="clickPayments($event.id)"
            @emit-sale-note="emitirNotaVenta"
            @emit-document="emitirComprobante"
            @dispatch-guide="generarGuiaRemision"
            @print-label="printLabel"
            @fix-billing="corregirComprobante"
            @shipment="openShipment"
            @upload-guide="subirGuia"
            @view-guide="openGuide"
            @restore-shipment="restaurarEnvio"
            @timeline="openTimeline"
            @edit="editarPedido($event.id)"
            @shipping-link="copyShippingLink"
        ></order-drawer>

        <!-- Verificar los cobros. Solo aparece si el tenant lo exige: con la
             regla apagada no hay nada que verificar y el boton seria ruido. -->
        <payment-verification
            :showDialog.sync="showVerifyDialog"
            :tipo="verifyTipo"
            :recordId="verifyRecordId"
            @changed="refrescarTrasEnvio"
        ></payment-verification>

        <!-- Historial: estados del pedido + bitácora del envío + impresiones. -->
        <order-timeline
            :visible.sync="showTimelineDialog"
            :order-id="timelineOrderId"
        ></order-timeline>

        <el-dialog
            title="Stock en almacén"
            width="40%"
            :visible="showDialog"
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            append-to-body
            :show-close="false"
        >
            <div class="form-body">
                <div class="row">
                    <div class="col-lg-12 col-md-12 table-responsive">
                        <table width="100%" class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Almacén</th>
                                </tr>
                            </thead>
                            <tbody
                                v-for="(rowProduct,
                                indexProduct) in totalProduct"
                                :key="indexProduct"
                                width="100%"
                            >
                                <tr>
                                    <td>
                                        {{ record.items[indexProduct].name }}
                                    </td>
                                    <td>
                                        <el-select
                                            v-model="form[rowProduct]"
                                            placeholder="Almacenes"
                                        >
                                            <el-option
                                                v-if="
                                                    rowProduct === item.item_id
                                                "
                                                v-for="item in warehouses"
                                                :key="item.id"
                                                :label="
                                                    item.warehouse +
                                                        ' - ' +
                                                        'Stock -> ' +
                                                        Math.trunc(item.stock)
                                                "
                                                :value="item.id"
                                                :disabled="
                                                    optionDisable(
                                                        item.item_id,
                                                        item.stock
                                                    )
                                                "
                                            ></el-option>
                                        </el-select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end pt-2">
                <el-button class="second-buton" @click="close"
                    >Cerrar</el-button
                >
                <el-button type="primary" @click="save">Guardar</el-button>
            </div>
        </el-dialog>

        <options-form
            :showDialog.sync="showDialogOptions"
            :recordId="documentNewId"
            :statusDocument="statusDocument"
            :resource="resource_options"
        ></options-form>

        <document-form
            :order_id="order_id"
            :user="user"
            :document_types="document_types"
            ref="document_form"
        >
        </document-form>

        <sale-note-form
            :showDialog.sync="showDialogSaleNote"
            :orderId="order_id"
            :dataSaleNote="dataSaleNote"
        >
        </sale-note-form>
    </div>
</template>
<style>
/* Stepper de estado del pedido */
.ord-status-edit {
    width: 100%;
    margin-top: 2px;
}
/* Saldo del encargo logistico. Su dinero vive en el envio, asi que la celda
   de Total muestra el importe a cobrar y, debajo, lo que falta. En rojo solo
   cuando queda deuda: es la unica parte que pide accion. */
.ord-actions-btn {
    padding: 5px 9px;
}
/* ── Columna "Documentos": los chips ─────────────────────────────────
   Cuatro siglas en una celda estrecha. El color dice el estado y el clic
   abre el panel (documents_panel.vue), donde ya caben el numero, la fecha,
   el motivo del bloqueo y la accion. Los tonos son los del resto de la tabla. */
.ord-doc-chips {
    display: inline-flex;
    gap: 4px;
    align-items: center;
    cursor: pointer;
    flex-wrap: wrap;
    justify-content: center;
}
.ord-doc-chip {
    display: inline-block;
    min-width: 26px;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    line-height: 1.5;
    border: 1px solid transparent;
}
/* Emitido y vigente. */
.ord-doc-chip.is-ok {
    background: #dcfce7;
    color: #166534;
}
/* En curso ante SUNAT: registrado o enviado, todavia sin respuesta. */
.ord-doc-chip.is-pend {
    background: #dbeafe;
    color: #1e40af;
}
/* Observado o por anular: hay algo que atender. */
.ord-doc-chip.is-warn {
    background: #fef3c7;
    color: #92400e;
}
/* Rechazado o anulado. */
.ord-doc-chip.is-err {
    background: #fee2e2;
    color: #b91c1c;
}
/* Se puede emitir ya: contorno, no relleno — no es un estado alcanzado. */
.ord-doc-chip.is-ready {
    background: #fff;
    color: #475569;
    border-color: #94a3b8;
    border-style: dashed;
}
/* Falta algo antes de poder emitirlo. Apagado a proposito. */
.ord-doc-chip.is-off {
    background: #f1f5f9;
    color: #94a3b8;
}
.ord-doc-flag {
    color: #b91c1c;
    font-size: 11px;
    margin-left: 2px;
}
/* El que corresponde emitir: contorno solido para distinguirlo del resto sin
   gritar. No es un estado alcanzado, es una recomendacion. */
.ord-doc-chip.is-sugerido {
    box-shadow: inset 0 0 0 1.5px #0f766e;
}
/* ══════════════════════════════════════════════════════════════════
   La fila: seis columnas
   ══════════════════════════════════════════════════════════════════
   Anchos en porcentaje y no fijos: la tabla debe repartirse el espacio
   que haya, no exigir un minimo. `Pedido` y `Estado` se llevan la mayor
   parte porque son las que llevan tres renglones. */
.orders th.ord-c-order { width: 24%; }
.orders th.ord-c-items { width: 15%; }
.orders th.ord-c-pay   { width: 13%; }
.orders th.ord-c-state { width: 22%; }
.orders th.ord-c-docs  { width: 10%; }
.orders th.ord-c-act   { width: 8%;  }

/* Densidad. La fila tenia 12px de padding vertical y celdas de cuatro
   renglones: en 1080px de alto entraban seis pedidos. */
.orders table tbody td {
    padding-top: 9px;
    padding-bottom: 9px;
    font-size: 12.5px;
    line-height: 1.4;
}

/* ── Pedido ─────────────────────────────────────────────────────── */
.ord-o-top {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
/* Es un boton, pero se lee como el codigo del pedido: el aspecto de boton
   en cada fila era justo lo que el rediseño venia a quitar. */
.ord-o-id {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: #0f172a;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: inherit;
    font-family: inherit;
    cursor: pointer;
}
.ord-o-id:hover {
    color: #4338ca;
    text-decoration: underline;
}
.ord-ver-btn {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 10px;
    cursor: pointer;
    margin-right: 4px;
}
.ord-ver-btn:hover {
    border-color: #4f46e5;
    color: #4f46e5;
}
/* Punto de canal: un dato de origen no merece una columna ni una
   etiqueta, pero si un color con su tooltip. */
.ord-o-canal {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
    flex: 0 0 auto;
}
.ord-o-ext {
    font-size: 10.5px;
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
}
.ord-o-cli {
    color: #334155;
    margin-top: 1px;
    /* El nombre puede ser largo; se corta con puntos suspensivos en vez
       de ensanchar la columna. El completo esta en el tooltip. */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Documento y telefono: una linea, dos datos, separados por un punto.
   Ocupan un renglon mas por fila y es deliberado — el operador los pedia. */
.ord-o-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1.35;
}
.ord-o-meta > span + span::before {
    content: "·";
    margin-right: 6px;
    color: #cbd5e1;
}
.ord-o-tel {
    font-variant-numeric: tabular-nums;
}
.ord-o-nodoc {
    color: #b45309;
}
.ord-o-fecha {
    color: #94a3b8;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
    margin-top: 1px;
}

/* ── Productos ──────────────────────────────────────────────────── */
.ord-i-ref {
    cursor: pointer;
    display: inline-block;
    max-width: 100%;
}
.ord-i-n {
    font-weight: 600;
    color: #334155;
    border-bottom: 1px dotted #cbd5e1;
}
.ord-i-first {
    display: block;
    color: #94a3b8;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Cobro ──────────────────────────────────────────────────────── */
.ord-p-total {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: #0f172a;
    white-space: nowrap;
}
/* Estado economico del cobro. Los cuatro tonos siguen al dinero, no al
   estado comercial: un pedido puede estar «En preparacion» y sin cobrar. */
.ord-p-chip {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 3px;
    margin-top: 2px;
    white-space: nowrap;
}
.ord-p-chip.is-pagado    { background: #dcfce7; color: #166534; }
.ord-p-chip.is-parcial   { background: #fef3c7; color: #92400e; }
.ord-p-chip.is-pendiente { background: #f1f5f9; color: #64748b; }
/* Un encargo al que nadie le cargo el importe. No es «pendiente»: no se sabe
   cuanto se debe, y decirlo seria inventarse una deuda. */
/* Marketplace: el dinero lo cobro el canal y no pasa por la tienda. Se
   distingue del «pagado» propio a proposito — no es lo mismo cobrar que
   confiar en que el canal cobro. */
.ord-p-chip.is-canal {
    background: #ede9fe;
    color: #5b21b6;
}
.ord-p-chip.is-canal_anulado {
    background: #fee2e2;
    color: #b91c1c;
}
.ord-p-chip.is-sin_monto {
    background: transparent;
    color: #94a3b8;
    border: 1px dashed #cbd5e1;
    font-weight: 600;
}
.ord-p-saldo {
    color: #b91c1c;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}
.ord-p-medio {
    color: #94a3b8;
    font-size: 11px;
    text-transform: capitalize;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Marketplace: el dinero no paso por caja. Distinguirlo importa al cuadrar. */
.ord-p-medio.is-mp {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    font-weight: 600;
    border-radius: 3px;
    padding: 0 6px;
    max-width: 100%;
}

/* ── Estado ─────────────────────────────────────────────────────── */
.ord-st {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
}
.ord-st-chip {
    display: inline-block;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid transparent;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Los cinco estados comerciales. El color avanza con el flujo: gris al
   entrar, verde al cobrar, ambar mientras se trabaja, azul en camino. */
.ord-st-chip.is-pend    { background: #f1f5f9; color: #475569; }
.ord-st-chip.is-ok      { background: #dcfce7; color: #166534; }
.ord-st-chip.is-work    { background: #fef3c7; color: #92400e; }
.ord-st-chip.is-ship    { background: #dbeafe; color: #1e40af; }
.ord-st-chip.is-done    { background: #e0e7ff; color: #3730a3; }
.ord-st-chip.is-neutral { background: #f1f5f9; color: #64748b; }
.ord-st-chip.is-cancel  { background: #fee2e2; color: #b91c1c; }
/* Envio anulado y sin envio son cosas distintas: decir «sin envio» sobre
   un pedido cuyo envio se anulo seria falso. */
.ord-st-chip.is-void {
    background: #fff7ed;
    color: #9a3412;
    border-color: #fed7aa;
}
.ord-st-chip.is-none {
    background: transparent;
    color: #94a3b8;
    border: 1px dashed #cbd5e1;
    font-weight: 600;
}
.ord-st-ship {
    display: flex;
    align-items: center;
    gap: 5px;
    max-width: 100%;
}
.ord-st-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex: 0 0 auto;
}
/* Destino del envio. Se recorta: el ancho lo manda la columna, no el
   nombre de la agencia. El completo va en el tooltip. */
.ord-st-dest {
    color: #94a3b8;
    font-size: 11px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ord-st-warn {
    color: #b45309;
    font-size: 11px;
    flex: 0 0 auto;
}

.ord-counts-error {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 13px;
    margin-bottom: 10px;
}
.ord-counts-retry {
    margin-left: auto;
    border: 1px solid #fecaca;
    background: #fff;
    color: #b91c1c;
    border-radius: 8px;
    padding: 3px 10px;
    font-size: 12px;
    cursor: pointer;
}
.ord-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 14px;
}
.ord-chip {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 999px;
    padding: 6px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s;
}
.ord-chip:hover {
    border-color: #c7d2fe;
    color: #4f46e5;
}
.ord-chip.active {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #fff;
}
.ord-chip-n {
    display: inline-block;
    min-width: 18px;
    text-align: center;
    background: rgba(0, 0, 0, 0.08);
    border-radius: 999px;
    padding: 0 6px;
    font-size: 11px;
    margin-left: 4px;
}
.ord-chip.active .ord-chip-n {
    background: rgba(255, 255, 255, 0.25);
}
/* ══════════════════════════════════════════════════════════════════
   Barra de filtros
   ══════════════════════════════════════════════════════════════════ */
.ord-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
/* La busqueda se lleva el espacio sobrante: es el control que mas se usa
   y el unico donde el ancho cambia lo que se puede escribir. */
.ord-search {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1 1 320px;
    min-width: 240px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    background: #fff;
    padding: 0 6px 0 10px;
    height: 34px;
}
.ord-search:focus-within {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}
.ord-search > i {
    color: #94a3b8;
    font-size: 14px;
    flex: 0 0 auto;
}
.ord-search input {
    flex: 1 1 auto;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    font-size: 13px;
    color: #1e293b;
    /* 16px es el minimo que evita que iOS haga zoom al enfocar. Por debajo
       de eso el navegador amplia la pagina y el operador se queda con la
       tabla descuadrada; por eso el tamaño solo baja en escritorio. */
    height: 100%;
}
.ord-search input::placeholder {
    color: #a8b2c1;
}
.ord-search-x {
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 2px 4px;
    line-height: 1;
}
.ord-search-x:hover {
    color: #475569;
}
.ord-search-go {
    border: 0;
    border-radius: 6px;
    background: #eef2ff;
    color: #4338ca;
    font-weight: 600;
    font-size: 12px;
    padding: 5px 12px;
    cursor: pointer;
    white-space: nowrap;
}
.ord-search-go:hover {
    background: #e0e7ff;
}
.ord-bar-sel {
    flex: 0 1 165px;
    min-width: 140px;
}
.ord-bar-fechas {
    flex: 0 1 250px;
    min-width: 210px;
}
.ord-sort {
    display: flex;
    align-items: stretch;
    gap: 0;
    flex: 0 1 190px;
    min-width: 160px;
}
.ord-sort-sel {
    flex: 1 1 auto;
    min-width: 0;
}
.ord-sort-dir {
    border: 1px solid #dbe2ea;
    border-left: 0;
    border-radius: 0 8px 8px 0;
    background: #fff;
    color: #475569;
    cursor: pointer;
    padding: 0 9px;
    flex: 0 0 auto;
}
.ord-sort-dir:hover {
    color: #4f46e5;
    border-color: #4f46e5;
}
.ord-bar-more {
    border: 1px solid #dbe2ea;
    background: #fff;
    color: #475569;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    padding: 7px 12px;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ord-bar-more:hover {
    border-color: #4f46e5;
    color: #4f46e5;
}
.ord-bar-more.is-on {
    border-color: #4f46e5;
    color: #4338ca;
    background: #eef2ff;
}
/* Un filtro escondido que sigue activo es la forma mas facil de que alguien
   crea que faltan pedidos. El numero lo dice sin abrir el panel. */
.ord-bar-badge {
    background: #4338ca;
    color: #fff;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    min-width: 16px;
    text-align: center;
    padding: 0 5px;
    line-height: 16px;
}

/* El DataTable trae su propio buscador: un boton «Mostrar filtros» y un
   desplegable donde hay que elegir la columna antes de escribir. Con la
   barra de arriba serian dos buscadores para lo mismo, y el de abajo es
   el peor de los dos. Se oculta SOLO en esta pantalla: el componente lo
   comparten muchos modulos y no se toca. */
.orders .filter-container .btn-filter-content,
.orders .filter-container .filter-content {
    display: none;
}

.ord-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px 12px;
    padding: 12px;
    margin-bottom: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fbfcfe;
}
.ord-filter {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}
/* Cada control lleva su etiqueta: sin ella, cuatro desplegables seguidos no
   dicen qué filtran y la barra se lee como piezas sueltas. */
.ord-filter label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin: 0;
}
.ord-filter .el-select,
.ord-filter .el-date-editor {
    width: 100%;
}
.ord-filter-reset {
    justify-content: flex-end;
}
.ord-filter-clear {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.ord-filter-clear:hover {
    border-color: #c7d2fe;
    color: #4f46e5;
}

@media (max-width: 640px) {
    .ord-filters {
        grid-template-columns: 1fr;
    }
}
/* KPIs */
.ord-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}
.ord-kpi {
    border: 1px solid #eef2f7;
    border-radius: 12px;
    padding: 12px 14px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.ord-kpi-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
}
.ord-kpi-val {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 2px;
}
.ord-kpi-warn .ord-kpi-val {
    color: #b45309;
}
.ord-kpi-ok .ord-kpi-val {
    color: #166534;
}
.ord-kpi-rev .ord-kpi-val {
    color: #4f46e5;
}
/* Barra de acciones masivas */
.ord-bulkbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    padding: 8px 12px;
    margin-bottom: 12px;
}
.ord-bulk-count {
    font-weight: 700;
    color: #3730a3;
    margin-right: 6px;
}
.ord-new-btn {
    border: 0;
    border-radius: 6px;
    background: #0f766e;
    color: #fff;
    font-weight: 600;
    font-size: 13px;
    padding: 8px 16px;
    cursor: pointer;
    white-space: nowrap;
}
.ord-new-btn:hover {
    background: #115e59;
}
.ord-bulk-btn {
    border: 1px solid #c7d2fe;
    background: #fff;
    color: #4f46e5;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.ord-bulk-btn:hover {
    background: #4f46e5;
    color: #fff;
}
.ord-bulk-btn.ghost {
    border-color: #e2e8f0;
    color: #64748b;
}
/* Vista móvil: tabla → tarjetas */
/* ══════════════════════════════════════════════════════════════════
   TABLETA — 768 a 1199 px
   ══════════════════════════════════════════════════════════════════
   Se ocultan las dos columnas que el operador consulta menos a menudo
   y que ya tienen su detalle a un clic. No se comprime nada: una tabla
   de seis columnas apretadas en 900 px se lee peor que una de cuatro.

   Productos sigue accesible desde el menu (el popover) y Docs desde el
   panel de documentos, asi que no se pierde el acceso, solo la columna. */
@media (min-width: 768px) and (max-width: 1199px) {
    .orders th.ord-c-items,
    .orders td[data-label="Productos"],
    .orders th.ord-c-docs,
    .orders td[data-label="Docs"] {
        display: none;
    }
    .orders th.ord-c-order { width: 34%; }
    .orders th.ord-c-pay   { width: 18%; }
    .orders th.ord-c-state { width: 34%; }
    .orders th.ord-c-act   { width: 14%; }
}

/* ══════════════════════════════════════════════════════════════════
   MOVIL — menos de 768 px
   ══════════════════════════════════════════════════════════════════
   Aqui la tabla deja de ser una tabla. Antes se apilaba con la etiqueta
   de cada celda delante («Cliente: …», «Cobro: …»), que es el patron
   correcto para una tabla de tres columnas pero con diez producia diez
   renglones por pedido y en la pantalla entraba uno y medio.

   Ahora cada fila es una tarjeta maquetada con grid. Se reutiliza el
   MISMO markup —no hay una segunda plantilla que mantener en paralelo—
   y solo cambia la colocacion:

        [☐]  #001245                      S/ 170.00
             Juan Perez · 07 set 14:35    saldo S/ 20
             3 productos
        [Pago verificado] [Agencia · Pendiente]
        NV B GR                                  [⋮]

   Las etiquetas `data-label` se ocultan: en esta disposicion el
   contenido ya se explica solo y repetir «Cobro:» delante del importe
   solo gasta linea. */
@media (max-width: 767px) {
    .orders .ord-kpis {
        grid-template-columns: repeat(2, 1fr);
    }
    /* 16px es el umbral por debajo del cual Safari amplia la pagina al
       enfocar un campo. Con 13px el operador acaba con la tabla
       descuadrada y teniendo que alejar a mano en cada busqueda. */
    .ord-search input {
        font-size: 16px;
    }
    .ord-search {
        flex: 1 1 100%;
        height: 38px;
    }
    /* Los dos desplegables se reparten la linea siguiente. */
    .ord-bar-sel {
        flex: 1 1 calc(50% - 4px);
        min-width: 0;
    }
    .ord-sort,
    .ord-bar-fechas {
        flex: 1 1 100%;
        min-width: 0;
    }
    .ord-bar-more,
    .ord-bar .ord-new-btn {
        flex: 1 1 calc(50% - 4px);
        justify-content: center;
        text-align: center;
    }
    .orders table thead {
        display: none;
    }
    .orders table tbody tr {
        display: grid;
        grid-template-columns: auto 1fr auto;
        grid-template-areas:
            "chk pedido cobro"
            "chk prods  cobro"
            "est est    est"
            "doc doc    act";
        column-gap: 8px;
        row-gap: 2px;
        align-items: start;
        border: 1px solid #e7ebf1;
        border-radius: 12px;
        margin-bottom: 9px;
        padding: 10px 12px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        background: #fff;
    }
    .orders table tbody td {
        border: none !important;
        padding: 0;
        text-align: left;
    }
    /* El contenido se explica solo en esta disposicion. */
    .orders table tbody td::before {
        content: none;
    }

    .orders table tbody td:first-child { grid-area: chk; padding-top: 2px; }
    .orders td[data-label="Pedido"]    { grid-area: pedido; min-width: 0; }
    .orders td[data-label="Productos"] { grid-area: prods; min-width: 0; }
    .orders td[data-label="Cobro"]     { grid-area: cobro; text-align: right; }
    .orders td[data-label="Estado"]    { grid-area: est; margin-top: 7px; }
    .orders td[data-label="Docs"]      { grid-area: doc; margin-top: 8px; }
    .orders td[data-label="Acciones"] { grid-area: act; margin-top: 8px; text-align: right; }

    /* En la tarjeta, los dos chips de estado caben en la misma linea. */
    .orders td[data-label="Estado"] .ord-st {
        flex-direction: row;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
    }
    /* «Cambiar» pasa a la derecha del todo para no partir la linea. */
    .orders .ord-status-editbar {
        margin-top: 0;
        margin-left: auto;
    }
    /* El nombre y el producto se recortan; el ancho lo manda la tarjeta,
       no el texto. Sin esto una direccion larga desborda la pantalla. */
    .orders .ord-o-cli,
    .orders .ord-i-first,
    .orders .ord-p-medio {
        max-width: 100%;
    }
    .orders .ord-i-first { display: none; }

    /* El detalle a pantalla completa: 480px en un telefono deja una franja
       de fondo inutil a un lado. El `!important` es necesario porque
       el-drawer fija el ancho en linea, y una regla de hoja de estilos con
       !important si gana a un style inline sin el. Va en el bloque NO
       scoped del padre: el cajon se pinta fuera del ambito del componente. */
    .od-drawer {
        width: 100% !important;
    }

    /* El popover de productos pide 540 px, que en un telefono se sale de
       la pantalla. El ancho lo fija Element UI en linea, asi que solo un
       max-width puede encogerlo. */
    .ord-items-pop {
        max-width: calc(100vw - 24px) !important;
        overflow-x: auto;
    }
}
.ord-status-editbar {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
}
.ord-saga-status {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-top: 5px;
}
.ord-lock-btn {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.ord-lock-btn:hover {
    border-color: #4f46e5;
    color: #4f46e5;
}
.ord-lock-btn i {
    margin-right: 3px;
}
.ord-lock-btn.cancel {
    color: #b91c1c;
}
.ord-lock-btn.cancel i {
    margin-right: 0;
}
@media only screen and (max-width: 485px) {
    .filter-container {
        margin-top: 0px;
        & .btn-filter-content,
        .btn-container-mobile {
            display: flex;
            align-items: center;
            justify-content: start;
        }
    }
}
</style>
<script>
import DataTable from "../../../components/DataTable.vue";
import queryString from "query-string";
import OptionsForm from "../pos/partials/options.vue";
import DocumentForm from "./partials/document_form.vue";
import SaleNoteForm from "./partials/sale_note_form.vue";
import ShipmentForm from "./partials/shipment_form.vue";
import OrderTimeline from "./partials/order_timeline.vue";
import RecordPayments from "../partials/record_payments.vue";
import ManualOrder from "./partials/manual_order.vue";
import ShipmentGuide from "./partials/shipment_guide.vue";
import BillingType from "./partials/billing_type.vue";
import SaleNoteGenerate from "../sale_notes/partials/option_documents.vue";
import DocumentsPanel from "./partials/documents_panel.vue";
import OrderDrawer from "./partials/order_drawer.vue";
import PaymentVerification from "./partials/payment_verification.vue";

export default {
    props: {
        user: { type: Object, default: null },
        /** ¿El tenant tiene el módulo de Envíos? Decide si hay configuración. */
        shipping: { type: Boolean, default: false },
        /** ¿El tenant exige verificar los cobros? Decide si se ofrece la acción. */
        verification: { type: Boolean, default: false },
    },

    components: {
        ShipmentGuide,
        BillingType,
        SaleNoteGenerate,
        DocumentsPanel,
        OrderDrawer,
        PaymentVerification,
        ManualOrder,
        DataTable,
        OptionsForm,
        DocumentForm,
        SaleNoteForm,
        ShipmentForm,
        OrderTimeline,
        RecordPayments,
    },
    data() {
        return {
            showDialog: false,
            showImportDialog: false,
            showImageDetail: false,
            resource: "orders",
            recordId: null,
            options: [],
            // Id del pedido cuyo estado está desbloqueado para editar (candado).
            editingStatusId: null,
            // Chips de filtro rápido (estilo Saga).
            mpFilter: "all",
            // Evita mezclar la cola de facturacion de Saga con pedidos propios.
            orderSource: "all",
            // Rango que se usa para seleccionar el lote de pedidos a facturar.
            invoiceDateRange: [],
            chipCounts: {},
            countsError: null,
            stats: {},
            selectedIds: [],
            currentRecords: [],
            // Envío del pedido (pestaña logística unificada).
            showShipmentDialog: false,
            shipmentOrderId: null,
            // Historial unificado (comercial + logístico).
            showTimelineDialog: false,
            timelineOrderId: null,
            // Chips = preguntas de trabajo, en el orden del flujo real:
            // confirmar → preparar → imprimir → embalar → despachar → tránsito
            // → entregar. Los tres últimos son de control, no de cola.
            orderChips: [
                { key: "all", label: "Todos" },
                // «Nuevos» es el buzon de entrada: el cliente acaba de
                // registrar su envio y nadie lo ha revisado. Es la misma
                // pestaña que abre por defecto el panel de Envios, y no tenia
                // equivalente aqui: «Por confirmar» suena parecido pero filtra
                // por el PAGO sin verificar, que es otra cosa.
                { key: "nuevos", label: "Nuevos" },
                { key: "por_confirmar", label: "Por confirmar" },
                { key: "por_preparar", label: "Por preparar" },
                { key: "por_imprimir", label: "Por imprimir" },
                { key: "por_embalar", label: "Por embalar" },
                { key: "por_despachar", label: "Por despachar" },
                { key: "en_transito", label: "En tránsito" },
                { key: "listos_recojo", label: "Listos para recojo" },
                { key: "entregados", label: "Entregados" },
                { key: "anulados", label: "Anulados" },
                { key: "sin_envio", label: "Sin envío" },
                { key: "no_invoice", label: "Falta emitir" },
            ],
            // Filtros logísticos de la barra superior.
            deliveryTypeFilter: "",
            agingFilter: "",
            // Periodo y fecha a considerar. Existían en el backend (`range` y
            // `date_type`) desde el primer commit, pero ninguna pantalla los
            // ofrecía: por eso Pedidos no filtraba como Registro de Envíos.
            dateRange: "",
            dateType: "order",
            rangeOptions: [
                { value: "", label: "Todo el histórico" },
                { value: "hoy", label: "Hoy" },
                { value: "ayer", label: "Ayer" },
                { value: "7dias", label: "Últimos 7 días" },
                { value: "30dias", label: "Últimos 30 días" },
                { value: "mes", label: "Este mes" },
                { value: "mes_pasado", label: "Mes pasado" },
                { value: "custom", label: "Personalizado…" },
            ],
            // Deben coincidir con OrderController::DATE_FIELDS.
            dateTypeOptions: [
                { value: "order", label: "Fecha del pedido" },
                { value: "paid", label: "Fecha de pago" },
                { value: "prepared", label: "Fecha de preparación" },
                { value: "printed", label: "Fecha de impresión" },
                { value: "dispatched", label: "Fecha de despacho" },
                { value: "delivered", label: "Fecha de entrega" },
                { value: "pickup", label: "Fecha de recojo" },
            ],
            deliveryTypeOptions: [
                { value: "", label: "Toda modalidad" },
                { value: "domicilio", label: "Lima / Callao" },
                { value: "agencia", label: "Provincia" },
                { value: "tienda", label: "Recojo en tienda" },
            ],
            agingOptions: [
                { value: "", label: "Cualquier antigüedad" },
                { value: "urgentes", label: "Urgentes" },
                { value: "vencidos", label: "Vencidos" },
            ],
            // Ruta lineal del pedido para el stepper (Cancelado=5 va aparte).
            statusSteps: [
                { id: 1, label: "Pendiente" },
                { id: 2, label: "Pago verificado" },
                { id: 3, label: "En preparación" },
                { id: 4, label: "Despachado" },
                { id: 6, label: "Entregado" },
            ],
            warehouses: [],
            estableciment_id: "",
            totalProduct: [], // items_id
            form: [],
            record: "", // record orders
            stocks: "",
            showDialogOptions: false,
            documentNewId: null,
            invoicing: null,   // mp_order_id que se esta emitiendo
            statusDocument: {},
            resource_options: null,
            loading_submit: false,
            document_types: [],
            order_id: null,
            dataSaleNote: {},
            showDialogSaleNote: false,
            showPaymentsDialog: false,
            paymentsOrderId: null,
            showManualDialog: false,
            showGuideDialog: false,
            guideOrderId: null,
            guideCode: "",
            // Busqueda unica. Se manda al DataTable como la columna `search`,
            // que en el backend es «buscar en todo».
            q: "",
            estadoPago: "",
            showMoreFilters: false,
            // Orden del listado. `fecha` reproduce el `latest()` de siempre,
            // asi que arrancar con el no cambia lo que el operador ya conoce.
            orden: "fecha",
            ordenDir: "desc",
            ordenOptions: [
                { value: "fecha", label: "Más recientes" },
                { value: "pedido", label: "N° de pedido" },
                { value: "cliente", label: "Cliente" },
                { value: "total", label: "Importe" },
                { value: "estado", label: "Estado" },
                { value: "actualizado", label: "Última actualización" },
            ],
            showVerifyDialog: false,
            verifyTipo: "order",
            verifyRecordId: null,
            showDrawer: false,
            drawerRow: null,
            showDocsDialog: false,
            docsRow: null,
            showCpeDialog: false,
            cpeSaleNoteId: null,
            showBillingDialog: false,
            billingOrderId: null,
            billingData: null,
            // null = alta; con id, el mismo dialogo edita ese pedido.
            manualOrderId: null,
            // A donde apunta el panel de pagos. Un encargo logistico cobra
            // contra su ENVIO (shipping_payments); el resto, contra el pedido.
            paymentsResource: "order_payments",
            paymentsForeignKey: "order_id",
            paymentsFileType: "orders",
            paymentsRecordId: null,
            paymentsTitle: "Pagos del pedido"
        };
    },
    async created() {
        this.$http.get(`/statusOrder/records`).then(response => {
            this.options = response.data;
        });
        this.loadChipCounts();
        this.loadStats();
        this.events();
    },
    computed: {
        /**
         * Cuantos filtros secundarios hay puestos.
         *
         * El boton «Mas filtros» los esconde, y un filtro escondido que sigue
         * activo es la forma mas facil de que alguien crea que faltan pedidos.
         * El numero se pinta en el propio boton.
         */
        extraFilterCount() {
            let n = 0;
            if (this.dateType !== "order") n++;
            if (this.deliveryTypeFilter) n++;
            if (this.agingFilter) n++;
            if ((this.invoiceDateRange || []).length) n++;

            return n;
        },
        hasActiveFilters() {
            return (
                !!this.dateRange ||
                this.dateType !== "order" ||
                !!this.deliveryTypeFilter ||
                !!this.agingFilter ||
                this.orderSource !== "all" ||
                !!this.estadoPago ||
                !!this.q
            );
        },
        allSelected() {
            return (
                this.currentRecords.length > 0 &&
                this.currentRecords.every(r =>
                    this.selectedIds.includes(r.id)
                )
            );
        },
    },
    methods: {
        onRecordsChanged(records) {
            this.currentRecords = records || [];
            this.selectedIds = []; // limpia selección al cambiar de página/filtro
            // Chips y KPI se recalculan CADA vez que la tabla se recarga, no
            // solo desde pushFilters(): la busqueda del DataTable recarga por
            // su cuenta y antes dejaba los tres numeros desincronizados.
            this.loadChipCounts();
            this.loadStats();
        },
        toggleAll(e) {
            if (e.target.checked) {
                this.selectedIds = this.currentRecords.map(r => r.id);
            } else {
                this.selectedIds = [];
            }
        },
        selectedRows() {
            return this.currentRecords.filter(r =>
                this.selectedIds.includes(r.id)
            );
        },
        runAction(cmd, row) {
            const acciones = {
                invoice: () => this.generateInvoice(row),
                upload: () => this.uploadInvoice(row),
                markExternal: () => this.markOneExternal(row),
                saleNote: () => this.clickOptions(row.sale_note_id),
                document: () => this.clickDownload(row.document_external_id),
                sagaLabel: () => this.downloadLabel(row),
                shippingLink: () => this.copyShippingLink(row),
                timeline: () => this.openTimeline(row),
                edit: () => this.editarPedido(row.id),
                cancelShipment: () => this.anularEnvio(row),
                payments: () => this.clickPayments(row.id),
                verifyPayments: () => this.verificarCobros(row),
                shipment: () => this.openShipment(row),
                restoreShipment: () => this.restaurarEnvio(row),
                // Documentos del pedido (Fase D). Ninguna de las tres emite
                // aqui: reenvian a los servicios y pantallas que ya existen.
                emitSaleNote: () => this.emitirNotaVenta(row),
                emitDocument: () => this.emitirComprobante(row),
                dispatchGuide: () => this.generarGuiaRemision(row),
                // OJO: `label` (rotulo del envio) y `sagaLabel` (hoja de
                // despacho de Saga) son acciones DISTINTAS. Estaban las dos
                // bajo la clave `label`, y en un objeto literal la segunda
                // pisa a la primera sin error: "Rotulo de Saga" acababa
                // imprimiendo el rotulo del envio.
                label: () => this.printLabel(row),
                // Mismo caso que `label`/`sagaLabel`, y volvio a pasar: SUBIR la
                // guia de la agencia y VERLA son acciones distintas, y las dos
                // se llamaban `guide`. En un objeto literal la segunda pisa a la
                // primera sin error, asi que «Subir guia» llamaba a `openGuide`,
                // que sin URL no hace nada. El boton estaba muerto.
                uploadGuide: () => this.subirGuia(row),
                viewGuide: () => this.openGuide(row)
            };
            if (acciones[cmd]) acciones[cmd]();
        },
        // Emitida en EBAEMY pero aun no cargada en Saga: sin esto la boleta
        // existe solo de nuestro lado y Saga la sigue esperando.
        canUploadInvoice(row) {
            return (
                this.isSagaOrder(row) &&
                row.mp_invoice_state === "ebaemy" &&
                !row.mp_invoice_uploaded
            );
        },
        async uploadInvoice(row) {
            try {
                const { data } = await this.$http.post(
                    `/ecommerce/marketplace/channels/${row.mp_channel_id}/orders/${row.mp_order_id}/upload-invoice`
                );
                this.$message.success(data.message || "Boleta subida a Saga.");
                this.$refs.ordersTable.getRecords();
            } catch (e) {
                const msg =
                    (e.response && e.response.data && (e.response.data.error || e.response.data.message)) ||
                    "No se pudo subir la boleta a Saga.";
                this.$message({ type: "error", message: msg, duration: 8000 });
            }
        },
        async markOneExternal(row) {
            if (!confirm("¿Marcar este pedido como ya facturado en el sistema de Saga?")) return;
            try {
                await this.$http.post(
                    `/ecommerce/marketplace/channels/${row.mp_channel_id}/orders/${row.mp_order_id}/mark-invoiced`
                );
                this.$message.success("Marcado.");
                this.$refs.ordersTable.getRecords();
                this.loadChipCounts();
            } catch (e) {
                this.$message.error("No se pudo marcar.");
            }
        },
        canGenerateInvoice(row) {
            // Solo pedidos ENTREGADOS o en camino. Un cancelado/devuelto no se
            // factura: en Peru esa boleta solo se deshace con nota de credito.
            return (
                this.isSagaOrder(row) &&
                !row.number_document &&
                row.mp_invoice_state === "pending" &&
                ["delivered", "shipped"].indexOf(row.mp_status) !== -1
            );
        },
        // 'shipped' = viajando: se puede facturar, pero el cliente todavia
        // puede rechazar el paquete. Se avisa antes.
        invoiceIsRisky(row) {
            return row.mp_status === "shipped";
        },
        invoiceButtonLabel(row) {
            return this.invoiceIsRisky(row) ? "Boleta ⚠" : "Boleta";
        },
        async generateInvoice(row) {
            // Es un comprobante ante SUNAT: se confirma con el monto a la vista
            // porque no se deshace con un boton.
            if (
                !confirm(
                    "Se emitira la boleta del pedido " +
                        (row.mp_external_order_id || row.mp_order_id) +
                        " por S/ " +
                        this.formatMoney(row.total) +
                        ". " +
                        (this.invoiceIsRisky(row)
                            ? "OJO: Saga todavia NO confirma la entrega. Si el cliente rechaza el paquete, la boleta quedaria emitida y habria que anularla con nota de credito. "
                            : "") +
                        "Es un comprobante ante SUNAT y no se puede deshacer. ¿Continuar?"
                )
            )
                return;

            this.invoicing = row.mp_order_id;
            try {
                const extra = this.invoiceIsRisky(row)
                    ? "?allow_undelivered=1"
                    : "";
                const { data } = await this.$http.post(
                    `/ecommerce/marketplace/channels/${row.mp_channel_id}/orders/${row.mp_order_id}/invoice${extra}`
                );
                this.$message.success(data.message || "Boleta generada.");
                this.$refs.ordersTable.getRecords();
                this.loadChipCounts();
            } catch (e) {
                // El motivo importa: casi siempre es un dato que falta (serie,
                // documento del cliente). Tragarselo dejaria al operador sin
                // saber que corregir.
                const msg =
                    (e.response && e.response.data && (e.response.data.error || e.response.data.message)) ||
                    "No se pudo generar la boleta.";
                this.$message({ type: "error", message: msg, duration: 8000 });
            } finally {
                this.invoicing = null;
            }
        },
        async bulkMarkInvoiced() {
            var rows = this.selectedRows().filter(r => this.isSagaOrder(r));
            if (!rows.length) {
                return this.$message.warning(
                    "Selecciona pedidos de marketplace."
                );
            }
            if (
                !confirm(
                    "¿Marcar " +
                        rows.length +
                        " pedido(s) como 'ya tiene boleta' (emitida fuera de EBAEMY)?"
                )
            )
                return;
            // Antes el catch estaba vacio y SIEMPRE decia "Listo: N marcados",
            // aunque fallaran todos. Se cuenta lo que de verdad se aplico.
            let ok = 0;
            let fallaron = 0;
            for (const r of rows) {
                try {
                    await this.$http.post(
                        `/ecommerce/marketplace/channels/${r.mp_channel_id}/orders/${r.mp_order_id}/mark-invoiced`
                    );
                    ok++;
                } catch (e) {
                    fallaron++;
                }
            }
            if (fallaron) {
                this.$message({
                    type: ok ? "warning" : "error",
                    message: `Marcados: ${ok}. No se pudo con ${fallaron}.`,
                    duration: 8000
                });
            } else {
                this.$message.success("Listo: " + ok + " marcados.");
            }
            this.selectedIds = [];
            this.$refs.ordersTable.getRecords();
        },
        bulkDownloadLabels() {
            var rows = this.selectedRows().filter(r => this.canDownloadLabel(r));
            if (!rows.length) {
                return this.$message.warning(
                    "Ningún pedido seleccionado tiene rótulo disponible."
                );
            }
            rows.forEach(r => this.downloadLabel(r));
        },
        loadChipCounts() {
            this.$http
                .get(`/orders/status-counts`, { params: this.countsParams() })
                .then(response => {
                    this.chipCounts = response.data || {};
                    this.countsError = null;
                })
                .catch(error => {
                    // El `catch` vacío que había aquí convertía cualquier fallo
                    // en "los chips salen sin número", que es indistinguible de
                    // "no hay nada que contar". Se perdía media hora antes de
                    // saber siquiera que la petición se estaba cayendo.
                    this.chipCounts = {};
                    this.countsError = this.describeError(error);
                    console.error("[pedidos] fallo al cargar los contadores", error);
                });
        },
        loadStats() {
            this.$http
                .get(`/orders/stats`, { params: this.countsParams() })
                .then(response => {
                    this.stats = response.data || {};
                })
                .catch(error => {
                    console.error("[pedidos] fallo al cargar los indicadores", error);
                });
        },
        /** Mensaje corto y accionable a partir de un error de axios. */
        describeError(error) {
            const res = error && error.response;
            if (!res) return "Sin respuesta del servidor (¿se cortó la conexión?).";
            if (res.status === 419) return "La sesión expiró. Recarga la página.";
            if (res.status === 403) return "No tienes permiso para ver estos contadores.";
            if (res.status === 500) return "Error del servidor al calcular los contadores.";
            if (res.status === 504) return "El cálculo de los contadores tardó demasiado.";
            return "El servidor respondió " + res.status + ".";
        },
        formatMoney(v) {
            return Number(v || 0).toLocaleString("es-PE", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        /**
         * ¿Este pedido viene de Saga/Falabella?
         *
         * Se llamaba desde cinco sitios —el menu de acciones de la fila,
         * `canGenerateInvoice`, `canUploadInvoice`, `canDownloadLabel` y el
         * marcado masivo— y NUNCA estuvo definida: entro asi en `0aee854e`.
         * No se notaba porque la tabla no llegaba a pintar ni una fila (el
         * filtro fantasma de almacen la dejaba siempre vacia), de modo que la
         * plantilla de fila jamas se ejecutaba. Al arreglar los datos, el
         * `TypeError` tumbaba el render y la pantalla se quedaba en la mascara
         * de carga: un bug tapaba al otro.
         *
         * Se exige la plataforma `falabella` y no solo «tiene pedido de
         * marketplace externo» porque estas acciones dicen literalmente «Subir
         * boleta a Saga» y «Marcar boleta hecha en Saga». Un pedido de otro
         * canal externo no debe verlas: el endpoint las rechazaria igual, pero
         * el operador ya habria leido una etiqueta que le miente. Cuando entre
         * en produccion una segunda plataforma habra que generalizar los
         * textos, y entonces esta condicion, no antes.
         */
        isSagaOrder(row) {
            return !!row.mp_order_id
                && String(row.mp_platform || "").toLowerCase() === "falabella";
        },

        // ── Columna «Documentos» ──────────────────────────────────────
        //
        // Estos tres metodos SOLO traducen a pixeles lo que ya viene resuelto
        // desde PHP. No deciden si un documento corresponde, ni si se puede
        // emitir, ni por que no: eso son reglas de SUNAT y viven en
        // `OrderDocuments`. Duplicarlas aqui garantizaria que un dia la
        // pantalla y el servidor digan cosas distintas.

        /**
         * Los documentos que corresponden a este pedido, en orden de lectura.
         *
         * El backend manda `null` para los que no aplican —el espejo de un
         * encargo no factura nada— y aqui se descartan: un chip gris que no
         * lleva a ninguna parte es peor que ningun chip.
         */
        documentSlots(row) {
            const d = row.documents || {};

            return ["nota_venta", "boleta", "factura", "guia"]
                .map(k => d[k])
                .filter(Boolean);
        },

        /**
         * Color del chip. Cuatro tonos, no siete: el operador necesita saber
         * si algo esta hecho, en curso, mal o pendiente — el detalle exacto lo
         * lee al abrir el panel.
         */
        docTone(s) {
            if (!s.existe) {
                // Sin bloqueo = listo para emitir; con bloqueo = falta algo.
                return s.bloqueo ? "off" : "ready";
            }

            switch (s.estado) {
                case "aceptado":
                case "emitido":
                    return "ok";
                case "registrado":
                case "enviado":
                    return "pend";
                case "observado":
                case "por_anular":
                    return "warn";
                case "rechazado":
                case "anulado":
                    return "err";
                default:
                    return "ok";
            }
        },

        /** El tooltip del chip: lo mismo que el panel, en una linea. */
        docTitle(s) {
            if (s.existe) {
                return s.nombre + " " + s.numero + " · " + s.estado_label;
            }

            return s.bloqueo || s.nombre + ": se puede emitir.";
        },

        /**
         * Abre la correccion del comprobante.
         *
         * Se le pasa el bloque `billing` de la fila tal cual: el dialogo no
         * recalcula nada, solo muestra lo que el servidor ya resolvio.
         */
        corregirComprobante(row) {
            this.billingOrderId = row.id;
            this.billingData = row.billing;
            this.showBillingDialog = true;
        },

        // ── Emision de documentos (Fase D) ────────────────────────────
        //
        // Ninguna de estas acciones emite nada por su cuenta. Reenvian al
        // servicio o a la pantalla que ya lo hacia:
        //   nota de venta  -> OrderToSaleNoteService
        //   comprobante    -> option_documents.vue, el MISMO modal de Notas de
        //                     Venta, reutilizado sin tocarle una linea
        //   guia           -> el formulario de Guia de Remision, precargado
        // La condicion para ofrecerlas la decide `OrderDocuments` en PHP.

        /**
         * Abre el detalle del pedido.
         *
         * Se guarda la fila entera: el cajon lee de ella y no consulta nada.
         * Toda la informacion que muestra ya viajaba en el payload — antes se
         * pintaba de golpe en la tabla y por eso no cabia.
         */
        verPedido(row) {
            this.drawerRow = row;
            this.showDrawer = true;
        },

        /**
         * Abre el panel de documentos.
         *
         * Se guarda la fila entera, no una copia: el panel necesita `documents`,
         * `billing` y `shipment`, y las acciones se emiten de vuelta con ella.
         */
        abrirDocumentos(row) {
            this.docsRow = row;
            this.showDocsDialog = true;
        },

        // ── Maqueta de la fila ────────────────────────────────────────
        //
        // Estos ayudantes solo formatean lo que la fila YA trae. No consultan
        // nada ni derivan reglas de negocio: cuando algo hay que decidir —que
        // documento corresponde, si un envio puede rotularse— viene resuelto
        // del servidor.

        /**
         * Fecha corta para la celda de Pedido: «07 sep · 14:35».
         *
         * La completa sigue disponible en el tooltip. Tenia una columna entera
         * para si con formato «DD-MM-YYYY h:mmA», que es mas largo y se lee
         * peor de un vistazo.
         */
        fechaCorta(v) {
            if (!v) return "";
            const d = moment(v);
            if (!d.isValid()) return "";

            // El mes se traduce a mano en vez de con `moment.locale('es')`:
            // `moment` es global (`window.moment`) y cambiarle el idioma aqui
            // se lo cambiaria a todas las pantallas del panel.
            const meses = ["ene","feb","mar","abr","may","jun",
                           "jul","ago","set","oct","nov","dic"];

            return d.format("DD") + " " + meses[d.month()] + " · " + d.format("HH:mm");
        },

        /** El primer producto, para dar contexto al contador. */
        primerProducto(row) {
            const items = row.items || [];
            if (!items.length) return "";

            const nombre = items[0].description || items[0].name || "";

            return nombre.length > 34 ? nombre.slice(0, 33) + "…" : nombre;
        },

        /**
         * Telefono y direccion del cliente.
         *
         * Se pintaban SIEMPRE, en dos renglones fijos, y son el texto mas largo
         * de la fila. Solo importan al despachar, asi que pasan al tooltip; el
         * detalle completo sigue en el popover de productos, que ya los traia.
         */
        clienteTitulo(row) {
            const partes = [row.customer];
            if (row.customer_telefono) partes.push("Tel. " + row.customer_telefono);
            if (row.customer_direccion) partes.push(row.customer_direccion);

            // El separador es un salto de linea escrito con su codigo y no
            // con la secuencia de escape: al generar este archivo desde una
            // herramienta la barra invertida se pierde y el literal queda
            // abierto, y el build muere con un error de parseo a 600 lineas
            // de distancia. Ya paso dos veces.
            return partes.filter(Boolean).join(String.fromCharCode(10));
        },

        /** Color del punto de canal. Gris cuando el pedido no declara canal. */
        canalColor(row) {
            const porTipo = {
                ecommerce: "#4f46e5",
                marketplace: "#b45309",
                pos: "#0f766e",
                other: "#64748b",
            };

            return porTipo[row.channel_type] || "#94a3b8";
        },

        canalTitulo(row) {
            return row.channel_name || "Sin canal declarado";
        },

        /**
         * Medio de pago. Absorbe la columna «Medio Pago», que pintaba
         * `reference_payment` en crudo y en mayusculas.
         */
        medioPago(row) {
            if (this.isMarketplace(row)) return this.marketplaceLabel(row);

            return row.reference_payment || "—";
        },

        /**
         * Importe a cobrar. En un encargo logistico vive en el ENVIO: el pedido
         * espejo nace con total 0 y una copia en el pedido se quedaria vieja
         * sin avisar.
         */
        importeCobro(row) {
            if (this.pagoEnElEnvio(row)) {
                return row.shipment && row.shipment.has_amount
                    ? "S/ " + this.formatMoney(row.shipment.amount_to_collect)
                    : "—";
            }

            return "S/ " + row.total;
        },

        /** Cuanto falta. Solo cuando falta algo: si no, el chip ya lo dice. */
        saldoCobro(row) {
            const s = row.shipment || {};
            // En marketplace no hay saldo: el cobro lo hizo el canal y aqui no
            // hay contra que compararlo.
            if (row.payment_state === "canal" || row.payment_state === "canal_anulado") {
                return "";
            }

            const pend = this.pagoEnElEnvio(row) ? s.pending_total : row.pending_total;

            return pend > 0 ? "saldo S/ " + this.formatMoney(pend) : "";
        },

        tituloCobro(row) {
            const s = row.shipment || {};
            const pagado = this.pagoEnElEnvio(row) ? s.paid_total : row.paid_total;

            return "Cobrado S/ " + this.formatMoney(pagado || 0);
        },

        /**
         * Etiqueta OPERATIVA del pedido.
         *
         * El catalogo `status_orders` bautizo los dos primeros estados con
         * lenguaje de dinero —«Pago pendiente» y «Pago verificado»— y eso hacia
         * que la columna Estado pareciera hablar del cobro. En alasitas son 212
         * de 298 pedidos diciendo «Pago verificado», y la mayoria son espejos de
         * encargos donde no hay NADA que cobrar en el pedido: la etiqueta mentia
         * dos veces.
         *
         * Ahora el dinero tiene su propia columna con su propio estado, asi que
         * aqui se dice en que ETAPA esta la operacion. Es un cambio de
         * presentacion: los ids del catalogo no se tocan —1.045 pedidos se
         * apoyan en ellos y otros modulos los leen—, solo la palabra que ve el
         * operador en esta pantalla.
         */
        etiquetaOperativa(id) {
            const propias = {
                1: "Por confirmar",
                2: "Listo para preparar",
                3: "En preparación",
                4: "Enviado",
                5: "Cancelado",
                6: "Entregado",
            };

            // Si un tenant añade un estado al catalogo, se muestra el suyo en
            // vez de quedarse en blanco.
            return propias[Number(id)] || this.statusLabel(id);
        },

        /** Tono del chip de estado comercial. */
        estadoTono(id) {
            switch (Number(id)) {
                case 1:
                    return "pend";
                case 2:
                    return "ok";
                case 3:
                    return "work";
                case 4:
                    return "ship";
                case 6:
                    return "done";
                default:
                    return "neutral";
            }
        },

        /**
         * A donde va el paquete: agencia y ciudad.
         *
         * `destination` ya devuelve una u otra —la agencia gana— pero en un
         * envio por agencia las dos hacen falta: «Shalom» no dice el destino y
         * «Trujillo» no dice por donde viaja. Cuando solo hay una, se pinta esa
         * y no se inventa la otra.
         */
        destinoEnvio(row) {
            const s = row.shipment || {};
            const partes = [s.agency, s.destination_city].filter(Boolean);

            if (partes.length) return partes.join(" · ");

            return s.destination && s.destination !== "—" ? s.destination : "";
        },

        /**
         * Destino, agencia y tracking, que perdieron su sitio en la fila.
         *
         * Van al tooltip del chip de envio. El dato completo sigue estando en
         * la ficha del envio, a un clic desde el menu.
         */
        envioTitulo(row) {
            const s = row.shipment || {};
            const partes = [s.delivery_label || s.delivery_short, s.status_label];

            const destino = this.shipmentDestination(row);
            if (destino) partes.push(destino);
            if (s.tracking_number) partes.push("Guía " + s.tracking_number);

            return partes.filter(Boolean).join(" · ");
        },

        /** ¿El servidor dice que este tipo se puede emitir ya? */
        puedeEmitir(row, tipo) {
            const s = (row.documents || {})[tipo];

            return !!s && !s.existe && !s.bloqueo;
        },

        /** Boleta o factura, la que corresponda y no este bloqueada. */
        comprobanteEmitible(row) {
            const d = row.documents || {};

            return ["boleta", "factura"].find(
                t => d[t] && !d[t].existe && !d[t].bloqueo
            );
        },

        puedeEmitirComprobante(row) {
            // Sin nota de venta no hay comprobante: todo el grafo de documentos
            // cuelga de ella, y el modal que se reutiliza lee de `/sale-notes`.
            return !!row.sale_note_id && !!this.comprobanteEmitible(row);
        },

        nombreComprobante(row) {
            const t = this.comprobanteEmitible(row);

            return t ? (row.documents[t].nombre || "").toLowerCase() : "comprobante";
        },

        emitirNotaVenta(row) {
            this.$confirm(
                "Se emitira la nota de venta del pedido " +
                    row.order_id +
                    " por S/ " +
                    row.total +
                    ". Queda como cancelada.",
                "Emitir nota de venta",
                { confirmButtonText: "Emitir", cancelButtonText: "Cancelar" }
            )
                .then(() =>
                    this.$http.post(`/orders/${row.id}/nota-venta`).then(r => {
                        this.$message.success(r.data.message);
                        this.refrescarTrasEnvio();
                    })
                )
                .catch(e => {
                    // El `catch` recoge tambien el «Cancelar» del confirm, que
                    // llega como la cadena 'cancel' y no es un error.
                    if (e === "cancel") return;
                    const d = (e.response && e.response.data) || {};
                    this.$message.error(d.message || "No se pudo emitir.");
                });
        },

        /**
         * Abre el modal de comprobante de Notas de Venta.
         *
         * Se le pasa el `sale_note_id` y el componente se encarga del resto: es
         * literalmente el mismo que usa esa pantalla, sin parametrizar ni
         * clonar. Por eso el pedido necesita la NV primero.
         */
        emitirComprobante(row) {
            this.cpeSaleNoteId = row.sale_note_id;
            this.showCpeDialog = true;
        },

        /**
         * Abre el formulario de Guia de Remision precargado.
         *
         * Dos puntos de partida, y la diferencia importa: si el pedido tiene
         * envio se entra por el, porque `ShipmentDispatchPrefill` resuelve
         * destinatario, direccion de llegada y transportista desde los datos del
         * encargo. Sin envio se entra por la nota de venta, que trae los items.
         *
         * No es una pantalla nuestra y no emite: el operador valida y corrige
         * antes de mandar a SUNAT, que es donde debe estar esa decision.
         */
        generarGuiaRemision(row) {
            const url =
                row.shipment && row.shipment.id
                    ? `/registro-envio/${row.shipment.id}/guia-remision`
                    : `/dispatches/create_new/sale_note/${row.sale_note_id}`;

            window.open(url, "_blank");
        },
        isMarketplace(row) {
            const ref = (row.reference_payment || "").toUpperCase();
            return ref.startsWith("MARKETPLACE") || row.channel_type === "marketplace";
        },
        marketplaceLabel(row) {
            const ref = (row.reference_payment || "").toUpperCase();
            if (ref.indexOf("FALABELLA") !== -1) return "Saga Falabella";
            if (ref.indexOf("MERCADOLIBRE") !== -1) return "MercadoLibre";
            if (ref.indexOf("TIKTOK") !== -1) return "TikTok Shop";
            if (ref.indexOf("META") !== -1) return "Meta";
            return row.channel_name || "Marketplace";
        },
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY h:mmA")
                : null;
        },
        /**
         * ¿El dinero de este pedido vive en el envío y no en el pedido?
         *
         * Los encargos de `/registro-envio` se espejan como pedido sin líneas y
         * sin importe: su monto (`amount_due`) y sus cobros viven en el ENVÍO,
         * en `shipping_payments`, que es el módulo de pagos bueno. Abrirles el
         * panel de `order_payments` mostraba un formulario vacío mientras había
         * cobros reales del otro lado.
         */
        pagoEnElEnvio(row) {
            return !!(row.shipment && row.shipment.id)
                && (!row.items || !row.items.length)
                && Number(row.total || 0) === 0;
        },
        /**
         * Texto de la accion de rotulado: dice lo que va a pasar de verdad.
         *
         * Un recojo en tienda no lleva rotulo —el servidor redirige al
         * comprobante de entrega— y una segunda impresion es una reimpresion
         * que va a pedir motivo. Llamarlas todas «Imprimir rotulo» hacia que el
         * operador descubriera la diferencia despues de abrir la pestaña.
         */
        openGuide(row) {
            const url = row.shipment && row.shipment.guide_url;
            if (!url) return;
            window.open(url, "_blank");
        },
        labelActionText(row) {
            const s = row.shipment || {};
            if (s.print_block) return "Rótulo no disponible";
            if (s.label_kind === "receipt") return "Comprobante de recojo";
            return s.needs_reason
                ? `Reimprimir rótulo (${s.print_count})`
                : "Imprimir rótulo";
        },

        /**
         * Imprime el rotulo del envio del pedido.
         *
         * Reutiliza `printLabel` del modulo de Envios en vez de duplicarlo: ahi
         * viven el conteo de impresiones, el historial, la bitacora, el bloqueo
         * por estado y los formatos. Aqui solo se decide que URL abrir.
         */
        printLabel(row) {
            const s = row.shipment;
            if (!s) return;

            if (s.print_block) {
                this.$message.warning(s.print_block);
                return;
            }

            // El servidor NO bloquea por datos incompletos: imprime un rotulo
            // igualmente. Por eso se avisa aqui, que es el ultimo momento util
            // — despues el paquete ya sale con una etiqueta sin destino.
            if (s.missing_data && s.missing_data.length) {
                // El mensaje se arma por lineas y se une con un salto real:
                // asi no depende de secuencias de escape, que es donde se
                // rompio la primera version de este aviso.
                const lineas = ["Al envio " + s.code + " le faltan datos para rotular:", ""]
                    .concat(s.missing_data.map(d => "  · " + d))
                    .concat(["", "El rotulo se imprimira igual, pero saldra incompleto. ¿Continuar?"]);

                if (!window.confirm(lineas.join(String.fromCharCode(10)))) return;
            }

            let url = `/registro-envio/${s.id}/imprimir`;

            // La reimpresion exige motivo y queda en el historial. Se pide
            // ANTES de abrir la pestaña: el servidor lo rechazaria igual, pero
            // el operador se encontraria el error en una ventana nueva.
            if (s.needs_reason) {
                const motivo = window.prompt(
                    `El rótulo de ${s.code} ya se imprimió ${s.print_count} vez/veces.
` +
                    `Indica el motivo de la reimpresión (queda registrado):`
                );
                if (motivo === null) return;
                if (!motivo.trim()) {
                    this.$message.warning("Sin motivo no se puede reimprimir.");
                    return;
                }
                url += `?motivo=${encodeURIComponent(motivo.trim())}`;
            }

            window.open(url, "_blank");

            // El conteo de impresiones cambia del lado del servidor: sin
            // refrescar, el siguiente clic seguiria creyendo que es la primera.
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
        },

        /**
         * Tras crear un pedido a mano hay que refrescar todo: la fila nueva, los
         * contadores de los chips y los indicadores. Es la misma recarga que
         * usa cualquier otro cambio, no una especial.
         */
        /**
         * Refresco tras una accion del envio hecha en su propio dialogo.
         * No anuncia nada: el dialogo ya dijo lo que paso, y dos avisos
         * seguidos para la misma accion se leen como si hubieran pasado dos
         * cosas.
         */
        refrescarTrasEnvio() {
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
            this.loadChipCounts();
        },

        /** Devuelve a la vida un envio anulado, con su motivo en la bitacora. */
        restaurarEnvio(row) {
            const c = row.shipment_cancelled;
            if (!c) return;

            this.$prompt(
                "Motivo de la restauración (queda en la bitácora). El envío vuelve " +
                    "al estado en que estaba antes de anularse.",
                "Restaurar envío " + (c.code || ""),
                { confirmButtonText: "Restaurar", cancelButtonText: "Cancelar" }
            )
                .then(({ value }) =>
                    this.$http.post(`/orders/${row.id}/envio/restaurar`, { reason: value || null })
                )
                .then(r => this.trasAccionDeEnvio(r))
                .catch(e => this.trasAccionDeEnvio(e, true));
        },

        subirGuia(row) {
            this.guideOrderId = row.id;
            this.guideCode = row.shipment ? row.shipment.code : "";
            this.showGuideDialog = true;
        },

        /** Anula el envio. El pedido NO se anula: son cosas distintas. */
        anularEnvio(row) {
            this.$prompt(
                "Motivo de la anulación (queda en la bitácora). El pedido no se anula: " +
                    "solo su envío, y puede restaurarse después.",
                "Anular envío " + (row.shipment ? row.shipment.code : ""),
                { confirmButtonText: "Anular envío", cancelButtonText: "Cancelar" }
            )
                .then(({ value }) =>
                    this.$http.post(`/orders/${row.id}/envio/anular`, { reason: value || null })
                )
                .then(r => this.trasAccionDeEnvio(r))
                .catch(e => this.trasAccionDeEnvio(e, true));
        },

        /**
         * Respuesta comun de las acciones del envio.
         *
         * El servidor responde 200 incluso cuando rechaza —lleva `success:
         * false` y el motivo— porque son reglas de negocio, no errores. Un
         * `cancel` del dialogo llega aqui como rechazo sin respuesta: eso no se
         * anuncia, el operador ya sabe que cancelo.
         */
        trasAccionDeEnvio(r, esError = false) {
            if (esError) {
                if (!r || !r.response) return;   // cancelo el dialogo
                const d = r.response.data || {};
                this.$message.error(d.message || "No se pudo completar la acción.");
                return;
            }

            const d = (r && r.data) || {};

            if (d.success === false) {
                this.$message({ type: "warning", message: d.message, duration: 7000 });
                return;
            }

            this.$message.success(d.message || "Hecho.");
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
            this.loadChipCounts();
        },

        editarPedido(orderId) {
            this.manualOrderId = orderId;
            this.showManualDialog = true;
        },
        onManualCreated() {
            this.manualOrderId = null;
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
            this.loadChipCounts();
            this.loadStats();
        },

        clickPayments(orderId) {
            const row = (this.currentRecords || []).find(r => r.id === orderId);

            // Antes esto abria la pantalla de Envios en otra pestaña. Era
            // correcto en cuanto a la fuente de verdad —el dinero del encargo
            // vive en shipping_payments— pero sacaba al operador del listado y
            // le hacia perder filtros, pagina y posicion.
            //
            // Ahora se queda aqui: el panel es el mismo, solo cambia a donde
            // apunta. Detras, `shipment_payments` es un ADAPTADOR que reenvia
            // al modulo de Envios, asi que las reglas de cobro —codigo de
            // operacion, duplicados, tope contra el saldo, Finanzas— siguen
            // estando en un unico sitio.
            if (row && this.pagoEnElEnvio(row)) {
                this.paymentsResource   = "shipment_payments";
                this.paymentsForeignKey = "shipment_id";
                this.paymentsFileType   = "shipments";
                this.paymentsRecordId   = row.shipment.id;
                this.paymentsTitle      = `Pagos del encargo ${row.shipment.code || ""}`.trim();
            } else {
                this.paymentsResource   = "order_payments";
                this.paymentsForeignKey = "order_id";
                this.paymentsFileType   = "orders";
                this.paymentsRecordId   = orderId;
                this.paymentsTitle      = "Pagos del pedido";
            }

            this.paymentsOrderId    = orderId;
            this.showPaymentsDialog = true;
        },
        // El saldo cambio: se refresca la fila y tambien los contadores, que
        // dependen del estado de pago (un pedido que se salda deja de estar
        // "por confirmar").
        onPaymentsUpdated() {
            this.refreshAfterPayment();
        },
        clickOptions(recordId) {
            this.documentNewId = recordId;
            this.statusDocument.send = "";
            this.resource_options = "sale-notes";
            this.showDialogOptions = true;
        },
        async clickDownload(row) {
            await this.$http
                .get(`/documents/search/externalId/${row}`)
                .then(response => {
                    this.documentNewId = response.data.id;
                });
            this.statusDocument.send = "";
            this.resource_options = "documents";
            this.showDialogOptions = true;
        },
        applyMpFilter(key) {
            this.mpFilter = key;
            var dt = this.$refs.ordersTable;
            if (!dt) return;
            // Inyecta el filtro en la consulta del DataTable (se hace spread de
            // search en getQueryParameters) y recarga desde el server.
            // `chip` es el parámetro unificado; `mp_filter` se limpia para que
            // un chip antiguo guardado no se quede aplicado por debajo.
            dt.search.chip = key === "all" ? null : key;
            dt.search.mp_filter = null;
            dt.pagination.current_page = 1;
            dt.getRecords();
        },

        /**
         * Filtros logísticos (modalidad y antigüedad).
         * Se recalculan los contadores porque acotan la base de los chips.
         */
        applyLogisticFilters() {
            this.pushFilters();
        },

        /**
         * Crea un lote de impresión con los pedidos seleccionados.
         *
         * El backend traduce pedidos → envíos y descarta los que no son
         * elegibles; aquí solo se informa el resultado, incluido qué pedidos
         * quedaron fuera por no tener envío configurado (que es accionable:
         * hay que configurárselo).
         */
        async bulkCreatePrintBatch() {
            if (!this.selectedIds.length) return;

            try {
                const { data } = await this.$http.post("/orders/print-batch", {
                    order_ids: this.selectedIds,
                    format: "a4",
                });

                let message = data.message;
                if (data.orders_without_shipment && data.orders_without_shipment.length) {
                    message +=
                        " Sin envío configurado: " +
                        data.orders_without_shipment.length +
                        " pedido(s).";
                }
                this.$message.success(message);

                this.selectedIds = [];
                this.$refs.ordersTable.getRecords();
                this.loadChipCounts();

                if (data.print_url) window.open(data.print_url, "_blank");
            } catch (e) {
                const body = e.response && e.response.data;
                this.$message.error((body && body.message) || "No se pudo crear el lote.");
            }
        },

        /**
         * Copia el enlace público para que el cliente complete sus datos de
         * entrega. El token es el `external_id` del pedido, así que el enlace
         * cae SIEMPRE sobre ese pedido y no puede crear uno nuevo.
         */
        async copyShippingLink(row) {
            const url =
                window.location.origin + "/pedido/" + row.external_id + "/datos-envio";

            try {
                // `clipboard` no existe fuera de HTTPS/localhost: sin el
                // fallback el operador se queda sin enlace y sin explicación.
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const helper = document.createElement("textarea");
                    helper.value = url;
                    helper.style.position = "fixed";
                    helper.style.opacity = "0";
                    document.body.appendChild(helper);
                    helper.select();
                    document.execCommand("copy");
                    document.body.removeChild(helper);
                }
                this.$message.success("Enlace copiado. Envíaselo al cliente.");
            } catch (e) {
                this.$alert(url, "Enlace de datos de envío", {
                    confirmButtonText: "Cerrar",
                });
            }
        },

        /** Abre el historial unificado del pedido. */
        openTimeline(row) {
            this.timelineOrderId = row.id;
            this.showTimelineDialog = true;
        },

        /** Abre la pestaña de envío del pedido. */
        openShipment(row) {
            this.shipmentOrderId = row.id;
            this.showShipmentDialog = true;
        },

        /** Tras configurar el envío, la fila debe reflejarlo sin recargar. */
        onShipmentSaved() {
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
            this.loadChipCounts();
        },

        /** Texto del destino para la columna de entrega. */
        shipmentDestination(row) {
            const s = row.shipment;
            if (!s) return "";
            return s.destination || "—";
        },
        /**
         * Filtros que POSEE esta pantalla: periodo, origen y logistica. Son los
         * que pushFilters() vuelca sobre `dt.search`. Los chips y los KPI NO
         * los consumen directamente — para eso esta countsParams().
         */
        invoiceDateParams() {
            const custom = this.dateRange === "custom";
            return {
                range: custom ? null : this.dateRange || null,
                date_type: this.dateType,
                date_from: custom ? (this.invoiceDateRange || [])[0] || null : null,
                date_to: custom ? (this.invoiceDateRange || [])[1] || null : null,
                order_source: this.orderSource,
                delivery_type: this.deliveryTypeFilter || null,
                aging: this.agingFilter || null,
            };
        },

        /**
         * Filtros de los chips y los KPI: EXACTAMENTE los de la tabla, menos el
         * chip activo (el numero de un chip debe ser lo que veras al pulsarlo).
         *
         * Se leen de `dt.search`, que es donde vive el estado real de la
         * consulta, y no solo de los filtros de esta pantalla: la busqueda del
         * DataTable tambien acota la tabla, y contarla aparte hacia que los
         * chips siguieran mostrando el histórico completo mientras la tabla
         * mostraba un cliente.
         *
         * `chip`/`mp_filter` se excluyen a proposito. `warehouse_id` viaja
         * porque la tabla lo manda: si los dos no mandan lo mismo, el numero
         * del chip vuelve a mentir.
         */
        countsParams() {
            const dt = this.$refs.ordersTable;
            const propios = this.invoiceDateParams();
            if (!dt) return propios;

            const heredados = Object.assign({}, dt.search);
            delete heredados.chip;
            delete heredados.mp_filter;

            return Object.assign(heredados, propios, {
                warehouse_id: dt.warehouse_id,
            });
        },

        /** Vuelca los filtros actuales en la tabla y recarga todo. */
        pushFilters() {
            const dt = this.$refs.ordersTable;
            if (!dt) return;

            Object.assign(dt.search, this.invoiceDateParams());

            // La busqueda viaja por el mismo canal que usaba el buscador del
            // DataTable: la columna `search` es «buscar en todo» en
            // `OrderController::columns()`. No se inventa un parametro nuevo.
            dt.search.column = "search";
            dt.search.value = this.q ? this.q.trim() : null;

            // Parametros propios y no `sort_field`: ese lo manda el DataTable
            // siempre con `id`, y honrarlo cambiaria el orden por defecto sin
            // que nadie lo hubiera pedido. Ver `OrderController::applyOrderSort`.
            dt.search.estado_pago = this.estadoPago || null;
            dt.search.orden = this.orden;
            dt.search.orden_dir = this.ordenDir;

            dt.pagination.current_page = 1;
            dt.getRecords();
            this.loadChipCounts();
            this.loadStats();
        },

        applyDateFilters() {
            // Al elegir "personalizado" todavía no hay fechas: no se recarga
            // hasta que el usuario elija el rango, o se perdería el filtro
            // anterior mostrando el histórico completo sin haberlo pedido.
            if (this.dateRange === "custom" && !(this.invoiceDateRange || []).length) return;
            this.pushFilters();
        },

        clearFilters() {
            this.dateRange = "";
            this.dateType = "order";
            this.invoiceDateRange = [];
            this.deliveryTypeFilter = "";
            this.agingFilter = "";
            this.orderSource = "all";
            this.q = "";
            this.estadoPago = "";
            this.orden = "fecha";
            this.ordenDir = "desc";
            this.pushFilters();
        },

        /**
         * Abre la verificacion de cobros.
         *
         * Apunta a la tabla donde vive el dinero de ESTE pedido: en un encargo
         * logistico son los cobros del envio, no los del pedido. Misma regla
         * que usa el panel de pagos.
         */
        verificarCobros(row) {
            const enElEnvio = this.pagoEnElEnvio(row);
            this.verifyTipo = enElEnvio ? "shipment" : "order";
            this.verifyRecordId = enElEnvio ? row.shipment.id : row.id;
            this.showVerifyDialog = true;
        },

        /** Abre una pantalla del modulo de Envios en otra pestaña. */
        irA(destino) {
            const rutas = {
                tienda: "/registro-envio/config-tienda",
                motorizado: "/registro-envio/motorizado",
            };

            if (rutas[destino]) window.open(rutas[destino], "_blank");
        },

        /** Buscar. Vuelve siempre a la pagina 1: buscar en la 4 no tiene sentido. */
        applySearch() {
            this.pushFilters();
        },
        applyOrderSource() {
            this.pushFilters();
        },
        canDownloadLabel(row) {
            // Solo pedidos de Saga ya despachables tienen rótulo en Saga.
            return (
                this.isSagaOrder(row) &&
                ["ready_to_ship", "shipped", "delivered"].indexOf(
                    row.mp_status
                ) !== -1
            );
        },
        downloadLabel(row) {
            window.open(
                "/ecommerce/marketplace/channels/" +
                    row.mp_channel_id +
                    "/orders/" +
                    row.mp_order_id +
                    "/document/shippingLabel",
                "_blank"
            );
        },
        statusIndex(statusId) {
            // Posición en la ruta lineal; -1 si no está (ej. Cancelado=5).
            return this.statusSteps.findIndex(function (s) {
                return String(s.id) === String(statusId);
            });
        },
        stepDone(statusId, i) {
            var cur = this.statusIndex(statusId);
            return cur >= 0 && i <= cur;
        },
        stepClass(statusId, i) {
            var cur = this.statusIndex(statusId);
            if (cur < 0) return "pending";
            if (i < cur) return "done";
            if (i === cur) return "current";
            return "pending";
        },
        statusLabel(statusId) {
            var found = this.options.find(function (o) {
                return String(o.id) === String(statusId);
            });
            if (found) return found.description;
            var step = this.statusSteps.find(function (s) {
                return String(s.id) === String(statusId);
            });
            return step ? step.label : "";
        },
        subtotal(item) {
            var subtotal;
            if (item.currency_type_id === "USD") {
                subtotal = Number(
                    item.cantidad *
                        item.exchange_rate_sale *
                        parseFloat(item.sale_unit_price)
                ).toFixed(2);
                if (isNaN(subtotal)) {
                    return "-";
                } else {
                    return subtotal;
                }
            } else {
                return parseFloat(item.cantidad * item.sale_unit_price);
            }
        },
        optionDisable(product, stock) {
            for (var i = 0; i < this.record.items.length; i++) {
                if (product === this.record.items[i].id) {
                    return stock >= this.record.items[i].cantidad
                        ? false
                        : true;
                }
            }
        },
        openDialogSaleNote(sale_note) {
            this.dataSaleNote = sale_note;
            this.showDialogSaleNote = true;
        },
        async updateStatus(record) {
            this.record = record;
            // Re-bloquea (candado) tras intentar el cambio.
            this.editingStatusId = null;

            if (record.status_order_id === 2) {
                this.order_id = record.id;

                if (record.purchase.codigo_tipo_documento == "80") {
                    if (record.has_sale_note)
                        return this.$message.success(
                            "Ya existe una nota de venta"
                        );
                    this.openDialogSaleNote(record.purchase);
                } else {
                    if (record.document_external_id) {
                        return this.$message.success(
                            "Ya existe un comprobante."
                        );
                    }
                    this.$refs.document_form.sendPreview(record.purchase);
                }
            } else if (record.status_order_id === 3) {
                this.totalProduct = await this.products(record);
                await this.$http
                    .post(`/orders/warehouse`, { item_id: this.totalProduct })
                    .then(response => {
                        this.warehouses = response.data.data;
                        this.showDialog = true;
                    });
                return;
            } else {
                this.saveUpdateStatus();
            }
        },
        saveUpdateStatus() {
            this.$http
                .post(`/statusOrder/update`, { record: this.record })
                .then(response => {
                    this.$message.success(response.data.message);
                    // Verificar el pago registra los pagos y genera la nota de
                    // venta, pero antes no se refrescaba nada: la fila seguia
                    // mostrando el estado viejo y habia que recargar la pagina
                    // a mano para verlo.
                    this.refreshAfterPayment();
                })
                .catch(error => {
                    // Sin este catch, un fallo se veia igual que un exito: no
                    // pasaba nada en pantalla y el pago quedaba sin registrar.
                    this.$message.error(this.describeError(error) || 'No se pudo actualizar el pedido');
                });
        },

        /**
         * Refresca solo lo que cambia al registrar un pago: la fila del
         * listado, los contadores de los chips y las metricas de arriba.
         *
         * getRecords() conserva la pagina, el orden, la busqueda y los filtros
         * activos, asi que el operador no pierde el contexto.
         */
        refreshAfterPayment() {
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
            this.loadChipCounts();
            this.loadStats();
        },
        async save() {
            var save = [];

            for (var i = 0; i < this.record.items.length; i++) {
                if (this.totalProduct[i] === this.record.items[i].id) {
                    save.push({
                        id: this.form[this.totalProduct[i]],
                        cantidad: this.record.items[i].cantidad
                    });
                }
            }

            await this.$http
                .post(`/statusOrder/update`, {
                    record: this.record,
                    discount: save
                })
                .then(response => {
                    this.$message.success(response.data.message);
                    this.close();
                    this.refreshAfterPayment();
                })
                .catch(error => {
                    this.$message.error(this.describeError(error) || 'No se pudo guardar el pedido');
                });
        },
        close() {
            this.form = [];
            this.showDialog = false;
            this.recoard = "";
        },
        products(products) {
            let listProduct = [];

            for (var i = 0; i <= products.items.length - 1; i++) {
                listProduct.push(products.items[i].id);
            }
            return listProduct;
        },
        async events() {
            await this.$eventHub.$on("cancelSale", () => {
                this.showDialogOptions = false;
            });
        },

        getHeaderConfig() {
            let token = this.user.api_token;
            let httpConfig = {
                headers: {
                    "Content-Type": "application/json",
                    Authorization: `Bearer ${token}`
                }
            };
            return httpConfig;
        }
    }
};
</script>
