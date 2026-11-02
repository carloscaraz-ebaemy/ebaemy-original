<template>
    <div v-loading="loading_submit">
        <div class="row ">
            <!-- Barra única (prop `toolbar`): buscador grande, estado y qué
                 listar a la vista, sin «Mostrar filtros». Hoy la usa la
                 Tienda virtual; el resto de pantallas sigue con la de abajo. -->
            <div v-if="toolbar" class="col-12">
                <div class="dt-tb">
                    <div class="dt-tb__search">
                        <el-date-picker
                            v-if="['date_of_issue','date_of_due','date_of_payment','delivery_date'].includes(search.column)"
                            v-model="search.value" type="date" placeholder="Buscar por fecha"
                            value-format="yyyy-MM-dd" style="width:100%" @change="getRecords"
                        ></el-date-picker>
                        <el-input v-else v-model="search.value" :placeholder="searchPlaceholder"
                                  prefix-icon="el-icon-search" clearable @input="getRecords">
                            <el-select v-if="Object.keys(columns).length > 1" slot="append" v-model="search.column"
                                       class="dt-tb__col" @change="changeClearInput">
                                <el-option v-for="(label, key) in columns" :key="key" :value="key" :label="label"></el-option>
                            </el-select>
                        </el-input>
                    </div>
                    <div v-if="showChannelFilter" class="dt-tb__field">
                        <span class="dt-tb__label">Estado</span>
                        <el-select v-model="search.channel_filter" class="dt-tb__select" @change="handleChannelFilterChange">
                            <el-option label="Todos" value="all"></el-option>
                            <el-option-group v-for="g in channelOptions" :key="g.label" :label="g.label">
                                <el-option v-for="o in g.options" :key="o.value" :label="o.label" :value="o.value">
                                    <span class="dt-dot" :style="{ background: o.color }"></span>{{ o.label }}
                                </el-option>
                            </el-option-group>
                        </el-select>
                    </div>
                    <div v-if="fromRestaurant || fromEcommerce" class="dt-tb__field">
                        <span class="dt-tb__label">Mostrar</span>
                        <el-select v-model="search.list_value" class="dt-tb__select dt-tb__select--sm" @change="handleListValueChange">
                            <el-option v-for="(label, key) in list_columns" :key="key" :value="key" :label="label"></el-option>
                        </el-select>
                    </div>
                    <el-tooltip v-if="fromRestaurant || fromEcommerce" placement="top"
                                content="Hace visibles en la tienda todos los productos con código interno. Aplica al canal actual.">
                        <el-button class="dt-tb__btn" icon="el-icon-view" @click="methodVisibleAllProduct">Hacer visibles todos</el-button>
                    </el-tooltip>
                </div>
            </div>
            <template v-else>
            <div class="filter-container"
            :class="{
              'col-md-12 col-lg-12 col-xl-12': !fromEcommerce && !fromRestaurant,
              'col-md-6 col-lg-6 col-xl-6': fromEcommerce || fromRestaurant
            }">
                <div class="btn-filter-content">
                    <el-button
                        type="secondary"
                        class="btn-show-filter mb-2"
                        :class="{ shift: isVisible }"
                        @click="toggleInformation"
                    >
                        {{ isVisible ? "Ocultar filtros" : "Mostrar filtros" }}
                    </el-button>
                </div>
                <div class="row filter-content" v-if="applyFilter && isVisible">
                    <div class="col-sm-12 pb-2"
                    :class="{
                      'col-lg-3 col-md-4': !fromEcommerce && !fromRestaurant,
                      'col-md-6 col-lg-6': fromEcommerce || fromRestaurant
                    }">
                        <div class="d-flex">
                            <div class="d-flex align-items-center me-2 text-nowrap">
                                Filtrar por:
                            </div>
                            <el-select
                                v-model="search.column"
                                placeholder="Select"
                                @change="changeClearInput"
                            >
                                <el-option
                                    v-for="(label, key) in columns"
                                    :key="key"
                                    :value="key"
                                    :label="label"
                                ></el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="col-sm-12 pb-2"
                    :class="{
                      'col-lg-5 col-md-5': !fromEcommerce && !fromRestaurant,
                      'col-md-5 col-lg-5': fromEcommerce || fromRestaurant
                    }">
                        <template
                            v-if="
                                search.column === 'date_of_issue' ||
                                search.column === 'date_of_due' ||
                                search.column === 'date_of_payment' ||
                                search.column === 'delivery_date'
                            "
                        >
                            <el-date-picker
                                v-model="search.value"
                                type="date"
                                style="width: 100%;"
                                placeholder="Buscar"
                                value-format="yyyy-MM-dd"
                                @change="getRecords"
                            >
                            </el-date-picker>
                        </template>
                        <template v-else>
                            <el-input
                                placeholder="Buscar"
                                v-model="search.value"
                                style="width: 100%;"
                                prefix-icon="el-icon-search"
                                @input="getRecords"
                            >
                            </el-input>
                        </template>
                    </div>
                    <div
                        v-if="showProductFilter"
                        class="col-lg-4 col-md-5 col-sm-12 pb-2 d-flex align-items-center justify-content-lg-end ms-auto"
                    >
                        <div class="datatable-product-filter">
                            <span class="datatable-filter-label" :title="filterLabel">{{ filterLabel }}</span>
                            <el-select
                                class="datatable-filter-select"
                                v-model="showDisabledValue"
                                :placeholder="filterPlaceholder"
                                size="small"
                                @change="handleShowDisabledChange"
                            >
                                <el-option label="Todos" value="all"></el-option>
                                <el-option label="Habilitados" value="enabled"></el-option>
                                <el-option label="Inhabilitados" value="disabled"></el-option>
                            </el-select>
                        </div>
                    </div>
                    <div
                        v-if="showChannelFilter"
                        class="col-lg-3 col-md-4 col-sm-12 pb-2 d-flex align-items-center"
                    >
                        <div class="datatable-product-filter">
                            <span class="datatable-filter-label">Estado</span>
                            <el-select
                                class="datatable-filter-select"
                                v-model="search.channel_filter"
                                size="small"
                                @change="handleChannelFilterChange"
                            >
                                <el-option label="Todos" value="all"></el-option>
                                <el-option-group label="Canal de venta">
                                    <el-option label="🛍️ En tienda" value="in_store"></el-option>
                                    <el-option label="🌐 En Marketplace" value="in_marketplace"></el-option>
                                    <el-option label="⏳ Pendiente MP" value="pending_mp"></el-option>
                                    <el-option label="⏸️ Pausado MP" value="paused_mp"></el-option>
                                    <el-option label="❌ Rechazado MP" value="rejected_mp"></el-option>
                                    <el-option label="Sin publicar" value="unpublished"></el-option>
                                </el-option-group>
                                <el-option-group label="Saga Falabella">
                                    <el-option label="🟢 En Saga" value="in_saga"></el-option>
                                    <el-option label="🟡 Saga pendiente" value="saga_pending"></el-option>
                                    <el-option label="🔴 Saga error" value="saga_error"></el-option>
                                </el-option-group>
                                <el-option-group label="Inventario">
                                    <el-option label="📦 Con stock" value="with_stock"></el-option>
                                    <el-option label="🚫 Sin stock" value="no_stock"></el-option>
                                </el-option-group>
                                <el-option-group label="Precio">
                                    <el-option label="🏷️ Con oferta vigente" value="on_offer"></el-option>
                                </el-option-group>
                            </el-select>
                        </div>
                    </div>
                    <div v-if="showWarehouseFilter" class="col-lg-3 col-md-3 col-sm-12 pb-2 d-flex ms-auto">
                        <div class="d-flex align-items-center me-2 text-nowrap">
                            Almacén:
                        </div>
                        <el-select
                          v-model="warehouse_id"
                          size="small"
                          @change="handleWarehouseChange"
                          placeholder="Seleccionar"
                        >
                          <el-option label="Todos" value="all"></el-option>
                          <el-option
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            :label="warehouse.description"
                            :value="warehouse.id"
                          ></el-option>
                        </el-select>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-6">
                <div class="row" v-if="fromRestaurant||fromEcommerce">
                    <div class="col-lg-12 col-md-12 col-sm-12 pb-2 d-flex flex-wrap justify-content-end align-items-center">
                        <div class="d-flex col-12 col-md-6 mb-2 mb-md-0 ps-0 pe-2" v-if="fromRestaurant||fromEcommerce">
                            <div class="my-auto w-100 text-end">
                                <el-button  @click="methodVisibleAllProduct" type="primary" class="button-truncate position-relative btn-show-all-products" title="Mostrar todos los productos">
                                    <span class="d-inline d-lg-none">Mostrar todos</span>
                                    <span class="d-none d-lg-inline">Mostrar todos los productos</span>
                                    <el-tooltip
                                        class="item"
                                        content="Solo se mostrarán productos con código interno registrado. Esta opción aplica para el canal actual."
                                        effect="dark"
                                        placement="top-start"
                                    >
                                        <i class="fa fa-info-circle ms-1"></i>
                                    </el-tooltip>
                                </el-button>
                            </div>
                        </div>

                        <div class="d-flex col-12 col-md-6 px-0">
                            <el-select
                                class="pe-0"
                                v-model="search.list_value"
                                placeholder="Select"
                                @change="handleListValueChange"
                            >
                                <el-option
                                    v-for="(label, key) in list_columns"
                                    :key="key"
                                    :value="key"
                                    :label="label"
                                ></el-option>
                            </el-select>
                        </div>
                    </div>
                </div>
            </div>
            </template>
            <div class="col-md-12 position-relative">
                <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>

                <div class="table-responsive table-responsive-new" ref="scrollContainer">
                    <table class="table">
                        <thead>
                            <slot name="heading" :sort="handleSort" :showRestaurantStock="showRestaurantStock"></slot>
                        </thead>
                        <tbody>
                            <!-- FILAS DE DATOS -->
                            <slot
                                v-for="(row, index) in records"
                                :row="row"
                                :index="customIndex(index)"
                                :showRestaurantStock="showRestaurantStock"
                            ></slot>
                            <tr v-if="records.length === 0">
                                <td colspan="100" style="border: none; padding: 0;">
                                    <empty-state />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                
                    <!-- PAGINACIÓN (solo si hay datos) -->
                    <div v-if="records.length > 0">
                        <el-pagination
                            @current-change="getRecords"
                            @size-change="cambiarTamano"
                            :layout="pageSizes ? 'total, sizes, prev, pager, next' : 'total, prev, pager, next'"
                            :page-sizes="pageSizes || [10]"
                            :total="pagination.total"
                            :current-page.sync="pagination.current_page"
                            :page-size="pagination.per_page"
                        >
                        </el-pagination>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<style>
.btn-show-filter {
    display: block !important;
}
.button-truncate, .list-products-container {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  display: inline-block;
  text-align: left;
}
.btn-show-filter.shift {
    display: block !important;
}

/* ── Barra compacta (prop toolbar) ── */
.dt-tb { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; }
.dt-tb__search { flex: 1 1 320px; min-width: 0; }
.dt-tb .el-input__inner { height: 40px; line-height: 40px; font-size: 14px; border-radius: 8px; }
.dt-tb .el-input__icon { line-height: 40px; }
.dt-tb__search .el-input-group__append { background: #f8fafc; border-radius: 0 8px 8px 0; padding: 0 12px; }
.dt-tb__search .el-input-group--append > .el-input__inner { border-radius: 8px 0 0 8px; }
.dt-tb__col { width: 150px; }
.dt-tb__col .el-input__inner { font-size: 13px; color: #475569; }
.dt-tb__field { display: flex; align-items: center; gap: 8px; }
.dt-tb__label { font-size: 13px; font-weight: 500; color: #475569; white-space: nowrap; }
.dt-tb__select { width: 220px; }
.dt-tb__select--sm { width: 130px; }
.dt-tb__btn.el-button { height: 40px; border-radius: 8px; font-size: 13px; padding: 0 14px; }
.dt-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; vertical-align: 1px; }
@media (max-width: 767px) {
    .dt-tb__search { flex-basis: 100%; }
    .dt-tb__field { flex: 1 1 calc(50% - 5px); min-width: 0; flex-direction: column; align-items: stretch; gap: 3px; }
    .dt-tb__label { font-size: 12px; }
    .dt-tb__select, .dt-tb__select--sm { width: 100%; }
    .dt-tb__col { width: 110px; }
    .dt-tb > .dt-tb__btn { flex: 1 1 100%; }
}

/* Barra de filtros: controles alineados a la base, ritmo y acabado consistentes. */
.filter-content {
    align-items: flex-end;
    row-gap: 10px;
}
.filter-content .el-input__inner {
    border-radius: 8px;
    height: 38px;
    line-height: 38px;
}
.filter-content .el-input__icon {
    line-height: 38px;
}
/* Buscador prominente: el input ocupa todo el ancho de su columna. */
.filter-content .el-input { width: 100%; }

.datatable-product-filter {
    display: flex;
    align-items: center;
    justify-content: flex-start; /* antes 'end' empujaba y recortaba el label/valor */
    gap: 8px;
    width: 100%;
    flex-wrap: wrap;             /* en pantallas chicas baja el select en vez de truncar */
}

.datatable-filter-label {
    display: inline-block;
    max-width: 100%;            /* antes 140px recortaba "Canal" -> "Ca..." */
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 12px;
    color: #6b7280;
    flex: 0 0 auto;
}

.datatable-filter-select {
    width: 100%;
    min-width: 170px;          /* evita que el valor salga cortado ("Per…") */
    max-width: 260px;
    flex: 1 1 170px;
}

.btn-show-all-products__info {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #fff;
}
</style>
<script>
import queryString from "query-string";

export default {
    props: {
        // Filas por página a elegir (p. ej. [10, 25, 50, 100, 200]). Solo las
        // pantallas cuyo servidor acepta `per_page` lo activan; la elección
        // se recuerda en este equipo con `pageSizeKey`.
        pageSizes: { type: Array, default: null },
        pageSizeKey: { type: String, default: null },
        productType: {
            type: String,
            required: false,
            default: ''
        },
        resource: String,
        applyFilter: {
            type: Boolean,
            default: true,
            required: false
        },
        pharmacy: Boolean,
        restaurant: Boolean,
        ecommerce: Boolean,
        sortField: {
            type: String,
            default: 'id'
        },
        sortDirection: {
            type: String,
            default: 'desc'
        },
        showProductFilter: {
            type: Boolean,
            default: false,
            required: false
        },
        filterLabel: {
            type: String,
            default: 'Listar productos',
            required: false
        },
        filterPlaceholder: {
            type: String,
            default: 'Filtrar productos',
            required: false
        },
        customListColumns: {
            type: Object,
            required: false,
            default: null
        },
        showChannelFilter: {
            type: Boolean,
            default: false,
            required: false
        },
        // Barra de filtros compacta y siempre visible (ver plantilla).
        toolbar: {
            type: Boolean,
            default: false,
        },
        // Filtros propios de la pantalla que usa la tabla (facetas de negocio,
        // rangos…). Se serializan junto al resto de parámetros, así que el padre
        // los declara y no tiene que manipular el estado interno de este
        // componente para que lleguen al backend. Las claves con valor null o
        // cadena vacía se descartan, para no mandar `price_min=` vacío.
        extraFilters: {
            type: Object,
            default: () => ({}),
            required: false
        },
    },
    data() {
        return {
            tamano: null,
            search: {
                column: null,
                value: null,
                list_value: 'all',
                channel_filter: 'all',
            },
            columns: [],
            records: [],
            pagination: {},
            isVisible: false,
            loading_submit: false,
            fromPharmacy: false,
            fromRestaurant: false,
            fromEcommerce: false,
            list_columns: {
                all:'Todos',
                visible:'Visibles',
                hidden:'Ocultos'
            },
            currentSort: {
                field: this.sortField,
                direction: this.sortDirection
            },
            showLeftShadow: false,
            showRightShadow: false,
            showDisabledValue: 'all',
            warehouse_id: 'all',
            warehouses: [],
        };
    },
    computed: {
        searchPlaceholder() {
            const col = this.columns && this.columns[this.search.column];
            return 'Buscar por ' + String(col || 'nombre').toLowerCase() + '…';
        },
        // Opciones del filtro «Estado» de la barra compacta: un punto de color
        // en vez de emojis, agrupadas por tema.
        channelOptions() {
            return [
                { label: 'Dónde se vende', options: [
                    { value: 'in_store', label: 'En tienda', color: '#10b981' },
                    { value: 'in_marketplace', label: 'En Marketplace', color: '#8b5cf6' },
                    { value: 'pending_mp', label: 'Marketplace: pendiente', color: '#f59e0b' },
                    { value: 'paused_mp', label: 'Marketplace: pausado', color: '#94a3b8' },
                    { value: 'rejected_mp', label: 'Marketplace: rechazado', color: '#ef4444' },
                    { value: 'unpublished', label: 'Sin publicar', color: '#cbd5e1' },
                ] },
                { label: 'Saga Falabella', options: [
                    { value: 'in_saga', label: 'En Saga', color: '#10b981' },
                    { value: 'saga_pending', label: 'Saga: pendiente', color: '#f59e0b' },
                    { value: 'saga_error', label: 'Saga: con error', color: '#ef4444' },
                ] },
                { label: 'Stock y precio', options: [
                    { value: 'with_stock', label: 'Con stock', color: '#10b981' },
                    { value: 'no_stock', label: 'Sin stock', color: '#ef4444' },
                    { value: 'on_offer', label: 'Con oferta vigente', color: '#e11d48' },
                ] },
            ];
        },
        // Descarta los filtros vacíos: mandar `price_min=` haría que el backend
        // viera el parámetro presente y filtrara por cadena vacía.
        cleanExtraFilters() {
            const out = {};
            Object.keys(this.extraFilters || {}).forEach(k => {
                const v = this.extraFilters[k];
                if (v !== null && v !== undefined && v !== '' && v !== false) out[k] = v;
            });
            return out;
        },

        showRestaurantStock() {
            return this.search.list_value === 'with_supplies';
        },
        showWarehouseFilter() {
            return this.resource === 'inventory' && window.location.pathname === '/inventory';
        }
    },
    created() {
        if(this.pharmacy !== undefined && this.pharmacy === true){
            this.fromPharmacy = true;
        }
        if(this.ecommerce !== undefined && this.ecommerce === true){
            this.fromEcommerce = true;
        }
        if(this.restaurant !== undefined && this.restaurant === true){
            this.fromRestaurant = true;
        }

        // Si se proporcionan columnas personalizadas, usarlas
        if (this.customListColumns) {
            this.list_columns = this.customListColumns;
        }

        const storedShowDisabled = localStorage.getItem(this.getShowDisabledStorageKey());
        if (['all', 'enabled', 'disabled'].includes(storedShowDisabled)) {
            this.showDisabledValue = storedShowDisabled;
        }

        const storedListValue = localStorage.getItem(this.getListValueStorageKey());
        if (storedListValue && Object.prototype.hasOwnProperty.call(this.list_columns, storedListValue)) {
            this.search.list_value = storedListValue;
        }

        if (this.showChannelFilter) {
            const allowed = ['all', 'in_store', 'in_marketplace', 'pending_mp', 'paused_mp', 'rejected_mp', 'unpublished', 'in_saga', 'saga_pending', 'saga_error', 'with_stock', 'no_stock', 'on_offer'];
            // Prioridad 1: querystring ?channel=X (deep-link desde dashboard / email / etc.)
            try {
                const qs = new URLSearchParams(window.location.search);
                const qsChannel = qs.get('channel');
                if (qsChannel && allowed.includes(qsChannel)) {
                    this.search.channel_filter = qsChannel;
                    try { localStorage.setItem(this.getChannelFilterStorageKey(), qsChannel); } catch (e) {}
                } else {
                    const storedChannel = localStorage.getItem(this.getChannelFilterStorageKey());
                    if (allowed.includes(storedChannel)) {
                        this.search.channel_filter = storedChannel;
                    }
                }
            } catch (e) {}
        }

        this.$eventHub.$on("reloadData", () => {
            this.getRecords();
        });
        this.$root.$refs.DataTable = this;
    },
    async mounted() {
        let column_resource = _.split(this.resource, "/");
        // Sin catch, si /columns fallaba mounted() cortaba aca y getRecords()
        // no llegaba a correr NUNCA: la tabla quedaba vacia para siempre y sin
        // una sola linea en consola. Las columnas solo alimentan el selector de
        // busqueda, asi que su fallo no puede costar las filas.
        try {
            const response = await this.$http.get(
                `/${_.head(column_resource)}/columns`
            );
            this.columns = response.data || {};
            this.search.column = _.head(Object.keys(this.columns));
        } catch (error) {
            this.columns = {};
            console.error(
                `[data-table] fallo al cargar /${_.head(column_resource)}/columns`,
                error && error.response ? error.response.status : error
            );
        }
        
        // Cargar almacenes si debe mostrar el filtro de almacén
        if (this.resource === 'inventory' && window.location.pathname === '/inventory') {
            await this.loadWarehouses();
        }
        
        await this.getRecords();

        this.$nextTick(() => {
            const el = this.$refs.scrollContainer;
            if (el) {
                el.addEventListener('scroll', this.checkScrollShadows);
                this.checkScrollShadows();
            }
        });
    },
    methods: {
                handleListValueChange() {
                        localStorage.setItem(this.getListValueStorageKey(), this.search.list_value);
                        this.getRecords();
                },
        handleChannelFilterChange() {
            this.pagination.current_page = 1;
            this.$emit('channel-filter-change', this.search.channel_filter);
            try {
                localStorage.setItem(this.getChannelFilterStorageKey(), this.search.channel_filter || 'all');
            } catch (e) {}
            this.getRecords();
        },
        getChannelFilterStorageKey() {
            return `datatable_channel_filter:${this.resource || 'items'}`;
        },
        setChannelFilter(value) {
            // Llamado externamente desde el widget de chips para sincronizar.
            this.search.channel_filter = value || 'all';
            this.handleChannelFilterChange();
        },
        handleShowDisabledChange() {
          localStorage.setItem(this.getShowDisabledStorageKey(), this.showDisabledValue);
          this.getRecords();
        },
        getShowDisabledStorageKey() {
            // Key única por recurso/tipo para evitar colisiones entre pantallas
            const resourceKey = this.resource || 'resource';
            const typeKey = this.productType || 'type';
            return `datatable_show_disabled:${resourceKey}:${typeKey}`;
        },
        getListValueStorageKey() {
            const resourceKey = this.resource || 'resource';
            const typeKey = this.productType || 'type';
            return `datatable_list_value:${resourceKey}:${typeKey}`;
        },
        async loadWarehouses() {
            try {
                const response = await this.$http.get('/inventory/tables');
                if (response.data && response.data.warehouses) {
                    this.warehouses = response.data.warehouses;
                }
            } catch (error) {
                console.error('Error al cargar almacenes:', error);
            }
        },
        handleWarehouseChange() {
            this.getRecords();
        },
        checkScrollShadows() {
            const el = this.$refs.scrollContainer;
            if (!el) return;

            const scrollLeft = el.scrollLeft;
            const scrollRight = el.scrollWidth - el.clientWidth - scrollLeft;

            this.showLeftShadow = scrollLeft > 1;
            this.showRightShadow = scrollRight > 1;
        },
        toggleInformation() {
            this.isVisible = !this.isVisible;
        },
        customIndex(index) {
            return (
                this.pagination.per_page * (this.pagination.current_page - 1) +
                index +
                1
            );
        },
        getRecords() {
            this.loading_submit = true;
            return this.$http
                .get(`/${this.resource}/records?${this.getQueryParameters()}`)
                .then(response => {
                    this.records = response.data.data;
                    this.pagination = response.data.meta;
                    this.pagination.per_page = parseInt(
                        response.data.meta.per_page
                    );

                    // El emit va AISLADO: es codigo del componente padre y no
                    // tiene por que ser correcto. Sin este try/catch, cualquier
                    // excepcion de un listener rechazaba la promesa, el .catch
                    // de abajo hacia `records = []` y BORRABA unas filas que ya
                    // habian cargado bien — con un mensaje que ademas culpaba a
                    // la peticion, que habia ido perfecta.
                    try {
                        this.$emit('records-changed', this.records);
                    } catch (e) {
                        console.error(
                            `[data-table] un listener de records-changed falló ` +
                            `(las filas SI cargaron; el fallo es del padre)`, e
                        );
                    }
                })
                .catch(error => {
                    // Un catch vacío deja la tabla en blanco sin distinguir
                    // "no hay resultados" de "la consulta se cayó". Es lo que
                    // hizo invisible un 500 en Pedidos durante todo un deploy.
                    this.records = [];
                    console.error(
                        `[data-table] fallo al cargar /${this.resource}/records`,
                        error && error.response ? error.response.status : error,
                        error && error.response ? error.response.data : null
                    );
                })
                .then(() => {
                    this.loading_submit = false;
                });
        },
        getQueryParameters() {
            if (this.productType == 'ZZ') {
                this.search.type = 'ZZ';
            }
            if (this.productType == 'PRODUCTS') {
                this.search.type = this.productType;
            }
            return queryString.stringify({
                page: this.pagination.current_page,
                per_page: this.pageSizes ? this.filasPorPagina() : undefined,
                limit: this.limit,
                isPharmacy:this.fromPharmacy,
                isRestaurant:this.fromRestaurant,
                isEcommerce:this.fromEcommerce,
                sort_field: this.currentSort.field,
                sort_direction: this.currentSort.direction,
                show_disabled: this.showDisabledValue,
                warehouse_id: this.warehouse_id,
                ...this.search,
                ...this.cleanExtraFilters
            });
        },
        changeClearInput() {
            this.search.value = "";
            this.getRecords();
        },
        filasPorPagina() {
            if (this.tamano) return this.tamano;
            let guardado = null;
            try { guardado = this.pageSizeKey ? parseInt(localStorage.getItem(this.pageSizeKey), 10) : null; } catch (e) {}
            return this.pageSizes.indexOf(guardado) !== -1 ? guardado : null;
        },
        cambiarTamano(n) {
            this.tamano = n;
            try { if (this.pageSizeKey) localStorage.setItem(this.pageSizeKey, String(n)); } catch (e) {}
            this.pagination.current_page = 1;
            this.getRecords();
        },
        getSearch() {
            return this.search;
        },
        async methodVisibleAllProduct() {
            let response = await this.$http.post(`/${this.resource}/visibleMassive`,{
                resource: this.fromRestaurant ? 'restaurant' : 'ecommerce',
            });
            console.log(response);

            if (response.status === 200) {
                this.$message.success(response.data.message);
                this.getRecords()
            } else {
                this.$message.error(response.data.message);

            }
        },
        handleSort(field) {
            if (this.currentSort.field === field) {
                if (this.currentSort.direction === 'asc') {
                    this.currentSort.direction = 'desc';
                } else if (this.currentSort.direction === 'desc' && field === 'description') {
                    this.currentSort.field = 'id';
                    this.currentSort.direction = 'desc';
                } else {
                    this.currentSort.direction = 'asc';
                }
            } else {
                this.currentSort.field = field;
                this.currentSort.direction = 'asc';
            }

            this.$emit('sort-change', this.currentSort);
            this.getRecords();
        }
    },
    watch: {
        // Si el componente cambia de resource/tipo, recargar preferencia
        resource() {
            const storedShowDisabled = localStorage.getItem(this.getShowDisabledStorageKey());
            this.showDisabledValue = ['all', 'enabled', 'disabled'].includes(storedShowDisabled)
                ? storedShowDisabled
                : 'all';

            const storedListValue = localStorage.getItem(this.getListValueStorageKey());
            this.search.list_value = (storedListValue && Object.prototype.hasOwnProperty.call(this.list_columns, storedListValue))
                ? storedListValue
                : 'all';
        },
        productType() {
            const storedShowDisabled = localStorage.getItem(this.getShowDisabledStorageKey());
            this.showDisabledValue = ['all', 'enabled', 'disabled'].includes(storedShowDisabled)
                ? storedShowDisabled
                : 'all';

            const storedListValue = localStorage.getItem(this.getListValueStorageKey());
            this.search.list_value = (storedListValue && Object.prototype.hasOwnProperty.call(this.list_columns, storedListValue))
                ? storedListValue
                : 'all';
        },
        showDisabled(newVal) {
            if (newVal) {
              this.getRecords();
            }
        },
        sortField(newVal) {
            this.currentSort.field = newVal;
        },
        sortDirection(newVal) {
            this.currentSort.direction = newVal;
        }
    }
};
</script>