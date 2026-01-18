<?php

namespace Modules\Item\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTagsTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'fields.*.html_id' => 'required|string|max:255',
            'fields.*.has_image' => 'required|boolean',  
            'canvas' => 'required|array',
            'canvas.width' => 'required|numeric',
            'canvas.height' =>  'required|numeric',
            'fields' => 'required|array',
            'fields.*.type' => 'required|in:text,barcode,image',
            'fields.*.systemData' => 'nullable  ',
            'fields.*.position.top' =>  ['required', 'regex:/^[0-9]+(\.[0-9]+)?px$/'],
            'fields.*.position.left' => ['required', 'regex:/^[0-9]+(\.[0-9]+)?px$/'],
            'fields.*.position.width' => ['required', 'regex:/^[0-9]+(\.[0-9]+)?px$/'],
            'fields.*.position.height' => ['required', 'regex:/^[0-9]+(\.[0-9]+)?px$/'],
            'fields.*.content' => 'nullable|array',
            'fields.*.barcode' => 'nullable|array',
            'name' => 'required|string|max:255',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
