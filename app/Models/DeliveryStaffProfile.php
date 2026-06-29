<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryStaffProfile extends Model
{
    protected $fillable = [
        'user_id',
        'aadhaar_number',
        'aadhaar_photo',
        'vehicle_number',
        'emergency_contact',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
