<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">💬 WhatsApp — SuperAdmin</h3>
      <span class="badge" :class="configured ? 'bg-success' : 'bg-secondary'">
        {{ configured ? 'Configurado' : 'No configurado' }}
      </span>
    </div>

    <p class="text-muted small mb-4">
      Envía mensajes de WhatsApp desde el SaaS hacia tus tenants:
      recordatorios de plan, anuncios, soporte. Usa el gateway QR API
      configurado a nivel sistema.
    </p>

    <!-- Stats -->
    <div class="row g-2 mb-4">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body py-2">
          <div class="text-muted small">Enviados hoy</div>
          <div class="h4 mb-0 text-success">{{ stats.today_sent || 0 }}</div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body py-2">
          <div class="text-muted small">Fallidos hoy</div>
          <div class="h4 mb-0 text-danger">{{ stats.today_failed || 0 }}</div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body py-2">
          <div class="text-muted small">Total histórico</div>
          <div class="h4 mb-0">{{ stats.total || 0 }}</div>
        </div></div>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'settings' }" @click.prevent="tab = 'settings'" href="#">
          ⚙️ Configuración
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'notify' }" @click.prevent="tab = 'notify'" href="#">
          📤 Notificar a tenant
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'logs' }" @click.prevent="tab = 'logs'" href="#" @click="loadLogs">
          📋 Historial
        </a>
      </li>
    </ul>

    <!-- TAB: Settings -->
    <div v-if="tab === 'settings'" class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Gateway QR API del sistema</h5>

        <div class="mb-3">
          <label class="form-label fw-bold">URL del gateway</label>
          <input
            type="text"
            class="form-control"
            v-model="config.qr_api_url"
            placeholder="https://wa.ebaemy.com" />
          <small class="text-muted">
            Endpoint base del gateway. Se invoca <code>{url}/api/message/send-text</code>.
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Token (Bearer)</label>
          <input
            type="password"
            class="form-control"
            v-model="config.qr_api_token"
            :placeholder="config.has_token ? '•••••••••• (guardado)' : 'Pega aquí el token'" />
          <small class="text-muted">
            Déjalo vacío para conservar el token actual.
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Mensaje plantilla por defecto</label>
          <textarea
            class="form-control"
            rows="3"
            v-model="config.qr_api_msg"
            placeholder="Hola {nombre}, te escribimos desde EBAEMY..."></textarea>
        </div>

        <button class="btn btn-primary me-2" @click="saveSettings" :disabled="saving">
          {{ saving ? 'Guardando...' : 'Guardar' }}
        </button>

        <hr class="my-4" />

        <h5 class="mb-3">Probar envío</h5>
        <div class="row g-2">
          <div class="col-md-4">
            <input type="text" class="form-control" v-model="testForm.phone" placeholder="51999999999" />
          </div>
          <div class="col-md-6">
            <input type="text" class="form-control" v-model="testForm.message" placeholder="Mensaje de prueba" />
          </div>
          <div class="col-md-2">
            <button class="btn btn-success w-100" @click="sendTest" :disabled="testing">
              {{ testing ? '...' : 'Enviar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Notificar tenant -->
    <div v-if="tab === 'notify'" class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Enviar mensaje a un tenant</h5>

        <div class="mb-3">
          <label class="form-label fw-bold">Tenant</label>
          <select class="form-select" v-model="notifyForm.hostname_id" @change="onTenantSelect">
            <option :value="null">— Selecciona un tenant —</option>
            <option v-for="t in tenants" :key="t.id" :value="t.id">
              {{ t.fqdn }} <span v-if="t.client_name">({{ t.client_name }})</span>
            </option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Teléfono destino</label>
          <input type="text" class="form-control" v-model="notifyForm.phone" placeholder="51999999999" />
          <small class="text-muted">Auto-rellena con el del cliente si seleccionas tenant.</small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Mensaje</label>
          <textarea class="form-control" rows="4" v-model="notifyForm.message"
            placeholder="Hola, te escribimos desde EBAEMY..."></textarea>
        </div>

        <button class="btn btn-primary" @click="sendNotify" :disabled="notifying || !notifyForm.hostname_id">
          {{ notifying ? 'Enviando...' : 'Enviar notificación' }}
        </button>
      </div>
    </div>

    <!-- TAB: Logs -->
    <div v-if="tab === 'logs'" class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Últimos 100 envíos</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tenant</th>
                <th>Teléfono</th>
                <th>Mensaje</th>
                <th>Estado</th>
                <th>Origen</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!logs.length">
                <td colspan="6" class="text-center text-muted py-3">Sin envíos aún</td>
              </tr>
              <tr v-for="log in logs" :key="log.id">
                <td><small>{{ formatDate(log.created_at) }}</small></td>
                <td><small>{{ log.tenant_hostname_id || '—' }}</small></td>
                <td><code>{{ log.recipient_phone }}</code></td>
                <td><small>{{ truncate(log.message, 60) }}</small></td>
                <td>
                  <span class="badge"
                    :class="log.status === 'sent' ? 'bg-success' : log.status === 'failed' ? 'bg-danger' : 'bg-secondary'">
                    {{ log.status }}
                  </span>
                </td>
                <td><small class="text-muted">{{ log.source }}</small></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      tab: 'settings',
      configured: false,
      config: {
        qr_api_url: '',
        qr_api_token: '',
        qr_api_msg: '',
        has_token: false,
      },
      stats: {},
      tenants: [],
      logs: [],
      testForm: { phone: '', message: 'Mensaje de prueba desde EBAEMY SuperAdmin' },
      notifyForm: { hostname_id: null, phone: '', message: '' },
      saving: false,
      testing: false,
      notifying: false,
    };
  },
  created() {
    this.loadData();
    this.loadTenants();
  },
  methods: {
    async loadData() {
      try {
        const { data } = await this.$http.get('/admin/whatsapp/data');
        this.configured = data.configured;
        this.config = { ...this.config, ...data.config };
        this.stats = data.stats;
      } catch (e) {
        console.error(e);
      }
    },
    async loadTenants() {
      try {
        const { data } = await this.$http.get('/admin/whatsapp/tenants');
        this.tenants = data;
      } catch (e) {
        console.error(e);
      }
    },
    async loadLogs() {
      try {
        const { data } = await this.$http.get('/admin/whatsapp/logs');
        this.logs = data;
      } catch (e) {
        console.error(e);
      }
    },
    async saveSettings() {
      this.saving = true;
      try {
        const payload = {
          qr_api_url: this.config.qr_api_url,
          qr_api_msg: this.config.qr_api_msg,
        };
        if (this.config.qr_api_token) {
          payload.qr_api_token = this.config.qr_api_token;
        }
        const { data } = await this.$http.put('/admin/whatsapp/settings', payload);
        this.$message({ message: data.message, type: data.success ? 'success' : 'error' });
        this.config.qr_api_token = '';
        this.loadData();
      } catch (e) {
        this.$message({ message: 'Error al guardar', type: 'error' });
      } finally {
        this.saving = false;
      }
    },
    async sendTest() {
      this.testing = true;
      try {
        const { data } = await this.$http.post('/admin/whatsapp/test', this.testForm);
        this.$message({ message: data.message, type: data.success ? 'success' : 'error' });
        this.loadData();
      } catch (e) {
        this.$message({ message: 'Error en envío de prueba', type: 'error' });
      } finally {
        this.testing = false;
      }
    },
    async sendNotify() {
      this.notifying = true;
      try {
        const { data } = await this.$http.post('/admin/whatsapp/notify-tenant', this.notifyForm);
        this.$message({ message: data.message, type: data.success ? 'success' : 'error' });
        if (data.success) {
          this.notifyForm.message = '';
        }
        this.loadData();
      } catch (e) {
        const msg = e.response?.data?.message || 'Error al notificar';
        this.$message({ message: msg, type: 'error' });
      } finally {
        this.notifying = false;
      }
    },
    onTenantSelect() {
      const t = this.tenants.find((x) => x.id === this.notifyForm.hostname_id);
      if (t && t.client_phone) {
        this.notifyForm.phone = t.client_phone;
      }
    },
    formatDate(iso) {
      if (!iso) return '—';
      try { return new Date(iso).toLocaleString('es-PE'); } catch { return iso; }
    },
    truncate(s, n) {
      if (!s) return '';
      return s.length > n ? s.substring(0, n) + '...' : s;
    },
  },
};
</script>
