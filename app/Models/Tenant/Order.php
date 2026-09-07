<?php

    namespace App\Models\Tenant;


    use Illuminate\Database\Eloquent\SoftDeletes;
    use App\Models\Tenant\Document;


    class Order extends ModelTenant
    {
    use \App\Traits\HasAmountDuePayments;

        use SoftDeletes;

        protected $fillable = [
            'external_id',
            'person_id',
            'customer',
            'shipping_address',
            'items',
            'total',
            'amount_due',
            'subtotal',
            'total_discount',
            'coupon_code',
            'discounts',
            'points_redeemed',
            'points_earned',
            'reference_payment',
            'document_external_id',
            'number_document',
            'status_order_id',
            'purchase',
            // Con qué se documenta el pedido cuando el operador lo corrige.
            // `purchase` NO se toca: es lo que el COMPRADOR pidió, y es la
            // única prueba de ello. Ver la migración add_billing_choice_to_orders.
            'billing_document_type_id',
            'billing_customer',
            'billing_set_by',
            'billing_set_at',
            'apply_restaurant',
            // Canal de venta
            'channel_id',
            'external_order_ref',   // Nro pedido en Saga/ML/Instagram
            'marketplace_notes',    // Notas/link del marketplace
            'warehouse_id',
            'seller_id',
            // L2 — Culqi pre-autorización
            'culqi_charge_id',
            'payment_status',
            // Fases del despacho ecommerce (ver migration add_warehouse_phase_timestamps_to_orders)
            'prepared_at',
            'dispatched_at',
            'delivered_at',
            // Fechas comerciales (ver migration add_business_dates_to_orders_table).
            // Nullable en pedidos históricos: no se inventan hacia atrás.
            'paid_at',
            'confirmed_at',
            'cancelled_at',
            // Shipping calculator
            'shipping_cost',
            'shipping_zone_id',
        ];

        protected $casts = [
            'customer' => 'array',
            'items' => 'array',
            'purchase' => 'array',
            'billing_customer' => 'array',
            'billing_set_at' => 'datetime',
            'discounts' => 'array',
            'prepared_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];

        // ── Estado del pago ───────────────────────────────────────────────────
        //
        // A-03 de la auditoria de Pedidos. Habia DOS fuentes de verdad para el
        // mismo hecho y cuatro vocabularios entre seis escritores. Decision:
        //
        //   `status_order_id`  → estado COMERCIAL del pago y del pedido. Es el
        //                        que manda: 1 = pago pendiente, 2 = verificado,
        //                        y de ahi en adelante la operacion. Lo usa todo
        //                        el ERP y es lo que ve el operador.
        //
        //   `payment_status`   → estado del cobro EN LA PASARELA, y nada mas.
        //                        NULL significa "este cobro no paso por una
        //                        pasarela" (efectivo, contra entrega, Saga —
        //                        donde Falabella cobra fuera del sistema), NO
        //                        significa "sin pagar": eso lo dice el estado
        //                        comercial.
        //
        // Por eso desaparecio el valor `paid`: lo escribian Saga y el dispatcher
        // del marketplace para decir "ya esta pagado", que es justo lo que le
        // toca decir a `status_order_id`. Nadie lo leia nunca.

        /** Cobro autorizado por la pasarela, pendiente de captura (Culqi). */
        public const PAYMENT_PENDING_CAPTURE = 'pending_capture';

        /** La pasarela cobro correctamente. */
        public const PAYMENT_CAPTURED = 'captured';

        /** La captura fallo: hay autorizacion pero no dinero. */
        public const PAYMENT_CAPTURE_FAILED = 'capture_failed';

        /**
         * Vocabulario CERRADO de `payment_status`. Cualquier otro valor es un
         * error de escritura, y filtrar por uno inexistente devuelve cero filas
         * sin decir por que — la misma trampa que vacio el panel entero (A-01).
         */
        public const PAYMENT_STATUSES = [
            self::PAYMENT_PENDING_CAPTURE,
            self::PAYMENT_CAPTURED,
            self::PAYMENT_CAPTURE_FAILED,
        ];

        /**
         * Crea el pedido descartando las columnas que la tabla `orders` de ESTE
         * tenant todavia no tenga.
         *
         * Los caminos que crean pedidos desde fuera del tenant (dispatchers del
         * marketplace, importacion de Saga) escribian antes con `insertGetId()`,
         * y el array nunca crecia. Al pasar a Eloquent, una columna reciente que
         * un tenant no haya migrado (`paid_at`, `payment_status`) convierte el
         * INSERT en un 1054 y se pierde una venta YA COBRADA por un desfase de
         * esquema. Preferimos grabar el pedido sin ese dato y dejar constancia.
         *
         * Las columnas se memorizan por base de datos: un mismo proceso crea
         * pedidos de varios tenants seguidos.
         */
        public static function crearTolerandoEsquemaViejo(array $payload): self
        {
            static $columnas = [];

            try {
                $schema = \Illuminate\Support\Facades\Schema::connection('tenant');
                $db     = $schema->getConnection()->getDatabaseName();

                if (!array_key_exists($db, $columnas)) {
                    $columnas[$db] = array_flip($schema->getColumnListing('orders'));
                }

                if ($faltan = array_keys(array_diff_key($payload, $columnas[$db]))) {
                    \Illuminate\Support\Facades\Log::warning(
                        'orders: el tenant no tiene estas columnas; el pedido se crea sin ellas',
                        ['database' => $db, 'columnas' => $faltan, 'accion' => 'falta tenancy:migrate']
                    );
                }

                $payload = array_intersect_key($payload, $columnas[$db]);
            } catch (\Throwable $e) {
                // Si no se puede leer el esquema, mejor intentar el insert
                // completo que abortar el pedido por una comprobacion auxiliar.
            }

            return static::create($payload);
        }

        public function status_order()
        {
            return $this->belongsTo(StatusOrder::class);
        }

        /**
         * Detalle logístico del pedido (Registro y Control de Envíos).
         *
         * El pedido es la entidad principal; `shipping_requests` deja de ser un
         * pedido paralelo y pasa a ser el detalle de entrega de ESTE pedido.
         *
         * Regla operativa: 1 pedido = 1 registro logístico. La columna todavía
         * NO lleva UNIQUE porque existen envíos históricos sin `order_id` y
         * podrían existir duplicados; hasta conciliarlos (`shipments:reconcile`)
         * se resuelve con `latestOfMany()`, que se queda con el más reciente en
         * vez de devolver una fila arbitraria.
         */
        public function shipment()
        {
            return $this->hasOne(ShippingRequest::class, 'order_id')->latestOfMany();
        }

        /**
         * El envío VIGENTE del pedido: ignora los anulados.
         *
         * Es el que manda para "¿este pedido ya tiene envío configurado?" —
         * anular un envío debe permitir volver a configurarlo sin arrastrar el
         * registro anulado, que se conserva por auditoría.
         */
        public function activeShipment()
        {
            return $this->hasOne(ShippingRequest::class, 'order_id')
                        ->ofMany(['id' => 'max'], fn($q) => $q->whereNull('cancelled_at'));
        }

        /** Todos los registros logísticos, incluidos los anulados (historial). */
        public function shipments()
        {
            return $this->hasMany(ShippingRequest::class, 'order_id');
        }

        public function sale_note()
        {
            return $this->hasOne(SaleNote::class);
        }

        /**
         * Comprobantes electrónicos del pedido (boleta, factura, notas).
         *
         * El enlace NO es directo: el pedido genera una nota de venta y ES ESA
         * la que se convierte en comprobante (`documents.sale_note_id`). Ese
         * grafo ya existía y es el que usa la pantalla de Notas de Venta; aquí
         * solo se le da nombre desde el pedido para no reconstruirlo a mano en
         * cada consulta.
         *
         * Ojo: un pedido de Saga puede tener comprobante SIN pasar por aquí —
         * `MarketplaceInvoiceService` lo enlaza por `orders.document_external_id`
         * porque necesita subir el PDF al portal en el mismo acto. Quien
         * pregunte «¿tiene comprobante?» debe mirar los dos caminos; eso lo
         * resuelve `OrderDocuments`.
         */
        public function documents()
        {
            return $this->hasManyThrough(
                Document::class,
                SaleNote::class,
                'order_id',      // sale_notes.order_id
                'sale_note_id',  // documents.sale_note_id
                'id',
                'id'
            );
        }

        /**
         * El comprobante enlazado directamente en el pedido.
         *
         * `orders.document_external_id` es el tercer camino por el que un
         * pedido puede acabar con boleta o factura, además de la nota de venta
         * y del enlace de marketplace. Lo escriben el flujo antiguo del panel
         * («Generar comprobante» al pasar a estado 2) y también
         * `MarketplaceInvoiceService`.
         *
         * Se enlaza por `external_id`, no por `id`: es la columna que el pedido
         * guarda, y es la misma que usan las rutas de impresión.
         */
        public function document()
        {
            return $this->belongsTo(Document::class, 'document_external_id', 'external_id');
        }

        /**
         * Guía de remisión del pedido, a través de su envío.
         *
         * La guía cuelga del registro logístico (`shipping_requests.dispatch_id`),
         * no del pedido: quien la emite es el panel de Envíos. Esta relación es
         * el atajo para responder «¿este despacho ya tiene guía?» desde Pedidos
         * sin encadenar dos consultas.
         *
         * Igual que el resto de relaciones logísticas, no usarla en tenants sin
         * el módulo instalado: comprobar `ShippingRequest::moduleInstalled()`
         * antes de precargarla o el eager loading revienta con un 1146.
         */
        public function dispatch()
        {
            return $this->hasOneThrough(
                Dispatch::class,
                ShippingRequest::class,
                'order_id',    // shipping_requests.order_id
                'id',          // dispatches.id
                'id',
                'dispatch_id'  // shipping_requests.dispatch_id
            );
        }

        public function payments()
        {
            return $this->hasMany(OrderPayment::class);
        }

        public function channel()
        {
            return $this->belongsTo(SalesChannel::class, 'channel_id');
        }

        public function marketplaceOrder()
        {
            return $this->hasOne(MarketplaceOrder::class, 'order_id');
        }

        public function warehouse()
        {
            return $this->belongsTo(\Modules\Inventory\Models\Warehouse::class, 'warehouse_id');
        }

        public function seller()
        {
            return $this->belongsTo(\App\Models\Tenant\User::class, 'seller_id');
        }

        // ── Scopes ────────────────────────────────────────────────────────────

        public function scopeByChannel($query, $channelId)
        {
            return $query->where('channel_id', $channelId);
        }

        public function scopeEcommerce($query)
        {
            return $query->whereHas('channel', fn($q) => $q->where('type', 'ecommerce'));
        }

        public function scopeNotCancelled($query)
        {
            return $query->where('status_order_id', '!=', 5);
        }

        public function reviews()
        {
            return $this->hasMany(\App\Models\Tenant\ProductReview::class);
        }

        /**
         * Retorna un standar de nomenclatura para el modelo
         *
         * @return array
         */
        public function getCollectionData()
        {
            $customer = $this->customer ?? [];

            $data = [
                'id' => $this->id,
                'external_id' => $this->external_id,
                'number_document' => $this->number_document,
                'order_id' => str_pad($this->id, 6, "0", STR_PAD_LEFT),
                'customer' => $customer['apellidos_y_nombres_o_razon_social'] ?? null,
                'customer_email' => $customer['correo_electronico'] ?? null,
                'customer_telefono' => $customer['telefono'] ?? null,
                'customer_direccion' => $customer['direccion'] ?? null,
                'items' => $this->items,
                'total' => $this->total,
                'reference_payment' => strtoupper($this->reference_payment ?? ''),
                'document_external_id' => $this->document_external_id,
                'created_at' => $this->created_at->format('Y-m-d'),
                'status_order_id' => $this->status_order_id,
                'purchase' => $this->purchase,
                'status_order_description' => $this->status_order->description ?? null,
                'points_earned'   => (float) $this->points_earned,
                'points_redeemed' => (float) $this->points_redeemed,
            ];

            return $data;
        }
    }
