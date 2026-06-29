<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TailorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_name' => $this->shop_name,
            'address' => $this->address,
            'rating' => $this->rating,
            'experience_years' => $this->experience_years,
            'is_available' => $this->is_available,
        ];
    }
}
