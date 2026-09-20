<template>
    <el-dialog :title="titleDialog" :visible="showDialog" :close-on-click-modal="false" :close-on-press-escape="false" @close="handleCloseDialog" @open="create">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group" :class="{'has-danger': errors.name}">
                            <label class="control-label">Nombre</label>
                            <el-input v-model="form.name"></el-input>
                            <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                        </div>
                    </div> 
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <!-- Sin padre = grupo de primer nivel. La tienda solo
                             pinta grupos en el menu; las hijas cuelgan de el. -->
                        <div class="form-group">
                            <label class="control-label">Categoría padre</label>
                            <el-select v-model="form.parent_id" clearable filterable
                                       placeholder="Ninguna — es un grupo principal" class="w-100">
                                <el-option v-for="p in parents" :key="p.id"
                                           :label="p.name" :value="p.id"
                                           :disabled="p.id === form.id"></el-option>
                            </el-select>
                            <small class="form-text text-muted">
                                Déjalo vacío para que sea un grupo principal del menú.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Orden</label>
                            <el-input-number v-model="form.sort_order" :min="0" :max="999"
                                             controls-position="right" class="w-100"></el-input-number>
                            <small class="form-text text-muted">0 = alfabético.</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Visible en la tienda</label>
                            <el-switch v-model="form.visible_ecommerce"
                                       active-text="Sí" inactive-text="No"></el-switch>
                        </div>
                    </div>
                </div> 
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.image}">
                        <label class="control-label">
                            Imágen
                            <span class="text-danger"></span>
                            <div class="sub-title text-danger">
                            <small>Se recomienda resoluciones 1024x1024</small>
                            </div>
                        </label>
                        <el-upload
                            class="avatar-uploader w-100"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp"
                            :data="{'type': 'categories'}"
                            :headers="headers"
                            :action="`/${resource}/upload`"
                            :show-file-list="false"
                            :on-success="onSuccess"
                        >
                            <img v-if="form.image_url" :src="form.image_url" class="avatar" />
                            <i v-else class="el-icon-plus avatar-uploader-icon"></i>
                        </el-upload>
                        <small class="form-control-feedback" v-if="errors.image" v-text="errors.image[0]"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end pt-2">
                <el-button class="second-buton me-2" @click.prevent="handleCloseDialog()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>
 
<script>
 

    export default {
        props: ['showDialog', 'recordId'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'categories', 
                errors: {}, 
                form: {}, 
                parents: [],
                headers: headers_token,
            }
        },
        created() {
            this.initForm() 
        },
        methods: {
            handleCloseDialog() {
              if (this.hasUnsavedChanges()) {
                this.$confirm('¿Estás seguro de cerrar el formulario? Se perderán los datos no guardados.', 'Confirmar', {
                  confirmButtonText: 'Cerrar sin guardar',
                  cancelButtonText: 'Cancelar',
                  type: 'warning'
                }).then(() => {
                  this.forceCloseDialog()
                }).catch(() => {

                });
              } else {
                this.forceCloseDialog()
              }
            },
            forceCloseDialog() {
              this.initForm()
              this.$emit('update:showDialog', false)
            },
            hasUnsavedChanges() {
                return JSON.stringify(this.form) !== this.originalForm
            },
            initForm() { 
                this.errors = {} 

                this.form = {
                    id: null,
                    name: null, 
                    image: null,
                    image_url: null,
                    temp_path: null,
                    parent_id: null,
                    sort_order: 0,
                    visible_ecommerce: true,
                }
                this.originalForm = JSON.stringify(this.form)
            },
            create() {

                this.titleDialog = (this.recordId)? 'Editar categoría':'Nueva categoría'
                this.$http.get(`/${this.resource}/parents`).then(response => {
                    // Una hija no puede ser padre: solo se ofrecen los grupos
                    // de primer nivel, y nunca la categoria que se esta editando.
                    this.parents = response.data.filter(p => p.id !== this.recordId)
                })
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                            this.form = response.data
                        })
                }
            },
            submit() {   
 

                this.loading_submit = true  
                this.$http.post(`${this.resource}`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            this.errors = error.response.data 
                        } else {
                            console.log(error.response)
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })
                    
            },  
            onSuccess(response, file, fileList) {
                if (response.success) {
                    this.form.image = response.data.filename;
                    this.form.image_url = response.data.temp_image;
                    this.form.temp_path = response.data.temp_path;
                } else {
                    this.$message.error(response.message);
                }
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            }
        }
    }
</script>
<style>
.avatar-uploader {
    width: 178px;
    height: 178px;
}
.avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-uploader-icon {
    font-size: 28px;
    color: #8c8c8c;
    line-height: 178px;
    text-align: center;
    max-width: 178px;
}
.el-icon-plus {
    width: 178px;
    height: 178px;
}
</style>