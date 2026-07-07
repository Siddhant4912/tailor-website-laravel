<?php
// siddhant pawar : 04-07-2026

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Garment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryStaffAppointmentController extends Controller
{

    // GET /staff/appointments/today
    public function today(Request $request)
    {
        try {
            $staffId = $request->user()->id;

            $appointments = Appointment::with([
                'customer.userProfile',
                'items.garment.design',
                'items.garment.category',
                'uploads',
                'invoices.transactions',
            ])
                ->where('assigned_staff_id', $staffId)
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get();

            return $this->successResponse(AppointmentResource::collection($appointments), 'Today\'s appointments');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /staff/appointments/{id}/start
    public function start(Request $request, $id)
    {
        try {
            $staffId = $request->user()->id;
            $appointment = Appointment::where('id', $id)
                ->where('assigned_staff_id', $staffId)
                ->firstOrFail();

            // FIX: Enum object asel tar tyala string madhye convert kara
            $status = is_object($appointment->status) ? $appointment->status->value : (string) $appointment->status;

            if ($status !== 'pending' && $status !== 'confirmed') {
                return $this->errorResponse('Cannot start this appointment', 422);
            }

            $appointment->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            $appointment->load([
                'customer.userProfile',
                'items.garment.design',
                'items.garment.category',
                'uploads',
                'invoices.transactions',
            ]);

            return $this->successResponse(new AppointmentResource($appointment), 'Visit started');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /staff/appointments/{id}/end
    public function end(Request $request, $id)
    {
        try {
            $staffId = $request->user()->id;
            $appointment = Appointment::where('id', $id)
                ->where('assigned_staff_id', $staffId)
                ->firstOrFail();

            // FIX: Enum object asel tar tyala string madhye convert kara
            $status = is_object($appointment->status) ? $appointment->status->value : (string) $appointment->status;

            if ($status !== 'in_progress') {
                return $this->errorResponse('Visit not started yet', 422);
            }

            $appointment->update([
                'status' => 'completed',
                'ended_at' => now(),
                'is_visited' => true,
                'measurement_taken' => $request->input('measurement_taken', true),
            ]);

            // auto generate invoice
            try {
                app(\App\Services\AppointmentInvoiceService::class)
                    ->generate($appointment->id);
            } catch (\Throwable $e) {
                \Log::error('Invoice generate failed: ' . $e->getMessage());
            }

            $appointment->load(['customer.userProfile', 'items.garment.design', 'items.garment.category', 'uploads', 'invoices.transactions']);
            return $this->successResponse(
                new AppointmentResource($appointment),
                'Visit completed'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /staff/appointments/{id}/measurements
    public function saveMeasurements(Request $request, $id)
    {
        try {
            $staffId = $request->user()->id;
            $appointment = Appointment::where('id', $id)
                ->where('assigned_staff_id', $staffId)
                ->firstOrFail();

            $data = $request->validate([
                'measurements' => 'required|array',
                'measurements.*.label' => 'required|string',
                'measurements.*.value' => 'required|string',
            ]);

            $appointment->update([
                'notes' => json_encode($data['measurements']),
                'measurement_taken' => true,
            ]);

            $appointment->load([
                'customer.userProfile',
                'items.garment.design',
                'items.garment.category',
                'uploads',
                'invoices.transactions',
            ]);

            return $this->successResponse(new AppointmentResource($appointment), 'Measurements saved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /staff/appointments/{id}/uploads
    public function uploadPhoto(Request $request, $id)
    {
        $request->validate(['image' => 'required|image|max:5120']);

        try {
            $staffId = $request->user()->id;
            $appointment = Appointment::where('id', $id)
                ->where('assigned_staff_id', $staffId)
                ->firstOrFail();

            $path = $request->file('image')
                ->store('appointments/' . $appointment->id, 'public');

            $upload = $appointment->uploads()->create(['file_path' => $path]);

            return $this->successResponse($upload, 'Photo uploaded', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // GET /staff/appointments/{id}/catalog
    public function catalog(Request $request, $id)
    {
        try {
            $staffId = $request->user()->id;
            $appointment = Appointment::where('id', $id)
                ->where('assigned_staff_id', $staffId)
                ->firstOrFail();

            $garmentIds = $appointment->items()->pluck('garment_id');

            if ($garmentIds->isEmpty()) {
                $garments = Garment::with(['measurements', 'design'])->get();
            } else {
                $garments = Garment::with(['measurements', 'design'])->whereIn('id', $garmentIds)->get();
            }

            return $this->successResponse($garments, 'Catalog fetched');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /staff/appointments/{id}/confirm-order
    public function confirmOrder(Request $request, $id)
    {
        try {
            $staffId = $request->user()->id;
            $appointment = Appointment::where('id', $id)
                ->where('assigned_staff_id', $staffId)
                ->firstOrFail();

            $statusStr = is_object($appointment->status) ? $appointment->status->value : (string) $appointment->status;
            if ($statusStr === 'completed') {
                return $this->errorResponse('This appointment has already been completed. You cannot confirm the order again.', 422);
            }

            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.garment_id' => 'required|exists:garments,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.custom_notes' => 'nullable|string',
                'items.*.provided_own_fabric' => 'nullable|boolean',
                'items.*.measurements' => 'nullable|array',
                'items.*.measurements.*.field_name' => 'required_with:items.*.measurements|string',
                'items.*.measurements.*.value' => 'required_with:items.*.measurements|string',
                'items.*.measurements.*.note' => 'nullable|string',
                'delivery_address' => 'required|string',
                'notes' => 'nullable|string',
                'advance_paid' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,online,upi,card',
            ]);

            $order = DB::transaction(function () use ($appointment, $data, $staffId) {

                $subtotal = collect($data['items'])
                    ->sum(fn($item) => $item['price'] * $item['quantity']);

                $gstService = app(\App\Services\GstService::class);
                $gstRate = $gstService->getActiveRate();
                $isInclusiveGst = $gstService->isActiveInclusive();

                if ($isInclusiveGst) {
                    $gstAmount = $subtotal - ($subtotal / (1 + ($gstRate / 100)));
                    $baseSubtotal = $subtotal - $gstAmount;
                } else {
                    $gstAmount = $subtotal * ($gstRate / 100);
                    $baseSubtotal = $subtotal;
                }

                $visitCharge = 0; // Waived on order confirmation

                // Calculate actual amount already paid online/invoiced on the appointment
                $alreadyPaidOnAppointment = 0;
                $lastVerifiedUpiTxnNumber = null;
                if ($appointment->invoices) {
                    foreach ($appointment->invoices as $apptInvoice) {
                        $successfulTxns = $apptInvoice->transactions()
                            ->where('status', \App\Enums\TransactionStatusEnum::SUCCESSFUL)
                            ->get();
                        foreach ($successfulTxns as $successfulTxn) {
                            $alreadyPaidOnAppointment += $successfulTxn->amount;
                            if ($successfulTxn->payment_mode === 'online') {
                                $lastVerifiedUpiTxnNumber = $successfulTxn->transaction_number;
                            }
                        }
                    }
                }

                $advancePaid = ($data['advance_paid'] ?? 0) + $alreadyPaidOnAppointment;
                
                if ($isInclusiveGst) {
                    $totalPrice = $subtotal + $visitCharge; // Subtotal already includes GST
                } else {
                    $totalPrice = $subtotal + $gstAmount + $visitCharge;
                }

                $order = Order::firstOrNew(['appointment_id' => $appointment->id]);
                if (!$order->exists) {
                    $order->order_number = 'ORD-' . strtoupper(uniqid());
                }

                $order->fill([
                    'customer_id' => $appointment->customer_id,
                    'status' => 'pending',
                    'subtotal' => $baseSubtotal,
                    'gst_rate' => $gstRate,
                    'gst_amount' => $gstAmount,
                    'visit_charge' => $visitCharge,
                    'advance_paid' => $advancePaid,
                    'total_price' => $totalPrice,
                    'delivery_address' => $data['delivery_address'],
                    'pickup_staff_id' => $staffId,
                    'notes' => $data['notes'] ?? null,
                ]);
                $order->save();

                // Clear old items and measurements if updating a draft
                OrderItem::where('order_id', $order->id)->delete();
                \App\Models\OrderMeasurement::where('order_id', $order->id)->delete();

                foreach ($data['items'] as $item) {
                    $garment = \App\Models\Garment::find($item['garment_id']);

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'garment_id' => $item['garment_id'],
                        'garment_name' => $garment ? $garment->name : 'Custom Garment',
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'custom_notes' => $item['custom_notes'] ?? null,
                        'provided_own_fabric' => $item['provided_own_fabric'] ?? false,
                    ]);

                    if (!empty($item['measurements'])) {
                        foreach ($item['measurements'] as $m) {
                            \App\Models\OrderMeasurement::create([
                                'order_id' => $order->id,
                                'order_item_id' => $orderItem->id,
                                'garment_id' => $item['garment_id'],
                                'field_name' => $m['field_name'] ?? '',
                                'value' => $m['value'] ?? '',
                                'note' => $m['note'] ?? null,
                            ]);
                        }
                    }
                }

                // Update appointment with advance/deposit
                $appointment->update([
                    'status' => 'completed',
                    'ended_at' => now(),
                    'is_visited' => true,
                    'measurement_taken' => true,
                    'deposit_amount' => $advancePaid,
                    'cloth_advance_amount' => 0,
                ]);

                // Waive the visit charge on the appointment invoice if one exists
                $apptInvoice = $appointment->invoices()->first();
                if ($apptInvoice) {
                    $apptInvoice->transactions()->where('status', \App\Enums\TransactionStatusEnum::PENDING)->delete();
                    $apptInvoice->update([
                        'subtotal' => 0,
                        'visit_charge' => 0,
                        'gst_amount' => 0,
                        'total_amount' => 0,
                        'advance_paid' => 0,
                        'status' => \App\Enums\InvoiceStatusEnum::PAID,
                        'paid_at' => now(),
                    ]);
                }

                // Auto generate order invoice
                try {
                    $invoice = $order->invoices()->first();
                    if (!$invoice) {
                        $invoice = app(\App\Services\InvoiceService::class)->generateForOrder($order);
                    } else {
                        $invoice->update([
                            'subtotal' => $baseSubtotal,
                            'visit_charge' => $visitCharge,
                            'advance_paid' => $advancePaid,
                            'gst_rate' => $gstRate,
                            'gst_amount' => $gstAmount,
                            'total_amount' => $totalPrice,
                        ]);
                    }

                    if ($invoice) {
                        // Clear old transactions to regenerate them correctly
                        $invoice->transactions()->delete();

                        // 1. Create a successful transaction for the advance payment
                        if ($advancePaid > 0) {
                            $invoice->transactions()->create([
                                'transaction_number' => $lastVerifiedUpiTxnNumber ?? ('TXN-' . strtoupper(uniqid())),
                                'payment_mode' => $lastVerifiedUpiTxnNumber ? 'online' : ($data['payment_method'] ?? 'cash'),
                                'amount' => $advancePaid,
                                'status' => \App\Enums\TransactionStatusEnum::SUCCESSFUL,
                            ]);
                        }

                        // 2. Create a pending transaction for the remaining balance
                        $remainingBalance = max(0, $totalPrice - $advancePaid);
                        if ($remainingBalance > 0) {
                            $invoice->transactions()->create([
                                'transaction_number' => 'TXN-' . strtoupper(uniqid()),
                                'payment_mode' => 'cash',
                                'amount' => $remainingBalance,
                                'status' => \App\Enums\TransactionStatusEnum::PENDING,
                            ]);
                            $invoice->update([
                                'status' => \App\Enums\InvoiceStatusEnum::GENERATED,
                                'paid_at' => null,
                            ]);
                        } else {
                            $invoice->update([
                                'status' => \App\Enums\InvoiceStatusEnum::PAID,
                                'paid_at' => now(),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::error('Invoice generate failed: ' . $e->getMessage());
                }

                $order->load(['items.garment', 'invoices.transactions']);
                return $order;
            });

            return $this->successResponse(new OrderResource($order), 'Order drafted', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /staff/appointments/{id}/charge-visit-only
    public function chargeVisitOnly(Request $request, $id)
    {
        try {
            $staffId = $request->user()->id;
            $appointment = Appointment::with('invoices.transactions')->where('id', $id)
                ->where('assigned_staff_id', $staffId)
                ->firstOrFail();

            $statusStr = is_object($appointment->status) ? $appointment->status->value : (string) $appointment->status;
            if ($statusStr === 'completed' || $statusStr === 'cancelled') {
                return $this->errorResponse('This appointment is already completed or cancelled.', 422);
            }

            $data = $request->validate([
                'payment_method' => 'required|string|in:cash,online,upi,card',
            ]);

            DB::transaction(function () use ($appointment, $data) {
                // Mark appointment as cancelled
                $appointment->update([
                    'status' => 'cancelled',
                    'ended_at' => now(),
                    'is_visited' => true,
                    'measurement_taken' => false,
                    'payment_status' => 'paid',
                ]);

                $visitChargeSetting = \App\Models\Setting::where('key', 'visit_charge')->first();
                $defaultVisitCharge = $visitChargeSetting ? (float)$visitChargeSetting->value : 200.00;
                $visitCharge = $appointment->visit_charge > 0 ? $appointment->visit_charge : $defaultVisitCharge;

                // Calculate how much was already paid online
                $alreadyPaidOnAppointment = 0;
                if ($appointment->invoices) {
                    foreach ($appointment->invoices as $apptInvoice) {
                        $alreadyPaidOnAppointment += $apptInvoice->transactions()
                            ->where('status', \App\Enums\TransactionStatusEnum::SUCCESSFUL)
                            ->sum('amount');
                    }
                }

                $invoice = $appointment->invoices()->first();
                if (!$invoice) {
                    $invoice = app(\App\Services\InvoiceService::class)->generateForAppointment($appointment);
                } else {
                    // Clear pending transactions since we are finalizing the visit charge
                    $invoice->transactions()->where('status', \App\Enums\TransactionStatusEnum::PENDING)->delete();
                }

                $amountToCollect = max(0, $visitCharge - $alreadyPaidOnAppointment);

                if ($amountToCollect > 0) {
                    $invoice->transactions()->create([
                        'transaction_number' => 'TXN-' . strtoupper(uniqid()),
                        'payment_mode' => $data['payment_method'],
                        'amount' => $amountToCollect,
                        'status' => \App\Enums\TransactionStatusEnum::SUCCESSFUL,
                    ]);
                }
                
                // Update the invoice to reflect only the visit charge
                $invoice->update([
                    'subtotal' => 0,
                    'gst_rate' => 0,
                    'gst_amount' => 0,
                    'visit_charge' => $visitCharge,
                    'advance_paid' => $alreadyPaidOnAppointment,
                    'total_amount' => $visitCharge,
                    'status' => \App\Enums\InvoiceStatusEnum::PAID,
                    'paid_at' => now(),
                ]);
            });

            return $this->successResponse(null, 'Visit charge collected and appointment cancelled', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // GET /staff/appointments/{id}
    public function show(Request $request, $id)
    {
        try {
            $staffId = $request->user()->id;

            $appointment = Appointment::with([
                'customer.userProfile',
                'items.garment.design',
                'items.garment.category',
                'uploads',
                'invoices.transactions',
            ])
                ->where('assigned_staff_id', $staffId)
                ->where('id', $id)
                ->firstOrFail();

            return $this->successResponse(new AppointmentResource($appointment), 'Appointment fetched');
        } catch (\Exception $e) {
            return $this->errorResponse('Appointment not found', 404);
        }
    }
}