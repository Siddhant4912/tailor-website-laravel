<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryStaffProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aadhaar_number' => $this->aadhaar_number,
            'aadhaar_photo' => $this->aadhaar_photo,
            'aadhaar_photo_url' => ($this->aadhaar_photo && str_starts_with($this->aadhaar_photo, 'aadhaar_photos/')) ? asset('storage/' . $this->aadhaar_photo) : null,
            'vehicle_number' => $this->vehicle_number,
            'emergency_contact' => $this->emergency_contact,
            'is_available' => $this->is_available,
        ];
    }
}
