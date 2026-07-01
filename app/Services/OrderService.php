<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMeasurement;
use App\Models\Appointment;
use App\Enums\OrderStatusEnum;
use App\Enums\AppointmentStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected GstService $gstService
    ) {
    }

    public function createFromAppointment(int $appointmentId, array $data): Order
    {
        return DB::transaction(function () use ($appointmentId, $data) {

            // FIX #2: eager load items.garment to avoid N+1
            $appointment = Appointment::with('items.garment.design', 'items.garment.category')
                ->where('id', $appointmentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($appointment->order()->exists()) {
                throw new \Exception('An order already exists for this appointment.');
            }

            // FIX #1: build full address from split columns when not explicitly provided
            $deliveryAddress = $data['delivery_address']
                ?? collect([
                    $appointment->address_line,
                    $appointment->city,
                    $appointment->state,
                    $appointment->pincode,
                ])->filter()->join(', ');

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'appointment_id' => $appointment->id,
                'customer_id' => $appointment->customer_id,
                'visit_charge' => $appointment->visit_charge,
                'delivery_address' => $deliveryAddress,
                'notes' => $data['notes'] ?? null,
                'status' => OrderStatusEnum::PENDING,
            ]);

            $subtotal = 0;

            if (!empty($data['items'])) {
                // Fetch all garments in one query to avoid N+1
                $garmentIds = collect($data['items'])->pluck('garment_id')->toArray();
                $garments = \App\Models\Garment::whereIn('id', $garmentIds)->get()->keyBy('id');

                foreach ($data['items'] as $item) {
                    $garment = $garments->get($item['garment_id']);
                    if (!$garment) continue;

                    $price = (float) ($item['price'] ?? $garment->price ?? 0);
                    $quantity = $item['quantity'] ?? 1;

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'garment_id' => $garment->id,
                        'garment_name' => $garment->name ?? 'Garment',
                        'price' => $price,
                        'quantity' => $quantity,
                    ]);

                    $subtotal += ($price * $quantity);

                    // Save measurements specific to this garment
                    foreach ($item['measurements'] ?? [] as $m) {
                        if (empty($m['field_name'])) continue;
                        OrderMeasurement::create([
                            'order_id' => $order->id,
                            'order_item_id' => $orderItem->id, // Add order_item_id just in case
                            'garment_id' => $garment->id, // Important for Staff Mesaurement
                            'field_name' => $m['field_name'],
                            'value' => $m['value'],
                        ]);
                    }
                }
            }

            $advancePaid = (float) $appointment->deposit_amount + (float) $appointment->cloth_advance_amount + (float) $appointment->visit_charge;

            $gstRate = $this->gstService->getActiveRate();
            $isInclusive = $this->gstService->isActiveInclusive();
            if ($isInclusive && $gstRate > 0) {
                $gstAmount = $subtotal - ($subtotal / (1 + ($gstRate / 100)));
                $baseSubtotal = $subtotal - $gstAmount;
                $grossTotal = $subtotal + $order->visit_charge;
            } else {
                $gstAmount = $subtotal * ($gstRate / 100);
                $baseSubtotal = $subtotal;
                $grossTotal = $subtotal + $gstAmount + $order->visit_charge;
            }

            $order->update([
                'subtotal' => $baseSubtotal,
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'advance_paid' => $advancePaid,
                'total_price' => $grossTotal,
            ]);

            $appointment->update(['status' => AppointmentStatusEnum::COMPLETED]);

            // Auto-generate invoice
            $this->invoiceService->generateForOrder($order);

            // Dispatch notification
            try {
                $order->customer->notify(new \App\Notifications\OrderStatusNotification($order, 'pending'));
            } catch (\Throwable $e) {
                Log::error('Order placement notification failed: ' . $e->getMessage());
            }

            return $order->load(['items.garment', 'items.tailor', 'measurements', 'customer', 'invoices.transactions']);
        });
    }

    public function updateStatus(int $orderId, OrderStatusEnum $status, int $userId = null, string $remarks = null, string $otp = null, string $paymentMode = null): Order
    {
        return DB::transaction(function () use ($orderId, $status, $userId, $remarks, $otp, $paymentMode) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();

            // Validate OTP if transitioning to DELIVERED status and an OTP is set in the DB
            if ($status === OrderStatusEnum::DELIVERED && $order->delivery_otp !== null) {
                if (trim($otp) !== trim($order->delivery_otp)) {
                    throw new \Exception('Invalid Delivery OTP. Please enter the correct code provided by the customer.');
                }
            }

            $updateData = ['status' => $status];
            if ($status === OrderStatusEnum::DELIVERED) {
                $updateData['delivered_at'] = now();
                $updateData['delivery_otp'] = null; // Clear OTP upon successful delivery

                // Mark associated invoices as paid and transactions as successful
                $invoice = $order->invoices()->first();
                if ($invoice) {
                    $invoice->update([
                        'status' => \App\Enums\InvoiceStatusEnum::PAID,
                        'paid_at' => now(),
                    ]);

                    $invoice->transactions()
                        ->where('status', '!=', \App\Enums\TransactionStatusEnum::SUCCESSFUL->value)
                        ->update([
                            'status' => \App\Enums\TransactionStatusEnum::SUCCESSFUL,
                            'payment_mode' => $paymentMode ?: 'cash',
                        ]);
                }
            }

            // Generate secure 4-digit OTP when order goes out for delivery
            if ($status === OrderStatusEnum::OUT_FOR_DELIVERY) {
                $deliveryOtp = strval(rand(1000, 9999));
                $updateData['delivery_otp'] = $deliveryOtp;

                if (!empty($order->customer->phone)) {
                    try {
                        $smsService = app(\App\Services\SmsService::class);
                        $smsService->sendDeliveryOtp($order->customer->phone, $deliveryOtp);
                    } catch (\Exception $smsEx) {
                        \Illuminate\Support\Facades\Log::error('Failed to send delivery OTP SMS: ' . $smsEx->getMessage());
                    }
                }
            }

            $order->update($updateData);

            $order->statusLogs()->create([
                'user_id' => $userId,
                'status' => $status->value,
                'remarks' => $remarks ?: 'Status updated to ' . $status->value,
            ]);

            // Dispatch notification
            try {
                $order->customer->notify(new \App\Notifications\OrderStatusNotification($order, $status->value));
            } catch (\Throwable $e) {
                Log::error('Order status change notification failed: ' . $e->getMessage());
            }

            // FIX #3: load all relations so OrderResource doesn't get empty data
            return $order->load(['items.garment', 'items.tailor', 'measurements', 'customer', 'invoices', 'statusLogs']);
        });
    }

    public function createDirectOrder(array $data, int $customerId): Order
    {
        return DB::transaction(function () use ($data, $customerId) {
            $deliveryAddress = $data['delivery_address'] ?? 'No Address Provided';

            $status = $data['status'] ?? (
                (isset($data['payment_method']) && $data['payment_method'] === 'online') 
                    ? OrderStatusEnum::Draft 
                    : OrderStatusEnum::PENDING
            );

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'customer_id' => $customerId,
                'visit_charge' => 0, // No visit charge for direct order
                'delivery_address' => $deliveryAddress,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
            ]);

            $subtotal = 0;

            if (!empty($data['items'])) {
                // Fetch all garments in one query to avoid N+1
                $garmentIds = collect($data['items'])->pluck('garment_id')->toArray();
                $garments = \App\Models\Garment::whereIn('id', $garmentIds)->get()->keyBy('id');

                foreach ($data['items'] as $item) {
                    $garment = $garments->get($item['garment_id']);
                    if (!$garment) continue;

                    $price = (float) ($item['price'] ?? $garment->price ?? 0);
                    $quantity = $item['quantity'] ?? 1;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'garment_id' => $garment->id,
                        'garment_name' => $garment->name ?? 'Garment',
                        'price' => $price,
                        'quantity' => $quantity,
                        'size' => $item['size'] ?? null,
                        'custom_notes' => $item['custom_notes'] ?? null,
                    ]);

                    $subtotal += ($price * $quantity);
                }
            }

            foreach ($data['measurements'] ?? [] as $m) {
                OrderMeasurement::create([
                    'order_id' => $order->id,
                    'field_name' => $m['field_name'],
                    'value' => $m['value'],
                ]);
            }

            $gstRate = $this->gstService->getActiveRate();
            $isInclusive = $this->gstService->isActiveInclusive();
            if ($isInclusive && $gstRate > 0) {
                $gstAmount = $subtotal - ($subtotal / (1 + ($gstRate / 100)));
                $baseSubtotal = $subtotal - $gstAmount;
                $total = $subtotal;
            } else {
                $gstAmount = $subtotal * ($gstRate / 100);
                $baseSubtotal = $subtotal;
                $total = $subtotal + $gstAmount;
            }

            $order->update([
                'subtotal' => $baseSubtotal,
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'total_price' => $total,
            ]);

            // Auto-generate invoice
            $this->invoiceService->generateForOrder($order);

            // Dispatch notification only if not draft
            $statusStr = is_object($order->status) ? $order->status->value : (string) $order->status;
            if ($statusStr !== 'draft') {
                try {
                    $order->customer->notify(new \App\Notifications\OrderStatusNotification($order, 'pending'));
                } catch (\Throwable $e) {
                    Log::error('Order placement notification failed: ' . $e->getMessage());
                }
            }

            return $order->load(['items.garment', 'items.tailor', 'measurements', 'customer', 'invoices.transactions']);
        });
    }
}