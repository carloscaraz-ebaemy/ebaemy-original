<template>
  <div class="promotions-panel">
    <div class="page-header pe-0">
      <h2>
        <a href="/promotions">
          <svg xmlns="http://www.w3.org/2000/svg" style="margin-top:-5px" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </a>
      </h2>
      <ol class="breadcrumbs">
        <li class="active"><span>Banners y Promociones</span></li>
      </ol>
    </div>

    <!-- ═══ BANNERS PRINCIPALES ═══ -->
    <div class="promo-section">
      <div class="promo-section__header">
        <div>
          <h3 class="promo-section__title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Banners principales
          </h3>
          <p class="promo-section__desc">Se muestran en el slider principal de tu tienda. Maximo 3 banners.</p>
        </div>
        <button type="button" class="btn-promo-add" @click.prevent="clickCreate()">
          <i class="fa fa-plus"></i> Nuevo banner
        </button>
      </div>

      <div class="promo-section__body">
        <data-table :apply-filter="false" :promotionType="'banners'" :resource="resource">
          <tr slot="heading">
            <th style="width:40%">Banner</th>
            <th style="width:25%">Nombre</th>
            <th style="width:15%" class="text-center">Estado</th>
            <th style="width:20%" class="text-end">Acciones</th>
          </tr>
          <tr></tr>
          <tr slot-scope="{ index, row }">
            <td>
              <div class="promo-thumb">
                <img :src="row.image_url" :alt="row.name" />
              </div>
            </td>
            <td>
              <div class="promo-name">{{ row.name }}</div>
              <div class="promo-type-badge">Banner principal</div>
            </td>
            <td class="text-center">
              <span class="promo-status promo-status--active">
                <i class="fa fa-circle" style="font-size:7px"></i> Activo
              </span>
            </td>
            <td class="text-end">
              <div class="promo-actions">
                <el-tooltip content="Editar" placement="top">
                  <button type="button" class="btn-promo-action btn-promo-action--edit" @click.prevent="clickCreate(row.id)">
                    <i class="fa fa-pencil"></i>
                  </button>
                </el-tooltip>
                <el-tooltip content="Eliminar" placement="top">
                  <button type="button" class="btn-promo-action btn-promo-action--delete" @click.prevent="clickDelete(row.id)">
                    <i class="fa fa-trash"></i>
                  </button>
                </el-tooltip>
              </div>
            </td>
          </tr>
        </data-table>
      </div>

      <promotions-form :showDialog.sync="showDialog" :recordId="recordId"></promotions-form>
    </div>

    <!-- ═══ ANUNCIOS / SPOTS ═══ -->
    <div class="promo-section">
      <div class="promo-section__header">
        <div>
          <h3 class="promo-section__title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Anuncios promocionales
          </h3>
          <p class="promo-section__desc">Imagenes pequenas que se muestran en la pagina principal. Maximo 4 anuncios.</p>
        </div>
        <button type="button" class="btn-promo-add" @click.prevent="clickCreateSpotList()">
          <i class="fa fa-plus"></i> Nuevo anuncio
        </button>
      </div>

      <div class="promo-section__body">
        <data-table :apply-filter="false" :promotionType="'spots'" :resource="resource">
          <tr slot="heading">
            <th style="width:40%">Imagen</th>
            <th style="width:25%">Nombre</th>
            <th style="width:15%" class="text-center">Estado</th>
            <th style="width:20%" class="text-end">Acciones</th>
          </tr>
          <tr></tr>
          <tr slot-scope="{ index, row }">
            <td>
              <div class="promo-thumb promo-thumb--spot">
                <img :src="row.image_url" :alt="row.name" />
              </div>
            </td>
            <td>
              <div class="promo-name">{{ row.name }}</div>
              <div class="promo-url" v-if="row.spot_url">
                <i class="fa fa-link" style="font-size:10px"></i> {{ row.spot_url | truncate(35) }}
              </div>
              <div class="promo-type-badge promo-type-badge--spot">Anuncio</div>
            </td>
            <td class="text-center">
              <span class="promo-status promo-status--active">
                <i class="fa fa-circle" style="font-size:7px"></i> Activo
              </span>
            </td>
            <td class="text-end">
              <div class="promo-actions">
                <el-tooltip content="Editar" placement="top">
                  <button type="button" class="btn-promo-action btn-promo-action--edit" @click.prevent="clickCreateSpotList(row.id)">
                    <i class="fa fa-pencil"></i>
                  </button>
                </el-tooltip>
                <el-tooltip content="Eliminar" placement="top">
                  <button type="button" class="btn-promo-action btn-promo-action--delete" @click.prevent="clickDeleteSpotList(row.id)">
                    <i class="fa fa-trash"></i>
                  </button>
                </el-tooltip>
              </div>
            </td>
          </tr>
        </data-table>
      </div>

      <spot-list-form :showDialog.sync="showDialogSpotList" :recordId="recordIdSpot"></spot-list-form>
    </div>
  </div>
</template>

<style>
.btn-show-filter { display: none; }
</style>

<style scoped>
.promotions-panel {
  max-width: 1200px;
}

/* Section */
.promo-section {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  margin-bottom: 20px;
  overflow: hidden;
}
.promo-section__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #f0f0f0;
}
.promo-section__title {
  font-size: 16px;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.promo-section__desc {
  font-size: 12px;
  color: #999;
  margin: 4px 0 0 28px;
}
.promo-section__body {
  padding: 16px 24px;
}

/* Add button */
.btn-promo-add {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: #5b5ea6;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  white-space: nowrap;
}
.btn-promo-add:hover {
  background: #4a4d8f;
}

/* Thumbnail */
.promo-thumb {
  width: 220px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #eee;
}
.promo-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.promo-thumb--spot {
  width: 180px;
  height: 60px;
}

/* Name & badges */
.promo-name {
  font-weight: 600;
  font-size: 13px;
  color: #333;
}
.promo-url {
  font-size: 11px;
  color: #999;
  margin-top: 2px;
}
.promo-type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  margin-top: 4px;
  background: #eef0ff;
  color: #5b5ea6;
}
.promo-type-badge--spot {
  background: #fff3e0;
  color: #e65100;
}

/* Status */
.promo-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}
.promo-status--active {
  background: #ecfdf5;
  color: #10b981;
}
.promo-status--inactive {
  background: #f3f4f6;
  color: #9ca3af;
}

/* Action buttons */
.promo-actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}
.btn-promo-action {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  transition: all 0.15s;
}
.btn-promo-action--edit:hover {
  background: #eef0ff;
  border-color: #5b5ea6;
  color: #5b5ea6;
}
.btn-promo-action--delete:hover {
  background: #fef2f2;
  border-color: #ef4444;
  color: #ef4444;
}

/* Table overrides */
.promo-section__body >>> .table {
  width: 100%;
}
.promo-section__body >>> .table td {
  vertical-align: middle;
  padding: 12px 8px;
}
.promo-section__body >>> .table th {
  font-size: 11px;
  text-transform: uppercase;
  color: #999;
  font-weight: 600;
  letter-spacing: 0.5px;
  padding: 8px;
  border-bottom: 1px solid #f0f0f0;
}
</style>

<script>
import PromotionsForm from "./form.vue";
import PromotionsListForm from "./promotionListForm.vue";
import DataTable from "../../../components/DataTablePromotionsEcommerce.vue";
import { deletable } from "../../../mixins/deletable";
import SpotListForm from "./spotListForm.vue";

export default {
  mixins: [deletable],
  components: { PromotionsForm, DataTable, PromotionsListForm, SpotListForm },
  filters: {
    truncate(value, length) {
      if (!value) return '';
      if (value.length <= length) return value;
      return value.substring(0, length) + '...';
    }
  },
  data() {
    return {
      showDialog: false,
      showDialogPromotionList: false,
      showDialogSpotList: false,
      resource: "promotions",
      recordId: null,
      recordIdPromotion: null,
      recordIdSpot: null
    };
  },
  methods: {
    clickCreate(recordId = null) {
      this.recordId = recordId;
      this.showDialog = true;
    },
    clickDelete(id) {
      this.destroy(`/${this.resource}/${id}`).then(() =>
        this.$eventHub.$emit("reloadData")
      );
    },
    clickCreatePromotionList(recordId = null) {
      this.recordIdPromotion = recordId;
      this.showDialogPromotionList = true;
    },
    clickDeletePromotionList(id) {
      this.destroy(`/${this.resource}/${id}`).then(() =>
        this.$eventHub.$emit("reloadData")
      );
    },
    clickCreateSpotList(recordId = null) {
      this.recordIdSpot = recordId;
      this.showDialogSpotList = true;
    },
    clickDeleteSpotList(id) {
      this.destroy(`/${this.resource}/${id}`).then(() =>
        this.$eventHub.$emit("reloadData")
      );
    }
  }
};
</script>
