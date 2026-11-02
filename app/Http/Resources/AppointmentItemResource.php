<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->appointment?->type;
        // If type is not directly available, try to resolve it from string/Backed Enum
        $typeStr = is_object($type) ? $type->value : (string) $type;
        $isCustom = $typeStr === 'custom_cloth';

        $garmentData = null;
        if ($this->relationLoaded('garment') && $this->garment) {
            $garmentData = [
                'id' => $this->garment->id,
                'name' => $isCustom 
                    ? ($this->garment->design?->name ?? $this->garment->name) . ($this->price_type ? ' (' . ucfirst($this->price_type) . ')' : '')
                    : $this->garment->name,
                'price' => $this->price ?? $this->garment->price ?? 0,
                'base_price' => $this->price ?? $this->garment->price ?? 0,
                'image' => $this->garment?->image,
                'design' => $isCustom ? null : $this->garment->design?->name,
                'category' => $this->garment->category?->name,
            ];
        } elseif ($this->custom_name || empty($this->garment_id)) {
            $isExplicitOther = empty($this->custom_name) || strtolower(trim($this->custom_name)) === 'other (custom design)';
            $garmentData = [
                'id' => null,
                'name' => $this->custom_name ?: 'Other (Custom Design)',
                'price' => $this->price ?? ($isExplicitOther ? 850 : 0),
                'base_price' => $this->price ?? ($isExplicitOther ? 850 : 0),
                'image' => null,
                'design' => $isExplicitOther ? 'Custom Design' : ($this->custom_name ?: 'Stitching Design'),
                'category' => 'Stitching',
                'is_other' => $isExplicitOther,
            ];
        }

        $unitPrice = (float) ($this->price ?? ($garmentData['price'] ?? 0));
        $qty = (int) ($this->quantity ?? 1);

        return [
            'id' => $this->id,
            'garment_id' => $this->garment_id,
            'custom_name' => $this->custom_name,
            'quantity' => $qty,
            'price' => $unitPrice,
            'total' => $unitPrice * $qty,
            'price_type' => $this->price_type,
            'garment' => $garmentData,
        ];
    }
}
