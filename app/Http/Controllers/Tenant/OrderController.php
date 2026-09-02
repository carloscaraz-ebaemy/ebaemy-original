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
     * Consulta ÚNICA de Gestión de Pedidos: comercial + logística.
     *
     * Unifica lo que antes vivía en dos pantallas — los filtros de
     * `OrderController::records()` y los de `ShipmentController::buildListQuery()`.
     * La comparten records(), statusCounts() y stats() para que el número del
     * chip y las filas de la tabla no puedan discrepar.
     */
    private function buildOrdersQuery(Request $request, bool $withRelations = true, bool $withChip = true)
    {
        $query = Order::query()->latest();

        if ($withRelations) {
            $query->with([
                'channel',
                // Estas tres las consumía OrderCollection SIN precargar: eran
                // 3 consultas extra por fila (60 por página de 20).
                'status_order:id,description',
                // Sin recorte de columnas: `number_full` es un accesor que se
                // arma con varias de ellas y un select parcial lo dejaría vacío.
                'sale_note',
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
                ]);
            }

            // Detalle logístico. Eager loading obligatorio: sin esto la columna
            // "Entrega" dispara una consulta por fila (N+1).
            // Condicionado a que el tenant tenga el módulo: sin la tabla, el
            // eager loading tumbaría la pantalla de pedidos entera.
            if (ShippingRequest::moduleInstalled()) {
                $query->with(['shipment', 'shipment.printBatch:id,code,status']);
            }
        }

        $this->applyOrderDateRange($query, $request);
        $this->applyOrderSource($query, $request);
        $this->applyCommercialFilters($query, $request);
        $this->applyLogisticFilters($query, $request);

        if ($withChip) {
            $this->applyOperationalChip($query, $request);
        }

        return $query;
    }

    /** Filtros del lado comercial del pedido. */
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

            if (!$setting) {
                // Sin el módulo de Envíos no hay antigüedad que medir, y la
                // respuesta honesta es "ningún pedido", igual que el resto de
                // filtros logísticos. Antes se ignoraba el filtro en silencio y
                // "vencidos" devolvía TODOS los pedidos, que es justo lo
                // contrario de lo que se preguntó.
                $this->whereShipment($query, fn($s) => $s);
            } else {
                $maxDays = $setting->max_days;
                $k = $aging === 'vencidos' ? $maxDays : max(1, $maxDays - 1);
                $cutoff = ShippingRequest::agingCutoff($k, (bool) ($setting->aging_skip_holidays ?? true))->toDateString();

                $this->whereShipment($query, fn($s) => $s
                    ->whereDate('created_at', '<=', $cutoff)
                    ->whereNotIn('status', ShippingRequest::CLOSED_STATUSES));
            }
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
        return response()->json(
            SalesChannel::active()->get(['id', 'name', 'type', 'code'])
        );
    }

    /**
     * Crear pedido manual desde cualquier canal (Saga, ML, Instagram, WhatsApp, teléfono)
     */
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

        $channel = SalesChannel::findOrFail($request->channel_id);

        // Calcular total
        $total = 0;
        $orderItems = [];
        foreach ($request->items as $itemData) {
            $item = Item::findOrFail($itemData['item_id']);
            $price = $itemData['unit_price'] ?? $item->sale_unit_price;
            $qty = $itemData['quantity'];
            $subtotal = round($price * $qty, 2);
            $total += $subtotal;

            $orderItems[] = [
                'item_id' => $item->id,
                'description' => $item->description,
                'internal_id' => $item->internal_id,
                'quantity' => $qty,
                'unit_price' => $price,
                'sale_unit_price' => $price,
                'subtotal' => $subtotal,
                'variant_id' => $itemData['variant_id'] ?? null,
            ];
        }

        $order = Order::create([
            'external_id' => \Illuminate\Support\Str::uuid(),
            'customer' => [
                'apellidos_y_nombres_o_razon_social' => $request->customer['name'],
                'correo_electronico' => $request->customer['email'] ?? null,
                'telefono' => $request->customer['phone'] ?? null,
                'direccion' => $request->customer['address'] ?? null,
                'numero_documento' => $request->customer['document_number'] ?? null,
            ],
            'items' => $orderItems,
            'total' => $total,
            'reference_payment' => $request->reference_payment ?? $channel->name,
            'status_order_id' => 1, // Pendiente
            'channel_id' => $channel->id,
            'external_order_ref' => $request->external_order_ref, // Nro pedido Saga/ML
            'marketplace_notes' => $request->marketplace_notes,
            'warehouse_id' => $request->warehouse_id ?? $channel->warehouse_id,
            'seller_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pedido #{$order->id} creado desde {$channel->name}",
            'order' => $order,
        ]);
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

