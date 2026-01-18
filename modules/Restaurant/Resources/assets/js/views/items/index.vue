<template>
    <div style="width: 100%;" class="me-0">
        <div class="page-header pr-0">
            <h2>
                <a href="/restaurant/list/items">
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-tools-kitchen-2"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M19 3v12h-5c-.023 -3.681 .184 -7.406 5 -12zm0 12v6h-1v-3m-10 -14v17m-3 -17v3a3 3 0 1 0 6 0v-3"
                        />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Productos</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <template>
                    <button
                        type="button"
                        class="btn btn-custom btn-sm  mt-2 me-2"
                        @click.prevent="clickImport()"
                    >
                        <i class="fa fa-upload"></i> Importar
                    </button>
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
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
        <h3 class="my-0">Listado de productos Restaurante</h3>
      </div> -->
            <div class="card-body">
                <data-table
                    :resource="'items'"
                    :restaurant="restaurant"
                    :sort-field="sortField"
                    :sort-direction="sortDirection"
                    :custom-list-columns="customListColumns"
                    @sort-change="handleSortChange">
                    <tr slot="heading" width="100%" slot-scope="{ sort, showRestaurantStock }">
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
                        <th class="text-start">Categoría</th>
                        <th class="text-center">Visible en Restaurant</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr></tr>
                    <tr slot-scope="{ index, row, showRestaurantStock }">
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.internal_id }}</td>
                        <td>{{ row.unit_type_id }}</td>
                        <td class="text-center">
                            <a @click="viewImages(row)" href="#">
                                <img
                                    :src="row.image_url_small"
                                    style="object-fit: contain;"
                                    alt
                                    width="32px"
                                    height="32px"
                                />
                            </a>
                        </td>
                        <td>{{ row.description }}</td>
                        <td class="text-end">{{ row.sale_unit_price }}</td>
                        <td
                            class="text-end"
                            :class="{
                                'text-danger': row.has_supplies ? row.restaurant_stock <= 0 : (row.has_sets ? row.restaurant_stock <= 0 : stock(row.warehouses) <= 0),
                                'text-warning': row.has_supplies ? row.restaurant_stock > 0 && row.restaurant_stock <= 5 : (row.has_sets ? row.restaurant_stock > 0 && row.restaurant_stock <= 5 : stock(row.warehouses) > 0 && stock(row.warehouses) <=5),
                                
                            }"
                        >
                            <span v-if="row.is_dish" class="ms-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-bowl-spoon">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M20 10a2 2 0 0 1 2 2v.5c0 1.694 -2.247 5.49 -3.983 6.983l-.017 .013v.504a2 2 0 0 1 -1.85 1.995l-.15 .005h-8a2 2 0 0 1 -2 -2v-.496l-.065 -.053c-1.76 -1.496 -3.794 -4.965 -3.928 -6.77l-.007 -.181v-.5a2 2 0 0 1 2 -2z"/>
                                    <path d="M8 2c1.71 0 3.237 .787 3.785 2h8.215a1 1 0 0 1 0 2l-8.216 .001c-.548 1.213 -2.074 1.999 -3.784 1.999c-2.144 0 -4 -1.237 -4 -3s1.856 -3 4 -3"/>
                                </svg>
                            </span>
                            <span v-if="row.has_sets" class="ms-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 640 640">
                                    <path d="M465.4 192L431.1 144L209 144L174.7 192L465.4 192zM96 212.5C96 199.2 100.2 186.2 107.9 175.3L156.9 106.8C168.9 90 188.3 80 208.9 80L431 80C451.7 80 471.1 90 483.1 106.8L532 175.3C539.8 186.2 543.9 199.2 543.9 212.5L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 212.5z"/>
                                </svg>
                            </span>
                            {{ row.has_supplies ? row.restaurant_stock : ( row.has_sets ? row.restaurant_stock : stock(row.warehouses) ) }}
                        </td>
                        <td class="text-start">
                            {{ row.category_description }}
                        </td>
                        <td class="text-center">
                            <el-tooltip
                                v-if="!row.has_supplies && row.unit_type_id === 'ZZ'"
                                content="Este plato no tiene insumos"
                                placement="top"
                            >
                                <span>
                                    <el-checkbox
                                        size="medium"
                                        @change="visibleRestaurant($event, row.id)"
                                        v-model="row.apply_restaurant"
                                        :disabled="!row.has_supplies && row.unit_type_id === 'ZZ'"
                                    ></el-checkbox>
                                </span>
                            </el-tooltip>

                            <template v-else>
                                <el-checkbox
                                    size="medium"
                                    @change="visibleRestaurant($event, row.id)"
                                    v-model="row.apply_restaurant"
                                    :disabled="!row.has_supplies && row.unit_type_id === 'ZZ'"
                                ></el-checkbox>
                            </template>
                        </td>
                        <td class="text-end">
                            <template>
                                <!-- v-if="typeUser === 'admin'" -->
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-info me-2"
                                    @click.prevent="clickCreate(row.id)"
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-danger"
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
                :fromRestaurant="true"
            ></items-form>

            <items-import :showDialog.sync="showImportDialog"></items-import>

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
</style>
<script>
import ItemsForm from "@views/items_ecommerce/form.vue";
import WarehousesDetail from "@views/items_ecommerce/partials/warehouses.vue";
import ItemsImport from "./import.vue";
import DataTable from "@components/DataTable.vue";
import { deletable } from "@mixins/deletable";

export default {
    props: [],
    mixins: [deletable],
    components: { ItemsForm, DataTable, WarehousesDetail, ItemsImport },
    data() {
        return {
            showDialog: false,
            showImportDialog: false,
            showWarehousesDetail: false,
            showImageDetail: false,
            resource: "restaurant",
            recordId: null,
            warehousesDetail: [],
            recordImages: {
                image_url: "",
                image_url_medium: "",
                image_url_small: ""
            },
            restaurant: true,
            sortField: localStorage.getItem('itemSortField') || 'id',
            sortDirection: localStorage.getItem('itemSortDirection') || 'desc',
            customListColumns: {
                all: 'Todos',
                visible: 'Visibles',
                hidden: 'Ocultos',
                with_supplies: 'Con insumos'
            }
        };
    },
    created() {},
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
        visibleRestaurant(apply_restaurant, id) {
            this.$http
                .post(`/${this.resource}/items/visible`, {
                    id,
                    apply_restaurant
                })
                .then(response => {
                    if (response.data.success) {
                        if (apply_restaurant) {
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
            this.destroy(`/items/${id}`).then(() =>
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
