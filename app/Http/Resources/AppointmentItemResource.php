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

        return [
            'id' => $this->id,
            'garment_id' => $this->garment_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'price_type' => $this->price_type,
            'garment' => $this->relationLoaded('garment') && $this->garment ? [
                'id' => $this->garment->id,
                'name' => $isCustom 
                    ? ($this->garment->design?->name ?? $this->garment->name) . ($this->price_type ? ' (' . ucfirst($this->price_type) . ')' : '')
                    : $this->garment->name,
                'price' => $this->price ?? $this->garment->price ?? 0,
                'base_price' => $this->price ?? $this->garment->price ?? 0,
                'image' => $this->garment?->image,
                'design' => $isCustom ? null : $this->garment->design?->name,
                'category' => $this->garment->category?->name,
            ] : null,
        ];
    }
}
