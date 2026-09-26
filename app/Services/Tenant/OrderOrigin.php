<?php

namespace App\Services\Tenant;

use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\Order;
use App\Models\Tenant\SalesChannel;

/**
 * De dónde viene un pedido — EL identificador, no uno más.
 *
 * Antes esta pregunta se respondía de tres maneras que no coincidían: la
 * relación `marketplaceOrder` (solo si venía precargada), el prefijo `MKP_`
 * del código de canal, y una heurística sobre el texto de `reference_payment`
 * en el Vue. El caso que lo delató: un tenant con el canal sembrado a mano
 * como `SAGA` —sin el prefijo— daba `isExternalChannel() = false`, así que un
 * pedido de Falabella se podía anular desde EBAEMY, que es justo lo que esa
 * regla existe para impedir.
 *
 * Aquí hay DOS grupos y nada más, porque es la división que usa el operador:
 *
 *   · `system`   — el pedido nació en EBAEMY: el link de envío que se comparte
 *                  a los clientes (ENV01), la tienda virtual del tenant
 *                  (ECOM), el marketplace propio de ebaemy.com (MKP01), el
 *                  alta manual y los pedidos antiguos sin canal.
 *   · `external` — el pedido lo hizo un portal de fuera: Saga Falabella,
 *                  MercadoLibre, TikTok Shop, Meta. Su ciclo de vida lo manda
 *                  el portal.
 *
 * El desglose fino NO vive aquí: son los canales reales del tenant
 * (`sales_channels`), que ya se filtran con `channel_id`. Una segunda lista de
 * categorías tendría que mantenerse en paralelo a los canales y volveríamos al
 * problema del principio.
 */
final class OrderOrigin
{
    public const GRUPO_SISTEMA = 'system';
    public const GRUPO_EXTERNO = 'external';

    /** Los dos grupos, más el «todos» que no filtra. */
    public const GRUPOS = [self::GRUPO_SISTEMA, self::GRUPO_EXTERNO];

    /**
     * Canales de tipo `marketplace` que NO son de fuera.
     *
     * `MKP01` es el marketplace propio (ebaemy.com): ese pedido es nuestro de
     * punta a punta y se gestiona —y se anula— desde aquí.
     */
    private const MARKETPLACE_PROPIO = ['MKP01'];

    /** Etiqueta para el operador. */
    public static function etiquetaGrupo(string $grupo): string
    {
        return $grupo === self::GRUPO_EXTERNO ? 'Otros canales' : 'Del sistema';
    }

    /**
     * ¿Este canal es un portal de fuera?
     *
     * Tres señales, y basta una. La tercera es la que faltaba: un canal de tipo
     * `marketplace` que no sea el propio es de fuera aunque su código no lleve
     * el prefijo, porque `marketplacePlatformChannel()` reutiliza cualquier
     * canal con nombre reconocible y esos no lo llevan.
     */
    public static function esCanalExterno(?SalesChannel $canal): bool
    {
        if (!$canal) {
            return false;
        }

        $code = strtoupper(trim((string) $canal->code));

        if ($code !== '' && str_starts_with($code, 'MKP_')) {
            return true;
        }

        return $canal->type === 'marketplace'
            && !in_array($code, self::MARKETPLACE_PROPIO, true);
    }

    /**
     * ¿Este pedido viene de fuera?
     *
     * La relación `marketplaceOrder` se mira solo si ya viene cargada: en un
     * listado de veinte filas, consultarla sería una consulta por fila, y el
     * canal ya resuelve el caso. Ojo con la trampa que esto esconde — para
     * decidir algo que BLOQUEA (anular, editar líneas) sobre un pedido suelto,
     * cargar la relación antes de preguntar.
     */
    public static function esExterno(Order $order): bool
    {
        if ($order->relationLoaded('marketplaceOrder') && $order->marketplaceOrder) {
            return true;
        }

        return self::esCanalExterno($order->channel);
    }

    public static function grupoDe(Order $order): string
    {
        return self::esExterno($order) ? self::GRUPO_EXTERNO : self::GRUPO_SISTEMA;
    }

    /**
     * Nombre del origen para la fila del listado: el del canal, y si el pedido
     * no declara canal se dice eso, no se inventa uno.
     */
    public static function nombreDe(Order $order): string
    {
        $nombre = trim((string) optional($order->channel)->name);

        if ($nombre !== '') {
            return $nombre;
        }

        if ($order->relationLoaded('marketplaceOrder') && $order->marketplaceOrder) {
            return trim((string) optional($order->marketplaceOrder->channel)->name)
                ?: 'Canal externo';
        }

        return 'Sin origen declarado';
    }

    /**
     * Restringe la consulta a un grupo. Es la MISMA regla que `esExterno()`,
     * escrita en SQL: si divergen, el contador del botón dice una cosa y la
     * tabla otra.
     *
     * @param string $grupo `system` o `external`
     */
    public static function aplicarGrupo($query, string $grupo)
    {
        $externo = function ($q) {
            $q->whereHas('channel', function ($c) {
                // `MKP\_%` con la barra: en LIKE el guion bajo es un comodín, y
                // sin escaparlo `MKP01` también entraría — el marketplace propio
                // acabaría contado como pedido de fuera.
                $c->where('code', 'LIKE', 'MKP\_%')
                  ->orWhere(function ($m) {
                      $m->where('type', 'marketplace')
                        ->whereNotIn('code', self::MARKETPLACE_PROPIO);
                  });
            });

            // Sin el módulo no hay tabla, y el `whereHas` la tumbaría con un 1146.
            if (MarketplaceOrder::moduleInstalled()) {
                $q->orWhereHas('marketplaceOrder', fn ($m) => $m);
            }
        };

        return $grupo === self::GRUPO_EXTERNO
            ? $query->where($externo)
            : $query->whereNot($externo);
    }
}
