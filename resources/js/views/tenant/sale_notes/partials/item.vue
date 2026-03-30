<template>
    <el-dialog
        :close-on-click-modal="false"
        :title="titleDialog"
        :visible="showDialog"
        top="7vh"
        @close="close"
        @open="create"
    >
        <Keypress key-event="keyup" @success="checkKey" />

        <form autocomplete="off" @submit.prevent="clickAddItem">
            <div class="form-body">
                <div class="row">
                    <div class="col-12">
                        <el-checkbox
                            v-model="various_item"
                            @change="setVariousItem"
                            :disabled="recordItem != null"
                            >Producto manual
                        </el-checkbox>
                    </div>
                    <div
                        class="col-md-7 col-lg-7 col-xl-7 col-sm-7 product-search-model"
                    >
                        <template v-if="various_item">
                            <div class="form-group">
                                <label class="control-label"
                                    >Descripción del Producto/Servicio</label
                                >
                                <el-input
                                    v-model="form.item.description"
                                    ref="inputItemDescription"
                                    maxlength="500"
                                >
                                </el-input>
                            </div>
                        </template>
                        <template v-else>
                            <el-tooltip
                                slot="append"
                                :disabled="isUpdateItem"
                                class="item"
                                content="Ver Stock del Producto"
                                effect="dark"
                                placement="bottom"
                            >
                                <el-button
                                    :disabled="isUpdateItem"
                                    @click.prevent="clickWarehouseDetail()"
                                >
                                    <i class="fa fa-search"></i>
                                </el-button>
                            </el-tooltip>
                            <div
                                id="custom-select"
                                :class="{ 'has-danger': errors.item_id }"
                                class="form-group more-width-input"
                            >
                                <label class="control-label d-flex align-items-center">
                                    Producto/Servicio
                                    <span
                                        v-if="can_add_new_product"
                                        class="btn-add-new-product"
                                        @click.prevent="
                                            showDialogNewItem = true
                                        "
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                    </span>
                                </label>

                                <template
                                    v-if="!search_item_by_barcode"
                                    id="select-append"
                                >
                                    <el-input id="custom-input" class="input-search-product">
                                        <el-select
                                            id="select-width"
                                            ref="selectSearchNormal"
                                            slot="prepend"
                                            tabindex="1"
                                            v-model="form.item_id"
                                            :disabled="isUpdateItem"
                                            :loading="loading_search"
                                            :remote-method="searchRemoteItems"
                                            filterable
                                            placeholder="Buscar"
                                            popper-class="el-select-items"
                                            remote
                                            @change="changeItem"
                                            @focus="focusSelectItem"
                                            @visible-change="focusTotalItem"
                                        >
                                            <el-tooltip
                                                v-for="option in items"
                                                :key="option.id"
                                                placement="left"
                                            >
                                                <div
                                                    slot="content"
                                                    v-html="
                                                        ItemSlotTooltipView(
                                                            option
                                                        )
                                                    "
                                                ></div>
                                                <el-option
                                                    :label="
                                                        ItemOptionDescriptionView(
                                                            option
                                                        )
                                                    "
                                                    :value="option.id"
                                                ></el-option>
                                            </el-tooltip>
                                            <template slot="empty">
                                                <p v-if="loading_search" class="el-select-dropdown__empty">
                                                    Cargando...
                                                </p>
                                            
                                                <p v-else class="el-select-dropdown__empty">
                                                    No se encontraron resultados
                                                </p>
                                            
                                                <div
                                                    v-if="!loading_search"
                                                    class="el-select-dropdown__item new-option"
                                                    @click.stop="openNewItemDialog"
                                                >
                                                    <span>{{ itemSearchTerm ? `Crear producto "${itemSearchTerm}"` : 'Crear producto' }}</span>
                                                </div>
                                            </template>
                                        </el-select>
                                    </el-input>
                                </template>
                                <template v-else>
                                    <el-input id="custom-input" class="input-search-product">
                                        <el-select
                                            id="select-width"
                                            ref="selectBarcode"
                                            slot="prepend"
                                            v-model="form.item_id"
                                            :disabled="isUpdateItem"
                                            :loading="loading_search"
                                            :remote-method="searchRemoteItems"
                                            filterable
                                            placeholder="Buscar"
                                            popper-class="el-select-items"
                                            remote
                                            value-key="id"
                                            @change="changeItem"
                                        >
                                            <el-option
                                                v-for="option in items"
                                                :key="option.id"
                                                :label="option.full_description"
                                                :value="option.id"
                                            ></el-option>
                                        </el-select>
                                        <el-tooltip
                                            slot="append"
                                            :disabled="isUpdateItem"
                                            class="item"
                                            content="Ver Stock del Producto"
                                            effect="dark"
                                            placement="bottom"
                                        >
                                            <el-button
                                                :disabled="isUpdateItem"
                                                @click.prevent="
                                                    clickWarehouseDetail()
                                                "
                                            >
                                                <i class="fa fa-search"></i>
                                            </el-button>
                                        </el-tooltip>
                                        <template slot="empty">
                                            <p v-if="loading_search" class="el-select-dropdown__empty">
                                                Cargando...
                                            </p>
                                        
                                            <p v-else class="el-select-dropdown__empty">
                                                No se encontraron resultados
                                            </p>
                                        
                                            <div
                                                v-if="!loading_search"
                                                class="el-select-dropdown__item new-option"
                                                @click.stop="openNewItemDialog"
                                            >
                                                <span>{{ itemSearchTerm ? `Crear producto "${itemSearchTerm}"` : 'Crear producto' }}</span>
                                            </div>
                                        </template>
                                    </el-input>
                                </template>

                                <template v-if="!is_client">
                                    <el-checkbox
                                        v-model="search_item_by_barcode"
                                        :disabled="isUpdateItem"
                                        >Buscar por código de barras
                                    </el-checkbox>
                                    <br />
                                </template>
                                <el-checkbox
                                    v-model="form.has_plastic_bag_taxes"
                                    :disabled="isEditItemNote"
                                    >Impuesto a la Bolsa Plástica
                                </el-checkbox>
                                <small
                                    v-if="errors.item_id"
                                    class="form-control-feedback"
                                    v-text="errors.item_id[0]"
                                ></small>

                                <!-- Stock inline por almacén — click para seleccionar -->
                                <div v-if="form.item_id && form.item && form.item.warehouses && form.item.warehouses.length"
                                     style="margin-top:5px; display:flex; flex-wrap:wrap; gap:4px;">
                                    <span
                                        v-for="wh in form.item.warehouses"
                                        :key="wh.warehouse_id"
                                        :style="{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: '4px',
                                            padding: '2px 8px',
                                            borderRadius: '12px',
                                            fontSize: '11px',
                                            fontWeight: '600',
                                            border: '1px solid',
                                            borderColor: wh.checked ? '#409EFF' : '#dcdfe6',
                                            color: wh.checked ? '#409EFF' : '#606266',
                                            background: wh.checked ? '#ecf5ff' : '#f5f7fa',
                                            cursor: 'pointer'
                                        }"
                                        :title="'Seleccionar: ' + wh.warehouse_description"
                                        @click="selectWarehouseInline(wh)"
                                    >
                                        <i class="fa fa-warehouse" style="font-size:10px;opacity:.7"></i>
                                        {{ wh.warehouse_description }}:
                                        <strong v-if="wh.registered === false" style="color:#aaa;font-style:italic;font-weight:400">No registrado</strong>
                                        <strong v-else :style="{color: wh.stock > 0 ? '#67C23A' : '#F56C6C'}">{{ parseFloat(wh.stock) }}</strong>
                                    </span>
                                </div>

                            </div>
                        </template>
                    </div>
                    <!-- ── Selector de variantes ─────────────────────────── -->
                    <div class="col-12" v-if="form.item_id && form.item.has_variants && form.item.item_options && form.item.item_options.length">
                        <div class="form-group" style="background:#f8f9ff;border:1px solid #d9e1ff;border-radius:8px;padding:12px 14px;margin-bottom:8px;">
                            <div style="font-weight:600;font-size:12px;color:#5a6acf;margin-bottom:10px;letter-spacing:.5px;">
                                VARIANTE DEL PRODUCTO
                            </div>
                            <div v-for="opt in form.item.item_options" :key="opt.id" style="margin-bottom:10px;">
                                <div style="font-size:12px;font-weight:600;color:#606266;margin-bottom:6px;">
                                    {{ opt.name }}
                                    <span v-if="selectedVariantOptions[opt.id]" style="font-weight:400;color:#409EFF;">
                                        — {{ selectedVariantOptions[opt.id].value }}
                                    </span>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                    <button
                                        v-for="val in opt.values"
                                        :key="val.id"
                                        type="button"
                                        @click="selectVariantOption(opt.id, val)"
                                        :style="{
                                            padding: '4px 12px',
                                            borderRadius: '20px',
                                            border: selectedVariantOptions[opt.id] && selectedVariantOptions[opt.id].id === val.id ? '2px solid #409EFF' : '1px solid #dcdfe6',
                                            background: selectedVariantOptions[opt.id] && selectedVariantOptions[opt.id].id === val.id ? '#ecf5ff' : '#fff',
                                            color: selectedVariantOptions[opt.id] && selectedVariantOptions[opt.id].id === val.id ? '#409EFF' : '#606266',
                                            fontWeight: '600',
                                            fontSize: '12px',
                                            cursor: 'pointer',
                                        }"
                                    >{{ val.value }}</button>
                                </div>
                            </div>
                            <div v-if="resolvedVariant" style="margin-top:6px;padding:6px 10px;background:#f0f9eb;border-radius:6px;display:flex;align-items:center;gap:8px;">
                                <i class="el-icon-check" style="color:#67C23A;font-weight:700;"></i>
                                <span style="font-size:12px;color:#67C23A;font-weight:600;">{{ resolvedVariant.display_name }}</span>
                                <span v-if="resolvedVariant.sale_unit_price" style="font-size:12px;color:#606266;margin-left:auto;">
                                    S/ {{ parseFloat(resolvedVariant.sale_unit_price).toFixed(2) }}
                                </span>
                            </div>
                            <el-alert v-if="variantNotFound" type="warning" :closable="false"
                                      title="Combinación no disponible. Elige otra opción." show-icon style="margin-top:6px;" />
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div
                            :class="{
                                'has-danger': errors.affectation_igv_type_id
                            }"
                            class="form-group"
                        >
                            <label class="control-label">Afectación Igv</label>
                            <el-select
                                v-model="form.affectation_igv_type_id"
                                :disabled="
                                    !change_affectation_igv_type_id ||
                                        isUpdateItem
                                "
                                filterable
                            >
                                <el-option
                                    v-for="option in affectation_igv_types"
                                    :key="option.id"
                                    :label="option.description"
                                    :value="option.id"
                                ></el-option>
                            </el-select>
                            <el-checkbox
                                v-model="change_affectation_igv_type_id"
                                :disabled="isUpdateItem"
                            >
                                Editar
                            </el-checkbox>
                            <small
                                v-if="errors.affectation_igv_type_id"
                                class="form-control-feedback"
                                v-text="errors.affectation_igv_type_id[0]"
                            ></small>
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-4">
                        <div
                            :class="{ 'has-danger': errors.quantity }"
                            class="form-group"
                        >
                            <label class="control-label">Cantidad</label>
                            <el-input-number
                                v-model="form.quantity"
                                @change="calculateTotal"
                                :disabled="form.item.calculate_quantity"
                                :min="0.01"
                            ></el-input-number>
                            <small
                                v-if="errors.quantity"
                                class="form-control-feedback"
                                v-text="errors.quantity[0]"
                            ></small>
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-4">
                        <div
                            :class="{ 'has-danger': errors.unit_price_value }"
                            class="form-group"
                        >
                            <label class="control-label">Precio Unitario</label>

                            <template
                                v-if="
                                    applyChangeCurrencyItem &&
                                        changeCurrencyFromParent
                                "
                            >
                                <el-input
                                    tabindex="3"
                                    v-model="form.unit_price_value"
                                    class="currency-container"
                                    :disabled="
                                        !hasPermissionEditItemPrices(
                                            permissionEditItemPrices
                                        )
                                    "
                                    @input="calculateQuantity"
                                >
                                    <template
                                        v-if="form.item.currency_type_symbol"
                                    >
                                        <el-select
                                            slot="prepend"
                                            v-model="form.item.currency_type_id"
                                            class="custom-change-select-currency"
                                        >
                                            <el-option
                                                v-for="option in currencyTypes"
                                                :key="option.id"
                                                :label="option.symbol"
                                                :value="option.id"
                                            ></el-option>
                                        </el-select>
                                    </template>
                                </el-input>
                            </template>
                            <template v-else>
                                <el-input
                                    tabindex="3"
                                    v-model="form.unit_price_value"
                                    class="currency-container"
                                    :disabled="
                                        !hasPermissionEditItemPrices(
                                            permissionEditItemPrices
                                        )
                                    "
                                    @input="calculateQuantity"
                                >
                                    <template
                                        v-if="form.item.currency_type_symbol"
                                        slot="prepend"
                                    >
                                        {{ form.item.currency_type_symbol }}
                                    </template>
                                </el-input>
                            </template>

                            <small
                                v-if="errors.unit_price_value"
                                class="form-control-feedback"
                                v-text="errors.unit_price_value[0]"
                            ></small>
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-4">
                        <div class="form-group">
                            <label class="control-label">Total</label>
                            <el-input
                                v-model="readonly_total"
                                readonly
                                @input="calculateTotal"
                            ></el-input>
                        </div>
                    </div>

                    <div
                        v-if="showLots"
                        class="col-md-3 col-sm-3"
                        style="padding-top: 1%;"
                    >
                        <a
                            class="text-center font-weight-bold text-info"
                            href="#"
                            @click.prevent="clickLotGroup"
                            >[&#10004; Seleccionar lote]</a
                        >
                    </div>

                    <div
                        v-if="showSeries"
                        class="col-md-3 col-sm-3"
                        style="padding-top: 1%;"
                    >
                        <a
                            class="text-center font-weight-bold text-info"
                            href="#"
                            @click.prevent="clickSelectLots"
                            >[&#10004; Seleccionar series]</a
                        >
                    </div>

                    <div
                        v-show="form.item.calculate_quantity"
                        class="col-md-3 col-sm-6"
                    >
                        <div
                            :class="{ 'has-danger': errors.total_item }"
                            class="form-group"
                        >
                            <label class="control-label"
                                >Total venta producto</label
                            >
                            <el-input
                                ref="total_item"
                                v-model="total_item"
                                :min="0.01"
                                @input="calculateQuantity"
                            >
                                <template
                                    v-if="form.item.currency_type_symbol"
                                    slot="prepend"
                                >
                                    {{ form.item.currency_type_symbol }}
                                </template>
                            </el-input>
                            <small
                                v-if="errors.total_item"
                                class="form-control-feedback"
                                v-text="errors.total_item[0]"
                            ></small>
                        </div>
                    </div>
                    <div
                        v-if="config.edit_name_product"
                        class="col-md-12 col-sm-12 mt-2"
                    >
                        <div class="form-group">
                            <label class="control-label">
                                <template
                                    v-if="canAddDescriptionToDocumentItem"
                                >
                                    Reemplazar nombre
                                </template>
                                <template v-else>
                                    Nombre producto en PDF
                                </template>
                            </label>
                            <vue-ckeditor
                                v-model="form.name_product_pdf"
                                :editors="editors"
                                type="classic"
                            ></vue-ckeditor>
                        </div>
                    </div>
                    <!--<div class="col-md-6 mt-4" v-if="form.item_id && form.item.series_enabled">
                        <a href="#"  class="text-center font-weight-bold text-info" @click.prevent="clickSelectLots">[&#10004; Seleccionar series]</a>
                    </div> -->
                    <template v-if="!is_client">
                        <div
                            v-if="form.item_unit_types.length > 0"
                            class="col-md-12"
                        >
                            <div class="table-responsive" style="margin:3px">
                                <h5 class="separator-title">
                                    Lista de Precios
                                    <el-tooltip
                                        class="item"
                                        content="Aplica para realizar compra/venta en presentacion de diferentes precios y/o cantidades"
                                        effect="dark"
                                        placement="top"
                                    >
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </h5>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Unidad</th>
                                            <th class="text-center">
                                                Descripción
                                            </th>
                                            <th class="text-center">Factor</th>
                                            <th class="text-center">
                                                {{ config.price1_label }}
                                            </th>
                                            <th class="text-center">
                                                {{ config.price2_label }}
                                            </th>
                                            <th class="text-center">
                                                {{ config.price3_label }}
                                            </th>
                                            <th class="text-center">
                                                Precio Default
                                            </th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row,
                                            index) in form.item_unit_types"
                                            :key="index"
                                        >
                                            <td class="text-center">
                                                {{ row.unit_type_id }}
                                            </td>
                                            <td class="text-center">
                                                {{ row.description }}
                                            </td>
                                            <td class="text-center">
                                                {{ row.quantity_unit }}
                                            </td>
                                            <td class="text-center">
                                                {{ row.price1 }}
                                            </td>
                                            <td class="text-center">
                                                {{ row.price2 }}
                                            </td>
                                            <td class="text-center">
                                                {{ row.price3 }}
                                            </td>
                                            <td class="text-center">
                                                Precio {{ row.price_default }}
                                            </td>
                                            <td
                                                class="series-table-actions text-end"
                                            >
                                                <button
                                                    :class="
                                                        getSelectedClass(row)
                                                    "
                                                    class="btn waves-effect waves-light btn-xs"
                                                    type="button"
                                                    @click.prevent="
                                                        selectedPrice(row)
                                                    "
                                                >
                                                    <i
                                                        class="el-icon-check"
                                                    ></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12 mt-2" v-if="!isUpdateItem">
                            <el-collapse v-model="activePanel">
                                <el-collapse-item
                                    :disabled="isUpdateItem"
                                    name="1"
                                    title="+ Agregar Descuentos/Cargos/Atributos especiales"
                                >
                                    <div v-if="discount_types.length > 0">
                                        <label class="control-label">
                                            Descuentos
                                            <a
                                                href="#"
                                                @click.prevent="
                                                    clickAddDiscount
                                                "
                                                >[+ Agregar]</a
                                            >
                                        </label>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Descripción</th>
                                                    <th>Porcentaje</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row,
                                                    index) in form.discounts"
                                                    :key="index"
                                                >
                                                    <td>
                                                        <el-select
                                                            v-model="
                                                                row.discount_type_id
                                                            "
                                                            @change="
                                                                changeDiscountType(
                                                                    index
                                                                )
                                                            "
                                                        >
                                                            <el-option
                                                                v-for="option in discount_types"
                                                                :key="option.id"
                                                                :label="
                                                                    option.description
                                                                "
                                                                :value="
                                                                    option.id
                                                                "
                                                            ></el-option>
                                                        </el-select>
                                                    </td>
                                                    <td>
                                                        <el-input
                                                            v-model="
                                                                row.description
                                                            "
                                                        ></el-input>
                                                    </td>
                                                    <td>
                                                        <el-checkbox
                                                            v-model="
                                                                row.is_amount
                                                            "
                                                            >Ingresar monto fijo
                                                        </el-checkbox>
                                                        <br />
                                                        <el-input
                                                            v-model="
                                                                row.percentage
                                                            "
                                                        ></el-input>
                                                    </td>
                                                    <td>
                                                        <button
                                                            class="btn btn-danger"
                                                            type="button"
                                                            @click.prevent="
                                                                clickRemoveDiscount(
                                                                    index
                                                                )
                                                            "
                                                        >
                                                            x
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-if="charge_types.length > 0">
                                        <label class="control-label">
                                            Cargos
                                            <a
                                                href="#"
                                                @click.prevent="clickAddCharge"
                                                >[+ Agregar]</a
                                            >
                                        </label>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Descripción</th>
                                                    <th>Porcentaje</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row,
                                                    index) in form.charges"
                                                    :key="index"
                                                >
                                                    <td>
                                                        <el-select
                                                            v-model="
                                                                row.charge_type_id
                                                            "
                                                            @change="
                                                                changeChargeType(
                                                                    index
                                                                )
                                                            "
                                                        >
                                                            <el-option
                                                                v-for="option in charge_types"
                                                                :key="option.id"
                                                                :label="
                                                                    option.description
                                                                "
                                                                :value="
                                                                    option.id
                                                                "
                                                            ></el-option>
                                                        </el-select>
                                                    </td>
                                                    <td>
                                                        <el-input
                                                            v-model="
                                                                row.description
                                                            "
                                                        ></el-input>
                                                    </td>
                                                    <td>
                                                        <el-input
                                                            v-model="
                                                                row.percentage
                                                            "
                                                        ></el-input>
                                                    </td>
                                                    <td>
                                                        <button
                                                            class="btn btn-danger"
                                                            type="button"
                                                            @click.prevent="
                                                                clickRemoveCharge(
                                                                    index
                                                                )
                                                            "
                                                        >
                                                            x
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-if="attribute_types.length > 0">
                                        <label class="control-label">
                                            Atributos
                                            <a
                                                href="#"
                                                @click.prevent="
                                                    clickAddAttribute
                                                "
                                                >[+ Agregar]</a
                                            >
                                        </label>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Descripción</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row,
                                                    index) in form.attributes"
                                                    :key="index"
                                                >
                                                    <td>
                                                        <el-select
                                                            v-model="
                                                                row.attribute_type_id
                                                            "
                                                            filterable
                                                            @change="
                                                                changeAttributeType(
                                                                    index
                                                                )
                                                            "
                                                        >
                                                            <el-option
                                                                v-for="option in attribute_types"
                                                                :key="option.id"
                                                                :label="
                                                                    option.description
                                                                "
                                                                :value="
                                                                    option.id
                                                                "
                                                            ></el-option>
                                                        </el-select>
                                                    </td>
                                                    <td>
                                                        <el-input
                                                            v-model="row.value"
                                                            @input="
                                                                inputAttribute(
                                                                    index
                                                                )
                                                            "
                                                        ></el-input>
                                                    </td>
                                                    <td>
                                                        <button
                                                            class="btn btn-danger"
                                                            type="button"
                                                            @click.prevent="
                                                                clickRemoveAttribute(
                                                                    index
                                                                )
                                                            "
                                                        >
                                                            x
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </el-collapse-item>
                            </el-collapse>
                        </div>
                    </template>
                </div>
            </div>

            <div class="form-actions text-end pt-2">
                <el-popover
                    placement="top-start"
                    title="Acceso directo"
                    width="145"
                    trigger="hover"
                    content="Presiona ESC"
                >
                    <el-button
                        slot="reference"
                        class="second-buton me-2"
                        @click.prevent="close()"
                    >
                        Cerrar
                    </el-button>
                </el-popover>
                <el-button
                    v-if="form.item_id"
                    class="add"
                    native-type="submit"
                    type="primary"
                >
                    {{ titleAction }}
                    <!-- Agregar -->
                </el-button>
            </div>
        </form>
        <item-form
            :external="true"
            :showDialog.sync="showDialogNewItem"
            :input_item="itemSearchTerm"
        ></item-form>

        <warehouses-detail
            :isUpdateWarehouseId="isUpdateWarehouseId"
            :showDialog.sync="showWarehousesDetail"
            :warehouses="warehousesDetail"
        >
        </warehouses-detail>

        <lots-group
            :lots_group="form.lots_group"
            :quantity="form.quantity"
            :showDialog.sync="showDialogLots"
            :isUpdateItem="isUpdateItem"
            :oldSelectedLotsGroup="old_selected_lots_group"
            @addRowLotGroup="addRowLotGroup"
        >
        </lots-group>

        <select-lots-form
            :saleNoteItemId="form.sale_note_item_id"
            :itemId="form.item_id"
            :lots="lots"
            :quantity="form.quantity"
            :showDialog.sync="showDialogSelectLots"
            @addRowSelectLot="addRowSelectLot"
        >
        </select-lots-form>

    </el-dialog>
</template>
<style>
.el-select-dropdown {
    max-width: 80% !important;
    margin-right: 5% !important;
}
</style>

<script>
import ItemForm from "../../items/form.vue";
import LotsGroup from "./lots_group.vue";

import { calculateRowItem } from "../../../../helpers/functions";
import WarehousesDetail from "../../documents/partials/select_warehouses.vue";
import SelectLotsForm from "../../documents/partials/lots.vue";

import ClassicEditor from "@ckeditor/ckeditor5-build-classic";
import VueCkeditor from "vue-ckeditor5";
import { mapActions, mapState } from "vuex/dist/vuex.mjs";
import {
    ItemOptionDescription,
    ItemSlotTooltip
} from "../../../../helpers/modal_item";
import Keypress from "vue-keypress";
import { checkPermissionEditPrices } from "@mixins/check-permission-edit-prices";

export default {
    props: [
        "recordItem",
        "showDialog",
        "operationTypeId",
        "currencyTypeIdActive",
        "exchangeRateSale",
        "typeUser",
        "isEditItemNote",
        "configuration",
        "documentTypeId",
        "noteCreditOrDebitTypeId",
        "percentageIgv",
        "currencyTypes",
        "showOptionChangeCurrency",
        "permissionEditItemPrices",
        "selectedOptionPrice"
    ],
    components: {
        ItemForm,
        WarehousesDetail,
        LotsGroup,
        Keypress,
        SelectLotsForm,
        "vue-ckeditor": VueCkeditor.component
    },
    mixins: [checkPermissionEditPrices],
    data() {
        return {
            extra_temp: undefined,
            can_add_new_product: false,
            loading_search: false,
            titleAction: "",
            is_client: false,
            validate_stock_add_item: false,
            titleDialog: "Agregar Producto o Servicio",
            resource: "sale-notes",
            // ── Variantes ──────────────────────────────────────────────────
            selectedVariantOptions: {},   // { optionId: { id, value } }
            resolvedVariant: null,        // variante encontrada
            variantNotFound: false,
            showDialogNewItem: false,
            has_list_prices: false,
            errors: {},
            form: {},
            all_items: [],
            items: [],
            operation_types: [],
            all_affectation_igv_types: [],
            aux_items: [],
            affectation_igv_types: [],
            system_isc_types: [],
            discount_types: [],
            charge_types: [],
            attribute_types: [],
            use_price: 1,
            change_affectation_igv_type_id: false,
            activePanel: 0,
            total_item: 0,
            item_unit_types: [],
            item_unit_type: {},
            showWarehousesDetail: false,
            warehousesDetail: [],
            showListStock: false,
            search_item_by_barcode: false,
            isUpdateWarehouseId: null,
            showDialogLots: false,
            showDialogSelectLots: false,
            lots: [],
            editors: {
                classic: ClassicEditor
            },
            value1: "hello",
            readonly_total: 0,
            old_selected_lots_group: [],
            various_item: false,
            various_item_barcode: "VARIOUS_ITEM",
            itemSearchTerm: '',
            //item_unit_type: {}
        };
    },
    watch: {
        showDialog(newVal) {
            if (newVal) {
                this.itemSearchTerm = ''
            }
        }
    },
    created() {
        this.loadConfiguration();
        this.$store.commit("setConfiguration", this.configuration);
        this.initForm();
        this.$eventHub.$on("reloadDataItems", item_id => {
            this.reloadDataItems(item_id);
            this.itemSearchTerm = ''
        });
    },
    mounted() {
        this.getTables();

        this.$eventHub.$on("selectWarehouseId", warehouse_id => {
            this.form.warehouse_id = warehouse_id;
        });
        this.canCreateProduct();
    },
    computed: {
        ...mapState(["config"]),
        showLots() {
            // if (
            //     this.form.item_id &&
            //     this.form.item.lots_enabled &&
            //     this.form.lots_group.length > 0
            // )

            if (this.form.item_id && this.form.item.lots_enabled) {
                return true;
            }

            return false;
        },
        showSeries() {
            if (this.form.item_id && this.form.item.series_enabled) {
                return true;
            }
            return false;
        },
        documentItem() {
            if (
                this.recordItem !== undefined &&
                this.recordItem !== null &&
                this.recordItem.id !== undefined &&
                this.recordItem.id !== 0
            ) {
                this.form.document_item_id = this.recordItem.id;
                return this.recordItem.id;
            }
            return this.form.document_item_id;
        },
        edit_unit_price() {
            if (this.typeUser === "admin") {
                return true;
            }
            if (this.typeUser === "seller") {
                return this.config.allow_edit_unit_price_to_seller;
            }
            return false;
        },
        isUpdateItem() {
            return !_.isEmpty(this.recordItem);
        },
        enabledSearchFactoryCodeItems() {
            if (this.configuration)
                return this.configuration.search_factory_code_items ? 1 : 0;
            return 0;
        },
        applyChangeCurrencyItem() {
            if (this.configuration)
                return this.configuration.change_currency_item;
            return false;
        },
        changeCurrencyFromParent() {
            return (
                this.showOptionChangeCurrency !== undefined &&
                this.showOptionChangeCurrency &&
                this.currencyTypes !== undefined &&
                Array.isArray(this.currencyTypes)
            );
        },
        canAddDescriptionToDocumentItem() {
            if (this.configuration)
                return this.configuration.add_description_to_document_item;

            return false;
        }
    },
    methods: {
        async addItemQuickSale(item, operation_type_id) {
            this.form.item_id = item.id;
            this.items = [{ ...item }];

            await this.changeItem();
            await this.generalSleep(500);
            const add_item = await this.clickAddItem();

            if (add_item !== null && add_item !== undefined) return add_item;

            throw new Error("No se pudo agregar el producto.");
        },
        ...mapActions(["loadConfiguration"]),
        hasAttributes() {
            if (
                this.form.item !== undefined &&
                this.form.item.attributes !== undefined &&
                this.form.item.attributes !== null &&
                this.form.item.attributes.length > 0
            ) {
                return true;
            }

            return false;
        },
        ItemSlotTooltipView(item) {
            return ItemSlotTooltip(item);
        },
        ItemOptionDescriptionView(item) {
            return ItemOptionDescription(item);
        },
        getTables() {
            this.$http.get(`/${this.resource}/item/tables`).then(response => {
                let data = response.data;
                this.all_items = data.items;
                // this.items =data.items
                this.operation_types = data.operation_types;
                this.all_affectation_igv_types = data.affectation_igv_types;
                this.affectation_igv_types = data.affectation_igv_types;
                this.system_isc_types = data.system_isc_types;
                this.discount_types = data.discount_types;
                this.charge_types = data.charge_types;
                this.attribute_types = data.attribute_types;
                this.is_client = data.is_client;
                this.validate_stock_add_item = data.validate_stock_add_item || false;
                this.filterItems();
            });
        },

        canCreateProduct() {
            if (this.typeUser === "admin") {
                this.can_add_new_product = true;
            } else if (this.typeUser === "seller") {
                if (
                    this.config !== undefined &&
                    this.config.seller_can_create_product !== undefined
                ) {
                    this.can_add_new_product = this.config.seller_can_create_product;
                }
            }
            return this.can_add_new_product;
        },
        validateQuantity() {
            if (!this.form.quantity) {
                this.setMinQuantity();
            }

            if (isNaN(Number(this.form.quantity))) {
                this.setMinQuantity();
            }

            if (typeof parseFloat(this.form.quantity) !== "number") {
                this.setMinQuantity();
            }

            if (this.form.quantity <= this.getMinQuantity()) {
                this.setMinQuantity();
            }

            this.calculateTotal();
        },
        changeValidateQuantity(event) {
            this.calculateTotal();
        },
        getMinQuantity() {
            return 0.01;
        },
        setMinQuantity() {
            this.form.quantity = this.getMinQuantity();
        },
        clickDecrease() {
            this.form.quantity = parseInt(this.form.quantity - 1);

            if (this.form.quantity <= this.getMinQuantity()) {
                this.setMinQuantity();
                return;
            }

            this.calculateTotal();
        },
        clickIncrease() {
            this.form.quantity = parseInt(this.form.quantity) + 1;
            this.calculateTotal();
        },
        async searchRemoteItems(input) {
            this.itemSearchTerm = input;

            if (input.length > 2) {
                this.loading_search = true;
                const params = {
                    input: input,
                    search_by_barcode: this.search_item_by_barcode ? 1 : 0,
                    search_item_by_barcode_presentation: this
                        .search_item_by_barcode_presentation
                        ? 1
                        : 0,
                    search_factory_code_items: this
                        .enabledSearchFactoryCodeItems
                };
                await this.$http
                    .get(`/${this.resource}/search-items/`, { params })
                    .then(response => {
                        this.items = response.data.items;
                        this.loading_search = false;
                        this.enabledSearchItemsBarcode(input);
                        this.enabledSearchItemBySeries(input);                        if (this.items.length == 0) {
                            this.filterItems();
                            this.items = [];
                        }
                    });
            } else {
                await this.filterItems();
            }
        },
        filterItems() {
            this.items = this.all_items;
        },
        enabledSearchItemsBarcode(input) {
            if (this.search_item_by_barcode) {
                if (this.$refs.selectBarcode) {
                    this.$refs.selectBarcode.$data.selectedLabel = "";
                }
                //busqueda por presentacion
                if (this.search_item_by_barcode_presentation) {
                    if (this.items.length == 1) {
                        const item_unit_type = _.find(
                            this.items[0].item_unit_types,
                            { barcode: input }
                        );

                        if (!_.isEmpty(item_unit_type)) {
                            this.form.item_id = this.items[0].id;
                            this.$refs.selectBarcode.blur();
                            this.changeItem();
                            this.selectedPrice(item_unit_type);
                        }
                    }
                }
                //busqueda comun

                else {
                    if(this.items.length == 1) {
                        this.form.item_id = this.items[0].id;
                        if (this.$refs.selectBarcode) {
                            this.$refs.selectBarcode.blur();
                        }
                        this.changeItem();
                    }

                }
            }
        },
        async enabledSearchItemBySeries() {
            if (this.config.search_item_by_series && this.items.length == 1) {
                this.$notify({
                    title: "Serie ubicada",
                    message: "Producto añadido!",
                    type: "success",
                    duration: 1200
                });
                this.form.item_id = this.items[0].id;
                this.$refs.selectSearchNormal.$data.selectedLabel = "";

                await this.changeItem();

                this.lots = this.form.item.lots.map(lot => {
                    lot.has_sale = true;
                    return lot;
                });

                await this.clickAddItem();

                this.$refs.selectSearchNormal.$data.selectedLabel = "";
            }

            if (this.config.search_item_by_series && this.items.length == 0) {
                this.$notify({
                    title: "Serie no ubicada",
                    message: "",
                    type: "warning",
                    duration: 1200
                });
            }
        },
        filterMethod(query) {
            let item = _.find(this.items, { internal_id: query });

            if (item) {
                this.form.item_id = item.id;
                this.changeItem();
            }
        },
        selectWarehouseInline(wh) {
            if (!this.form.item || !this.form.item.warehouses) return;
            this.form.item.warehouses.forEach(w => { w.checked = false; });
            wh.checked = true;
            this.form.warehouse_id = wh.warehouse_id;
            this.$eventHub.$emit('selectWarehouseId', wh.warehouse_id);
        },
        clickWarehouseDetail() {
            if (!this.form.item_id) {
                return this.$message.error("Seleccione un item");
            }

            let item = _.find(this.items, { id: this.form.item_id });

            this.warehousesDetail = item.warehouses;
            this.showWarehousesDetail = true;
        },
        // filterItems(){
        //     this.items = this.items.filter(item => item.warehouses.length >0)
        // },
        initForm() {
            this.errors = {};
            this.readonly_total = 0;

            this.form = {
                // category_id: [1],
                // edit: false,
                item_id: null,
                item: {},
                affectation_igv_type_id: null,
                affectation_igv_type: {},
                has_isc: false,
                system_isc_type_id: null,
                percentage_isc: 0,
                suggested_price: 0,
                quantity: 1,
                unit_price: 0,
                unit_price_value: 0,
                input_unit_price: 0,
                input_unit_price_value: 0,
                charges: [],
                discounts: [],
                attributes: [],
                has_igv: null,
                is_set: false,
                item_unit_types: [],
                has_plastic_bag_taxes: false,
                series_enabled: false,
                warehouse_id: null,
                lots_group: [],
                IdLoteSelected: null,
                document_item_id: null,
                name_product_pdf: "",
                sale_note_item_id: null,
                record_id: null,
                variant_id: null,
            };

            this.activePanel = 0;
            this.total_item = 0;
            this.item_unit_type = {};
            this.lots = [];
            this.has_list_prices = false;
        },
        // initializeFields() {
        //     this.form.affectation_igv_type_id = this.affectation_igv_types[0].id
        // },
        async create() {
            this.titleDialog = this.isUpdateItem
                ? " Editar Producto o Servicio"
                : " Agregar Producto o Servicio";
            this.titleAction = this.isUpdateItem ? " Editar" : " Agregar";
            if (this.operation_types !== undefined) {
                let operation_type = await _.find(this.operation_types, {
                    id: this.operationTypeId
                });
                if (operation_type !== undefined) {
                    this.affectation_igv_types = await _.filter(
                        this.all_affectation_igv_types,
                        { exportation: operation_type.exportation }
                    );
                }
            }

            // console.log(this.recordItem, "aq")

            this.updateItem();

            if (this.$refs.selectSearchNormal) {
                this.$refs.selectSearchNormal.$el
                    .getElementsByTagName("input")[0]
                    .focus();
            }
        },
        // edicion de item
        searchGetIdLoteSelected() {
            if (this.old_selected_lots_group.length > 0) {
                let new_id_lote_selected = [];

                this.old_selected_lots_group.forEach(lot => {
                    let search_lot = _.find(this.form.lots_group, {
                        id: lot.id
                    });

                    if (search_lot) {
                        search_lot.compromise_quantity =
                            lot.compromise_quantity;
                        new_id_lote_selected.push(lot);
                    }
                });

                if (new_id_lote_selected.length > 0) {
                    return new_id_lote_selected;
                }
            }

            return null;
        },
        regularizeCompromiseQuantityLots() {
            this.form.IdLoteSelected.forEach(lot => {
                let search_lot = _.find(this.form.lots_group, { id: lot.id });
                if (search_lot)
                    search_lot.compromise_quantity = lot.compromise_quantity;
            });
        },
        setIdLoteSelected() {
            // console.log(this.recordItem.item.IdLoteSelected)

            if (this.recordItem.item.IdLoteSelected) {
                this.old_selected_lots_group = this.recordItem.item.IdLoteSelected;
                this.form.IdLoteSelected = this.recordItem.item.IdLoteSelected;
                this.regularizeCompromiseQuantityLots();
            } else {
                if (this.recordItem.item.lots_group) {
                    this.old_selected_lots_group = _.filter(
                        this.recordItem.item.lots_group,
                        function(lot) {
                            return lot.compromise_quantity > 0;
                        }
                    );
                    this.form.IdLoteSelected = this.searchGetIdLoteSelected();
                }
            }
        },
        setItemLots() {
            if (this.recordItem.item.lots) {
                this.form.sale_note_item_id = this.recordItem.record_id;
                this.form.item.lots = this.recordItem.item.lots;
                this.lots = this.recordItem.item.lots;
            }
        },
        setNameProductPdf() {
            if (this.recordItem.item.name_product_pdf) {
                this.form.name_product_pdf = this.recordItem.item.name_product_pdf;
            }
        },
        setUnitPriceValue() {
            if (this.recordItem.item.has_igv) {
                this.form.unit_price_value = this.recordItem
                    .input_unit_price_value
                    ? this.recordItem.input_unit_price_value
                    : this.recordItem.unit_price;
            } else {
                this.form.unit_price_value = this.recordItem
                    .input_unit_price_value
                    ? this.recordItem.input_unit_price_value
                    : this.recordItem.unit_value;
            }
        },
        async updateItem() {
            if (this.isUpdateItem) {
                await this.reloadDataItems(this.recordItem.item_id);

                if (
                    this.recordItem.item.barcode === this.various_item_barcode
                ) {
                    this.various_item = true;
                    this.form.item.description = this.recordItem.item.description;
                } else {
                    this.various_item = false;
                }

                this.form.quantity = parseFloat(this.recordItem.quantity);
                this.setUnitPriceValue();
                this.form.has_plastic_bag_taxes =
                    this.recordItem.total_plastic_bag_taxes > 0 ? true : false;
                this.form.warehouse_id = this.recordItem.warehouse_id;
                this.isUpdateWarehouseId = this.recordItem.warehouse_id;
                this.form.record_id = this.recordItem.record_id;
                this.form.affectation_igv_type_id = this.recordItem.affectation_igv_type_id;

                this.setIdLoteSelected();
                this.setItemLots();
                this.setPresentationEditItem();
                this.setNameProductPdf();
                this.calculateQuantity();
            } else {
                this.isUpdateWarehouseId = null;

                if (this.various_item) {
                    await this.setFocusSelectItem();
                }
            }
        },
        // edicion de item
        setPresentationEditItem() {
            if (!_.isEmpty(this.recordItem.item.presentation)) {
                this.selectedPrice(this.recordItem.item.presentation);
                this.getSelectedClass(this.recordItem.item.presentation);
            }
        },
        async regularizeLots() {
            if (this.form.document_item_id && this.form.item.lots.length > 0) {
                await this.$http
                    .get(
                        `/${this.resource}/regularize-lots/${
                            this.form.document_item_id
                        }`
                    )
                    .then(response => {
                        let all_lots = this.form.item.lots;
                        let available_lots = response.data;

                        all_lots.forEach((lot, index) => {
                            let exist_lot = _.find(available_lots, it => {
                                return it.id == lot.id;
                            });

                            if (!exist_lot) {
                                this.form.item.lots.splice(index, 1);
                            }
                        });
                    })
                    .catch(error => {})
                    .then(() => {});
            }
        },
        clickAddDiscount() {
            this.form.discounts.push({
                discount_type_id: null,
                discount_type: null,
                description: null,
                percentage: 0,
                factor: 0,
                amount_exact: 0,
                amount: 0,
                base: 0,
                is_amount: false
            });
        },
        clickRemoveDiscount(index) {
            this.form.discounts.splice(index, 1);
        },
        changeDiscountType(index) {
            let discount_type_id = this.form.discounts[index].discount_type_id;
            this.form.discounts[index].discount_type = _.find(
                this.discount_types,
                { id: discount_type_id }
            );
        },
        clickAddCharge() {
            this.form.charges.push({
                charge_type_id: null,
                charge_type: null,
                description: null,
                percentage: 0,
                factor: 0,
                amount: 0,
                base: 0
            });
        },
        clickRemoveCharge(index) {
            this.form.charges.splice(index, 1);
        },
        changeChargeType(index) {
            let charge_type_id = this.form.charges[index].charge_type_id;
            this.form.charges[index].charge_type = _.find(this.charge_types, {
                id: charge_type_id
            });
        },
        clickAddAttribute() {
            this.form.attributes.push({
                attribute_type_id: null,
                description: null,
                value: null,
                start_date: null,
                end_date: null,
                duration: null
            });
        },
        clickRemoveAttribute(index) {
            this.form.attributes.splice(index, 1);
        },
        changeAttributeType(index) {
            let attribute_type_id = this.form.attributes[index]
                .attribute_type_id;
            let attribute_type = _.find(this.attribute_types, {
                id: attribute_type_id
            });
            this.form.attributes[index].description =
                attribute_type.description;
            this.inputAttribute(index);
        },
        inputAttribute(index) {
            let value = this.form.attributes[index].value;
            let hotelAttributes = ["4003", "4004"];

            this.form.attributes[index].start_date = hotelAttributes.includes(
                this.form.attributes[index].attribute_type_id
            )
                ? value
                : null;
        },
        close() {
            this.initForm();
            this.$emit("update:showDialog", false);
        },
        // ── Variantes ──────────────────────────────────────────────────────────
        selectVariantOption(optionId, val) {
            this.$set(this.selectedVariantOptions, optionId, val);
            this.resolveVariant();
        },

        resolveVariant() {
            const opts = this.form.item.item_options || [];
            if (!opts.length) return;

            // Verificar que todos los grupos tienen selección
            const allSelected = opts.every(opt => this.selectedVariantOptions[opt.id]);
            if (!allSelected) {
                this.resolvedVariant = null;
                this.variantNotFound = false;
                this.form.variant_id = null;
                return;
            }

            // IDs de valores seleccionados ordenados
            const selectedIds = opts
                .map(opt => this.selectedVariantOptions[opt.id].id)
                .sort((a, b) => a - b);

            // Buscar variante que tenga exactamente esos option_value_ids
            const variants = this.form.item.variants || [];
            const found = variants.find(v => {
                const vIds = [...(v.option_value_ids || [])].sort((a, b) => a - b);
                return JSON.stringify(vIds) === JSON.stringify(selectedIds);
            });

            if (found) {
                this.resolvedVariant = found;
                this.variantNotFound = false;
                this.form.variant_id = found.id;
                // Actualizar precio con el de la variante si tiene uno
                if (found.sale_unit_price !== null && found.sale_unit_price !== undefined) {
                    this.form.unit_price_value = parseFloat(found.sale_unit_price);
                    this.calculateTotal();
                }
            } else {
                this.resolvedVariant = null;
                this.variantNotFound = true;
                this.form.variant_id = null;
            }
        },

        // ── /Variantes ─────────────────────────────────────────────────────────

        async changeItem() {
            const selectedItem = _.find(this.items, { id: this.form.item_id });
            if (!selectedItem) return;

            this.form.item = { ...selectedItem };
            // Reset variant selection when item changes
            this.selectedVariantOptions = {};
            this.resolvedVariant = null;
            this.variantNotFound = false;
            this.form.variant_id = null;
            // this.form.item = _.find(this.items, {'id': this.form.item_id});
            this.form.item_unit_types = selectedItem.item_unit_types;
            this.form.unit_price_value = this.form.item.sale_unit_price;
            if (
                !this.configuration.enable_list_product &&
                this.selectedOptionPrice !== 1
            ) {
                if (this.form.item_unit_types.length) {
                    let first_list = this.form.item_unit_types[0];
                    let priceSelected = first_list[this.selectedOptionPrice];
                    this.form.unit_price_value = priceSelected;
                } else {
                    this.form.unit_price_value = "0";
                }
            }
            this.lots = this.form.item.lots;

            this.form.has_igv = this.form.item.has_igv;
            this.form.has_plastic_bag_taxes = this.form.item.has_plastic_bag_taxes;
            this.form.affectation_igv_type_id = this.form.item.sale_affectation_igv_type_id;
            this.form.quantity = 1;
            this.cleanTotalItem();
            this.showListStock = true;

            if (this.hasAttributes()) {
                const contex = this;
                this.form.item.attributes.forEach(row => {
                    contex.form.attributes.push({
                        attribute_type_id: row.attribute_type_id,
                        description: row.description,
                        value: row.value,
                        start_date: row.start_date,
                        end_date: row.end_date,
                        duration: row.duration
                    });
                });
            }
            this.form.lots_group = this.form.item.lots_group;
            // if (!this.recordItem) {
            //     await this.form.item.warehouses.forEach(element => {
            //         if(element.checked){
            //             this.form.warehouse_id = element.warehouse_id
            //         }
            //     });
            // }

            //this.item_unit_types = this.form.item.item_unit_types;
            //(this.item_unit_types.length > 0) ? this.has_list_prices = true : this.has_list_prices = false;
            if (
                this.form.item.name_product_pdf &&
                this.config.item_name_pdf_description
            ) {
                this.form.name_product_pdf = this.form.item.name_product_pdf;
            }

            this.addDescriptionToDocumentItem();
            this.readonly_total = this.form.unit_price_value;
        },
        addDescriptionToDocumentItem() {
            if (this.canAddDescriptionToDocumentItem) {
                const name = this.form.item.description
                    ? `<p>${this.form.item.description}</p>`
                    : "";
                const description = this.form.item.name
                    ? `<p>${this.form.item.name}</p>`
                    : "";

                this.form.name_product_pdf = `${name}${description}`;
            }
        },
        focusTotalItem(change) {
            if (!change && this.form.item.calculate_quantity) {
                this.$refs.total_item.$el
                    .getElementsByTagName("input")[0]
                    .focus();
                this.total_item = this.form.unit_price_value;
            }
        },
        calculateQuantity() {
            if (this.form.item.calculate_quantity) {
                this.form.quantity = _.round(
                    this.total_item / this.form.unit_price_value,
                    4
                );
            }
            this.calculateTotal();
        },
        calculateTotal() {
            this.readonly_total = _.round(
                this.form.quantity * this.form.unit_price_value,
                4
            );
        },
        cleanTotalItem() {
            this.total_item = null;
        },
        getResponseMessage(success, message = null) {
            return {
                success: success,
                message: message
            };
        },
        validateIdLoteSelected() {
            if (this.form.item.lots_enabled) {
                if (!this.form.IdLoteSelected) {
                    return this.getResponseMessage(
                        false,
                        "Debe seleccionar un lote."
                    );
                } else {
                    const compromise_quantity = parseFloat(
                        _.sumBy(this.form.IdLoteSelected, "compromise_quantity")
                    );

                    if (compromise_quantity != parseFloat(this.form.quantity)) {
                        return this.getResponseMessage(
                            false,
                            "La suma de cantidades comprometidas de los lotes debe der igual a la cantidad pedida."
                        );
                    }
                }
            }

            return this.getResponseMessage(true);
        },
        async validateCurrentStock() {
            const data = {
                item_id: this.form.item_id,
                quantity: this.form.quantity,
                warehouse_id: this.form.warehouse_id,
                presentation: this.item_unit_type
            };
            const response = await this.$http.post(`/validate-current-item-stock`, data);
            return response.data;
        },
        async clickAddItem() {
            // if(this.form.quantity < this.getMinQuantity()){
            //     return this.$message.error(`La cantidad no puede ser inferior a ${this.getMinQuantity()}`);
            // }
            this.validateQuantity();
            if (
                !this.form.item.description ||
                !this.form.item.description.trim().length
            ) {
                return this.$message.error("La descripción es requerida");
            }

            // Validar variante seleccionada
            if (this.form.item.has_variants && this.form.item.item_options && this.form.item.item_options.length && !this.form.variant_id) {
                return this.$message.warning('Selecciona todas las opciones de variante antes de agregar.');
            }

            const validate_id_lote_selected = this.validateIdLoteSelected();
            if (!validate_id_lote_selected.success)
                return this.$message.error(validate_id_lote_selected.message);

            if (this.validateTotalItem().total_item) return;

            if (this.validate_stock_add_item && this.form.item_id) {
                const stock_check = await this.validateCurrentStock();
                if (!stock_check.success)
                    return this.$message.error(stock_check.message);
            }

            let affectation_igv_type_id = this.form.affectation_igv_type_id;
            // let unit_price = (this.form.has_igv) ? this.form.unit_price_value : this.form.unit_price_value * 1.18;
            let unit_price = this.form.unit_price_value;
            if (this.form.has_igv === false) {
                if (
                    affectation_igv_type_id === "20" ||
                    affectation_igv_type_id === "21" ||
                    affectation_igv_type_id === "40"
                ) {
                    // do nothing
                    // exonerado de igv
                } else {
                    unit_price =
                        this.form.unit_price_value * (1 + this.percentageIgv);
                }
            }

            //validar precio compra y venta
            if (this.configuration.validate_purchase_sale_unit_price) {
                let val_purchase_unit_price = parseFloat(
                    this.form.item.purchase_unit_price
                );

                if (val_purchase_unit_price > parseFloat(unit_price)) {
                    return this.$message.error(
                        `El precio de compra no puede ser superior al precio de venta (P. Compra: ${val_purchase_unit_price})`
                    );
                }
            }

            this.form.input_unit_price_value = this.form.unit_price_value;

            this.form.unit_price = unit_price;
            this.form.item.unit_price = unit_price;
            this.form.item.presentation = this.item_unit_type;
            this.form.affectation_igv_type = _.find(
                this.affectation_igv_types,
                { id: affectation_igv_type_id }
            );

            let IdLoteSelected = this.form.IdLoteSelected;
            let document_item_id = this.form.document_item_id;
            this.row = calculateRowItem(
                this.form,
                this.currencyTypeIdActive,
                this.exchangeRateSale,
                this.percentageIgv
            );

            this.row.item.name_product_pdf = this.row.name_product_pdf || "";
            // Adjuntar variante seleccionada al row
            if (this.form.variant_id && this.resolvedVariant) {
                this.row.variant_id = this.form.variant_id;
                this.row.item.variant_id = this.form.variant_id;
                this.row.item.variant_display_name = this.resolvedVariant.display_name;
                // Agregar variante a la descripción visible y al PDF
                this.row.item.description = this.row.item.description + ' / ' + this.resolvedVariant.display_name;
                this.row.item.name_product_pdf = '<p>' + this.row.item.description + '</p>';
            }

            let select_lots = await _.filter(this.row.item.lots, {
                has_sale: true
            });
            let un_select_lots = await _.filter(this.row.item.lots, {
                has_sale: false
            });

            if (this.form.item.series_enabled) {
                if (select_lots.length != this.form.quantity)
                    return this.$message.error(
                        "La cantidad de series seleccionadas son diferentes a la cantidad a vender"
                    );
            }

            // this.row.item.lots = un_select_lots
            // this.row.lots = select_lots
            this.initForm();

            if (this.recordItem) this.row.aux_index = this.recordItem.aux_index;

            this.row.IdLoteSelected = IdLoteSelected;
            this.row.document_item_id = document_item_id;

            this.$emit("add", this.row);

            if (this.search_item_by_barcode) {
                this.cleanItems();
            }

            if (this.recordItem) {
                this.close();
            } else {
                this.setFocusSelectItem();
            }
        },
        cleanItems() {
            this.items = [];
            this.$refs.selectBarcode.$el
                .getElementsByTagName("input")[0]
                .focus();
            // console.log("add cart barcode")
        },
        validateTotalItem() {
            this.errors = {};

            if (this.form.item.calculate_quantity) {
                if (this.total_item < 0.01)
                    this.$set(this.errors, "total_item", [
                        "total venta item debe ser mayor a 0.01"
                    ]);
            }

            return this.errors;
        },
        async reloadDataItems(item_id) {
            if (!item_id) {
                await this.$http
                    .get(`/${this.resource}/table/items`)
                    .then(response => {
                        this.items = response.data;
                        this.form.item_id = item_id;
                        // if(item_id) this.changeItem()
                        // this.filterItems()
                    });
            } else {
                await this.$http
                    .get(`/${this.resource}/search/item/${item_id}`)
                    .then(response => {
                        this.items = response.data;
                        // this.items = response.data.items
                        this.form.item_id = item_id;
                        this.changeItem();
                    });
            }
        },
        changePresentation() {
            let price = 0;

            this.item_unit_type = _.find(this.form.item.item_unit_types, {
                id: this.form.item_unit_type_id
            });

            switch (this.item_unit_type.price_default) {
                case 1:
                    price = this.item_unit_type.price1;
                    break;
                case 2:
                    price = this.item_unit_type.price2;
                    break;
                case 3:
                    price = this.item_unit_type.price3;
                    break;
            }

            this.form.unit_price_value = price;
            this.form.item.unit_type_id = this.item_unit_type.unit_type_id;
        },
        selectedPrice(row) {
            if (this.isSelectedPrice(row)) {
                this.form.item_unit_type_id = null;
                this.item_unit_type = {};
                this.form.unit_price = this.form.item.sale_unit_price;
                this.form.unit_price_value = this.form.item.sale_unit_price;
                this.form.item.unit_type_id = this.form.item.original_unit_type_id;
            } else {
                let valor = 0;
                switch (row.price_default) {
                    case 1:
                        valor = row.price1;
                        break;
                    case 2:
                        valor = row.price2;
                        break;
                    case 3:
                        valor = row.price3;
                        break;
                }
                this.form.item_unit_type_id = row.id;
                this.item_unit_type = row;
                this.form.unit_price = valor;
                this.form.unit_price_value = valor;
                this.form.item.unit_type_id = row.unit_type_id;
            }
            this.calculateQuantity();
            this.getTables();
        },
        addRowLotGroup(id) {
            this.form.IdLoteSelected = id;
        },
        clickLotGroup() {
            this.showDialogLots = true;
        },
        async clickSelectLots() {
            this.showDialogSelectLots = true;
        },
        addRowSelectLot(lots) {
            this.lots = lots;
        },
        focusSelectItem() {
            this.$refs.selectSearchNormal.$el
                .getElementsByTagName("input")[0]
                .focus();
        },
        async setFocusSelectItem() {
            if (this.various_item) {
                await this.setVariousItem();
                this.$refs.inputItemDescription.$el
                    .getElementsByTagName("input")[0]
                    .focus();
            } else {
                if (this.$refs.selectSearchNormal)
                    this.$refs.selectSearchNormal.$el
                        .getElementsByTagName("input")[0]
                        .focus();
            }
        },
        isSelectedPrice(item_unit_type) {
            if (!_.isEmpty(this.item_unit_type)) {
                return this.item_unit_type.id === item_unit_type.id;
            }
            return false;
        },
        getSelectedClass(row) {
            if (this.isSelectedPrice(row)) return "btn-success";
            return "btn-secondary";
        },
        checkKey(e) {
            let code = e.event.code;
            if (code === "Escape") {
                this.close();
            }
        },
        async setVariousItem() {
            if (this.various_item) {
                let original_value = this.search_item_by_barcode;
                this.search_item_by_barcode = true;

                await this.searchRemoteItems(this.various_item_barcode);

                this.search_item_by_barcode = original_value;

                if (
                    this.form.item == null ||
                    this.form.item.barcode !== this.various_item_barcode
                ) {
                    this.$notify({
                        title: "Producto Manual",
                        message: `Debe registrar un producto con código de barras ${
                            this.various_item_barcode
                        }`,
                        type: "error",
                        duration: 1200
                    });
                    this.various_item = false;
                } else {
                    this.form.item.description = "";
                    this.$refs.inputItemDescription.$el
                        .getElementsByTagName("input")[0]
                        .focus();
                    return;
                }
            }
            this.initForm();
            this.filterItems();
        },
        openNewItemDialog() {
            this.showDialogNewItem = true;
        },

    }
};
</script>
