<?php

namespace App\Services\Tenant;

use App\Models\Tenant\PaymentMethodType;

/**
 * ¿Este cobro necesita código de operación?
 *
 * El código de operación identifica un movimiento BANCARIO: el número que da
 * el banco al hacer una transferencia o un depósito. Es lo que permite
 * comprobar que el dinero entró y detectar el mismo voucher cargado dos veces.
 *
 * Un cobro en efectivo que entra a CAJA GENERAL no tiene ese número, porque no
 * pasó por ningún banco. Hasta ahora el módulo de envíos lo exigía igual
 * (`payment_code => required`) y no había forma de registrar una venta al
 * contado: el operador terminaba inventando un código, que es peor que no
 * tener ninguno —ensucia la detección de duplicados con basura—.
 *
 * ── Por qué una clase y no un `if` en cada sitio ─────────────────────────
 *
 * Hay CUATRO puntos que deciden lo mismo: la validación de
 * `ShipmentController::storePayment`, el adaptador `ShipmentPaymentController`,
 * el trait `ManagesRecordPayments` (pagos de Pedido y de Nota de Pedido) y las
 * dos pantallas (el panel Vue y el modal Blade de Envíos). Si cada uno
 * decidiera por su cuenta, el formulario y el servidor acabarían discrepando y
 * el operador vería un campo opcional que al guardar rebota.
 *
 * Las pantallas tampoco replican la regla: `describe()` la adjunta a cada
 * método del catálogo (`requires_reference`) y el front solo lee esa bandera.
 *
 * ── La regla ─────────────────────────────────────────────────────────────
 *
 *   Transferencia / depósito / abono en cuenta  → OBLIGATORIO
 *   Efectivo, Yape, Plin, tarjeta, contado…     → opcional
 *   Sin método elegido                          → opcional
 *
 * Yape y Plin quedan opcionales a propósito: tienen código, pero no siempre a
 * la vista del que cobra, y exigirlo repetiría el problema con otro método.
 * Si se carga, se guarda y entra en la detección de duplicados igual.
 *
 * El catálogo `payment_method_types` lo edita cada tienda (Finanzas → M. de
 * Pago), así que la regla NO puede depender solo del id: se mira la
 * descripción y el id sirve de respaldo para el catálogo de fábrica.
 */
class PaymentReferenceRule
{
    /** Ids del catálogo de fábrica que son operación bancaria. */
    public const IDS_OBLIGATORIO = ['04'];

    /** Palabras que delatan un movimiento bancario en la descripción. */
    private const PALABRAS_OBLIGATORIO = [
        'transferencia', 'transferencias', 'transfer',
        'deposito', 'depositos',
        'abono en cuenta', 'abono cuenta',
        'interbancaria', 'interbancario',
    ];

    /**
     * Siglas que solo valen como palabra suelta.
     *
     * «cci» suelto es una cuenta interbancaria, pero como trozo aparece dentro
     * de palabras corrientes («transacción», «fraccionado») y convertiria en
     * obligatorio un método que no lo es.
     */
    private const SIGLAS_OBLIGATORIO = ['cci'];

    /**
     * Palabras que mandan sobre las de arriba.
     *
     * Existen porque una tienda puede llamar a un método «Efectivo o depósito»
     * o «Yape / transferencia»: ahí lo que decide es que el operador puede
     * estar cobrando en mano, y exigirle el código lo dejaría trabado.
     */
    private const PALABRAS_OPCIONAL = [
        'efectivo', 'caja', 'contado', 'contra entrega', 'contraentrega',
        'yape', 'plin', 'credito', 'crédito',
    ];

    /** Mensaje único, para que el front y el back digan lo mismo. */
    public const MENSAJE = 'El código de operación es obligatorio para pagos mediante transferencia.';

    /**
     * Deja el código listo para guardar: sin espacios y, si queda vacío, null.
     *
     * Vacío, null y «   » son el mismo hecho —no hay código—, y guardarlos
     * distinto rompe la búsqueda de duplicados y hace que un pago «tenga»
     * código sin tenerlo.
     */
    public static function normalizar($codigo): ?string
    {
        $codigo = trim((string) ($codigo ?? ''));

        return $codigo === '' ? null : $codigo;
    }

    /** ¿Falta el código? (null, cadena vacía o solo espacios son lo mismo.) */
    public static function vacio($codigo): bool
    {
        return static::normalizar($codigo) === null;
    }

    /** ¿El método de pago elegido exige código de operación? */
    public static function requiere($paymentMethodTypeId): bool
    {
        $id = trim((string) ($paymentMethodTypeId ?? ''));

        if ($id === '') {
            return false;   // sin método declarado no se puede exigir nada
        }

        $descripcion = static::descripcion($id);

        if ($descripcion !== null) {
            return static::descripcionRequiere($descripcion);
        }

        // El método ya no está en el catálogo (se borró, o el pago es viejo).
        return in_array($id, static::IDS_OBLIGATORIO, true);
    }

    /**
     * ¿Hay que rechazar este cobro por falta de código?
     *
     * Es la pregunta que hacen los controladores: junta «el método lo exige» y
     * «el campo vino vacío» para que ninguno de los dos se olvide de la otra
     * mitad.
     */
    public static function falta($paymentMethodTypeId, $codigo): bool
    {
        return static::requiere($paymentMethodTypeId) && static::vacio($codigo);
    }

    /**
     * El catálogo tal cual lo necesita un formulario: cada método con su
     * bandera `requires_reference`.
     *
     * Así la pantalla no reimplementa la regla; solo lee el dato que ya viene
     * resuelto por el servidor.
     */
    public static function catalogo()
    {
        return PaymentMethodType::all()->map(function ($metodo) {
            $metodo->requires_reference = static::descripcionRequiere((string) $metodo->description)
                || (static::descripcion((string) $metodo->id) === null
                    && in_array((string) $metodo->id, static::IDS_OBLIGATORIO, true));

            return $metodo;
        });
    }

    /** Descripción del método, o null si ya no existe en el catálogo. */
    private static function descripcion(string $id): ?string
    {
        static $cache = null;

        if ($cache === null) {
            $cache = PaymentMethodType::all()->pluck('description', 'id');
        }

        $descripcion = $cache->get($id);

        return $descripcion === null ? null : (string) $descripcion;
    }

    /**
     * Decide sobre el texto del método, sin acentos ni mayúsculas.
     *
     * Es pública a propósito: es la única parte de la regla que no toca la base
     * de datos, y así se puede probar el catálogo entero —el de fábrica y el
     * que invente cada tienda— sin levantar una conexión de tenant.
     */
    public static function descripcionRequiere(string $descripcion): bool
    {
        $texto = static::plano($descripcion);

        if ($texto === '') {
            return false;
        }

        foreach (static::PALABRAS_OPCIONAL as $palabra) {
            if (str_contains($texto, static::plano($palabra))) {
                return false;
            }
        }

        foreach (static::PALABRAS_OBLIGATORIO as $palabra) {
            if (str_contains($texto, static::plano($palabra))) {
                return true;
            }
        }

        foreach (static::SIGLAS_OBLIGATORIO as $sigla) {
            if (preg_match('/\b' . preg_quote($sigla, '/') . '\b/', $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Minúsculas y sin acentos: «Depósito» y «deposito» son lo mismo. */
    private static function plano(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');

        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}
