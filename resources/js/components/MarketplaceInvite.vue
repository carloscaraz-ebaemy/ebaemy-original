<template>
    <!-- Aviso del Inicio. El servidor (/items/marketplace-invite) decide si se
         muestra y cuál (solo admin, solo tiendas que venden en línea):
          - kind=shipping: confirmar cómo envía. Va primero, publicar lo exige.
          - kind=publish:  publicar en ebaemy.com lo que aún no está.
         Cada uno se cierra para siempre con «Ahora no». -->
    <div v-if="data && data.show && !closed" class="mpi" role="region" :aria-label="title">
        <div class="mpi__icon" aria-hidden="true">{{ isShipping ? '🚚' : (isReturns ? '↩️' : '🌐') }}</div>
        <div class="mpi__text">
            <span class="mpi__badge">Nuevo</span>
            <h3 class="mpi__title">{{ title }}</h3>
            <template v-if="isShipping">
                <p class="mpi__body">
                    Ahora tus clientes ven el costo y el plazo de envío en tu tienda y en ebaemy.com.
                    Configúralo una vez: Lima, provincias y recojo.
                </p>
                <p v-if="data.published > 0" class="mpi__stat">
                    Mientras tanto, tus productos en ebaemy.com dicen «A coordinar con la tienda».
                </p>
            </template>
            <template v-else-if="isReturns">
                <p class="mpi__body">
                    Tus compradores quieren saber si pueden cambiar o devolver. Indica tus condiciones
                    (días, quién paga el envío) y armamos el texto por ti.
                </p>
                <p class="mpi__stat">Hoy tu página «Cambios y devoluciones» está vacía.</p>
            </template>
            <template v-else>
                <p class="mpi__body">
                    Publícalos gratis en ebaemy.com. Estamos preparando campañas en Google, Facebook,
                    Instagram y TikTok con los productos del marketplace.
                </p>
                <p class="mpi__stat">
                    Hoy tienes <strong>{{ data.published }} de {{ data.total }}</strong> productos publicados.
                </p>
            </template>
        </div>
        <div class="mpi__actions">
            <button type="button" class="mpi__btn" @click="go">{{ isShipping ? 'Configurar envíos' : (isReturns ? 'Completar política' : 'Publicar mis productos') }}</button>
            <button type="button" class="mpi__later" @click="later">Ahora no</button>
        </div>
    </div>
</template>

<script>
const KEYS = { publish: 'aviso-marketplace-2026-10', shipping: 'aviso-envios-2026-10', returns: 'aviso-cambios-2026-10' };

export default {
    name: 'MarketplaceInvite',
    data() {
        return { data: null, closed: false };
    },
    computed: {
        isShipping() { return this.data && this.data.kind === 'shipping'; },
        isReturns() { return this.data && this.data.kind === 'returns'; },
        title() {
            if (this.isShipping) return 'Confirma cómo envías tus productos';
            if (this.isReturns) return 'Publica tu política de cambios';
            return 'Tus productos pueden llegar a más clientes';
        },
    },
    mounted() {
        this.$http.get('/items/marketplace-invite')
            .then(r => { this.data = r.data; })
            .catch(() => { /* el aviso nunca debe estorbar al Inicio */ });
    },
    methods: {
        mark(status) {
            return this.$http.post(`/feature-tours/${KEYS[this.data.kind] || KEYS.publish}`, { status }).catch(() => {});
        },
        go() {
            // Envíos y cambios no se marcan como vistos al pulsar: si no los
            // guarda, la tarjeta vuelve a salir.
            if (this.isShipping) { window.location.href = '/items_ecommerce?envios=1'; return; }
            if (this.isReturns) { window.location.href = '/items_ecommerce?cambios=1'; return; }
            // Lleva a Productos, donde una guía señala «Publicar todos en Marketplace».
            this.mark('completed').then(() => { window.location.href = '/items_ecommerce?publicar=1'; });
        },
        later() {
            this.closed = true;
            this.mark('dismissed');
        },
    },
};
</script>

<style scoped>
.mpi {
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    margin: 0 0 16px; padding: 16px 18px; border-radius: 12px;
    background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
    border: 1px solid #c7d2fe; color: #1e1b4b;
}
.mpi__icon { font-size: 34px; line-height: 1; flex: none; }
.mpi__text { flex: 1 1 320px; min-width: 0; }
.mpi__badge {
    display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; color: #fff; background: #6366f1; border-radius: 4px; padding: 2px 6px;
}
.mpi__title { font-size: 17px; font-weight: 700; margin: 6px 0 4px; color: #312e81; }
.mpi__body { font-size: 13.5px; line-height: 1.45; margin: 0 0 4px; color: #3730a3; }
.mpi__stat { font-size: 13px; margin: 0; color: #4338ca; }
.mpi__actions { display: flex; align-items: center; gap: 12px; flex: none; }
.mpi__btn {
    border: 0; background: #4f46e5; color: #fff; font-weight: 600; font-size: 14px;
    border-radius: 8px; padding: 10px 16px; cursor: pointer; min-height: 42px;
}
.mpi__btn:hover { background: #4338ca; }
.mpi__later {
    border: 0; background: none; color: #6366f1; font-size: 13px; cursor: pointer;
    text-decoration: underline; padding: 8px 4px;
}
@media (max-width: 576px) {
    .mpi { padding: 14px; gap: 10px; }
    .mpi__icon { display: none; }
    .mpi__actions { width: 100%; justify-content: space-between; }
    .mpi__btn { flex: 1; }
}
</style>
