<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Design;

use Illuminate\Database\Eloquent\Model;

class ClothCategory extends Model
{
        use HasFactory;
        protected $fillable = [
        'name',
        'gender',
        'description',
        'is_active',
    ];

     // --- Relationships ---
    public function designs()
    {
        return $this->hasMany(Design::class, 'category_id');
    }


     // --- Scopes ---
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            \Illuminate\Support\Facades\Cache::forget('catalog_system_index');
        });

        static::deleted(function ($model) {
            \Illuminate\Support\Facades\Cache::forget('catalog_system_index');
        });
    }
}
