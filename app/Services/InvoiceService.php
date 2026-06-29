<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Appointment;
use App\Enums\InvoiceStatusEnum;
use App\Enums\TransactionStatusEnum;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateForOrder(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            // Check if invoice already exists
            if ($order->invoices()->exists()) {
                throw new \Exception('Invoice already generated for this order.');
            }

            $isOrderDelivered = $order->status === \App\Enums\OrderStatusEnum::DELIVERED;

            $invoice = $order->invoices()->create([
                'customer_id' => $order->customer_id,
                'invoice_number' => 'INV-' . strtoupper(uniqid()),
                'subtotal' => $order->subtotal,
                'visit_charge' => $order->visit_charge,
                'advance_paid' => $order->advance_paid ?? 0,
                'gst_rate' => $order->gst_rate,
                'gst_amount' => $order->gst_amount,
                'total_amount' => $order->total_price,
                'status' => $isOrderDelivered ? InvoiceStatusEnum::PAID : InvoiceStatusEnum::GENERATED,
                'generated_at' => now(),
                'paid_at' => $isOrderDelivered ? now() : null,
            ]);

            // Auto-initialize a pending transaction record
            $invoice->transactions()->create([
                'transaction_number' => 'TXN-' . strtoupper(uniqid()),
                'payment_mode' => 'cash',
                'amount' => max(0, $invoice->total_amount - $invoice->advance_paid),
                'status' => $isOrderDelivered ? TransactionStatusEnum::SUCCESSFUL : TransactionStatusEnum::PENDING,
            ]);

            return $invoice;
        });
    }

    public function generateForAppointment(Appointment $appointment): Invoice
    {
        return DB::transaction(function () use ($appointment) {
            // Lock to prevent duplicates
            $appointment = Appointment::where('id', $appointment->id)->lockForUpdate()->firstOrFail();

            if ($appointment->invoices()->exists()) {
                throw new \Exception('Invoice already generated for this appointment.');
            }

            $subtotal = 0;
            foreach ($appointment->items as $item) {
                $itemPrice = $item->price ?? ($item->garment?->price ?? 0);
                $subtotal += $itemPrice * ($item->quantity ?? 1);
            }

            $gstRate = 0;
            $gstAmount = 0;
            $baseSubtotal = $subtotal;
            if ($subtotal > 0) {
                try {
                    $gstRate = app(\App\Services\GstService::class)->getActiveRate();
                    $isInclusive = app(\App\Services\GstService::class)->isActiveInclusive();
                    if ($isInclusive && $gstRate > 0) {
                        $gstAmount = $subtotal - ($subtotal / (1 + ($gstRate / 100)));
                        $baseSubtotal = $subtotal - $gstAmount;
                    } else {
                        $gstAmount = $subtotal * ($gstRate / 100);
                        $baseSubtotal = $subtotal;
                    }
                } catch (\Throwable $e) {
                    \Log::error('GST rate fetch failed for appointment invoice: ' . $e->getMessage());
                }
            }

            $totalAmount = $baseSubtotal + $gstAmount + $appointment->visit_charge;

            $requiredDeposit = ($appointment->deposit_amount ?? 0) + ($appointment->cloth_advance_amount ?? 0);

            $invoice = $appointment->invoices()->create([
                'customer_id' => $appointment->customer_id,
                'invoice_number' => 'INV-APP-' . strtoupper(uniqid()),
                'subtotal' => $baseSubtotal,
                'visit_charge' => $appointment->visit_charge,
                'advance_paid' => 0, // Initially 0, updated when payment succeeds
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'status' => InvoiceStatusEnum::GENERATED,
                'generated_at' => now(),
            ]);

            if ($requiredDeposit > 0) {
                // Auto-initialize a pending transaction record
                $invoice->transactions()->create([
                    'transaction_number' => 'TXN-' . strtoupper(uniqid()),
                    'payment_mode' => 'cash',
                    'amount' => $requiredDeposit,
                    'status' => TransactionStatusEnum::PENDING,
                ]);
            }

            return $invoice;
        });
    }
}