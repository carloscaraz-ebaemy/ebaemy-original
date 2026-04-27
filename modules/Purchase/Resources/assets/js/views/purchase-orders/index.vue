<template>
  <div>
    <div class="page-header pe-0">
      <h2>
        <a href="/purchase-orders">
          <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z" /><path d="M9 11v-5a3 3 0 0 1 6 0v5" /></svg>
        </a>
      </h2>
      <ol class="breadcrumbs">
        <li class="active">
          <span>Ordenes de compra</span>
        </li>
      </ol>
      <div class="right-wrapper pull-right">
        <a :href="`/${resource}/create`" class="btn btn-custom btn-sm mt-2 me-2">
          <i class="fa fa-plus-circle"></i> Nuevo
        </a>
      </div>
    </div>
    <div class="card tab-content-default row-new mb-0">
      <div class="card-body">
        <data-table :resource="resource">
          <tr slot="heading">
            <!-- <th>#</th> -->
            <th class="text-start">F. Emisión</th>
            <th class="text-start">F. Vencimiento</th>
            <th>Proveedor</th>
            <!-- <th>Estado</th> -->
            <th>O. Compra</th>
            <th class="text-center">Estado</th>
            <th>O. Venta</th>
            <!-- <th>F. Pago</th> -->
            <th class="text-center">Moneda</th>
            <!-- <th class="text-end">T.Gratuita</th>
            <th class="text-end">T.Inafecta</th>
            <th class="text-end">T.Exonerado</th> -->
            <th class="text-end">T.Gravado</th>
            <th class="text-end">T.Igv</th>
            <!-- <th>Percepcion</th> -->
            <th class="text-end">Total</th>
            <th class="text-center">Descarga</th>
            <th class="text-end">Acciones</th>
          </tr>
          <tr></tr>
          <tr slot-scope="{ index, row }">
            <!-- <td>{{ index }}</td> -->
            <td class="text-start">{{ row.date_of_issue }}</td>
            <td class="text-start">{{ row.date_of_due }}</td>
            <td>
              {{ row.supplier_name }}
              <br />
              <small v-text="row.supplier_number"></small>
            </td>
            <!-- <td>{{row.state_type_description}}</td> -->
            <td>
              {{ row.number }}
              <br />
              <small v-text="row.document_type_description"></small>
              <br />
            </td>

            <td class="text-center">
                <span class="badge bg-secondary text-white" :class="{'bg-danger': (row.state_type_id === '11'), 'bg-warning': (row.state_type_id === '13'), 'bg-secondary': (row.state_type_id === '01')}">
                    {{ row.state_type_description }}
                </span>
                <br>
                <span v-if="row.reception_status" class="badge mt-1"
                      :class="{
                        'bg-success text-white': row.reception_status === 'received',
                        'bg-warning text-dark': row.reception_status === 'partial',
                        'bg-light text-dark border': row.reception_status === 'pending'
                      }">
                    <span v-if="row.reception_status === 'received'">✓ Recibida</span>
                    <span v-else-if="row.reception_status === 'partial'">⏳ Recepción parcial</span>
                    <span v-else>📦 Por recibir</span>
                </span>
            </td>

            <td>{{row.sale_opportunity_number_full}}</td>

            <!-- <td>{{ row.payment_method_type_description }}</td> -->
            <!-- <td>{{ row.state_type_description }}</td> -->
            <td class="text-center">{{ row.currency_type_id }}</td>
            <!-- <td class="text-right">{{ row.total_exportation }}</td> -->
            <!-- <td class="text-right">{{ row.total_free }}</td>
            <td class="text-right">{{ row.total_unaffected }}</td>
            <td class="text-end">{{ row.total_exonerated }}</td> -->
            <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_taxed }}</td>
            <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_igv }}</td>
            <!-- <td class="text-right">{{ row.total_perception ? row.total_perception : 0 }}</td> -->
            <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total }}</td>

                        <td class="text-center">

                            <button type="button" class="btn waves-effect waves-light btn-xs btn-info"
                                    @click.prevent="clickDownload(row.external_id)">PDF</button>
                        </td>

            <td class="text-end">
              <!-- <el-button
                @click.prevent="clickOptions(row.id)"
                size="mini"
                type="primary"
                :disabled="row.state_type_id == '03' || row.state_type_id == '11'"
              >Generar comprobante</el-button>
              <el-button
                :disabled="row.state_type_id == '11'  || row.state_type_id == '03' "
                type="danger"
                  size="mini"
                @click.prevent="clickAnulate(row.id)"
              >Anular</el-button> -->


              <button type="button" v-if="row.show_actions_row" class="btn waves-effect waves-light btn-xs btn-custom m-1__2 me-1"
                      @click.prevent="clickCreate(row.id)">Editar</button>

              <a :href="`/purchases/create/${row.id}`" class="btn waves-effect waves-light btn-xs btn-success m-1__2 me-1"
                      v-if="row.show_actions_row">Generar compra</a>

              <button type="button"
                      v-if="row.state_type_id !== '11' && row.reception_status !== 'received'"
                      class="btn waves-effect waves-light btn-xs btn-warning m-1__2 me-1"
                      @click.prevent="clickReceive(row.id)">
                  Recibir mercancía
              </button>

              <button type="button" v-if="row.show_actions_row" class="btn waves-effect waves-light btn-xs btn-danger m-1__2 me-1"
                      @click.prevent="clickAnulate(row.id)">Anular</button>

              <button type="button" class="btn waves-effect waves-light btn-xs btn-info m-1__2 me-1"
                      @click.prevent="clickOptions(row.id)">Opciones</button>
            </td>
          </tr>
        </data-table>
      </div>

      <!-- <documents-voided :showDialog.sync="showDialogVoided"
      :recordId="recordId"></documents-voided>-->

      <!-- <document-generate
        :showDialog.sync="showDialogGenerateDocument"
        :recordId="recordId"
        :showClose="true"
      ></document-generate> -->


        <purchase-options :showDialog.sync="showDialogOptions"
                          :recordId="recordId"
                          :showClose="true"></purchase-options>
    </div>
  </div>
</template>
<style>
@media only screen and (max-width: 485px){
  .filter-container{
    margin-top: 0px;
    & .btn-filter-content, .btn-container-mobile{
      display: flex;
      align-items: center;
      justify-content: start;
    }
  }
}
</style>
<script>
    // import DocumentGenerate from "./partials/document_generate.vue";
    // import DocumentOptions from './partials/document_options.vue'
    import DataTable from "@components/DataTable.vue";
    import PurchaseOptions from './partials/options.vue'

    import {deletable} from '@mixins/deletable'


export default {
      mixins: [deletable],
      // components: {DocumentsVoided, DocumentOptions, DataTable},
      components: { DataTable , PurchaseOptions}, //DocumentOptions
      data() {
        return {
          showDialogVoided: false,
          resource: "purchase-orders",
          recordId: null,
          showDialogOptions: false,
          showDialogGenerateDocument: false,
        };
      },
      created() {},
      methods: {
          clickCreate(id = '') {
              location.href = `/${this.resource}/create/${id}`
          },
          clickVoided(recordId = null) {
            this.recordId = recordId;
            this.showDialogVoided = true;
          },
                  clickDownload(external_id) {
                      window.open(`/${this.resource}/download/${external_id}`, '_blank');
                  },
          clickGenerateDocument(recordId) {
            this.recordId = recordId;
            this.showDialogGenerateDocument = true;
          },
          clickAnulate(id) {
            this.anular(`/${this.resource}/anular/${id}`).then(() =>
              this.$eventHub.$emit("reloadData")
            );
          },
          clickReceive(id) {
            this.$confirm(
              'Se marcarán todos los items de la OC como recibidos completos y se sumarán al stock del almacén del establecimiento. ¿Continuar?',
              'Recibir mercancía',
              {
                confirmButtonText: 'Sí, recibir',
                cancelButtonText: 'Cancelar',
                type: 'warning',
              }
            ).then(() => {
              this.$http.post(`/${this.resource}/${id}/receive`)
                .then(({ data }) => {
                  if (data.success) {
                    this.$message.success(data.message);
                    this.$eventHub.$emit('reloadData');
                  } else {
                    this.$message.error(data.message || 'No se pudo registrar la recepción');
                  }
                })
                .catch((err) => {
                  this.$message.error(err.response?.data?.message || 'Error de servidor al recibir mercancía');
                });
            }).catch(() => {});
          },
          clickOptions(recordId = null) {
              this.recordId = recordId
              this.showDialogOptions = true
          },
    }
};
</script>
