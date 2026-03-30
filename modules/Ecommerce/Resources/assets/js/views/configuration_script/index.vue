<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config shadow-sm">

      <!-- Header -->
      <div class="card-header cs-header">
        <div class="cs-header__left">
          <div class="cs-header__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
            </svg>
          </div>
          <div>
            <h3 class="my-0">Scripts Personalizados</h3>
            <small>Inyecta código de terceros en tu tienda (GTM, Hotjar, LiveChat, etc.)</small>
          </div>
        </div>
        <div class="cs-header__right">
          <span class="cs-count-badge">{{ scripts.length }} script{{ scripts.length !== 1 ? 's' : '' }}</span>
        </div>
      </div>

      <div class="card-body cs-body">

        <!-- Plataformas rápidas -->
        <div class="cs-quick-platforms">
          <span class="cs-quick-label">Agregar rápido:</span>
          <button v-for="tpl in templates" :key="tpl.key"
                  type="button" class="cs-quick-btn"
                  :style="{ '--tpl-color': tpl.color }"
                  @click="addFromTemplate(tpl)">
            <span class="cs-quick-btn__dot" :style="{ background: tpl.color }"></span>
            {{ tpl.label }}
          </button>
          <button type="button" class="cs-quick-btn cs-quick-btn--custom" @click="addCustom">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Personalizado
          </button>
        </div>

        <!-- Empty state -->
        <div v-if="scripts.length === 0" class="cs-empty">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="1" opacity=".3">
            <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
          </svg>
          <p>No hay scripts configurados</p>
          <small>Usa los botones de arriba para agregar un script</small>
        </div>

        <!-- Lista de scripts (grid horizontal) -->
        <div v-else class="cs-list" style="display:flex;flex-wrap:wrap;gap:12px">
          <div v-for="(item, index) in scripts" :key="index"
               class="cs-item" :class="{ 'cs-item--inactive': !item.active, 'cs-item--open': item._open }"
               :style="{ flex: item._open ? '1 1 100%' : '1 1 calc(50% - 6px)', maxWidth: item._open ? '100%' : 'calc(50% - 6px)' }">

            <!-- Cabecera del item -->
            <div class="cs-item__head" @click="toggleItem(index)">
              <div class="cs-item__head-left">
                <span class="cs-item__drag" title="Orden">⠿</span>
                <span class="cs-platform-dot"
                      :style="{ background: getPlatformColor(item.title) }"></span>
                <span class="cs-item__title">
                  {{ item.title || 'Sin nombre' }}
                </span>
                <span class="cs-pos-badge" :class="'cs-pos-badge--' + (item.position || 'head')">
                  {{ item.position || 'head' }}
                </span>
                <span v-if="!item.active" class="cs-inactive-badge">Inactivo</span>
              </div>
              <div class="cs-item__head-right">
                <span class="cs-toggle-indicator">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                       fill="none" stroke="currentColor" stroke-width="2.5"
                       :style="{ transform: item._open ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform .2s' }">
                    <polyline points="6 9 12 15 18 9"/>
                  </svg>
                </span>
              </div>
            </div>

            <!-- Cuerpo expandible -->
            <div v-show="item._open" class="cs-item__body">
              <div class="cs-item__fields">

                <!-- Nombre + Posición + Activo -->
                <div class="cs-item__row">
                  <div class="cs-item__field cs-item__field--name">
                    <label class="cs-label">Nombre / Plataforma</label>
                    <el-input v-model="item.title" placeholder="Ej: Google Tag Manager" clearable />
                  </div>
                  <div class="cs-item__field cs-item__field--pos">
                    <label class="cs-label">Posición</label>
                    <el-select v-model="item.position" placeholder="Selecciona">
                      <el-option label="&lt;head&gt; — antes del CSS" value="head">
                        <span class="cs-opt-head">&lt;head&gt;</span>
                        <small class="text-muted ml-1">antes del CSS</small>
                      </el-option>
                      <el-option label="&lt;body&gt; — inicio del body" value="body">
                        <span class="cs-opt-body">&lt;body&gt;</span>
                        <small class="text-muted ml-1">inicio del body</small>
                      </el-option>
                    </el-select>
                  </div>
                  <div class="cs-item__field cs-item__field--toggle">
                    <label class="cs-label">Estado</label>
                    <div class="cs-toggle-wrap">
                      <el-switch v-model="item.active"
                                 active-text="Activo"
                                 inactive-text="Inactivo"
                                 active-color="#10b981"
                                 inactive-color="#d1d5db" />
                    </div>
                  </div>
                </div>

                <!-- Script textarea -->
                <div class="cs-item__field cs-item__field--code">
                  <label class="cs-label">
                    Código del script
                    <span class="cs-char-count" :class="item.script && item.script.length > 10000 ? 'text-danger' : 'text-muted'">
                      {{ item.script ? item.script.length.toLocaleString() : 0 }} caracteres
                    </span>
                  </label>
                  <el-input
                    type="textarea"
                    v-model="item.script"
                    :rows="6"
                    placeholder="Pega aquí el código completo, incluyendo las etiquetas <script> si las tiene."
                    class="cs-code-textarea"
                  />
                  <div class="cs-code-hint">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                      <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    Pega el código exactamente como te lo proporciona la plataforma (con etiquetas <code>&lt;script&gt;</code> incluidas si las trae).
                  </div>
                </div>

                <!-- Acciones del item -->
                <div class="cs-item__actions">
                  <el-button type="danger" size="small" plain icon="el-icon-delete"
                             @click.stop="remove(index)">
                    Eliminar script
                  </el-button>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- Botón guardar -->
        <div class="cs-footer">
          <button type="button" class="cs-add-btn" @click="addCustom">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Agregar script
          </button>
          <el-button type="primary" :loading="loading_submit" @click="submit" class="cs-save-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
              <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            Guardar todos los scripts
          </el-button>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
const PLATFORM_COLORS = {
  'google tag manager': '#4285F4',
  'gtm': '#4285F4',
  'google analytics': '#E37400',
  'ga4': '#E37400',
  'hotjar': '#FD3A5C',
  'facebook': '#1877F2',
  'meta': '#1877F2',
  'tiktok': '#010101',
  'livechat': '#F5A623',
  'intercom': '#286EFA',
  'crisp': '#1972F5',
  'clarity': '#0078D4',
  'microsoft clarity': '#0078D4',
  'hubspot': '#FF7A59',
  'mailchimp': '#FFE01B',
  'zendesk': '#03363D',
};

function getPlatformColor(title) {
  if (!title) return '#6b7280';
  const key = title.toLowerCase();
  for (const [k, v] of Object.entries(PLATFORM_COLORS)) {
    if (key.includes(k)) return v;
  }
  // Generate consistent color from string
  let hash = 0;
  for (let i = 0; i < title.length; i++) hash = title.charCodeAt(i) + ((hash << 5) - hash);
  const hue = Math.abs(hash) % 360;
  return `hsl(${hue}, 55%, 45%)`;
}

export default {
  data() {
    return {
      loading_submit: false,
      scripts: [],
      templates: [
        { key: 'gtm',      label: 'Google Tag Manager', color: '#4285F4', position: 'head',   script: '' },
        { key: 'hotjar',   label: 'Hotjar',             color: '#FD3A5C', position: 'head',   script: '' },
        { key: 'clarity',  label: 'MS Clarity',         color: '#0078D4', position: 'head',   script: '' },
        { key: 'livechat', label: 'LiveChat',            color: '#F5A623', position: 'body',   script: '' },
        { key: 'intercom', label: 'Intercom',            color: '#286EFA', position: 'body',   script: '' },
        { key: 'hubspot',  label: 'HubSpot',             color: '#FF7A59', position: 'body',   script: '' },
      ],
    };
  },

  created() {
    this.loadScripts();
  },

  methods: {
    getPlatformColor,

    async loadScripts() {
      try {
        const r = await this.$http.get('/ecommerce/social-scripts');
        this.scripts = r.data.map(s => ({ ...s, active: Boolean(s.active), _open: false }));
      } catch (e) {
        console.error(e);
      }
    },

    addCustom() {
      this.scripts.push({ title: '', script: '', position: 'head', active: true, _open: true });
    },

    addFromTemplate(tpl) {
      this.scripts.push({
        title:    tpl.label,
        script:   tpl.script,
        position: tpl.position,
        active:   true,
        _open:    true,
      });
      this.$message.info(`Plataforma "${tpl.label}" agregada. Pega el código de su panel de configuración.`);
    },

    toggleItem(index) {
      this.scripts[index]._open = !this.scripts[index]._open;
    },

    async remove(index) {
      try {
        await this.$confirm(
          `¿Eliminar el script "${this.scripts[index].title || 'sin nombre'}"?`,
          'Confirmar',
          { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
        );
        this.scripts.splice(index, 1);
        await this.submit(true);
      } catch (e) {
        if (e !== 'cancel') this.$message.error('Error al eliminar');
      }
    },

    async submit(silent = false) {
      this.loading_submit = true;
      try {
        await this.$http.post('/ecommerce/social-scripts/save-all', { scripts: this.scripts });
        if (!silent) this.$message.success('Scripts guardados correctamente');
      } catch (e) {
        this.$message.error('Error al guardar scripts');
      } finally {
        this.loading_submit = false;
      }
    },
  },
};
</script>

<style scoped>
/* ── Header ─────────────────────────────────────────────────────────── */
.cs-header {
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
}
.cs-header__left { display: flex; align-items: center; gap: 12px; }
.cs-header__icon {
  width: 38px; height: 38px;
  background: rgba(255,255,255,.12);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.cs-header h3 { font-size: 1.5rem; margin: 0; }
.cs-header small { color: rgba(255,255,255,.6); font-size: 1.1rem; }
.cs-count-badge {
  background: rgba(255,255,255,.15);
  color: #fff;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 1.15rem;
}

/* ── Body ────────────────────────────────────────────────────────────── */
.cs-body { padding: 20px; }

/* ── Quick platforms ─────────────────────────────────────────────────── */
.cs-quick-platforms {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding: 12px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}
.cs-quick-label {
  font-size: 1.2rem;
  font-weight: 600;
  color: #64748b;
  flex-shrink: 0;
}
.cs-quick-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 1.2rem;
  color: #374151;
  cursor: pointer;
  transition: all .15s;
}
.cs-quick-btn:hover {
  border-color: var(--tpl-color, #6366f1);
  color: var(--tpl-color, #6366f1);
  background: #f0f4ff;
}
.cs-quick-btn__dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.cs-quick-btn--custom {
  border-style: dashed;
  color: #6366f1;
  --tpl-color: #6366f1;
}

/* ── Empty state ────────────────────────────────────────────────────── */
.cs-empty {
  text-align: center;
  padding: 40px 20px;
  color: #9ca3af;
}
.cs-empty p { font-size: 1.4rem; margin: 12px 0 4px; color: #6b7280; }
.cs-empty small { font-size: 1.15rem; }

/* ── List ────────────────────────────────────────────────────────────── */
.cs-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }

/* ── Item ────────────────────────────────────────────────────────────── */
.cs-item {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
  transition: border-color .15s;
}
.cs-item--inactive { border-color: #e5e7eb; opacity: .7; }
.cs-item:has(.cs-item__head:hover) { border-color: #cbd5e1; }

.cs-item__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  cursor: pointer;
  background: #f8fafc;
  user-select: none;
  transition: background .12s;
}
.cs-item__head:hover { background: #f1f5f9; }
.cs-item--open .cs-item__head { background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }

.cs-item__head-left {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}
.cs-item__drag { font-size: 1.4rem; color: #cbd5e1; cursor: grab; flex-shrink: 0; }
.cs-platform-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.cs-item__title {
  font-size: 1.3rem;
  font-weight: 600;
  color: #1e293b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cs-pos-badge {
  font-size: 1.05rem;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 600;
  flex-shrink: 0;
}
.cs-pos-badge--head { background: #dbeafe; color: #1d4ed8; }
.cs-pos-badge--body { background: #fef9c3; color: #854d0e; }
.cs-inactive-badge {
  font-size: 1.05rem;
  background: #fee2e2;
  color: #b91c1c;
  padding: 2px 8px;
  border-radius: 4px;
  flex-shrink: 0;
}
.cs-item__head-right { flex-shrink: 0; margin-left: 8px; }

/* ── Item body ───────────────────────────────────────────────────────── */
.cs-item__body { padding: 16px; }
.cs-item__fields { display: flex; flex-direction: column; gap: 14px; }
.cs-item__row { display: flex; gap: 12px; flex-wrap: wrap; }
.cs-item__field { display: flex; flex-direction: column; gap: 4px; }
.cs-item__field--name  { flex: 2; min-width: 160px; }
.cs-item__field--pos   { flex: 1; min-width: 150px; }
.cs-item__field--toggle { flex: 1; min-width: 120px; }
.cs-item__field--code  { width: 100%; }

.cs-label {
  font-size: 1.2rem;
  font-weight: 600;
  color: #374151;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.cs-char-count { font-weight: 400; font-size: 1.1rem; }
.cs-toggle-wrap { padding-top: 8px; }

.cs-code-hint {
  display: flex;
  align-items: flex-start;
  gap: 5px;
  margin-top: 5px;
  font-size: 1.1rem;
  color: #6b7280;
}
.cs-code-hint code {
  background: #f3f4f6;
  padding: 0 4px;
  border-radius: 3px;
  font-size: 1.05rem;
}

.cs-item__actions {
  display: flex;
  justify-content: flex-end;
  padding-top: 4px;
}

/* ── Footer ─────────────────────────────────────────────────────────── */
.cs-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding-top: 4px;
  border-top: 1px solid #f1f5f9;
}
.cs-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 2px dashed #c7d2fe;
  background: #eef2ff;
  color: #4f46e5;
  border-radius: 8px;
  font-size: 1.25rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
}
.cs-add-btn:hover {
  background: #e0e7ff;
  border-color: #a5b4fc;
}

/* ── Code textarea ───────────────────────────────────────────────────── */
.cs-code-textarea :deep(textarea) {
  font-family: 'Courier New', Consolas, monospace;
  font-size: 1.2rem;
  background: #0f172a;
  color: #e2e8f0;
  border-radius: 6px;
  line-height: 1.6;
}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 640px) {
  .cs-item__row { flex-direction: column; }
  .cs-footer { flex-direction: column-reverse; align-items: stretch; }
  .cs-footer .el-button { width: 100%; justify-content: center; }
}
</style>
