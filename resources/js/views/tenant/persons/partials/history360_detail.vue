<template>
    <!-- Detalle desplegable de una venta o cotización: productos y pagos. -->
    <div class="c360-detail">
        <div class="c360-detail__title">Productos</div>
        <table class="c360-detail__table">
            <tr v-for="(it, i) in row.items" :key="'i' + i">
                <td class="c360-code">{{ it.code || '—' }}</td>
                <td class="c360-qty">{{ it.quantity }}</td>
                <td>{{ it.description }}</td>
                <td class="c360-num">{{ money(it.unit_price) }}</td>
                <td class="c360-num"><strong>{{ money(it.total) }}</strong></td>
            </tr>
            <tr v-if="!row.items || !row.items.length">
                <td colspan="5" class="text-muted">Sin productos.</td>
            </tr>
        </table>

        <template v-if="row.payments">
            <div class="c360-detail__title">Pagos</div>
            <table class="c360-detail__table">
                <tr v-for="(p, i) in row.payments" :key="'p' + i">
                    <td class="c360-date">{{ p.date }}</td>
                    <td>{{ p.method }}<template v-if="p.reference"> · Op. {{ p.reference }}</template></td>
                    <td class="c360-num"><strong class="text-success">{{ money(p.amount) }}</strong></td>
                </tr>
                <tr v-if="!row.payments.length">
                    <td colspan="3" class="text-danger">Sin pagos registrados.</td>
                </tr>
            </table>
        </template>
    </div>
</template>

<script>
export default {
    props: {
        row: { type: Object, required: true },
    },
    methods: {
        money(v) {
            return Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })
        },
    },
}
</script>

<style scoped>
.c360-detail { padding: 4px 8px 8px 24px; }
.c360-detail__title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin: 6px 0 4px;
}
.c360-detail__table { width: 100%; font-size: 12px; border-collapse: collapse; }
.c360-detail__table td { padding: 3px 6px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.c360-code { width: 110px; font-weight: 700; white-space: nowrap; color: #334155; }
.c360-qty { width: 48px; text-align: right; color: #475569; }
.c360-date { width: 90px; white-space: nowrap; }
.c360-num { text-align: right; white-space: nowrap; width: 90px; }
@media (max-width: 640px) {
    .c360-detail { padding-left: 4px; }
}
</style>
