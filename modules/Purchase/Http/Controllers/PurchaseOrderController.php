<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\SearchItemController;
use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Catalogs\OperationType;
use App\Models\Tenant\Configuration;
use App\Traits\OfflineTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Company;
use App\Models\Tenant\Warehouse;
use Illuminate\Support\Str;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Template;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Exception;
use Illuminate\Support\Facades\Mail;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseQuotation;
use Modules\Purchase\Http\Resources\PurchaseOrderCollection;
use Modules\Purchase\Http\Resources\PurchaseOrderResource;
use Modules\Purchase\Mail\PurchaseOrderEmail;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\ChargeDiscountType;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\SystemIscType;
use App\Models\Tenant\Catalogs\AttributeType;
use App\Models\Tenant\PaymentMethodType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use Modules\Sale\Models\SaleOpportunity;
use Modules\Finance\Helpers\UploadFileHelper;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\StockMovement;
use App\Enums\StockMovementTypeEnum;
use App\Services\Tenant\ItemVariantService;

class PurchaseOrderController extends Controller
{

    use StorageDocument;
    use OfflineTrait;

    protected $purchase_order;
    protected $company;

    public function index()
    {
        return view('purchase::purchase-orders.index');
    }


    public function create($id = null)
    {
        $sale_opportunity = null;
        return view('purchase::purchase-orders.form', compact('id','sale_opportunity'));
    }

    public function generate($id)
    {
        $purchase_quotation = PurchaseQuotation::with(['items'])->findOrFail($id);

        return view('purchase::purchase-orders.generate', compact('purchase_quotation'));
    }

    public function generateFromSaleOpportunity($id)
    {
        $sale_opportunity = SaleOpportunity::with(['items'])->findOrFail($id);
        $id = null;

        return view('purchase::purchase-orders.form', compact('id','sale_opportunity'));
    }

    public function columns()
    {
        return [
            'date_of_issue' => 'Fecha de emisión'
        ];
    }

    public function records(Request $request)
    {
        $records = PurchaseOrder::where($request->column, 'like', "%{$request->value}%")
                            ->whereTypeUser()
                            ->latest();

        return new PurchaseOrderCollection($records->paginate(config('tenant.items_per_page')));
    }


    public function tables() {

        $suppliers = $this->table('suppliers');
        // $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        $currency_types = CurrencyType::whereActive()->get();
        $company = Company::active();
        $payment_method_types = PaymentMethodType::all();

        return compact('suppliers', 'establishment','company','currency_types','payment_method_types');
    }


    public function item_tables()
    {

        // $items = $this->table('items');
        $items =  SearchItemController::getItemToPurchaseOrder();

        $categories = [];
        $affectation_igv_types = AffectationIgvType::whereActive()->get();
        $system_isc_types = SystemIscType::whereActive()->get();
        $price_types = PriceType::whereActive()->get();
        $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        $attribute_types = AttributeType::whereActive()->orderByDescription()->get();
        $warehouses = Warehouse::all();

        $operation_types = OperationType::whereActive()->get();
        $is_client = $this->getIsClient();

        return compact(
        'items',
        'categories',
        'affectation_igv_types',
        'system_isc_types',
        'price_types',
        'discount_types',
        'charge_types',
        'attribute_types',
        'warehouses',
        'attribute_types',
        'operation_types',
        'is_client'
        );
    }


    public function record($id)
    {
        $record = new PurchaseOrderResource(PurchaseOrder::findOrFail($id));

        return $record;
    }


    public function getFullDescription($row){

        $desc = ($row->internal_id)?$row->internal_id.' - '.$row->description : $row->description;
        $category = ($row->category) ? " - {$row->category->name}" : "";
        $brand = ($row->brand) ? " - {$row->brand->name}" : "";

        $desc = "{$desc} {$category} {$brand}";

        return $desc;
    }


    public function store(PurchaseOrderRequest $request) {

        DB::connection('tenant')->transaction(function () use ($request) {

            $data = $this->mergeData($request);

            $id = $request->input('id');

            $this->purchase_order =  PurchaseOrder::updateOrCreate( ['id' => $id], $data);

            $this->purchase_order->items()->delete();

            foreach ($data['items'] as $row) {
                $this->purchase_order->items()->create($row);
            }

            $temp_path = $request->input('attached_temp_path');

            if($temp_path) {

                $datenow = date('YmdHis');
                $file_name_old = $request->input('attached');
                $file_name_old_array = explode('.', $file_name_old);
                $file_name = Str::slug($this->purchase_order->id).'-'.$datenow.'.'.$file_name_old_array[1];
                $file_content = file_get_contents($temp_path);

                // validaciones archivos
                $allowed_file_types_images = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/svg'];
                $is_image = UploadFileHelper::getIsImage($temp_path, $allowed_file_types_images);

                $allowed_file_types = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/svg', 'application/pdf'];
                UploadFileHelper::checkIfValidFile($file_name, $temp_path, $is_image, 'jpg,jpeg,png,gif,svg,pdf', $allowed_file_types);
                // validaciones archivos

                Storage::disk('tenant')->put('purchase_order_attached'.DIRECTORY_SEPARATOR.$file_name, $file_content);
                $this->purchase_order->upload_filename = $file_name;
                $this->purchase_order->save();

            }

            $this->setFilename();
            $this->createPdf($this->purchase_order, "a4", $this->purchase_order->filename);
            //$this->email($this->purchase_order);
        });

        return [
            'success' => true,
            'data' => [
                'id' => $this->purchase_order->id,
                'number_full' => $this->purchase_order->number_full,
            ],
        ];
    }


    public function mergeData($inputs)
    {

        $this->company = Company::active();

        $values = [
            'user_id' => auth()->id(),
            'supplier' => PersonInput::set($inputs['supplier_id']),
            'external_id' => Str::uuid()->toString(),
            'establishment' => EstablishmentInput::set($inputs['establishment_id']),
            'soap_type_id' => $this->company->soap_type_id,
            'state_type_id' => '01'
        ];

        $inputs->merge($values);

        return $inputs->all();
    }



    private function setFilename(){

        $name = [$this->purchase_order->prefix,$this->purchase_order->id,date('Ymd')];
        $this->purchase_order->filename = join('-', $name);
        $this->purchase_order->save();

    }


    public function table($table)
    {
        switch ($table) {
            case 'suppliers':

                $suppliers = Person::whereType('suppliers')->orderBy('name')->get()->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => $row->number.' - '.$row->name,
                        'name' => $row->name,
                        'number' => $row->number,
                        'email' => $row->email,
                        'identity_document_type_id' => $row->identity_document_type_id,
                        'identity_document_type_code' => $row->identity_document_type->code
                    ];
                });
                return $suppliers;

                break;

            case 'items':

                $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

                $items = Item::orderBy('description')->whereNotIsSet()
                    ->get()->transform(function($row) {
                    $full_description = $this->getFullDescription($row);
                    return [
                        'id' => $row->id,
                        'full_description' => $full_description,
                        'description' => $row->description,
                        'model' => $row->model,
                        'currency_type_id' => $row->currency_type_id,
                        'currency_type_symbol' => $row->currency_type->symbol,
                        'sale_unit_price' => $row->sale_unit_price,
                        'purchase_unit_price' => $row->purchase_unit_price,
                        'unit_type_id' => $row->unit_type_id,
                        'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                        'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                        'has_perception' => (bool) $row->has_perception,
                        'purchase_has_igv' => (bool) $row->purchase_has_igv,
                        'percentage_perception' => $row->percentage_perception,
                        'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                            return [
                                'id' => $row->id,
                                'description' => "{$row->description}",
                                'item_id' => $row->item_id,
                                'unit_type_id' => $row->unit_type_id,
                                'quantity_unit' => $row->quantity_unit,
                                'price1' => $row->price1,
                                'price2' => $row->price2,
                                'price3' => $row->price3,
                                'price_default' => $row->price_default,
                            ];
                        }),
                        'series_enabled' => (bool) $row->series_enabled,
                        'is_set' => (bool) $row->is_set,
                    ];
                });
                return $items;

                break;
            default:
                return [];

                break;
        }
    }


    public function download($external_id, $format = "a4") {

        $purchase_order = PurchaseOrder::where('external_id', $external_id)->first();

        if (!$purchase_order) throw new Exception("El código {$external_id} es inválido, no se encontro la cotización de compra relacionada");

        $this->reloadPDF($purchase_order, $format, $purchase_order->filename);

        return $this->downloadStorage($purchase_order->filename, 'purchase_order');

    }

    public function downloadAttached($external_id) {

        $purchase_order = PurchaseOrder::where('external_id', $external_id)->first();

        if (!$purchase_order) throw new Exception("El código {$external_id} es inválido, no se encontro la orden de compra relacionada");

        return Storage::disk('tenant')->download('purchase_order_attached'.DIRECTORY_SEPARATOR.$purchase_order->upload_filename);

    }

    public function toPrint($external_id, $format) {

        $purchase_order = PurchaseOrder::where('external_id', $external_id)->first();

        if (!$purchase_order) throw new Exception("El código {$external_id} es inválido, no se encontro la cotización de compra relacionada");

        $this->reloadPDF($purchase_order, $format, $purchase_order->filename);
        $temp = tempnam(sys_get_temp_dir(), 'purchase_order');

        file_put_contents($temp, $this->getStorage($purchase_order->filename, 'purchase_order'));

        /*
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$purchase_order->filename.'.pdf'.'"'
        ];
        */

        return response()->file($temp, $this->generalPdfResponseFileHeaders($purchase_order->filename));
    }


    private function reloadPDF($purchase_order, $format, $filename) {
        $this->createPdf($purchase_order, $format, $filename);
    }


    public function createPdf($purchase_order = null, $format_pdf = null, $filename = null) {
        ini_set("pcre.backtrack_limit", "5000000");
        $template = new Template();
        $pdf = new Mpdf();

        $document = ($purchase_order != null) ? $purchase_order : $this->purchase_order;
        $company = ($this->company != null) ? $this->company : Company::active();
        $filename = ($filename != null) ? $filename : $this->purchase_order->filename;

        $base_template = Establishment::find($document->establishment_id)->template_pdf;

        $html = $template->pdf($base_template, "purchase_order", $company, $document, $format_pdf);

        $pdf_font_regular = config('tenant.pdf_name_regular');
        $pdf_font_bold = config('tenant.pdf_name_bold');

        if ($pdf_font_regular != false) {
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $pdf = new Mpdf([
                'fontDir' => array_merge($fontDirs, [
                    app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                                DIRECTORY_SEPARATOR.'pdf'.
                                                DIRECTORY_SEPARATOR.$base_template.
                                                DIRECTORY_SEPARATOR.'font')
                ]),
                'fontdata' => $fontData + [
                    'custom_bold' => [
                        'R' => $pdf_font_bold.'.ttf',
                    ],
                    'custom_regular' => [
                        'R' => $pdf_font_regular.'.ttf',
                    ],
                ]
            ]);
        }

        $path_css = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_template.
                                             DIRECTORY_SEPARATOR.'style.css');

        $stylesheet = file_get_contents($path_css);

        $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        if ($format_pdf != 'ticket') {
            if(config('tenant.pdf_template_footer')) {
                $html_footer = $template->pdfFooter($base_template,$this->purchase_order);
                $pdf->SetHTMLFooter($html_footer);
            }
        }

        $this->uploadFile($filename, $pdf->output('', 'S'), 'purchase_order');
    }


    public function uploadFile($filename, $file_content, $file_type) {
        $this->uploadStorage($filename, $file_content, $file_type);
    }


    public function email(Request $request)
    {
        $record = PurchaseOrder::find($request->input('id'));
        $customer_email = $request->input('customer_email');

        $email = $customer_email;
        $mailable = new  PurchaseOrderEmail($record);
        $id = (int)$record->id;
        $sendIt = EmailController::SendMail($email, $mailable, $id, 5);
        /*
        Configuration::setConfigSmtpMail();
        $array_email = explode(',', $customer_email);
        if (count($array_email) > 1) {
            foreach ($array_email as $email_to) {
                $email_to = trim($email_to);
                if(!empty($email_to)) {
                    Mail::to($email_to)->send(new  PurchaseOrderEmail($record));
                }
            }
        } else {
            Mail::to($customer_email)->send(new  PurchaseOrderEmail($record));
        }
        */
        return [
            'success' => true
        ];
    }

    public function uploadAttached(Request $request)
    {

        $validate_upload = UploadFileHelper::validateUploadFile($request, 'file', 'jpg,jpeg,png,gif,svg,pdf', false);

        if(!$validate_upload['success']){
            return $validate_upload;
        }

        if ($request->hasFile('file')) {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            return $this->upload_attached($new_request);
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    function upload_attached($request)
    {
        $file = $request['file'];
        $type = $request['type'];

        $temp = tempnam(sys_get_temp_dir(), $type);
        file_put_contents($temp, file_get_contents($file));

        $mime = mime_content_type($temp);
        $data = file_get_contents($temp);

        return [
            'success' => true,
            'data' => [
                'filename' => $file->getClientOriginalName(),
                'temp_path' => $temp,
                'temp_image' => 'data:' . $mime . ';base64,' . base64_encode($data)
            ]
        ];
    }

    public function anular($id)
    {
        $obj =  PurchaseOrder::find($id);

        // Defensa: no permitir anular OC que ya tiene mercancía recibida
        // (parcial o total). Hay que primero reversar la recepción
        // (futuro) o anularla con flag de override que requeriría auditor.
        if (in_array($obj->reception_status, ['partial', 'received'], true)) {
            return [
                'success' => false,
                'message' => 'No se puede anular una OC con recepción registrada. Estado: ' . $obj->reception_status
            ];
        }

        $obj->state_type_id = 11;
        $obj->save();
        return [
            'success' => true,
            'message' => 'Orden de compra anulada con éxito'
        ];
    }

    /**
     * Recibir mercancía de una OC — recepción COMPLETA en 1 click.
     *
     * Marca todos los items con quantity_received = quantity, dispara
     * movimiento de stock por warehouse principal del establecimiento,
     * y deja la OC en estado 'received'.
     *
     * Si la OC ya estaba 'received', es idempotente (no aplica stock 2x).
     * Si estaba 'partial', completa la diferencia faltante.
     * Si está anulada (state_type_id=11), bloquea.
     *
     * Recepción parcial por ítem queda para sprint con UI dedicada.
     */
    public function receive(Request $request, $id)
    {
        try {
            return DB::connection('tenant')->transaction(function () use ($request, $id) {
                $po = PurchaseOrder::with('items')->find($id);
                if (!$po) {
                    return ['success' => false, 'message' => 'OC no encontrada'];
                }
                if ((int) $po->state_type_id === 11) {
                    return ['success' => false, 'message' => 'La OC está anulada — no se puede recibir.'];
                }
                if ($po->reception_status === 'received') {
                    return ['success' => false, 'message' => 'Esta OC ya está marcada como recibida.'];
                }

                // Guard anti doble-ingreso: si la OC ya tiene una Compra registrada,
                // el stock ya entró vía PurchaseItem::created. Recibir además duplicaría
                // el inventario. Bloquear y orientar al usuario.
                if ($po->purchases()->exists()) {
                    return [
                        'success' => false,
                        'message' => 'Esta OC ya tiene una compra registrada (el stock ya ingresó con ese documento). No se debe recibir además para no duplicar inventario.',
                    ];
                }

                // Resolver warehouse de destino: el del establecimiento.
                $establishment = Establishment::find($po->establishment_id);
                $warehouseId = optional($establishment)->warehouse_id
                    ?? \Modules\Inventory\Models\Warehouse::where('establishment_id', $po->establishment_id)->value('id');

                if (!$warehouseId) {
                    return ['success' => false, 'message' => 'No se encontró almacén para el establecimiento de la OC.'];
                }

                $now = now();
                $totalDelta = 0;

                foreach ($po->items as $poItem) {
                    $alreadyReceived = (float) ($poItem->quantity_received ?? 0);
                    $expected        = (float) $poItem->quantity;
                    $delta           = $expected - $alreadyReceived;

                    if ($delta <= 0) continue; // ya estaba recibido completo

                    $item = Item::find($poItem->item_id);
                    if (!$item) continue;

                    // Ingreso de stock variant-safe + visible en kardex + ledger Smart Stock.
                    $this->applyReceptionStock($item, (int) $warehouseId, $delta, $po);

                    // Actualizar tracking en el item
                    $poItem->quantity_received = $expected;
                    $poItem->save();

                    $totalDelta += $delta;
                }

                $po->reception_status = 'received';
                $po->received_at      = $now;
                $po->received_by      = auth()->id();
                if ($request->filled('reception_notes')) {
                    $po->reception_notes = mb_substr((string) $request->reception_notes, 0, 500);
                }
                $po->save();

                return [
                    'success' => true,
                    'message' => "Mercancía recibida correctamente. {$totalDelta} unidades ingresadas al almacén.",
                    'reception_status' => 'received',
                ];
            });
        } catch (\Throwable $e) {
            \Log::error('[PurchaseOrderController] receive failed', [
                'po_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Error al recibir: ' . $e->getMessage()];
        }
    }

    /**
     * Ingresa $delta unidades de $item al almacén $warehouseId por recepción de OC,
     * de forma íntegra con los tres sistemas de stock del ERP:
     *
     *  1. Kardex físico (inventory_kardex): se crea un movimiento `Inventory` type=1,
     *     cuyo observer (InventoryChangeServiceProvider) registra el kardex y suma el
     *     stock legacy en item_warehouse.stock. Así la recepción SÍ aparece en el
     *     reporte de movimientos de inventario.
     *
     *  2. Productos CON variantes (regla de oro, skill ebaemy-stock-flow): NUNCA se
     *     escribe item_warehouse.stock directo. El ingreso se enruta a la variante
     *     primaria en item_variant_warehouse y luego propagateStock() recalcula el
     *     padre desde las variantes — sobrescribiendo el bump del observer, por lo que
     *     NO hay doble conteo.
     *
     *  3. Ledger Smart Stock (stock_movements + stock_physical): se sincroniza
     *     stock_physical y se registra un movimiento PURCHASE_ENTRY con snapshot.
     *
     * NOTA de costeo: la valorización SUNAT (kardex valorizado) se alimenta del
     * documento de Compra real, no de la OC. La recepción es un movimiento físico;
     * el costeo se consolida al registrar la factura/boleta de compra asociada.
     */
    private function applyReceptionStock(Item $item, int $warehouseId, float $delta, PurchaseOrder $po): void
    {
        $reference = trim(($po->prefix ? $po->prefix . '-' : 'OC-') . $po->id);

        // (1) Movimiento de inventario + kardex físico vía observer Inventory::created
        \Modules\Inventory\Models\Inventory::create([
            'type'         => 1,
            'description'  => 'Recepción ' . $reference,
            'item_id'      => $item->id,
            'warehouse_id' => $warehouseId,
            'quantity'     => $delta,
            'comments'     => 'Ingreso por recepción de orden de compra ' . $reference,
        ]);

        if ($item->has_variants) {
            // (2) Variant-safe: enrutar a la variante primaria y propagar.
            $variant = ItemVariant::where('item_id', $item->id)
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first();

            if ($variant) {
                $ivw = ItemVariantWarehouse::firstOrNew([
                    'item_variant_id' => $variant->id,
                    'warehouse_id'    => $warehouseId,
                ]);
                $ivw->stock_physical  = (float) ($ivw->stock_physical ?? 0) + $delta;
                $ivw->stock_committed = (float) ($ivw->stock_committed ?? 0);
                $ivw->stock           = $ivw->stock_physical;
                $ivw->save();

                // item_variants.stock = SUM(item_variant_warehouse.stock_physical)
                $variant->stock = ItemVariantWarehouse::where('item_variant_id', $variant->id)
                    ->sum('stock_physical');
                $variant->save();
            }

            // Recalcula item_warehouse e items.stock desde las variantes (fuente de verdad).
            app(ItemVariantService::class)->propagateStock($item->fresh());
        } else {
            // El observer ya sumó item_warehouse.stock; sincronizar stock_physical
            // (Smart Stock) para que stock_available del ecommerce refleje la recepción.
            $iw = ItemWarehouse::where('item_id', $item->id)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();
            if ($iw) {
                $iw->stock_physical = (float) $iw->stock;
                $iw->save();
            }
        }

        // (3) Ledger Smart Stock — snapshot post-operación sobre el item_warehouse padre.
        $iwLedger = ItemWarehouse::where('item_id', $item->id)
            ->where('warehouse_id', $warehouseId)
            ->first();
        if ($iwLedger) {
            StockMovement::record(
                $iwLedger,
                StockMovementTypeEnum::PURCHASE_ENTRY,
                $delta,
                auth()->id(),
                $po,
                'Recepción ' . $reference
            );
        }
    }


    /**
     * @param $id
     *
     * @return array
     */
    public function searchItemById($id)
    {
        $items = SearchItemController::getItemToPurchaseOrder(null, $id);

        return compact('items');

    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function searchItems(Request $request)
    {
        $items = SearchItemController::getItemToPurchaseOrder($request);

        return compact('items');
    }


    /**
     * @deprecated
     * @param \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection $items
     */
    public function formatItem($items){
        // $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();
        return $items->transform(function($row) {
            $full_description = $this->getFullDescription($row);
            return [
                'id' => $row->id,
                'full_description' => $full_description,
                'description' => $row->description,
                'model' => $row->model,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_unit_price' => $row->sale_unit_price,
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'has_perception' => (bool) $row->has_perception,
                'purchase_has_igv' => (bool) $row->purchase_has_igv,
                'percentage_perception' => $row->percentage_perception,
                'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => "{$row->description}",
                        'item_id' => $row->item_id,
                        'unit_type_id' => $row->unit_type_id,
                        'quantity_unit' => $row->quantity_unit,
                        'price1' => $row->price1,
                        'price2' => $row->price2,
                        'price3' => $row->price3,
                        'price_default' => $row->price_default,
                    ];
                }),
                'series_enabled' => (bool) $row->series_enabled,
            ];
        });
    }
}
