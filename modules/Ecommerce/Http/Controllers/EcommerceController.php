<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Http\Resources\Tenant\ItemCollection;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\User;
use App\Models\Tenant\Person;
use Illuminate\Support\Str;
use App\Models\Tenant\Order;
use App\Models\Tenant\ItemsRating;
use App\Models\Tenant\ConfigurationEcommerce;
use Modules\Ecommerce\Http\Resources\ItemBarCollection;
use stdClass;
use Illuminate\Support\Facades\Mail;
use App\Mail\Tenant\CulqiEmail;
use App\Http\Controllers\Tenant\Api\ServiceController;
use Illuminate\Support\Facades\Validator;
use Modules\Inventory\Models\InventoryConfiguration;
use App\Http\Resources\Tenant\OrderCollection;
use App\Models\Tenant\Promotion;
use Modules\ApiPeruDev\Data\ServiceData;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Tenant\Document;
use Modules\Item\Models\Category;
use App\Models\Tenant\Company;
use App\Mail\Tenant\ReclamoEmail;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\FlashSale;



class EcommerceController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function __construct(){
        // DB query deferred to avoid "tenant not configured" error during middleware boot
    }

    // public function index()
    // {
    //   $dataPaginate = Item::where([['apply_store', 1], ['internal_id','!=', null]])->paginate(15);
    //   $configuration = InventoryConfiguration::first();
    //   return view('ecommerce::index', ['dataPaginate' => $dataPaginate, 'configuration' => $configuration->stock_control]);
    // }
    public function index($name = null)
    {
        if ($name) {
            $name = str_replace('-', ' ', $name);
        }

        $category = Category::where('name', $name)->first();
        
        // Obtener preferencias de configuración
        $configEcommerce = ConfigurationEcommerce::first();
        $preferences = $configEcommerce && $configEcommerce->preferences 
            ? (is_string($configEcommerce->preferences) ? json_decode($configEcommerce->preferences, true) : $configEcommerce->preferences)
            : ['show_description' => 1, 'show_stock' => 0, 'only_available_products' => 0];
        
        // Parámetros de filtrado desde la URL
        $sortBy      = request('sort', 'newest');
        $minPrice    = request('min_price');
        $maxPrice    = request('max_price');
        $onlyAvail   = request('available', 0);
        $categoryId  = request('category_id');   // filtro por categoría via AJAX
        $searchQ     = request('q');              // búsqueda por nombre

        // Si hay category_id en query param, úsarlo (AJAX); si no, el $category del slug
        if ($categoryId && !$category) {
            $category = Category::find((int)$categoryId);
        }

        // Rango de precios disponibles (para el slider)
        $priceRange = Cache::remember('ecommerce_price_range', 600, function () {
            $base = Item::where([['apply_store', 1], ['internal_id', '!=', null]]);
            return [
                'min' => (int) floor($base->min('sale_unit_price') ?? 0),
                'max' => (int) ceil($base->max('sale_unit_price') ?? 9999),
            ];
        });

        // Query base
        $query = Item::where([['apply_store', 1], ['internal_id', '!=', null]]);

        // Búsqueda por nombre
        if ($searchQ) {
            $query->where(function ($q) use ($searchQ) {
                $q->where('description', 'like', '%' . $searchQ . '%')
                  ->orWhere('name', 'like', '%' . $searchQ . '%');
            });
        }

        // Filtrar solo productos disponibles (config o parámetro URL)
        $forceAvail = (isset($preferences['only_available_products']) && $preferences['only_available_products'] == 1);
        if ($forceAvail || $onlyAvail) {
            $query->where('stock', '>', 0);
        }

        // Filtrar por rango de precio
        if (is_numeric($minPrice)) {
            $query->where('sale_unit_price', '>=', (float)$minPrice);
        }
        if (is_numeric($maxPrice) && (float)$maxPrice > 0) {
            $query->where('sale_unit_price', '<=', (float)$maxPrice);
        }

        // Ordenación
        $sortMap = [
            'price_asc'  => ['sale_unit_price', 'ASC'],
            'price_desc' => ['sale_unit_price', 'DESC'],
            'name_asc'   => ['description', 'ASC'],
            'newest'     => ['created_at', 'DESC'],
        ];
        [$sortCol, $sortDir] = $sortMap[$sortBy] ?? ['created_at', 'DESC'];

        $dataPaginate = $query->orderBy($sortCol, $sortDir)
            ->category($category ? $category->id : null)
            ->paginate(24)
            ->appends(request()->query());
            
        $configuration = InventoryConfiguration::first();

        // Caché de 30 minutos para datos estáticos (categorías y spots)
        $categories = Cache::remember('ecommerce_categories', 1800, function () {
            return Category::get();
        });

        $spots = Cache::remember('ecommerce_spots', 1800, function () {
            return Promotion::where('apply_restaurant', 0)
                ->where('type', 'spots')
                ->where('status', 1)
                ->orderBy('id', 'ASC')
                ->limit(4)
                ->get();
        });

        // Paquetes/bundles visibles en tienda
        $bundles = Cache::remember('ecommerce_bundles', 1800, function () {
            return Item::where('apply_store', 1)
                ->where('is_set', true)
                ->whereNotNull('internal_id')
                ->with(['sets.individual_item', 'warehouses'])
                ->select(['id', 'slug', 'description', 'image', 'sale_unit_price',
                          'sale_unit_price_set', 'currency_type_id', 'stock', 'created_at'])
                ->limit(8)
                ->get();
        });

        // Flash sale activa
        try {
            $flashSale = FlashSale::active()->with(['items' => function ($q) {
                $q->where('apply_store', 1)->whereNotNull('internal_id')->limit(12);
            }])->first();
        } catch (\Exception $e) {
            $flashSale = null;
        }

        $viewData = [
            'dataPaginate'    => $dataPaginate,
            'configuration'   => $configuration->stock_control,
            'spots'           => $spots,
            'preferences'     => $preferences,
            'bundles'         => $bundles,
            'sortBy'          => $sortBy,
            'onlyAvail'       => $onlyAvail,
            'minPrice'        => $minPrice,
            'maxPrice'        => $maxPrice,
            'priceRange'      => $priceRange,
            'currentCategory' => $category,
            'categories'      => $categories,
            'flashSale'       => $flashSale,
        ];

        // AJAX: devolver solo el grid parcial
        if (request()->ajax() || request('_ajax')) {
            return view('ecommerce::layouts.partials_ecommerce.products_grid', $viewData);
        }

        return view('ecommerce::index', $viewData);
    }
    
    public function category(Request $request, $category)
    {
        request()->merge(['category_id' => $category]);
        return $this->index();
    }

    // public function category(Request $request)
    // {
    //   $dataPaginate = Item::select('i.*')
    //     ->where([['i.apply_store', 1], ['i.internal_id','!=', null], ['it.tag_id', $request->category]])
    //     ->from('items as i')
    //     ->join('item_tags as it', 'it.item_id','i.id')->paginate(15);
    //     $configuration = InventoryConfiguration::first();
    //   return view('ecommerce::index', ['dataPaginate' => $dataPaginate, 'configuration' => $configuration->stock_control]);
    // }



    // terminos y condiciones 


    
        public function terminosCondiciones()
    {
        $config = ConfigurationEcommerce::first();
        return view('ecommerce::layouts.terminos_condiciones.terminos_condiciones', [
            'terms' => $config ? $config->termino_conditions : ''
        ]);
    }
    public function politicaPrivacy(){
        $config = ConfigurationEcommerce::first();
    // Ajusta la ruta con puntos para entrar en las subcarpetas
    return view('ecommerce::layouts.terminos_condiciones.politica_privacidad', [
        'terms' => $config ? $config->politica_privacy : ''
    ]);
    }
    public function cambiosDevolucion()
    {
        $config = ConfigurationEcommerce::first();
        return view('ecommerce::layouts.terminos_condiciones.cambios_devolucion', [
            'terms' => $config ? $config->cambios_devolucion : ''
        ]);
    }

    public function politicaEnvio()
    {
        $config = ConfigurationEcommerce::first();
        return view('ecommerce::layouts.terminos_condiciones.politica_envio', [
            'terms' => $config ? $config->politica_envio : ''
        ]);
    }

  
     
    
    public function libroReclamaciones()
    {
        $config = ConfigurationEcommerce::first();
        $company = Company::first();

        return view('ecommerce::layouts.terminos_condiciones.libro_reclamaciones', [
            'information_contact_email' => optional($config)->information_contact_email,
            'information_contact_phone' => optional($config)->information_contact_phone,
            'information_contact_address' => optional($config)->information_contact_address,
            'name_company' => optional($company)->name,
            'number_company' => optional($company)->number,
        ]);
    }


    public function enviarReclamo(Request $request)
    {
        $request->validate([
            'nombres' => 'required',
            'apellidos' => 'required',
            'tipo_documento' => 'required',
            'numero_documento' => 'required',
            'descripcion' => 'required',
            'detalle_reclamo' => 'required',
            'pedido_consumidor' => 'required',
        ]);

        $datosFormulario = $request->all();

        // Guardar archivos
        if ($request->hasFile('archivos')) {
            foreach ($request->file('archivos') as $archivo) {
                $path = $archivo->store('reclamaciones', 'public');
                $datosFormulario['archivos'][] = $path;
            }
        }

        $company = Company::first();
        $configuration = ConfigurationEcommerce::first();

        // 👇 usa el email referencial del cliente
        $email = $configuration->information_contact_email;
        try {
            Mail::to($email)->send(new ReclamoEmail($company, $datosFormulario));
        } catch (\Exception $e) {
            // Esto grabará el error exacto en storage/logs/laravel.log
            \Log::error("Error enviando Libro de Reclamaciones: " . $e->getMessage());
            return back()->with('error', 'Error técnico al enviar el correo.');
        }

        return redirect()
            ->route('tenant.libro_reclamaciones')
            ->with('success', 'Reclamo enviado correctamente');
    }



// fin terminos y condiciones



    public function getDescriptionWithPromotion($item, $promotion_id)
    {
        $promotion = Promotion::findOrFail($promotion_id);

        return "{$item->description} - {$promotion->name}";
    }
    public function item($slug, $promotion_id = null)
    {
        $row = Item::where('slug', $slug)->firstOrFail();

        $exchange_rate_sale = $this->getExchangeRateSale();

        $sale_unit_price = ($row->has_igv) ? $row->sale_unit_price : $row->sale_unit_price * 1.18;

        $description = $promotion_id ? $this->getDescriptionWithPromotion($row, $promotion_id) : $row->description;

        $record = (object)[
            'id'                          => $row->id,
            'slug'                        => $row->slug,
            'internal_id'                 => $row->internal_id,
            'unit_type_id'                => $row->unit_type_id,
            'description'                 => $description,
            'category'                    => $row->category,
            'stock'                       => $row->stock,
            'technical_specifications'    => $row->technical_specifications,
            'name'                        => $row->name,
            'second_name'                 => $row->second_name,
            'sale_unit_price'             => ($row->currency_type_id === 'PEN') ? $sale_unit_price : ($sale_unit_price * $exchange_rate_sale),
            'currency_type'               => $row->currency_type,
            'has_igv'                     => (bool) $row->has_igv,
            'sale_unit'                   => $row->sale_unit_price,
            'sale_affectation_igv_type_id'=> $row->sale_affectation_igv_type_id,
            'currency_type_symbol'        => $row->currency_type->symbol,
            'image'                       => $row->image,
            'image_medium'                => $row->image_medium,
            'image_small'                 => $row->image_small,
            'tags'                        => $row->tags->pluck('tag_id')->toArray(),
            'images'                      => $row->images,
            'attributes'                  => $row->attributes ?? [],
            'promotion_id'                => $promotion_id,
        ];

        // Productos relacionados: misma categoría, excluir el actual, máx 8
        $relatedProducts = collect();
        try {
            if ($row->category_id) {
                $relatedProducts = Item::where('apply_store', 1)
                    ->where('category_id', $row->category_id)
                    ->where('id', '!=', $row->id)
                    ->with(['category', 'warehouses'])
                    ->select(['id', 'slug', 'description', 'name', 'image', 'sale_unit_price',
                              'currency_type_id', 'stock', 'created_at'])
                    ->inRandomOrder()
                    ->limit(8)
                    ->get();
            }
        } catch (\Exception $e) {}

        return view('ecommerce::items.record', compact('record', 'relatedProducts'));
    }

    public function items()
    {
        $records = Item::where('apply_store', 1)->get();
        return view('ecommerce::items.index', compact('records'));
    }

    public function itemsBar()
    {
        $records = Item::where('apply_store', 1)->get();
        // return new ItemCollection($records);
        return new ItemBarCollection($records);

    }

    public function partialItem($id)
    {
        $record = Item::find($id);
        return view('ecommerce::items.partial', compact('record'));
    }

    public function quickView($id)
    {
        $row = Item::with(['category', 'images', 'warehouses'])->findOrFail($id);

        $exchange_rate_sale = $this->getExchangeRateSale();
        $sale_unit_price = ($row->has_igv) ? $row->sale_unit_price : $row->sale_unit_price * 1.18;
        if ($row->currency_type_id !== 'PEN') {
            $sale_unit_price = $sale_unit_price * $exchange_rate_sale;
        }

        $stock = $row->warehouses->sum('stock');

        $images = collect();
        $defaultImage = asset('logo/imagen-no-disponible.jpg');

        $mainImg = ($row->image && $row->image !== 'imagen-no-disponible.jpg')
            ? asset('storage/uploads/items/' . $row->image)
            : $defaultImage;

        $images->push($mainImg);
        foreach ($row->images as $img) {
            if ($img->image && $img->image !== 'imagen-no-disponible.jpg') {
                $images->push(asset('storage/uploads/items/' . $img->image));
            }
        }

        return response()->json([
            'id'          => $row->id,
            'slug'        => $row->slug ?: $row->id,
            'description' => $row->description,
            'name'        => $row->name,
            'category'    => $row->category ? $row->category->name : null,
            'price'       => round($sale_unit_price, 2),
            'symbol'      => $row->currency_type->symbol ?? 'S/',
            'stock'       => $stock,
            'images'      => $images->values(),
            'attributes'  => $row->attributes ?? [],
            'item_data'   => [
                'id'                          => $row->id,
                'description'                 => $row->description,
                'sale_unit_price'             => round($sale_unit_price, 2),
                'currency_type_id'            => $row->currency_type_id,
                'currency_type_symbol'        => $row->currency_type->symbol ?? 'S/',
                'has_igv'                     => (bool) $row->has_igv,
                'sale_affectation_igv_type_id'=> $row->sale_affectation_igv_type_id,
                'unit_type_id'                => $row->unit_type_id,
                'internal_id'                 => $row->internal_id,
                'calculate_quantity'          => (bool) $row->calculate_quantity,
                'stock'                       => $stock,
                'image'                       => $row->image,
                'image_small'                 => $row->image_small,
                'image_medium'                => $row->image_medium,
            ],
        ]);
    }

    public function detailCart()
    {
        $configuration = ConfigurationEcommerce::first();
        return view('ecommerce::cart.detail', compact(['configuration']));
    }

    public function checkout()
    {
        $configuration = ConfigurationEcommerce::first();
        return view('ecommerce::cart.checkout', compact(['configuration']));
    }

    public function orderConfirmation($external_id)
    {
        $order = Order::where('external_id', $external_id)->firstOrFail();
        $configuration = ConfigurationEcommerce::first();
        $company = Company::first();
        return view('ecommerce::order.confirmation', compact('order', 'configuration', 'company'));
    }

    public function orderList()
    {
        if (auth('ecommerce')->user()) {
            $configuration = ConfigurationEcommerce::first();
            return view('ecommerce::document_list.order', compact('configuration'));
        } else {
            return redirect('ecommerce');
        }

    }

    public function documentList()
    {
        // dd(auth('ecommerce')->user());
        if (auth('ecommerce')->user()) {
            return view('ecommerce::document_list.document');
        } else {
            return redirect('ecommerce');
        }
    }

    public function orders(Request $request)
    {
        if (auth('ecommerce')->user()) {
            $user = auth('ecommerce')->user();
            
            // Inicializar la consulta base
            $records = Order::where(function($query) use ($user) {
                $query->where('customer', 'LIKE', '%' . $user->email . '%')
                      ->orWhereJsonContains('customer->correo_electronico', $user->email);
            });
            
            // Aplicar filtro de estado si se proporciona
            if ($request->state_order_id) {
                $state_allowed = $request->state_order_id && $request->state_order_id != 'all' ? [$request->state_order_id] : [1, 2, 3, 4];
                $records = $records->whereIn('status_order_id', $state_allowed);
            }

            // Aplicar filtro de fecha si se proporciona
            if ($request->date_of_endd || $request->date_of_start) {
                $date_of_start = $request->date_of_start ?? date('Y-m-d');
                $date_of_end = $request->date_of_endd ?? date('Y-m-d');
                $records = $records->whereBetween('created_at', [$date_of_start, $date_of_end]);
            }
            
            // Obtener los resultados paginados
            $records = $records->paginate(config('tenant.items_per_page', 10));
            
            // Transformar los datos manteniendo la estructura de paginación
            $records->getCollection()->transform(function ($row) {
                return $row->getCollectionData();
            });

            return $records;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }
    }

    public function documents(Request $request) 
    {
        if (auth('ecommerce')->user()) {
            $user = auth('ecommerce')->user();

            
            // Buscar órdenes del usuario
            $orders = Order::where(function($query) use ($user) {
                        $query->where('customer', 'LIKE', '%' . $user->email . '%')
                              ->orWhereJsonContains('customer->correo_electronico', $user->email);
                    })->get();
            
            $arrays_external_id = $orders->pluck('document_external_id')->filter()->toArray();
            
            if (empty($arrays_external_id)) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => config('tenant.items_per_page', 10),
                    'total' => 0
                ]);
            }

            $documents = Document::where(function($q) use($arrays_external_id, $user, $request) {
                            $q->whereIn('external_id', $arrays_external_id)
                                ->orWhere('customer->email', $user->email);
                        });

            if ($request->date_of_endd || $request->date_of_start) {
                $date_of_start = $request->date_of_start ?? date('Y-m-d');
                $date_of_end = $request->date_of_endd ?? date('Y-m-d');
                $documents = $documents->whereBetween('date_of_issue', [$date_of_start, $date_of_end]);
            }

            if ($request->state_type_id ) {
                $state_allowed = $request->state_type_id && $request->state_type_id != 'all'  ? [$request->state_type_id] : ['05', '09', '11'];
                $documents = $documents->whereIn('state_type_id', $state_allowed);
            }

            $documents = $documents->orderBy('date_of_issue', 'desc')
                        ->paginate(config('tenant.items_per_page'));
            
            // Transformar los datos manteniendo la estructura de paginación
            $documents->getCollection()->transform(function ($dc) {
                return [
                    'number' => $dc->getNumberFullAttribute(),
                    'description' => $dc->document_type->description,
                    'date_of_issue' => $dc->date_of_issue->format('Y-m-d'),
                    'customer' => [
                        'name' => $dc->customer->name,
                        'number' => $dc->customer->number,
                        'identity_document_type_id' => $dc->customer->identity_document_type_id,
                    ],
                    'status' => $dc->state_type->description,
                    'state_type_id' => $dc->state_type_id,
                    'download_pdf' => $dc->download_external_pdf,
                    'download_xml' =>  $dc->download_external_xml,
                    'total' => $dc->total,
                ];
            });

            return $documents;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No autentificado'
            ], 401);
        }
    }

    public function pay()
    {
        return view('ecommerce::cart.pay');
    }

    public function showLogin()
    {
        return view('ecommerce::user.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::guard('ecommerce')->attempt($credentials)) {
           return[
               'success' => true,
               'message' => 'Login Success'
           ];
        }
        else{
            return[
                'success' => false,
                'message' => 'Usuario o Password incorrectos'
            ];
        }

    }

    /**
     * Get the Socialite Google driver configured with tenant credentials if available.
     */
    private function googleDriver()
    {
        $config = ConfigurationEcommerce::first();

        if ($config && $config->google_login_enabled && $config->google_client_id && $config->google_client_secret) {
            config([
                'services.google.client_id'     => $config->google_client_id,
                'services.google.client_secret' => $config->google_client_secret,
                'services.google.redirect'      => url('/ecommerce/auth/google/callback'),
            ]);
        }

        return Socialite::driver('google');
    }

    /**
     * Redirect to Google for OAuth authentication.
     */
    public function googleRedirect()
    {
        return $this->googleDriver()
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback — login or auto-register the user.
     */
    public function googleCallback()
    {
        try {
            $googleUser = $this->googleDriver()->user();
        } catch (\Exception $e) {
            return redirect()->route('tenant.ecommerce.index')
                ->with('error', 'No se pudo autenticar con Google.');
        }

        // 1. Look for existing Person with this google_id
        $person = Person::where('google_id', $googleUser->getId())->first();

        // 2. Or by email
        if (!$person) {
            $person = Person::where('email', $googleUser->getEmail())->first();
        }

        // 3. Auto-register if new user
        if (!$person) {
            $person = Person::create([
                'type'                       => 'customers',
                'identity_document_type_id'  => 1,   // DNI por defecto
                'number'                     => '00000000', // placeholder — no tienen doc
                'name'                       => $googleUser->getName() ?? $googleUser->getEmail(),
                'email'                      => $googleUser->getEmail(),
                'password'                   => \Hash::make(Str::random(32)),
                'google_id'                  => $googleUser->getId(),
                'avatar'                     => $googleUser->getAvatar(),
                'condition'                  => '01',
                'state'                      => '01',
            ]);
        } else {
            // Update google_id and avatar if not set
            $person->update([
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
            ]);
        }

        Auth::guard('ecommerce')->login($person, true);

        return redirect()->route('tenant.ecommerce.index');
    }

    public function logout()
    {
        Auth::guard('ecommerce')->logout();
        
        $referer = request()->headers->get('referer');
        
        if ($referer && (str_contains($referer, '/pedidos') || str_contains($referer, 'pedidos/'))) {
            return redirect('/pedidos');
        }
        
        // Detectar si viene de restaurant y redirigir apropiadamente
        if ($referer && str_contains($referer, '/restaurant')) {
            return redirect('restaurant/list/items');
        }
        
        return redirect('ecommerce');
    }

    public function storeUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
                'pswd'  => 'required|string|min:6',
                'name'  => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return ['success' => false, 'message' => $validator->errors()->first()];
            }

            // Check email uniqueness
            if (Person::where('email', $request->email)->exists()) {
                return ['success' => false, 'message' => 'Ya existe una cuenta con ese correo electrónico.'];
            }

            // Use provided name or derive from email
            $name = $request->name
                ? trim($request->name)
                : ucfirst(explode('@', $request->email)[0]);

            // Generate a unique internal number (no real DNI required)
            $number = 'EC' . strtoupper(Str::random(8));

            $person = new Person();
            $person->type                      = 'customers';
            $person->identity_document_type_id = 0;   // Sin documento
            $person->number                    = $number;
            $person->name                      = $name;
            $person->country_id                = 'PE';
            $person->nationality_id            = 'PE';
            $person->establishment_code        = '0000';
            $person->email                     = $request->email;
            $person->password                  = bcrypt($request->pswd);
            $person->save();

            Auth::guard('ecommerce')->attempt([
                'email'    => $person->email,
                'password' => $request->pswd,
            ]);

            return ['success' => true, 'message' => '¡Cuenta creada!'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function transactionFinally(Request $request)
    {
        try{
            //1. confirmar dato de comprobante en order
            $order_generated = Order::find($request->orderId);
            $order_generated->document_external_id = $request->document_external_id;
            $order_generated->number_document = $request->number_document;
            $order_generated->save();

            return [
                'success' => true,
                'message' => 'Order Actualizada',
                'order_total' => $order_generated->total
            ];
        }
        catch(Exception $e)
        {
            return [
                'success' => false,
                'message' =>  $e->getMessage()
            ];
        }

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
                // ===== VERIFICACIÓN SERVER-SIDE DE PRECIOS =====
                // Recalcular total desde BD ignorando precios del cliente
                $clientItems = $request->items ?? [];
                $verifiedTotal = 0;
                $verifiedItems = [];

                foreach ($clientItems as $clientItem) {
                    $itemId = $clientItem['id'] ?? null;
                    if (!$itemId) continue;

                    $dbItem = Item::find($itemId);
                    if (!$dbItem || !$dbItem->apply_store) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Producto no disponible en tienda: ' . ($clientItem['description'] ?? $itemId)
                        ], 422);
                    }

                    $qty = max(1, (int)($clientItem['quantity'] ?? 1));
                    $realPrice = (float) $dbItem->sale_unit_price;
                    $verifiedTotal += $realPrice * $qty;

                    // Reemplazar datos del item con valores verificados de BD
                    $verifiedItems[] = array_merge($clientItem, [
                        'sale_unit_price' => $realPrice,
                        'quantity'        => $qty,
                        'subtotal'        => $realPrice * $qty,
                    ]);
                }

                // ── Aplicar cupón de descuento ──────────────────────────────────
                $couponDiscount = 0;
                $couponCode     = strtoupper(trim($request->coupon_code ?? ''));
                $appliedCoupon  = null;

                if ($couponCode) {
                    $coupon = Coupon::where('code', $couponCode)->first();
                    if ($coupon && !$coupon->validate($verifiedTotal)) {
                        $couponDiscount = $coupon->calculateDiscount($verifiedTotal);
                        $appliedCoupon  = $coupon;
                    }
                }

                // ── Canjear puntos ───────────────────────────────────────────────────
                $pointsDiscount = 0;
                if ($request->input('redeem_points') && auth('ecommerce')->check()) {
                    $person       = auth('ecommerce')->user();
                    $ptConfig     = Configuration::getDataPointSystem();
                    if ($ptConfig->enabled_point_system) {
                        $balance      = (float) $person->accumulated_points;
                        $maxByPercent = ($verifiedTotal - $couponDiscount) * 0.5; // máx 50%
                        $requested    = (float) $request->input('points_amount', $balance);
                        $pointsDiscount = min($balance, $maxByPercent, $requested);
                        $pointsDiscount = max(0, round($pointsDiscount, 2));
                    }
                }

                $finalTotal = max(0, round($verifiedTotal - $couponDiscount - $pointsDiscount, 2));

                // Convertir a centavos para Culqi (si aplica) y comparar
                $clientTotal = (float) ($request->precio_culqi ?? 0);
                $tolerance   = 0.10; // tolerancia de S/. 0.10 por redondeo

                if (abs($finalTotal - $clientTotal) > $tolerance) {
                    \Log::warning('Intento de manipulación de precio', [
                        'user_id'        => auth('ecommerce')->id(),
                        'client_total'   => $clientTotal,
                        'verified_total' => $finalTotal,
                        'coupon'         => $couponCode,
                        'ip'             => request()->ip(),
                    ]);
                    // Usar total verificado en lugar del enviado por el cliente
                }

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

                // ── Email: usar del usuario autenticado o del campo enviado por el invitado ──
                $customerData  = $request->customer ?? [];
                $customer_email = $user
                    ? $user->email
                    : ($customerData['correo_electronico'] ?? null);
                $customer_name  = $user
                    ? $user->name
                    : ($customerData['apellidos_y_nombres_o_razon_social'] ?? 'Cliente');

                // ── Calcular puntos a otorgar ────────────────────────────────────────
                $earnedPoints = 0;
                $ptConfig2 = Configuration::getDataPointSystem();
                if ($ptConfig2->enabled_point_system && $ptConfig2->point_system_sale_amount > 0) {
                    $rawEarned   = ($finalTotal / $ptConfig2->point_system_sale_amount) * $ptConfig2->quantity_of_points;
                    $earnedPoints = $ptConfig2->round_points_of_sale ? intval($rawEarned) : round($rawEarned, 2);
                }

                $order = Order::create([
                    'external_id'       => Str::uuid()->toString(),
                    'person_id'         => $user ? $user->id : null,
                    'customer'          => $request->customer,
                    'shipping_address'  => $customerData['direccion'] ?? 'Sin dirección',
                    'items'             => $verifiedItems,
                    'total'             => $finalTotal,
                    'points_redeemed'   => $pointsDiscount,
                    'points_earned'     => $earnedPoints,
                    'reference_payment' => 'efectivo',
                    'status_order_id'   => 1,
                    'purchase'          => $request->purchase,
                ]);

                // Incrementar uso del cupón
                if ($appliedCoupon) {
                    $appliedCoupon->increment('used_count');
                }

                // ── Actualizar puntos del cliente ────────────────────────────────────
                if ($user) {
                    $newBalance = max(0, (float) $user->accumulated_points - $pointsDiscount + $earnedPoints);
                    $user->accumulated_points = $newBalance;
                    $user->save();
                }

                // ── Enviar email de confirmación ─────────────────────────────────────
                if ($customer_email) {
                    try {
                        \Mail::to($customer_email)->send(
                            new \App\Mail\Tenant\OrderConfirmationEmail($order, $customer_name, $customer_email)
                        );
                    } catch (\Exception $mailEx) {
                        \Log::warning('OrderConfirmationEmail failed: ' . $mailEx->getMessage());
                    }
                }

                return [
                    'success'        => true,
                    'order'          => $order,
                    'redirect_route' => url('/ecommerce/order/confirmation/' . $order->external_id),
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

    /**
     * Landing page dedicada para un bundle/pack.
     */
    public function bundleLanding($slug)
    {
        $bundle = Item::where('is_set', true)
            ->where('apply_store', 1)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug)->orWhere('id', (int) $slug);
            })
            ->with(['sets.individual_item.warehouses', 'warehouses', 'images'])
            ->firstOrFail();

        // Calcular precio normal = suma de precios individuales × cantidad
        $normalTotal = 0;
        foreach ($bundle->sets as $setItem) {
            if ($setItem->individual_item) {
                $normalTotal += $setItem->individual_item->sale_unit_price * $setItem->quantity;
            }
        }

        $packPrice   = (float) ($bundle->sale_unit_price_set ?: $bundle->sale_unit_price);
        $savings     = max(0, $normalTotal - $packPrice);
        $savingsPct  = $normalTotal > 0 ? round(($savings / $normalTotal) * 100) : 0;
        $stock       = $bundle->warehouses->sum('stock');
        $symbol      = $bundle->currency_type['symbol'] ?? 'S/';

        // Imagen principal
        $mainImage = ($bundle->image && $bundle->image !== 'imagen-no-disponible.jpg')
            ? asset('storage/uploads/items/' . $bundle->image)
            : asset('logo/imagen-no-disponible.jpg');

        // Imágenes adicionales
        $galleryImages = collect([$mainImage]);
        if ($bundle->images && $bundle->images->count()) {
            foreach ($bundle->images as $img) {
                $url = asset('storage/uploads/items/' . $img->image_url);
                if (!$galleryImages->contains($url)) $galleryImages->push($url);
            }
        }

        // Flash sale activa para este item
        $flashSale = \App\Models\Tenant\FlashSale::active()
            ->whereHas('items', fn($q) => $q->where('items.id', $bundle->id))
            ->first();

        $flashEndsAt = $flashSale ? $flashSale->ends_at : null;

        return view('ecommerce::bundles.landing', compact(
            'bundle', 'normalTotal', 'packPrice', 'savings', 'savingsPct',
            'stock', 'symbol', 'mainImage', 'galleryImages', 'flashEndsAt'
        ));
    }

    /**
     * Devuelve el saldo de puntos y la configuración del sistema para el cliente autenticado.
     */
    public function pointsBalance()
    {
        $user = auth('ecommerce')->user();
        if (!$user) {
            return response()->json(['enabled' => false, 'balance' => 0, 'rate' => 0, 'earn_rate' => 0]);
        }

        $cfg = Configuration::getDataPointSystem();
        return response()->json([
            'enabled'    => (bool) $cfg->enabled_point_system,
            'balance'    => (float) $user->accumulated_points,
            // 1 punto = S/1 de descuento (tasa fija en esta versión)
            'point_value'=> 1,
            // Para ganar: cada point_system_sale_amount gastado → quantity_of_points puntos
            'sale_amount'=> (float) $cfg->point_system_sale_amount,
            'earn_rate'  => (float) $cfg->quantity_of_points,
        ]);
    }

    public function paymentCashEmail($customer_email, $document)
    {
        try {
            $email = $customer_email;
            $mailable = new CulqiEmail($document);
            $id = (int) $document->id;
            $model = __FILE__.";;".__LINE__;
            $sendIt = EmailController::SendMail($email, $mailable, $id, $model);
            /*
            Configuration::setConfigSmtpMail();
            $array_email = explode(',', $customer_email);
            if (count($array_email) > 1) {
                foreach ($array_email as $email_to) {
                    $email_to = trim($email_to);
                if(!empty($email_to)) {
                        Mail::to($email_to)->send(new CulqiEmail($document));
                    }
                }
            } else {
                Mail::to($customer_email)->send(new CulqiEmail($document));
            }*/
        }catch(\Exception $e)
        {
            return true;
        }
    }

    public function ratingItem(Request $request)
    {
        if (auth('ecommerce')->user()) {
            $user_id = auth('ecommerce')->id();
            $user    = auth('ecommerce')->user();
            $row = ItemsRating::firstOrNew(['user_id' => $user_id, 'item_id' => $request->item_id]);
            $row->value         = $request->value;
            $row->reviewer_name = $request->reviewer_name ?? $user->name ?? 'Usuario';
            $row->comment       = $request->comment ?? null;
            $row->save();
            return ['success' => true, 'message' => 'Rating Guardado'];
        }
        return ['success' => false, 'message' => 'No se guardó Rating'];
    }

    public function getReviews($id)
    {
        $reviews = ItemsRating::where('item_id', $id)
            ->orderByDesc('created_at')
            ->get(['id', 'value', 'reviewer_name', 'comment', 'created_at']);

        $total  = $reviews->count();
        $avg    = $total ? round($reviews->avg('value'), 1) : 0;
        $dist   = [];
        for ($i = 5; $i >= 1; $i--) {
            $count   = $reviews->where('value', $i)->count();
            $dist[$i] = ['count' => $count, 'pct' => $total ? round($count / $total * 100) : 0];
        }

        return response()->json([
            'total'   => $total,
            'avg'     => $avg,
            'dist'    => $dist,
            'reviews' => $reviews,
        ]);
    }

    public function manifest()
    {
        $company = Company::first();
        $config  = ConfigurationEcommerce::first();

        $name      = $company->trade_name ?? $company->name ?? 'Tienda Online';
        $short     = mb_substr($name, 0, 12);
        $primary   = $config->color ?? '#ff8000';
        $bgColor   = '#ffffff';

        // Build icon URLs
        $faviconRaw = $company->favicon ?? null;
        $iconUrl = $faviconRaw
            ? (str_contains($faviconRaw, 'storage/')
                ? asset($faviconRaw)
                : asset('storage/uploads/favicons/' . $faviconRaw))
            : asset('porto-ecommerce/assets/images/icons/favicon.ico');

        $manifest = [
            'name'             => $name,
            'short_name'       => $short,
            'description'      => $config->seo_description ?? 'Tienda Online',
            'start_url'        => '/ecommerce',
            'scope'            => '/ecommerce',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => $bgColor,
            'theme_color'      => $primary,
            'lang'             => 'es',
            'categories'       => ['shopping'],
            'icons'            => [
                ['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => $iconUrl, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'screenshots'      => [],
        ];

        return response()->json($manifest, 200, [
            'Content-Type'  => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function offline()
    {
        $company = Company::first();
        $config  = ConfigurationEcommerce::first();
        return view('ecommerce::offline', compact('company', 'config'));
    }

    public function applyCoupon(Request $request)
    {
        $code   = strtoupper(trim($request->coupon_code ?? ''));
        $amount = (float) ($request->amount ?? 0);

        if (!$code) {
            return response()->json(['success' => false, 'message' => 'Ingresa un código de cupón.']);
        }

        try {
            $coupon = Coupon::where('code', $code)->first();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Cupón no válido.']);
        }

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'Cupón no válido.']);
        }

        $error = $coupon->validate($amount);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        $discount = $coupon->calculateDiscount($amount);
        $label    = $coupon->type === 'percentage'
            ? $coupon->value . '% de descuento'
            : 'S/ ' . number_format($coupon->value, 2) . ' de descuento';

        return response()->json([
            'success'  => true,
            'code'     => $coupon->code,
            'discount' => $discount,
            'label'    => $label,
            'message'  => '¡Cupón aplicado! ' . $label,
        ]);
    }

    public function getRating($id)
    {
        if(auth('ecommerce')->user())
        {
            $user_id = auth('ecommerce')->id();
            $row = ItemsRating::where('user_id', $user_id)->where('item_id', $id)->first();
            return[
                'success' => true,
                'value' => ($row) ? $row->value : 0,
                'message' => 'Valor Obtenido'
            ];
        }
        return[
            'success' => false,
            'value' => 0,
            'message' => 'No se obtuvo valor'
        ];

    }

    private function getExchangeRateSale(){

        $exchange_rate = app(ServiceController::class)->exchangeRateTest(date('Y-m-d'));

        return (array_key_exists('sale', $exchange_rate)) ? $exchange_rate['sale'] : 1;


    }

    public function profile()
    {
        if (!auth('ecommerce')->user()) {
            return redirect()->route('tenant_ecommerce_login');
        }
        $user = auth('ecommerce')->user();
        return view('ecommerce::profile.index', compact('user'));
    }

    public function saveDataUser(Request $request)
    {
        $user = auth('ecommerce')->user();
        if ($request->address) {
            $user->address = $request->address;
        }
        if ($request->telephone) {
            $user->telephone = $request->telephone;
        }
        if ($request->name) {
            $user->name = $request->name;
        }

        $user->save();

        return ['success' => true];
    }

    public function changePassword(Request $request)
    {
        $user = auth('ecommerce')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'La contraseña actual no es correcta']);
        }

        if ($request->new_password !== $request->new_password_confirmation) {
            return response()->json(['success' => false, 'message' => 'Las contraseñas no coinciden']);
        }

        if (strlen($request->new_password) < 6) {
            return response()->json(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        }

        $user->password = \Hash::make($request->new_password);
        $user->save();

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    }

    public function searchDocument($type, $number)
    {
        return (new ServiceData)->service($type, $number);
    }

    /**
     * Tracking público — el cliente consulta el estado de su pedido.
     * Sin login, accesible por tracking number del courier o número de documento.
     */
    public function tracking(\Illuminate\Http\Request $request)
    {
        $saleNote = null;
        $error    = null;
        $query    = $request->input('q');

        if ($query) {
            $q = trim($query);

            $saleNote = \App\Models\Tenant\SaleNote::where('tracking_number', $q)
                ->with(['person', 'items.relation_item'])
                ->first();

            if (!$saleNote) {
                if (str_contains($q, '-')) {
                    [$series, $number] = explode('-', $q, 2);
                    $saleNote = \App\Models\Tenant\SaleNote::where('series', trim($series))
                        ->where('number', (int) trim($number))
                        ->with(['person', 'items.relation_item'])
                        ->first();
                } else {
                    $saleNote = \App\Models\Tenant\SaleNote::where('number', (int) $q)
                        ->with(['person', 'items.relation_item'])
                        ->first();
                }
            }

            if (!$saleNote) {
                $error = 'No encontramos ningún pedido con ese número. Verifica e intenta nuevamente.';
            }
        }

        $timeline = $saleNote ? $this->buildTrackingTimeline($saleNote) : [];

        return view('ecommerce::tracking.index', compact('saleNote', 'timeline', 'error', 'query'));
    }

    private function buildTrackingTimeline(\App\Models\Tenant\SaleNote $saleNote): array
    {
        $status = $saleNote->logistic_status;

        if (!$status) {
            return [['label' => 'Pedido registrado', 'description' => 'Tu pedido fue recibido.', 'icon' => '📋', 'completed' => true, 'active' => true]];
        }

        if (in_array($status->value, ['RECOGIDO', 'ENTREGA_INMEDIATA'])) {
            return [
                ['label' => 'Pedido registrado', 'description' => 'Tu pedido fue recibido.',      'icon' => '📋', 'completed' => true, 'active' => false],
                ['label' => $status->label(),     'description' => 'Pedido entregado / retirado.', 'icon' => '✅', 'completed' => true, 'active' => true],
            ];
        }

        $steps = ['PENDIENTE', 'PREPARANDO', 'LISTO_DESPACHO', 'DESPACHADO'];
        $index = array_search($status->value, $steps);

        $courierDesc = $saleNote->courier_name
            ? "Enviado con {$saleNote->courier_name}." . ($saleNote->tracking_number ? " Guía: {$saleNote->tracking_number}" : '')
            : 'Tu pedido está en camino.';

        return [
            ['label' => 'Pedido recibido',  'description' => 'Tu pedido está en cola de almacén.',    'icon' => '📋', 'completed' => $index >= 0, 'active' => $index === 0],
            ['label' => 'En preparación',   'description' => 'El almacén está preparando tu pedido.', 'icon' => '📦', 'completed' => $index >= 1, 'active' => $index === 1],
            ['label' => 'Listo para envío', 'description' => 'Empacado y listo para despacho.',       'icon' => '🏷️', 'completed' => $index >= 2, 'active' => $index === 2],
            ['label' => 'En camino',        'description' => $courierDesc,                            'icon' => '🚚', 'completed' => $index >= 3, 'active' => $index === 3],
        ];
    }

    // ── Ubigeo en cascada ────────────────────────────────────────────────────
    public function ubigeoGetDepartments()
    {
        $data = \App\Models\Tenant\Catalogs\Department::where('active', 1)
            ->orderBy('description')
            ->get(['id', 'description']);
        return response()->json($data);
    }

    public function ubigeoGetProvinces($dep_id)
    {
        $data = \App\Models\Tenant\Catalogs\Province::where('department_id', $dep_id)
            ->where('active', 1)
            ->orderBy('description')
            ->get(['id', 'description']);
        return response()->json($data);
    }

    public function ubigeoGetDistricts($prov_id)
    {
        $data = \App\Models\Tenant\Catalogs\District::where('province_id', $prov_id)
            ->where('active', 1)
            ->orderBy('description')
            ->get(['id', 'description']);
        return response()->json($data);
    }
}
