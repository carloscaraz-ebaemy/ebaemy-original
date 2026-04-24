<template>
    <div v-loading="loading_submit">
        <div class="row ">
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
                      'col-lg-4 col-md-4': !fromEcommerce && !fromRestaurant,
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
                      'col-lg-3 col-md-3': !fromEcommerce && !fromRestaurant,
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
                            <span class="datatable-filter-label">Canal</span>
                            <el-select
                                class="datatable-filter-select"
                                v-model="search.channel_filter"
                                size="small"
                                @change="handleChannelFilterChange"
                            >
                                <el-option label="Todos" value="all"></el-option>
                                <el-option label="🛍️ En tienda online" value="in_store"></el-option>
                                <el-option label="🌐 Publicado en Marketplace" value="in_marketplace"></el-option>
                                <el-option label="⏳ Pendiente en Marketplace" value="pending_mp"></el-option>
                                <el-option label="⏸️ Pausado en Marketplace" value="paused_mp"></el-option>
                                <el-option label="❌ Rechazado en Marketplace" value="rejected_mp"></el-option>
                                <el-option label="Sin publicar (ningún canal)" value="unpublished"></el-option>
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
                            layout="total, prev, pager, next"
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

.datatable-product-filter {
    display: flex;
    align-items: center;
    justify-content: end;
    gap: 8px;
    width: 100%;
}

.datatable-filter-label {
    display: inline-block;
    max-width: 140px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.datatable-filter-select {
    width: 100%;
    max-width: 220px;
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
    },
    data() {
        return {
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
            const allowed = ['all', 'in_store', 'in_marketplace', 'pending_mp', 'paused_mp', 'rejected_mp', 'unpublished'];
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
        await this.$http
            .get(`/${_.head(column_resource)}/columns`)
            .then(response => {
                this.columns = response.data;
                this.search.column = _.head(Object.keys(this.columns));
            });
        
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

                    this.$emit('records-changed', this.records);
                })
                .catch(error => {})
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
                limit: this.limit,
                isPharmacy:this.fromPharmacy,
                isRestaurant:this.fromRestaurant,
                isEcommerce:this.fromEcommerce,
                sort_field: this.currentSort.field,
                sort_direction: this.currentSort.direction,
                show_disabled: this.showDisabledValue,
                warehouse_id: this.warehouse_id,
                ...this.search
            });
        },
        changeClearInput() {
            this.search.value = "";
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