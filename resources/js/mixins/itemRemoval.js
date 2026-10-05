/**
 * Quitar productos del sistema, desde cualquiera de las dos listas de Productos.
 *
 * El mixin genérico `deletable` se traga la respuesta del servidor: muestra el
 * mensaje de error y resuelve igual, así que el "Eliminar" de un producto ya
 * vendido parecía no hacer nada. Aquí sí se lee la respuesta y, cuando el
 * histórico impide el borrado, se ofrece retirar el producto en el mismo paso.
 */
export const itemRemoval = {
    methods: {
        /**
         * Pide confirmación, intenta borrar y, si no se puede, ofrece retirar.
         * Resuelve `true` si el listado debe recargarse.
         */
        async removeItem(id) {
            const confirmed = await this.confirmAction(
                '¿Eliminar este producto del sistema? Si nunca se vendió ni se compró, se borra por completo.',
                'Eliminar producto',
                'Eliminar'
            );

            if (!confirmed) return false;

            let data;

            try {
                const res = await this.$http.delete(`/items/${id}`);
                data = res.data || {};
            } catch (error) {
                this.$message.error('No se pudo contactar al servidor para eliminar el producto');
                return false;
            }

            if (data.success) {
                this.$message.success(data.message || 'Producto eliminado con éxito');
                this.$eventHub.$emit('reloadData');
                return true;
            }

            if (data.can_retire) {
                return await this.offerRetire(id, data.message);
            }

            this.$message.error(data.message || 'No se pudo eliminar el producto');
            return false;
        },

        /** El producto tiene histórico: se explica el motivo y se propone retirarlo. */
        async offerRetire(id, reason) {
            const confirmed = await this.confirmAction(
                reason,
                'No se puede eliminar',
                'Retirar del sistema'
            );

            if (!confirmed) return false;

            return await this.retireItem(id, false);
        },

        /** Deja el producto inactivo, fuera de la tienda y fuera del marketplace. */
        async retireItem(id, askFirst = true) {
            if (askFirst) {
                const confirmed = await this.confirmAction(
                    '¿Retirar este producto? Dejará de verse y de poder venderse, pero se conserva su histórico de ventas y compras.',
                    'Retirar del sistema',
                    'Retirar'
                );

                if (!confirmed) return false;
            }

            try {
                const res = await this.$http.post(`/items/${id}/retire`);

                if (res.data && res.data.success) {
                    this.$message.success(res.data.message);
                    this.$eventHub.$emit('reloadData');
                    return true;
                }

                this.$message.error((res.data && res.data.message) || 'No se pudo retirar el producto');
            } catch (error) {
                this.$message.error('No se pudo contactar al servidor para retirar el producto');
            }

            return false;
        },

        /** Reactiva un producto retirado. No lo republica: eso se hace aparte. */
        async restoreItem(id) {
            const confirmed = await this.confirmAction(
                '¿Reactivar este producto? Volverá a estar disponible en el sistema; para mostrarlo en la tienda o en el marketplace hay que activarlo ahí.',
                'Reactivar producto',
                'Reactivar'
            );

            if (!confirmed) return false;

            try {
                const res = await this.$http.post(`/items/${id}/restore`);

                if (res.data && res.data.success) {
                    this.$message.success(res.data.message);
                    this.$eventHub.$emit('reloadData');
                    return true;
                }

                this.$message.error((res.data && res.data.message) || 'No se pudo reactivar el producto');
            } catch (error) {
                this.$message.error('No se pudo contactar al servidor para reactivar el producto');
            }

            return false;
        },

        /** `$confirm` rechaza al cancelar; aquí el "no" no es un error. */
        async confirmAction(message, title, confirmButtonText) {
            try {
                await this.$confirm(message, title, {
                    confirmButtonText,
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                    dangerouslyUseHTMLString: false,
                });

                return true;
            } catch (cancelled) {
                return false;
            }
        },
    },
};
