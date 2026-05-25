<template>
    <el-dialog width="50%" :title="titleDialog" :visible="showDialog" :close-on-click-modal="false" @close="close" @open="create" append-to-body top="7vh">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <el-upload
                            ref="upload_images"
                            list-type="picture-card"
                            :file-list="fileList"
                            :action="`/${resource}/upload`"
                            :data="{ type: 'items', skip_preview: 1 }"
                            :headers="headers"
                            :on-success="onSuccessF"
                            :on-error="onErrorF"
                            :on-progress="onProgressF"
                            :on-remove="handleRemove"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/heic,image/heif"
                            capture="environment"
                            :before-upload="beforeUpload" >
                            <i class="el-icon-plus"></i>
                        </el-upload>
                        <div v-if="processingMsg" class="vt-async-status">
                            <i class="el-icon-loading" style="margin-right:6px"></i>
                            {{ processingMsg }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions text-right pt-2">
                <el-button @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>

    </el-dialog>
</template>

<script>
    import { imageCompressor } from '../../../../mixins/imageCompressor';

    export default {
          mixins: [imageCompressor],
          props: ['showDialog', 'recordId'],
        data() {
            return {
                titleDialog: 'Imagenes',
                loading_submit: false,
                fileList: [],
                headers: headers_token,
                resource: 'items',
                source_images: [],
                // F2: estado del upload async para mostrar al seller
                processingMsg: '',
            }
        },
        created() {

        },
        methods: {
            handleRemove(file, fileList)
            {
                if(file.id)
                {

                    this.$http.get(`/${this.resource}/images/delete/${file.id}`)
                        .then(response => {
                           console.log(response.data)
                        })

                }else{
                    let ind = this.source_images.findIndex( x => x.filename.includes(file.name))
                    this.source_images.splice(ind, 1);
                }

            },
            onSuccessF(response, file)
            {
                this.processingMsg = ''
                if(response.success)
                {
                    const payload = response.data || {}
                    // Compatibilidad: algunos flujos devuelven temp_image
                    // (upload sync/fallback) en lugar de image_url.
                    if (!payload.image_url && payload.temp_image) {
                        payload.image_url = payload.temp_image
                    }
                    // Element UI (picture-card) necesita `file.url` para pintar
                    // el thumbnail en caliente tras subir.
                    const thumb = payload.image_url || payload.temp_image || payload.url
                    if (file && thumb) {
                        file.url = thumb
                    }
                    this.source_images.push(payload)
                }else {
                    this.$message.error(response.message || 'Error al subir la imagen.')
                }
            },
            onErrorF(err, file)
            {
                this.processingMsg = ''
                console.error('[form_images] upload error:', err)
                this.$message.error('No se pudo subir la imagen "' + (file && file.name) + '". ' + (err && err.message ? err.message : 'Intenta de nuevo.'))
            },
            onProgressF(event)
            {
                const pct = event && event.percent ? Math.round(event.percent) : 0
                if (pct < 100)       this.processingMsg = `Subiendo… ${pct}%`
                else                  this.processingMsg = `Listo`
            },
            cleanFileList(){
                // this.fileList = []
            },
            async submit()
            {
                await this.$emit('saveImages', this.source_images);
                 await this.$emit('update:showDialog', false)
            },
            async close() {
                await this.$emit('update:showDialog', false)
                this.clear()
            },
            create()
            {
                 if (this.recordId) {
                    this.$http.get(`/${this.resource}/images/${this.recordId}`)
                        .then(response => {
                            this.fileList = response.data.data
                        })
                }
            },
            clear()
            {
                if(this.$refs.upload_images)
                {
                    this.$refs.upload_images.clearFiles();
                }

                this.source_images= []
            }
        }
    }
</script>

<style scoped>
.vt-async-status {
    margin-top: 8px;
    font-size: 12px;
    color: #6b7280;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 6px 10px;
    display: inline-flex;
    align-items: center;
}
</style>
