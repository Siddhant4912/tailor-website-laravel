<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Garment extends Model
{
    protected $fillable = [
        'category_id',
        'design_id',
        'name',
        'description',
        'images',
        'price',
        'secondary_price',
        'stitching_time_days',
        'is_active',
    ];

    protected $appends = [
        'image',
        'stitching_time',
        'base_price',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'secondary_price' => 'decimal:2',
            'stitching_time_days' => 'integer',
        ];
    }

    public function getBasePriceAttribute()
    {
        return $this->price;
    }

    /**
     * Decode JSON paths and convert each to fully-qualified asset URLs
     */
    public function getImagesAttribute($value)
    {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_map(function ($img) {
            if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
                return $img;
            }
            return asset('storage/' . $img);
        }, $decoded);
    }

    /**
     * Save raw array or string directly as JSON encoded
     */
    public function setImagesAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['images'] = json_encode($value);
        } else {
            $this->attributes['images'] = $value;
        }
    }

    /**
     * Fallback singular image accessor returning the first image URL in the list
     */
    public function getImageAttribute()
    {
        $imgs = $this->images;
        return is_array($imgs) && count($imgs) > 0 ? $imgs[0] : null;
    }

    /**
     * Retrieve the clean, relative database storage paths for physical file operations
     */
    public function rawImages(): array
    {
        $raw = $this->getRawOriginal('images');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Backward-compatible accessor for stitching_time returning stitching_time_days
     */
    public function getStitchingTimeAttribute()
    {
        return $this->stitching_time_days;
    }

    /**
     * Backward-compatible mutator for stitching_time mapping to stitching_time_days
     */
    public function setStitchingTimeAttribute($value)
    {
        $this->attributes['stitching_time_days'] = $value;
    }

    public function category()
    {
        return $this->belongsTo(ClothCategory::class, 'category_id');
    }

    public function design()
    {
        return $this->belongsTo(Design::class, 'design_id');
    }

    public function measurements()
    {
        return $this->hasMany(GarmentMeasurement::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function ratingStat()
    {
        return $this->hasOne(GarmentRatingStat::class);
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