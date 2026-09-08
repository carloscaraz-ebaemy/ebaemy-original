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
            <!-- El hueco de la cabecera estaba vacio y la barra de filtros
                 cargaba con siete controles. Lo que no se usa en cada consulta
                 —ajustes de la tienda y la descarga— sube aqui. -->
            <div class="right-wrapper pull-right ord-head-actions">
                <!-- Las cuatro pantallas de apoyo del envio. Estaban
                     repartidas entre tres entradas del menu lateral y este
                     boton; ahora entran todas por aqui, que es desde donde se
                     trabaja. El panel de «Registro de Envios» sale del menu:
                     dio de alta DOS envios en toda su vida.

                     El punto del boton avisa de lo que no puede esperar sin
                     obligar a abrir el menu para enterarse. -->
                <el-dropdown v-if="shipping" trigger="click" @command="irA">
                    <button class="ord-head-btn" :title="avisoEnvios || 'Pantallas de envío'">
                        <i class="fas fa-cog"></i> Envíos
                        <span v-if="avisoEnvios" class="ord-head-dot"></span>
                    </button>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item command="tienda">
                            <i class="el-icon-office-building"></i>
                            Configuración de tienda
                        </el-dropdown-item>
                        <!-- OJO: esto NO es una configuracion de motorizados
                             —no existe tal catalogo— sino el tablero de
                             reparto a domicilio. -->
                        <el-dropdown-item command="motorizado">
                            <i class="el-icon-bicycle"></i>
                            Tablero de reparto a domicilio
                        </el-dropdown-item>
                        <el-dropdown-item command="tablero" divided>
                            <i class="el-icon-data-line"></i>
                            Tablero logístico
                            <span v-if="shipAlerts.sin_guia" class="ord-head-badge is-bad"
                                >{{ shipAlerts.sin_guia }} sin guía</span
                            >
                        </el-dropdown-item>
                        <el-dropdown-item command="lotes">
                            <i class="el-icon-tickets"></i>
                            Lotes de impresión
                            <span v-if="shipAlerts.lotes" class="ord-head-badge"
                                >{{ shipAlerts.lotes }} abierto{{ shipAlerts.lotes === 1 ? "" : "s" }}</span
                            >
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>

                <!-- Exporta lo FILTRADO, no todo: un boton que ignora los
                     filtros recien puestos descarga 710 filas cuando se
                     pidieron 12. -->
                <button class="ord-head-btn" title="Descargar el listado filtrado en Excel" @click="exportar">
                    <i class="fas fa-file-download"></i> Exportar
                </button>
            </div>
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
                    <!-- Dar de alta cierra la fila de indicadores: es la unica
                         accion que CREA algo, y perdida entre los filtros
                         parecia uno mas de ellos. -->
                    <button class="ord-new-btn" @click="manualOrderId = null; showManualDialog = true">
                        <i class="fas fa-plus"></i> Nuevo pedido
                    </button>
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
                <!-- Barra de filtros.
                     Empezo con SEIS desplegables siempre visibles; luego bajaron
                     a cinco. Sigue siendo una fila de controles que el operador
                     lee de izquierda a derecha cada vez para saber que hay
                     puesto. Ahora arriba solo esta lo que se toca a diario, el
                     resto vive en un cajon, y lo que este activo se ve como
                     chips que se quitan de un clic — que es la pregunta real:
                     no «que filtros hay» sino «que estoy filtrando». -->
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
                            placeholder="Buscar por código, cliente, DNI, RUC, teléfono o guía…"
                            @keyup.enter="applySearch"
                            @search="applySearch"
                        />
                        <button v-if="q" class="ord-search-x" title="Limpiar" @click="q = ''; applySearch()">
                            <i class="el-icon-close"></i>
                        </button>
                        <button class="ord-search-go" @click="applySearch">Buscar</button>
                    </div>

                    <button
                        class="ord-bar-more"
                        :class="{ 'is-on': filtrosActivos.length }"
                        @click="showFiltersDrawer = true"
                    >
                        <i class="fas fa-sliders-h"></i> Filtros
                        <span v-if="filtrosActivos.length" class="ord-bar-badge">{{
                            filtrosActivos.length
                        }}</span>
                    </button>

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
                </div>

                <!-- Lo que esta filtrando ahora mismo. Un filtro puesto y
                     escondido es la forma mas facil de que alguien crea que
                     faltan pedidos; aqui se ve y se quita de un clic. -->
                <div v-if="filtrosActivos.length" class="ord-fchips">
                    <span
                        v-for="f in filtrosActivos"
                        :key="f.key"
                        class="ord-fchip"
                    >
                        {{ f.label }}
                        <button :title="'Quitar ' + f.label" @click="quitarFiltro(f.key)">×</button>
                    </span>
                    <button class="ord-fchips-clear" @click="clearFilters">
                        Limpiar todo
                    </button>
                </div>

                <!-- Aviso de vencidos. Solo si los hay: una franja permanente
                     diciendo «0 vencidos» ocupa sitio y deja de leerse, que es
                     lo contrario de lo que tiene que hacer una alerta. -->
                <div
                    v-if="shipping && chipCounts.vencidos > 0 && agingFilter !== 'vencidos'"
                    class="ord-overdue"
                >
                    <span>
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>{{ chipCounts.vencidos }}</strong>
                        {{ chipCounts.vencidos === 1 ? "pedido superó" : "pedidos superaron" }}
                        el plazo de despacho
                    </span>
                    <button class="ord-overdue-cta" @click="verAntiguedad('vencidos')">
                        Ver vencidos →
                    </button>
                </div>

                <!-- Prioridad. El plazo sale de los dias habiles configurados en
                     la tienda, no de dias de calendario: el filtro ya existia en
                     el servidor y lo que faltaba era poder llegar a el sin
                     abrir «Mas filtros». -->
                <!-- Esta franja es lo ultimo antes de la tabla, asi que su
                     extremo derecho cae justo sobre la columna «Acciones»: ahi
                     va el selector de columnas, que decide como se ve la tabla
                     y no que pedidos entran en ella.
                     El recuadro se pinta SIEMPRE aunque el tenant no tenga el
                     modulo de Envios; lo que desaparece sin envios son los
                     botones de prioridad, no las columnas. -->
                <div class="ord-prio">
                    <template v-if="shipping">
                    <span class="ord-prio-lbl">Prioridad</span>
                    <button
                        class="ord-prio-btn"
                        :class="{ 'is-on': orden === 'fecha' && ordenDir === 'asc' }"
                        title="El pedido que lleva más tiempo esperando, arriba"
                        @click="masAntiguosPrimero"
                    >
                        Más antiguos primero
                    </button>
                    <button
                        v-if="chipCounts.urgentes"
                        class="ord-prio-btn is-warn"
                        :class="{ 'is-on': agingFilter === 'urgentes' }"
                        title="A un día hábil de pasarse del plazo"
                        @click="verAntiguedad('urgentes')"
                    >
                        Urgentes <b>{{ chipCounts.urgentes }}</b>
                    </button>
                    <button
                        v-if="chipCounts.vencidos"
                        class="ord-prio-btn is-bad"
                        :class="{ 'is-on': agingFilter === 'vencidos' }"
                        title="Pasados del plazo de despacho"
                        @click="verAntiguedad('vencidos')"
                    >
                        Vencidos <b>{{ chipCounts.vencidos }}</b>
                    </button>
                    <button
                        v-if="agingFilter"
                        class="ord-prio-clear"
                        @click="agingFilter = ''; applyLogisticFilters()"
                    >
                        Quitar
                    </button>
                    </template>

                    <el-dropdown class="ord-prio-cols" trigger="click" :hide-on-click="false">
                        <button class="ord-cols-btn" title="Elegir qué columnas ver">
                            <i class="fas fa-table-columns"></i> Columnas
                        </button>
                        <el-dropdown-menu slot="dropdown">
                            <el-dropdown-item
                                v-for="c in columnasOpcionales"
                                :key="c.key"
                            >
                                <el-checkbox
                                    :value="columnas[c.key]"
                                    @change="alternarColumna(c.key)"
                                    >{{ c.label }}</el-checkbox
                                >
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </el-dropdown>
                </div>

                <!-- Todos los filtros, en un cajon.
                     Se aplican al cambiarlos, no al pulsar «Aplicar»: con un
                     boton habria DOS estados —lo elegido y lo aplicado— y basta
                     con que alguien cierre el cajon sin pulsarlo para que la
                     tabla y los controles digan cosas distintas. Los chips de
                     arriba dan la confirmacion inmediata que ese boton
                     pretendia dar. -->
                <el-drawer
                    :visible.sync="showFiltersDrawer"
                    :with-header="false"
                    direction="rtl"
                    size="380px"
                    custom-class="ord-fdrawer"
                >
                    <div class="ord-fd">
                        <header class="ord-fd-head">
                            <span>Filtros</span>
                            <button class="ord-fd-x" @click="showFiltersDrawer = false">
                                <i class="el-icon-close"></i>
                            </button>
                        </header>

                        <div class="ord-fd-body">
                            <section class="ord-fd-sec">
                                <h5>Cobro</h5>
                                <el-select v-model="estadoPago" size="small" @change="pushFilters">
                                    <el-option label="Todo el cobro" value=""></el-option>
                                    <el-option label="Pago pendiente" value="pendiente"></el-option>
                                    <el-option label="Pago parcial" value="parcial"></el-option>
                                    <el-option label="Pagado" value="pagado"></el-option>
                                    <el-option label="Cobrado por el canal" value="canal"></el-option>
                                </el-select>
                            </section>

                            <section class="ord-fd-sec">
                                <h5>Origen</h5>
                                <el-select v-model="orderSource" size="small" @change="applyOrderSource">
                                    <el-option label="Todos los pedidos" value="all"></el-option>
                                    <el-option label="Solo Saga Falabella" value="saga"></el-option>
                                    <el-option label="Otros pedidos" value="other"></el-option>
                                </el-select>
                            </section>

                            <section class="ord-fd-sec">
                                <h5>Fecha</h5>
                                <el-select v-model="dateRange" size="small" @change="applyDateFilters">
                                    <el-option
                                        v-for="opt in rangeOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    ></el-option>
                                </el-select>
                                <!-- Solo con «Personalizado»: si no, compite con
                                     el rango rapido y no se sabe cual manda. -->
                                <el-date-picker
                                    v-if="dateRange === 'custom'"
                                    v-model="invoiceDateRange"
                                    class="ord-fd-fechas"
                                    type="daterange"
                                    size="small"
                                    range-separator="a"
                                    start-placeholder="Desde"
                                    end-placeholder="Hasta"
                                    value-format="yyyy-MM-dd"
                                    :clearable="true"
                                    @change="applyDateFilters"
                                ></el-date-picker>
                                <label class="ord-fd-lbl">Qué fecha se mira</label>
                                <el-select v-model="dateType" size="small" @change="applyDateFilters">
                                    <el-option
                                        v-for="opt in dateTypeOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    ></el-option>
                                </el-select>
                            </section>

                            <section v-if="shipping" class="ord-fd-sec">
                                <h5>Entrega</h5>
                                <el-select v-model="deliveryTypeFilter" size="small" @change="applyLogisticFilters">
                                    <el-option
                                        v-for="opt in deliveryTypeOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    ></el-option>
                                </el-select>
                                <label class="ord-fd-lbl">Antigüedad</label>
                                <el-select v-model="agingFilter" size="small" @change="applyLogisticFilters">
                                    <el-option
                                        v-for="opt in agingOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    ></el-option>
                                </el-select>
                            </section>
                        </div>

                        <footer class="ord-fd-foot">
                            <button
                                class="ord-fd-clear"
                                :disabled="!filtrosActivos.length"
                                @click="clearFilters"
                            >
                                Limpiar filtros
                            </button>
                            <el-button size="small" type="primary" @click="showFiltersDrawer = false">
                                Ver resultados
                            </el-button>
                        </footer>
                    </div>
                </el-drawer>

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
                        <th v-if="columnas.cliente" class="ord-c-cli">Cliente</th>
                        <th v-if="columnas.cobro" class="text-end ord-c-pay">Cobro</th>
                        <th v-if="columnas.estado" class="ord-c-state">Estado</th>
                        <th v-if="columnas.envio" class="ord-c-ship">Envío</th>
                        <th v-if="columnas.docs" class="text-center ord-c-docs">Docs</th>
                        <th class="text-end ord-c-act">Acciones</th>
                    </tr>
                    <tr></tr>
                    <!-- El asomo va en la FILA, no en el chip: es como
                         funciona Envios, donde basta recorrer la tabla con el
                         mouse para ir viendo que lleva cada paquete. Obligar a
                         apuntar a un chip de 90 px convierte en punteria lo
                         que deberia ser un barrido. -->
                    <tr
                        slot-scope="{ index, row }"
                        :class="{ 'ord-peek-on': peek.id === row.id }"
                        @mouseenter="asomarPaquete(row, $event)"
                        @mouseleave="ocultarPaquete"
                    >
                        <td class="text-center">
                            <input
                                type="checkbox"
                                :value="row.id"
                                v-model="selectedIds"
                            />
                        </td>
                        <!-- Pedido: QUE pedido es. Numero, cuando entro y que
                             lleva dentro, en ese orden.

                             La caja va debajo del codigo, como parte de la
                             identificacion: este codigo es este pedido y esta
                             caja es su contenido. Deja de ser columna propia
                             —el contador cabe bajo el numero y ahorra una
                             columna en una tabla que ya iba justa— y el
                             contenido sigue asomandose al pasar por la fila.

                             El cliente sale de aqui: es otra pregunta y ahora
                             tiene su columna. -->
                        <td data-label="Pedido">
                            <div class="ord-o-top">
                                <!-- Texto, no boton. Abrir el pedido se hace
                                     desde «Ver», y solo desde ahi: habia
                                     CUATRO sitios haciendo lo mismo y ninguno
                                     lo hacia mejor que los otros. -->
                                <span class="ord-o-id">#{{ row.order_id }}</span>
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
                            <div class="ord-o-fecha" :title="row.created_at">
                                {{ fechaCorta(row.created_at) }}
                            </div>
                            <!-- La caja. No es un boton: su trabajo es MOSTRAR
                                 el contenido, y eso ya pasa al posarse en la
                                 fila. Un clic aqui abriria el mismo cajon que
                                 «Ver», que es la duplicacion que veniamos a
                                 quitar. -->
                            <span
                                class="ord-pk"
                                :class="{ 'is-vacio': !paquete(row).length }"
                                :title="tituloPaquete(row)"
                            >
                                <span v-if="miniaturas(row).length" class="ord-pk-ths">
                                    <img
                                        v-for="(m, k) in miniaturas(row)"
                                        :key="k"
                                        :src="m"
                                        alt=""
                                        @error="sinImagen"
                                    />
                                    <span v-if="restantes(row)" class="ord-pk-mas"
                                        >+{{ restantes(row) }}</span
                                    >
                                </span>
                                <span class="ord-pk-t">
                                    <span v-if="!miniaturas(row).length" class="ord-pk-e">&#128230;</span>
                                    {{ resumenProductos(row) }}
                                </span>
                            </span>
                        </td>

                        <!-- Cliente: QUIEN lo hizo. Nombre, documento y
                             telefono — los tres datos con los que el operador
                             identifica a quien llama y decide a nombre de quien
                             sale la boleta. Estaban apretados bajo el codigo
                             del pedido, que responde otra pregunta. -->
                        <td v-if="columnas.cliente" data-label="Cliente">
                            <div class="ord-o-cli" :title="clienteTitulo(row)">
                                {{ row.customer }}
                            </div>
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
                            </div>
                            <div v-if="row.customer_telefono" class="ord-o-tel">
                                {{ row.customer_telefono }}
                            </div>
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
                        <td v-if="columnas.cobro" class="text-end" data-label="Cobro">
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
                        <!-- Estado: EN QUE ETAPA esta el pedido. Nada mas.

                             Aqui vivian ademas la modalidad de entrega, el
                             destino, el estado del envio, el semaforo de
                             antiguedad y el aviso de datos que faltan. Seis
                             hechos de dos dimensiones distintas en una celda:
                             era la columna que hacia ilegible la fila.

                             Todo eso pasa a «Envio», que responde otra
                             pregunta —como y donde se entrega—. No se pierde
                             ni un dato: cambia de columna. -->
                        <td v-if="columnas.estado" data-label="Estado">
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

                        <!-- Envio: COMO y DONDE se entrega.

                             Tres renglones y ninguno repite lo de al lado: la
                             modalidad con su color, a donde va, y en que punto
                             del traslado esta. El semaforo de antiguedad y el
                             aviso de datos que faltan vienen con el envio
                             porque son suyos, no del pedido. -->
                        <td v-if="columnas.envio" data-label="Envío">
                            <template v-if="row.shipment">
                                <div class="ord-sh-l1">
                                    <span
                                        v-if="modalidadEnvio(row)"
                                        class="ord-st-mod"
                                        :style="{
                                            color: modalidadEnvio(row).color,
                                            background: modalidadEnvio(row).fondo,
                                            borderColor: modalidadEnvio(row).linea,
                                        }"
                                        :title="modalidadEnvio(row).titulo"
                                        >{{ modalidadEnvio(row).texto }}</span
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
                                <div
                                    v-if="destinoEnvio(row)"
                                    class="ord-st-dest"
                                    :title="destinoEnvio(row)"
                                >
                                    {{ destinoEnvio(row) }}
                                </div>
                                <div class="ord-sh-est" :title="envioTitulo(row)">
                                    {{ row.shipment.status_label }}
                                </div>
                            </template>

                            <!-- Un envio anulado NO es lo mismo que no tener
                                 envio, y decir «sin envio» seria falso. -->
                            <span
                                v-else-if="row.shipment_cancelled"
                                class="ord-st-chip is-void"
                                :title="'El envío ' + row.shipment_cancelled.code + ' fue anulado. Puedes restaurarlo desde el menú.'"
                                >Envío anulado</span
                            >
                            <span v-else class="ord-st-chip is-none">Sin envío</span>
                        </td>
                        <!-- Documentos: QUE SE IMPRIMIO.

                             Antes esta celda pintaba un chip por cada tipo que
                             el pedido PODIA emitir. Medido sobre 75 pedidos
                             reales de tres tenants: 98 chips pintados y CERO
                             documentos emitidos. La columna hablaba de
                             posibilidades, no de hechos.

                             Ahora solo sale lo que existe de verdad, y con la
                             distincion que faltaba: un contorno es «emitido» y
                             el punto es «ya se imprimio». Emitir y imprimir son
                             dos cosas: un comprobante que nunca fue a la
                             impresora no esta en la caja del paquete.

                             Entran ademas el rotulo y la guia de la agencia,
                             que son los que MAS se imprimen —24 de 25 filas en
                             importacionesdeywa— y vivian escondidos dentro del
                             envio, en una columna que no era esta.

                             Cuando no hay nada: una raya. Lo que corresponde
                             emitir se dice en su titulo y en el panel, que
                             sigue a un clic. -->
                        <td v-if="columnas.docs" class="text-center" data-label="Docs">
                            <div
                                class="ord-doc-chips"
                                role="button"
                                tabindex="0"
                                title="Ver los documentos del pedido"
                                @click="abrirDocumentos(row)"
                                @keyup.enter="abrirDocumentos(row)"
                            >
                                <span
                                    v-for="s in docsReales(row)"
                                    :key="s.tipo"
                                    class="ord-doc-chip"
                                    :class="['is-' + docTone(s), { 'is-impreso': s.impreso }]"
                                    :title="docTitle(s)"
                                    >{{ s.chip
                                    }}<i v-if="s.impreso" class="ord-doc-dot"></i
                                ></span>

                                <span
                                    v-if="!docsReales(row).length"
                                    class="ord-doc-nada"
                                    :title="tituloSinDocs(row)"
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
            @changed="onVerificationChanged"
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

        <!-- ── El asomo del paquete ──────────────────────────────────────
             Una sola tarjeta para toda la tabla, no una por fila: pintar 20
             popovers ocultos es DOM que nadie mira.

             Se cuelga del <body> en `mounted`. Dentro de `.table-responsive`
             quedaria recortada por el overflow — es el mismo motivo por el
             que Envios la cuelga alli, y por el que el-dialog trae
             `append-to-body`. Vue la sigue gobernando: solo cambia el padre.
        -->
        <div
            ref="peekCard"
            class="ord-peek"
            :class="{ 'is-on': peek.on }"
            :style="{ left: peek.x + 'px', top: peek.y + 'px' }"
            role="tooltip"
        >
            <div class="ord-pk-h">
                <span class="ord-pk-h-t">Contenido del pedido</span>
                <span class="ord-pk-h-m">{{ peek.meta }}</span>
            </div>
            <ol class="ord-pk-l">
                <li v-for="(l, k) in peek.lineas" :key="k">
                    <img v-if="l.img" class="ord-pk-th" :src="l.img" alt="" @error="sinImagen" />
                    <span class="ord-pk-n">{{ l.nombre }}</span>
                    <span v-if="l.cant" class="ord-pk-c">&times;{{ entero(l.cant) }}</span>
                </li>
            </ol>
            <!-- El detalle de un encargo es texto que escribio el almacen: no
                 lleva precio, no reserva stock y no se puede facturar. -->
            <p v-if="peek.origen === 'envio'" class="ord-pk-f">
                Es el detalle del envío, no líneas de venta.
            </p>
        </div>
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
/* Impreso. El punto es deliberadamente pequeno: la columna tiene que poder
   leerse de un vistazo en veinte filas, y un segundo chip por cada documento
   la doblaria de ancho. Contorno = emitido; contorno con punto = ya salio a
   la impresora. */
.ord-doc-chip {
    position: relative;
}
.ord-doc-chip.is-impreso {
    padding-right: 11px;
}
.ord-doc-dot {
    position: absolute;
    top: 3px;
    right: 3px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: currentColor;
}
.ord-doc-nada {
    color: #cbd5e1;
    font-weight: 700;
}
/* ══════════════════════════════════════════════════════════════════
   La fila: siete columnas, una pregunta cada una
   ══════════════════════════════════════════════════════════════════
   Que pedido es · quien lo hizo · si pago · en que etapa esta · como se
   entrega · que se imprimio · que puedo hacer.

   Eran seis y `Estado` cargaba con dos preguntas: la etapa del pedido Y
   todo el envio. Sale una columna nueva y entra otra —«Productos» deja
   de serlo, porque el contador cabe bajo el codigo— asi que el reparto
   crece en uno, no en dos.

   Porcentajes y no fijos: la tabla se reparte el espacio que haya en vez
   de exigir un minimo que en una laptop de 1366 no cabia. */
.orders th.ord-c-order { width: 19%; }
.orders th.ord-c-cli   { width: 18%; }
.orders th.ord-c-pay   { width: 13%; }
.orders th.ord-c-state { width: 16%; }
.orders th.ord-c-ship  { width: 17%; }
.orders th.ord-c-docs  { width: 9%;  }
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
/* Ya no es un boton: abrir el pedido se hace desde «Ver», y solo desde ahi.
   Habia cuatro caminos al mismo cajon —el codigo, la caja, el popover de
   productos y «Ver»— y ninguno hacia nada que los otros no hicieran. */
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

/* ── Productos: el pedido como un paquete ───────────────── */
/* Mismo lenguaje que el chip de Envios: caja, cuenta y borde tenue. Alla
   esta en Blade y aca en Vue, asi que se comparte el aspecto y no el
   codigo; lo que NO se comparte es el posicionamiento, que alla son 170
   lineas a mano y aqui lo resuelve Popper. */
/* Es un `<span>`, no un `<button>`: su trabajo es MOSTRAR el contenido, y
   eso ya pasa al posarse en la fila. `default` y no `pointer` — un cursor
   de mano prometeria un clic que no lleva a ninguna parte. */
.ord-pk {
    margin-top: 3px;
    cursor: default;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 3px 8px 3px 4px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font: inherit;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
    transition: background 0.14s ease-out, border-color 0.14s ease-out;
}
.ord-pk.is-vacio {
    color: #94a3b8;
    font-weight: 500;
}
.ord-pk-t {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    padding-left: 4px;
}
.ord-pk-e {
    font-size: 12px;
    line-height: 1;
}
.ord-pk-ths {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
}
/* Superpuestas: tres miniaturas en fila harian crecer la columna, y la
   pila se lee igual de bien a este tamano. */
.ord-pk-ths img {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    border: 1.5px solid #fff;
    box-shadow: 0 0 0 1px #e2e8f0;
}
.ord-pk-ths img + img {
    margin-left: -7px;
}
.ord-pk-mas {
    margin-left: -5px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    border: 1.5px solid #fff;
    font-size: 9.5px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-variant-numeric: tabular-nums;
}

/* El asomo. Cabecera con tinte, lista numerada: al embalar se cuentan
   items, y un numero se sigue mejor que un punto. Es el mismo patron de
   `.sh-peek` en Envios. */
.ord-pk-h {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    margin: -12px -12px 8px;
    padding: 8px 12px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
}
.ord-pk-h-t {
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #475569;
}
.ord-pk-h-m {
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}
.ord-pk-l {
    list-style: none;
    margin: 0;
    padding: 0;
    counter-reset: pk;
    max-height: 260px;
    overflow-y: auto;
}
.ord-pk-l li {
    counter-increment: pk;
    position: relative;
    display: flex;
    align-items: center;
    gap: 7px;
    padding-left: 22px;
    margin-bottom: 5px;
    font-size: 12.5px;
    line-height: 1.35;
    color: #1e293b;
}
.ord-pk-l li:last-child {
    margin-bottom: 0;
}
.ord-pk-l li::before {
    content: counter(pk);
    position: absolute;
    left: 0;
    top: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    font-size: 9.5px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ord-pk-th {
    width: 26px;
    height: 26px;
    border-radius: 5px;
    object-fit: cover;
    flex: 0 0 auto;
    background: #f1f5f9;
}
.ord-pk-n {
    flex: 1;
    min-width: 0;
    overflow-wrap: anywhere;
}
.ord-pk-c {
    flex: 0 0 auto;
    font-weight: 800;
    color: #475569;
    font-variant-numeric: tabular-nums;
}
.ord-pk-f {
    margin: 8px 0 0;
    padding-top: 7px;
    border-top: 1px solid #eef2f7;
    font-size: 11px;
    color: #94a3b8;
    line-height: 1.4;
}

/* ── La tarjeta del asomo ───────────────────────────────────────
   Vive colgada del <body>. Esta hoja NO es `scoped` —justo por lo que se
   pinta fuera del componente: el cajon, los dialogos y ahora esto— y por eso
   reutiliza tal cual el `.ord-pk-*` de arriba. Si algun dia se le pone
   `scoped`, esta tarjeta se queda sin estilos y nadie sabra por que. */
.ord-peek {
    position: fixed;
    z-index: 2010;
    width: 320px;
    padding: 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 20px 48px -16px rgba(15, 23, 42, 0.4);
    color: #1e293b;
    /* No captura el mouse: si lo hiciera, al pasar por encima la fila
       recibiria `mouseleave` y la tarjeta se cerraria sola. */
    pointer-events: none;
    opacity: 0;
    visibility: hidden;
    transform: translateX(-6px);
    transition: opacity 0.12s ease-out, transform 0.12s ease-out;
}
.ord-peek.is-on {
    opacity: 1;
    visibility: visible;
    transform: none;
}
/* La fila que se esta asomando se resalta: liga la tarjeta con su origen. */
.orders tr.ord-peek-on > td {
    background: #f8fafc;
}

/* Aviso del boton de Envios. Un punto y no un numero: el detalle esta en el
   titulo y dentro del menu, y en la cabecera lo que hace falta es saber si hay
   algo que mirar, no cuanto. */
.ord-head-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #dc2626;
    margin-left: 5px;
    vertical-align: 3px;
}
.ord-head-badge {
    margin-left: 8px;
    font-size: 10.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
}
.ord-head-badge.is-bad {
    background: #fee2e2;
    color: #b91c1c;
}

/* ── Envio: como y donde se entrega ─────────────────────────────── */
.ord-sh-l1 {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}
/* El estado del traslado. Va en gris y en tercer renglon a proposito: la
   etapa del PEDIDO ya la dice su propia columna, y esta es la del paquete.
   Dos estados a la misma altura se leerian como uno contradiciendo al otro. */
.ord-sh-est {
    color: #64748b;
    font-size: 11px;
    margin-top: 2px;
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
.ord-st-dest-w {
    display: flex;
    align-items: center;
    gap: 5px;
    min-width: 0;
    margin-top: 2px;
}
/* Los colores los pone el modelo por estilo en linea: son los mismos tres
   de Envios y tenerlos aqui repetidos es como acaban divergiendo. */
.ord-st-mod {
    flex: 0 0 auto;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.02em;
    padding: 1px 6px;
    border: 1px solid;
    border-radius: 999px;
    white-space: nowrap;
}
.ord-st-dest {
    color: #94a3b8;
    font-size: 11px;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ord-st-warn {
    color: #b45309;
    font-size: 11px;
    flex: 0 0 auto;
}

/* ── Prioridad y vencidos ────────────────────────────────────────── */
.ord-overdue {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 9px 14px;
    margin-bottom: 10px;
    color: #991b1b;
    font-size: 13px;
}
.ord-overdue i {
    margin-right: 6px;
}
.ord-overdue-cta {
    border: 1px solid #fca5a5;
    background: #fff;
    color: #b91c1c;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 11px;
    cursor: pointer;
    white-space: nowrap;
}
.ord-overdue-cta:hover {
    background: #fee2e2;
}
.ord-prio {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.ord-prio-lbl {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #94a3b8;
}
.ord-prio-btn {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    cursor: pointer;
    white-space: nowrap;
}
.ord-prio-btn b {
    margin-left: 4px;
    font-variant-numeric: tabular-nums;
}
.ord-prio-btn:hover {
    border-color: #94a3b8;
}
.ord-prio-btn.is-warn { color: #92400e; border-color: #fde68a; background: #fffbeb; }
.ord-prio-btn.is-bad  { color: #b91c1c; border-color: #fecaca; background: #fef2f2; }
.ord-prio-btn.is-on {
    border-color: #4f46e5;
    background: #eef2ff;
    color: #4338ca;
}
.ord-prio-clear {
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 12px;
    cursor: pointer;
    text-decoration: underline;
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
/* ── Chips de lo que se esta filtrando ───────────────────────────── */
.ord-fchips {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin: -4px 0 12px;
}
.ord-fchip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #eef2ff;
    color: #3730a3;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 6px 3px 11px;
    max-width: 260px;
}
.ord-fchip button {
    border: 0;
    background: rgba(55, 48, 163, 0.12);
    color: #3730a3;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    line-height: 15px;
    font-size: 13px;
    cursor: pointer;
    padding: 0;
    flex: 0 0 auto;
}
.ord-fchip button:hover {
    background: #3730a3;
    color: #fff;
}
.ord-fchips-clear {
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 11.5px;
    cursor: pointer;
    text-decoration: underline;
}

/* ── Cajon de filtros ────────────────────────────────────────────── */
.ord-fd {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.ord-fd-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 18px;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    color: #0f172a;
    flex: 0 0 auto;
}
.ord-fd-x {
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 18px;
    cursor: pointer;
    line-height: 1;
}
.ord-fd-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 4px 18px 18px;
}
.ord-fd-sec {
    padding: 14px 0;
    border-bottom: 1px solid #eef2f7;
}
.ord-fd-sec:last-child {
    border-bottom: 0;
}
.ord-fd-sec h5 {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
    margin: 0 0 8px;
}
.ord-fd-sec .el-select,
.ord-fd-fechas {
    width: 100% !important;
}
.ord-fd-lbl {
    display: block;
    font-size: 11px;
    color: #94a3b8;
    margin: 10px 0 4px;
}
.ord-fd-fechas {
    margin-top: 8px;
}
.ord-fd-foot {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 13px 18px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}
.ord-fd-clear {
    border: 0;
    background: transparent;
    color: #475569;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
}
.ord-fd-clear:disabled {
    color: #cbd5e1;
    cursor: default;
    text-decoration: none;
}

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

/* Cada control lleva su etiqueta: sin ella, cuatro desplegables seguidos no
   dicen qué filtran y la barra se lee como piezas sueltas. */

@media (max-width: 640px) {
}
/* KPIs */
.ord-kpis {
    display: grid;
    /* La quinta columna es `auto`: el boton ocupa lo que mide y los cuatro
       indicadores siguen repartiendose el resto. Con repeat(4,1fr) a secas
       caeria a una segunda fila el solo. */
    grid-template-columns: repeat(4, 1fr) auto;
    gap: 10px;
    margin-bottom: 14px;
    align-items: stretch;
}
.ord-kpis .ord-new-btn {
    align-self: stretch;
    padding-left: 18px;
    padding-right: 18px;
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
/* Cabecera: lo que no se toca en cada consulta. */
.ord-head-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ord-head-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 13px;
    cursor: pointer;
    white-space: nowrap;
}
.ord-head-btn:hover {
    border-color: #94a3b8;
    color: #1e293b;
}

/* Columnas, pegado al borde derecho de la franja de prioridad. */
.ord-prio-cols {
    margin-left: auto;
}
.ord-cols-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    cursor: pointer;
    white-space: nowrap;
}
.ord-cols-btn:hover {
    border-color: #94a3b8;
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
    .orders th.ord-c-docs,
    .orders td[data-label="Docs"],
    .orders th.ord-c-cli,
    .orders td[data-label="Cliente"] {
        display: none;
    }
    /* El cliente se va porque su nombre ya esta en el tooltip del pedido y
       en el cajon. Docs, porque el panel completo esta a un clic. Envio se
       queda: en una tableta de almacen es la pregunta del dia. */
    .orders th.ord-c-order { width: 30%; }
    .orders th.ord-c-pay   { width: 17%; }
    .orders th.ord-c-state { width: 21%; }
    .orders th.ord-c-ship  { width: 20%; }
    .orders th.ord-c-act   { width: 12%; }
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
    /* Dos indicadores por fila y el boton cruzando las dos columnas: en una
       celda `auto` de un grid de 2 quedaria un boton de medio ancho. */
    .orders .ord-kpis .ord-new-btn {
        grid-column: 1 / -1;
        padding-top: 10px;
        padding-bottom: 10px;
    }
    /* La cabecera es estrecha: los dos botones se reparten la linea. */
    .ord-head-actions {
        width: 100%;
        margin-top: 8px;
    }
    .ord-head-actions .el-dropdown,
    .ord-head-actions .ord-head-btn {
        flex: 1 1 0;
    }
    .ord-head-actions .ord-head-btn {
        width: 100%;
        justify-content: center;
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
    .ord-sort,
    .ord-bar-more {
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
            "chk cli    cobro"
            "est est    est"
            "shp shp    shp"
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
    .orders td[data-label="Cliente"]   { grid-area: cli; min-width: 0; }
    .orders td[data-label="Cobro"]     { grid-area: cobro; text-align: right; }
    .orders td[data-label="Estado"]    { grid-area: est; margin-top: 7px; }
    .orders td[data-label="Envío"]     { grid-area: shp; margin-top: 5px; }
    .orders td[data-label="Docs"]      { grid-area: doc; margin-top: 8px; }
    .orders td[data-label="Acciones"] { grid-area: act; margin-top: 8px; text-align: right; }

    /* En la tarjeta el envio se lee en una sola linea: modalidad, destino y
       estado seguidos. En columna gastaria tres renglones para tres datos
       cortos. */
    .orders td[data-label="Envío"] {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .orders td[data-label="Envío"] .ord-sh-l1 { display: contents; }
    .orders td[data-label="Envío"] .ord-st-dest { max-width: 55%; }

    /* En la tarjeta, el estado y su «Cambiar» caben en la misma linea. */
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
    .orders .ord-p-medio {
        max-width: 100%;
    }

    /* El detalle a pantalla completa: 480px en un telefono deja una franja
       de fondo inutil a un lado. El `!important` es necesario porque
       el-drawer fija el ancho en linea, y una regla de hoja de estilos con
       !important si gana a un style inline sin el. Va en el bloque NO
       scoped del padre: el cajon se pinta fuera del ambito del componente. */
    .od-drawer,
    .ord-fdrawer {
        width: 100% !important;
    }

    /* El ancho lo fija Element UI en linea, asi que solo un max-width
       puede encogerlo por debajo de los 320 px que pide. */
    .ord-pk-pop {
        max-width: calc(100vw - 24px) !important;
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
        /**
         * Los dos avisos que vivían en el menú lateral: paquetes sin guía y
         * lotes de impresión abiertos.
         *
         * Al retirarse «Registro de Envíos» del menú se habrían perdido, y son
         * señal real: un paquete sin guía es uno que la agencia ya tiene y del
         * que no se puede dar razón al cliente.
         */
        shipAlerts: { type: Object, default: () => ({ sin_guia: 0, lotes: 0 }) },
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
            showFiltersDrawer: false,
            // Que columnas ve el operador. «Pedido» y «Acciones» no se pueden
            // apagar: sin la primera no se sabe que fila es y sin la segunda no
            // se puede hacer nada con ella.
            columnasOpcionales: [
                { key: "cliente", label: "Cliente" },
                { key: "cobro", label: "Cobro" },
                { key: "estado", label: "Estado" },
                { key: "envio", label: "Envío" },
                { key: "docs", label: "Documentos" },
            ],
            // «Productos» ya no es columna: la caja vive bajo el codigo del
            // pedido. Un tenant que la tuviera apagada de antes no pierde
            // nada — `cargarColumnas` solo lee las claves que existen hoy.
            columnas: {
                cliente: true,
                cobro: true,
                estado: true,
                envio: true,
                docs: true,
            },
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
            paymentsTitle: "Pagos del pedido",

            /** El asomo: una sola tarjeta para toda la tabla. */
            peek: { on: false, id: null, x: 0, y: 0, lineas: [], meta: "", origen: "" },
        };
    },
    mounted() {
        // Al <body>: dentro de `.table-responsive` el overflow la recortaria.
        // Es el mismo motivo por el que Envios la cuelga alli y por el que
        // el-dialog trae `append-to-body`.
        if (this.$refs.peekCard) document.body.appendChild(this.$refs.peekCard);

        // Al desplazar quedaria flotando donde estaba, apuntando a una fila
        // que ya no esta ahi. En captura: el scroll puede ser del contenedor
        // de la tabla y no de la ventana, y ese no burbujea.
        this.cerrarAsomo = () => {
            clearTimeout(this.peekTimer);
            this.peek.on = false;
            this.peek.id = null;
        };
        window.addEventListener("scroll", this.cerrarAsomo, true);
        window.addEventListener("resize", this.cerrarAsomo);
    },
    beforeDestroy() {
        // Vue ya no la limpia: la saco yo del arbol al que la mude.
        const c = this.$refs.peekCard;
        if (c && c.parentNode) c.parentNode.removeChild(c);
        clearTimeout(this.peekTimer);
        window.removeEventListener("scroll", this.cerrarAsomo, true);
        window.removeEventListener("resize", this.cerrarAsomo);
    },
    async created() {
        this.cargarColumnas();
        this.$http.get(`/statusOrder/records`).then(response => {
            this.options = response.data;
        });
        this.loadChipCounts();
        this.loadStats();
        this.events();
    },
    computed: {
        /**
         * Lo que se esta filtrando ahora mismo, para pintarlo como chips.
         *
         * Cada uno lleva la etiqueta que el operador reconoce —no el valor
         * interno— y la clave con la que se quita. La busqueda entra tambien:
         * es el filtro que mas se olvida puesto.
         */
        /**
         * Que hay pendiente detras del boton de Envios.
         *
         * Devuelve el texto o cadena vacia: se usa a la vez como condicion del
         * punto y como titulo, para que no haya un punto sin explicacion.
         */
        avisoEnvios() {
            const a = this.shipAlerts || {};
            const partes = [];

            if (a.sin_guia) partes.push(a.sin_guia + " paquete(s) sin guía");
            if (a.lotes) partes.push(a.lotes + " lote(s) de impresión abierto(s)");

            return partes.join(" · ");
        },

        filtrosActivos() {
            const et = (lista, v) => (lista.find(o => o.value === v) || {}).label || v;
            const chips = [];

            if (this.q) chips.push({ key: "q", label: '«' + this.q + '»' });

            if (this.estadoPago) {
                const pagos = {
                    pendiente: "Pago pendiente",
                    parcial: "Pago parcial",
                    pagado: "Pagado",
                    canal: "Cobrado por el canal",
                };
                chips.push({ key: "estadoPago", label: pagos[this.estadoPago] });
            }

            if (this.orderSource !== "all") {
                chips.push({
                    key: "orderSource",
                    label: this.orderSource === "saga" ? "Solo Saga" : "Sin Saga",
                });
            }

            if (this.dateRange) chips.push({ key: "dateRange", label: et(this.rangeOptions, this.dateRange) });
            if (this.dateType !== "order") chips.push({ key: "dateType", label: et(this.dateTypeOptions, this.dateType) });
            if (this.deliveryTypeFilter) chips.push({ key: "deliveryTypeFilter", label: et(this.deliveryTypeOptions, this.deliveryTypeFilter) });
            if (this.agingFilter) chips.push({ key: "agingFilter", label: et(this.agingOptions, this.agingFilter) });

            return chips;
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
         * Color del chip. Cuatro tonos, no siete: el operador necesita saber
         * si algo esta hecho, en curso, mal o pendiente — el detalle exacto lo
         * lee al abrir el panel.
         */
        /**
         * Los documentos que EXISTEN, no los que se podrian emitir.
         *
         * Medido sobre 75 pedidos reales: 98 chips pintados y cero documentos
         * emitidos. La columna hablaba de posibilidades y por eso no servia
         * para saber que falta despachar.
         *
         * Se suman el rotulo y la guia de la agencia, que vienen del ENVIO y
         * no de `OrderDocuments` porque son suyos, con su propio registro de
         * impresion. En la columna van juntos: para el operador que prepara un
         * paquete, todo lo que se imprime es un documento del pedido.
         */
        docsReales(row) {
            const d = row.documents || {};

            const emitidos = ["nota_venta", "boleta", "factura", "guia"]
                .map(k => d[k])
                .filter(x => x && x.existe);

            return emitidos.concat(row.prints || []);
        },

        /**
         * Que decir cuando no hay nada impreso.
         *
         * La raya sola no explica si es que falta emitir algo o si este pedido
         * no genera documentos. Lo pendiente se cuenta AQUI y no como un chip
         * apagado en la fila: la columna es de hechos, no de recordatorios.
         */
        tituloSinDocs(row) {
            const d = row.documents || {};
            const sugerido = ["boleta", "factura", "nota_venta"]
                .map(k => d[k])
                .find(x => x && !x.existe && x.sugerido);

            if (sugerido) {
                return "Todavía no se ha impreso nada. Corresponde emitir: " + sugerido.nombre + ".";
            }

            return "Todavía no se ha impreso nada de este pedido.";
        },

        docTone(s) {
            // El rotulo y la guia no pasan por SUNAT: no tienen `estado` ni
            // `existe`, y su unico hecho relevante es que se imprimieron.
            if (s.tipo === "rotulo" || s.tipo === "guia_agencia") return "ok";

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
            if (s.tipo === "rotulo") {
                // Una reimpresion no es un detalle: significa que la primera
                // etiqueta se perdio o iba mal, y exige motivo.
                const veces = s.veces > 1 ? " · impreso " + s.veces + " veces" : " · impreso";

                return s.nombre + veces + (s.fecha ? " el " + s.fecha : "");
            }

            if (s.tipo === "guia_agencia") {
                return s.nombre + " · cargada. El paquete se entregó al transportista.";
            }

            if (s.existe) {
                const impresion = s.impreso
                    ? " · impreso" + (s.veces > 1 ? " " + s.veces + " veces" : "") +
                      (s.impreso_at ? " el " + s.impreso_at : "")
                    : " · sin imprimir";

                return s.nombre + " " + s.numero + " · " + s.estado_label + impresion;
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

        /**
         * Asomar el contenido al recorrer la tabla con el mouse.
         *
         * Mismo comportamiento que Envios y, sobre todo, mismo motivo: el
         * operador barre la lista buscando un pedido y necesita ir viendo que
         * lleva cada uno. Si hay que apuntar a un chip, deja de ser un barrido.
         *
         * El posicionamiento tambien copia el orden de preferencia de alla
         * —derecha, izquierda, debajo— con un limite: NUNCA invadir el menu
         * lateral. Lo que no se copia son las 170 lineas del partial: aquello
         * es JS suelto sobre Blade y esto vive dentro del componente.
         */
        asomarPaquete(row, ev) {
            clearTimeout(this.peekTimer);

            const lineas = this.paquete(row);

            // Sin contenido no hay nada que asomar, y una tarjeta vacia
            // siguiendo al mouse solo estorba.
            if (!lineas.length) return this.ocultarPaquete();

            this.peek.lineas = lineas;
            this.peek.meta   = this.metaPaquete(row);
            this.peek.origen = this.paqueteOrigen(row);
            this.peek.id     = row.id;
            this.peek.on     = true;

            const tr = ev.currentTarget;

            // Tras pintar: el alto real solo se conoce con el contenido puesto.
            this.$nextTick(() => this.colocarPaquete(tr));
        },

        /** Coloca la tarjeta junto a la fila, sin pisar el menu ni salirse. */
        colocarPaquete(tr) {
            const el = this.$refs.peekCard;
            if (!el || !tr || !tr.isConnected) return;

            const GAP = 12;
            const EDGE = 10;
            const r = tr.getBoundingClientRect();
            const cw = el.offsetWidth;
            const ch = el.offsetHeight;
            const vw = window.innerWidth;
            const vh = window.innerHeight;

            // Borde izquierdo permitido: el area de contenido, no el viewport.
            // Sin este limite la tarjeta acaba montada sobre la navegacion.
            const panel = document.querySelector(".orders") || document.querySelector(".table-responsive");
            const minL = panel ? Math.max(EDGE, panel.getBoundingClientRect().left) : EDGE;

            let left;
            let top;
            let lado = null;

            if (r.right + GAP + cw <= vw - EDGE) {
                left = r.right + GAP;
                lado = "r";
            } else if (r.left - GAP - cw >= minL) {
                left = r.left - GAP - cw;
                lado = "l";
            } else {
                left = Math.min(Math.max(minL, r.left), vw - cw - EDGE);
            }

            if (lado) {
                top = r.top + r.height / 2 - ch / 2;
            } else {
                top = r.bottom + GAP + ch <= vh - EDGE ? r.bottom + GAP : r.top - GAP - ch;
            }

            this.peek.x = Math.round(left);
            this.peek.y = Math.round(Math.min(Math.max(EDGE, top), Math.max(EDGE, vh - ch - EDGE)));
        },

        /**
         * Esconder, con un respiro.
         *
         * `mouseleave` salta tambien al cruzar de una fila a la siguiente: sin
         * el retardo, la tarjeta parpadearia en cada salto.
         */
        ocultarPaquete() {
            clearTimeout(this.peekTimer);
            this.peekTimer = setTimeout(() => {
                this.peek.on = false;
                this.peek.id = null;
            }, 90);
        },

        /**
         * El contenido del pedido, como una lista con UNA forma.
         *
         * De donde sale, por orden:
         *
         *   1. `paquete` — lo que normalizo el servidor a partir de `items`.
         *      Son lineas de venta: llevan cantidad, precio y a veces foto.
         *   2. El detalle del ENVIO, y solo si no hay lineas propias. El
         *      espejo de un encargo nace con `items` vacio porque el envio
         *      guarda su contenido como TEXTO libre, escrito por el almacen.
         *
         * Un pedido con lineas de venta manda sobre el envio: si tiene
         * productos del catalogo, esos son los productos.
         */
        paquete(row) {
            const lineas = row.paquete || [];
            if (lineas.length) return lineas;

            // Texto libre: sin cantidad y sin precio, porque no los tiene.
            // Inventar un «x1» aqui seria afirmar algo que nadie escribio.
            return ((row.shipment && row.shipment.content_lines) || []).map(t => ({
                nombre: t,
                cant: null,
                img: null,
            }));
        },

        /** `venta`, `envio` o `vacio`. Decide el vocabulario de la celda. */
        paqueteOrigen(row) {
            if ((row.paquete || []).length) return "venta";
            if (((row.shipment && row.shipment.content_lines) || []).length) return "envio";

            return "vacio";
        },

        /** Unidades, no lineas: 3 productos pueden ser 7 cosas en la caja. */
        unidadesPaquete(row) {
            return (row.paquete || []).reduce((t, l) => t + (Number(l.cant) || 0), 0);
        },

        /**
         * Lo que dice el chip.
         *
         * «items» y no «productos» cuando el detalle sale del envio: es texto
         * libre y no una linea del catalogo. Llamarlo igual haria creer que se
         * puede facturar, y no se puede.
         */
        resumenProductos(row) {
            const n = this.paquete(row).length;

            if (!n) return "Sin detalle";

            if (this.paqueteOrigen(row) === "envio") {
                return n + (n === 1 ? " ítem" : " ítems");
            }

            return n + (n === 1 ? " producto" : " productos");
        },

        /** La cabecera del asomo: lineas y, si difieren, unidades. */
        metaPaquete(row) {
            const n = this.paquete(row).length;
            const u = this.unidadesPaquete(row);

            // Con una unidad por linea el total no aporta nada y solo compite
            // con el numero que ya se leyo en la fila.
            if (this.paqueteOrigen(row) !== "venta" || u === n) return this.resumenProductos(row);

            return this.resumenProductos(row) + " · " + this.entero(u) + " unidades";
        },

        /** Hasta tres miniaturas. Mas no caben sin hacer crecer la fila. */
        miniaturas(row) {
            return this.paquete(row)
                .filter(l => l.img)
                .slice(0, 3)
                .map(l => l.img);
        },

        /** Cuantas quedan fuera de las miniaturas. */
        restantes(row) {
            return Math.max(0, this.paquete(row).length - this.miniaturas(row).length);
        },

        /** El titulo nativo: por que esta vacio, o que hay dentro. */
        tituloPaquete(row) {
            const o = this.paqueteOrigen(row);

            if (o === "vacio") {
                // Los 120 pedidos «vacios» de alasitas son espejos de encargos
                // a los que nadie les escribio el contenido. No es un fallo del
                // pedido, y el operador tiene que saber donde se arregla.
                return row.shipment
                    ? "El envío no tiene el contenido cargado. Se escribe al editar el envío."
                    : "Este pedido no tiene productos cargados.";
            }

            return this.metaPaquete(row) + " — clic para ver el detalle";
        },

        /** Sin decimales cuando son enteros: «x2», no «x2.00». */
        entero(v) {
            const n = Number(v) || 0;

            return Number.isInteger(n) ? String(n) : n.toFixed(2);
        },

        /**
         * Una miniatura que no carga se esconde.
         *
         * El nombre del archivo viaja dentro del JSON del pedido y puede
         * apuntar a una imagen que ya se borro del catalogo. El hueco roto del
         * navegador es peor que no poner nada.
         */
        sinImagen(ev) {
            ev.target.style.display = "none";
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
         * A donde va el paquete, segun la modalidad.
         *
         * Antes esta celda pintaba «agencia · ciudad» y nada mas, asi que un
         * envio a domicilio y un recojo en tienda —que no tienen agencia— se
         * quedaban con la ciudad suelta o con la direccion cruda, sin que
         * nada dijera de que modalidad se trataba.
         *
         * Es la MISMA regla que usa Envios en su columna de destino:
         *
         *   agencia    → el nombre de la agencia, y la ciudad detras
         *   domicilio  → la direccion de entrega
         *   tienda     → nada: el cliente pasa a recogerlo
         *
         * La modalidad no se deduce del texto: viaja aparte, en el chip.
         */
        destinoEnvio(row) {
            const s = row.shipment || {};

            if (s.delivery_type === "tienda") return "";

            if (s.delivery_type === "domicilio") {
                return s.address || s.destination_city || "";
            }

            const partes = [s.agency, s.destination_city].filter(Boolean);

            if (partes.length) return partes.join(" · ");

            return s.destination && s.destination !== "—" ? s.destination : "";
        },

        /**
         * La modalidad, con las palabras y los colores de Envios.
         *
         * `delivery_mode` («Agencia», «Domicilio», «Recojo en tienda») y
         * `delivery_meta` salen del MODELO. No se inventan aqui: dos pantallas
         * llamando distinto al mismo hecho es como el operador termina
         * creyendo que son cosas distintas.
         *
         * Y es `delivery_mode`, no `delivery_short`: el corto es geografico
         * —«Lima»— y aqui el chip queda pegado a la direccion de entrega, con
         * lo que se leen como dos destinos que no coinciden.
         */
        modalidadEnvio(row) {
            const s = row.shipment || {};

            if (!s.delivery_type) return null;

            const meta = s.delivery_meta || {};

            return {
                texto: s.delivery_mode || s.delivery_short || "",
                titulo: s.delivery_label || "",
                color: meta.color || "#475569",
                fondo: meta.bg || "#f1f5f9",
                linea: meta.line || "#e2e8f0",
            };
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
        /**
         * Un cobro cambio. Se refresca ESA fila, no la tabla.
         *
         * Antes esto llamaba a `refreshAfterPayment()`, que dispara tres
         * peticiones —filas, chips y KPI— y devuelve la tabla a la primera
         * pagina perdiendo el scroll. Registrar el cobro de un pedido no tiene
         * por que mover los otros diecinueve de la pantalla.
         *
         * Los contadores SI se recargan cuando el pedido AVANZO de estado: ahi
         * los chips cambian de verdad. Si solo cambio el dinero, no.
         */
        onPaymentsUpdated(respuesta) {
            const id = respuesta && respuesta.order_id;

            if (!id) {
                // Cobro de un envio sin pedido identificable: se cae al
                // comportamiento de antes en vez de no refrescar nada.
                this.refreshAfterPayment();
                return;
            }

            this.refrescarFila(id);

            if (respuesta.advanced) {
                this.loadChipCounts();
                this.loadStats();
            }
        },

        /** Tras verificar o rechazar: la misma fila, no la tabla entera. */
        onVerificationChanged(respuesta) {
            this.onPaymentsUpdated(respuesta);
        },

        /**
         * Sustituye una fila del listado por su version fresca.
         *
         * La fila se pide al servidor y no se parchea a mano: los documentos,
         * el estado economico y el bloque logistico los resuelve PHP, y
         * recalcularlos aqui seria tener dos verdades. Si la peticion falla no
         * se toca nada — mejor una fila vieja que una fila en blanco.
         */
        refrescarFila(id) {
            const dt = this.$refs.ordersTable;
            if (!dt) return;

            this.$http
                .get(`/orders/row/${id}`)
                .then(r => {
                    const fila = r.data && r.data.data;
                    if (!fila) return;

                    const reemplazar = lista => {
                        const i = (lista || []).findIndex(x => x.id === fila.id);
                        // `splice` y no asignacion por indice: Vue 2 no detecta
                        // `arr[i] = x` y la fila no se repintaria.
                        if (i !== -1) lista.splice(i, 1, fila);
                    };

                    reemplazar(dt.records);
                    reemplazar(this.currentRecords);
                })
                .catch(() => {
                    // Silencio a proposito: el cobro SI se guardo, y un fallo
                    // aqui solo significa que la fila no se refresco.
                });
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

        /**
         * Enciende o apaga una columna, y lo recuerda.
         *
         * Va a `localStorage` y no al servidor: es una preferencia de quien
         * mira, no un dato del negocio, y guardarla en la base obligaria a una
         * tabla, una migracion y un endpoint para algo que solo importa en este
         * navegador. Se lee dentro de un try: en una ventana privada o con las
         * cookies bloqueadas, `localStorage` LANZA en vez de devolver null, y
         * eso tumbaria el arranque del componente entero.
         */
        alternarColumna(clave) {
            this.$set(this.columnas, clave, !this.columnas[clave]);

            try {
                window.localStorage.setItem(
                    "ord.columnas",
                    JSON.stringify(this.columnas)
                );
            } catch (e) {
                // Sin persistencia, pero la sesion actual sigue funcionando.
            }
        },

        /** Recupera las columnas guardadas, si las hay y si se pueden leer. */
        cargarColumnas() {
            try {
                const guardado = window.localStorage.getItem("ord.columnas");
                if (!guardado) return;

                const datos = JSON.parse(guardado);

                // Solo se aceptan las claves que EXISTEN hoy: un `localStorage`
                // viejo con una columna que ya se quito no debe resucitarla.
                this.columnasOpcionales.forEach(c => {
                    if (typeof datos[c.key] === "boolean") {
                        this.$set(this.columnas, c.key, datos[c.key]);
                    }
                });
            } catch (e) {
                // Se queda con todas visibles, que es el valor por defecto.
            }
        },

        /**
         * Descarga el listado filtrado.
         *
         * Se navega en vez de pedirlo por axios: la respuesta es un fichero y
         * el navegador ya sabe guardarlo. Con axios habria que montar un blob y
         * un enlace temporal para acabar en lo mismo.
         */
        exportar() {
            const dt = this.$refs.ordersTable;
            const params = new URLSearchParams();

            if (dt && dt.search) {
                Object.keys(dt.search).forEach(k => {
                    const v = dt.search[k];
                    if (v !== null && v !== undefined && v !== "") params.append(k, v);
                });
            }

            params.append("warehouse_id", (dt && dt.warehouse_id) || "all");

            window.open("/orders/export?" + params.toString(), "_blank");
        },

        /**
         * Abre una de las cuatro pantallas de apoyo del envio.
         *
         * Las URL cuelgan de `orders/` y ya no de `registro-envio/`: mismo
         * controlador y mismas vistas, lo que cambia es la puerta. Cuando el
         * prefijo viejo desaparezca, estas cuatro no se enteran.
         */
        irA(destino) {
            const rutas = {
                tienda: "/orders/config-envios",
                motorizado: "/orders/reparto-domicilio",
                tablero: "/orders/tablero-logistico",
                lotes: "/orders/print-batches",
            };

            if (rutas[destino]) window.open(rutas[destino], "_blank");
        },

        /**
         * Ordena por el que lleva mas tiempo esperando.
         *
         * Es el orden que pide la operacion: lo urgente no es lo ultimo que
         * entro, es lo que lleva mas dias sin salir. Un segundo clic lo
         * devuelve a «mas recientes».
         */
        masAntiguosPrimero() {
            const yaEsta = this.orden === "fecha" && this.ordenDir === "asc";
            this.orden = "fecha";
            this.ordenDir = yaEsta ? "desc" : "asc";
            this.pushFilters();
        },

        /** Filtra por antiguedad. Un segundo clic en el mismo lo quita. */
        verAntiguedad(cual) {
            this.agingFilter = this.agingFilter === cual ? "" : cual;
            this.applyLogisticFilters();
        },

        /**
         * Quita un filtro desde su chip.
         *
         * Cada uno vuelve a su valor NEUTRO, que no siempre es la cadena vacia
         * —`orderSource` es «all» y `dateType` es «order»—; ponerlos a "" los
         * dejaria en un estado que el backend no reconoce.
         */
        quitarFiltro(clave) {
            const neutro = {
                q: "",
                estadoPago: "",
                orderSource: "all",
                dateRange: "",
                dateType: "order",
                deliveryTypeFilter: "",
                agingFilter: "",
            };

            if (!(clave in neutro)) return;

            this[clave] = neutro[clave];

            // Quitar «personalizado» tiene que llevarse las fechas con el, o
            // seguirian filtrando sin que nada lo diga.
            if (clave === "dateRange") this.invoiceDateRange = [];

            this.pushFilters();
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
