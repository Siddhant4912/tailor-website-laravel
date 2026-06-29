<?php

namespace App\Http\Requests\Garments;

use Illuminate\Foundation\Http\FormRequest;

class GarmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'    => 'required|exists:cloth_categories,id',
            'design_id'      => 'required|exists:designs,id',
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'secondary_price'=> 'nullable|numeric|min:0',
            'stitching_time' => 'nullable|integer|min:1',


            'images'   => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:10240',

            // Existing images array for updates
            'existing_images'   => 'nullable|array',
            'existing_images.*' => 'string',

            'measurements'               => 'nullable|array',
            'measurements.*.field_name'  => 'required_with:measurements|string',
            'measurements.*.unit'        => 'nullable|in:inch,cm',
            'measurements.*.is_required' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $newImagesCount = is_array($this->file('images')) ? count($this->file('images')) : 0;
            $existingCount = is_array($this->input('existing_images')) ? count($this->input('existing_images')) : 0;

            $totalCount = $newImagesCount + $existingCount;

            if ($totalCount < 1) {
                $validator->errors()->add('images', 'At least 1 garment image must be uploaded.');
            }

            if ($totalCount > 5) {
                $validator->errors()->add('images', 'A maximum of 5 garment images is allowed.');
            }
        });
    }
}