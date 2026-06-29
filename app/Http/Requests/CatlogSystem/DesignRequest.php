<?php

namespace App\Http\Requests\CatlogSystem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DesignRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:cloth_categories,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png',
            'image_url' => 'nullable|string|max:2048',
            'description' => 'nullable|string',
            'additional_price' => 'nullable|numeric|min:0',
            'secondary_price' => 'nullable|numeric|min:0',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}