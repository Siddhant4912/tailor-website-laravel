<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->relationLoaded('order') ? $this->order : $this->order()->first();
        $hasOrder = !is_null($order);

        // Calculate items subtotal
        $itemsSubtotal = 0.0;
        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $itemPrice = (float) ($item->price > 0 ? $item->price : ($item->garment?->price ?? 0));
                $itemQty = (int) ($item->quantity ?: 1);
                $itemsSubtotal += ($itemPrice * $itemQty);
            }
        } elseif ($this->items()->exists()) {
            foreach ($this->items()->get() as $item) {
                $itemPrice = (float) ($item->price > 0 ? $item->price : ($item->garment?->price ?? 0));
                $itemQty = (int) ($item->quantity ?: 1);
                $itemsSubtotal += ($itemPrice * $itemQty);
            }
        }

        $isPaid = false;
        if ($this->relationLoaded('invoices')) {
            $isPaid = $this->invoices->contains('status', \App\Enums\InvoiceStatusEnum::PAID)
                || $this->invoices->flatMap->transactions->contains('status', \App\Enums\TransactionStatusEnum::SUCCESSFUL);
        } else {
            $isPaid = $this->invoices()->where('status', \App\Enums\InvoiceStatusEnum::PAID)->exists()
                || $this->invoices()->whereHas('transactions', function ($q) {
                    $q->where('status', \App\Enums\TransactionStatusEnum::SUCCESSFUL);
                })->exists();
        }

        if (!$isPaid && $this->payment_status === 'paid') {
            $isPaid = true;
        }

        $depositPaid = $isPaid ? (float) ($this->deposit_amount ?: 0) : 0.0;
        
        if ($hasOrder) {
            $subtotal = (float) $order->subtotal;
            $gstAmount = (float) $order->gst_amount;
            $totalAmount = (float) $order->total_price;
            $advancePaid = (float) $order->advance_paid;
            $balanceDue = max(0.0, $totalAmount - $advancePaid);
        } else {
            $subtotal = $itemsSubtotal;
            $gstAmount = 0.0;
            $totalAmount = $subtotal;
            $advancePaid = $depositPaid;
            $balanceDue = max(0.0, $totalAmount - $advancePaid);
        }

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
            'has_order' => $hasOrder,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'address' => [
                'building_name' => $this->building_name,
                'flat_number' => $this->flat_number,
                'wing' => $this->wing,
                'street' => $this->street,
                'locality' => $this->locality,
                'landmark' => $this->landmark,
                'line' => $this->formatted_address ?: $this->address_line,
                'city' => $this->city,
                'district' => $this->district,
                'state' => $this->state,
                'pincode' => $this->pincode,
                'formatted_address' => $this->formatted_address,
            ],
            'financials' => [
                'visit_charge' => $this->visit_charge,
                'subtotal' => $subtotal,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'advance_paid' => $advancePaid,
                'balance_due' => $balanceDue,
                'payment_status' => $isPaid ? 'paid' : ($this->payment_status ?: 'pending'),
                'deposit_amount' => $isPaid ? (float)$this->deposit_amount : 0.0,               // NEW
                'cloth_advance_amount' => $isPaid ? (float)$this->cloth_advance_amount : 0.0,   // NEW
                'cloth_total_amount' => (float)$this->cloth_total_amount,                       // NEW
            ],
            // Compatibility fields for backward compatibility with frontend clients expecting root level database fields
            'subtotal' => $subtotal,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'advance_paid' => $advancePaid,
            'balance_due' => $balanceDue,
            'appointment_date' => $this->appointment_date?->format('Y-m-d'),
            'appointment_time' => $this->appointment_time,
            'building_name' => $this->building_name,
            'flat_number' => $this->flat_number,
            'wing' => $this->wing,
            'street' => $this->street,
            'locality' => $this->locality,
            'landmark' => $this->landmark,
            'address_line' => $this->formatted_address ?: $this->address_line,
            'city' => $this->city,
            'district' => $this->district,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'formatted_address' => $this->formatted_address,
            'payment_status' => $isPaid ? 'paid' : ($this->payment_status ?: 'pending'),
            'visit_charge' => $this->visit_charge,
            'deposit_amount' => $isPaid ? (float)$this->deposit_amount : 0.0,
            'cloth_advance_amount' => $isPaid ? (float)$this->cloth_advance_amount : 0.0,
            'cloth_total_amount' => (float)$this->cloth_total_amount,
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
