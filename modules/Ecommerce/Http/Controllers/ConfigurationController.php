<?php

namespace Modules\Ecommerce\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Company;
use App\Http\Requests\Tenant\ConfigurationEcommerceRequest;
use App\Http\Resources\Tenant\ConfigurationEcommerceResource;
use Modules\Finance\Helpers\UploadFileHelper;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant\ConfigurationScript;


class ConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('ecommerce::configuration.index');
    }

    public function record()
    {
        $configuration = ConfigurationEcommerce::first();
        $record = new ConfigurationEcommerceResource($configuration);
        return $record;
    }

    public function store_configuration_terms(Request $request)
    {
        // Buscamos el primer registro de configuración
        $configuration = ConfigurationEcommerce::first();

        // Si por alguna razón no existe, lo creamos
        if (!$configuration) {
            $configuration = new ConfigurationEcommerce();
        }

        // Llenamos el modelo con los datos del request (v-model de Vue)
        // Esto funciona porque ya agregaste los campos al $fillable del modelo
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Términos y políticas actualizados correctamente'
        ];
    }

  
    



    public function store_configuration(ConfigurationEcommerceRequest $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
    }

    public function getSocialScripts()
    {
        return ConfigurationScript::all();
    }

    public function saveSocialScripts(Request $request)
    {
        $scripts = $request->input('scripts', []);

       

        $ids = [];

        foreach ($scripts as $data) {
            $validated = validator($data, [
                'id' => 'nullable',
                'title' => 'required|string|max:255',
                'script' => 'required|string',
                'position' => 'required|in:head,body',
                'active' => 'boolean',
            ])->validate();

            $script = ConfigurationScript::updateOrCreate(
                ['id' => $data['id'] ?? null],
                $validated
            );

            $ids[] = $script->id;
        }

        // (Opcional) eliminar scripts que ya no están
        ConfigurationScript::whereNotIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Scripts actualizados y eliminados.']);
    }



    public function store_configuration_seo(Request $request)
    {
    $request->validate([
        'seo_title' => 'nullable|string|max:255',
        'seo_description' => 'nullable|string|max:255',
        'seo_keywords' => 'nullable|string|max:255',

        'og_title' => 'nullable|string|max:255',
        'og_description' => 'nullable|string|max:255',
        'og_image' => 'nullable|string|max:255',

        'twitter_title' => 'nullable|string|max:255',
        'twitter_description' => 'nullable|string|max:255',
        'twitter_image' => 'nullable|string|max:255',

        'indexable' => 'nullable|boolean',
    ]);

    $configuration = ConfigurationEcommerce::first(); // <--- AQUÍ
    if (!$configuration) {
        $configuration = ConfigurationEcommerce::create([]);
    }

    $configuration->update([
        'seo_title' => $request->seo_title,
        'seo_description' => $request->seo_description,
        'seo_keywords' => $request->seo_keywords,

        'og_title' => $request->og_title,
        'og_description' => $request->og_description,
        'og_image' => $request->og_image,

        'twitter_title' => $request->twitter_title,
        'twitter_description' => $request->twitter_description,
        'twitter_image' => $request->twitter_image,

        'indexable' => (bool) $request->indexable,
    ]);

    return [
        'success' => true,
        'message' => 'Configuración SEO actualizada correctamente'
    ];
}



    public function store_configuration_culqui(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Culqui actualizada'
        ];
    }

    public function store_configuration_paypal(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Paypal actualizada'
        ];
    }

    public function store_configuration_tag(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Tags actualizada'
        ];
    }

    public function store_configuration_social(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de Redes Sociales actualizada'
        ];
    }

    public function uploadFile(Request $request)
    {
        if ($request->hasFile('file')) {

            $config = ConfigurationEcommerce::first();
            $company = Company::first();

            $type = $request->input('type'); //logo_store

            $file = $request->file('file');

            if (!$file->isValid() || empty($file->getPathname()) || !is_file($file->getPathname())) {
                return [
                    'success' => false,
                    'message' =>  __('app.actions.upload.error'),
                ];
            }

            $ext = $file->getClientOriginalExtension();
            $name = $type . '_' . $company->number . '.' . $ext;

            request()->validate(['file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048']);

            UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);

            $stream = fopen($file->getPathname(), 'r');
            Storage::put('public/uploads/logos/' . $name, $stream);
            if (is_resource($stream)) fclose($stream);

            $config->logo = $name;

            $config->save();

            return [
                'success' => true,
                'message' => __('app.actions.upload.success'),
                'name' => $name,
                'type' => $type
            ];
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    public function store_configuration_links(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de links personalizados actualizado'
        ];
    }

    public function store_configuration_color(Request $request)
    {

        $id = $request->input('id');
        $color = $request->input('color_ecommerce');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->color_ecommerce = $color;

        // Guardar preferencias (el cast a array maneja automáticamente el json_encode)
        $configuration->preferences = [
            'show_description' => (int) $request->input('show_description', 1),
            'show_stock' => (int) $request->input('show_stock', 0),
            'only_available_products' => (int) $request->input('only_available_products', 0),
            'full_width_banner' => (int) $request->input('full_width_banner', 0),
        ];

        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de color y preferencias actualizadas correctamente'
        ];
    }


    public function getColorEcommerce()
    {
        $config = \App\Models\Tenant\ConfigurationEcommerce::first();
        $color = $config ? $config->color_ecommerce : null;
        return response()->json(['color' => $color]);
    }
}
