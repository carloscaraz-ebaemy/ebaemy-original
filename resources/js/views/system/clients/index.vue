<template>
    <div>
        <header class="page-header">
            <h2>
                <a href="/dashboard">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-layout-dashboard" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /><path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /></svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Dashboard</span>
                </li>
            </ol>
        </header>
        <div class="row mb-3 px-2">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-0 m-0">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="chart-data-selector ready ps-3 pe-4 pt-4">
                                    <div class="chart-data-selector-items">
                                        <chart-line v-if="chartDataLoaded && dataChartLine.labels.length > 0"
                                                    :data="dataChartLine"></chart-line>
                                        <div v-else class="d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="sr-only">Cargando gráfico...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row px-4 mt-2 pb-3">
                            <div class="col-2 font-weight-bold text-primary-new">{{ year }}</div>
                            <div class="col-10 font-weight-semibold text-end">Comprobantes generados por mes</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="row">
                    <div class="col-md-12">
                        <section class="card card-horizontal mb-4">
                            <header class="card-header bg-success">
                                <div class="card-header-icon">
                                    <svg  xmlns="http://www.w3.org/2000/svg"  width="50"  height="50"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users-group"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" /><path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M17 10h2a2 2 0 0 1 2 2v1" /><path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M3 13v-1a2 2 0 0 1 2 -2h2" /></svg>
                                </div>
                            </header>
                            <div class="card-body p-4 text-center">
                                <p class="font-weight-semibold mb-0 mx-4">Total Clientes</p>
                                <h2 class="font-weight-semibold mt-0">{{ records.length }}</h2>
                                <div class="summary-footer">
                                    <a class="text-muted text-uppercase"
                                       href="#client-list">Ver todos</a>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-md-12 mb-0">
                        <section class="card card-horizontal">
                            <header class="card-header bg-info">
                                <div class="card-header-icon">
                                    <svg  xmlns="http://www.w3.org/2000/svg"  width="50"  height="50"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-file-text"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg>
                                </div>
                            </header>
                            <div class="card-body p-4 text-center">
                                <p class="font-weight-semibold mb-0 mt-3">Total Comprobantes</p>
                                <h2 class="font-weight-semibold mt-0 mb-3">{{ total_documents }}</h2>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
        <div class="row px-2">
            <div class="col-md-3">
                <section class="card card-featured-left card-featured-primary mb-4">
                    <div class="card-body mx-0">
                        <div class="widget-summary widget-summary-md">
                            <div class="widget-summary-col widget-summary-col-icon">
                                <div class="summary-icon text-secondary">
                                    <div :data-value='discUsed'
                                         class="progress1 mx-auto">
                    <span class="progress1-left">
                      <span class="progress1-bar border-primary"></span>
                    </span>
                                        <span class="progress1-right">
                      <span class="progress1-bar border-primary"></span>
                    </span>
                                        <div class="progress1-value w-100 h-100 rounded-circle d-flex align-items-center justify-content-center">
                                            <div class="font-weight-bold">{{ discUsed }}<small class="small"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="widget-summary-col">
                                <div class="summary">
                                    <h4 class="title"><!-- Disco <br> Duro --></h4>
                                    <div class="info">
                                        <strong class="amount">Disco Duro</strong><br>
                                        <!-- <span class="text-warning" v-if="discUsed == 0">no se pudo obtener</span> -->
                                    </div>
                                </div>
                                <div class="summary-footer d-block">
                                    <a class="text-muted text-uppercase"
                                       href="https://docs.google.com/document/d/1hpEQUs9OFha_35yyLb1cMKeluD-dEku5lQsQ3TJFib8/edit"
                                       target="BLANK">Incrementar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-md-3">
                <section class="card card-featured-left card-featured-primary mb-4">
                    <div class="card-body mx-0">
                        <div class="widget-summary widget-summary-md">
                            <div class="widget-summary-col widget-summary-col-icon">
                                <div class="summary-icon text-secondary">
                                    <div :data-value='iUsed'
                                         class="progress1 mx-auto">
                    <span class="progress1-left">
                      <span class="progress1-bar border-primary"></span>
                    </span>
                                        <span class="progress1-right">
                      <span class="progress1-bar border-primary"></span>
                    </span>
                                        <div class="progress1-value w-100 h-100 rounded-circle d-flex align-items-center justify-content-center">
                                            <div class="font-weight-bold">{{ iUsed }}<small class="small"></small></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="widget-summary-col">
                                <div class="summary">
                                    <h4 class="title"><!-- Disco <br> Duro --></h4>
                                    <div class="info">
                                        <strong class="amount">Inodes</strong>
                                        <!-- <span class="text-primary">(14 unread)</span> -->
                                    </div>
                                </div>
                                <div class="summary-footer d-block">
                                    <a class="text-muted text-uppercase"
                                       href="https://drive.google.com/open?id=1foPKDI3V3Z9uKTjRc2SPSoztVSOBevPAluT2BqFbfxA"
                                       target="BLANK">Limpiar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-md-3">
                <section class="card card-featured-left card-featured-primary mb-4">
                    <div class="card-body mx-0">
                        <div class="widget-summary widget-summary-md">
                            <div class="widget-summary-col widget-summary-col-icon">
                                <div class="summary-icon text-secondary">
                                    <div class="progress1 mx-auto"
                                         data-value='100'>
                    <span class="progress1-left">
                      <span class="progress1-bar border-tertiary"></span>
                    </span>
                                        <span class="progress1-right">
                      <span class="progress1-bar border-tertiary"></span>
                    </span>
                                        <div class="progress1-value w-100 h-100 rounded-circle d-flex align-items-center justify-content-center">
                                            <div class="font-weight-bold">{{ storageSize }}<small class="small"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="widget-summary-col">
                                <div class="summary">
                                    <h4 class="title"><!-- Disco <br> Duro --></h4>
                                    <div class="info">
                                        <strong class="amount">Archivos <br> Generados</strong>
                                        <!-- <span class="text-primary">(14 unread)</span> -->
                                    </div>
                                </div>
                                <div class="summary-footer">
                                    <!-- <a class="text-muted text-uppercase">(view all)</a> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-md-3">
                <section class="card card-featured-left card-featured-primary mb-4">
                    <div class="card-body mx-0">
                        <div class="widget-summary widget-summary-md">
                            <div class="widget-summary-col widget-summary-col-icon">
                                <div class="summary-icon"
                                     style="background-color: #292961">
                                    <i class="fab fa-gitlab"></i>
                                </div>
                            </div>
                            <div class="widget-summary-col">
                                <div class="summary">
                                    <h4 class="title"><!-- Disco <br> Duro --></h4>
                                    <div class="info">
                                        <strong class="amount">Versión</strong><br>
                                        <span class="text-primary">{{ version }}</span>
                                    </div>
                                </div>
                                <div class="summary-footer">
                                    <!-- <a class="text-muted text-uppercase">(view all)</a> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="row mb-0 mt-3">
            <div class="col-12">
                <header class="page-header">
                    <h2>
                        <a href="/dashboard">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M21 21v-2a4 4 0 0 0 -3 -3.85" /></svg>
                        </a>
                    </h2>
                    <ol class="breadcrumbs">
                        <li class="active">
                            <span>Listado de Clientes</span>
                        </li>
                    </ol>                    
                    <div class="right-wrapper pull-right">
                        <div class="btn-group flex-wrap">
                            <button
                                class="btn btn-custom btn-sm mt-2 me-2 mb-3 primary-buton pull-end"
                                type="button"
                                @click.prevent="clickCreate()"
                                v-bind:disabled="!checkLimitUsers || isLoading"
                            >
                                <i :class="{ 
                                    'fa-plus-circle': checkLimitUsers, 
                                    'fa-exclamation-circle': !checkLimitUsers 
                                    }"
                                    class="fa"
                                ></i>
                                {{ checkLimitUsers ? 'Nuevo' : 'Clientes máximos creados. Actualice su plan llamando al 944999965' }}
                            </button>
                        </div>
                    </div>
                </header>                
            </div>
        </div>

        <div id="client-list"
             class="card">
            <div class="card-body mx-2">
                <!-- Botón para mostrar/ocultar filtros -->
                <div class="btn-filter-content mb-3 d-flex">
                    <el-button
                        type="secondary"
                        class="btn-show-filter"
                        :class="{ shift: isFiltersVisible }"
                        @click="toggleFilters"
                    >
                        {{ isFiltersVisible ? "Ocultar filtros" : "Mostrar filtros" }}
                    </el-button>
                    <el-button
                        v-if="hasActiveFilters"
                        type="secondary"
                        @click="clearFilters"
                    >
                        Limpiar Filtros
                    </el-button>
                </div>

                <!-- Filtros (ahora incluyen el buscador) -->
                <div v-if="isFiltersVisible" class="filter-section">
                    <div class="row">
                        <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                            <label class="control-label mb-1">Buscar:</label>
                            <el-input
                                v-model="searchQuery"
                                placeholder="Buscar por hostname, nombre, ruc o correo"
                                style="width: 100%;"
                                prefix-icon="el-icon-search"
                                @input="applyFilters">
                            </el-input>
                        </div>
                        <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                            <label class="control-label mb-1">Filtrar por Entorno:</label>
                            <el-select
                                v-model="filters.entorno"
                                placeholder="Todos los entornos"
                                style="width: 100%;"
                                @change="applyFilters">
                                <el-option label="Todos" value=""></el-option>
                                <el-option label="Demo" value="01"></el-option>
                                <el-option label="Producción" value="02"></el-option>
                                <el-option label="Interno" value="03"></el-option>
                            </el-select>
                        </div>
                        <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                            <label class="control-label mb-1">Filtrar por Plan:</label>
                            <el-select
                                v-model="filters.plan"
                                placeholder="Todos los planes"
                                style="width: 100%;"
                                @change="applyFilters">
                                <el-option label="Todos" value=""></el-option>
                                <el-option 
                                    v-for="plan in availablePlans" 
                                    :key="plan" 
                                    :label="plan" 
                                    :value="plan">
                                </el-option>
                            </el-select>
                        </div>
                        <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                            <label class="control-label mb-1">Estado de Cuenta:</label>
                            <el-select
                                v-model="filters.bloqueo"
                                placeholder="Todos los estados"
                                style="width: 100%;"
                                @change="applyFilters">
                                <el-option label="Todos" value=""></el-option>
                                <el-option label="Activos" value="0"></el-option>
                                <el-option label="Bloqueados" value="1"></el-option>
                            </el-select>
                        </div>
                    </div>
                </div>

                <!-- Columnas mostrar/ocultar -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-end mt-2 me-2 data-table-visible-columns">
                            <el-dropdown :hide-on-click="false" placement="bottom-end">
                                <el-button type="secondary">
                                    Mostrar columnas<i class="el-icon-arrow-down el-icon--right"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown" class="clients-columns-dropdown-menu">
                                    <el-dropdown-item v-for="(column, index) in columnsComputed" :key="index">
                                        <el-checkbox
                                            v-if="column.title !== undefined && column.visible !== undefined"
                                            v-model="column.visible" @change="saveColumnVisibility"
                                        >{{ column.title }}
                                        </el-checkbox>
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </el-dropdown>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 position-relative mt-4">
                <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>
                <div class="table-responsive" ref="scrollContainer">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th class="sticky-column">Hostname</th>
                            <th v-if="columns.bloquear_cuenta.visible" class="text-center">Bloquear <br>cuenta</th>
                            <th v-if="columns.nombre.visible" class="column-name">Nombre</th>
                            <th v-if="columns.ruc.visible">RUC</th>
                            <th v-if="columns.plan.visible">Plan</th>
                            <th v-if="columns.correo.visible">Correo</th>
                            <th v-if="columns.entorno.visible">Entorno</th>                            
                            <th v-if="columns.total_comprobantes.visible" class="text-center">Total de<br>Comprobantes</th>
                            <th v-if="columns.notificaciones.visible" class="text-center">Notificaciones</th>
                            <th v-if="columns.inicio_ciclo.visible" class="text-center">Inicio<br>Ciclo Facturación</th>
                            <th v-if="columns.comprobantes_ciclo.visible" class="text-center">Comprobantes<br>Ciclo Facturación</th>
                            <th v-if="columns.usuarios.visible" class="text-center">Usuarios</th>
                            <th v-if="columns.sucursales.visible" class="text-center">Sucursales</th>
                            <th v-if="columns.ventas_mes.visible" class="text-center">Ventas (Mes)</th>
                            <th v-if="columns.fecha_creacion.visible" class="text-center">F.Creación</th>
                            <th v-if="columns.consultas_api.visible" class="text-center">Consultas <br>API Peru <br>(mes)</th>
                            <th v-if="columns.notas_venta.visible" class="text-center">Cant. <br>Notas de venta</th>
                            <th v-if="columns.total_mes.visible" class="text-center">Total<br><small>(Comprobantes <br>por mes)</small></th>
                            <th v-if="columns.total_pse.visible" class="text-center">Total<br><small>(Comprobantes <br>a PSE-GIOR)</small></th>
                            <th v-if="columns.total_notas.visible" class="text-center">Total<br><small>(Comprobantes <br>notas de venta)</small></th>                            
                            <th v-if="columns.limitar_doc.visible" class="text-end">Limitar Doc.</th>
                            <th v-if="columns.limitar_usuarios.visible" class="text-center">Limitar <br>Usuarios</th>
                            <th v-if="columns.limitar_sucursales.visible" class="text-center">Limitar <br>Sucursales</th>
                            <th v-if="columns.limitar_ventas.visible" class="text-center">
                                <el-tooltip class="item"
                                    content="Límite de ventas mensual asociado al ciclo de facturación"
                                    effect="dark"
                                    placement="top">
                                    <label>Limitar <br>Ventas (Mes)</label>
                                </el-tooltip>
                            </th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in filteredRecords" :key="index">
                            <td>{{ index + 1 }}</td>
                            <td class="sticky-column">
                                <!-- {{ row.hostname }} -->
                                <a :href="`http://${row.hostname}`"
                                   style="color:black"
                                   target="_blank">{{ row.hostname }}</a>
                            </td>
                            <td v-if="columns.bloquear_cuenta.visible" class="text-center">
                                <template v-if="!row.locked">
                                    <el-switch
                                        v-model="row.locked_tenant"
                                        style="display: block"
                                        @change="changeLockedTenant(row)"
                                    ></el-switch>
                                </template>
                            </td>
                            <td v-if="columns.nombre.visible" class="column-name table-cell">{{ row.name }}</td>
                            <td v-if="columns.ruc.visible">{{ row.number }}</td>
                            <td v-if="columns.plan.visible">{{ row.plan }}</td>
                            <td v-if="columns.correo.visible">{{ row.email }}</td>
                            <td v-if="columns.entorno.visible">
                                <span v-if="row.soap_type == '01'"
                                      class="badge badge-default">Demo</span>
                                <span v-if="row.soap_type == '02'"
                                      class="badge badge-success">Producción</span>
                                <span v-if="row.soap_type == '03'"
                                      class="badge badge-info">Interno</span>
                            </td>
                            <td v-if="columns.total_comprobantes.visible" class="text-center">
                                <label>
                                    <strong>{{ row.count_doc }}</strong>
                                </label>
                            </td>

                            <td v-if="columns.notificaciones.visible" class="d-flex justify-content-center">
                                <template v-if="row.document_not_sent > 0 || row.document_to_be_regularized > 0 || row.document_to_be_canceled > 0">
                                    <el-tooltip
                                        class="item"
                                        content="Comprobantes enviados / por enviar"
                                        effect="dark"
                                        placement="top-start">
                                        <el-badge
                                            v-if="row.document_not_sent > 0"
                                            :value="row.document_not_sent"
                                            class="item mx-2"
                                            :type="row.document_not_sent == 0 ? 'primary' : 'danger'">
                                            <i class="far fa-bell text-secondary"></i>
                                        </el-badge>
                                    </el-tooltip>
                                
                                    <el-tooltip
                                        class="item"
                                        content="Comprobantes pendientes de rectificación"
                                        effect="dark"
                                        placement="top-start">
                                        <el-badge
                                            v-if="row.document_to_be_regularized > 0"
                                            :value="row.document_regularize_shipping"
                                            class="item mx-2"
                                            :type="row.document_regularize_shipping == 0 ? 'primary' : 'danger'">
                                            <i class="fas fa-exclamation-triangle text-secondary"></i>
                                        </el-badge>
                                    </el-tooltip>
                                
                                    <el-tooltip
                                        class="item"
                                        content="Comprobantes por anular"
                                        effect="dark"
                                        placement="top-start">
                                        <el-badge
                                            v-if="row.document_to_be_canceled > 0"
                                            :value="row.document_to_be_canceled"
                                            class="item mx-2"
                                            :type="row.document_to_be_canceled == 0 ? 'primary' : 'danger'">
                                            <i class="fas fa-exclamation-circle text-secondary"></i>
                                        </el-badge>
                                    </el-tooltip>
                                
                                </template>
                            
                                <template v-else>
                                    <span class="text-muted small d-flex align-items-center">
                                        Todo OK
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check ms-1 text-success"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                    </span>
                                </template>
                            </td>

                            <td v-if="columns.inicio_ciclo.visible" class="text-center">
                                <template v-if="row.start_billing_cycle">
                                    <span></span>
                                    <span>{{ row.start_billing_cycle }}</span>
                                </template>
                                <template v-else>
                                    <el-date-picker
                                        v-model="row.select_date_billing"
                                        placeholder="..."
                                        type="date"
                                        value-format="yyyy-MM-dd"
                                        @change="setStartBillingCycle($event, row.id)"
                                    ></el-date-picker>
                                </template>
                            </td>

                            <td v-if="columns.comprobantes_ciclo.visible" class="text-center">
                                <strong>
                                    <template v-if="row.sale_notes_quantity_if_include > 0">
                                        {{ row.count_doc_month ? (row.count_doc_month + row.sale_notes_quantity_if_include) : 0 }} /
                                    </template>
                                    <template v-else>
                                    {{ row.count_doc_month ? row.count_doc_month : 0 }} /
                                    </template>

                                    <template v-if="row.max_documents == 0">
                                        <i class="fas fa-infinity"></i>
                                    </template>
                                    <template v-else>
                                        <strong>{{ row.max_documents }}</strong>
                                    </template>
                                </strong>
                            </td>

                            <td v-if="columns.usuarios.visible" class="text-center">
                                <template v-if="row.max_users !== 0 && row.count_user > row.max_users">
                                    <el-popover
                                        :content="text_limit_users"
                                        placement="top-start"
                                        trigger="hover"
                                        width="220"
                                    >
                                        <label slot="reference"
                                               class="text-danger">
                                            <strong>{{ row.count_user }}</strong>
                                        </label>
                                    </el-popover>
                                </template>
                                <template v-else>
                                    <label>
                                        <strong>{{ row.count_user }}</strong>
                                    </label>
                                </template>
                                /
                                <template v-if="row.max_users == 0">
                                    <i class="fas fa-infinity"></i>
                                </template>
                                <template v-else>
                                    <strong>{{ row.max_users }}</strong>
                                </template>
                            </td>

                            <td v-if="columns.sucursales.visible" class="text-center">

                                <data-limit-notification
                                    entity_description="sucursales"
                                    :unlimited="row.establishments_unlimited"
                                    :quantity="row.quantity_establishments"
                                    :max_quantity="row.max_quantity_establishments"
                                    >
                                </data-limit-notification>

                            </td>

                            <td v-if="columns.ventas_mes.visible" class="text-center">

                                <data-limit-notification
                                    entity_description="ventas"
                                    style_div="width: 150px !important"
                                    :unlimited="row.sales_unlimited"
                                    :quantity="row.monthly_sales_total"
                                    :max_quantity="row.max_sales_limit"
                                    >
                                </data-limit-notification>

                            </td>

                            <td v-if="columns.fecha_creacion.visible" class="text-center">{{ row.created_at }}</td>
                            <td v-if="columns.consultas_api.visible">{{ row.queries_to_apiperu }}</td>

                            <td v-if="columns.notas_venta.visible" class="text-center"><strong>{{ row.count_sales_notes }}</strong></td>
                            <td v-if="columns.total_mes.visible" class="text-center"><strong>{{ row.current_count_doc_month }}</strong></td>
                            <td v-if="columns.total_pse.visible" class="text-center"><strong>{{ row.count_doc_pse }}</strong></td>
                            <td v-if="columns.total_notas.visible" class="text-center"><strong>{{ row.count_doc_month + row.count_sales_notes_month }}</strong></td>

                            <td v-if="columns.limitar_doc.visible" class="text-center">
                                <el-switch
                                    v-model="row.locked_emission"
                                    style="display: block"
                                    @change="changeLockedEmission(row)"
                                ></el-switch>
                            </td>

                            <td v-if="columns.limitar_usuarios.visible" class="text-center">
                                <el-switch
                                    v-model="row.locked_users"
                                    style="display: block"
                                    @change="changeLockedUser(row)"
                                ></el-switch>
                            </td>

                            <td v-if="columns.limitar_sucursales.visible" class="text-center">
                                <el-switch
                                    v-model="row.locked_create_establishments"
                                    style="display: block"
                                    @change="changeLockedByColumn(row, 'locked_create_establishments')"
                                ></el-switch>
                            </td>

                            <td v-if="columns.limitar_ventas.visible" class="text-center">
                                <el-switch
                                    v-model="row.restrict_sales_limit"
                                    style="display: block"
                                    @change="changeLockedByColumn(row, 'restrict_sales_limit')"
                                ></el-switch>
                            </td>

                            <td class="text-end">
                                <el-dropdown trigger="click" @command="handleCommand" placement="bottom-end">
                                    <el-button type="text" size="small" class="dropdown-trigger">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </el-button>
                                    <el-dropdown-menu slot="dropdown">
                                        <el-dropdown-item :command="{action: 'edit', id: row.id}">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                            Editar
                                        </el-dropdown-item>

                                        <el-dropdown-item divided></el-dropdown-item>

                                        <el-dropdown-item :command="{action: 'secretLogin', id: row.id}">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-dual-screen me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4l8 3v15l-8 -3z" /><path d="M13 19h6v-15h-14" /></svg>
                                            Acceso Maestro
                                        </el-dropdown-item>

                                        <el-dropdown-item 
                                            v-if="row.soap_type=='01'" 
                                            :command="{action: 'demoConfig', id: row.id}">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-settings me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                                            Configurar Demo
                                        </el-dropdown-item>

                                        <el-dropdown-item :command="{action: 'payments', id: row.id}">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-cash-banknote me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M3 8a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" /><path d="M18 12h.01" /><path d="M6 12h.01" /></svg>
                                            Pagos
                                        </el-dropdown-item>

                                        <el-dropdown-item :command="{action: 'accountStatus', id: row.id}">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-chart-line me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 19l16 0" /><path d="M4 15l4 -6l4 2l4 -5l4 4" /></svg>
                                            Estado de cuenta
                                        </el-dropdown-item>
                                        
                                        <template v-if="!row.locked">
                                            <el-dropdown-item :command="{action: 'password', id: row.id}">
                                                <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-key me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16.555 3.843l3.602 3.602a2.877 2.877 0 0 1 0 4.069l-2.643 2.643a2.877 2.877 0 0 1 -4.069 0l-.301 -.301l-6.558 6.558a2 2 0 0 1 -1.239 .578l-.175 .008h-1.172a1 1 0 0 1 -.993 -.883l-.007 -.117v-1.172a2 2 0 0 1 .467 -1.284l.119 -.13l.414 -.414h2v-2h2v-2l2.144 -2.144l-.301 -.301a2.877 2.877 0 0 1 0 -4.069l2.643 -2.643a2.877 2.877 0 0 1 4.069 0z" /><path d="M15 9h.01" /></svg>
                                                Restablecer contraseña
                                            </el-dropdown-item>
                                            <el-dropdown-item v-if="deletePermission == true" divided></el-dropdown-item>
                                            <el-dropdown-item 
                                                class="text-danger option-delete"
                                                v-if="deletePermission == true"
                                                :command="{action: 'delete', row: row}">
                                                <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-trash me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                                Eliminar Cliente
                                            </el-dropdown-item>
                                        </template>                                       
                                    
                                    </el-dropdown-menu>
                                </el-dropdown>
                            </td>
                            <!-- <td class="text-right">
                                <button
                                    class="btn waves-effect waves-light btn-xs btn-warning m-1__2"
                                    type="button"
                                    @click.prevent="clickPayments(row.id)"
                                >Pagos
                                </button>
                            </td>
                            <td class="text-right">
                                <button
                                    class="btn waves-effect waves-light btn-xs btn-primary m-1__2"
                                    type="button"
                                    @click.prevent="clickAccountStatus(row.id)"
                                >E. Cuenta
                                </button>
                            </td>
                            <td class="text-right">
                                <button
                                    class="btn waves-effect waves-light btn-xs btn-primary m-1__2"
                                    type="button"
                                    @click.prevent="clickEdit(row.id)"
                                >Editar
                                </button>
                            </td> -->

                        </tr>
                        
                        <!-- Fila cuando no hay resultados -->
                        <tr v-if="filteredRecords.length === 0 && records.length > 0">
                            <td colspan="20" class="text-center py-4">
                                <div class="text-muted">
                                    <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30" style="opacity: 0.5;"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                                    <p class="mb-0">No se encontraron clientes que coincidan con los filtros aplicados.</p>
                                    <small>
                                        <template v-if="searchQuery">
                                            Búsqueda: "{{ searchQuery }}"
                                        </template>
                                        <template v-if="filters.entorno">
                                            | Entorno: {{ getEntornoLabel(filters.entorno) }}
                                        </template>
                                        <template v-if="filters.plan">
                                            | Plan: {{ filters.plan }}
                                        </template>
                                        <template v-if="filters.bloqueo !== ''">
                                            | Estado: {{ filters.bloqueo === '1' ? 'Bloqueados' : 'Activos' }}
                                        </template>
                                    </small>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                </div>                
            </div>
        </div>

        <system-clients-form :recordId="recordId"
                             :showDialog.sync="showDialog"></system-clients-form>

        <!--<system-clients-form-edit :showDialog.sync="showDialogEdit"
        :recordId="recordId"></system-clients-form-edit>-->

        <client-payments :clientId="recordId"
                         :showDialog.sync="showDialogPayments"></client-payments>

        <account-status :clientId="recordId"
                        :showDialog.sync="showDialogAccountStatus"></account-status>

        <client-delete :record="record"
                        :showDialog.sync="showDialogDelete"></client-delete>

        <demo-configuration :clientId="recordId"
                        :showDialog.sync="showDemoConfiguration"></demo-configuration>
    </div>
</template>
<style>
.table th,
.table td {
    white-space: nowrap;
}
th.sticky-column,
td.sticky-column {
    position: sticky;
    left: 0;
    background-color: #fff !important;
    z-index: 1;
}
th.sticky-column.scroll-active::after,
td.sticky-column.scroll-active::after {
  opacity: 1;
  transition: opacity 0.2s ease;
}
th.sticky-column::after,
td.sticky-column::after {
    content: "";
    position: absolute;
    top: 0;
    right: -6px;
    width: 6px;
    height: 100%;
    background: linear-gradient(to right, rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0));
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s ease;
}
th.sticky-column {
    z-index: 2;
}
.dropdown-trigger {
    color: #6c757d;
    border: none !important;
    background: none !important;
    box-shadow: none !important;
    padding: 4px 8px;
}
.dropdown-trigger:hover {
    color: #495057;
    background-color: #f8f9fa !important;
}
.shadow-right {
    right: 0px;
}
.shadow-left {
    left: 0px;
    z-index: 3;
}
.page-header {
    position: relative;
}
.clients-columns-dropdown-menu {
    max-height: 80vh;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
}

.clients-columns-dropdown-menu::-webkit-scrollbar {
    width: 6px;
}
.page-header-actions {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
}
@media (max-width: 768px) {
    .page-header-actions {
        position: static;
        transform: none;
        margin-top: 1rem;
    }
}
</style>
<script>
// import CompaniesForm from "./form.vue";
//   import CompaniesFormEdit from './form_edit.vue'
import {deletable} from "../../../mixins/deletable";
import {changeable} from "../../../mixins/changeable";
import ChartLine from "./charts/Line.vue";
import ClientPayments from "./partials/payments.vue";
import DemoConfiguration from "./partials/demo_configuration.vue";
import AccountStatus from "./partials/account_status.vue";
import ClientDelete from "./partials/delete.vue";
import DataLimitNotification from "./partials/DataLimitNotification.vue";

export default {
    mixins: [
        deletable,
        changeable
    ],
    props: [
        'deletePermission',
        'discUsed',
        'iUsed',
        'storageSize',
        'version'
    ],
    components: {
        // CompaniesForm,
        ChartLine,
        ClientPayments,
        AccountStatus,
        ClientDelete,
        DataLimitNotification,
        DemoConfiguration
    },
    data() {
        return {
            searchQuery: "",
            selectBillingDate: "",
            showDialogEdit: false,
            showDialog: false,
            showDialogPayments: false,
            showDialogAccountStatus: false,
            resource: "clients",
            recordId: null,
            records: [],
            isLoading: true,
            checkLimitUsers: true,
            text_limit_doc: null,
            text_limit_users: null,
            loaded: false,
            chartDataLoaded: false,
            year: moment().format('YYYY'),
            total_documents: 0,
            filters: {
                entorno: "",
                plan: "",
                bloqueo: ""
            },
            dataChartLine: {
                labels: [],
                datasets: [
                    {
                        // label: 'Data One',
                        // backgroundColor: '#f87979',
                        data: []
                    }
                ]
            },
            showDialogDelete: false,
            record: {},
            showDemoConfiguration:false,
            showLeftShadow: false,
            showRightShadow: false,
            isFiltersVisible: false,
            columns: {
                nombre: {
                    title: 'Nombre',
                    visible: true
                },
                ruc: {
                    title: 'RUC',
                    visible: true
                },
                plan: {
                    title: 'Plan',
                    visible: true
                },
                correo: {
                    title: 'Correo',
                    visible: true
                },
                entorno: {
                    title: 'Entorno',
                    visible: true
                },
                total_comprobantes: {
                    title: 'Total de Comprobantes',
                    visible: false
                },
                notificaciones: {
                    title: 'Notificaciones',
                    visible: true
                },
                inicio_ciclo: {
                    title: 'Inicio Ciclo Facturación',
                    visible: false
                },
                comprobantes_ciclo: {
                    title: 'Comprobantes Ciclo Facturación',
                    visible: true
                },
                usuarios: {
                    title: 'Usuarios',
                    visible: true
                },
                sucursales: {
                    title: 'Sucursales',
                    visible: false
                },
                ventas_mes: {
                    title: 'Ventas (Mes)',
                    visible: false
                },
                fecha_creacion: {
                    title: 'F.Creación',
                    visible: true
                },
                consultas_api: {
                    title: 'Consultas API Peru (mes)',
                    visible: false
                },
                notas_venta: {
                    title: 'Cant.Notas de venta',
                    visible: false
                },
                total_mes: {
                    title: 'Total (Comprobantes por mes)',
                    visible: false
                },
                total_pse: {
                    title: 'Total (Comprobantes a PSE-GIOR)',
                    visible: false
                },
                total_notas: {
                    title: 'Total (Comprobantes notas de venta)',
                    visible: false
                },
                bloquear_cuenta: {
                    title: 'Bloquear cuenta',
                    visible: true
                },
                limitar_doc: {
                    title: 'Limitar Doc.',
                    visible: false
                },
                limitar_usuarios: {
                    title: 'Limitar Usuarios',
                    visible: false
                },
                limitar_sucursales: {
                    title: 'Limitar Sucursales',
                    visible: false
                },
                limitar_ventas: {
                    title: 'Limitar Ventas (Mes)',
                    visible: false
                }
            }
        };
    },
    async mounted() {
        this.loaded = false;
        this.chartDataLoaded = false;
        
        try {
            const response = await this.$http.get(`/${this.resource}/charts`);
            let line = response.data.line;
            
            if (line && line.labels && line.data) {
                this.dataChartLine.labels = line.labels;
                this.dataChartLine.datasets[0].data = line.data;
                this.total_documents = response.data.total_documents || 0;
                
                // Mostrar advertencia si hubo errores en el servidor
                if (response.data.error) {
                    this.$message.warning(response.data.error);
                }
                
                this.$nextTick(() => {
                    this.chartDataLoaded = true;
                });
            } else {
                // Si no hay datos, inicializar con valores vacíos
                this.dataChartLine.labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
                this.dataChartLine.datasets[0].data = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
                this.total_documents = 0;
                this.chartDataLoaded = true;
            }
        } catch (error) {
            console.error('Error loading chart data:', error);
            
            // Mostrar mensaje de error al usuario
            if (error.response && error.response.status === 500) {
                this.$message.error('Error al cargar los datos del gráfico. Por favor, contacte al administrador.');
            } else {
                this.$message.error('No se pudieron cargar los datos del gráfico.');
            }
            
            // Inicializar con datos vacíos para que el gráfico se muestre
            this.dataChartLine.labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
            this.dataChartLine.datasets[0].data = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
            this.total_documents = 0;
            this.chartDataLoaded = true;
        }
        
        this.loaded = true;
        this.initScrollShadow();
        this.$nextTick(() => {
            const el = this.$refs.scrollContainer;
            if (el) {
                el.addEventListener('scroll', this.checkScrollShadows);
                this.checkScrollShadows();
            }
        });
    },
    created() {
        this.$eventHub.$on("reloadData", () => {
            this.getData();
        });
        this.getData();
        this.checkLimit();

        this.text_limit_doc = "El límite de comprobantes fue superado";
        this.text_limit_users = "El límite de usuarios fue superado";
        this.loadColumnVisibility();
    },
    computed: {
        filteredRecords() {
            let filtered = this.records;

            // Filtro por búsqueda de texto
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter((row) => {
                    return (
                        (row.name && row.name.toLowerCase().includes(query)) ||
                        (row.hostname && row.hostname.toLowerCase().includes(query)) ||
                        (row.email && row.email.toLowerCase().includes(query)) ||
                        (row.number && row.number.toLowerCase().includes(query))
                    );
                });
            }

            // Filtro por entorno
            if (this.filters.entorno) {
                filtered = filtered.filter((row) => {
                    return row.soap_type === this.filters.entorno;
                });
            }

            // Filtro por plan
            if (this.filters.plan) {
                filtered = filtered.filter((row) => {
                    return row.plan === this.filters.plan;
                });
            }

            // Filtro por estado de bloqueo
            if (this.filters.bloqueo !== "") {
                filtered = filtered.filter((row) => {
                    const isBlocked = Boolean(row.locked_tenant);
                    
                    if (this.filters.bloqueo === "1") {
                        return isBlocked; // Bloqueados
                    } else if (this.filters.bloqueo === "0") {
                        return !isBlocked; // Activos
                    }
                    return true;
                });
            }

            return filtered;
        },
        availablePlans() {
            const plans = [...new Set(this.records.map(record => record.plan).filter(plan => plan))];
            return plans.sort();
        },
        columnsComputed: function () {
            return this.columns;
        },
        hasActiveFilters() {
            return !!(
                this.searchQuery ||
                this.filters.entorno ||
                this.filters.plan ||
                this.filters.bloqueo !== ""
            );
        }
    },
    methods: {
        checkLimit() {
            this.isLoading = true;
            this.$http.get('clients/confirm-limit-reseller')
            .then(response => {
                if (response.data.success) {
                    this.checkLimitUsers = true;
                } else {
                    this.checkLimitUsers = false;
                }
            })
            .finally(() => {
                this.isLoading = false;
            })
            .catch(error => {
                console.error('Error al verificar el límite:', error);
                this.checkLimitUsers = false;
            });
        },
        saveColumnVisibility() {
            localStorage.setItem('columnVisibilityClients', JSON.stringify(this.columns));
        },
        loadColumnVisibility() {
            const savedColumns = localStorage.getItem('columnVisibilityClients');
            if (savedColumns) {
                this.columns = JSON.parse(savedColumns);
            }
        },
        applyFilters() {
            console.log('Filtros aplicados:', this.filters);
            
            this.$nextTick(() => {
                const totalRecords = this.records.length;
                const filteredCount = this.filteredRecords.length;
                
                if (this.filters.bloqueo !== "" && filteredCount === 0) {
                    const filterType = this.filters.bloqueo === "1" ? "bloqueados" : "activos";
                    console.log(`No se encontraron clientes ${filterType} de un total de ${totalRecords} registros`);
                }
            });
        },
        clearFilters() {
            this.filters.entorno = "";
            this.filters.plan = "";
            this.filters.bloqueo = "";
            this.searchQuery = "";
        },
        toggleFilters() {
            this.isFiltersVisible = !this.isFiltersVisible;
        },
        getEntornoLabel(value) {
            const entornos = {
                '01': 'Demo',
                '02': 'Producción',
                '03': 'Interno'
            };
            return entornos[value] || value;
        },
        checkScrollShadows() {
            const el = this.$refs.scrollContainer;
            if (!el) return;

            const scrollLeft = el.scrollLeft;
            const scrollRight = el.scrollWidth - el.clientWidth - scrollLeft;

            this.showLeftShadow = scrollLeft > 1;
            this.showRightShadow = scrollRight > 1;
        },
        handleCommand(command) {
            switch (command.action) {
                case 'password':
                    this.clickPassword(command.id);
                    break;
                case 'delete':
                    this.clickDelete(command.row);
                    break;
                case 'edit':
                    this.clickEdit(command.id);
                    break;
                case 'demoConfig':
                    this.clickDemoConfiguration(command.id);
                    break;
                case 'secretLogin':
                    this.clickSecretLogin(command.id);
                    break;
                case 'payments':
                    this.clickPayments(command.id);
                    break;
                case 'accountStatus':
                    this.clickAccountStatus(command.id);
                    break;
            }
        },
        initScrollShadow() {
          this.$nextTick(() => {
            const container = this.$refs.scrollContainer;
            if (!container) return;
        
            container.addEventListener('scroll', () => {
              const isScrolled = container.scrollLeft > 0;
            
              const thSticky = container.querySelector('th.sticky-column');
              const tdStickies = container.querySelectorAll('tbody td.sticky-column');
            
              if (thSticky) {
                if (isScrolled) thSticky.classList.add('scroll-active');
                else thSticky.classList.remove('scroll-active');
              }
          
              tdStickies.forEach(td => {
                if (isScrolled) td.classList.add('scroll-active');
                else td.classList.remove('scroll-active');
              });
            });
        
            container.dispatchEvent(new Event('scroll'));
          });
        },
        changeLockedTenant(row) {
            this.$http
                .post(`${this.resource}/locked_tenant`, row)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 500) {
                        this.$message.error(error.response.data.message);
                    } else {
                        console.log(error.response);
                    }
                })
                .then(() => {
                });
        },

        changeLockedUser(row) {
            this.$http
                .post(`${this.resource}/locked_user`, row)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 500) {
                        this.$message.error(error.response.data.message);
                    } else {
                        console.log(error.response);
                    }
                })
                .then(() => {
                });
        },
        changeLockedByColumn(row, column)
        {
            const params = { ...row }
            params.column = column

            this.$http
                .post(`${this.resource}/locked-by-column`, params)
                .then(response => {

                    if (response.data.success)
                    {
                        this.$message.success(response.data.message)
                        this.$eventHub.$emit("reloadData")
                    }
                    else
                    {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    if (error.response.status === 500)
                    {
                        this.$message.error(error.response.data.message)
                    }
                    else
                    {
                        console.log(error.response)
                    }
                })
                .then(() => {
                })
        },
        setStartBillingCycle(event, id) {
            this.$http
                .post(`${this.resource}/set_billing_cycle`, {
                    id: id,
                    start_billing_cycle: event
                })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 500) {
                        this.$message.error(error.response.data.message);
                    } else {
                        console.log(error.response);
                    }
                })
                .then(() => {
                    this.$eventHub.$emit("reloadData");
                });
        },
        changeLockedEmission(row) {
            this.$http
                .post(`${this.resource}/locked_emission`, row)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 500) {
                        this.$message.error(error.response.data.message);
                    } else {
                        console.log(error.response);
                    }
                })
                .then(() => {
                });
        },
        getData() {
            this.$http.get(`/${this.resource}/records`).then(response => {
                this.records = response.data.data;
            });
        },
        clickCreate(recordId = null) {
            this.recordId = recordId;
            this.showDialog = true;
        },
        clickPayments(recordId = null) {
            this.recordId = recordId;
            this.showDialogPayments = true;
        },
        clickAccountStatus(recordId = null) {
            this.recordId = recordId;
            this.showDialogAccountStatus = true;
        },
        clickSecretLogin(clientId) {
            // Crear un formulario dinámico para enviar la solicitud POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/secret-login`;
            form.target = '_blank';
            form.style.display = 'none';

            // Agregar el token CSRF
            const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            // Agregar el ID del cliente
            const clientIdInput = document.createElement('input');
            clientIdInput.type = 'hidden';
            clientIdInput.name = 'client_id';
            clientIdInput.value = clientId;
            form.appendChild(clientIdInput);

            // Agregar el formulario al documento y enviarlo
            document.body.appendChild(form);
            form.submit();
        },

        clickPassword(id) {
            this.change(`/${this.resource}/password/${id}`);
        },
        clickDelete(record) {

            this.record = record
            this.showDialogDelete = true
            // this.destroy(`/${this.resource}/${id}`).then(() =>
            //     this.$eventHub.$emit("reloadData")
            // );
        },
        clickEdit(recordId) {
            this.recordId = recordId;
            this.showDialog = true;
        },
        clickDemoConfiguration(recordId = null) {
            this.recordId = recordId;
            this.showDemoConfiguration = true;
        },
    }
};
</script>
