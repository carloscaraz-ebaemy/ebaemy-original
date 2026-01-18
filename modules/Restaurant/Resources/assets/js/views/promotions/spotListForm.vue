<template>
  <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create">
    <form autocomplete="off" @submit.prevent="submit">
      <div class="form-body">
        <div class="row">
          <div class="col-12 col-sm-6">
            <div class="col-12">
              <div class="form-group banner-img" :class="{'has-danger': errors.image}">
                <label class="control-label">
                  Imágen
                  <span class="text-danger"></span>
                  <div class="sub-title text-danger">
                    <small>Se requiere resoluciones 645x165</small>
                  </div>
                </label>
                <el-upload
                  class="avatar-uploader"
                  :data="{'type': 'spots'}"
                  :headers="headers"
                  :action="`/restaurant/promotions/upload`"
                  :show-file-list="false"
                  :on-success="onSuccess"
                >
                  <img v-if="form.image_url" :src="form.image_url" class="avatar" />
                  <i v-else class="el-icon-plus avatar-uploader-icon"></i>
                </el-upload>
                <div class="sub-title text-muted">
                  <small>Se permiten imágenes en formato <strong>JPG, PNG y GIF</strong>.
                  Estas se mostrarán en la parte inferior de la tienda.</small>
                </div>
                <small class="form-control-feedback" v-if="errors.image" v-text="errors.image[0]"></small>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6">
            <div class="col-12">
              <div class="form-group" :class="{'has-danger': errors.name}">
                <label class="control-label">Nombre</label>
                <el-input v-model="form.name" placeholder="Ingrese el nombre del anuncio">
                </el-input>
                <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group" :class="{'has-danger': errors.spot_url}">
                <label class="control-label">
                  Enlace del anuncio
                  <el-tooltip
                    content="Puedes colocar un enlace o link que será el destino del anuncio publicitario al hacer clic."
                    placement="top"
                    effect="dark"
                  >
                    <i class="fa fa-info-circle"></i>
                  </el-tooltip>
                </label>
                <el-input v-model="form.spot_url" placeholder="Ingrese la URL del anuncio">
                </el-input>
                <small
                  class="form-control-feedback"
                  v-if="errors.spot_url"
                  v-text="errors.spot_url[0]"
                ></small>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="form-actions text-end mt-4">
        <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
        <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
      </div>
    </form>
  </el-dialog>
</template>

<script>
export default {
  props: ["showDialog", "recordId"],
  data() {
      return {
          items: [],
          headers: headers_token,
          loading_submit: false,
          titleDialog: null,
          resource: "spot-list",
          errors: {},
          form: {}
      };
  },
  created() {
      this.initForm();
      this.loadItems();
  },
  methods: {
      initForm() {
          this.errors = {};
          this.form = {
              name: null,
              description: null,
              image: null,
              image_url: null,
              temp_path: null,
              spot_url: null,
              type: "spots"
          };
      },
      loadItems() {
          // No necesitamos cargar items para spots
      },
      create() {
          this.titleDialog = this.recordId ? 'Editar Promoción' : 'Nueva Promoción';
          
          if (this.recordId) {
              this.$http.get(`/restaurant/${this.resource}/record/${this.recordId}`)
                  .then(response => {
                      this.form = response.data.data;
                  });
          }
      },
      submit() {
          this.loading_submit = true;
          this.errors = {};
          
          const url = this.recordId ? `/restaurant/${this.resource}/${this.recordId}` : `/restaurant/${this.resource}`;
          const method = this.recordId ? 'put' : 'post';
          
          const formData = {
              ...this.form,
              type: 'spots'
          };
          
          this.$http[method](url, formData)
              .then(response => {
                  if (response.data.success) {
                      this.$message.success(response.data.message);
                      this.$eventHub.$emit("reloadData");
                      this.close();
                  } else {
                      this.$message.error(response.data.message);
                  }
              })
              .catch(error => {
                if (error.response.status === 422) {
                  this.errors = error.response.data;
                } else {
                  console.log(error);
                  this.$message.error(error.response.data.message);
                }
              })
              .finally(() => {
                  this.loading_submit = false;
              });
      },
      close() {
          this.$emit("update:showDialog", false);
          this.initForm();
      },
      onSuccess(response, file) {
          if (response.success) {
              this.form.image = response.data.filename;
              this.form.image_url = response.data.temp_image;
              this.form.temp_path = response.data.temp_path;
          } else {
              this.$message.error(response.message);
          }
      }
  }
};
</script>