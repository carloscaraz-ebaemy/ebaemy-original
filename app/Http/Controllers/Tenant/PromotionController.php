<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Tenant\PromotionRequest;
use App\Http\Resources\Tenant\PromotionCollection;
use App\Http\Resources\Tenant\PromotionResource;
use Exception;
use Illuminate\Http\Request;
use App\Models\Tenant\Promotion;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\Cache;
use Hyn\Tenancy\Environment;
use Modules\Finance\Helpers\UploadFileHelper;


class PromotionController extends Controller
{
    /** Maximo de banners del slider por tenant. */
    private const MAX_BANNERS = 10;

    public function index()
    {
        return view('tenant.promotion.index');
    }


    public function columns()
    {
        return [
            'description' => 'Nombre'
            // 'description' => 'Descripción'
        ];
    }

    public function tables()
    {
       
        $items = Item::where('apply_store', 1)->get();

        // Categorías del tenant: destino "categoría" del banner.
        $categories = \Modules\Item\Models\Category::orderBy('name')->get(['id', 'name']);

        return compact('items', 'categories');
    }


    public function records(Request $request)
    {
        $records = Promotion::where('apply_restaurant', 0)
            ->where(function ($query) {
                $query->where('type', '<>', 'promotions')
                    ->where('type', '<>', 'spots')
                    ->orWhereNull('type');
            })
            ->orderBy('description');
        
        return new PromotionCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function recordsPromotionList(Request $request)
    {
        $records = Promotion::where('apply_restaurant', 0)->where('type','promotions')->orderBy('description');
        
        return new PromotionCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function recordsSpotList(Request $request)
    {
        $records = Promotion::where('apply_restaurant', 0)->where('type','spots')->orderBy('description');
        
        return new PromotionCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function create()
    {
        return view('tenant.promotion.form');
    }


    public function record($id)
    {
        $record = new PromotionResource(Promotion::findOrFail($id));
        return $record;
    }

    public function store(PromotionRequest $request) {


        $id = $request->input('id');

        if(!$id)
        {
            $count = Promotion::where('apply_restaurant', 0)
                ->where(function ($query) {
                    $query->where('type','=', 'banners') // Verificar que tiene los banners
                    ->orWhereNull('type');
                })
                ->count();
            // El tope era 3, de cuando el slider no tenia orden ni vigencia.
            // Con banners programados por temporada 3 obliga a borrar los
            // viejos para cargar los nuevos; 10 alcanza sin que el carrusel
            // se vuelva pesado.
            if($count >= self::MAX_BANNERS)
            {
                return [
                    'success' => false,
                    'message' => 'Solo se permiten ' . self::MAX_BANNERS . ' banners. Elimina uno o programa su vigencia para reutilizarlo.',
                ];
            }
        }

        $item = Promotion::firstOrNew(['id' => $id]);
        $item->fill($request->all());

        $temp_path = $request->input('temp_path');
        if($temp_path) {

            UploadFileHelper::checkIfValidFile($request->input('image'), $temp_path, true);

            $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR;
            $file_name_old = $request->input('image');
            $file_name_old_array = explode('.', $file_name_old);
            $file_content = file_get_contents($temp_path);
            $datenow = date('YmdHis');
            $file_name = Str::slug($item->description).'-'.$datenow.'.'.$file_name_old_array[1];
            Storage::put($directory.$file_name, $file_content);
            $item->image = $file_name;

        }else if(!$request->input('image') && !$request->input('temp_path') && !$request->input('image_url')){
            $item->image = 'imagen-no-disponible.jpg';
        }

        $this->storeMobileImage($request, $item);
        $this->normalizeSliderFields($request, $item);

        $item->save();
        $this->clearEcommerceCache();

        return [
            'success' => true,
            'message' => ($id)?'Banner editado con éxito':'Banner registrado con éxito',
            'id' => $item->id
        ];
    }

    /**
     * Guarda la imagen vertical del banner. Mismo flujo que la de desktop
     * pero con su propio temp_path, para poder subir una sin tocar la otra.
     */
    private function storeMobileImage(Request $request, Promotion $item): void
    {
        $temp_path = $request->input('temp_path_mobile');
        if (!$temp_path) {
            // Quitar la imagen mobile es explícito: el front manda la clave
            // en null. Si no viene, se conserva la que ya estaba.
            if ($request->exists('image_mobile') && !$request->input('image_mobile')) {
                $item->image_mobile = null;
            }
            return;
        }

        UploadFileHelper::checkIfValidFile($request->input('image_mobile'), $temp_path, true);

        $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR;
        $original  = explode('.', $request->input('image_mobile'));
        $file_name = Str::slug($item->description ?: 'banner').'-mobile-'.date('YmdHis').'.'.end($original);

        Storage::put($directory.$file_name, file_get_contents($temp_path));
        $item->image_mobile = $file_name;
    }

    /**
     * Normaliza los campos del slider antes de guardar.
     *
     * link_type manda: si el tenant elige "producto" se limpia la URL y la
     * categoría, y viceversa. Sin esto quedan destinos huérfanos y el banner
     * apunta a donde no debe (Promotion::getLinkHrefAttribute prioriza por
     * link_type, pero los datos viejos quedarían inconsistentes).
     */
    private function normalizeSliderFields(Request $request, Promotion $item): void
    {
        $type = $request->input('link_type');
        if (in_array($type, ['product', 'url', 'category', 'none'], true)) {
            $item->link_type = $type;

            if ($type !== 'product')  $item->item_id = null;
            if ($type !== 'url')      $item->banner_url = null;
            if ($type !== 'category') $item->link_category_id = null;
        }

        $item->sort_order = (int) $request->input('sort_order', 0);
        $item->starts_at  = $request->input('starts_at') ?: null;
        $item->ends_at    = $request->input('ends_at') ?: null;

        // Rango invertido: se ignora la fecha de fin en vez de dejar el banner
        // permanentemente oculto sin que el tenant entienda por qué.
        if ($item->starts_at && $item->ends_at && $item->ends_at < $item->starts_at) {
            $item->ends_at = null;
        }
    }

    public function storePromotionList(PromotionRequest $request) {


        $id = $request->input('id');

        if(!$id)
        {
            $count = Promotion::where('apply_restaurant', 0)->where('type','promotions')->count();
            if($count > 2)
            {
                return [
                    'success' => false,
                    'message' => 'Solo esta permitido 3 Promociones',
                ];
            }
        }

        $item = Promotion::firstOrNew(['id' => $id]);
        $item->fill($request->all());

        $temp_path = $request->input('temp_path');
        if($temp_path) {

            UploadFileHelper::checkIfValidFile($request->input('image'), $temp_path, true);

            $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR;
            $file_name_old = $request->input('image');
            $file_name_old_array = explode('.', $file_name_old);
            $file_content = file_get_contents($temp_path);
            $datenow = date('YmdHis');
            $file_name = Str::slug($item->description).'-'.$datenow.'.'.$file_name_old_array[1];
            Storage::put($directory.$file_name, $file_content);
            $item->image = $file_name;

        }else if(!$request->input('image') && !$request->input('temp_path') && !$request->input('image_url')){
            $item->image = 'imagen-no-disponible.jpg';
        }

        $item->save();
        $this->clearEcommerceCache();

        return [
            'success' => true,
            'message' => ($id)?'Promocion editada con éxito':'Promocion registrada con éxito',
            'id' => $item->id
        ];
    }

    public function storeSpotList(Request $request) {
        $id = $request->input('id');

        // Validar la URL solo si se proporciona
        $request->validate([
            'spot_url' => 'nullable|url',
        ], [
            'spot_url.url' => 'Debe ingresar una URL válida',
        ]);

        // Validar que tenga imagen al crear (temp_path o image_url)
        if(!$id && !$request->input('temp_path') && !$request->input('image_url')) {
            return [
                'success' => false,
                'message' => 'La imagen es requerida',
            ];
        }

        if(!$id)
        {
            $count = Promotion::where('apply_restaurant', 0)->where('type','spots')->count();
            if($count > 3)
            {
                return [
                    'success' => false,
                    'message' => 'Solo está permitido 4 Anuncios publicitarios',
                ];
            }
        }

        $item = Promotion::firstOrNew(['id' => $id]);
        $item->spot_url = $request->input('spot_url');
        $item->type = 'spots';
        $item->name = $request->input('name') ?? 'Anuncio';
        $item->description = $request->input('description') ?? 'Anuncio publicitario';
        $item->apply_restaurant = 0;
        $item->item_id = null; // Los spots no requieren item_id

        $temp_path = $request->input('temp_path');
        if($temp_path) {

            UploadFileHelper::checkIfValidFile($request->input('image'), $temp_path, true);

            $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR;
            $file_name_old = $request->input('image');
            $file_name_old_array = explode('.', $file_name_old);
            $file_content = file_get_contents($temp_path);
            $datenow = date('YmdHis');
            $file_name = 'spot-'.$datenow.'.'.$file_name_old_array[1];
            Storage::put($directory.$file_name, $file_content);
            $item->image = $file_name;

        }else if(!$request->input('image') && !$request->input('temp_path') && !$request->input('image_url')){
            $item->image = 'imagen-no-disponible.jpg';
        }

        $item->save();
        $this->clearEcommerceCache();

        return [
            'success' => true,
            'message' => ($id)?'Anuncio editado con éxito':'Anuncio registrado con éxito',
            'id' => $item->id
        ];
    }
    
    public function destroy($id)
    {
        $item = Promotion::findOrFail($id);
        $item->status = 0;
        $item->save();

        $this->clearEcommerceCache();

        return [
            'success' => true,
            'message' => 'Promocion eliminada con éxito'
        ];
    }

    private function clearEcommerceCache(): void
    {
        try {
            $uuid = app(Environment::class)->tenant()?->uuid ?? 'system';
            $prefix = 'ec_' . $uuid . '_';
            Cache::forget($prefix . 'spots');
            Cache::forget($prefix . 'categories_with_items');
            Cache::forget($prefix . 'bundles');
            Cache::forget($prefix . 'price_range');
        } catch (\Throwable $e) {
            // No bloquear la operación si falla el cache clear
        }
    }


    public function upload(Request $request)
    {
        
        $validate_upload = UploadFileHelper::validateUploadFile($request, 'file', 'jpg,jpeg,png,gif,svg,webp,heic,heif');
        
        if(!$validate_upload['success']){
            return $validate_upload;
        }

        if ($request->hasFile('file')) {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            return $this->upload_image($new_request);
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    function upload_image($request)
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
}