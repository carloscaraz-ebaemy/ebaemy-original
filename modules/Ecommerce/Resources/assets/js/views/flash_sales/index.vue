<template>
    <div class="fs-panel">
        <div class="fs-panel__header">
            <div>
                <p class="text-muted mb-0">Crea ofertas relampago con cuenta regresiva para impulsar tus ventas.</p>
            </div>
            <button class="fs-btn-add" @click="openCreate">
                <i class="fa fa-plus"></i> Nueva flash sale
            </button>
        </div>

        <!-- Lista de Flash Sales -->
        <div class="fs-list" v-loading="loading">
            <div v-if="sales.length === 0 && !loading" class="fs-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
                <p>No tienes ofertas flash activas</p>
                <button class="fs-btn-add fs-btn-add--sm" @click="openCreate">Crear primera oferta</button>
            </div>

            <div v-for="sale in sales" :key="sale.id" class="fs-card" :class="{ 'fs-card--live': sale.is_live, 'fs-card--inactive': !sale.active }">
                <div class="fs-card__header">
                    <div class="fs-card__info">
                        <div class="fs-card__status">
                            <span v-if="sale.is_live" class="fs-badge fs-badge--live">
                                <span class="fs-badge__dot"></span> En vivo
                            </span>
                            <span v-else-if="sale.active" class="fs-badge fs-badge--scheduled">Programada</span>
                            <span v-else class="fs-badge fs-badge--inactive">Inactiva</span>
                        </div>
                        <h3 class="fs-card__title">{{ sale.title }}</h3>
                        <p class="fs-card__subtitle" v-if="sale.subtitle">{{ sale.subtitle }}</p>
                    </div>
                    <div class="fs-card__actions">
                        <el-tooltip content="Enviar a clientes por WhatsApp" placement="top">
                            <button class="fs-btn-icon fs-btn-icon--wa" @click="sendWhatsApp(sale)">
                                <span class="fs-btn-icon__label">WA</span>
                            </button>
                        </el-tooltip>
                        <el-switch v-model="sale.active" @change="toggleActive(sale)" active-color="#16a34a"></el-switch>
                        <el-tooltip content="Editar" placement="top">
                            <button class="fs-btn-icon" @click="openEdit(sale)" aria-label="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </button>
                        </el-tooltip>
                        <el-tooltip content="Eliminar" placement="top">
                            <button class="fs-btn-icon fs-btn-icon--danger" @click="remove(sale)"><i class="fa fa-trash"></i></button>
                        </el-tooltip>
                    </div>
                </div>

                <div class="fs-card__meta">
                    <div class="fs-meta-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>{{ sale.starts_at || 'Ahora' }} — {{ sale.ends_at }}</span>
                    </div>
                    <div class="fs-meta-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3v4M8 3v4M2 11h20"/></svg>
                        <span>{{ sale.items_count }} producto{{ sale.items_count !== 1 ? 's' : '' }}</span>
                    </div>
                </div>

                <!-- Preview de productos -->
                <div class="fs-card__products" v-if="sale.items.length">
                    <div v-for="item in sale.items.slice(0, 4)" :key="item.id" class="fs-product-chip">
                        <span class="fs-product-chip__name">{{ item.description }}</span>
                        <span class="fs-product-chip__prices">
                            <del class="text-muted">S/ {{ Number(item.regular_price).toFixed(2) }}</del>
                            <strong class="text-danger">S/ {{ Number(item.flash_price).toFixed(2) }}</strong>
                        </span>
                    </div>
                    <span v-if="sale.items.length > 4" class="fs-more">+{{ sale.items.length - 4 }} mas</span>
                </div>
            </div>
        </div>

        <!-- Dialog crear/editar -->
        <el-dialog
            :title="form.id ? 'Editar Flash Sale' : 'Nueva Flash Sale'"
            :visible.sync="dialogVisible"
            width="720px"
            custom-class="fs-dialog"
            :close-on-click-modal="false"
            top="5vh"
        >
            <div class="fs-form">
                <!-- Info basica -->
                <div class="fs-form__section">
                    <div class="fs-form__section-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
                        Informacion de la oferta
                    </div>
                    <div class="row">
                        <div class="col-md-7">
                            <div class="fs-field">
                                <label>Titulo <span class="text-danger">*</span></label>
                                <el-input v-model="form.title" placeholder="Ej: Oferta del dia" maxlength="100" show-word-limit></el-input>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="fs-field">
                                <label>Subtitulo</label>
                                <el-input v-model="form.subtitle" placeholder="Ej: Solo por hoy"></el-input>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Periodo -->
                <div class="fs-form__section">
                    <div class="fs-form__section-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Periodo de la oferta
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="fs-field">
                                <label>Inicio <small class="text-muted">(opcional, por defecto ahora)</small></label>
                                <el-date-picker v-model="form.starts_at" type="datetime" format="yyyy-MM-dd HH:mm" value-format="yyyy-MM-dd HH:mm" placeholder="Inicio" style="width:100%"></el-date-picker>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="fs-field">
                                <label>Fin <span class="text-danger">*</span></label>
                                <el-date-picker v-model="form.ends_at" type="datetime" format="yyyy-MM-dd HH:mm" value-format="yyyy-MM-dd HH:mm" placeholder="Fecha y hora de cierre" style="width:100%"></el-date-picker>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="fs-field">
                                <label>Activa</label>
                                <div style="padding-top:6px"><el-switch v-model="form.active" active-color="#16a34a"></el-switch></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Productos -->
                <div class="fs-form__section">
                    <div class="fs-form__section-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        Productos en oferta
                        <span class="badge bg-primary ms-2" v-if="form.items.length">{{ form.items.length }}</span>
                    </div>

                    <div class="fs-field" style="margin-bottom:12px">
                        <label>Agregar producto</label>
                        <el-autocomplete v-model="itemSearch" :fetch-suggestions="searchItems" placeholder="Buscar producto..." @select="addItem" style="width:100%" value-key="description" popper-class="fs-suggestions-pop">
                            <template slot-scope="{item}">
                                <div class="fs-suggestion">
                                    <img class="fs-suggestion__img" :src="item.image_url_small || item.image_url" alt="" @error="onImgError">
                                    <span class="fs-suggestion__name">{{ item.description }}</span>
                                    <span class="fs-suggestion__meta">
                                        <span v-if="item.is_set" class="fs-suggestion__pack">PACK</span>
                                        <span class="text-muted small">{{ item.sale_unit_price }}</span>
                                    </span>
                                </div>
                            </template>
                        </el-autocomplete>
                    </div>

                    <div class="fs-products-table" v-if="form.items.length">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40%">Producto</th>
                                    <th style="width:18%" class="text-center">Precio normal</th>
                                    <th style="width:22%" class="text-center">Precio flash</th>
                                    <th style="width:12%" class="text-center">Descuento</th>
                                    <th style="width:8%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.items" :key="item.id">
                                    <td class="fs-cell-name">
                                        <div class="fs-prod-name">
                                            <img class="fs-prod-name__img" :src="item.image_url" alt="" v-if="item.image_url" @error="onImgError">
                                            <strong>{{ item.description }}</strong>
                                        </div>
                                    </td>
                                    <td class="text-center text-muted" data-label="Precio normal"><del>S/ {{ Number(item.regular_price).toFixed(2) }}</del></td>
                                    <td class="text-center" data-label="Precio flash">
                                        <el-input-number v-model="item.flash_price" :min="0.01" :precision="2" :step="1" size="small" class="fs-flash-input" @change="scheduleMarginCheck"></el-input-number>
                                        <div v-if="marginChip(item)" class="fs-margin-chip" :class="'is-' + marginChip(item).status">{{ marginChip(item).text }}</div>
                                    </td>
                                    <td class="text-center" data-label="Descuento">
                                        <span class="fs-discount-badge" v-if="item.regular_price > 0">-{{ discount(item) }}%</span>
                                    </td>
                                    <td class="text-center fs-cell-remove">
                                        <button class="fs-btn-icon fs-btn-icon--danger fs-btn-icon--sm" @click="removeItem(index)"><i class="fa fa-times"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="fs-empty-products">
                        <p>Sin productos aun. Busca y agrega al menos uno.</p>
                    </div>
                </div>
            </div>

            <span slot="footer">
                <el-button @click="dialogVisible = false">Cancelar</el-button>
                <el-button type="primary" :loading="saving" @click="save">
                    <i class="fa fa-save" v-if="!saving"></i>
                    {{ form.id ? 'Actualizar' : 'Crear Flash Sale' }}
                </el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            sales: [], loading: false, saving: false, dialogVisible: false,
            itemSearch: '', _allItems: null,
            marginInfo: {}, _marginTimer: null,
            form: { id: null, title: '', subtitle: '', starts_at: null, ends_at: null, active: true, items: [] }
        };
    },
    mounted() { this.load(); },
    methods: {
        load() {
            this.loading = true;
            this.$http.get('/ecommerce/flash-sales/records')
                .then(r => { this.sales = r.data.data; })
                .finally(() => { this.loading = false; });
        },
        openCreate() {
            this.form = { id: null, title: '', subtitle: '', starts_at: null, ends_at: null, active: true, items: [] };
            this.itemSearch = '';
            this.marginInfo = {};
            this.dialogVisible = true;
        },
        openEdit(row) {
            this.form = {
                id: row.id, title: row.title, subtitle: row.subtitle || '',
                starts_at: row.starts_at || null, ends_at: row.ends_at, active: row.active,
                items: row.items.map(i => ({ id: i.id, description: i.description, regular_price: i.regular_price, flash_price: i.flash_price, image_url: i.image_url || null }))
            };
            this.itemSearch = '';
            this.marginInfo = {};
            this.dialogVisible = true;
            this.checkMargins();
        },
        save() {
            if (!this.form.title) { this.$message.warning('Ingresa el titulo'); return; }
            if (!this.form.ends_at) { this.$message.warning('Ingresa la fecha de fin'); return; }
            if (!this.form.items.length) { this.$message.warning('Agrega al menos un producto'); return; }

            // Guardrail: avisar si algún precio flash queda bajo costo o bajo el
            // margen mínimo del producto (salvo que el producto esté en liquidación).
            const risky = this.form.items.filter(i => {
                const m = this.marginInfo[i.id];
                return m && m.has_cost !== false && !m.liquidation_mode
                    && (m.status === 'block_below_cost' || m.status === 'warn_below_min');
            });
            if (risky.length) {
                const loss = risky.filter(i => this.marginInfo[i.id].status === 'block_below_cost').length;
                const msg = loss > 0
                    ? `${loss} producto(s) quedan POR DEBAJO DEL COSTO (pérdida) y ${risky.length - loss} bajo el margen mínimo.`
                    : `${risky.length} producto(s) quedan bajo el margen mínimo (piso).`;
                this.$confirm(msg + ' ¿Guardar la oferta de todos modos?', 'Revisa el margen', {
                    type: 'warning',
                    confirmButtonText: 'Guardar igual',
                    cancelButtonText: 'Revisar precios'
                }).then(() => this._doSave()).catch(() => {});
                return;
            }
            this._doSave();
        },
        _doSave() {
            this.saving = true;
            const payload = {
                title: this.form.title, subtitle: this.form.subtitle,
                starts_at: this.form.starts_at, ends_at: this.form.ends_at, active: this.form.active,
                items: this.form.items.map(i => ({ id: i.id, flash_price: i.flash_price }))
            };
            const req = this.form.id
                ? this.$http.put('/ecommerce/flash-sales/' + this.form.id, payload)
                : this.$http.post('/ecommerce/flash-sales', payload);
            req.then(r => {
                this.$message.success(r.data.message || 'Guardado');
                this.dialogVisible = false;
                this.load();
            }).catch(e => {
                this.$message.error(e.response?.data?.message || 'Error al guardar');
            }).finally(() => { this.saving = false; });
        },
        toggleActive(row) {
            this.$http.put('/ecommerce/flash-sales/' + row.id, {
                title: row.title, ends_at: row.ends_at, active: row.active
            }).catch(() => { row.active = !row.active; });
        },
        remove(row) {
            this.$confirm('Eliminar "' + row.title + '"?', 'Confirmar', { type: 'warning' })
                .then(() => {
                    this.$http.delete('/ecommerce/flash-sales/' + row.id).then(r => {
                        this.$message.success(r.data.message || 'Eliminada');
                        this.load();
                    });
                }).catch(() => {});
        },
        sendWhatsApp(row) {
            this.$confirm(
                'Se enviaran productos en oferta de esta flash sale a clientes registrados con WhatsApp. Deseas continuar?',
                'Enviar por WhatsApp',
                { type: 'warning' }
            ).then(() => {
                this.$http.post('/ecommerce/flash-sales/' + row.id + '/send-whatsapp', {
                    limit_customers: 200,
                    max_products: 3,
                    cooldown_hours: 48
                }).then(({ data }) => {
                    this.$message.success(
                        `Campana enviada. Exito: ${data.sent || 0}, Fallidos: ${data.failed || 0}`
                    );
                }).catch((e) => {
                    this.$message.error(e.response?.data?.message || 'No se pudo enviar la campana');
                });
            }).catch(() => {});
        },
        searchItems(query, cb) {
            if (!query || query.length < 2) { cb([]); return; }
            if (this._allItems) { cb(this._filterItems(query)); return; }
            this.$http.get('/ecommerce/items_bar').then(r => {
                this._allItems = Array.isArray(r.data) ? r.data : (r.data.data || []);
                cb(this._filterItems(query));
            }).catch(() => { cb([]); });
        },
        _filterItems(q) {
            q = q.toLowerCase();
            return (this._allItems || []).filter(i => i.description && i.description.toLowerCase().includes(q)).slice(0, 20);
        },
        addItem(item) {
            if (this.form.items.find(i => i.id === item.id)) { this.$message.info('Ya esta en la lista'); return; }
            const price = parseFloat(item.amount_sale_unit_price) || parseFloat(item.sale_unit_price) || 0;
            this.form.items.push({
                id: item.id,
                description: item.description,
                regular_price: price,
                flash_price: price,
                image_url: item.image_url_small || item.image_url || null,
            });
            this.itemSearch = '';
            this.scheduleMarginCheck();
        },
        onImgError(e) {
            // Fallback si la imagen del producto no carga
            e.target.src = '/logo/imagen-no-disponible.jpg';
        },
        // ─── Guardrail de margen: reusa el mismo PriceCalculator del form de producto ───
        scheduleMarginCheck() {
            clearTimeout(this._marginTimer);
            this._marginTimer = setTimeout(() => this.checkMargins(), 350);
        },
        checkMargins() {
            const items = this.form.items
                .filter(i => i.id && Number(i.flash_price) > 0)
                .map(i => ({ id: i.id, flash_price: i.flash_price }));
            if (!items.length) { this.marginInfo = {}; return; }
            this.$http.post('/ecommerce/flash-sales/price-check', { items })
                .then(r => { this.marginInfo = (r.data && r.data.data) || {}; })
                .catch(() => { /* silencioso: el chip simplemente no se muestra */ });
        },
        // Devuelve { status, text } para pintar el chip de cada producto, o null.
        marginChip(item) {
            const m = this.marginInfo[item.id];
            if (!m) return null;
            if (m.has_cost === false) return { status: 'nocost', text: 'Sin costo' };
            if (m.liquidation_mode && (m.status === 'warn_below_min' || m.status === 'block_below_cost')) {
                return { status: 'liquidation', text: 'Liquidación' };
            }
            if (m.status === 'block_below_cost') return { status: 'loss', text: 'Pérdida' };
            if (m.status === 'warn_below_min')  return { status: 'warn', text: 'Margen ' + m.margin_actual_pct + '%' };
            return { status: 'ok', text: 'Margen ' + m.margin_actual_pct + '%' };
        },
        removeItem(idx) { this.form.items.splice(idx, 1); this.scheduleMarginCheck(); },
        discount(item) {
            if (!item.regular_price || item.regular_price <= 0) return 0;
            return Math.round(((item.regular_price - item.flash_price) / item.regular_price) * 100);
        }
    }
};
</script>

<style scoped>
.fs-panel__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.fs-btn-add { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #f59e0b; color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
.fs-btn-add:hover { background: #d97706; }
.fs-btn-add--sm { padding: 8px 14px; font-size: 12px; }

.fs-empty { text-align: center; padding: 50px 20px; color: #9ca3af; }
.fs-empty p { margin: 12px 0; font-size: 14px; }

.fs-list { display: flex; flex-direction: column; gap: 14px; }
.fs-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: box-shadow 0.2s; }
.fs-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.fs-card--live { border-color: #16a34a; border-left: 4px solid #16a34a; }
.fs-card--inactive { opacity: 0.6; }

.fs-card__header { display: flex; justify-content: space-between; align-items: flex-start; padding: 16px 20px 10px; }
.fs-card__title { font-size: 16px; font-weight: 700; color: #1e293b; margin: 4px 0 0; }
.fs-card__subtitle { font-size: 12px; color: #6b7280; margin: 2px 0 0; }
.fs-card__actions { display: flex; align-items: center; gap: 8px; }

.fs-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.fs-badge--live { background: #ecfdf5; color: #16a34a; }
.fs-badge--scheduled { background: #fef3c7; color: #92400e; }
.fs-badge--inactive { background: #f3f4f6; color: #6b7280; }
.fs-badge__dot { width: 6px; height: 6px; border-radius: 50%; background: #16a34a; animation: pulse 1.5s infinite; }
@keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.4; } }

.fs-card__meta { display: flex; gap: 20px; padding: 0 20px 12px; }
.fs-meta-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; }

.fs-card__products { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 20px 16px; }
.fs-product-chip { display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: #f8f9fa; border-radius: 8px; font-size: 12px; }
.fs-product-chip__name { font-weight: 600; color: #333; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fs-product-chip__prices { display: flex; gap: 6px; align-items: center; }
.fs-product-chip__prices del { font-size: 11px; }
.fs-product-chip__prices strong { font-size: 12px; }
.fs-more { font-size: 12px; color: #6b7280; padding: 6px 0; }

.fs-btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; transition: all 0.15s; }
.fs-btn-icon:hover { background: #f3f4f6; }
.fs-btn-icon--wa { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; }
.fs-btn-icon--wa:hover { background: #dcfce7; border-color: #86efac; }
.fs-btn-icon__label { font-size: 10px; font-weight: 700; line-height: 1; }
.fs-btn-icon--danger:hover { background: #fef2f2; border-color: #ef4444; color: #ef4444; }
.fs-btn-icon--sm { width: 26px; height: 26px; }

/* Form */
.fs-form { display: flex; flex-direction: column; gap: 16px; }
.fs-form__section { border: 1px solid #eee; border-radius: 10px; padding: 16px 20px; background: #fafbfc; }
.fs-form__section-title { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.fs-field { margin-bottom: 10px; }
.fs-field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }

.fs-products-table { border: 1px solid #eee; border-radius: 8px; overflow: hidden; background: #fff; }
.fs-products-table table { width: 100%; border-collapse: collapse; }
.fs-products-table th { font-size: 11px; text-transform: uppercase; color: #999; font-weight: 600; padding: 8px 12px; background: #f8f9fa; border-bottom: 1px solid #eee; }
.fs-products-table td { padding: 10px 12px; vertical-align: middle; border-bottom: 1px solid #f5f5f5; }
.fs-flash-input { width: 120px; }

/* Nombre del producto con miniatura */
.fs-prod-name { display: flex; align-items: center; gap: 10px; }
.fs-prod-name__img {
    width: 36px; height: 36px; flex-shrink: 0;
    border-radius: 7px; object-fit: cover;
    border: 1px solid #ececf1; background: #f6f7f9;
}

.fs-discount-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background: #fef2f2; color: #ef4444; }

/* Chip de salud del margen del precio flash (mismo criterio que el form de producto) */
.fs-margin-chip { display: inline-block; margin-top: 5px; padding: 1px 7px; border-radius: 8px; font-size: 10.5px; font-weight: 700; line-height: 1.6; white-space: nowrap; }
.fs-margin-chip.is-ok          { background: #ecfdf3; color: #15803d; }
.fs-margin-chip.is-warn        { background: #fffbeb; color: #b45309; }
.fs-margin-chip.is-loss        { background: #fef2f2; color: #dc2626; }
.fs-margin-chip.is-nocost,
.fs-margin-chip.is-liquidation  { background: #f3f4f6; color: #6b7280; }

.fs-empty-products { text-align: center; padding: 20px; color: #9ca3af; font-size: 13px; }

/* ─── Móvil: la tabla de productos se apila como tarjetas (no más texto vertical) ─── */
@media (max-width: 640px) {
    .fs-products-table { border: 0; background: transparent; border-radius: 0; overflow: visible; }
    .fs-products-table table,
    .fs-products-table tbody,
    .fs-products-table tr,
    .fs-products-table td { display: block; width: 100%; }
    .fs-products-table thead { display: none; }
    .fs-products-table tr {
        border: 1px solid #ebedf2;
        border-radius: 10px;
        background: #fff;
        padding: 12px 14px;
        margin-bottom: 10px;
        position: relative;
    }
    .fs-products-table td {
        border: 0;
        padding: 5px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        text-align: right !important;
    }
    .fs-products-table td::before {
        content: attr(data-label);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #9aa0aa;
        font-weight: 600;
        text-align: left;
        flex-shrink: 0;
    }
    /* Nombre del producto: ocupa toda la fila, sin etiqueta, arriba */
    .fs-products-table td.fs-cell-name {
        display: block;
        font-size: 14px;
        padding: 0 0 8px;
        margin-bottom: 6px;
        border-bottom: 1px solid #f1f2f5;
        text-align: left !important;
    }
    .fs-products-table td.fs-cell-name::before { display: none; }
    /* Botón eliminar: esquina superior derecha de la tarjeta */
    .fs-products-table td.fs-cell-remove {
        position: absolute;
        top: 10px;
        right: 10px;
        width: auto;
        padding: 0;
    }
    .fs-products-table td.fs-cell-remove::before { display: none; }
    .fs-cell-name .fs-prod-name { padding-right: 34px; }
    .fs-flash-input { width: 140px; }
}
</style>

<!-- No-scoped: alcanza las clases internas del el-dialog de Element y el
     popper del autocomplete (que Element monta en el body) -->
<style>
/* Item del buscador con miniatura del producto */
.fs-suggestions-pop .fs-suggestion {
    display: flex;
    align-items: center;
    gap: 10px;
    line-height: 1.3;
    padding: 2px 0;
}
.fs-suggestions-pop .fs-suggestion__img {
    width: 34px; height: 34px; flex-shrink: 0;
    border-radius: 7px; object-fit: cover;
    border: 1px solid #ececf1; background: #f6f7f9;
}
.fs-suggestions-pop .fs-suggestion__name {
    flex: 1; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fs-suggestions-pop .fs-suggestion__meta {
    display: flex; align-items: center; gap: 6px; flex-shrink: 0;
}
.fs-suggestions-pop .fs-suggestion__pack {
    background: #eef0ff; color: #5b5ea6;
    padding: 1px 6px; border-radius: 8px; font-size: 10px; font-weight: 700;
}
.fs-suggestions-pop li { line-height: 1.4; padding-top: 6px; padding-bottom: 6px; }

@media (max-width: 640px) {
    .fs-dialog {
        width: 96vw !important;
        max-width: 96vw !important;
        margin: 0 auto !important;
    }
    .fs-dialog .el-dialog__body { padding: 14px 16px; }
    .fs-dialog .fs-form { gap: 12px; }
    .fs-dialog .fs-form__section { padding: 12px 14px; }
    /* el-input-number e inputs no se desbordan de la tarjeta */
    .fs-dialog .el-input-number { max-width: 100%; }
}
</style>
