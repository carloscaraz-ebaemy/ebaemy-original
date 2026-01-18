<template>
  <div>
    <h3 class="mb-3">Entornos de Mesas</h3>
    <div class="row">
      <div class="col-md-6 mb-4" v-for="(environment, index) in environments" :key="index">
        <div class="card-body bg-light m-0">
          <div class="d-flex justify-content-between mb-2">
            <h4 class="text-dark">Ambiente: <b class="mr-2">{{ environment.original_name }}</b></h4>
            <el-tooltip class="item"
              content="Editar Nombre de Ambiente"
              effect="dark"
              placement="top-start"
              v-if="environment.can_edit"
              v-show="!environment.is_editing">
              <el-button class="second-buton btn btn-sm float-right" @click="enableEdit(environment)">
                <i class="el-icon-edit"></i>
              </el-button>
            </el-tooltip>
          </div>
          <div v-show="environment.is_editing" class="row">
            <div class="col-8 form-group" :class="{'has-danger': errors.number}">
              <el-input v-model="environment.name" :maxlength="30"></el-input>
              <small class="form-control-feedback" v-if="errors.number" v-text="errors.number[0]"></small>
            </div>
            <div class="col-4">
              <el-button type="primary" class="btn btn-md mx-1" @click.prevent="changeEnvironment(environment)">
                <i class="fa fa-check"></i>
              </el-button>
              <el-button type="danger" class="btn btn-md" @click.prevent="clickCancelEnvironment(environment.id)">
                <i class="el-icon-close"></i>
              </el-button>
            </div>
          </div>
          <label class="control-label">
            Habilitar ambiente
          </label>
          <div :class="{'has-danger': errors.active}" class="form-group tables-restaurant">
            <el-switch v-model="environment.active"
              active-text="Si"
              inactive-text="No"
              @change="changeEnvironment(environment)"
              :disabled="!environment.can_deactivate"></el-switch>
            <small v-if="errors.active"
                    class="form-control-feedback"
                    v-text="errors.active[0]"></small>
            <br><br>
            <template v-if="!environment.is_delivery && !environment.is_takeaway">
              <label class="control-label tables-quantity">Cantidad de mesas <b>{{ environment.tables_quantity }}</b></label>
              <el-slider
                v-model="environment.tables_quantity"
                :step="1"
                :min="2"
                :max="50"
                show-stops
                @change="changeEnvironment(environment)">
              </el-slider>
            </template>
          </div>
        </div>
      </div>
      <div class="col-md-6 mb-4">
        <div class="card-body bg-light m-0">
          <h4 class="text-dark">Crear Nuevo Ambiente</h4>
          <div class="form-group">
            <label>Nombre del Ambiente</label>
            <el-input v-model="newEnvironment.name" :maxlength="30" placeholder="Ingrese el nombre"></el-input>
          </div>
          <label class="control-label tables-quantity">Cantidad de mesas <b>{{ newEnvironment.tables_quantity }}</b></label>
          <el-slider
            v-model="newEnvironment.tables_quantity"
            :step="1"
            :min="5"
            :max="50"
            show-stops>
          </el-slider>
          <br>
          <el-button @click="createEnvironment" class="btn btn-primary" :disabled="!newEnvironment.name.trim()">
            <i class="fa fa-plus"></i> Crear Ambiente
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      resource: 'restaurant',
      environments: [],
      errors: {},
      newEnvironment: {
        name: '',
        tables_quantity: 10
      }
    }
  },
  mounted() {
    this.getRestaurantEnvironments()
  },
  methods: {
    getRestaurantEnvironments() {
      this.$http.get(`/${this.resource}/configuration/get-envs`)
        .then(({data}) => {
          this.environments = data.data
        })
    },
    enableEdit(environment) {
      environment.is_editing = true
    },
    clickCancelEnvironment(id) {
      let environment = this.environments.find(env => env.id === id)
      environment.name = environment.original_name
      environment.is_editing = false
    },
    changeEnvironment(environment) {
      this.$http.post(`/${this.resource}/configuration/update-envs`, environment)
        .then(({data}) => {
          this.$message.success(data.message);
          environment = data.data
        })
        .catch(({response}) => {
          this.errors = response.data.errors || {}
        })
    },
    createEnvironment() {
      this.$http.post(`/${this.resource}/configuration/create-envs`, {
        name: this.newEnvironment.name,
        tables_quantity: this.newEnvironment.tables_quantity
      })
        .then(({data}) => {
          this.$message.success(data.message);
          this.getRestaurantEnvironments();
          this.newEnvironment = { name: '', tables_quantity: 10 };
        })
        .catch(({response}) => {
          this.errors = response.data.errors || {};
        })
    }
  }
}
</script>