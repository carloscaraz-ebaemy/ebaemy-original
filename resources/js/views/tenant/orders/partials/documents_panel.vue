<template>
    <el-dialog
        :close-on-click-modal="true"
        :visible="showDialog"
        :title="'Documentos del pedido ' + (row ? row.order_id : '')"
        top="7vh"
        width="560px"
        @close="cerrar"
    >
        <!-- El contenido vive en `documents_block`, que tambien se empotra en
             el drawer de detalle. Aqui solo se le pone marco y se cierra el
             dialogo cuando una accion se lleva al operador a otra pantalla. -->
        <documents-block
            :row="row"
            @emit-sale-note="conCierre('emit-sale-note', $event)"
            @emit-document="conCierre('emit-document', $event)"
            @dispatch-guide="conCierre('dispatch-guide', $event)"
            @print-label="conCierre('print-label', $event)"
            @fix-billing="conCierre('fix-billing', $event)"
            @doc-options="conCierre('doc-options', $event)"
            @upload-saga="conCierre('upload-saga', $event)"
            @mark-external="conCierre('mark-external', $event)"
        ></documents-block>

        <span slot="footer">
            <el-button @click="cerrar">Cerrar</el-button>
        </span>
    </el-dialog>
</template>

<script>
import DocumentsBlock from "./documents_block.vue";

/**
 * Panel de documentos del pedido, en dialogo.
 *
 * Lo abren los chips de la columna «Docs». Es un marco: todo lo que se ve —los
 * grupos, los estados, los bloqueos y las acciones— lo pone `documents_block`,
 * que es el mismo componente que el drawer de detalle empotra.
 */
export default {
    components: { DocumentsBlock },
    props: {
        showDialog: { type: Boolean, default: false },
        row: { type: Object, default: null },
    },
    methods: {
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        /** Reenvia la accion al padre y cierra: la accion abre otra pantalla. */
        conCierre(evento, carga) {
            this.$emit(evento, carga);
            this.cerrar();
        },
    },
};
</script>
