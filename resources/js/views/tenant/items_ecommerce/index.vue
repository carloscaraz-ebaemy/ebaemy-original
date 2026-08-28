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
            <!-- Cuando itemsCount===0 mostramos el welcome card abajo con su
                 propio CTA grande "Crear mi primer producto". Ocultamos estos
                 botones del header para no duplicar acciones y dejarle al
                 seller una sola decisión clara. -->
            <div class="right-wrapper pull-right" v-if="itemsCount !== 0">
                <template>
                    <!-- Acceso rápido al dashboard analítico del marketplace.
                         Solo se muestra cuando hay productos (sino no hay nada
                         que medir). Iconos lucide-like estilo light. -->
                    <a href="/marketplace-dashboard"
                       class="btn btn-sm btn-outline-info mt-2 me-2"
                       title="Ver métricas del marketplace">
                        <i class="fa fa-chart-line"></i> Dashboard marketplace
                    </a>
                    <el-tooltip
                        content="Publica todos los productos de tu tienda online en ebaemy.com/marketplace"
                        placement="top"
                    >
                        <button
                            type="button"
                            class="btn btn-sm mt-2 me-2"
                            style="background:#8b5cf6;border:1px solid #7c3aed;color:#fff"
                            :disabled="publishingAll"
                            @click.prevent="publishAllToMarketplace()"
                        >
                            <i class="fa" :class="publishingAll ? 'fa-spinner fa-spin' : 'fa-globe'"></i>
                            {{ publishingAll ? 'Publicando…' : 'Publicar todos en Marketplace' }}
                        </button>
                    </el-tooltip>
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
        <!-- ── Banner unificación catálogo (Opción A) ──
             Solo cuando ya hay productos. Sin productos, el welcome card de
             abajo es suficiente y este banner sería confuso ("publicados"
             cuando aún no hay nada). -->
        <div class="ie-info-banner" v-if="itemsCount !== 0">
            <div class="ie-info-banner__icon">📚</div>
            <div class="ie-info-banner__text">
                <div class="ie-info-banner__title">Productos de tu catálogo publicados en la tienda</div>
                <div class="ie-info-banner__hint">
                    No se duplican: viven en el catálogo maestro. Precio, stock y datos de facturación se sincronizan automáticamente.
                </div>
            </div>
            <a href="/items" class="btn btn-sm btn-outline-success ie-info-banner__cta">
                Ver catálogo completo →
            </a>
        </div>

        <!-- ── Welcome / Empty state: solo cuando no hay productos ── -->
        <div v-if="itemsCount === 0" class="ie-welcome-card">
            <div class="ie-welcome-card__icon">🛍️</div>
            <h3 class="ie-welcome-card__title">¡Empieza a vender!</h3>
            <p class="ie-welcome-card__lead">
                Aún no tienes productos en tu tienda. Crea el primero y aparecerá en
                <strong>{{ tenantSubdomain }}.ebaemy.com</strong> en segundos.
            </p>
            <div class="ie-welcome-card__cta">
                <button type="button" class="btn btn-primary btn-lg" @click="clickCreate()">
                    <i class="fa fa-plus-circle"></i> Crear mi primer producto
                </button>
            </div>
            <div class="ie-welcome-card__tips">
                <div class="ie-welcome-card__tip">
                    <span class="ie-welcome-card__tip-icon">📷</span>
                    <span><strong>Fotos claras</strong> aumentan la conversión 3×</span>
                </div>
                <div class="ie-welcome-card__tip">
                    <span class="ie-welcome-card__tip-icon">🏷️</span>
                    <span><strong>Categoría correcta</strong> para que aparezca en marketplace</span>
                </div>
                <div class="ie-welcome-card__tip">
                    <span class="ie-welcome-card__tip-icon">💰</span>
                    <span><strong>Precio realista</strong> + describe los detalles</span>
                </div>
            </div>
        </div>

        <!-- ── Alerta: publicados en marketplace pero OCULTOS por no tener foto ──
             El marketplace filtra los listings sin ninguna imagen (ni del
             producto ni de sus variantes) porque la card sale vacia y no
             vende. Aqui el seller se entera y puede arreglarlo de un clic. -->
        <div v-if="mpStats.no_image > 0" class="mp-noimg-card">
            <div class="mp-noimg-head">
                <span class="mp-noimg-icon">⚠️</span>
                <span>
                    <strong>{{ mpStats.no_image }}</strong>
                    {{ mpStats.no_image === 1 ? 'producto no se está mostrando' : 'productos no se están mostrando' }}
                    en <strong>ebaemy.com/marketplace</strong> porque no
                    {{ mpStats.no_image === 1 ? 'tiene' : 'tienen' }} imagen.
                </span>
            </div>
            <div class="mp-noimg-list">
                <button v-for="it in mpStats.no_image_items"
                        :key="it.id"
                        type="button"
                        class="mp-noimg-chip"
                        @click.prevent="clickCreate(it.remote_item_id)">
                    {{ it.title }}
                    <small v-if="it.has_variants">(variantes)</small>
                </button>
                <span v-if="mpStats.no_image > mpStats.no_image_items.length" class="mp-noimg-more">
                    y {{ mpStats.no_image - mpStats.no_image_items.length }} más…
                </span>
            </div>
            <div class="mp-noimg-hint">
                Sube una foto al producto (o a una de sus variantes) y volverá a aparecer en el siguiente sync.
            </div>
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

        <!-- Tabla principal: oculta cuando itemsCount===0 (mostramos el
             welcome card en su lugar). Apenas haya >=1 producto, esto
             aparece y el welcome card desaparece. -->
        <div class="card tab-content-default row-new mb-0" v-show="itemsCount !== 0">
            <!-- <div class="card-header bg-info">
        <h3 class="my-0">Listado de productos Tienda Virtual</h3>
      </div> -->
            <div class="card-body">
                <data-table :resource="resource" :ecommerce="ecommerce" :sort-field="sortField" :sort-direction="sortDirection" :showChannelFilter="true" @sort-change="handleSortChange">
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
                        <th class="text-end">Precio</th>
                        <th class="text-end">Precio oferta</th>
                        <th class="text-end">Stock General</th>
                        <th class="text-center">Tags</th>

                        <th class="text-center">Visible en Tienda</th>
                        <th class="text-center" style="min-width:90px">
                            Marketplace
                            <el-tooltip content="Publicar este producto en ebaemy.com/marketplace" placement="top">
                                <i class="el-icon-info text-info" style="cursor:help;margin-left:2px"></i>
                            </el-tooltip>
                        </th>
                        <th v-if="mpStats.has_saga" class="text-center" style="min-width:90px">
                            Saga Falabella
                            <el-tooltip content="Publicar este producto en Saga Falabella con un clic" placement="top">
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
                        <td>
                            {{ row.description }}
                            <!-- Estado en Saga Falabella -->
                            <template v-if="row.saga_status">
                                <span v-if="row.saga_status === 'error' || row.saga_qc === 'rejected'" class="ie-saga ie-saga--err" title="Saga Falabella: error / rechazado">🔴 Saga</span>
                                <span v-else-if="row.saga_status === 'pending' || row.saga_qc === 'queued' || row.saga_qc === 'processing'" class="ie-saga ie-saga--pend" title="Saga Falabella: pendiente / en revisión">🟡 Saga</span>
                                <span v-else-if="row.saga_status === 'synced'" class="ie-saga ie-saga--ok" title="En Saga Falabella">🟢 Saga</span>
                            </template>
                        </td>
                        <!-- Precio (normal) -->
                        <td class="text-end">S/ {{ regularOf(row) }}</td>
                        <!-- Precio oferta: texto + tooltip (estado + duración) + lápiz -->
                        <td class="text-end" style="min-width:120px">
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px">
                                <el-tooltip placement="top" :disabled="!offerOf(row)" effect="dark">
                                    <div slot="content">
                                        <div><strong>El precio de oferta está {{ offerStatus(row) }}</strong></div>
                                        <div>Desde: {{ fmtDate(row.compare_at_from) }}</div>
                                        <div>Hasta: {{ fmtDate(row.compare_at_until) }}</div>
                                    </div>
                                    <span v-if="offerOf(row)" style="color:#e53e3e;font-weight:700;cursor:help">S/ {{ offerOf(row) }}</span>
                                    <span v-else style="color:#9ca3af">—</span>
                                </el-tooltip>
                                <el-button type="text" icon="el-icon-edit" @click="openOfferDialog(row)"
                                           title="Editar precio"
                                           style="padding:0;font-size:17px;color:#409eff"></el-button>
                            </div>
                        </td>
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
                        <td v-if="mpStats.has_saga" class="text-center">
                            <el-switch
                                v-model="row.saga_enabled"
                                active-color="#e30613"
                                :loading="sagaLoading === row.id"
                                @change="toggleSaga($event, row)"
                            ></el-switch>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center" style="gap:6px">
                                <!-- Acción principal: Editar -->
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-info"
                                    @click.prevent="clickCreate(row.id)"
                                >
                                    Editar
                                </button>

                                <!-- Acciones secundarias en menú "⋮" -->
                                <el-dropdown trigger="click" @command="onRowActionCommand($event, row)">
                                    <button
                                        type="button"
                                        class="btn waves-effect waves-light btn-xs"
                                        style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;padding:0 8px;line-height:1"
                                    >
                                        <i class="el-icon-more" style="font-size:16px;font-weight:700"></i>
                                    </button>
                                    <el-dropdown-menu slot="dropdown">
                                        <el-dropdown-item command="erp">
                                            <i class="el-icon-document"></i> Ficha ERP completa
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="row.apply_store && row.slug" command="store">
                                            🛍️ Ver en mi tienda
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="row.marketplace_publishable && row.mp_status === 'active'" command="marketplace">
                                            🌐 Ver en ebaemy.com/marketplace
                                        </el-dropdown-item>
                                        <el-dropdown-item divided command="delete">
                                            <span style="color:#dc2626"><i class="el-icon-delete"></i> Eliminar</span>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </el-dropdown>
                            </div>
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

            <!-- Popup: editar precio oferta + duración (estilo Saga) -->
            <el-dialog title="Editar precio" :visible.sync="offerDialogVisible" width="380px" append-to-body>
                <div class="form-group">
                    <label class="control-label">Precio normal</label>
                    <el-input v-model.number="offerForm.regular" type="number" min="0" step="0.01" placeholder="Ej. 200"></el-input>
                </div>
                <div class="form-group">
                    <label class="control-label">Precio de venta (oferta)</label>
                    <el-input v-model.number="offerForm.offer" type="number" min="0" step="0.01" placeholder="Ej. 109"></el-input>
                    <small class="text-muted">Debe ser menor al normal. Deja vacío para quitar la oferta.</small>
                </div>
                <div class="form-group">
                    <label class="control-label">Duración oferta</label>
                    <el-date-picker v-model="offerForm.dateRange" type="daterange" value-format="yyyy-MM-dd"
                                    format="dd/MM/yyyy" range-separator="→"
                                    start-placeholder="Desde" end-placeholder="Hasta" style="width:100%"></el-date-picker>
                </div>
                <span slot="footer">
                    <el-button @click="offerDialogVisible=false">Cancelar</el-button>
                    <el-button type="primary" @click="saveOffer">Guardar</el-button>
                </span>
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

/* ── Alerta productos sin imagen (ocultos en marketplace) ───────────── */
.mp-noimg-card {
    background:#fffbeb;
    border:1px solid #fde68a;
    border-left:4px solid #f59e0b;
    border-radius:10px;
    padding:10px 14px;
    margin:0 0 10px;
}
.mp-noimg-head { display:flex; align-items:flex-start; gap:8px; font-size:13px; color:#78350f; line-height:1.4; }
.mp-noimg-icon { flex:0 0 auto; }
.mp-noimg-list { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.mp-noimg-chip {
    background:#fff; border:1px solid #fcd34d; color:#92400e;
    border-radius:999px; padding:2px 10px; font-size:11.5px; cursor:pointer;
    max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.mp-noimg-chip:hover { background:#fef3c7; }
.mp-noimg-chip small { color:#b45309; }
.mp-noimg-more { font-size:11.5px; color:#92400e; align-self:center; }
.mp-noimg-hint { margin-top:7px; font-size:11.5px; color:#a16207; }

/* ── Marketplace stats card ─────────────────────────────────────────── */
.mp-stats-card {
    background: linear-gradient(135deg,#faf5ff 0%,#f3e8ff 100%);
    border: 1px solid #e9d5ff;
    border-radius: 10px;
    padding: 8px 14px;
    margin: 0 0 10px;
}
.mp-stats-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
.mp-stats-title { font-size:13px; color:#6b21a8; }
.mp-stats-link  { font-size:12px; color:#7e22ce; text-decoration:none; }
.mp-stats-link:hover { text-decoration:underline; }
/* Compacto: tira horizontal de mini-stats en vez de 4 tarjetas grandes. */
.mp-stats-grid { display:flex; flex-wrap:wrap; gap:6px 20px; }
.mp-stat { background:transparent; border:0; padding:0; display:flex; align-items:baseline; gap:6px; }
.mp-stat__n { font-size:15px; font-weight:700; color:#111827; }
.mp-stat__l { font-size:11px; color:#6b7280; margin-top:0; text-transform:none; letter-spacing:0; }
.mp-stat--hl .mp-stat__n { color:#7e22ce; }
.mp-stats-top { margin-top:6px; font-size:11.5px; color:#64748b; display:flex; gap:10px; flex-wrap:wrap; }
/* Chip de estado en Saga Falabella (celda Nombre) */
.ie-saga { display:inline-block; margin-left:6px; padding:1px 7px; border-radius:999px; font-size:10.5px; font-weight:600; vertical-align:middle; white-space:nowrap; }
.ie-saga--ok   { background:#dcfce7; color:#166534; }
.ie-saga--pend { background:#fef3c7; color:#92400e; }
.ie-saga--err  { background:#fee2e2; color:#991b1b; }
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
            offerDialogVisible: false,
            offerForm: { id: null, regular: 0, offer: null, dateRange: [], _row: null },
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
            mpStats: { published: 0, views: 0, clicks: 0, leads_total: 0, leads_30d: 0, top: [], has_saga: false, no_image: 0, no_image_items: [] },
            publishingAll: false,
            // id de la fila cuyo switch de Saga está procesando (spinner en el switch)
            sagaLoading: null,
            // null = cargando · 0 = vacío (mostrar welcome card) · >0 = hay items
            // Sólo se usa para decidir si mostrar el welcome card; el data-table
            // sigue siendo el dueño del listado real y paginación.
            itemsCount: null,
            // Subdomain del tenant para mostrar "X.ebaemy.com" en el welcome
            tenantSubdomain: (typeof window !== 'undefined' && window.location)
                ? (window.location.hostname.split('.')[0] || 'tu-tienda')
                : 'tu-tienda',
        };
    },
    created() {
        this.loadMarketplaceStats();
        this.loadItemsCount();
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
        // Precio "normal" mostrado: si hay oferta (compare_at > venta) el normal es
        // compare_at_price; si no, es el propio precio de venta.
        regularOf(row) {
            var sale = parseFloat(row.amount_sale_unit_price) || 0;
            var cap = parseFloat(row.compare_at_price) || 0;
            return (cap > sale ? cap : sale).toFixed(2);
        },
        // Precio "oferta": solo si hay descuento (compare_at > venta); si no, vacío.
        offerOf(row) {
            var sale = parseFloat(row.amount_sale_unit_price) || 0;
            var cap = parseFloat(row.compare_at_price) || 0;
            return (cap > sale) ? sale.toFixed(2) : '';
        },
        // Estado de la oferta según el rango de fechas (estilo Saga).
        offerStatus(row) {
            var today = new Date(); today.setHours(0,0,0,0);
            var from = row.compare_at_from ? new Date(row.compare_at_from + 'T00:00:00') : null;
            var until = row.compare_at_until ? new Date(row.compare_at_until + 'T00:00:00') : null;
            if (from && today < from) return 'Programado';
            if (until && today > until) return 'Vencido';
            return 'Activo';
        },
        // 'yyyy-MM-dd' → '10 de junio de 2026'
        fmtDate(d) {
            if (!d) return 'Sin definir';
            var meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
            var p = String(d).split('-');
            if (p.length < 3) return d;
            return parseInt(p[2],10) + ' de ' + (meses[parseInt(p[1],10)-1] || '') + ' de ' + p[0];
        },
        openOfferDialog(row) {
            var regular = parseFloat(this.regularOf(row));
            var offerStr = this.offerOf(row);
            this.offerForm = {
                id: row.id,
                regular: regular,
                offer: offerStr === '' ? null : parseFloat(offerStr),
                dateRange: (row.compare_at_from || row.compare_at_until)
                    ? [row.compare_at_from || '', row.compare_at_until || ''] : [],
                _row: row,
            };
            this.offerDialogVisible = true;
        },
        saveOffer() {
            var f = this.offerForm;
            var offer = (f.offer === '' || f.offer === null || f.offer === undefined) ? null : parseFloat(f.offer);
            if (offer !== null && offer >= f.regular) {
                this.$message.warning('La oferta debe ser menor al precio normal');
                return;
            }
            var from = (f.dateRange && f.dateRange.length) ? (f.dateRange[0] || null) : null;
            var until = (f.dateRange && f.dateRange.length) ? (f.dateRange[1] || null) : null;
            this.$http
                .post(`/${this.resource}/quick-price`, {
                    id: f.id, regular_price: f.regular, offer_price: offer,
                    compare_at_from: from, compare_at_until: until,
                })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success('Precio actualizado');
                        var row = f._row;
                        row.amount_sale_unit_price = response.data.sale_unit_price;
                        row.compare_at_price = response.data.compare_at_price;
                        row.compare_at_from = response.data.compare_at_from;
                        row.compare_at_until = response.data.compare_at_until;
                        row.sale_unit_price = 'S/ ' + parseFloat(response.data.sale_unit_price).toFixed(2);
                        this.offerDialogVisible = false;
                    } else {
                        this.$message.error(response.data.message || 'No se pudo actualizar');
                    }
                })
                .catch(() => this.$message.error('Error al actualizar precio'));
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
        // Un clic: publicar / retirar este producto en Saga Falabella. El switch
        // ya está optimista (v-model); si el backend falla lo revertimos. Al
        // volver, sincronizamos saga_status para que el chip 🔴🟡🟢 quede al día.
        toggleSaga(value, row) {
            this.sagaLoading = row.id;
            this.$http
                .post(`/items/saga-toggle`, { id: row.id, saga_enabled: value })
                .then(response => {
                    const d = response.data || {};
                    if (d.success) {
                        // Refleja el estado real devuelto por el backend.
                        row.saga_status = d.saga_status || null;
                        row.saga_enabled = !!d.saga_enabled;
                        d.warning
                            ? this.$message.warning(d.message)
                            : this.$message.success(d.message);
                    } else {
                        // No se aplicó: revertir el switch a su estado previo.
                        row.saga_enabled = !value;
                        this.$message.error(d.message || 'No se pudo actualizar Saga');
                    }
                })
                .catch(() => {
                    row.saga_enabled = !value;
                    this.$message.error('Error al actualizar Saga Falabella');
                })
                .finally(() => {
                    this.sagaLoading = null;
                });
        },
        loadMarketplaceStats() {
            this.$http
                .get(`/items/marketplace-stats`)
                .then(r => { if (r.data) this.mpStats = r.data; })
                .catch(() => { /* stats opcionales; si fallan, el card queda oculto */ });
        },
        // Solo necesitamos saber si hay 0 productos para decidir si renderizar
        // el welcome card. Limit 1 para mantener la query barata; total viene
        // en el meta de la paginación estándar de Laravel.
        loadItemsCount() {
            this.$http.get(`/items/records?limit=1&page=1`)
                .then(r => {
                    const total = (r.data && r.data.meta && r.data.meta.total) || (r.data && r.data.total) || 0
                    this.itemsCount = total
                })
                .catch(() => { this.itemsCount = null })
        },
        publishAllToMarketplace() {
            this.$confirm(
                'Se publicarán en ebaemy.com/marketplace todos los productos de tu tienda online que aún no estén publicados. ¿Continuar?',
                'Publicar todos en Marketplace',
                {
                    confirmButtonText: 'Sí, publicar todos',
                    cancelButtonText: 'Cancelar',
                    type: 'info'
                }
            ).then(() => {
                this.publishingAll = true;
                this.$http
                    .post('/items/marketplace-publish-all-store')
                    .then(response => {
                        const data = response.data || {};
                        if (data.success) {
                            if ((data.updated || 0) > 0) {
                                this.$message.success(data.message);
                            } else {
                                this.$message.info(data.message);
                            }
                            this.loadMarketplaceStats();
                            this.$eventHub.$emit('reloadData');
                        } else if (data.limit_reached) {
                            this.$alert(data.message, 'Límite de plan alcanzado', {
                                confirmButtonText: 'Entendido',
                                type: 'warning'
                            });
                        } else {
                            this.$message.error(data.message || 'No se pudo publicar');
                        }
                    })
                    .catch(() => {
                        this.$message.error('Error al publicar al marketplace');
                    })
                    .then(() => {
                        this.publishingAll = false;
                    });
            }).catch(() => { /* cancelado */ });
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
        // Despacha la opción elegida del dropdown "⋮" de cada fila.
        onRowActionCommand(command, row) {
            switch (command) {
                case 'erp':
                    window.location.href = '/items#edit-' + row.id;
                    break;
                case 'store':
                    if (row.slug) window.open('/item/' + row.slug, '_blank', 'noopener');
                    break;
                case 'marketplace':
                    window.open(
                        'https://ebaemy.com/marketplace?q=' + encodeURIComponent(row.description || row.name || ''),
                        '_blank', 'noopener'
                    );
                    break;
                case 'delete':
                    this.clickDelete(row.id);
                    break;
            }
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

<style scoped>
/* ── Banner informativo unificado ── */
.ie-info-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    padding: 7px 13px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 9px;
    flex-wrap: wrap;
}
.ie-info-banner__icon { font-size: 17px; line-height: 1; flex-shrink: 0; }
.ie-info-banner__text { flex: 1; min-width: 240px; }
.ie-info-banner__title {
    font-weight: 600;
    font-size: 13px;
    color: #065f46;
}
.ie-info-banner__hint {
    font-size: 11.5px;
    color: #047857;
    margin-top: 2px;
}
.ie-info-banner__cta {
    border-color: #10b981;
    color: #065f46;
    flex-shrink: 0;
}

/* ── Welcome card (estado vacío) ── */
.ie-welcome-card {
    background: linear-gradient(135deg, #fafbff 0%, #fef3ff 100%);
    border: 1px solid #e0e7ff;
    border-radius: 14px;
    padding: 36px 28px;
    margin-bottom: 18px;
    text-align: center;
    box-shadow: 0 4px 20px -8px rgba(99, 102, 241, .12);
}
.ie-welcome-card__icon {
    font-size: 48px;
    line-height: 1;
    margin-bottom: 12px;
}
.ie-welcome-card__title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 8px;
}
.ie-welcome-card__lead {
    font-size: 14px;
    color: #475569;
    max-width: 560px;
    margin: 0 auto 22px;
    line-height: 1.5;
}
.ie-welcome-card__lead strong {
    color: #4338ca;
    font-weight: 600;
}
.ie-welcome-card__cta { margin-bottom: 28px; }
.ie-welcome-card__cta .btn-lg {
    background: #4f46e5;
    border-color: #4338ca;
    color: #fff;
    font-weight: 600;
    padding: 12px 28px;
    font-size: 15px;
    border-radius: 10px;
    transition: transform .12s, box-shadow .12s;
}
.ie-welcome-card__cta .btn-lg:hover {
    background: #4338ca;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -6px rgba(79, 70, 229, .4);
}
.ie-welcome-card__tips {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 16px 24px;
    max-width: 760px;
    margin: 0 auto;
    padding-top: 18px;
    border-top: 1px dashed #c7d2fe;
}
.ie-welcome-card__tip {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: #475569;
}
.ie-welcome-card__tip-icon {
    font-size: 18px;
    line-height: 1;
}
.ie-welcome-card__tip strong { color: #1e293b; }

/* ── Header del page (h2 + breadcrumb + buttons) ── */
.items_ecommerce .page-header .right-wrapper { display: flex; flex-wrap: wrap; gap: 4px; }

/* ── Responsive ── */
@media (max-width: 767px) {
    .ie-info-banner {
        padding: 10px 12px;
        gap: 8px;
        margin-bottom: 10px;
    }
    .ie-info-banner__icon { font-size: 18px; }
    .ie-info-banner__title { font-size: 13px; }
    .ie-info-banner__hint { font-size: 11px; }
    .ie-info-banner__cta {
        width: 100%;
        text-align: center;
    }
    .ie-welcome-card {
        padding: 24px 16px;
        margin-bottom: 12px;
        border-radius: 10px;
    }
    .ie-welcome-card__icon { font-size: 38px; }
    .ie-welcome-card__title { font-size: 18px; }
    .ie-welcome-card__lead { font-size: 13px; margin-bottom: 16px; }
    .ie-welcome-card__cta .btn-lg {
        width: 100%;
        padding: 12px;
        font-size: 14px;
    }
    .ie-welcome-card__tips {
        gap: 10px;
        padding-top: 14px;
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
    /* Header buttons stack vertical en mobile */
    .items_ecommerce .page-header { display: flex; flex-direction: column; align-items: stretch; gap: 8px; }
    .items_ecommerce .page-header .right-wrapper {
        width: 100%;
        flex-direction: column;
        gap: 6px;
    }
    .items_ecommerce .page-header .right-wrapper > * { width: 100%; }
    .items_ecommerce .page-header .right-wrapper .btn { width: 100%; margin: 0 !important; }
    .items_ecommerce .page-header .right-wrapper span { width: 100%; display: block; }
}
</style>
