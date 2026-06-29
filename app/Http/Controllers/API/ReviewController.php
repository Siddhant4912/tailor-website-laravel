<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $service) {}

    public function index(Request $request)
    {
        $query = Review::with(['user', 'images', 'garment']);

        if ($request->has('garment_id')) {
            $query->where('garment_id', $request->garment_id)->where('type', 'garment');
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Default to approved reviews only unless user is admin
        if (!$request->user() || !$request->user()->isAdmin()) {
            $query->where('status', 'approved');
        }

        return $this->successResponse(ReviewResource::collection($query->latest()->paginate(20)), 'Reviews fetched');
    }

    public function store(StoreReviewRequest $request)
    {
        $this->authorize('create', Review::class);

        $review = $this->service->createReview($request->user()->id, $request->validated());

        return $this->successResponse(new ReviewResource($review), 'Review submitted successfully.', 201);
    }

    public function update(Request $request, Review $review)
    {
        $this->authorize('update', $review);

        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($validated);

        // Update analytics if garment review
        if ($review->type->value === 'garment') {
            app(\App\Services\ReviewAnalyticsService::class)->updateGarmentStats($review->garment_id);
        }

        return $this->successResponse(new ReviewResource($review), 'Review updated successfully.');
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        // Update analytics if garment review
        if ($review->type->value === 'garment') {
            app(\App\Services\ReviewAnalyticsService::class)->updateGarmentStats($review->garment_id);
        }

        return $this->successResponse(null, 'Review deleted successfully.');
    }
}
