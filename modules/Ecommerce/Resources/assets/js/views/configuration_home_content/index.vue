<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config">
      <div class="card-header bg-info">
        <h3 class="my-0">Contenido del Home</h3>
      </div>
      <div class="card-body">
        <div v-if="loading" class="text-center text-muted py-4">Cargando…</div>

        <el-tabs v-else v-model="tab">
          <!-- ── Garantías ────────────────────────────────────────── -->
          <el-tab-pane label="Garantías" name="benefits">
            <p class="hc-hint">
              La franja de confianza debajo del catálogo. Escribe solo lo que
              tu tienda realmente cumple.
            </p>

            <div v-for="(b, i) in benefits" :key="'b'+i" class="hc-benefit">
              <el-select v-model="b.icon" size="small" class="hc-benefit__icon" placeholder="Ícono">
                <el-option v-for="(label, key) in icons" :key="key" :value="key" :label="label"></el-option>
              </el-select>
              <div class="hc-benefit__fields">
                <el-input v-model="b.title" size="small" maxlength="60" placeholder="Título — ej: Envío a todo el Perú"></el-input>
                <el-input v-model="b.text" size="small" maxlength="120" placeholder="Detalle — ej: Despacho en 24-48h"></el-input>
              </div>
              <el-button type="text" class="hc-remove" @click="benefits.splice(i, 1)" title="Quitar">✕</el-button>
            </div>

            <el-button size="mini" @click="benefits.push({icon:'star', title:'', text:''})"
                       :disabled="benefits.length >= 6">
              + Agregar garantía
            </el-button>
            <el-button size="mini" type="text" @click="benefits = []">Vaciar</el-button>
            <p class="hc-note" v-if="!benefits.length">
              Sin garantías la sección no se muestra. Guarda vacío para ocultarla.
            </p>
          </el-tab-pane>

          <!-- ── Categorías destacadas ────────────────────────────── -->
          <el-tab-pane label="Categorías" name="categories">
            <p class="hc-hint">
              Tarjetas con imagen al inicio de la portada. Usan la imagen que
              cargaste en cada categoría. Se ocultan solas si no eliges ninguna.
            </p>
            <el-select v-model="featuredIds" multiple filterable
                       placeholder="Elige las categorías a destacar" style="width:100%">
              <el-option v-for="c in availableCategories" :key="c.id" :value="c.id" :label="c.name"></el-option>
            </el-select>
            <div class="hc-switch">
              <el-switch v-model="showCount" :active-value="true" :inactive-value="false"></el-switch>
              <span>Mostrar cuántos productos tiene cada categoría</span>
            </div>
          </el-tab-pane>

          <!-- ── Marcas ───────────────────────────────────────────── -->
          <el-tab-pane label="Marcas" name="brands">
            <p class="hc-hint">
              Franja de logos. Sin logo se muestra el nombre de la marca.
              Se oculta sola si no eliges ninguna.
            </p>

            <el-select v-model="brandPick" filterable clearable
                       placeholder="Agregar marca" style="width:100%" @change="addBrand">
              <el-option v-for="b in selectableBrands" :key="b.id" :value="b.id" :label="b.name"></el-option>
            </el-select>

            <div v-for="(b, i) in brands" :key="'m'+b.id" class="hc-brand">
              <el-upload
                class="hc-brand__upload"
                accept="image/jpeg,image/jpg,image/png,image/webp,image/svg+xml"
                :headers="headers"
                :action="`/${resource}/home_content/brand-logo`"
                :show-file-list="false"
                :on-success="r => onLogo(r, b)"
                :on-error="onLogoError"
              >
                <img v-if="b.logo_url" :src="b.logo_url" class="hc-brand__logo" />
                <span v-else class="hc-brand__empty">Subir logo</span>
              </el-upload>
              <span class="hc-brand__name">{{ brandName(b.id) }}</span>
              <el-button type="text" class="hc-remove" @click="brands.splice(i, 1)" title="Quitar">✕</el-button>
            </div>

            <p class="hc-note" v-if="!brands.length">Todavía no agregaste marcas.</p>
          </el-tab-pane>
        </el-tabs>

        <div class="form-actions text-end pt-2" v-if="!loading">
          <el-button type="primary" @click="submit" :loading="loading_submit">Guardar</el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.hc-hint { font-size: 12px; color: #6b7280; line-height: 1.5; margin: 0 0 12px; }
.hc-note { font-size: 11.5px; color: #9ca3af; margin: 10px 0 0; }

.hc-benefit { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; }
.hc-benefit__icon { width: 118px; flex: 0 0 auto; }
.hc-benefit__fields { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
.hc-remove { color: #d1d5db; padding: 0 4px; font-size: 13px; }
.hc-remove:hover { color: #ef4444; }

.hc-switch { display: flex; align-items: center; gap: 9px; margin-top: 12px; font-size: 12.5px; color: #374151; }

.hc-brand { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.hc-brand__upload { flex: 0 0 auto; }
.hc-brand__logo {
    width: 84px; height: 44px; object-fit: contain;
    border: 1px solid #e5e7eb; border-radius: 6px; background: #fafafa; padding: 4px;
    cursor: pointer;
}
.hc-brand__empty {
    display: flex; align-items: center; justify-content: center;
    width: 84px; height: 44px; font-size: 11px; color: #9ca3af;
    border: 1px dashed #d1d5db; border-radius: 6px; cursor: pointer;
}
.hc-brand__empty:hover { border-color: #9ca3af; color: #6b7280; }
.hc-brand__name { flex: 1; font-size: 13px; font-weight: 600; color: #374151; }
</style>

<script>
export default {
  data() {
    return {
      resource: "ecommerce",
      headers: headers_token,
      loading: true,
      loading_submit: false,
      tab: "benefits",
      benefits: [],
      icons: {},
      featuredIds: [],
      showCount: false,
      brands: [],
      brandPick: null,
      availableCategories: [],
      availableBrands: []
    };
  },
  computed: {
    // Una marca ya agregada no vuelve a ofrecerse en el selector.
    selectableBrands() {
      const used = this.brands.map(b => b.id);
      return this.availableBrands.filter(b => used.indexOf(b.id) === -1);
    }
  },
  created() {
    this.load();
  },
  methods: {
    load() {
      this.loading = true;
      this.$http.get(`/${this.resource}/home_content`)
        .then(r => {
          const d = r.data || {};
          this.icons = d.icons || {};
          this.benefits = (d.benefits || []).map(b => Object.assign({}, b));
          this.featuredIds = (d.featured_categories && d.featured_categories.ids) || [];
          this.showCount = !!(d.featured_categories && d.featured_categories.show_count);
          this.availableCategories = d.available_categories || [];
          this.availableBrands = d.available_brands || [];
          this.brands = (d.brands || []).map(b => ({
            id: b.id,
            logo: b.logo || null,
            logo_url: b.logo ? `/storage/uploads/brands/${b.logo}` : null
          }));
        })
        .catch(() => { this.$message.error('No se pudo cargar el contenido del home'); })
        .then(() => { this.loading = false; });
    },
    brandName(id) {
      const b = this.availableBrands.find(x => x.id === id);
      return b ? b.name : `Marca #${id}`;
    },
    addBrand(id) {
      if (!id) return;
      if (this.brands.some(b => b.id === id)) return;
      this.brands.push({ id, logo: null, logo_url: null });
      this.$nextTick(() => { this.brandPick = null; });
    },
    onLogo(response, brand) {
      if (response && response.success) {
        // Se guarda el nombre del archivo; la URL es solo para la vista previa.
        this.$set(brand, 'logo', response.data.filename);
        this.$set(brand, 'logo_url', response.data.url);
      } else {
        this.$message.error((response && response.message) || 'No se pudo subir el logo');
      }
    },
    onLogoError(err) {
      let msg = 'No se pudo subir el logo';
      try {
        const body = JSON.parse(err.message);
        if (body.errors && body.errors.file) msg = body.errors.file[0];
        else if (body.message) msg = body.message;
      } catch (e) { /* respuesta no JSON: queda el mensaje genérico */ }
      this.$message.error(msg);
    },
    submit() {
      this.loading_submit = true;
      this.$http.post(`/${this.resource}/home_content`, {
        benefits: this.benefits,
        featured_categories: { ids: this.featuredIds, show_count: this.showCount },
        brands: this.brands.map(b => ({ id: b.id, logo: b.logo }))
      })
        .then(r => {
          if (r.data.success) {
            this.$message.success(r.data.message);
          } else {
            this.$message.error(r.data.message || 'No se pudo guardar');
          }
        })
        .catch(() => { this.$message.error('Error al guardar el contenido'); })
        .then(() => { this.loading_submit = false; });
    }
  }
};
</script>
