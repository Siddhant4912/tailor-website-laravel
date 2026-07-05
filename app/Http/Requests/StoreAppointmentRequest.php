<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Optionally restrict to specific roles
    }

    public function rules(): array
    {
        $maxDate = now()->addDays(7)->format('Y-m-d');
        return [
            'type' => 'required|string|in:catalog_visit,custom_cloth',
            'appointment_date' => 'required|date|after:today|before_or_equal:' . $maxDate,
            'appointment_time' => 'required|date_format:H:i',
            
            'address_line' => 'required|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            
            'notes' => 'nullable|string',
            
            'items' => 'required|array|min:1',
            'items.*.garment_id' => 'required|exists:garments,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.price_type' => 'nullable|string|in:additional,secondary,stitching,fabric',
        ];
    }
}
