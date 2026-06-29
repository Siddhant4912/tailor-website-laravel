<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'address_line',
        'city',
        'state',
        'pincode',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function role(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value instanceof RoleEnum) {
                    return $value;
                }

                $map = [
                    'admin' => RoleEnum::ADMIN,
                    'ADM' => RoleEnum::ADMIN,
                    'tailor' => RoleEnum::TAILOR,
                    'TLR' => RoleEnum::TAILOR,
                    'customer' => RoleEnum::CUSTOMER,
                    'USR' => RoleEnum::CUSTOMER,
                    'delivery_staff' => RoleEnum::DELIVERY_STAFF,
                    'DLV' => RoleEnum::DELIVERY_STAFF,
                ];

                return $map[$value] ?? RoleEnum::CUSTOMER;
            },
            set: function ($value) {
                if ($value instanceof RoleEnum) {
                    return $value->value;
                }
                return $value;
            }
        );
    }

    public function userProfile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function tailorProfile()
    {
        return $this->hasOne(TailorProfile::class);
    }

    public function deliveryStaffProfile()
    {
        return $this->hasOne(DeliveryStaffProfile::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function assignedAppointments()
    {
        return $this->hasMany(Appointment::class, 'assigned_staff_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::ADMIN;
    }

    public function isTailor(): bool
    {
        return $this->role === RoleEnum::TAILOR;
    }

    public function isCustomer(): bool
    {
        return $this->role === RoleEnum::CUSTOMER;
    }

    public function isDeliveryStaff(): bool
    {
        return $this->role === RoleEnum::DELIVERY_STAFF;
    }
}