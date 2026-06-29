<?php

namespace App\Services;

use App\Models\GarmentRatingStat;
use App\Models\Review;
use App\Enums\ReviewTypeEnum;
use App\Enums\ReviewStatusEnum;
use Illuminate\Support\Facades\DB;

class ReviewAnalyticsService
{
    /**
     * Recalculates the garment rating stats when a review is added/updated/deleted.
     */
    public function updateGarmentStats(int $garmentId): void
    {
        $stats = Review::where('garment_id', $garmentId)
            ->where('type', ReviewTypeEnum::GARMENT->value)
            ->where('status', ReviewStatusEnum::APPROVED->value)
            ->select(
                DB::raw('COUNT(*) as total_reviews'),
                DB::raw('AVG(rating) as average_rating'),
                DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1_count'),
                DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2_count'),
                DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3_count'),
                DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4_count'),
                DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5_count')
            )
            ->first();

        GarmentRatingStat::updateOrCreate(
            ['garment_id' => $garmentId],
            [
                'total_reviews' => $stats->total_reviews ?? 0,
                'average_rating' => $stats->average_rating ?? 0.00,
                'rating_1_count' => $stats->rating_1_count ?? 0,
                'rating_2_count' => $stats->rating_2_count ?? 0,
                'rating_3_count' => $stats->rating_3_count ?? 0,
                'rating_4_count' => $stats->rating_4_count ?? 0,
                'rating_5_count' => $stats->rating_5_count ?? 0,
            ]
        );
    }
}
