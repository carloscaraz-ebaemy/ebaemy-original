<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config shadow-sm">
      <div class="card-header" style="background:#1a1a2e;color:#fff">
        <h3 class="my-0 d-flex align-items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0">
            <circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
            <path d="M4.93 4.93a10 10 0 0 0 0 14.14"/>
            <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M8.46 8.46a5 5 0 0 0 0 7.07"/>
          </svg>
          Píxeles de Publicidad
        </h3>
      </div>

      <div class="card-body">
        <p class="text-muted" style="font-size:1.25rem;margin-bottom:1.5rem">
          Conecta tus píxeles para rastrear visitas, carritos y ventas en Facebook/Instagram, TikTok y Google Ads.
        </p>

        <form autocomplete="off" @submit.prevent="submit">

          <!-- Meta (Facebook) Pixel -->
          <div class="pixel-block">
            <div class="pixel-block__header">
              <div class="pixel-icon" style="background:#1877F215;color:#1877F2">
                <i class="fab fa-facebook-f"></i>
              </div>
              <div>
                <div class="pixel-block__title">Meta Pixel (Facebook / Instagram)</div>
                <div class="pixel-block__hint">Rastrea ViewContent, AddToCart, Checkout y Purchase</div>
              </div>
            </div>
            <div class="form-group mt-2 mb-0">
              <el-input v-model="form.facebook_pixel_id"
                        placeholder="Ej: 123456789012345"
                        clearable>
                <template slot="prepend">Pixel ID</template>
              </el-input>
              <small class="text-muted">
                Meta Business Suite → Administrador de Eventos → Píxeles
              </small>
            </div>
          </div>

          <!-- TikTok Pixel -->
          <div class="pixel-block">
            <div class="pixel-block__header">
              <div class="pixel-icon" style="background:#01010115;color:#010101">
                <i class="fab fa-tiktok"></i>
              </div>
              <div>
                <div class="pixel-block__title">TikTok Pixel</div>
                <div class="pixel-block__hint">Rastrea vistas, carritos y compras en TikTok Ads</div>
              </div>
            </div>
            <div class="form-group mt-2 mb-0">
              <el-input v-model="form.tiktok_pixel_id"
                        placeholder="Ej: CXXXXXXXXXXXXXXXXXXXXXX"
                        clearable>
                <template slot="prepend">Pixel ID</template>
              </el-input>
              <small class="text-muted">
                TikTok Ads Manager → Assets → Events → Web Events → Manage Pixel
              </small>
            </div>
          </div>

          <!-- Google Analytics 4 -->
          <div class="pixel-block">
            <div class="pixel-block__header">
              <div class="pixel-icon" style="background:#4285F415;color:#4285F4">
                <i class="fab fa-google"></i>
              </div>
              <div>
                <div class="pixel-block__title">Google Analytics 4 (GA4)</div>
                <div class="pixel-block__hint">Rastrea eventos de ecommerce en Google Analytics</div>
              </div>
            </div>
            <div class="form-group mt-2 mb-0">
              <el-input v-model="form.ga4_measurement_id"
                        placeholder="Ej: G-XXXXXXXXXX"
                        clearable>
                <template slot="prepend">Measurement ID</template>
              </el-input>
              <small class="text-muted">
                Google Analytics → Admin → Flujos de datos → Tu sitio web → ID de medición
              </small>
            </div>
          </div>

          <!-- Status badges -->
          <div class="pixel-status-row">
            <span class="pixel-badge" :class="form.facebook_pixel_id ? 'pixel-badge--on' : 'pixel-badge--off'">
              <i class="fab fa-facebook-f"></i>
              Meta {{ form.facebook_pixel_id ? 'Activo' : 'Inactivo' }}
            </span>
            <span class="pixel-badge" :class="form.tiktok_pixel_id ? 'pixel-badge--on' : 'pixel-badge--off'">
              <i class="fab fa-tiktok"></i>
              TikTok {{ form.tiktok_pixel_id ? 'Activo' : 'Inactivo' }}
            </span>
            <span class="pixel-badge" :class="form.ga4_measurement_id ? 'pixel-badge--on' : 'pixel-badge--off'">
              <i class="fab fa-google"></i>
              GA4 {{ form.ga4_measurement_id ? 'Activo' : 'Inactivo' }}
            </span>
          </div>

          <div class="text-end pt-3">
            <el-button type="primary" native-type="submit" :loading="loading_submit">
              Guardar Píxeles
            </el-button>
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
      headers: headers_token,
      loading_submit: false,
      resource: 'ecommerce',
      form: {
        facebook_pixel_id:  '',
        tiktok_pixel_id:    '',
        ga4_measurement_id: '',
      },
    };
  },

  async created() {
    await this.loadData();
  },

  methods: {
    async loadData() {
      try {
        const r = await this.$http.get(`/${this.resource}/record`);
        if (r.data && r.data.data) {
          this.form.facebook_pixel_id  = r.data.data.facebook_pixel_id  || '';
          this.form.tiktok_pixel_id    = r.data.data.tiktok_pixel_id    || '';
          this.form.ga4_measurement_id = r.data.data.ga4_measurement_id || '';
        }
      } catch (e) {
        console.error('Error cargando píxeles:', e);
      }
    },

    async submit() {
      this.loading_submit = true;
      try {
        const r = await this.$http.post(`/${this.resource}/configuration/pixels`, {
          facebook_pixel_id:  this.form.facebook_pixel_id  || null,
          tiktok_pixel_id:    this.form.tiktok_pixel_id    || null,
          ga4_measurement_id: this.form.ga4_measurement_id || null,
        });
        if (r.data.success) {
          this.$message.success(r.data.message);
        }
      } catch (e) {
        this.$message.error('Error al guardar los píxeles');
      } finally {
        this.loading_submit = false;
      }
    },
  },
};
</script>

<style scoped>
.pixel-block {
  border: 1px solid #e8e8e8;
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 14px;
  background: #fafafa;
}
.pixel-block__header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}
.pixel-icon {
  width: 36px; height: 36px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}
.pixel-block__title {
  font-size: 1.3rem;
  font-weight: 600;
  color: #333;
  line-height: 1.2;
}
.pixel-block__hint {
  font-size: 1.1rem;
  color: #888;
}
.pixel-status-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
  margin-bottom: 4px;
}
.pixel-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 1.15rem;
  font-weight: 500;
}
.pixel-badge--on  { background: #ecfdf5; color: #10b981; }
.pixel-badge--off { background: #f3f4f6; color: #9ca3af; }
</style>
