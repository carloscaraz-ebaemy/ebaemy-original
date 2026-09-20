<?php

namespace Modules\Item\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Item\Models\Category;
use Modules\Item\Http\Resources\CategoryCollection;
use Modules\Item\Http\Resources\CategoryResource;
use Modules\Item\Http\Requests\CategoryRequest;
use Modules\Finance\Helpers\UploadFileHelper;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{

    public function index()
    {
        return view('item::categories.index');
    }


    public function columns()
    {
        return [
            'name' => 'Nombre',
        ];
    }

    public function records(Request $request)
    {
        $records = Category::where($request->column, 'like', "%{$request->value}%")
                            ->latest();

        return new CategoryCollection($records->paginate(config('tenant.items_per_page')));
    }


    public function record($id)
    {
        $record = Category::findOrFail($id);

        return $record;
    }

    /**
     * Crea o edita una nueva categoría.
     * El nombre de categoría debe ser único, por lo tanto se valida cuando el nombre existe.
     *
     * @param CategoryRequest $request
     *
     * @return array
     */
    public function store(CategoryRequest $request)
    {
        $id = (int)$request->input('id');
        $name = $request->input('name');
        $error = null;
        $category = null;
        if(!empty($name)){
            $category = Category::where('name', $name);
            if(empty($id)) {
                $category= $category->first();
                if (!empty($category)) {
                    $error = 'El nombre de categoría ya existe';
                }
            }else{
                $category = $category->where('id','!=',$id)->first();
                if (!empty($category)) {
                    $error = 'El nombre de categoría ya existe para otro registro';
                }
            }
        }
        $data = [
            'success' => false,
            'message' => $error,
            'data' => $category
        ];
        if(empty($error)){
            $category = Category::firstOrNew(['id' => $id]);
            $category->fill($request->all());

            // Sólo dos niveles. Si la categoría ya tiene hijas no puede
            // colgarse de otra: quedaría un nieto, y ni el menú de la tienda
            // ni el filtro saben bajar tan hondo.
            if ($category->id && $request->filled('parent_id')
                && Category::where('parent_id', $category->id)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Esta categoría ya es un grupo con subcategorías dentro. Sácalas primero si quieres moverla.',
                ];
            }

            // Y el padre elegido tiene que ser de primer nivel.
            if ($request->filled('parent_id')) {
                $parent = Category::find((int) $request->input('parent_id'));
                if (!$parent || $parent->parent_id !== null) {
                    return [
                        'success' => false,
                        'message' => 'El grupo elegido no es una categoría principal.',
                    ];
                }
            }

            $temp_path = $request->input('temp_path');
            if($temp_path) {

                UploadFileHelper::checkIfValidFile($request->input('image'), $temp_path, true);

                $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'categories'.DIRECTORY_SEPARATOR;
                $file_name_old = $request->input('image');
                $file_name_old_array = explode('.', $file_name_old);
                $file_content = file_get_contents($temp_path);
                $datenow = date('YmdHis');
                $file_name = Str::slug($category->name).'-'.$datenow.'.'.$file_name_old_array[1];
                Storage::put($directory.$file_name, $file_content);
                $category->image = $file_name;

            }else if(!$request->input('image') && !$request->input('temp_path') && !$request->input('image_url')){
                $category->image = 'imagen-no-disponible.jpg';
            }

            $category->save();

            $data = [
                'success' => true,
                'message' => ($id)?'Categoría editada con éxito':'Categoría registrada con éxito',
                'data' => $category
            ];
        }
        return $data;

    }

    /**
     * Categorías de primer nivel, para el selector de grupo.
     */
    public function parents()
    {
        return Category::parents()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Listado plano id → nombre, para los selectores.
     *
     * `searchData` no sirve aquí: sin término de búsqueda devuelve sólo diez
     * filas, que es justo lo que necesita un autocompletado y justo lo que no
     * necesita un desplegable de destino.
     */
    public function list()
    {
        return Category::orderBy('name')->get(['id', 'name', 'parent_id']);
    }

    /**
     * Fusiona una categoría en otra: los productos se mudan y la vacía se
     * borra.
     *
     * Con 164 categorías importadas de Saga, muchas con un solo producto,
     * moverlas de una en una desde la ficha de cada artículo no es trabajo que
     * se le pueda pedir a nadie.
     */
    public function merge(Request $request)
    {
        $request->validate([
            'from_id' => 'required|integer|different:to_id',
            'to_id'   => 'required|integer',
        ], [
            'from_id.different' => 'No se puede fusionar una categoría consigo misma.',
        ]);

        $from = Category::findOrFail($request->input('from_id'));
        $to   = Category::findOrFail($request->input('to_id'));

        if (Category::where('parent_id', $from->id)->exists()) {
            return [
                'success' => false,
                'message' => 'Esa categoría tiene subcategorías dentro. Muévelas o fusiónalas primero.',
            ];
        }

        $moved = 0;

        \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($from, $to, &$moved) {
            $moved = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('items')
                ->where('category_id', $from->id)
                ->update(['category_id' => $to->id]);

            $from->delete();
        });

        return [
            'success' => true,
            'message' => "Fusionada en «{$to->name}». Se movieron {$moved} producto(s).",
        ];
    }

    public function destroy($id)
    {
        try {

            $category = Category::findOrFail($id);
            $category->delete();

            return [
                'success' => true,
                'message' => 'Categoría eliminada con éxito'
            ];

        } catch (Exception $e) {

            return ($e->getCode() == '23000') ? ['success' => false,'message' => "La categoría esta siendo usada por otros registros, no puede eliminar"] : ['success' => false,'message' => "Error inesperado, no se pudo eliminar la categoría"];

        }

    }

    
    /**
     *
     * @param  Request $request
     * @return array
     */
    public function searchData(Request $request)
    {
        $input = $request->input ?? null;
        $records = Category::query();
        
        if($input)
        {
            $records->where('name', 'like', "%{$input}%")
                    ->filterForTables()
                    ->take(100);
        }
        else
        {
            $records->take(10);
        }

        return $records->get();
    }

    public function upload(Request $request)
    {
        
        $validate_upload = UploadFileHelper::validateUploadFile($request, 'file', 'jpg,jpeg,png,gif,svg');
        
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
