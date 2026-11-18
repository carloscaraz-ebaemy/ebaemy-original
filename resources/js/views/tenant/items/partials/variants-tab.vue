<template>
    <div class="variants-tab">

        <!-- Sin ID todavía: el producto debe guardarse primero -->
        <el-alert v-if="!itemId"
                  title="Guarda el producto primero para poder agregar variantes."
                  type="info" show-icon :closable="false" class="mb-3" />

        <template v-else>
            <!-- ── Toggle: usar imagen principal del producto en todas las variantes ── -->
            <div class="vt-parent-image-toggle mb-3">
                <el-switch v-model="useParentImage"
                           active-text="Usar imagen del producto en todas las variantes"
                           @change="toggleUseParentImage" />
                <div class="text-muted small mt-1" style="font-size:11px">
                    Útil cuando no tienes una foto distinta por color/talla.
                    Al activarlo, el marketplace ignora las imágenes individuales
                    de cada variante y usa la imagen principal del producto.
                </div>
            </div>

            <!-- ── Cabecera ─────────────────────────────────────────────── -->
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <strong>Variantes del producto</strong>
                    <span v-if="showNewBadge" class="vt-new-badge">NUEVO</span>
                    <span class="text-muted ms-2 small">
                        ({{ variants.length }} combinaciones)
                    </span>
                    <!-- Novedad 2026-11: fotos por modelo y tarjetas. Una vez por
                         usuario (se guarda en su cuenta). Ver components/FeatureTour.vue. -->
                    <feature-tour
                        v-if="variants.length"
                        tour-key="variantes-galeria-2026-11"
                        :delay="1200"
                        :steps="tourSteps"
                    ></feature-tour>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Toggle vista matriz: solo cuando hay EXACTAMENTE 2 opciones
                         (típicamente Color × Talla). Útil para ver/editar stock
                         de muchas combinaciones de un vistazo, estilo Excel. -->
                    <el-radio-group v-if="canShowMatrix" v-model="viewMode" size="small">
                        <el-radio-button label="list">Lista</el-radio-button>
                        <el-radio-button label="matrix">Matriz</el-radio-button>
                    </el-radio-group>
                    <el-button v-if="variants.length > 0" size="small" plain
                               icon="el-icon-copy-document"
                               title="Pon el mismo precio a todas las variantes de una vez"
                               @click="openBulkPriceDialog">
                        Mismo precio a todas
                    </el-button>
                    <el-button v-if="variants.length > 0" size="small" plain
                               icon="el-icon-magic-stick"
                               @click="generateSkus"
                               title="Genera SKUs automáticos basados en el código del producto y los valores de cada variante">
                        Generar SKUs
                    </el-button>
                    <el-button v-if="inactiveCount > 0 || showInactive"
                               size="small" plain
                               :type="showInactive ? 'warning' : ''"
                               icon="el-icon-view"
                               @click="toggleInactive"
                               title="Las variantes con stock no se borran al quitar una opción: se desactivan. Aquí puedes verlas y recuperarlas.">
                        {{ showInactive ? 'Ocultar desactivadas' : `Ver desactivadas (${inactiveCount})` }}
                    </el-button>
                    <el-button size="small" type="primary" plain
                               icon="el-icon-plus"
                               @click="showOptionsEditor = true">
                        Gestionar opciones
                    </el-button>
                </div>
            </div>

            <!-- ── Aviso de variantes desactivadas ──────────────────────── -->
            <el-alert v-if="showInactive && inactiveVariants.length"
                      type="warning" :closable="false" show-icon
                      class="mb-3">
                <template slot="title">
                    {{ inactiveVariants.length }} variante(s) desactivada(s)
                </template>
                Su stock no se cuenta en el total del producto ni se publica en la
                tienda. Reactívalas si la combinación vuelve a existir, o mueve su
                stock a otra variante para no perder inventario.
            </el-alert>

            <!-- ── Mover stock de una variante desactivada ──────────────── -->
            <el-dialog title="Mover stock a otra variante"
                       :visible.sync="showMoveDialog"
                       append-to-body width="460px">
                <p v-if="movingVariant" class="mb-3">
                    Se moverán las unidades libres de
                    <strong>{{ movingVariant.display_name }}</strong>
                    a la variante que elijas, almacén por almacén. El total del
                    producto no cambia.
                </p>
                <el-select v-model="moveTargetId" placeholder="Variante de destino"
                           class="w-100" filterable>
                    <el-option v-for="t in moveTargets" :key="t.id"
                               :label="t.display_name" :value="t.id" />
                </el-select>
                <p class="text-muted small mt-2 mb-0">
                    Las unidades comprometidas por pedidos pendientes no se mueven:
                    siguen respaldando esas reservas.
                </p>
                <span slot="footer">
                    <el-button size="small" @click="showMoveDialog = false">Cancelar</el-button>
                    <el-button size="small" type="primary"
                               :loading="movingStock"
                               @click="confirmMoveStock">Mover stock</el-button>
                </span>
            </el-dialog>

            <!-- ── Editor de opciones (modal inline) ──────────────────── -->
            <el-dialog title="Opciones y valores"
                       :visible.sync="showOptionsEditor"
                       append-to-body width="540px"
                       :close-on-click-modal="false">

                <div v-for="(opt, oi) in editOptions" :key="oi" class="mb-3 border rounded p-2">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <el-input v-model="opt.name" placeholder="Ej: Color" size="small"
                                  style="width:160px" />
                        <span v-if="isColorOption(opt.name)" class="badge bg-info text-white small"
                              style="font-size:10px;font-weight:600">🎨 con color</span>
                        <span class="text-muted small ms-1">Valores:</span>
                        <el-button size="mini" type="success" plain icon="el-icon-plus"
                                   @click="addValue(oi)" />
                        <el-button size="mini" type="danger" plain icon="el-icon-delete"
                                   @click="removeOption(oi)" />
                    </div>
                    <div v-for="(val, vi) in opt.values" :key="vi"
                         class="d-flex align-items-center gap-2 mb-1 ms-2">
                        <!-- Combobox SOLO cuando hay sugerencias contextuales
                             (color o talla). Para opciones libres tipo
                             "modelo/material/sabor" usamos el-input puro porque
                             el-select+allow-create con array vacio tiene un bug
                             que committea el index en vez del texto tipeado. -->
                        <template v-if="hasOptionValueSuggestions(opt.name)">
                            <el-select v-model="val.value"
                                       filterable allow-create
                                       :placeholder="optionValuePlaceholder(opt.name)"
                                       size="mini"
                                       style="width:150px"
                                       @change="onOptionValueChange(oi, vi, $event)">
                                <el-option v-for="sg in optionValueSuggestions(opt.name)"
                                           :key="sg.value"
                                           :label="sg.value"
                                           :value="sg.value">
                                    <span v-if="sg.color_hex" :style="{display:'inline-block',width:'10px',height:'10px',borderRadius:'50%',background:sg.color_hex,marginRight:'6px',verticalAlign:'middle'}"></span>
                                    {{ sg.value }}
                                </el-option>
                            </el-select>
                        </template>
                        <template v-else>
                            <el-input v-model="val.value"
                                      :placeholder="optionValuePlaceholder(opt.name)"
                                      size="mini"
                                      style="width:150px" />
                        </template>
                        <el-color-picker v-if="isColorOption(opt.name)"
                                         v-model="val.color_hex" size="mini"
                                         title="Define el color para que aparezca como punto en las cards del marketplace" />
                        <el-button size="mini" plain icon="el-icon-close"
                                   @click="removeValue(oi, vi)" />
                    </div>
                    <div v-if="isColorOption(opt.name)" class="ms-2 mt-1" style="font-size:11px;color:#6b7280">
                        💡 Define el color de cada valor. Aparecerá como punto/círculo en el listado del marketplace.
                    </div>

                    <!-- Chips predefinidos: el seller agrega valores comunes con un click -->
                    <template v-if="isColorOption(opt.name)">
                        <div class="vt-pre-label">⚡ Agregar rápido (click para añadir):</div>
                        <div class="vt-pre-chips">
                            <button v-for="pc in preColors" :key="pc.value"
                                    type="button"
                                    class="vt-chip vt-chip--color"
                                    :title="pc.value"
                                    @click="addPredefined(oi, pc)">
                                <span class="vt-chip__swatch" :style="{ background: pc.color_hex }"></span>
                                {{ pc.value }}
                            </button>
                        </div>
                    </template>

                    <template v-if="isSizeOption(opt.name)">
                        <div class="vt-pre-label">⚡ Agregar rápido — Tallas de ropa:</div>
                        <div class="vt-pre-chips">
                            <button v-for="s in preSizesClothing" :key="'c'+s"
                                    type="button" class="vt-chip"
                                    @click="addPredefined(oi, { value: s, color_hex: null })">
                                {{ s }}
                            </button>
                        </div>
                        <div class="vt-pre-label">⚡ Calzado:</div>
                        <div class="vt-pre-chips">
                            <button v-for="s in preSizesShoes" :key="'sh'+s"
                                    type="button" class="vt-chip"
                                    @click="addPredefined(oi, { value: s, color_hex: null })">
                                {{ s }}
                            </button>
                        </div>
                    </template>
                </div>

                <el-button size="small" plain icon="el-icon-plus" @click="addOption">
                    Agregar opción
                </el-button>

                <div slot="footer">
                    <el-button @click="showOptionsEditor = false">Cancelar</el-button>
                    <el-button type="primary" :loading="saving"
                               @click="saveOptions">
                        Guardar y generar variantes
                    </el-button>
                </div>
            </el-dialog>

            <!-- ── Variantes como tarjetas ─────────────────────────────────
                 Una tarjeta por variante: foto principal + fotos adicionales,
                 precio, stock, SKU y estado. En escritorio salen en dos
                 columnas; en el celular, apiladas a todo el ancho. Todo se
                 guarda al momento (no hay botón Guardar en esta sección). -->
            <div v-if="variants.length > 0 && viewMode === 'list'" class="vt-cards">
                <template v-for="group in groupedVariants">
                    <div v-if="hasMultipleOptions" :key="`hdr-${group.key}`" class="vt-group-title">
                        <span v-if="group.color_hex" class="vt-group-swatch"
                              :style="`background:${group.color_hex}`"></span>
                        <strong>{{ group.label }}</strong>
                        <span class="text-muted small">
                            ({{ group.variants.length }} {{ group.variants.length === 1 ? 'variante' : 'variantes' }})
                        </span>
                    </div>

                    <article v-for="v in group.variants" :key="v.id"
                             class="vt-card"
                             :class="{ 'vt-card--off': !v.is_active }">

                        <!-- Cabecera: nombre + mostrar primero + activa -->
                        <header class="vt-card__head">
                            <div class="vt-card__title">
                                <span class="vt-card__name">{{ variantSubLabel(v) }}</span>
                                <span v-if="!v.is_active" class="vt-card__badge">desactivada</span>
                            </div>
                            <button type="button"
                                    class="vt-star-btn"
                                    :class="{ 'is-on': v.is_primary }"
                                    :aria-pressed="v.is_primary ? 'true' : 'false'"
                                    :title="v.is_primary ? 'Este modelo sale primero en el listado del marketplace. En la ficha, tu cliente elige el modelo antes de comprar.' : 'Marcar para que este modelo salga primero en el listado del marketplace'"
                                    @click="setPrimary(v)">
                                {{ v.is_primary ? '⭐' : '☆' }}
                            </button>
                            <label class="vt-card__active" :title="v.is_active ? 'Se vende' : 'No se vende'">
                                <el-switch v-model="v.is_active" @change="patchVariant(v)" />
                                <span>{{ v.is_active ? 'Activa' : 'Inactiva' }}</span>
                            </label>
                        </header>

                        <!-- Fotos: principal + adicionales -->
                        <div class="vt-media" :class="{ 'vt-media--muted': useParentImage }">
                            <div class="vt-media__row">
                                <!-- Principal. Sin foto: sube varias de una vez (la
                                     primera queda de principal y el resto en la
                                     galería). Con foto: tocarla la cambia. -->
                                <div class="vt-media__main">
                                    <el-upload v-if="v.image_url"
                                               :action="`/items/${itemId}/variants/${v.id}/image`"
                                               :headers="headers"
                                               :show-file-list="false"
                                               :before-upload="(f) => beforeVariantUpload(v, f)"
                                               :on-success="(r) => onVariantImageSuccess(v, r)"
                                               :on-error="(e, f) => onVariantUploadError(v, e, f)"
                                               accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/heic,image/heif"
                                               name="file"
                                               class="vt-tile-upload">
                                        <div class="vt-tile vt-tile--main" title="Cambiar la foto principal">
                                            <img :src="v.thumb_url || v.image_url" :alt="'Foto principal de ' + v.display_name" loading="lazy">
                                            <span class="vt-tile__tag">Principal</span>
                                        </div>
                                    </el-upload>
                                    <el-upload v-else
                                               :action="`/items/${itemId}/variants/${v.id}/images`"
                                               :headers="headers"
                                               multiple
                                               :show-file-list="false"
                                               :before-upload="(f) => beforeVariantUpload(v, f)"
                                               :on-success="(r) => onVariantGallerySuccess(v, r)"
                                               :on-error="(e, f) => onVariantUploadError(v, e, f)"
                                               accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/heic,image/heif"
                                               name="file"
                                               class="vt-tile-upload">
                                        <div class="vt-tile vt-tile--main vt-tile--empty" title="Subir fotos de esta variante (puedes elegir varias)">
                                            <i class="el-icon-camera"></i>
                                            <span>Fotos</span>
                                        </div>
                                    </el-upload>
                                </div>

                                <!-- Adicionales: tocar una muestra sus acciones -->
                                <div class="vt-media__strip">
                                    <button v-for="(img, i) in (v.images || [])" :key="img.id"
                                            type="button"
                                            class="vt-tile"
                                            :class="{ 'is-picked': picked.variantId === v.id && picked.imageId === img.id }"
                                            :aria-label="`Foto ${i + 2} de ${v.display_name}`"
                                            @click="togglePick(v, img)">
                                        <img :src="img.thumb_url || img.url" alt="" loading="lazy">
                                    </button>
                                    <div v-for="n in (uploading[v.id] || 0)" :key="'up' + n"
                                         class="vt-tile vt-tile--loading" aria-label="Subiendo foto">
                                        <i class="el-icon-loading"></i>
                                    </div>
                                    <el-upload v-if="v.image_url && (v.images || []).length < maxGallery"
                                               :action="`/items/${itemId}/variants/${v.id}/images`"
                                               :headers="headers"
                                               multiple
                                               :show-file-list="false"
                                               :before-upload="(f) => beforeVariantUpload(v, f)"
                                               :on-success="(r) => onVariantGallerySuccess(v, r)"
                                               :on-error="(e, f) => onVariantUploadError(v, e, f)"
                                               accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/heic,image/heif"
                                               name="file"
                                               class="vt-tile-upload">
                                        <div class="vt-tile vt-tile--add" title="Agregar más fotos a esta variante">
                                            <i class="el-icon-plus"></i>
                                            <span>Más</span>
                                        </div>
                                    </el-upload>
                                </div>
                            </div>

                            <!-- Acciones de la foto elegida (botones de 44px) -->
                            <div v-if="picked.variantId === v.id && pickedImage(v)" class="vt-pick-bar">
                                <button type="button" class="vt-pick-btn vt-pick-btn--star"
                                        :disabled="busyImage"
                                        @click="promoteVariantImage(v, pickedImage(v))">★ Principal</button>
                                <button type="button" class="vt-pick-btn"
                                        :disabled="busyImage || pickedIndex(v) === 0"
                                        aria-label="Mover a la izquierda"
                                        @click="moveVariantImage(v, -1)">◀</button>
                                <button type="button" class="vt-pick-btn"
                                        :disabled="busyImage || pickedIndex(v) === (v.images || []).length - 1"
                                        aria-label="Mover a la derecha"
                                        @click="moveVariantImage(v, 1)">▶</button>
                                <button type="button" class="vt-pick-btn vt-pick-btn--del"
                                        :disabled="busyImage"
                                        @click="removeVariantGalleryImage(v, pickedImage(v))">Quitar</button>
                            </div>
                            <div v-else-if="v.image_url" class="vt-media__hint">
                                Toca la principal para cambiarla; toca otra foto para hacerla principal, moverla o quitarla.
                            </div>

                            <div v-if="useParentImage" class="vt-img-overridden">
                                ⓘ Se usará la foto del producto: estas fotos quedan guardadas pero no se muestran.
                            </div>
                        </div>

                        <!-- Datos de venta -->
                        <div class="vt-fields">
                            <label class="vt-field">
                                <span class="vt-field__label">Precio (S/)</span>
                                <el-input-number v-model="v.sale_unit_price"
                                                 :min="0" :precision="2" :controls="false"
                                                 :placeholder="parentPriceLabel"
                                                 class="vt-input"
                                                 @change="patchVariant(v)" />
                                <small v-if="!v.sale_unit_price && parentPrice > 0" class="vt-inherit-hint">
                                    usa el del producto
                                </small>
                                <small v-else-if="margin(v)" :class="margin(v).loss ? 'vt-margin-loss' : 'vt-margin-ok'">
                                    gana {{ margin(v).profit }} ({{ margin(v).pct }}%)
                                </small>
                            </label>

                            <div class="vt-field">
                                <span class="vt-field__label">Stock</span>
                                <button type="button" class="vt-stock-btn"
                                        :class="{ 'vt-stock-zero': v.stock <= 0 }"
                                        :title="stockTooltip(v)"
                                        @click="openStockDialog(v)">
                                    <span>{{ formatQty(v.stock) }}</span>
                                    <i class="el-icon-edit"></i>
                                </button>
                                <small v-if="isMarketplacePublishable && v.stock <= 0 && v.is_active"
                                       class="vt-stock-warn"
                                       title="Esta variante no se mostrará en el marketplace mientras tenga stock 0">
                                    ⚠ no sale en el marketplace
                                </small>
                            </div>

                            <label class="vt-field">
                                <span class="vt-field__label">SKU</span>
                                <el-input v-model="v.sku" placeholder="Auto" class="vt-input"
                                          @change="patchVariant(v)" />
                            </label>
                        </div>

                        <!-- Acciones de la variante -->
                        <footer class="vt-card__foot">
                            <template v-if="!v.is_active">
                                <el-button size="small" plain icon="el-icon-refresh-left"
                                           @click="reactivateVariant(v)">Reactivar</el-button>
                                <el-button size="small" plain icon="el-icon-sort"
                                           :disabled="!(v.stock > 0)"
                                           :title="v.stock > 0 ? 'Mover su stock a otra variante' : 'No tiene stock que mover'"
                                           @click="openMoveStock(v)">Mover stock</el-button>
                            </template>
                            <el-popconfirm v-else title="¿Eliminar esta variante?"
                                           @confirm="deleteVariant(v)">
                                <el-button slot="reference" size="small" type="danger"
                                           plain icon="el-icon-delete">Eliminar</el-button>
                            </el-popconfirm>
                        </footer>
                    </article>
                </template>
            </div>

            <!-- ── Vista matriz (solo con 2 opciones) ───────────────────── -->
            <div v-if="variants.length > 0 && viewMode === 'matrix'" class="vt-matrix-wrap">
                <div class="vt-matrix-hint">
                    <i class="el-icon-info"></i>
                    Edita el stock de cada combinación directamente. Click en la celda
                    para abrir más opciones (precio, SKU, imagen).
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 vt-matrix">
                        <thead class="table-light">
                            <tr>
                                <th class="vt-matrix-corner">
                                    {{ matrixOptions.rowName }} ↓ / {{ matrixOptions.colName }} →
                                </th>
                                <th v-for="col in matrixOptions.cols" :key="`c-${col.id}`"
                                    class="text-center">
                                    {{ col.value }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in matrixOptions.rows" :key="`r-${row.id}`">
                                <th class="vt-matrix-rowhead">
                                    <span v-if="row.color_hex"
                                          class="vt-group-swatch"
                                          :style="`background:${row.color_hex}`"></span>
                                    {{ row.value }}
                                </th>
                                <td v-for="col in matrixOptions.cols" :key="`cell-${row.id}-${col.id}`"
                                    class="vt-matrix-cell"
                                    :class="{ 'vt-matrix-cell--missing': !findVariantByValues(row.id, col.id) }">
                                    <template v-if="findVariantByValues(row.id, col.id)">
                                        <el-input-number :value="findVariantByValues(row.id, col.id).stock"
                                                         :min="0" :precision="0" :controls="false"
                                                         size="mini" style="width:60px"
                                                         @change="onMatrixStockChange(findVariantByValues(row.id, col.id), $event)" />
                                        <button type="button"
                                                class="vt-matrix-edit"
                                                @click="openStockDialog(findVariantByValues(row.id, col.id))"
                                                title="Más opciones">⋯</button>
                                    </template>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <el-empty v-if="variants.length === 0" description="Sin variantes. Define las opciones y genera las combinaciones." />

            <!-- ── Dialog de ajuste de stock ──────────────────────────── -->
            <el-dialog title="Ajustar stock de variante"
                       :visible.sync="showStockDialog"
                       append-to-body width="420px">
                <template v-if="selectedVariant">
                    <p class="mb-2">
                        <strong>{{ selectedVariant.display_name }}</strong>
                    </p>
                    <div v-for="ws in selectedVariant.warehouse_stocks" :key="ws.warehouse_id"
                         class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span style="width:160px" class="small font-weight-bold">{{ ws.warehouse_name || 'Almacén #' + ws.warehouse_id }}</span>
                            <!-- Sin precision forzado: el stock muestra su valor
                                 natural (100 → '100'; 1.5 → '1.5'). Si necesitas
                                 fraccionar (kg/lt/m) tipea el decimal directamente. -->
                            <el-input-number v-model="ws.stock_physical" :min="0" :step="1"
                                             size="small" style="width:130px" />
                        </div>
                        <div v-if="ws.stock_committed > 0" class="small text-warning ml-1">
                            Comprometido (pedidos): {{ ws.stock_committed }} &nbsp;|&nbsp;
                            Disponible: {{ Math.max(0, ws.stock_physical - ws.stock_committed) }}
                        </div>
                    </div>
                    <div v-if="!selectedVariant.warehouse_stocks || selectedVariant.warehouse_stocks.length === 0">
                        <el-alert type="warning" :closable="false"
                                  title="No hay almacenes asignados a este producto." />
                    </div>
                </template>
                <div slot="footer">
                    <el-button @click="showStockDialog = false">Cancelar</el-button>
                    <el-button type="primary" :loading="savingStock"
                               @click="saveStock">Guardar stock</el-button>
                </div>
            </el-dialog>

            <!-- ── Dialog: aplicar precio a todas las variantes ─────────────
                 Util cuando todas las variantes (modelos/hojas/colores)
                 comparten el mismo precio y el seller no quiere teclearlo
                 una por una. -->
            <el-dialog title="Aplicar precio a todas las variantes"
                       :visible.sync="showBulkPriceDialog"
                       append-to-body width="420px">
                <p class="mb-2 small text-muted">
                    Esto reemplazará el precio de las
                    <strong>{{ variants.length }}</strong> variantes con el
                    valor que ingreses. Las que tenían un precio distinto
                    también se sobrescriben.
                </p>
                <div class="d-flex align-items-center gap-2">
                    <span class="small">Precio:</span>
                    <span class="small text-muted">S/</span>
                    <el-input-number v-model="bulkPriceValue"
                                     :min="0" :precision="4" :controls="false"
                                     size="small" style="width:140px"
                                     placeholder="0.00" />
                </div>
                <div v-if="parentPrice > 0" class="mt-2 small text-muted">
                    💡 Si dejas el campo vacío en cada variante, todas heredan
                    automáticamente el precio del producto padre
                    (<strong>S/ {{ formatMoney(parentPrice) }}</strong>) — esa
                    es la forma más simple. Esta opción solo es útil si quieres
                    forzar un precio distinto al del padre en todas a la vez.
                </div>
                <div slot="footer">
                    <el-button @click="showBulkPriceDialog = false">Cancelar</el-button>
                    <el-button type="primary"
                               :loading="applyingBulkPrice"
                               :disabled="bulkPriceValue === null || bulkPriceValue < 0"
                               @click="applyBulkPrice">
                        Aplicar a todas
                    </el-button>
                </div>
            </el-dialog>

        </template>
    </div>
</template>

<script>
import { imageCompressor } from '../../../../mixins/imageCompressor'
import FeatureTour from '../../../../components/FeatureTour.vue'

// Etiqueta «NUEVO» de las fotos por modelo (2026-11).
const NOVEDAD_VARIANTES_HASTA = new Date('2026-12-31T23:59:59')

export default {
    name: 'VariantsTab',
    mixins: [imageCompressor],
    components: { FeatureTour },

    props: {
        itemId:                   { type: Number,  default: null },
        parentPrice:              { type: Number,  default: 0 },
        // Costo del producto padre y su % de costo adicional (flete, importación,
        // mermas). Una variante sin costo propio hereda ambos — es lo que usa la
        // columna Utilidad para no mostrar un margen inventado.
        parentCost:               { type: Number,  default: 0 },
        landedCostExtraPct:       { type: Number,  default: 0 },
        // Código del producto padre (internal_id o item_code) — base para el
        // generador de SKUs automáticos. Si no llega, el botón avisa al seller.
        itemCode:                 { type: String,  default: '' },
        // Si el producto está publicado en marketplace, mostramos un aviso por
        // variante cuando su stock=0 (porque el card del marketplace la oculta).
        isMarketplacePublishable: { type: Boolean, default: false },
        // Flag actual del producto (items.use_parent_image_for_variants).
        // El form padre lo carga en el record y nos lo pasa; cambios se
        // persisten vía endpoint POST /items/{id}/use-parent-image.
        useParentImageInitial:    { type: Boolean, default: false },
    },

    computed: {
        // Variantes desactivadas que se están mostrando ahora mismo. Sirve para
        // el aviso de la cabecera y para saber si hay algo que rescatar.
        inactiveVariants() {
            return (this.variants || []).filter(v => !v.is_active)
        },
        // Destinos posibles al mover el stock de una variante desactivada:
        // solo las activas, nunca ella misma.
        moveTargets() {
            return (this.variants || []).filter(v => v.is_active && v.id !== (this.movingVariant || {}).id)
        },
        // Texto del placeholder del input de precio. Si el padre no tiene
        // precio (común en producto recién creado) dejamos el placeholder
        // genérico, así no pone "0" engañoso.
        parentPriceLabel() {
            return this.parentPrice > 0 ? this.formatMoney(this.parentPrice) : 'Precio'
        },
        // ID de la variante marcada como principal — usado por el radio
        // group para que solo una esté seleccionada a la vez.
        primaryVariantId() {
            const found = (this.variants || []).find(v => v.is_primary)
            return found ? found.id : null
        },

        // Hay 2+ opciones definidas (ej. Color × Talla). Solo cuando es true
        // mostramos los headers de grupo y filtramos los chips secundarios.
        hasMultipleOptions() {
            return (this.editOptions || []).length > 1
        },

        // ID de la opción "principal" — la que tiene menor position.
        // Es la que usamos para agrupar las filas (Blanco / Rosa / etc).
        primaryOptionId() {
            if (!this.editOptions || !this.editOptions.length) return null
            const sorted = [...this.editOptions].sort(
                (a, b) => (a.position || 0) - (b.position || 0)
            )
            return sorted[0].id
        },

        // Vista matriz solo disponible cuando hay EXACTAMENTE 2 opciones.
        // Con 1 opción la lista basta; con 3+ no cabe en una tabla 2D.
        canShowMatrix() {
            // En el celular la matriz obligaba a desplazarse de lado: ahí solo
            // se ofrecen las tarjetas.
            return !this.narrow && (this.editOptions || []).length === 2 && this.variants.length > 0
        },

        // Configuración de la matriz: ordenamos las opciones por position
        // — la primera (ej. Color) va en filas, la segunda (ej. Talla)
        // en columnas. Esto coincide con la convención de los thumbs en
        // Falabella/Saga donde el color es el primer selector visual.
        matrixOptions() {
            if (!this.canShowMatrix) return null
            const sorted = [...this.editOptions].sort(
                (a, b) => (a.position || 0) - (b.position || 0)
            )
            const [rowOpt, colOpt] = sorted
            return {
                rowName: rowOpt.name,
                colName: colOpt.name,
                rows: (rowOpt.values || []).map(v => ({
                    id: v.id, value: v.value, color_hex: v.color_hex,
                })),
                cols: (colOpt.values || []).map(v => ({
                    id: v.id, value: v.value,
                })),
            }
        },

        // Agrupa las variantes por el valor de la opción principal.
        // Si hay solo 1 opción, devuelve un único grupo con todas (el render
        // omite el header y muestra los chips completos como antes).
        groupedVariants() {
            if (!this.hasMultipleOptions) {
                return [{ key: '_all', label: null, color_hex: null, variants: this.variants }]
            }
            const primaryId = this.primaryOptionId
            const groups = new Map()
            for (const v of this.variants) {
                const primaryVal = (v.option_values || []).find(ov =>
                    Number(ov.item_option_id) === Number(primaryId)
                )
                const key = primaryVal ? primaryVal.value : '__sin'
                if (!groups.has(key)) {
                    groups.set(key, {
                        key,
                        label: primaryVal ? primaryVal.value : 'Sin opción',
                        color_hex: (primaryVal && primaryVal.color_hex) || null,
                        variants: [],
                    })
                }
                groups.get(key).variants.push(v)
            }
            return Array.from(groups.values())
        },
    },

    data() {
        return {
            showNewBadge: new Date() <= NOVEDAD_VARIANTES_HASTA,
            tourSteps: [
                {
                    target: '.vt-media',
                    title: 'Fotos por modelo',
                    body: 'Sube varias fotos de cada modelo a la vez. La primera queda como principal; toca cualquier otra para hacerla principal, moverla o quitarla.',
                },
                {
                    target: '.vt-fields',
                    title: 'Todo en una tarjeta',
                    body: 'Cambia precio, stock y código de cada modelo aquí mismo. Se guarda al momento, en tu tienda y en el marketplace.',
                },
            ],
            // Estado principal
            variants:    [],
            editOptions: [],

            // Rescate de variantes desactivadas. Una variante con stock nunca se
            // borra: se desactiva. Sin este interruptor quedaban invisibles y su
            // stock era irrecuperable desde la interfaz.
            showInactive:   false,
            inactiveCount:  0,
            movingVariant:  null,
            moveTargetId:   null,
            showMoveDialog: false,
            movingStock:    false,

            // UI
            showOptionsEditor: false,
            showStockDialog:   false,
            saving:            false,
            savingStock:       false,
            selectedVariant:   null,

            // Bulk-apply de precio a todas las variantes (caso 'plantas
            // colgantes con distintas hojas pero mismo precio').
            showBulkPriceDialog: false,
            bulkPriceValue:      null,
            applyingBulkPrice:   false,

            // Headers para uploads autenticados (Sanctum/CSRF) — la global
            // headers_token la setea el layout principal del tenant.
            headers: typeof headers_token !== 'undefined' ? headers_token : {},

            // Paletas predefinidas para que el seller agregue valores con
            // un click en lugar de teclear nombre + abrir color picker.
            // Cubren los casos típicos de ropa/calzado/accesorios.
            preColors: [
                { value: 'Rojo',     color_hex: '#dc2626' },
                { value: 'Negro',    color_hex: '#0a0a0a' },
                { value: 'Blanco',   color_hex: '#ffffff' },
                { value: 'Gris',     color_hex: '#6b7280' },
                { value: 'Azul',     color_hex: '#2563eb' },
                { value: 'Celeste',  color_hex: '#0ea5e9' },
                { value: 'Verde',    color_hex: '#16a34a' },
                { value: 'Amarillo', color_hex: '#eab308' },
                { value: 'Naranja',  color_hex: '#ea580c' },
                { value: 'Rosa',     color_hex: '#ec4899' },
                { value: 'Morado',   color_hex: '#9333ea' },
                { value: 'Marrón',   color_hex: '#78350f' },
                { value: 'Beige',    color_hex: '#d6b88a' },
                { value: 'Mostaza',  color_hex: '#ca8a04' },
                { value: 'Vino',     color_hex: '#7f1d1d' },
                { value: 'Plomo',    color_hex: '#9ca3af' },
            ],
            preSizesClothing: ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'ÚNICA'],
            preSizesShoes:    ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'],
            preSizesNumeric:  ['1', '2', '3', '4', '5', '6'],

            // Modo de visualización de variantes: 'list' (default, una fila
            // por combinación con todos los campos) o 'matrix' (Excel con
            // primera opción en filas y segunda en columnas, celdas=stock).
            // Solo aplica cuando hay 2 opciones (ej. Color × Talla).
            viewMode: 'list',

            // Toggle "usar imagen del padre" — copia desde el prop al mount,
            // luego se mantiene local y se persiste con cada cambio.
            useParentImage: false,

            // Galería por variante. `uploading` cuenta las fotos en camino por
            // variante (pinta un recuadro «cargando» por cada una); `picked` es
            // la foto adicional tocada, cuyas acciones salen debajo de la tira.
            uploading: {},
            picked:    { variantId: null, imageId: null },
            busyImage: false,
            maxGallery: 8,   // = ItemVariantController::MAX_GALLERY_IMAGES

            // Pantalla estrecha (< 768px): sin vista matriz.
            narrow: false,
        }
    },

    watch: {
        useParentImageInitial: {
            immediate: true,
            handler(v) { this.useParentImage = !!v },
        },
        itemId(newId) {
            if (newId) this.loadVariants()
        },
    },

    mounted() {
        if (this.itemId) this.loadVariants()
        if (typeof window !== 'undefined' && window.matchMedia) {
            this._mq = window.matchMedia('(max-width: 767px)')
            this._onMq = () => {
                this.narrow = this._mq.matches
                if (this.narrow && this.viewMode === 'matrix') this.viewMode = 'list'
            }
            this._onMq()
            if (this._mq.addEventListener) this._mq.addEventListener('change', this._onMq)
            else if (this._mq.addListener) this._mq.addListener(this._onMq)
        }
    },

    beforeDestroy() {
        if (this._mq && this._onMq) {
            if (this._mq.removeEventListener) this._mq.removeEventListener('change', this._onMq)
            else if (this._mq.removeListener) this._mq.removeListener(this._onMq)
        }
    },

    methods: {
        // ── Cargar datos ──────────────────────────────────────────────────

        loadVariants() {
            const params = this.showInactive ? '?include_inactive=1' : ''
            this.$http.get(`/items/${this.itemId}/variants${params}`)
                .then(({ data }) => {
                    this.inactiveCount = data.inactive_count || 0
                    // Normalizar precio: backend envía 0 cuando la variante no
                    // tiene override; convertimos a null para que el input
                    // muestre el placeholder con el precio heredado del padre
                    // en vez de "0.0000" (que confunde al seller).
                    this.variants = (data.variants || []).map(v => ({
                        ...v,
                        sale_unit_price: Number(v.sale_unit_price) > 0
                            ? Number(v.sale_unit_price)
                            : null,
                    }))
                    this.editOptions = this.cloneOptions(data.options || [])

                    // El stock que muestra el formulario del producto se deriva de
                    // las variantes ACTIVAS: las desactivadas no cuentan en el total
                    // (ver ItemVariantService::COUNT_INACTIVE), así que al mirarlas
                    // con el interruptor no deben inflar la cifra del padre.
                    this.$emit('variants-updated', this.variants.filter(v => v.is_active))
                })
                .catch(() => this.$message.error('Error al cargar variantes'))
        },

        // Utilidad y margen de una variante, con la MISMA fórmula que el backend
        // (PriceCalculator): margen sobre el precio de venta, no markup sobre el
        // costo. Devuelve null cuando no hay costo con el que comparar, para no
        // pintar un 100 % de margen que solo significa "falta el costo".
        margin(v) {
            const precio = Number(v.sale_unit_price) > 0
                ? Number(v.sale_unit_price)
                : Number(this.parentPrice)

            const costoBase = Number(v.purchase_unit_price) > 0
                ? Number(v.purchase_unit_price)
                : Number(this.parentCost)

            if (!(precio > 0) || !(costoBase > 0)) return null

            const costo    = costoBase * (1 + Number(this.landedCostExtraPct || 0) / 100)
            const utilidad = precio - costo

            return {
                profit: (utilidad < 0 ? '-S/ ' : 'S/ ') + this.formatMoney(Math.abs(utilidad)),
                pct:    (utilidad / precio * 100).toFixed(1),
                loss:   utilidad < 0,
            }
        },

        // ── Rescate de variantes desactivadas ────────────────────────────
        //
        // Al quitar un valor de una opción, las combinaciones que lo usaban
        // dejan de ser válidas. Si tenían stock NO se borran —se perdería
        // inventario real— sino que se desactivan. Antes desaparecían de la
        // pantalla y no había forma de reactivarlas ni de recuperar su stock.

        toggleInactive() {
            this.showInactive = !this.showInactive
            this.loadVariants()
        },

        reactivateVariant(variant) {
            this.$http.post(`/items/${this.itemId}/variants/${variant.id}/reactivate`)
                .then(({ data }) => {
                    this.$message.success('Variante reactivada')
                    this.loadVariants()
                })
                .catch((err) => {
                    // 422 = la combinación ya no existe en las opciones actuales.
                    // El backend explica qué hacer; mostramos su mensaje tal cual
                    // en vez de un error genérico.
                    const msg = ((err.response || {}).data || {}).message
                    this.$message.warning(msg || 'No se pudo reactivar la variante')
                })
        },

        openMoveStock(variant) {
            this.movingVariant  = variant
            this.moveTargetId   = null
            this.showMoveDialog = true
        },

        confirmMoveStock() {
            if (!this.moveTargetId) {
                return this.$message.warning('Elige la variante que recibirá el stock.')
            }

            this.movingStock = true
            this.$http.post(`/items/${this.itemId}/variants/${this.movingVariant.id}/move-stock`, {
                target_variant_id: this.moveTargetId,
            })
                .then(({ data }) => {
                    this.$message.success(data.message)
                    this.showMoveDialog = false
                    this.movingVariant  = null
                    this.loadVariants()
                })
                .catch((err) => {
                    const msg = ((err.response || {}).data || {}).message
                    this.$message.error(msg || 'No se pudo mover el stock')
                })
                .finally(() => { this.movingStock = false })
        },

        // ── Gestión de opciones (editor) ─────────────────────────────────

        addOption() {
            this.editOptions.push({ name: '', position: this.editOptions.length, values: [] })
        },

        removeOption(oi) {
            this.editOptions.splice(oi, 1)
        },

        addValue(oi) {
            this.editOptions[oi].values.push({
                value: '', color_hex: null, position: this.editOptions[oi].values.length
            })
        },

        // Heurística por nombre: si la opción se llama "color" (en cualquier
        // mayúscula/minúscula, con o sin acentos), activamos el color picker
        // y los hints de UI específicos para colores.
        isColorOption(name) {
            if (!name) return false
            const n = String(name).toLowerCase().trim()
            return n === 'color' || n === 'colores' || n === 'colour'
                || n.includes('color') || n.includes('colour')
        },

        // Detecta si la opción es de "talla" para mostrar chips XS/S/M/L/etc.
        isSizeOption(name) {
            if (!name) return false
            const n = String(name).toLowerCase().trim()
            return n === 'talla' || n === 'tallas' || n === 'size' || n === 'sizes'
                || n.includes('talla') || n.includes('size')
        },

        // Devuelve la lista de sugerencias para el combobox del valor,
        // según el nombre de la opción. Si no es color/talla, devuelve
        // array vacío (el combobox sigue funcionando con allow-create —
        // el seller puede escribir lo que quiera).
        optionValueSuggestions(name) {
            if (this.isColorOption(name)) return this.preColors
            if (this.isSizeOption(name)) {
                // Unimos tallas de ropa + calzado + numéricas. El filterable
                // del el-select se encarga del filtrado por substring.
                return [
                    ...this.preSizesClothing.map(v => ({ value: v })),
                    ...this.preSizesShoes.map(v => ({ value: v })),
                    ...this.preSizesNumeric.map(v => ({ value: v })),
                ]
            }
            return []
        },

        optionValuePlaceholder(name) {
            if (this.isColorOption(name)) return 'Ej: Rojo'
            if (this.isSizeOption(name)) return 'Ej: M'
            return 'Ej: Rojo, M, Boa'
        },

        // True solo si la opcion tiene sugerencias (color o talla). Para otras
        // (modelo, material, sabor...) renderizamos el-input puro en el template
        // porque el-select+allow-create con options vacio tiene bug de commit.
        hasOptionValueSuggestions(name) {
            return this.isColorOption(name) || this.isSizeOption(name)
        },

        // Cuando el seller cambia el valor (escribiendo o eligiendo del
        // dropdown). Si eligió un color de la lista preColors, auto-rellena
        // el color_hex para que el dot del marketplace salga correcto sin
        // tener que abrir el color picker.
        onOptionValueChange(oi, vi, newValue) {
            const opt = this.editOptions[oi]
            if (!opt) return
            const val = opt.values[vi]
            if (!val) return

            if (this.isColorOption(opt.name)) {
                const match = this.preColors.find(
                    pc => String(pc.value).toLowerCase() === String(newValue || '').toLowerCase()
                )
                if (match && match.color_hex) {
                    val.color_hex = match.color_hex
                }
            }
        },

        // Agrega un valor predefinido al final del listado de la opción `oi`.
        // De-duplica por value (case-insensitive) para no insertar el mismo
        // 2 veces si el seller hace click 2 veces en el mismo chip.
        addPredefined(oi, predefined) {
            const opt = this.editOptions[oi]
            if (!opt) return
            const newVal  = String(predefined.value || '').trim()
            if (!newVal) return
            const exists = opt.values.some(v =>
                String(v.value || '').toLowerCase().trim() === newVal.toLowerCase()
            )
            if (exists) {
                this.$message.info(`"${newVal}" ya está en la lista.`)
                return
            }
            opt.values.push({
                value:     newVal,
                color_hex: predefined.color_hex || null,
                position:  opt.values.length,
            })
        },

        removeValue(oi, vi) {
            this.editOptions[oi].values.splice(vi, 1)
        },

        saveOptions() {
            // Validación básica
            for (const opt of this.editOptions) {
                if (!opt.name.trim()) {
                    return this.$message.warning('El nombre de la opción es obligatorio.')
                }
                if (opt.values.length === 0) {
                    return this.$message.warning(`La opción "${opt.name}" debe tener al menos un valor.`)
                }
                for (const v of opt.values) {
                    if (!v.value.trim()) {
                        return this.$message.warning('Todos los valores deben tener nombre.')
                    }
                }
            }

            this.saving = true
            this.$http.post(`/items/${this.itemId}/variants/options`, { options: this.editOptions })
                .then(({ data }) => {
                    this.variants          = data.variants || []
                    this.editOptions       = this.cloneOptions(data.options || [])
                    this.showOptionsEditor = false
                    this.$message.success(
                        `Variantes generadas: ${data.stats.created} nuevas, ${data.stats.deactivated} desactivadas.`
                    )
                    this.$emit('variants-updated', this.variants)
                })
                .catch(err => {
                    const msg = err.response?.data?.message || 'Error al guardar opciones'
                    this.$message.error(msg)
                })
                .finally(() => { this.saving = false })
        },

        // ── Edición de variante individual ───────────────────────────────

        patchVariant(variant) {
            // Mandamos null si el seller no puso precio — el backend lo
            // persiste como NULL y la variante hereda el precio del padre
            // automáticamente. Mandar 0 quedaría como "regalado" (S/ 0.00).
            const price = Number(variant.sale_unit_price) > 0
                ? Number(variant.sale_unit_price)
                : null
            this.$http.patch(`/items/${this.itemId}/variants/${variant.id}`, {
                sale_unit_price: price,
                sku:             variant.sku || null,
                is_active:       variant.is_active,
            })
                .then(({ data }) => {
                    const idx = this.variants.findIndex(v => v.id === variant.id)
                    if (idx !== -1) {
                        // Re-aplicar la misma normalización del load para no
                        // pintar "0.0000" tras un patch sin precio.
                        this.$set(this.variants, idx, {
                            ...data.variant,
                            sale_unit_price: Number(data.variant.sale_unit_price) > 0
                                ? Number(data.variant.sale_unit_price)
                                : null,
                        })
                    }
                })
                .catch(() => this.$message.error('Error al actualizar variante'))
        },

        // ── Generador de SKUs ────────────────────────────────────────────
        // Toma el código del producto padre + slug 3-char por cada valor de
        // opción. Solo rellena variantes que NO tengan SKU (no sobrescribe).
        // Ej: producto "00012", variante (Color: Rojo, Talla: M) → "00012-ROJ-M"
        generateSkus() {
            if (!this.itemCode) {
                this.$message.warning('El producto no tiene código interno. Configúralo en la pestaña General antes de generar SKUs.')
                return
            }
            let count = 0
            this.variants.forEach(v => {
                if (v.sku) return
                const valSlug = (v.option_values || [])
                    .map(ov => this.skuSlug(ov.value))
                    .filter(s => s)
                    .join('-')
                if (!valSlug) return
                v.sku = `${this.itemCode}-${valSlug}`
                this.patchVariant(v)
                count++
            })
            if (count > 0) {
                this.$message.success(`SKUs generados para ${count} variantes.`)
            } else {
                this.$message.info('Todas las variantes ya tienen SKU.')
            }
        },

        // Slug de 3 chars sin acentos en mayúsculas. "Rojo"→"ROJ", "M"→"M",
        // "38"→"38". Si el valor está vacío, retorna ''.
        skuSlug(value) {
            // ̀-ͯ = combining diacritical marks (acentos en NFD).
            // Quita acentos y caracteres no alfanuméricos, retorna 3 chars max.
            return String(value || '')
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '')
                .substring(0, 3)
        },

        formatMoney(n) {
            const num = Number(n) || 0
            return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
        },

        // Busca la variante que combina rowValueId × colValueId. Devuelve
        // null si esa combinación no fue generada (ej. seller la borró).
        findVariantByValues(rowValueId, colValueId) {
            return this.variants.find(v => {
                const ids = (v.option_values || []).map(ov => Number(ov.id))
                return ids.includes(Number(rowValueId)) && ids.includes(Number(colValueId))
            }) || null
        },

        // Stock rápido desde la matriz: se aplica al PRIMER almacén de la
        // variante (típicamente "Oficina Principal"). Si la variante tiene
        // múltiples almacenes y el seller necesita ajuste fino, click en
        // "⋯" abre el dialog completo. Mantiene la matriz simple sin perder
        // capacidad.
        onMatrixStockChange(variant, newStock) {
            const stocks = variant.warehouse_stocks || []
            if (stocks.length === 0) {
                this.$message.warning('Esta variante no tiene almacenes configurados. Abre el dialog para asignar uno.')
                this.openStockDialog(variant)
                return
            }
            const wh = stocks[0]
            this.$http.post(`/items/${this.itemId}/variants/${variant.id}/stock`, {
                warehouse_id: wh.warehouse_id,
                stock:        Number(newStock) || 0,
            })
                .then(({ data }) => {
                    const idx = this.variants.findIndex(v => v.id === variant.id)
                    if (idx !== -1) this.$set(this.variants, idx, data.variant)
                })
                .catch(() => this.$message.error('No se pudo actualizar el stock.'))
        },

        // Label de la fila cuando se renderiza dentro de un grupo: muestra
        // SOLO los valores secundarios (ya que el primario está en el header).
        // Cuando hay 1 sola opción usa el display_name completo.
        variantSubLabel(v) {
            if (!this.hasMultipleOptions) return v.display_name
            const primaryId = this.primaryOptionId
            const secondary = (v.option_values || [])
                .filter(ov => Number(ov.item_option_id) !== Number(primaryId))
                .map(ov => ov.value)
                .join(' · ')
            return secondary || v.display_name
        },

        // ── Toggle: usar imagen del producto padre ────────────────────────
        // Persiste el flag en items.use_parent_image_for_variants y dispara
        // resync al marketplace para que los listings reflejen el cambio.
        toggleUseParentImage(value) {
            this.$http.post(`/items/${this.itemId}/use-parent-image`, {
                use_parent_image_for_variants: !!value,
            })
                .then(({ data }) => {
                    this.$message.success(value
                        ? 'Ahora se usará la imagen del producto en todas las variantes.'
                        : 'Cada variante volverá a usar su propia imagen.')
                    this.$emit('use-parent-image-changed', !!value)
                })
                .catch(() => {
                    // Revertir el switch si falló
                    this.useParentImage = !value
                    this.$message.error('No se pudo guardar el cambio.')
                })
        },

        // ── Variante principal ───────────────────────────────────────────
        // El backend hace exclusivo en transacción: marca esta como is_primary
        // y todas las demás del item como false. Refrescamos la lista local
        // con el flag actualizado para que el radio resalte la nueva.
        setPrimary(variant) {
            this.$http.post(`/items/${this.itemId}/variants/${variant.id}/primary`)
                .then(() => {
                    this.variants.forEach(v => {
                        v.is_primary = (v.id === variant.id)
                    })
                    this.$message.success(`"${variant.display_name}" es ahora la variante principal del marketplace.`)
                })
                .catch(() => this.$message.error('No se pudo marcar como principal.'))
        },

        deleteVariant(variant) {
            this.$http.delete(`/items/${this.itemId}/variants/${variant.id}`)
                .then(({ data }) => {
                    if (data.result === 'deleted') {
                        this.variants = this.variants.filter(v => v.id !== variant.id)
                        this.$message.success('Variante eliminada.')
                    } else {
                        // Desactivada: refrescar
                        this.loadVariants()
                        this.$message.info('Variante desactivada (tiene stock).')
                    }
                    this.$emit('variants-updated', this.variants)
                })
                .catch(() => this.$message.error('Error al eliminar variante'))
        },

        // ── Imagen por variante ──────────────────────────────────────────
        /**
         * Misma puerta que el resto del panel. Antes decidia por `file.type`,
         * que en Android llega vacio a menudo, y rechazaba fotos buenas; y si
         * la compresion fallaba devolvia el original (`processed || file`),
         * que es justo lo que el servidor luego no admitia.
         */
        beforeVariantImage(file) {
            return this.prepararImagen(file)
        },

        onVariantImageSuccess(variant, response) {
            this.bumpUploading(variant.id, -1)
            if (!response || !response.success) {
                this.$message.error(response && response.message ? response.message : 'Error al subir')
                return
            }
            // Refrescar la variante en la lista con la nueva info (image + image_url)
            this.replaceVariant(response.variant)
            this.$message.success('Imagen actualizada')
        },

        onVariantImageError(err) {
            console.error('[variants-tab] upload error:', err)
            this.$message.error('No se pudo subir la imagen. Intenta de nuevo.')
        },

        // ── Galería de la variante ───────────────────────────────────────
        // Toda foto pasa por la misma puerta del estándar (prepararImagen:
        // valida y comprime en el navegador); el servidor la procesa con
        // ImageProcessingService. Cada acción se guarda al momento.

        async beforeVariantUpload(variant, file) {
            const lista = await this.prepararImagen(file)   // rechaza = no se sube
            this.bumpUploading(variant.id, 1)
            return lista
        },

        bumpUploading(variantId, delta) {
            const n = Math.max(0, (this.uploading[variantId] || 0) + delta)
            this.$set(this.uploading, variantId, n)
        },

        // Las fotos suben en paralelo y cada respuesta trae la variante tal
        // como estaba al guardarse ESA foto: una respuesta que llega tarde no
        // debe borrar de la pantalla las fotos que ya llegaron.
        mergeVariant(variantId, fresh) {
            const idx = this.variants.findIndex(v => v.id === variantId)
            if (idx === -1 || !fresh) return
            const actual = this.variants[idx]
            const porId = {}
            ;(actual.images || []).forEach(i => { porId[i.id] = i })
            ;(fresh.images || []).forEach(i => { porId[i.id] = i })
            const images = Object.values(porId)
                .sort((a, b) => (a.position - b.position) || (a.id - b.id))
            this.$set(this.variants, idx, {
                ...fresh,
                image:     fresh.image || actual.image,
                image_url: fresh.image_url || actual.image_url,
                thumb_url: fresh.thumb_url || actual.thumb_url,
                images,
                sale_unit_price: Number(fresh.sale_unit_price) > 0 ? Number(fresh.sale_unit_price) : null,
            })
        },

        // Reemplazo directo (borrar, ordenar, ★): ahí la respuesta es la verdad.
        replaceVariant(fresh) {
            if (!fresh) return
            const idx = this.variants.findIndex(v => v.id === fresh.id)
            if (idx === -1) return
            this.$set(this.variants, idx, {
                ...fresh,
                sale_unit_price: Number(fresh.sale_unit_price) > 0 ? Number(fresh.sale_unit_price) : null,
            })
        },

        onVariantGallerySuccess(variant, response) {
            this.bumpUploading(variant.id, -1)
            if (!response || !response.success) {
                this.$message.error((response && response.message) || 'No se pudo subir la foto.')
                return
            }
            this.mergeVariant(variant.id, response.variant)
            if (response.message) this.$message.warning(response.message)
        },

        onVariantUploadError(variant, err, file) {
            this.bumpUploading(variant.id, -1)
            this.$message.error('No se pudo subir "' + ((file && file.name) || 'la foto') + '". ' + this.uploadErrorText(err))
        },

        // El <el-upload> entrega el error como Error con el cuerpo en `message`.
        // En el panel los 422 llegan con { message } o como { campo: [msg] }.
        uploadErrorText(err) {
            const raw = err && err.message ? String(err.message) : ''
            try {
                const body = JSON.parse(raw.replace(/^[^{]*/, ''))
                if (body.message) return body.message
                const first = Object.values(body).find(x => Array.isArray(x) && x.length)
                if (first) return first[0]
            } catch (_) { /* no era JSON */ }
            if (/413/.test(raw)) return 'La foto pesa demasiado.'
            return 'Intenta de nuevo.'
        },

        togglePick(variant, img) {
            const same = this.picked.variantId === variant.id && this.picked.imageId === img.id
            this.picked = same ? { variantId: null, imageId: null } : { variantId: variant.id, imageId: img.id }
        },

        pickedImage(variant) {
            return (variant.images || []).find(i => i.id === this.picked.imageId) || null
        },

        pickedIndex(variant) {
            return (variant.images || []).findIndex(i => i.id === this.picked.imageId)
        },

        promoteVariantImage(variant, img) {
            if (!img || this.busyImage) return
            this.busyImage = true
            this.$http.post(`/items/${this.itemId}/variants/${variant.id}/images/${img.id}/primary`)
                .then(({ data }) => {
                    // La que era principal ocupa el sitio de la elegida (mismo
                    // id): sigue marcada, así otro ★ deshace el cambio.
                    this.replaceVariant(data.variant)
                    this.$message.success('Foto principal cambiada.')
                })
                .catch(() => this.$message.error('No se pudo cambiar la foto principal.'))
                .finally(() => { this.busyImage = false })
        },

        moveVariantImage(variant, dir) {
            const list = [...(variant.images || [])]
            const i = this.pickedIndex(variant)
            const j = i + dir
            if (i < 0 || j < 0 || j >= list.length || this.busyImage) return
            const tmp = list[i]; list[i] = list[j]; list[j] = tmp
            // Optimista: se pinta ya y se revierte si el servidor no lo guarda.
            const antes = variant.images
            this.$set(variant, 'images', list)
            this.busyImage = true
            this.$http.post(`/items/${this.itemId}/variants/${variant.id}/images/reorder`, {
                ids: list.map(x => x.id),
            })
                .then(({ data }) => this.replaceVariant(data.variant))
                .catch(() => {
                    this.$set(variant, 'images', antes)
                    this.$message.error('No se pudo cambiar el orden.')
                })
                .finally(() => { this.busyImage = false })
        },

        removeVariantGalleryImage(variant, img) {
            if (!img || this.busyImage) return
            this.$confirm('¿Quitar esta foto de la variante?', 'Quitar foto', {
                confirmButtonText: 'Quitar',
                cancelButtonText: 'Cancelar',
                type: 'warning',
            }).then(() => {
                this.busyImage = true
                return this.$http.delete(`/items/${this.itemId}/variants/${variant.id}/images/${img.id}`)
                    .then(({ data }) => {
                        this.picked = { variantId: null, imageId: null }
                        this.replaceVariant(data.variant)
                        this.$message.success('Foto quitada.')
                    })
                    .catch(() => this.$message.error('No se pudo quitar la foto.'))
                    .finally(() => { this.busyImage = false })
            }).catch(() => {})
        },

        formatQty(n) {
            const num = Number(n) || 0
            return Number.isInteger(num) ? String(num) : num.toFixed(2)
        },

        deleteVariantImage(variant) {
            this.$http.delete(`/items/${this.itemId}/variants/${variant.id}/image`)
                .then(({ data }) => {
                    if (!data.success) return this.$message.error('No se pudo quitar la imagen')
                    this.replaceVariant(data.variant)
                    this.$message.success('Imagen quitada')
                })
                .catch(() => this.$message.error('Error al quitar imagen'))
        },

        // ── Aplicar precio a todas las variantes ─────────────────────────
        // Caso típico: producto con varias hojas/modelos/colores que comparten
        // el mismo precio. En lugar de teclearlo N veces, abre un dialog con
        // un input y al confirmar lo aplica a todas via patchVariant().

        openBulkPriceDialog() {
            if (this.variants.length === 0) {
                this.$message.warning('Genera variantes primero.')
                return
            }
            // Sugerir el precio actual más frecuente (o el del padre si no hay).
            const prices = this.variants
                .map(v => Number(v.sale_unit_price) || 0)
                .filter(p => p > 0)
            this.bulkPriceValue = prices.length > 0
                ? prices[0]                       // primer precio existente
                : (Number(this.parentPrice) || null)
            this.showBulkPriceDialog = true
        },

        applyBulkPrice() {
            const price = Number(this.bulkPriceValue)
            if (isNaN(price) || price < 0) return

            this.applyingBulkPrice = true
            let count = 0
            // Mutamos cada variante y disparamos su PATCH individual. Reutilizamos
            // patchVariant que ya conoce la lógica de NULL para herencia del padre
            // y el reemplazo en this.variants tras la respuesta del backend.
            for (const v of this.variants) {
                v.sale_unit_price = price > 0 ? price : null
                this.patchVariant(v)
                count++
            }

            this.$message.success(
                `Precio S/ ${price.toFixed(2)} aplicado a ${count} ${count === 1 ? 'variante' : 'variantes'}.`
            )
            this.showBulkPriceDialog = false
            this.applyingBulkPrice = false
        },

        // ── Stock ─────────────────────────────────────────────────────────

        openStockDialog(variant) {
            // Clonar para editar sin mutar el array principal
            this.selectedVariant = JSON.parse(JSON.stringify(variant))
            this.showStockDialog = true
        },

        saveStock() {
            if (!this.selectedVariant) return
            const stocks = this.selectedVariant.warehouse_stocks || []
            if (stocks.length === 0) return this.$message.warning('Sin almacenes configurados.')

            this.savingStock = true
            const promises = stocks.map(ws =>
                this.$http.post(`/items/${this.itemId}/variants/${this.selectedVariant.id}/stock`, {
                    warehouse_id: ws.warehouse_id,
                    stock:        ws.stock_physical,
                })
            )

            Promise.all(promises)
                .then(() => {
                    this.showStockDialog = false
                    this.loadVariants()
                    this.$message.success('Stock actualizado.')
                })
                .catch(() => this.$message.error('Error al guardar stock'))
                .finally(() => { this.savingStock = false })
        },

        stockTooltip(v) {
            if (!v.warehouse_stocks || v.warehouse_stocks.length === 0) return 'Sin almacenes'
            return v.warehouse_stocks.map(ws =>
                `${ws.warehouse_name || 'Almacén'}: ${ws.stock_available} disp.`
            ).join(' | ')
        },

        // ── Helpers ───────────────────────────────────────────────────────

        cloneOptions(options) {
            return JSON.parse(JSON.stringify(options))
        },
    },
}
</script>

<style scoped>
.vt-margin-ok   { font-weight: 600; color: #16a34a; font-variant-numeric: tabular-nums; }
.vt-margin-loss { font-weight: 700; color: #dc2626; font-variant-numeric: tabular-nums; }
.vt-margin-pct  { font-size: 11px; color: #8c939d; font-variant-numeric: tabular-nums; }
.variants-tab .gap-1 { gap: 4px; }
.variants-tab .gap-2 { gap: 8px; }
.variants-tab table th,
.variants-tab table td { vertical-align: middle; }

/* ─────── Thumbnail de imagen por variante ─────── */
.vt-img-uploader { display: inline-block; }
.vt-img-thumb {
    position: relative;
    width: 44px; height: 44px;
    border-radius: 8px;
    background-size: cover;
    background-position: center;
    border: 2px solid #e5e7eb;
    cursor: pointer;
    transition: border-color .15s, transform .12s;
}
.vt-img-thumb:hover {
    border-color: #10b981;
    transform: scale(1.06);
}
.vt-img-del {
    position: absolute;
    top: -6px; right: -6px;
    width: 18px; height: 18px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    border: 2px solid #fff;
    cursor: pointer;
    font-size: 11px; font-weight: 700;
    line-height: 1;
    display: none;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
}
.vt-img-thumb:hover .vt-img-del { display: block; }
.vt-img-empty {
    width: 44px; height: 44px;
    border-radius: 8px;
    border: 2px dashed #d1d5db;
    background: #f9fafb;
    color: #6b7280;
    font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .15s, color .15s, background .15s;
}
.vt-img-empty:hover {
    border-color: #10b981;
    color: #065f46;
    background: #ecfdf5;
}

/* ─────── Chips de valores predefinidos (colores/tallas) ─────── */
.vt-pre-label {
    font-size: 11px; font-weight: 600;
    color: #475569; text-transform: uppercase;
    letter-spacing: .3px;
    margin: 8px 0 4px 8px;
}
.vt-pre-chips {
    display: flex; flex-wrap: wrap; gap: 4px;
    margin: 0 8px 6px;
}
.vt-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px;
    font-size: 11.5px; font-weight: 600;
    color: #1f2937;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    cursor: pointer;
    transition: border-color .12s, background .12s, transform .1s;
}
.vt-chip:hover {
    border-color: #10b981;
    background: #ecfdf5;
    color: #065f46;
    transform: translateY(-1px);
}
.vt-chip__swatch {
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 999px;
    border: 1px solid rgba(0,0,0,.08);
    flex-shrink: 0;
}

/* ─────── Hint de precio heredado ─────── */
.vt-inherit-hint {
    font-size: 10px;
    color: #9ca3af;
    margin-top: 2px;
    line-height: 1.2;
    font-style: italic;
}

/* ─────── Toggle "usar imagen padre" ─────── */
.vt-parent-image-toggle {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    padding: 10px 14px;
}
.vt-parent-image-toggle .el-switch__label {
    color: #1f2937;
    font-weight: 600;
}

/* Estrella "Mostrar primero" — más visible que un radio invisible */
.vt-star-btn {
    background: transparent;
    border: 1.5px solid #e5e7eb;
    border-radius: 999px;
    width: 36px; height: 36px;
    cursor: pointer;
    font-size: 18px;
    color: #d1d5db;
    line-height: 1;
    padding: 0;
    transition: transform .12s, border-color .12s, color .12s, background .12s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.vt-star-btn:hover {
    border-color: #f59e0b;
    color: #f59e0b;
    transform: scale(1.08);
}
.vt-star-btn.is-on {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
    font-size: 20px;
}

/* Cuando el toggle está ON: imágenes de variante atenuadas + texto bajo. */
.vt-img-cell--muted .vt-img-thumb,
.vt-img-cell--muted .vt-img-empty {
    opacity: 0.38;
    filter: grayscale(0.6);
    transition: opacity .15s, filter .15s;
}
.vt-img-cell--muted .vt-img-thumb::after {
    content: '✕';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f97316;
    font-weight: 800;
    font-size: 22px;
    pointer-events: none;
    text-shadow: 0 1px 2px rgba(255,255,255,.8);
}
.vt-img-overridden {
    font-size: 10px;
    color: #9a3412;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 4px;
    padding: 1px 4px;
    margin-top: 3px;
    line-height: 1.2;
    display: inline-block;
}

/* ─────── Vista matriz (Color × Talla en grilla) ─────── */
.vt-matrix-wrap { margin-top: 4px; }
.vt-matrix-hint {
    font-size: 11.5px;
    color: #475569;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 6px 10px;
    margin-bottom: 8px;
}
.vt-matrix-hint i { margin-right: 4px; color: #2563eb; }
.vt-matrix th, .vt-matrix td {
    vertical-align: middle;
    text-align: center;
}
.vt-matrix-corner {
    background: #f1f5f9 !important;
    font-size: 10.5px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .3px;
    text-align: left !important;
}
.vt-matrix-rowhead {
    background: #f3f4f6 !important;
    text-align: left !important;
    padding-left: 10px !important;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
}
.vt-matrix-cell {
    padding: 4px !important;
    position: relative;
    min-width: 76px;
}
.vt-matrix-cell--missing {
    background: #f9fafb;
}
.vt-matrix-edit {
    position: absolute;
    top: 2px; right: 3px;
    background: transparent;
    border: 0;
    color: #9ca3af;
    font-size: 14px;
    line-height: 1;
    cursor: pointer;
    padding: 0 4px;
}
.vt-matrix-edit:hover { color: #1f2937; }

/* ─────── Header de grupo (Color principal en variantes Color × Talla) ─────── */
.vt-group-header td {
    background: #f3f4f6 !important;
    border-top: 2px solid #d1d5db !important;
    padding: 6px 10px !important;
}
.vt-group-swatch {
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 999px;
    border: 1.5px solid #9ca3af;
    margin-right: 6px;
    vertical-align: middle;
}

/* ─────── Stock=0 en rojo + warning marketplace ─────── */
.vt-stock-zero {
    color: #dc2626;
    font-weight: 700;
}
.vt-stock-warn {
    font-size: 10px;
    color: #b45309;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 1px 5px;
    margin-top: 3px;
    display: inline-block;
    line-height: 1.3;
    cursor: help;
}

/* ═══════════════════════ Mobile-first responsive ═══════════════════════
   < 768px: el dialog "Opciones y valores" sale del viewport con su width
   fijo 540px; las filas option/value se atropellan y la tabla de variantes
   con 8 columnas es ilegible. Estos overrides convierten todo a layout
   apilado tipo card sin tocar el markup. */
@media (max-width: 768px) {

    /* Dialog: ancho casi total + margen pequeño */
    .variants-tab :deep(.el-dialog),
    :deep(.el-dialog__wrapper .el-dialog) {
        width: 94% !important;
        max-width: 94vw !important;
        margin: 6vh auto !important;
    }
    .variants-tab :deep(.el-dialog__body) {
        padding: 14px 16px !important;
        max-height: 70vh;
        overflow-y: auto;
    }

    /* Editor de opciones: stack del row "Color [valores +] [del]" */
    .variants-tab :deep(.el-dialog .d-flex.align-items-center) {
        flex-wrap: wrap;
        gap: 6px !important;
    }
    .variants-tab :deep(.el-dialog .el-input),
    .variants-tab :deep(.el-dialog .el-select) {
        width: 100% !important;
        max-width: 100% !important;
    }
    .variants-tab :deep(.el-dialog .el-input__inner),
    .variants-tab :deep(.el-dialog .el-select .el-input__inner) {
        /* iOS no hace zoom solo si font-size >= 16px */
        font-size: 16px !important;
    }
    .variants-tab :deep(.el-dialog .el-color-picker) {
        flex-shrink: 0;
    }

    /* Chips predefinidos: scroll horizontal en lugar de wrap-vertical
       (ocupaba demasiado alto en móvil con 16 colores). */
    .vt-pre-chips {
        flex-wrap: nowrap !important;
        overflow-x: auto;
        scrollbar-width: thin;
        padding-bottom: 4px;
    }
    .vt-pre-chips .vt-chip {
        flex-shrink: 0;
    }

    /* Toggle "usar imagen del producto" y cabecera de variantes: stack */
    .variants-tab .vt-parent-image-toggle .el-switch__label {
        max-width: 80vw;
        white-space: normal;
    }
}

/* ═══════════════════ Tarjetas de variante (vista lista) ═══════════════════
   Móvil primero: una columna a todo el ancho, sin desplazamiento lateral,
   áreas táctiles de 44px. Desde 1100px, dos columnas (el modal del producto
   ocupa casi toda la pantalla en escritorio). */
.vt-cards {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
}
@media (min-width: 1100px) {
    .vt-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.vt-group-title {
    grid-column: 1 / -1;
    display: flex; align-items: center; gap: 8px;
    padding: 8px 4px 0;
    font-size: 14px; color: #1f2937;
}
.vt-card {
    min-width: 0;
    display: flex; flex-direction: column; gap: 12px;
    padding: 12px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}
.vt-card--off { background: #f8fafc; border-style: dashed; }
.vt-card--off .vt-media,
.vt-card--off .vt-fields { opacity: .7; }

.vt-card__head {
    display: flex; align-items: center; gap: 10px;
    min-width: 0;
}
.vt-card__title { flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.vt-card__name {
    font-size: 15px; font-weight: 700; color: #111827;
    overflow-wrap: anywhere;
}
.vt-card__badge {
    font-size: 11px; font-weight: 600;
    color: #92400e; background: #fef3c7;
    border-radius: 999px; padding: 2px 8px;
}
.vt-card__active {
    display: inline-flex; align-items: center; gap: 6px;
    min-height: 44px; margin: 0;
    font-size: 12.5px; color: #475569; cursor: pointer;
}
.vt-card .vt-star-btn { width: 44px; height: 44px; flex-shrink: 0; }

/* Fotos */
.vt-media { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.vt-new-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 2px 7px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 12px;
    font-weight: 700;
    vertical-align: middle;
}
.vt-media__row { display: flex; gap: 10px; align-items: flex-start; min-width: 0; }
.vt-media__main { flex-shrink: 0; }
.vt-media__strip {
    flex: 1; min-width: 0;
    display: flex; flex-wrap: wrap; gap: 8px;
}
.vt-tile-upload, .vt-tile-upload :deep(.el-upload) { display: block; }
.vt-tile {
    position: relative;
    width: 64px; height: 64px;
    padding: 0;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    background: #f8fafc;
    overflow: hidden;
    cursor: pointer;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
    color: #64748b; font-size: 11px; font-weight: 600;
    transition: border-color .12s, box-shadow .12s;
}
.vt-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
.vt-tile:hover, .vt-tile:focus-visible { border-color: #10b981; outline: none; }
.vt-tile.is-picked { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .25); }
.vt-tile--main { width: 96px; height: 96px; border-color: #10b981; }
.vt-tile--main i, .vt-tile--add i { font-size: 20px; }
.vt-tile--empty, .vt-tile--add { border-style: dashed; border-color: #cbd5e1; background: #fff; }
.vt-tile--empty { border-color: #10b981; color: #065f46; background: #ecfdf5; }
.vt-tile--loading { cursor: default; font-size: 20px; }
.vt-tile__tag {
    position: absolute; left: 0; right: 0; bottom: 0;
    background: rgba(6, 95, 70, .85); color: #fff;
    font-size: 10.5px; font-weight: 700; text-align: center;
    padding: 2px 0;
}
.vt-media--muted .vt-media__row { opacity: .4; filter: grayscale(.6); }

.vt-media__hint { font-size: 11.5px; color: #64748b; line-height: 1.4; }
.vt-link-btn {
    background: none; border: 0; padding: 0 0 0 4px;
    color: #b91c1c; font-size: 11.5px; font-weight: 600;
    text-decoration: underline; cursor: pointer;
}

.vt-pick-bar {
    display: flex; flex-wrap: wrap; gap: 8px;
    padding: 8px; border-radius: 10px;
    background: #eff6ff; border: 1px solid #bfdbfe;
}
.vt-pick-btn {
    min-height: 44px; min-width: 44px;
    padding: 0 14px;
    border-radius: 8px;
    border: 1px solid #cbd5e1; background: #fff;
    font-size: 14px; font-weight: 600; color: #1f2937;
    cursor: pointer;
}
.vt-pick-btn:disabled { opacity: .45; cursor: not-allowed; }
.vt-pick-btn--star { border-color: #f59e0b; color: #92400e; background: #fffbeb; }
.vt-pick-btn--del  { border-color: #fecaca; color: #b91c1c; margin-left: auto; }

/* Datos de venta: 3 columnas (precio · stock · SKU), 2 en celular chico */
.vt-fields {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.vt-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; margin: 0; }
.vt-field__label { font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .3px; }
.vt-input, .vt-input.el-input-number { width: 100% !important; }
/* !important: el formulario de Tienda virtual fuerza 36px a todos los campos
   en el celular; aquí son áreas táctiles y necesitan 44px. */
.vt-card .vt-input :deep(.el-input__inner) { height: 44px !important; line-height: 44px !important; text-align: left; }
.vt-stock-btn {
    min-height: 44px; width: 100%;
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    padding: 0 12px;
    border: 1px solid #dcdfe6; border-radius: 4px; background: #fff;
    font-size: 15px; font-weight: 700; color: #111827;
    cursor: pointer;
}
.vt-stock-btn i { color: #64748b; font-size: 14px; }
.vt-stock-btn.vt-stock-zero { color: #dc2626; }

.vt-card__foot {
    display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;
    padding-top: 8px; border-top: 1px dashed #e5e7eb;
}
.vt-card__foot .el-button { min-height: 44px; margin-left: 0; }

@media (max-width: 767px) {
    /* iOS no hace zoom si el texto del campo mide 16px o más */
    .vt-card .vt-input :deep(.el-input__inner) { font-size: 16px !important; }
    .vt-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .vt-fields .vt-field:last-child { grid-column: 1 / -1; }
    .vt-tile { width: 72px; height: 72px; }
    .vt-tile--main { width: 96px; height: 96px; }
    .vt-media__row { flex-wrap: wrap; }
    .vt-pick-btn { flex: 1 1 auto; }
    .vt-pick-btn--del { margin-left: 0; }
}
</style>
