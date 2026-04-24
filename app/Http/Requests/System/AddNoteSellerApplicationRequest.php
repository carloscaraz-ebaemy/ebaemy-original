<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class AddNoteSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'note' => 'required|string|min:3|max:2000',
        ];
    }
}
