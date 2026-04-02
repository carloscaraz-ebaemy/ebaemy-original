<template>
  <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create" width="720px" class="banner-dialog">
    <form autocomplete="off" @submit.prevent="submit">
      <div class="banner-form">
        <!-- Image upload section -->
        <div class="banner-form__upload">
          <label class="banner-form__label">
            Imagen del banner
            <span class="banner-form__hint">Recomendado: 1200 x 400 px (formato horizontal)</span>
          </label>
          <el-upload
            class="banner-uploader"
            :data="{'type': 'promotions'}"
            :headers="headers"
            :action="`/${resource}/upload`"
            :show-file-list="false"
            :on-success="onSuccess"
            :before-upload="beforeUpload"
            drag
          >
            <div v-if="form.image_url" class="banner-uploader__preview">
              <img :src="form.image_url" />
              <div class="banner-uploader__overlay">
                <i class="el-icon-camera"></i>
                <span>Cambiar imagen</span>
              </div>
            </div>
            <div v-else class="banner-uploader__empty">
              <i class="el-icon-upload"></i>
              <p>Arrastra tu imagen aquí o <em>haz clic para seleccionar</em></p>
              <span>JPG, PNG o GIF — max 2MB</span>
            </div>
          </el-upload>
          <small class="text-danger" v-if="errors.image" v-text="errors.image[0]"></small>
        </div>

        <!-- Fields section -->
        <div class="banner-form__fields">
          <div class="row">
            <div class="col-12 mb-3">
              <label class="banner-form__label">Nombre del banner <span class="text-danger">*</span></label>
              <el-input v-model="form.name" placeholder="Ej: Ofertas de temporada" clearable></el-input>
              <small class="text-danger" v-if="errors.name" v-text="errors.name[0]"></small>
            </div>

            <div class="col-12 mb-3">
              <label class="banner-form__label">
                Destino al hacer clic
                <el-tooltip content="Elige un producto de tu catálogo o escribe una URL externa" placement="top">
                  <i class="fa fa-info-circle text-muted" style="font-size:12px"></i>
                </el-tooltip>
              </label>
              <el-radio-group v-model="linkType" size="small" class="mb-2" style="display:flex">
                <el-radio-button label="product">Producto</el-radio-button>
                <el-radio-button label="url">URL externa</el-radio-button>
                <el-radio-button label="none">Sin enlace</el-radio-button>
              </el-radio-group>

              <el-select v-if="linkType === 'product'"
                         v-model="form.item_id"
                         filterable
                         clearable
                         placeholder="Buscar producto..."
                         style="width:100%">
                <el-option
                  v-for="option in items"
                  :key="option.id"
                  :value="option.id"
                  :label="option.description"
                ></el-option>
              </el-select>

              <el-input v-if="linkType === 'url'"
                        v-model="form.banner_url"
                        placeholder="https://ejemplo.com/pagina"
                        clearable>
                <template slot="prepend"><i class="fa fa-link"></i></template>
              </el-input>

              <small class="text-danger" v-if="errors.item_id" v-text="errors.item_id[0]"></small>
            </div>
          </div>
        </div>
      </div>

      <div class="banner-form__actions">
        <el-button @click.prevent="close()">Cancelar</el-button>
        <el-button type="primary" native-type="submit" :loading="loading_submit">
          <i class="fa fa-save" v-if="!loading_submit"></i>
          {{ recordId ? 'Actualizar Banner' : 'Crear Banner' }}
        </el-button>
      </div>
    </form>
  </el-dialog>
</template>

<script>
import { imageCompressor } from '../../../mixins/imageCompressor'

export default {
  mixins: [imageCompressor],
  props: ["showDialog", "recordId", "external"],
  data() {
    return {
      items: [],
      headers: headers_token,
      loading_submit: false,
      titleDialog: null,
      resource: "promotions",
      errors: {},
      form: {},
      linkType: 'product'
    };
  },
  created() {
    this.initForm();
    this.$http.get(`/${this.resource}/tables`).then(response => {
      this.items = response.data.items;
    });
  },
  watch: {
    linkType(val) {
      if (val === 'none') {
        this.form.item_id = null;
        this.form.banner_url = null;
      } else if (val === 'product') {
        this.form.banner_url = null;
      } else if (val === 'url') {
        this.form.item_id = null;
      }
    }
  },
  methods: {
    initForm() {
      this.errors = {};
      this.linkType = 'product';
      this.form = {
        name: null,
        description: '',
        image: null,
        image_url: null,
        temp_path: null,
        item_id: null,
        banner_url: null,
        type: "banners"
      };
    },
    create() {
      this.titleDialog = this.recordId ? "Editar Banner" : "Nuevo Banner";
      if (this.recordId) {
        this.$http
          .get(`/${this.resource}/record/${this.recordId}`)
          .then(response => {
            this.form = response.data.data;
            if (this.form.description === null) {
              this.form.description = '';
            }
            // Detect link type
            if (this.form.banner_url) {
              this.linkType = 'url';
            } else if (this.form.item_id) {
              this.linkType = 'product';
            } else {
              this.linkType = 'none';
            }
          });
      }
    },
    submit() {
      this.loading_submit = true;
      this.$http
        .post(`/${this.resource}`, this.form)
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
            this.$message.error(error.response.data.message);
          }
        })
        .then(() => {
          this.loading_submit = false;
        });
    },
    close() {
      this.$emit("update:showDialog", false);
      this.initForm();
    },
    onSuccess(response) {
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

<style scoped>
.banner-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.banner-form__upload {
  width: 100%;
}
.banner-form__label {
  display: block;
  font-weight: 600;
  font-size: 13px;
  color: #333;
  margin-bottom: 6px;
}
.banner-form__hint {
  display: block;
  font-weight: 400;
  font-size: 11px;
  color: #999;
}
.banner-form__fields {
  width: 100%;
}
.banner-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid #f0f0f0;
}

/* Upload styles */
.banner-uploader >>> .el-upload {
  width: 100%;
  border: 2px dashed #d9d9d9;
  border-radius: 10px;
  overflow: hidden;
  transition: border-color 0.2s;
}
.banner-uploader >>> .el-upload:hover {
  border-color: #409EFF;
}
.banner-uploader >>> .el-upload-dragger {
  width: 100%;
  height: auto;
  min-height: 180px;
  border: none;
  background: #fafafa;
}
.banner-uploader__preview {
  position: relative;
  width: 100%;
}
.banner-uploader__preview img {
  width: 100%;
  max-height: 300px;
  object-fit: cover;
  display: block;
}
.banner-uploader__overlay {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #fff;
  opacity: 0;
  transition: opacity 0.2s;
  cursor: pointer;
}
.banner-uploader__overlay i { font-size: 28px; margin-bottom: 4px; }
.banner-uploader__overlay span { font-size: 13px; }
.banner-uploader__preview:hover .banner-uploader__overlay {
  opacity: 1;
}
.banner-uploader__empty {
  padding: 40px 20px;
  text-align: center;
  color: #999;
}
.banner-uploader__empty i {
  font-size: 48px;
  color: #c0c4cc;
  margin-bottom: 8px;
}
.banner-uploader__empty p {
  margin: 0;
  font-size: 14px;
  color: #666;
}
.banner-uploader__empty em {
  color: #409EFF;
  font-style: normal;
}
.banner-uploader__empty span {
  font-size: 11px;
  color: #bbb;
}
</style>
