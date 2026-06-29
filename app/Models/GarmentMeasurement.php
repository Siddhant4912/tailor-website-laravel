<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Garment;

class GarmentMeasurement extends Model
{
    protected $fillable = [
        'garment_id',
        'field_name',
        'unit',
        'is_required'
    ];

    public function garment()
    {
        return $this->belongsTo(Garment::class);
    }
}