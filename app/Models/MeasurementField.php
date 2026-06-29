<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MeasurementField extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'unit',
        'is_active',
    ];
}
