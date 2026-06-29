<?php

namespace App\Http\Requests\Garments;

use Illuminate\Foundation\Http\FormRequest;

class GarmentMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garment_id' => 'required|exists:garments,id',
            'field_name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'is_required' => 'boolean',
        ];
    }
}