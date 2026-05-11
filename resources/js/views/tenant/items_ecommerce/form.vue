<template>
    <el-dialog :close-on-click-modal="false"
               :title="titleDialog"
               :visible="showDialog"
               append-to-body
               :top="dialogTop"
               :width="dialogWidth"
               custom-class="ie-items-dialog"
               @close="close"
               @open="create">

        <!-- Banner: edición rápida sobre el catálogo maestro + estado de canales -->
        <div style="margin:-8px 0 12px;padding:10px 14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:13px;flex-wrap:wrap">
            <span style="font-size:18px;line-height:1">📚</span>
            <div style="flex:1;min-width:240px;color:#065f46">
                <strong>Edición rápida del catálogo.</strong> Cambios aquí afectan el producto maestro (inventario, facturación, tienda, marketplace).
            </div>
            <!-- Badges de estado: muestran de un vistazo dónde se está vendiendo
                 el producto. Se sincronizan automáticamente con los checkboxes
                 de "Canales de venta" del form. -->
            <div v-if="form.id" class="ie-status-badges">
                <span class="ie-status-badge"
                      :class="form.apply_store ? 'ie-status-badge--on' : 'ie-status-badge--off'"
                      :title="form.apply_store ? 'Visible en tu tienda virtual' : 'No publicado en tu tienda'">
                    🏪 Tienda
                </span>
                <span class="ie-status-badge"
                      :class="form.marketplace_publishable ? 'ie-status-badge--mp' : 'ie-status-badge--off'"
                      :title="form.marketplace_publishable ? 'Publicado en ebaemy.com/marketplace' : 'No publicado en marketplace'">
                    🌐 Marketplace
                </span>
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

                    <!-- ━━━━━━━━━━ BANNER DE PROGRESO ━━━━━━━━━━
                         Guía visual para que el seller sepa qué falta. Sticky top
                         dentro del tab para que siempre quede a la vista mientras
                         scrollea el form. Click en cualquier ítem faltante hace
                         scroll al campo correspondiente. -->
                    <div class="ie-progress-banner"
                         :class="{ 'is-complete': completionPercent === 100,
                                   'is-ready-mp': completionPercent >= 60 && completionPercent < 100 }">
                        <div class="ie-progress-banner__head">
                            <div class="ie-progress-banner__title">
                                <span v-if="completionPercent === 100">
                                    🎉 Producto listo para publicar
                                </span>
                                <span v-else>
                                    ✨ Tu producto: <strong>{{ completionPercent }}%</strong> listo
                                </span>
                            </div>
                            <div class="ie-progress-banner__bar">
                                <div class="ie-progress-banner__bar-fill"
                                     :style="{ width: completionPercent + '%' }"></div>
                            </div>
                        </div>
                        <div class="ie-progress-banner__items">
                            <button v-for="item in completionItems" :key="item.key"
                                    type="button"
                                    class="ie-progress-banner__chip"
                                    :class="['is-' + item.status, item.required ? 'is-required' : 'is-optional']"
                                    @click="scrollToField(item.target)"
                                    :title="item.hint">
                                <span class="ie-progress-banner__chip-icon">
                                    <span v-if="item.status === 'done'">✓</span>
                                    <span v-else-if="item.required">!</span>
                                    <span v-else>○</span>
                                </span>
                                {{ item.label }}
                            </button>
                        </div>
                    </div>

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
                                            type="number" min="0" step="0.0001"
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
                                    <el-input v-model="form.stock" type="number" min="0" step="1"></el-input>
                                    <small v-if="errors.stock"
                                        class="form-control-feedback"
                                        v-text="errors.stock[0]"></small>
                                </div>
                            </div>
                            <div v-if="form.unit_type_id !== 'ZZ'" class="col-md-3">
                                <div :class="{'has-danger': errors.stock_min}"
                                    class="form-group">
                                    <label class="control-label">Stock Mínimo</label>
                                    <el-input v-model="form.stock_min" type="number" min="0" step="1"></el-input>
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

                            <!-- Descripción del producto en el marketplace.
                                 IMPORTANTE: usa form.mp_notes (no form.description),
                                 porque en este proyecto items.description guarda el
                                 NOMBRE del producto (convención legacy SUNAT). El
                                 sync mapea items.mp_notes → marketplace_listings.description
                                 que es lo que renderiza show.blade.php.

                                 Desplegable: el CKEditor ocupa mucho espacio vertical y
                                 muchas veces el seller ya completó la descripción. Se
                                 abre solo si está vacía (sin mp_notes) o si el seller
                                 lo expande manualmente. -->
                            <div class="col-md-12">
                                <details class="ie-collapse" :open="mpDescOpen" @toggle="mpDescOpen = $event.target.open">
                                    <summary class="ie-collapse__summary">
                                        <span class="ie-collapse__chev">▼</span>
                                        <strong>Descripción</strong>
                                        <span class="ie-collapse__hint">
                                            (visible en la página del producto en el marketplace)
                                        </span>
                                        <span v-if="form.mp_notes" class="ie-collapse__badge">✓ con texto</span>
                                    </summary>
                                    <div :class="{'has-danger': errors.mp_notes}" class="form-group mt-2">
                                        <vue-ckeditor
                                            v-model="form.mp_notes"
                                            :editors="editors"
                                            type="classic"></vue-ckeditor>
                                        <small v-if="errors.mp_notes"
                                               class="form-control-feedback"
                                               v-text="errors.mp_notes[0]"></small>
                                    </div>
                                </details>
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


                            <!-- ━━━━━━━━━━ SECCIÓN: IMAGEN + CATEGORIZACIÓN (colapsable) ━━━━━━━━━━ -->
                            <div class="col-md-12">
                                <div class="mp-form-section mp-form-section--alt mp-form-section--collapsible"
                                     :class="{ 'is-collapsed': !imageSectionOpen }"
                                     @click="imageSectionOpen = !imageSectionOpen"
                                     role="button">
                                    <div class="mp-form-section__head">
                                        <span class="mp-collapse-chev" :class="{ 'is-open': imageSectionOpen }">▸</span>
                                        🏷️ Imagen y categorización
                                        <span v-if="!imageSectionOpen && form.image_url && form.marketplace_category_id"
                                              class="ie-collapse__badge"
                                              style="margin-left:auto">✓ completo</span>
                                    </div>
                                    <div v-show="imageSectionOpen" class="mp-form-section__hint">
                                        Imagen, categoría interna, marca, etiquetas y datos de compra.
                                    </div>
                                </div>
                            </div>
                            <div v-show="imageSectionOpen" class="row col-md-12">
                                <!-- Strip horizontal full-width: imagen principal a la izquierda,
                                     galería desplegándose en fila hacia la derecha. Antes estaba
                                     todo apilado en col-md-3 (vertical), comía mucho espacio. -->
                                <div class="col-md-12">
                                    <div class="ie-image-card ie-image-card--row">

                                        <!-- ═══════ Imagen principal (izquierda) ═══════ -->
                                        <div class="ie-image-card__primary-wrap">
                                            <div class="ie-image-card__label">
                                                <span>📷 Principal</span>
                                                <span v-if="form.image_url" class="ie-image-card__ok-pill">✓</span>
                                            </div>
                                            <el-upload :action="`/${resource}/upload`"
                                                    :data="{'type': 'items', 'skip_preview': 1}"
                                                    :headers="headers"
                                                    :on-success="onSuccess"
                                                    :on-error="onUploadError"
                                                    :before-upload="beforeUpload"
                                                    :on-change="onFileChange"
                                                    :show-file-list="false"
                                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp"
                                                    class="ie-primary-upload">
                                                <img v-if="form.image_url"
                                                    :src="form.image_url"
                                                    class="ie-primary-upload__img">
                                                <div v-else class="ie-primary-upload__empty">
                                                    <i class="el-icon-plus"></i>
                                                    <span>Subir</span>
                                                </div>
                                            </el-upload>
                                            <div class="ie-image-card__hint">
                                                <strong>1024×720</strong> Full HD
                                            </div>
                                        </div>

                                        <!-- ═══════ Galería adicional (derecha) ═══════ -->
                                        <div class="ie-image-card__gallery-wrap">
                                            <div class="ie-image-card__label">
                                                <span>🖼️ Galería adicional</span>
                                                <span v-if="allGalleryImages.length"
                                                      class="ie-image-card__count-pill">
                                                    {{ allGalleryImages.length }} foto<span v-if="allGalleryImages.length > 1">s</span>
                                                </span>
                                                <span v-else
                                                      class="ie-image-card__count-pill ie-image-card__count-pill--empty">
                                                    sin fotos
                                                </span>
                                                <small v-if="hasPendingImages"
                                                       class="ie-image-card__hint-pending"
                                                       style="margin-left:8px">
                                                    ⏳ {{ pendingImagesCount }} sin guardar
                                                </small>
                                            </div>

                                            <!-- Strip horizontal de thumbnails. Combina galleryImages (DB)
                                                 + form.multi_images (pendientes). Pendientes con borde ámbar
                                                 + ⏳. Click en cualquier thumb o en "+" abre el dialog. -->
                                            <div class="ie-image-card__strip">
                                                <div v-for="(img, i) in allGalleryImages" :key="img._key || i"
                                                     class="ie-image-card__thumb"
                                                     :class="{ 'is-pending': img._pending }"
                                                     @click="openImages"
                                                     :title="(img._pending ? 'Pendiente de guardar · ' : '') + 'Click para gestionar'">
                                                    <img :src="img.url" :alt="'Foto ' + (i + 1)">
                                                </div>
                                                <div class="ie-image-card__add" @click="openImages"
                                                     :title="allGalleryImages.length ? 'Agregar más fotos' : 'Agregar fotos'">
                                                    <i class="el-icon-plus"></i>
                                                    <span>{{ allGalleryImages.length ? 'Más' : 'Agregar' }}</span>
                                                </div>
                                            </div>
                                            <small v-if="!allGalleryImages.length" class="ie-image-card__hint">
                                                Click "+ Agregar" para subir fotos adicionales.
                                            </small>
                                        </div>

                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="row">
                                        <!-- ═════════ 1) CATEGORÍA OFICIAL ═════════
                                             Lo primero que el seller debería ver. Si no la
                                             elige, no puede publicar en marketplace. -->
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
                                                    :key="'cas-' + cascaderKey"
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

                                        <!-- ═════════ 2) CANALES DE VENTA (destacado) ═════════
                                             Subido al tope para que sea evidente dónde se vende
                                             el producto. Antes estaba escondido entre datos de
                                             compra y tags. -->
                                        <div class="col-md-12">
                                            <div class="ie-channels-card">
                                                <div class="ie-channels-card__head">🛒 Canales de venta</div>
                                                <div class="ie-channels-card__body">
                                                    <label class="ie-channel">
                                                        <el-checkbox v-model="form.apply_store">
                                                            <span class="ie-channel__title">🏪 Tu tienda virtual</span>
                                                        </el-checkbox>
                                                        <div class="ie-channel__hint">
                                                            Visible en <strong>{{ tenant_subdomain || 'tu tienda' }}.ebaemy.com</strong>
                                                        </div>
                                                    </label>
                                                    <label class="ie-channel">
                                                        <el-checkbox v-model="form.marketplace_publishable">
                                                            <span class="ie-channel__title">🌐 Marketplace ebaemy</span>
                                                        </el-checkbox>
                                                        <div class="ie-channel__hint">
                                                            Aparece en <strong>ebaemy.com/marketplace</strong> para todos
                                                            <el-tooltip content="Las solicitudes llegan como pedidos en el canal 'Marketplace ebaemy'." placement="top">
                                                                <i class="el-icon-info text-info" style="margin-left:4px;cursor:help"></i>
                                                            </el-tooltip>
                                                        </div>
                                                    </label>
                                                </div>

                                                <!-- Panel extra solo si publica en marketplace -->
                                                <div v-if="form.marketplace_publishable" class="ie-channels-card__extra">
                                                    <div v-if="!form.marketplace_category_id" style="font-size:12px;color:#dc2626;font-weight:600;margin-bottom:6px">
                                                        ⚠️ Selecciona una categoría arriba para poder publicar.
                                                    </div>
                                                    <div class="ie-mp-extra-row">
                                                        <div>
                                                            <label class="ie-mp-extra-label">Precio en marketplace</label>
                                                            <el-input-number v-model="form.mp_price" :min="0" :precision="2" :step="1"
                                                                placeholder="Igual al precio normal" controls-position="right" size="small"
                                                                style="width:100%"></el-input-number>
                                                            <small style="color:#7c3aed;font-size:11px">Vacío = precio de venta normal.</small>
                                                        </div>
                                                        <a href="#" @click.prevent="openMpCategoryRequest" class="ie-mp-extra-link">
                                                            ¿No encuentras una categoría adecuada?
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ═════════ 3) DATOS DE COMPRA Y ETIQUETADO (colapsable) ═════════
                                             Datos secundarios: el seller común no los toca al editar
                                             un producto. Por default cerrados; chevron + abrir manual. -->
                                        <div class="col-md-12">
                                            <details class="ie-collapse mt-2" :open="contableOpen" @toggle="contableOpen = $event.target.open">
                                                <summary class="ie-collapse__summary">
                                                    <span class="ie-collapse__chev">▼</span>
                                                    <strong>Datos contables y etiquetas</strong>
                                                    <span class="ie-collapse__hint">(compra, tags, área de preparación)</span>
                                                </summary>
                                                <div class="row mt-2">
                                                    <div class="col-md-6">
                                                        <div :class="{'has-danger': errors.purchase_affectation_igv_type_id}" class="form-group">
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
                                                    <div class="col-md-6">
                                                        <div :class="{'has-danger': errors.purchase_unit_price}" class="form-group">
                                                            <label class="control-label">Precio Unitario (Compra)</label>
                                                            <el-input v-model="form.purchase_unit_price"
                                                                    type="number" min="0" step="0.0001"
                                                                    dusk="purchase_unit_price"
                                                                    @input="calculatePercentageOfProfitByPurchase"></el-input>
                                                            <small v-if="errors.purchase_unit_price"
                                                                class="form-control-feedback"
                                                                v-text="errors.purchase_unit_price[0]"></small>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="control-label">Tags</label>
                                                            <el-select v-model="form.tags_id" filterable multiple
                                                                       placeholder="Selecciona o crea etiquetas">
                                                                <el-option v-for="option in tags"
                                                                        :key="option.id"
                                                                        :label="option.name"
                                                                        :value="option.id"></el-option>
                                                            </el-select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div :class="{'has-danger': errors.preparation_area_id}" class="form-group">
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
                                            </details>
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

                    <!-- ════════════ VARIANTES (talla, color, etc.) — Colapsable ════════════
                         El componente VariantsTab puede ser grande (tablas con
                         varias filas + matriz). Lo envolvemos en <details> con
                         el mismo patrón visual que descripción y datos contables.
                         Abierto por default si el producto ya tiene variantes. -->
                    <div class="col-md-12 mt-3">
                        <details class="ie-collapse ie-collapse--variants" :open="hasVariantsOpen">
                            <summary class="ie-collapse__summary">
                                <span class="ie-collapse__chev">▼</span>
                                <strong>🎨 Variantes del producto</strong>
                                <span class="ie-collapse__hint">
                                    (talla, color, etc. — opcional)
                                </span>
                                <span v-if="form.has_variants" class="ie-collapse__badge" style="background:#fae8ff;color:#86198f;border:1px solid #f5d0fe">
                                    ✓ activas
                                </span>
                            </summary>
                            <div class="ie-collapse__content">
                                <div v-if="!form.id" style="color:#6b7280;text-align:center;padding:20px 12px;background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px">
                                    <div style="font-size:32px;line-height:1;margin-bottom:8px">🎨</div>
                                    <div style="font-weight:600;margin-bottom:4px;color:#374151">Las variantes necesitan el producto creado</div>
                                    <div style="font-size:12.5px;margin-bottom:12px">Guardamos el producto ahora y desbloqueamos las variantes en este mismo formulario.</div>
                                    <el-button type="primary" size="small" :loading="loading_submit"
                                               @click="submit({ keepOpen: true })"
                                               icon="el-icon-check">
                                        Guardar y continuar con variantes
                                    </el-button>
                                </div>
                                <variants-tab v-else
                                              :item-id="form.id"
                                              :parent-price="parseFloat(form.sale_unit_price) || 0"
                                              :item-code="form.internal_id || form.item_code || ''"
                                              :is-marketplace-publishable="!!form.marketplace_publishable"
                                              :use-parent-image-initial="!!form.use_parent_image_for_variants"
                                              @use-parent-image-changed="form.use_parent_image_for_variants = $event"
                                              ></variants-tab>
                            </div>
                        </details>
                    </div>

                    <!-- Sticky bottom bar: el botón Guardar/Cancelar sigue al scroll
                         para que el seller no tenga que bajar hasta el final del form.
                         Position sticky + backdrop blur sobre el contenido detrás. -->
                    <div class="form-actions ie-sticky-actions text-end">
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

            <!-- Tab "Variantes" eliminado: ahora vive inline al final del
                 tab General para que toda la edición rápida quede en una
                 sola vista. -->
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
.mp-form-section--variants {
    background: linear-gradient(135deg, #fdf4ff, #faf5ff);
    border-left-color: #a855f7;
}
.mp-form-section--variants .mp-form-section__head { color: #6b21a8; }
.mp-form-section--variants .mp-form-section__hint { color: #7e22ce; }

/* ─────── Desplegable con <details> (descripción rica) ─────── */
.ie-collapse {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
    margin-bottom: 12px;
}
.ie-collapse__summary {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    cursor: pointer;
    user-select: none;
    background: #f9fafb;
    list-style: none;          /* quita el triángulo nativo del browser */
    transition: background .12s;
}
.ie-collapse__summary::-webkit-details-marker { display: none; }
.ie-collapse__summary:hover { background: #f3f4f6; }
.ie-collapse__chev {
    color: #6b7280;
    transition: transform .15s;
    font-size: 12px;
}
.ie-collapse[open] .ie-collapse__chev { transform: rotate(180deg); }
.ie-collapse__hint {
    font-size: 11.5px;
    color: #9ca3af;
    font-weight: 400;
    margin-left: 4px;
}
.ie-collapse__badge {
    margin-left: auto;
    font-size: 11px;
    font-weight: 600;
    color: #047857;
    background: #d1fae5;
    padding: 2px 8px;
    border-radius: 999px;
}
.ie-collapse > *:not(summary) {
    padding: 12px 14px;
}

/* ─────── Badges de estado (header banner) ─────── */
.ie-status-badges {
    display: inline-flex;
    gap: 6px;
    flex-wrap: wrap;
}
.ie-status-badge {
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 999px;
    border: 1px solid;
    line-height: 1.4;
    white-space: nowrap;
}
.ie-status-badge--on  { background:#dcfce7; color:#15803d; border-color:#86efac; }
.ie-status-badge--mp  { background:#dbeafe; color:#1d4ed8; border-color:#93c5fd; }
.ie-status-badge--off { background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb; }

/* ─────── Banner de progreso (sticky top) ─────── */
.ie-progress-banner {
    position: sticky;
    top: 0;
    z-index: 20;
    background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%);
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(59, 130, 246, .08);
}
.ie-progress-banner.is-ready-mp {
    background: linear-gradient(135deg, #fefce8 0%, #fff7ed 100%);
    border-color: #fcd34d;
}
.ie-progress-banner.is-complete {
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
    border-color: #86efac;
}
.ie-progress-banner__head {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 10px;
}
.ie-progress-banner__title {
    font-size: 13.5px;
    font-weight: 600;
    color: #1e3a8a;
    white-space: nowrap;
}
.is-ready-mp .ie-progress-banner__title { color: #92400e; }
.is-complete .ie-progress-banner__title { color: #166534; }
.ie-progress-banner__title strong { font-weight: 800; }
.ie-progress-banner__bar {
    flex: 1;
    height: 8px;
    background: rgba(255, 255, 255, .6);
    border-radius: 999px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, .05);
}
.ie-progress-banner__bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
    border-radius: 999px;
    transition: width .35s ease;
}
.is-ready-mp .ie-progress-banner__bar-fill {
    background: linear-gradient(90deg, #f59e0b 0%, #f97316 100%);
}
.is-complete .ie-progress-banner__bar-fill {
    background: linear-gradient(90deg, #10b981 0%, #22c55e 100%);
}
.ie-progress-banner__items {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.ie-progress-banner__chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    border: 1px solid;
    background: #fff;
    transition: transform .08s, box-shadow .12s;
}
.ie-progress-banner__chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
}
.ie-progress-banner__chip.is-done {
    color: #166534;
    border-color: #86efac;
    background: #dcfce7;
}
.ie-progress-banner__chip.is-pending.is-required {
    color: #b91c1c;
    border-color: #fca5a5;
    background: #fee2e2;
}
.ie-progress-banner__chip.is-pending.is-optional {
    color: #6b7280;
    border-color: #d1d5db;
    background: #f9fafb;
    border-style: dashed;
}
.ie-progress-banner__chip-icon {
    width: 14px;
    height: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
}
.ie-progress-banner__chip.is-done .ie-progress-banner__chip-icon {
    background: #16a34a;
    color: #fff;
}
.ie-progress-banner__chip.is-pending.is-required .ie-progress-banner__chip-icon {
    background: #dc2626;
    color: #fff;
}
.ie-progress-banner__chip.is-pending.is-optional .ie-progress-banner__chip-icon {
    background: #fff;
    color: #9ca3af;
    border: 1px solid #d1d5db;
}
/* Flash visual cuando se hace scroll a un campo */
@keyframes ie-flash-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    50%      { box-shadow: 0 0 0 6px rgba(59, 130, 246, .25); }
}
.ie-flash {
    animation: ie-flash-pulse 1.4s ease;
    border-radius: 8px;
}
@media (max-width: 767px) {
    .ie-progress-banner { padding: 10px 12px; margin-bottom: 12px; border-radius: 8px; }
    .ie-progress-banner__head { flex-direction: column; align-items: stretch; gap: 8px; margin-bottom: 8px; }
    .ie-progress-banner__title { white-space: normal; font-size: 12.5px; }
    .ie-progress-banner__items { gap: 4px; }
    .ie-progress-banner__chip { font-size: 11px; padding: 3px 8px; }
    .ie-progress-banner__chip-icon { width: 12px; height: 12px; font-size: 9px; }
}

/* ─────── Responsive global del form ─────── */
.ie-items-dialog .el-dialog__body { padding: 14px 18px; }
@media (max-width: 767px) {
    /* En mobile: dialog full-screen sin radios ni shadows raros */
    .ie-items-dialog {
        margin: 0 !important;
        max-width: 100% !important;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0 !important;
        display: flex !important;
        flex-direction: column;
    }
    .ie-items-dialog .el-dialog__header { padding: 12px 14px; flex-shrink: 0; }
    .ie-items-dialog .el-dialog__body {
        padding: 10px 14px 14px;
        overflow-y: auto;
        flex: 1;
    }
    /* Header sections con menos padding */
    .mp-form-section { padding: 10px 12px !important; margin-bottom: 10px !important; }
    .mp-form-section__head h5 { font-size: 14px !important; }
    .mp-form-section__hint { font-size: 11.5px !important; }
    /* Element UI inputs/selects más compactos */
    .el-input__inner, .el-select .el-input__inner, .el-cascader .el-input__inner {
        height: 36px !important;
        line-height: 36px !important;
        font-size: 14px;
    }
    .control-label { font-size: 12.5px; margin-bottom: 4px; }
    /* CKEditor no se desborda */
    .ck-editor__main { max-width: 100%; overflow-x: auto; }
    /* Botones sticky del fondo */
    .ie-sticky-actions { padding: 8px 10px !important; }
    .ie-sticky-actions .el-button { padding: 8px 12px; font-size: 13px; }
}
@media (max-width: 480px) {
    /* Smartphone pequeño: tipografía más chica + secciones más estrechas */
    .ie-items-dialog .el-dialog__body { padding: 8px 10px 12px; }
    .mp-form-section { padding: 8px 10px !important; }
    .form-group { margin-bottom: 10px; }
}

/* ─────── Card "Imagen principal + Galería adicional" ─────── */
.ie-image-card {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 14px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,.03);
}
.ie-image-card__section { display: flex; flex-direction: column; gap: 8px; }

/* Modo horizontal: imagen principal a la izquierda (fija) + galería en fila a la derecha */
.ie-image-card--row {
    flex-direction: row;
    align-items: stretch;
    gap: 18px;
}
.ie-image-card__primary-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 140px;
    flex-shrink: 0;
}
.ie-image-card__gallery-wrap {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
    min-width: 0;
    border-left: 1px solid #e5e7eb;
    padding-left: 18px;
}
/* Strip horizontal de thumbs — scroll si hay muchos */
.ie-image-card__strip {
    display: flex;
    flex-direction: row;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: thin;
}
.ie-image-card__strip::-webkit-scrollbar { height: 6px; }
.ie-image-card__strip::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.ie-image-card__strip .ie-image-card__thumb,
.ie-image-card__strip .ie-image-card__add {
    width: 90px;
    height: 90px;
    aspect-ratio: unset;
    flex-shrink: 0;
}
@media (max-width: 767px) {
    .ie-image-card--row { flex-direction: column; }
    .ie-image-card__primary-wrap { width: 100%; }
    .ie-image-card__gallery-wrap {
        border-left: none;
        border-top: 1px solid #e5e7eb;
        padding-left: 0;
        padding-top: 14px;
    }
}
.ie-image-card__label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #1f2937;
}
.ie-image-card__hint {
    font-size: 11.5px;
    color: #6b7280;
    line-height: 1.4;
}
.ie-image-card__hint strong { color: #374151; }
.ie-image-card__ok-pill {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.ie-image-card__count-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    border: 1px solid #93c5fd;
}
.ie-image-card__count-pill--empty {
    background: #f3f4f6;
    color: #9ca3af;
    border-color: #e5e7eb;
}

/* Imagen principal: avatar uploader grande, cuadrado, prominente */
.ie-primary-upload .el-upload {
    width: 100%;
    aspect-ratio: 1 / 1;
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color .12s, background .12s;
    overflow: hidden;
}
.ie-primary-upload .el-upload:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
}
.ie-primary-upload__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.ie-primary-upload__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    color: #94a3b8;
    font-size: 12.5px;
    font-weight: 500;
}
.ie-primary-upload__empty i {
    font-size: 28px;
    color: #3b82f6;
}

/* Galería: grid 3-cols */
.ie-image-card__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.ie-image-card__thumb,
.ie-image-card__add {
    aspect-ratio: 1 / 1;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color .12s, transform .08s;
}
.ie-image-card__thumb:hover,
.ie-image-card__add:hover {
    border-color: #3b82f6;
    transform: translateY(-1px);
}
.ie-image-card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.ie-image-card__add {
    flex-direction: column;
    gap: 2px;
    color: #6b7280;
    background: #fff;
    border: 1px dashed #cbd5e1;
    font-size: 10.5px;
    font-weight: 600;
}
.ie-image-card__add i { font-size: 16px; color: #3b82f6; }

/* Thumb pendiente de guardar (subido via upload-async pero el item aún no se guardó) */
.ie-image-card__thumb.is-pending {
    border: 2px dashed #f59e0b;
    background: #fffbeb;
    position: relative;
}
.ie-image-card__thumb.is-pending::after {
    content: '⏳';
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 11px;
    background: rgba(255,255,255,.9);
    border-radius: 999px;
    padding: 1px 4px;
    line-height: 1;
}
.ie-image-card__hint-pending {
    font-size: 11px;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 4px 8px;
    display: inline-block;
}
.ie-image-card__hint-pending strong { color: #78350f; }

/* ─────── Sección colapsable (Imagen y categorización) ─────── */
.mp-form-section--collapsible {
    cursor: pointer;
    user-select: none;
    transition: background .12s;
}
.mp-form-section--collapsible:hover { filter: brightness(0.97); }
.mp-form-section--collapsible .mp-form-section__head {
    display: flex;
    align-items: center;
    gap: 8px;
}
.mp-form-section--collapsible.is-collapsed { margin-bottom: 6px; }
.mp-collapse-chev {
    color: #6b7280;
    font-size: 11px;
    transition: transform .15s;
    display: inline-block;
}
.mp-collapse-chev.is-open { transform: rotate(90deg); }

/* ─────── Card destacado de canales de venta ─────── */
.ie-channels-card {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
    padding: 12px 14px;
    margin-bottom: 12px;
}
.ie-channels-card__head {
    font-size: 12.5px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 8px;
    letter-spacing: .2px;
}
.ie-channels-card__body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
@media (max-width: 720px) {
    .ie-channels-card__body { grid-template-columns: 1fr; }
}
.ie-channel {
    display: block;
    padding: 10px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: border-color .12s, box-shadow .12s;
    margin: 0;
}
.ie-channel:hover { border-color: #10b981; box-shadow: 0 1px 6px -2px rgba(16,185,129,.18); }
.ie-channel__title { font-weight: 600; color: #111827; font-size: 13px; }
.ie-channel__hint  { font-size: 11.5px; color: #6b7280; margin-top: 2px; }
.ie-channels-card__extra {
    margin-top: 10px;
    padding: 10px 12px;
    background: #faf5ff;
    border: 1px solid #e9d5ff;
    border-radius: 8px;
}
.ie-mp-extra-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
    justify-content: space-between;
}
.ie-mp-extra-row > div { flex: 1; min-width: 180px; }
.ie-mp-extra-label {
    display: block;
    font-size: 12px;
    color: #6b21a8;
    font-weight: 500;
    margin-bottom: 2px;
}
.ie-mp-extra-link {
    font-size: 11px;
    color: #7c3aed;
    text-decoration: underline;
    white-space: nowrap;
}

/* ─────── Colapsable variantes — más espacioso ─────── */
.ie-collapse--variants .ie-collapse__content { padding: 14px; }

/* ─────── Sticky action bar al fondo del dialog ─────── */
.ie-sticky-actions {
    position: sticky;
    bottom: 0;
    margin: 18px -20px -20px;        /* compensa el padding del el-dialog__body */
    padding: 12px 20px;
    background: rgba(255,255,255,.96);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-top: 1px solid #e5e7eb;
    z-index: 5;
    box-shadow: 0 -4px 12px -8px rgba(0,0,0,.08);
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
            // Toggle del bloque "Imagen y categorización" — abierto por
            // default; se cierra automáticamente al editar un producto que
            // ya tiene imagen + categoría (loadRecord ajusta este flag).
            imageSectionOpen: true,
            // Toggle manual del bloque Descripción (CKEditor). Antes estaba
            // bindeado a `!form.mp_notes` que reaccionaba con cada keystroke
            // cerrando el editor al primer carácter. Ahora el usuario lo
            // controla y loadRecord lo ajusta una sola vez al inicio.
            mpDescOpen: true,
            // Toggle del bloque "Datos contables y etiquetas".
            contableOpen: false,
            // Galería de fotos adicionales (item_images). Se carga en
            // loadRecord vía GET /items/images/{id} y se refresca cuando
            // el dialog cierra (saveImages).
            galleryImages: [],
            // Ancho del viewport — alimenta los computed dialogWidth/dialogTop
            // para que el modal se ajuste a celular/tablet/desktop. Se actualiza
            // en el resize handler (mounted/beforeDestroy).
            viewportWidth: (typeof window !== 'undefined' ? window.innerWidth : 1200),
            // Subdomain del tenant para el texto "tu_subdomain.ebaemy.com"
            // en el card de canales. Se lee de window si está disponible.
            tenant_subdomain: (typeof window !== 'undefined' && window.location)
                ? (window.location.hostname.split('.')[0] || '')
                : '',
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
            // Bump para forzar re-mount del el-cascader cuando llegan tree+
            // record en momentos distintos. Element UI cascader 2.x tiene
            // un bug donde no re-renderiza si v-model y :options cambian
            // muy cercanos en el tiempo. Re-mount fuerza la hidratación.
            cascaderKey: 0,
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
    computed: {
        // Si el producto NO tiene variantes aún, dejamos cerrado por default
        // (probablemente no las necesita). Si ya tiene, abrimos para que el
        // seller vea el listado al editar.
        hasVariantsOpen() {
            return !!(this.form && this.form.has_variants)
        },
        // El el-dialog no se adapta solo: en mobile (<768px) el 65% deja
        // mucho espacio en blanco a los lados. Calculamos según el ancho
        // del viewport. Refresca via this.viewportWidth en mounted/resize.
        dialogWidth() {
            const w = this.viewportWidth || (typeof window !== 'undefined' ? window.innerWidth : 1200)
            if (w < 576) return '100%'        // celular: full screen
            if (w < 768) return '95%'         // tablet vertical
            if (w < 1200) return '80%'        // tablet horizontal
            return '65%'                       // desktop
        },
        dialogTop() {
            const w = this.viewportWidth || (typeof window !== 'undefined' ? window.innerWidth : 1200)
            return w < 768 ? '0' : '7vh'      // mobile: pegado arriba
        },
        // Galería unificada: fotos persistidas en DB (galleryImages, de
        // /items/images/{id}) + fotos recién subidas via upload-async
        // (form.multi_images) que todavía no se guardaron. Los pending
        // se marcan con _pending para mostrar el badge ⏳.
        allGalleryImages() {
            const persisted = (this.galleryImages || []).map((img, i) => ({
                url: img.url,
                _key: 'p_' + (img.id || i),
                _pending: false,
            }))
            const pending = (this.form.multi_images || []).map((img, i) => ({
                url: img.image_url || img.url,
                _key: 'n_' + (img.filename || i),
                _pending: true,
            }))
            return [...persisted, ...pending]
        },
        hasPendingImages() {
            return (this.form.multi_images || []).length > 0
        },
        pendingImagesCount() {
            return (this.form.multi_images || []).length
        },
        // Items que componen el checklist del banner de progreso. Cada uno tiene:
        //   key      identificador estable para v-for
        //   label    texto del chip
        //   status   'done' | 'pending'
        //   required true = obligatorio para guardar/publicar; false = recomendado
        //   target   selector CSS o data-key para hacer scroll al campo
        //   hint     tooltip explicativo
        completionItems() {
            const f = this.form || {}
            const has = v => !!v && (typeof v !== 'string' || v.trim() !== '')
            const items = [
                {
                    key: 'name',
                    label: 'Nombre',
                    required: true,
                    status: has(f.name) ? 'done' : 'pending',
                    target: '[dusk="description"]',
                    hint: 'El nombre del producto es obligatorio',
                },
                {
                    key: 'price',
                    label: 'Precio',
                    required: true,
                    status: parseFloat(f.sale_unit_price) > 0 ? 'done' : 'pending',
                    target: '[dusk="sale_unit_price"]',
                    hint: 'El precio de venta debe ser mayor a 0',
                },
                {
                    key: 'image',
                    label: 'Imagen principal',
                    required: true,
                    status: has(f.image_url) ? 'done' : 'pending',
                    target: '.ie-primary-upload',
                    hint: 'Sube al menos 1 foto para que el cliente vea el producto',
                },
                {
                    key: 'mp_category',
                    label: 'Categoría',
                    required: !!f.marketplace_publishable,
                    status: f.marketplace_category_id ? 'done' : 'pending',
                    target: '.el-cascader',
                    hint: 'Obligatoria si publicas en marketplace',
                },
                {
                    key: 'description',
                    label: 'Descripción',
                    required: false,
                    status: has(f.mp_notes) ? 'done' : 'pending',
                    target: '.ie-collapse',
                    hint: 'Recomendado: productos con descripción venden 3x más',
                },
                {
                    key: 'gallery',
                    label: 'Más fotos (2+)',
                    required: false,
                    status: this.allGalleryImages.length >= 2 ? 'done' : 'pending',
                    target: '.ie-image-card',
                    hint: 'Recomendado: 2+ fotos mejoran la conversión hasta 40%',
                },
            ]
            return items
        },
        completionPercent() {
            const items = this.completionItems
            if (!items.length) return 0
            const done = items.filter(i => i.status === 'done').length
            return Math.round((done / items.length) * 100)
        },
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
    mounted() {
        // Listener para que el modal se reajuste si el seller rota el
        // celular (portrait <-> landscape) o redimensiona la ventana.
        this._onResize = () => { this.viewportWidth = window.innerWidth }
        window.addEventListener('resize', this._onResize)
    },
    beforeDestroy() {
        if (this._onResize) window.removeEventListener('resize', this._onResize)
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
                // Cargar tree + record en PARALELO. Esperamos ambos antes de
                // asignar this.form para que el cascader vea las options ya
                // disponibles cuando v-model recibe marketplace_category_path
                // (sin esperar, el cascader se queda con placeholder porque
                // el path llegó pero el árbol estaba vacío y no re-renderiza).
                Promise.all([
                    this.loadMarketplaceCategoryTree(),
                    this.$http.get(`/${this.resource}/record/${this.recordId}`),
                ]).then((results) => {
                    const response = results[1]
                    this.form = response.data.data
                    if (!Array.isArray(this.form.marketplace_category_path)) {
                        this.form.marketplace_category_path = []
                    }
                    // Si el backend no resolvió el path correcto pero sí
                    // devolvió el ID, lo calculamos en el frontend buscando
                    // en el árbol que ya está cargado. Esto cubre items
                    // creados antes de tener resolveMarketplaceCategoryPath
                    // y casos donde la columna depth_path está vacía.
                    this.hydrateMpCategoryPath()
                    this.has_percentage_perception = (this.form.percentage_perception) ? true : false
                    this.changeAffectationIgvType()
                    // Re-mount del cascader para forzar hidratación con
                    // tree + path nuevos (workaround del bug ElementUI).
                    this.$nextTick(() => { this.cascaderKey++ })
                })
            } else {
                // Producto nuevo: solo necesitamos el tree disponible.
                this.loadMarketplaceCategoryTree()
            }
        },
        loadRecord() {
            if (this.recordId) {
                Promise.all([
                    this.loadMarketplaceCategoryTree(),
                    this.$http.get(`/${this.resource}/record/${this.recordId}`),
                ]).then((results) => {
                    const response = results[1]
                    this.form = response.data.data
                    if (!Array.isArray(this.form.marketplace_category_path)) {
                        this.form.marketplace_category_path = []
                    }
                    this.hydrateMpCategoryPath()
                    this.changeAffectationIgvType()
                    // Si el producto YA tiene imagen y categoría oficial,
                    // colapsamos la sección por default — el seller raramente
                    // necesita verla al editar. Se abre con un click si quiere.
                    if (this.form.image_url && this.form.marketplace_category_id) {
                        this.imageSectionOpen = false
                    }
                    // Si ya hay descripción, arrancar colapsado para no comer
                    // espacio vertical — el usuario expande con el chevron
                    // si quiere editarla.
                    this.mpDescOpen = !this.form.mp_notes
                    this.loadGalleryImages()
                    this.$nextTick(() => { this.cascaderKey++ })
                })
            } else {
                this.loadMarketplaceCategoryTree()
            }
        },

        // Si el ID está pero el path no, busca el path en el árbol cargado.
        // Recursión simple sobre node.children (el tree puede tener cualquier
        // profundidad). Falla silencioso: si no encuentra el ID, deja el
        // path como estaba (al menos vacío para no romper el cascader).
        hydrateMpCategoryPath() {
            const id = this.form.marketplace_category_id
            const currentPath = this.form.marketplace_category_path
            if (!id) return
            // Si el path ya está bien (incluye el id final), no tocamos
            if (Array.isArray(currentPath) && currentPath.length
                && Number(currentPath[currentPath.length - 1]) === Number(id)) {
                return
            }
            const tree = this.mp_category_tree || []
            const find = (nodes, target, acc) => {
                for (const n of nodes) {
                    const next = acc.concat([n.id])
                    if (Number(n.id) === Number(target)) return next
                    if (n.children && n.children.length) {
                        const found = find(n.children, target, next)
                        if (found) return found
                    }
                }
                return null
            }
            const path = find(tree, id, [])
            if (path) this.form.marketplace_category_path = path
        },
        // Retorna una promesa que resuelve cuando el árbol está cargado
        // (o ya estaba). Permite encadenar con Promise.all en create/load.
        loadMarketplaceCategoryTree() {
            if (this.mp_category_tree.length) return Promise.resolve()
            if (this.mp_category_loading) {
                // Ya hay request en vuelo; esperar a que termine vía polling
                // mínimo (15ms) — alternativa a guardar el promise en data.
                return new Promise((resolve) => {
                    const wait = () => this.mp_category_loading
                        ? setTimeout(wait, 15)
                        : resolve()
                    wait()
                })
            }
            this.mp_category_loading = true
            return this.$http.get('/marketplace-categories/tree')
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
        // `options.keepOpen=true` — guarda pero NO cierra el formulario:
        // útil para el botón "Guardar y continuar con variantes" del empty
        // state. Después del save reseteamos el recordId con el id devuelto
        // y disparamos loadRecord() para entrar en modo edición sin que el
        // seller tenga que volver a abrir el form.
        submit(options) {
            const keepOpen = !!(options && options.keepOpen)
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
                        if (keepOpen && response.data.id) {
                            // Re-abrir como edición: setear recordId y recargar
                            // el form con los datos persistidos para que las
                            // secciones bloqueadas (variantes) se desbloqueen.
                            this.recordId = response.data.id
                            this.form.id = response.data.id
                            this.loadRecord()
                            // Y dejar abierta la sección de variantes
                            this.$nextTick(() => {
                                const variantsSection = this.$el.querySelector('.ie-collapse--variants')
                                if (variantsSection) {
                                    variantsSection.setAttribute('open', '')
                                    variantsSection.scrollIntoView({ behavior: 'smooth', block: 'start' })
                                }
                            })
                        } else {
                            this.close()
                        }
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
            // Refrescar la galería inline para que el seller vea las
            // fotos recién subidas sin tener que recargar el form.
            this.loadGalleryImages()
        },
        loadGalleryImages() {
            if (!this.recordId) {
                this.galleryImages = []
                return
            }
            this.$http.get(`/${this.resource}/images/${this.recordId}`)
                .then(response => { this.galleryImages = response.data.data || [] })
                .catch(()  => { this.galleryImages = [] })
        },
        // Click en chip del banner de progreso: scroll al campo + flash visual
        // breve para que el seller vea exactamente dónde escribir.
        scrollToField(selector) {
            if (!selector) return
            const el = this.$el.querySelector(selector)
            if (!el) return
            el.scrollIntoView({ behavior: 'smooth', block: 'center' })
            // Pequeño "flash" para resaltar el campo destino. Funciona porque
            // agregamos la clase, esperamos 1.5s, la quitamos. Si el elemento
            // ya tiene una clase animada Element UI igual ignora la nuestra.
            el.classList.add('ie-flash')
            setTimeout(() => el.classList.remove('ie-flash'), 1500)
            // Intentar enfocar si es input
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.focus()
            } else {
                const input = el.querySelector('input, textarea')
                if (input) input.focus()
            }
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
