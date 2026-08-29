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
                <div class="form-group form-modern">
                  <label class="control-label">
                    Color Principal de la Tienda
                  </label>
                  <el-color-picker class="col-12 px-0" size="medium" v-model="form.color_ecommerce"></el-color-picker>
                </div>
              </div>

              <!-- ── Paleta completa ─────────────────────────────────────
                   El color principal por sí solo no alcanza: el header, el
                   footer y el color de ofertas los venía fijando cada theme
                   a mano. Aquí el tenant los controla. -->
              <div class="col-12 mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h5 class="mb-0 text-muted">Paleta de la tienda</h5>
                  <el-button type="text" size="mini" @click="showPalette = !showPalette">
                    {{ showPalette ? 'Ocultar' : 'Personalizar' }}
                  </el-button>
                </div>

                <div v-show="showPalette">
                  <p class="tc-hint">
                    Empieza por un preset y ajusta lo que quieras. El texto sobre
                    el header, el footer y los botones se calcula solo para que
                    siempre se lea.
                  </p>

                  <div class="tc-presets">
                    <button v-for="(preset, key) in presets"
                            :key="key"
                            type="button"
                            class="tc-preset"
                            @click="applyPreset(key)">
                      <span class="tc-preset__swatches">
                        <i :style="{background: preset.colors.primary}"></i>
                        <i :style="{background: preset.colors.header}"></i>
                        <i :style="{background: preset.colors.accent}"></i>
                        <i :style="{background: preset.colors.offer}"></i>
                      </span>
                      {{ preset.label }}
                    </button>
                    <button type="button" class="tc-preset tc-preset--reset" @click="resetPalette">
                      Restaurar
                    </button>
                  </div>

                  <div class="tc-grid">
                    <div v-for="field in paletteFields" :key="field.key" class="tc-field">
                      <el-color-picker size="mini" v-model="form.theme_colors[field.key]"></el-color-picker>
                      <div class="tc-field__text">
                        <label>{{ field.label }}</label>
                        <small>{{ field.hint }}</small>
                      </div>
                    </div>
                  </div>

                  <!-- Vista previa: los mismos tokens que recibe la tienda,
                       aplicados con estilos inline para verlos al instante. -->
                  <div class="tc-preview" :style="previewVars">
                    <div class="tc-preview__header">
                      <strong>Mi Tienda</strong>
                      <span class="tc-preview__cart">Carrito · 2</span>
                    </div>
                    <div class="tc-preview__body">
                      <div class="tc-preview__card">
                        <div class="tc-preview__img">
                          <span class="tc-preview__badge">-35%</span>
                        </div>
                        <span class="tc-preview__name">Audífonos inalámbricos</span>
                        <span class="tc-preview__price">S/ 199.00</span>
                        <span class="tc-preview__old">S/ 299.00</span>
                        <span class="tc-preview__btn">Agregar al carrito</span>
                      </div>
                      <div class="tc-preview__side">
                        <span class="tc-preview__link">Ver todas las ofertas →</span>
                        <p class="tc-preview__text">
                          Texto secundario de la tienda, como la descripción
                          corta de un producto.
                        </p>
                      </div>
                    </div>
                    <div class="tc-preview__footer">Envíos a todo el Perú · Compra segura</div>
                  </div>
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
/* Los pickers de la paleta son chicos y en grilla: no deben estirarse. */
.tc-grid .el-color-picker__trigger { width: 28px !important; height: 28px !important; }

.tc-hint { font-size: 12px; color: #6b7280; line-height: 1.5; margin: 0 0 10px; }

.tc-presets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.tc-preset {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #d1d5db; border-radius: 8px;
    padding: 6px 12px; font-size: 12px; color: #374151; cursor: pointer;
}
.tc-preset:hover { border-color: #6b7280; }
.tc-preset__swatches { display: flex; }
.tc-preset__swatches i {
    width: 12px; height: 12px; border-radius: 50%;
    border: 1.5px solid #fff; margin-left: -4px;
}
.tc-preset__swatches i:first-child { margin-left: 0; }
.tc-preset--reset { color: #6b7280; }

.tc-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 16px; margin-bottom: 18px;
}
.tc-field { display: flex; align-items: center; gap: 10px; }
.tc-field__text { min-width: 0; }
.tc-field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin: 0; }
.tc-field small { display: block; font-size: 10.5px; color: #9ca3af; line-height: 1.3; }
@media (max-width: 767px) { .tc-grid { grid-template-columns: 1fr; } }

/* ── Vista previa ── */
.tc-preview {
    border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;
    background: var(--tcp-background); font-size: 12px;
}
.tc-preview__header {
    background: var(--tcp-header); color: var(--tcp-header-text);
    padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;
}
.tc-preview__cart { font-size: 11px; opacity: .85; }
.tc-preview__body { display: flex; gap: 14px; padding: 14px; }
.tc-preview__card {
    background: var(--tcp-surface); border: 1px solid var(--tcp-border);
    border-radius: 8px; padding: 10px; width: 150px; flex: 0 0 auto;
}
.tc-preview__img {
    height: 62px; border-radius: 6px; background: var(--tcp-primary-soft);
    position: relative; margin-bottom: 8px;
}
.tc-preview__badge {
    position: absolute; top: 6px; left: 6px;
    background: var(--tcp-offer); color: var(--tcp-offer-text);
    font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px;
}
.tc-preview__name { display: block; color: var(--tcp-text-primary); font-weight: 600; margin-bottom: 3px; }
.tc-preview__price { color: var(--tcp-primary); font-weight: 700; font-size: 14px; margin-right: 5px; }
.tc-preview__old { color: var(--tcp-text-secondary); text-decoration: line-through; font-size: 11px; }
.tc-preview__btn {
    display: block; text-align: center; margin-top: 8px;
    background: var(--tcp-primary); color: var(--tcp-primary-text);
    border-radius: 6px; padding: 6px; font-weight: 600;
}
.tc-preview__side { flex: 1; min-width: 0; }
.tc-preview__link { color: var(--tcp-accent); font-weight: 600; }
.tc-preview__text { color: var(--tcp-text-secondary); margin: 6px 0 0; line-height: 1.5; }
.tc-preview__footer {
    background: var(--tcp-footer); color: var(--tcp-footer-text);
    padding: 9px 14px; font-size: 11px;
}
</style>

<script>
export default {
  data() {
    return {
      loading_submit: false,
      resource: "ecommerce",
      errors: {},
      form: { theme_colors: {} },
      showPalette: false,
      presets: {},
      defaults: {},
      paletteFields: [
        { key: 'header',         label: 'Header',            hint: 'Fondo de la barra superior' },
        { key: 'footer',         label: 'Footer',            hint: 'Fondo del pie de página' },
        { key: 'accent',         label: 'Acento',            hint: 'Enlaces y detalles' },
        { key: 'offer',          label: 'Ofertas',           hint: 'Badges de descuento' },
        { key: 'secondary',      label: 'Secundario',        hint: 'Botones secundarios' },
        { key: 'background',     label: 'Fondo',             hint: 'Fondo general de la página' },
        { key: 'surface',        label: 'Tarjetas',          hint: 'Fondo de tarjetas y cajas' },
        { key: 'border',         label: 'Bordes',            hint: 'Líneas y separadores' },
        { key: 'text_primary',   label: 'Texto principal',   hint: 'Títulos y nombres' },
        { key: 'text_secondary', label: 'Texto secundario',  hint: 'Descripciones y notas' },
        { key: 'success',        label: 'Éxito',             hint: 'Confirmaciones' },
        { key: 'danger',         label: 'Error',             hint: 'Alertas y errores' },
      ]
    };
  },
  computed: {
    // Las mismas variables que EcommerceThemeTokens emite en la tienda, con
    // prefijo propio para no chocar con el CSS del panel. Los derivados
    // (contraste, tinte) se replican aquí para que la previa sea fiel sin
    // tener que ir al servidor en cada cambio de color.
    previewVars() {
      const c = key => this.form.theme_colors[key] || this.defaults[key] || '#ffffff';
      const primary = this.form.color_ecommerce || '#ff8000';
      return {
        '--tcp-primary':        primary,
        '--tcp-primary-text':   this.readableOn(primary),
        '--tcp-primary-soft':   this.mixWithWhite(primary, 88),
        '--tcp-accent':         c('accent'),
        '--tcp-background':     c('background'),
        '--tcp-surface':        c('surface'),
        '--tcp-border':         c('border'),
        '--tcp-text-primary':   c('text_primary'),
        '--tcp-text-secondary': c('text_secondary'),
        '--tcp-header':         c('header'),
        '--tcp-header-text':    this.readableOn(c('header')),
        '--tcp-footer':         c('footer'),
        '--tcp-footer-text':    this.readableOn(c('footer')),
        '--tcp-offer':          c('offer'),
        '--tcp-offer-text':     this.readableOn(c('offer')),
      };
    }
  },
  async created() {
    await this.loadPalette();
    await this.$http.get(`/${this.resource}/record`).then(response => {
      if (response.data !== "") {
        let data = response.data.data;

        let preferences = { show_description: 1, show_stock: 0, only_available_products: 0, full_width_banner: 0 };
        if (data.preferences) {
          const prefs = typeof data.preferences === 'string'
            ? JSON.parse(data.preferences)
            : data.preferences;
          preferences = prefs;
        }

        this.form = {
          id: data.id,
          color_ecommerce: data.color_ecommerce,
          show_description: parseInt(preferences.show_description) || 0,
          show_stock: parseInt(preferences.show_stock) || 0,
          only_available_products: parseInt(preferences.only_available_products) || 0,
          full_width_banner: parseInt(preferences.full_width_banner) || 0,
          theme_colors: Object.assign({}, this.defaults, preferences.theme_colors || {})
        };
      } else {
        this.initForm();
      }
      this.ensurePaletteKeys();
    });
  },
  methods: {
    // Presets y defaults vienen del backend (App\Services\EcommerceThemeTokens)
    // para que exista una sola definición de la paleta.
    loadPalette() {
      return this.$http.get(`/${this.resource}/theme_colors`)
        .then(r => {
          this.presets  = r.data.presets  || {};
          this.defaults = r.data.defaults || {};
        })
        .catch(() => { /* sin presets el formulario sigue siendo usable */ });
    },
    // Vue 2 no detecta claves agregadas después: si el endpoint de paleta
    // falló, theme_colors llegaría incompleto y el picker de un color faltante
    // no actualizaría la vista previa. Sembramos todas las claves de una.
    ensurePaletteKeys() {
      this.paletteFields.forEach(f => {
        if (this.form.theme_colors[f.key] === undefined) {
          this.$set(this.form.theme_colors, f.key, this.defaults[f.key] || '#ffffff');
        }
      });
    },
    applyPreset(key) {
      const preset = this.presets[key];
      if (!preset) return;
      const colors = Object.assign({}, preset.colors);
      if (colors.primary) {
        this.form.color_ecommerce = colors.primary;
        delete colors.primary;
      }
      this.form.theme_colors = Object.assign({}, this.defaults, colors);
      this.$message.success(`Preset "${preset.label}" aplicado. Recuerda guardar.`);
    },
    resetPalette() {
      this.form.theme_colors = Object.assign({}, this.defaults);
    },
    // Espejo en JS de EcommerceThemeTokens::readableOn (luminancia WCAG).
    readableOn(hex) {
      const rgb = this.toRgb(hex);
      const ch = ['r', 'g', 'b'].map(k => {
        const v = rgb[k] / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
      });
      const lum = 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
      return lum > 0.45 ? '#111827' : '#ffffff';
    },
    mixWithWhite(hex, whitePct) {
      const c = this.toRgb(hex);
      const f = whitePct / 100;
      const mix = v => Math.round(v + (255 - v) * f);
      return `rgb(${mix(c.r)}, ${mix(c.g)}, ${mix(c.b)})`;
    },
    toRgb(hex) {
      let h = String(hex || '').replace('#', '').trim();
      if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      if (!/^[0-9a-fA-F]{6}$/.test(h)) h = '000000';
      return {
        r: parseInt(h.substr(0, 2), 16),
        g: parseInt(h.substr(2, 2), 16),
        b: parseInt(h.substr(4, 2), 16)
      };
    },
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        color_ecommerce: null,
        show_description: 1,
        show_stock: 0,
        only_available_products: 0,
        full_width_banner: 0,
        theme_colors: Object.assign({}, this.defaults)
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
