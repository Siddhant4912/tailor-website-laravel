<?php

namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'appointment_id' => $this->appointment_id,
            'status' => $this->status,
            'customer' => $this->whenLoaded('customer', fn() => new UserResource($this->customer)),
            'pickup_staff' => $this->whenLoaded('pickupStaff', fn() => new UserResource($this->pickupStaff)),
                        'delivery_staff_id' => $this->delivery_staff_id,

            'delivery_staff' => $this->whenLoaded('deliveryStaff', fn() => new UserResource($this->deliveryStaff)),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'measurements' => OrderMeasurementResource::collection($this->whenLoaded('measurements')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'status_logs' => StatusLogResource::collection($this->whenLoaded('statusLogs')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'delivery_address' => $this->delivery_address,
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'visit_charge' => $this->visit_charge,
            'gst_rate' => $this->gst_rate,
            'gst_amount' => $this->gst_amount,
            'total_price' => $this->total_price,
            'advance_paid' => $this->advance_paid,
            'balance_due' => ($this->status === \App\Enums\OrderStatusEnum::DELIVERED || $this->status === \App\Enums\OrderStatusEnum::CANCELLED || ($this->relationLoaded('invoices') && $this->invoices->contains('status', \App\Enums\InvoiceStatusEnum::PAID)))
                ? 0
                : max(0, (float) $this->total_price - (float) $this->advance_paid),
            'pickup_at' => $this->pickup_at,
            'delivered_at' => $this->delivered_at,
            'delivery_date' => $this->delivery_date,
            'delivery_proof' => $this->delivery_proof,
            'delivery_proof_url' => $this->delivery_proof ? asset('storage/' . $this->delivery_proof) : null,
            'delivery_otp' => $this->delivery_otp,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
           
        ];
    }
}