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
            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp"
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

        <!-- Imagen vertical para celular. Opcional: sin ella el slider usa
             la de desktop, que es como funcionaba antes. -->
        <div class="banner-form__upload">
          <label class="banner-form__label">
            Imagen para celular <span class="banner-form__optional">opcional</span>
            <span class="banner-form__hint">Recomendado: 900 x 1100 px (formato vertical)</span>
          </label>
          <el-upload
            class="banner-uploader banner-uploader--mobile"
            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp"
            :data="{'type': 'promotions'}"
            :headers="headers"
            :action="`/${resource}/upload`"
            :show-file-list="false"
            :on-success="onSuccessMobile"
            :before-upload="beforeUpload"
            drag
          >
            <div v-if="form.image_mobile_url" class="banner-uploader__preview">
              <img :src="form.image_mobile_url" />
              <div class="banner-uploader__overlay">
                <i class="el-icon-camera"></i>
                <span>Cambiar imagen</span>
              </div>
            </div>
            <div v-else class="banner-uploader__empty">
              <i class="el-icon-upload"></i>
              <p>Arrastra la versión vertical o <em>haz clic para seleccionar</em></p>
              <span>Si no subes ninguna se usará la de escritorio</span>
            </div>
          </el-upload>
          <el-button v-if="form.image_mobile_url"
                     type="text" size="mini"
                     @click.prevent="clearMobileImage">Quitar imagen de celular</el-button>
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
                <el-radio-button label="category">Categoría</el-radio-button>
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

              <el-select v-if="linkType === 'category'"
                         v-model="form.link_category_id"
                         filterable
                         clearable
                         placeholder="Buscar categoría..."
                         style="width:100%">
                <el-option
                  v-for="option in categories"
                  :key="option.id"
                  :value="option.id"
                  :label="option.name"
                ></el-option>
              </el-select>

              <el-input v-if="linkType === 'url'"
                        v-model="form.banner_url"
                        placeholder="https://ejemplo.com/pagina"
                        clearable>
                <template slot="prepend"><i class="fa fa-link"></i></template>
              </el-input>

              <small class="text-danger" v-if="errors.item_id" v-text="errors.item_id[0]"></small>
              <small class="text-danger" v-if="errors.banner_url" v-text="errors.banner_url[0]"></small>
              <small class="text-danger" v-if="errors.link_category_id" v-text="errors.link_category_id[0]"></small>
            </div>

            <!-- Texto sobre el banner. Todo opcional: si se deja vacío el
                 banner se ve exactamente como antes, pura imagen. -->
            <div class="col-12 mb-3">
              <label class="banner-form__label">
                Texto sobre el banner <span class="banner-form__optional">opcional</span>
                <span class="banner-form__hint">Se muestra encima de la imagen. Déjalo vacío si tu imagen ya trae el texto.</span>
              </label>
              <el-input v-model="form.title" placeholder="Título — ej: Hasta 40% de descuento" clearable class="mb-2"></el-input>
              <el-input v-model="form.subtitle" placeholder="Subtítulo — ej: Solo por esta semana" clearable class="mb-2"></el-input>
              <el-input v-model="form.button_text"
                        placeholder="Texto del botón — ej: Ver ofertas"
                        clearable
                        :disabled="linkType === 'none'">
              </el-input>
              <small v-if="linkType === 'none'" class="text-muted" style="font-size:11px">
                El botón necesita un destino: elige producto, categoría o URL.
              </small>
              <small class="text-danger" v-if="errors.title" v-text="errors.title[0]"></small>
              <small class="text-danger" v-if="errors.subtitle" v-text="errors.subtitle[0]"></small>
              <small class="text-danger" v-if="errors.button_text" v-text="errors.button_text[0]"></small>
            </div>

            <div class="col-md-4 mb-3">
              <label class="banner-form__label">
                Orden
                <span class="banner-form__hint">Menor primero</span>
              </label>
              <el-input-number v-model="form.sort_order" :min="0" :max="9999" controls-position="right" style="width:100%"></el-input-number>
            </div>

            <div class="col-md-8 mb-3">
              <label class="banner-form__label">
                Vigencia <span class="banner-form__optional">opcional</span>
                <span class="banner-form__hint">Sin fechas el banner se muestra siempre</span>
              </label>
              <el-date-picker
                v-model="dateRange"
                type="datetimerange"
                range-separator="→"
                start-placeholder="Desde"
                end-placeholder="Hasta"
                format="dd/MM/yyyy HH:mm"
                value-format="yyyy-MM-dd HH:mm:ss"
                style="width:100%">
              </el-date-picker>
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
      categories: [],
      headers: headers_token,
      loading_submit: false,
      titleDialog: null,
      resource: "promotions",
      errors: {},
      form: {},
      linkType: 'product',
      // El date-picker de rango trabaja con un array; se sincroniza con
      // starts_at/ends_at al cargar y al enviar.
      dateRange: null
    };
  },
  created() {
    this.initForm();
    this.$http.get(`/${this.resource}/tables`).then(response => {
      this.items = response.data.items;
      this.categories = response.data.categories || [];
    });
  },
  watch: {
    linkType(val) {
      // Un solo destino a la vez. El backend repite esta limpieza en
      // normalizeSliderFields por si el payload llega de otro lado.
      if (val !== 'product')  this.form.item_id = null;
      if (val !== 'url')      this.form.banner_url = null;
      if (val !== 'category') this.form.link_category_id = null;
      if (val === 'none')     this.form.button_text = null;
    },
    dateRange(val) {
      this.form.starts_at = (val && val[0]) || null;
      this.form.ends_at   = (val && val[1]) || null;
    }
  },
  methods: {
    initForm() {
      this.errors = {};
      this.linkType = 'product';
      this.dateRange = null;
      this.form = {
        name: null,
        description: '',
        image: null,
        image_url: null,
        temp_path: null,
        item_id: null,
        banner_url: null,
        type: "banners",
        image_mobile: null,
        image_mobile_url: null,
        temp_path_mobile: null,
        title: null,
        subtitle: null,
        button_text: null,
        link_type: 'product',
        link_category_id: null,
        sort_order: 0,
        starts_at: null,
        ends_at: null
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
            if (this.form.sort_order === undefined || this.form.sort_order === null) {
              this.form.sort_order = 0;
            }

            // link_type explícito si el banner ya se guardó con la fase 3;
            // si no, se deduce como se hacía antes.
            if (this.form.link_type) {
              this.linkType = this.form.link_type;
            } else if (this.form.banner_url) {
              this.linkType = 'url';
            } else if (this.form.item_id) {
              this.linkType = 'product';
            } else {
              this.linkType = 'none';
            }

            // El watcher de linkType dispara DESPUÉS de asignarlo y limpiaría
            // los destinos recién cargados, así que se restauran en el tick
            // siguiente junto con el rango de fechas.
            const loaded = {
              item_id: this.form.item_id,
              banner_url: this.form.banner_url,
              link_category_id: this.form.link_category_id,
              button_text: this.form.button_text
            };
            this.$nextTick(() => {
              Object.assign(this.form, loaded);
              this.dateRange = (this.form.starts_at || this.form.ends_at)
                ? [this.form.starts_at, this.form.ends_at]
                : null;
            });
          });
      }
    },
    submit() {
      this.loading_submit = true;
      this.form.link_type = this.linkType;
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
    },
    onSuccessMobile(response) {
      if (response.success) {
        this.form.image_mobile = response.data.filename;
        this.form.image_mobile_url = response.data.temp_image;
        this.form.temp_path_mobile = response.data.temp_path;
      } else {
        this.$message.error(response.message);
      }
    },
    clearMobileImage() {
      this.form.image_mobile = null;
      this.form.image_mobile_url = null;
      this.form.temp_path_mobile = null;
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
.banner-form__optional {
  font-weight: 500;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #9ca3af;
  background: #f3f4f6;
  border-radius: 3px;
  padding: 1px 5px;
  margin-left: 4px;
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
