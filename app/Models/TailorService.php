<?php

namespace App\Models;
use App\Models\TailorProfile;

use Illuminate\Database\Eloquent\Model;

class TailorService extends Model
{
 
 protected $fillable = [
        'tailor_id',
        'service_name',
        'base_price',
    ];
//

public function tailor()
{
    return $this->belongsTo(TailorProfile::class,'tailor_id');
}
}
