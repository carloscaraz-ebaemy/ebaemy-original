<template>
  <div class="col-lg-6 col-md-12 0">
    <div class="card card-config">
      <div class="card-header bg-info">
        <h3 class="my-0">Configuración de Tienda Virtual y Restaurante</h3>
      </div>
      <div class="card-body">
        <form autocomplete="off" @submit.prevent="submit">
          <div class="form-body">
            <div class="row">
              <div class="col-12">
                <div class="form-group form-modern" :class="{'has-danger': errors.script_paypal}">
                  <label class="control-label">
                    Color Principal de la Tienda
                  </label>
                  <el-color-picker class="col-12 px-0" size="medium"  v-model="form.color_ecommerce"></el-color-picker>
                  <!-- <el-input v-model="form.color_ecommerce" placeholder="Please input" :disabled="true">
                    <template slot="prepend">#</template>
                  </el-input> -->
                </div>
              </div>
              <div class="col-12 mt-3 mb-0">
                <h5 class="mb-3 text-muted">Preferencias del Banner Principal</h5>
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.full_width_banner" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Activar ancho completo del banner</label>
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Las imágenes del carrusel ocuparán el 100% del ancho de la pantalla. 
                    Aseguresé que sus imágenes tenga la proporción 5:2
                  </small>
                </div>                
              </div>
              <div class="col-12 my-3">
                <h5 class="mb-3 text-muted">Preferencias de Visualización</h5>
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.show_description" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Mostrar descripción del producto</label>
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Muestra el nombre adicional o descripción corta debajo del título del producto</small>
                </div>
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.show_stock" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Mostrar stock disponible</label>
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Muestra la cantidad disponible en inventario de cada producto</small>
                </div>
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.only_available_products" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Ocultar productos sin stock</label>                  
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Los productos agotados no aparecerán en el catálogo de la tienda</small>
                </div>
              </div>              
            </div>
          </div>
          <div class="form-actions text-end pt-2">
            <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
<style>
.el-color-picker__trigger {
    width: 100% !important;
    padding: 0;
}
</style>
<script>
export default {
  data() {
    return {
      loading_submit: false,
      // headers: headers_token,
      resource: "ecommerce",
      errors: {},
      form: {},
      soap_sends: [],
      soap_types: []
    };
  },
  async created() {
    await this.$http.get(`/${this.resource}/record`).then(response => {
      if (response.data !== "") {
        let data = response.data.data;
        
        // Cargar preferencias si existen
        let preferences = { show_description: 1, show_stock: 0, only_available_products: 0, full_width_banner: 0 };
        if (data.preferences) {
          const prefs = typeof data.preferences === 'string' 
            ? JSON.parse(data.preferences) 
            : data.preferences;
          preferences = prefs;
        }
        
        // Inicializar form con todos los datos de una vez
        this.form = {
          id: data.id,
          color_ecommerce: data.color_ecommerce,
          show_description: parseInt(preferences.show_description) || 0,
          show_stock: parseInt(preferences.show_stock) || 0,
          only_available_products: parseInt(preferences.only_available_products) || 0,
          full_width_banner: parseInt(preferences.full_width_banner) || 0
        };
      } else {
        this.initForm();
      }
    });
  },
  methods: {
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        color_ecommerce: null,
        show_description: 1,
        show_stock: 0,
        only_available_products: 0,
        full_width_banner: 0
      };
    },
    submit() {
      this.loading_submit = true;
      this.$http
        .post(`/${this.resource}/configuration_color`, this.form)
        .then(response => {
          if (response.data.success) {
            this.$message.success(response.data.message);
          } else {
            this.$message.error(response.data.message);
          }
        })
        .catch(error => {
          if (error.response.status === 422) {
            this.errors = error.response.data;
          } else {
            console.log(error);
          }
        })
        .then(() => {
          this.loading_submit = false;
        });
    }
  }
};
</script>



