<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GstSetting extends Model
{
    //
    protected $fillable = [
        'rate',
        'from_date',
        'to_date',
        'is_active',
        'is_inclusive',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_inclusive' => 'boolean',
    ];
}
