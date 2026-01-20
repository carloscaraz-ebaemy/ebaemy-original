<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config">
      <div class="card-header bg-info">
        <h3 class="my-0">SEO & Redes Sociales</h3>
      </div>
      <div class="card-body">
        <form autocomplete="off" @submit.prevent="submit">
          <div class="form-body">
            <div class="row">
              <!-- SEO GENERAL -->
              <div class="col-md-12">
                <div class="form-group" :class="{'has-danger': errors.seo_title}">
                  <label class="control-label">SEO Title</label>
                  <el-input v-model="form.seo_title"></el-input>
                  <small class="form-control-feedback" v-if="errors.seo_title" v-text="errors.seo_title[0]"></small>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group" :class="{'has-danger': errors.seo_description}">
                  <label class="control-label">SEO Description</label>
                  <el-input type="textarea" :rows="3" v-model="form.seo_description"></el-input>
                  <small class="form-control-feedback" v-if="errors.seo_description" v-text="errors.seo_description[0]"></small>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group" :class="{'has-danger': errors.seo_keywords}">
                  <label class="control-label">SEO Keywords</label>
                  <el-input v-model="form.seo_keywords"></el-input>
                  <small class="form-control-feedback" v-if="errors.seo_keywords" v-text="errors.seo_keywords[0]"></small>
                </div>
              </div>

              <!-- SEO SOCIAL -->
              <div class="col-md-12 mt-3">
                <div class="form-group" :class="{'has-danger': errors.og_title}">
                  <label class="control-label">OG Title (Facebook / WhatsApp)</label>
                  <el-input v-model="form.og_title"></el-input>
                  <small class="form-control-feedback" v-if="errors.og_title" v-text="errors.og_title[0]"></small>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group" :class="{'has-danger': errors.og_description}">
                  <label class="control-label">OG Description</label>
                  <el-input type="textarea" :rows="2" v-model="form.og_description"></el-input>
                  <small class="form-control-feedback" v-if="errors.og_description" v-text="errors.og_description[0]"></small>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group" :class="{'has-danger': errors.og_image}">
                  <label class="control-label">OG Image (path)</label>
                  <el-input v-model="form.og_image"></el-input>
                  <small class="form-control-feedback" v-if="errors.og_image" v-text="errors.og_image[0]"></small>
                </div>
              </div>

              <!-- INDEXACIÓN -->
              <div class="col-md-12 mt-2">
                <el-checkbox v-model="form.indexable">Permitir indexación en Google</el-checkbox>
              </div>
            </div>
          </div>

          <div class="form-actions text-end float-end pt-2">
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
      resource: "ecommerce",
      errors: {},
      form: {}
    };
  },
  async created() {
    this.initForm();

    try {
      const response = await this.$http.get(`/${this.resource}/record`);
      if (response.data && response.data.data) {
        const data = response.data.data;
        this.form = {
          id: data.id,
          seo_title: data.seo_title,
          seo_description: data.seo_description,
          seo_keywords: data.seo_keywords,
          og_title: data.og_title,
          og_description: data.og_description,
          og_image: data.og_image,
          indexable: data.indexable
        };
      }
    } catch (error) {
      console.error("Error al cargar los datos SEO:", error);
    }
  },
  methods: {
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        seo_title: "",
        seo_description: "",
        seo_keywords: "",
        og_title: "",
        og_description: "",
        og_image: "",
        indexable: true
      };
    },
    async submit() {
      this.loading_submit = true;
      this.errors = {};
      try {
        const response = await this.$http.post(`/${this.resource}/configuration/seo`, this.form);
        if (response.data.success) {
          this.$message.success(response.data.message);
        } else {
          this.$message.error(response.data.message);
        }
      } catch (error) {
        if (error.response && error.response.status === 422) {
          // 👈 Laravel devuelve errores en "errors"
          this.errors = error.response.data.errors || {};
        } else {
          console.error(error);
          this.$message.error('Ocurrió un error inesperado');
        }
      } finally {
        this.loading_submit = false;
      }
    }
  }
};
</script>
