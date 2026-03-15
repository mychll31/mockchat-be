<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'sometimes|required|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'image_url' => 'nullable|url|max:500',
        ];
    }
}
