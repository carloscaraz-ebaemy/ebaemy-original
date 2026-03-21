<template>
    <el-dialog :visible="showDialog" @close="close" @open="getData" width="65%" :close-on-click-modal="false" :close-on-press-escape="false">
        <span slot="title" class="d-flex justify-content-between h5 p-3"> {{title}}
            <el-button type="primary" icon="el-icon-plus" @click="clickAddRow" :loading="loadingButton" v-if="showAddButton">Agregar Despacho</el-button>
        </span>
        <div class="form-body">
            <div class="row">
                <div class="col-md-12" v-if="records.length > 0">
                    <div class="table-responsive table-sm">
                        <table class="table">
                            <thead>
                            <tr>
                                <!-- <th></th> -->
                                <th>#</th>
                                <th>Tipo de entrega</th>
                                <th>Fecha de despacho</th>
                                <th>Hora de despacho</th>
                                <th>Persona quien recogio</th>
                                <th>Referencia</th>
                                <th>Personal que despacho</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in records" :key="index">
                                <template v-if="row.id">
                                    <td><span class="badge bg-secondary">DESPACHO-{{ row.id }}</span></td>
                                    <td>
                                        <span :class="row.type ? 'badge bg-success' : 'badge bg-warning text-dark'">
                                            {{ row.type == null ? '—' : (row.type ? 'Entregado' : 'Parcial') }}
                                        </span>
                                    </td>
                                    <td>{{ row.date_dispatch }}</td>
                                    <td>{{ row.time_dispatch }}</td>
                                    <td>{{ row.person_pick }}</td>
                                    <td>{{ row.reference }}</td>
                                    <td class="text-right">{{ row.person_dispatch }}</td>
                                    <td class="series-table-actions text-right">
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)" v-if="typeUser === 'admin'">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </template>
                                <template v-else>
                                    <td></td>
                                    <td>
                                        <el-select v-model="row.status">
                                            <el-option
                                                v-for="option in options_status"
                                                :key="option.value"
                                                :value="option.value"
                                                :label="option.description"
                                                >
                                            </el-option>
                                        </el-select>
                                    </td>
                                    <td>{{ row.date_dispatch }}</td>
                                    <td>{{ row.time_dispatch }} </td>
                                    <td>
                                        <div class="form-group mb-0 d-flex align-items-center" style="gap:4px;" :class="{'has-danger': row.errors.person_pick}">
                                            <el-select
                                                v-model="row.person_pick"
                                                filterable
                                                remote
                                                clearable
                                                :remote-method="searchPersons"
                                                :loading="loadingPersons"
                                                placeholder="Buscar cliente..."
                                                style="width:180px;"
                                            >
                                                <el-option
                                                    v-for="p in personOptions"
                                                    :key="p.id"
                                                    :value="p.name"
                                                    :label="p.description"
                                                ></el-option>
                                            </el-select>
                                            <el-button
                                                type="primary"
                                                icon="el-icon-plus"
                                                size="mini"
                                                circle
                                                @click="showDialogNewPerson = true"
                                                title="Agregar nuevo cliente"
                                            ></el-button>
                                            <small class="form-control-feedback" v-if="row.errors.person_pick" v-text="row.errors.person_pick[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.reference}">
                                            <el-input v-model="row.reference"></el-input>
                                            <small class="form-control-feedback" v-if="row.errors.reference" v-text="row.errors.reference[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.person_dispatch}">
                                            <el-input v-model="row.person_dispatch"></el-input>
                                            <small class="form-control-feedback" v-if="row.errors.person_dispatch" v-text="row.errors.person_dispatch[0]"></small>
                                        </div>
                                    </td>
                                    <td class="series-table-actions text-right">
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="clickSubmit(index)">
                                            <i class="fa fa-check"></i>
                                        </button>
                                        
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickCancel(index)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </template>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- <div class="col-md-12 pt-2">
                    <div class="d-flex">
                        <div class="d-flex">
                            <div class="d-flex flex-column">
                                <el-radio v-model="status_display" @change="statusUpdate" :checked="checked_display" label="1">Entregado</el-radio>
                                <el-radio v-model="status_display" @change="statusUpdate" :checked="checked_display" label="0">Parcial</el-radio>
                            </div>
                            <button v-if="typeUser != 'seller'" type="button" class="btn waves-effect waves-light btn-xs btn-light" @click.prevent="statusUpdate('initial')">Borrar Check</button>
                        </div>
                        <div class="w-100 text-center">
                        </div>
                    </div>
                    
                </div> -->
                
            </div>
        </div>
        <tenant-person-form
            :showDialog.sync="showDialogNewPerson"
            type="customers"
            :external="true"
            :input_person="personSearchTerm"
        ></tenant-person-form>
    </el-dialog>

</template>

<style>
.el-upload-list__item-name [class^="el-icon"] {
    display: none;
}
.el-upload-list__item-name {
    margin-right: 25px;
}
.el-upload-list__item {
    font-size: 10px;
}
</style>

<script>

    import {deletable} from '../../../../mixins/deletable'
    import moment from 'moment'

    export default {
        props: ['showDialog', 'documentId', 'typeUser', 'statusDispatch'],
        mixins: [deletable],
        data() {
            return {
                title: null,
                resource: 'sale-notes',
                records: [],
                loadingButton: true,
                showAddButton: true,
                document: {},
                selecteds:[],
                dispatch_active:false,
                personOptions: [],
                loadingPersons: false,
                showDialogNewPerson: false,
                personSearchTerm: '',
                options_status:[
                    {
                        description: 'PARCIAL',
                        value: '0', //segun estructura del campo
                        label: '0'
                    }, {
                        description: 'ENTREGADO',
                        value: '1',
                        label: '1'
                    }
                ]
            }
        },
        async created()
        {
            await this.initForm()
            this.$eventHub.$on('reloadDataPersons', (customer_id) => {
                this.$http.get(`/${this.resource}/search/customer/${customer_id}`)
                    .then(response => {
                        if (response.data.customers && response.data.customers.length > 0) {
                            const person = response.data.customers[0];
                            this.personOptions = response.data.customers;
                            const editRow = this.records.find(r => r.id === null);
                            if (editRow) {
                                editRow.person_pick = person.name;
                            }
                        }
                    });
            });
        },
        methods: {
            initForm() {
                this.records = []
                this.showAddButton = true
            },
            async getData()
            {
                this.initForm()
                // dispatch sale notes
                await this.$http.get(`/${this.resource}/dispatch/${this.documentId}`)
                    .then(response => {
                        this.document = response.data
                        this.records = response.data.records
                        this.loadingButton = false
                        this.title = 'Estado de despacho del comprobante: '+this.document.number_full;
                    })
            },
            clickAddRow() 
            {
                if(this.document.status_dispatch === 'ENTREGADO')
                {
                    return this.$message.error('No puede agregar despachos, el documento tiene estado ENTREGADO');
                }

                this.records.push({
                    id: null,
                    date_dispatch: moment().format("YYYY/MM/DD"),
                    time_dispatch: moment().format('HH:mm:ss'),
                    person_pick:null,
                    person_dispatch: null,
                    reference: null,
                    errors: {},
                    loading: false,
                    status: '0'
                })

                this.showAddButton = false

            },
            clickCancel(index) {
                this.records.splice(index, 1);
                this.showAddButton = true;
            },
            clickSubmit(index) {

                let form = {
                    id: this.records[index].id,
                    sale_note_id: this.documentId,
                    date_dispatch: this.records[index].date_dispatch,
                    time_dispatch: this.records[index].time_dispatch,
                    person_pick:this.records[index].person_pick,
                    person_dispatch: this.records[index].person_dispatch,
                    reference: this.records[index].reference,
                    status: this.records[index].status,
                };
                
                this.$http.post(`/${this.resource}/dispatch`, form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message);
                            this.dispatch_active=true
                            this.getData();
                            this.$eventHub.$emit('reloadData')
                            this.showAddButton = true;
                        } else {
                            this.$message.error(response.data.message);
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            this.records[index].errors = error.response.data;
                        } else {
                            console.log(error);
                        }
                    })
            },
            searchPersons(query) {
                this.personSearchTerm = query;
                if (query.length > 0) {
                    this.loadingPersons = true;
                    this.$http.get(`/${this.resource}/search/customers?input=${query}`)
                        .then(response => {
                            this.personOptions = response.data.customers;
                            this.loadingPersons = false;
                        });
                } else {
                    this.personOptions = [];
                }
            },
            close() {
                this.$emit('update:showDialog', false);
                this.loadingButton = true;
            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/dispatch/delete/${id}`).then(() =>{
                        this.getData()
                        this.$eventHub.$emit('reloadData')
                    }
                )
            },
        },
    }
</script>
