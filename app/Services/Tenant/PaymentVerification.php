<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingSetting;

/**
 * Verificación de un cobro: registrar no es lo mismo que comprobar.
 *
 * ── El problema ───────────────────────────────────────────────────────────
 *
 * Hasta ahora los dos hechos estaban confundidos. En Envíos, el botón se llama
 * «Confirmar pago» y lo que abre es el registro de cobros. En Pedidos no había
 * verificación de ninguna clase, y el único estado del pago era la etiqueta
 * «Pago verificado» del catálogo comercial, que se pone a mano y no mira el
 * dinero.
 *
 * ── Un solo sitio para la regla ───────────────────────────────────────────
 *
 * Hay DOS puntos de escritura de cobros —`ManagesRecordPayments::store()` para
 * los pedidos y `ShipmentController::storePayment()` para los envíos— y no
 * comparten código. Lo que sí comparten, desde aquí, es la decisión de con qué
 * estado nace un cobro y qué transiciones son legales. Sin esto, encender la
 * verificación en un módulo y olvidarla en el otro sería cuestión de tiempo.
 *
 * ── Configurable, como pidió el negocio ───────────────────────────────────
 *
 * Se gobierna con `require_payment_verification`, junto a `require_payment` y
 * `require_payment_code` en la configuración de tienda. Apagado, los cobros
 * nacen sin estado y nada cambia para quien no la use: encenderla añade un paso
 * al trabajo diario y esa es una decisión de cada tienda, no del sistema.
 */
class PaymentVerification
{
    public const PENDIENTE  = 'pendiente';
    public const VERIFICADO = 'verificado';
    public const RECHAZADO  = 'rechazado';

    /** Vocabulario cerrado. Cualquier otro valor es un error de escritura. */
    public const ESTADOS = [self::PENDIENTE, self::VERIFICADO, self::RECHAZADO];

    public const ETIQUETAS = [
        self::PENDIENTE  => 'Por verificar',
        self::VERIFICADO => 'Verificado',
        self::RECHAZADO  => 'Rechazado',
    ];

    /**
     * ¿Este tenant exige verificar los cobros?
     *
     * `currentOrNull` y no `current`: `current()` hace `firstOrCreate` y
     * ESCRIBE, y esto se consulta desde el listado, que es un camino de solo
     * lectura que ningún tenant puede permitirse que falle.
     */
    public static function requerida(): bool
    {
        $cfg = ShippingSetting::currentOrNull();

        return (bool) ($cfg->require_payment_verification ?? false);
    }

    /**
     * Con qué estado nace un cobro nuevo.
     *
     * NULL cuando el tenant no verifica: es distinto de «pendiente». Un cobro
     * sin estado no está esperando a nadie; uno «pendiente» sí, y aparece como
     * trabajo atrasado en el panel de quien tiene que revisarlo.
     */
    public static function estadoInicial(): ?string
    {
        return static::requerida() ? self::PENDIENTE : null;
    }

    /**
     * ¿Este cobro cuenta como dinero bueno?
     *
     * Un cobro RECHAZADO no cuenta: se registró y resultó no ser válido, así
     * que el pedido sigue debiendo. Los demás sí — incluidos los que están por
     * verificar y los anteriores a que existiera la verificación, porque el
     * dinero entró aunque nadie lo haya mirado todavía.
     *
     * Es la regla que decide si el saldo baja, y por eso vive aquí y no en cada
     * pantalla: si el listado y el panel de pagos la interpretaran distinto, el
     * mismo pedido mostraría dos saldos.
     */
    public static function cuenta(?string $estado): bool
    {
        return $estado !== self::RECHAZADO;
    }

    /**
     * ¿La tabla de cobros de este tenant ya tiene la verificación?
     *
     * Memoizado: se pregunta en cada listado y `hasColumn` consulta el
     * information_schema. La clave lleva la base del TENANT — la del sistema es
     * la misma para todos y un solo `false` se reutilizaría para el sistema
     * entero, que es exactamente el fallo que dejó muerta la columna Cobro.
     */
    public static function instalada(string $tabla): bool
    {
        static $memo = [];

        $key = \Illuminate\Support\Facades\DB::connection('tenant')->getDatabaseName() . '|' . $tabla;

        return $memo[$key] ??= \Illuminate\Support\Facades\Schema::connection('tenant')
            ->hasColumn($tabla, 'verification_status');
    }

    /**
     * Deja fuera los cobros rechazados.
     *
     * Se aplica a TODA suma de dinero. Un cobro rechazado se registró y resultó
     * no ser válido: si siguiera sumando, rechazarlo no cambiaría el saldo y el
     * botón no serviría para nada.
     *
     * En un tenant sin la columna no filtra: no hay rechazados que quitar, y
     * nombrarla sería un 1054 en el listado entero.
     */
    public static function soloValidos($query, string $tabla)
    {
        if (!static::instalada($tabla)) {
            return $query;
        }

        return $query->where(function ($q) {
            $q->whereNull('verification_status')
              ->orWhere('verification_status', '!=', self::RECHAZADO);
        });
    }

    /** El mismo filtro, en SQL crudo, para las subconsultas del listado. */
    public static function sqlSoloValidos(string $alias, string $tabla): string
    {
        if (!static::instalada($tabla)) {
            return '1 = 1';
        }

        return "({$alias}.verification_status IS NULL"
             . " OR {$alias}.verification_status <> '" . self::RECHAZADO . "')";
    }

    /**
     * Por qué NO se puede pasar a este estado, o null si se puede.
     *
     * Se devuelve texto y no un booleano porque el destinatario es el operador:
     * lo único útil de un «no se puede» es saber qué pasó.
     */
    public static function motivoBloqueo(?string $actual, string $destino): ?string
    {
        if (!in_array($destino, [self::VERIFICADO, self::RECHAZADO], true)) {
            return 'Estado de verificación desconocido.';
        }

        if ($actual === $destino) {
            return 'El cobro ya está ' . mb_strtolower(self::ETIQUETAS[$destino]) . '.';
        }

        // Un cobro rechazado no se «des-rechaza»: si el dinero sí entró, se
        // registra el cobro otra vez y queda la constancia de los dos.
        if ($actual === self::RECHAZADO) {
            return 'Este cobro fue rechazado. Si el pago sí entró, regístralo de nuevo '
                 . 'en vez de revertir el rechazo: así queda constancia de lo que pasó.';
        }

        return null;
    }
}
