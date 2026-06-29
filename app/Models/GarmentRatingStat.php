<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GarmentRatingStat extends Model
{
    // Define the primary key explicitly if we ever query by it, but it's linked via garment_id.
    
    protected $fillable = [
        'garment_id',
        'average_rating',
        'total_reviews',
        'rating_1_count',
        'rating_2_count',
        'rating_3_count',
        'rating_4_count',
        'rating_5_count',
    ];

    protected function casts(): array
    {
        return [
            'average_rating' => 'decimal:2',
            'total_reviews' => 'integer',
            'rating_1_count' => 'integer',
            'rating_2_count' => 'integer',
            'rating_3_count' => 'integer',
            'rating_4_count' => 'integer',
            'rating_5_count' => 'integer',
        ];
    }

    public function garment()
    {
        return $this->belongsTo(Garment::class);
    }
}
