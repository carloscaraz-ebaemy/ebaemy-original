<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Models\Tenant\Item;
use Modules\Item\Models\Category;
use Modules\Inventory\Models\InventoryConfiguration;
use Modules\Restaurant\Http\Resources\ItemCollection;
use App\Models\Tenant\Promotion;
use App\Http\Controllers\Tenant\Api\ServiceController;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Order;
use Exception;
use Illuminate\Support\Facades\Validator;
use stdClass;
use Illuminate\Support\Str;
use App\Http\Controllers\Tenant\EmailController;
use App\Mail\Tenant\CulqiEmail;
use Modules\Restaurant\Models\RestaurantConfiguration;
use Modules\Restaurant\Models\RestaurantNote;
use Modules\Restaurant\Models\RestaurantTable;
use Modules\Restaurant\Models\RestaurantItemOrderStatus;
use Modules\Restaurant\Models\RestaurantStockProduct;
use Modules\Restaurant\Services\RestaurantStockService;
use Illuminate\Support\Facades\DB;
use Modules\ApiPeruDev\Data\ServiceData;
use App\Models\Tenant\Person;




class RestaurantController extends Controller
{
    public function menu($name = null)
    {
        if($name) {
            $name = str_replace('-', ' ', $name);
        }

        $category = Category::where('name', $name)->first();

        // Obtener preferencias de configuración
        $configEcommerce = ConfigurationEcommerce::first();
        $preferences = $configEcommerce && $configEcommerce->preferences
            ? (is_string($configEcommerce->preferences) ? json_decode($configEcommerce->preferences, true) : $configEcommerce->preferences)
            : ['show_description' => 1, 'show_stock' => 0, 'only_available_products' => 0];

        // Query base
        $query = Item::where([['apply_restaurant', 1], ['internal_id','!=', null]]);

        // Filtrar solo productos disponibles si está activado
        if (isset($preferences['only_available_products']) && $preferences['only_available_products'] == 1) {
            $query->where('stock', '>', 0);
        }

        $dataPaginate = $query->category($category ? $category->id : null)
                              ->paginate(12);

        $configuration = InventoryConfiguration::first();
        $categories = Category::get();

        // Obtener spots publicitarios
        $spots = Promotion::where('apply_restaurant', 1)
            ->where('type', 'spots')
            ->get();

        return view('restaurant::index', [
            'dataPaginate' => $dataPaginate,
            'configuration' => $configuration->stock_control,
            'spots' => $spots,
            'preferences' => $preferences
        ])->with('categories', $categories);
    }

    /*
     * vista privada
     */
    public function list_items()
    {
        return view('restaurant::items.index');
    }

    public function is_visible(Request $request)
    {
        $item = Item::find($request->id);

        if(!$item->internal_id && $request->apply_restaurant){
            return [
                'success' => false,
                'message' =>'Para habilitar la visibilidad, debe asignar un codigo interno al producto',
            ];
        }

        $visible = $request->apply_restaurant == true ? 1 : 0 ;
        $item->apply_restaurant = $visible;
        $item->save();

        return [
            'success' => true,
            'message' => ($visible > 0 )?'El Producto ya es visible en restaurante' : 'El Producto ya no es visible en restaurante',
            'id' => $request->id
        ];

    }

    public function items(Request $request){

        $warehouse_id = auth()->user()->establishment->id;

        $items = Item::where('apply_restaurant', 1)
            ->whereNotNull('internal_id')
            ->where(function($query) use ($warehouse_id) {
                $query->whereHas('warehouses', function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })->orWhereHas('restaurantSupplies');
            })
            ->with(['restaurantSupplies'])
            ->get();

        $records = new ItemCollection($items);

        return [
            'success' => true,
            'data' => $records
        ];
    }

    public function setRestaurantFavoriteItem(Request $request) {
        $item = Item::findOrFail($request->id);

        $item->restaurant_favorite = ($item->restaurant_favorite == 1) ? 0 : 1;
        $item->save();

        return [
            'success' => true,
            'message' => ($item->restaurant_favorite == 1)? "Producto agregado a favoritos": "Producto quitado de favoritos"
        ];
    }

    public function categories(Request $request){
        $records = Category::all();
        return [
            'success' => true,
            'data' => $records
        ];
    }

    public function partialItem($id)
    {
        $record = Item::find($id);
        return view('restaurant::items.partial', compact('record'));
    }


    public function item($id, $promotion_id = null)
    {
        $row = Item::find($id);
        $exchange_rate_sale = $this->getExchangeRateSale();
        $sale_unit_price = ($row->has_igv) ? $row->sale_unit_price : $row->sale_unit_price*1.18;

        $description = $promotion_id ? $this->getDescriptionWithPromotion($row, $promotion_id) : $row->description;

        $record = (object)[
            'id' => $row->id,
            'internal_id' => $row->internal_id,
            'unit_type_id' => $row->unit_type_id,
            'description' => $description,
            'category' => $row->category,
            'stock' => $row->stock,
            // 'description' => $row->description,
            'technical_specifications' => $row->technical_specifications,
            'name' => $row->name,
            'second_name' => $row->second_name,
            'sale_unit_price' => ($row->currency_type_id === 'PEN') ? $sale_unit_price : ($sale_unit_price * $exchange_rate_sale),
            'currency_type' => $row->currency_type,
            'has_igv' => (bool) $row->has_igv,
            'sale_unit' => $row->sale_unit_price,
            'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
            'currency_type_symbol' => $row->currency_type->symbol,
            'image' =>  $row->image,
            'image_medium' => $row->image_medium,
            'image_small' => $row->image_small,
            'tags' => $row->tags->pluck('tag_id')->toArray(),
            'images' => $row->images,
            'attributes' => $row->attributes ? $row->attributes : [],
            'promotion_id' => $promotion_id,
        ];

        return view('restaurant::items.record', compact('record'));
    }


    private function getExchangeRateSale(){
        $exchange_rate = app(ServiceController::class)->exchangeRateTest(date('Y-m-d'));
        return (array_key_exists('sale', $exchange_rate)) ? $exchange_rate['sale'] : 1;
    }

    public function getDescriptionWithPromotion($item, $promotion_id)
    {
        $promotion = Promotion::findOrFail($promotion_id);
        return "{$item->description} - {$promotion->name}";
    }

    public function detailCart()
    {
        $configuration = ConfigurationEcommerce::first();

        $history_records = [];
        if (auth('ecommerce')->user()) {
            $email_user = auth('ecommerce')->user()->email;
            $history_records = Order::where('apply_restaurant', 1)->where('customer', 'LIKE', '%'.$email_user.'%')
                    ->get()
                    ->transform(function($row) {
                        /** @var  Order $row */
                        return $row->getCollectionData();
                    })->toArray();
        }
        return view('restaurant::cart.detail', compact(['configuration','history_records']));
    }

    public function paymentCash(Request $request)
    {
        $validator = Validator::make($request->customer, [
            'telefono' => 'required|numeric',
            'direccion' => 'required',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        } else {
            try {

                $type = ($request->purchase["datos_del_cliente_o_receptor"]["codigo_tipo_documento_identidad"]=='6')?'ruc':'dni';
                $document_number = $request->purchase["datos_del_cliente_o_receptor"]["numero_documento"];

                $dataDocument = $this->searchDocument($type,$document_number);
                if ($dataDocument["success"]) {
                    $clientData = [ "apellidos_y_nombres_o_razon_social" => $dataDocument["data"]["name"] ];
                    if ($type === 'ruc') {
                        $clientData["direccion"] = $dataDocument['data']['address'];
                        $clientData["ubigeo"] = $dataDocument['data']['location_id'][2] ?? null;
                    }
                    $request->merge([
                        'purchase' => array_merge($request->purchase, [
                            "datos_del_cliente_o_receptor" => array_merge(
                                $request->purchase["datos_del_cliente_o_receptor"],
                                $clientData
                            )
                        ])
                    ]);
                }

                $user = auth('ecommerce')->user();
                $order = Order::create([
                    'external_id' => Str::uuid()->toString(),
                    'customer' =>  $request->customer,
                    'shipping_address' => 'direccion 1',
                    'items' =>  $request->items,
                    'total' => $request->precio_culqi,
                    'reference_payment' => 'efectivo',
                    'status_order_id' => 1,
                    'purchase' => $request->purchase,
                    'apply_restaurant' => 1
                ]);

                $customer_email = $user->email;
                $document = new stdClass;
                $document->client = $user->name;
                $document->product = $request->producto;
                $document->total = $request->precio_culqi;
                $document->items = $request->items;

                $this->paymentCashEmail($customer_email, $document);

                //Mail::to($customer_email)->send(new CulqiEmail($document));
                return [
                    'success' => true,
                    'order' => $order
                ];

        }catch(Exception $e)
        {
            return [
                'success' => false,
                'message' =>  $e->getMessage()
            ];
        }
      }
    }

    public function paymentCashEmail($customer_email, $document)
    {
        try {
            $email = $customer_email;
            $mailable = new CulqiEmail($document);
            $id = (int) $document->id;
            $model = __FILE__.";;".__LINE__;
            $sendIt = EmailController::SendMail($email, $mailable, $id, $model);
        }catch(\Exception $e)
        {
            return true;
        }
    }

    public function savePrice(Request $request) {
        $item = Item::find($request->id);
        $item->sale_unit_price = $request->sale_unit_price;
        $item->save();

        return [
            'success' => true,
            'message' => 'Precio editado correctamente.'
        ];
    }

    public function searchDocument($type, $number)
    {
        return (new ServiceData)->service($type, $number);
    }

    // Cambiar mesa de un pedido a otra mesa
    public function changeTablePedido(Request $request)
    {
        try {
            $request->validate([
                'tableid_origin' => 'required|integer|different:tableid_destination',
                'tableid_destination' => 'required|integer'
            ]);

            $tableid_origin = $request->tableid_origin;
            $tableid_destination = $request->tableid_destination;

            $defaultTableValues = [
                'status' => 'available',
                'products' => [],
                'total' => 0.0,
                'personas' => 0,
                'cliente' => null,
                'comentarios' => null,
                'waiter' => null,
                'opening_date' => null,
                'order_status' => 'pending'
            ];

            $table_origin = RestaurantTable::find($tableid_origin);
            if (!$table_origin) {
                return ['success' => false, 'message' => 'La mesa de origen no existe.'];
            }

            $table_destination = RestaurantTable::find($tableid_destination);
            if (!$table_destination) {
                return ['success' => false, 'message' => 'La mesa destino no existe.'];
            }

            DB::transaction(function() use ($table_origin, $table_destination, $defaultTableValues, $tableid_origin, $tableid_destination) {

                $table_destination->update([
                    'status' => $table_origin->status,
                    'products' => $table_origin->products,
                    'total' => $table_origin->total,
                    'personas' => $table_origin->personas,
                    'cliente' => $table_origin->cliente,
                    'comentarios' => $table_origin->comentarios,
                    'waiter' => $table_origin->waiter,
                    'opening_date' => $table_origin->opening_date,
                    'order_status' => $table_origin->order_status
                ]);

                RestaurantItemOrderStatus::where('table_id', $tableid_origin)
                    ->update(['table_id' => $tableid_destination]);

                $table_origin->update($defaultTableValues);

            });

            return ['success' => true, 'message' => 'Los datos de la mesa se han movido correctamente.'];

        } catch(\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtiene el stock disponible de todos los items del restaurante
     * Versión optimizada para consultas frecuentes (websockets)
     * Solo retorna: item_id, stock, quantity_reserved, available
     *
     * @return array
     */
    public function getStockStatus()
    {
        try {
            $stockData = RestaurantStockProduct::select(
                'item_id',
                'stock',
                'quantity_reserved',
                DB::raw('GREATEST(0, stock - quantity_reserved) as available')
            )->get();

            return [
                'success' => true,
                'data' => $stockData
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener el stock: ' . $e->getMessage()
            ];
        }
    }

}
