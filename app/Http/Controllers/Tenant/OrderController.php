<?php
namespace App\Http\Controllers\Tenant;

use Exception;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use App\Models\Tenant\Series;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\ItemWarehouse;

use App\Http\Resources\Tenant\OrderCollection;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Http\Resources\Tenant\ItemWarehouseCollection;
use Modules\Inventory\Models\Warehouse as ModuleWarehouse;
use App\Models\Tenant\Item;
use App\Models\Tenant\SalesChannel;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Services\Tenant\OrderService;
use App\Services\Tenant\OrderDocuments;
use App\Services\Tenant\BillingDocumentResolver;
use App\Services\Tenant\PaymentVerification;
use App\Models\Tenant\OrderStatusLog;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\CardBrand;
use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\ShippingRequest;
use App\Models\Tenant\ShippingSetting;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Traits\FinanceTrait;

class OrderController extends Controller
{

  use StorageDocument;
  use FinanceTrait;

  protected $company;

    /**
     * Gestión de Pedidos.
     *
     * ── QUÉ ES UN PEDIDO AQUÍ (perímetro del módulo, decidido el 2026-09-01) ──
     *
     * Pedidos es **la venta que requiere entrega**. Todo lo que entra tiene algo
     * pendiente de hacer: preparar, imprimir el rótulo, embalar, despachar,
     * entregar. Por eso conviven en la misma pantalla el pedido del ecommerce,
     * el del marketplace, el de Saga y el encargo logístico suelto — que no
     * lleva productos ni importe, pero sí trabajo.
     *
     * Lo que NO entra: la venta de mostrador. Un ticket de POS ya está entregado
     * en el momento en que ocurre, así que meterlo aquí llenaría la cola de
     * trabajo con filas que nacen terminadas. Vive en `documents` / `sale_notes`
     * y no genera `Order`. El canal `POS01` existe en el catálogo pero se
     * desactiva cuando no tiene pedidos, para no ofrecer en el filtro un origen
     * que nunca va a producir ninguno.
     *
     * Si algún día el mostrador necesita reservar o entregar más tarde, ESE caso
     * sí es un pedido y debe crear su `Order` — pero entonces ya no es una venta
     * de mostrador, es una venta con entrega.
     */
    public function index()
    {
        return view('tenant.orders.index');
    }

    /**
     * Opciones del buscador de la tabla.
     *
     * `search` va PRIMERA a propósito: el DataTable toma la primera clave como
     * criterio por defecto, así que al abrir Pedidos se busca en todo. Antes el
     * default era el código, y para encontrar a un cliente que llama por
     * teléfono había que saber de antemano su número de pedido.
     */
    public function columns()
    {
        return [
            'search'          => 'Buscar en todo (cliente, DNI, teléfono, envío, tracking)',
            'id'              => 'Codigo de Pedido',
            'number_document' => 'Comprobante Electronico',
        ];
    }

    public function tables()
    {
      $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
      $series = collect(Series::all())->transform(function($row) {
          return [
              'id' => $row->id,
              'contingency' => (bool) $row->contingency,
              'document_type_id' => $row->document_type_id,
              'establishment_id' => $row->establishment_id,
              'number' => $row->number
          ];
      });

      $document_types = DocumentType::all();

      return compact('series', 'establishments', 'document_types');

    }

    public function item($internal_id)
    {
        $establishment_id = auth()->user()->establishment_id;
        $warehouse = ModuleWarehouse::where('establishment_id', $establishment_id)->first();

        $row = Item::where('internal_id', $internal_id)->first();

        if (!$row) {
            return response()->json(['error' => 'Producto no encontrado: ' . $internal_id], 404);
        }

        $warehouseId = $warehouse ? $warehouse->id : null;

        return [
            'id' => $row->id,
            'description' => $row->description,
            'sale_unit_price' => round($row->sale_unit_price, 2),
            'lots' => $row->item_lots->where('has_sale', false)->when($warehouseId, fn($c) => $c->where('warehouse_id', $warehouseId))->transform(function($row) {
                return [
                    'id' => $row->id,
                    'series' => $row->series,
                    'date' => $row->date,
                    'item_id' => $row->item_id,
                    'warehouse_id' => $row->warehouse_id,
                    'has_sale' => (bool)$row->has_sale,
                    'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                ];
            })->values(),
            'series_enabled' => (bool) $row->series_enabled,
            'warehouse_id'   => $warehouseId,
        ];
    }

    public function records(Request $request)
    {
        $query = $this->buildOrdersQuery($request);

        return new OrderCollection($query->paginate(config('tenant.items_per_page')));
    }

    /**
     * UNA fila, con la misma forma que el listado.
     *
     * Existe para no recargar la tabla entera cuando cambia un solo pedido.
     * Hoy registrar un cobro dispara tres peticiones —filas, chips y KPI— y el
     * operador pierde el scroll; con esto se sustituye la fila y ya.
     *
     * NO confundir con `record()`, que devuelve el pedido normalizado para el
     * FORMULARIO de edicion: otra forma, otro consumidor. Aqui se reutiliza
     * `OrderCollection` a proposito, porque la fila tiene que salir exactamente
     * igual que salio la primera vez — con sus documentos, su estado economico
     * y su bloque logistico resueltos por el mismo codigo.
     *
     * Se pasa por `buildOrdersQuery` en vez de por `Order::find` para heredar
     * las precargas: sin ellas `OrderDocuments` responde «no hay documentos» y
     * la fila volveria con los chips vacios.
     */
    public function row(Request $request, Order $order)
    {
        $query = $this->buildOrdersQuery($request, true, false)
                      ->reorder()
                      ->where('orders.id', $order->id);

        $fila = $query->first();

        if (!$fila) {
            return response()->json(['success' => false, 'message' => 'El pedido no existe.'], 404);
        }

        // El recurso es una COLECCION: se le pasa una de un elemento y se saca
        // la fila ya transformada.
        $datos = (new OrderCollection(collect([$fila])))->toArray($request);

        return response()->json(['success' => true, 'data' => $datos[0] ?? null]);
    }

    /**
     * Consulta ÚNICA de Gestión de Pedidos: comercial + logística.
     *
     * Unifica lo que antes vivía en dos pantallas — los filtros de
     * `OrderController::records()` y los de `ShipmentController::buildListQuery()`.
     * La comparten records(), statusCounts() y stats() para que el número del
     * chip y las filas de la tabla no puedan discrepar.
     */
    /**
     * Memoizado por conexion: `Schema::hasTable` consulta el information_schema
     * y esto se llama una vez por request del listado, los chips y las stats.
     */
    private function orderPaymentsTableExists(): bool
    {
        static $cache = [];

        // `Schema::hasTable()` A SECAS pregunta por la conexion POR DEFECTO,
        // que es `mysql` -> la base del SISTEMA (`ebaemy`). Ahi no existe
        // `order_payments`: es una tabla de tenant. Asi que esto devolvia
        // SIEMPRE false, en todos los tenants, y el `withSum` de mas abajo no
        // se aplicaba nunca.
        //
        // Consecuencia: `paid_total` valia 0 en cada fila del listado. La
        // columna Cobro no ha mostrado jamas ni «pagado» ni el saldo de un
        // pedido propio, y el unico pago registrado del sistema —el del pedido
        // 27 de alasitas, S/ 313.50 sobre un total de S/ 313.50— salia como si
        // no existiera. Se descubrio al comparar el estado economico calculado
        // en PHP contra el mismo estado calculado en SQL: cuadraban en tres
        // tenants y discrepaban en ese unico pedido.
        //
        // La clave del memo tenia el mismo fallo: la base por defecto es la
        // misma para todos los tenants, asi que un solo `false` se reutilizaba
        // para el sistema entero.
        $conexion = \Illuminate\Support\Facades\DB::connection('tenant');
        $key      = $conexion->getDatabaseName();

        return $cache[$key] ??= \Illuminate\Support\Facades\Schema::connection('tenant')
            ->hasTable('order_payments');
    }

    private function buildOrdersQuery(Request $request, bool $withRelations = true, bool $withChip = true)
    {
        $query = Order::query()->latest();

        if ($withRelations) {
            // Cobrado del pedido, agregado en la MISMA consulta. Sin esto la
            // tabla solo sabia el total y nunca cuanto se habia cobrado: los
            // pagos se registraban en `order_payments` y no volvian a la
            // pantalla. Un accesor por fila serian 20 consultas por pagina.
            //
            // La guarda de tabla sigue el mismo criterio que
            // `ShippingRequest::moduleInstalled()`: hay tenants cuyo
            // `tenancy:migrate` puede ir atrasado, y un listado que revienta
            // con "table doesn't exist" es peor que un listado sin el dato.
            if ($this->orderPaymentsTableExists()) {
                // Un cobro RECHAZADO no suma: se registro y resulto no ser
                // valido, asi que el pedido sigue debiendo. Si siguiera
                // contando, rechazarlo no cambiaria el saldo.
                $query->withSum([
                    'payments as paid_total' => fn ($q) => PaymentVerification::soloValidos($q, 'order_payments'),
                ], 'payment');
            }

            $query->with([
                'channel',
                // Estas tres las consumía OrderCollection SIN precargar: eran
                // 3 consultas extra por fila (60 por página de 20).
                'status_order:id,description',
                // Sin recorte de columnas: `number_full` es un accesor que se
                // arma con varias de ellas y un select parcial lo dejaría vacío.
                'sale_note',
                // Los comprobantes cuelgan de la NV, no del pedido. Sin esta
                // precarga, `OrderDocuments` responde «no hay boleta» para un
                // pedido que sí la tiene: el servicio no dispara consultas a
                // propósito, para no meter 20 por página.
                'sale_note.documents:id,sale_note_id,document_type_id,state_type_id,series,number,external_id,date_of_issue,response_regularize_shipping',
                // Tercer camino al comprobante: el pedido lo enlaza en crudo
                // por `document_external_id`. Ver `Order::document()`.
                'document:id,document_type_id,state_type_id,series,number,external_id,date_of_issue,response_regularize_shipping',
                'warehouse:id,description',
            ]);

            // Los datos de Saga son la fuente de verdad para cliente y entrega.
            // Sin estos campos el recurso solo podia mostrar los fallbacks del
            // pedido ERP (por ejemplo, la direccion literal "Marketplace").
            // Condicionado igual que los envíos: sin la tabla, el eager loading
            // tumbaría la pantalla entera con un 1146.
            if (MarketplaceOrder::moduleInstalled()) {
                $query->with([
                    'marketplaceOrder:id,order_id,channel_id,external_order_id,status,customer_data,shipping_data,invoice_uploaded_at,document_id',
                    'marketplaceOrder.channel:id,platform,name',
                    // La boleta de Saga NO cuelga de la nota de venta: se
                    // enlaza aquí. Sin precargarla, un pedido ya facturado
                    // aparecería como «sin comprobante» y se podría emitir dos
                    // veces. Ver `OrderDocuments::comprobante()`.
                    'marketplaceOrder.document:id,document_type_id,state_type_id,series,number,external_id,date_of_issue,response_regularize_shipping',
                ]);
            }

            // Detalle logístico. Eager loading obligatorio: sin esto la columna
            // "Entrega" dispara una consulta por fila (N+1).
            // Condicionado a que el tenant tenga el módulo: sin la tabla, el
            // eager loading tumbaría la pantalla de pedidos entera.
            if (ShippingRequest::moduleInstalled()) {
                $query->with([
                    // La suma de cobros del envio, en la MISMA consulta. El
                    // accesor `paid_total` la calcula por fila si no viene, y
                    // la fila la pide para el saldo y para el estado economico.
                    'shipment' => fn ($q) => $q->withSum([
                        'payments' => fn ($p) => PaymentVerification::soloValidos($p, 'shipping_payments'),
                    ], 'amount'),
                    'shipment.printBatch:id,code,status',
                    // La guía de remisión, por el mismo motivo que los
                    // comprobantes: `OrderDocuments` la lee de aquí o la da
                    // por inexistente.
                    'shipment.dispatch:id,state_type_id,series,number,external_id,date_of_issue',
                    // El envio VIGENTE. `shipment` es el ultimo aunque este
                    // anulado, y el estado economico tiene que mirar el que
                    // sigue en pie — que es el mismo que mira el filtro en SQL.
                    'activeShipment' => fn ($q) => $q->withSum([
                        'payments' => fn ($p) => PaymentVerification::soloValidos($p, 'shipping_payments'),
                    ], 'amount'),
                ]);
            }
        }

        $this->applyOrderDateRange($query, $request);
        $this->applyOrderSource($query, $request);
        $this->applyCommercialFilters($query, $request);
        $this->applyPaymentStateFilter($query, $request);
        $this->applyLogisticFilters($query, $request);

        if ($withChip) {
            $this->applyOperationalChip($query, $request);
        }

        // El orden es cosa del LISTADO. Los otros dos consumidores de esta
        // consulta (`statusCounts` y `stats`) piden agregados y ya llaman a
        // `reorder()` donde hace falta; anadirles un ORDER BY solo seria
        // trabajo tirado, y con una expresion JSON ademas seria un riesgo bajo
        // ONLY_FULL_GROUP_BY.
        if ($withRelations) {
            $this->applyOrderSort($query, $request);
        }

        return $query;
    }

    /**
     * Columnas por las que se puede ordenar el listado.
     *
     * Lista blanca a proposito: el parametro llega del navegador y termina en
     * un ORDER BY. Cualquier valor que no este aqui se ignora y manda el orden
     * por defecto.
     */
    private const ORDENES = [
        'fecha'       => 'created_at',
        'pedido'      => 'id',
        'total'       => 'total',
        'estado'      => 'status_order_id',
        'actualizado' => 'updated_at',
        // `cliente` no es una columna: el nombre vive dentro del JSON de
        // `orders.customer`, y ademas bajo dos claves distintas segun quien
        // creara el pedido. Se resuelve en applyOrderSort().
        'cliente'     => null,
    ];

    /**
     * Ordena el listado.
     *
     * Hasta ahora la consulta era `Order::query()->latest()`, fijo: no habia
     * forma de ordenar por importe, por cliente ni por ultima actualizacion.
     *
     * El parametro es propio (`orden`) y NO el `sort_field` que el DataTable
     * manda siempre. No es capricho: ese componente envia `sort_field=id` por
     * defecto, y honrarlo cambiaria el orden por defecto de «fecha del pedido»
     * a «id», que NO es lo mismo. El espejo de un encargo logistico se crea con
     * la fecha del envio, que puede ser anterior a la de otro pedido con id
     * mas bajo; el listado dejaria de estar en orden cronologico sin que nadie
     * hubiera pedido ese cambio.
     */
    private function applyOrderSort($query, Request $request): void
    {
        $clave = (string) $request->input('orden', '');
        $dir   = strtolower((string) $request->input('orden_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!array_key_exists($clave, self::ORDENES)) {
            // El de siempre: lo mas reciente arriba.
            $query->latest();

            return;
        }

        $query->reorder();

        if ($clave === 'cliente') {
            // El nombre esta dentro del JSON y bajo dos claves segun el origen
            // del pedido. Sin indice, pero la tabla mas grande en produccion
            // tiene 703 filas: el coste es irrelevante y la alternativa seria
            // una columna derivada que habria que mantener en seis sitios.
            $query->orderByRaw(
                "COALESCE("
                . "JSON_UNQUOTE(JSON_EXTRACT(customer, '$.apellidos_y_nombres_o_razon_social')),"
                . "JSON_UNQUOTE(JSON_EXTRACT(customer, '$.name')),"
                . "''"
                . ") {$dir}"
            );
        } else {
            $query->orderBy(self::ORDENES[$clave], $dir);
        }

        // Desempate estable. Sin el, dos pedidos del mismo dia o con el mismo
        // importe pueden cambiar de sitio entre una pagina y la siguiente, y el
        // operador ve el mismo pedido dos veces o ninguna.
        $query->orderBy('id', 'desc');
    }

    /** Filtros del lado comercial del pedido. */
    /**
     * Cuanto se ha cobrado de este pedido, en SQL.
     *
     * Suma los DOS origenes porque un pedido normal cobra en `order_payments`
     * y el espejo de un encargo logistico —que nace con total 0— cobra en
     * `shipping_payments`, a traves de su envio. Sumarlos cubre los dos sin
     * preguntar de que tipo es el pedido.
     *
     * Los envios ANULADOS quedan fuera: su dinero se devolvio o nunca entro, y
     * contarlo daria por cobrado un pedido que no lo esta.
     */
    private function sqlCobrado(bool $conEnvios): string
    {
        $validosOp = PaymentVerification::sqlSoloValidos('op', 'order_payments');
        $propios = "COALESCE((SELECT SUM(op.payment) FROM order_payments op"
                 . " WHERE op.order_id = orders.id AND {$validosOp}), 0)";

        if (!$conEnvios) {
            return "({$propios})";
        }

        // Del envio VIGENTE, no de todos los que tuvo el pedido. Es la regla
        // del modulo —1 pedido = 1 registro logistico vigente— y es la que usa
        // la fila, que lee `activeShipment`. Sumar todos los historicos daba un
        // pedido cobrado que la fila pintaba pendiente.
        $validosSp = PaymentVerification::sqlSoloValidos('sp', 'shipping_payments');
        $delEnvio = "COALESCE((SELECT SUM(sp.amount) FROM shipping_payments sp"
                  . " WHERE {$validosSp} AND sp.shipment_id = (SELECT sr.id FROM shipping_requests sr"
                  . " WHERE sr.order_id = orders.id AND sr.cancelled_at IS NULL"
                  . " ORDER BY sr.id DESC LIMIT 1)), 0)";

        return "({$propios} + {$delEnvio})";
    }

    /**
     * Cuanto hay que cobrar. El total del pedido, salvo que valga 0 y haya un
     * encargo detras: entonces es el importe cargado en el envio.
     */
    private function sqlACobrar(bool $conEnvios): string
    {
        if (!$conEnvios) {
            return "(orders.total)";
        }

        // `amount_due` es lo mismo que el accesor `amount_to_collect` del
        // modelo, que es lo que usa la fila. Un envio anulado no cuenta: su
        // importe ya no hay que cobrarlo.
        return "(CASE WHEN orders.total > 0 THEN orders.total ELSE COALESCE("
             . "(SELECT sr.amount_due FROM shipping_requests sr"
             . " WHERE sr.order_id = orders.id AND sr.cancelled_at IS NULL"
             . " ORDER BY sr.id DESC LIMIT 1), 0) END)";
    }

    /**
     * Filtra por estado economico del cobro.
     *
     * Es la MISMA regla que pinta la fila (`OrderCollection::paymentState`),
     * escrita en SQL porque filtrar en PHP obligaria a traerse la tabla entera.
     * Si las dos divergen, el operador filtra por «parcial» y le salen pedidos
     * que la fila pinta «pagado» — y no hay forma de que se de cuenta de cual
     * de las dos miente.
     *
     * `sin_monto` no se ofrece como filtro: es el hueco del encargo al que
     * nadie le cargo el importe, y para eso ya esta el aviso de la fila.
     */
    private function applyPaymentStateFilter($query, Request $request): void
    {
        $estado = (string) $request->input('estado_pago', '');

        if (!in_array($estado, ['pendiente', 'parcial', 'pagado', 'canal'], true)) {
            return;
        }

        // Los pedidos de marketplace no entran en la cuenta del dinero: su
        // cobro lo hizo el canal y aqui no hay ni habra un `order_payment`. Sin
        // esta separacion, filtrar «pago pendiente» en carolayimport devuelve
        // los 710 pedidos, que es exactamente el ruido que se quiere quitar.
        $conMarketplace = MarketplaceOrder::moduleInstalled();

        // «Cobrado por el canal»: existe y el canal no lo cancelo ni lo devolvio.
        $cobradoPorCanal = "EXISTS (SELECT 1 FROM marketplace_orders mo"
                         . " WHERE mo.order_id = orders.id"
                         . " AND mo.status NOT IN ('canceled','returned'))";

        // Cualquier pedido de marketplace, cancelado o no.
        $esDeMarketplace = "EXISTS (SELECT 1 FROM marketplace_orders mo"
                         . " WHERE mo.order_id = orders.id)";

        if ($estado === 'canal') {
            // Sin el modulo no hay pedidos de canal: ninguno cumple.
            $query->whereRaw($conMarketplace ? $cobradoPorCanal : '1 = 0');

            return;
        }

        // Los tres estados del dinero excluyen a TODO pedido de marketplace, no
        // solo a los cobrados. Un pedido cancelado por el canal sale en la fila
        // como «Sin cobro del canal», que no es «pago pendiente»: aqui nadie va
        // a cobrar nada. Con la exclusion a medias, filtrar «pendiente» en
        // carolayimport devolvia 49 pedidos cuyo chip decia otra cosa — la
        // divergencia entre fila y filtro que este trabajo viene a evitar.
        if ($conMarketplace) {
            $query->whereRaw("NOT {$esDeMarketplace}");
        }

        // Sin el modulo de Envios no existen `shipping_payments` ni
        // `shipping_requests`: nombrarlas en el SQL seria un 1146 en el
        // listado entero.
        $conEnvios = ShippingRequest::moduleInstalled();
        $cobrado   = $this->sqlCobrado($conEnvios);
        $aCobrar   = $this->sqlACobrar($conEnvios);

        // El margen de un centimo evita que un redondeo deje como «parcial» un
        // pedido cobrado al completo.
        match ($estado) {
            'pendiente' => $query->whereRaw("{$aCobrar} > 0 AND {$cobrado} <= 0"),
            'parcial'   => $query->whereRaw("{$aCobrar} > 0 AND {$cobrado} > 0 AND {$cobrado} + 0.009 < {$aCobrar}"),
            'pagado'    => $query->whereRaw("{$aCobrar} > 0 AND {$cobrado} + 0.009 >= {$aCobrar}"),
        };
    }

    private function applyCommercialFilters($query, Request $request)
    {
        $allowedColumns = ['date_of_issue', 'id', 'shipping_address', 'reference_payment', 'total'];
        $column = in_array($request->column, $allowedColumns) ? $request->column : 'id';

        // El buscador de la tabla manda `column` + `value`. Con la columna
        // `search` ese texto alimenta la búsqueda unificada en vez de un LIKE
        // sobre una sola columna: es lo que conecta el buscador de la pantalla
        // con los datos del envío.
        $esBusquedaUnificada = $request->input('column') === 'search';

        if ($request->value && !$esBusquedaUnificada) {
            $query->where($column, 'like', "%{$request->value}%");
        }

        // Búsqueda unificada: el operador escribe lo que tiene a mano — el N° de
        // pedido, el código ENV, el DNI o el teléfono del cliente que llama, o
        // el tracking de la agencia— y debe encontrar el pedido con cualquiera.
        $termino = $esBusquedaUnificada ? $request->input('value', '') : $request->input('q', '');

        if ($q = trim((string) $termino)) {
            $qNum = preg_replace('/\D+/', '', $q);
            $hasShipping = ShippingRequest::moduleInstalled();

            $query->where(function ($w) use ($q, $qNum, $hasShipping) {
                $w->where('id', 'like', "%{$q}%")
                  ->orWhere('external_order_ref', 'like', "%{$q}%")
                  ->orWhere('number_document', 'like', "%{$q}%")
                  ->orWhere('shipping_address', 'like', "%{$q}%")
                  // customer es JSON: el LIKE sobre el texto crudo cubre nombre,
                  // documento y telefono sin depender del motor JSON de MySQL.
                  ->orWhere('customer', 'like', "%{$q}%");

                if ($qNum !== '' && $qNum !== $q) {
                    $w->orWhere('customer', 'like', "%{$qNum}%");
                }

                // El OR sobre envios solo si el tenant tiene el modulo: sin la
                // tabla, buscar un pedido devolveria un error de SQL.
                if ($hasShipping) {
                    $w->orWhereHas('shipments', function ($s) use ($q, $qNum) {
                        $s->where('shipment_code', 'like', "%{$q}%")
                          ->orWhere('full_name', 'like', "%{$q}%")
                          ->orWhere('tracking_number', 'like', "%{$q}%")
                          ->orWhere('destination_city', 'like', "%{$q}%")
                          ->orWhere('shipping_agency', 'like', "%{$q}%")
                          ->orWhere('dni', 'like', "%{$q}%")
                          ->orWhere('phone', 'like', "%{$q}%");

                        if ($qNum !== '' && $qNum !== $q) {
                            $s->orWhere('dni', 'like', "%{$qNum}%")
                              ->orWhere('phone', 'like', "%{$qNum}%");
                        }
                    });
                }
            });
        }

        if ($request->status_order_id) {
            $query->where('status_order_id', $request->status_order_id);
        }

        // Estado de la PASARELA, no «esta pagado» — eso lo dice status_order_id
        // (ver el bloque «Estado del pago» en el modelo Order). Se valida contra
        // el vocabulario cerrado en vez de aceptar cualquier cadena: un valor
        // inexistente devolvia cero filas sin decir por que, que es exactamente
        // como el panel entero estuvo vacio durante un ciclo completo (A-01).
        if ($request->filled('payment_status')) {
            if (!in_array($request->payment_status, Order::PAYMENT_STATUSES, true)) {
                abort(422, 'Estado de pago inválido. Valores: ' . implode(', ', Order::PAYMENT_STATUSES) . '.');
            }
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        // El DataTable manda SIEMPRE `warehouse_id`, y por defecto vale la
        // cadena 'all': el selector solo se dibuja en /inventory, asi que aqui
        // viaja invisible. Un `if ($request->warehouse_id)` la daba por buena y
        // MySQL casteaba 'all' a 0 contra una columna entera, de modo que la
        // consulta devolvia CERO filas con HTTP 200 y sin una linea en el log.
        // Solo un id real filtra; cualquier otra cosa es "todos los almacenes".
        if (is_numeric($request->warehouse_id)) {
            $query->where('warehouse_id', (int) $request->warehouse_id);
        }

        if ($request->channel_type) {
            $query->whereHas('channel', fn($q) => $q->where('type', $request->channel_type));
        }

        return $query;
    }

    /**
     * Filtros que viven en el registro logístico del pedido.
     *
     * Siempre sobre el envío VIGENTE (`cancelled_at IS NULL`): un envío anulado
     * no debe hacer que su pedido aparezca en "por despachar".
     */
    private function applyLogisticFilters($query, Request $request)
    {
        // Modalidad de entrega (domicilio = Lima · agencia = Provincia · tienda).
        if (($type = $request->delivery_type) && array_key_exists($type, ShippingRequest::DELIVERY_TYPES)) {
            $this->whereShipment($query, fn($s) => $s->where('delivery_type', $type));
        }

        // Estado logístico (independiente del estado comercial del pedido).
        if ($status = $request->shipping_status) {
            $list = is_array($status) ? $status : explode(',', (string) $status);
            $list = array_values(array_filter($list, fn($v) => isset(ShippingRequest::STATUSES[$v])));
            if ($list) {
                $this->whereShipment($query, fn($s) => $s->whereIn('status', $list));
            }
        }

        if ($batchId = (int) $request->batch_id) {
            $this->whereShipment($query, fn($s) => $s->where('print_batch_id', $batchId));
        }

        if ($priority = (int) $request->priority) {
            $this->whereShipment($query, fn($s) => $s->where('priority', $priority));
        }

        if ($request->boolean('with_shipping_guide')) {
            $this->whereShipment($query, fn($s) => $s->whereNotNull('shipping_guide_path'));
        }

        if ($request->boolean('without_shipping_guide')) {
            $this->whereShipment($query, fn($s) => $s->whereNull('shipping_guide_path'));
        }

        // Pedidos que todavía no tienen envío configurado — la cola real de
        // trabajo del encargado tras la unificación.
        if ($request->boolean('without_shipment')) {
            $this->whereWithoutShipment($query);
        }

        // Antigüedad en días HÁBILES. Se traduce a un corte de fecha calendario
        // con la MISMA primitiva que pinta el semáforo, para que el filtro y el
        // badge no se desalineen (fines de semana y feriados incluidos).
        $aging = $request->input('aging');
        if (in_array($aging, ['urgentes', 'vencidos'], true)) {
            $setting = ShippingSetting::currentOrNull();

            $this->applyAging($query, $aging, $setting);
        }

        return $query;
    }

    /**
     * EXISTS sobre el envío vigente del pedido.
     *
     * Se usa la relación `shipments` (hasMany) y no `shipment` (one-of-many)
     * a propósito: `whereHas` sobre una relación con `ofMany()` arrastra la
     * subconsulta de agregación al WHERE y deja de ser un EXISTS aprovechable
     * por el índice de `order_id`.
     */
    /**
     * Antiguedad: urgentes (a un dia habil del plazo) y vencidos (pasados).
     *
     * Vive aparte porque la usan DOS consumidores: el filtro del listado y los
     * contadores de la barra de prioridad. Con la regla escrita dos veces, el
     * contador diria «2 vencidos» y el filtro devolveria otra cosa — y el
     * operador no tendria forma de saber cual miente.
     */
    /** Cuantos pedidos hay en ese tramo de antiguedad. */
    private function contarPorAntiguedad($base, string $aging): int
    {
        $setting = ShippingSetting::currentOrNull();

        if (!$setting || !ShippingRequest::moduleInstalled()) {
            return 0;
        }

        $q = (clone $base)->reorder();
        $this->applyAging($q, $aging, $setting);

        return $q->count();
    }

    private function applyAging($query, string $aging, $setting): void
    {
        if (!$setting) {
            // Sin el módulo de Envíos no hay antigüedad que medir, y la
            // respuesta honesta es "ningún pedido", igual que el resto de
            // filtros logísticos. Antes se ignoraba el filtro en silencio y
            // "vencidos" devolvía TODOS los pedidos, que es justo lo
            // contrario de lo que se preguntó.
            $this->whereShipment($query, fn($s) => $s);

            return;
        }

        $maxDays = $setting->max_days;
        $k = $aging === 'vencidos' ? $maxDays : max(1, $maxDays - 1);
        $cutoff = ShippingRequest::agingCutoff($k, (bool) ($setting->aging_skip_holidays ?? true))->toDateString();

        $this->whereShipment($query, fn($s) => $s
            ->whereDate('created_at', '<=', $cutoff)
            ->whereNotIn('status', ShippingRequest::CLOSED_STATUSES));
    }

    private function whereShipment($query, callable $constraint)
    {
        // Sin el módulo instalado no hay envíos: cualquier filtro logístico
        // devuelve vacío, que es la respuesta correcta (y no un error de SQL).
        if (!ShippingRequest::moduleInstalled()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('shipments', function ($s) use ($constraint) {
            $s->whereNull('cancelled_at');
            $constraint($s);
        });
    }

    /**
     * Pedidos SIN envío vigente.
     * Sin el módulo instalado la respuesta correcta es "todos", no un error:
     * en ese tenant ningún pedido tiene envío configurado.
     */
    private function whereWithoutShipment($query)
    {
        if (!ShippingRequest::moduleInstalled()) {
            return $query;
        }

        return $query->whereDoesntHave('shipments', fn($s) => $s->whereNull('cancelled_at'));
    }

    /**
     * Chips operativos de la pantalla unificada.
     *
     * Cada chip es una PREGUNTA DE TRABAJO ("¿qué me toca hacer ahora?"), no un
     * estado suelto: por eso combinan estado comercial y estado logístico.
     * Se conservan las claves antiguas (`mp_filter`) porque hay enlaces
     * guardados y el panel de Saga las sigue usando.
     */
    private function applyOperationalChip($query, Request $request, ?string $chip = null)
    {
        $chip = $chip ?: ($request->input('chip') ?: $request->input('mp_filter'));

        switch ($chip) {
            // ── Chips comerciales heredados (Saga) ────────────────────────
            case 'todispatch': // Por despachar (pendiente/verificado/preparación)
                $query->whereIn('status_order_id', [1, 2, 3]);
                break;
            case 'shipped':    // Enviados (despachado)
                $query->where('status_order_id', 4);
                break;
            case 'canceled':   // Cancelados / Devoluciones
                $query->where('status_order_id', 5);
                break;
            case 'no_invoice': // Pedidos de marketplace SIN boleta
                // Sin la tabla no hay pedidos de marketplace externo, y la
                // respuesta correcta es "ninguno", no un error de SQL.
                if (!MarketplaceOrder::moduleInstalled()) {
                    $query->whereRaw('1 = 0');
                    break;
                }
                $query->whereNull('number_document')
                    ->whereHas('marketplaceOrder', function ($q) {
                        $q->whereNull('invoice_uploaded_at')->whereNull('document_id');
                    });
                break;

            // ── Chips operativos unificados ───────────────────────────────
            case 'por_confirmar':
                // Pago aún no validado: nada de logística arranca hasta aquí.
                $query->where('status_order_id', 1);
                break;

            case 'por_preparar':
                // Pago OK y todavía sin cerrar logísticamente. Incluye los
                // pedidos que ni siquiera tienen envío configurado: son
                // trabajo pendiente, no pedidos "sin estado".
                $query->whereIn('status_order_id', [2, 3]);

                // Sin el modulo de Envios, "por preparar" es sencillamente todo
                // pedido pagado: no hay estado logistico que lo descarte.
                if (ShippingRequest::moduleInstalled()) {
                    $query->where(function ($w) {
                        $w->whereDoesntHave('shipments', fn($s) => $s->whereNull('cancelled_at'))
                          ->orWhereHas('shipments', fn($s) => $s->whereNull('cancelled_at')
                              ->whereIn('status', [
                                  ShippingRequest::STATUS_RECIBIDO,
                                  ShippingRequest::STATUS_CONFIRMADO,
                                  ShippingRequest::STATUS_PREPARANDO,
                                  'pendiente',
                              ]));
                    });
                }
                break;

            case 'por_imprimir':
                // Requiere rótulo (el recojo en tienda no) y aún no se imprimió.
                $this->whereShipment($query, fn($s) => $s
                    ->whereNull('printed_at')
                    ->printableLabel());
                break;

            case 'por_embalar':
                $this->whereShipment($query, fn($s) => $s->where('status', ShippingRequest::STATUS_IMPRESO));
                break;

            case 'por_despachar':
                $this->whereShipment($query, fn($s) => $s->where('status', ShippingRequest::STATUS_EMBALANDO));
                break;

            case 'en_transito':
                // Lima: en camino · Provincia: despachado / en agencia / en ruta.
                $this->whereShipment($query, fn($s) => $s->whereIn('status', [
                    ShippingRequest::STATUS_EN_CAMINO,
                    ShippingRequest::STATUS_DESPACHADO,
                    ShippingRequest::STATUS_EN_AGENCIA,
                    ShippingRequest::STATUS_EN_RUTA,
                    'enviado', // legado = entregado a agencia
                ]));
                break;

            case 'listos_recojo':
                $this->whereShipment($query, fn($s) => $s
                    ->where('delivery_type', ShippingRequest::DELIVERY_TIENDA)
                    ->where('status', ShippingRequest::STATUS_LISTO_RECOJO));
                break;

            case 'delivered':
            case 'entregados':
                $query->where('status_order_id', 6);
                break;

            case 'anulados':
                $query->where('status_order_id', 5);
                break;

            case 'nuevos':
                // El buzon de entrada: el cliente acaba de registrar su envio y
                // nadie lo ha revisado todavia.
                //
                // NO es lo mismo que «Por confirmar», aunque se parezcan de
                // nombre: aquel filtra por `status_order_id = 1`, o sea por el
                // PAGO sin verificar. Un encargo llegado del formulario publico
                // nace con el pago en estado 2 —no hay nada que cobrar en el
                // pedido— asi que jamas aparecia en ningun chip que dijera
                // «nuevo», y era justo el trabajo mas urgente.
                //
                // La lista de estados es la MISMA que usa la pestaña «Nuevos»
                // del panel de Envios (`ShipmentController`, grupo `confirmar`).
                // Si alli cambia, cambia aqui.
                $this->whereShipment($query, fn($s) => $s
                    ->whereIn('status', [
                        ShippingRequest::STATUS_RECIBIDO,
                        'pendiente',
                    ]));
                break;

            case 'sin_envio':
                $this->whereWithoutShipment($query);
                break;
        }

        return $query;
    }

    /**
     * Conteos por chip de filtro (para los badges estilo Saga).
     */
    /**
     * Conteo de cada chip de la pantalla unificada.
     *
     * Se calculan sobre la MISMA consulta base que la tabla (mismos filtros de
     * fecha, canal, búsqueda…) pero sin el chip activo: el número de un chip
     * debe ser lo que verás al pulsarlo, no un total global.
     */
    public function statusCounts(Request $request)
    {
        $base = $this->buildOrdersQuery($request, false, false);

        // Dos consultas agregadas en vez de una por chip.
        //
        // La versión anterior hacía 15 COUNT, y seis de ellos eran EXISTS
        // correlacionados contra `shipping_requests` sobre TODA la tabla de
        // pedidos. En local, con 13 filas, era instantáneo; en un tenant con
        // volumen real es la diferencia entre responder y agotar el tiempo —y
        // este endpoint se llama en cada carga y en cada cambio de página.

        // 1. Todo lo que depende solo del estado comercial.
        // `reorder()` es obligatorio: buildOrdersQuery arrastra `latest()`, y un
        // ORDER BY por una columna que no está en el GROUP BY revienta bajo
        // ONLY_FULL_GROUP_BY (el modo por defecto de MySQL 8).
        $porEstado = (clone $base)->reorder()
            ->selectRaw('status_order_id, COUNT(*) as total')
            ->groupBy('status_order_id')
            ->pluck('total', 'status_order_id');

        $enEstados = fn(array $ids) => (int) collect($ids)
            ->sum(fn($id) => (int) ($porEstado[$id] ?? 0));

        $counts = [
            'all'           => (int) $porEstado->sum(),
            'todispatch'    => $enEstados([1, 2, 3]),
            'shipped'       => $enEstados([4]),
            'canceled'      => $enEstados([5]),
            'por_confirmar' => $enEstados([1]),
            // Se cuenta aparte y no en `shipmentStageCounts` porque no depende
            // del estado comercial: un envio recien registrado puede estar en
            // cualquiera de ellos.
            // Prioridad. Se cuentan con la MISMA regla que filtra —
            // `applyAging`— y no con una copia.
            'urgentes'      => $this->contarPorAntiguedad($base, 'urgentes'),
            'vencidos'      => $this->contarPorAntiguedad($base, 'vencidos'),
            'nuevos'        => ShippingRequest::moduleInstalled()
                ? (clone $base)->whereHas('shipments', fn($s) => $s
                    ->whereNull('cancelled_at')
                    ->whereIn('status', [ShippingRequest::STATUS_RECIBIDO, 'pendiente']))->count()
                : 0,
            'entregados'    => $enEstados([6]),
            'anulados'      => $enEstados([5]),
        ];

        // 2. Todo lo que depende del envío vigente, en UNA pasada.
        $counts += $this->shipmentStageCounts($base, $enEstados([2, 3]));

        // 3. Los dos que no encajan en ninguna de las dos anteriores.
        $counts['no_invoice'] = MarketplaceOrder::moduleInstalled()
            ? (clone $base)->whereNull('number_document')
                ->whereHas('marketplaceOrder', fn($q) => $q->whereNull('invoice_uploaded_at')->whereNull('document_id'))
                ->count()
            : 0;

        // Alias histórico: el Vue actual lee `delivered`.
        $counts['delivered'] = $counts['entregados'];

        return response()->json($counts);
    }

    /**
     * Conteos por etapa logística, resueltos con UN agregado sobre el envío
     * vigente en vez de un EXISTS por chip.
     *
     * @param int $pagados Pedidos con pago validado (2 y 3), para calcular
     *                     "por preparar" restando los que ya avanzaron.
     * @return array<string, int>
     */
    private function shipmentStageCounts($base, int $pagados): array
    {
        $totalPedidos = (clone $base)->reorder()->count();

        $vacio = [
            'por_preparar' => $pagados, 'por_imprimir' => 0, 'por_embalar' => 0,
            'por_despachar' => 0, 'en_transito' => 0, 'listos_recojo' => 0,
            'sin_envio' => $totalPedidos,
        ];

        // Sin el módulo no hay etapas: todo pedido está "sin envío" y todo
        // pedido pagado está por preparar.
        if (!ShippingRequest::moduleInstalled()) {
            return $vacio;
        }

        // Subconsulta y no JOIN: `created_at` existe en las DOS tablas, así que
        // cualquier filtro de fecha del listado quedaba ambiguo y reventaba.
        $idsFiltrados = (clone $base)->reorder()->select('orders.id');

        $conEnvio = ShippingRequest::query()
            ->whereNull('cancelled_at')
            ->whereIn('order_id', $idsFiltrados)
            ->selectRaw('status as estado, delivery_type as modalidad,
                         COUNT(DISTINCT order_id) as total,
                         COUNT(DISTINCT CASE WHEN printed_at IS NULL THEN order_id END) as sin_imprimir')
            ->groupBy('status', 'delivery_type')
            ->get();

        $sumar = fn(callable $filtro) => (int) $conEnvio->filter($filtro)->sum('total');

        $enPreparacion = [
            ShippingRequest::STATUS_RECIBIDO, ShippingRequest::STATUS_CONFIRMADO,
            ShippingRequest::STATUS_PREPARANDO, 'pendiente',
        ];

        $conEnvioTotal = (int) $conEnvio->sum('total');

        return [
            // "Por preparar" incluye los pedidos pagados que aún no tienen
            // envío configurado: son trabajo pendiente, no pedidos sin estado.
            'por_preparar'  => max(0, $pagados - $conEnvioTotal)
                             + $sumar(fn($r) => in_array($r->estado, $enPreparacion, true)),
            'por_imprimir'  => (int) $conEnvio
                ->filter(fn($r) => $r->modalidad !== ShippingRequest::DELIVERY_TIENDA
                    && !in_array($r->estado, ShippingRequest::LABEL_LOCKED_STATUSES, true))
                ->sum('sin_imprimir'),
            'por_embalar'   => $sumar(fn($r) => $r->estado === ShippingRequest::STATUS_IMPRESO),
            'por_despachar' => $sumar(fn($r) => $r->estado === ShippingRequest::STATUS_EMBALANDO),
            'en_transito'   => $sumar(fn($r) => in_array($r->estado, [
                ShippingRequest::STATUS_EN_CAMINO, ShippingRequest::STATUS_DESPACHADO,
                ShippingRequest::STATUS_EN_AGENCIA, ShippingRequest::STATUS_EN_RUTA, 'enviado',
            ], true)),
            'listos_recojo' => $sumar(fn($r) => $r->modalidad === ShippingRequest::DELIVERY_TIENDA
                                && $r->estado === ShippingRequest::STATUS_LISTO_RECOJO),
            'sin_envio'     => max(0, $totalPedidos - $conEnvioTotal),
        ];
    }

    public function stats(Request $request)
    {
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        // Sin relaciones ni chip: son agregados, y el chip activo no debe
        // recortar los KPIs de cabecera.
        $orders = $this->buildOrdersQuery($request, false, false);

        $total        = (clone $orders)->count();
        $pending      = (clone $orders)->where('status_order_id', 1)->count();
        $verified     = (clone $orders)->where('status_order_id', 2)->count();
        $dispatched   = (clone $orders)->where('status_order_id', 3)->count();
        $revenueMonth = (clone $orders)->when(!$request->date_from && !$request->date_to, fn($q) => $q->whereDate('created_at', '>=', $monthStart))
                             ->whereNotIn('status_order_id', [5])
                             ->sum('total');
        $revenueToday = (clone $orders)->whereDate('created_at', $today)->sum('total');

        // Desglose por canal (para el dashboard).
        //
        // `reorder()` NO es opcional: `buildOrdersQuery` arrastra `latest()`, y
        // un ORDER BY por `created_at` junto a un GROUP BY por `channel_id`
        // revienta con el error 1055 de MySQL (ONLY_FULL_GROUP_BY, el modo por
        // defecto). El endpoint devolvía 500 y el `catch` vacío del Vue lo
        // convertía en "los indicadores salen vacíos", sin rastro visible.
        $byChannel = (clone $orders)->reorder()
                          ->selectRaw('channel_id, COUNT(*) as count, SUM(total) as revenue')
                          ->when(!$request->date_from && !$request->date_to, fn($q) => $q->whereDate('created_at', '>=', $monthStart))
                          ->whereNotIn('status_order_id', [5])
                          ->groupBy('channel_id')
                          ->with('channel:id,name,type,code')
                          ->get()
                          ->map(fn($r) => [
                              'channel_id'   => $r->channel_id,
                              'channel_name' => $r->channel?->name ?? 'Sin canal',
                              'channel_type' => $r->channel?->type ?? 'other',
                              'count'        => (int) $r->count,
                              'revenue'      => (float) $r->revenue,
                          ]);

        return response()->json(compact('total', 'pending', 'verified', 'dispatched', 'revenueMonth', 'revenueToday', 'byChannel'));
    }

    /**
     * Fechas por las que se puede filtrar y dónde vive cada una.
     *
     * `shipment:` marca las que están en el registro logístico: se filtran con
     * un EXISTS sobre el envío vigente, no con una columna de `orders`.
     * Deliberadamente NO se ofrece `updated_at`: no es una fecha de negocio y
     * cualquier edición la mueve.
     */
    private const DATE_FIELDS = [
        'order'      => 'created_at',
        'paid'       => 'paid_at',
        'confirmed'  => 'confirmed_at',
        'prepared'   => 'prepared_at',
        'dispatched' => 'dispatched_at',
        'delivered'  => 'delivered_at',
        'cancelled'  => 'cancelled_at',
        'printed'    => 'shipment:printed_at',
        'ready'      => 'shipment:ready_at',
        'sent'       => 'shipment:sent_at',
        'pickup'     => 'shipment:picked_up_at',
    ];

    /**
     * Periodo del listado. Acepta un rango rápido (`range`) o fechas explícitas
     * (`date_from`/`date_to`), y elige POR QUÉ fecha filtrar (`date_type`).
     *
     * El default sigue siendo la fecha del pedido, que es lo que hacía antes:
     * las llamadas existentes del Vue no cambian de comportamiento.
     */
    private function applyOrderDateRange($query, Request $request)
    {
        $dates = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $from = $dates['date_from'] ?? null;
        $to   = $dates['date_to'] ?? null;

        // Rango rápido: pisa a las fechas sueltas porque es lo que el usuario
        // acaba de elegir en el selector.
        [$rangeFrom, $rangeTo] = $this->resolveQuickRange((string) $request->input('range', ''));
        if ($rangeFrom) {
            [$from, $to] = [$rangeFrom, $rangeTo];
        }

        if (!$from && !$to) {
            return $query;
        }

        $type  = (string) $request->input('date_type', 'order');
        $field = self::DATE_FIELDS[$type] ?? self::DATE_FIELDS['order'];

        if (str_starts_with($field, 'shipment:')) {
            $column = substr($field, strlen('shipment:'));
            return $this->whereShipment($query, function ($s) use ($column, $from, $to) {
                if ($from) $s->whereDate($column, '>=', $from);
                if ($to)   $s->whereDate($column, '<=', $to);
            });
        }

        // Las fechas comerciales llegaron en una migración posterior al código.
        // Entre el deploy y `tenancy:migrate` —o si la migración falla en un
        // tenant— la columna no existe, y filtrar por ella devolvía un 1054 que
        // el usuario veía como la pantalla en blanco. Sin la columna ningún
        // pedido tiene esa fecha, así que la respuesta honesta es "ninguno",
        // igual que con el resto de filtros que no se pueden resolver.
        if (in_array($field, self::DATE_FIELDS_NUEVAS, true) && !$this->orderHasColumn($field)) {
            \Illuminate\Support\Facades\Log::warning(
                "Filtro por «{$type}» pedido en un tenant sin la columna orders.{$field}; "
                . 'falta correr tenancy:migrate.'
            );

            return $query->whereRaw('1 = 0');
        }

        if ($from) $query->whereDate($field, '>=', $from);
        if ($to)   $query->whereDate($field, '<=', $to);

        return $query;
    }

    /**
     * Columnas de fecha que llegaron DESPUÉS del código que las usa
     * (migración `add_business_dates_to_orders_table`) y que, por tanto,
     * pueden no existir todavía en un tenant.
     *
     * Solo estas se comprueban. Guardar también `created_at` sería peor que el
     * problema: si la lectura del esquema falla por cualquier motivo, la vista
     * por defecto —que filtra por fecha de pedido— se quedaría en cero filas
     * sin explicación.
     */
    private const DATE_FIELDS_NUEVAS = ['paid_at', 'confirmed_at', 'cancelled_at'];

    /**
     * ¿La tabla `orders` de ESTE tenant tiene la columna?
     *
     * Memorizado por base de datos: un worker de cola atiende varios tenants
     * seguidos y una memo global le daría la respuesta del anterior.
     */
    private function orderHasColumn(string $column): bool
    {
        static $cache = [];

        try {
            $schema = \Illuminate\Support\Facades\Schema::connection('tenant');
            $clave  = $schema->getConnection()->getDatabaseName() . '|' . $column;

            if (!array_key_exists($clave, $cache)) {
                $cache[$clave] = $schema->hasColumn('orders', $column);
            }

            return $cache[$clave];
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Traduce un rango rápido a [desde, hasta].
     * Mismas claves que el panel de envíos, para que el operador no tenga que
     * aprender dos vocabularios.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveQuickRange(string $range): array
    {
        $hoy = now();

        switch ($range) {
            case 'hoy':    return [$hoy->toDateString(), $hoy->toDateString()];
            case 'ayer':
                $d = $hoy->copy()->subDay()->toDateString();
                return [$d, $d];
            case '7dias':  return [$hoy->copy()->subDays(6)->toDateString(), $hoy->toDateString()];
            case '30dias': return [$hoy->copy()->subDays(29)->toDateString(), $hoy->toDateString()];
            case 'mes':    return [$hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString()];
            case 'mes_pasado':
                $lm = $hoy->copy()->subMonthNoOverflow();
                return [$lm->copy()->startOfMonth()->toDateString(), $lm->copy()->endOfMonth()->toDateString()];
        }

        return [null, null];
    }

    /** Delimita el tablero al canal que el operador está gestionando. */
    private function applyOrderSource($query, Request $request)
    {
        $source = $request->input('order_source', 'all');
        if (!in_array($source, ['all', 'saga', 'other'], true)) {
            abort(422, 'Origen de pedido inválido.');
        }

        // Sin la tabla, ningún pedido es de Saga: «saga» no devuelve nada y
        // «otros» los devuelve todos. Cualquiera de los dos `whereHas` sobre una
        // tabla inexistente tumbaría el listado con un 1146.
        if (!MarketplaceOrder::moduleInstalled()) {
            return $source === 'saga' ? $query->whereRaw('1 = 0') : $query;
        }

        if ($source === 'saga') {
            $query->whereHas('marketplaceOrder.channel', function ($marketplaceChannel) {
                $marketplaceChannel->where('platform', 'falabella');
            });
        }

        if ($source === 'other') {
            $query->whereDoesntHave('marketplaceOrder.channel', function ($marketplaceChannel) {
                $marketplaceChannel->where('platform', 'falabella');
            });
        }

        return $query;
    }

    /**
     * FASE 5 — Reporte completo de ventas por canal.
     * GET /orders/channel-report?from=2026-01-01&to=2026-03-31
     */
    public function channelReport(Request $request)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to   = $request->to   ?? now()->toDateString();

        $channels = SalesChannel::active()->get();

        $report = $channels->map(fn($ch) => $ch->salesSummary($from, $to));

        // Totales globales para comparación
        $globalRevenue = Order::whereDate('created_at', '>=', $from)
                              ->whereDate('created_at', '<=', $to)
                              ->whereNotIn('status_order_id', [5])
                              ->sum('total');

        // Añadir porcentaje de participación
        $report = $report->map(function ($row) use ($globalRevenue) {
            $row['revenue_share'] = $globalRevenue > 0
                ? round(($row['revenue'] / $globalRevenue) * 100, 1)
                : 0;
            return $row;
        });

        return response()->json([
            'from'           => $from,
            'to'             => $to,
            'global_revenue' => (float) $globalRevenue,
            'channels'       => $report->values(),
        ]);
    }

    /**
     * Devuelve los canales activos (para filtros en el frontend).
     */
    public function channels()
    {
        return response()->json([
            'channels' => SalesChannel::active()->get(['id', 'name', 'type', 'code']),
            // El alta manual deja escribir el precio, y no todo el mundo deberia
            // poder. Se reutiliza el permiso que ya existe en el ERP en vez de
            // inventar uno nuevo. Esto es solo para que la pantalla lo refleje:
            // quien manda es la comprobacion del servidor al guardar.
            'can_edit_prices' => (bool) optional(auth()->user())->permission_edit_item_prices,
        ]);
    }

    /**
     * Convierte las filas del formulario en lineas del pedido y sus totales.
     *
     * Existe para que el alta y la edicion hagan EXACTAMENTE la misma
     * aritmetica. Cuando cada una calculaba lo suyo bastaba tocar una para que
     * un pedido editado dejara de cuadrar con el mismo pedido recien creado.
     *
     * El descuento es un importe, no un porcentaje: es lo que el operador
     * negocia («te dejo 10 soles»), y guardar el porcentaje obliga a redondear
     * dos veces. Se limita al importe de la linea — un descuento mayor que lo
     * que se cobra seria un pedido que devuelve dinero, y eso no es un
     * descuento sino una nota de credito.
     *
     * El desglose se guarda con la forma que ya usa PromotionEngine
     * (`label` / `amount` negativo / `type`), no con una tercera.
     *
     * @return array{items: array, subtotal: float, descuento: float, total: float, desglose: array}
     */
    private function construirLineas(array $filas): array
    {
        $puedeDescontar = (bool) optional(auth()->user())->permission_edit_item_prices;

        $items = [];
        $subtotal = 0.0;
        $descuentoTotal = 0.0;
        $desglose = [];

        foreach ($filas as $fila) {
            $item     = Item::findOrFail($fila['item_id']);
            $cantidad = (float) $fila['quantity'];
            $precio   = $this->precioDeLinea($item, $fila);
            $bruto    = round($precio * $cantidad, 2);

            // Descontar es la misma potestad que bajar el precio: quien no puede
            // lo uno tampoco lo otro, o el permiso no sirve de nada.
            $descuento = $puedeDescontar ? (float) ($fila['discount'] ?? 0) : 0.0;
            $descuento = max(0, min($descuento, $bruto));

            $neto = round($bruto - $descuento, 2);

            $subtotal       += $bruto;
            $descuentoTotal += $descuento;

            if ($descuento > 0) {
                $desglose[] = [
                    'label'  => 'Descuento en ' . $item->description,
                    'amount' => -$descuento,
                    'type'   => 'manual',
                ];
            }

            $items[] = [
                'item_id'         => $item->id,
                'description'     => $item->description,
                'internal_id'     => $item->internal_id,
                'quantity'        => $cantidad,
                'unit_price'      => $precio,
                'sale_unit_price' => $precio,
                'discount'        => $descuento,
                'subtotal'        => $neto,
                'variant_id'      => $fila['variant_id'] ?? null,
            ];
        }

        return [
            'items'     => $items,
            'subtotal'  => round($subtotal, 2),
            'descuento' => round($descuentoTotal, 2),
            'total'     => round($subtotal - $descuentoTotal, 2),
            'desglose'  => $desglose,
        ];
    }

    /**
     * Precio que de verdad se va a cobrar por una linea.
     *
     * Sin permiso, el precio que llegue del formulario se IGNORA y manda el del
     * catalogo. Deshabilitar el campo en pantalla no es un control: una peticion
     * a mano se lo salta, y aqui se esta fijando lo que se cobra.
     */
    private function precioDeLinea(Item $item, array $fila): float
    {
        $delCatalogo = (float) ($item->is_set
            ? ($item->sale_unit_price_set ?: $item->sale_unit_price)
            : $item->sale_unit_price);

        if (!empty($fila['variant_id'])) {
            $variante = \App\Models\Tenant\ItemVariant::find($fila['variant_id']);
            if ($variante) {
                $delCatalogo = (float) ($variante->sale_unit_price ?: $delCatalogo);
            }
        }

        if (!optional(auth()->user())->permission_edit_item_prices) {
            return $delCatalogo;
        }

        return isset($fila['unit_price']) ? (float) $fila['unit_price'] : $delCatalogo;
    }

    /**
     * Crear pedido manual desde cualquier canal (Saga, ML, Instagram, WhatsApp, teléfono)
     */
    /**
     * Buscador de productos del alta manual.
     *
     * No reutiliza ninguno de los que ya hay porque ninguno sirve: el de Envios
     * devuelve datos pensados para escribir TEXTO en el bulto (sin variantes ni
     * precio), y el del ERP (`items/search-items`) filtra insumos de produccion.
     *
     * Lo que si se reutiliza es lo que importa: el disponible sale de
     * `StockReservation::disponible()`, la MISMA funcion que valida el guardado.
     * Si el buscador calculara lo suyo, el operador veria 5 disponibles y el
     * alta le diria que no hay.
     *
     * Un producto con variantes se devuelve con ellas: el stock vive en la
     * variante, y elegir el padre seria vender algo que no existe como tal.
     */
    public function searchItems(Request $request)
    {
        $termino = trim((string) $request->input('q', ''));

        if (mb_strlen($termino) < 2) {
            return response()->json([]);
        }

        $canal       = $request->channel_id ? SalesChannel::find($request->channel_id) : null;
        $warehouseId = $request->warehouse_id ?: ($canal->warehouse_id ?? null);
        $stock       = app(\App\Services\Tenant\StockReservation::class);

        // Todas las palabras deben aparecer: "polo rojo" trae el polo rojo, no
        // todo lo que sea polo mas todo lo que sea rojo.
        $palabras = array_slice(preg_split('/\s+/u', $termino, -1, PREG_SPLIT_NO_EMPTY), 0, 4);

        $q = Item::query()->where('active', true);

        foreach ($palabras as $palabra) {
            $like = '%' . $palabra . '%';
            $q->where(fn ($w) => $w->where('description', 'like', $like)
                                   ->orWhere('internal_id', 'like', $like));
        }

        // `variants()` ya filtra por activas y ordena. No se le encadena nada.
        $items = $q->with('variants')
                   ->orderBy('description')
                   ->limit(15)
                   ->get();

        return response()->json($items->map(function (Item $item) use ($stock, $warehouseId) {
            $variantes = $item->variants->map(fn ($v) => [
                'id'         => $v->id,
                'name'       => $v->display_name ?: $item->description,
                'price'      => (float) ($v->sale_unit_price ?: $item->sale_unit_price),
                'available'  => $stock->disponible($item->id, $v->id, $warehouseId),
            ])->values();

            return [
                'id'          => $item->id,
                'name'        => trim((string) $item->description) ?: 'Producto',
                'code'        => $item->internal_id,
                'price'       => (float) ($item->is_set
                                    ? ($item->sale_unit_price_set ?: $item->sale_unit_price)
                                    : $item->sale_unit_price),
                'is_set'      => (bool) $item->is_set,
                // null = el producto no lleva control de stock. La pantalla
                // debe mostrar «sin control», no un cero que asusta.
                'available'   => $variantes->isEmpty()
                                    ? $stock->disponible($item->id, null, $warehouseId)
                                    : null,
                'variants'    => $variantes,
            ];
        }));
    }

    /**
     * Crea un producto al vuelo desde el alta manual del pedido.
     *
     * ── Por que hace falta ────────────────────────────────────────────────
     *
     * Registro de Envios deja escribir el contenido del paquete a mano, porque
     * ahi es TEXTO para el rotulo. En Pedidos una linea es una venta: lleva
     * `item_id`, precio y reserva de stock, y alimenta la nota de venta — donde
     * `sale_note_items.item_id` es NOT NULL. Una linea de texto libre no se
     * puede facturar, asi que copiar el campo de Envios habria dejado pedidos
     * cobrados e imposibles de documentar.
     *
     * En vez de eso, lo que no esta en el catalogo se CREA. La linea queda
     * normal —se reserva, se factura, sale en el comprobante— y no hay ningun
     * caso especial que arrastrar despues.
     *
     * ── Se crea como SERVICIO ─────────────────────────────────────────────
     *
     * `unit_type_id = 'ZZ'`, que es el tipo que el sistema ya trata como «no
     * controla stock» y que los listados de inventario excluyen. Es lo honesto
     * para algo que se vende una vez y no hay en almacen: un producto normal
     * con stock cero ensuciaria el inventario y, peor, aparentaria una rotura
     * de stock que no existe.
     *
     * Ademas no se le crea fila en `item_warehouse`, y por eso
     * `StockReservation` lo deja pasar: su regla es que «sin fila de almacen»
     * significa «sin control», no «cero».
     *
     * Quien necesite un producto de verdad —con stock, variantes, imagen— lo
     * crea en Productos. Esto es para la venta puntual que no puede esperar.
     */
    public function productoRapido(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
        ], [
            'nombre.required' => 'Escribe el nombre del producto.',
            'nombre.min'      => 'El nombre es demasiado corto.',
            'precio.required' => 'Indica el precio de venta.',
        ]);

        $nombre = trim($datos['nombre']);

        // Si ya existe uno con ese nombre exacto, se devuelve ese en vez de
        // duplicarlo: el operador escribio lo mismo dos veces, no quiere dos
        // productos iguales en el catalogo.
        $existente = Item::where('description', $nombre)->first();

        if ($existente) {
            return response()->json([
                'success' => true,
                'nuevo'   => false,
                'message' => 'Ese producto ya estaba en el catálogo.',
                'item'    => $this->itemParaBuscador($existente),
            ]);
        }

        $item = new Item();
        $item->item_type_id                    = '01';
        $item->unit_type_id                    = Item::SERVICE_UNIT_TYPE;
        $item->currency_type_id                = 'PEN';
        // `description` es el NOMBRE del producto en este ERP; `name` es el
        // texto corto de los comprobantes y va nulo como en el resto.
        $item->description                     = $nombre;
        $item->internal_id                     = $this->siguienteCodigoInterno();
        $item->sale_unit_price                 = round((float) $datos['precio'], 2);
        $item->purchase_unit_price             = 0;
        $item->sale_affectation_igv_type_id    = '10';
        $item->purchase_affectation_igv_type_id = '10';
        $item->stock                           = 0;
        $item->save();

        return response()->json([
            'success' => true,
            'nuevo'   => true,
            'message' => 'Producto creado como servicio (no controla stock).',
            'item'    => $this->itemParaBuscador($item),
        ]);
    }

    /**
     * El siguiente `internal_id` libre.
     *
     * Se respeta el ANCHO del ultimo codigo en vez de imponer uno fijo. Con
     * relleno a cinco digitos, un catalogo cuyo maximo es «1415» recibiria
     * «01416», que ordenado como texto va ANTES que todos los demas — y el
     * codigo interno es justo por donde el operador busca y ordena.
     */
    private function siguienteCodigoInterno(): string
    {
        $ultimo   = (string) Item::max('internal_id');
        $siguiente = (string) (((int) $ultimo) + 1);

        // Solo se rellena si el catalogo ya usaba relleno y el numero sigue
        // cabiendo: al pasar de 999 a 1000 el codigo crece, y esta bien.
        return strlen($siguiente) < strlen($ultimo)
            ? str_pad($siguiente, strlen($ultimo), '0', STR_PAD_LEFT)
            : $siguiente;
    }

    /**
     * El item con la MISMA forma que devuelve el buscador, para que la
     * pantalla lo agregue sin distinguir de donde vino.
     */
    private function itemParaBuscador(Item $item): array
    {
        return [
            'id'        => $item->id,
            'name'      => trim((string) $item->description) ?: 'Producto',
            'code'      => $item->internal_id,
            'price'     => (float) $item->sale_unit_price,
            'is_set'    => false,
            // null = sin control de stock. No es cero: cero significaria que se
            // agoto, y esto simplemente no se inventaria.
            'available' => null,
            'variants'  => [],
        ];
    }

    /**
     * Resuelve los datos del cliente a partir del documento, para el alta manual.
     *
     * Primero la cartera y SOLO despues el servicio externo, por dos razones:
     * el cliente propio trae telefono y correo -que es justo lo que el alta
     * necesita y RENIEC no da-, y cada consulta externa se paga.
     *
     * La busqueda en cartera usa la MISMA regla que `Person::resolveCustomer()`
     * (numero en digitos, tipo customers). Si buscara de otra forma, la pantalla
     * mostraria un cliente y el guardado enlazaria otro.
     *
     * No encontrar nada NO es un error: el cliente nuevo es el caso normal.
     */
    public function searchCustomer(Request $request)
    {
        $doc = preg_replace('/\D+/', '', (string) $request->input('document_number', ''));

        if ($doc === '') {
            return response()->json(['found' => false]);
        }

        $persona = \App\Models\Tenant\Person::where('number', $doc)
            ->where('type', 'customers')
            ->first();

        if ($persona) {
            return response()->json([
                'found'    => true,
                'source'   => 'cartera',
                'customer' => [
                    'name'  => $persona->name,
                    'phone' => $persona->telephone,
                    'email' => $persona->email,
                ],
            ]);
        }

        // 8 = DNI, 11 = RUC. Otra longitud no es consultable y preguntar por
        // ella solo gasta una llamada de la API.
        $tipo = strlen($doc) === 11 ? 'ruc' : (strlen($doc) === 8 ? 'dni' : null);

        if (!$tipo) {
            return response()->json(['found' => false]);
        }

        try {
            $res = (new \Modules\ApiPeruDev\Data\ServiceData())->service($tipo, $doc);
        } catch (\Throwable $e) {
            // Sin token, sin red o servicio caido. El alta sigue siendo manual:
            // se avisa, pero no se bloquea al operador.
            \Illuminate\Support\Facades\Log::warning(
                'Consulta ' . $tipo . ' ' . $doc . ' fallida: ' . $e->getMessage()
            );

            return response()->json([
                'found'   => false,
                'message' => 'No se pudo consultar ' . strtoupper($tipo) . '. Escribe los datos a mano.',
            ]);
        }

        if (empty($res['success'])) {
            return response()->json([
                'found'   => false,
                'message' => $res['message'] ?? null,
            ]);
        }

        return response()->json([
            'found'    => true,
            'source'   => $tipo,
            'customer' => [
                'name'  => $res['data']['name'] ?? null,
                'phone' => null,
                'email' => null,
            ],
        ]);
    }

    public function storeManual(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer',
            'customer' => 'required|array',
            'customer.name' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $channel     = SalesChannel::findOrFail($request->channel_id);
        $warehouseId = $request->warehouse_id ?? $channel->warehouse_id;

        // Stock. Hasta ahora este alta NO lo miraba: se podia vender por
        // telefono lo que el ecommerce acababa de vender, y no se descubria
        // hasta preparar el pedido.
        //
        // Se comprueban TODAS las lineas antes de crear nada y se avisa de
        // todas juntas: corregir un pedido de ocho productos de uno en uno es
        // la forma mas lenta de hacerlo.
        $lineas = collect($request->items)->map(fn ($i) => [
            'item_id'    => (int) ($i['item_id'] ?? 0),
            'variant_id' => $i['variant_id'] ?? null,
            'quantity'   => (float) ($i['quantity'] ?? 0),
        ])->all();

        $stock = app(\App\Services\Tenant\StockReservation::class);

        if ($problemas = $stock->problemas($lineas, $warehouseId)) {
            return response()->json([
                'success'  => false,
                'message'  => count($problemas) === 1
                    ? $problemas[0]
                    : 'No se puede crear el pedido con el stock actual.',
                'problemas' => $problemas,
            ], 422);
        }

        $calculo = $this->construirLineas($request->items);

        // Enlazar el pedido a la cartera. Faltaba: `$persona` se usaba mas
        // abajo sin existir, asi que TODO pedido manual nacia con
        // `person_id` null y el aviso de «no quedo enlazado» saltaba aunque
        // el operador hubiera escrito el documento.
        $persona = \App\Models\Tenant\Person::resolveCustomer(
            $request->customer['document_number'] ?? null,
            $request->customer['name'] ?? null,
            [
                'telephone' => $request->customer['phone'] ?? null,
                'email'     => $request->customer['email'] ?? null,
            ]
        );

        $order = Order::create([
            'external_id' => \Illuminate\Support\Str::uuid(),
            'person_id' => $persona?->id,
            'customer' => [
                'apellidos_y_nombres_o_razon_social' => $request->customer['name'],
                'correo_electronico' => $request->customer['email'] ?? null,
                'telefono' => $request->customer['phone'] ?? null,
                'direccion' => $request->customer['address'] ?? null,
                'numero_documento' => $request->customer['document_number'] ?? null,
            ],
            'items' => $calculo['items'],
            'total' => $calculo['total'],
            'subtotal' => $calculo['subtotal'],
            'total_discount' => $calculo['descuento'],
            'discounts' => $calculo['desglose'],
            'reference_payment' => $request->reference_payment ?? $channel->name,
            'status_order_id' => 1, // Pendiente
            'channel_id' => $channel->id,
            'external_order_ref' => $request->external_order_ref, // Nro pedido Saga/ML
            'marketplace_notes' => $request->marketplace_notes,
            'warehouse_id' => $warehouseId,
            'seller_id' => auth()->id(),
        ]);

        // Reservar DESPUES de crear el pedido: si la creacion falla, no queda
        // stock comprometido contra un pedido que no existe.
        $stock->reservar($lineas, $warehouseId);

        return response()->json([
            'success' => true,
            'message' => "Pedido #{$order->id} creado desde {$channel->name}",
            'order' => $order,
            // Para que la pantalla pueda decir si el cliente quedo enlazado a
            // la cartera o el pedido nacio suelto por falta de documento.
            'person' => $persona ? ['id' => $persona->id, 'name' => $persona->name] : null,
        ]);
    }

    /**
     * Estados en los que un pedido todavia se puede editar.
     *
     * 1 pago pendiente · 2 pago verificado · 3 en preparacion. A partir de
     * «enviado» el paquete ya salio: cambiar sus lineas no cambiaria lo que el
     * cliente va a recibir, solo mentiria sobre ello. Cancelado y entregado son
     * finales.
     */
    private const ESTADOS_EDITABLES = [1, 2, 3];

    /**
     * Estados en los que ademas se pueden cambiar las LINEAS.
     *
     * Mas corto que ESTADOS_EDITABLES a proposito. En «en preparacion» (3) el
     * pedido ya tiene stock comprometido y, casi siempre, el rotulo impreso:
     * cambiar los productos ahi deja una etiqueta que dice una cosa y una caja
     * que lleva otra, y el rotulo no se marca solo como desactualizado.
     *
     * Corregir el telefono o el nombre del cliente en preparacion SI se
     * permite: no cambia lo que va dentro de la caja.
     */
    private const ESTADOS_LINEAS_EDITABLES = [1, 2];

    /** ¿Se pueden cambiar los productos de este pedido? */
    private function lineasEditables(Order $order): bool
    {
        return in_array((int) $order->status_order_id, self::ESTADOS_LINEAS_EDITABLES, true);
    }

    /**
     * Un pedido, con lo justo para volver a abrirlo en el formulario.
     *
     * La ruta `orders/record/{order}` existia desde hace tiempo apuntando a un
     * metodo que NO existia: cualquier llamada reventaba. Se implementa ahora
     * porque la edicion la necesita.
     *
     * No devuelve el pedido en crudo: `items` es un JSON historico y sus claves
     * han cambiado con los años (`item_id` o `id`, `unit_price` o
     * `sale_unit_price`). Aqui se normaliza para que el formulario no tenga que
     * conocer esa arqueologia.
     */
    /**
     * Emite la nota de venta del pedido a mano.
     *
     * La NV ya se generaba sola en tres sitios (checkout en efectivo, captura de
     * Culqi y el cambio de estado a «pago verificado»), pero no había ninguna
     * puerta para el resto: un pedido cuyo estado se movió antes de tener los
     * datos, o uno cargado a mano, se quedaba sin nota de venta y sin forma de
     * conseguirla. Y sin nota de venta no hay comprobante ni guía, porque todo
     * el grafo de documentos cuelga de ella.
     *
     * NO se reimplementa nada: llama a `OrderToSaleNoteService`, que es el
     * único sitio que sabe armar la NV (ítems con IGV, descuentos, pagos, serie
     * y correlativo) y ya es idempotente con `lockForUpdate`.
     */
    public function generarNotaVenta(Order $order)
    {
        $docs = OrderDocuments::for($order->loadMissing([
            'sale_note', 'sale_note.documents', 'shipment',
        ]));

        if (!$docs->aplica(OrderDocuments::NOTA_VENTA)) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido no documenta una venta: no tiene productos ni importe.',
            ], 422);
        }

        if ($motivo = $docs->motivoBloqueo(OrderDocuments::NOTA_VENTA)) {
            return response()->json(['success' => false, 'message' => $motivo], 422);
        }

        // `OrderToSaleNoteService` marca la nota como PAGADA sin preguntar
        // (`paid = true`, `total_canceled = true`). Automáticamente eso es
        // correcto, porque solo se dispara con el cobro hecho. A mano no: emitir
        // desde un pedido en «pago pendiente» crearía una nota que afirma un
        // cobro que no ocurrió, y eso descuadra la caja.
        if ((int) $order->status_order_id < 2) {
            return response()->json([
                'success' => false,
                'message' => 'El pedido figura con el pago pendiente. Verifica el pago antes '
                           . 'de emitir la nota de venta: se emite como cancelada.',
            ], 422);
        }

        // La serie es el fallo silencioso más probable: sin ella el servicio
        // devuelve null y solo deja rastro en el log.
        $establecimiento = Establishment::first();
        $serie = $establecimiento
            ? Series::where('establishment_id', $establecimiento->id)
                    ->where('document_type_id', '80')->first()
            : null;

        if (!$serie) {
            return response()->json([
                'success' => false,
                'message' => 'No hay una serie de NOTA DE VENTA (80) configurada en el '
                           . 'establecimiento. Créala antes de emitir.',
            ], 422);
        }

        $nota = app(\App\Services\Tenant\OrderToSaleNoteService::class)->generate($order);

        if (!$nota) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar la nota de venta. El motivo quedó en el '
                           . 'registro de errores del sistema.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nota de venta ' . $nota->number_full . ' emitida.',
            'sale_note_id' => $nota->id,
        ]);
    }

    /**
     * Corrige con qué documento se factura el pedido y sus datos tributarios.
     *
     * Hasta ahora esto lo decidía el COMPRADOR en el checkout y nadie podía
     * cambiarlo. El caso que lo obliga es cotidiano: el cliente marcó «boleta»
     * y después dejó un RUC, o el pedido vino de un canal que no manda el dato.
     * Sin esta corrección, la única salida era emitir mal y anular con nota de
     * crédito.
     *
     * NO emite nada. Guarda la elección y la deja auditada con autor y fecha.
     * `orders.purchase` no se toca: es lo que el comprador pidió, y sobrescribirlo
     * borraría la única prueba de ello.
     */
    public function tipoDocumento(Request $request, Order $order)
    {
        // Tenant con `tenancy:migrate` atrasado: mejor decirlo que fallar con un
        // 1054 que el operador no puede interpretar.
        if (!\Illuminate\Support\Facades\Schema::connection('tenant')
                ->hasColumn('orders', 'billing_document_type_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Este tenant todavía no tiene la corrección de comprobante instalada.',
            ], 422);
        }

        if ((int) $order->status_order_id === 5) {
            return response()->json([
                'success' => false,
                'message' => 'El pedido está anulado.',
            ], 422);
        }

        $datos = $request->validate([
            'document_type_id' => ['required', 'in:' . implode(',', BillingDocumentResolver::ELEGIBLES)],
            // El documento se valida por FORMA, no por tipo: 8 dígitos es DNI,
            // 11 es RUC y 9-12 cubre el carné de extranjería. Qué combinación
            // es válida para el comprobante lo decide el resolutor, que es
            // donde viven las reglas de SUNAT.
            'numero'           => ['nullable', 'string', 'regex:/^\d{8,12}$/'],
            'nombre'           => ['nullable', 'string', 'max:255'],
        ], [
            'numero.regex' => 'El documento debe tener entre 8 y 12 dígitos, sin letras ni guiones.',
        ]);

        // Un comprobante ya emitido no se corrige cambiando un campo: se anula
        // con nota de crédito. Dejar tocar esto sería prometer algo que no pasa.
        $docs = OrderDocuments::for($order->loadMissing([
            'sale_note', 'sale_note.documents', 'document', 'marketplaceOrder', 'marketplaceOrder.document',
        ]));

        foreach ([OrderDocuments::BOLETA, OrderDocuments::FACTURA] as $tipo) {
            if ($docs->tiene($tipo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido ya tiene comprobante emitido. '
                               . 'Para cambiarlo hay que anularlo con nota de crédito.',
                ], 422);
            }
        }

        $order->billing_document_type_id = $datos['document_type_id'];

        // Solo se guardan los campos que el operador escribió. Un `billing_customer`
        // con las claves vacías pisaría los datos buenos del checkout.
        $corregido = array_filter([
            'numero' => isset($datos['numero']) ? preg_replace('/\D+/', '', $datos['numero']) : null,
            'nombre' => isset($datos['nombre']) ? trim($datos['nombre']) : null,
        ], fn ($v) => $v !== null && $v !== '');

        $order->billing_customer = $corregido ?: null;
        $order->billing_set_by   = auth()->id();
        $order->billing_set_at   = now();
        $order->save();

        $propuesta = (new BillingDocumentResolver())->resolve($order->refresh());

        return response()->json([
            'success'   => true,
            'message'   => $propuesta['puede_emitir']
                ? 'Se emitirá ' . mb_strtolower($propuesta['nombre']) . '.'
                : 'Guardado, pero todavía falta ' . implode(' y ', $propuesta['faltan']) . '.',
            'propuesta' => $propuesta,
        ]);
    }

    public function record(Order $order)
    {
        $cliente = (array) ($order->customer ?? []);

        $lineas = collect(is_array($order->items) ? $order->items : [])
            ->map(function ($fila) {
                $fila = (array) $fila;
                $itemId = $fila['item_id'] ?? $fila['id'] ?? null;

                return $itemId ? [
                    'item_id'    => (int) $itemId,
                    'variant_id' => $fila['variant_id'] ?? null,
                    'name'       => $fila['description'] ?? 'Producto',
                    'code'       => $fila['internal_id'] ?? null,
                    'quantity'   => (float) ($fila['quantity'] ?? 1),
                    'unit_price' => (float) ($fila['unit_price'] ?? $fila['sale_unit_price'] ?? 0),
                ] : null;
            })
            ->filter()
            ->values();

        return response()->json([
            'id'         => $order->id,
            'channel_id' => $order->channel_id,
            'status_order_id' => (int) $order->status_order_id,
            // Que la pantalla sepa si puede editar sin repetir la regla.
            'editable'   => in_array((int) $order->status_order_id, self::ESTADOS_EDITABLES, true),
            // Distinto de `editable`: en preparacion se corrige el cliente
            // pero no los productos. El formulario bloquea las lineas con esto.
            'lines_editable' => $this->lineasEditables($order),
            'customer'   => [
                'name'            => $cliente['apellidos_y_nombres_o_razon_social'] ?? ($cliente['name'] ?? ''),
                'document_number' => $cliente['numero_documento'] ?? ($cliente['numero'] ?? ''),
                'phone'           => $cliente['telefono'] ?? ($cliente['phone'] ?? ''),
                'email'           => $cliente['correo_electronico'] ?? ($cliente['email'] ?? ''),
            ],
            'items'      => $lineas,
            'total'      => (float) $order->total,
        ]);
    }


    /**
     * Edicion de un pedido: cliente y lineas.
     *
     * ── Las tres reglas que gobiernan esto ────────────────────────────────
     *
     * 1. LOS PAGOS NO SE TOCAN. Nunca. Cambiar el total recalcula el saldo, que
     *    es una resta; el historico de cobros es un hecho y no se reescribe.
     *    Si el total baja por debajo de lo cobrado, el saldo queda en cero y la
     *    diferencia se ve comparando ambos — no se inventa una devolucion.
     *
     * 2. El stock se SUELTA y se vuelve a reservar. No se calcula un delta por
     *    linea: con variantes, packs y cambios de almacen, el delta tiene mas
     *    formas de salir mal que de salir bien. Todo va en una transaccion, asi
     *    que si la validacion falla se restaura la reserva anterior sola.
     *
     * 3. No se editan los datos de envio. Tienen su propia pantalla y su propia
     *    bitacora; duplicarlos aqui seria una segunda forma de cambiarlos.
     */
    public function updateManual(Request $request, Order $order)
    {
        if (!in_array((int) $order->status_order_id, self::ESTADOS_EDITABLES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido ya no se puede editar: figura como «'
                    . (optional($order->status_order)->description ?: 'cerrado') . '».',
            ], 422);
        }

        $request->validate([
            'customer'         => 'required|array',
            'customer.name'    => 'required|string',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        // En preparacion solo se corrigen los datos del cliente. Se comprueba
        // en el SERVIDOR y no solo bloqueando el formulario: deshabilitar un
        // campo en pantalla no es un control, una peticion a mano se lo salta.
        if (!$this->lineasEditables($order)) {
            if ($this->lineasCambian($order, $request->items)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido ya esta en preparacion: puedes corregir los '
                        . 'datos del cliente, pero no los productos. Si hay que cambiar el '
                        . 'contenido, anulalo y crea uno nuevo.',
                ], 422);
            }

            $this->guardarCliente($order, $request);

            return response()->json([
                'success' => true,
                'message' => "Datos del cliente del pedido #{$order->id} actualizados.",
                'order'   => $order->fresh(),
                'summary' => $order->getPaymentSummary(),
            ]);
        }

        $warehouseId = $order->warehouse_id;
        $stock       = app(\App\Services\Tenant\StockReservation::class);
        $ordenes     = app(\App\Services\Tenant\OrderService::class);

        $lineas = collect($request->items)->map(fn ($i) => [
            'item_id'    => (int) ($i['item_id'] ?? 0),
            'variant_id' => $i['variant_id'] ?? null,
            'quantity'   => (float) ($i['quantity'] ?? 0),
        ])->all();

        $problemas = [];

        try {
            DB::connection('tenant')->transaction(function () use (
                $order, $request, $lineas, $stock, $ordenes, $warehouseId, &$problemas
            ) {
                // Soltar lo que este pedido tenia cogido ANTES de validar: si no,
                // su propia reserva cuenta contra el disponible y subir de 2 a 3
                // unidades parece imposible teniendo stock de sobra.
                $ordenes->releaseCommittedStock($order);

                if ($problemas = $stock->problemas($lineas, $warehouseId)) {
                    // La excepcion revierte la liberacion: el pedido se queda
                    // exactamente como estaba, con su reserva intacta.
                    throw new \RuntimeException('stock');
                }

                $calculo = $this->construirLineas($request->items);

                $persona = \App\Models\Tenant\Person::resolveCustomer(
                    $request->customer['document_number'] ?? null,
                    $request->customer['name'] ?? null,
                    [
                        'telephone' => $request->customer['phone'] ?? null,
                        'email'     => $request->customer['email'] ?? null,
                    ]
                );

                $order->fill([
                    'person_id' => $persona?->id ?: $order->person_id,
                    'customer'  => [
                        'apellidos_y_nombres_o_razon_social' => $request->customer['name'],
                        'correo_electronico' => $request->customer['email'] ?? null,
                        'telefono'           => $request->customer['phone'] ?? null,
                        'numero_documento'   => $request->customer['document_number'] ?? null,
                    ],
                    'items'          => $calculo['items'],
                    'total'          => $calculo['total'],
                    'subtotal'       => $calculo['subtotal'],
                    'total_discount' => $calculo['descuento'],
                    'discounts'      => $calculo['desglose'],
                ])->save();

                $stock->reservar($lineas, $warehouseId);
            });
        } catch (\RuntimeException $e) {
            if ($problemas) {
                return response()->json([
                    'success'   => false,
                    'message'   => count($problemas) === 1
                        ? $problemas[0]
                        : 'No se puede guardar el pedido con el stock actual.',
                    'problemas' => $problemas,
                ], 422);
            }

            throw $e;
        }

        $order->refresh();

        return response()->json([
            'success' => true,
            'message' => "Pedido #{$order->id} actualizado.",
            'order'   => $order,
            // El saldo se deriva del total nuevo; los cobros no se tocaron.
            'summary' => $order->getPaymentSummary(),
        ]);
    }

    /**
     * ¿Las lineas que llegan son otras que las guardadas?
     *
     * Compara el resultado de `construirLineas` contra lo que hay en el
     * pedido, no el crudo del formulario: asi un precio que el usuario no
     * puede cambiar —y que el servidor sustituye por el del catalogo— no
     * cuenta como cambio y no bloquea una correccion legitima del cliente.
     */
    private function lineasCambian(Order $order, $filas): bool
    {
        $huella = function (array $lineas) {
            return collect($lineas)->map(function ($l) {
                $l = (array) $l;

                return [
                    (int) ($l['item_id'] ?? $l['id'] ?? 0),
                    $l['variant_id'] ?? null,
                    (float) ($l['quantity'] ?? 0),
                    round((float) ($l['unit_price'] ?? $l['sale_unit_price'] ?? 0), 2),
                    round((float) ($l['discount'] ?? 0), 2),
                ];
            })->sortBy(fn ($x) => $x[0] . '|' . $x[1])->values()->all();
        };

        $nuevas  = $this->construirLineas(is_array($filas) ? $filas : []);
        $actuales = is_array($order->items) ? $order->items : [];

        return $huella($nuevas['items']) !== $huella($actuales);
    }

    /** Cliente del pedido, sin tocar lineas, totales ni stock. */
    private function guardarCliente(Order $order, Request $request): void
    {
        $persona = \App\Models\Tenant\Person::resolveCustomer(
            $request->customer['document_number'] ?? null,
            $request->customer['name'] ?? null,
            [
                'telephone' => $request->customer['phone'] ?? null,
                'email'     => $request->customer['email'] ?? null,
            ]
        );

        $order->fill([
            'person_id' => $persona?->id ?: $order->person_id,
            'customer'  => [
                'apellidos_y_nombres_o_razon_social' => $request->customer['name'],
                'correo_electronico' => $request->customer['email'] ?? null,
                'telefono'           => $request->customer['phone'] ?? null,
                'numero_documento'   => $request->customer['document_number'] ?? null,
            ],
        ])->save();
    }

    public function updateStatusOrders(Request $request)
    {
      // NOTA: `exists:orders,id` removido — en multi-tenant la regla usa la
      // conexión default (system) donde `orders` no existe, generando un 500.
      // El `findOrFail` de abajo ya valida la existencia en la conexión tenant.
      $validated = $request->validate([
        'record.id' => 'required|integer|min:1',
        'record.status_order_id' => 'required|integer|in:1,2,3,4,5,6',
      ]);

      $orderId = (int) data_get($validated, 'record.id');
      $statusId = (int) data_get($validated, 'record.status_order_id');

      try {
          $order = Order::findOrFail($orderId);
      } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
          return response()->json(['message' => "Pedido #{$orderId} no encontrado"], 404);
      }

      $currentStatusId = (int) $order->status_order_id;
      if ($currentStatusId === $statusId) {
        return [
          'message' => 'El pedido ya se encuentra en ese estado'
        ];
      }

      // Delegamos TODAS las reglas de transición (mapa + guard de payment_status +
      // reglas por rol) al OrderPolicy::transitionTo. Si la transición es inválida
      // lanza InvalidOrderTransitionException con mensaje específico.
      try {
          $this->authorize('transitionTo', [$order, $statusId]);
      } catch (\App\Exceptions\InvalidOrderTransitionException $e) {
          return response()->json(['message' => $e->getMessage()], 422);
      } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
          return response()->json(['message' => $e->getMessage() ?: 'No autorizado para esta acción'], 403);
      }

      /** @var OrderService $orderService */
      $orderService = app(OrderService::class);
      $discountItems = $request->discount ?? [];

      try {
      // ── 2 → 3  (En preparación) ──────────────────────────────────────────
      // Flujo nuevo: solo marca prepared_at, no toca stock (ya reservado en checkout).
      // Retrocompat: si el UI envía `discount` (legacy), se despacha físico aquí mismo
      // y queda también marcado dispatched_at para evitar doble descuento en 3→4.
      if ($statusId === 3) {
        if (!empty($discountItems)) {
          $orderService->processEcommerceDispatch($order, $discountItems);
          $this->logStatusTransition($order->fresh(), $currentStatusId, 3, ['discount' => $discountItems, 'mode' => 'legacy']);
          $this->sendWhatsAppStatusNotification($order->fresh(), 3);
          return ['message' => 'Estatus y Stock actualizado'];
        }

        $orderService->prepareEcommerceOrder($order);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 3, ['mode' => 'prepare']);
        $this->sendWhatsAppStatusNotification($order->fresh(), 3);
        return ['message' => 'Pedido marcado como en preparación'];
      }

      // ── 3 → 4  (Despachado / Enviado) ────────────────────────────────────
      // Descuento físico real. Idempotente: si ya se hizo en 2→3 (legacy),
      // solo actualiza el estado sin volver a descontar stock.
      if ($statusId === 4) {
        $orderService->dispatchEcommerceOrder($order, $discountItems);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 4, ['discount' => $discountItems]);
        $this->sendWhatsAppStatusNotification($order->fresh(), 4);

        \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.status_changed', [
            'order_id'  => $order->id,
            'status_id' => 4,
            'total'     => $order->total,
        ]);

        return ['message' => 'Pedido despachado'];
      }

      // ── 4 → 6  (Entregado) ───────────────────────────────────────────────
      if ($statusId === 6) {
        $orderService->markEcommerceDelivered($order);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 6, []);
        $this->sendWhatsAppStatusNotification($order->fresh(), 6);

        \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.status_changed', [
            'order_id'  => $order->id,
            'status_id' => 6,
            'total'     => $order->total,
        ]);

        return ['message' => 'Pedido entregado'];
      }

      // ── * → 5  (Cancelado) ───────────────────────────────────────────────
      // Libera stock_committed si el pedido todavía no fue despachado.
      if ($statusId === 5) {
        $reason = (string) $request->input('cancel_reason', '');
        $orderService->cancelEcommerceOrder($order, $reason);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 5, ['reason' => $reason]);
        $this->sendWhatsAppStatusNotification($order->fresh(), 5);

        \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.cancelled', [
            'order_id'  => $order->id,
            'status_id' => 5,
            'total'     => $order->total,
            'reason'    => $reason,
        ]);

        return ['message' => 'Pedido cancelado'];
      }

      // ── 1 → 2  (Pago verificado) ─────────────────────────────────────────
      // Guardar pagos + actualizar estado + generar NV en UNA sola transacción.
      // Antes los 3 pasos iban en secuencia sin atomicidad: si la generación de NV
      // fallaba, el status quedaba en 2 sin comprobante. Ahora si algo falla,
      // todo se revierte y la orden permanece en 1 (el admin puede reintentar).
      if ($statusId === 2) {
          DB::transaction(function () use ($order, $orderId, $statusId, $request) {
              $payments = $request->input('payments', []);
              if (is_array($payments) && !empty($payments)) {
                  $this->saveOrderPayments($order, $payments);
              }

              Order::where('id', $orderId)->update(['status_order_id' => $statusId]);
              $order->status_order_id = $statusId;

              $autoSaleNoteService = app(\App\Services\Tenant\OrderToSaleNoteService::class);
              $autoSaleNoteService->generate($order);
          });
      } else {
          Order::where('id', $orderId)->update(['status_order_id' => $statusId]);
          $order->status_order_id = $statusId;
      }

      $this->logStatusTransition($order, $currentStatusId, $statusId, []);
      $this->sendWhatsAppStatusNotification($order, $statusId);

      \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.status_changed', [
          'order_id'  => $order->id,
          'status_id' => $statusId,
          'total'     => $order->total,
      ]);

      return [
        'message' => 'Estatus actualizado'
      ];
      } catch (\App\Exceptions\InsufficientStockException $e) {
          return response()->json(['message' => $e->getMessage()], 422);
      } catch (\App\Exceptions\InvalidOrderTransitionException $e) {
          return response()->json(['message' => $e->getMessage()], 422);
      } catch (\Throwable $e) {
          \Log::error('[updateStatusOrders] unexpected error', [
              'order_id'   => $orderId,
              'status_id'  => $statusId,
              'from'       => $currentStatusId,
              'discount'   => $discountItems,
              'exception'  => get_class($e),
              'message'    => $e->getMessage(),
              'file'       => $e->getFile() . ':' . $e->getLine(),
              'trace'      => collect($e->getTrace())->take(8)->map(fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''))->all(),
          ]);
          return response()->json([
              'message' => 'Error al actualizar el estado: ' . $e->getMessage(),
              'exception' => get_class($e),
          ], 500);
      }
    }

    /**
     * GET /orders/payment-catalogs
     * Retorna los catálogos necesarios para el modal de "Verificar pago":
     *   - payment_method_types: métodos SUNAT (01=Efectivo, 02=Crédito, etc.)
     *   - payment_destinations: caja + cuentas bancarias activas
     *   - card_brands: marcas de tarjeta (Visa, Mastercard, etc.)
     *
     * Reutiliza el mismo helper `FinanceTrait::getPaymentDestinations()` que
     * usa el form de SaleNote para que el comportamiento sea 1:1.
     */
    public function paymentCatalogs()
    {
        $payment_method_types = PaymentMethodType::all()->map(function ($row) {
            return [
                'id'          => $row->id,
                'description' => $row->description,
            ];
        });

        $payment_destinations = $this->getPaymentDestinations();
        $card_brands = CardBrand::all()->map(function ($row) {
            return [
                'id'          => $row->id,
                'description' => $row->description,
            ];
        });

        return response()->json([
            'payment_method_types' => $payment_method_types,
            'payment_destinations' => $payment_destinations,
            'card_brands'          => $card_brands,
        ]);
    }

    /**
     * Guarda los pagos de un pedido (reemplazando los existentes si aplica).
     * Usa la misma estructura que `SaleNoteController::savePayments()`.
     *
     * @param  Order  $order
     * @param  array  $payments Array de pagos con estructura:
     *   [{date_of_payment, payment_method_type_id, has_card, card_brand_id,
     *     reference, change, payment, payment_destination_id}, ...]
     */
    private function saveOrderPayments(Order $order, array $payments): void
    {
        // Reemplaza los pagos existentes (patrón consistente con SaleNote)
        $order->payments()->delete();

        foreach ($payments as $row) {
            if (empty($row['payment_method_type_id'])) continue;

            $order->payments()->create([
                'date_of_payment'         => $row['date_of_payment'] ?? now()->toDateString(),
                'payment_method_type_id'  => $row['payment_method_type_id'],
                'has_card'                => (bool) ($row['has_card'] ?? false),
                'card_brand_id'           => $row['card_brand_id'] ?? null,
                'reference'               => $row['reference'] ?? null,
                'change'                  => $row['change'] ?? null,
                'payment'                 => (float) ($row['payment'] ?? 0),
                'payment_destination_id'  => (string) ($row['payment_destination_id'] ?? 'cash'),
            ]);
        }
    }

    /**
     * GET /orders/{order}/status-logs
     * Devuelve el historial de transiciones del pedido para renderizar timeline.
     */
    public function statusLogs($orderId)
    {
        $order = Order::findOrFail((int) $orderId);

        $labels = [
            1 => 'Pendiente',
            2 => 'Pago verificado',
            3 => 'En preparación',
            4 => 'Despachado',
            5 => 'Cancelado',
            6 => 'Entregado',
        ];

        $logs = OrderStatusLog::where('order_id', $order->id)
            ->with('actor:id,name,email')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($log) use ($labels) {
                return [
                    'id'               => $log->id,
                    'from_status'      => $log->from_status,
                    'from_label'       => $labels[$log->from_status] ?? null,
                    'to_status'        => $log->to_status,
                    'to_label'         => $labels[$log->to_status] ?? null,
                    'payment_status'   => $log->payment_status,
                    'actor'            => $log->actor ? [
                        'id'    => $log->actor->id,
                        'name'  => $log->actor->name,
                        'email' => $log->actor->email,
                    ] : null,
                    'payload'          => $log->payload,
                    'created_at'       => $log->created_at?->format('Y-m-d H:i:s'),
                    'created_at_human' => $log->created_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'order_id' => $order->id,
            'current_status' => [
                'id'    => (int) $order->status_order_id,
                'label' => $labels[(int) $order->status_order_id] ?? null,
            ],
            'payment_status' => $order->payment_status,
            'phases' => [
                'paid_at'       => optional($order->paid_at)->format('Y-m-d H:i:s'),
                'prepared_at'   => optional($order->prepared_at)->format('Y-m-d H:i:s'),
                'dispatched_at' => optional($order->dispatched_at)->format('Y-m-d H:i:s'),
                'delivered_at'  => optional($order->delivered_at)->format('Y-m-d H:i:s'),
            ],
            'logs' => $logs,
            // Historial UNIFICADO: una sola línea de tiempo con lo comercial y
            // lo logístico. Las tablas siguen separadas —fusionarlas sería una
            // migración destructiva— y se unifica solo la lectura.
            'timeline' => $this->buildOrderTimeline($order, $logs, $labels),
        ]);
    }

    /**
     * Línea de tiempo del pedido: estados, envío, impresiones y bitácora.
     *
     * Ordenada por fecha real del hecho. Cada entrada lleva `source` para que la
     * interfaz pueda distinguir de dónde salió sin volver a preguntar.
     */
    private function buildOrderTimeline(Order $order, $logs, array $labels): array
    {
        $events = [];

        // 1. Nacimiento del pedido.
        $events[] = [
            'at'     => optional($order->created_at)->format('Y-m-d H:i:s'),
            'rank'   => 0,
            'source' => 'order',
            'icon'   => 'cart',
            'title'  => 'Pedido creado',
            'detail' => $order->channel ? ('Canal: ' . $order->channel->name) : null,
        ];

        // 2. Cambios de estado comercial.
        foreach ($logs as $log) {
            $esSync = ($log['payload']['source'] ?? null) === 'shipment';
            $events[] = [
                'at'     => $log['created_at'],
                // Un cambio disparado por logistica es CONSECUENCIA del evento
                // del envio: en un empate de segundo tiene que ir despues, o la
                // historia se lee al reves ("Despachado" antes de "Envio
                // configurado").
                'rank'   => $esSync ? 4 : 1,
                'source' => $esSync ? 'sync' : 'order',
                'icon'   => 'status',
                'title'  => 'Pedido: ' . ($log['to_label'] ?? $log['to_status']),
                'detail' => $log['actor']['name'] ?? (($log['payload']['source'] ?? null) === 'shipment'
                    ? 'Automático desde el envío ' . ($log['payload']['shipment_code'] ?? '')
                    : null),
            ];
        }

        // 3. Bitácora logística + impresiones del envío.
        $shipment = ShippingRequest::moduleInstalled() ? $order->shipment : null;
        if ($shipment) {
            $events[] = [
                'at'     => optional($shipment->created_at)->format('Y-m-d H:i:s'),
                'rank'   => 2,
                'source' => 'shipment',
                'icon'   => 'truck',
                'title'  => 'Envío configurado · ' . $shipment->delivery_label,
                'detail' => $shipment->shipment_code,
            ];

            foreach ($shipment->auditLogs as $entry) {
                $events[] = [
                    'at'     => optional($entry->created_at)->format('Y-m-d H:i:s'),
                    'rank'   => 3,
                    'source' => 'shipment',
                    'icon'   => $entry->action,
                    'title'  => \App\Models\Tenant\ShippingAuditLog::ACTION_LABELS[$entry->action] ?? $entry->action,
                    'detail' => $entry->notes ?: trim(($entry->old_value ?? '') . ' → ' . ($entry->new_value ?? ''), ' →'),
                    'actor'  => $entry->user_name,
                ];
            }

            foreach ($shipment->printEvents as $print) {
                $events[] = [
                    'at'     => optional($print->created_at)->format('Y-m-d H:i:s'),
                    'rank'   => 3,
                    'source' => 'print',
                    'icon'   => $print->is_reprint ? 'reprint' : 'print',
                    'title'  => $print->is_reprint
                        ? "Reimpresión #{$print->sequence}"
                        : 'Rótulo impreso',
                    'detail' => $print->reason,
                    'actor'  => $print->user_name,
                ];
            }
        }

        // Los eventos sin fecha van al final: no se pueden ordenar y ponerlos
        // al principio daría una cronología falsa.
        //
        // El desempate por `rank` importa de verdad: configurar un envío y su
        // primer asiento caen en el MISMO segundo, y sin él la línea de tiempo
        // los mezclaba en el orden en que se leyeron las tablas.
        usort($events, fn($a, $b) =>
            [($a['at'] ?? '9999'), $a['rank']] <=> [($b['at'] ?? '9999'), $b['rank']]);

        return $events;
    }

    /**
     * Registra una transición de estado en `order_status_logs`.
     * Falla silenciosa (solo log en canal laravel) — el audit trail
     * no debe romper operaciones de negocio.
     */
    private function logStatusTransition(?Order $order, int $from, int $to, array $payload = []): void
    {
        if (!$order) return;
        try {
            OrderStatusLog::create([
                'order_id'       => $order->id,
                'from_status'    => $from,
                'to_status'      => $to,
                'payment_status' => $order->payment_status,
                'actor_id'       => auth()->id(),
                'payload'        => $payload ?: null,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to write OrderStatusLog', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function searchWarehouse(Request $request)
    {
      $product = ItemWarehouse::whereIn('item_id', $request->item_id)->orderBy('item_id')->get();
      return new ItemWarehouseCollection($product);
    }

    private function sendWhatsAppStatusNotification(?Order $order, int $statusId): void
    {
        if (!$order) return;

        $customer = $order->customer ?? [];
        $phone = $customer['telefono'] ?? null;
        $name  = $customer['apellidos_y_nombres_o_razon_social'] ?? 'Cliente';
        $orderId = str_pad($order->id, 6, '0', STR_PAD_LEFT);

        if (!$phone) return;

        $job = match ((int) $statusId) {
            2 => \App\Jobs\SendWhatsAppMessage::text($phone, "¡Hola {$name}! ✅\n\nTu pago para el pedido *#{$orderId}* ha sido *verificado*.\nEstamos preparando tu pedido.\n\n¡Gracias por tu compra!"),
            3 => \App\Jobs\SendWhatsAppMessage::clientDispatched($phone, $name, $orderId),
            4 => \App\Jobs\SendWhatsAppMessage::text($phone, "¡Hola {$name}! 🚚\n\nTu pedido *#{$orderId}* está *en camino*.\n\n¡Pronto lo recibirás!"),
            6 => \App\Jobs\SendWhatsAppMessage::clientDelivered($phone, $name, $orderId),
            5 => \App\Jobs\SendWhatsAppMessage::text($phone, "Hola {$name},\n\nTu pedido *#{$orderId}* ha sido *cancelado*.\nSi tienes dudas, contáctanos.\n\nDisculpa las molestias."),
            default => null,
        };

        if ($job) {
            dispatch($job);
        }
    }
}

