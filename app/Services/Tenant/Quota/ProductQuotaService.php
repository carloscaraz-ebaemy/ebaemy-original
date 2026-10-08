<?php

namespace App\Services\Tenant\Quota;

use App\Models\Tenant\Item;
use App\Services\FeatureGate;
use Illuminate\Support\Facades\DB;

/**
 * Cupo de productos del catálogo según el plan del tenant.
 *
 * Fuente de verdad ÚNICA: todo punto capaz de dar de alta un producto —el
 * formulario, los importadores de Excel, el duplicado, la API móvil y la
 * importación de Saga— pregunta aquí. Ningún controlador vuelve a escribir un
 * número ni una frase.
 *
 * Multi-tenant: `Item` vive en la BD del tenant y el plan se resuelve por el
 * hostname de la petición, así que el cupo de un tenant es físicamente
 * incapaz de contar los productos de otro.
 */
class ProductQuotaService
{
    public const FEATURE = 'products_limit';

    public function __construct(private readonly FeatureGate $features) {}

    /**
     * Productos que ocupan cupo.
     *
     * Se cuentan los ACTIVOS. Las decisiones detrás de este WHERE:
     *
     * - `active = 1` y nada más: `apply_store` y `marketplace_publishable` son
     *   canales de venta, no existencia. Un producto retirado de la tienda
     *   sigue estando en el catálogo y sigue ocupando su sitio.
     * - Las variantes NO cuentan: una camiseta con cinco tallas es un
     *   producto, no cinco. El cupo se mide en fichas de catálogo.
     * - No hay borrado lógico que valga: `items` no tiene `deleted_at` y
     *   `ItemController@destroy` borra de verdad, así que eliminar un producto
     *   libera su cupo sin que haya que hacer nada.
     */
    public function currentCount(): int
    {
        return Item::where('active', 1)->count();
    }

    /** null = ilimitado · 0 = el plan no incluye catálogo. */
    public function limit(): ?int
    {
        return $this->features->limit(self::FEATURE);
    }

    /**
     * ¿Caben `$extra` productos más?
     *
     * Sólo mira la creación. Editar un producto existente no pasa por aquí a
     * propósito: un tenant en 10/10 tiene que poder corregir un precio, y un
     * tenant que bajó de plan con 50 productos y tope 10 tiene que poder
     * seguir vendiéndolos. Lo que se bloquea es el 11, no el trabajo diario.
     */
    public function check(int $extra = 1): QuotaResult
    {
        $limit = $this->limit();

        // Sin límite configurado el catálogo es ilimitado. Ningún plan actual
        // tiene el catálogo capado salvo Gratis, y un plan sin la fila no
        // puede quedarse sin poder crear productos: sería dejar al tenant sin
        // sistema.
        if ($limit === null || $limit === 0) {
            return QuotaResult::unlimited($this->currentCount());
        }

        $current   = $this->currentCount();
        $remaining = max(0, $limit - $current);

        if ($current + $extra > $limit) {
            return new QuotaResult(
                allowed:   false,
                limit:     $limit,
                current:   $current,
                remaining: $remaining,
                message:   $this->message($current, $limit, $extra, $remaining),
            );
        }

        return new QuotaResult(true, $limit, $current, $remaining);
    }

    /**
     * Reserva el cupo y ejecuta el alta dentro de la misma transacción.
     *
     * El `lockForUpdate` sobre el conteo es lo que impide que dos altas
     * simultáneas se cuelen las dos viendo 9/10. La segunda espera, vuelve a
     * contar y encuentra 10.
     *
     * @throws QuotaExceededException si no cabe
     */
    public function consume(int $extra, callable $callback)
    {
        return DB::connection('tenant')->transaction(function () use ($extra, $callback) {
            $limit = $this->limit();

            if ($limit !== null && $limit !== 0) {
                $current = Item::where('active', 1)->lockForUpdate()->count();

                if ($current + $extra > $limit) {
                    throw new QuotaExceededException(
                        $this->message($current, $limit, $extra, max(0, $limit - $current)),
                        new QuotaResult(false, $limit, $current, max(0, $limit - $current))
                    );
                }
            }

            return $callback();
        });
    }

    /** El texto que ve el comerciante. Escrito una sola vez, aquí. */
    private function message(int $current, int $limit, int $extra, int $remaining): string
    {
        $plan = $this->features->planName() ?? 'tu plan';

        if ($extra > 1) {
            return "Has utilizado {$current} de {$limit} productos de {$plan}. "
                 . ($remaining > 0
                    ? "Sólo te quedan {$remaining} y este archivo trae {$extra}."
                    : 'No te queda ninguno disponible.');
        }

        return "Has utilizado {$current} de {$limit} productos disponibles en {$plan}.";
    }
}
