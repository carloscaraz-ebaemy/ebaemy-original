<template>
    <el-dialog :close-on-click-modal="false"
               :title="titleDialog"
               :visible="showDialog"
               append-to-body
               class="pt-0"
               top="7vh"
               width="65%"
               @close="close"
               @open="create">
        <form autocomplete="off"
              @submit.prevent="submit">


            <el-tabs v-model="activeName">
                <el-tab-pane class
                             name="first">
                    <span slot="label">General</span>
                    <div class="row">
                        <div class="col-md-3">
                            <div v-show="show_has_igv"
                                 class="">
                                <div :class="{'has-danger': errors.has_igv}"
                                     class="form-group">
                                    <el-checkbox v-model="form.has_igv">Incluye Igv
                                    </el-checkbox>
                                    <br>
                                    <small v-if="errors.has_igv"
                                           class="form-control-feedback"
                                           v-text="errors.has_igv[0]"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div v-show="form.unit_type_id !='ZZ'"
                                 class="">
                                <div :class="{'has-danger': errors.calculate_quantity}"
                                     class="form-group">
                                    <el-checkbox v-model="form.calculate_quantity">Calcular cantidad por precio
                                    </el-checkbox>
                                    <br>
                                    <small v-if="errors.calculate_quantity"
                                           class="form-control-feedback"
                                           v-text="errors.calculate_quantity[0]"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="">
                                <div :class="{'has-danger': errors.has_plastic_bag_taxes}"
                                     class="form-group">
                                    <el-checkbox v-model="form.has_plastic_bag_taxes">Impuesto a la Bolsa Plástica
                                    </el-checkbox>
                                    <br>
                                    <small v-if="errors.has_plastic_bag_taxes"
                                           class="form-control-feedback"
                                           v-text="errors.has_plastic_bag_taxes[0]"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div :class="{'has-danger': errors.description}"
                                 class="form-group">
                                <label class="control-label">Nombre<span class="text-danger">*</span></label>
                                <el-input v-model="form.description"
                                          dusk="description"></el-input>
                                <small v-if="errors.description"
                                       class="form-control-feedback"
                                       v-text="errors.description[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div :class="{'has-danger': errors.second_name}"
                                 class="form-group">
                                <label class="control-label">Nombre secundario </label>
                                <el-input v-model="form.second_name"
                                          dusk="second_name"></el-input>
                                <small v-if="errors.second_name"
                                       class="form-control-feedback"
                                       v-text="errors.second_name[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div :class="{'has-danger': errors.name}"
                                 class="form-group">
                                <label class="control-label">Descripción</label>
                                <el-input v-model="form.name"
                                          dusk="name"></el-input>
                                <small v-if="errors.name"
                                       class="form-control-feedback"
                                       v-text="errors.name[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.model}"
                                 class="form-group">
                                <label class="control-label">Modelo</label>
                                <el-input v-model="form.model"
                                          dusk="model"></el-input>
                                <small v-if="errors.model"
                                       class="form-control-feedback"
                                       v-text="errors.model[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.unit_type_id}"
                                 class="form-group">
                                <label class="control-label">Unidad</label>
                                <el-select v-model="form.unit_type_id"
                                           dusk="unit_type_id">
                                    <el-option v-for="option in unit_types"
                                               :key="option.id"
                                               :label="option.description"
                                               :value="option.id"></el-option>
                                </el-select>
                                <small v-if="errors.unit_type_id"
                                       class="form-control-feedback"
                                       v-text="errors.unit_type_id[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.currency_type_id}"
                                 class="form-group">
                                <label class="control-label">Moneda</label>
                                <el-select v-model="form.currency_type_id"
                                           dusk="currency_type_id">
                                    <el-option v-for="option in currency_types"
                                               :key="option.id"
                                               :label="option.description"
                                               :value="option.id"></el-option>
                                </el-select>
                                <small v-if="errors.currency_type_id"
                                       class="form-control-feedback"
                                       v-text="errors.currency_type_id[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.sale_unit_price}"
                                 class="form-group">
                                <label class="control-label">Precio Unitario <span class="text-danger">*</span></label>
                                <el-input v-model="form.sale_unit_price"
                                          dusk="sale_unit_price"
                                          @input="calculatePercentageOfProfitBySale"></el-input>
                                <small v-if="errors.sale_unit_price"
                                       class="form-control-feedback"
                                       v-text="errors.sale_unit_price[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div :class="{'has-danger': errors.sale_affectation_igv_type_id}"
                                 class="form-group">
                                <label class="control-label">Tipo de afectación</label>
                                <el-select
                                    v-model="form.sale_affectation_igv_type_id"
                                    filterable
                                    @change="changeAffectationIgvType">
                                    <el-option
                                        v-for="option in affectation_igv_types"
                                        :key="option.id"
                                        :label="option.description"
                                        :value="option.id"
                                    ></el-option>
                                </el-select>
                                <small
                                    v-if="errors.sale_affectation_igv_type_id"
                                    class="form-control-feedback"
                                    v-text="errors.sale_affectation_igv_type_id[0]"></small>
                            </div>
                        </div>
                        <div class="col-12"></div>
                        <div v-if="form.unit_type_id !='ZZ'"
                             v-show="recordId==null"
                             class="col-md-3">
                            <div :class="{'has-danger': errors.warehouse_id}"
                                 class="form-group">
                                <label class="control-label">
                                    Almacén
                                    <el-tooltip class="item"
                                                content="Si no selecciona almacén, se asignará por defecto el relacionado al sucursal"
                                                effect="dark"
                                                placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </label>
                                <el-select v-model="form.warehouse_id"
                                           filterable>
                                    <el-option v-for="option in warehouses"
                                               :key="option.id"
                                               :label="option.description"
                                               :value="option.id"></el-option>
                                </el-select>
                                <small v-if="errors.warehouse_id"
                                       class="form-control-feedback"
                                       v-text="errors.warehouse_id[0]"></small>
                            </div>
                        </div>
                        <div v-show="recordId==null && form.unit_type_id !='ZZ'"
                             class="col-md-3">
                            <div :class="{'has-danger': errors.stock}"
                                 class="form-group">
                                <label class="control-label">Stock Inicial</label>
                                <el-input v-model="form.stock"></el-input>
                                <small v-if="errors.stock"
                                       class="form-control-feedback"
                                       v-text="errors.stock[0]"></small>
                            </div>
                        </div>
                        <div v-show="form.unit_type_id !='ZZ'"
                             class="col-md-3">
                            <div :class="{'has-danger': errors.stock_min}"
                                 class="form-group">
                                <label class="control-label">Stock Mínimo</label>
                                <el-input v-model="form.stock_min"></el-input>
                                <small v-if="errors.stock_min"
                                       class="form-control-feedback"
                                       v-text="errors.stock_min[0]"></small>
                            </div>
                        </div>
                        <!-- Peso y Dimensiones (envío / marketplace) -->
                        <div class="col-12 mt-2 mb-1" v-show="form.unit_type_id !='ZZ'">
                            <small class="text-muted"><strong>Envío / Marketplace</strong> (peso y dimensiones para cálculo de envío)</small>
                        </div>
                        <div class="col-md-3" v-show="form.unit_type_id !='ZZ'">
                            <div class="form-group">
                                <label class="control-label">Peso (kg)</label>
                                <el-input v-model="form.weight" type="number" step="0.01" placeholder="0.00"></el-input>
                            </div>
                        </div>
                        <div class="col-md-3" v-show="form.unit_type_id !='ZZ'">
                            <div class="form-group">
                                <label class="control-label">Largo (cm)</label>
                                <el-input v-model="form.length" type="number" step="0.1" placeholder="0.0"></el-input>
                            </div>
                        </div>
                        <div class="col-md-3" v-show="form.unit_type_id !='ZZ'">
                            <div class="form-group">
                                <label class="control-label">Ancho (cm)</label>
                                <el-input v-model="form.width" type="number" step="0.1" placeholder="0.0"></el-input>
                            </div>
                        </div>
                        <div class="col-md-3" v-show="form.unit_type_id !='ZZ'">
                            <div class="form-group">
                                <label class="control-label">Alto (cm)</label>
                                <el-input v-model="form.height" type="number" step="0.1" placeholder="0.0"></el-input>
                            </div>
                        </div>

                        <div v-show="form.unit_type_id !='ZZ' && form.lots_enabled"
                             class="col-md-3">
                            <div :class="{'has-danger': errors.date_of_due}"
                                 class="form-group">
                                <label class="control-label">Fec. Vencimiento</label>
                                <el-date-picker v-model="form.date_of_due"
                                                :clearable="true"
                                                type="date"
                                                value-format="yyyy-MM-dd"></el-date-picker>
                                <small v-if="errors.date_of_due"
                                       class="form-control-feedback"
                                       v-text="errors.date_of_due[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.barcode}"
                                 class="form-group">
                                <label class="control-label">Código de barra</label>
                                <el-input v-model="form.barcode"></el-input>
                                <small v-if="errors.barcode"
                                       class="form-control-feedback"
                                       v-text="errors.barcode[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.internal_id}"
                                 class="form-group">
                                <!-- migracion desarrollo sin terminar #1401 -->
                                 <!-- <template v-if="inventory_configuration && inventory_configuration.generate_internal_id == 1">
                                    <label class="control-label">Código Interno
                                    <el-tooltip class="item"
                                                content="Código interno de la empresa para el control de sus productos | Autogenerado por el sistema"
                                                effect="dark"
                                                placement="top-start">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                    </label>
                                    <el-input :disabled="true" v-model="form.internal_id"
                                          dusk="internal_id"></el-input>
                                    <small v-if="errors.internal_id"
                                       class="form-control-feedback"
                                       v-text="errors.internal_id[0]"></small>
                                </template> -->
                                <!-- <template v-else> -->
                                    <label class="control-label">Código Interno
                                        <el-tooltip class="item"
                                                    content="Código interno de la empresa para el control de sus productos"
                                                    effect="dark"
                                                    placement="top-start">
                                            <i class="fa fa-info-circle"></i>
                                        </el-tooltip>
                                    </label>
                                    <el-input v-model="form.internal_id"
                                            dusk="internal_id"></el-input>
                                    <small v-if="errors.internal_id"
                                        class="form-control-feedback"
                                        v-text="errors.internal_id[0]"></small>
                                <!-- </template> -->
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.item_code}"
                                 class="form-group">
                                <label class="control-label">Código Sunat
                                    <el-tooltip class="item"
                                                content="Código proporcionado por SUNAT, campo obligatorio para exportaciones"
                                                effect="dark"
                                                placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </label>
                                <el-input v-model="form.item_code"
                                          dusk="item_code"></el-input>
                                <small v-if="errors.item_code"
                                       class="form-control-feedback"
                                       v-text="errors.item_code[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.line}"
                                 class="form-group">
                                <label class="control-label">
                                    Línea de producto
                                    <el-tooltip class="item"
                                                content="Grupo de productos que tienen una relación directa entre sí"
                                                effect="dark"
                                                placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </label>
                                <el-input v-model="form.line">
                                </el-input>
                                <small v-if="errors.line"
                                       class="form-control-feedback"
                                       v-text="errors.line[0]"></small>
                            </div>
                        </div>
                        <!-- sanitary -->
                        <div v-show="showPharmaElement"
                             class="col-md-3">
                            <div :class="{'has-danger': errors.sanitary}"
                                 class="form-group">
                                <label class="control-label">
                                    Registro Sanitario
                                    <el-tooltip
                                        class="item"
                                        content="Número de registro sanitario"
                                        effect="dark"
                                        placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </label>
                                <el-input v-model="form.sanitary">
                                </el-input>
                                <small v-if="errors.sanitary"
                                       class="form-control-feedback"
                                       v-text="errors.sanitary[0]"></small>
                            </div>
                        </div>
                        <!-- cod_digemid -->
                        <div v-show="showPharmaElement"
                             class="col-md-3">
                            <div :class="{'has-danger': errors.cod_digemid}"
                                 class="form-group">
                                <label class="control-label">
                                    Código DIGEMID
                                    <el-tooltip
                                        class="item"
                                        content="Código de observación DIGEMID"
                                        effect="dark"
                                        placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </label>
                                <el-input v-model="form.cod_digemid">
                                </el-input>
                                <small v-if="errors.cod_digemid"
                                       class="form-control-feedback"
                                       v-text="errors.cod_digemid[0]"></small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.factory_code}"
                                 class="form-group">
                                <label class="control-label">
                                    Código de fábrica
                                    <el-tooltip
                                        class="item"
                                        content="Para habilitar la búsqueda debe realizarlo en configuración/avanzado"
                                        effect="dark"
                                        placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </label>
                                <el-input v-model="form.factory_code">
                                </el-input>
                                <small v-if="errors.factory_code"
                                       class="form-control-feedback"
                                       v-text="errors.factory_code[0]"></small>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive table-border-none">
                                <table class="table table-sm mb-0 table-borderless">
                                    <thead>
                                    <tr>
                                        <th width="25%">
                                            <el-checkbox v-model="form.has_perception"
                                                         @change="changeHasPerception">Incluye percepción
                                            </el-checkbox>
                                        </th>
                                        <th width="25%">
                                            <div v-show="form.unit_type_id !='ZZ'">
                                                <el-checkbox v-model="form.lots_enabled"
                                                             @change="changeLotsEnabled">¿Maneja lotes?
                                                </el-checkbox>
                                            </div>
                                        </th>
                                        <th width="25%">
                                            <div v-show="form.unit_type_id !='ZZ'">
                                                <el-checkbox v-model="form.series_enabled"
                                                             @change="changeLotsEnabled">¿Maneja series?
                                                </el-checkbox>
                                            </div>
                                        </th>
                                        <!-- <th width="25%">
                                            <div v-show="form.unit_type_id !='ZZ' && canSeeProduction">
                                                <el-checkbox v-model="form.is_for_production"
                                                             @change="changeProductioTab">Este producto, ¿requiere insumos?
                                                </el-checkbox>
                                            </div>
                                        </th> -->
                                    </tr>
                                                                        </thead>
                                    <tbody>
                                    <tr>
                                        <td>
                                            <div v-show="form.has_perception">
                                                <div class="form-group">
                                                    <el-input v-model="form.percentage_perception"
                                                              placeholder="% de percepción"></el-input>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div v-show="form.unit_type_id !='ZZ' && form.lots_enabled">
                                                <div :class="{'has-danger': errors.lot_code}"
                                                     class="form-group">

                                                     <el-tooltip class="item"
                                                    content="Si va a usar el mismo LOTE en otros almacenes coloque un prefijo para diferenciarlos."
                                                    effect="dark"
                                                    placement="top">
                                                    <el-input v-model="form.lot_code"
                                                              placeholder="Código de lote"></el-input>
                                                    </el-tooltip>
                                                    <small v-if="errors.lot_code"
                                                           class="form-control-feedback"
                                                           v-text="errors.lot_code[0]"></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div v-show="form.unit_type_id !='ZZ' && form.series_enabled && !recordId">
                                                <div :class="{'has-danger': errors.lot_code}"
                                                     class="form-group">
                                                    <el-button icon="el-icon-edit-outline"
                                                               size="small"
                                                               type="primary"
                                                               @click.prevent="clickLotcode">Ingrese series
                                                    </el-button>
                                                    <small v-if="errors.lot_code"
                                                           class="form-control-feedback"
                                                           v-text="errors.lot_code[0]"></small>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.has_isc}"
                                 class="form-group ms-2">
                                <el-checkbox v-model="form.has_isc"
                                             @change="changeIsc">Incluye ISC
                                </el-checkbox>
                                <br>
                                <small v-if="errors.has_isc"
                                       class="form-control-feedback"
                                       v-text="errors.has_isc[0]"></small>
                            </div>
                        </div>

                        <template v-if="form.has_isc">
                            <div class="col-md-3">
                                <div :class="{'has-danger': errors.system_isc_type_id}"
                                     class="form-group">
                                    <label class="control-label">Tipo de sistema ISC</label>
                                    <el-select
                                        v-model="form.system_isc_type_id"
                                        filterable>
                                        <el-option
                                            v-for="option in system_isc_types"
                                            :key="option.id"
                                            :label="option.description"
                                            :value="option.id"
                                        ></el-option>
                                    </el-select>
                                    <small
                                        v-if="errors.system_isc_type_id"
                                        class="form-control-feedback"
                                        v-text="errors.system_isc_type_id[0]"></small>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div :class="{'has-danger': errors.percentage_isc}"
                                     class="form-group">
                                    <label class="control-label">Porcentaje ISC</label>
                                    <el-input v-model="form.percentage_isc"></el-input>
                                    <small
                                        v-if="errors.percentage_isc"
                                        class="form-control-feedback"
                                        v-text="errors.percentage_isc[0]"></small>
                                </div>
                            </div>
                        </template>


                        <div class="col-md-3">
                            <div :class="{'has-danger': errors.subject_to_detraction}"
                                 class="form-group ms-1">
                                <el-checkbox v-model="form.subject_to_detraction">Sujeto a detracción</el-checkbox>
                                <br>
                                <small v-if="errors.subject_to_detraction"
                                       class="form-control-feedback"
                                       v-text="errors.subject_to_detraction[0]"></small>
                            </div>
                        </div>


                        <div class="col-md-3" v-if="showRestrictSaleItemsCpe">
                            <div :class="{'has-danger': errors.restrict_sale_cpe}"
                                 class="form-group">
                                <el-checkbox v-model="form.restrict_sale_cpe">Restringir venta en CPE</el-checkbox>
                                <br>
                                <small v-if="errors.restrict_sale_cpe"
                                       class="form-control-feedback"
                                       v-text="errors.restrict_sale_cpe[0]"></small>
                            </div>
                        </div>

                        <template v-if="showPointSystem">
                            <div class="col-md-3">
                                <div :class="{'has-danger': errors.exchange_points}"
                                    class="form-group">
                                    <el-checkbox v-model="form.exchange_points">¿Se puede canjear por puntos?</el-checkbox>
                                    <br>
                                    <small v-if="errors.exchange_points"
                                        class="form-control-feedback"
                                        v-text="errors.exchange_points[0]"></small>
                                </div>
                            </div>

                            <div class="col-md-3 mb-2" v-if="form.exchange_points">
                                <label class="control-label">
                                    N° de puntos
                                    <el-tooltip
                                        class="item"
                                        content="Total de puntos que necesitará el cliente para canjear el producto."
                                        effect="dark"
                                        placement="top-start">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </label>
                                <div :class="{'has-danger': errors.quantity_of_points}" class="form-group">
                                    <el-input-number v-model="form.quantity_of_points" :min="0.01" :precision="2" :step="1" controls-position="right"></el-input-number>
                                    <small v-if="errors.quantity_of_points" class="form-control-feedback" v-text="errors.quantity_of_points[0]"></small>
                                </div>
                            </div>
                        </template>

                    </div>
                </el-tab-pane>

                <el-tab-pane name="channels">
                    <span slot="label">🛒 Canales de venta</span>
                    <div class="row">
                        <div class="col-md-12" style="margin-bottom:12px">
                            <div class="alert alert-info py-2 mb-2" style="font-size:13px">
                                <strong>Este es tu producto maestro.</strong> Desde aquí decides en qué canales se publica.
                                No se duplica: es el mismo producto en tu catálogo, tu tienda online y el marketplace.
                            </div>
                        </div>

                        <!-- Canal 1: Venta interna -->
                        <div class="col-md-12" style="margin-bottom:16px;padding:14px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px">
                            <div style="display:flex;align-items:flex-start;gap:10px">
                                <div style="font-size:22px;line-height:1">🏢</div>
                                <div style="flex:1">
                                    <div style="font-weight:600;font-size:14px;color:#111827">Venta interna / POS</div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:2px">Siempre disponible para facturación, boletas y notas de venta desde el punto de venta.</div>
                                </div>
                                <span class="badge" style="background:#10b981;color:#fff;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600">Activo</span>
                            </div>
                        </div>

                        <!-- Canal 2: Tienda virtual -->
                        <div class="col-md-12" style="margin-bottom:16px;padding:14px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:10px">
                            <div style="display:flex;align-items:flex-start;gap:10px">
                                <div style="font-size:22px;line-height:1">🛍️</div>
                                <div style="flex:1">
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <el-switch v-model="form.apply_store" active-color="#0f8a82"></el-switch>
                                        <span style="font-weight:600;font-size:14px;color:#111827">Publicar en mi tienda online</span>
                                    </div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:2px">Muestra el producto en la ruta <code>/ecommerce</code> de tu tienda virtual privada. El precio y stock salen de este mismo producto.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Canal 3: Marketplace ebaemy -->
                        <div class="col-md-12" style="margin-bottom:16px;padding:14px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:10px">
                            <div style="display:flex;align-items:flex-start;gap:10px">
                                <div style="font-size:22px;line-height:1">🌐</div>
                                <div style="flex:1">
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <el-switch v-model="form.marketplace_publishable" active-color="#7c3aed"></el-switch>
                                        <span style="font-weight:600;font-size:14px;color:#111827">Publicar en Marketplace ebaemy</span>
                                        <el-tooltip content="Aparecerá en ebaemy.com/marketplace. Miles de compradores de todo Perú podrán descubrirlo." placement="top">
                                            <i class="el-icon-info text-info" style="font-size:15px;cursor:help"></i>
                                        </el-tooltip>
                                    </div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:2px">Llega a compradores de todo Perú vía <strong>ebaemy.com</strong>. Las ventas entran como pedidos a tu tenant.</div>

                                    <div v-if="form.marketplace_publishable" style="margin-top:12px;padding:12px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px">
                                        <label style="display:block;font-size:12px;color:#6b21a8;margin-bottom:4px;font-weight:500">Categoría oficial del marketplace *</label>
                                        <div v-if="mp_category_suggestions.length && !form.marketplace_category_id" style="margin:0 0 8px;padding:8px 10px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px">
                                            <div style="font-size:11px;color:#047857;margin-bottom:4px;font-weight:500">💡 Sugerencias basadas en tu categoría interna:</div>
                                            <div style="display:flex;flex-wrap:wrap;gap:6px">
                                                <button v-for="s in mp_category_suggestions" :key="s.id" type="button" @click="applyMpSuggestion(s)" style="background:#fff;border:1px solid #10b981;color:#065f46;padding:4px 10px;border-radius:6px;font-size:12px;cursor:pointer">
                                                    {{ s.breadcrumb }} ✓
                                                </button>
                                            </div>
                                        </div>
                                        <el-cascader
                                            v-model="form.marketplace_category_path"
                                            :options="mp_category_tree"
                                            :props="{ value: 'id', label: 'name', children: 'children', checkStrictly: false, emitPath: true }"
                                            :show-all-levels="true"
                                            placeholder="Selecciona una categoría…"
                                            filterable clearable size="mini" style="width:100%"
                                            @change="onMpCategoryChange">
                                        </el-cascader>
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px">
                                            <small style="color:#7c3aed;font-size:11px">El producto aparecerá bajo esta categoría en el marketplace.</small>
                                            <a href="#" @click.prevent="openMpCategoryRequest" style="font-size:11px;color:#7c3aed;text-decoration:underline">
                                                ¿No encuentras categoría?
                                            </a>
                                        </div>

                                        <div class="row" style="margin-top:10px">
                                            <div class="col-md-6">
                                                <label style="display:block;font-size:12px;color:#6b21a8;margin-bottom:4px;font-weight:500">Precio en marketplace (opcional)</label>
                                                <el-input-number v-model="form.mp_price" :min="0" :precision="2" :step="1"
                                                    placeholder="Usar precio normal" controls-position="right" size="mini"
                                                    style="width:100%"></el-input-number>
                                                <small style="color:#7c3aed;font-size:11px">Vacío = usar precio de venta.</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label style="display:block;font-size:12px;color:#6b21a8;margin-bottom:4px;font-weight:500">Estado marketplace</label>
                                                <div style="padding:6px 10px;background:#fff;border:1px solid #e9d5ff;border-radius:6px;font-size:12px;color:#6b21a8">
                                                    <span v-if="!form.mp_status || form.mp_status==='pending'">⏳ Pendiente de revisión</span>
                                                    <span v-else-if="form.mp_status==='active'">✅ Publicado</span>
                                                    <span v-else-if="form.mp_status==='paused'">⏸️ Pausado</span>
                                                    <span v-else-if="form.mp_status==='rejected'">❌ Rechazado</span>
                                                    <span v-else>{{ form.mp_status }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-top:10px">
                                            <label style="display:block;font-size:12px;color:#6b21a8;margin-bottom:4px;font-weight:500">Notas / descripción marketplace (opcional)</label>
                                            <el-input v-model="form.mp_notes" type="textarea" :rows="2" size="mini"
                                                placeholder="Descripción extendida que verán los compradores en ebaemy.com…"></el-input>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </el-tab-pane>

                <el-tab-pane class
                             v-if="!isService"
                             name="second">
                    <span slot="label">Almacenes</span>
                    <div class="row">
                        <div v-show="form.unit_type_id !='ZZ'"
                             class="col-12">
                            <h5 class="separator-title mt-0">Precios por almacén</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                    <tr v-for="item in form.item_warehouse_prices"
                                        :key="item.id">
                                        <td>{{ item.description }}</td>
                                        <td width="150">
                                            <el-input v-model="item.price"
                                                      min="0"
                                                      placeholder="Precio"
                                                      step="0.01"
                                                      type="number"></el-input>
                                        </td>
                                    </tr>
                                    <!-- <tr v-for="w in warehouses" :key="w.id">
                                        <td>{{ w.description }}</td>
                                        <td width="150">
                                            <el-input placeholder="Precio" v-model="w.price" type="number" min="0" step="0.01"></el-input>
                                        </td>
                                    </tr> -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </el-tab-pane>
                <el-tab-pane class  v-if="!isService" name="third">
                    <span slot="label">Presentaciones</span>
                    <div class="row">
                        <div v-show="form.unit_type_id !='ZZ'"
                             class="col-md-12">
                            <h5 class="separator-title mt-0">
                                Listado de precios
                                <el-tooltip class="item"
                                            content="Aplica para realizar compra/venta en presentacion de diferentes precios y/o cantidades"
                                            effect="dark"
                                            placement="top">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                                <small v-if="form.item_unit_types.length > 1 && !config.enable_list_product" class="text-warning">Sólo se toma en cuenta el primer registro para mostrar en POS</small>
                            </h5>
                        </div>
                        <div v-if="form.item_unit_types.length > 0"
                             v-show="form.unit_type_id !='ZZ'"
                             class="col-md-12">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="bg-light">
                                    <tr>
                                        <th class="text-center d-none">Código de barra</th>
                                        <th class="text-center">Unidad</th>
                                        <th class="text-center">Descripción</th>
                                        <th class="text-center">
                                            Factor
                                            <el-tooltip class="item"
                                                        content="Cantidad de unidades"
                                                        effect="dark"
                                                        placement="top">
                                                <i class="fa fa-info-circle"></i>
                                            </el-tooltip>
                                        </th>
                                        <th class="text-center">{{ config.price1_label }}</th>
                                        <th class="text-center">{{ config.price2_label }}</th>
                                        <th class="text-center">{{ config.price3_label }}</th>
                                        <th class="text-center d-none">P. Defecto</th>
                                        <th v-if="config.enable_list_product"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(row, index) in form.item_unit_types"
                                        :key="index">
                                        <template v-if="row.id">
                                            <td class="text-center d-none"> {{row.barcode}} </td>
                                            <td class="text-center">{{ row.unit_type_id }}</td>
                                            <td class="text-center">{{ row.description }}</td>
                                            <td class="text-center">{{ row.quantity_unit }}</td>
                                            <td class="text-center">
                                                <el-input v-model="row.price1"></el-input>
                                            </td>
                                            <td class="text-center">
                                                <el-input v-model="row.price2"></el-input>
                                            </td>
                                            <td class="text-center">
                                                <el-input v-model="row.price3"></el-input>
                                            </td>
                                            <td class="text-center d-none">Precio {{ row.price_default }}</td>
                                            <td class="series-table-actions text-end" v-if="config.enable_list_product">
                                                <button class="btn waves-effect waves-light btn-xs btn-danger"
                                                        type="button"
                                                        @click.prevent="clickDelete(row.id)">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </template>
                                        <template v-else>
                                            <td class="text-center d-none">
                                                <el-input v-model="row.barcode"></el-input>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <el-select v-model="row.unit_type_id"
                                                               :disabled="!config.enable_list_product"
                                                               dusk="item_unit_type.unit_type_id">
                                                        <el-option v-for="option in unit_types"
                                                                   :key="option.id"
                                                                   :label="option.description"
                                                                   :value="option.id"></el-option>
                                                    </el-select>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <el-input v-model="row.description"
                                                              :disabled="!config.enable_list_product"></el-input>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <el-input v-model="row.quantity_unit"
                                                              :disabled="!config.enable_list_product"></el-input>
                                                    <!-- <small class="form-control-feedback" v-if="errors.quantity_unit" v-text="errors.quantity_unit[0]"></small> -->
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <el-input v-model="row.price1"></el-input>
                                                    <!-- <small class="form-control-feedback" v-if="errors.stock_min" v-text="errors.stock_min[0]"></small> -->
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <el-input v-model="row.price2"></el-input>
                                                    <!-- <small class="form-control-feedback" v-if="errors.stock_min" v-text="errors.stock_min[0]"></small> -->
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <el-input v-model="row.price3"></el-input>
                                                    <!-- <small class="form-control-feedback" v-if="errors.stock_min" v-text="errors.stock_min[0]"></small> -->
                                                </div>
                                            </td>
                                            <td class="d-none">
                                                <div>
                                                    <el-select v-model="row.price_default">
                                                        <el-option :key="1"
                                                                   :value="1"
                                                                   :label="config.price1_label"></el-option>
                                                        <el-option :key="2"
                                                                   :value="2"
                                                                   :label="config.price2_label"></el-option>
                                                        <el-option :key="3"
                                                                   :value="3"
                                                                   :label="config.price3_label"></el-option>
                                                    </el-select>
                                                </div>
                                            </td>
                                            <td class="series-table-actions text-end" v-if="config.enable_list_product">
                                                <!-- <button type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="clickSubmit(index)">
                                                    <i class="fa fa-check"></i>
                                                </button> -->
                                                <button class="btn waves-effect waves-light btn-xs btn-danger"
                                                        type="button"
                                                        @click.prevent="clickCancel(index)">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </template>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col" v-if="config.enable_list_product || !config.enable_list_product && form.item_unit_types.length < 1">
                            <a class="control-label font-weight-bold text-info"
                               href="#"
                               @click="clickAddRow">
                               [ + Agregar]
                            </a>
                        </div>
                    </div>
                </el-tab-pane>
                <el-tab-pane class
                             name="fourth">
                    <span slot="label">Atributos</span>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Imágen <span class="text-danger"></span></label>
                                <el-upload :action="`/${resource}/upload`"
                                           :data="{'type': 'items', 'skip_preview': 1}"
                                           :headers="headers"
                                           :on-success="onSuccess"
                                           :before-upload="beforeImageUpload"
                                           :on-change="onFileChange"
                                           :show-file-list="false"
                                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp"
                                           class="avatar-uploader">
                                    <img v-if="form.image_url"
                                         :src="form.image_url"
                                         class="avatar">
                                    <i v-else
                                       class="el-icon-plus avatar-uploader-icon"></i>
                                </el-upload>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <div :class="{'has-danger': errors.category_id}"
                                         class="form-group">
                                        <label class="control-label">
                                            Categoría interna
                                            <el-tooltip class="item" effect="dark" placement="top"
                                                content="Uso interno (reportes, POS, contabilidad). Los compradores en marketplace verán la 'Categoría oficial del marketplace' que eliges en la pestaña Canales de venta.">
                                                <i class="fa fa-info-circle text-info" style="font-size:12px"></i>
                                            </el-tooltip>
                                            <!-- <a v-if="form_category.add == false"
                                                class="control-label font-weight-bold text-info"
                                                href="#"
                                                @click="form_category.add = true"> [ + Nuevo]</a>
                                            <a v-if="form_category.add == true"
                                                class="control-label font-weight-bold text-info"
                                                href="#"
                                                @click="saveCategory()"> [ + Guardar]</a>
                                            <a v-if="form_category.add == true"
                                                class="control-label font-weight-bold text-danger"
                                                href="#"
                                                @click="form_category.add = false"> [ Cancelar]</a> -->
                                        </label>
                                        <el-input v-if="form_category.add == true"
                                                  v-model="form_category.name"
                                                  dusk="item_code"
                                                  style="margin-bottom:1.5%;"></el-input>

                                        <el-select v-if="form_category.add == false"
                                                   v-model="form.category_id"
                                                   clearable
                                                   filterable
                                                   :filter-method="filterCategories"
                                                   @visible-change="onCategoryDropdownChange"
                                                   @keydown.enter.native.prevent="createCategoryFromSearch">
                                            <el-option v-for="option in filteredCategories"
                                                       :key="option.id"
                                                       :label="option.name"
                                                       :value="option.id"></el-option>
                                            <template slot="empty">
                                                <p v-if="loading_search" class="el-select-dropdown__empty">
                                                    Cargando...
                                                </p>
                                                <p v-else-if="categorySearchQuery" class="el-select-dropdown__empty">
                                                    No se encontraron resultados
                                                </p>
                                            
                                                <p v-else class="el-select-dropdown__empty">
                                                    No hay categorías. <br> Escriba el nombre y presione Enter para crear
                                                </p>
                                            
                                                <div
                                                    v-if="!loading_search && categorySearchQuery"
                                                    class="el-select-dropdown__item new-option"
                                                    @click.stop="createCategoryFromSearch"
                                                >
                                                    <span>Crear categoría "{{ categorySearchQuery }}"</span>
                                                </div>
                                            </template>
                                        </el-select>
                                        <small v-if="errors.category_id"
                                               class="form-control-feedback"
                                               v-text="errors.category_id[0]"></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div :class="{'has-danger': errors.brand_id}"
                                         class="form-group">
                                        <label class="control-label">
                                            Marca
                                            <!-- <a v-if="form_brand.add == false"
                                                class="control-label font-weight-bold text-info"
                                                href="#"
                                                @click="form_brand.add = true"> [ + Nuevo]</a>
                                            <a v-if="form_brand.add == true"
                                                class="control-label font-weight-bold text-info"
                                                href="#"
                                                @click="saveBrand()"> [ + Guardar]</a>
                                            <a v-if="form_brand.add == true"
                                                class="control-label font-weight-bold text-danger"
                                                href="#"
                                                @click="form_brand.add = false"> [ Cancelar]</a> -->
                                        </label>
                                        <el-input v-if="form_brand.add == true"
                                                  v-model="form_brand.name"
                                                  dusk="item_code"
                                                  style="margin-bottom:1.5%;"></el-input>

                                        <el-select v-if="form_brand.add == false"
                                                   v-model="form.brand_id"
                                                   clearable
                                                   filterable
                                                   :filter-method="filterBrands"
                                                   @visible-change="onBrandDropdownChange"
                                                   @keydown.enter.native.prevent="createBrandFromSearch">
                                            <el-option v-for="option in filteredBrands"
                                                       :key="option.id"
                                                       :label="option.name"
                                                       :value="option.id"></el-option>
                                            <template slot="empty">
                                                <p v-if="loading_search" class="el-select-dropdown__empty">
                                                    Cargando...
                                                </p>
                                            
                                                <p v-else-if="brandSearchQuery" class="el-select-dropdown__empty">
                                                    No se encontraron resultados
                                                </p>
                                                
                                                <p v-else class="el-select-dropdown__empty">
                                                    No hay marcas. <br> Escriba el nombre y presione Enter para crear
                                                </p>
                                            
                                                <div
                                                    v-if="!loading_search && brandSearchQuery"
                                                    class="el-select-dropdown__item new-option"
                                                    @click.stop="createBrandFromSearch"
                                                >
                                                    <span>Crear marca "{{ brandSearchQuery }}"</span>
                                                </div>
                                            </template>
                                        </el-select>
                                        <small v-if="errors.brand_id"
                                               class="form-control-feedback"
                                               v-text="errors.brand_id[0]"></small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div :class="{'has-danger': errors.tags_id}" class="form-group">
                                        <label class="control-label">
                                            Tags / Atributos
                                            <el-tooltip class="item" effect="dark" placement="top"
                                                content="Características transversales del producto (ecológico, hecho a mano, talla M, color rojo, edición limitada). Sirven como filtros sin duplicar la categoría.">
                                                <i class="fa fa-info-circle text-info" style="font-size:12px"></i>
                                            </el-tooltip>
                                        </label>
                                        <el-select v-model="form.tags_id"
                                                   filterable multiple clearable
                                                   placeholder="Selecciona uno o varios tags"
                                                   style="width:100%">
                                            <el-option v-for="option in tags"
                                                       :key="option.id"
                                                       :label="option.name"
                                                       :value="option.id"></el-option>
                                        </el-select>
                                        <small v-if="errors.tags_id"
                                               class="form-control-feedback"
                                               v-text="errors.tags_id[0]"></small>
                                    </div>
                                </div>
                            </div>
                            <div v-if="attribute_types.length > 0">
                                <h5 class="separator-title mb-0">
                                    Listado
                                    <el-tooltip class="item"
                                                content="Diferentes presentaciones para la venta del producto"
                                                effect="dark"
                                                placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </h5>
                            </div>
                            <div v-if="form.attributes.length > 0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 table-borderless">
                                        <thead>
                                        <tr>
                                            <th class="pb-0">Tipo</th>
                                            <th class="pb-0">Descripción</th>
                                            <th class="pb-0"></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(row, index) in form.attributes"
                                            :key="index">
                                            <td>
                                                <el-select v-model="row.attribute_type_id"
                                                           filterable
                                                           @change="changeAttributeType(index)">
                                                    <el-option v-for="option in attribute_types"
                                                               :key="option.id"
                                                               :label="option.description"
                                                               :value="option.id"></el-option>
                                                </el-select>
                                            </td>
                                            <td>
                                                <el-input v-model="row.value"></el-input>
                                            </td>
                                            <td>
                                                <button class="btn btn-danger btn-sm"
                                                        type="button"
                                                        @click.prevent="clickRemoveAttribute(index)">x
                                                </button>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <a class="control-label font-weight-bold text-info"
                               href="#"
                               @click.prevent="clickAddAttribute">[+ Agregar]</a>
                        </div>
                    </div>
                </el-tab-pane>
                <el-tab-pane class
                             v-if="!isService"
                             name="five">
                    <span slot="label">Compra</span>
                    <div class="row">
                        <div class="col-md-8">
                            <div :class="{'has-danger': errors.purchase_affectation_igv_type_id}"
                                 class="form-group">
                                <label class="control-label">Tipo de afectación</label>
                                <el-select v-model="form.purchase_affectation_igv_type_id"
                                           @change="changePurchaseAffectationIgvType">
                                    <el-option v-for="option in affectation_igv_types"
                                               :key="option.id"
                                               :label="option.description"
                                               :value="option.id"></el-option>
                                </el-select>
                                <small v-if="errors.purchase_affectation_igv_type_id"
                                       class="form-control-feedback"
                                       v-text="errors.purchase_affectation_igv_type_id[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div :class="{'has-danger': errors.purchase_unit_price}"
                                 class="form-group">
                                <label class="control-label">Precio Unitario</label>
                                <el-input v-model="form.purchase_unit_price"
                                          dusk="purchase_unit_price"
                                          @input="calculatePercentageOfProfitByPurchase"></el-input>
                                <small v-if="errors.purchase_unit_price"
                                       class="form-control-feedback"
                                       v-text="errors.purchase_unit_price[0]"></small>
                            </div>
                        </div>
                        <div v-show="purchase_show_has_igv"
                             class="col-md-4 center-el-checkbox pt-2">
                            <div :class="{'has-danger': errors.purchase_has_igv}"
                                 class="form-group">
                                <el-checkbox v-model="form.purchase_has_igv">Incluye Igv</el-checkbox>
                                <br>
                                <small v-if="errors.purchase_has_igv"
                                       class="form-control-feedback"
                                       v-text="errors.purchase_has_igv[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-4 center-el-checkbox pt-2">
                            <div class="form-group">
                                <el-checkbox v-model="enabled_percentage_of_profit"
                                             @change="changeEnabledPercentageOfProfit">Aplica ganancia
                                </el-checkbox>
                                <br>
                            </div>
                        </div>
                        <div class="col-md-4 pt-2">
                            <div :class="{'has-danger': errors.percentage_of_profit}"
                                 class="form-group">
                                <label class="control-label">Porcentaje de ganancia (%)</label>
                                <el-input v-model="form.percentage_of_profit"
                                          :disabled="!enabled_percentage_of_profit"
                                          @input="calculatePercentageOfProfitByPercentage"></el-input>
                                <small v-if="errors.percentage_of_profit"
                                       class="form-control-feedback"
                                       v-text="errors.percentage_of_profit[0]"></small>
                            </div>
                        </div>

                        <!-- isc compras -->
                        <div class="col-md-4">
                            <div :class="{'has-danger': errors.purchase_has_isc}"
                                 class="form-group">
                                <el-checkbox v-model="form.purchase_has_isc"
                                             @change="purchaseChangeIsc">Incluye ISC
                                </el-checkbox>
                                <br>
                                <small v-if="errors.purchase_has_isc"
                                       class="form-control-feedback"
                                       v-text="errors.purchase_has_isc[0]"></small>
                            </div>
                        </div>

                        <template v-if="form.purchase_has_isc">
                            <div class="col-md-4">
                                <div :class="{'has-danger': errors.purchase_system_isc_type_id}"
                                     class="form-group">
                                    <label class="control-label">Tipo de sistema ISC</label>
                                    <el-select
                                        v-model="form.purchase_system_isc_type_id"
                                        filterable>
                                        <el-option
                                            v-for="option in system_isc_types"
                                            :key="option.id"
                                            :label="option.description"
                                            :value="option.id"
                                        ></el-option>
                                    </el-select>
                                    <small
                                        v-if="errors.purchase_system_isc_type_id"
                                        class="form-control-feedback"
                                        v-text="errors.purchase_system_isc_type_id[0]"></small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div :class="{'has-danger': errors.purchase_percentage_isc}"
                                     class="form-group">
                                    <label class="control-label">Porcentaje ISC</label>
                                    <el-input v-model="form.purchase_percentage_isc"></el-input>
                                    <small
                                        v-if="errors.purchase_percentage_isc"
                                        class="form-control-feedback"
                                        v-text="errors.purchase_percentage_isc[0]"></small>
                                </div>
                            </div>
                        </template>
                        <!-- isc compras -->

                    </div>
                </el-tab-pane>

                <el-tab-pane v-if="canShowExtraData"
                             class
                             name="last">
                    <span slot="label">Informacion Adicional</span>
                    <extra-info
                        :form.sync="form"
                    ></extra-info>
                </el-tab-pane>

                <el-tab-pane class
                             v-if="form.is_for_production && canSeeProduction"
                             name="six">
                    <span slot="label">Producción</span>
                    <div class="row">

                        <div class="col-md-7 col-lg-7 col-xl-7 col-sm-7">
                            <div id="custom-select"
                                 :class="{'has-danger': errors.item_id}"
                                 class="form-group">
                                <label class="control-label">
                                    Insumo
                                </label>

                                <template id="select-append">
                                    <el-input id="custom-input">
                                        <el-select
                                            id="select-width"
                                            ref="selectSearchNormal"
                                            slot="prepend"
                                            v-model="item_suplly"
                                            :loading="loading_search"
                                            :remote-method="searchRemoteItems"
                                            filterable
                                            placeholder="Buscar"
                                            popper-class="el-select-items"
                                            remote
                                            @change="changeItem"
                                            @focus="focusSelectItem">


                                            <el-tooltip
                                                v-for="option in items"
                                                :key="option.id"
                                                placement="left">
                                                <div
                                                    slot="content"
                                                    v-html="ItemSlotTooltipView(option)"
                                                ></div>
                                                <el-option
                                                    :label="ItemOptionDescriptionView(option)"
                                                    :value="option.id"
                                                ></el-option>

                                            </el-tooltip>
                                        </el-select>
                                    </el-input>
                                </template>
                                <small v-if="errors.item_id"
                                       class="form-control-feedback"
                                       v-text="errors.item_id[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-7 col-lg-7 col-xl-7 col-sm-7 " style="    margin-top: 1rem !important;">
                            <div class="form-group ">
                                <button class="btn waves-effect waves-light btn-primary"
                                        type="button"
                                        @click.prevent="clickAddSupply" >
                                    + Agregar Producto
                                </button>
                            </div>
                        </div>
                        <div class="col-12 table-responsive" v-if="form.supplies && form.supplies.length > 0">

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th>#</th>
<!--                                        <th>item_id</th>-->
                                        <th>Insumo</th>
                                        <th>Cantidad</th>
<!--                                        <th class="text-end">Acciones</th>-->
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(row, index) in form.supplies" :key="index">
                                        <td>{{ index + 1 }}</td>
<!--                                        <td>{{ row.item_id }}</td>-->
                                        <td>{{ (row.individual_item)?row.individual_item.description:row.individual_item }}</td>
                                        <td>
                                            <el-input-number v-model="row.quantity"
                                                      ></el-input-number>
                                            </td>

                                        <!--
                                        <td class="text-end">
                                            <button type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="clickCreate(row.id)">Editar</button>

                                            <template v-if="typeUser === 'admin'">
                                                <button type="button" class="btn waves-effect waves-light btn-xs btn-danger"  @click.prevent="clickDelete(row.id)">Eliminar</button>
                                            </template>
                                        </td>
                                        -->
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!--
                        <div class="col-md-4">
                            <div :class="{'has-danger': errors.purchase_unit_price}"
                                 class="form-group">
                                <label class="control-label">Precio Unitario</label>
                                <el-input v-model="form.purchase_unit_price"
                                          dusk="purchase_unit_price"
                                          @input="calculatePercentageOfProfitByPurchase"></el-input>
                                <small v-if="errors.purchase_unit_price"
                                       class="form-control-feedback"
                                       v-text="errors.purchase_unit_price[0]"></small>
                            </div>
                        </div>
                        <div v-show="purchase_show_has_igv"
                             class="col-md-4 center-el-checkbox pt-2">
                            <div :class="{'has-danger': errors.purchase_has_igv}"
                                 class="form-group">
                                <el-checkbox v-model="form.purchase_has_igv">Incluye Igv</el-checkbox>
                                <br>
                                <small v-if="errors.purchase_has_igv"
                                       class="form-control-feedback"
                                       v-text="errors.purchase_has_igv[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-4 center-el-checkbox pt-2">
                            <div class="form-group">
                                <el-checkbox v-model="enabled_percentage_of_profit"
                                             @change="changeEnabledPercentageOfProfit">Aplica ganancia
                                </el-checkbox>
                                <br>
                            </div>
                        </div>
                        <div class="col-md-4 pt-2">
                            <div :class="{'has-danger': errors.percentage_of_profit}"
                                 class="form-group">
                                <label class="control-label">Porcentaje de ganancia (%)</label>
                                <el-input v-model="form.percentage_of_profit"
                                          :disabled="!enabled_percentage_of_profit"
                                          @input="calculatePercentageOfProfitByPercentage"></el-input>
                                <small v-if="errors.percentage_of_profit"
                                       class="form-control-feedback"
                                       v-text="errors.percentage_of_profit[0]"></small>
                            </div>
                        </div>
                        -->
                    </div>
                </el-tab-pane>

                <!-- ── Tab Variantes ──────────────────────────────────── -->
                <el-tab-pane v-if="!isService" name="variants">
                    <span slot="label">
                        <i class="el-icon-s-grid"></i> Variantes
                    </span>
                    <variants-tab
                        :item-id="form.id || null"
                        :parent-price="parseFloat(form.sale_unit_price) || 0"
                        @variants-updated="onVariantsUpdated"
                    />
                </el-tab-pane>

            </el-tabs>
            <div class="form-actions text-end pt-2 mt-2">
                <template v-if="forOnlyShowAllDetails">
                    <el-button @click.prevent="close()">Cerrar</el-button>
                </template>
                <template v-else>
                    <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                    <el-button :loading="loading_submit || uploadingImage"
                            :disabled="uploadingImage"
                            native-type="submit"
                            type="primary">{{ uploadingImage ? 'Subiendo imagen…' : 'Guardar' }}
                    </el-button>
                </template>
            </div>
        </form>

        <lots-form
            :lots="form.lots"
            :recordId="recordId"
            :showDialog.sync="showDialogLots"
            :stock="form.stock"
            @addRowLot="addRowLot">
        </lots-form>

        <el-dialog title="Solicitar nueva categoría" :visible.sync="mp_category_request_dialog" width="500px" append-to-body>
            <div style="font-size:13px;color:#555;margin-bottom:12px">
                Si no encuentras una categoría adecuada, envíala al equipo de ebaemy. Te avisaremos cuando sea aprobada.
            </div>
            <el-form label-position="top" size="small">
                <el-form-item label="Nombre sugerido *">
                    <el-input v-model="mp_category_request_form.suggested_name" placeholder="Ej: Accesorios para gatos"></el-input>
                </el-form-item>
                <el-form-item label="Categoría padre sugerida (opcional)">
                    <el-cascader
                        v-model="mp_category_request_form._parent_path"
                        :options="mp_category_tree"
                        :props="{ value: 'id', label: 'name', children: 'children', checkStrictly: true, emitPath: true }"
                        :show-all-levels="true"
                        placeholder="Sin padre (categoría raíz)"
                        filterable clearable style="width:100%"
                        @change="onMpRequestParentChange">
                    </el-cascader>
                </el-form-item>
                <el-form-item label="Descripción / motivo (opcional)">
                    <el-input v-model="mp_category_request_form.description" type="textarea" :rows="3"
                        placeholder="¿Qué tipo de productos publicarías aquí?"></el-input>
                </el-form-item>
            </el-form>
            <span slot="footer">
                <el-button size="small" @click="mp_category_request_dialog = false">Cancelar</el-button>
                <el-button size="small" type="primary" :loading="mp_category_request_sending" @click="submitMpCategoryRequest">Enviar solicitud</el-button>
            </span>
        </el-dialog>

    </el-dialog>
</template>

<script>
import LotsForm from './partials/lots.vue'
import ExtraInfo from './partials/extra_info.vue'
import VariantsTab from './partials/variants-tab.vue'
import {mapActions, mapState} from "vuex";
import {ItemOptionDescription, ItemSlotTooltip} from "../../../helpers/modal_item";


export default {
    props: [
        'showDialog',
        'recordId',
        'external',
        'type',
        'pharmacy',
        'onlyShowAllDetails',
        'input_item',
    ],
    components: {
        LotsForm,
        ExtraInfo,
        VariantsTab,
    },
    computed: {
        forOnlyShowAllDetails()
        {
            if(this.onlyShowAllDetails != undefined && this.onlyShowAllDetails != null) return this.onlyShowAllDetails

            return false
        },
        ...mapState([
            'colors',
            'CatItemSize',
            'CatItemUnitsPerPackage',
            'CatItemMoldProperty',
            'CatItemUnitBusiness',
            'CatItemStatus',
            'CatItemPackageMeasurement',
            'CatItemMoldCavity',
            'CatItemProductFamily',
            'config',
        ]),
        isService: function () {
            // Tener en cuenta que solo oculta las pestañas para tipo servicio.
            if (this.form !== undefined) {
                // Es servicio por selección
                if (this.form.unit_type_id !== undefined && this.form.unit_type_id === 'ZZ') {
                    if (
                        this.activeName == 'second' ||
                        this.activeName == 'third' ||
                        this.activeName == 'five'
                    ) {
                        this.activeName = 'first';
                    }
                    return true;
                }
            }
            return false;
        },
        canSeeProduction:function(){
            if(this.config && this.config.production_app) return this.config.production_app
            return false;
        },
        requireSupply:function(){

            if(this.form.is_for_production) {

                if( this.form.is_for_production == true) return true
            };
            return false;
        },

        canShowExtraData: function () {
            if (this.config && this.config.show_extra_info_to_item !== undefined) {
                return this.config.show_extra_info_to_item;
            }
            return false;
        },
        showPharmaElement() {

            if (this.fromPharmacy === true) return true;
            if (this.config.is_pharmacy === true) return true;
            return false;
        },
        showPointSystem()
        {
            if(this.config) return this.config.enabled_point_system

            return false
        },
        showRestrictSaleItemsCpe()
        {
            if(this.config) return this.config.restrict_sale_items_cpe

            return false
        }

    },

    data() {
        return {
            loading_search: false,
            showDialogLots: false,
            form_category: {add: false, name: null, id: null},
            form_brand: {add: false, name: null, id: null},
            warehouses: [],
            items: [],
            loading_submit: false,
            uploadingImage: false,
            categorySearchQuery: '',
            filteredCategories: [],
            brandSearchQuery: '',
            filteredBrands: [],
            showPercentagePerception: false,
            has_percentage_perception: false,
            percentage_perception: null,
            enabled_percentage_of_profit: false,
            titleDialog: null,
            resource: 'items',
            errors: {},
            item_suplly: {},
            headers: headers_token,
            form: {
                item_supplies:[],
                is_for_production:false,
            },
            // configuration: {},
            unit_types: [],
            currency_types: [],
            system_isc_types: [],
            affectation_igv_types: [],
            categories: [],
            brands: [],
            tags: [],
            accounts: [],
            show_has_igv: true,
            purchase_show_has_igv: true,
            have_account: false,
            item_unit_type: {
                id: null,
                unit_type_id: null,
                quantity_unit: 0,
                price1: 0,
                price2: 0,
                price3: 0,
                price_default: 2,

            },
            attribute_types: [],
            activeName: 'first',
            fromPharmacy: false,
            inventory_configuration: null,
            mp_category_tree: [],
            mp_category_loading: false,
            mp_category_request_dialog: false,
            mp_category_request_form: {
                suggested_name: '',
                suggested_parent_id: null,
                description: '',
                _parent_path: [],
            },
            mp_category_request_sending: false,
            mp_category_suggestions: [],
        }
    },
    async created() {
        this.loadConfiguration()
        if (this.pharmacy !== undefined && this.pharmacy == true) {
            this.fromPharmacy = true;
        }
        await this.initForm();

        await this.$http.get(`/${this.resource}/tables`)
            .then(response => {
                let data = response.data;
                this.unit_types = data.unit_types
                this.accounts = data.accounts
                this.currency_types = data.currency_types
                this.system_isc_types = data.system_isc_types
                this.affectation_igv_types = data.affectation_igv_types
                this.warehouses = data.warehouses
                this.categories = data.categories
                this.brands = data.brands
                this.tags = data.tags || []
                this.attribute_types = data.attribute_types
                // this.config = data.configuration
                if (this.canShowExtraData) {
                    this.$store.commit('setColors', data.colors);
                    this.$store.commit('setCatItemSize', data.CatItemSize);
                    this.$store.commit('setCatItemUnitsPerPackage', data.CatItemUnitsPerPackage);
                    this.$store.commit('setCatItemStatus', data.CatItemStatus);
                    this.$store.commit('setCatItemMoldCavity', data.CatItemMoldCavity);
                    this.$store.commit('setCatItemMoldProperty', data.CatItemMoldProperty);
                    this.$store.commit('setCatItemUnitBusiness', data.CatItemUnitBusiness);
                    this.$store.commit('setCatItemPackageMeasurement', data.CatItemPackageMeasurement);
                    this.$store.commit('setCatItemProductFamily', data.CatItemPackageMeasurement);
                }
                this.$store.commit('setConfiguration', data.configuration);


                this.loadConfiguration()
                this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
                this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
                this.inventory_configuration = data.inventory_configuration;
                this.filteredCategories = this.categories;
                this.filteredBrands = this.brands;
            })

        this.$eventHub.$on('submitPercentagePerception', (data) => {
            this.form.percentage_perception = data
            if (!this.form.percentage_perception) this.has_percentage_perception = false
        })

        this.$eventHub.$on('reloadTables', () => {
            this.reloadTables()
        })

        await this.setDefaultConfiguration()

        this.$eventHub.$on('establishmentChanged', () => {
            this.loadCurrentEstablishment();
        });

    },
    beforeDestroy() {
        this.$eventHub.$off('establishmentChanged');
    },

    watch: {
        'form.unit_type_id'(newValue) {
            if (!this.config.enable_list_product && this.form.item_unit_types.length > 0) {
                const selectedUnit = this.unit_types.find(u => u.id === newValue);
                const unitDescription = selectedUnit ? selectedUnit.description : '';

                this.form.item_unit_types.forEach(item => {
                    if (!item.id) {
                        item.unit_type_id = newValue;
                        item.description = unitDescription;
                        item.quantity_unit = 1;
                    }
                });
            }
        },
        'form.marketplace_publishable'(val) {
            if (val) {
                this.loadMarketplaceCategoryTree()
                this.suggestMpCategoryFromTenant()
            } else {
                this.mp_category_suggestions = []
            }
        },
        'form.category_id'() {
            if (this.form.marketplace_publishable && !this.form.marketplace_category_id) {
                this.suggestMpCategoryFromTenant()
            }
        },
    },

    methods: {

        ...mapActions([
            'loadConfiguration',
        ]),
        setDefaultConfiguration() {
            this.form.sale_affectation_igv_type_id = (this.config) ? this.config.affectation_igv_type_id : '10'

            this.$http.get(`/configurations/record`).then(response => {
                this.form.has_igv = response.data.data.include_igv
                this.form.purchase_has_igv = response.data.data.include_igv
                // this.$setStorage('configuration',response.data.data)
                this.$store.commit('setConfiguration', response.data.data);
                this.loadConfiguration()
            })
        },
        purchaseChangeIsc() {

            if (!this.form.purchase_has_isc) {
                this.form.purchase_system_isc_type_id = null
                this.form.purchase_percentage_isc = 0
            }

        },
        changeIsc() {

            if (!this.form.has_isc) {
                this.form.system_isc_type_id = null
                this.form.percentage_isc = 0
            }

        },
        clickAddAttribute() {
            this.form.attributes.push({
                attribute_type_id: null,
                description: null,
                value: null,
                start_date: null,
                end_date: null,
                duration: null,
            })
        },
        async reloadTables() {
            await this.$http.get(`/${this.resource}/tables`)
                .then(response => {
                    this.unit_types = response.data.unit_types
                    this.accounts = response.data.accounts
                    this.currency_types = response.data.currency_types
                    this.system_isc_types = response.data.system_isc_types
                    this.affectation_igv_types = response.data.affectation_igv_types
                    this.warehouses = response.data.warehouses
                    this.categories = response.data.categories
                    this.brands = response.data.brands
                    this.filteredCategories = this.categories
                    this.filteredBrands = this.brands

                    this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
                    this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
                })
        },
        changeLotsEnabled() {

            // if(!this.form.lots_enabled){
            //     this.form.lot_code = null
            //     this.form.lots = []
            // }

        },
        changeProductioTab(){

        },
        onVariantsUpdated(variants) {
            // Actualiza el stock del form si el servidor propagó el cambio
            if (variants && variants.length > 0) {
                const total = variants.reduce((sum, v) => sum + (parseFloat(v.stock) || 0), 0)
                this.form.stock = total
            }
        },
        addRowLot(lots) {
            this.form.lots = lots
        },
        clickLotcode() {
            this.showDialogLots = true
        },
        changeHaveAccount() {
            if (!this.have_account) this.form.account_id = null
        },
        changeEnabledPercentageOfProfit() {
            // if(!this.enabled_percentage_of_profit) this.form.percentage_of_profit = 0
        },
        clickDelete(id) {

            this.$http.delete(`/${this.resource}/item-unit-type/${id}`)
                .then(res => {
                    if (res.data.success) {
                        this.loadRecord()
                        this.$message.success('Se eliminó correctamente el registro')
                    }
                })
                .catch(error => {
                    if (error.response.status === 500) {
                        this.$message.error('Error al intentar eliminar');
                    } else {
                        console.log(error.response.data.message)
                    }
                })

        },
        changeHasPerception() {
            if (!this.form.has_perception) {
                this.form.percentage_perception = null
            }
        },
        clickAddRow() {
            let unitTypeId = 'NIU';
            let description = null;
            let quantityUnit = 0;
            
            if (!this.config.enable_list_product) {
                unitTypeId = this.form.unit_type_id;
                const selectedUnit = this.unit_types.find(u => u.id === unitTypeId);
                description = selectedUnit ? selectedUnit.description : null;
                quantityUnit = 1;
            }
            
            this.form.item_unit_types.push({
                id: null,
                description: description,
                unit_type_id: unitTypeId,
                quantity_unit: quantityUnit,
                price1: 0,
                price2: 0,
                price3: 0,
                price_default: 2,
                barcode: null
            })
        },
        clickCancel(index) {
            this.form.item_unit_types.splice(index, 1)
        },
        initForm() {
            this.loading_submit = false,
            this.errors = {}

            this.form = {
                id: null,
                colors: [],
                item_type_id: '01',
                internal_id: null,
                item_code: null,
                item_code_gs1: null,
                description: null,
                name: null,
                second_name: null,
                unit_type_id: 'NIU',
                currency_type_id: 'PEN',
                sale_unit_price: 0,
                purchase_unit_price: 0,
                has_isc: false,
                system_isc_type_id: null,
                percentage_isc: 0,
                suggested_price: 0,
                sale_affectation_igv_type_id: null,
                purchase_affectation_igv_type_id: null,
                calculate_quantity: false,
                stock: 0,
                stock_min: 1,
                weight: null,
                length: null,
                width: null,
                height: null,
                has_igv: true,
                has_perception: false,
                item_unit_types: [],
                percentage_of_profit: 0,
                percentage_perception: null,
                image: null,
                image_url: null,
                temp_path: null,
                is_set: false,
                account_id: null,
                category_id: null,
                brand_id: null,
                tags_id: [],
                date_of_due: null,
                lot_code: null,
                line: null,
                lots_enabled: false,
                lots: [],
                attributes: [],
                series_enabled: false,
                purchase_has_igv: true,
                web_platform_id: null,
                has_plastic_bag_taxes: false,
                item_warehouse_prices: [],
                item_supplies:[],

                purchase_has_isc: false,
                purchase_system_isc_type_id: null,
                purchase_percentage_isc: 0,
                subject_to_detraction: false,

                exchange_points: false,
                quantity_of_points: 0,
                factory_code: null,
                restrict_sale_cpe: false,
                warehouse_id: null,

                // Canales de venta (Opción A de unificación UX)
                apply_store: false,
                marketplace_publishable: false,
                mp_price: null,
                mp_notes: null,
                mp_status: null,
                marketplace_category_id: null,
                marketplace_category_path: [],
            }

            this.show_has_igv = true
            this.purchase_show_has_igv = true
            this.enabled_percentage_of_profit = false
            this.loadCurrentEstablishment()
        },
        // Validación del lado del cliente antes de enviar al servidor.
        // Permite cualquier nombre de archivo — no se valida el nombre, solo peso y tipo.
        beforeImageUpload(file) {
            const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp']
            const MAX_MB        = 15

            if (!ALLOWED_TYPES.includes(file.type)) {
                this.$message.error('Solo se permiten imágenes JPG, PNG, GIF, WEBP o BMP.')
                return false
            }

            const sizeMB = file.size / 1024 / 1024
            if (sizeMB > MAX_MB) {
                this.$message.error(`La imagen es demasiado grande (${sizeMB.toFixed(1)} MB). Máximo ${MAX_MB} MB.`)
                return false
            }

            return true
        },

        onSuccess(response, file, fileList) {
            this.uploadingImage = false
            if (response.success) {
                this.form.image = response.data.filename
                this.form.temp_path = response.data.temp_path
                // Si el server no devolvió temp_image (skip_preview), el preview ya
                // se mostró desde onFileChange con un blob URL local.
                if (response.data.temp_image) {
                    this.form.image_url = response.data.temp_image
                }
            } else {
                this.$message.error(response.message)
            }
        },
        // Maneja preview local instantáneo + estado de subida.
        // El preview se pinta en cuanto se selecciona el archivo (sin esperar al server),
        // pero `uploadingImage=true` bloquea el botón Guardar hasta que el server confirme
        // el temp_path — sin ese path el backend no procesa la nueva imagen.
        onFileChange(file) {
            if (!file || !file.raw) return
            if (file.raw.type && file.raw.type.startsWith('image/')) {
                if (this.form.image_url && typeof this.form.image_url === 'string' && this.form.image_url.startsWith('blob:')) {
                    URL.revokeObjectURL(this.form.image_url)
                }
                this.form.image_url = URL.createObjectURL(file.raw)
            }
            if (file.status === 'ready' || file.status === 'uploading') {
                this.uploadingImage = true
            } else if (file.status === 'success' || file.status === 'fail') {
                this.uploadingImage = false
            }
        },
        changeAffectationIgvType() {
            
            let affectation_igv_type_exonerated = [20, 21, 30, 31, 32, 33, 34, 35, 36, 37]
            let is_exonerated = affectation_igv_type_exonerated.includes((parseInt(this.form.sale_affectation_igv_type_id)));

            if (is_exonerated) {
                this.show_has_igv = false
                this.form.has_igv = true
            } else {
                this.show_has_igv = true
            }

        },
        changePurchaseAffectationIgvType() {

            let affectation_igv_type_exonerated = [20, 21, 30, 31, 32, 33, 34, 35, 36, 37]
            let is_exonerated = affectation_igv_type_exonerated.includes((parseInt(this.form.purchase_affectation_igv_type_id)));

            if (is_exonerated) {
                this.purchase_show_has_igv = false
                this.form.purchase_has_igv = true
            } else {
                this.purchase_show_has_igv = true
            }

        },
        resetForm() {
            this.initForm()
            this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
            this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
            this.setDefaultConfiguration()
        },
        setDialogTitle()
        {
            if(this.forOnlyShowAllDetails)
            {
                this.titleDialog = 'Ver Producto'
            }
            else
            {
                this.titleDialog = (this.recordId) ? 'Editar Producto' : 'Nuevo Producto'
            }
        },

        async create() {
            // console.log(this.warehouses)
            // this.warehouses = this.warehouses.map(w => {
            //     delete w.price;
            //     return w;
            // });
this.activeName =  'first'
            if (this.type) {
                if (this.type !== 'PRODUCTS') {
                    this.form.unit_type_id = 'ZZ';
                }
            }

            this.setDialogTitle()

            if (this.recordId) {
                await this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                        // Hidratar campos array que pueden venir null/undefined
                        // del backend (rompe v-model del cascader y multiselect).
                        if (!Array.isArray(this.form.marketplace_category_path)) {
                            this.form.marketplace_category_path = []
                        }
                        if (!Array.isArray(this.form.tags_id)) {
                            this.form.tags_id = this.form.tags_id ? Array.from(this.form.tags_id) : []
                        }
                        this.has_percentage_perception = (this.form.percentage_perception) ? true : false
                        this.changeAffectationIgvType()
                        this.changePurchaseAffectationIgvType()
                        // Precargar árbol oficial si el item ya está en marketplace.
                        // El watcher de marketplace_publishable no dispara al hidratar
                        // (no hay "cambio") y sin árbol el cascader no muestra la
                        // categoría guardada.
                        if (this.form.marketplace_publishable) {
                            this.loadMarketplaceCategoryTree()
                        }
                        // let warehousePrices = response.data.data.warehouse_prices;
                        // console.error(warehousePrices);
                        // if (warehousePrices.length > 0) {
                        //     this.warehouses = this.warehouses.map(w => {
                        //         let price = warehousePrices.find(wp => wp.warehouse_id === w.id);
                        //         if (price) {
                        //             var priceToJson = {...price};
                        //             w.price = priceToJson.price;
                        //         }
                        //         return w;
                        //     });
                        // } else {
                        //     this.warehouses = this.warehouses.map(w => {
                        //         delete w.price;
                        //         return w;
                        //     });
                        // }
                    })

            }

            this.setDataToItemWarehousePrices()

            if (this.warehouses.length === 0) {
                await this.reloadTables();
            }
    
            this.setDialogTitle();

            if (this.recordId) {
                await this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data;
                        this.has_percentage_perception = (this.form.percentage_perception) ? true : false;
                        this.changeAffectationIgvType();
                        this.changePurchaseAffectationIgvType();
                    });
            } else {
                
                this.loadCurrentEstablishment();
                if (this.external && this.input_item && typeof this.input_item === 'string') {
                    this.form.description = this.input_item;
                }
            }

         this.setDataToItemWarehousePrices();
         // Función para recargar validaciones para afectaciones que han sido establecidos por defecto en configuración 
         this.changeAffectationIgvType();

        },
        setDataToItemWarehousePrices() {

            this.warehouses.forEach(warehouse => {

                let item_warehouse_price = _.find(this.form.item_warehouse_prices, {warehouse_id: warehouse.id})

                if (!item_warehouse_price) {

                    this.form.item_warehouse_prices.push({
                        id: null,
                        item_id: null,
                        warehouse_id: warehouse.id,
                        price: null,
                        description: warehouse.description,
                    })
                }

            });

            this.form.item_warehouse_prices = _.orderBy(this.form.item_warehouse_prices, ['warehouse_id'])

        },
        loadRecord() {
            if (this.recordId) {
                this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                        if (!Array.isArray(this.form.marketplace_category_path)) {
                            this.form.marketplace_category_path = []
                        }
                        if (!Array.isArray(this.form.tags_id)) {
                            this.form.tags_id = this.form.tags_id ? Array.from(this.form.tags_id) : []
                        }
                        this.changeAffectationIgvType()
                        this.changePurchaseAffectationIgvType()
                        // Si el item ya viene con marketplace activado, precarga el árbol
                        // (el watcher no dispara porque no hay "cambio" de valor).
                        if (this.form.marketplace_publishable) {
                            this.loadMarketplaceCategoryTree()
                        }
                    })
            }
        },
        loadMarketplaceCategoryTree() {
            if (this.mp_category_tree.length || this.mp_category_loading) return
            this.mp_category_loading = true
            this.$http.get('/marketplace-categories/tree')
                .then(res => { this.mp_category_tree = res.data.tree || [] })
                .catch(() => { this.mp_category_tree = [] })
                .then(() => { this.mp_category_loading = false })
        },
        onMpCategoryChange(path) {
            const lastId = Array.isArray(path) && path.length ? path[path.length - 1] : null
            this.form.marketplace_category_id = lastId
            if (lastId) this.mp_category_suggestions = []
        },
        suggestMpCategoryFromTenant() {
            if (this.form.marketplace_category_id) return
            const cat = (this.categories || []).find(c => c.id === this.form.category_id)
            const name = cat ? (cat.name || '') : ''
            if (!name || name.trim().length < 2) {
                this.mp_category_suggestions = []
                return
            }
            this.$http.get('/marketplace-categories/suggest', { params: { q: name.trim() } })
                .then(res => { this.mp_category_suggestions = res.data.suggestions || [] })
                .catch(() => { this.mp_category_suggestions = [] })
        },
        applyMpSuggestion(suggestion) {
            this.form.marketplace_category_path = suggestion.path_ids
            this.form.marketplace_category_id   = suggestion.id
            this.mp_category_suggestions = []
        },
        openMpCategoryRequest() {
            this.mp_category_request_form = {
                suggested_name: '',
                suggested_parent_id: null,
                description: '',
                _parent_path: [],
            }
            this.mp_category_request_dialog = true
        },
        onMpRequestParentChange(path) {
            const lastId = Array.isArray(path) && path.length ? path[path.length - 1] : null
            this.mp_category_request_form.suggested_parent_id = lastId
        },
        submitMpCategoryRequest() {
            if (!this.mp_category_request_form.suggested_name || !this.mp_category_request_form.suggested_name.trim()) {
                return this.$message.error('Ingresa el nombre sugerido de la categoría.')
            }
            this.mp_category_request_sending = true
            this.$http.post('/marketplace-categories/request-new', {
                suggested_name: this.mp_category_request_form.suggested_name.trim(),
                suggested_parent_id: this.mp_category_request_form.suggested_parent_id,
                description: this.mp_category_request_form.description,
                product_id: this.form.id,
            })
                .then(res => {
                    if (res.data.success) {
                        this.$message.success(res.data.message || 'Solicitud enviada.')
                        this.mp_category_request_dialog = false
                    } else {
                        this.$message.error(res.data.message || 'No se pudo enviar la solicitud.')
                    }
                })
                .catch(err => {
                    const msg = (err.response && err.response.data && err.response.data.message) || 'Error al enviar la solicitud.'
                    this.$message.error(msg)
                })
                .then(() => { this.mp_category_request_sending = false })
        },
        calculatePercentageOfProfitBySale() {
            let difference = parseFloat(this.form.sale_unit_price) - parseFloat(this.form.purchase_unit_price);

            if (parseFloat(this.form.purchase_unit_price) === 0) {
                this.form.percentage_of_profit = 0;
            } else {
                if (this.enabled_percentage_of_profit) this.form.percentage_of_profit = difference / parseFloat(this.form.purchase_unit_price) * 100;
            }
        },
        calculatePercentageOfProfitByPurchase() {
            if (this.form.percentage_of_profit === '') {
                this.form.percentage_of_profit = 0;
            }

            if (this.enabled_percentage_of_profit) this.form.sale_unit_price = (this.form.purchase_unit_price * (100 + parseFloat(this.form.percentage_of_profit))) / 100
        },
        calculatePercentageOfProfitByPercentage() {
            if (this.form.percentage_of_profit === '') {
                this.form.percentage_of_profit = 0;
            }

            if (this.enabled_percentage_of_profit) this.form.sale_unit_price = (this.form.purchase_unit_price * (100 + parseFloat(this.form.percentage_of_profit))) / 100
        },
        validateItemUnitTypes() {

            let error_by_item = 0

            if (this.form.item_unit_types.length > 0) {

                this.form.item_unit_types.forEach(item => {

                    if (parseFloat(item.quantity_unit) < 0.0001) {
                        error_by_item++
                    }

                })

            }

            return error_by_item

        },
        async submit() {
            if (this.uploadingImage) {
                return this.$message.warning('Espera a que termine de subir la imagen.')
            }

            const stock = parseInt(this.form.stock);
            if(isNaN(stock)){
                 return this.$message.error('Stock Inicial debe ser un número entero.');
            }

            if (this.validateItemUnitTypes() > 0) return this.$message.error('El campo factor no puede ser menor a 0.0001');

            if (this.fromPharmacy === true) {
                if (this.form.cod_digemid === null) {
                    return this.$message.error('Debe haber un codigo DIGEMID');
                }
                if (this.form.sanitary === null) {
                    return this.$message.error('Debe haber un Registro Sanitario');
                }
            }
            if (this.form.has_perception && !this.form.percentage_perception) return this.$message.error('Ingrese un porcentaje');

            if (this.form.marketplace_publishable && !this.form.marketplace_category_id) {
                return this.$message.error('Selecciona una categoría oficial en "Canales de venta" antes de publicar en marketplace.')
            }

            if (this.form.lots_enabled && stock > 0) {

                if (!this.form.lot_code)
                    return this.$message.error('Código de lote es requerido');

                if (!this.form.date_of_due)
                    return this.$message.error('Fecha de vencimiento es requerido si lotes esta habilitado.');
            }

            if (!this.recordId && this.form.series_enabled) {

                if (this.form.lots.length > this.form.stock)
                    return this.$message.error('La cantidad de series registradas es superior al stock');

                if (this.form.lots.length != this.form.stock)
                    return this.$message.error('La cantidad de series registradas son diferentes al stock');
            }

            if (this.form.has_isc) {
                if (this.form.percentage_isc <= 0)
                    return this.$message.error('El porcentaje isc debe ser mayor a 0');
            }

            if (this.form.purchase_has_isc) {
                if (this.form.purchase_percentage_isc <= 0)
                    return this.$message.error('El porcentaje isc debe ser mayor a 0 (Compras)');
            }

            this.loading_submit = true


            await this.$http.post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        if (this.external) {
                            this.$eventHub.$emit('reloadDataItems', response.data.id)
                        } else {
                            this.$eventHub.$emit('reloadData')
                        }
                        this.close()
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data
                    } else {
                        console.log(error)
                        this.$message.error(error.response.data.message)
                    }
                })
                .then(() => {
                    this.loading_submit = false
                })
        },
        close() {
            this.$emit('update:showDialog', false)
            this.resetForm()
        },
        changeHasIsc() {
            this.form.system_isc_type_id = null
            this.form.percentage_isc = 0
            this.form.suggested_price = 0
        },
        changeSystemIscType() {
            if (this.form.system_isc_type_id !== '03') {
                this.form.suggested_price = 0
            }
        },
        saveCategory() {
            this.form_category.add = false

            this.$http.post(`/categories`, this.form_category)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.categories.push(response.data.data)
                        this.filteredCategories = this.categories
                        this.form_category.name = null
                    } else {
                        this.$message.error('No se guardaron los cambios')
                    }
                })
                .catch(error => {

                })
        },
        filterCategories(query) {
            this.categorySearchQuery = query
            
            if (query) {
                this.filteredCategories = this.categories.filter(category => {
                    return category.name.toLowerCase().includes(query.toLowerCase())
                })
            } else {
                this.filteredCategories = this.categories
            }
        },
        onCategoryDropdownChange(visible) {
            if (!visible) {
                // Reset cuando se cierra
                this.categorySearchQuery = ''
            } else {
                // Inicializar cuando se abre
                this.filteredCategories = this.categories
            }
        },
        createCategoryFromSearch() {
            const categoryName = this.categorySearchQuery
            
            if (!categoryName || categoryName.trim() === '') {
                return
            }

            this.form_category.name = categoryName
            
            this.$http.post(`/categories`, this.form_category)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.categories.push(response.data.data)
                        this.filteredCategories = this.categories
                        
                        this.$nextTick(() => {
                            this.form.category_id = response.data.data.id
                        })
                        
                        this.form_category.name = null
                        this.categorySearchQuery = ''
                    } else {
                        this.$message.error('No se guardaron los cambios')
                    }
                })
                .catch(error => {
                    this.$message.error('Error al crear la categoría')
                })
        },
        saveBrand() {
            this.form_brand.add = false

            this.$http.post(`/brands`, this.form_brand)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.brands.push(response.data.data)
                        this.filteredBrands = this.brands
                        this.form_brand.name = null

                    } else {
                        this.$message.error('No se guardaron los cambios')
                    }
                })
                .catch(error => {

                })


        },
        filterBrands(query) {
            this.brandSearchQuery = query
            
            if (query) {
                this.filteredBrands = this.brands.filter(brand => {
                    return brand.name.toLowerCase().includes(query.toLowerCase())
                })
            } else {
                this.filteredBrands = this.brands
            }
        },
        onBrandDropdownChange(visible) {
            if (!visible) {
                // Reset cuando se cierra
                this.brandSearchQuery = ''
            } else {
                // Inicializar cuando se abre
                this.filteredBrands = this.brands
            }
        },
        createBrandFromSearch() {
            const brandName = this.brandSearchQuery
            
            if (!brandName || brandName.trim() === '') {
                return
            }

            this.form_brand.name = brandName
            
            this.$http.post(`/brands`, this.form_brand)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.brands.push(response.data.data)
                        this.filteredBrands = this.brands
                        
                        this.$nextTick(() => {
                            this.form.brand_id = response.data.data.id
                        })
                        
                        this.form_brand.name = null
                        this.brandSearchQuery = ''
                    } else {
                        this.$message.error('No se guardaron los cambios')
                    }
                })
                .catch(error => {
                    this.$message.error('Error al crear la marca')
                })
        },
        changeAttributeType(index) {
            let attribute_type_id = this.form.attributes[index].attribute_type_id
            let attribute_type = _.find(this.attribute_types, {id: attribute_type_id})
            this.form.attributes[index].description = attribute_type.description
        },
        clickRemoveAttribute(index) {
            this.form.attributes.splice(index, 1)
        },
        async searchRemoteItems(input) {
            if (input.length > 2) {
                this.loading_search = true
                const params = {
                    'input': input,
                    'search_by_barcode': this.search_item_by_barcode ? 1 : 0,
                    'production':1
                }
                await this.$http.get(`/${this.resource}/search-items/`, {params})
                    .then(response => {
                        this.items = response.data.items
                        this.loading_search = false
                        // this.enabledSearchItemsBarcode()
                        // this.enabledSearchItemBySeries()
                        if (this.items.length == 0) {
                            // this.filterItems()
                        }
                    })
            } else {
                // await this.filterItems()
            }

        },
        getItems() {
            this.$http.get(`/${this.resource}/item/tables`).then(response => {
                this.items = response.data.items
            })
        },
        changeItem() {
            this.getItems();
            this.item_suplly = _.find(this.items, {'id': this.item_suplly});
            /*
            this.form.unit_price = this.item_suplly.sale_unit_price;

            this.lots = this.item_suplly.lots

            this.form.has_igv = this.item_suplly.has_igv;

            this.form.affectation_igv_type_id = this.item_suplly.sale_affectation_igv_type_id;
            this.form.quantity = 1;
            this.item_unit_types = this.item_suplly.item_unit_types;

            (this.item_unit_types.length > 0) ? this.has_list_prices = true : this.has_list_prices = false;
            */

        },
        focusSelectItem() {
            this.$refs.selectSearchNormal.$el.getElementsByTagName('input')[0].focus()
        },

        ItemSlotTooltipView(item) {
            return ItemSlotTooltip(item);
        },
        ItemOptionDescriptionView(item) {
            return ItemOptionDescription(item)
        },
        clickAddSupply(){
            // item_supplies
            if(this.form.supplies === undefined) this.form.supplies = [];
            let item = this.item_suplly;
            if(item === null) return false;
            if(item === undefined) return false;
            if(item.id=== undefined) return false;
            this.items = [];
            this.item_suplly = {}

            item.item_id = this.form.id
            //item.individual_item_id = item.id
            item.individual_item_id = item.id
            item.individual_item = {
                'description':item.description
            }
            //item.individual_item = item
            // item.quantity = 0
            //if(isNaN(item.quantity)) item.quantity = 0 ;
            this.form.supplies.push(item)
            this.changeItem()


        },
        loadCurrentEstablishment() {
            this.$http.get('/establishments/getEstablishmentActive')
                .then(response => {
                    if (response.data.success) {
                        const establishment = response.data.establishment;
                        
                        if (establishment && this.warehouses.length > 0) {
                            const relatedWarehouse = this.warehouses.find(w => 
                                w.description.includes(establishment.description));
                    
                            if (relatedWarehouse) {
                                this.form.warehouse_id = relatedWarehouse.id;
                            } else {
                                
                                this.form.warehouse_id = this.warehouses[0].id;
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error al obtener la sucursal activa:', error);
                });
        },
    }
}
</script>
