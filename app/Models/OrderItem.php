<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'garment_id',
        'garment_name',
        'price',
        'quantity',
        'size',
        'assigned_tailor_id',
        'status',
        'provided_own_fabric',
        'custom_notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function garment()
    {
        return $this->belongsTo(Garment::class);
    }

    public function tailor()
    {
        return $this->belongsTo(User::class, 'assigned_tailor_id');
    }

    
}