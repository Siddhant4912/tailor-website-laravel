<?php


namespace App\Models;
use App\Models\ClothCategory;
use App\Models\Garment;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Design extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'image',
        'image_url',
        'description',
        'additional_price',
        'secondary_price',
        'is_active',
    ];

    protected $appends = [
        'image_url'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'additional_price' => 'decimal:2',
            'secondary_price' => 'decimal:2',
        ];
    }

    public function getImageUrlAttribute()
    {
        if (!empty($this->attributes['image_url'])) {
            return $this->attributes['image_url'];
        }
        if (!empty($this->image)) {
            return $this->image;
        }
        return null;
    }

    public function category()
    {
        return $this->belongsTo(ClothCategory::class, 'category_id');
    }

    public function garments()
    {
        return $this->hasMany(Garment::class, 'design_id');
    }
    // --- Scopes ---
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
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
