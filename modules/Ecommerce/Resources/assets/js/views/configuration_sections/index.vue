<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config">
      <div class="card-header bg-info">
        <h3 class="my-0">Secciones del Home</h3>
      </div>
      <div class="card-body">
        <p class="hs-hint">
          Ordena y enciende las secciones de la portada de tu tienda.
          Se agrupan por su ubicación en la página: cada sección se mueve
          dentro de su bloque.
        </p>

        <div v-if="loading" class="text-center text-muted py-4">Cargando…</div>

        <template v-else>
          <div v-for="zone in zones" :key="zone.key" class="hs-zone">
            <h6 class="hs-zone__title">{{ zone.label }}</h6>

            <div v-for="(section, i) in sectionsIn(zone.key)"
                 :key="section.key"
                 class="hs-row"
                 :class="{'hs-row--off': !section.enabled}">
              <div class="hs-row__move">
                <button type="button"
                        class="hs-move"
                        :disabled="i === 0"
                        title="Subir"
                        @click="move(zone.key, i, -1)">▲</button>
                <button type="button"
                        class="hs-move"
                        :disabled="i === sectionsIn(zone.key).length - 1"
                        title="Bajar"
                        @click="move(zone.key, i, 1)">▼</button>
              </div>

              <div class="hs-row__text">
                <span class="hs-row__label">{{ section.label }}</span>
                <small>{{ section.hint }}</small>
              </div>

              <div class="hs-row__switch">
                <el-switch v-if="!section.locked"
                           v-model="section.enabled"
                           :active-value="true"
                           :inactive-value="false"></el-switch>
                <span v-else class="hs-locked" title="Esta sección no se puede ocultar">Fija</span>
              </div>
            </div>
          </div>

          <div class="form-actions text-end pt-2">
            <el-button size="small" @click="reset" :disabled="loading_submit">Restaurar por defecto</el-button>
            <el-button type="primary" @click="submit" :loading="loading_submit">Guardar</el-button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style>
.hs-hint { font-size: 12px; color: #6b7280; line-height: 1.5; margin: 0 0 14px; }

.hs-zone { margin-bottom: 18px; }
.hs-zone__title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: #9ca3af; margin: 0 0 8px;
}

.hs-row {
    display: flex; align-items: center; gap: 12px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 8px 12px; margin-bottom: 6px;
}
.hs-row--off { background: #fafafa; }
.hs-row--off .hs-row__label { color: #9ca3af; }

.hs-row__move { display: flex; flex-direction: column; gap: 2px; }
.hs-move {
    background: #f3f4f6; border: 0; border-radius: 4px;
    width: 22px; height: 15px; line-height: 1; font-size: 9px;
    color: #6b7280; cursor: pointer; padding: 0;
}
.hs-move:hover:not(:disabled) { background: #e5e7eb; color: #374151; }
.hs-move:disabled { opacity: .3; cursor: default; }

.hs-row__text { flex: 1; min-width: 0; }
.hs-row__label { display: block; font-size: 13px; font-weight: 600; color: #374151; }
.hs-row__text small { display: block; font-size: 11px; color: #9ca3af; line-height: 1.35; }

.hs-row__switch { flex: 0 0 auto; }
.hs-locked {
    font-size: 10px; font-weight: 600; color: #6b7280;
    background: #f3f4f6; border-radius: 4px; padding: 2px 7px;
}
</style>

<script>
export default {
  data() {
    return {
      resource: "ecommerce",
      loading: true,
      loading_submit: false,
      sections: [],
      // Las secciones viven en dos contenedores distintos del home y no se
      // pueden mover entre ellos sin cambiarles el ancho. Ver
      // App\Services\EcommerceHomeSections, nota sobre zonas.
      zones: [
        { key: 'main', label: 'Cuerpo de la portada' },
        { key: 'wide', label: 'Pie de la portada' }
      ]
    };
  },
  created() {
    this.load();
  },
  methods: {
    load() {
      this.loading = true;
      this.$http.get(`/${this.resource}/home_sections`)
        .then(r => { this.sections = (r.data && r.data.sections) || []; })
        .catch(() => { this.$message.error('No se pudieron cargar las secciones'); })
        .then(() => { this.loading = false; });
    },
    sectionsIn(zone) {
      return this.sections.filter(s => s.zone === zone);
    },
    // Mover dentro de la zona: traducimos el índice de la zona al índice real
    // del array plano, que es el que define el orden que se guarda.
    move(zone, indexInZone, delta) {
      const inZone = this.sectionsIn(zone);
      const target = indexInZone + delta;
      if (target < 0 || target >= inZone.length) return;

      const a = this.sections.indexOf(inZone[indexInZone]);
      const b = this.sections.indexOf(inZone[target]);

      // Intercambio en una copia y reasignación: Vue 2 no reacciona a
      // this.sections[i] = x, pero sí a reemplazar el array entero.
      const copy = this.sections.slice();
      copy[a] = this.sections[b];
      copy[b] = this.sections[a];
      this.sections = copy;
    },
    reset() {
      this.$http.post(`/${this.resource}/home_sections`, { order: [], disabled: [] })
        .then(r => {
          this.sections = r.data.sections || [];
          this.$message.success('Se restauró el orden y se encendieron todas las secciones');
        })
        .catch(() => { this.$message.error('No se pudo restaurar'); });
    },
    submit() {
      this.loading_submit = true;
      this.$http.post(`/${this.resource}/home_sections`, {
        order: this.sections.map(s => s.key),
        disabled: this.sections.filter(s => !s.enabled && !s.locked).map(s => s.key)
      })
        .then(r => {
          if (r.data.success) {
            this.sections = r.data.sections || this.sections;
            this.$message.success(r.data.message);
          } else {
            this.$message.error(r.data.message || 'No se pudo guardar');
          }
        })
        .catch(() => { this.$message.error('Error al guardar las secciones'); })
        .then(() => { this.loading_submit = false; });
    }
  }
};
</script>
