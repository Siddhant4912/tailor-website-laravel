<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'building_name',
        'flat_number',
        'wing',
        'street',
        'locality',
        'landmark',
        'address',
        'city',
        'district',
        'state',
        'pincode',
        'gender',
        'date_of_birth',
        'profile_photo',
    ];

    public function getFormattedAddressAttribute(): string
    {
        $parts = [
            $this->building_name,
            $this->flat_number,
            $this->wing,
            $this->street,
            $this->locality,
            $this->landmark,
            $this->city,
            $this->district,
            $this->state,
            $this->pincode,
        ];
        $filtered = array_filter(array_map('trim', array_map('strval', $parts)), fn($v) => strlen($v) > 0);
        return !empty($filtered) ? implode(', ', $filtered) : ($this->address ?: '');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}