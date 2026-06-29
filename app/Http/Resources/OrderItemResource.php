<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,

            'garment_id' => $this->garment_id,
            'garment_name' => $this->garment_name,

            'price' => $this->price,
            'quantity' => $this->quantity,
            'size' => $this->size,

            'status' => $this->status,

            // IMPORTANT
            'assigned_tailor_id' => $this->assigned_tailor_id,

            // IMPORTANT
            'tailor' => $this->whenLoaded('tailor', function () {
                return [
                    'id' => $this->tailor?->id,
                    'name' => $this->tailor?->name,
                ];
            }),

            'order' => $this->whenLoaded('order', function() {
                return [
                    'id' => $this->order?->id,
                    'order_number' => $this->order?->order_number,
                    'status' => $this->order?->status,
                    'delivery_date' => $this->order?->delivery_date?->toDateString(),
                    'customer' => [
                        'name' => $this->order?->customer?->name,
                        'phone' => $this->order?->customer?->phone,
                    ]
                ];
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}