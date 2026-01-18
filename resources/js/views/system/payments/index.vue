<template>
    <div>
        <header class="page-header">
            <h2><a href="/payment-orders">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-credit-card" style="margin-top: -3px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 10l18 0" /><path d="M7 15l.01 0" /><path d="M11 15l2 0" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Administración de pagos</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button @click="showDialogOrder = true" type="button" class="btn btn-custom btn-sm  mt-2 me-2" ><i class="fa fa-plus-circle"></i> Nueva Orden</button>
            </div>
        </header>
        <!-- Cards -->
        <div class="col-md-12 container">
            <div class="row">
                
                <template v-for="pay in sortedPayments">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="status-container p-3 d-flex align-items-center justify-content-between" :style="{backgroundColor: getClassStatePay(pay.id)}">
                        <div>
                            <span class="status-price">S/ {{ parseFloat(pay.total).toFixed(2) }}</span>
                            <p class="m-0">{{ pay.name }}</p>
                        </div>
                        <div v-html="getIconPay(pay.id)"></div>
                    </div>                    
                </div>

                </template>
            </div>

        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-body mx-2">
                <div class="btn-filter-content mb-3 d-flex">
                    <el-button
                        type="secondary"
                        class="btn-show-filter"
                        :class="{ shift: isFiltersVisible }"
                        @click="toggleFilters"
                    >
                        {{ isFiltersVisible ? "Ocultar filtros" : "Mostrar filtros" }}
                    </el-button>
                    <el-button v-if="isFiltersVisible" @click="getRecords()" type="secondary" icon="el-icon-search">
                        Aplicar filtros
                    </el-button>
                    <el-button 
                      v-if="filtersChanged" 
                      @click="initFilters(); getRecords()" 
                      type="secondary" 
                      icon="el-icon-refresh">
                      Limpiar filtros
                    </el-button>
                </div>
                <div v-if="isFiltersVisible" class="filter-section">
                   <div class="row">
                       <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                           <label class="control-label">Buscar cliente</label>
                           <el-select v-model="filters.client_id" 
                               filterable
                               remote
                               :remote-method="searchRemoteCustomers"
                           >
                               <el-option
                               v-for="client in clients"
                               :key="client.id"
                               :label="client.name"
                               :value="client.id">
                               </el-option>
                           </el-select>
                       </div>
                       <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                           <label class="control-label">Estado</label>
                           <el-select v-model="filters.order_state_id"
                           >
                               <el-option
                               v-for="state in status"
                               :key="state.id"
                               :label="state.name"
                               :value="state.id">
                               </el-option>
                           </el-select>
                       </div>
                       <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                           <label class="control-label">Fecha de inicio</label>
                           <el-date-picker
                               v-model="filters.date_start"
                               type="date"
                               placeholder="Fecha de inicio"
                               class="w-100">
                           </el-date-picker>
                       </div>
                       <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                           <label class="control-label">Fecha de fin</label>
                           <el-date-picker
                               v-model="filters.date_end"
                               type="date"
                               placeholder="Fecha de fin"
                               class="w-100">
                           </el-date-picker>
                       </div>                       
                   </div>
                </div>
                <div class="col-md-12 mt-3 card-filters-client" v-if="filters.client_id">
                   <header class="card-filters-header p-3" >
                       <div class="row">
                           <div class="col-md-12 text-center position-relative">
                               <strong class="client-name">
                                   {{ getClient.name }} ({{ getClient.number }})
                               </strong>
                               <div class="container-edit-delete d-flex">
                                 <button
                                   type="button"
                                   class="btn btn-light btn-sm me-2 d-flex align-items-center justify-content-center"
                                   @click="showDialogEditClient = true"
                                   title="Editar cliente"
                                 >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                 </button>
                               
                                 <button
                                   type="button"
                                   class="btn btn-light btn-sm d-flex align-items-center justify-content-center"
                                   @click="showDialogOrder = true"
                                   title="Nueva orden"
                                 >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                 </button>
                               </div>
                           </div>                           
                       </div>
                       <div class="row mt-3 d-flex justify-content-center">
                           <div class="col-md-4 text-center" v-if="getClient.client_name || getClient.contact_email">
                               <span>
                                   {{ getClient.client_name }}
                               </span>
                               <br>
                               <strong class="text-center">
                                   {{ getClient.contact_email }}
                               </strong>
                           </div>
                           <div class="col-md-4 text-center" v-if="getClient.phone_ws">
                               <span>
                                   WHATSAPP
                               </span>
                               <br>
                               <strong class="text-center">
                                   {{ getClient.phone_ws }}
                               </strong>
                           </div>
                           <div class="col-md-4 text-center" v-if="getClient.created_at || getClient.diff_time_creation">
                               <span>
                                   CREADO {{ getClient.created_at }}
                               </span>
                               <br>
                               <strong class="text-center">
                                   {{ getClient.diff_time_creation }}
                               </strong>
                           </div>
                       </div>
                   </header>
                   <article>
                       <div class="col-md-12 container border-bottom-filters">
                           <div class="row row-cols-4">
                           <template v-for="pay in sortedClientPays">
                               <div class="col text-center p-3">
                                   <span class="text-muted">
                                       {{ pay.name }}
                                   </span>
                                   <br>
                                   <strong
                                     class="text-center price-pay"
                                     :class="getTextClassPay(pay.id)"
                                   >
                                     S/ {{ parseFloat(pay.total).toFixed(2) }}
                                   </strong>
                               </div>                           
                           </template>
                           </div>
                       </div>
                       <div class="col-md-12 container">
                           <div class="row row-cols-5">
                               <div class="col text-center p-3">
                                   <span class="text-muted">
                                       PLAN
                                   </span>
                                   <br>
                                   <strong class="text-center price-pay">
                                       {{ getClient.plan.name }}
                                   </strong>
                               </div>
                               <div class="col text-center p-3">
                                   <span class="text-muted">
                                       PRECIO
                                   </span>
                                   <br>
                                   <strong class="text-center price-pay">
                                       S/ {{ parseFloat(getClient.plan.price).toFixed(2) }}
                                   </strong>
                               </div>
                               <div class="col text-center p-3">
                                   <span class="text-muted">
                                       PERIODO
                                   </span>
                                   <br>
                                   <strong class="text-center price-pay">
                                       {{ getClient.plan.period }}
                                   </strong>
                               </div>
                               <div class="col text-center p-3">
                                   <span class="text-muted">
                                       SERVICIO
                                   </span>
                                   <br>
                                   <strong class="text-center price-pay" :class="getClient.plan.active ? 'text-success' : 'text-danger'">
                                       {{ getClient.plan.active ? 'Activo' : 'Inactivo' }}
                                   </strong>
                               </div>
                               <div class="col text-center p-3">
                                   <span class="text-muted">
                                       Vence {{ getClient.plan.date_of_ending }}
                                   </span>
                                   <br>
                                   <strong class="text-center price-pay">
                                       {{ getClient.plan.diff_date_of_ending }}
                                   </strong>
                               </div>
                           </div>
                       
                       
                       </div>
                   </article>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table ">
                        <thead>
                            <tr>
                                <th class="text-start">
                                    ID de orden
                                </th>
                                <th class="client-column">
                                    Cliente
                                </th>
                                <th class="amount-column">
                                    Monto
                                </th>
                                <th class="datetime-column">
                                    Fecha de vencimiento
                                </th>
                                <th>
                                    Estado
                                </th>
                                <th class="text-center">
                                    Notificaciones
                                </th>
                                <th class="text-end">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="record in records" :key="record.id">
                                <td class="text-start fw-bold"> 
                                    <span>
                                        #{{ record.order }}
                                    </span>
                                    <br>
                                    <template v-if="record.created_by === 'Manual'">
                                        <span  class="text-center badge badge-info badge-pill">
                                            {{ record.created_by }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span  class="text-center badge badge-pill badge-secondary">
                                            {{ record.created_by }}
                                        </span>
                                    </template>
                                </td>
                                <td class="client-column">
                                    {{ record.client }}
                                    <br>
                                    <span class="description-text text-muted">
                                        {{ record.description }}
                                    </span>
                                </td>
                                <td class="amount-column">
                                    <el-input
                                        :disabled="record.order_state_id == 4 || record.order_state_id == 2"
                                        v-model="record.amount"
                                        size="small"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        @blur="formatDecimal(record)"
                                        @change="updateTable(record.id)"
                                    ></el-input>
                                </td>
                                <td class="datetime-column">
                                    <el-date-picker
                                        :disabled="record.order_state_id == 4 || record.order_state_id == 2"
                                        v-model="record.due_date"
                                        @change="updateTable(record.id)"    
                                        value-format="yyyy-MM-dd"
                                        default-time="00:00:00"
                                        placeholder="Elija la fecha de vencimiento de la orden">
                                    </el-date-picker>
                                </td>
                                <td>
                                    <span class="badge badge-pill text-center" :class="[getClassState(record.order_state_id)]">
                                        {{ record.order_state }}
                                    </span>
                                    <br>
                                    <span class="text-muted" v-if="record.date_of_payment"> {{ record.date_of_payment }} </span>
                                </td>
                                <!-- <td>{{ record.order_state }}</td> -->
                                <td class="text-center">
                                    <span class="text-center badge badge-info badge-pill" v-if="record.notifications >= 1">
                                        {{ record.notifications }}
                                    </span>
                                    <br>
                                    <span class="text-muted" v-if="record.diff_notification"> {{ record.diff_notification }}</span>
                                </td>
                                <td class="text-end">
                                  <template v-if="!(record.order_state_id == 4 || record.order_state_id == 2)">
                                    <el-dropdown trigger="click" size="small">
                                      <el-button type="text" size="small" class="dropdown-trigger">
                                        <i class="fas fa-ellipsis-v"></i>
                                      </el-button>
                                      <el-dropdown-menu slot="dropdown">
                                        <el-dropdown-item @click.native="actionsPaymentOrder('notify', record.id)">
                                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z" /><path d="M3 7l9 6l9 -6" /></svg> 
                                          Notificar
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="viewButtonPay(record)" @click.native="actionsPaymentOrder('pays', record.id)">
                                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cash me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 15h-3a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v3" /><path d="M7 9m0 1a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v8a1 1 0 0 1 -1 1h-12a1 1 0 0 1 -1 -1z" /><path d="M12 14a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /></svg> 
                                          Pagar
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="viewButtonCancel(record)" @click.native="actionsPaymentOrder('cancel', record.id)">
                                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-ban me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M5.7 5.7l12.6 12.6" /></svg> 
                                          Anular
                                        </el-dropdown-item>
                                      </el-dropdown-menu>
                                    </el-dropdown>
                                  </template>
                                  <template v-else>
                                    <span class="text-muted small d-inline-flex align-items-center text-center">
                                        Sin<br> acciones
                                    </span>
                                  </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>                  
        
            
        <create-order
            :showDialogOrder.sync="showDialogOrder"
            :clients="clients"
            :resource="resource"
            :recordId="getClient.id"
            @refresh-records="reload()">
        </create-order>
        <edit-client
            :showDialogEditClient.sync="showDialogEditClient"
            :client="getClient"
            @refresh-records="reload()">
        </edit-client>
    </div>
</template>
<style scoped>

    .container-pay {
        display: flex;
        justify-content: space-between;
    }
    .icon-wh {
        font-size: 30px;
    }
</style>

<script>
import { get } from 'jquery';
import CreateOrder from './form.vue'
import EditClient from './partials/edit-client.vue'
    export default {
        data() {
            return {
                showDialog: false,
                resource: 'payment-orders',
                clients: [],
                clientPlans: [],
                records: [],                
                filters: {},
                status: [],
                payments_records: [],
                showDialogOrder: false,
                showDialogPayment: false,
                showDialogEditClient: false,
                recordId: null,
                check_active_cron: false,
                isFiltersVisible: false,
                originalFilters: {},
            }
        },
        components: {
            CreateOrder,
            EditClient
        },
       async created() {            
            await this.getTables()
            this.initFilters();
            await this.getRecords()
            if (!this.check_active_cron) {
                    this.$notify({
                        title: 'Información',
                        message: "Debe habilitar el cron desde configuraciones para que este modulo funcione correctamente",
                        type: 'warning'
                    });
            }
        },
        computed: {
            getClient() {
              return this.clientPlans.find(client => client.id === this.filters.client_id) || {};
            },
            sortedPayments() {
              const order = [2, 1, 3, 4];
              return this.payments_records.slice().sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
            },
            filtersChanged() {
              return JSON.stringify(this.filters) !== JSON.stringify(this.originalFilters);
            },
            sortedClientPays() {
              if (!this.getClient?.pays) return [];
              const order = [2, 1, 3, 4]; // Pagado, Pendiente, Vencido, Anulado
              return this.getClient.pays.slice().sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
            },
        },
        methods: {
            initForm() {
                this.form = {}
            },
            initFilters() {
                this.filters = {}
                this.originalFilters = { ...this.filters };
            },
            toggleFilters() {
                this.isFiltersVisible = !this.isFiltersVisible;
            },
            reload() {
                this.getTables()
                this.getRecords()
            },
            viewButtonCancel(record)
            {
                return record.order_state_id != 4 || record.order_state_id == 1 
            },
            viewButtonPay(record)
            {
                return record.order_state_id == 1  || record.order_state_id == 3
            },
            getClassState(state_order_id)
            {
                switch(state_order_id) {
                    case 1:
                        return 'badge badge-warning'
                    case 2:
                        return 'badge badge-success'
                    case 3:
                        return 'badge badge-danger'
                    case 4:
                        return 'badge badge-dark'
                }
            },
            getClassStatePay(state_order_id)
            {
                switch(state_order_id) {
                    case 1:
                        return 'var(--warning)'
                    case 2:
                        return 'var(--success)'
                    case 3:
                        return 'var(--danger)'
                    case 4:
                        return 'var(--muted)'
                }
            },
            getTextClassPay(state_order_id) {
              switch (state_order_id) {
                case 1:
                  return 'text-warning'
                case 2:
                  return 'text-success'
                case 3:
                  return 'text-danger'
                case 4:
                  return 'text-secondary'
              }
            },
            async getTables() {
                await this.$http.get(`/${this.resource}/tables`)
                    .then(response => {
                        this.clients = response.data.clients                         
                        this.clientPlans = response.data.clientPlans
                        this.status = response.data.status
                        this.check_active_cron = response.data.active_cron
                    })
            },
            async getRecords() {
              await this.$http.get(`/${this.resource}/records`, { params: this.filters })
                .then(response => {
                  this.records = response.data.data.map(record => {
                    if (record.amount !== null && record.amount !== undefined) {
                      record.amount = parseFloat(record.amount).toFixed(2)
                    }
                    return record
                  })
                  this.payments_records = response.data.pays
                })
            },
            actionsPaymentOrder(action, id)
            {
                this.$http.get(`/${this.resource}/${action}/${id}`)
                    .then(response => {
                        if (response.data.success) {
                            this.$message({
                                message: response.data.message,
                                type: 'success'
                            });
                            this.reload()
                        } else {
                            this.$message({
                                message: response.data.message,
                                type: 'error'
                            });
                        }

                    })
            },
            updateTable(id){
                let it = this.records.find(item => item.id === id);
                let form = {
                    id,
                    price: it.amount,
                    date_of_due: it.due_date,
                }
                this.$http.post(`/${this.resource}/updateTable`, form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message({
                                message: response.data.message,
                                type: 'success'
                            });
                            this.reload()
                        }
                    })
            },
            searchRemoteCustomers(query) {
                if (query.length < 3) {
                    return;
                }
                this.$http.get(`/clients/search`, { params: { query } })
                    .then(response => {
                        this.clients = response.data.clients;
                    });
            },
            getIconPay(id) {
              switch (id) {
                case 1:
                  return `
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-clock-hour-4 icon-transparent"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 2.66a1 1 0 0 0 -1 1v5.026l.009 .105l.02 .107l.04 .129l.048 .102l.046 .078l.042 .06l.069 .08l.088 .083l.083 .062l3 2a1 1 0 1 0 1.11 -1.664l-2.555 -1.704v-4.464a1 1 0 0 0 -.883 -.993z" /></svg>`; // 💳 Tarjeta (ej. Pendiente)
                case 2:
                  return `
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-circle-check icon-transparent"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -14.995 8.984l-.005 -.324l.005 -.324a10 10 0 0 1 14.995 -8.336zm-1.293 5.953a1 1 0 0 0 -1.32 -.083l-.094 .083l-3.293 3.292l-1.293 -1.292l-.094 -.083a1 1 0 0 0 -1.403 1.403l.083 .094l2 2l.094 .083a1 1 0 0 0 1.226 0l.094 -.083l4 -4l.083 -.094a1 1 0 0 0 -.083 -1.32z" /></svg>`; // ✅ Pagado
                case 3:
                  return `
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-alert-triangle icon-transparent"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 1.67c.955 0 1.845 .467 2.39 1.247l.105 .16l8.114 13.548a2.914 2.914 0 0 1 -2.307 4.363l-.195 .008h-16.225a2.914 2.914 0 0 1 -2.582 -4.2l.099 -.185l8.11 -13.538a2.914 2.914 0 0 1 2.491 -1.403zm.01 13.33l-.127 .007a1 1 0 0 0 0 1.986l.117 .007l.127 -.007a1 1 0 0 0 0 -1.986l-.117 -.007zm-.01 -7a1 1 0 0 0 -.993 .883l-.007 .117v4l.007 .117a1 1 0 0 0 1.986 0l.007 -.117v-4l-.007 -.117a1 1 0 0 0 -.993 -.883z" /></svg>`; // ❌ Anulado o Error
                case 4:
                  return `
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-ban icon-transparent-ban"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M5.7 5.7l12.6 12.6" /></svg>`; // ⚪ Otro estado
                default:
                  return '';
              }
            },
            formatDecimal(record) {
              if (record.amount === '' || record.amount === null || record.amount === undefined) return;
              const val = parseFloat(record.amount);
              if (!isNaN(val)) {
                record.amount = val.toFixed(2);
              } else {
                record.amount = '0.00';
              }
            }
        }
    }
</script>
