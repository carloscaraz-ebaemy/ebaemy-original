<template>
    <div :class="{ 'content-opacity': isVisible }" class="" @click.self="toggleInformation">
        <span class="module-title-marker" data-page-title="Nueva Nota de Venta"></span>
        <Keypress key-event="keyup" @success="checkKey" />
        <Keypress key-event="keyup" :multiple-keys="multiple" @success="checkKeyWithAlt" />

        <div class="tab-content tab-content-default row-new" v-if="company && establishment">
            <div class="invoice p-0">
                <header class="clearfix clearfix-default p-2">
                    <div class="d-flex head-notes">
                        <div class="d-flex is-hidden-mobile">
                            <div class="text-center mt-3 mb-0">
                                <logo url="/" :path_logo="getCurrentLogo"></logo>
                            </div>
                            <div class="text-start mt-3 mb-0" style="margin-left: 10%;">
                                <address class="ib mr-2">
                                    <span class="font-weight-bold d-block">NOTA DE VENTA</span>
                                    <!-- <span class="font-weight-bold  d-block">NV-XXX</span> -->
                                    <span class="font-weight-bold">{{
                                        company.name
                                        }}</span>
                                    <br />
                                    <div v-if="establishment.address != '-'">
                                        {{ establishment.address }},
                                    </div>
                                    {{ establishment.district ? establishment.district.description : '' }}
                                    {{ establishment.province ? ', ' + establishment.province.description : '' }}
                                    {{ establishment.department ? ', ' + establishment.department.description : '' }}
                                    {{ establishment.country ? ' - ' + establishment.country.description : '' }}
                                    <br />
                                    {{ establishment.email }} -
                                    <span v-if="establishment.telephone != '-'">{{ establishment.telephone }}</span>
                                </address>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-end dates datetime-container"
                            style="margin-left: auto;">
                            <div class="p-1 issue-date" style="width: 45%;">
                                <div class="form-group" :class="{
                                    'has-danger': errors.date_of_issue
                                }">
                                    <label class="control-label">Fec. Emisión</label>
                                    <el-date-picker v-model="form.date_of_issue" type="date" value-format="yyyy-MM-dd"
                                        :clearable="false" @change="changeDateOfIssue">
                                    </el-date-picker>
                                    <small class="form-control-feedback" v-if="errors.date_of_issue"
                                        v-text="errors.date_of_issue[0]"></small>
                                </div>
                            </div>
                            <div class="p-1 expiration-date" style="width: 45%;">
                                <div class="form-group" :class="{ 'has-danger': errors.due_date }">
                                    <label class="control-label">Fec. Vencimiento</label>
                                    <el-date-picker v-model="form.due_date" type="date" value-format="yyyy-MM-dd"
                                        :clearable="true" :picker-options="pickerOptions">
                                    </el-date-picker>
                                    <small class="form-control-feedback" v-if="errors.due_date"
                                        v-text="errors.due_date[0]"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
                <form autocomplete="off" @submit.prevent="submit" class="">
                    <div class="form-body m-3">
                        <div class="row mt-1">
                            <div class="col-lg-5">
                                <div class="form-group position-relative" :class="{
                                    'has-danger': errors.customer_id
                                }">
                                    <label class="control-label font-weight-bold">
                                        Cliente
                                    </label>
                                    <el-select v-model="form.customer_id" filterable remote
                                        class="border-left rounded-left border-info" popper-class="el-select-customers"
                                        dusk="customer_id"
                                        placeholder="Escriba el nombre o número de documento del cliente"
                                        :remote-method="searchRemoteCustomers" @change="changeCustomer"
                                        @focus="focus_on_client = true" @blur="focus_on_client = false"
                                        :loading="loading_search" @keyup.enter.native="keyupCustomer">
                                        <el-option v-for="option in customers" :key="option.id" :value="option.id"
                                            :label="option.description"></el-option>

                                        <template slot="empty">
                                            <p v-if="loading_search" class="el-select-dropdown__empty">
                                                Cargando...
                                            </p>

                                            <p v-else class="el-select-dropdown__empty">
                                                No se encontraron resultados
                                            </p>

                                            <div v-if="!loading_search" class="el-select-dropdown__item new-option"
                                                @click.stop="openNewPersonDialog">
                                                <span>{{ customerSearchTerm ? `Crear cliente "${customerSearchTerm}"` :
                                                    'Crear cliente' }}</span>
                                            </div>
                                        </template>
                                    </el-select>
                                    <span class="btn-add-new" @click.prevent="showDialogNewPerson = true"
                                        title="Agregar nuevo cliente">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-user-plus">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                            <path d="M16 19h6" />
                                            <path d="M19 16v6" />
                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4" />
                                        </svg>
                                    </span>
                                    <small class="form-control-feedback" v-if="errors.customer_id"
                                        v-text="errors.customer_id[0]"
                                        style="position:absolute; bottom:-18px; left:0;"></small>
                                </div>
                            </div>

                            <div class="row col-lg-7 pe-0">
                                <div class="col-lg-3 branch-input">
                                    <div class="form-group" :class="{
                                        'has-danger':
                                            errors.establishment_id
                                    }">
                                        <label class="control-label">Sucursal</label>
                                        <el-select v-model="form.establishment_id" @change="changeEstablishment">
                                            <el-option v-for="option in establishments" :key="option.id"
                                                :value="option.id" :label="option.description"></el-option>
                                        </el-select>
                                        <small class="form-control-feedback" v-if="errors.establishment_id"
                                            v-text="errors.establishment_id[0]"></small>
                                    </div>
                                </div>
                                <div class="col-lg-3 serie-input">
                                    <div class="form-group" :class="{
                                        'has-danger': errors.series_id
                                    }">
                                        <label class="control-label">Serie</label>
                                        <el-select v-model="form.series_id" :disabled="disabledSeries()">
                                            <el-option v-for="option in series" :key="option.id" :value="option.id"
                                                :label="option.number"></el-option>
                                        </el-select>
                                        <small class="form-control-feedback" v-if="errors.series_id"
                                            v-text="errors.series_id[0]"></small>
                                    </div>
                                </div>
                                <div class="col-lg-3 money-input">
                                    <div class="form-group" :class="{
                                        'has-danger':
                                            errors.currency_type_id
                                    }">
                                        <label class="control-label">Moneda</label>
                                        <el-select v-model="form.currency_type_id" @change="changeCurrencyType">
                                            <el-option v-for="option in currency_types" :key="option.id"
                                                :value="option.id" :label="option.description"></el-option>
                                        </el-select>
                                        <small class="form-control-feedback" v-if="errors.currency_type_id"
                                            v-text="errors.currency_type_id[0]"></small>
                                    </div>
                                </div>
                                <div class="col-lg-3 change-type">
                                    <div class="form-group" :class="{
                                        'has-danger':
                                            errors.exchange_rate_sale
                                    }">
                                        <label class="control-label">Tipo de cambio
                                            <el-tooltip class="item" effect="dark"
                                                content="Tipo de cambio del día, extraído de SUNAT" placement="top-end">
                                                <i class="fa fa-info-circle"></i>
                                            </el-tooltip>
                                        </label>
                                        <el-input v-model="form.exchange_rate_sale"></el-input>
                                        <small class="form-control-feedback" v-if="errors.exchange_rate_sale" v-text="errors.exchange_rate_sale[0]
                                            "></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Informacion Adicional -->
                            <div>
                                <!-- Botón para mostrar/ocultar el componente -->
                                <span class="toggle-button toggle-button-sales" :class="{ shift: isVisible }"
                                    @click="toggleInformation"
                                    :title="isVisible ? 'Cerrar Información Adicional' : 'Abrir Información Adicional'">
                                    <span class="toggle-button-text">
                                        {{
                                            isVisible
                                                ? "Cerrar Información Adicional"
                                                : "Abrir Información Adicional"
                                        }}
                                    </span>
                                </span>
                                <div class="column pt-2 ps-5 pe-5 additional-information" :class="{ show: isVisible }">
                                    <h3 class="text-center">
                                        Información Adicional
                                    </h3>

                                    <div class="close-container">
                                        <i class="el-icon el-icon-close" @click="toggleInformation">
                                        </i>
                                    </div>

                                    <div class="">
                                        <div class="form-group form-seller">
                                            <label class="control-label">Vendedor</label>
                                            <el-select v-model="form.seller_id" clearable>
                                                <el-option v-for="sel in sellers" :key="sel.id" :value="sel.id"
                                                    :label="sel.name">{{ sel.name }}
                                                </el-option>
                                            </el-select>
                                        </div>
                                    </div>

                                    <div class="">
                                        <div class="form-group">
                                            <label class="control-label">
                                                Tipo periodo
                                                <el-tooltip class="item" effect="dark"
                                                    content="Creación recurrente de N. Venta de forma automática, por periodo."
                                                    placement="top-start">
                                                    <i class="fa fa-info-circle"></i>
                                                </el-tooltip>
                                            </label>
                                            <el-select v-model="form.type_period" clearable>
                                                <el-option v-for="option in type_periods" :key="option.id"
                                                    :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="errors.type_period"
                                                v-text="errors.type_period[0]"></small>
                                        </div>
                                    </div>
                                    <div class="">
                                        <div class="form-group">
                                            <label class="control-label">Cant. Periodos</label>
                                            <el-input-number v-model="form.quantity_period" :min="0"></el-input-number>
                                            <small class="form-control-feedback" v-show="sms_periodo.length > 1"
                                                v-text="sms_periodo"></small>
                                        </div>
                                    </div>

                                    <div v-if="
                                        config.active_allowance_charge &&
                                        form.total > 0
                                    " class="col-lg-2 col-md-2">
                                        <div class="form-group">
                                            <label class="control-label">Porcentaje otros cargos</label>

                                            <el-input-number v-model="config.percentage_allowance_charge
                                                " :min="0" controls-position="right" size="mini"
                                                @change="calculateTotal">
                                            </el-input-number>
                                        </div>
                                    </div>

                                    <div class="">
                                        <div class="form-group">
                                            <label class="control-label">Placa</label>
                                            <el-input v-model="form.license_plate" :maxlength="200"></el-input>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label">Orden de compra</label>
                                        <el-input v-model="form.purchase_order" :maxlength="50"></el-input>
                                    </div>

                                    <div class="">
                                        <div class="form-group">
                                            <label class="control-label">Observación
                                            </label>
                                            <el-input type="textarea" v-model="form.observation"></el-input>
                                            <small class="form-control-feedback" v-if="errors.observation"
                                                v-text="errors.observation[0]"></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- ── Tipo de entrega (solo usuarios con permiso de despacho) ──── -->
                            <div v-if="canManageDispatch" class="row mt-2">
                                <div class="col-12">
                                    <label class="fw-semibold small text-muted mb-2 d-block">
                                        <i class="fas fa-shipping-fast me-1"></i> Tipo de Entrega
                                    </label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Entrega Inmediata -->
                                        <label class="delivery-option border rounded p-2 flex-fill text-center cursor-pointer"
                                               :class="form.delivery_type === 'store' ? 'border-secondary bg-light' : 'border-light'"
                                               style="cursor:pointer; min-width:120px">
                                            <input type="radio" v-model="form.delivery_type" value="store" class="d-none">
                                            <div>
                                                <i class="fas fa-store fa-lg" :class="form.delivery_type === 'store' ? 'text-secondary' : 'text-muted'"></i>
                                            </div>
                                            <div class="small fw-semibold mt-1" :class="form.delivery_type === 'store' ? 'text-secondary' : 'text-muted'">
                                                Entrega Inmediata
                                            </div>
                                            <div class="text-muted" style="font-size:10px">Se lleva ahora</div>
                                        </label>
                                        <!-- Recojo en Tienda -->
                                        <label class="delivery-option border rounded p-2 flex-fill text-center cursor-pointer"
                                               :class="form.delivery_type === 'pickup' ? 'border-info bg-light' : 'border-light'"
                                               style="cursor:pointer; min-width:120px">
                                            <input type="radio" v-model="form.delivery_type" value="pickup" class="d-none">
                                            <div>
                                                <i class="fas fa-hand-holding-box fa-lg" :class="form.delivery_type === 'pickup' ? 'text-info' : 'text-muted'"></i>
                                            </div>
                                            <div class="small fw-semibold mt-1" :class="form.delivery_type === 'pickup' ? 'text-info' : 'text-muted'">
                                                Recojo en Tienda
                                            </div>
                                            <div class="text-muted" style="font-size:10px">Paga y vuelve a recoger</div>
                                        </label>
                                        <!-- Courier / Despacho -->
                                        <label class="delivery-option border rounded p-2 flex-fill text-center cursor-pointer"
                                               :class="form.delivery_type === 'province' ? 'border-primary bg-light' : 'border-light'"
                                               style="cursor:pointer; min-width:120px"
                                               @click="onSelectCourierDelivery">
                                            <input type="radio" v-model="form.delivery_type" value="province" class="d-none">
                                            <div>
                                                <i class="fas fa-truck fa-lg" :class="form.delivery_type === 'province' ? 'text-primary' : 'text-muted'"></i>
                                            </div>
                                            <div class="small fw-semibold mt-1" :class="form.delivery_type === 'province' ? 'text-primary' : 'text-muted'">
                                                Envío por Courier
                                            </div>
                                            <div class="text-muted" style="font-size:10px">Olva, Shalom, etc.</div>
                                        </label>
                                    </div>
                                    <!-- Alerta urgente (solo para recojo o courier) -->
                                    <div v-if="form.delivery_type !== 'store'" class="mt-2">
                                        <label class="d-flex align-items-center gap-2 cursor-pointer"
                                               style="cursor:pointer">
                                            <input type="checkbox" v-model="form.is_urgent" class="form-check-input mt-0">
                                            <span class="small">
                                                <i class="fas fa-bolt text-danger me-1"></i>
                                                <strong class="text-danger">URGENTE</strong>
                                                — aparece primero en la cola del almacén
                                            </span>
                                        </label>
                                    </div>
                                    <!-- Info contextual -->
                                    <div class="mt-2">
                                        <small class="text-muted" v-if="form.delivery_type === 'store'">
                                            <i class="fas fa-info-circle me-1"></i>El cliente se lleva el producto ahora mismo. No pasa por la cola del almacén.
                                        </small>
                                        <small class="text-info" v-else-if="form.delivery_type === 'pickup'">
                                            <i class="fas fa-info-circle me-1"></i>El cliente <strong>paga aquí y vuelve</strong> a recoger. El almacén preparará el pedido.
                                        </small>
                                        <small class="text-primary" v-else-if="form.delivery_type === 'province'">
                                            <i class="fas fa-info-circle me-1"></i>El pedido se enviará por courier.
                                        </small>
                                    </div>

                                    <!-- ── Datos de envío — botón compacto ──────────────────── -->
                                    <div v-if="form.delivery_type === 'province'" class="mt-2">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <el-button size="small" type="primary" plain
                                                       icon="el-icon-location-outline"
                                                       @click="showShippingDialog = true">
                                                Datos de Envío
                                                <span v-if="form.shipping_address"
                                                      class="ms-1 text-success fw-bold">✓</span>
                                            </el-button>
                                            <!-- Resumen compacto si ya se llenaron -->
                                            <span v-if="form.shipping_recipient || form.shipping_address"
                                                  class="text-muted small"
                                                  style="max-width:420px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">
                                                <i class="fas fa-user me-1"></i>{{ form.shipping_recipient }}
                                                <span v-if="form.shipping_address">
                                                    &nbsp;·&nbsp;<i class="fas fa-map-marker-alt me-1"></i>{{ form.shipping_address }}
                                                    <span v-if="form.shipping_city">, {{ form.shipping_city }}</span>
                                                </span>
                                                <span v-if="form.preferred_courier">
                                                    &nbsp;·&nbsp;<i class="fas fa-truck me-1"></i>{{ form.preferred_courier }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    <!-- fin datos de envío -->

                                </div>
                            </div>
                            <!-- fin de informacion adicional -->
                        </div>
                        <div v-if="consigneds.length" class="card-body border-top">
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <div :class="{ 'has-danger': errors.consigned_id }" class="form-group">
                                        <label class="control-label fw-bold text-info">
                                            Consignado
                                            <a href="#" @click.prevent="showDialogConsignedForm = true">[+ Nuevo]</a>
                                        </label>
                                        <el-select class="w-100" v-model="form.consigned_id"
                                            @change="getConsignedAddresses" filterable
                                            placeholder="Seleccionar consignado">
                                            <el-option v-for="option in consigneds" :key="option.id"
                                                :label="option.name" :value="option.id"></el-option>
                                        </el-select>
                                        <small v-if="errors.consigned_id" class="invalid-feedback"
                                            v-text="errors.consigned_id[0]"></small>
                                    </div>
                                </div>
                                <div class="form-group col-sm-6 mb-0">
                                    <label class="control-label fw-bold text-info">Dirección</label>
                                    <el-select v-model="form.consigned_address_id" @change="changeConsignedAddresses">
                                        <el-option v-for="option in consigned_addresses" :key="option.id"
                                            :label="option.address" :value="option.id"></el-option>
                                    </el-select>
                                </div>
                            </div>
                        </div>


                        <div class="row mt-4" v-loading="loading_items">
                            <div class="col-md-8 mb-3" v-if="showSearchItemsMainForm">
                                <item-search-quick-sale @changeItem="changeItemQuickSale" :resource="resource"
                                    :showDetailButton="configuration.show_all_item_details
                                        " :selectedOptionPrice="selected_option_price"
                                    ref="item_search_quick_sale"></item-search-quick-sale>
                            </div>
                            <div class="col-md-4">
                                <el-select v-if="!configuration.enable_list_product" v-model="selected_option_price"
                                    filterable style="width:100%;">
                                    <el-option v-for="option in price_options" :key="option.id"
                                        :label="option.description" :value="option.id"></el-option>
                                </el-select>
                            </div>

                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table mb-1">
                                        <template v-if="showEditableItems">
                                            <thead>
                                                <tr>
                                                    <!-- <th width="3%">#</th> -->
                                                    <th class="font-weight-bold" width="16%">
                                                        Descripción
                                                    </th>
                                                    <th width="8%" class="text-center font-weight-bold">
                                                        Unidad
                                                    </th>
                                                    <th width="12%" class="text-end font-weight-bold">
                                                        Cantidad
                                                    </th>
                                                    <th width="14%" class="text-end font-weight-bold">
                                                        Valor Unitario
                                                    </th>
                                                    <th width="14%" class="text-end font-weight-bold">
                                                        Precio Unitario
                                                    </th>
                                                    <th width="14%" class="text-end font-weight-bold">
                                                        Subtotal
                                                    </th>
                                                    <th width="14%" class="text-end font-weight-bold">
                                                        Total
                                                    </th>
                                                    <th width="5%"></th>
                                                </tr>
                                            </thead>
                                            <tbody v-if="form.items.length > 0">
                                                <tr v-for="(row,
                                                    index) in form.items" :key="index">
                                                    <!-- <td>{{ index + 1 }}</td> -->
                                                    <td>
                                                        <template v-if="
                                                            canAddDescriptionToDocumentItem
                                                        ">
                                                            <template v-if="
                                                                row.name_product_pdf &&
                                                                row.name_product_pdf !=
                                                                ''
                                                            ">
                                                                <label v-html="row.name_product_pdf
                                                                    "></label>
                                                            </template>
                                                            <template v-else>
                                                                <label>
                                                                    <p v-text="setDescriptionOfItem(
                                                                        row.item
                                                                    )
                                                                        "></p>
                                                                </label>
                                                            </template>
                                                        </template>
                                                        <template v-else>
                                                            {{
                                                                setDescriptionOfItem(
                                                                    row.item
                                                                )
                                                            }}
                                                        </template>

                                                        <pack-item-description v-if="
                                                            row.item
                                                                .is_set &&
                                                            configuration.show_item_description_pack
                                                        " :item-id="row.item_id
                                                                ">
                                                        </pack-item-description>

                                                        <template v-if="
                                                            row.item
                                                                .presentation
                                                        ">
                                                            {{
                                                                row.item.presentation.hasOwnProperty(
                                                                    "description"
                                                                )
                                                                    ? row.item
                                                                        .presentation
                                                                        .description
                                                                    : ""
                                                            }}
                                                        </template>
                                                        <br />
                                                        <small v-if="row.affectation_igv_type">{{
                                                            row
                                                                .affectation_igv_type
                                                                .description
                                                        }}</small>

                                                        <p class="control-label font-weight-bold text-info" v-if="
                                                            configuration.show_all_item_details
                                                        ">
                                                            <a href="#" @click.prevent="
                                                                clickShowItemDetail(
                                                                    row.item_id
                                                                )
                                                                ">[Ver
                                                                detalle]</a>
                                                        </p>
                                                    </td>
                                                    <td class="text-center">
                                                        {{
                                                            row.item
                                                                .unit_type_id
                                                        }}
                                                    </td>

                                                    <td class="text-end">
                                                        <div @keydown.enter="
                                                            handleEnterKey(
                                                                $event
                                                            )
                                                            ">
                                                            <el-input-number v-model="row.quantity
                                                                " :min="0.01" class="input-custom"
                                                                controls-position="right"
                                                                style="min-width: 70px !important" :disabled="hasRowAdvancedOption(
                                                                    row
                                                                )
                                                                    " @change="
                                                                    changeRowQuantity(
                                                                        row
                                                                    )
                                                                    " @focus="
                                                                    valueInputSelect(
                                                                        $event
                                                                    )
                                                                    ">
                                                            </el-input-number>
                                                        </div>
                                                    </td>

                                                    <td class="text-end">
                                                        <div @keydown.enter="
                                                            handleEnterKey(
                                                                $event
                                                            )
                                                            " class="input-with-currency">
                                                            <span class="currency-symbol">{{
                                                                currency_type.symbol
                                                            }}</span>

                                                            <el-input-number v-model="row.unit_value
                                                                " :min="0" class="input-custom"
                                                                controls-position="right"
                                                                style="min-width: 115px !important" :disabled="hasRowAdvancedOption(
                                                                    row
                                                                ) ||
                                                                    !hasPermissionEditItemPrices(
                                                                        authUser.permission_edit_item_prices
                                                                    )
                                                                    " @change="
                                                                    changeRowUnitValue(
                                                                        row
                                                                    )
                                                                    " @focus="
                                                                    valueInputSelect(
                                                                        $event
                                                                    )
                                                                    ">
                                                            </el-input-number>
                                                        </div>
                                                    </td>

                                                    <td class="text-end">
                                                        <div @keydown.enter="
                                                            handleEnterKey(
                                                                $event
                                                            )
                                                            " class="input-with-currency">
                                                            <span class="currency-symbol">{{
                                                                currency_type.symbol
                                                            }}</span>

                                                            <el-input-number v-model="row.unit_price
                                                                " :min="0.01" class="input-custom"
                                                                controls-position="right"
                                                                style="min-width: 115px !important" :disabled="hasRowAdvancedOption(
                                                                    row
                                                                ) ||
                                                                    !hasPermissionEditItemPrices(
                                                                        authUser.permission_edit_item_prices
                                                                    )
                                                                    " @change="
                                                                    changeRowUnitPrice(
                                                                        row
                                                                    )
                                                                    " @focus="
                                                                    valueInputSelect(
                                                                        $event
                                                                    )
                                                                    ">
                                                            </el-input-number>
                                                        </div>
                                                    </td>

                                                    <td class="text-end">
                                                        <div @keydown.enter="
                                                            handleEnterKey(
                                                                $event
                                                            )
                                                            " class="input-with-currency">
                                                            <span class="currency-symbol">{{
                                                                currency_type.symbol
                                                            }}</span>

                                                            <el-input-number v-model="row.total_value
                                                                " :min="0.01" class="input-custom"
                                                                controls-position="right"
                                                                style="min-width: 115px !important" :disabled="hasRowAdvancedOption(
                                                                    row
                                                                ) ||
                                                                    !hasPermissionEditItemPrices(
                                                                        authUser.permission_edit_item_prices
                                                                    )
                                                                    " @change="
                                                                    changeRowTotalValue(
                                                                        row
                                                                    )
                                                                    " @focus="
                                                                    valueInputSelect(
                                                                        $event
                                                                    )
                                                                    ">
                                                            </el-input-number>
                                                        </div>
                                                    </td>

                                                    <td class="text-end">
                                                        <div @keydown.enter="
                                                            handleEnterKey(
                                                                $event
                                                            )
                                                            " class="input-with-currency">
                                                            <span class="currency-symbol">{{
                                                                currency_type.symbol
                                                            }}</span>

                                                            <el-input-number v-model="row.total
                                                                " :min="0" class="input-custom"
                                                                controls-position="right"
                                                                style="min-width: 115px !important" :disabled="hasRowAdvancedOption(
                                                                    row
                                                                ) ||
                                                                    !hasPermissionEditItemPrices(
                                                                        authUser.permission_edit_item_prices
                                                                    )
                                                                    " @change="
                                                                    changeRowTotal(
                                                                        row
                                                                    )
                                                                    " @focus="
                                                                    valueInputSelect(
                                                                        $event
                                                                    )
                                                                    ">
                                                            </el-input-number>
                                                        </div>
                                                    </td>

                                                    <td class="text-center">
                                                        <button class="btn waves-effect waves-light btn-xs btn-info"
                                                            type="button" @click="
                                                                clickEdiItem(
                                                                    row,
                                                                    index
                                                                )
                                                                ">
                                                            <span style="font-size:10px;">&#9998;</span>
                                                        </button>

                                                        <button type="button"
                                                            class="btn waves-effect waves-light btn-xs btn-danger"
                                                            @click.prevent="
                                                                clickRemoveItem(
                                                                    index
                                                                )
                                                                ">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="9"></td>
                                                </tr>
                                            </tbody>
                                        </template>

                                        <template v-else>
                                            <thead>
                                                <tr class="table-titles-default">
                                                    <th width="0.5%">
                                                        <!--#-->
                                                    </th>
                                                    <th class="font-weight-bold" width="30%">
                                                        Descripción
                                                    </th>
                                                    <th width="8%" class="text-center font-weight-bold">
                                                        Unidad
                                                    </th>
                                                    <th width="8%" class="text-center font-weight-bold">
                                                        Cantidad
                                                    </th>
                                                    <th class="text-center font-weight-bold">
                                                        Valor Unitario
                                                    </th>
                                                    <th class="text-center font-weight-bold">
                                                        Precio Unitario
                                                    </th>
                                                    <th class="text-center font-weight-bold">
                                                        Subtotal
                                                    </th>
                                                    <th class="text-center font-weight-bold">
                                                        Total
                                                    </th>
                                                    <th width="8%"></th>
                                                </tr>
                                            </thead>
                                            <tbody v-if="form.items.length > 0">
                                                <tr v-for="(row,
                                                    index) in form.items" :key="index">
                                                    <td>
                                                        <!--{{ index + 1 }}-->
                                                    </td>
                                                    <td>
                                                        <template v-if="
                                                            canAddDescriptionToDocumentItem
                                                        ">
                                                            <template v-if="
                                                                row.name_product_pdf &&
                                                                row.name_product_pdf !=
                                                                ''
                                                            ">
                                                                <label v-html="row.name_product_pdf
                                                                    "></label>
                                                            </template>
                                                            <template v-else>
                                                                <label>
                                                                    <p v-text="setDescriptionOfItem(
                                                                        row.item
                                                                    )
                                                                        "></p>
                                                                </label>
                                                            </template>
                                                        </template>
                                                        <template v-else>
                                                            {{
                                                                setDescriptionOfItem(
                                                                    row.item
                                                                )
                                                            }}
                                                        </template>

                                                        <pack-item-description v-if="
                                                            row.item
                                                                .is_set &&
                                                            configuration.show_item_description_pack
                                                        " :item-id="row.item_id
                                                                ">
                                                        </pack-item-description>

                                                        <template v-if="
                                                            row.item
                                                                .presentation
                                                        ">
                                                            {{
                                                                row.item.presentation.hasOwnProperty(
                                                                    "description"
                                                                )
                                                                    ? row.item
                                                                        .presentation
                                                                        .description
                                                                    : ""
                                                            }}
                                                        </template>
                                                        <br /><small v-if="row.affectation_igv_type">{{
                                                            row
                                                                .affectation_igv_type
                                                                .description
                                                        }}</small>

                                                        <p class="control-label font-weight-bold text-info" v-if="
                                                            configuration.show_all_item_details
                                                        ">
                                                            <a href="#" @click.prevent="
                                                                clickShowItemDetail(
                                                                    row.item_id
                                                                )
                                                                ">[Ver
                                                                detalle]</a>
                                                        </p>
                                                    </td>
                                                    <td class="text-center">
                                                        {{
                                                            row.item
                                                                .unit_type_id
                                                        }}
                                                    </td>
                                                    <td class="text-center">
                                                        {{ row.quantity }}
                                                    </td>
                                                    <td class="text-center">
                                                        {{
                                                            currency_type.symbol
                                                        }}
                                                        {{
                                                            getFormatUnitPriceRow(
                                                                row.unit_value
                                                            )
                                                        }}
                                                    </td>
                                                    <td class="text-center">
                                                        {{
                                                            currency_type.symbol
                                                        }}
                                                        {{
                                                            getFormatUnitPriceRow(
                                                                row.unit_price
                                                            )
                                                        }}
                                                    </td>

                                                    <td class="text-center">
                                                        {{
                                                            currency_type.symbol
                                                        }}
                                                        {{ row.total_value }}
                                                    </td>
                                                    <td class="text-center">
                                                        {{
                                                            currency_type.symbol
                                                        }}
                                                        {{ row.total }}
                                                    </td>
                                                    <td class="text-center">
                                                        <button class="btn waves-effect waves-light btn-xs btn-info"
                                                            type="button" @click="
                                                                clickEdiItem(
                                                                    row,
                                                                    index
                                                                )
                                                                ">
                                                            <span style="font-size:10px;">&#9998;</span>
                                                        </button>

                                                        <button type="button"
                                                            class="btn waves-effect waves-light btn-xs btn-danger"
                                                            @click.prevent="
                                                                clickRemoveItem(
                                                                    index
                                                                )
                                                                ">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="9"></td>
                                                </tr>
                                            </tbody>
                                        </template>
                                    </table>
                                </div>
                            </div>

                            <div class="col-lg-12 col-md-6 d-flex flex-column align-items-start mt-0">
                                <div class="pb-2">
                                    <el-popover placement="top-start" :open-delay="1000" width="145" trigger="hover"
                                        content="Presiona F2">
                                        <button slot="reference" type="button"
                                            class="btn waves-effect waves-light btn-primary"
                                            @click.prevent="clickAddItem">
                                            Agregar Producto <kbd>F2</kbd>
                                        </button>
                                    </el-popover>
                                </div>
                                <div v-if="form.items.length > 0" class="total-rows">
                                    <span>Total de ítems:
                                        {{ form.items.length }}</span>
                                </div>
                            </div>

                            <div class="col-md-8 mt-3"></div>

                            <div class="col-md-12">
                                <!-- descuentos -->
                                <div class="row mt-1 mb-2" v-if="form.total > 0">
                                    <div class="col-lg-10 float-end">
                                        <label class="float-end control-label">
                                            <el-tooltip class="item" :content="global_discount_type.description
                                                " effect="dark" placement="top">
                                                <i class="fa fa-info-circle"></i>
                                            </el-tooltip>

                                            DESCUENTO
                                            {{ is_amount ? "MONTO" : "%" }}
                                            <el-checkbox v-model="is_amount" class="ml-1 mr-1"
                                                @change="changeTypeDiscount"></el-checkbox>
                                            :
                                        </label>
                                    </div>

                                    <div class="col-lg-2 text-end">
                                        <el-input-number v-model="total_global_discount" :min="0" class="input-custom"
                                            controls-position="right"
                                            @change="changeTotalGlobalDiscount"></el-input-number>
                                    </div>
                                </div>
                                <!-- descuentos -->

                                <p class="text-end" v-if="form.total_exportation > 0">
                                    OP.EXPORTACIÓN: {{ currency_type.symbol }}
                                    {{ form.total_exportation }}
                                </p>
                                <p class="text-end" v-if="form.total_free > 0">
                                    OP.GRATUITAS: {{ currency_type.symbol }}
                                    {{ form.total_free }}
                                </p>
                                <p class="text-end" v-if="form.total_unaffected > 0">
                                    OP.INAFECTAS: {{ currency_type.symbol }}
                                    {{ form.total_unaffected }}
                                </p>
                                <p class="text-end" v-if="form.total_exonerated > 0">
                                    OP.EXONERADAS: {{ currency_type.symbol }}
                                    {{ form.total_exonerated }}
                                </p>
                                <p class="text-end" v-if="form.total_taxed > 0">
                                    OP.GRAVADA: {{ currency_type.symbol }}
                                    {{ form.total_taxed }}
                                </p>
                                <p class="text-end" v-if="form.total_igv > 0">
                                    IGV: {{ currency_type.symbol }}
                                    {{ form.total_igv }}
                                </p>
                                <p class="text-end" v-if="form.total_discount > 0">
                                    DESCUENTOS TOTALES:
                                    {{ currency_type.symbol }}
                                    {{ form.total_discount }}
                                </p>

                                <div class="row mt-1" v-if="form.total > 0">
                                    <div class="col-lg-10 float-end mt-1">
                                        <label class="float-end control-label">OTROS CARGOS:
                                        </label>
                                    </div>
                                    <div class="col-lg-2 float-end">
                                        <div class="form-group">
                                            <table>
                                                <tr>
                                                    <td>
                                                        {{
                                                            currency_type.symbol
                                                        }}
                                                    </td>
                                                    <td>
                                                        <el-input-number v-model="total_global_charge
                                                            " :disabled="config.active_allowance_charge ==
                                                                    true
                                                                    ? true
                                                                    : false
                                                                " :min="0" class="input-custom ml-2"
                                                            controls-position="right" @change="
                                                                calculateTotal
                                                            ">
                                                        </el-input-number>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Costo de envío al cliente (solo provincia, no facturado) ── -->
                                <div v-if="form.delivery_type === 'province' && form.total > 0"
                                     class="border rounded p-2 mb-2 bg-light">
                                    <div class="d-flex align-items-center flex-wrap justify-content-end" style="gap:.5rem;">
                                        <span class="text-muted small">
                                            <i class="fas fa-truck mr-1"></i>
                                            <b>Envío cobrado al cliente</b>
                                            <span class="badge badge-warning ml-1">No facturado</span>
                                        </span>
                                        <el-input-number
                                            v-model="form.shipping_cost_customer"
                                            :min="0" :precision="2" :step="1"
                                            size="small" style="width:130px;"
                                            placeholder="S/ 0.00">
                                        </el-input-number>
                                    </div>
                                    <div class="text-end mt-1" v-if="form.shipping_cost_customer > 0">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Almacén confirmará bultos y costo de agencia al despachar.
                                        </small>
                                    </div>
                                </div>

                                <h3 class="text-end" v-if="form.total > 0">
                                    <b>TOTAL A PAGAR: </b>{{ currency_type.symbol }} {{ form.total }}
                                </h3>

                                <h5 class="text-end" v-if="form.total > 0">
                                    CONDICIÓN DE PAGO:
                                    <el-select v-model="form.payment_condition_id" dusk="document_type_id"
                                        popper-class="el-select-document_type" style="max-width: 200px;"
                                        @change="changePaymentCondition">
                                        <el-option label="Crédito con cuotas" value="03"
                                            :disabled="customer_has_expired"></el-option>
                                        <el-option label="Crédito" value="02"
                                            :disabled="customer_has_expired"></el-option>
                                        <el-option label="Contado" value="01"></el-option>
                                    </el-select>
                                </h5>

                                <div v-if="form.total > 0 && customer_has_expired"
                                    class="alert float-end alert-danger mt-2 mb-0 text-center">
                                    El cliente excede los {{ config.finances.max_expired_days }} días de vencimiento de
                                    crédito. Solo puede
                                    emitir comprobantes al contado.
                                </div>
                            </div>
                            <div class="col-md-4 mt-3"></div>
                            <div class="col-md-8">
                                <div class="d-flex justify-content-end">

                                    <!-- Pagos -->
                                    <table width="100%">
                                        <tr v-if="form.total > 0">
                                            <!-- Metodos de pago -->
                                            <td class="p-0" colspan="2">
                                                <!-- Crédito con cuotas -->
                                                <div v-if="
                                                    form.payment_condition_id ===
                                                    '03'
                                                " class="table-responsive">
                                                    <table class="text-start table" style="table-layout: auto;"
                                                        width="100%">
                                                        <thead>
                                                            <tr v-if="
                                                                form
                                                                    .fee
                                                                    .length >
                                                                0
                                                            ">
                                                                <th class="text-start" style="width: 100px">
                                                                    Fecha
                                                                </th>
                                                                <th class="text-start" style="width: 100px">
                                                                    Monto
                                                                </th>
                                                                <th style="width: 30px"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(row,
                                                                index) in form.fee" :key="index
                                                                                ">
                                                                <td>
                                                                    <el-date-picker v-model="row.date
                                                                        " :clearable="false
                                                                                        " format="dd/MM/yyyy" type="date"
                                                                        @change="
                                                                            changeCreditFeeDate(
                                                                                index
                                                                            )
                                                                            "
                                                                        value-format="yyyy-MM-dd"></el-date-picker>
                                                                </td>
                                                                <td>
                                                                    <el-input v-model="row.amount
                                                                        "></el-input>
                                                                </td>
                                                                <td class="text-center">
                                                                    <button v-if="
                                                                        index >
                                                                        0
                                                                    "
                                                                        class="btn waves-effect waves-light btn-xs btn-danger"
                                                                        type="button" @click.prevent="
                                                                            clickRemoveFee(
                                                                                index
                                                                            )
                                                                            ">
                                                                        <i class="fa fa-trash"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="5">
                                                                    <label class="control-label">
                                                                        <a class="" href="#" @click.prevent="
                                                                            clickAddFee
                                                                        "><i
                                                                                class="fa fa-plus font-weight-bold text-info"></i>
                                                                            <span style="color: #777777">Agregar
                                                                                cuota</span></a>
                                                                    </label>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <!-- Credito -->
                                                <div v-if="
                                                    form.payment_condition_id ===
                                                    '02'
                                                " class="table-responsive">
                                                    <table v-if="
                                                        form.fee
                                                            .length >
                                                        0
                                                    " class="text-start table" width="100%">
                                                        <thead>
                                                            <tr>
                                                                <th v-if="
                                                                    form
                                                                        .fee
                                                                        .length >
                                                                    0
                                                                " style="width: 120px">
                                                                    Método
                                                                    de
                                                                    pago
                                                                </th>
                                                                <th class="text-start" style="width: 100px">
                                                                    Fecha
                                                                </th>
                                                                <th class="text-start" style="width: 100px">
                                                                    Monto
                                                                </th>
                                                                <th style="width: 30px"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(row,
                                                                index) in form.fee" :key="index
                                                                                ">
                                                                <td>
                                                                    <el-select v-model="row.payment_method_type_id
                                                                        " @change="
                                                                                        changePaymentMethodType(
                                                                                            index
                                                                                        )
                                                                                        ">
                                                                        <el-option
                                                                            v-for="option in credit_payment_method" :key="option.id
                                                                                " :label="option.description
                                                                                            " :value="option.id
                                                                                            "></el-option>
                                                                    </el-select>
                                                                </td>
                                                                <td>
                                                                    <el-date-picker v-model="row.date
                                                                        " :clearable="false
                                                                                        " format="dd/MM/yyyy" type="date"
                                                                        value-format="yyyy-MM-dd" :readonly="row.payment_method_type_id !==
                                                                            '09'
                                                                            ">
                                                                    </el-date-picker>
                                                                </td>
                                                                <td>
                                                                    <el-input v-model="row.amount
                                                                        " :readonly="true
                                                                                        "></el-input>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <!-- Contado -->
                                                <div v-if="
                                                    !is_receivable &&
                                                    form.payment_condition_id ===
                                                    '01'
                                                " class="table-responsive payment mt-4">
                                                    <table class="text-start table">
                                                        <thead>
                                                            <tr>
                                                                <template v-if="
                                                                    showLoadVoucher &&
                                                                    form
                                                                        .payments
                                                                        .length >
                                                                    0
                                                                ">
                                                                    <th style="width:50px">
                                                                        Voucher
                                                                    </th>
                                                                </template>

                                                                <th v-if="
                                                                    form
                                                                        .payments
                                                                        .length >
                                                                    0
                                                                " style="width: 120px">
                                                                    Método
                                                                    de
                                                                    pago
                                                                </th>
                                                                <template v-if="
                                                                    enabled_payments
                                                                ">
                                                                    <th v-if="
                                                                        form
                                                                            .payments
                                                                            .length >
                                                                        0
                                                                    " style="width: 120px">
                                                                        Destino
                                                                        <el-tooltip class="item"
                                                                            content="Aperture caja o cuentas bancarias"
                                                                            effect="dark" placement="top-start">
                                                                            <i class="fa fa-info-circle"></i>
                                                                        </el-tooltip>
                                                                    </th>
                                                                    <th v-if="
                                                                        form
                                                                            .payments
                                                                            .length >
                                                                        0
                                                                    " style="width: 100px">
                                                                        Referencia
                                                                    </th>
                                                                    <th v-if="
                                                                        form
                                                                            .payments
                                                                            .length >
                                                                        0
                                                                    " style="width: 100px">
                                                                        Monto
                                                                    </th>
                                                                    <th style="width: 30px"></th>
                                                                </template>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(row,
                                                                index) in form.payments" :key="index
                                                                                ">
                                                                <template v-if="
                                                                    showLoadVoucher
                                                                ">
                                                                    <td class="" style="width: 50px">
                                                                        <el-upload accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp" :data="{
                                                                            index: index
                                                                        }" :headers="headers_token
                                                                                            " :multiple="false
                                                                                            " :on-remove="(
                                                                                            file,
                                                                                            fileList
                                                                                        ) =>
                                                                                                handleRemoveUploadVoucher(
                                                                                                    file,
                                                                                                    fileList,
                                                                                                    index
                                                                                                )
                                                                                            " :action="`/finances/payment-file/upload`
                                                                                            " :show-file-list="true
                                                                                            " :file-list="row.file_list
                                                                                            " :on-success="(
                                                                                            response,
                                                                                            file,
                                                                                            fileList
                                                                                        ) =>
                                                                                                onSuccessUploadVoucher(
                                                                                                    response,
                                                                                                    file,
                                                                                                    fileList,
                                                                                                    index
                                                                                                )
                                                                                            " :limit="1
                                                                                            ">
                                                                            <button type="button"
                                                                                class="btn btn-sm btn-primary"
                                                                                slot="trigger">
                                                                                <i class="fasa fa-fw fa-upload"></i>
                                                                            </button>
                                                                        </el-upload>
                                                                    </td>
                                                                </template>

                                                                <td>
                                                                    <el-select v-model="row.payment_method_type_id
                                                                        " @change="
                                                                                        changePaymentMethodType(
                                                                                            index
                                                                                        )
                                                                                        ">
                                                                        <el-option v-for="option in cash_payment_method"
                                                                            :key="option.id
                                                                                " :label="option.description
                                                                                            " :value="option.id
                                                                                            "></el-option>
                                                                    </el-select>
                                                                </td>
                                                                <template v-if="
                                                                    enabled_payments
                                                                ">
                                                                    <td>
                                                                        <el-select v-model="row.payment_destination_id
                                                                            " filterable>
                                                                            <el-option
                                                                                v-for="option in payment_destinations"
                                                                                :key="option.id
                                                                                    " :label="option.description
                                                                                                " :value="option.id
                                                                                                "></el-option>
                                                                        </el-select>
                                                                    </td>
                                                                    <td>
                                                                        <el-input v-model="row.reference
                                                                            "></el-input>
                                                                    </td>
                                                                    <td>
                                                                        <el-input v-model="row.payment
                                                                            "></el-input>
                                                                    </td>

                                                                    <td class="text-center">
                                                                        <button
                                                                            class="btn waves-effect waves-light btn-xs btn-danger"
                                                                            type="button" @click.prevent="
                                                                                clickCancel(
                                                                                    index
                                                                                )
                                                                                ">
                                                                            <i class="fa fa-trash"></i>
                                                                        </button>
                                                                    </td>
                                                                </template>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="5">
                                                                    <label class="control-label">
                                                                        <a class="" href="#" @click.prevent="
                                                                            clickAddPayment
                                                                        "><i
                                                                                class="fa fa-plus font-weight-bold text-info"></i>
                                                                            <span>Agregar
                                                                                pago</span></a>
                                                                    </label>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <!-- Fin Pagos -->
                            </div>
                        </div>
                    </div>

                    <div class="form-actions footer-card-default mt-4 px-4 py-3">
                        <el-button class="second-buton btn btn-default second-buton-default"
                            @click.prevent="close()">Cancelar</el-button>

                        <el-popover placement="top-start" width="145" trigger="hover" content="Presiona ALT + G">
                            <el-button slot="reference" class="submit btn btn-primary btn-submit-default" type="primary"
                                native-type="submit" :loading="loading_submit" v-if="form.items.length > 0">
                                Generar <kbd>ALT</kbd>+<kbd>G</kbd>
                            </el-button>
                        </el-popover>
                    </div>
                </form>
            </div>
        </div>

        <sale-notes-form-item :typeUser="typeUser" :showDialog.sync="showDialogAddItem"
            :currency-type-id-active="form.currency_type_id" :exchange-rate-sale="form.exchange_rate_sale"
            :configuration="config" :recordItem="recordItem" :percentage-igv="percentage_igv"
            :currency-types="currency_types" :show-option-change-currency="true"
            :permissionEditItemPrices="authUser.permission_edit_item_prices" ref="form_add_item"
            :selectedOptionPrice="selected_option_price" @add="addRow"></sale-notes-form-item>

        <person-form :showDialog.sync="showDialogNewPerson" type="customers" :external="true"
            :input_person="customerSearchTerm" :document_type_id="form.document_type_id"></person-form>

        <sale-notes-options :showDialog.sync="showDialogOptions" :recordId="saleNotesNewId" :showClose="false"
            :configuration="config"></sale-notes-options>
        <consigned-form :personId="form.customer_id" :showDialog.sync="showDialogConsignedForm">
        </consigned-form>

        <!-- ── Modal Datos de Envío ──────────────────────────────────── -->
        <el-dialog title="Datos de Envío (Courier)" :visible.sync="showShippingDialog"
                   width="600px" :close-on-click-modal="false" append-to-body>

            <!-- Direcciones anteriores del cliente -->
            <div v-if="shippingHistory.length > 0" class="mb-3">
                <div class="text-muted small fw-semibold mb-2">
                    <i class="fas fa-history me-1"></i> Direcciones anteriores — click para usar:
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div v-for="(addr, idx) in shippingHistory" :key="idx"
                         @click="applyShippingAddress(addr)"
                         class="shipping-addr-card"
                         :class="{ 'shipping-addr-card--active': form.shipping_address === addr.shipping_address }">
                        <div class="fw-semibold small">{{ addr.shipping_recipient || '—' }}</div>
                        <div class="text-muted" style="font-size:11px;line-height:1.3">
                            {{ addr.shipping_address }}
                            <span v-if="addr.shipping_city">, {{ addr.shipping_city }}</span>
                        </div>
                        <div v-if="addr.preferred_courier" class="mt-1" style="font-size:10px">
                            <i class="fas fa-truck me-1 text-info"></i>{{ addr.preferred_courier }}
                        </div>
                        <div v-if="addr._label" class="mt-1" style="font-size:10px;color:#aaa">
                            {{ addr._label }}
                        </div>
                    </div>
                    <!-- Tarjeta "Nueva dirección" -->
                    <div @click="clearShippingFields"
                         class="shipping-addr-card shipping-addr-card--new">
                        <i class="fas fa-plus-circle text-primary d-block mb-1" style="font-size:18px"></i>
                        <div class="small text-primary fw-semibold">Nueva dirección</div>
                    </div>
                </div>
                <hr class="my-3">
            </div>

            <!-- Formulario -->
            <div class="row g-3">
                <div class="col-12 col-sm-7">
                    <label class="form-label form-label-sm mb-1 fw-semibold">
                        Destinatario *
                        <span v-if="form.customer_id"
                              @click="resetRecipient"
                              style="cursor:pointer;font-size:11px;"
                              class="text-primary ms-2">
                            ↺ usar nombre del cliente
                        </span>
                    </label>
                    <input type="text" v-model="form.shipping_recipient"
                           class="form-control form-control-sm"
                           placeholder="Nombre de quien recibe">
                </div>
                <div class="col-12 col-sm-5">
                    <label class="form-label form-label-sm mb-1 fw-semibold">Teléfono</label>
                    <input type="text" v-model="form.shipping_phone"
                           class="form-control form-control-sm"
                           placeholder="Cel / fijo">
                </div>
                <div class="col-12 col-sm-8">
                    <label class="form-label form-label-sm mb-1 fw-semibold">Dirección de entrega *</label>
                    <input type="text" v-model="form.shipping_address"
                           class="form-control form-control-sm"
                           placeholder="Calle, número, referencia">
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label form-label-sm mb-1 fw-semibold">Distrito</label>
                    <el-cascader v-model="shippingLocation"
                                 :options="locations"
                                 filterable clearable
                                 :show-all-levels="false"
                                 size="small" style="width:100%"
                                 placeholder="Buscar distrito…"
                                 @change="onShippingLocationChange">
                    </el-cascader>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label form-label-sm mb-1 fw-semibold">
                        Transportista (Courier)
                        <a v-if="!dispatchers.length"
                           href="/dispatches/carriers" target="_blank"
                           class="text-warning ms-1" style="font-size:10px">
                            <i class="fas fa-exclamation-triangle"></i> Registrar transportistas
                        </a>
                    </label>
                    <el-select v-model="form.preferred_carrier_id"
                               size="small" style="width:100%"
                               filterable clearable
                               placeholder="Seleccione transportista…"
                               @change="onCarrierChange">
                        <el-option v-for="d in dispatchers"
                                   :key="d.id"
                                   :value="d.id"
                                   :label="d.name + ' — RUC: ' + d.number">
                        </el-option>
                    </el-select>
                    <small v-if="form.preferred_courier" class="text-muted" style="font-size:10px">
                        {{ form.preferred_courier }}
                    </small>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label form-label-sm mb-1 fw-semibold">Instrucciones especiales</label>
                    <input type="text" v-model="form.shipping_notes"
                           class="form-control form-control-sm"
                           placeholder="Frágil, no doblar, llamar antes…" maxlength="200">
                </div>
            </div>
            <span slot="footer">
                <el-button size="small" @click="showShippingDialog = false">Cancelar</el-button>
                <el-button size="small" type="primary" @click="showShippingDialog = false">
                    <i class="el-icon-check me-1"></i> Listo
                </el-button>
            </span>
        </el-dialog>
        <!-- fin modal envío -->

    </div>
</template>

<style>
.el-upload-list__item {
    max-width: 150px;
}

/* Tarjetas de direcciones anteriores */
.shipping-addr-card {
    border: 1px solid #d0e4f7;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
    background: #f7fbff;
    min-width: 160px;
    max-width: 200px;
    transition: border-color .15s, box-shadow .15s, background .15s;
}
.shipping-addr-card:hover {
    border-color: #409eff;
    box-shadow: 0 2px 8px rgba(64,158,255,.2);
    background: #eaf4ff;
}
.shipping-addr-card--active {
    border-color: #409eff !important;
    background: #d9ecff !important;
    box-shadow: 0 0 0 2px rgba(64,158,255,.35);
}
.shipping-addr-card--new {
    background: #fff;
    border-style: dashed;
    border-color: #b0c4de;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 64px;
}
.shipping-addr-card--new:hover {
    border-color: #409eff;
    background: #f0f8ff;
}

header .head-notes {
    justify-content: space-between;
}

header .head-notes>div {
    flex: 1;
}

.toggle-button {
    position: fixed;
    top: 35%;
    right: -105px;
    transform: translateY(-50%) rotate(-90deg);
    transform-origin: center;
    background-color: rgba(115, 183, 255, 0.6);
    color: white;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 5px;
    z-index: 1;
    transition: all 0.3s ease-in-out;
    font-weight: 400;
    font-size: 16px;
    line-height: 1;
    display: block;
    height: auto;
    border: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}

.toggle-button:hover {
    background-color: rgba(0, 123, 255, 0.8);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.toggle-button.shift {
    right: 400px;
    background-color: rgba(0, 123, 255, 0.8);
    z-index: 1023;
}

.toggle-button.shift:hover {
    box-shadow: none;
}

.additional-information {
    position: fixed;
    top: 0;
    right: -100%;
    height: 100%;
    width: 500px;
    background-color: #f9f9f9;
    box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);
    transition: right 0.3s ease-in-out;
    overflow-y: auto;
    z-index: 1022;
}

.additional-information::-webkit-scrollbar {
    width: 8px;
}

.additional-information::-webkit-scrollbar-thumb {
    background-color: #d3dbf3;
    border-radius: 4px;
}

.additional-information::-webkit-scrollbar-thumb:hover {
    background-color: #cacfe1;
}

.additional-information::-webkit-scrollbar-track {
    background-color: transparent;
}

.additional-information.show {
    right: 0;
}

.content-opacity {
    position: relative;
}

.el-input-number__decrease,
.el-input-number__increase {
    top: 1px;
}

.content-opacity::after {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1021;
}

@media only screen and (max-width: 995px) {
    .head-notes .dates {
        display: flex;
        flex-direction: column;
    }

    .head-notes .dates .issue-date,
    .head-notes .dates .expiration-date {
        width: 90% !important;
    }
}

@media only screen and (max-width: 770px) {
    .payment-container {
        width: 100% !important;
    }
}

@media only screen and (max-width: 767px) {

    .branch-input,
    .change-type {
        width: 60%;
    }

    .money-input,
    .serie-input {
        width: 40%;
    }

    .change-type,
    .serie-input {
        padding-right: 0px;
    }
}

@media only screen and (max-width: 576px) {
    header .head-notes {
        display: flex;
        flex-direction: column;
    }

    .head-notes .dates {
        margin-left: 0px !important;
    }

    .head-notes .dates .issue-date,
    .head-notes .dates .expiration-date {
        width: 100% !important;
    }
}
</style>

<script>
import SaleNotesFormItem from "./partials/item.vue";
import PersonForm from "../persons/form.vue";
import SaleNotesOptions from "./partials/options.vue";
import {
    functions,
    exchangeRate,
    fnItemSearchQuickSale
} from "../../../mixins/functions";
import {
    calculateRowItem,
    sumAmountDiscountsNoBaseByItem,
    showNamePdfOfDescription
} from "../../../helpers/functions";
import Logo from "../companies/logo.vue";
import { mapActions, mapState } from "vuex/dist/vuex.mjs";
import Keypress from "vue-keypress";
import { editableRowItems } from "@mixins/editable-row-items";
import ItemSearchQuickSale from "@components/items/ItemSearchQuickSale.vue";
import PackItemDescription from "@components/items/PackItemDescription.vue";
import ConsignedForm from './partials/consigned.vue';

export default {
    props: ["id", "typeUser", "configuration", "authUser"],
    components: {
        SaleNotesFormItem,
        PersonForm,
        SaleNotesOptions,
        Logo,
        Keypress,
        ItemSearchQuickSale,
        PackItemDescription,
        ConsignedForm
    },
    mixins: [functions, exchangeRate, editableRowItems, fnItemSearchQuickSale],
    computed: {
        canManageDispatch() {
            return this.authUser?.logistic_enabled === true
                && ['admin', 'superadmin', 'warehouse'].includes(this.authUser?.type);
        },
        getCurrentLogo() {
            const isDarkMode = document.documentElement.classList.contains('dark');

            if (isDarkMode && this.company.logo_dark) {
                return `/storage/uploads/logos/${this.company.logo_dark}`;
            }
            if (this.company.logo) {
                return `/storage/uploads/logos/${this.company.logo}`;
            }
            return '';
        },
        credit_payment_method: function () {
            return _.filter(this.payment_method_types, { is_credit: true });
        },
        cash_payment_method: function () {
            return _.filter(this.payment_method_types, { is_credit: false });
        },
        ...mapState(["config"]),
        sms_periodo: function () {
            let text = "";
            let type = this.form.type_period;
            let time = this.form.quantity_period;
            if (time > 0 && (type === "year" || type === "month")) {
                text = "Se duplicará cada " + time;
                if (type === "year") {
                    text = time > 1 ? text + " años" : text + " año";
                } else if (type === "month") {
                    text = time > 1 ? text + " meses" : text + " mes";
                } else {
                    text = "";
                }
                return text;
            }
            return text;
        },
        showLoadVoucher() {
            return (
                this.configuration.show_load_voucher && !this.isUpdateDocument
            );
        },
        isUpdateDocument() {
            return !_.isEmpty(this.id);
        },
        isGlobalDiscountBase() {
            return this.config.global_discount_type_id === "02";
        },
        canAddDescriptionToDocumentItem() {
            if (this.configuration)
                return this.configuration.add_description_to_document_item;

            return false;
        }
    },
    watch: {
        'form.customer_id': 'checkCustomerExpiredDebt',
        'form.payment_condition_id': 'checkCustomerExpiredDebt',
        showDialogNewPerson(newVal) {
            if (!newVal) {
                this.customerSearchTerm = ''
            }
        }
    },
    data() {
        return {
            input_person: {},
            pickerOptions: {
                disabledDate: date => {
                    let now = new Date();
                    return date.getTime() < now.getTime();
                }
            },
            multiple: [
                {
                    keyCode: 78, // N
                    modifiers: ["altKey"],
                    preventDefault: true
                },
                {
                    keyCode: 71, // g
                    modifiers: ["altKey"],
                    preventDefault: true
                }
            ],
            isVisible: false,
            focus_on_client: false,
            sellers: [],
            courier_list: [],
            locations: [],
            dispatchers: [],
            shippingLocation: [],
            shippingHistory: [],
            showShippingDialog: false,
            resource: "sale-notes",
            showDialogConsignedForm: false,
            showDialogAddItem: false,
            showDialogNewPerson: false,
            showDialogOptions: false,
            loading_submit: false,
            loading_form: false,
            errors: {},
            form: {},
            currency_types: [],
            discount_types: [],
            charges_types: [],
            all_customers: [],
            customers: [],
            company: null,
            establishments: [],
            establishment: null,
            currency_type: {},
            saleNotesNewId: null,
            form_payment: {},
            payment_method_types: [],
            activePanel: 0,
            loading_search: false,
            type_periods: [],
            series: [],
            all_series: [],
            is_contingency: false,
            enabled_payments: true,
            payment_destinations: [],
            total_discount_no_base: 0,
            total_global_charge: 0,
            global_charge_types: [],
            is_receivable: false,
            recordItem: null,
            headers_token: headers_token,
            global_discount_types: [],
            global_discount_type: {},
            is_amount: true,
            total_global_discount: 0,
            selected_option_price: null,
            showPayments: false,
            price_options: [
                {
                    id: 1,
                    description: "Precio principal"
                },
                {
                    id: "price1",
                    description: "Precio 1"
                },
                {
                    id: "price2",
                    description: "Precio 2"
                },
                {
                    id: "price3",
                    description: "Precio 3"
                }
            ],
            recordDiscountsGlobal: null,
            customer_expired_days: 0,
            customer_has_expired: false,
            consigneds: [],
            consigned_addresses: [],
            customerSearchTerm: ''
        };
    },
    async created() {
        this.selected_option_price = this.price_options[0].id;
        this.loadConfiguration();
        this.$store.commit("setConfiguration", this.configuration);

        // Actualizar price_options con los labels personalizados
        if (this.config) {
            this.price_options[1].description = this.config.price1_label || 'Precio 1';
            this.price_options[2].description = this.config.price2_label || 'Precio 2';
            this.price_options[3].description = this.config.price3_label || 'Precio 3';
        }

        await this.initForm();

        // Cargar lista de couriers para el datalist
        this.$http.get('/logistic/courier-companies/list')
            .then(r => { this.courier_list = r.data || []; })
            .catch(() => {});

        await this.$http.get(`/${this.resource}/tables`).then(response => {
            this.currency_types = response.data.currency_types;
            this.establishments = response.data.establishments;
            this.all_customers = response.data.customers;
            this.discount_types = response.data.discount_types;
            this.charges_types = response.data.charges_types;
            this.global_charge_types = response.data.global_charge_types;

            this.payment_method_types = response.data.payment_method_types;
            this.company = response.data.company;
            if (this.config.currency_type_id === undefined) {
                this.form.currency_type_id =
                    this.currency_types.length > 0
                        ? this.currency_types[0].id
                        : null;
            }
            this.form.establishment_id =
                this.establishments.length > 0
                    ? this.establishments[0].id
                    : null;
            this.type_periods = [
                { id: "month", description: "Mensual" },
                { id: "year", description: "Anual" }
            ];
            this.all_series = response.data.series;
            this.payment_destinations = response.data.payment_destinations;
            this.sellers = response.data.sellers;
            this.global_discount_types = response.data.global_discount_types;
            this.locations   = response.data.locations   || [];
            this.dispatchers = response.data.dispatchers || [];

            this.changeEstablishment();
            this.changeDateOfIssue();
            this.changeCurrencyType();
            this.allCustomers();
            this.selectDestinationSale();
            this.setDefaultSerieByDocument();
            this.setConfigGlobalDiscountType();
        });
        await this.getPercentageIgv();
        this.loading_form = true;
        this.$eventHub.$on("reloadDataPersons", customer_id => {
            this.reloadDataCustomers(customer_id);
            this.customerSearchTerm = ''
        });
        this.$eventHub.$on("initInputPerson", () => {
            this.initInputPerson();
        });
        this.$eventHub.$on("reloadDataConsigned", () => {
            this.getConsigneds();
        });
        this.isUpdate();
        this.changeCurrencyType();
    },
    methods: {
        changePaymentCondition() {
            this.form.fee = [];
            this.form.payments = [];
            if (this.form.payment_condition_id === "01") {
                this.clickAddPayment();
                this.initDataPaymentCondition01();
            }
            if (this.form.payment_condition_id === "02") {
                this.clickAddFeeNew();
            }
            if (this.form.payment_condition_id === "03") {
                this.clickAddFee();
            }
        },
        initDataPaymentCondition01() {
            this.enabled_payments = true;
            this.form.due_date = this.form.date_of_issue;
            this.form.payment_method_type_id = null;
        },
        clickAddFeeNew() {
            let first = {
                id: "05",
                number_days: 0
            };
            if (this.credit_payment_method[0] !== undefined) {
                first = this.credit_payment_method[0];
            }

            let date = moment(this.form.date_of_issue)
                .add(first.number_days, "days")
                .format("YYYY-MM-DD");

            this.form.due_date = date;
            this.form.fee.push({
                id: null,
                document_id: null,
                payment_method_type_id: first.id,
                date: date,
                currency_type_id: this.form.currency_type_id,
                amount: 0
            });
            this.calculateFee();
        },
        changeCreditFeeDate(index) {
            const last_index = this.getLastIndexFee();

            if (last_index === index) {
                this.setDateOfDue(this.getLastDateFee(last_index));
            }
        },
        getLastDateFee(input_last_index = null) {
            const last_index = input_last_index || this.getLastIndexFee();
            return this.form.fee[last_index].date;
        },
        clickRemoveFee(index) {
            this.form.fee.splice(index, 1);
            this.calculateFee();
            this.setDateOfDue(this.getLastDateFee());
        },
        setDateOfDue(date_of_due) {
            this.form.due_date = date_of_due;
        },
        getLastIndexFee() {
            return this.form.fee.length - 1;
        },
        toggleInformation() {
            this.isVisible = !this.isVisible;
        },
        handleEnterKey(event) {
            event.preventDefault();
            event.target.blur();
        },
        valueInputSelect(event) {
            event.target.select();
        },
        clickShowItemDetail(id) {
            window.open(`/items/show-item-detail/${id}`);
        },
        setDescriptionOfItem(item) {
            return showNamePdfOfDescription(item, this.config.show_pdf_name);
        },
        onSuccessUploadVoucher(response, file, fileList, index) {
            if (response.success) {
                this.form.payments[index].filename = response.data.filename;
                this.form.payments[index].temp_path = response.data.temp_path;
                this.form.payments[index].file_list = fileList;
            } else {
                this.cleanFileListUploadVoucher(index);
                this.$message.error(response.message);
            }
        },
        cleanFileListUploadVoucher(index) {
            this.form.payments[index].file_list = [];
        },
        handleRemoveUploadVoucher(file, fileList, index) {
            this.form.payments[index].filename = null;
            this.form.payments[index].temp_path = null;
            this.cleanFileListUploadVoucher(index);
        },
        disabledSeries() {
            return (
                this.configuration.restrict_series_selection_seller &&
                this.typeUser !== "admin"
            );
        },
        setDefaultSerieByDocument() {
            if (this.authUser.multiple_default_document_types) {
                const default_document_type_serie = _.find(
                    this.authUser.default_document_types,
                    { document_type_id: "80" }
                );

                if (default_document_type_serie) {
                    const exist_serie = _.find(this.series, {
                        id: default_document_type_serie.series_id
                    });
                    if (exist_serie)
                        this.form.series_id =
                            default_document_type_serie.series_id;
                }
            }
        },
        ...mapActions(["loadConfiguration"]),
        clickEdiItem(row, index) {
            row.aux_index = index;
            this.recordItem = row;
            this.showDialogAddItem = true;
        },
        clickAddItem() {
            this.recordItem = null;
            this.showDialogAddItem = true;
        },
        changePaymentMethodType(index) {
            let id = "01";
            if (
                this.form.payments[index] !== undefined &&
                this.form.payments[index].payment_method_type_id !== undefined
            ) {
                id = this.form.payments[index].payment_method_type_id;
            } else if (
                this.form.fee[index] !== undefined &&
                this.form.fee[index].payment_method_type_id !== undefined
            ) {
                id = this.form.fee[index].payment_method_type_id;
            }
            let payment_method_type = _.find(this.payment_method_types, {
                id: id
            });

            if (payment_method_type.number_days) {
                this.form.due_date = moment(this.form.date_of_issue)
                    .add(payment_method_type.number_days, "days")
                    .format("YYYY-MM-DD");
                this.enabled_payments = false;
                this.form.payment_method_type_id = payment_method_type.id;

                let date = moment(this.form.date_of_issue)
                    .add(payment_method_type.number_days, "days")
                    .format("YYYY-MM-DD");

                if (this.form.fee !== undefined) {
                    for (let index = 0; index < this.form.fee.length; index++) {
                        this.form.fee[index].date = date;
                    }
                }
            } else if (
                payment_method_type.id == "09" ||
                payment_method_type.is_credit
            ) {
                this.form.payment_method_type_id = payment_method_type.id;
                this.form.due_date = this.form.date_of_issue;
                this.enabled_payments = false;
            } else {
                this.form.due_date = this.form.date_of_issue;
                this.form.payment_method_type_id = null;
                this.enabled_payments = true;
            }
        },
        selectDestinationSale() {
            if (
                this.config.destination_sale &&
                this.payment_destinations.length > 0
            ) {
                let cash = _.find(this.payment_destinations, { id: "cash" });
                this.form.payments[0].payment_destination_id = cash
                    ? cash.id
                    : this.payment_destinations[0].id;
            }
        },
        getPaymentDestinationId() {
            if (
                this.config.destination_sale &&
                this.payment_destinations.length > 0
            ) {
                let cash = _.find(this.payment_destinations, { id: "cash" });

                return cash ? cash.id : this.payment_destinations[0].id;
            }

            return null;
        },
        setTotalDefaultPayment() {
            if (this.form.payments.length > 0) {
                this.form.payments[0].payment = this.form.total;
            }
        },
        filterSeries() {
            this.form.series_id = null;
            this.series = _.filter(this.all_series, {
                establishment_id: this.form.establishment_id,
                document_type_id: "80",
                contingency: this.is_contingency
            });
            this.form.series_id =
                this.series.length > 0 ? this.series[0].id : null;
        },
        getFormatUnitPriceRow(unit_price) {
            return _.round(unit_price, 6);
        },
        async isUpdate() {
            if (this.id) {
                await this.$http
                    .get(`/${this.resource}/record2/${this.id}`)
                    .then(response => {
                        this.form = response.data.data;
                        this.setDataUpdate();
                        this.changeCurrencyType();
                    });
            }
        },
        setDataUpdate() {
            if (this.form.total_charge > 0)
                this.total_global_charge = this.form.total_charge;

            this.form.charges = this.form.charges
                ? Object.values(this.form.charges)
                : [];

            this.form.discounts = this.getDataGlobalDiscount();
        },
        getDataGlobalDiscount() {
            const discounts = this.form.discounts
                ? Object.values(this.form.discounts)
                : [];

            if (discounts.length === 1) {
                if (
                    discounts[0].is_amount !== undefined &&
                    discounts[0].is_amount !== null
                )
                    this.is_amount = discounts[0].is_amount;
                this.total_global_discount = this.is_amount
                    ? discounts[0].amount
                    : discounts[0].factor * 100;
            }

            return discounts;
        },
        clickAddPayment() {
            let id = "01";
            if (
                this.cash_payment_method !== undefined &&
                this.cash_payment_method[0] !== undefined
            ) {
                id = this.cash_payment_method[0].id;
            }
            let total = 0;
            if (this.form.total !== undefined) {
                total = this.form.total;
            }
            this.form.due_date = moment().format("YYYY-MM-DD");

            const cashOption = _.find(this.payment_destinations, { id: 'cash' });
            const destination_id = cashOption ? 'cash' : (this.payment_destinations.length > 0 ? this.payment_destinations[0].id : null);

            this.form.payments.push({
                id: null,
                document_id: null,
                date_of_payment: moment().format("YYYY-MM-DD"),
                payment_method_type_id: id,
                reference: null,
                payment_destination_id: destination_id,
                payment: total,

                payment_received: true,
                filename: null,
                temp_path: null,
                file_list: []
            });

            this.calculatePayments();
        },
        clickAddFee() {

            this.form.due_date = moment().format("YYYY-MM-DD");
            this.form.fee.push({
                id: null,
                date: moment().format("YYYY-MM-DD"),
                currency_type_id: this.form.currency_type_id,
                amount: 0
            });
            this.calculateFee();
        },
        calculateFee() {
            let fee_count = this.form.fee.length;
            let total = this.form.total;

            let accumulated = 0;
            let amount = _.round(total / fee_count, 2);
            _.forEach(this.form.fee, row => {
                accumulated += amount;
                if (total - accumulated < 0) {
                    amount = _.round(total - accumulated + amount, 2);
                }
                row.amount = amount;
            });
        },
        calculatePayments() {
            let payment_count = this.form.payments.length;
            let total = this.form.total;

            let payment = 0;
            let amount = _.round(total / payment_count, 2);
            _.forEach(this.form.payments, row => {
                payment += amount;
                if (total - payment < 0) {
                    amount = _.round(total - payment + amount, 2);
                }
                row.payment = amount;
            });
        },
        clickCancel(index) {
            this.form.payments.splice(index, 1);
        },
        changeCustomer() {
            this.checkCustomerExpiredDebt();
            let customer = _.find(this.customers, {
                id: this.form.customer_id
            });
            if (customer) {
                let seller = this.sellers.find(
                    element => element.id == customer.seller_id
                );
                if (seller !== undefined) {
                    this.form.seller_id = seller.id;
                }
            }
            this.getConsigneds();

            // Cargar último envío del cliente para pre-llenar datos de envío
            if (this.form.customer_id && this.form.delivery_type === 'province') {
                this.loadLastShipping(this.form.customer_id, customer);
            } else if (customer) {
                // Si aún no es courier, pre-llenar solo el destinatario
                if (!this.form.shipping_recipient) {
                    this.form.shipping_recipient = customer.name || customer.description || '';
                }
                if (!this.form.shipping_phone && customer.telephone) {
                    this.form.shipping_phone = customer.telephone;
                }
            }
        },
        loadLastShipping(customerId, customer) {
            this.$http.get(`/${this.resource}/search/customer/${customerId}`)
                .then(response => {
                    const history = response.data.shipping_history || [];
                    // El API devuelve customers[] con datos completos del cliente
                    const apiCustomer = (response.data.customers || [])[0] || customer || {};

                    const combined = [...history];

                    // Agregar dirección registrada del cliente si tiene y no está ya en el historial
                    if (apiCustomer.address) {
                        const alreadyHas = history.some(h =>
                            h.shipping_address &&
                            h.shipping_address.toLowerCase().trim() === apiCustomer.address.toLowerCase().trim()
                        );
                        if (!alreadyHas) {
                            combined.push({
                                shipping_recipient:   apiCustomer.name || '',
                                shipping_phone:       apiCustomer.telephone || '',
                                shipping_address:     apiCustomer.address,
                                shipping_city:        apiCustomer.district ? apiCustomer.district.description : '',
                                shipping_district_id: apiCustomer.district_id || null,
                                preferred_courier:    '',
                                shipping_notes:       '',
                                _label: 'Dirección registrada',
                            });
                        }
                    }

                    this.shippingHistory = combined;

                    if (history.length > 0) {
                        // Hay historial de envíos → pre-llenar con el más reciente
                        this.applyShippingAddress(history[0]);
                    } else if (apiCustomer.address) {
                        // Sin historial, pero tiene dirección registrada → usarla
                        this.applyShippingAddress({
                            shipping_recipient:   apiCustomer.name || '',
                            shipping_phone:       apiCustomer.telephone || '',
                            shipping_address:     apiCustomer.address,
                            shipping_city:        apiCustomer.district ? apiCustomer.district.description : '',
                            shipping_district_id: apiCustomer.district_id || null,
                            preferred_courier:    '',
                            shipping_notes:       '',
                        });
                    } else {
                        // Sin nada — solo nombre y teléfono
                        this.form.shipping_recipient = apiCustomer.name || customer?.name || '';
                        this.form.shipping_phone     = apiCustomer.telephone || customer?.telephone || '';
                    }
                })
                .catch(() => {
                    this.shippingHistory = [];
                    if (customer) this.form.shipping_recipient = customer.name || customer.description || '';
                });
        },
        onCarrierChange(carrierId) {
            // Guarda también el nombre del transportista en preferred_courier para display/historial
            const found = this.dispatchers.find(d => d.id === carrierId);
            this.form.preferred_courier = found ? found.name : '';
        },
        calcShippingCost() {
            const pkgs  = parseFloat(this.form.shipping_packages)      || 0;
            const price = parseFloat(this.form.shipping_price_package) || 0;
            this.form.shipping_cost = parseFloat((pkgs * price).toFixed(2));
        },
        applyShippingAddress(addr) {
            this.form.shipping_recipient   = addr.shipping_recipient   || '';
            this.form.shipping_phone       = addr.shipping_phone       || '';
            this.form.shipping_address     = addr.shipping_address     || '';
            this.form.shipping_city        = addr.shipping_city        || '';
            this.form.shipping_district_id = addr.shipping_district_id || '';
            this.form.preferred_courier    = addr.preferred_courier    || '';
            this.form.preferred_carrier_id = addr.preferred_carrier_id || null;
            this.form.shipping_notes       = addr.shipping_notes       || '';
            const distId = addr.shipping_district_id;
            if (distId && distId.length >= 6) {
                this.shippingLocation = [
                    distId.substring(0, 2),
                    distId.substring(0, 4),
                    distId
                ];
            } else {
                this.shippingLocation = [];
            }
        },
        clearShippingFields() {
            this.form.shipping_recipient   = '';
            this.form.shipping_phone       = '';
            this.form.shipping_address     = '';
            this.form.shipping_city        = '';
            this.form.shipping_district_id = '';
            this.form.preferred_courier    = '';
            this.form.preferred_carrier_id = null;
            this.form.shipping_notes       = '';
            this.shippingLocation          = [];
        },
        onSelectCourierDelivery() {
            // Abrir modal automáticamente si no hay datos aún
            this.$nextTick(() => {
                if (!this.form.shipping_address) {
                    this.showShippingDialog = true;
                }
            });
        },
        onShippingLocationChange(val) {
            if (!val || val.length === 0) {
                this.form.shipping_district_id = null;
                this.form.shipping_city = null;
                return;
            }
            // val = [dept_id, prov_id, district_id]
            const districtId = val[val.length - 1];
            this.form.shipping_district_id = districtId;
            // Buscar el label del distrito en el árbol de locations para guardar texto legible
            const dept = this.locations.find(d => d.value === val[0]);
            if (dept) {
                const prov = (dept.children || []).find(p => p.value === val[1]);
                if (prov) {
                    const dist = (prov.children || []).find(d => d.value === districtId);
                    if (dist) {
                        // Guardar solo el nombre del distrito (sin código)
                        const label = dist.label.replace(/^\d+\s*-\s*/, '').trim();
                        this.form.shipping_city = label;
                    }
                }
            }
        },
        searchRemoteCustomers(input) {
            this.customerSearchTerm = input;

            if (input.length > 0) {
                this.loading_search = true;
                let parameters = `input=${input}`;

                this.$http
                    .get(`/${this.resource}/search/customers?${parameters}`)
                    .then(response => {
                        this.customers = response.data.customers;
                        this.loading_search = false;
                        this.input_person.number =
                            this.customers.length == 0 ? input : null;
                    });
            } else {
                this.allCustomers();
                this.input_person.number = null;
            }
        },
        initForm() {
            this.errors = {};
            this.form = {
                id: null,
                series_id: null,
                prefix: "NV",
                establishment_id: null,
                due_date: moment().format("YYYY-MM-DD"),
                date_of_issue: moment().format("YYYY-MM-DD"),
                time_of_issue: moment().format("HH:mm:ss"),
                customer_id: null,
                currency_type_id: this.config.currency_type_id,
                purchase_order: null,
                exchange_rate_sale: 0,
                total_prepayment: 0,
                total_charge: 0,
                total_discount: 0,
                total_exportation: 0,
                total_free: 0,
                total_taxed: 0,
                total_unaffected: 0,
                total_exonerated: 0,
                total_igv: 0,
                total_base_isc: 0,
                total_isc: 0,
                total_base_other_taxes: 0,
                total_other_taxes: 0,
                total_taxes: 0,
                total_value: 0,
                subtotal: 0,
                total_igv_free: 0,
                total: 0,
                operation_type_id: null,
                items: [],
                charges: [],
                discounts: [],
                attributes: [],
                guides: [],
                payments: [],
                additional_information: null,
                actions: {
                    format_pdf: "a4"
                },
                apply_concurrency: false,
                type_period: null,
                quantity_period: 0,
                automatic_date_of_issue: null,
                enabled_concurrency: false,
                license_plate: null,
                payment_method_type_id: null,
                paid: false,
                fee: [],
                observation: null,
                terms_condition: null,
                consigned_id: null,
                consigned_address_id: null,
                consigned_address: null,
                consigned_ubigeo: null,
                // ✅ CAMBIO: payment_condition_id por defecto en "01" (Contado)
                payment_condition_id: "01",
                // ── Tipo de entrega ──────────────────────────────────────────
                delivery_type: 'store',   // 'store' | 'pickup' | 'province'
                is_urgent: false,
                requires_warehouse_dispatch: false, // retrocompatibilidad
                // ── Datos de envío (solo courier/province) ───────────────────
                shipping_recipient: null,
                shipping_phone:     null,
                shipping_address:   null,
                shipping_city:        null,
                shipping_district_id: null,
                preferred_courier:    null,
                preferred_carrier_id: null,
                shipping_notes:       null,
                // ── Costo de envío (no facturado) ─────────────────────────────
                shipping_packages:       1,
                shipping_cost_customer:  0,
                shipping_cost_paid:      false,
            };

            this.total_discount_no_base = 0;

            this.clickAddPayment();
            this.enabled_payments = true;
            this.total_global_charge = 0;
            this.initInputPerson();

            this.total_global_discount = 0;
            this.is_amount = true;
        },
        resetForm() {
            this.activePanel = 0;
            this.initForm();
            if (this.config.currency_type_id === undefined) {
                this.form.currency_type_id =
                    this.currency_types.length > 0
                        ? this.currency_types[0].id
                        : null;
            }
            this.form.establishment_id =
                this.establishments.length > 0
                    ? this.establishments[0].id
                    : null;
            this.changeEstablishment();
            this.changeDateOfIssue();
            this.changeCurrencyType();
            this.allCustomers();
            this.setDefaultSerieByDocument();
        },
        changeEstablishment() {
            this.establishment = _.find(this.establishments, {
                id: this.form.establishment_id
            });
            this.filterSeries();
            this.selectDefaultCustomer();
        },
        cleanCustomer() {
            this.form.customer_id = null;
        },
        resetRecipient() {
            let customer = _.find(this.customers, { id: this.form.customer_id });
            if (customer) {
                this.form.shipping_recipient = customer.name || customer.description || '';
                this.form.shipping_phone     = customer.telephone || this.form.shipping_phone;
            }
        },
        async changeDateOfIssue() {
            await this.searchExchangeRateByDate(this.form.date_of_issue).then(
                response => {
                    this.form.exchange_rate_sale = response;
                }
            );
            await this.getPercentageIgv();
            this.changeCurrencyType();
        },
        assignmentDateOfPayment() {
            this.form.payments.forEach(payment => {
                payment.date_of_payment = this.form.date_of_issue;
            });
        },
        allCustomers() {
            this.customers = this.all_customers;
        },
        addRow(row) {
            if (this.recordItem) {
                this.form.items[this.recordItem.aux_index] = row;
                this.recordItem = null;
            } else {
                this.form.items.push(JSON.parse(JSON.stringify(row)));
            }

            this.calculateTotal();
        },
        clickRemoveItem(index) {
            this.form.items.splice(index, 1);
            this.calculateTotal();
        },
        changeCurrencyType() {
            this.currency_type = _.find(this.currency_types, {
                id: this.form.currency_type_id
            });
            let items = [];
            this.form.items.forEach(row => {
                items.push(
                    calculateRowItem(
                        row,
                        this.form.currency_type_id,
                        this.form.exchange_rate_sale,
                        this.percentage_igv
                    )
                );
            });
            this.form.items = items;
            this.calculateTotal();
        },
        calculateTotal() {
            let total_discount = 0;
            let total_charge = 0;
            let total_exportation = 0;
            let total_taxed = 0;
            let total_exonerated = 0;
            let total_unaffected = 0;
            let total_free = 0;
            let total_igv = 0;
            let total_value = 0;
            let total = 0;
            this.total_discount_no_base = 0;

            let total_igv_free = 0;

            this.form.items.forEach(row => {
                total_discount += parseFloat(row.total_discount);
                total_charge += parseFloat(row.total_charge);

                if (row.affectation_igv_type_id === "10") {
                    total_taxed += parseFloat(row.total_value);
                }
                if (
                    row.affectation_igv_type_id === "20" ||
                    row.affectation_igv_type_id === "21"
                ) {
                    total_exonerated += parseFloat(row.total_value);
                }
                if (
                    row.affectation_igv_type_id === "30" ||
                    row.affectation_igv_type_id === "31" ||
                    row.affectation_igv_type_id === "32" ||
                    row.affectation_igv_type_id === "33" ||
                    row.affectation_igv_type_id === "34" ||
                    row.affectation_igv_type_id === "35" ||
                    row.affectation_igv_type_id === "36" ||
                    row.affectation_igv_type_id === "37"
                ) {
                    total_unaffected += parseFloat(row.total_value);
                }
                if (row.affectation_igv_type_id === "40") {
                    total_exportation += parseFloat(row.total_value);
                }
                if (
                    [
                        "10",
                        "20",
                        "21",
                        "30",
                        "31",
                        "32",
                        "33",
                        "34",
                        "35",
                        "36",
                        "40"
                    ].indexOf(row.affectation_igv_type_id) < 0
                ) {
                    total_free += parseFloat(row.total_value);
                }
                if (
                    [
                        "10",
                        "20",
                        "21",
                        "30",
                        "31",
                        "32",
                        "33",
                        "34",
                        "35",
                        "36",
                        "40"
                    ].indexOf(row.affectation_igv_type_id) > -1
                ) {
                    total_igv += parseFloat(row.total_igv);
                    total += parseFloat(row.total);
                }

                if (
                    ["11", "12", "13", "14", "15", "16"].includes(
                        row.affectation_igv_type_id
                    )
                ) {
                    let total_value_partial = row.total_value;
                    row.total_taxes = 0;

                    row.total_igv =
                        total_value_partial * (row.percentage_igv / 100);
                    row.total_base_igv = total_value_partial;
                    total_value -= row.total_value;

                    total_igv_free += row.total_igv;
                }

                total_value += parseFloat(row.total_value);

                this.total_discount_no_base += sumAmountDiscountsNoBaseByItem(
                    row
                );
            });

            this.form.total_igv_free = _.round(total_igv_free, 2);

            this.form.total_discount = _.round(total_discount, 2);
            this.form.total_exportation = _.round(total_exportation, 2);
            this.form.total_taxed = _.round(total_taxed, 2);
            this.form.total_exonerated = _.round(total_exonerated, 2);
            this.form.total_unaffected = _.round(total_unaffected, 2);
            this.form.total_free = _.round(total_free, 2);
            this.form.total_igv = _.round(total_igv, 2);
            this.form.total_value = _.round(total_value, 2);
            this.form.total_taxes = _.round(total_igv, 2);
            this.form_payment.payment = this.form.total;

            this.form.subtotal = _.round(total, 2);
            this.form.total = _.round(total - this.total_discount_no_base, 2);

            this.showPayments = this.form.items.length > 0;

            this.chargeGlobal();

            this.discountGlobal();

            this.setTotalDefaultPayment();
        },
        deleteDiscountGlobal() {
            let discount = _.find(this.form.discounts, {
                discount_type_id: this.config.global_discount_type_id
            });
            let index = this.form.discounts.indexOf(discount);

            if (index > -1) {
                this.form.discounts.splice(index, 1);
                this.form.total_discount = 0;
            }
        },
        discountGlobal() {
            this.deleteDiscountGlobal();

            let input_global_discount = parseFloat(this.total_global_discount);

            if (input_global_discount > 0) {
                const percentage_igv = this.percentage_igv * 100;
                let base = this.isGlobalDiscountBase
                    ? parseFloat(this.form.total_taxed)
                    : parseFloat(this.form.total);
                let amount = 0;
                let factor = 0;

                if (this.is_amount) {
                    amount = input_global_discount;
                    factor = _.round(amount / base, 5);
                } else {
                    factor = _.round(input_global_discount / 100, 5);
                    amount = factor * base;
                }

                this.form.total_discount = _.round(amount, 2);

                if (this.isGlobalDiscountBase) {
                    this.form.total_taxed = _.round(
                        base - this.form.total_discount,
                        2
                    );
                    this.form.total_value = this.form.total_taxed;
                    this.form.total_igv = _.round(
                        this.form.total_taxed * (percentage_igv / 100),
                        2
                    );

                    let total_plastic_bag_taxes = this.form
                        .total_plastic_bag_taxes
                        ? this.form.total_plastic_bag_taxes
                        : 0;

                    this.form.total_taxes = _.round(
                        this.form.total_igv +
                        this.form.total_isc +
                        total_plastic_bag_taxes,
                        2
                    );
                    this.form.total = _.round(
                        this.form.total_taxed + this.form.total_taxes,
                        2
                    );
                    this.form.subtotal = this.form.total;

                    if (this.form.total <= 0 && this.total_global_discount > 0)
                        this.$message.error(
                            "El total debe ser mayor a 0, verifique el tipo de descuento asignado (Configuración/Avanzado/Contable)"
                        );
                }
                else {
                    this.form.total = _.round(this.form.total - amount, 2);
                }

                this.setGlobalDiscount(factor, _.round(amount, 2), base);
            }
        },
        changeTypeDiscount() {
            this.calculateTotal();
        },
        changeTotalGlobalDiscount() {
            this.calculateTotal();
        },
        setConfigGlobalDiscountType() {
            this.global_discount_type = _.find(this.global_discount_types, {
                id: this.config.global_discount_type_id
            });
        },
        setGlobalDiscount(factor, amount, base) {
            this.form.discounts.push({
                discount_type_id: this.global_discount_type.id,
                description: this.global_discount_type.description,
                factor: factor,
                amount: amount,
                base: base,
                is_amount: this.is_amount
            });
        },
        getGlobalCharge(id) {
            return _.find(this.global_charge_types, { id: id });
        },
        chargeGlobal() {
            let base = parseFloat(this.form.total);

            if (this.config.active_allowance_charge) {
                let percentage_allowance_charge = parseFloat(
                    this.config.percentage_allowance_charge
                );
                this.total_global_charge = _.round(
                    base * (percentage_allowance_charge / 100),
                    2
                );
            }

            if (this.total_global_charge == 0) {
                this.deleteChargeGlobal();
                return;
            }

            let amount = parseFloat(this.total_global_charge);
            let factor = _.round(amount / base, 5);
            let charge = _.find(this.form.charges, { charge_type_id: "50" });

            if (amount > 0 && !charge) {
                this.form.total_charge = _.round(amount, 2);
                this.form.total = _.round(
                    this.form.total + this.form.total_charge,
                    2
                );
                const global_charge = this.getGlobalCharge("50");

                this.form.charges.push({
                    charge_type_id: global_charge.id,
                    description: global_charge.description,
                    factor: factor,
                    amount: amount,
                    base: base
                });
            } else {
                let pos = this.form.charges.indexOf(charge);

                if (pos > -1) {
                    this.form.total_charge = _.round(amount, 2);
                    this.form.total = _.round(
                        this.form.total + this.form.total_charge,
                        2
                    );

                    this.form.charges[pos].base = base;
                    this.form.charges[pos].amount = amount;
                    this.form.charges[pos].factor = factor;
                }
            }
        },
        deleteChargeGlobal() {
            let charge = _.find(this.form.charges, { charge_type_id: "50" });
            let index = this.form.charges.indexOf(charge);

            if (index > -1) {
                this.form.charges.splice(index, 1);
                this.form.total_charge = 0;
            }
        },
        async saveCashDocument(sale_note_id) {
            if (!this.id) {
                await this.$http
                    .post(`/cash/cash_document`, {
                        document_id: null,
                        sale_note_id: sale_note_id
                    })
                    .then(response => {
                        if (response.data.success) {
                            // success
                        } else {
                            this.$message.error(response.data.message);
                        }
                    })
                    .catch(error => {
                        console.log(error);
                    });
            }
        },
        validatePaymentDestination() {
            let error_by_item = 0;

            this.form.payments.forEach(item => {
                if (!["05", "08", "09"].includes(item.payment_method_type_id)) {
                    if (item.payment_destination_id == null) error_by_item++;
                }
            });

            return {
                error_by_item: error_by_item
            };
        },
        async submit() {
            this.errors = {};
            if (this.config.affect_all_documents) {
                this.form.terms_condition = this.config.terms_condition_sale;
            }

            let validate = await this.validate_payments();
            if (
                validate.acum_total > parseFloat(this.form.total) ||
                validate.error_by_item > 0
            ) {
                return this.$message.error(
                    "Los montos ingresados superan al monto a pagar o son incorrectos"
                );
            }

            if (this.form.type_period) {
                if (this.form.quantity_period == 0) {
                    return this.$message.error(
                        "La cantidad de periodos debe ser mayor a 0"
                    );
                }

                this.form.enabled_concurrency =
                    this.form.quantity_period > 0 ? true : false;
            }

            if (validate.acum_total == parseFloat(this.form.total)) {
                this.form.paid = true;
            }

            let validate_payment_destination = await this.validatePaymentDestination();

            if (validate_payment_destination.error_by_item > 0) {
                return this.$message.error(
                    "El destino del pago es obligatorio"
                );
            }

            // Condicion de pago Credito con cuota pasa a credito
            let original_payment_condition_id = this.form.payment_condition_id;
            if (this.form.payment_condition_id === "03")
                this.form.payment_condition_id = "02";

            if (!this.enabled_payments) {
                this.form.payments = [];
            }
            this.loading_submit = true;
            this.$http
                .post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.form_payment.sale_note_id = response.data.data.id;
                        this.$eventHub.$emit("reloadDataItems", null);
                        this.resetForm();
                        this.saleNotesNewId = response.data.data.id;
                        this.showDialogOptions = true;
                        this.saveCashDocument(response.data.data.id);
                    } else {
                        this.form.payment_condition_id = original_payment_condition_id;
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    this.form.payment_condition_id = original_payment_condition_id;
                    if (error.response.status === 422) {
                        this.errors = error.response.data;
                        const firstError = Object.values(error.response.data)[0];
                        if (firstError) this.$message.error(Array.isArray(firstError) ? firstError[0] : firstError);
                    } else {
                        this.$message.error(error.response.data.message);
                    }
                })
                .then(() => {
                    this.form.currency_type_id = this.config.currency_type_id;
                    this.loading_submit = false;
                });
        },
        validate_payments() {
            for (let index = this.form.payments.length - 1; index >= 0; index--) {
                if (parseFloat(this.form.payments[index].payment) === 0)
                    this.form.payments.splice(index, 1);
            }

            let error_by_item = 0;
            let acum_total = 0;

            this.form.payments.forEach(item => {
                acum_total += parseFloat(item.payment);
                if (item.payment <= 0 || item.payment == null) error_by_item++;
            });

            return {
                error_by_item: error_by_item,
                acum_total: acum_total
            };
        },
        close() {
            location.href = "/sale-notes";
        },
        reloadDataCustomers(customer_id) {
            this.$http
                .get(`/${this.resource}/search/customer/${customer_id}`)
                .then(response => {
                    this.customers = response.data.customers;
                    this.form.customer_id = customer_id;
                });
        },
        async selectDefaultCustomer() {
            if (this.config.establishment.customer_id) {
                let temp_all_customers = [...this.all_customers];
                let temp_customers = [...this.customers];
                await this.$http
                    .get(
                        `/${this.resource}/search/customer/${this.config.establishment.customer_id
                        }`
                    )
                    .then(response => {
                        let data_customer = response.data.customers;
                        temp_all_customers.push(...data_customer);
                        temp_customers.push(...data_customer);
                    });
                temp_all_customers = temp_all_customers.filter(
                    (item, index, self) =>
                        index === self.findIndex(t => t.id === item.id)
                );
                temp_customers = temp_customers.filter(
                    (item, index, self) =>
                        index === self.findIndex(t => t.id === item.id)
                );
                this.all_customers = temp_all_customers;
                this.customers = temp_customers;
                let alt = _.find(this.customers, {
                    id: this.config.establishment.customer_id
                });

                if (alt !== undefined) {
                    this.form.customer_id = this.config.establishment.customer_id;
                    let seller = this.sellers.find(
                        element => element.id == alt.seller_id
                    );
                    if (seller !== undefined) {
                        this.form.seller_id = seller.id;
                    }
                }
            }
        },
        checkKeyWithAlt(e) {
            let code = e.event.code;
            if (this.showDialogOptions === true && code === "KeyN") {
                this.showDialogOptions = false;
            }

            if (
                code === "KeyG" &&
                !this.showDialogAddItem &&
                this.form.items.length > 0 &&
                this.focus_on_client === false
            ) {
                this.submit();
            }
        },
        checkKey(e) {
            let code = e.event.code;
            if (code === "F2") {
                if (!this.showDialogAddItem) this.showDialogAddItem = true;
            }
            if (code === "Escape") {
                if (this.showDialogAddItem) {
                    this.showDialogAddItem = false;
                }
            }
        },
        keyupCustomer() {
            if (this.input_person.number) {
                if (!isNaN(parseInt(this.input_person.number))) {
                    switch (this.input_person.number.length) {
                        case 8:
                            this.input_person.identity_document_type_id = "1";
                            this.showDialogNewPerson = true;
                            break;

                        case 11:
                            this.input_person.identity_document_type_id = "6";
                            this.showDialogNewPerson = true;
                            break;
                        default:
                            this.input_person.identity_document_type_id = "6";
                            this.showDialogNewPerson = true;
                            break;
                    }
                }
            }
        },
        initInputPerson() {
            this.input_person = {
                number: null,
                identity_document_type_id: null
            };
        },
        async checkCustomerExpiredDebt() {
            this.customer_expired_days = 0;
            this.customer_has_expired = false;

            if (
                this.config.finances &&
                this.config.finances.restriction_expired_debt &&
                this.form.customer_id
            ) {
                try {
                    const response = await this.$http.get(`/finances/unpaid/customer-expired-days/${this.form.customer_id}?model=sale_note`);
                    this.customer_expired_days = response.data.max_expired_days || 0;

                    this.customer_has_expired =
                        this.customer_expired_days > Number(this.config.finances.max_expired_days);

                    if (this.customer_has_expired) {
                        this.form.payment_condition_id = "01";
                    }
                } catch (e) {
                    this.customer_expired_days = 0;
                    this.customer_has_expired = false;
                }
            }
        },
        async getConsigneds() {
            this.consigneds = [];
            this.form.consigned_id = null;
            this.consigned_addresses = [];
            this.form.consigned_address_id = null;
            this.form.consigned_address = null;

            if (this.form.customer_id) {
                this.consigneds = []
                this.consigned_addresses = []
                await this.$http.get(`/consigneds/search_by_customer/${this.form.customer_id}`).then((response) => {
                    this.consigneds = response.data.consigneds
                })
            }
        },
        async getConsignedAddresses() {
            this.consigned_addresses = [];
            this.form.consigned_address_id = null;
            let parameters = `?consigned_id=${this.form.consigned_id}&person_id=${this.form.customer_id}`;
            await this.$http.get(`/consigneds/addresses/${parameters}`).then((response) => {
                this.consigned_addresses = response.data.consigned_addresses
            })
        },
        changeConsignedAddresses() {
            this.form.consigned_address = null;
            let consigned_address = _.find(this.consigned_addresses, { 'id': this.form.consigned_address_id });
            if (consigned_address) {
                this.form.consigned_address = consigned_address.address;
                this.form.consigned_ubigeo = consigned_address.district_id;
            }
        },
        openNewPersonDialog() {
            this.showDialogNewPerson = true
        },
    }
};
</script>