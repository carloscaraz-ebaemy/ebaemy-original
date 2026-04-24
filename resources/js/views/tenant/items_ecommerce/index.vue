<template>
    <div class="items_ecommerce">
        <div class="page-header pe-0">
            <h2>
                <a href="/items_ecommerce">
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
                    <span>Productos en tu tienda online</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <template>
                    <!-- v-if="typeUser === 'admin'" -->
                    <!-- <button type="button" class="btn btn-custom btn-sm  mt-2 me-2" @click.prevent="clickImport()"><i class="fa fa-upload"></i> Importar</button>-->
                    <button
                        type="button"
                        class="btn btn-custom btn-sm mt-2 me-2"
                        @click.prevent="clickCreate()"
                    >
                        <i class="fa fa-plus-circle"></i> Nuevo
                    </button>
                </template>
            </div>
        </div>
        <!-- ── Banner unificación catálogo (Opción A) ── -->
        <div style="margin-bottom:12px;padding:12px 16px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <div style="font-size:22px;line-height:1">📚</div>
            <div style="flex:1;min-width:240px">
                <div style="font-weight:600;font-size:14px;color:#065f46">Estos son productos de tu catálogo publicados en la tienda online</div>
                <div style="font-size:12px;color:#047857;margin-top:2px">
                    No se duplican: es el mismo producto de "Productos y Servicios". El precio, stock y datos de facturación viven en el catálogo maestro.
                </div>
            </div>
            <a href="/items" class="btn btn-sm btn-outline-success" style="border-color:#10b981;color:#065f46">
                Ver catálogo completo →
            </a>
        </div>

        <!-- ── Marketplace stats (solo si tenant tiene listings publicados) ── -->
        <div v-if="mpStats.published > 0" class="mp-stats-card">
            <div class="mp-stats-head">
                <span class="mp-stats-title">🌐 Tus productos en <strong>ebaemy.com/marketplace</strong></span>
                <a href="/items_ecommerce?published_mp=1" class="mp-stats-link">Ver todos</a>
            </div>
            <div class="mp-stats-grid">
                <div class="mp-stat"><div class="mp-stat__n">{{ mpStats.published }}</div><div class="mp-stat__l">Publicados</div></div>
                <div class="mp-stat"><div class="mp-stat__n">{{ mpStats.views }}</div><div class="mp-stat__l">Vistas totales</div></div>
                <div class="mp-stat"><div class="mp-stat__n">{{ mpStats.clicks }}</div><div class="mp-stat__l">Clicks a tienda</div></div>
                <div class="mp-stat mp-stat--hl"><div class="mp-stat__n">{{ mpStats.leads_30d }}</div><div class="mp-stat__l">Pedidos 30d</div></div>
            </div>
            <div v-if="mpStats.top && mpStats.top.length" class="mp-stats-top">
                <span class="mp-stats-top__label">Más vistos:</span>
                <span v-for="(t, i) in mpStats.top" :key="i" class="mp-stats-top__item">
                    {{ t.title }} <small>({{ t.view_count }} vistas)</small>
                </span>
            </div>
        </div>

        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
        <h3 class="my-0">Listado de productos Tienda Virtual</h3>
      </div> -->
            <div class="card-body">
                <data-table :resource="resource" :ecommerce="ecommerce" :sort-field="sortField" :sort-direction="sortDirection" @sort-change="handleSortChange">
                    <tr slot="heading" width="100%" slot-scope="{ sort }">
                        <!-- <th>#</th> -->
                        <th>Cód. Interno</th>
                        <th>Unidad</th>
                        <th class="text-center">Imagen</th>
                        <th>
                            <a href="#" @click.prevent="sort('description')" style="color: inherit; text-decoration: none;">
                                Nombre 
                                <i class="fas" :class="{
                                    'fa-sort-up': sortField === 'description' && sortDirection === 'asc',
                                    'fa-sort-down': sortField === 'description' && sortDirection === 'desc',
                                    'fa-sort': sortField !== 'description' || 
                                              (sortField === 'description' && sortDirection === 'default')
                                }"></i>
                            </a>
                        </th>
                        <th class="text-end">P.Unitario (Venta)</th>
                        <th class="text-end">Stock General</th>
                        <th class="text-center">Tags</th>

                        <th class="text-center">Visible en Tienda</th>
                        <th class="text-center" style="min-width:90px">
                            Marketplace
                            <el-tooltip content="Publicar este producto en ebaemy.com/marketplace" placement="top">
                                <i class="el-icon-info text-info" style="cursor:help;margin-left:2px"></i>
                            </el-tooltip>
                        </th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr></tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.internal_id }}</td>
                        <td>{{ row.unit_type_id }}</td>
                        <td class="text-center">
                            <a @click="viewImages(row)" href="#">
                                <img
                                    :src="row.image_url_small"
                                    tyle="object-fit: contain;"
                                    alt
                                    width="32px"
                                    height="32px"
                                />
                            </a>
                            <!--<img :src="row.image_url_medium"  width="40" height="40" class="img-thumbail img-custom" /> -->
                        </td>
                        <td>{{ row.description }}</td>
                        <td class="text-end">{{ row.sale_unit_price }}</td>
                        <td
                            class="text-end"
                            :class="{
                                'text-danger': stock(row.warehouses) <= 0
                            }"
                        >
                            {{ stock(row.warehouses) }}
                        </td>
                        <td class="text-center">
                            <el-tag
                                style="margin:1px"
                                v-for="tag in row.tags"
                                :key="tag.id"
                                >{{ tag.tag ? (tag.tag.name) : '' }}</el-tag
                            >
                        </td>
                        <td class="text-center">
                            <el-checkbox
                                size="medium"
                                @change="visibleStore($event, row.id)"
                                v-model="row.apply_store"
                            ></el-checkbox>
                        </td>
                        <td class="text-center">
                            <el-switch
                                v-model="row.marketplace_publishable"
                                active-color="#8b5cf6"
                                @change="toggleMarketplace($event, row.id)"
                            ></el-switch>
                        </td>
                        <td class="text-end">
                            <template>
                                <!-- v-if="typeUser === 'admin'" -->
                                <el-tooltip content="Edición rápida (datos web)" placement="top">
                                    <button
                                        type="button"
                                        class="btn waves-effect waves-light btn-xs btn-info"
                                        @click.prevent="clickCreate(row.id)"
                                    >
                                        Editar
                                    </button>
                                </el-tooltip>
                                <el-tooltip content="Abrir ficha completa del catálogo (stock, compras, lotes, atributos)" placement="top">
                                    <a
                                        :href="'/items#edit-' + row.id"
                                        class="btn waves-effect waves-light btn-xs btn-default ms-1"
                                        style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151"
                                    >
                                        Ficha ERP
                                    </a>
                                </el-tooltip>
                                <el-tooltip v-if="row.apply_store && row.slug" content="Abrir en tu tienda online (pública)" placement="top">
                                    <a
                                        :href="'/item/' + row.slug"
                                        target="_blank" rel="noopener"
                                        class="btn waves-effect waves-light btn-xs ms-1"
                                        style="background:#ecfdf5;border:1px solid #10b981;color:#065f46"
                                    >
                                        🛍️
                                    </a>
                                </el-tooltip>
                                <el-tooltip v-if="row.marketplace_publishable && row.mp_status === 'active'" content="Ver en ebaemy.com/marketplace" placement="top">
                                    <a
                                        :href="'https://ebaemy.com/marketplace?q=' + encodeURIComponent(row.description || row.name || '')"
                                        target="_blank" rel="noopener"
                                        class="btn waves-effect waves-light btn-xs ms-1"
                                        style="background:#faf5ff;border:1px solid #a78bfa;color:#5b21b6"
                                    >
                                        🌐
                                    </a>
                                </el-tooltip>
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-danger ms-1"
                                    @click.prevent="clickDelete(row.id)"
                                >
                                    Eliminar
                                </button>
                            </template>
                        </td>
                    </tr>
                </data-table>
            </div>

            <items-form
                :showDialog.sync="showDialog"
                :recordId="recordId"
            ></items-form>

            <!-- <items-import :showDialog.sync="showImportDialog"></items-import> -->

            <warehouses-detail
                :showDialog.sync="showWarehousesDetail"
                :warehouses="warehousesDetail"
            ></warehouses-detail>

            <!-- <images-record :showDialog.sync="showImageDetail" :recordImages="recordImages"></images-record> -->

            <el-dialog
                :visible.sync="showImageDetail"
                title="Imagenes de Producto"
                width="50%"
                append-to-body
                top="7vh"
            >
                <div class="row d-flex align-items-end justify-content-end">
                    <div class="col-md-3">
                        <h4>Thumbs</h4>
                        <img
                            class="img-thumbnail"
                            :src="recordImages.image_url_small"
                            alt
                            width="128"
                        />
                    </div>
                    <div class="col-md-4">
                        <h4>Para productos de Venta</h4>
                        <img
                            class="img-thumbnail"
                            :src="recordImages.image_url_medium"
                            alt
                            width="256"
                        />
                    </div>
                    <div class="col-md-4">
                        <h4>Para Tienda</h4>
                        <img
                            class="img-thumbnail"
                            :src="recordImages.image_url"
                            alt
                            width="512"
                        />
                    </div>
                </div>
                <div class="row text-end pt-2">
                    <div class="col align-self-end">
                        <el-button
                            type="primary"
                            @click="showImageDetail = false"
                            >Cerrar</el-button
                        >
                    </div>
                </div>
            </el-dialog>
        </div>
    </div>
</template>
<style>
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

/* ── Marketplace stats card ─────────────────────────────────────────── */
.mp-stats-card {
    background: linear-gradient(135deg,#faf5ff 0%,#f3e8ff 100%);
    border: 1px solid #e9d5ff;
    border-radius: 12px;
    padding: 14px 18px;
    margin: 0 0 12px;
}
.mp-stats-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.mp-stats-title { font-size:14px; color:#6b21a8; }
.mp-stats-link  { font-size:12px; color:#7e22ce; text-decoration:none; }
.mp-stats-link:hover { text-decoration:underline; }
.mp-stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.mp-stat { background:#fff; border-radius:8px; padding:10px 12px; text-align:center; border:1px solid #f3e8ff; }
.mp-stat__n { font-size:20px; font-weight:700; color:#111827; }
.mp-stat__l { font-size:11px; color:#6b7280; margin-top:2px; text-transform:uppercase; letter-spacing:.3px; }
.mp-stat--hl .mp-stat__n { color:#7e22ce; }
.mp-stats-top { margin-top:10px; font-size:12px; color:#64748b; display:flex; gap:12px; flex-wrap:wrap; }
.mp-stats-top__label { font-weight:600; }
.mp-stats-top__item small { color:#9ca3af; }
@media (max-width:680px) {
    .mp-stats-grid { grid-template-columns:repeat(2,1fr); }
}
</style>
<script>
import ItemsForm from "./form.vue";
import WarehousesDetail from "./partials/warehouses.vue";
// import ItemsImport from './import.vue'
import DataTable from "../../../components/DataTable.vue";
import { deletable } from "../../../mixins/deletable";

export default {
    props: [], //'typeUser'
    mixins: [deletable],
    components: { ItemsForm, DataTable, WarehousesDetail }, //ItemsImport
    data() {
        return {
            showDialog: false,
            showImportDialog: false,
            showWarehousesDetail: false,
            showImageDetail: false,
            resource: "items",
            recordId: null,
            warehousesDetail: [],
            recordImages: {
                image_url: "",
                image_url_medium: "",
                image_url_small: ""
            },
            ecommerce: true,
            sortField: localStorage.getItem('itemSortField') || 'id',
            sortDirection: localStorage.getItem('itemSortDirection') || 'desc',
            mpStats: { published: 0, views: 0, clicks: 0, leads_total: 0, leads_30d: 0, top: [] }
        };
    },
    created() {
        this.loadMarketplaceStats();
    },
    methods: {
        handleSortChange(sort) {
            if (this.sortField === sort.field && this.sortDirection === 'desc' && sort.field === 'description') {
                this.sortField = 'id';
                this.sortDirection = 'desc';
            } else {
                this.sortField = sort.field;
                this.sortDirection = sort.direction;
            }

            localStorage.setItem('itemSortField', this.sortField);
            localStorage.setItem('itemSortDirection', this.sortDirection);
        },
        viewImages(row) {
            this.recordImages.image_url = row.image_url;
            this.recordImages.image_url_medium = row.image_url_medium;
            this.recordImages.image_url_small = row.image_url_small;
            this.showImageDetail = true;
        },
        visibleStore(apply_store, id) {
            this.$http
                .post(`/${this.resource}/visible_store`, { id, apply_store })
                .then(response => {
                    if (response.data.success) {
                        if (apply_store) {
                            this.$message.success(response.data.message);
                        } else {
                            this.$message.warning(response.data.message);
                        }
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error(response.data.message);
                        this.$eventHub.$emit("reloadData");
                    }
                })
                .catch(error => {})
                .then(() => {});
        },
        toggleMarketplace(value, id) {
            this.$http
                .post(`/items/marketplace-toggle`, { id, marketplace_publishable: value })
                .then(response => {
                    if (response.data.success) {
                        value
                            ? this.$message.success(response.data.message)
                            : this.$message.warning(response.data.message);
                        // Recarga stats para reflejar el cambio sin refrescar la página
                        this.loadMarketplaceStats();
                    } else {
                        this.$message.error(response.data.message || 'No se pudo actualizar');
                    }
                })
                .catch(() => {
                    this.$message.error('Error al actualizar marketplace');
                });
        },
        loadMarketplaceStats() {
            this.$http
                .get(`/items/marketplace-stats`)
                .then(r => { if (r.data) this.mpStats = r.data; })
                .catch(() => { /* stats opcionales; si fallan, el card queda oculto */ });
        },
        clickWarehouseDetail(warehouses) {
            this.warehousesDetail = warehouses;
            this.showWarehousesDetail = true;
        },
        clickCreate(recordId = null) {
            this.recordId = recordId;
            this.showDialog = true;
        },
        clickImport() {
            this.showImportDialog = true;
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        stock(items) {
            let stock = 0;
            items.forEach(item => {
                stock += parseInt(item.stock);
            });
            return stock;
        }
    }
};
</script>
