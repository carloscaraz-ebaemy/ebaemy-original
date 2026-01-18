<template>
    <div class="container">
      <!-- HEADER -->
      <div class="header">
        <h1>Editor de Etiquetas</h1>
  
        <div class="header-center">
          <!-- Dimensiones -->
          <div class="dimensions-controls">
            <label>Ancho:</label>
            <input
              type="number"
              v-model.number="labelWidth"
              @change="updateCanvasSize"
            />
            <span class="unit-label">mm</span>
  
            <label style="margin-left: 1rem;">Alto:</label>
            <input
              type="number"
              v-model.number="labelHeight"
              @change="updateCanvasSize"
            />
            <span class="unit-label">mm</span>
          </div>
  
          <!-- Zoom -->
          <div class="zoom-controls">
            <button class="zoom-btn" @click="changeZoom(-0.1)" title="Alejar">-</button>
            <span class="zoom-level">{{ zoomPercent }}%</span>
            <button class="zoom-btn" @click="changeZoom(0.1)" title="Acercar">+</button>
            <button
              class="zoom-btn"
              style="margin-left: 0.25rem; width: auto; padding: 0 0.5rem; font-size: 0.75rem;"
              @click="resetZoom"
              title="Zoom 100%"
            >
              1:1
            </button>
          </div>
        </div>
  
        <div class="header-actions">
          <button class="btn btn-outline" @click="downloadAsImage">
            <span>📥</span> Descargar PNG
          </button>
          <button class="btn btn-outline" @click="downloadAsPDF">
            <span>📄</span> Descargar PDF
          </button>
        </div>
      </div>
  
      <!-- MAIN -->
      <div class="main-content">
        <!-- LEFT SIDEBAR -->
        <div class="sidebar">
          <!-- Datos del sistema -->
          <div class="tool-section">
            <h3>Datos del Sistema</h3>
            <div class="input-group">
              <label>Buscar y seleccionar datos</label>
  
              <div class="tag-selector">
                <!-- Tags seleccionados -->
                <div class="selected-tags">
                  <div
                    v-for="tagKey in selectedTags"
                    :key="tagKey"
                    class="selected-tag"
                  >
                    {{ dataLabels[tagKey] }}
                    <button
                      class="remove-tag"
                      @click.stop="removeTag(tagKey)"
                    >×</button>
                  </div>
                </div>
  
                <!-- Buscador de tags -->
                <div
                  class="tag-input-container"
                  v-if="selectedTags.length < availableTags.length"
                >
                  <input
                    type="text"
                    v-model="tagSearch"
                    placeholder="Buscar datos..."
                    autocomplete="off"
                    @focus="showTagDropdown = true"
                    @keydown.esc="showTagDropdown = false"
                    @keydown.enter.prevent="selectFirstVisibleTag"
                  />
  
                  <div
                    class="tag-dropdown"
                    :class="{ show: showTagDropdown }"
                  >
                    <div
                      class="tag-option"
                      v-for="tag in filteredTags"
                      :key="tag.key"
                      :class="{ selected: selectedTags.includes(tag.key) }"
                      @click="selectTag(tag.key)"
                    >
                      <div class="tag-option-name">{{ tag.label }}</div>
                      <div class="tag-option-value">{{ tag.value }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
  
          </div>
  
          <!-- Agregar elementos -->
          <div class="tool-section">
            <h3>Agregar Elementos</h3>
            <div style="display: flex; gap: 0.25rem;">
              <!-- <button class="btn btn-secondary btn-full" @click="addField('text')">
                Texto
              </button> -->
              <button
                class="btn btn-secondary btn-full"
                @click="$refs.imageUpload.click()"
              >
                Subir Imagen
              </button>
              <input
                ref="imageUpload"
                type="file"
                class="file-upload"
                accept="image/*"
                @change="handleImageUpload"
              />
            </div>
            <!-- <div style="margin-top: 0.5rem;">
              <button
                class="btn btn-secondary btn-full"
                @click="addField('barcode','barcode')"
              >
                Código de Barras
              </button>
            </div> -->
          </div>
  
          <!-- Propiedades del campo -->
          <div
            class="field-properties-section"
            v-show="showFieldProperties"
          >
            <h3>Propiedades del Campo</h3>
  
            <!-- TEXTO -->
            <div v-if="fieldType === 'text'">
              <div class="input-group">
                <label>Contenido</label>
                <input
                  type="text"
                  v-model="fieldContent"
                  @input="updateFieldContent"
                />
              </div>
  
              <div class="input-group">
                <label>Tamaño de Letra</label>
                <div style="display: flex; gap: 0.25rem;">
                  <input
                    type="number"
                    v-model.number="fontSize"
                    min="8"
                    max="72"
                    @change="updateFieldStyle"
                    style="width: 30%;"
                  />
  
                  <button
                    class="btn-toggle"
                    :class="{ active: fontBold }"
                    @click="toggleFontWeight"
                    title="Negrita"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                      stroke="currentColor" stroke-width="2">
                      <path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                      <path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                    </svg>
                  </button>
  
                  <button
                    class="btn-align"
                    :class="{ active: textAlign === 'left' }"
                    @click="setAlignment('left')"
                    title="Izquierda"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                      stroke="currentColor" stroke-width="2">
                      <line x1="17" y1="10" x2="3" y2="10"></line>
                      <line x1="21" y1="6" x2="3" y2="6"></line>
                      <line x1="21" y1="14" x2="3" y2="14"></line>
                      <line x1="17" y1="18" x2="3" y2="18"></line>
                    </svg>
                  </button>
  
                  <button
                    class="btn-align"
                    :class="{ active: textAlign === 'center' }"
                    @click="setAlignment('center')"
                    title="Centro"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                      stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="10" x2="6" y2="10"></line>
                      <line x1="21" y1="6" x2="3" y2="6"></line>
                      <line x1="21" y1="14" x2="3" y2="14"></line>
                      <line x1="18" y1="18" x2="6" y2="18"></line>
                    </svg>
                  </button>
  
                  <button
                    class="btn-align"
                    :class="{ active: textAlign === 'right' }"
                    @click="setAlignment('right')"
                    title="Derecha"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                      stroke="currentColor" stroke-width="2">
                      <line x1="21" y1="10" x2="7" y2="10"></line>
                      <line x1="21" y1="6" x2="3" y2="6"></line>
                      <line x1="21" y1="14" x2="3" y2="14"></line>
                      <line x1="21" y1="18" x2="7" y2="18"></line>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
  
            <!-- BARRAS -->
            <div v-if="fieldType === 'barcode'">
              <div class="input-group">
                <label>Contenido del Código</label>
                <input
                  type="text"
                  v-model="barcodeContent"
                  @input="handleBarcodeInput"
                />
              </div>
  
              <div class="input-group">
                <label>Formato</label>
                <select v-model="barcodeFormat" @change="updateBarcodeStyle">
                  <option value="CODE128">CODE128</option>
                  <option value="EAN13">EAN13</option>
                  <option value="EAN8">EAN8</option>
                  <option value="UPC">UPC</option>
                  <option value="CODE39">CODE39</option>
                </select>
              </div>
  
              <div class="input-group">
                <label>Configuración</label>
                <div style="display: flex; gap: 0.25rem;">
                  <div style="flex: 1;">
                    <label style="font-size: 0.8rem; margin-bottom: 0.1rem;">Altura</label>
                    <input
                      type="number"
                      v-model.number="barcodeHeight"
                      min="20"
                      max="150"
                      @change="updateBarcodeStyle"
                      style="width: 100%;"
                    />
                  </div>
                  <div style="flex: 1;">
                    <label style="font-size: 0.8rem; margin-bottom: 0.1rem;">Mostrar Valor</label>
                    <select
                      v-model="barcodeDisplayValue"
                      @change="updateBarcodeStyle"
                      style="width: 100%;"
                    >
                      <option value="true">Sí</option>
                      <option value="false">No</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
  
            <!-- IMAGEN (ahora solo borrado) -->
            <div v-if="fieldType === 'image'">
              <div class="input-group">
                <label>Acciones de Imagen</label>
                <button class="btn btn-destructive btn-full" @click="deleteSelectedField">
                  Eliminar imagen
                </button>
              </div>
            </div>
  
            <div class="tool-section">
              <button class="btn btn-outline btn-full" @click="deleteSelectedField">
                Eliminar Campo
              </button>
            </div>
          </div>
  
          <!-- Limpiar todo -->
          <div class="tool-section">
            <button class="btn btn-outline btn-full" @click="clearCanvas">
              Limpiar Todo
            </button>
          </div>
        </div>
  
        <!-- CANVAS -->
        <div
          class="canvas-container"
          ref="canvasContainer"
          @click="handleCanvasContainerClick"
          @wheel.prevent="onCanvasWheel"
        >
          <div
            id="labelCanvas"
            ref="labelCanvas"
          ></div>
        </div>
  
        <!-- RIGHT SIDEBAR -->
        <div class="right-sidebar">
          <div class="tool-section">
            <h3>Diseños Guardados</h3>
            <div class="input-group">
              <label>Nombre del diseño</label>
              <input
                type="text"
                v-model="templateName"
                placeholder="Ingresa un nombre para el diseño"
              />
            </div>
            <button class="btn btn-primary btn-full" @click="saveTemplate(null)">
              Guardar Diseño Actual
            </button>
  
            <div style="margin-top: 1rem;">
              <div
                v-for="(tpl, index) in templates"
                :key="tpl.timestamp"
                class="template-card"
                :data-template-index="index"
                @click="applyTemplate(index)"
                :class="{ select: index === selectTemplate, default: index === defaultTemplateIndex }"
              >

              <div v-if="tpl.is_default" class="default-badge">POR DEFECTO</div>
                <div
                  class="template-name"
                >
                  {{ tpl.name }}
                </div>
                <div class="template-actions">
                  <button
                    class="btn-icon"
                    :class="{ default: tpl.is_default }"
                    @click.stop="isDefault(tpl.id)"
                    :title="index === defaultTemplateIndex ? 'Diseño predeterminado' : 'Establecer como predeterminado'"
                  >
                    {{ index === defaultTemplateIndex ? '★' : '☆' }}
                  </button>
                  <button
                    class="btn-icon primary"
                    @click.stop="saveTemplate(tpl.id)"
                    title="Guardar diseño"
                  >
                    ⤓
                  </button>
                  <button
                    class="btn-icon destructive"
                    @click.stop="deleteTemplate(tpl.id)"
                    title="Eliminar diseño"
                  >
                    🗑
                  </button>
                </div>
              </div>
            </div>
  
          </div>
        </div>
      </div>
    </div>
  </template>

  <style scoped>
  .select {
    border-color: hsl(142.1 76.2% 36.3%);
    background: hsl(142.1 76.2% 36.3% / 0.05);
  }
  .default-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: hsl(142.1 76.2% 36.3%);
    color: white;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 9999px;
    border: 2px solid white;
    box-shadow: 0 2px 4px 0 rgb(0 0 0 / 0.15);
    z-index: 10;
  }
</style>
  
  <script>
  
  export default {
    name: 'LabelDesigner',
  
    data () {
      return {
        resource: 'item-editor-tag',
        // Tamaño etiqueta
        labelWidth: 100,
        labelHeight: 60,
  
        // Zoom
        currentZoom: 1,
        minZoom: 0.25,
        maxZoom: 3,
  
        // Drag/resize
        selectedField: null,
        isDragging: false,
        isResizing: false,
        startX: 0,
        startY: 0,
        startWidth: 0,
        startHeight: 0,
        startLeft: 0,
        startTop: 0,
        fieldCounter: 0,
        uploadedImages: {},
  
        // Datos del sistema
        systemData: {
          internal_id: 'PROD001',
          name: 'Producto Ejemplo',
          barcode: '1234567890123',
          category: 'Electrónicos',
          unit_type: 'Piezas',
          brand: 'Marca Ejemplo',
          sale_unit_price: '$99.99',
          attribute_5013: 'M',
          attribute_5014: 'Azul, Rojo',
          status: 'Activo'
        },
        dataLabels: {
          internal_id: 'Código Interno',
          name: 'Nombre',
          barcode: 'Código de Barras',
          category: 'Categoría',
          unit_type: 'Unidad',
          brand: 'Marca',
          sale_unit_price: 'Precio',
          attribute_5013: 'Talla',
          attribute_5014: 'Colores',
          status: 'Status'
        },
  
        // Tags
        selectedTags: [],
        infoFields: [], // Información de los campos en el canvas
        infoCanva: {},
        tagSearch: '',
        showTagDropdown: false,
        selectTemplate:  null,
  
        // Propiedades del campo seleccionado
        showFieldProperties: false,
        fieldType: null,
        fieldContent: '',
        fontSize: 14,
        fontBold: false,
        textAlign: 'left',
        barcodeContent: '',
        barcodeFormat: 'CODE128',
        barcodeHeight: 50,
        barcodeDisplayValue: 'true',
  
        // Plantillas
        templates: [],
        templateName: '',
        defaultTemplateIndex: null
      }
    },
  
    computed: {
      zoomPercent () {
        return Math.round(this.currentZoom * 100)
      },
      availableTags () {
        return Object.keys(this.dataLabels).map(key => ({
          key,
          label: this.dataLabels[key],
          value: this.systemData[key]
        }))
      },
  
      filteredTags () {
        const term = (this.tagSearch || '').toLowerCase()
        return this.availableTags.filter(tag => {
          if (this.selectedTags.includes(tag.key)) return false
          if (!term) return true
          return (
            tag.label.toLowerCase().includes(term) ||
            String(tag.value).toLowerCase().includes(term)
          )
        })
      }
    },
  
    mounted () {
      this.updateCanvasSize()
      this.initCanvasListeners()
      this.getRecords()
  
      // Cerrar dropdown de tags al hacer click fuera
      document.addEventListener('click', this.handleGlobalClick)
      document.addEventListener('mousemove', this.onMouseMove)
      document.addEventListener('mouseup', this.onMouseUp)
      document.addEventListener('keydown', this.onKeyDown)
    },
  
    beforeDestroy () {
      document.removeEventListener('click', this.handleGlobalClick)
      document.removeEventListener('mousemove', this.onMouseMove)
      document.removeEventListener('mouseup', this.onMouseUp)
      document.removeEventListener('keydown', this.onKeyDown)
    },
  
    methods: {
      async getRecords()
      {
        await this.$http.get(`${this.resource}/records`)
          .then(response => {
            this.templates = response.data.templates;
          })
          .catch(error => {
            console.error('Error fetching records:', error);
          });
      },
      mmToPx () {
        return 3.7795275591
      },
  
      updateCanvasSize () {
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
        const baseWidth = this.labelWidth * this.mmToPx()
        const baseHeight = this.labelHeight * this.mmToPx()
        canvas.style.width = baseWidth + 'px'
        canvas.style.height = baseHeight + 'px'
        canvas.style.transformOrigin = 'center center'
        canvas.style.transform = `scale(${this.currentZoom})`
      },
  
      changeZoom (delta) {
        const newZoom = Math.max(
          this.minZoom,
          Math.min(this.maxZoom, this.currentZoom + delta)
        )
        if (newZoom !== this.currentZoom) {
          this.currentZoom = newZoom
          this.updateCanvasSize()
        }
      },
  
      resetZoom () {
        this.currentZoom = 1
        this.updateCanvasSize()
      },
  
      onCanvasWheel (e) {
        const delta = e.deltaY > 0 ? -0.1 : 0.1
        this.changeZoom(delta)
      },
  
      // ---- TAGS ----
      handleGlobalClick (e) {
        if (!e.target.closest('.tag-selector')) {
          this.showTagDropdown = false
        }
      },
  
      selectTag (key) {
        
        if (!this.selectedTags.includes(key)) {
          this.selectedTags.push(key)
        }
        this.tagSearch = ''
        this.showTagDropdown = true


        this.addField(key === 'barcode' ? 'barcode' : 'text', key)
      },
  
      removeTag (key) {
        this.selectedTags = this.selectedTags.filter(k => k !== key)
      },
  
      selectFirstVisibleTag () {
        if (!this.filteredTags.length) return
        this.selectTag(this.filteredTags[0].key)
      },
  
      addSystemFields () {
        this.selectedTags.forEach(key => {
          // Texto por cada tag
          this.addField('text', key)
        })
      },
  
      // ---- CAMPOS ----
      initCanvasListeners () {
        const container = this.$refs.canvasContainer
        if (!container) return
        // resto de listeners globales ya están en mounted()
      },
  
      handleCanvasContainerClick (e) {
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
        if (e.target === this.$refs.canvasContainer || e.target === canvas) {
          if (this.selectedField) {
            this.selectedField.classList.remove('selected')
            this.selectedField = null
            this.showFieldProperties = false
          }
        }
      },
  
      addField (type, systemDataKey = null) {
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
  
        const field = document.createElement('div')
        let fieldId   = 'field_' + (++this.fieldCounter)
        field.className = 'field'
        field.id  = fieldId
        field.dataset.type = type
        if (systemDataKey) {
          field.dataset.systemData = systemDataKey
        }
  
        field.style.left = '20px'
        field.style.top = '20px'
        field.style.width = type === 'image' ? '100px' : '150px'
        field.style.height = type === 'image' ? '100px' : '40px'

        
        let infoField = {
          id: fieldId,
          type: '', 
          x:  field.style.left,
          y:  field.style.top,
          width: field.style.width,
          height: field.style.height,
        }

  
        const content = document.createElement('div')
        content.className = 'field-content'
  
        if (type === 'text') {
          const textValue = systemDataKey
            ? (this.systemData[systemDataKey] || '')
            : 'Texto de ejemplo'

            infoField.type = 'text';
          content.textContent = textValue
          content.style.fontSize = '14px'
          content.style.color = '#000000'
          content.style.fontWeight = 'normal'
          content.style.textAlign = 'left'
        } else if (type === 'barcode') {
          const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
          svg.setAttribute('id', 'barcode_' + this.fieldCounter)
          content.appendChild(svg)
          field.style.height = '80px'
          field.style.width = '200px'

          infoField.type = 'barcode';
  
          const barcodeValue =
            systemDataKey === 'barcode'
              ? this.systemData.barcode
              : '123456789012'

  
          field.dataset.barcodeValue = barcodeValue
          field.dataset.barcodeFormat = 'CODE128'
          field.dataset.barcodeHeight = '50'
          field.dataset.barcodeDisplayValue = 'true'
  
          setTimeout(() => {
            try {
              if (window.JsBarcode) {
                window.JsBarcode(svg, barcodeValue, {
                  format: 'CODE128',
                  height: 50,
                  displayValue: true,
                  fontSize: 14,
                  margin: 5
                })
              }
            } catch (e) {
              console.error('Error generando código de barras:', e)
            }
          }, 100)
        }
  
        // Botón borrar
        const deleteBtn = document.createElement('button')
        deleteBtn.className = 'delete-btn'
        deleteBtn.textContent = '×'
        deleteBtn.onclick = ev => {
          console.log();
          
          ev.stopPropagation()
          field.remove()
          if (this.selectedField === field) {
            this.selectedField = null
            this.showFieldProperties = false
          }
        }
  
        // Resize handle
        const resizeHandle = document.createElement('div')
        resizeHandle.className = 'resize-handle'
  
        // Badge
        const badge = document.createElement('div')
        badge.className = 'field-badge'
        const typeLabels = {
          text: systemDataKey ? this.dataLabels[systemDataKey] : 'Texto',
          barcode: systemDataKey ? this.dataLabels[systemDataKey] : 'Código'
        }
        badge.textContent = typeLabels[type] || type
  
        field.appendChild(content)
        field.appendChild(badge)
        field.appendChild(deleteBtn)
        field.appendChild(resizeHandle)
        canvas.appendChild(field)
  
        field.addEventListener('mousedown', e => {
          if (e.target === resizeHandle) {
            this.startResize(e, field)
          } else if (e.target === field || e.target === content) {
            this.startDrag(e, field)
          }
        })
  
        this.infoFields.push(infoField);
        this.selectField(field)
        
      },
  
      handleImageUpload (event) {
        const file = event.target.files[0]
        if (!file) return
  
          const canvas = this.$refs.labelCanvas
          if (!canvas) return
  
          const field = document.createElement('div')
          field.className = 'field'
          field.id = 'field_' + (++this.fieldCounter)
          field.dataset.type = 'image'
  
          field.style.left = '20px'
          field.style.top = '20px'
          field.style.width = '100px'
          field.style.height = '100px'
  
          const content = document.createElement('div')
          content.className = 'field-content'
  
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          content.appendChild(img)
          this.uploadedImages[field.id] = file 
          const deleteBtn = document.createElement('button')
          deleteBtn.className = 'delete-btn'
          deleteBtn.textContent = '×'
          deleteBtn.onclick = ev => {
            ev.stopPropagation()
            field.remove()
            delete this.uploadedImages[field.id]
            if (this.selectedField === field) {
              this.selectedField = null
              this.showFieldProperties = false
            }
          }
  
          const resizeHandle = document.createElement('div')
          resizeHandle.className = 'resize-handle'
  
          const badge = document.createElement('div')
          badge.className = 'field-badge'
          badge.textContent = 'Imagen'
  
          field.appendChild(content)
          field.appendChild(badge)
          field.appendChild(deleteBtn)
          field.appendChild(resizeHandle)
          canvas.appendChild(field)
  
          field.addEventListener('mousedown', ev => {
            if (ev.target === resizeHandle) {
              this.startResize(ev, field)
            } else if (!ev.target.classList.contains('delete-btn')) {
              this.startDrag(ev, field)
            }
          })
  
          this.selectField(field)
        event.target.value = ''
      },
  
      startDrag (e, field) {
        if (e.target.classList.contains('delete-btn')) return
        this.isDragging = true
        this.selectField(field)
  
        this.startX = e.clientX
        this.startY = e.clientY
        this.startLeft = field.offsetLeft
        this.startTop = field.offsetTop
        e.preventDefault()
      },
  
      startResize (e, field) {
        this.isResizing = true
        this.selectField(field)
  
        this.startX = e.clientX
        this.startY = e.clientY
        this.startWidth = field.offsetWidth
        this.startHeight = field.offsetHeight
        e.preventDefault()
        e.stopPropagation()
      },
  
      onMouseMove (e) {
        if (!this.selectedField) return
  
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
  
        const baseWidth = this.labelWidth * this.mmToPx()
        const baseHeight = this.labelHeight * this.mmToPx()
  
        // WIDTH
        let indexField = this.infoFields.findIndex(f => f.id === this.selectedField.id);
        if (this.isDragging) {


          
            
          const dx = (e.clientX - this.startX) / this.currentZoom
          const dy = (e.clientY - this.startY) / this.currentZoom
  
          const newLeft = Math.max(
            0,
            Math.min(this.startLeft + dx, baseWidth - this.selectedField.offsetWidth)
          )
          const newTop = Math.max(
            0,
            Math.min(this.startTop + dy, baseHeight - this.selectedField.offsetHeight)
          )
          if (indexField !== -1) {
              this.infoFields[indexField].x = newLeft + 'px';
              this.infoFields[indexField].y = newTop + 'px';
          }
  
          this.selectedField.style.left = newLeft + 'px'
          this.selectedField.style.top = newTop + 'px'
          
        }
  
        if (this.isResizing) {
          const dx = (e.clientX - this.startX) / this.currentZoom
          const dy = (e.clientY - this.startY) / this.currentZoom
  
          const newWidth = Math.max(50, this.startWidth + dx)
          const newHeight = Math.max(20, this.startHeight + dy)
  
          this.selectedField.style.width = newWidth + 'px'
          this.selectedField.style.height = newHeight + 'px'

          if (indexField !== -1) {
              this.infoFields[indexField].width = newWidth + 'px';
              this.infoFields[indexField].height = newHeight + 'px';
          }

        }
      },
  
      onMouseUp () {
        this.isDragging = false
        this.isResizing = false
      },
  
      onKeyDown (e) {
        if ((e.ctrlKey || e.metaKey) && this.$refs.canvasContainer) {
          if (e.key === '+' || e.key === '=') {
            e.preventDefault()
            this.changeZoom(0.1)
          } else if (e.key === '-') {
            e.preventDefault()
            this.changeZoom(-0.1)
          } else if (e.key === '0') {
            e.preventDefault()
            this.resetZoom()
          }
        }
      },
  
      selectField (field) {
        const allFields = this.$refs.labelCanvas
          ? this.$refs.labelCanvas.querySelectorAll('.field')
          : []
        Array.from(allFields).forEach(f => f.classList.remove('selected'))
  
        field.classList.add('selected')
        this.selectedField = field
        this.showFieldProperties = true
  
        const content = field.querySelector('.field-content')
        const type = field.dataset.type
        this.fieldType = type
  
        if (type === 'image') {
          
          // nada especial
          return
        }
  
        if (type === 'barcode') {
          const value =
            field.dataset.barcodeValue ||
            this.systemData[field.dataset.systemData] ||
            '123456789012'
          this.barcodeContent = value
          this.barcodeFormat = field.dataset.barcodeFormat || 'CODE128'
          this.barcodeHeight = parseInt(field.dataset.barcodeHeight || '50')
          this.barcodeDisplayValue = field.dataset.barcodeDisplayValue || 'true'
          return
        }
  
        // Texto
        this.fieldContent = content.textContent || ''
        this.fontSize = parseInt(content.style.fontSize || '14')
        this.textAlign = content.style.textAlign || 'left'
        this.fontBold = content.style.fontWeight === 'bold'
      },
  
      toggleFontWeight () {
        if (!this.selectedField || this.fieldType !== 'text') return
        this.fontBold = !this.fontBold
        const content = this.selectedField.querySelector('.field-content')
        content.style.fontWeight = this.fontBold ? 'bold' : 'normal'
      },
  
      setAlignment (align) {
        if (!this.selectedField || this.fieldType !== 'text') return
        this.textAlign = align
        const content = this.selectedField.querySelector('.field-content')
        content.style.textAlign = align
      },
  
      updateFieldContent () {
        if (!this.selectedField || this.fieldType === 'image') return
        const content = this.selectedField.querySelector('.field-content')
        content.textContent = this.fieldContent
  
        if (this.selectedField.dataset.systemData) {
          this.systemData[this.selectedField.dataset.systemData] = this.fieldContent
        }
      },
  
      updateFieldStyle () {
        if (!this.selectedField || this.fieldType !== 'text') return
        const content = this.selectedField.querySelector('.field-content')
        content.style.fontSize = (this.fontSize || 14) + 'px'
      },
  
      deleteSelectedField () {
        if (!this.selectedField) return
        if (this.selectedField.dataset.type === 'image') {
          delete this.uploadedImages[this.selectedField.id]
        }
        this.selectedField.remove()
        this.selectedField = null
        this.showFieldProperties = false
      },
  
      clearCanvas () {
        const canvas = this.$refs.labelCanvas
        if (canvas) canvas.innerHTML = ''
        this.uploadedImages = {}
        this.selectedField = null
        this.fieldCounter = 0
        this.selectedTags = []
        this.showFieldProperties = false
      },
  
      // ---- BARRAS ----
      handleBarcodeInput () {
        // Solo números
        this.barcodeContent = this.barcodeContent.replace(/[^0-9]/g, '')
        this.updateBarcode()
      },
  
      updateBarcode () {
        if (!this.selectedField || this.fieldType !== 'barcode') return
        let value = this.barcodeContent
        const format = this.barcodeFormat
        const height = this.barcodeHeight
        const displayValue = this.barcodeDisplayValue === 'true'
  
        if (!value) return
  
        let isValid = true
        let errorMsg = ''
  
        switch (format) {
          case 'EAN13':
            if (value.length !== 13 && value.length !== 12) {
              isValid = false
              errorMsg = 'EAN13 requiere 12 o 13 dígitos'
            }
            break
          case 'EAN8':
            if (value.length !== 8 && value.length !== 7) {
              isValid = false
              errorMsg = 'EAN8 requiere 7 u 8 dígitos'
            }
            break
          case 'UPC':
            if (value.length !== 12 && value.length !== 11) {
              isValid = false
              errorMsg = 'UPC requiere 11 o 12 dígitos'
            }
            break
        }
  
        if (!isValid) {
          alert(errorMsg)
          return
        }
  
        const svg = this.selectedField.querySelector('svg')
        if (svg && window.JsBarcode) {
          try {
            window.JsBarcode(svg, value, {
              format,
              height,
              displayValue,
              fontSize: 14,
              margin: 5,
              width: 2
            })
            this.selectedField.dataset.barcodeValue = value
            this.selectedField.dataset.barcodeFormat = format
            this.selectedField.dataset.barcodeHeight = height
            this.selectedField.dataset.barcodeDisplayValue = displayValue
          } catch (e) {
            console.error('Error generando código de barras:', e)
            alert('Error al generar código de barras: ' + e.message)
          }
        }
      },
  
      updateBarcodeStyle () {
        this.updateBarcode()
      },
  
      // ---- EXPORTAR ----
      async downloadAsImage () {
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
        if (!window.html2canvas) {
          alert('html2canvas no está disponible')
          return
        }
  
        const originalTransform = canvas.style.transform
        canvas.style.transform = 'scale(1)'
  
        const fields = canvas.querySelectorAll('.field')
        fields.forEach(field => {
          field.style.border = 'none'
          field.style.background = 'transparent'
          field.style.boxShadow = 'none'
          const handle = field.querySelector('.resize-handle')
          const btn = field.querySelector('.delete-btn')
          const badge = field.querySelector('.field-badge')
          if (handle) handle.style.display = 'none'
          if (btn) btn.style.display = 'none'
          if (badge) badge.style.display = 'none'
        })
  
        try {
          const canvasImg = await window.html2canvas(canvas, {
            scale: 3,
            backgroundColor: '#ffffff',
            logging: false,
            useCORS: true,
            allowTaint: true
          })
  
          canvasImg.toBlob(blob => {
            const url = URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.download = 'etiqueta_' + Date.now() + '.png'
            link.href = url
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            URL.revokeObjectURL(url)
  
            this.restoreEditingElements(fields)
            canvas.style.transform = originalTransform
          }, 'image/png')
        } catch (error) {
          console.error('Error al generar imagen:', error)
          alert('Error al generar la imagen: ' + error.message)
          this.restoreEditingElements(fields)
          canvas.style.transform = originalTransform
        }
      },
  
      async downloadAsPDF () {
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
        if (!window.html2canvas) {
          alert('html2canvas no está disponible')
          return
        }
        if (!window.jspdf || !window.jspdf.jsPDF) {
          alert('jsPDF no está disponible')
          return
        }
  
        const originalTransform = canvas.style.transform
        canvas.style.transform = 'scale(1)'
  
        const fields = canvas.querySelectorAll('.field')
        fields.forEach(field => {
          field.style.border = 'none'
          field.style.background = 'transparent'
          field.style.boxShadow = 'none'
          const handle = field.querySelector('.resize-handle')
          const btn = field.querySelector('.delete-btn')
          const badge = field.querySelector('.field-badge')
          if (handle) handle.style.display = 'none'
          if (btn) btn.style.display = 'none'
          if (badge) badge.style.display = 'none'
        })
  
        try {
          const canvasImg = await window.html2canvas(canvas, {
            scale: 3,
            backgroundColor: '#ffffff',
            logging: false,
            useCORS: true,
            allowTaint: true
          })
  
          const imgData = canvasImg.toDataURL('image/png')
          const { jsPDF } = window.jspdf
          const width = this.labelWidth
          const height = this.labelHeight
  
          const pdf = new jsPDF({
            orientation: width > height ? 'landscape' : 'portrait',
            unit: 'mm',
            format: [width, height]
          })
  
          pdf.addImage(imgData, 'PNG', 0, 0, width, height)
          pdf.save('etiqueta_' + Date.now() + '.pdf')
  
          this.restoreEditingElements(fields)
          canvas.style.transform = originalTransform
        } catch (error) {
          console.error('Error al generar PDF:', error)
          alert('Error al generar el PDF: ' + error.message)
          this.restoreEditingElements(fields)
          canvas.style.transform = originalTransform
        }
      },
  
      restoreEditingElements (fields) {
        fields.forEach(field => {
          field.style.border = ''
          field.style.background = ''
          field.style.boxShadow = ''
          const handle = field.querySelector('.resize-handle')
          const btn = field.querySelector('.delete-btn')
          const badge = field.querySelector('.field-badge')
          if (handle) handle.style.display = ''
          if (btn && field.classList.contains('selected')) btn.style.display = ''
          if (badge) badge.style.display = ''
        })
      },
  
      // ---- PLANTILLAS ----
      async saveTemplate (id = null) {
        const name = (this.templateName || '').trim()
        if (!name && !id) {
          this.$message.warning('Por favor ingresa un nombre para el diseño')
          return
        }
  
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
  
        const fields = Array.from(canvas.querySelectorAll('.field')).map(field => {
          
          const content = field.querySelector('.field-content')
          const template = {
            b_id: field.dataset.bId || null,
            html_id: field.id,
            type: field.dataset.type,
            systemData: field.dataset.systemData || null,
            position: {
              left: field.style.left,
              top: field.style.top,
              width: field.style.width,
              height: field.style.height
            },
            has_image: false,
            content: {
              text: content.textContent,
              fontSize: content.style.fontSize,
              fontWeight: content.style.fontWeight,
              textAlign: content.style.textAlign,
              color: content.style.color
            }
          }
  
          if (field.dataset.type === 'barcode') {
            template.barcode = {
              value: field.dataset.barcodeValue,
              format: field.dataset.barcodeFormat,
              height: field.dataset.barcodeHeight,
              displayValue: field.dataset.barcodeDisplayValue
            }
          }
  
            let d_has_image = Boolean(field.dataset.hasImage);
            let d_path = field.dataset.path || '';
            if (id && d_has_image && d_path ) {
              template.systemData = "image";
              template.has_image = true;
              template.path = d_path            
            }
          if (field.dataset.type === 'image' && this.uploadedImages[field.id]) {
            
              template.systemData = "image";
              template.has_image = true;
          }
  
          return template
        })
  
        const templateData = {
          name,
          timestamp: Date.now(),
          canvas: {
            width: this.labelWidth,
            height: this.labelHeight
          },
          fields
        }
        
        this.templateName = ''
        if (id) {
          await this.$http.post('item-editor-tag\\tags\\update\\' + id,templateData )
            .then(async (response) => {
              if (response.data.success) {
                await this.saveImages(response.data.fields_image);
                await this.getRecords();
                this.$message.success(response.data.message);
                this.selectTemplate = this.templates.length -1;
              }
            })
          
        } else {
          await this.$http.post('item-editor-tag\\tags\\save',templateData )
            .then( async (response) => {
              if (response.data.success) {
                await this.saveImages(response.data.fields_image);
                await this.getRecords();
                this.$message.success(response.data.message);
                this.selectTemplate = this.templates.length -1;
                
              }
            })
        }
      },
      async saveImages(f_images){
        let promises = f_images.map(async (f )=> {
          let image = this.uploadedImages[f.html_id];
          
          let fmdata = new FormData();

          fmdata.append('id', f.id);
          fmdata.append('image', image);

          return this.$http.post('item-editor-tag\\tags\\save-image',fmdata )
        })
        await Promise.all(promises);
      },

      applyTemplate (index) {
        this.selectedTags = []
        const template = this.templates[index]
        
        if (!template) return
  
        this.labelWidth = parseFloat(template.canvas.width || 100)
        this.labelHeight = parseFloat(template.canvas.height || 60)
        this.updateCanvasSize()
  
        const canvas = this.$refs.labelCanvas
        if (!canvas) return
        canvas.innerHTML = ''
        this.uploadedImages = {}
        this.selectedField = null
        this.fieldCounter = 0
  
        template.fields.forEach((fieldData, index) => {
          const field = document.createElement('div')
          field.className = 'field'
          field.dataset.bId = fieldData.id
          field.id = 'field_' + index
          field.dataset.type = fieldData.type
          if (fieldData.systemData) {
            field.dataset.systemData = fieldData.systemData
          }
  
          field.style.left = fieldData.position.left
          field.style.top = fieldData.position.top
          field.style.width = fieldData.position.width
          field.style.height = fieldData.position.height
  
          const content = document.createElement('div')
          content.className = 'field-content'
          if (!this.selectedTags.includes(fieldData.systemData) && fieldData.systemData !== 'image') {
            this.selectedTags.push(fieldData.systemData)
          }
          
          if (fieldData.type === 'text') {
            content.textContent = fieldData.content.text
            content.style.fontSize = fieldData.content.fontSize || '14px'
            content.style.fontWeight = fieldData.content.fontWeight || 'normal'
            content.style.textAlign = fieldData.content.textAlign || 'left'
            content.style.color = fieldData.content.color || '#000'
          } else if (fieldData.type === 'barcode' && fieldData.barcode) {
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
            content.appendChild(svg)
  
            field.dataset.barcodeValue = fieldData.barcode.value
            field.dataset.barcodeFormat = fieldData.barcode.format
            field.dataset.barcodeHeight = fieldData.barcode.height
            field.dataset.barcodeDisplayValue = fieldData.barcode.displayValue
            setTimeout(() => {
              try {
                if (window.JsBarcode) {
                  window.JsBarcode(svg, fieldData.barcode.value, {
                    format: fieldData.barcode.format,
                    height: parseInt(fieldData.barcode.height || 50),
                    displayValue: fieldData.barcode.displayValue === 'true' || fieldData.barcode.displayValue === true,
                    fontSize: 14,
                    margin: 5
                  })
                }
              } catch (e) {
                console.error('Error generando código de barras:', e)
              }
            }, 100)
          } else if (fieldData.type === 'image' && fieldData.image) {
            const img = document.createElement('img')
            field.dataset.hasImage = fieldData.has_image
            field.dataset.path = fieldData.path || ''
            img.src = fieldData.image
            content.appendChild(img)
          }
  
          const deleteBtn = document.createElement('button')
          deleteBtn.className = 'delete-btn'
          deleteBtn.textContent = '×'
          deleteBtn.onclick = e => {
            let element = e.target;
            let parent = element.closest('.field');
            
            e.stopPropagation()
            field.remove()
            if (this.selectedField === field) {
              this.selectedField = null
              this.showFieldProperties = false
              const canvas = this.$refs.labelCanvas
              if (!canvas) return
  
              this.selectedTags = this.selectedTags.filter( key => key !== parent.dataset.systemData)
              
            }
          }
  
          const resizeHandle = document.createElement('div')
          resizeHandle.className = 'resize-handle'
  
          const badge = document.createElement('div')
          badge.className = 'field-badge'
          const typeLabels = {
            text: fieldData.systemData ? this.dataLabels[fieldData.systemData] : 'Texto',
            barcode: 'Código',
            image: 'Imagen'
          }
          badge.textContent = typeLabels[fieldData.type] || fieldData.type
  
          field.appendChild(content)
          field.appendChild(badge)
          field.appendChild(deleteBtn)
          field.appendChild(resizeHandle)
          canvas.appendChild(field)
  
          field.addEventListener('mousedown', e => {
            if (e.target === resizeHandle) {
              this.startResize(e, field)
            } else if (e.target === field || e.target === content) {
              this.startDrag(e, field)
            }
          })
  
          const fieldNumber = parseInt(field.id.split('_')[1]) || 0
          if (fieldNumber >= this.fieldCounter) {
            this.fieldCounter = fieldNumber
          }
        })


        this.selectTemplate = index
      },
  
      deleteTemplate (id) {
        this.$http.get(`${this.resource}/tags/delete/${id}`)
          .then(response => {
            if (response.data.success) {
              this.$message.success(response.data.message);
              this.clearCanvas();
              this.getRecords();
            }
          })
          .catch(error => {
            console.error('Error al eliminar la plantilla:', error);
          });
      },
      isDefault(id) {
        this.$http.get(`${this.resource}/tags/default/${id}`)
          .then(response => {
            if (response.data.success) {
              this.$message.success(response.data.message);
              this.getRecords();
            }
          })
          .catch(error => {
          });

      },
      setDefaultTemplate (index) {
        this.defaultTemplateIndex = index
        // Solo visual; si quieres persistir, guarda este índice en localStorage
      }
    }
  }
  </script>
  