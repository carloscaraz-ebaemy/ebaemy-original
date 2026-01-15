<template>
  <el-dialog :close-on-click-modal="false"
             :title="titleDialog"
             :visible="showDialog"
             append-to-body
             @close="close"
             @open="create">
    <form autocomplete="off"
          @submit.prevent="submit">
      <div class="form-body">
        <div class="row">
          <div class="col-md-6">
            <div :class="{'has-danger': errors.plate_number}"
                 class="form-group">
              <label class="control-label">Nro. de Placa <span class="text-danger">*</span></label>
              <el-input v-model="form.plate_number"
                        dusk="name"
                        @input="validatePlateNumber"
                        maxlength="15"
                        placeholder="Solo letras y números"></el-input>
              <small v-if="errors.plate_number"
                     class="form-control-feedback"
                     v-text="errors.plate_number[0]"></small>
              <small v-else class="form-text text-muted">
                Solo se permiten letras y números (sin espacios ni caracteres especiales)
              </small>
            </div>
          </div>
          <div class="col-md-6">
            <div :class="{'has-danger': errors.model}"
                 class="form-group">
              <label class="control-label">Modelo</label>
              <el-input v-model="form.model"></el-input>
              <small v-if="errors.model"
                     class="form-control-feedback"
                     v-text="errors.model[0]"></small>
            </div>
          </div>
          <div class="col-md-6">
            <div :class="{'has-danger': errors.brand}"
                 class="form-group">
              <label class="control-label">Marca</label>
              <el-input v-model="form.brand"></el-input>
              <small v-if="errors.brand"
                     class="form-control-feedback"
                     v-text="errors.brand[0]"></small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group" style="margin-top: 32px;">
              <el-switch v-model="form.is_default"
                         active-text="Predeterminado"
                         inactive-text=""></el-switch>
            </div>
          </div>
          <div class="col-md-6">
            <div :class="{'has-danger': errors.tuc}"
                 class="form-group">
              <label class="control-label">Certificado de habilitación vehicular</label>
              <el-input v-model="form.tuc"
                        @input="validateTuc"
                        maxlength="15"></el-input>
              <small v-if="errors.tuc"
                     class="invalid-feedback"
                     v-text="errors.tuc[0]"></small>
            </div>
          </div>
        </div>
      </div>
      <div class="form-actions text-right mt-4">
        <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
        <el-button :loading="loading_submit"
                   native-type="submit"
                   type="primary">Guardar
        </el-button>
      </div>
    </form>
  </el-dialog>
</template>

<script>

export default {
  name: 'DispatchTransportForm',
  props: ['showDialog', 'recordId', 'external'],
  data() {
    return {
      loading_submit: false,
      titleDialog: null,
      resource: 'transports',
      errors: {},
      form: {},
    }
  },
  created() {
    this.initForm()
  },
  methods: {
    initForm() {
      this.errors = {}
      this.form = {
        id: null,
        plate_number: null,
        model: null,
        brand: null,
        is_default: false,
        is_active: true,
        tuc: null,
      }
    },
    validatePlateNumber() {
      // Eliminar cualquier caracter que no sea letra o número
      if (this.form.plate_number) {
        const cleaned = this.form.plate_number.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
        if (this.form.plate_number !== cleaned) {
          this.form.plate_number = cleaned
          this.$message.warning('Solo se permiten letras y números en el número de placa')
        }
      }
    },
    validateTuc() {
      // Limitar a alfanumérico para TUC también
      if (this.form.tuc) {
        const cleaned = this.form.tuc.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
        if (this.form.tuc !== cleaned) {
          this.form.tuc = cleaned
        }
      }
    },
    async create() {
      this.initForm();
      this.titleDialog = (this.recordId) ? 'Editar Vehículo' : 'Nuevo Vehículo'
      if (this.recordId) {
        await this.$http.get(`/${this.resource}/record/${this.recordId}`)
            .then(response => {
              this.form = response.data.data
            })
      }
    },
    async submit() {
      // Validación del número de placa
      if (!this.form.plate_number) {
        return this.$message.error('El número de placa es obligatorio')
      }

      // Limpiar y validar placa
      this.form.plate_number = this.form.plate_number.trim().toUpperCase()
      
      const plateRegex = /^[A-Z0-9]+$/
      if (!plateRegex.test(this.form.plate_number)) {
        return this.$message.error('El número de placa solo debe contener letras y números (sin espacios, guiones u otros caracteres)')
      }

      if (this.form.plate_number.length < 5 || this.form.plate_number.length > 15) {
        return this.$message.error('El número de placa debe tener entre 5 y 15 caracteres')
      }

      // Validación del TUC
      if (this.form.tuc) {
        this.form.tuc = this.form.tuc.trim().toUpperCase()
        
        const tucRegex = /^[A-Z0-9]+$/
        if (!tucRegex.test(this.form.tuc)) {
          return this.$message.error('El certificado de habilitación vehicular solo debe contener letras y números')
        }
        
        if (this.form.tuc.length < 10 || this.form.tuc.length > 15) {
          return this.$message.error('El certificado de habilitación vehicular debe tener entre 10 y 15 caracteres')
        }
      }
    
      this.loading_submit = true
      await this.$http.post(`/${this.resource}`, this.form)
          .then(response => {
            if (response.data.success) {
              this.$message.success(response.data.message)
              this.$emit('success', response.data.id)
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
            }
          })
          .then(() => {
            this.loading_submit = false
          })
    },
    close() {
      this.$emit('update:showDialog', false)
    },
  }
}
</script>

<style scoped>
.form-text {
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
</style>