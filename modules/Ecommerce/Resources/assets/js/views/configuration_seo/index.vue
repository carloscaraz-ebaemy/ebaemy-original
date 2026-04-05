<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config shadow-sm">
      <div class="card-header bg-info text-white">
        <h3 class="my-0">
          <i class="el-icon-picture mr-2"></i>
          SEO & Redes Sociales
        </h3>
      </div>

      <div class="card-body">
        <form autocomplete="off" @submit.prevent="submit">
          <el-tabs v-model="activeTab" type="border-card">

            <el-tab-pane label="Google" name="google">
              <div class="row mt-3">
                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">SEO Title</label>
                  <el-input v-model="form.seo_title" maxlength="60" show-word-limit />
                  <small :class="seoTitleClass">
                    {{ (form.seo_title || '').length }}/60 caracteres
                  </small>
                </div>

                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">SEO Description</label>
                  <el-input type="textarea" :rows="3" v-model="form.seo_description" maxlength="160" show-word-limit />
                  <small :class="seoDescriptionClass">
                    {{ (form.seo_description || '').length }}/160 caracteres
                  </small>
                </div>

                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">Palabras clave (Keywords)</label>
                  <el-input type="textarea" :rows="2" v-model="form.seo_keywords" maxlength="300" show-word-limit
                    placeholder="decoracion, hogar, muebles, plantas artificiales, tienda online" />
                  <small class="text-muted">Separadas por comas. Ayudan a Google a entender de qué trata tu tienda.</small>
                </div>

                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">Indexación</label>
                  <br>
                  <el-switch v-model="form.indexable" active-text="Indexar en Google" inactive-text="No indexar" />
                </div>

                <div class="col-md-12 mt-4">
                  <label class="font-weight-bold mb-2 d-block" style="font-size:12px;color:#6b7280">Vista previa en Google</label>
                  <div class="google-preview p-3 border rounded">
                    <div class="google-title">{{ form.seo_title || 'Título de ejemplo para Google' }}</div>
                    <div class="google-url">{{ siteUrl }}</div>
                    <div class="google-description">
                      {{ form.seo_description || 'Descripción de ejemplo que aparecerá en los resultados de búsqueda.' }}
                    </div>
                  </div>
                </div>
              </div>
            </el-tab-pane>

            <el-tab-pane label="Facebook / WhatsApp" name="facebook">
              <div class="row mt-3">
                <div class="col-md-12 form-group">
                  <label class="font-weight-bold d-block">Imagen de Compartición (1200x630)</label>
                  <el-input v-model="form.og_image" :readonly="true">
                    <el-upload slot="append" :headers="headers" :data="{ 'type': 'og_image' }"
                      action="/ecommerce/uploads" :show-file-list="false" :on-success="successUpload"
                      :on-error="errorUpload">
                      <el-button type="primary" icon="el-icon-upload"></el-button>
                    </el-upload>
                  </el-input>

                  <div class="preview-box mx-auto border rounded mt-2" v-if="form.og_image">
                    <img :src="`/storage/uploads/logos/${form.og_image}?t=${image_timestamp}`" />
                  </div>
                  <div v-else class="preview-box mx-auto border rounded mt-2 text-muted">1200 x 630 px</div>
                </div>

                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">OG Title</label>
                  <el-input v-model="form.og_title" maxlength="95" show-word-limit />
                </div>

                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">OG Description</label>
                  <el-input type="textarea" :rows="2" v-model="form.og_description" maxlength="200" show-word-limit />
                </div>
                <div class="col-md-12 form-group ">
                  <label class="font-weight-bold">
                    Verificación Google Search Console
                  </label>

                  <el-input v-model="form.google_site_verification"
                    placeholder="Ejemplo: tmkonoTve89fzcKv7sJ59wPRHjY1PX0Y8PCbh0i4WKQ" clearable>
                  </el-input>

                  <small class="text-muted">
                    Pega solo el código del content, no el meta completo.
                  </small>
                </div>


              </div>
            </el-tab-pane>

            <el-tab-pane label="Twitter (X)" name="twitter">
              <div class="row mt-3">
                <div class="col-md-12 form-group">
                  <label class="font-weight-bold d-block">Imagen Twitter</label>
                  <el-input v-model="form.twitter_image" :readonly="true">
                    <el-upload slot="append" :headers="headers" :data="{ 'type': 'twitter_image' }"
                      action="/ecommerce/uploads" :show-file-list="false" :on-success="successUpload"
                      :on-error="errorUpload">
                      <el-button type="primary" icon="el-icon-upload"></el-button>
                    </el-upload>
                  </el-input>

                  <div class="preview-box mx-auto border rounded mt-2" v-if="form.twitter_image">
                    <img :src="`/storage/uploads/logos/${form.twitter_image}`" />
                  </div>
                </div>

                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">Twitter Title</label>
                  <el-input v-model="form.twitter_title" maxlength="70" show-word-limit />
                </div>

                <div class="col-md-12 form-group">
                  <label class="font-weight-bold">Twitter Description</label>
                  <el-input type="textarea" :rows="2" v-model="form.twitter_description" maxlength="200"
                    show-word-limit />
                </div>
              </div>
            </el-tab-pane>
          </el-tabs>

          <div class="text-end pt-3">
            <el-button type="primary" native-type="submit" :loading="loading_submit">
              Guardar Configuración
            </el-button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>


<script>export default {
  data() {
    return {
      headers: headers_token,
      activeTab: 'google',
      loading_submit: false,
      resource: "ecommerce",
      siteUrl: window.location.origin,
      form: {},
      errors: {},
      image_timestamp: new Date().getTime()
    };
  },

  computed: {
    seoTitleClass() {
      const length = (this.form.seo_title || "").length;
      return length <= 60 ? 'text-success' : 'text-danger';
    },
    seoDescriptionClass() {
      const length = (this.form.seo_description || "").length;
      return length <= 160 ? 'text-success' : 'text-danger';
    }
  },

  async created() {
    this.initForm();
    await this.loadData();
  },

  methods: {

    successUpload(response) {
      if (response.success) {
        this.$message.success(response.message);
        this.form[response.type] = response.name;
        this.image_timestamp = new Date().getTime();
      } else {
        this.$message.error("Error al subir el archivo");
      }
    },

    errorUpload() {
      this.$message.error('Error al subir el archivo');
    },

    initForm() {
      this.form = {
        id: null,
        seo_title: "",
        seo_description: "",
        seo_keywords: "",
        og_image: null,
        twitter_image: null,
        google_site_verification: null,
        indexable: true
      };
    },

    async loadData() {
      try {
        const response = await this.$http.get(`/${this.resource}/record`);
        if (response.data && response.data.data) {
          this.form = Object.assign({}, this.form, response.data.data);
          this.cleanImageFieldName('og_image');
          this.cleanImageFieldName('twitter_image');
        }
      } catch (error) {
        console.error("Error al cargar configuración:", error);
      }
    },

    cleanImageFieldName(field) {
      if (this.form[field] && String(this.form[field]).includes('/')) {
        this.form[field] = this.form[field].split('/').pop();
      }
    },

    // 🔥 NUEVO: Limpia si pegan el meta completo
    cleanGoogleVerification() {
      if (!this.form.google_site_verification) return;

      let value = this.form.google_site_verification.trim();

      // Si pegan el meta completo
      const match = value.match(/content="([^"]+)"/);
      if (match) {
        value = match[1];
      }

      this.form.google_site_verification = value || null;
    },

    async submit() {
      this.loading_submit = true;

      // 🔥 Limpiar antes de enviar
      this.cleanGoogleVerification();

      const seoFields = [
        'id', 'seo_title', 'seo_description', 'seo_keywords', 'seo_author', 'seo_robots',
        'og_title', 'og_description', 'og_image', 'og_type',
        'twitter_title', 'twitter_description', 'twitter_image', 'twitter_card',
        'canonical_url', 'indexable', 'schema_json',
        'google_site_verification'
      ];

      let dataToSend = {};

      seoFields.forEach(key => {
        if (this.form[key] !== undefined) {
          let value = this.form[key];

          if (key === 'indexable') {
            dataToSend[key] = value ? 1 : 0;
          } else {
            dataToSend[key] = (value === "" || value === null) ? null : value;
          }
        }
      });

      try {
        const response = await this.$http.post(`/${this.resource}/configuration/seo`, dataToSend);

        if (response.data.success) {
          this.$message.success(response.data.message);
          await this.loadData();
        }

      } catch (error) {
        if (error.response && error.response.status === 422) {
          const errors = error.response.data.errors;
          const firstErr = Object.keys(errors)[0];
          this.$message.error(`Error en ${firstErr}: ${errors[firstErr][0]}`);
        }
      } finally {
        this.loading_submit = false;
      }
    }
  }
};
</script>
<style scoped>
.preview-box {
  width: 100%;
  max-width: 400px;
  min-height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9f9f9;
  overflow: hidden;
}

.preview-box img {
  width: 100%;
  height: auto;
  object-fit: cover;
}

.google-preview {
  background: #fff;
  font-family: arial, sans-serif;
}

.google-title {
  color: #1a0dab;
  font-size: 18px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.google-url {
  color: #006621;
  font-size: 14px;
}

.google-description {
  color: #545454;
  font-size: 14px;
  line-height: 1.4;
}
</style>