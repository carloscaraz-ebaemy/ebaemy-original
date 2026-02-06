<template>
  <div class="col-lg-12 col-md-12">
    <div class="card card-config">
      <div class="card-header bg-dark text-white">
        <h5 class="my-0">Gestión de Pixels (Scripts de Seguimiento)</h5>
      </div>

      <div class="card-body">
        <form @submit.prevent="saveAllPixels">

          <div
            v-for="(pixel, index) in pixels"
            :key="index"
            class="mb-4 border p-3 rounded bg-light shadow-sm"
          >
            <el-form label-position="top">

              <el-form-item label="Título del Pixel">
                <el-input v-model="pixel.title" placeholder="Ej: Google Analytics 4" />
              </el-form-item>

              <el-form-item label="Ubicación del Script">
                <el-radio-group v-model="pixel.position">
                  <el-radio-button label="head">HEAD</el-radio-button>
                  <el-radio-button label="body">BODY</el-radio-button>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="Script (HTML / JS)">
                <el-input
                  type="textarea"
                  :rows="4"
                  v-model="pixel.script"
                  placeholder="Pega aquí el código <script>..."
                />
              </el-form-item>

              <div class="d-flex justify-content-between align-items-center">
                <el-checkbox v-model="pixel.active">Activo</el-checkbox>

                <el-button
                  type="danger"
                  size="mini"
                  @click="confirmDelete(index)"
                >
                  Eliminar
                </el-button>
              </div>

            </el-form>
          </div>

          <div class="d-flex justify-content-between mt-4">
            <el-button type="primary" plain @click="addPixel">
              + Agregar Pixel
            </el-button>

            <el-button
              type="success"
              native-type="submit"
              :loading="loading_save"
            >
              Guardar Configuración
            </el-button>
          </div>

        </form>

        <div
          v-if="pixels.length === 0"
          class="text-center py-5 text-muted border rounded mt-4"
        >
          <p>No hay pixels configurados</p>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading_save: false,
      pixels: [],
      deleted_ids: [],
    };
  },

  created() {
    this.loadPixels();
  },

  methods: {
    async loadPixels() {
      try {
        const res = await this.$http.get('/ecommerce/configuration/pixels');
        this.pixels = res.data.pixels || [];
      } catch {
        this.$message.error('Error al cargar los pixels');
      }
    },

    addPixel() {
      this.pixels.push({
        id: null,
        title: '',
        script: '',
        position: 'head',
        active: true,
      });
    },

    confirmDelete(index) {
      this.$confirm(
        '¿Deseas eliminar este Pixel?',
        'Confirmación',
        { type: 'warning' }
      )
        .then(() => this.removePixel(index))
        .catch(() => {});
    },

    removePixel(index) {
      const pixel = this.pixels[index];
      if (pixel.id) {
        this.deleted_ids.push(pixel.id);
      }
      this.pixels.splice(index, 1);
    },

    async saveAllPixels() {
      this.loading_save = true;
      try {
        const res = await this.$http.post(
          '/ecommerce/configuration/pixels',
          {
            pixels: this.pixels,
            deleted_ids: this.deleted_ids,
          }
        );

        this.pixels = res.data.pixels;
        this.deleted_ids = [];
        this.$message.success('Pixels guardados correctamente');
      } catch {
        this.$message.error('Error al guardar');
      } finally {
        this.loading_save = false;
      }
    },
  },
};
</script>
