<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentItem extends Model
{
    protected $fillable = [
        'appointment_id',
        'garment_id',
        'quantity',
        'price',
        'price_type',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function garment()
    {
        return $this->belongsTo(Garment::class);
    }
}