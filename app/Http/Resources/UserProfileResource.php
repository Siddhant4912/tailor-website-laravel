<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_name' => $this->building_name,
            'flat_number' => $this->flat_number,
            'wing' => $this->wing,
            'street' => $this->street,
            'locality' => $this->locality,
            'landmark' => $this->landmark,
            'address' => $this->formatted_address ?: $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'formatted_address' => $this->formatted_address,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'profile_photo' => $this->profile_photo,
        ];
    }
}
