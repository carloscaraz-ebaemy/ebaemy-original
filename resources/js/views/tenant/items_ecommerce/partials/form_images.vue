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
                            :headers="headers"
                            :action="`/${resource}/upload`"
                            :on-success="onSuccessF"
                            :on-error="onErrorF"
                            :on-remove="handleRemove"
                            :before-upload="beforeUpload" >
                            <i class="el-icon-plus"></i>
                        </el-upload>
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
                source_images: []
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
            onSuccessF(response)
            {
                if(response.success)
                {
                    this.source_images.push(response.data)
                }else {
                    this.$message.error(response.message || 'Error al subir la imagen.')
                }
            },
            onErrorF(err, file)
            {
                console.error('[form_images] upload error:', err)
                this.$message.error('No se pudo subir la imagen "' + file.name + '". Intenta de nuevo.')
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
