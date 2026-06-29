<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Review;
use App\Enums\OrderStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(protected ReviewAnalyticsService $analyticsService) {}

    public function createReview(int $userId, array $data): Review
    {
        return DB::transaction(function () use ($userId, $data) {
            $order = Order::with('items')->where('id', $data['order_id'])->firstOrFail();

            if ($order->customer_id !== $userId) {
                throw ValidationException::withMessages(['order_id' => 'You cannot review an order you do not own.']);
            }

            if ($order->status !== OrderStatusEnum::DELIVERED) {
                throw ValidationException::withMessages(['order_id' => 'You can only review completed/delivered orders.']);
            }

            if ($data['type'] === 'garment') {
                if (empty($data['garment_id'])) {
                    throw ValidationException::withMessages(['garment_id' => 'Garment ID is required for garment reviews.']);
                }

                $orderedGarmentIds = $order->items->pluck('garment_id')->toArray();
                if (!in_array($data['garment_id'], $orderedGarmentIds)) {
                    throw ValidationException::withMessages(['garment_id' => 'You did not order this garment in the specified order.']);
                }
            } else {
                $data['garment_id'] = null; // Ensure garment_id is null for service reviews
            }

            // The database unique constraint will throw an exception if duplicate,
            // but we can catch it early for better UX.
            $exists = Review::where('user_id', $userId)
                ->where('order_id', $data['order_id'])
                ->where('type', $data['type'])
                ->where('garment_id', $data['garment_id'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['general' => 'You have already submitted a review for this.']);
            }

            $review = Review::create([
                'user_id' => $userId,
                'order_id' => $data['order_id'],
                'garment_id' => $data['garment_id'],
                'type' => $data['type'],
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'] ?? null,
                'status' => 'approved', // Auto-approve based on plan
            ]);

            // Handle Image Uploads
            if (!empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    $path = $image->store('reviews', 'public');
                    $review->images()->create(['file_path' => $path]);
                }
            }

            // Update Analytics
            if ($review->type->value === 'garment') {
                $this->analyticsService->updateGarmentStats($review->garment_id);
            }

            return $review->load('images');
        });
    }
}
