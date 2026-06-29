<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMeasurement extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'measurement_field_id',
        'garment_id',
        'field_name',
        'value',
        'note',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}