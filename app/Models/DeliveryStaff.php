<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class DeliveryStaff extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'delivery_staff';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'gender',
        'profile_photo',
        'date_of_birth',
        'emergency_contact',
        'aadhaar_number',
        'address',
        'password',
        'status',
        'is_available',
        'latitude',
        'longitude',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_available'  => 'boolean',
        'date_of_birth' => 'date',
        'latitude'      => 'decimal:7',
        'longitude'     => 'decimal:7',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'assigned_staff_id');
    }
}