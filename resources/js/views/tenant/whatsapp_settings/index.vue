<template>
  <div class="wa-settings" v-loading="loading">
    <!-- Header -->
    <div class="page-header pe-0">
      <h2>
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top:-4px">
          <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"></path>
          <path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"></path>
        </svg>
        WhatsApp — Configuración
      </h2>
      <ol class="breadcrumbs">
        <li class="active"><span>Tienda Virtual / WhatsApp</span></li>
      </ol>
    </div>

    <!-- Resumen driver activo -->
    <div class="wa-card" :class="{ 'wa-card--ok': data && data.active_configured, 'wa-card--warn': data && !data.active_configured }">
      <div class="wa-card__body">
        <div>
          <div class="wa-card__label">Driver activo</div>
          <div class="wa-card__value">
            {{ data && data.active_driver ? driverLabel(data.active_driver) : '—' }}
          </div>
        </div>
        <div>
          <div class="wa-card__label">Estado</div>
          <div class="wa-card__badge" :class="data && data.active_configured ? 'wa-badge--ok' : 'wa-badge--warn'">
            {{ data && data.active_configured ? '✓ Configurado' : '⚠ Sin configurar' }}
          </div>
        </div>
        <div>
          <div class="wa-card__label">Hoy</div>
          <div class="wa-card__kpi">
            <span class="wa-kpi wa-kpi--green">✓ {{ data && data.stats_today ? data.stats_today.sent : 0 }}</span>
            <span class="wa-kpi wa-kpi--red">✗ {{ data && data.stats_today ? data.stats_today.failed : 0 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Selector de driver -->
    <div class="wa-section">
      <h3>Proveedor de WhatsApp</h3>
      <p class="wa-hint">Elige qué servicio usar para enviar mensajes. Si dejás la selección automática, el sistema usará el primero disponible.</p>
      <div class="wa-driver-grid">
        <label v-for="d in (data ? data.drivers : [])" :key="d.name" class="wa-driver-card"
               :class="{ 'is-selected': config.whatsapp_driver === d.name, 'is-configured': d.configured }">
          <input type="radio" v-model="config.whatsapp_driver" :value="d.name" />
          <div class="wa-driver-card__body">
            <strong>{{ d.label }}</strong>
            <span class="wa-driver-card__desc">{{ d.description }}</span>
            <span v-if="d.configured" class="wa-mini-badge wa-mini-badge--ok">✓ Configurado</span>
            <span v-else class="wa-mini-badge">Sin credenciales</span>
          </div>
        </label>
      </div>
    </div>

    <!-- Meta Cloud credenciales -->
    <div class="wa-section">
      <h3>Meta Cloud API (oficial)</h3>
      <div class="row">
        <div class="col-md-6 form-group">
          <label>Token permanente</label>
          <input type="password" class="form-control" v-model="config.whatsapp_api_token"
                 :placeholder="config.whatsapp_api_token_set ? '••••••••••••  (guardado)' : 'EAAFq...'" />
          <small class="form-text text-muted">
            Déjalo vacío para conservar el actual. Obténlo en Business Manager → System Users.
          </small>
        </div>
        <div class="col-md-3 form-group">
          <label>Phone Number ID</label>
          <input type="text" class="form-control" v-model="config.whatsapp_phone_id" placeholder="123456789012345" />
        </div>
        <div class="col-md-3 form-group">
          <label>WhatsApp del admin</label>
          <input type="text" class="form-control" v-model="config.whatsapp_vendor_number" placeholder="51999999999" />
          <small class="form-text text-muted">Para recibir alertas de nuevos pedidos.</small>
        </div>
      </div>
    </div>

    <!-- QR API (legacy) -->
    <div class="wa-section">
      <h3>QR API / WhatsApp-Web <small class="wa-muted">— opcional, no oficial</small></h3>
      <div class="row">
        <div class="col-md-2 form-group">
          <label>Habilitar</label>
          <div class="wa-switch">
            <el-switch v-model="config.qr_api_enable" active-color="#1fb1a6"></el-switch>
          </div>
        </div>
        <div class="col-md-6 form-group">
          <label>URL del gateway</label>
          <input type="text" class="form-control" v-model="config.qr_api_url" placeholder="https://wa.midominio.com" />
        </div>
        <div class="col-md-4 form-group">
          <label>API Key</label>
          <input type="password" class="form-control" v-model="config.qr_api_apiKey"
                 :placeholder="config.qr_api_apiKey_set ? '••••••••••••  (guardado)' : 'token'" />
        </div>
      </div>
    </div>

    <!-- Notificaciones -->
    <div class="wa-section">
      <h3>Notificaciones automáticas</h3>
      <p class="wa-hint">Activa o desactiva qué eventos disparan un mensaje de WhatsApp.</p>
      <div class="wa-toggles">
        <label v-for="(label, key) in (data ? data.notification_types : {})" :key="key" class="wa-toggle">
          <el-switch v-model="config.notifications[key]" active-color="#1fb1a6"></el-switch>
          <span>{{ label }}</span>
        </label>
      </div>
    </div>

    <!-- Acciones -->
    <div class="wa-actions">
      <el-button @click="load" :disabled="loading">Recargar</el-button>
      <el-button type="primary" @click="save" :loading="saving">Guardar configuración</el-button>
    </div>

    <!-- Test send -->
    <div class="wa-section">
      <h3>Probar envío</h3>
      <p class="wa-hint">Envía un mensaje de prueba al número que indiques para validar que la configuración funciona.</p>
      <div class="row">
        <div class="col-md-4 form-group">
          <label>Número (con o sin prefijo)</label>
          <input type="text" class="form-control" v-model="test.phone" placeholder="999999999 o 51999999999" />
        </div>
        <div class="col-md-6 form-group">
          <label>Mensaje (opcional)</label>
          <input type="text" class="form-control" v-model="test.message" placeholder="Mensaje de prueba..." />
        </div>
        <div class="col-md-2 form-group">
          <label>&nbsp;</label>
          <el-button type="success" @click="sendTest" :loading="test.sending" :disabled="!test.phone" style="width:100%">
            Enviar prueba
          </el-button>
        </div>
      </div>
      <div v-if="test.result" class="wa-result" :class="test.result.success ? 'wa-result--ok' : 'wa-result--err'">
        <b>{{ test.result.success ? '✓' : '✗' }}</b> {{ test.result.message }}
        <span v-if="test.result.driver" class="wa-muted">(driver: {{ test.result.driver }})</span>
      </div>
    </div>

    <!-- Plantillas Meta -->
    <div class="wa-section" v-if="data && data.active_driver === 'meta_cloud'">
      <h3>
        Plantillas aprobadas (Meta)
        <el-button size="mini" @click="loadTemplates" :loading="templates.loading" style="margin-left:10px">
          {{ templates.items.length ? 'Recargar' : 'Cargar' }}
        </el-button>
      </h3>
      <p class="wa-hint">Solo plantillas con status "APPROVED" pueden usarse para mensajes iniciados por el negocio.</p>
      <div v-if="templates.error" class="wa-result wa-result--err">
        <b>⚠</b> {{ templates.error }}
      </div>
      <table v-if="templates.items.length" class="table wa-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Idioma</th>
            <th>Estado</th>
            <th>Contenido</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in templates.items" :key="t.name + t.language">
            <td><code>{{ t.name }}</code></td>
            <td>{{ t.category }}</td>
            <td>{{ t.language }}</td>
            <td>
              <span class="wa-status-pill" :class="'wa-status-' + (t.status||'').toLowerCase()">{{ t.status }}</span>
            </td>
            <td class="wa-tpl-body">{{ t.body }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else-if="templates.loaded" class="wa-empty">No hay plantillas. Créalas en Meta Business Manager.</div>
    </div>

    <!-- Logs recientes -->
    <div class="wa-section">
      <h3>
        Últimos envíos
        <el-button size="mini" @click="loadLogs" :loading="logs.loading" style="margin-left:10px">Recargar</el-button>
      </h3>
      <table class="table wa-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Teléfono</th>
            <th>Driver</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Estado</th>
            <th>Error</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in logs.items" :key="l.id">
            <td class="wa-muted">{{ l.created_at }}</td>
            <td>{{ l.phone }}</td>
            <td>{{ l.driver }}</td>
            <td>{{ l.type }}<span v-if="l.template" class="wa-muted"> / {{ l.template }}</span></td>
            <td>{{ l.source || '—' }}<span v-if="l.source_id" class="wa-muted"> #{{ l.source_id }}</span></td>
            <td>
              <span class="wa-status-pill" :class="l.status === 'sent' ? 'wa-status-approved' : 'wa-status-rejected'">
                {{ l.status === 'sent' ? '✓ sent' : '✗ failed' }}
              </span>
            </td>
            <td class="wa-muted">{{ l.error || '' }}</td>
          </tr>
          <tr v-if="!logs.items.length && !logs.loading">
            <td colspan="7" class="wa-empty">Todavía no hay envíos registrados.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WhatsAppSettings',
  data() {
    return {
      loading: false,
      saving: false,
      data: null,
      config: {
        whatsapp_driver: null,
        whatsapp_api_token: '',
        whatsapp_api_token_set: false,
        whatsapp_phone_id: '',
        whatsapp_vendor_number: '',
        qr_api_enable: false,
        qr_api_url: '',
        qr_api_apiKey: '',
        qr_api_apiKey_set: false,
        notifications: {},
      },
      test: {
        phone: '',
        message: '',
        sending: false,
        result: null,
      },
      templates: { loading: false, items: [], error: null, loaded: false },
      logs: { loading: false, items: [] },
    };
  },
  created() {
    this.load();
    this.loadLogs();
  },
  methods: {
    driverLabel(name) {
      return { meta_cloud: 'Meta Cloud API', qr_api: 'QR API (WhatsApp-Web)', none: 'Deshabilitado' }[name] || name;
    },
    async load() {
      this.loading = true;
      try {
        const r = await this.$http.get('/whatsapp/settings/data');
        this.data = r.data;
        this.config = { ...this.config, ...r.data.config };
        this.config.whatsapp_api_token = '';
        this.config.qr_api_apiKey = '';
      } catch (e) {
        this.$message.error('No se pudo cargar la configuración');
      } finally {
        this.loading = false;
      }
    },
    async save() {
      this.saving = true;
      try {
        const payload = {
          whatsapp_driver: this.config.whatsapp_driver,
          whatsapp_phone_id: this.config.whatsapp_phone_id,
          whatsapp_vendor_number: this.config.whatsapp_vendor_number,
          notifications: this.config.notifications,
          qr_api_enable: this.config.qr_api_enable,
          qr_api_url: this.config.qr_api_url,
        };
        if (this.config.whatsapp_api_token) payload.whatsapp_api_token = this.config.whatsapp_api_token;
        if (this.config.qr_api_apiKey) payload.qr_api_apiKey = this.config.qr_api_apiKey;

        const r = await this.$http.put('/whatsapp/settings', payload);
        this.$message.success(r.data.message || 'Configuración guardada');
        await this.load();
      } catch (e) {
        this.$message.error(e?.response?.data?.message || 'No se pudo guardar');
      } finally {
        this.saving = false;
      }
    },
    async sendTest() {
      if (!this.test.phone) return;
      this.test.sending = true;
      this.test.result = null;
      try {
        const r = await this.$http.post('/whatsapp/settings/test', {
          phone: this.test.phone,
          message: this.test.message || null,
        });
        this.test.result = r.data;
        await this.loadLogs();
      } catch (e) {
        this.test.result = e?.response?.data || { success: false, message: 'Error al enviar prueba' };
      } finally {
        this.test.sending = false;
      }
    },
    async loadTemplates() {
      this.templates.loading = true;
      this.templates.error = null;
      try {
        const r = await this.$http.get('/whatsapp/settings/templates');
        this.templates.items = r.data.templates || [];
        if (r.data.error) this.templates.error = r.data.error;
      } catch (e) {
        this.templates.error = e?.response?.data?.error || 'Error al cargar plantillas';
      } finally {
        this.templates.loading = false;
        this.templates.loaded = true;
      }
    },
    async loadLogs() {
      this.logs.loading = true;
      try {
        const r = await this.$http.get('/whatsapp/settings/logs', { params: { limit: 30 } });
        this.logs.items = r.data.logs || [];
      } catch (e) {
        // silencioso
      } finally {
        this.logs.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.wa-settings { padding: 20px; }

.wa-card {
  display: flex; align-items: center;
  background: #fff; border: 1px solid var(--eb-line, #e2e8f0);
  border-radius: 14px; padding: 20px 24px; margin-bottom: 22px;
  box-shadow: 0 2px 6px rgba(0,0,0,.04);
}
.wa-card--ok { border-left: 4px solid #10b981; }
.wa-card--warn { border-left: 4px solid #f59e0b; }
.wa-card__body { display: flex; gap: 48px; flex-wrap: wrap; width: 100%; }
.wa-card__label { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.wa-card__value { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 4px; }
.wa-card__badge { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-top: 4px; }
.wa-badge--ok { background: #d1fae5; color: #065f46; }
.wa-badge--warn { background: #fef3c7; color: #92400e; }
.wa-card__kpi { display: flex; gap: 8px; margin-top: 4px; }
.wa-kpi { padding: 4px 10px; border-radius: 8px; font-size: 14px; font-weight: 600; }
.wa-kpi--green { background: #d1fae5; color: #065f46; }
.wa-kpi--red { background: #fee2e2; color: #991b1b; }

.wa-section {
  background: #fff; border: 1px solid var(--eb-line, #e2e8f0);
  border-radius: 14px; padding: 22px 24px; margin-bottom: 20px;
}
.wa-section h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 6px; }
.wa-hint { color: #64748b; font-size: 13px; margin: 0 0 14px; }
.wa-muted { color: #94a3b8; font-weight: 400; font-size: 12px; }

.wa-driver-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
.wa-driver-card {
  display: flex; gap: 12px; padding: 14px 16px;
  border: 1.5px solid var(--eb-line, #e2e8f0); border-radius: 12px; cursor: pointer;
  transition: all .18s ease; background: #fff;
}
.wa-driver-card input { margin-top: 4px; }
.wa-driver-card__body { display: flex; flex-direction: column; gap: 4px; }
.wa-driver-card__desc { font-size: 12px; color: #64748b; }
.wa-driver-card:hover { border-color: #1fb1a6; }
.wa-driver-card.is-selected { border-color: #1fb1a6; background: #e8f6f5; }
.wa-mini-badge {
  display: inline-block; padding: 2px 8px; border-radius: 999px;
  background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; margin-top: 4px; align-self: flex-start;
}
.wa-mini-badge--ok { background: #d1fae5; color: #065f46; }

.wa-toggles { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px 18px; }
.wa-toggle { display: flex; align-items: center; gap: 10px; padding: 6px 0; cursor: pointer; font-size: 14px; }

.wa-switch { padding-top: 10px; }

.wa-actions { display: flex; gap: 10px; justify-content: flex-end; margin: 10px 0 24px; }

.wa-result {
  margin-top: 12px; padding: 10px 14px; border-radius: 10px;
  font-size: 14px;
}
.wa-result--ok { background: #d1fae5; color: #065f46; }
.wa-result--err { background: #fee2e2; color: #991b1b; }

.wa-table { font-size: 13px; }
.wa-table th { background: #f9fafb; font-weight: 600; color: #0f172a; font-size: 12px; }
.wa-table td.wa-muted { color: #94a3b8; font-size: 12px; }
.wa-status-pill { padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.wa-status-approved { background: #d1fae5; color: #065f46; }
.wa-status-rejected { background: #fee2e2; color: #991b1b; }
.wa-status-pending { background: #fef3c7; color: #92400e; }
.wa-tpl-body { max-width: 420px; font-size: 12px; color: #475569; white-space: pre-wrap; }

.wa-empty { padding: 24px; text-align: center; color: #94a3b8; }
</style>
