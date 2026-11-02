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
        'old_cloth_photo',
        'new_cloth_photo',
        'clothes',
    ];

    protected $appends = [
        'old_cloth_photo_url',
        'new_cloth_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'clothes' => 'array',
        ];
    }

    public function getOldClothPhotoUrlAttribute(): ?string
    {
        if (!$this->old_cloth_photo) return null;
        if (str_starts_with($this->old_cloth_photo, 'http://') || str_starts_with($this->old_cloth_photo, 'https://')) {
            return $this->old_cloth_photo;
        }
        return asset('storage/' . $this->old_cloth_photo);
    }

    public function getNewClothPhotoUrlAttribute(): ?string
    {
        if (!$this->new_cloth_photo) return null;
        if (str_starts_with($this->new_cloth_photo, 'http://') || str_starts_with($this->new_cloth_photo, 'https://')) {
            return $this->new_cloth_photo;
        }
        return asset('storage/' . $this->new_cloth_photo);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function garment()
    {
        return $this->belongsTo(Garment::class)->withTrashed();
    }

    public function tailor()
    {
        return $this->belongsTo(User::class, 'assigned_tailor_id');
    }

    public function measurements()
    {
        return $this->hasMany(OrderMeasurement::class, 'order_item_id');
    }
}