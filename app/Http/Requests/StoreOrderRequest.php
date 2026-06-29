<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'delivery_address' => 'nullable|string',
            'notes' => 'nullable|string',
            
            // For Direct Orders (no appointment)
            'items' => 'sometimes|array',
            'items.*.garment_id' => 'required_with:items|integer|exists:garments,id',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.custom_notes' => 'nullable|string',
            'items.*.size' => 'nullable|string|in:S,M,L,XL,XXL',
            
            // For both or appointment-based
            'measurements' => 'nullable|array',
            'measurements.*.field_name' => 'required_with:measurements|string',
            'measurements.*.value' => 'required_with:measurements|string',

            // For admin creation
            'customer_id' => 'nullable|integer|exists:users,id',
            'payment_method' => 'nullable|string|in:cash,online',
        ];
    }
}
