<?php

namespace Modules\Dispatch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {

        $id = $this->input('id');

        return [
            'plate_number' => [
                'required',
                'string',
                'min:5',
                'max:15',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('tenant.transports')->ignore($id),
            ],
            'brand' => [
                'required',
                'string',
                'max:50',
            ],
            'model' => [
                'required',
                'string',
                'max:50',
            ],
            'tuc' => [
                'nullable',
                'string',
                'min:10',
                'max:15',
                'regex:/^[A-Za-z0-9]+$/',
            ],
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
             
        ];
    }
    public function messages()
    {
        return [
            'plate_number.required' => 'El número de placa es obligatorio',
            'plate_number.regex' => 'El número de placa solo debe contener letras y números (sin espacios, guiones u otros caracteres)',
            'plate_number.min' => 'El número de placa debe tener al menos 5 caracteres',
            'plate_number.max' => 'El número de placa no debe exceder 15 caracteres',
            'plate_number.unique' => 'Este número de placa ya está registrado',
            'brand.required' => 'La marca es obligatoria',
            'model.required' => 'El modelo es obligatorio',
            'tuc.regex' => 'El certificado de habilitación vehicular solo debe contener letras y números',
            'tuc.min' => 'El certificado de habilitación vehicular debe tener al menos 10 caracteres',
            'tuc.max' => 'El certificado de habilitación vehicular no debe exceder 15 caracteres',
        ];
    }
    protected function prepareForValidation()
    {
        $data = [];

        if($this->has('plate_number')){
            $data['plate_number'] = strtoupper(trim($this->plate_number));
        }
        if($this->has('tuc') && !empty($this->tuc)){
            $data['tuc'] = strtoupper(trim($this->tuc));
        }

        $this->merge($data);
    }

}
