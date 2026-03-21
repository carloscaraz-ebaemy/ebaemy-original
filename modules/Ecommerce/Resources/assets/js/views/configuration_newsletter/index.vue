<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config">
      <div class="card-header bg-warning text-dark">
        <h3 class="my-0">Pop-up Newsletter con Descuento</h3>
      </div>
      <div class="card-body">
        <form autocomplete="off" @submit.prevent="submit">
          <div class="form-body">
            <div class="row">

              <!-- Activar pop-up -->
              <div class="col-12 mb-3">
                <div class="form-group form-modern">
                  <el-switch
                    v-model="form.newsletter_popup_enabled"
                    :active-value="true"
                    :inactive-value="false"
                  ></el-switch>
                  <label class="ms-2 mb-0">Activar pop-up de newsletter</label>
                  <small class="d-block text-muted ms-5" style="padding: 0; line-height: 1.5;">
                    Muestra un pop-up a los 8 s de visita o cuando el usuario intenta salir de la página.
                    Se oculta por 7 días tras cerrarlo.
                  </small>
                </div>
              </div>

              <!-- Título del pop-up -->
              <div class="col-12 mb-3">
                <div class="form-group form-modern">
                  <label class="control-label">Título del pop-up</label>
                  <el-input
                    v-model="form.newsletter_popup_title"
                    placeholder="¡Obtén 10% de descuento!"
                  ></el-input>
                </div>
              </div>

              <!-- Descripción -->
              <div class="col-12 mb-3">
                <div class="form-group form-modern">
                  <label class="control-label">Descripción / Subtítulo</label>
                  <el-input
                    v-model="form.newsletter_popup_desc"
                    type="textarea"
                    :rows="2"
                    placeholder="Suscríbete y recibe un cupón exclusivo en tu primer pedido."
                  ></el-input>
                </div>
              </div>

              <!-- Código de descuento -->
              <div class="col-12 mb-3">
                <div class="form-group form-modern">
                  <label class="control-label">Código de descuento</label>
                  <el-input
                    v-model="form.newsletter_discount_code"
                    placeholder="BIENVENIDO10"
                    style="font-family: monospace;"
                  ></el-input>
                  <small class="text-muted">Se muestra al usuario después de suscribirse. Deja vacío para omitir.</small>
                </div>
              </div>

              <!-- Imagen lateral -->
              <div class="col-12 mb-3">
                <div class="form-group form-modern">
                  <label class="control-label">Imagen lateral del pop-up</label>
                  <div v-if="imagePreview" class="mb-2">
                    <img :src="imagePreview" style="max-height: 120px; border-radius: 6px; border: 1px solid #ddd;" />
                    <br>
                    <el-button type="danger" size="mini" class="mt-1" @click="removeImage">Quitar imagen</el-button>
                  </div>
                  <el-upload
                    v-else
                    action="#"
                    :auto-upload="false"
                    :show-file-list="false"
                    :on-change="handleImageChange"
                    accept="image/*"
                  >
                    <el-button size="small" type="info">
                      <i class="fas fa-image mr-1"></i> Seleccionar imagen
                    </el-button>
                  </el-upload>
                  <small class="text-muted d-block mt-1">Recomendado: 400×500 px. Se muestra a la izquierda del formulario.</small>
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

<script>
export default {
  data() {
    return {
      loading_submit: false,
      resource: 'ecommerce',
      form: {
        newsletter_popup_enabled: false,
        newsletter_popup_title: '',
        newsletter_popup_desc: '',
        newsletter_discount_code: '',
        newsletter_popup_image: null,
      },
      imagePreview: null,
      imageBase64: null,
    };
  },
  async created() {
    await this.$http.get(`/${this.resource}/record`).then(response => {
      if (response.data && response.data.data) {
        const d = response.data.data;
        this.form = {
          newsletter_popup_enabled: !!d.newsletter_popup_enabled,
          newsletter_popup_title:   d.newsletter_popup_title   || '',
          newsletter_popup_desc:    d.newsletter_popup_desc    || '',
          newsletter_discount_code: d.newsletter_discount_code || '',
          newsletter_popup_image:   null,
        };
        if (d.newsletter_popup_image) {
          this.imagePreview = d.newsletter_popup_image;
          this.form.newsletter_popup_image = d.newsletter_popup_image;
        }
      }
    });
  },
  methods: {
    handleImageChange(file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        this.imageBase64   = e.target.result;
        this.imagePreview  = e.target.result;
        this.form.newsletter_popup_image = e.target.result;
      };
      reader.readAsDataURL(file.raw);
    },
    removeImage() {
      this.imagePreview = null;
      this.imageBase64  = null;
      this.form.newsletter_popup_image = null;
    },
    submit() {
      this.loading_submit = true;
      this.$http
        .post(`/${this.resource}/configuration_newsletter`, this.form)
        .then(response => {
          if (response.data.success) {
            this.$message.success(response.data.message);
          } else {
            this.$message.error(response.data.message);
          }
        })
        .catch(error => {
          console.error(error);
          this.$message.error('Error al guardar la configuración');
        })
        .then(() => {
          this.loading_submit = false;
        });
    }
  }
};
</script>
