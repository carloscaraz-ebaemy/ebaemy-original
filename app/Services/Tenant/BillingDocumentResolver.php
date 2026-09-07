<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Order;

/**
 * Con qué documento corresponde documentar un pedido, y qué falta para poder
 * emitirlo.
 *
 * ── El problema que resuelve ──────────────────────────────────────────────
 *
 * Hasta ahora el tipo de comprobante lo decidía el COMPRADOR en el checkout
 * (`orders.purchase.codigo_tipo_documento`) y nadie podía corregirlo después.
 * Eso falla en casos cotidianos: el cliente marcó «boleta» y luego dejó un RUC;
 * el pedido vino de un canal que no manda el dato; el pedido se cargó a mano.
 * El operador se encontraba con un comprobante equivocado y sin manera de
 * arreglarlo salvo anularlo con nota de crédito.
 *
 * ── Las reglas no son nuestras ────────────────────────────────────────────
 *
 * Las dos que mandan son de SUNAT y ya estaban implementadas —enterradas—
 * dentro de `MarketplaceInvoiceService`, que emite las boletas de Saga:
 *
 *   1. A un RUC no se le emite boleta: corresponde FACTURA.
 *   2. Una boleta sin identificar al comprador solo es válida por debajo de
 *      S/ 700. Por encima, SUNAT exige el documento.
 *
 * Aquí se declaran en un solo sitio para que las use también Pedidos. El
 * servicio de Saga sigue teniendo las suyas porque lanza excepciones en mitad
 * de una emisión; este responde con una propuesta y una lista de lo que falta,
 * que es lo que necesita una pantalla ANTES de emitir nada.
 *
 * ── Lo que NO hace ────────────────────────────────────────────────────────
 *
 * No emite, no guarda y no decide por el operador: propone. La última palabra
 * es de quien pulsa, y su elección se respeta salvo que sea imposible.
 */
class BillingDocumentResolver
{
    public const NOTA_VENTA = '80';
    public const BOLETA     = '03';
    public const FACTURA    = '01';

    /** Tope de una boleta sin comprador identificado (SUNAT). */
    public const TOPE_BOLETA_ANONIMA = 700;

    public const NOMBRES = [
        self::NOTA_VENTA => 'Nota de venta',
        self::BOLETA     => 'Boleta',
        self::FACTURA    => 'Factura',
    ];

    /** Tipos que el operador puede elegir a mano. */
    public const ELEGIBLES = [self::NOTA_VENTA, self::BOLETA, self::FACTURA];

    /**
     * Qué corresponde emitir y por qué.
     *
     * @return array{
     *   tipo:string, nombre:string, origen:string, motivo:string,
     *   faltan:array, puede_emitir:bool, documento:string, nombre_cliente:string,
     *   elegido_por_operador:bool
     * }
     */
    public function resolve(Order $order): array
    {
        $documento = $this->documento($order);
        $elegido   = $this->eleccionDelOperador($order);

        [$tipo, $origen, $motivo] = $this->decidir($order, $documento, $elegido);

        $faltan = $this->faltanDatos($order, $tipo, $documento);

        return [
            'tipo'                 => $tipo,
            'nombre'               => self::NOMBRES[$tipo],
            'origen'               => $origen,
            'motivo'               => $motivo,
            'faltan'               => $faltan,
            'puede_emitir'         => empty($faltan),
            'documento'            => $documento,
            'nombre_cliente'       => $this->nombreCliente($order),
            'elegido_por_operador' => $elegido !== null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // La decisión
    // ══════════════════════════════════════════════════════════════════════

    /**
     * @return array{0:string,1:string,2:string} tipo, origen, motivo
     */
    private function decidir(Order $order, string $documento, ?string $elegido): array
    {
        $esRuc = strlen($documento) === 11;

        // 1. Un RUC manda sobre la boleta, incluso si alguien eligió boleta.
        //    No es una preferencia del sistema: emitir una boleta a un RUC deja
        //    al cliente sin el comprobante que necesita y obliga a anularla.
        //    La nota de venta SÍ se respeta: es interna y no va a SUNAT.
        if ($esRuc && $elegido !== self::NOTA_VENTA) {
            return [
                self::FACTURA,
                $elegido === self::FACTURA ? 'operador' : 'ruc',
                'El cliente tiene RUC (' . $documento . '): a un RUC le corresponde factura.',
            ];
        }

        // 2. La elección explícita del operador, cuando es posible.
        if ($elegido !== null) {
            return [
                $elegido,
                'operador',
                'Lo eligió el operador.',
            ];
        }

        // 3. Lo que el comprador pidió en el checkout.
        $delCheckout = $this->eleccionDelComprador($order);
        if ($delCheckout !== null) {
            return [
                $delCheckout,
                'checkout',
                'Es lo que el cliente pidió al comprar.',
            ];
        }

        // 4. Por defecto, boleta: es el comprobante de una venta a consumidor
        //    final, que es la mayoría. Con RUC ya se habría ido por la rama 1.
        return [
            self::BOLETA,
            'defecto',
            $documento !== ''
                ? 'El cliente tiene documento de persona natural.'
                : 'El pedido no indica tipo de comprobante.',
        ];
    }

    /**
     * Qué datos tributarios faltan para emitir ese tipo.
     *
     * Devuelve frases, no códigos: el destinatario es el operador, y lo único
     * que le sirve de un «no se puede» es saber qué completar.
     */
    public function faltanDatos(Order $order, string $tipo, ?string $documento = null): array
    {
        $documento = $documento ?? $this->documento($order);
        $faltan    = [];

        if ($tipo === self::FACTURA) {
            if (strlen($documento) !== 11) {
                $faltan[] = $documento === ''
                    ? 'el RUC del cliente'
                    : 'un RUC de 11 dígitos (el cliente tiene «' . $documento . '»)';
            }

            if (trim($this->nombreCliente($order)) === '') {
                $faltan[] = 'la razón social';
            }

            return $faltan;
        }

        if ($tipo === self::BOLETA) {
            // Sin documento la boleta sale como Cliente Final 00000000, que es
            // legal solo por debajo del tope.
            if ($documento === '' && (float) $order->total > self::TOPE_BOLETA_ANONIMA) {
                $faltan[] = 'el DNI del cliente (la boleta supera S/ '
                          . self::TOPE_BOLETA_ANONIMA . ' y SUNAT exige identificarlo)';
            }

            return $faltan;
        }

        // La nota de venta es interna: no le falta nada ante SUNAT.
        return [];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Datos efectivos del cliente
    // ══════════════════════════════════════════════════════════════════════

    /**
     * El documento que se usará al emitir.
     *
     * La corrección del operador (`billing_customer`) pisa a la foto del
     * checkout (`orders.customer`). Ese es el sentido de la corrección: el
     * operador vio que el dato estaba mal.
     */
    public function documento(Order $order): string
    {
        $corregido = $this->arreglo($order->billing_customer);
        $doc = $corregido['numero'] ?? $corregido['number'] ?? null;

        if (!$doc) {
            $cliente = $this->arreglo($order->customer);
            // El checkout, Saga y el alta manual no usan la misma clave.
            $doc = $cliente['numero']
                ?? $cliente['number']
                ?? $cliente['numero_documento']
                ?? '';
        }

        return preg_replace('/\D+/', '', (string) $doc);
    }

    /** Nombre o razón social que irá en el comprobante. */
    public function nombreCliente(Order $order): string
    {
        $corregido = $this->arreglo($order->billing_customer);
        $nombre = $corregido['nombre']
            ?? $corregido['apellidos_y_nombres_o_razon_social']
            ?? null;

        if (!$nombre) {
            $cliente = $this->arreglo($order->customer);
            $nombre = $cliente['apellidos_y_nombres_o_razon_social']
                ?? $cliente['nombre']
                ?? $cliente['name']
                ?? '';
        }

        return trim((string) $nombre);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Las dos elecciones previas
    // ══════════════════════════════════════════════════════════════════════

    private function eleccionDelOperador(Order $order): ?string
    {
        $tipo = $order->billing_document_type_id;

        return in_array($tipo, self::ELEGIBLES, true) ? $tipo : null;
    }

    /**
     * Lo que el comprador marcó en el checkout.
     *
     * Vive en `orders.purchase`, que es un JSON del ecommerce. Un valor que no
     * reconocemos se ignora en vez de propagarse: preferimos caer al criterio
     * por defecto antes que proponer un tipo que no sabemos emitir.
     */
    private function eleccionDelComprador(Order $order): ?string
    {
        $purchase = $this->arreglo($order->purchase);
        $tipo = (string) ($purchase['codigo_tipo_documento'] ?? '');

        return in_array($tipo, self::ELEGIBLES, true) ? $tipo : null;
    }

    private function arreglo($valor): array
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }
        if (is_object($valor)) {
            $valor = (array) $valor;
        }

        return is_array($valor) ? $valor : [];
    }
}
