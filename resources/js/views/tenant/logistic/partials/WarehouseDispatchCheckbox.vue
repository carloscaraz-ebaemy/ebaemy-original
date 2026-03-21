<template>
    <!--
        SNIPPET — Checkbox de despacho por almacén para el formulario de Nota de Venta.

        INTEGRACIÓN:
        Importar y registrar en el componente del formulario de NV (sale_notes/form o similar),
        luego colocar <warehouse-dispatch-checkbox v-model="form.requires_warehouse_dispatch" />
        antes del botón de guardar.
        Asegurarse de que 'form.requires_warehouse_dispatch' se envíe al backend en el POST.
    -->
    <div class="card border-info mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-3">
                <el-switch
                    v-model="localValue"
                    @change="$emit('update:modelValue', localValue)"
                    :active-text="activeLabel"
                    :inactive-text="inactiveLabel"
                    active-color="#0d6efd"
                    inactive-color="#6c757d"
                />
                <div>
                    <div class="fw-semibold text-dark">
                        {{ localValue ? 'Despacho por almacén' : 'Entrega inmediata' }}
                    </div>
                    <small class="text-muted">
                        {{ localValue
                            ? 'El pedido irá a la cola del almacén (estado: PENDIENTE)'
                            : 'El stock se descuenta y el cliente recibe en tienda (estado: ENTREGA INMEDIATA)'
                        }}
                    </small>
                </div>
            </div>

            <transition name="fade">
                <div v-if="localValue" class="mt-2 p-2 rounded bg-light border-start border-primary border-3">
                    <small>
                        <i class="fas fa-info-circle text-primary me-1"></i>
                        El pedido quedará en la cola del almacén.
                        El personal de almacén lo preparará y despachará, registrando los datos del courier.
                    </small>
                </div>
            </transition>
        </div>
    </div>
</template>

<script>
export default {
    name: 'WarehouseDispatchCheckbox',
    emits: ['update:modelValue'],

    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        activeLabel: {
            type: String,
            default: 'Sí (almacén)',
        },
        inactiveLabel: {
            type: String,
            default: 'No (tienda)',
        },
    },

    data() {
        return {
            localValue: this.modelValue,
        }
    },

    watch: {
        modelValue(val) {
            this.localValue = val
        },
    },
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
