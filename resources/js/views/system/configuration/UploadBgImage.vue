<template>
  <div>
    <div class="image-container">
      <img
        :src="imageUrl"
        alt="Vista previa"
        class="img-fluid img-thumbnail w-100"
      />
      <div class="overlay">
        <el-button
          type="primary"
          class="change-btn"
          @click="onShowFilePicker"
          :loading="loading"
          :disabled="loading"
          >{{ btnText }}</el-button
        >
      </div>
    </div>
    <input
      type="file"
      @change="onGeneratePreview"
      ref="inputFile"
      class="hidden"
      accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp,image/heic,image/heif"
    />
    <small class="form-control-feedback mt-2 d-block"
      >{{ helpText }}</small
    >
    <small v-if="compressInfo" class="form-control-feedback text-muted d-block mt-1">
      {{ compressInfo }}
    </small>
  </div>
</template>

<script>
import { imageCompressor } from "../../../mixins/imageCompressor";

export default {
  mixins: [imageCompressor],
  props: {
    type: {
      type: String,
      required: true,
    },
    config: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      imageUrl: "",
      loading: false,
      btnText: '',
      helpText: '',
      compressInfo: '',
    };
  },
  mounted() {
      if (this.type === 'bg') {
        this.imageUrl = this.config.image;
        this.helpText = 'Se recomienda una imagen de 1000x1000px con fondo transparente en formato PNG o SVG';
      } else {
          this.imageUrl = this.config.logo || '/logo/tulogo.png';
          this.helpText = 'Se recomienda una imagen de 600x300px con fondo transparente en formato PNG';
      }
    if (this.type === 'bg') {
        this.btnText = 'Cambiar imagen de fondo';
    } else {
        this.btnText = 'Cambiar logo';
    }
  },
  methods: {
    onShowFilePicker() {
      this.$refs.inputFile.click();
    },
    async onGeneratePreview(event) {
      const files = event.target.files;
      if (!files || !files.length) return;

      const originalFile = files[0];
      const originalKb = Math.round(originalFile.size / 1024);

      this.loading = true;
      this.compressInfo = '';

      // Comprimir/redimensionar antes de subir (mixin imageCompressor):
      //  - HEIC/HEIF  JPG via heic2any (dynamic import)
      //  - resize a max 1200x1200 (1024 en mvil)
      //  - quality 0.82 JPG
      // Garantiza que la imagen llegue al server con tamao razonable
      // independiente del original, sin tocar php.ini ni post_max_size.
      let image = originalFile;
      try {
        image = await this.beforeUpload(originalFile);
      } catch (e) {
        this.$message.error('Error procesando la imagen: ' + (e.message || e));
        this.loading = false;
        return;
      }

      const finalKb = Math.round(image.size / 1024);
      if (finalKb < originalKb) {
        this.compressInfo = `Comprimida: ${originalKb} KB  ${finalKb} KB`;
      }

      // Preview local de la imagen comprimida
      const fileReader = new FileReader();
      fileReader.addEventListener("load", () => {
        this.imageUrl = fileReader.result;
      });
      fileReader.readAsDataURL(image);

      const payload = new FormData();
      payload.append("image", image);
      payload.append("type", this.type);
      this.$http
        .post("/configurations/bg", payload)
          .then((response) => {
            this.$message({
              message: response.data.message,
              type: "success",
            });
            // Si el backend devolvi la nueva URL, refrescar la preview con
            // la URL real (no la local del FileReader) para confirmar que
            // se guard.
            if (response.data && response.data.image) {
                this.imageUrl = response.data.image;
            }
          })
          .catch(error => {
              const resp = error.response;
              let msg = 'Error al subir el archivo.';
              if (resp) {
                  if (resp.status === 422 && resp.data && resp.data.errors) {
                      // Errores de validacin de Laravel
                      const allErrors = Object.values(resp.data.errors).flat();
                      msg = allErrors.join(' ');
                  } else if (resp.status === 413) {
                      msg = 'El archivo es demasiado grande para el servidor.';
                  } else if (resp.status === 419) {
                      msg = 'Tu sesin caduc. Recarga la pgina e intenta de nuevo.';
                  } else if (resp.data && resp.data.message) {
                      msg = resp.data.message;
                  } else {
                      msg = `Error ${resp.status}: ${resp.statusText || ''}`;
                  }
              } else if (error.message) {
                  msg = error.message;
              }
              this.$message.error(msg);
              // Revertir preview a la imagen anterior porque NO se guard
              if (this.type === 'bg') {
                  this.imageUrl = this.config.image;
              } else {
                  this.imageUrl = this.config.logo || '/logo/tulogo.png';
              }
          })
          .finally(() => (this.loading = false));
    },
  },
};
</script>

<style scoped>
.image-container {
  position: relative;
  display: inline-block;
  width: 100%;
}

.overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color:rgb(255 255 255 / 70%);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
  border-radius: 0.375rem; /* Para coincidir con img-thumbnail */
}

.image-container:hover .overlay {
  opacity: 1;
}

.change-btn:active {
  transform: translateY(0);
}
</style>
