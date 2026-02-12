<template>
    <div class="col-lg-6 col-md-12">
        <div class="card">
            <div class="card-header bg-info">
                <h3 class="my-0">Scripts sociales</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 160px;">Plataforma</th>
                            <th>Script</th>
                            <th>Posición</th>
                            <th style="width: 60px;">Activo</th>

                            <th style="width: 60px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in scripts" :key="index">
                            <td>
                                <el-input v-model="item.title"></el-input>
                            </td>
                            <td>
                                <el-input type="textarea" :rows="3" v-model="item.script"
                                    placeholder="<script>...</script>"></el-input>
                            </td>
                            <td>
                                <el-select v-model="item.position" placeholder="Selecciona posición">
                                    <el-option label="Head" value="head"></el-option>
                                    <el-option label="Body" value="body"></el-option>
                                </el-select>
                            </td>
                            <td class="text-center">
                                <el-checkbox v-model="item.active"></el-checkbox>
                            </td>
                            <td class="text-center">
                                <el-button type="danger" icon="el-icon-delete" size="mini"
                                    @click="remove(index)"></el-button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div>
                    <a href="#" @click.prevent="add" style="color: #1e88e5; font-weight: bold;">[ + Agregar ]</a>
                </div>

                <div class="text-right pt-3">
                    <el-button type="primary" :loading="loading_submit" @click="submit">Guardar</el-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            loading_submit: false,
            scripts: [],
        };
    },
    created() {
        this.loadScripts();
    },
    methods: {
        async loadScripts() {
            try {
                const response = await this.$http.get('/ecommerce/social-scripts');
                this.scripts = response.data.map(script => ({
                    ...script,
                    active: Boolean(script.active), // <-- conversión aquí
                }));
            } catch (e) {
                console.error(e);
            }
        },
        add() {
            this.scripts.push({
                title: '',
                script: '',
                position: '',
                active: true,
            });
        },
        async remove(index) { // Añadimos async
            try {
                // 1. Pedir confirmación al usuario
                await this.$confirm('¿Estás seguro de que deseas eliminar este script?', 'Confirmar eliminación', {
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                });

                // 2. Eliminar del array localmente
                this.scripts.splice(index, 1);

                // 3. Llamar al servidor inmediatamente para sincronizar la base de datos
                // Esto disparará tu función saveSocialScripts en el controlador
                await this.submit();

                this.$message({
                    type: 'success',
                    message: 'Script eliminado de la base de datos',
                });

            } catch (error) {
                if (error !== 'cancel') {
                    console.error(error);
                    this.$message.error('Hubo un error al intentar eliminar el registro');
                }
            }
        },
        async submit() {
            this.loading_submit = true;
            try {
                await this.$http.post('/ecommerce/social-scripts/save-all', { scripts: this.scripts });
                this.$message.success('Scripts guardados correctamente');
            } catch (error) {
                this.$message.error('Error al guardar scripts');
                console.error(error);
            } finally {
                this.loading_submit = false;
            }
        },
    },
};
</script>