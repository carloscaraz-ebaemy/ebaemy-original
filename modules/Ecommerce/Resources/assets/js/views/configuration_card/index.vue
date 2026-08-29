<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config">
      <div class="card-header bg-info">
        <h3 class="my-0">Tarjeta de Producto</h3>
      </div>
      <div class="card-body">
        <p class="cc-hint">
          Elige qué muestra cada producto en el listado de tu tienda.
          Los cambios aplican a todas las páginas del catálogo.
        </p>

        <div v-if="loading" class="text-center text-muted py-4">Cargando…</div>

        <template v-else>
          <div v-for="group in groups" :key="group.group" class="cc-group">
            <h6 class="cc-group__title">{{ group.group }}</h6>
            <div v-for="opt in group.options" :key="opt.key" class="cc-row">
              <el-switch v-model="opt.enabled" :active-value="true" :inactive-value="false"></el-switch>
              <div class="cc-row__text">
                <span class="cc-row__label">{{ opt.label }}</span>
                <small>{{ opt.hint }}</small>
              </div>
            </div>
          </div>

          <div class="form-actions text-end pt-2">
            <el-button type="primary" @click="submit" :loading="loading_submit">Guardar</el-button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style>
.cc-hint { font-size: 12px; color: #6b7280; line-height: 1.5; margin: 0 0 14px; }

.cc-group { margin-bottom: 16px; }
.cc-group__title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: #9ca3af;
    margin: 0 0 8px; padding-bottom: 5px; border-bottom: 1px solid #f3f4f6;
}
.cc-row { display: flex; align-items: flex-start; gap: 10px; padding: 6px 0; }
.cc-row__text { min-width: 0; }
.cc-row__label { display: block; font-size: 13px; font-weight: 600; color: #374151; line-height: 1.3; }
.cc-row__text small { display: block; font-size: 11px; color: #9ca3af; line-height: 1.35; }
</style>

<script>
export default {
  data() {
    return {
      resource: "ecommerce",
      loading: true,
      loading_submit: false,
      groups: []
    };
  },
  created() {
    this.load();
  },
  methods: {
    // El catálogo y los defaults viven en App\Services\EcommerceCardOptions:
    // una sola definición para el backend y esta pantalla.
    load() {
      this.loading = true;
      this.$http.get(`/${this.resource}/card_options`)
        .then(r => { this.groups = (r.data && r.data.groups) || []; })
        .catch(() => { this.$message.error('No se pudieron cargar las opciones'); })
        .then(() => { this.loading = false; });
    },
    submit() {
      this.loading_submit = true;
      const options = {};
      this.groups.forEach(g => g.options.forEach(o => { options[o.key] = o.enabled; }));

      this.$http.post(`/${this.resource}/card_options`, { options })
        .then(r => {
          if (r.data.success) {
            this.groups = r.data.groups || this.groups;
            this.$message.success(r.data.message);
          } else {
            this.$message.error(r.data.message || 'No se pudo guardar');
          }
        })
        .catch(() => { this.$message.error('Error al guardar la tarjeta'); })
        .then(() => { this.loading_submit = false; });
    }
  }
};
</script>
