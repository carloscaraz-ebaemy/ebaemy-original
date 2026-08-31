<template>
  <el-dialog :title="title" :visible="showDialog" @close="close" @open="load"
             width="70%" custom-class="rp-dialog"
             :close-on-click-modal="false" :close-on-press-escape="false">

    <div v-if="loading" class="text-center text-muted py-4">Cargando…</div>

    <template v-else>
      <!-- ── Monto a cobrar ────────────────────────────────────────────
           En estos pedidos el total sale de los productos, pero cuando no
           tienen precio cargado ese total no sirve para cobrar. Acá se
           escribe el monto real que debe el cliente. -->
      <div class="rp-amount">
        <div class="rp-amount__row">
          <span class="rp-amount__label">Total de los productos</span>
          <span class="rp-amount__value rp-amount__value--muted">{{ money(summary.total) }}</span>
        </div>

        <div class="rp-amount__row rp-amount__row--edit">
          <span class="rp-amount__label">
            Monto a cobrar
            <small v-if="summary.has_manual_amount">escrito a mano</small>
            <small v-else>toma el total de los productos</small>
          </span>
          <div class="rp-amount__edit">
            <el-input v-model="amountDue" size="small" placeholder="Automático"
                      @keyup.enter.native="saveAmountDue">
              <template slot="prepend">S/</template>
            </el-input>
            <el-button size="small" type="primary" :loading="savingAmount"
                       @click="saveAmountDue">Guardar</el-button>
            <el-button size="small" v-if="summary.has_manual_amount"
                       :loading="savingAmount" @click="clearAmountDue">Automático</el-button>
          </div>
        </div>
      </div>

      <!-- ── Resumen ───────────────────────────────────────────────── -->
      <div class="rp-summary">
        <div class="rp-summary__cell">
          <span>A cobrar</span><strong>{{ money(summary.amount_to_collect) }}</strong>
        </div>
        <div class="rp-summary__cell">
          <span>Pagado</span><strong class="rp-ok">{{ money(summary.total_paid) }}</strong>
        </div>
        <div class="rp-summary__cell">
          <span>Resta pagar</span>
          <strong :class="summary.total_difference > 0 ? 'rp-due' : 'rp-ok'">
            {{ money(summary.total_difference) }}
          </strong>
        </div>
      </div>

      <!-- ── Pagos registrados ─────────────────────────────────────── -->
      <div class="table-responsive">
        <table class="table rp-table">
          <thead>
            <tr>
              <th>#</th><th>Fecha</th><th>Método</th><th>Destino</th>
              <th>Referencia</th><th>Archivo</th>
              <th class="text-right">Monto</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in records" :key="row.id">
              <td data-label="#">{{ row.code }}</td>
              <td data-label="Fecha">{{ row.date_of_payment }}</td>
              <td data-label="Método">{{ row.payment_method_type_description }}</td>
              <td data-label="Destino">{{ row.destination_description || '—' }}</td>
              <td data-label="Referencia">{{ row.reference || '—' }}</td>
              <td data-label="Archivo">
                <a v-if="row.filename" :href="`/finances/payment-file/download-file/${row.filename}/${fileType}`"
                   target="_blank" class="btn btn-xs btn-primary">
                  <i class="fas fa-file-download"></i>
                </a>
                <span v-else>—</span>
              </td>
              <td data-label="Monto" class="text-right">{{ money(row.payment) }}</td>
              <td class="text-right">
                <button type="button" class="btn btn-xs btn-danger"
                        @click.prevent="remove(row)"><i class="fas fa-trash"></i></button>
              </td>
            </tr>

            <!-- Fila de alta -->
            <tr v-if="newRow">
              <td data-label="#"></td>
              <td data-label="Fecha">
                <el-date-picker v-model="newRow.date_of_payment" type="date" :clearable="false"
                                format="dd/MM/yyyy" value-format="yyyy-MM-dd" size="small"></el-date-picker>
              </td>
              <td data-label="Método">
                <el-select v-model="newRow.payment_method_type_id" size="small">
                  <el-option v-for="o in paymentMethodTypes" v-show="o.id != '09'"
                             :key="o.id" :value="o.id" :label="o.description"></el-option>
                </el-select>
              </td>
              <td data-label="Destino">
                <el-select v-model="newRow.payment_destination_id" filterable size="small">
                  <el-option v-for="o in paymentDestinations" :key="o.id" :value="o.id"
                             :label="o.description"></el-option>
                </el-select>
              </td>
              <td data-label="Referencia">
                <el-input v-model="newRow.reference" size="small" placeholder="Operación"></el-input>
              </td>
              <td data-label="Archivo">
                <el-upload
                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,application/pdf"
                    :headers="headers" :multiple="false" :limit="1"
                    action="/finances/payment-file/upload"
                    :show-file-list="true" :on-success="onFileUploaded">
                  <el-button slot="trigger" size="small"><i class="fas fa-file-upload"></i></el-button>
                </el-upload>
              </td>
              <td data-label="Monto">
                <el-input v-model="newRow.payment" size="small" class="text-right"></el-input>
              </td>
              <td class="text-right">
                <button type="button" class="btn btn-xs btn-info" :disabled="saving"
                        @click.prevent="submit"><i class="fa fa-check"></i></button>
                <button type="button" class="btn btn-xs btn-danger"
                        @click.prevent="newRow = null"><i class="fa fa-times"></i></button>
              </td>
            </tr>

            <tr v-if="!records.length && !newRow">
              <td colspan="8" class="text-center text-muted py-3">
                Todavía no hay pagos registrados.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="text-center pt-2">
        <el-button v-if="!newRow && summary.total_difference > 0"
                   type="primary" icon="el-icon-plus" @click="addRow">Nuevo pago</el-button>
        <p v-else-if="!newRow && summary.amount_to_collect > 0" class="rp-paid">
          Este pedido está totalmente pagado.
        </p>
        <p v-else-if="!newRow" class="text-muted rp-hint">
          Escribe el monto a cobrar para poder registrar pagos.
        </p>
      </div>
    </template>
  </el-dialog>
</template>

<style>
.rp-dialog .el-dialog__body { padding-top: 10px; }

.rp-amount {
    background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 12px 14px; margin-bottom: 12px;
}
.rp-amount__row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.rp-amount__row + .rp-amount__row { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e5e7eb; }
.rp-amount__label { font-size: 13px; font-weight: 600; color: #374151; }
.rp-amount__label small { display: block; font-size: 11px; font-weight: 400; color: #9ca3af; }
.rp-amount__value { font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums; }
.rp-amount__value--muted { color: #9ca3af; }
.rp-amount__edit { display: flex; gap: 6px; align-items: center; }
.rp-amount__edit .el-input { width: 150px; }

.rp-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
.rp-summary__cell {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 10px 12px; text-align: center;
}
.rp-summary__cell span { display: block; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
.rp-summary__cell strong { font-size: 18px; font-variant-numeric: tabular-nums; }
.rp-ok { color: #16a34a; }
.rp-due { color: #dc2626; }

.rp-table td, .rp-table th { vertical-align: middle; font-size: 13px; }
.rp-paid { color: #16a34a; font-weight: 600; margin: 0; }
.rp-hint { font-size: 12.5px; margin: 0; }

/* Mobile: la tabla se apila y cada celda muestra su etiqueta. Una grilla de
   8 columnas en un celular es ilegible. */
@media (max-width: 767px) {
    .rp-dialog { width: 96% !important; }
    .rp-amount__row, .rp-amount__row--edit { flex-direction: column; align-items: stretch; }
    .rp-amount__edit .el-input { width: auto; flex: 1; }
    .rp-summary { grid-template-columns: 1fr; gap: 6px; }
    .rp-summary__cell { display: flex; align-items: baseline; justify-content: space-between; text-align: left; }

    .rp-table thead { display: none; }
    .rp-table, .rp-table tbody, .rp-table tr, .rp-table td { display: block; width: 100%; }
    .rp-table tr { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 10px; padding: 6px 10px; }
    .rp-table td { border: 0; padding: 5px 0; display: flex; justify-content: space-between; gap: 10px; text-align: right; }
    .rp-table td::before { content: attr(data-label); font-weight: 600; color: #6b7280; text-align: left; }
}
</style>

<script>
/**
 * Panel de pagos, uno solo para las pantallas que cobran contra un registro.
 * El backend expone el mismo contrato para todas
 * (App\Http\Controllers\Tenant\Concerns\ManagesRecordPayments):
 *
 *   GET    {resource}/tables
 *   GET    {resource}/summary/{id}
 *   GET    {resource}/records/{id}
 *   POST   {resource}
 *   POST   {resource}/amount-due/{id}
 *   DELETE {resource}/{paymentId}
 */
export default {
  props: {
    showDialog: Boolean,
    recordId: [Number, String],
    // 'order_payments' | 'order_note_payments'
    resource: { type: String, required: true },
    foreignKey: { type: String, required: true },
    // Carpeta donde el backend guarda el adjunto; la necesita el link de
    // descarga (/finances/payment-file/download-file/{archivo}/{tipo}).
    fileType: { type: String, required: true },
    title: { type: String, default: 'Pagos del pedido' }
  },
  data() {
    return {
      headers: headers_token,
      loading: true,
      saving: false,
      savingAmount: false,
      summary: { total: 0, amount_to_collect: 0, total_paid: 0, total_difference: 0, has_manual_amount: false },
      records: [],
      paymentMethodTypes: [],
      paymentDestinations: [],
      amountDue: null,
      newRow: null
    };
  },
  methods: {
    money(v) {
      return 'S/ ' + Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    load() {
      this.loading = true;
      this.newRow = null;
      Promise.all([
        this.$http.get(`/${this.resource}/tables`),
        this.$http.get(`/${this.resource}/summary/${this.recordId}`),
        this.$http.get(`/${this.resource}/records/${this.recordId}`)
      ]).then(([tables, summary, records]) => {
        this.paymentMethodTypes  = tables.data.payment_method_types || [];
        this.paymentDestinations = tables.data.payment_destinations || [];
        this.applySummary(summary.data);
        this.records = records.data.data || [];
      }).catch(() => {
        this.$message.error('No se pudieron cargar los pagos');
      }).then(() => { this.loading = false; });
    },
    applySummary(s) {
      this.summary = s;
      // Vacío cuando es automático: así el placeholder explica el estado.
      this.amountDue = s.amount_due === null || s.amount_due === undefined ? null : String(s.amount_due);
    },
    saveAmountDue() {
      this.savingAmount = true;
      this.$http.post(`/${this.resource}/amount-due/${this.recordId}`, { amount_due: this.amountDue })
        .then(r => {
          if (r.data.success) {
            this.applySummary(r.data.summary);
            this.$message.success(r.data.message);
            this.$emit('updated', r.data.summary);
          } else {
            this.$message.error(r.data.message);
          }
        })
        .catch(() => { this.$message.error('No se pudo guardar el monto'); })
        .then(() => { this.savingAmount = false; });
    },
    clearAmountDue() {
      this.amountDue = null;
      this.saveAmountDue();
    },
    addRow() {
      this.newRow = {
        date_of_payment: new Date().toISOString().slice(0, 10),
        payment_method_type_id: '01',
        payment_destination_id: 'cash',
        reference: null,
        // Propone el saldo completo: el caso más común es cobrar todo.
        payment: this.summary.total_difference,
        filename: null,
        temp_path: null
      };
    },
    onFileUploaded(response) {
      if (response && response.success) {
        this.newRow.filename = response.data.filename;
        this.newRow.temp_path = response.data.temp_path;
      } else {
        this.$message.error((response && response.message) || 'No se pudo subir el archivo');
      }
    },
    submit() {
      this.saving = true;
      const payload = Object.assign({}, this.newRow);
      payload[this.foreignKey] = this.recordId;

      this.$http.post(`/${this.resource}`, payload)
        .then(r => {
          if (r.data.success) {
            this.$message.success(r.data.message);
            this.newRow = null;
            this.applySummary(r.data.summary);
            this.$emit('updated', r.data.summary);
            return this.$http.get(`/${this.resource}/records/${this.recordId}`)
              .then(res => { this.records = res.data.data || []; });
          }
          this.$message.error(r.data.message || 'No se pudo registrar el pago');
        })
        .catch(error => {
          const errs = error.response && error.response.data && error.response.data.errors;
          this.$message.error(errs ? Object.values(errs)[0][0] : 'Error al registrar el pago');
        })
        .then(() => { this.saving = false; });
    },
    remove(row) {
      this.$confirm(`¿Eliminar el ${row.code} por ${this.money(row.payment)}?`, 'Eliminar pago', {
        confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', type: 'warning'
      }).then(() => {
        this.$http.delete(`/${this.resource}/${row.id}`)
          .then(r => {
            if (r.data.success) {
              this.$message.success(r.data.message);
              this.records = this.records.filter(x => x.id !== row.id);
              this.applySummary(r.data.summary);
              this.$emit('updated', r.data.summary);
            } else {
              this.$message.error(r.data.message);
            }
          })
          .catch(() => { this.$message.error('No se pudo eliminar el pago'); });
      }).catch(() => { /* cancelado */ });
    },
    close() {
      this.$emit('update:showDialog', false);
    }
  }
};
</script>
