/**
 * «Filas por página» (20 / 50 / 100) para las tablas que piden al servidor.
 *
 * Mismo patrón que resources/js/components/DataTable.vue: la tabla manda
 * `per_page` en la petición de `/records` y el middleware ApplyPerPage hace
 * que el servidor pagine con ese número. La elección se recuerda en este
 * equipo por tabla (localStorage, clave `per_page_<resource>`), siempre
 * dentro de try/catch: en modo privado o con el almacenamiento bloqueado la
 * tabla sigue funcionando con el tamaño por defecto del servidor.
 *
 * Uso en la plantilla:
 *   <el-pagination
 *       @current-change="getRecords"
 *       @size-change="cambiarTamano($event, getRecords)"
 *       layout="total, sizes, prev, pager, next"
 *       :page-sizes="opcionesTamano(pagination.per_page)"
 *       ...>
 * y en getQueryParameters(): `per_page: this.filasPorPagina(),`
 */
export const PAGE_SIZES = [20, 50, 100];

export const perPage = {
    data() {
        return {
            tamano: null,
        };
    },
    methods: {
        claveTamano() {
            if (this.pageSizeKey) return this.pageSizeKey;
            const base = this.resource
                || (this.$options && (this.$options.name || this.$options._componentTag))
                || (typeof window !== 'undefined' ? window.location.pathname : '')
                || 'tabla';
            return 'per_page_' + base;
        },
        // Tamaño elegido (o guardado) por el usuario. `undefined` si no eligió
        // nada: así query-string no manda el parámetro y el servidor usa su
        // tamaño de siempre.
        filasPorPagina() {
            if (this.tamano) return this.tamano;
            let guardado = null;
            try { guardado = parseInt(localStorage.getItem(this.claveTamano()), 10); } catch (e) {}
            return PAGE_SIZES.indexOf(guardado) !== -1 ? guardado : undefined;
        },
        // Opciones del selector. Incluye el tamaño que hoy devuelve el servidor
        // (5, 10, 25…) para que el selector muestre el valor real.
        opcionesTamano(actual) {
            const n = parseInt(actual, 10);
            const lista = PAGE_SIZES.slice();
            if (n > 0 && lista.indexOf(n) === -1) lista.push(n);
            return lista.sort((a, b) => a - b);
        },
        cambiarTamano(n, recargar, paginacion) {
            this.tamano = n;
            try { localStorage.setItem(this.claveTamano(), String(n)); } catch (e) {}
            const p = paginacion || this.pagination;
            if (p && typeof p === 'object') {
                if ('current_page' in p) p.current_page = 1;
                if ('per_page' in p) p.per_page = n;
            }
            if (typeof recargar === 'function') recargar();
        },
    },
};

export default perPage;
