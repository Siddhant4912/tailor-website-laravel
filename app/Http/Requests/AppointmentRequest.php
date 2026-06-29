<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppointmentRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $isPut = $this->isMethod('put') || $this->isMethod('patch');
        $reqRule = $isPut ? 'sometimes|required' : 'required';

        return [
            // Core details
            'type' => "$reqRule|string|max:255",
            'appointment_date' => "$reqRule|date",
            'appointment_time' => "$reqRule|string",
            'address_line' => "$reqRule|string",
            'city' => "$reqRule|string",
            'state' => "$reqRule|string",
            'pincode' => "$reqRule|string",
            'notes' => 'nullable|string',

            // NEW: Tailoring custom preferences
            'gender' => "$reqRule|string|in:male,female,other",
            'female_tailor_visit' => 'nullable|string|in:yes,no',
            'has_fabric' => "$reqRule|string|in:yes,no",
            'measurement_type' => "$reqRule|string|in:onsite_visit,existing_garment",

            // NEW: Tailoring financials (prices are calculated securely by the backend now)

            // Related Items from the Cart / Catalog selection
            'items' => 'nullable|array',
            'items.*.garment_id' => 'required_with:items|integer|exists:garments,id',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.price_type' => 'nullable|string|in:additional,secondary,stitching,fabric',
            // Prices are ignored if sent from the frontend
            // Allow admin to override customer_id
            'customer_id' => 'nullable|integer|exists:users,id',

            // Allow assigning staff and updating status
            'assigned_staff_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string|in:draft,pending,confirmed,in_progress,completed,cancelled',
            'payment_preference' => 'nullable|string|in:cash,online',
        ];
    }
}
