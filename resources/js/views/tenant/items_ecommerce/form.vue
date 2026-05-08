<template>
    <el-dialog :close-on-click-modal="false"
               :title="titleDialog"
               :visible="showDialog"
               append-to-body
               top="7vh"
               width="65%"
               @close="close"
               @open="create">

        <!-- Banner: edición rápida sobre el catálogo maestro -->
        <div style="margin:-8px 0 12px;padding:10px 14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:13px">
            <span style="font-size:18px;line-height:1">📚</span>
            <div style="flex:1;color:#065f46">
                <strong>Edición rápida del catálogo.</strong> Cambios aquí afectan el producto maestro (inventario, facturación, tienda, marketplace).
            </div>
            <a v-if="form.id" :href="'/items#edit-' + form.id" style="color:#065f46;font-size:12px;font-weight:500;text-decoration:underline">
                Abrir ficha completa →
            </a>
        </div>

        <!-- Tabs adicionales para Restaurant -->
        <el-tabs v-model="activeTab" class="mt-3">
            <el-tab-pane label="General" name="general">
                <form autocomplete="off"
                    @submit.prevent="submit">
                    <div class="form-body">
                        <!-- ━━━━━━━━━━ SECCIÓN: INFORMACIÓN BÁSICA ━━━━━━━━━━ -->
                        <div class="mp-form-section">
                            <div class="mp-form-section__head">📦 Información básica</div>
                            <div class="mp-form-section__hint">Datos esenciales del producto: nombre, precios, stock e imagen.</div>
                        </div>
                        <div class="row">

                            <div v-if="fromRestaurant && !form.has_sets" class="col-md-3 center-el-checkbox">
                                <div :class="{'has-danger': errors.is_dish}"
                                    class="form-group">
                                    <el-checkbox v-model="form.is_dish" :disabled="form.has_supplies" @change="changeIsDish()">Producto con receta
                                        <el-tooltip class="item"
                                                    content="Este producto consume insumos del almacén al venderse."
                                                    effect="dark"
                                                    placement="top-start">
                                            <i class="fa fa-info-circle" style="margin-left: 5px;"></i>
                                        </el-tooltip></el-checkbox>
                                    <br>
                                    <small v-if="errors.is_dish"
                                        class="form-control-feedback"
                                        v-text="errors.is_dish[0]"></small>
                                </div>
                            </div>

                            <div v-show="show_has_igv" class="col-md-3 center-el-checkbox">
                                <div  :class="{'has-danger': errors.has_igv}"
                                    class="form-group">
                                    <el-checkbox v-model="form.has_igv">Incluye Igv</el-checkbox>
                                    <br>
                                    <small v-if="errors.has_igv"
                                        class="form-control-feedback"
                                        v-text="errors.has_igv[0]"></small>
                                </div>
                            </div>

                        </div>
                        <div class="row">

                            <!-- <div class="col-md-6">
                                <div class="form-group" :class="{'has-danger': errors.description}">
                                    <label class="control-label">Descripción <span class="text-danger">*</span></label>
                                    <el-input v-model="form.description" dusk="description"></el-input>
                                    <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
                                </div>
                            </div> -->
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
        <!--
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
                            </div> -->

                            <!-- <div class="col-md-9">
                            <div class="form-group" :class="{'has-danger': errors.name}">
                                <label class="control-label">Nombre  <span class="text-danger">*</span></label>
                                <el-input v-model="form.name" dusk="name"></el-input>
                                <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                            </div>
                        </div> -->
                            <!-- <div class="col-md-9">
                                <div :class="{'has-danger': errors.name}"
                                    class="form-group">
                                    <label class="control-label">Descripción</label>
                                    <el-input v-model="form.name"
                                        dusk="name"></el-input>
                                    <small v-if="errors.name"
                                        class="form-control-feedback"
                                        v-text="errors.name[0]"></small>
                                </div>
                            </div> -->

                            <!-- <div v-if="!fromRestaurant" class="col-md-3"> -->
                            <div class="col-md-3" v-if="!form.is_dish" v-show="show_unit_type">
                                <div :class="{'has-danger': errors.unit_type_id}"
                                    class="form-group">
                                    <label class="control-label">Unidad</label>
                                    <el-select v-model="form.unit_type_id"
                                            dusk="unit_type_id"
                                            :disabled="form.is_dish">
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
                <!--       
                            <div class="col-md-3">
                                <div :class="{'has-danger': errors.unit_type_id}" class="form-group">
                                    <label class="control-label">Unidad</label>

                                    <el-select v-model="form.unit_type_id" dusk="unit_type_id">
                                        <el-option label="Plato" value="ZZ"></el-option>
                                        <el-option label="Producto" value="NIU"></el-option>
                                    </el-select>

                                    <small v-if="errors.unit_type_id"
                                        class="form-control-feedback"
                                        v-text="errors.unit_type_id[0]">
                                    </small>
                                </div>
                            </div>
                            -->
        <!--
                            <div class="col-md-12">
                                <div :class="{'has-danger': errors.technical_specifications}"
                                    class="form-group">
                                    <label class="control-label">Especificaciones técnicas</label>
                                    <vue-ckeditor
                                        v-model="form.technical_specifications"
                                        :editors="editors"
                                        type="classic"></vue-ckeditor>
                                    <small v-if="errors.technical_specifications"
                                        class="form-control-feedback"
                                        v-text="errors.technical_specifications[0]"></small>
                                </div>
                            </div> -->

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
                                    <label class="control-label">Precio Unitario (Venta)
                                        <span class="text-danger">*</span></label>
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
                                    <label class="control-label">Tipo de afectación (Venta)</label>
                                    <el-select v-model="form.sale_affectation_igv_type_id"
                                            @change="changeAffectationIgvType">
                                        <el-option v-for="option in affectation_igv_types"
                                                :key="option.id"
                                                :label="option.description"
                                                :value="option.id"></el-option>
                                    </el-select>
                                    <small v-if="errors.sale_affectation_igv_type_id"
                                        class="form-control-feedback"
                                        v-text="errors.sale_affectation_igv_type_id[0]"></small>
                                </div>
                            </div>
                            <!-- <div v-show="form.unit_type_id !='ZZ'"
                                class="col-md-3 center-el-checkbox">
                                <div :class="{'has-danger': errors.calculate_quantity}"
                                    class="form-group">
                                    <el-checkbox v-model="form.calculate_quantity">Calcular cantidad por precio</el-checkbox>
                                    <br>
                                    <small v-if="errors.calculate_quantity"
                                        class="form-control-feedback"
                                        v-text="errors.calculate_quantity[0]"></small>
                                </div>
                            </div> -->

                            <div class="col-md-3">
                                <div :class="{'has-danger': errors.internal_id}"
                                    class="form-group">
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
                                </div>
                            </div>
                            <!-- <div class="col-md-3">
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
                            </div> -->
                            <div v-if="form.unit_type_id !== 'ZZ'" v-show="recordId==null"
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
                            <div v-if="form.unit_type_id !== 'ZZ'" class="col-md-3">
                                <div :class="{'has-danger': errors.stock_min}"
                                    class="form-group">
                                    <label class="control-label">Stock Mínimo</label>
                                    <el-input v-model="form.stock_min"></el-input>
                                    <small v-if="errors.stock_min"
                                        class="form-control-feedback"
                                        v-text="errors.stock_min[0]"></small>
                                </div>
                            </div>
                            <div v-show="form.unit_type_id !='ZZ'"
                                class="col-md-3">
                                <div :class="{'has-danger': errors.warehouse_id}"
                                    class="form-group">
                                    <label class="control-label">Almacen</label>
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

                            <!-- <div class="col-md-3 center-el-checkbox">
                                <div class="form-group">
                                    <el-checkbox v-model="has_percentage_perception"
                                                @change="changePercentagePerception">Incluye percepción
                                    </el-checkbox>
                                    <br>
                                </div>
                            </div> -->
                            <!-- <div v-show="has_percentage_perception"
                                class="col-md-3 center-el-checkbox">
                                <div class="form-group">
                                    <label class="control-label">Porcentaje de percepción</label>

                                    <el-input v-model="form.percentage_perception"></el-input>
                                </div>
                            </div> -->
                            <!-- <div class="col-md-3 center-el-checkbox">
                                <div class="form-group" >
                                    <el-checkbox v-model="have_account" @change="changeHaveAccount">¿Tiene cuenta contable?</el-checkbox><br>
                                </div>
                            </div>
                            <div class="col-md-3" v-show="have_account">
                                <div class="form-group" :class="{'has-danger': errors.account_id}">
                                    <label class="control-label">Cuenta contable</label>
                                    <el-select v-model="form.account_id" filterable>
                                        <el-option v-for="option in accounts" :key="option.id" :value="option.id" :label="`${option.number} - ${option.description}`"></el-option>
                                    </el-select>
                                    <small class="form-control-feedback" v-if="errors.account_id" v-text="errors.account_id[0]"></small>
                                </div>
                            </div> -->
                            <!-- <div v-show="form.unit_type_id !='ZZ'"
                                class="col-md-12">
                                <h5 class="separator-title ">
                                    Listado de precios
                                    <a class="control-label font-weight-bold text-info"
                                    href="#"
                                    @click="clickAddRow"> [ + Nuevo]</a>
                                </h5>
                            </div> -->
                            <!-- <div v-if="form.item_unit_types.length > 0"
                                v-show="form.unit_type_id !='ZZ'"
                                class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th class="text-center">Unidad</th>
                                            <th class="text-center">Descripción</th>
                                            <th class="text-center">Factor</th>
                                            <th class="text-center">{{ config.price1_label }}</th>
                                            <th class="text-center">{{ config.price2_label }}</th>
                                            <th class="text-center">{{ config.price3_label }}</th>
                                            <th class="text-center">P. Defecto</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(row, index) in form.item_unit_types">
                                            <template v-if="row.id">
                                                <td class="text-center">{{ row.unit_type_id }}</td>
                                                <td class="text-center">{{ row.description }}</td>
                                                <td class="text-center">{{ row.quantity_unit }}</td>
                                                <td class="text-center">{{ row.price1 }}</td>
                                                <td class="text-center">{{ row.price2 }}</td>
                                                <td class="text-center">{{ row.price3 }}</td>
                                                <td class="text-center">Precio {{ row.price_default }}</td>
                                                <td class="series-table-actions text-right">
                                                    <button class="btn waves-effect waves-light btn-xs btn-danger"
                                                            type="button"
                                                            @click.prevent="clickDelete(row.id)">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </template>
                                            <template v-else>
                                                <td>
                                                    <div class="form-group">
                                                        <el-select v-model="row.unit_type_id"
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
                                                        <el-input v-model="row.description"></el-input>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-group">
                                                        <el-input v-model="row.quantity_unit"></el-input>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-group">
                                                        <el-input v-model="row.price1"></el-input>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-group">
                                                        <el-input v-model="row.price2"></el-input>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-group">
                                                        <el-input v-model="row.price3"></el-input>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <el-radio-group v-model="row.price_default">
                                                            <el-radio :label="1"
                                                                    class="d-block">{{ config.price1_label }}
                                                            </el-radio>
                                                            <el-radio :label="2"
                                                                    class="d-block">{{ config.price2_label }}
                                                            </el-radio>
                                                            <el-radio :label="3"
                                                                    class="d-block">{{ config.price3_label }}
                                                            </el-radio>
                                                        </el-radio-group>
                                                    </div>
                                                </td>
                                                <td class="series-table-actions text-right">
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
                            </div> -->

                            <!-- <div v-if="attribute_types.length > 0"
                                class="col-md-12">
                                <h5 class="separator-title ">
                                    Atributos
                                    <el-tooltip class="item"
                                                content="Diferentes presentaciones para la venta del producto"
                                                effect="dark"
                                                placement="top">
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                    <a class="control-label font-weight-bold text-info"
                                    href="#"
                                    @click.prevent="clickAddAttribute">[+ Agregar]</a>
                                </h5>
                            </div> -->
                            <!-- <div v-if="form.attributes.length > 0"
                                class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Descripción</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(row, index) in form.attributes">
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
                                                <button class="btn btn-danger"
                                                        type="button"
                                                        @click.prevent="clickRemoveAttribute(index)">x
                                                </button>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div> -->


                            <!-- ━━━━━━━━━━ SECCIÓN: IMAGEN + CATEGORIZACIÓN ━━━━━━━━━━ -->
                            <div class="col-md-12">
                                <div class="mp-form-section mp-form-section--alt">
                                    <div class="mp-form-section__head">🏷️ Imagen y categorización</div>
                                    <div class="mp-form-section__hint">Imagen, categoría interna, marca, etiquetas y datos de compra.</div>
                                </div>
                            </div>
                            <div class="row col-md-12">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">Imágen <span class="text-danger"></span></label>
                                        <el-upload :action="`/${resource}/upload`"
                                                :data="{'type': 'items', 'skip_preview': 1}"
                                                :headers="headers"
                                                :on-success="onSuccess"
                                                :on-error="onUploadError"
                                                :before-upload="beforeUpload"
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
                                        <div class="sub-title text-danger"><small>Se recomienda resoluciones Full Hd
                                                                                1024x720</small></div>
                                        <el-button type="primary"
                                                @click="openImages">Agregar más fotos
                                        </el-button>

                                    </div>
                                </div>

                                <div class="col-md-9">
                                    <div class="row">
                                        <!-- ═════════ CATEGORÍA UNIFICADA (oficial del marketplace) ═════════ -->
                                        <!-- La categoría interna del tenant (form.category_id) se auto-asigna en
                                             ItemController::store a partir del leaf de la categoría oficial.
                                             Solo mostramos el cascader oficial — un único control para el seller. -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="control-label">
                                                    Categoría <span class="text-danger">*</span>
                                                    <small style="font-weight:normal;color:#6b7280;margin-left:4px">(taxonomía oficial de ebaemy)</small>
                                                </label>
                                                <div v-if="mp_category_suggestions.length && !form.marketplace_category_id" style="margin:0 0 8px;padding:8px 10px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px">
                                                    <div style="font-size:11px;color:#047857;margin-bottom:4px;font-weight:500">💡 Sugerencias basadas en el nombre del producto:</div>
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
                                                    placeholder="Selecciona una categoría…"
                                                    filterable
                                                    clearable
                                                    style="width:100%"
                                                    @change="onMpCategoryChange"
                                                    @click.native="loadMarketplaceCategoryTree"
                                                />
                                                <small style="font-size:11px;color:#9ca3af">Se usa para el filtro del marketplace y para tu catálogo interno automáticamente.</small>
                                            </div>
                                        </div>

                                        <div class="short-div col-md-12">
                                            <div :class="{'has-danger': errors.purchase_affectation_igv_type_id}"
                                                class="form-group">
                                                <label class="control-label">Tipo de afectación (Compra)</label>
                                                <el-select v-model="form.purchase_affectation_igv_type_id">
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

                                        <div class="short-div col-md-4">
                                            <div :class="{'has-danger': errors.purchase_unit_price}"
                                                class="form-group">
                                                <label class="control-label">Precio Unitario (Compra)</label>
                                                <el-input v-model="form.purchase_unit_price"
                                                        dusk="purchase_unit_price"
                                                        @input="calculatePercentageOfProfitByPurchase"></el-input>
                                                <small v-if="errors.purchase_unit_price"
                                                    class="form-control-feedback"
                                                    v-text="errors.purchase_unit_price[0]"></small>
                                            </div>
                                        </div>
                                        <!-- <div class="short-div col-md-4">
                                            <div :class="{'has-danger': errors.percentage_of_profit}"
                                                class="form-group">
                                                <label class="control-label">Porcentaje de ganancia (%)</label>
                                                <el-input v-model="form.percentage_of_profit"
                                                        @input="calculatePercentageOfProfitByPercentage"></el-input>
                                                <small v-if="errors.percentage_of_profit"
                                                    class="form-control-feedback"
                                                    v-text="errors.percentage_of_profit[0]"></small>
                                            </div>
                                        </div> -->
                                        <div class="col-md-4 center-el-checkbox">
                                            <div class="form-group">
                                                <div class="mp-form-subhead">🛒 Canales de venta</div>
                                                <el-checkbox v-model="form.apply_store">Aplica en Tienda</el-checkbox>
                                                <br>
                                                <el-checkbox v-model="form.marketplace_publishable" style="margin-top:4px">
                                                    🌐 Publicar en Marketplace ebaemy
                                                </el-checkbox>
                                                <el-tooltip content="Si se activa, el producto aparecerá en ebaemy.com/marketplace. Las solicitudes llegan como pedidos en el canal 'Marketplace ebaemy'." placement="top">
                                                    <i class="el-icon-info text-info" style="margin-left:4px;cursor:help"></i>
                                                </el-tooltip>
                                                <!-- Solo precio especial + link de "no encuentro categoría". El
                                                     cascader de categoría se gestiona arriba en el campo unificado.
                                                     Al activar marketplace solo pedimos lo extra: precio diferenciado. -->
                                                <div v-if="form.marketplace_publishable" style="margin-top:8px;padding:10px 12px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px">
                                                    <div v-if="!form.marketplace_category_id" style="font-size:12px;color:#dc2626;font-weight:600;margin-bottom:6px">
                                                        ⚠️ Selecciona una categoría arriba para poder publicar.
                                                    </div>
                                                    <a href="#" @click.prevent="openMpCategoryRequest" style="font-size:11px;color:#7c3aed;text-decoration:underline;display:inline-block;margin-bottom:6px">
                                                        ¿No encuentras una categoría adecuada?
                                                    </a>
                                                    <label style="display:block;font-size:12px;color:#6b21a8;margin:4px 0 4px;font-weight:500">Precio en marketplace (opcional)</label>
                                                    <el-input-number v-model="form.mp_price" :min="0" :precision="2" :step="1"
                                                        placeholder="Usar precio normal" controls-position="right" size="mini"
                                                        style="width:100%"></el-input-number>
                                                    <small style="color:#7c3aed;font-size:11px">Deja vacío para usar el precio de venta normal.</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div :class="{'has-danger': errors.warehouse_id}"
                                                class="form-group">
                                                <label class="control-label">Tags</label>
                                                <el-select v-model="form.tags_id"
                                                        filterable
                                                        multiple>
                                                    <el-option v-for="option in tags"
                                                            :key="option.id"
                                                            :label="option.name"
                                                            :value="option.id"></el-option>
                                                </el-select>
                                                <small v-if="errors.warehouse_id"
                                                    class="form-control-feedback"
                                                    v-text="errors.warehouse_id[0]"></small>
                                            </div>
                                        </div>

                                        <div class="short-div col-md-5">
                                            <div :class="{'has-danger': errors.preparation_area_id}"
                                                class="form-group">
                                                <label class="control-label">Área de Preparación</label>
                                                <el-select v-model="form.preparation_area_id" clearable placeholder="Seleccione">
                                                    <el-option v-for="area in preparation_areas"
                                                            :key="area.id"
                                                            :label="area.name"
                                                            :value="area.id"></el-option>
                                                </el-select>
                                                <small v-if="errors.preparation_area_id"
                                                    class="form-control-feedback"
                                                    v-text="errors.preparation_area_id[0]"></small>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>


                            <!--<div class="col-md-12" v-if="form.warehouses">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th>Ubicación</th>
                                        <th class="text-right">Stock</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="row in form.warehouses">
                                        <th>{{ row.warehouse_description }}</th>
                                        <th class="text-right">{{ row.stock }}</th>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>-->
                        </div>
                    </div>
                    <div class="form-actions text-end pt-2">
                        <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                        <el-button :loading="loading_submit || uploadingImage"
                                :disabled="uploadingImage"
                                native-type="submit"
                                type="primary">{{ uploadingImage ? 'Subiendo imagen…' : 'Guardar' }}
                        </el-button>
                    </div>
                </form>
            </el-tab-pane>
            <el-tab-pane :disabled="!fromRestaurant || (fromRestaurant && form.is_dish === false)" label="Insumos" name="supplies">
                <template #label>
                    <el-tooltip
                        v-if="!fromRestaurant || (fromRestaurant && form.is_dish == false)"
                        content="Solo se puede colocar insumos a los productos con receta"
                        placement="top"
                    >
                        <span>Insumos</span>
                    </el-tooltip>

                    <span v-else>Insumos</span>
                </template>
                <supplies-tab :itemId="recordId"></supplies-tab>
            </el-tab-pane>
            <el-tab-pane :disabled="!fromRestaurant" label="Modificadores" name="modifiers">
                <modifiers-tab :itemId="recordId"></modifiers-tab>
            </el-tab-pane>

            <!-- ════════════ VARIANTES (talla, color, etc.) ════════════ -->
            <el-tab-pane name="variants">
                <template #label>
                    <span>🎨 Variantes</span>
                </template>
                <div v-if="!form.id" class="text-center py-4" style="color:#6b7280">
                    <div style="font-size:36px;line-height:1;margin-bottom:8px">🎨</div>
                    <div style="font-weight:600;margin-bottom:4px">Variantes (talla, color, etc.)</div>
                    <div style="font-size:13px">Guarda el producto primero para configurar sus variantes.</div>
                    <div style="font-size:12px;color:#9ca3af;margin-top:6px">Usa esta sección si tu producto tiene combinaciones (ej. "Camiseta Roja Talla M").</div>
                </div>
                <variants-tab v-else :itemId="form.id"></variants-tab>
            </el-tab-pane>
        </el-tabs>
        <!-- <percentage-perception
                :showDialog.sync="showPercentagePerception"
                :percentage_perception="percentage_perception">
        </percentage-perception> -->

        <form-images ref="form_images"
                     :recordId="recordId"
                     :showDialog.sync="showDialogImages"
                     @saveImages="saveImages"></form-images>

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

<style>
.el-tabs__item.is-disabled {
    pointer-events: auto !important;   /* No clics ni hover */
    cursor: not-allowed !important;
    opacity: 0.6;                      /* Opcional */
}

/* Quitar efectos hover */
.el-tabs__item.is-disabled:hover {
    color:  #c0c4cc !important;
    background-color: transparent !important;
    cursor: not-allowed !important;
}

/* ───────── Separadores de sección (Información básica / Imagen / etc.) ───────── */
.mp-form-section {
    margin: 18px 0 12px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #ecfdf5, #f0fdfa);
    border-left: 4px solid #10b981;
    border-radius: 8px;
}
.mp-form-section--alt {
    background: linear-gradient(135deg, #eff6ff, #f0f9ff);
    border-left-color: #3b82f6;
}
.mp-form-section__head {
    font-size: 14px;
    font-weight: 700;
    color: #065f46;
    line-height: 1.3;
}
.mp-form-section--alt .mp-form-section__head { color: #1e40af; }
.mp-form-section__hint {
    font-size: 12px;
    color: #047857;
    margin-top: 2px;
}
.mp-form-section--alt .mp-form-section__hint { color: #1d4ed8; }
.mp-form-subhead {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 6px;
    padding-bottom: 4px;
    border-bottom: 1px dashed #e2e8f0;
}
</style>

<script>
// import PercentagePerception from './partials/percentage_perception.vue'
import FormImages from "./partials/form_images.vue";
import SuppliesTab from "../../../../../modules/Restaurant/Resources/assets/js/views/items/supplies-tab.vue";
import ModifiersTab from "../../../../../modules/Restaurant/Resources/assets/js/views/items/modifiers-tab.vue";
import VariantsTab from "../items/partials/variants-tab.vue";
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import VueCkeditor from 'vue-ckeditor5';
import { imageCompressor } from '../../../mixins/imageCompressor';

export default {
    mixins: [imageCompressor],
    props: ['showDialog', 'recordId', 'external', 'fromRestaurant'],
    components: {
        FormImages,
        SuppliesTab,
        ModifiersTab,
        VariantsTab,
        'vue-ckeditor': VueCkeditor.component
    },

    data() {
        return {
            activeTab: 'general',
            loading_search: false,
            tags: [],
            categories: [],
            preparation_areas: [],
            form_category: {add: false, name: null, id: null},
            warehouses: [],
            loading_submit: false,
            uploadingImage: false,
            categorySearchQuery: '',
            showPercentagePerception: false,
            filteredCategories: [],
            has_percentage_perception: false,
            percentage_perception: null,
            titleDialog: null,
            resource: 'items',
            errors: {},
            headers: headers_token,
            form: {},
            unit_types: [],
            currency_types: [],
            system_isc_types: [],
            affectation_igv_types: [],
            accounts: [],
            show_has_igv: true,
            show_unit_type: true,
            have_account: false,
            editors: {
                classic: ClassicEditor
            },
            item_unit_type: {
                id: null,
                unit_type_id: null,
                quantity_unit: 0,
                price1: 0,
                price2: 0,
                price3: 0,
                price_default: 2,

            },
            showDialogImages: false,
            attribute_types: [],
            mp_category_tree: [],
            mp_category_loading: false,
            mp_category_request_dialog: false,
            mp_category_request_form: {
                suggested_name: '',
                suggested_parent_id: null,
                description: '',
            },
            mp_category_request_sending: false,
            mp_category_suggestions: [],
        }
    },
    watch: {
        'form.marketplace_publishable'(val) {
            if (val) {
                this.loadMarketplaceCategoryTree()
                this.suggestMpCategoryFromTenant()
            } else {
                this.mp_category_suggestions = []
            }
        },
        'form.category_id'() {
            // Si el seller cambia su categoría interna mientras el toggle MP
            // está activo y aún no eligió una oficial, recalcular sugerencias
            if (this.form.marketplace_publishable && !this.form.marketplace_category_id) {
                this.suggestMpCategoryFromTenant()
            }
        },
    },
    created() {
        this.initForm()
        this.$http.get(`/${this.resource}/tables`)
            .then(response => {
                this.unit_types = response.data.unit_types
                this.accounts = response.data.accounts
                this.currency_types = response.data.currency_types
                this.system_isc_types = response.data.system_isc_types
                this.affectation_igv_types = response.data.affectation_igv_types
                this.warehouses = response.data.warehouses
                this.categories = response.data.categories
                this.tags = response.data.tags
                this.attribute_types = response.data.attribute_types

                this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
                this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
                this.filteredCategories = this.categories;
            })

        // Cargar áreas de preparación
        this.$http.get('/restaurant/preparation-areas')
            .then(response => {
                if (response.data.success) {
                    this.preparation_areas = response.data.data
                }
            })
            .catch(() => {
                this.preparation_areas = []
            })

        this.$eventHub.$on('submitPercentagePerception', (data) => {
            this.form.percentage_perception = data
            if (!this.form.percentage_perception) this.has_percentage_perception = false
        })

    },
    methods: {

        changeIsDish() {
            if (!this.form.is_dish) {
                this.form.unit_type_id = 'NIU';
                this.show_unit_type = true;
            } else {
                this.form.unit_type_id = 'ZZ';
                this.show_unit_type = !this.form.is_dish;
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
        changeHaveAccount() {
            if (!this.have_account) this.form.account_id = null
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
        changePercentagePerception() {
            // if(this.has_percentage_perception){
            //     // this.percentage_perception = (this.recordId) ? this.form.percentage_perception:null
            // } else{
            //     this.form.percentage_perception = null
            // }

        },
        clickAddRow() {
            this.form.item_unit_types.push({
                id: null,
                description: null,
                unit_type_id: 'NIU',
                quantity_unit: 0,
                price1: 0,
                price2: 0,
                price3: 0,
                price_default: 2
            })
        },
        clickCancel(index) {
            this.form.item_unit_types.splice(index, 1)
            // this.initDocumentTypes()
            // this.showAddButton = true
        },
        initForm() {
            this.loading_submit = false,
                this.errors = {}
            this.form = {
                id: null,
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
                category_id: null,
                preparation_area_id: null,
                sale_affectation_igv_type_id: null,
                purchase_affectation_igv_type_id: null,
                calculate_quantity: false,
                stock: 0,
                stock_min: 1,
                has_igv: true,
                has_supplies: false,
                has_sets: false,
                is_dish: false,
                item_unit_types: [],
                percentage_of_profit: 0,
                percentage_perception: 0,
                image: null,
                image_url: null,
                temp_path: null,
                account_id: null,
                apply_store: true,
                marketplace_publishable: false,
                mp_price: null,
                marketplace_category_id: null,
                marketplace_category_path: [],
                tags_id: [],
                multi_images: [],
                attributes: [],
                technical_specifications: ''
            }
            this.show_has_igv = true
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
                this.$message.error(response.message || 'Error al subir la imagen.')
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
        onUploadError(err, file) {
            this.uploadingImage = false
            console.error('[items_ecommerce] upload error:', err)
            this.$message.error('No se pudo subir "' + file.name + '". ' + (err.message || 'Intenta de nuevo.'))
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
        resetForm() {
            this.initForm()
            this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
            this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
        },
        create() {
            this.titleDialog = (this.recordId) ? 'Editar Producto' : 'Nuevo Producto'
            if (this.recordId) {
                this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                        // El cascader del marketplace requiere array; si el backend
                        // devuelve null/undefined el v-model rompe el control.
                        if (!Array.isArray(this.form.marketplace_category_path)) {
                            this.form.marketplace_category_path = []
                        }
                        this.has_percentage_perception = (this.form.percentage_perception) ? true : false
                        this.changeAffectationIgvType()
                        // Si el item ya está marcado para marketplace, precargamos el
                        // árbol oficial. Sin esta llamada explícita el watcher de
                        // marketplace_publishable no dispara (no hay "cambio" — el
                        // valor ya viene true del backend) y el cascader queda vacío.
                        if (this.form.marketplace_publishable) {
                            this.loadMarketplaceCategoryTree()
                        }
                    })
            }
        },
        loadRecord() {
            if (this.recordId) {
                this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                        if (!Array.isArray(this.form.marketplace_category_path)) {
                            this.form.marketplace_category_path = []
                        }
                        this.changeAffectationIgvType()
                        // Si el item ya viene con marketplace activado, precarga el árbol
                        // (el watcher no dispara porque el valor no "cambió" respecto al default).
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
            // Si el seller eligió manualmente, ocultar sugerencias
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
                this.$message.error('Ingresa el nombre sugerido de la categoría.')
                return
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
                this.form.percentage_of_profit = difference / parseFloat(this.form.purchase_unit_price) * 100;
            }
        },
        calculatePercentageOfProfitByPurchase() {
            if (this.form.percentage_of_profit === '') {
                this.form.percentage_of_profit = 0;
            }
            this.form.sale_unit_price = (this.form.purchase_unit_price * (100 + parseFloat(this.form.percentage_of_profit))) / 100
        },
        calculatePercentageOfProfitByPercentage() {
            if (this.form.percentage_of_profit === '') {
                this.form.percentage_of_profit = 0;
            }
            this.form.sale_unit_price = (this.form.purchase_unit_price * (100 + parseFloat(this.form.percentage_of_profit))) / 100
        },
        submit() {
            if (this.uploadingImage) {
                return this.$message.warning('Espera a que termine de subir la imagen.')
            }
            if (this.has_percentage_perception && !this.form.percentage_perception) return this.$message.error('Ingrese un porcentaje');
            if (!this.has_percentage_perception) this.form.percentage_perception = null

            if (this.form.marketplace_publishable && !this.form.marketplace_category_id) {
                return this.$message.error('Selecciona una categoría oficial del marketplace antes de publicar.')
            }

            this.$refs.form_images.clear()

            this.loading_submit = true
            this.$http.post(`/${this.resource}`, this.form)
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
                        this.$message.error(error.response.data.message)
                        console.log(error)
                    }
                })
                .then(() => {
                    this.loading_submit = false
                })
        },
        close() {
            this.$emit('update:showDialog', false)
            this.activeTab = 'general'
            this.resetForm()
            this.$refs.form_images.clear()
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
        openImages() {
            this.showDialogImages = true
        },
        saveImages(source) {

            this.form.multi_images = source
        },
        changeAttributeType(index) {
            let attribute_type_id = this.form.attributes[index].attribute_type_id
            let attribute_type = _.find(this.attribute_types, {id: attribute_type_id})
            this.form.attributes[index].description = attribute_type.description
        },
        clickRemoveAttribute(index) {
            this.form.attributes.splice(index, 1)
        },
    }
}
</script>
