<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span> Listado de afectación para productos </span></li>
            </ol>
        </div>
        <div class="card tab-content-default row-new">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Listado de Atributos</h3>
            </div> -->
            <div class="card-body">                
                <div class="col-md-12">
                <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>
                <div class="table-responsive" ref="scrollContainer">
                    <table class="table">
                        <thead>
                        <tr width="100%">
                            <th width="5%">#</th>
                            <th width="50%">Descripción</th>
                            <th width="5%">Activo</th>

                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(row, index) in records" :key="index">
                            <td>{{ index + 1 }}</td>
                            <td>{{ row.description }}</td>
                            <td class="text-center">
                                <el-switch
                                    @change="changeActive(index)"
                                    v-model="row.active"
                                    >
                                </el-switch>

                            </td>
                            <!-- <td class="text-end">
                                <button type="button" class="btn waves-effect waves-light btn-xs btn-info me-1" @click.prevent="clickCreate(row.id)">Editar</button>
                                  <template v-if="typeUser === 'admin'">
                                    <button type="button" class="btn waves-effect waves-light btn-xs btn-danger"  @click.prevent="clickDelete(row.id)">Eliminar</button>
                                  </template>
                            </td> -->
                        </tr>
                        </tbody>
                    </table>
                </div>
                </div>                
                <!-- <div class="row">
                    <div class="col">
                        <button type="button" class="btn btn-custom btn-sm  mt-2 mr-2" @click.prevent="clickCreate()"><i class="fa fa-plus-circle"></i> Nuevo</button>
                    </div>
                </div> -->
            </div>
    

        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            resource: 'item-affectations-igv',
            records: [],
        }
    },
    created(){
        this.getRecords();

    },
    methods:{
        getRecords(){
            this.$http.get(`${this.resource}/records`).then(response => {
                this.records = response.data;
            }).catch(error => {
                console.error(error);
            });
        },
        changeActive(index){
            const record = this.records[index];
            const active = Number(record.active);
            
            this.$http.get(`${this.resource}/active/${record.id}/${active}`).then(response => {
                if (response.data.success) {
                    this.$message.success(response.data.message);
                } else {
                    this.$message.error(response.data.message);
                    record.active = !record.active; 
                }
            }).catch(error => {
            });

        }
    }
}
</script>