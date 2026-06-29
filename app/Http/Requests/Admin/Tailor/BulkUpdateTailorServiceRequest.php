<?php

namespace App\Http\Requests\Admin\Tailor;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTailorServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'services' => 'required|array|min:1',
            'services.*.id' => 'required|exists:tailor_services,id',
            'services.*.service_name' => 'required|string|max:255',
            'services.*.base_price' => 'required|numeric|min:0',
        ];
    }
}