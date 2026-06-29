<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TailorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'shop_name',
        'address',
        'rating',
        'experience_years',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'rating' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
