<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'subtotal' => $this->subtotal,
            'visit_charge' => $this->visit_charge,
            'gst_rate' => $this->gst_rate,
            'gst_amount' => $this->gst_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'generated_at' => $this->generated_at?->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'advance_paid' => $this->advance_paid,
            'amount' => $this->total_amount - $this->advance_paid,
            'transactions' => $this->whenLoaded('transactions'),
        ];
    }
}
