<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config">
      <div class="card-header bg-info">
        <h3 class="my-0">Marketplaces</h3>
      </div>
      <div class="card-body">

        <div class="row">

          <!-- Saga Falabella -->
          <div class="col-12 mb-3">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center" style="background:#f8f9fa">
                <strong>🟠 Saga Falabella</strong>
                <el-switch v-model="form.falabella_active" active-text="Activo" inactive-text="Inactivo" />
              </div>
              <div class="card-body" v-if="form.falabella_active">
                <div class="form-group">
                  <label class="control-label">User ID (Email vendedor)</label>
                  <el-input v-model="form.falabella_user_id" placeholder="seller@empresa.com"></el-input>
                </div>
                <div class="form-group">
                  <label class="control-label">API Key</label>
                  <el-input v-model="form.falabella_api_key" placeholder="Tu API Key de Seller Center" show-password></el-input>
                </div>
                <div class="form-group">
                  <label class="control-label">API URL</label>
                  <el-input v-model="form.falabella_api_url" placeholder="https://sellercenter-api.falabella.com"></el-input>
                </div>
                <el-button size="small" type="success" @click="testConnection('falabella')" :loading="testing_falabella">
                  Probar conexión
                </el-button>
                <span v-if="test_result_falabella" :class="test_result_falabella === 'ok' ? 'text-success' : 'text-danger'" style="margin-left:10px">
                  {{ test_result_falabella === 'ok' ? '✓ Conectado' : '✗ Error de conexión' }}
                </span>
              </div>
            </div>
          </div>

          <!-- MercadoLibre -->
          <div class="col-12 mb-3">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center" style="background:#f8f9fa">
                <strong>🟡 MercadoLibre</strong>
                <el-switch v-model="form.mercadolibre_active" active-text="Activo" inactive-text="Inactivo" />
              </div>
              <div class="card-body" v-if="form.mercadolibre_active">
                <div class="form-group">
                  <label class="control-label">Access Token</label>
                  <el-input v-model="form.mercadolibre_token" placeholder="APP_USR-..." show-password></el-input>
                </div>
                <div class="form-group">
                  <label class="control-label">Seller ID</label>
                  <el-input v-model="form.mercadolibre_seller_id" placeholder="123456789"></el-input>
                </div>
                <el-button size="small" type="success" @click="testConnection('mercadolibre')" :loading="testing_mercadolibre">
                  Probar conexión
                </el-button>
                <span v-if="test_result_mercadolibre" :class="test_result_mercadolibre === 'ok' ? 'text-success' : 'text-danger'" style="margin-left:10px">
                  {{ test_result_mercadolibre === 'ok' ? '✓ Conectado' : '✗ Error de conexión' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Meta (Facebook / Instagram) -->
          <div class="col-12 mb-3">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center" style="background:#f8f9fa">
                <strong>🔵 Meta (Facebook / Instagram)</strong>
                <el-switch v-model="form.meta_active" active-text="Activo" inactive-text="Inactivo" />
              </div>
              <div class="card-body" v-if="form.meta_active">
                <div class="form-group">
                  <label class="control-label">Catalog ID</label>
                  <el-input v-model="form.meta_catalog_id" placeholder="ID del catálogo en Meta Business"></el-input>
                </div>
                <div class="form-group">
                  <label class="control-label">Access Token</label>
                  <el-input v-model="form.meta_access_token" placeholder="Token de acceso" show-password></el-input>
                </div>
                <div class="form-group">
                  <label class="control-label">Feed URL (automático)</label>
                  <el-input :value="feedUrl" readonly>
                    <template slot="append">
                      <el-button @click="copyFeedUrl" size="mini">Copiar</el-button>
                    </template>
                  </el-input>
                  <small class="text-muted">Usa esta URL en Meta Business para importar tu catálogo</small>
                </div>
                <el-button size="small" type="warning" @click="regenerateFeed" :loading="regenerating_feed">
                  Regenerar Feed
                </el-button>
                <span v-if="feed_message" class="text-success" style="margin-left:10px">{{ feed_message }}</span>
              </div>
            </div>
          </div>

          <!-- TikTok Shop -->
          <div class="col-12 mb-3">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center" style="background:#f8f9fa">
                <strong>⚫ TikTok Shop</strong>
                <el-switch v-model="form.tiktok_active" active-text="Activo" inactive-text="Inactivo" />
              </div>
              <div class="card-body" v-if="form.tiktok_active">
                <div class="form-group">
                  <label class="control-label">App Key</label>
                  <el-input v-model="form.tiktok_app_key" placeholder="App Key de TikTok Shop" show-password></el-input>
                </div>
                <div class="form-group">
                  <label class="control-label">App Secret</label>
                  <el-input v-model="form.tiktok_app_secret" placeholder="App Secret" show-password></el-input>
                </div>
                <small class="text-muted">Próximamente: sincronización automática con TikTok Shop</small>
              </div>
            </div>
          </div>

        </div>

        <!-- Guardar -->
        <div class="text-end pt-3">
          <el-button type="primary" @click="submit" :loading="loading_submit" icon="el-icon-check">
            Guardar configuración de marketplaces
          </el-button>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading_submit: false,
      resource: 'ecommerce',
      form: {
        falabella_active: false,
        falabella_user_id: '',
        falabella_api_key: '',
        falabella_api_url: 'https://sellercenter-api.falabella.com',
        mercadolibre_active: false,
        mercadolibre_token: '',
        mercadolibre_seller_id: '',
        meta_active: false,
        meta_catalog_id: '',
        meta_access_token: '',
        tiktok_active: false,
        tiktok_app_key: '',
        tiktok_app_secret: '',
      },
      testing_falabella: false,
      testing_mercadolibre: false,
      test_result_falabella: null,
      test_result_mercadolibre: null,
      regenerating_feed: false,
      feed_message: '',
    };
  },

  computed: {
    feedUrl() {
      return window.location.origin + '/storage/feeds/meta-catalog.xml';
    }
  },

  async created() {
    try {
      const response = await this.$http.get(`/${this.resource}/configuration_marketplaces`);
      if (response.data && response.data.data) {
        Object.assign(this.form, response.data.data);
      }
    } catch (e) {
      // First time — no config saved yet
    }
  },

  methods: {
    async submit() {
      this.loading_submit = true;
      try {
        const response = await this.$http.post(`/${this.resource}/configuration_marketplaces`, this.form);
        if (response.data.success) {
          this.$message.success(response.data.message || 'Configuración guardada');
        } else {
          this.$message.error(response.data.message || 'Error al guardar');
        }
      } catch (e) {
        this.$message.error('Error al guardar configuración');
      }
      this.loading_submit = false;
    },

    async testConnection(platform) {
      this['testing_' + platform] = true;
      this['test_result_' + platform] = null;
      try {
        const response = await this.$http.post(`/${this.resource}/test_marketplace_connection`, {
          platform: platform,
          credentials: this.form,
        });
        this['test_result_' + platform] = response.data.success ? 'ok' : 'error';
      } catch (e) {
        this['test_result_' + platform] = 'error';
      }
      this['testing_' + platform] = false;
    },

    async regenerateFeed() {
      this.regenerating_feed = true;
      this.feed_message = '';
      try {
        const response = await this.$http.post(`/${this.resource}/regenerate_feed`);
        this.feed_message = response.data.success ? '✓ Feed regenerado' : 'Error al generar feed';
      } catch (e) {
        this.feed_message = 'Error al generar feed';
      }
      this.regenerating_feed = false;
    },

    copyFeedUrl() {
      navigator.clipboard.writeText(this.feedUrl)
        .then(() => this.$message.success('URL copiada'))
        .catch(() => this.$message.error('No se pudo copiar'));
    }
  }
};
</script>
