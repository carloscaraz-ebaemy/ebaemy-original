<template>
<div v-if="visible" class="cmd-overlay" @click.self="close">
    <div class="cmd-palette">
        <div class="cmd-search">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input ref="input" v-model="query" placeholder="Buscar módulo, acción o página..." @keydown.esc="close" @keydown.enter="go(filtered[selectedIndex])" @keydown.down.prevent="selectedIndex = Math.min(selectedIndex+1, filtered.length-1)" @keydown.up.prevent="selectedIndex = Math.max(selectedIndex-1, 0)">
        </div>
        <div class="cmd-results" v-if="filtered.length">
            <div v-for="(item, i) in filtered" :key="item.url" class="cmd-item" :class="{active: i===selectedIndex}" @click="go(item)" @mouseenter="selectedIndex=i">
                <span class="cmd-icon" v-html="item.icon"></span>
                <div>
                    <div class="cmd-title">{{ item.title }}</div>
                    <div class="cmd-desc">{{ item.description }}</div>
                </div>
                <span class="cmd-shortcut" v-if="item.shortcut">{{ item.shortcut }}</span>
            </div>
        </div>
        <div v-else class="cmd-empty">No se encontraron resultados</div>
        <div class="cmd-footer">
            <span>↑↓ Navegar</span><span>↵ Abrir</span><span>Esc Cerrar</span>
        </div>
    </div>
</div>
</template>
<script>
export default {
    data() {
        return {
            visible: false,
            query: '',
            selectedIndex: 0,
            items: [
                {title:'Dashboard', description:'Panel principal', url:'/dashboard', icon:'&#x1F4CA;', shortcut:''},
                {title:'Productos', description:'Gestión de items', url:'/items', icon:'&#x1F4E6;', shortcut:''},
                {title:'Nuevo Producto', description:'Crear item', url:'/items/create', icon:'&#x2795;', shortcut:''},
                {title:'Ventas / Documentos', description:'Facturas y boletas', url:'/documents', icon:'&#x1F4C4;', shortcut:''},
                {title:'Nueva Venta', description:'Crear documento', url:'/documents/create', icon:'&#x1F9FE;', shortcut:''},
                {title:'Notas de Venta', description:'Lista de notas', url:'/sale-notes', icon:'&#x1F4CB;', shortcut:''},
                {title:'POS', description:'Punto de venta', url:'/pos', icon:'&#x1F5A5;', shortcut:''},
                {title:'Pedidos Ecommerce', description:'Órdenes online', url:'/orders', icon:'&#x1F6D2;', shortcut:''},
                {title:'Clientes', description:'Gestión de personas', url:'/persons/customers', icon:'&#x1F465;', shortcut:''},
                {title:'Proveedores', description:'Gestión de proveedores', url:'/persons/suppliers', icon:'&#x1F3ED;', shortcut:''},
                {title:'Inventario', description:'Stock y almacenes', url:'/inventory', icon:'&#x1F4CA;', shortcut:''},
                {title:'Almacenes', description:'Gestión de almacenes', url:'/warehouses', icon:'&#x1F3EA;', shortcut:''},
                {title:'Caja', description:'Apertura y cierre', url:'/cash', icon:'&#x1F4B0;', shortcut:''},
                {title:'Compras', description:'Órdenes de compra', url:'/purchases', icon:'&#x1F6CD;', shortcut:''},
                {title:'Cotizaciones', description:'Presupuestos', url:'/quotations', icon:'&#x1F4DD;', shortcut:''},
                {title:'Reportes', description:'Informes y análisis', url:'/reports', icon:'&#x1F4C8;', shortcut:''},
                {title:'Reportes Ecommerce', description:'KPIs tienda online', url:'/reports/ecommerce', icon:'&#x1F310;', shortcut:''},
                {title:'Configuración', description:'Ajustes del sistema', url:'/configurations', icon:'&#x2699;', shortcut:''},
                {title:'Usuarios', description:'Gestión de usuarios', url:'/users', icon:'&#x1F464;', shortcut:''},
                {title:'Empresa', description:'Datos de la empresa', url:'/companies', icon:'&#x1F3E2;', shortcut:''},
                {title:'Guías de Remisión', description:'Despachos', url:'/dispatches', icon:'&#x1F69A;', shortcut:''},
                {title:'Cola de Almacén', description:'Despacho logístico', url:'/logistic/sale-notes/queue', icon:'&#x1F4E6;', shortcut:''},
                {title:'Reseñas', description:'Moderación de reseñas', url:'/reviews', icon:'&#x2B50;', shortcut:''},
            ]
        }
    },
    computed: {
        filtered() {
            if (!this.query) return this.items.slice(0, 10);
            const q = this.query.toLowerCase();
            return this.items.filter(i =>
                i.title.toLowerCase().includes(q) || i.description.toLowerCase().includes(q)
            ).slice(0, 10);
        }
    },
    methods: {
        open() { this.visible = true; this.query = ''; this.selectedIndex = 0; this.$nextTick(() => this.$refs.input?.focus()); },
        close() { this.visible = false; },
        go(item) { if (item) { window.location.href = item.url; } }
    },
    mounted() {
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); this.open(); }
        });
    }
}
</script>
<style scoped>
.cmd-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding-top:15vh}
.cmd-palette{background:#fff;border-radius:12px;width:100%;max-width:560px;box-shadow:0 25px 50px rgba(0,0,0,.25);overflow:hidden}
.cmd-search{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid #e5e7eb}
.cmd-search input{flex:1;border:none;outline:none;font-size:16px;color:#1f2937}
.cmd-results{max-height:360px;overflow-y:auto;padding:8px}
.cmd-item{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;cursor:pointer;transition:background .1s}
.cmd-item.active,.cmd-item:hover{background:#f3f4f6}
.cmd-icon{font-size:20px;width:32px;text-align:center}
.cmd-title{font-weight:600;color:#1f2937;font-size:14px}
.cmd-desc{color:#6b7280;font-size:12px}
.cmd-shortcut{margin-left:auto;color:#9ca3af;font-size:12px;background:#f3f4f6;padding:2px 8px;border-radius:4px}
.cmd-empty{padding:24px;text-align:center;color:#9ca3af}
.cmd-footer{display:flex;gap:16px;padding:10px 20px;border-top:1px solid #e5e7eb;background:#f9fafb;font-size:12px;color:#9ca3af}
</style>
