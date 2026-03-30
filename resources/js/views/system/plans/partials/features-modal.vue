<template>
  <el-dialog :visible.sync="visible" :title="`Features — ${planName}`"
             width="680px" @open="load" @closed="reset">

    <div v-if="loading" class="text-center py-4">
      <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
    </div>

    <template v-else>
      <!-- Group by category -->
      <div v-for="(group, cat) in grouped" :key="cat" class="mb-3">
        <div class="text-muted small text-uppercase font-weight-bold mb-2"
             style="letter-spacing:.06em; border-bottom:1px solid #eee; padding-bottom:4px">
          {{ cat }}
        </div>
        <div v-for="feat in group" :key="feat.id" class="d-flex align-items-center mb-2 gap-2">
          <el-switch v-model="feat.enabled" :active-color="'#34A853'"></el-switch>
          <div class="flex-grow-1">
            <span class="font-weight-semibold">{{ feat.name }}</span>
            <span v-if="feat.description" class="text-muted small ml-2">— {{ feat.description }}</span>
          </div>
          <div v-if="feat.enabled" style="width:130px">
            <el-input-number v-model="feat.limit" :min="0" size="mini"
              placeholder="Sin límite" controls-position="right"
              style="width:100%">
            </el-input-number>
            <div class="text-muted" style="font-size:0.7rem; margin-top:2px">0 = sin límite</div>
          </div>
          <div v-else style="width:130px"></div>
        </div>
      </div>

      <div v-if="!features.length" class="text-muted text-center py-3">
        No hay features activas configuradas.<br>
        <small>Correr: <code>php artisan migrate</code> (features_table)</small>
      </div>
    </template>

    <span slot="footer">
      <el-button @click="visible = false">Cancelar</el-button>
      <el-button type="primary" :loading="saving" @click="save">Guardar</el-button>
    </span>
  </el-dialog>
</template>

<script>
export default {
  props: {
    planId:   { type: Number, default: null },
    planName: { type: String, default: '' },
    value:    { type: Boolean, default: false },
  },

  data() {
    return {
      visible:  false,
      loading:  false,
      saving:   false,
      features: [],
    };
  },

  watch: {
    value(v) { this.visible = v; },
    visible(v) { this.$emit('input', v); },
  },

  computed: {
    grouped() {
      const g = {};
      for (const f of this.features) {
        if (!g[f.category]) g[f.category] = [];
        g[f.category].push(f);
      }
      return g;
    },
  },

  methods: {
    async load() {
      if (!this.planId) return;
      this.loading = true;
      const r = await this.$http.get(`/plans/${this.planId}/features`);
      // Deep clone so we can mutate freely
      this.features = r.data.data.map(f => ({ ...f }));
      this.loading = false;
    },

    reset() {
      this.features = [];
    },

    async save() {
      this.saving = true;
      try {
        await this.$http.post(`/plans/${this.planId}/features`, {
          features: this.features,
        });
        this.$message.success('Features actualizadas');
        this.$emit('saved');
        this.visible = false;
      } catch (e) {
        this.$message.error('Error al guardar');
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>
