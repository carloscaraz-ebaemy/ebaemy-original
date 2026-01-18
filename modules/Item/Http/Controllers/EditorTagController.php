<?php

namespace Modules\Item\Http\Controllers;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Http\Controllers\SearchItemController;
use App\Models\Tenant\Company;
use App\Models\Tenant\Item;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Item\Http\Requests\CreateTagsTemplateRequest;
use Modules\Item\Models\TagTemplate;
use Modules\Item\Models\TagTemplateField;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

class EditorTagController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('item::editor-tag.index');
    }

    public function tables()
    {

        $items = SearchItemController::getItemsToDocuments();
        $templates = TagTemplate::all();
        $count_items = Item::count();

        return [
            'templates' => $templates,
            'items' => $items,
            'count_items' => $count_items,  
        ];

    }

    public function store(CreateTagsTemplateRequest $request)
    {
        $establshment_id = auth()->user()->establishment_id;
        $arrTemplate = [
            'establishment_id' => $establshment_id,
            'name' => $request->input('name'),
            'width' => $request->input('canvas.width'),
            'height' => $request->input('canvas.height'),
        ];

        $template = new TagTemplate();
        $template->fill($arrTemplate);
        $template->save();


        $arrFields = collect($request->input('fields'))->transform(function ($value)  use ($template) {
            return [
                'type' => $value['type'],
                'column' => $value['systemData'] ?? null,
                'x' => $value['position']['left'],
                'y' =>  $value['position']['top'],
                'width' => $value['position']['width'],
                'height' => $value['position']['height'],
                'style' => isset($value['content']) ? json_encode($value['content']) : null,
                'barcode' => isset($value['barcode']) ? json_encode($value['barcode']) : null,
                'tag_template_id' => $template->id,
                'html_id' => $value['html_id'] ?? null,
                'has_image' => $value['has_image'] ?? false,
            ];
        });

        TagTemplateField::insert($arrFields->toArray());

        // $collect_image = TagTemplate::find($template->id)
        //             ->fields
        //     ->filter(function ($field) {
        //         return $field->type === 'image' && $field->has_image && !isset($field->image);
        //     })->transform(function ($field) {
        //         return [
        //             'id' => $field->id,
        //             'html_id' => $field->html_id,
        //         ];
        //     })->values();

        $collect_image = $this->filterMissingImages($template->id);

        return [
            'success' => true,
            'fields_image' =>  $collect_image,
            'message' => 'Plantilla guardada correctamente'
        ];
    }

    public function saveImage(Request $request)
    {
        $template_field = TagTemplateField::findOrFail($request->input('id'));  
        $template = TagTemplate::findOrFail($template_field->tag_template_id);

        if ($request->hasFile('image')) {
            $file = $request->file('image');

            $extension = $file->getClientOriginalExtension();
            $name = "tag_image_field-". $request->input('id') ."_{$template->name}-" . date('YmdHis') . ".".$extension;

            $file->storeAs("tag_images", $name, 'public');

            $template_field->image = $name;
            $template_field->save();

            return [
                'success' => true,
                'message' => 'Imagen guardada correctamente',
                'image_url' => urlencode(asset('storage'.DIRECTORY_SEPARATOR.'tag_images'. DIRECTORY_SEPARATOR . $name)),
            ];
        }

        return [
            'success' => false,
            'message' => 'No se ha subido ninguna imagen',
        ];
    }


    public function records()
    {
        $templates = TagTemplate::with('fields')->get();
        return [
            'templates' => $templates->transform(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'canvas' => [
                        'width' => $item->width,
                        'height' => $item->height,
                    ],
                    'fields' => $item->fields->transform(function($field) {
                        return [
                            'id' => $field->id,
                            'html_id' => "$field->html_id",
                            'type' => $field->type,
                            'systemData' => $field->column,
                            'position' => [
                                'top' => $field->y,
                                'left' => $field->x,
                                'width' => $field->width,
                                'height' => $field->height,
                            ],
                            'has_image' => $field->has_image,
                            'path' => $field->image,
                            'content' => $field->style,
                            'barcode' => $field->barcode,
                            'image' => $field->image ? asset('storage'.DIRECTORY_SEPARATOR.'tag_images'.DIRECTORY_SEPARATOR .rawurlencode($field->image)) : null,
                        ];
                    }),
                    'is_default' => $item->is_default,
                ];
            })
        ];
    }

    public function delete($id)
    {
        $template = TagTemplate::findOrFail($id);
        $template->delete();

        return [
            'success' => true,
            'message' => 'Plantilla eliminada correctamente'
        ];
    }

    public function export(Request $request)
    {
        ini_set('pcre.backtrack_limit', '50000000');
        $items_id = $request->query('items');
        $type = $request->input('type', 'individual');
        $template_id = $request->input('template_id');
        $quantity_per_item = $request->input('quantity_per_item', 1);

        $template = TagTemplate::with('fields')->findOrFail($template_id);

        $template = TagTemplate::with('fields')->findOrFail($template_id);

        if (!$template) {
            return [
                'success' => false,
                'message' => 'Plantilla no encontrada'
            ];
        }

        if ($type === 'individual') {
            $items = Item::with('brand', 'category')->whereIn('id', $items_id)->select('internal_id', 'name', 'description', 'sale_unit_price', 'barcode' ,'category_id', 'brand_id', 'unit_type_id', 'attributes' )->get();
        } else if ($type === 'all') {
            $items = Item::with('brand', 'category',)->select('internal_id','name', 'description', 'sale_unit_price', 'barcode', 'category_id', 'brand_id', 'unit_type_id', 'attributes')->get();
        }

        $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    $template->width,
                    $template->height
                ],
                'margin_top' => 2,
                'margin_right' => 2,
                'margin_bottom' => 0,
                'margin_left' => 2
            ]);
        $html = view('tenant.items.exports.editor_tags', [
            'items' => $items,
            'template' => $template,
            'type' => $type,
            'quantity_per_item' => $quantity_per_item,
        ])->render();

        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        return response($pdf->output('etiquetas_'.now()->format('Y_m_d').'.pdf', 'I'), 200)
      ->header('Content-Type', 'application/pdf');
    
    }


    public function update(int $id, Request $request)
    {
        $template = TagTemplate::findOrFail($id);

        $template->width = $request->input('canvas.width');
        $template->height = $request->input('canvas.height');
        $template->save();


        $ids = collect($request->input('fields'))->filter(function ($value) {
            return isset($value['b_id']);
        })->pluck('b_id')->toArray();

        $missing_ids = TagTemplateField::where('tag_template_id', $template->id)
            ->whereNotIn('id', $ids)
            ->pluck('id')
            ->toArray();


        collect($request->input('fields'))->each(function ($value)  use ($template) {

            $data = [
                'type' => $value['type'],
                'column' => $value['systemData'] ?? null,
                'x' => $value['position']['left'],
                'y' =>  $value['position']['top'],
                'width' => $value['position']['width'],
                'height' => $value['position']['height'],
                'style' => isset($value['content']) ? json_encode($value['content']) : null,
                'barcode' => isset($value['barcode']) ? json_encode($value['barcode']) : null,
                'html_id' => $value['html_id'] ?? null,
                'tag_template_id' => $template->id,
                'has_image' => $value['has_image'] ?? false,    
                'image' => $value['path'] ?? null,
            ];

            TagTemplateField::updateOrCreate(
                ['id' => $value['b_id'] ?? null],
                $data
            );
        });


        // Ids que no se encuentra en la actualizacion

        TagTemplateField::whereIn('id', $missing_ids)->delete();

        $fields_image = $this->filterMissingImages($id);

        return [
            'success' => true,
            'message' => 'Plantilla actualizada correctamente',
            'fields_image' =>  $fields_image,

        ];
    }

    public function default(int $id)
    {
        TagTemplate::query()->update(['is_default' => false]);

        $template = TagTemplate::findOrFail($id);
        $template->is_default = true;
        $template->save();

        return [
            'success' => true,
            'message' => 'Plantilla por defecto actualizada correctamente',
        ];
    }

    private function filterMissingImages($id)
    {
        return TagTemplate::find($id)->fields->filter(function ($field) {
            return $field->type === 'image' && $field->has_image && !isset($field->image);
        })->transform(function ($field) {
            return [
                'id' => $field->id,
                'html_id' => $field->html_id,
            ];
        })->values();
    }

}
