<?php

namespace App\Models;

use App\Enums\ReviewStatusEnum;
use App\Enums\ReviewTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_id',
        'garment_id',
        'type',
        'rating',
        'title',
        'comment',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReviewTypeEnum::class,
            'status' => ReviewStatusEnum::class,
            'rating' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function garment()
    {
        return $this->belongsTo(Garment::class);
    }

    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }

    public function reports()
    {
        return $this->hasMany(ReviewReport::class);
    }
}
