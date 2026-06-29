<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTailorProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
           'shop_name' => 'nullable|string|max:150',
        'shop_address' => 'nullable|string|max:255',
        'experience_years' => 'nullable|integer|min:0|max:60',
        'description' => 'nullable|string|max:500',
        ];
    }
}
