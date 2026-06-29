<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'status' => $this->status,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->whenLoaded('user', fn() => $this->user->name),
            ],
            'order_id' => $this->order_id,
            'garment_id' => $this->garment_id,
            'garment' => $this->whenLoaded('garment', fn() => [
                'id' => $this->garment?->id,
                'name' => $this->garment?->name,
            ]),
            'images' => $this->whenLoaded('images', fn() => $this->images->pluck('file_path')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
