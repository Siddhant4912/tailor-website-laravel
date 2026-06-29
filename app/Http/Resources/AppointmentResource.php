<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'gender' => $this->gender,                                   // NEW
            'female_tailor_visit' => $this->female_tailor_visit,         // NEW
            'has_fabric' => $this->has_fabric,                           // NEW
            'measurement_type' => $this->measurement_type,               // NEW
            'date' => $this->appointment_date?->format('Y-m-d'),
            'time' => $this->appointment_time,
            'status' => $this->status?->value,
            'address' => [
                'line' => $this->address_line,
                'city' => $this->city,
                'state' => $this->state,
                'pincode' => $this->pincode,
            ],
            'financials' => [
                'visit_charge' => $this->visit_charge,
                'payment_status' => ($this->relationLoaded('invoices') && $this->invoices->contains('status', \App\Enums\InvoiceStatusEnum::PAID))
                    ? 'paid'
                    : $this->payment_status,
                'deposit_amount' => $this->deposit_amount,               // NEW
                'cloth_advance_amount' => $this->cloth_advance_amount,   // NEW
                'cloth_total_amount' => $this->cloth_total_amount,       // NEW
            ],
            // Compatibility fields for backward compatibility with frontend clients expecting root level database fields
            'appointment_date' => $this->appointment_date?->format('Y-m-d'),
            'appointment_time' => $this->appointment_time,
            'address_line' => $this->address_line,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'payment_status' => ($this->relationLoaded('invoices') && $this->invoices->contains('status', \App\Enums\InvoiceStatusEnum::PAID))
                ? 'paid'
                : $this->payment_status,
            'visit_charge' => $this->visit_charge,
            'deposit_amount' => $this->deposit_amount,
            'cloth_advance_amount' => $this->cloth_advance_amount,
            'cloth_total_amount' => $this->cloth_total_amount,
            'visit_tracking' => [
                'is_visited' => $this->is_visited,
                'measurement_taken' => $this->measurement_taken,
                'started_at' => $this->started_at?->toDateTimeString(),
                'ended_at' => $this->ended_at?->toDateTimeString(),
            ],
            'notes' => $this->notes,
            'measurements' => json_decode($this->notes, true) ?? [],

            // FIX: wrap in new UserResource() only when the relation is actually loaded,
            // otherwise whenLoaded() returns MissingValue which crashes UserResource::toArray()
            'customer' => $this->whenLoaded(
                'customer',
                fn() => new UserResource($this->customer)
            ),
            'assigned_staff' => $this->whenLoaded(
                'assignedStaff',
                fn() => new UserResource($this->assignedStaff)
            ),

            'items' => AppointmentItemResource::collection($this->whenLoaded('items')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'uploads' => $this->whenLoaded('uploads'),

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
