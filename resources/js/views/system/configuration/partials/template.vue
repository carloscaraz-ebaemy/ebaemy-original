<template>
    <el-dialog title="Plantilla de mensaje" :visible="showDialogTemplateWs" @open="open" @close="close" :close-on-click-modal="false">
        <h2 style="opacity: 0.4;">
            Plantilla de mensaje
        </h2>
        <div>
            <span>
                Personaliza el mensaje que se recibirán los clientes. Usa varialbes para datos dinamicos.
            </span>
        </div>

    </el-dialog>
</template>

<script>
export default{
    props: ['showDialogTemplateWs', 'config'],
    data(){
        return {
            textarea: ''
        }
    },
    methods:{
        open(){
            console.log('open')
        },
        close(){
            this.$emit('update:showDialogTemplateWs', false)
        }
    }
}
</script>